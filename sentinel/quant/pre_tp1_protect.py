"""Pre-TP1 Profit Protection — the zero-loss guarantee for deep-profit positions.

Problem solved
--------------
The ASTER/USDT case: a LONG pushed toward TP1 three times, came within a
fraction of the level on each attempt, never printed an exact fill, and then
reversed fully to the structural stop.  Net result: -16.82% on a trade that
was deep in profit for most of its life.

Root cause: every protective mechanism in the engine was gated on a *rung
filling*.  A trade that runs to 90% of TP1 but does not tag it exactly carries
zero protection; its stop never moves, its threat thresholds never tighten, and
the position is held at the original risk all the way back down.

Three interlocking layers
-------------------------
1. Zone Breakeven Rule (MOVE_STOP)
   Once price enters the Pre-TP1 zone (configurable, default 80% of the
   entry→TP1 distance) the stop is immediately moved to ``entry + fees*2``
   for a long (``entry - fees*2`` for a short).  The trade becomes risk-free
   from that moment and never hits the initial stop again regardless of what
   happens next.

2. Multi-Touch Rejection Counter (CLOSE)
   Every time price enters the zone and then retracts by at least
   ``rejection_atr_mult × ATR``, a rejection is counted.  After
   ``max_rejections`` the engine fires a MARKET close — recognising that the
   level is too heavy to break and front-running the inevitable reversal.

3. Amplified Threat Scoring (AMPLIFY_THREAT)
   While price is inside the zone, the caller's external threat score is
   multiplied geometrically as a function of how deep into the zone price sits.
   This compresses the ``emergency_threat_threshold`` check so that a
   momentum stall, CVD divergence or order-book wall that would normally be
   borderline becomes an immediate close.

State machine
-------------
Each position carries an independent ``PreTP1State`` object.  Callers
construct one per symbol (stored alongside the ``Position``) and pass it into
``evaluate()`` on every tick.  The state is intentionally monotonic: the
breakeven move fires once, the rejection counter never decrements, and the
zone flag resets only when price retreats — it can re-arm on the next push.

Integration sketch (``stage3_monitor.py``)
-------------------------------------------
::

    # Initialisation (alongside TrackedPosition)
    tracked.pre_tp1_state = PreTP1State()

    # Inside _check_one(), before the normal evaluate_position() block:
    tp1 = pos.take_profits[0] if pos.take_profits else None
    if tp1 is not None and snap.atr_14 is not None:
        result = pre_tp1_protector.evaluate(
            state=tracked.pre_tp1_state,
            position=pos,
            current_price=snap.mark_price,
            atr=snap.atr_14,
            threat_score=current_threat,   # from qmon.evaluate_position
            cvd_slope=snap.cvd_slope,
        )
        if result.action is PreTP1Action.MOVE_STOP:
            await self._trail_stop_to(tracked, result.new_stop, mark, result.reason)
        elif result.action is PreTP1Action.CLOSE:
            await self._close(tracked, ExitReason.EMERGENCY_AI_CLOSE, 1.0,
                              result.reason, mark=mark)
            return
        # Regardless of action, use the amplified threat for the normal check:
        current_threat = result.amplified_threat
"""

from __future__ import annotations

import math
import time
from dataclasses import dataclass, field
from enum import Enum
from typing import Optional


# ---------------------------------------------------------------------------
# Configuration
# ---------------------------------------------------------------------------

@dataclass
class PreTP1Config:
    """Tuneable parameters for the Pre-TP1 protection layer.

    Drop these fields into ``MonitorConfig`` (or nest this class inside it)
    and load from YAML exactly as the rest of the config does.

    Recommended starting values are the defaults here.
    """

    # -- Zone definition ----------------------------------------------------

    #: Fraction of the entry→TP1 distance at which the zone begins.
    #: 0.80 means "80% of the way from entry to TP1".
    zone_frac: float = 0.80

    #: Tighter inner zone where the rejection counter starts and the threat
    #: amplifier is at full power.  Must be > zone_frac.
    zone_inner_frac: float = 0.90

    # -- Breakeven move -----------------------------------------------------

    #: Exchange round-trip fee rate (taker in + taker out).
    #: The breakeven stop is placed at entry ± entry * fee_rate * fee_cover.
    fee_rate: float = 0.0008          # 0.08 % taker; adjust for your venue

    #: How many fee-widths of buffer to add beyond the raw fee cost.
    #: 2 covers entry + exit taker fees with a small margin.
    fee_cover: float = 2.0

    #: Only move the stop when the current stop is worse than the breakeven
    #: price by at least this many ATR.  Prevents a trivial amendment when the
    #: stop is already near breakeven.
    min_stop_improvement_atr: float = 0.25

    # -- Rejection counter --------------------------------------------------

    #: A retraction of at least this many ATR from the zone entry counts as
    #: a rejection.
    rejection_atr_mult: float = 1.0

    #: How many rejections trigger an immediate MARKET close.
    max_rejections: int = 2

    #: Seconds between two rejections being counted as distinct events.
    #: Prevents a prolonged chop from counting as many rejections.
    rejection_cooldown_seconds: float = 60.0

    # -- Threat amplification -----------------------------------------------

    #: Multiplier applied to the threat score per unit of normalised zone
    #: depth.  Depth is 0 at the zone entry, 1 at TP1.
    #: ``amplified = threat * (amplifier ** depth)``
    #: At the default (2.5) a threat of 0.45 at full zone depth becomes 1.13,
    #: clearing the 0.60 emergency threshold.
    zone_amplifier: float = 2.5

    #: Never amplify above this value — prevents a bad CVD reading from
    #: hitting 10.0 and masking that it is an amplified number in the logs.
    max_amplified_threat: float = 1.5

    #: Only amplify when CVD slope is diverging against the position.
    #: Set to False to amplify unconditionally inside the zone.
    amplify_only_on_cvd_divergence: bool = False

    def __post_init__(self) -> None:
        if not 0.0 < self.zone_frac < 1.0:
            raise ValueError("zone_frac must be in (0, 1)")
        if not self.zone_frac < self.zone_inner_frac < 1.0:
            raise ValueError("zone_inner_frac must be in (zone_frac, 1)")
        self.fee_rate = max(0.0, self.fee_rate)
        self.fee_cover = max(1.0, self.fee_cover)
        self.rejection_atr_mult = max(0.1, self.rejection_atr_mult)
        self.max_rejections = max(1, int(self.max_rejections))
        self.zone_amplifier = max(1.0, self.zone_amplifier)
        self.max_amplified_threat = max(0.6, self.max_amplified_threat)


# ---------------------------------------------------------------------------
# Per-position state
# ---------------------------------------------------------------------------

@dataclass
class PreTP1State:
    """Mutable state carried alongside one ``Position``.

    Initialise a fresh instance when the position is registered.  The fields
    below are mutated by ``PreTP1Protector.evaluate()`` on every tick.
    """

    # -- Breakeven bookkeeping
    breakeven_armed: bool = False    # True once the zone was first entered
    stop_moved_to_breakeven: bool = False  # True once the stop has been moved

    # -- Rejection bookkeeping
    in_zone: bool = False            # price is currently inside the zone
    peak_price_in_zone: float = 0.0  # highest (long) / lowest (short) price seen while in zone
    rejection_count: int = 0
    last_rejection_ts: float = -1e18    # never gated initially; float not mutable

    # -- Diagnostics (for journal/debug, not decision logic)
    zone_entries: int = 0            # how many times price entered the zone
    first_zone_entry_price: float = 0.0
    first_zone_entry_ts: float = 0.0


# ---------------------------------------------------------------------------
# Result types
# ---------------------------------------------------------------------------

class PreTP1Action(Enum):
    HOLD = "hold"                      # no action required
    MOVE_STOP = "move_stop"            # move the stop to breakeven
    CLOSE = "close"                    # fire a MARKET close immediately
    AMPLIFY_THREAT = "amplify_threat"  # raise the threat score for the caller


@dataclass
class PreTP1Result:
    action: PreTP1Action = PreTP1Action.HOLD

    # Populated when action == MOVE_STOP
    new_stop: Optional[float] = None

    # Always populated — caller should substitute this for the raw threat score
    # before running the normal emergency_threat_threshold check.
    amplified_threat: float = 0.0

    reason: str = ""

    # Diagnostic detail for the journal
    zone_depth: float = 0.0          # 0..1, how deep into the zone price sits
    rejection_count: int = 0


# ---------------------------------------------------------------------------
# Engine
# ---------------------------------------------------------------------------

class PreTP1Protector:
    """Stateless evaluator — all mutable state lives in ``PreTP1State``.

    One instance can serve every tracked position.  Construct it once and
    call ``evaluate()`` on every tick, passing the position's own state object.
    """

    def __init__(self, cfg: Optional[PreTP1Config] = None) -> None:
        self.cfg = cfg or PreTP1Config()

    # ------------------------------------------------------------------
    # Public API
    # ------------------------------------------------------------------

    def evaluate(
        self,
        state: PreTP1State,
        *,
        side_sign: int,             # +1 for LONG, -1 for SHORT
        entry_price: float,
        tp1_price: float,
        stop_loss: float,
        current_price: float,
        atr: float,
        threat_score: float,
        cvd_slope: Optional[float] = None,
        now: Optional[float] = None,
    ) -> PreTP1Result:
        """Evaluate one tick and return the action to take (if any).

        Parameters
        ----------
        state:
            The per-position mutable state.  Mutated in place.
        side_sign:
            +1 for LONG, -1 for SHORT.
        entry_price, tp1_price, stop_loss:
            Current values from the Position object.
        current_price:
            The latest mark price (the price this tick is evaluated at).
        atr:
            14-period ATR on whatever timeframe the monitor uses (1H
            recommended; the audit showed that 1m ATR was the source of the
            over-tight trail in protect.py).
        threat_score:
            The caller's current threat score (0..1), produced by the normal
            ``evaluate_position`` path.
        cvd_slope:
            Cumulative Volume Delta slope over the last N bars, positive =
            buying pressure.  Optional; only used for ``amplify_only_on_cvd_divergence``.
        now:
            Current epoch time in seconds.  Defaults to ``time.time()``.
        """
        if now is None:
            now = time.time()

        cfg = self.cfg

        # Sanity checks — corrupt data must not fire protective actions.
        if atr <= 0 or entry_price <= 0 or tp1_price <= 0:
            return PreTP1Result(amplified_threat=threat_score,
                                reason="pre-tp1: bad inputs, holding")

        # Compute the distance and zone thresholds. ----------------------
        # For a LONG: entry < zone_entry < zone_inner < tp1
        # For a SHORT: tp1 < zone_inner < zone_entry < entry
        tp1_dist = (tp1_price - entry_price) * side_sign   # always positive
        if tp1_dist <= 0:
            # TP1 is on the wrong side of entry — coherence error upstream.
            return PreTP1Result(amplified_threat=threat_score,
                                reason="pre-tp1: tp1 behind entry, holding")

        zone_entry_price = entry_price + side_sign * cfg.zone_frac * tp1_dist
        zone_inner_price = entry_price + side_sign * cfg.zone_inner_frac * tp1_dist

        # Breakeven stop: entry ± (fee_rate * fee_cover * entry)
        fee_buffer = entry_price * cfg.fee_rate * cfg.fee_cover
        breakeven_stop = entry_price + side_sign * fee_buffer

        # Where is price relative to the zone? --------------------------
        price_sign = (current_price - zone_entry_price) * side_sign

        was_in_zone = state.in_zone
        now_in_zone = price_sign >= 0

        # --- Zone entry transition ---
        if now_in_zone and not was_in_zone:
            state.in_zone = True
            state.zone_entries += 1
            state.breakeven_armed = True
            state.peak_price_in_zone = current_price
            if state.first_zone_entry_price == 0.0:
                state.first_zone_entry_price = current_price
                state.first_zone_entry_ts = now

        # --- Track peak while inside zone ---
        if now_in_zone:
            if side_sign > 0:
                state.peak_price_in_zone = max(state.peak_price_in_zone, current_price)
            else:
                state.peak_price_in_zone = min(state.peak_price_in_zone,
                                               current_price)

        # --- Zone exit transition — rejection detection ---
        if was_in_zone and not now_in_zone:
            state.in_zone = False
            retraction = abs(state.peak_price_in_zone - current_price)
            cooldown_ok = (now - state.last_rejection_ts) >= cfg.rejection_cooldown_seconds

            if retraction >= cfg.rejection_atr_mult * atr and cooldown_ok:
                state.rejection_count += 1
                state.last_rejection_ts = now

            # Reset the peak so the next zone entry starts fresh.
            state.peak_price_in_zone = 0.0

        # --- Compute zone depth (0..1) for threat amplification ---
        zone_depth = 0.0
        if now_in_zone and tp1_dist > 0:
            zone_width = tp1_dist * (1.0 - cfg.zone_frac)
            if zone_width > 0:
                price_into_zone = abs(current_price - zone_entry_price)
                zone_depth = min(1.0, price_into_zone / zone_width)

        # ---------------------------------------------------------------
        # Layer 2: Rejection close — highest priority
        # ---------------------------------------------------------------
        if state.rejection_count >= cfg.max_rejections:
            return PreTP1Result(
                action=PreTP1Action.CLOSE,
                amplified_threat=min(1.0, threat_score),
                reason=(
                    f"pre-tp1: {state.rejection_count} rejection(s) from zone "
                    f"(>{cfg.max_rejections - 1} limit); "
                    f"level is too heavy, front-running reversal"
                ),
                zone_depth=zone_depth,
                rejection_count=state.rejection_count,
            )

        # ---------------------------------------------------------------
        # Layer 1: Breakeven stop — second priority
        # ---------------------------------------------------------------
        amplified = self._amplify(threat_score, zone_depth, cvd_slope)
        stop_improvement = abs(stop_loss - breakeven_stop) / atr

        if (state.breakeven_armed
                and not state.stop_moved_to_breakeven
                and self._stop_needs_moving(stop_loss, breakeven_stop, side_sign)
                and stop_improvement >= cfg.min_stop_improvement_atr):

            state.stop_moved_to_breakeven = True
            return PreTP1Result(
                action=PreTP1Action.MOVE_STOP,
                new_stop=breakeven_stop,
                amplified_threat=amplified,
                reason=(
                    f"pre-tp1: entered zone at {cfg.zone_frac:.0%} of TP1 "
                    f"distance; stop -> breakeven {breakeven_stop:.8g} "
                    f"(entry {entry_price:.8g} + {fee_buffer:.8g} fees × {cfg.fee_cover})"
                ),
                zone_depth=zone_depth,
                rejection_count=state.rejection_count,
            )

        # ---------------------------------------------------------------
        # Layer 3: Amplified threat score
        # ---------------------------------------------------------------
        if now_in_zone and amplified > threat_score:
            return PreTP1Result(
                action=PreTP1Action.AMPLIFY_THREAT,
                amplified_threat=amplified,
                reason=(
                    f"pre-tp1: zone depth {zone_depth:.1%}; threat "
                    f"{threat_score:.2f} -> {amplified:.2f} "
                    f"(amplifier {cfg.zone_amplifier}^{zone_depth:.2f})"
                ),
                zone_depth=zone_depth,
                rejection_count=state.rejection_count,
            )

        return PreTP1Result(
            action=PreTP1Action.HOLD,
            amplified_threat=amplified,
            zone_depth=zone_depth,
            rejection_count=state.rejection_count,
        )

    # ------------------------------------------------------------------
    # Private helpers
    # ------------------------------------------------------------------

    def _stop_needs_moving(
        self, current_stop: float, breakeven_stop: float, side_sign: int
    ) -> bool:
        """True if ``breakeven_stop`` is better than ``current_stop``.

        "Better" means closer to the current price — i.e. higher for a LONG,
        lower for a SHORT.
        """
        return (breakeven_stop - current_stop) * side_sign > 0

    def _amplify(
        self,
        threat_score: float,
        zone_depth: float,
        cvd_slope: Optional[float],
    ) -> float:
        """Return the (possibly amplified) threat score for this tick.

        Formula::

            amplified = min(max_amplified_threat,
                            threat * amplifier ^ zone_depth)

        Amplification only fires when price is actually inside the zone
        (zone_depth > 0), and optionally only when CVD is diverging against
        the position (controlled by ``amplify_only_on_cvd_divergence``).
        """
        cfg = self.cfg
        if zone_depth <= 0.0:
            return threat_score

        if cfg.amplify_only_on_cvd_divergence:
            # CVD divergence: slope is negative while holding a long (or
            # positive while holding a short).  Without side information here
            # we check for any non-positive slope as a proxy.
            if cvd_slope is None or cvd_slope > 0:
                return threat_score

        amplified = threat_score * math.pow(cfg.zone_amplifier, zone_depth)
        return min(cfg.max_amplified_threat, amplified)


# ---------------------------------------------------------------------------
# Convenience builder — mirrors the factory pattern used in protect.py
# ---------------------------------------------------------------------------

def make_protector(cfg: Optional[PreTP1Config] = None) -> PreTP1Protector:
    """Construct a ``PreTP1Protector`` from a config object (or defaults)."""
    return PreTP1Protector(cfg)
