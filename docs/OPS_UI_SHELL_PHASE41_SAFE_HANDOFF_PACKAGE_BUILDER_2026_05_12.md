# Ops UI Shell Phase 41 — Safe Handoff Package Builder — 2026-05-12

## Purpose

Adds an admin-only Safe Handoff ZIP builder to `/ops/handoff-center.php`.

The builder creates a private downloadable ZIP containing live project files, a database SQL export, documentation, Firefox helper source folders when present, and sanitized config placeholders.

## Files added/changed

- `public_html/gov.cabnet.app/ops/handoff-center.php`
- `gov.cabnet.app_app/src/Support/SafeHandoffPackageBuilder.php`

## Safety rules

The package builder does not call Bolt, EDXEIX, or AADE.

The builder does not copy real server-only config files from `/home/cabnet/gov.cabnet.app_config`. Instead, it creates sanitized placeholders under `gov.cabnet.app_config_examples/`.

The package excludes obvious logs, cache files, sessions, mailboxes, temporary files, backup directories, compressed archives, and files with sensitive names.

The database export is included because Andreas explicitly requested it. The resulting ZIP must be treated as private operational data and must not be committed to GitHub unless the database export is intentionally sanitized.

## Verification

```bash
php -l /home/cabnet/gov.cabnet.app_app/src/Support/SafeHandoffPackageBuilder.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/handoff-center.php
```

Expected:

```text
No syntax errors detected
```

Open:

```text
https://gov.cabnet.app/ops/handoff-center.php
```

Expected:

- Login required.
- Admin user sees the Safe Handoff ZIP download button.
- The button downloads a ZIP.
- ZIP contains project files and `DATABASE_EXPORT.sql`.
- ZIP contains sanitized config placeholders under `gov.cabnet.app_config_examples/`.
- Real config values are not included.
