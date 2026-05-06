# HANDOFF — gov.cabnet.app Bolt → EDXEIX bridge

Current state after v4.0:
- Live EDXEIX submission remains disabled.
- Mail intake from `bolt-bridge@gov.cabnet.app` is active and scanned by cron every 1 minute.
- Future guard is configured to 2 minutes for near-real-time production intake behavior.
- `bolt_mail_intake` parser, status dashboard, preflight bridge, and stale-candidate expiry are installed.
- v4.0 adds a synthetic Bolt `Ride details` test harness to avoid rider-app credit-card transactions during testing.
- Synthetic tests use `CABNET TEST DO NOT SUBMIT` and can be closed as `blocked_past`.
- No synthetic tool calls Bolt, calls EDXEIX, creates jobs, or submits live.

Primary safe entry points:
- `https://gov.cabnet.app/ops/mail-status.php?key=INTERNAL_KEY`
- `https://gov.cabnet.app/ops/mail-intake.php?key=INTERNAL_KEY`
- `https://gov.cabnet.app/ops/mail-preflight.php?key=INTERNAL_KEY`
- `https://gov.cabnet.app/ops/mail-synthetic-test.php?key=INTERNAL_KEY`

Do not enable live submission unless Andreas explicitly requests it after a real eligible future Bolt trip passes preflight and EDXEIX session/form access remains confirmed.
