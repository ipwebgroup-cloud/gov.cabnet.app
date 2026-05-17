# EDXEIX Pre-Ride Future Candidate Capture v3.2.22

Generated for Andreas on 2026-05-17.

## Purpose

This patch adds the next ASAP automation step without affecting production V0:

- Parse a Bolt pre-ride email into a separate `bolt_pre_ride_email` EDXEIX candidate preview.
- Keep existing `bolt_mail` receipt-only rows blocked.
- Apply the +30 minute future guard.
- Resolve driver, vehicle, lessor, and starting point using the existing EDXEIX mapping lookup.
- Block Admin Excluded vehicles, including the permanent EMT8640 rule.
- Optionally capture sanitized metadata into a new additive table.

## Safety contract

Default behavior is dry-run/read-only.

This patch does not:

- submit to EDXEIX;
- call AADE/myDATA;
- create submission jobs;
- create or change `normalized_bookings` rows;
- change production V0 receipt/pre-ride behavior;
- store raw pre-ride email body.

The optional metadata capture writes only parsed/sanitized fields, mapping status, readiness status, payload preview, source hash, and blockers.

## New files

```text
gov.cabnet.app_app/lib/edxeix_pre_ride_candidate_lib.php
gov.cabnet.app_app/cli/pre_ride_candidate_diagnostic.php
public_html/gov.cabnet.app/ops/pre-ride-edxeix-candidate.php
gov.cabnet.app_sql/2026_05_17_edxeix_pre_ride_candidates.sql
```

## Updated files

```text
gov.cabnet.app_app/lib/edxeix_submit_diagnostic_lib.php
gov.cabnet.app_app/cli/edxeix_submit_diagnostic.php
public_html/gov.cabnet.app/ops/edxeix-submit-diagnostic.php
README.md
SCOPE.md
HANDOFF.md
CONTINUE_PROMPT.md
PROJECT_FILE_MANIFEST.md
PATCH_README.md
```

## CLI usage

Dry-run latest Maildir pre-ride candidate:

```bash
php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_candidate_diagnostic.php --json --latest-mail=1
```

Dry-run candidate from a saved email text file:

```bash
php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_candidate_diagnostic.php --json --email-file=/path/to/pre_ride_email.txt
```

Include the latest pre-ride email candidate in the EDXEIX submit diagnostic:

```bash
php /home/cabnet/gov.cabnet.app_app/cli/edxeix_submit_diagnostic.php --json --list-candidates=1 --limit=75 --pre-ride-latest=1
```

Optional metadata capture after the SQL migration is installed:

```bash
php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_candidate_diagnostic.php --json --latest-mail=1 --write=1
```

## Web usage

```text
https://gov.cabnet.app/ops/pre-ride-edxeix-candidate.php
https://gov.cabnet.app/ops/edxeix-submit-diagnostic.php?pre_ride_latest=1
```

## Expected classifications

```text
NO_PRE_RIDE_EMAIL_SOURCE
PRE_RIDE_CANDIDATE_BLOCKED
PRE_RIDE_READY_CANDIDATE
```

A ready candidate is still not submitted. It only means the pre-ride email is structurally ready for a future supervised one-shot readiness step.

## Next step after v3.2.22

When a real future pre-ride email is available:

1. Run dry-run parse/readiness.
2. Confirm `PRE_RIDE_READY_CANDIDATE`.
3. Capture sanitized metadata with `--write=1` if approved.
4. Build the next patch to convert captured candidates into a supervised one-shot EDXEIX live-submit readiness path.
