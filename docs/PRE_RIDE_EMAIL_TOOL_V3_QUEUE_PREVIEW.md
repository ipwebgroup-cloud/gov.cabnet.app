# Pre-Ride Email Tool V3 — Dry-Run Queue Preview

This patch advances the isolated V3 tool toward the final queue workflow without enabling DB writes or EDXEIX submission.

## Route

```text
https://gov.cabnet.app/ops/pre-ride-email-toolv3.php
```

## Safety

- Does not modify `/ops/pre-ride-email-tool.php`.
- Does not write to `submission_jobs` or `submission_attempts`.
- Does not create any V3 queue rows.
- Does not call EDXEIX server-side.
- Does not call AADE.
- Does not mark Maildir emails as processed.

## What it adds

The V3 page now builds a dry-run queue preview from recent Maildir candidates.

For each candidate it shows:

- whether the candidate would queue or be blocked,
- deterministic V3 dedupe key,
- mapped lessor/driver/vehicle/starting-point IDs,
- pickup time and minutes until pickup,
- block reasons for unsafe candidates,
- copyable JSON preview for future queue-table development.

## Purpose

This prepares the future queue stage while keeping the system in read-only/preflight mode.

Future queue work should use separate V3 queue tables first, not production queue tables, unless explicitly approved.
