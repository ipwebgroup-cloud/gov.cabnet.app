# gov.cabnet.app Patch — v3.2.24 Pre-Ride Source Diagnostics

Generated for Andreas on 2026-05-17.

## What changed

Adds opt-in safe source diagnostics to the pre-ride future EDXEIX candidate workflow after v3.2.23 showed the selected latest Maildir message had zero parsed labels and zero fallback labels.

The new diagnostic output helps identify whether the selected Maildir body is encoded, malformed, unexpected, or not a usable Bolt pre-ride email, while redacting/truncating line values.

## Files included

- `CONTINUE_PROMPT.md`
- `HANDOFF.md`
- `PATCH_README.md`
- `PROJECT_FILE_MANIFEST.md`
- `README.md`
- `SCOPE.md`
- `docs/EDXEIX_PRE_RIDE_CANDIDATE_v3.2.24.md`
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
/home/cabnet/docs/EDXEIX_PRE_RIDE_CANDIDATE_v3.2.24.md
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
```

Safe source diagnostic:

```bash
php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_candidate_diagnostic.php --json --latest-mail=1 --debug-source=1
```

Integrated diagnostic:

```bash
php /home/cabnet/gov.cabnet.app_app/cli/edxeix_submit_diagnostic.php --json --list-candidates=1 --limit=75 --pre-ride-latest=1 --pre-ride-debug-source=1
```

## Expected result

- Syntax checks pass.
- EDXEIX transport remains false/not performed.
- Pre-ride candidate remains blocked unless a parseable, future-safe, mapped email exists.
- JSON includes `source_debug` with redacted structural clues.

## Git commit title

Add safe pre-ride source diagnostics

## Git commit description

Adds opt-in redacted source diagnostics for pre-ride Maildir candidate parsing after v3.2.23 validation showed zero primary and fallback parsed fields. The diagnostics report line/label structure, phrase/colon hits, and redacted/truncated preview lines without storing or exposing raw email body content.

No SQL changes. No live EDXEIX submit, AADE call, queue job, normalized booking write, or production V0 route change.
