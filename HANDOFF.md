# gov.cabnet.app Bolt → EDXEIX Bridge — Handoff after v4.7

## Current state

The bridge is running in safe automated dry-run posture.

- Mail intake cron is active.
- Auto dry-run cron is active.
- Bolt driver directory sync cron is active.
- Driver email copy is validated with a real Bolt pre-ride email.
- Driver email routing is by Bolt driver identity/name/identifier, not by vehicle plate.
- Driver-facing copy formatting was updated in v4.5.3.
- Live EDXEIX submit remains OFF.
- submission_jobs remained 0 after driver-copy testing.
- submission_attempts remained 0 after driver-copy testing.

## v4.7 addition

Added:

```text
/ops/launch-readiness.php?key=INTERNAL_API_KEY
```

Purpose: read-only launch control panel for production hardening.

It checks:

- dry-run config
- live-submit disabled config
- guard minutes
- submission job/attempt counts
- driver notification state
- driver directory coverage
- cron log freshness
- latest intake rows
- latest driver notifications
- latest dry-run evidence
- schema readiness
- credential rotation as a manual gate

## Safety boundary

v4.7 does not enable live submission and does not create EDXEIX jobs or attempts.

Before any live-submit phase:

- rotate exposed ops key and credentials
- design v5 live-submit worker separately
- require explicit Andreas approval
- keep strict terminal/past/synthetic/test blocking
