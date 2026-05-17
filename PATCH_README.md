# gov.cabnet.app Patch — EDXEIX Pre-Ride Candidate Parser Fallback v3.2.23

Generated for Andreas on 2026-05-17.

## What changed

This patch improves the v3.2.22 pre-ride candidate path after production validation showed the latest Maildir message was loaded, but the existing parser returned empty required fields.

Key changes:

- Adds a diagnostics-only fallback label parser in `edxeix_pre_ride_candidate_lib.php`.
- Keeps the existing `BoltPreRideEmailParser.php` untouched so production V0/manual pre-ride tooling is not changed.
- Uses fallback only when the primary parser finds too few fields.
- Adds `candidate.parser_fallback` output with `used`, reason, field counts, and safe label-hit diagnostics.
- Updates CLI/web version strings to v3.2.23.
- No live EDXEIX submit, AADE call, queue job, or normalized booking write is enabled.

## Files included

- `HANDOFF.md`
- `CONTINUE_PROMPT.md`
- `PATCH_README.md`
- `PROJECT_FILE_MANIFEST.md`
- `README.md`
- `SCOPE.md`
- `docs/EDXEIX_PRE_RIDE_CANDIDATE_v3.2.23.md`
- `gov.cabnet.app_app/lib/edxeix_pre_ride_candidate_lib.php`
- `gov.cabnet.app_app/lib/edxeix_submit_diagnostic_lib.php`
- `gov.cabnet.app_app/cli/pre_ride_candidate_diagnostic.php`
- `gov.cabnet.app_app/cli/edxeix_submit_diagnostic.php`
- `public_html/gov.cabnet.app/ops/pre-ride-edxeix-candidate.php`
- `public_html/gov.cabnet.app/ops/edxeix-submit-diagnostic.php`

## Exact upload paths

Upload/replace:

```text
/home/cabnet/HANDOFF.md
/home/cabnet/CONTINUE_PROMPT.md
/home/cabnet/PATCH_README.md
/home/cabnet/PROJECT_FILE_MANIFEST.md
/home/cabnet/README.md
/home/cabnet/SCOPE.md
/home/cabnet/docs/EDXEIX_PRE_RIDE_CANDIDATE_v3.2.23.md
/home/cabnet/gov.cabnet.app_app/lib/edxeix_pre_ride_candidate_lib.php
/home/cabnet/gov.cabnet.app_app/lib/edxeix_submit_diagnostic_lib.php
/home/cabnet/gov.cabnet.app_app/cli/pre_ride_candidate_diagnostic.php
/home/cabnet/gov.cabnet.app_app/cli/edxeix_submit_diagnostic.php
/home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-edxeix-candidate.php
/home/cabnet/public_html/gov.cabnet.app/ops/edxeix-submit-diagnostic.php
```

## SQL to run

None.

The v3.2.22 additive table has already been installed if `--write=1` returned `candidate_id`.

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

## Verification URLs

```text
https://gov.cabnet.app/ops/pre-ride-edxeix-candidate.php
https://gov.cabnet.app/ops/edxeix-submit-diagnostic.php?pre_ride_latest=1
```

## Expected result

- Syntax checks pass.
- Latest pre-ride candidate diagnostic remains dry-run.
- Output includes `candidate.parser_fallback`.
- If labels were present but not line-normalized, parsed fields should now populate.
- If the message is not a usable future pre-ride email, the result remains `PRE_RIDE_CANDIDATE_BLOCKED`.
- No EDXEIX submit is performed.

## Git commit title

Harden pre-ride candidate Maildir parsing

## Git commit description

Adds a diagnostics-only fallback label parser to the v3.2.22 pre-ride EDXEIX candidate path after production validation showed a matched Maildir message with empty parsed fields. The fallback is isolated inside the candidate diagnostic library, leaves the existing production pre-ride parser untouched, reports parser_fallback diagnostics, and preserves the dry-run/no-submit safety posture.

No SQL changes. No live EDXEIX submit, AADE call, queue job, normalized booking write, or production V0 route change.
