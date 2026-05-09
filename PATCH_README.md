# gov.cabnet.app Patch — v6.6.2 EDXEIX stale mail candidate reporting

## What changed

Improves the read-only EDXEIX readiness report so stale `bolt_mail_intake.safety_status = future_candidate` rows are not presented as active future candidates after their pickup time has passed.

This patch keeps the EDXEIX source policy unchanged:

- EDXEIX uses pre-ride Bolt email only.
- Bolt API pickup/finalized data is not an EDXEIX submission source.
- AADE uses only the Bolt API pickup timestamp worker.

## Files included

```text
gov.cabnet.app_app/cli/edxeix_readiness_report.php
docs/EDXEIX_READINESS_REPORT.md
PATCH_README.md
```

## Upload paths

Upload:

```text
/home/cabnet/gov.cabnet.app_app/cli/edxeix_readiness_report.php
```

Local repo docs:

```text
docs/EDXEIX_READINESS_REPORT.md
PATCH_README.md
```

## SQL

None.

## Verification

```bash
php -l /home/cabnet/gov.cabnet.app_app/cli/edxeix_readiness_report.php

/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/edxeix_readiness_report.php --future-hours=72 --past-minutes=60 --limit=50 --json

/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/edxeix_readiness_report.php --only-ready --future-hours=168 --limit=100 --json

mysql cabnet_gov -e "
SELECT COUNT(*) AS submission_jobs FROM submission_jobs;
SELECT COUNT(*) AS submission_attempts FROM submission_attempts;
"
```

## Expected result

```text
ok: true
version: v6.6.2
queue_counts.queues_unchanged: true
mail_intake_summary.currently_future_candidates reflects only rows with parsed_pickup_at > NOW()
mail_intake_summary.stale_future_candidate_rows shows old rows that still carry the legacy future_candidate label
```

## Git commit title

```text
Clarify stale Bolt mail candidates in EDXEIX readiness report
```

## Git commit description

```text
Improves the read-only EDXEIX readiness report so mail intake rows that were originally marked future_candidate but now have past pickup times are reported separately as stale future-candidate rows.

Keeps the source policy unchanged: EDXEIX uses pre-ride Bolt email only, while AADE invoice issuing remains limited to the Bolt API pickup timestamp worker.

The report remains read-only and does not call EDXEIX, issue AADE receipts, create submission_jobs, create submission_attempts, or expose session cookies/CSRF tokens.

No SQL changes and no live-submit activation.
```
