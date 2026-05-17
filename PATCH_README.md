# gov.cabnet.app Patch — Safe Handoff Package Privacy Hardening

Generated for Andreas on 2026-05-17.

## What changed

This patch refreshes the project handoff state and hardens safe handoff package generation/validation so DB-free Git-safe packages do not accidentally contain private audit/runtime material.

Key changes:

- Updated `HANDOFF.md` and `CONTINUE_PROMPT.md` from stale queue 1590/v3.2.15 wording to the current queue 2398 closed-test safe blocked posture.
- Added `.gitignore` protection for root `DATABASE_EXPORT.sql`, runtime locks, receipt attachment PDFs, and cPanel backup/broken files.
- Updated `SafeHandoffPackageBuilder` so database exports are excluded by default unless explicitly requested.
- Updated CLI package builder so DB-free is the default; private DB audit export now requires `--include-db`.
- Updated `SafeHandoffPackageValidator` to flag receipt attachments, runtime locks, storage artifacts, backup/broken files, and accidental database exports.
- Added documentation: `docs/SAFE_HANDOFF_PACKAGE_PRIVACY_HARDENING_2026_05_17.md`.

## Files included

- `.gitignore`
- `HANDOFF.md`
- `CONTINUE_PROMPT.md`
- `PATCH_README.md`
- `docs/SAFE_HANDOFF_PACKAGE_PRIVACY_HARDENING_2026_05_17.md`
- `gov.cabnet.app_app/src/Support/SafeHandoffPackageBuilder.php`
- `gov.cabnet.app_app/src/Support/SafeHandoffPackageValidator.php`
- `gov.cabnet.app_app/cli/build_safe_handoff_package.php`
- `public_html/gov.cabnet.app/ops/handoff-package-cli.php`

## Exact upload paths

Upload/replace:

```text
/home/cabnet/.gitignore
/home/cabnet/HANDOFF.md
/home/cabnet/CONTINUE_PROMPT.md
/home/cabnet/PATCH_README.md
/home/cabnet/docs/SAFE_HANDOFF_PACKAGE_PRIVACY_HARDENING_2026_05_17.md
/home/cabnet/gov.cabnet.app_app/src/Support/SafeHandoffPackageBuilder.php
/home/cabnet/gov.cabnet.app_app/src/Support/SafeHandoffPackageValidator.php
/home/cabnet/gov.cabnet.app_app/cli/build_safe_handoff_package.php
/home/cabnet/public_html/gov.cabnet.app/ops/handoff-package-cli.php
```

For local GitHub Desktop repo, extract this ZIP at the repository root. The ZIP root mirrors the repo/live layout directly and has no wrapper folder.

## SQL to run

None.

## Verification commands

```bash
php -l /home/cabnet/gov.cabnet.app_app/src/Support/SafeHandoffPackageBuilder.php
php -l /home/cabnet/gov.cabnet.app_app/src/Support/SafeHandoffPackageValidator.php
php -l /home/cabnet/gov.cabnet.app_app/cli/build_safe_handoff_package.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/handoff-package-cli.php

php /home/cabnet/gov.cabnet.app_app/cli/build_safe_handoff_package.php --json
php /home/cabnet/gov.cabnet.app_app/cli/validate_safe_handoff_package.php --latest --json
```

Optional private DB audit package command, only when explicitly needed:

```bash
php /home/cabnet/gov.cabnet.app_app/cli/build_safe_handoff_package.php --include-db --json
```

## Verification URLs

```text
https://gov.cabnet.app/ops/handoff-center.php
https://gov.cabnet.app/ops/handoff-package-validator.php
https://gov.cabnet.app/ops/handoff-package-tools.php
```

## Expected result

- Normal CLI package generation creates a `_no_db.zip` package.
- Validator reports no dangerous entries for DB-free packages.
- DB-free packages do not contain `DATABASE_EXPORT.sql`, receipt PDFs, runtime `.lock` files, storage artifacts, backup/broken PHP copies, or temporary package residue.
- DB audit packages are still possible only when explicitly requested and must remain private operational material.

## Git commit title

Safe-harden handoff package exports

## Git commit description

Refreshes the project handoff state after the queue 2398 closed test and hardens safe handoff package generation/validation. DB-free packages are now the default for CLI generation, database export requires explicit `--include-db`, and the builder/validator now exclude or flag runtime locks, receipt attachments, storage artifacts, backup/broken files, and accidental database exports.

No SQL changes. No Bolt, EDXEIX, or AADE calls. No live-submit behavior enabled.
