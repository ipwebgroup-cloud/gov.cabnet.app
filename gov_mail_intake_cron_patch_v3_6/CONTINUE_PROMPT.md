Sophion, continue the gov.cabnet.app Bolt → EDXEIX bridge project.

Current state:
- Gmail filtered forwarding from `mykonoscab@gmail.com` to `bolt-bridge@gov.cabnet.app` is configured for Bolt `Ride details` emails.
- Webmail/Maildir delivery was confirmed.
- v3.5 mail intake patch is installed and SQL migration succeeded.
- `/ops/mail-intake.php` manually scanned the mailbox and imported 2 Bolt Ride details emails.
- Both test emails parsed successfully and were correctly marked `blocked_past` because the pickup times were historical.
- v3.6 adds a private CLI scanner for cron: `/home/cabnet/gov.cabnet.app_app/cli/import_bolt_mail.php`.
- Recommended cron: every 2 minutes, logging to `/home/cabnet/gov.cabnet.app_app/storage/logs/bolt_mail_intake.log`.
- Live EDXEIX submission remains disabled.

Next safest step:
- Install v3.6 CLI cron patch.
- Test the CLI scanner manually.
- Add the cPanel cron.
- Wait for the next real future Bolt pre-ride email.
- Confirm it imports as `future_candidate`.
- Review readiness/preflight only.

Do not enable live EDXEIX submission unless Andreas explicitly requests it after a real eligible future Bolt trip passes preflight.
