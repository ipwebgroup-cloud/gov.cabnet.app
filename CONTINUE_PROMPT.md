Continue the gov.cabnet.app Bolt → EDXEIX V3 automation project from v3.0.61.

Current focus: V3 closed-gate live adapter preparation.

Verified so far:
- V3 readiness path reached `live_submit_ready`.
- Payload audit passed.
- Package export passed.
- Operator approval workflow passed with row 418.
- Final rehearsal blocked only by master gate.
- Closed-gate diagnostics passed as expected.
- Adapter skeleton and contract probe passed.

Latest issue/fix:
- v3.0.60 live adapter kill-switch checker failed because `SHOW TABLES LIKE ?` caused a MariaDB syntax error.
- v3.0.61 fixes the checker by using `INFORMATION_SCHEMA.TABLES`.

Hard rules:
- Do not touch V0 laptop/manual helper.
- Do not enable live-submit unless Andreas explicitly asks.
- Do not call EDXEIX live.
- Do not call AADE.
- Keep work V3-only, closed-gate, read-only unless explicitly testing V3 approval table writes.
