# Safe Handoff Package Privacy Hardening — 2026-05-17

## Purpose

This patch tightens safe handoff package generation after the 2026-05-17 audit ZIP showed that private audit packages can include material that must not be committed to GitHub.

## What changed

- `DATABASE_EXPORT.sql` is ignored at repository root.
- Runtime lock files under `gov.cabnet.app_app/storage/locks/` are ignored and excluded from generated packages.
- Receipt attachment PDFs under `gov.cabnet.app_app/storage/receipt_attachments/` are ignored and excluded from generated packages.
- cPanel backup/broken filenames such as `.broken_YYYYMMDD`, `.parse_broken_*`, `.parse_error_guard_backup_*`, `.noop_backup_*`, and `.before_*` are ignored and excluded from generated packages.
- `SafeHandoffPackageBuilder` now excludes database exports by default unless `include_database` is explicitly true.
- CLI package generation is DB-free by default; database audit export now requires `--include-db`.
- `SafeHandoffPackageValidator` now flags runtime locks, receipt attachments, storage artifacts, backup/broken files, and accidental database exports as dangerous/suspicious package entries.

## Safety posture

This patch does not call Bolt, EDXEIX, or AADE. It does not alter live-submit gates, queue rows, mapping rows, receipt rows, or production data.

## Verification

Run after upload:

```bash
php -l /home/cabnet/gov.cabnet.app_app/src/Support/SafeHandoffPackageBuilder.php
php -l /home/cabnet/gov.cabnet.app_app/src/Support/SafeHandoffPackageValidator.php
php -l /home/cabnet/gov.cabnet.app_app/cli/build_safe_handoff_package.php

php /home/cabnet/gov.cabnet.app_app/cli/build_safe_handoff_package.php --json
php /home/cabnet/gov.cabnet.app_app/cli/validate_safe_handoff_package.php --latest --json
```

Expected DB-free package result:

- `include_database` is `false`.
- final ZIP name ends with `_no_db.zip`.
- validator reports `has_database_export = false`.
- no `storage/locks/`, `storage/receipt_attachments/`, `.broken_*`, `.before_*`, or backup files appear in the archive.
