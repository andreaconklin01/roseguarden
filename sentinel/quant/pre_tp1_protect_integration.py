"""Integration reference for pre_tp1_protect.py.

This file is NOT imported anywhere — it is a drop-in reference showing
exactly which lines to add/change in stage3_monitor.py and config.py.
Delete it after integration is done.

=============================================================================
A) config.py  — add PreTP1Config and wire it into MonitorConfig
=============================================================================

1. Import (at the top of config.py, near other dataclass imports):
   The class lives in sentinel/quant/pre_tp1_protect.py so it must be
   imported lazily to avoid a circular import — or just defined inline in
   config.py (copy the dataclass from pre_tp1_protect.py).

   Easiest: copy the PreTP1Config dataclass into config.py immediately
   above MonitorConfig, then add one field to MonitorConfig:

       protect_pre_tp1: PreTP1Config = field(default_factory=PreTP1Config)

   YAML key: ``monitor.protect_pre_tp1.zone_frac: 0.85`` etc.


=============================================================================
B) stage3_monitor.py — three insertion points
=============================================================================
"""

# --------------------------------------------------------------------------
# B-1. Imports (top of file, alongside the existing quant imports)
# --------------------------------------------------------------------------

from sentinel.quant.pre_tp1_protect import (   # noqa: F401 (reference only)
    PreTP1Config,
    PreTP1Protector,
    PreTP1State,
    PreTP1Action,
    PreTP1Result,
    make_protector,
)


# --------------------------------------------------------------------------
# B-2. TrackedPosition — add one field
# --------------------------------------------------------------------------
#
# Inside the TrackedPosition dataclass, add:
#
#   pre_tp1_state: PreTP1State = field(default_factory=PreTP1State)
#
# This keeps all per-position state local to the tracked object, exactly
# as position, last_decision, etc. already are.


# --------------------------------------------------------------------------
# B-3. PositionMonitor.__init__ — build the protector once
# --------------------------------------------------------------------------
#
# After  ``self._tracked: dict[str, TrackedPosition] = {}``  add:
#
#   self._pre_tp1 = make_protector(
#       getattr(cfg.monitor, "protect_pre_tp1", None)
#   )


# --------------------------------------------------------------------------
# B-4. _check_one — call the protector before the normal evaluate block
# --------------------------------------------------------------------------
#
# Replace the current block that calls evaluate_position and then dispatches
# on decision.action with the version below.  The only additions are the
# five lines that run the Pre-TP1 check before the normal logic.
#
# Insertion point: AFTER ``tracked.errors = 0`` and BEFORE the
# ``decision = evaluate_position(...)`` call.

async def _check_one_with_pre_tp1(self, tracked, snap, mark, time_now):  # type: ignore[override]
    """Drop-in replacement body for the guard block in _check_one.

    In the real file, copy only the three pre_tp1 lines and the
    ``current_threat`` update — the surrounding code stays exactly as-is.
    """
    pos = tracked.position

    # ---- Normal evaluate_position (unchanged) --------------------------
    from sentinel.quant.monitor import evaluate_position, Action   # noqa
    decision = evaluate_position(pos, snap, self.cfg.monitor, now=time_now)
    tracked.last_decision = decision
    pos.peak_roe = decision.peak_roe
    current_threat = decision.threat_score

    # ---- Pre-TP1 protection (NEW — insert these lines here) -----------
    tp1 = pos.take_profits[0] if pos.take_profits else None
    atr = getattr(snap, "atr_14", None) or getattr(snap, "atr", None)
    cvd_slope = getattr(snap, "cvd_slope", None)

    if tp1 is not None and atr and atr > 0 and not pos.tp1_filled:
        pre_result = self._pre_tp1.evaluate(
            tracked.pre_tp1_state,
            side_sign=pos.side.sign,
            entry_price=pos.entry,
            tp1_price=tp1,
            stop_loss=pos.stop_loss,
            current_price=mark,
            atr=atr,
            threat_score=current_threat,
            cvd_slope=cvd_slope,
            now=time_now,
        )

        if pre_result.action is PreTP1Action.CLOSE:
            from sentinel.models import ExitReason              # noqa
            await self._close(
                tracked, ExitReason.EMERGENCY_AI_CLOSE, 1.0,
                pre_result.reason, mark=mark,
            )
            # Journal the zone state for post-trade review
            if tracked.trade_id:
                await self.journal.record_event(
                    tracked.trade_id, "PRE_TP1_CLOSE",
                    mark=mark, roe=decision.roe,
                    threat=pre_result.amplified_threat,
                    detail=pre_result.reason,
                )
            return

        if pre_result.action is PreTP1Action.MOVE_STOP:
            new_stop = pre_result.new_stop
            if new_stop is not None and pos.stop_is_better(new_stop):
                try:
                    await self._dispatch_move_stop(pos, new_stop)
                    pos.stop_loss = new_stop
                    pos.trailed_stop = new_stop
                except Exception as exc:               # noqa: BLE001
                    import logging
                    logging.getLogger("sentinel.stage3").warning(
                        "pre-tp1 stop move failed on %s: %s", pos.symbol, exc
                    )
                if tracked.trade_id:
                    await self.journal.record_event(
                        tracked.trade_id, "PRE_TP1_BREAKEVEN",
                        mark=mark, roe=decision.roe,
                        threat=pre_result.amplified_threat,
                        detail=pre_result.reason,
                    )

        # Always substitute the (possibly amplified) threat so the normal
        # emergency_threat_threshold check below uses the zone-aware value.
        current_threat = pre_result.amplified_threat

    # ---- Rest of _check_one — unchanged --------------------------------
    # The decision object still holds the original (unamplified) threat,
    # which is fine for logging.  The amplified current_threat is used
    # only for the emergency close check below.

    if decision.action is Action.HOLD:
        return

    # ... rest of the existing dispatch (ARM, TRAIL_STOP, PROTECT_CLOSE,
    # banks_a_rung, is_exit) continues unchanged, but for the emergency
    # close condition replace ``decision.threat_score`` with
    # ``current_threat`` in whatever comparison the caller makes.
