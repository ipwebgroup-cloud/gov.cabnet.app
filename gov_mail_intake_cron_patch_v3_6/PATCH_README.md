# gov.cabnet.app Patch v3.6 — Bolt Mail Intake CLI Cron

## What changed

Adds a private CLI scanner for automated cron-based import of Bolt `Ride details` emails from `bolt-bridge@gov.cabnet.app` into the existing `bolt_mail_intake` table.

## Files included

- `gov.cabnet.app_app/cli/import_bolt_mail.php`
- `gov.cabnet.app_app/cron/cron-examples-mail-intake.txt`
- `docs/BOLT_MAIL_INTAKE_CRON.md`
- `HANDOFF.md`
- `CONTINUE_PROMPT.md`
- `PATCH_README.md`

## Upload paths

```text
/home/cabnet/gov.cabnet.app_app/cli/import_bolt_mail.php
/home/cabnet/gov.cabnet.app_app/cron/cron-examples-mail-intake.txt
```

Docs/continuity files are for repository/project tracking.

## SQL

No SQL changes in v3.6.

Requires v3.5 SQL table:

```text
bolt_mail_intake
```

## Verify syntax

```bash
php -l /home/cabnet/gov.cabnet.app_app/cli/import_bolt_mail.php
```

## Manual CLI test

```bash
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/import_bolt_mail.php --limit=250 --days=30 --json
```

Expected after v3.5 test rows already imported:

```text
ok: true
duplicates: 2 or more
errors: 0
```

## Recommended cron

```cron
*/2 * * * * /usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/import_bolt_mail.php --limit=250 --days=30 >> /home/cabnet/gov.cabnet.app_app/storage/logs/bolt_mail_intake.log 2>&1
```

## Expected result

New Bolt pre-ride emails forwarded into `bolt-bridge@gov.cabnet.app` are imported automatically into `bolt_mail_intake`.

Historical/expired emails become `blocked_past`.
Future valid emails become `future_candidate`.

Live EDXEIX submission remains disabled.

## Git commit title

Add Bolt mail intake CLI cron scanner

## Git commit description

Adds a private CLI command and cron example for automatically scanning the bolt-bridge Maildir and importing Bolt Ride details emails into the local mail intake table. The command performs intake only and does not stage jobs or submit to EDXEIX.
