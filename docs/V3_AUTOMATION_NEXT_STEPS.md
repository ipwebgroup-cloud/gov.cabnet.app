# V3 Automation Next Steps

Current state after v3.0.63:

- V3 readiness pipeline proven.
- Payload audit proven.
- Package export proven.
- Operator approval workflow proven.
- Final rehearsal accepts approval and blocks only on master gate.
- Adapter skeleton and contract probe proven.
- Kill-switch checker aligned with approval logic.
- Pre-live switchboard added.
- Live submit remains disabled.
- V0 laptop/manual helper remains untouched.

## Next safe phase

`v3.0.64` should be a checkpoint package preserving the pre-live switchboard proof.

After that, the next implementation phase can begin planning the real adapter internals behind disabled config. Do not enable live submit until Andreas explicitly requests a live-submit gate-opening update.
