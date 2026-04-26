# Bolt Evidence Bundle v1.3

## Purpose

The Evidence Bundle adds a read-only session report for the next real future Bolt ride test.

It consolidates:

- readiness status
- mapped driver/vehicle counts
- candidate readiness
- sanitized Bolt API Visibility JSONL snapshots
- stage coverage for accepted/assigned, pickup/waiting, trip started, and completed
- watch match status for driver, vehicle, and optional order fragment
- copy/paste recap for chat/debugging

## Safety contract

`/ops/evidence-bundle.php` is read-only.

It does not:

- call Bolt
- call EDXEIX
- stage jobs
- update mappings
- write database rows
- store raw payloads
- enable live submission

It reads existing sanitized timeline entries created by `/ops/bolt-api-visibility.php` and `/ops/dev-accelerator.php`.

## URL

```text
https://gov.cabnet.app/ops/evidence-bundle.php
```

JSON output:

```text
https://gov.cabnet.app/ops/evidence-bundle.php?format=json
```

Inspect a specific date:

```text
https://gov.cabnet.app/ops/evidence-bundle.php?date=2026-04-26
```

## Recommended usage during the real Bolt test

1. Open `/ops/dev-accelerator.php`.
2. Capture `Accepted / Assigned` after Filippos accepts/is assigned.
3. Capture `Pickup / Waiting` after arrival or pickup.
4. Capture `Trip Started` when the trip is in progress.
5. Capture `Completed` after the ride completes.
6. Open `/ops/evidence-bundle.php` and review the timeline summary.
7. Open preflight JSON only if a real future candidate appears.
8. Stop before live submission.

## Expected evidence

For a complete test, the Evidence Bundle should show at least one snapshot for each stage:

- accepted-assigned
- pickup-waiting
- trip-started
- completed

Useful indicators:

- `Max orders seen > 0`
- `Max local rows > 0`
- driver match YES or vehicle match YES
- readiness verdict still safe
- real future candidate appears before preflight review

## Current safety posture

Live EDXEIX submission remains disabled and is not used by this patch.
