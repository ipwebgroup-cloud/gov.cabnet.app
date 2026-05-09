# gov.cabnet.app Patch — v6.6.1 EDXEIX Mail Source Policy

## What Changed

Corrects the EDXEIX readiness report so the EDXEIX submission data source is strictly pre-ride Bolt email / mail-derived normalized bookings.

This patch also documents the source split:

- EDXEIX source: pre-ride Bolt email only.
- AADE source: Bolt API pickup timestamp worker only.

## Files Included

```text
gov.cabnet.app_app/cli/edxeix_readiness_report.php
docs/EDXEIX_READINESS_REPORT.md
HANDOFF.md
CONTINUE_PROMPT.md
PATCH_README.md
```

## Upload Paths

Upload to server:

```text
/home/cabnet/gov.cabnet.app_app/cli/edxeix_readiness_report.php
```

Local repo/docs files:

```text
docs/EDXEIX_READINESS_REPORT.md
HANDOFF.md
CONTINUE_PROMPT.md
PATCH_README.md
```

## SQL

None.

## Verification Commands

```bash
php -l /home/cabnet/gov.cabnet.app_app/cli/edxeix_readiness_report.php

/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/edxeix_readiness_report.php --future-hours=72 --past-minutes=60 --limit=50 --json

/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/edxeix_readiness_report.php --only-ready --future-hours=168 --limit=100 --json

mysql cabnet_gov -e "
SELECT COUNT(*) AS submission_jobs FROM submission_jobs;
SELECT COUNT(*) AS submission_attempts FROM submission_attempts;
"
```

Optional diagnostic command to prove non-mail/API rows are blocked as the wrong EDXEIX source:

```bash
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/edxeix_readiness_report.php --include-non-mail --future-hours=168 --limit=100 --json
```

## Expected Result

```text
ok: true
version: v6.6.1
source_policy.edxeix_submission_source: pre_ride_bolt_email_only
source_policy.edxeix_uses_bolt_api_as_source: false
source_policy.aade_invoice_source: bolt_api_pickup_timestamp_worker_only
safety.does_not_call_edxeix: true
safety.does_not_issue_aade_receipts: true
queue_counts.queues_unchanged: true
submission_jobs: 0
submission_attempts: 0
```

## Git Commit Title

```text
Correct EDXEIX readiness source policy to pre-ride email
```

## Git Commit Description

```text
Updates the EDXEIX readiness report and continuity docs so EDXEIX submission readiness is based strictly on pre-ride Bolt email / mail-derived normalized bookings.

Clarifies the source split:
- EDXEIX uses pre-ride Bolt email intake only.
- Bolt API pickup/finalized data is not an EDXEIX submission source.
- AADE invoice issuing remains limited to the Bolt API pickup timestamp worker.

The report remains read-only and does not call EDXEIX, issue AADE receipts, create submission_jobs, create submission_attempts, or expose session cookies/CSRF tokens.

No SQL changes and no live-submit activation.
```
