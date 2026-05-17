# gov.cabnet.app Patch — EDXEIX Pre-Ride Candidate v3.2.25

Generated for Andreas on 2026-05-17.

## What changed

This patch fixes the next diagnostics blocker found in v3.2.24: the Maildir source contains the expected Bolt pre-ride labels, but they are wrapped in `<p dir="ltr"><strong>Label: ...` HTML rows. The diagnostics-only fallback parser now cleans `<p>/<strong>` HTML label rows before extracting values.

No production V0 parser file is modified. The change stays inside the pre-ride EDXEIX candidate diagnostic library.

## Files included

- `CONTINUE_PROMPT.md`
- `HANDOFF.md`
- `PATCH_README.md`
- `PROJECT_FILE_MANIFEST.md`
- `README.md`
- `SCOPE.md`
- `docs/EDXEIX_PRE_RIDE_CANDIDATE_v3.2.25.md`
- `gov.cabnet.app_app/lib/edxeix_pre_ride_candidate_lib.php`
- `gov.cabnet.app_app/lib/edxeix_submit_diagnostic_lib.php`
- `gov.cabnet.app_app/cli/pre_ride_candidate_diagnostic.php`
- `gov.cabnet.app_app/cli/edxeix_submit_diagnostic.php`
- `public_html/gov.cabnet.app/ops/pre-ride-edxeix-candidate.php`
- `public_html/gov.cabnet.app/ops/edxeix-submit-diagnostic.php`

## Exact upload paths

Upload/replace:

```text
/home/cabnet/CONTINUE_PROMPT.md
/home/cabnet/HANDOFF.md
/home/cabnet/PATCH_README.md
/home/cabnet/PROJECT_FILE_MANIFEST.md
/home/cabnet/README.md
/home/cabnet/SCOPE.md
/home/cabnet/docs/EDXEIX_PRE_RIDE_CANDIDATE_v3.2.25.md
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

The pre-ride candidate output should now show fallback HTML cleanup and label extraction, for example:

```text
parser_fallback.used: true
parser_fallback.diagnostics.fallback_html_cleanup_applied: true
parser_fallback.diagnostics.fallback_label_hits: greater than 0
```

If the source email is not at least +30 minutes in the future or mapping is incomplete, it must still remain blocked.

## Git commit title

Harden pre-ride HTML label extraction

## Git commit description

Fixes the diagnostics-only pre-ride EDXEIX candidate fallback parser so Maildir messages with `<p>/<strong>` HTML label rows can be cleaned and parsed. This follows v3.2.24 validation showing expected labels were present but not extractable by the fallback parser. Existing production V0 parser behavior remains untouched.

No SQL changes. No live EDXEIX submit, AADE call, queue job, normalized booking write, or production V0 route change.
