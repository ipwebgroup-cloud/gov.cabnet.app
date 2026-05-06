Continue gov.cabnet.app Bolt → EDXEIX bridge from v4.3.

Project constraints:
- Plain PHP, mysqli/MariaDB, cPanel/manual upload.
- No frameworks, Composer, Node, or heavy dependencies.
- Live EDXEIX submit must remain OFF unless Andreas explicitly approves live-submit work.

Current state:
- Mail intake is working via `bolt-bridge@gov.cabnet.app` Maildir.
- Cron imports mail every minute.
- Future guard is 2 minutes.
- Stale candidates expire automatically.
- Synthetic test harness works.
- Manual Mail Preflight local booking creation works.
- Dry-run evidence table/page exists from v4.2.
- v4.3 adds `auto_bolt_mail_dry_run.php` and `/ops/mail-auto-dry-run.php` to auto-create local preflight bookings and record dry-run evidence only.

Next action:
- Verify v4.3 upload and syntax.
- Run `/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/auto_bolt_mail_dry_run.php --preview-only --json`.
- Create a synthetic future email and run the worker.
- Confirm evidence exists and `submission_jobs` / `submission_attempts` remain empty.
