# Ops UI Shell Phase 43 — Safe Handoff Package CLI Runner

Date: 2026-05-12

## Summary

Adds a CLI-safe runner for the Safe Handoff Package Builder and a read-only admin guide page.

The browser download builder remains available from `/ops/handoff-center.php`, but the CLI runner is safer for large packages because it avoids browser timeout issues and stores the ZIP in a private server directory.

## Files

- `gov.cabnet.app_app/cli/build_safe_handoff_package.php`
- `public_html/gov.cabnet.app/ops/handoff-package-cli.php`

## Safety

The CLI runner:

- does not call Bolt
- does not call EDXEIX
- does not call AADE
- does not expose real config values
- stores generated ZIPs outside the public webroot
- supports building with or without `DATABASE_EXPORT.sql`
- supports cleanup of old generated packages

The resulting ZIP must be treated as private operational material if it includes the database export.

## Default private output path

```text
/home/cabnet/gov.cabnet.app_app/var/handoff-packages
```

## Commands

```bash
php /home/cabnet/gov.cabnet.app_app/cli/build_safe_handoff_package.php
php /home/cabnet/gov.cabnet.app_app/cli/build_safe_handoff_package.php --json
php /home/cabnet/gov.cabnet.app_app/cli/build_safe_handoff_package.php --no-db
php /home/cabnet/gov.cabnet.app_app/cli/build_safe_handoff_package.php --cleanup --keep-days=7
```

## Verification

```bash
php -l /home/cabnet/gov.cabnet.app_app/cli/build_safe_handoff_package.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/handoff-package-cli.php
```

Expected:

```text
No syntax errors detected
```

## URL

```text
https://gov.cabnet.app/ops/handoff-package-cli.php
```
