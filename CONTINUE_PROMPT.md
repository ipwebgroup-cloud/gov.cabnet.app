Sophion, continue the gov.cabnet.app Bolt → EDXEIX bridge project.

Current state:
- Mail intake is active and near-real-time.
- Cron imports the `bolt-bridge@gov.cabnet.app` Maildir every 1 minute.
- Future guard is 2 minutes.
- Stale future candidates auto-expire.
- Synthetic payment-free testing works.
- Mail Preflight can create local `source='bolt_mail'` normalized bookings manually.
- v4.2 adds a dry-run evidence page/table for recording local payload/mapping/safety snapshots without creating submission jobs.
- Live EDXEIX submission remains disabled.

Continue safely. Do not enable live EDXEIX submission unless Andreas explicitly asks for a live-submit update after a real eligible future trip passes preflight and dry-run evidence.
