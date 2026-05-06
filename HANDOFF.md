# HANDOFF — gov.cabnet.app Bolt → EDXEIX bridge

Current state after v4.2:

- Gmail/Bolt mail forwarding to `bolt-bridge@gov.cabnet.app` is working.
- Maildir scanner cron runs every 1 minute.
- Future guard is 2 minutes for near-real-time production intake.
- Mail intake parser imports Bolt `Ride details` emails into `bolt_mail_intake`.
- Past, expired, stale, and too-soon rows are blocked.
- Stale future candidates are automatically expired by cron.
- Synthetic mail test harness exists for payment-free testing.
- Mail Preflight can manually create local `normalized_bookings` rows from valid future candidates.
- v4.2 adds a dry-run evidence layer that records a local payload/mapping/safety snapshot in `bolt_mail_dry_run_evidence`.
- Live EDXEIX submission remains disabled.
- No live-submit POST path should be added unless Andreas explicitly requests it after real future-trip validation.

Primary safe URLs:

- `/ops/mail-status.php?key=...`
- `/ops/mail-intake.php?key=...`
- `/ops/mail-preflight.php?key=...`
- `/ops/mail-synthetic-test.php?key=...`
- `/ops/mail-dry-run-evidence.php?key=...`

Do not expose config secrets. Rotate the currently exposed internal key, DB password, and Bolt credentials before final live operation.
