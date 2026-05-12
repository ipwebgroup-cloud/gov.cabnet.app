# Ops UI Shell Phase 45 — Handoff Package Validator

Date: 2026-05-12

## Summary

Adds a read-only safety validator for generated Safe Handoff ZIP packages.

## Added files

- `gov.cabnet.app_app/src/Support/SafeHandoffPackageValidator.php`
- `gov.cabnet.app_app/cli/validate_safe_handoff_package.php`
- `public_html/gov.cabnet.app/ops/handoff-package-validator.php`

## Purpose

The validator checks generated handoff ZIPs before they are trusted, shared, or archived.
It verifies expected entries, sanitized config examples, database export presence, and dangerous path patterns.

## Safety

The validator does not:

- build a ZIP
- export the database
- extract the ZIP
- read or print `DATABASE_EXPORT.sql` contents
- display secrets
- call Bolt
- call EDXEIX
- call AADE
- write workflow data
- enable live submission

## CLI usage

Validate latest generated package:

```bash
php /home/cabnet/gov.cabnet.app_app/cli/validate_safe_handoff_package.php --latest
```

Validate latest generated package with JSON output:

```bash
php /home/cabnet/gov.cabnet.app_app/cli/validate_safe_handoff_package.php --latest --json
```

Validate a specific package:

```bash
php /home/cabnet/gov.cabnet.app_app/cli/validate_safe_handoff_package.php --file=/home/cabnet/gov.cabnet.app_app/var/handoff-packages/PACKAGE.zip
```

## GUI

Open:

```text
https://gov.cabnet.app/ops/handoff-package-validator.php
```

Admin login is required.
