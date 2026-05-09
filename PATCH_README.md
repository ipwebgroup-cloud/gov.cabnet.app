# gov.cabnet.app v6.6.0 — EDXEIX Readiness Report

## What changed

Adds a read-only EDXEIX readiness report CLI script.

This is a pre-live audit/report tool only. It does not submit to EDXEIX, does not create queue rows, does not issue AADE receipts, and does not expose secrets.

## Files included

```text
gov.cabnet.app_app/cli/edxeix_readiness_report.php
docs/EDXEIX_READINESS_REPORT.md
PATCH_README.md
```

## Exact upload paths

Upload/extract these files to:

```text
/home/cabnet/gov.cabnet.app_app/cli/edxeix_readiness_report.php
```

and into the local repository path:

```text
docs/EDXEIX_READINESS_REPORT.md
PATCH_README.md
```

The project workflow is:

1. Download this zip.
2. Extract locally into the GitHub Desktop repo root.
3. Review changes.
4. Upload changed files manually to the server.
5. Test on server.
6. Commit after production confirmation.

## SQL

None.

## Verification commands

Syntax check:

```bash
php -l /home/cabnet/gov.cabnet.app_app/cli/edxeix_readiness_report.php
```

Run read-only JSON report:

```bash
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/edxeix_readiness_report.php --future-hours=72 --past-minutes=60 --limit=50 --json
```

Run only-ready view:

```bash
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/edxeix_readiness_report.php --only-ready --future-hours=168 --limit=100 --json
```

Confirm queues remain unchanged/zero:

```bash
mysql cabnet_gov -e "
SELECT COUNT(*) AS submission_jobs FROM submission_jobs;
SELECT COUNT(*) AS submission_attempts FROM submission_attempts;
"
```

## Expected result

- Script syntax passes.
- JSON output shows `ok: true`.
- Safety flags show no EDXEIX calls, no AADE issuing, no queue creation.
- `queue_counts.queues_unchanged` is `true`.
- `submission_jobs` and `submission_attempts` remain zero unless they already contained rows before the report.

## Git commit title

```text
Add read-only EDXEIX readiness report
```

## Git commit description

```text
Adds a read-only CLI report for EDXEIX pre-live readiness.

The report analyzes normalized Bolt bookings and classifies whether each one is preflight-ready, blocked, receipt-only, not real Bolt, lab/test, terminal/past, missing mapping, duplicate, or blocked by live config/session state.

Safety posture:
- Does not call EDXEIX.
- Does not issue AADE receipts.
- Does not create submission_jobs.
- Does not create submission_attempts.
- Does not print cookies, CSRF tokens, API keys, or private config values.

No SQL changes and no production behavior changes.
EDXEIX live submit remains disabled.
```
