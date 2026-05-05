# HANDOFF — gov.cabnet.app Bolt → EDXEIX bridge

Current state after v3.6:
- Gmail filtered forwarding from `mykonoscab@gmail.com` to `bolt-bridge@gov.cabnet.app` was configured for Bolt `Ride details` emails.
- Roundcube/Webmail and server Maildir delivery were confirmed for `bolt-bridge@gov.cabnet.app`.
- v3.5 added the `bolt_mail_intake` table, Maildir scanner, Bolt pre-ride email parser, importer, and guarded Ops screen at `/ops/mail-intake.php`.
- phpMyAdmin migration succeeded and `bolt_mail_intake` exists.
- Manual scan imported 2 candidate emails with 2 inserted, 0 duplicates, 0 errors.
- Imported test rows parsed successfully and were correctly marked `blocked_past` because their pickup times were already historical.
- v3.6 adds private CLI/cron scanner `gov.cabnet.app_app/cli/import_bolt_mail.php` and cron examples.
- Live EDXEIX submission remains disabled.
- No live-submit handler should be added unless Andreas explicitly requests it after a real eligible future Bolt trip passes preflight.

Primary safe entry:
`https://gov.cabnet.app/ops/home.php`

Mail intake review:
`https://gov.cabnet.app/ops/mail-intake.php?key=SERVER_INTERNAL_KEY`

CLI intake scanner:
`/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/import_bolt_mail.php --limit=250 --days=30`

Recommended production cron:
`*/2 * * * * /usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/import_bolt_mail.php --limit=250 --days=30 >> /home/cabnet/gov.cabnet.app_app/storage/logs/bolt_mail_intake.log 2>&1`

Security note:
Rotate `app.internal_api_key` and DB password before/at go-live because sensitive values were exposed during deployment support.
