# gov.cabnet.app Patch — EDXEIX Pre-Ride Future Candidate Capture v3.2.22

Generated for Andreas on 2026-05-17.

## What changed

This patch adds a separate pre-ride email candidate path so the project can move toward full EDXEIX automation ASAP without affecting production V0.

Key changes:

- Adds dry-run parsing of Bolt pre-ride emails into sanitized `bolt_pre_ride_email` EDXEIX candidate previews.
- Keeps existing `bolt_mail` receipt-only rows blocked.
- Applies the +30 minute future guard.
- Resolves driver, vehicle, lessor, and starting point using existing EDXEIX mapping lookup.
- Blocks Admin Excluded vehicles, including the permanent EMT8640 safety rule.
- Adds an optional additive SQL table for sanitized pre-ride candidate metadata.
- Adds CLI and `/ops/` UI tools for pre-ride candidate readiness.
- Updates the EDXEIX submit diagnostic to optionally include the latest pre-ride candidate with `--pre-ride-latest=1`.

No live EDXEIX submit is enabled. No AADE/myDATA behavior is changed. No production V0 route is replaced.

## Files included

- `CONTINUE_PROMPT.md`
- `HANDOFF.md`
- `PATCH_README.md`
- `PROJECT_FILE_MANIFEST.md`
- `README.md`
- `SCOPE.md`
- `docs/EDXEIX_PRE_RIDE_CANDIDATE_v3.2.22.md`
- `docs/EDXEIX_SUBMIT_DIAGNOSTIC_v3.2.21.md`
- `gov.cabnet.app_app/cli/edxeix_submit_diagnostic.php`
- `gov.cabnet.app_app/cli/pre_ride_candidate_diagnostic.php`
- `gov.cabnet.app_app/lib/edxeix_pre_ride_candidate_lib.php`
- `gov.cabnet.app_app/lib/edxeix_submit_diagnostic_lib.php`
- `gov.cabnet.app_sql/2026_05_17_edxeix_pre_ride_candidates.sql`
- `public_html/gov.cabnet.app/ops/edxeix-submit-diagnostic.php`
- `public_html/gov.cabnet.app/ops/pre-ride-edxeix-candidate.php`

## Exact upload paths

Upload/replace:

```text
/home/cabnet/CONTINUE_PROMPT.md
/home/cabnet/HANDOFF.md
/home/cabnet/PATCH_README.md
/home/cabnet/PROJECT_FILE_MANIFEST.md
/home/cabnet/README.md
/home/cabnet/SCOPE.md
/home/cabnet/docs/EDXEIX_PRE_RIDE_CANDIDATE_v3.2.22.md
/home/cabnet/docs/EDXEIX_SUBMIT_DIAGNOSTIC_v3.2.21.md
/home/cabnet/gov.cabnet.app_app/cli/edxeix_submit_diagnostic.php
/home/cabnet/gov.cabnet.app_app/cli/pre_ride_candidate_diagnostic.php
/home/cabnet/gov.cabnet.app_app/lib/edxeix_pre_ride_candidate_lib.php
/home/cabnet/gov.cabnet.app_app/lib/edxeix_submit_diagnostic_lib.php
/home/cabnet/gov.cabnet.app_sql/2026_05_17_edxeix_pre_ride_candidates.sql
/home/cabnet/public_html/gov.cabnet.app/ops/edxeix-submit-diagnostic.php
/home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-edxeix-candidate.php
```

For local GitHub Desktop repo, extract this ZIP at the repository root. The ZIP root mirrors the repo/live layout directly and has no wrapper folder.

## SQL to run

Optional but recommended before using `--write=1` candidate capture:

```bash
mysql -u YOUR_DB_USER -p YOUR_DB_NAME < /home/cabnet/gov.cabnet.app_sql/2026_05_17_edxeix_pre_ride_candidates.sql
```

This migration is additive only. It creates `edxeix_pre_ride_candidates` if it does not exist. It does not alter production V0 tables.

## Verification commands

```bash
php -l /home/cabnet/gov.cabnet.app_app/lib/edxeix_pre_ride_candidate_lib.php
php -l /home/cabnet/gov.cabnet.app_app/lib/edxeix_submit_diagnostic_lib.php
php -l /home/cabnet/gov.cabnet.app_app/cli/pre_ride_candidate_diagnostic.php
php -l /home/cabnet/gov.cabnet.app_app/cli/edxeix_submit_diagnostic.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-edxeix-candidate.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/edxeix-submit-diagnostic.php

php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_candidate_diagnostic.php --json --latest-mail=1
php /home/cabnet/gov.cabnet.app_app/cli/edxeix_submit_diagnostic.php --json --list-candidates=1 --limit=75 --pre-ride-latest=1
```

Optional capture after SQL is installed:

```bash
php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_candidate_diagnostic.php --json --latest-mail=1 --write=1
```

## Verification URLs

```text
https://gov.cabnet.app/ops/pre-ride-edxeix-candidate.php
https://gov.cabnet.app/ops/edxeix-submit-diagnostic.php?pre_ride_latest=1
```

## Expected result

- Syntax checks pass.
- Existing EDXEIX submit diagnostic remains dry-run/read-only.
- Pre-ride candidate page can parse pasted/latest pre-ride email.
- Latest pre-ride candidate may classify as `PRE_RIDE_READY_CANDIDATE` only if it is future, mapped, not excluded, and has required fields.
- No EDXEIX transport is performed.
- No AADE calls occur.
- No queue jobs are created.
- No normalized bookings are created or changed.
- Optional `--write=1` stores sanitized metadata only and never stores the raw email body.

## Git commit title

Add pre-ride EDXEIX candidate diagnostics

## Git commit description

Adds a separate dry-run pre-ride email candidate path for EDXEIX automation readiness. Pre-ride emails can now be parsed into sanitized future candidate previews with +30 minute guard, mapping readiness checks, Admin Excluded vehicle blocking, CLI/web diagnostics, and optional additive metadata capture in `edxeix_pre_ride_candidates`. Existing receipt-only Bolt mail rows remain blocked. No live EDXEIX submit, AADE behavior, production V0 route, queue job, or normalized booking behavior is changed.
