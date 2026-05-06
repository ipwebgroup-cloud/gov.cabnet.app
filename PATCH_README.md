# gov.cabnet.app patch v3.9 — Mail intake stale candidate expiry

## What changed

Adds automatic stale-row expiry to the private Bolt mail intake cron command.

## Files included

- `gov.cabnet.app_app/src/Mail/BoltMailIntakeMaintenance.php`
- `gov.cabnet.app_app/cli/import_bolt_mail.php`
- `docs/BOLT_MAIL_INTAKE_EXPIRY.md`
- `HANDOFF.md`
- `CONTINUE_PROMPT.md`

## Upload paths

- `/home/cabnet/gov.cabnet.app_app/src/Mail/BoltMailIntakeMaintenance.php`
- `/home/cabnet/gov.cabnet.app_app/cli/import_bolt_mail.php`

## SQL

No SQL migration is required.

## Verify

```bash
php -l /home/cabnet/gov.cabnet.app_app/src/Mail/BoltMailIntakeMaintenance.php
php -l /home/cabnet/gov.cabnet.app_app/cli/import_bolt_mail.php
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/import_bolt_mail.php --limit=250 --days=30 --json
```

Expected JSON includes `expired_open_rows`.

## Safety

No EDXEIX jobs are created. No live submit is performed.
