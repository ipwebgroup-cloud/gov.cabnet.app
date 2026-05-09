# EDXEIX Readiness Report — v6.6.2

Read-only CLI report for pre-live EDXEIX readiness.

## Source policy

- EDXEIX submission source is strictly pre-ride Bolt email intake / mail-derived normalized bookings.
- Bolt API pickup/finalized data is not an EDXEIX submission source.
- AADE invoice issuing remains strictly limited to the Bolt API pickup timestamp worker.
- Pre-ride email must never issue AADE invoices.

## v6.6.2 change

The report now separates stale mail rows from active future candidates. Older rows may keep `bolt_mail_intake.safety_status = future_candidate` because they were future at import time, but once their pickup time has passed they are no longer active EDXEIX candidates.

The `mail_intake_summary` now includes:

```text
legacy_status_future_candidate_rows
currently_future_candidates
currently_future_unlinked_candidates
currently_future_linked_candidates
stale_future_candidate_rows
stale_future_linked_candidates
stale_future_unlinked_candidates
```

The older aliases are retained, but now mean currently future rows:

```text
future_candidates
future_unlinked_candidates
future_linked_candidates
```

## Safety guarantees

This script does not:

- call EDXEIX
- issue AADE receipts
- create `submission_jobs`
- create `submission_attempts`
- print cookies, CSRF tokens, API keys, or private config values

## Commands

```bash
php -l /home/cabnet/gov.cabnet.app_app/cli/edxeix_readiness_report.php

/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/edxeix_readiness_report.php --future-hours=72 --past-minutes=60 --limit=50 --json

/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/edxeix_readiness_report.php --only-ready --future-hours=168 --limit=100 --json

/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/edxeix_readiness_report.php --include-non-mail --future-hours=168 --limit=100 --json
```

## Expected clean posture

```text
ok: true
version: v6.6.2
source_policy.edxeix_submission_source: pre_ride_bolt_email_only
source_policy.edxeix_uses_bolt_api_as_source: false
queue_counts.queues_unchanged: true
submission_jobs: 0
submission_attempts: 0
```
