# Ops UI Shell Phase 42 — Handoff Package Inspector — 2026-05-12

Adds `/ops/handoff-package-inspector.php`, an admin-only read-only inspector for the Safe Handoff ZIP builder.

## Purpose

Before building a full server handoff ZIP, the inspector checks:

- `ZipArchive` availability
- `mysqldump` availability
- temp directory writability
- key project folder presence/readability
- expected packaging areas
- sample included/excluded files
- exclusion behavior for logs, cache, sessions, mailboxes, backups, archives, and secret-looking filenames

## Safety

The page does not:

- build a ZIP
- export the database
- read or display secrets
- call Bolt
- call EDXEIX
- call AADE
- write database rows
- change production workflow behavior

## Upload path

`public_html/gov.cabnet.app/ops/handoff-package-inspector.php` → `/home/cabnet/public_html/gov.cabnet.app/ops/handoff-package-inspector.php`

## Verification

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/handoff-package-inspector.php
```

Open:

`https://gov.cabnet.app/ops/handoff-package-inspector.php`
