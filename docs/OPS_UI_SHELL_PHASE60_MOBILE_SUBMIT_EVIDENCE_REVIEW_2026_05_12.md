# Ops UI Shell Phase 60 — Mobile Submit Evidence Review

Date: 2026-05-12
Project: gov.cabnet.app Bolt → EDXEIX bridge

## Summary

Adds a read-only Mobile Submit Evidence Review page:

- `public_html/gov.cabnet.app/ops/mobile-submit-evidence-review.php`

The page reviews sanitized dry-run evidence records saved in `mobile_submit_evidence_log` and summarizes evidence status, EDXEIX ID coverage, starting point coverage, and email hashes without displaying raw email text.

## Safety

This patch does not modify the production pre-ride route:

- `/home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-tool.php`

The page does not:

- call Bolt
- call EDXEIX
- call AADE
- write database rows
- stage jobs
- enable live EDXEIX submission
- display raw email text
- display cookies, sessions, CSRF token values, credentials, or real config values

## Upload path

```text
public_html/gov.cabnet.app/ops/mobile-submit-evidence-review.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/mobile-submit-evidence-review.php
```

## SQL

No new SQL. Uses the Phase 59 table when installed:

```text
mobile_submit_evidence_log
```

## Verification

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/mobile-submit-evidence-review.php
```

Expected:

```text
No syntax errors detected
```

Open:

```text
https://gov.cabnet.app/ops/mobile-submit-evidence-review.php
```

Expected:

- login required
- shared ops shell loads
- evidence log status displays
- saved evidence rows display if present
- selected record detail displays sanitized/redacted JSON
- no live submit controls exist

## Commit

Title:

```text
Add mobile submit evidence review
```

Description:

```text
Adds a read-only Mobile Submit Evidence Review page for sanitized dry-run evidence records. The page summarizes saved evidence status, EDXEIX IDs, starting point coverage, email hashes, and redacted evidence JSON without displaying raw email text or secrets.

The production pre-ride email tool remains unchanged. No Bolt calls, EDXEIX calls, AADE calls, database writes, queue staging, live submission behavior, cookie output, session output, CSRF token output, credential output, or real config value output are added.
```
