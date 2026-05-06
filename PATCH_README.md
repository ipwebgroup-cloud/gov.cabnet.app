# gov.cabnet.app Patch v4.2 — Bolt Mail Dry-run Evidence

## What changed

Adds a local dry-run evidence layer for Bolt mail preflight bookings.

## Files included

- `gov.cabnet.app_app/src/Mail/BoltMailDryRunEvidenceService.php`
- `public_html/gov.cabnet.app/ops/mail-dry-run-evidence.php`
- `gov.cabnet.app_sql/2026_05_06_bolt_mail_dry_run_evidence.sql`
- `docs/BOLT_MAIL_DRY_RUN_EVIDENCE.md`
- `HANDOFF.md`
- `CONTINUE_PROMPT.md`
- `PATCH_README.md`

## Upload paths

- `/home/cabnet/gov.cabnet.app_app/src/Mail/BoltMailDryRunEvidenceService.php`
- `/home/cabnet/public_html/gov.cabnet.app/ops/mail-dry-run-evidence.php`
- `/home/cabnet/gov.cabnet.app_sql/2026_05_06_bolt_mail_dry_run_evidence.sql`

## SQL

```bash
DB_NAME=$(php -r '$c=require "/home/cabnet/gov.cabnet.app_config/config.php"; echo $c["db"]["database"];')
mysql "$DB_NAME" < /home/cabnet/gov.cabnet.app_sql/2026_05_06_bolt_mail_dry_run_evidence.sql
```

## Verify syntax

```bash
php -l /home/cabnet/gov.cabnet.app_app/src/Mail/BoltMailDryRunEvidenceService.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/mail-dry-run-evidence.php
```

## Safety

No Bolt call, no EDXEIX call, no submission job, no live submit.
