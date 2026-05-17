# gov.cabnet.app Patch — EDXEIX Pre-Ride Candidate v3.2.26

Generated for Andreas on 2026-05-17.

## What changed

This patch fixes the diagnostics-only pre-ride fallback parser after v3.2.25 validation showed HTML cleanup was detected but fallback label hits still returned zero.

Root cause:

```text
preg_match_all() returns the number of matches, not only 1.
The v3.2.25 guard accepted exactly 1 match and rejected normal emails with many labels.
```

Fix:

- Accept any positive match count from `preg_match_all()`.
- Preserve redacted source diagnostics.
- Preserve dry-run/no-submit behavior.
- Leave the production V0 parser file untouched.

## Files included

- `CONTINUE_PROMPT.md`
- `HANDOFF.md`
- `PATCH_README.md`
- `PROJECT_FILE_MANIFEST.md`
- `README.md`
- `SCOPE.md`
- `docs/EDXEIX_PRE_RIDE_CANDIDATE_v3.2.26.md`
- `gov.cabnet.app_app/lib/edxeix_pre_ride_candidate_lib.php`
- `gov.cabnet.app_app/lib/edxeix_submit_diagnostic_lib.php`
- `gov.cabnet.app_app/cli/pre_ride_candidate_diagnostic.php`
- `gov.cabnet.app_app/cli/edxeix_submit_diagnostic.php`
- `public_html/gov.cabnet.app/ops/pre-ride-edxeix-candidate.php`
- `public_html/gov.cabnet.app/ops/edxeix-submit-diagnostic.php`

## Exact upload paths

```text
/home/cabnet/CONTINUE_PROMPT.md
/home/cabnet/HANDOFF.md
/home/cabnet/PATCH_README.md
/home/cabnet/PROJECT_FILE_MANIFEST.md
/home/cabnet/README.md
/home/cabnet/SCOPE.md
/home/cabnet/docs/EDXEIX_PRE_RIDE_CANDIDATE_v3.2.26.md
/home/cabnet/gov.cabnet.app_app/lib/edxeix_pre_ride_candidate_lib.php
/home/cabnet/gov.cabnet.app_app/lib/edxeix_submit_diagnostic_lib.php
/home/cabnet/gov.cabnet.app_app/cli/pre_ride_candidate_diagnostic.php
/home/cabnet/gov.cabnet.app_app/cli/edxeix_submit_diagnostic.php
/home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-edxeix-candidate.php
/home/cabnet/public_html/gov.cabnet.app/ops/edxeix-submit-diagnostic.php
```

## SQL to run

None.

## Verification commands

```bash
php -l /home/cabnet/gov.cabnet.app_app/lib/edxeix_pre_ride_candidate_lib.php
php -l /home/cabnet/gov.cabnet.app_app/lib/edxeix_submit_diagnostic_lib.php
php -l /home/cabnet/gov.cabnet.app_app/cli/pre_ride_candidate_diagnostic.php
php -l /home/cabnet/gov.cabnet.app_app/cli/edxeix_submit_diagnostic.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-edxeix-candidate.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/edxeix-submit-diagnostic.php

php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_candidate_diagnostic.php --json --latest-mail=1 --debug-source=1
php /home/cabnet/gov.cabnet.app_app/cli/edxeix_submit_diagnostic.php --json --list-candidates=1 --limit=75 --pre-ride-latest=1 --pre-ride-debug-source=1
```

## Expected result

The pre-ride report should now show:

```text
parser_fallback.used: true
fallback_html_cleanup_applied: true
fallback_label_hits: greater than 1
parsed_fields populated
```

The candidate should still remain blocked unless the email is a real future ride, mapped, non-excluded, and passes the +30 minute guard. No EDXEIX transport is enabled.

## Git commit title

Fix pre-ride fallback multi-label parsing

## Git commit description

Fixes the diagnostics-only pre-ride candidate fallback parser so `preg_match_all()` accepts any positive label match count instead of only exactly one match. This follows v3.2.25 validation showing expected HTML labels were present but the fallback parser still returned zero fields. Production V0 parser behavior remains untouched.

No SQL changes. No live EDXEIX submit, AADE call, queue job, normalized booking write, or production V0 route change.
