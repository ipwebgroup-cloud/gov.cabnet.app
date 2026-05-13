# V3 Pre-Ride Email Cron Health Page

Adds a read-only operations page for the isolated V3 cron automation path.

## URL

```text
https://gov.cabnet.app/ops/pre-ride-email-v3-cron-health.php
```

## Purpose

The page gives Andreas one place to confirm whether the V3 intake cron and V3 submit dry-run cron are alive without using repeated terminal checks.

It displays:

- V3 queue table status.
- Total/future/queued/submit-dry-run-ready counts.
- Latest V3 queue rows.
- Intake cron log freshness.
- Submit dry-run cron log freshness.
- Latest SUMMARY lines.
- Recent BLOCKED examples.
- Collapsible log tails.

## Safety

- Read-only page.
- No DB writes.
- No EDXEIX calls.
- No AADE calls.
- No production `submission_jobs` writes.
- No production `submission_attempts` writes.
- Does not touch `/ops/pre-ride-email-tool.php`.

## Files

```text
public_html/gov.cabnet.app/ops/pre-ride-email-v3-cron-health.php
```
