Sophion, continue the gov.cabnet.app Bolt → EDXEIX bridge project.

Current state:
- Live EDXEIX submission remains disabled.
- Gmail filtered forwarding to `bolt-bridge@gov.cabnet.app` is active.
- Maildir path `/home/cabnet/mail/gov.cabnet.app/bolt-bridge` is confirmed.
- `bolt_mail_intake` exists and parsed two forwarded Bolt Ride details emails.
- Existing rows are correctly `blocked_past`.
- CLI cron scanner runs every 2 minutes:
  `/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/import_bolt_mail.php --limit=250 --days=30`
- Cron log:
  `/home/cabnet/gov.cabnet.app_app/storage/logs/bolt_mail_intake.log`
- v3.7 added `/ops/mail-preflight.php` for manually converting only `future_candidate` rows into local normalized bookings for preflight only.
- v3.8 added `/ops/mail-status.php` as a read-only monitor.

Next safe step:
- Wait for a real future Bolt Ride details email.
- Confirm cron imports it as `future_candidate`.
- Use `/ops/mail-preflight.php?key=INTERNAL_KEY` to preview and manually create a local normalized booking only if mappings pass.
- Then review `/bolt_edxeix_preflight.php?limit=30`.

Do not create live EDXEIX submission code and do not enable live submission unless Andreas explicitly requests it after a real eligible future trip passes preflight.
