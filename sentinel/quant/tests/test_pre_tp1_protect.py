"""Tests for sentinel/quant/pre_tp1_protect.py.

Covers all three layers plus the state machine transitions.
Run with:  pytest sentinel/quant/tests/test_pre_tp1_protect.py -v
"""

import pytest
from sentinel.quant.pre_tp1_protect import (
    PreTP1Action,
    PreTP1Config,
    PreTP1Protector,
    PreTP1State,
)

# ---------------------------------------------------------------------------
# Fixtures
# ---------------------------------------------------------------------------

LONG = 1
SHORT = -1

ENTRY = 1.0000
TP1 = 1.1000       # +10% from entry
ATR = 0.0100       # 1% of entry, 1H ATR
STOP = 0.9700      # -3% structural stop


def make(cfg: PreTP1Config | None = None) -> PreTP1Protector:
    return PreTP1Protector(cfg or PreTP1Config())


def call(
    protector: PreTP1Protector,
    state: PreTP1State,
    price: float,
    threat: float = 0.30,
    atr: float = ATR,
    cvd_slope: float | None = None,
    now: float = 0.0,
) -> object:
    return protector.evaluate(
        state,
        side_sign=LONG,
        entry_price=ENTRY,
        tp1_price=TP1,
        stop_loss=STOP,
        current_price=price,
        atr=atr,
        threat_score=threat,
        cvd_slope=cvd_slope,
        now=now,
    )


# ---------------------------------------------------------------------------
# Layer 1: Zone Breakeven Rule
# ---------------------------------------------------------------------------

class TestZoneBreakeven:
    def test_no_action_below_zone(self):
        """Price below 80% of TP1 distance: HOLD."""
        prot = make()
        state = PreTP1State()
        # 80% threshold = 1.0 + 0.8 * 0.1 = 1.08
        result = call(prot, state, price=1.075)
        assert result.action is PreTP1Action.HOLD
        assert not state.breakeven_armed

    def test_move_stop_on_zone_entry(self):
        """Price crossing 80% zone fires MOVE_STOP exactly once."""
        prot = make()
        state = PreTP1State()

        # zone_entry = 1.0 + 0.8 * 0.1 = 1.08
        result = call(prot, state, price=1.082)

        assert result.action is PreTP1Action.MOVE_STOP
        assert result.new_stop is not None
        # Breakeven stop for LONG: entry + entry * fee_rate * fee_cover
        cfg = prot.cfg
        expected_stop = ENTRY + ENTRY * cfg.fee_rate * cfg.fee_cover
        assert abs(result.new_stop - expected_stop) < 1e-10
        assert state.breakeven_armed
        assert state.stop_moved_to_breakeven

    def test_stop_not_moved_twice(self):
        """MOVE_STOP fires once; subsequent ticks inside the zone do not re-fire."""
        prot = make()
        state = PreTP1State()

        result1 = call(prot, state, price=1.082)
        assert result1.action is PreTP1Action.MOVE_STOP

        result2 = call(prot, state, price=1.085)
        assert result2.action is not PreTP1Action.MOVE_STOP

    def test_stop_not_moved_when_already_better(self):
        """No amendment when the current stop is already above the breakeven level."""
        cfg = PreTP1Config(fee_rate=0.0008, fee_cover=2.0)
        prot = PreTP1Protector(cfg)
        state = PreTP1State()
        breakeven = ENTRY + ENTRY * cfg.fee_rate * cfg.fee_cover   # ~1.0016

        # Simulate position where stop is already at breakeven
        result = prot.evaluate(
            state,
            side_sign=LONG,
            entry_price=ENTRY,
            tp1_price=TP1,
            stop_loss=breakeven + 0.0050,  # already above breakeven
            current_price=1.082,
            atr=ATR,
            threat_score=0.30,
        )
        # Should still fire because stop_is_better check is separate;
        # the protector only skips if improvement < min_stop_improvement_atr.
        # With the stop already well above breakeven AND improvement < threshold,
        # it should HOLD or AMPLIFY, not MOVE_STOP.
        # (min_stop_improvement_atr=0.25 ATR = 0.0025; improvement would be
        # breakeven - stop = negative, so _stop_needs_moving returns False)
        assert result.action is not PreTP1Action.MOVE_STOP

    def test_breakeven_stop_is_above_entry_for_long(self):
        """Breakeven stop must always be > entry for a long."""
        prot = make()
        state = PreTP1State()
        result = call(prot, state, price=1.082)
        assert result.new_stop is not None
        assert result.new_stop > ENTRY

    def test_short_breakeven_stop_is_below_entry(self):
        """For a SHORT the breakeven stop must be below entry."""
        prot = make()
        state = PreTP1State()
        tp1_short = 0.90   # 10% below entry for a short

        result = prot.evaluate(
            state,
            side_sign=SHORT,
            entry_price=ENTRY,
            tp1_price=tp1_short,
            stop_loss=1.03,     # structural stop above entry for short
            current_price=0.918,  # 80% of the way down to tp1_short
            atr=ATR,
            threat_score=0.30,
        )
        assert result.action is PreTP1Action.MOVE_STOP
        assert result.new_stop is not None
        assert result.new_stop < ENTRY


# ---------------------------------------------------------------------------
# Layer 2: Multi-Touch Rejection Counter
# ---------------------------------------------------------------------------

class TestRejectionCounter:
    def _enter_and_exit_zone(
        self, prot: PreTP1Protector, state: PreTP1State,
        in_price: float, out_price: float, ts: float
    ) -> None:
        """Simulate one zone touch: enter, then retract."""
        call(prot, state, price=in_price, now=ts)
        call(prot, state, price=out_price, now=ts + 1.0)

    def test_first_rejection_counted(self):
        prot = make()
        state = PreTP1State()
        self._enter_and_exit_zone(prot, state, 1.082, 1.065, ts=0.0)
        # 1.082 -> 1.065 retraction = 0.017 > 1 ATR (0.01), counts
        assert state.rejection_count == 1

    def test_small_retraction_not_counted(self):
        """Retraction smaller than rejection_atr_mult * ATR is not a rejection."""
        prot = make()
        state = PreTP1State()
        self._enter_and_exit_zone(prot, state, 1.082, 1.079, ts=0.0)
        # retraction = 0.003 < 1 ATR (0.01), not counted
        assert state.rejection_count == 0

    def test_close_after_max_rejections(self):
        """After max_rejections, the next tick returns CLOSE."""
        cfg = PreTP1Config(max_rejections=2, rejection_cooldown_seconds=0.0)
        prot = PreTP1Protector(cfg)
        state = PreTP1State()

        # Rejection 1
        self._enter_and_exit_zone(prot, state, 1.082, 1.065, ts=0.0)
        # Rejection 2
        self._enter_and_exit_zone(prot, state, 1.082, 1.065, ts=61.0)

        # Next tick — still outside zone, but rejection_count == max_rejections
        result = call(prot, state, price=1.070, now=125.0)
        assert result.action is PreTP1Action.CLOSE
        assert state.rejection_count >= cfg.max_rejections

    def test_rejection_cooldown_prevents_double_counting(self):
        """Two exits within cooldown_seconds count as one rejection."""
        cfg = PreTP1Config(rejection_cooldown_seconds=60.0, max_rejections=3)
        prot = PreTP1Protector(cfg)
        state = PreTP1State()

        # Enter zone, exit — rejection 1
        call(prot, state, price=1.082, now=0.0)
        call(prot, state, price=1.065, now=1.0)
        count_after_1 = state.rejection_count

        # Immediately re-enter and exit — still within cooldown
        call(prot, state, price=1.082, now=30.0)
        call(prot, state, price=1.065, now=31.0)

        # Cooldown blocks the second count
        assert state.rejection_count == count_after_1

    def test_aster_usdt_scenario(self):
        """Reproduce the ASTER/USDT case: 3 zone touches → close on 3rd exit."""
        cfg = PreTP1Config(max_rejections=2, rejection_cooldown_seconds=0.0,
                           zone_frac=0.80)
        prot = PreTP1Protector(cfg)
        state = PreTP1State()

        entry, tp1 = 0.76708335, 0.757696 / 0.76708335 * 0.76708335
        # Using round numbers that match the pattern: entry ~0.7667, TP1 ~0.777
        entry, tp1, atr = 0.7667, 0.777, 0.009

        def ev(price, ts):
            return prot.evaluate(
                state,
                side_sign=LONG,
                entry_price=entry,
                tp1_price=tp1,
                stop_loss=entry - 1.17 * atr,
                current_price=price,
                atr=atr,
                threat_score=0.35,
                now=ts,
            )

        # zone_entry = 0.7667 + 0.8 * (0.777 - 0.7667) = 0.7667 + 0.00824 = 0.7749
        zone_entry = entry + 0.8 * (tp1 - entry)

        # Push 1: enters zone (breakeven fires), retracts 1.5 ATR
        # peak in zone = zone_entry + 0.001; must retract >= 1 ATR (0.009)
        # exit price = zone_entry + 0.001 - 1.5 * atr = zone_entry - 0.0125
        exit_1 = zone_entry + 0.001 - 1.5 * atr
        ev(zone_entry + 0.001, ts=0.0)
        result_1_exit = ev(exit_1, ts=60.0)           # retract 1.5 ATR > threshold
        assert state.rejection_count == 1

        # Push 2: re-enters zone, retracts again
        exit_2 = zone_entry + 0.002 - 1.5 * atr
        ev(zone_entry + 0.002, ts=120.0)
        result_2_exit = ev(exit_2, ts=180.0)
        assert state.rejection_count == 2

        # Push 3 (or any tick): CLOSE fires
        result_3 = ev(zone_entry + 0.001, ts=240.0)
        assert result_3.action is PreTP1Action.CLOSE


# ---------------------------------------------------------------------------
# Layer 3: Amplified Threat Scoring
# ---------------------------------------------------------------------------

class TestThreatAmplification:
    def test_no_amplification_below_zone(self):
        prot = make()
        state = PreTP1State()
        result = call(prot, state, price=1.075, threat=0.50)
        assert result.amplified_threat == pytest.approx(0.50)

    def test_amplification_at_zone_entry(self):
        """At zone entry (depth ≈ 0) amplification is minimal."""
        prot = make()
        state = PreTP1State()
        # zone_entry = 1.08; at exactly zone_entry, depth = 0 → amplifier^0 = 1
        result = call(prot, state, price=1.0801, threat=0.40)
        # depth is near zero so amplified ≈ 0.40
        assert result.amplified_threat == pytest.approx(0.40, abs=0.05)

    def test_amplification_at_zone_midpoint(self):
        """At 50% zone depth, threat is multiplied by amplifier^0.5."""
        cfg = PreTP1Config(zone_amplifier=2.5, zone_frac=0.80, zone_inner_frac=0.90)
        prot = PreTP1Protector(cfg)
        state = PreTP1State()

        # zone spans 1.08 to 1.10 (zone_frac=0.80, zone_inner_frac not limiting here)
        # midpoint at 1.09, depth = (1.09 - 1.08) / (1.10 - 1.08) = 0.5
        import math
        expected = min(cfg.max_amplified_threat, 0.40 * math.pow(2.5, 0.5))

        result = call(prot, state, price=1.09, threat=0.40)
        assert result.amplified_threat == pytest.approx(expected, rel=0.05)

    def test_amplification_capped(self):
        """Amplified threat never exceeds max_amplified_threat."""
        cfg = PreTP1Config(zone_amplifier=5.0, max_amplified_threat=1.0)
        prot = PreTP1Protector(cfg)
        state = PreTP1State()
        result = call(prot, state, price=1.099, threat=0.80)
        assert result.amplified_threat <= cfg.max_amplified_threat

    def test_cvd_gate_suppresses_amplification(self):
        """When amplify_only_on_cvd_divergence=True, positive CVD skips amplification."""
        cfg = PreTP1Config(amplify_only_on_cvd_divergence=True, zone_amplifier=4.0)
        prot = PreTP1Protector(cfg)
        state = PreTP1State()

        result_positive_cvd = call(prot, state, price=1.085, threat=0.40, cvd_slope=500.0)
        assert result_positive_cvd.amplified_threat == pytest.approx(0.40)

        state2 = PreTP1State()
        result_negative_cvd = call(prot, state2, price=1.085, threat=0.40, cvd_slope=-300.0)
        assert result_negative_cvd.amplified_threat > 0.40


# ---------------------------------------------------------------------------
# State machine
# ---------------------------------------------------------------------------

class TestStateMachine:
    def test_zone_entry_count_increments(self):
        prot = make()
        state = PreTP1State()
        call(prot, state, price=1.082)
        assert state.zone_entries == 1

    def test_zone_re_entry_increments_again(self):
        prot = make()
        state = PreTP1State()
        call(prot, state, price=1.082, now=0.0)    # enter
        call(prot, state, price=1.070, now=1.0)    # exit
        call(prot, state, price=1.083, now=61.0)   # re-enter
        assert state.zone_entries == 2

    def test_first_zone_entry_price_recorded(self):
        prot = make()
        state = PreTP1State()
        call(prot, state, price=1.082)
        assert state.first_zone_entry_price == pytest.approx(1.082)

    def test_bad_inputs_return_hold(self):
        prot = make()
        state = PreTP1State()
        result = prot.evaluate(
            state,
            side_sign=LONG,
            entry_price=0.0,   # invalid
            tp1_price=TP1,
            stop_loss=STOP,
            current_price=1.082,
            atr=ATR,
            threat_score=0.30,
        )
        assert result.action is PreTP1Action.HOLD

    def test_tp1_behind_entry_returns_hold(self):
        prot = make()
        state = PreTP1State()
        result = prot.evaluate(
            state,
            side_sign=LONG,
            entry_price=ENTRY,
            tp1_price=ENTRY - 0.01,  # wrong side
            stop_loss=STOP,
            current_price=1.082,
            atr=ATR,
            threat_score=0.30,
        )
        assert result.action is PreTP1Action.HOLD


# ---------------------------------------------------------------------------
# Config validation
# ---------------------------------------------------------------------------

class TestConfig:
    def test_invalid_zone_frac(self):
        with pytest.raises(ValueError, match="zone_frac"):
            PreTP1Config(zone_frac=1.5)

    def test_invalid_inner_frac(self):
        with pytest.raises(ValueError, match="zone_inner_frac"):
            PreTP1Config(zone_frac=0.80, zone_inner_frac=0.75)

    def test_defaults_are_valid(self):
        cfg = PreTP1Config()
        assert cfg.zone_frac == 0.80
        assert cfg.max_rejections == 2
