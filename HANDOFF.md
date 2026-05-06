# HANDOFF — gov.cabnet.app Bolt → EDXEIX bridge

Current state after v4.1:
- Live EDXEIX submission remains disabled.
- Mail intake from `bolt-bridge@gov.cabnet.app` is active and scanned by cron every 1 minute.
- Future guard is configured to 2 minutes for near-real-time production intake behavior.
- `bolt_mail_intake` parser, status dashboard, preflight bridge, stale-candidate expiry, and synthetic mail test harness are installed.
- v4.0 added the synthetic Bolt `Ride details` test harness to avoid rider-app credit-card transactions during testing.
- v4.1 improves `/ops/mail-status.php` with clearer active/linked/synthetic/stale/submission safety visibility.
- Synthetic tests use `CABNET TEST DO NOT SUBMIT` and can be closed as `blocked_past`.
- No synthetic tool calls Bolt, calls EDXEIX, creates jobs, or submits live.

Primary safe entry points:
- `https://gov.cabnet.app/ops/mail-status.php?key=INTERNAL_KEY`
- `https://gov.cabnet.app/ops/mail-intake.php?key=INTERNAL_KEY`
- `https://gov.cabnet.app/ops/mail-preflight.php?key=INTERNAL_KEY`
- `https://gov.cabnet.app/ops/mail-synthetic-test.php?key=INTERNAL_KEY`

Expected clean state after synthetic cleanup:
- Active unlinked candidates: 0
- Open submission jobs: 0
- Stale open intake rows: 0
- Live submit: OFF

Do not enable live submission unless Andreas explicitly requests it after a real eligible future Bolt trip passes preflight and EDXEIX session/form access remains confirmed.
