# EDXEIX Pre-Ride Candidate v3.2.23

## Purpose

v3.2.23 keeps the v3.2.22 pre-ride future candidate workflow dry-run/safe, and improves Maildir parsing diagnostics after the first production test showed the latest matched Maildir message loaded but the primary line-based parser returned empty fields.

## What changed

- Adds a diagnostics-only fallback label parser inside `edxeix_pre_ride_candidate_lib.php`.
- The fallback is used only when the existing `BoltPreRideEmailParser` finds too few fields.
- The existing production parser file is not changed.
- Production V0 pre-ride/manual tool behavior remains untouched.
- Adds `parser_fallback` details to candidate output, including whether fallback was used and label-hit diagnostics.

## Safety contract

- No EDXEIX HTTP transport.
- No AADE/myDATA calls.
- No queue jobs.
- No `normalized_bookings` writes.
- Raw email body is not stored.
- Optional `--write=1` still stores sanitized candidate metadata only.

## Why this was needed

The first v3.2.22 live test loaded a Maildir message and correctly blocked it, but all parsed fields were empty even though the loader considered it a matching pre-ride email. v3.2.23 handles cases where labels are present but not normalized at the start of lines, for example HTML/table-derived text or labels glued together after MIME cleanup.

## Verification

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

Expected result:

- If fallback helps, `candidate.parser_fallback.used` becomes `true` and parsed fields should populate.
- If fallback does not help, the output remains safely blocked and reports fallback label diagnostics.
- No submit is performed either way.
