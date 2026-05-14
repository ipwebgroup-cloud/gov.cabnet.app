# V3 Queue Focus

Version: v3.0.42-v3-queue-focus

## Purpose

Adds a read-only V3 queue focus page for fast operator visibility.

URL:

```text
/ops/pre-ride-email-v3-queue-focus.php
```

The page shows:

- queue status metrics
- newest V3 queue row
- pickup time and minutes until pickup
- driver / vehicle / lessor / starting point IDs
- status distribution
- latest 25 V3 queue rows
- last_error previews

## Safety boundary

This page is V3-only and read-only.

It does not:

- call Bolt
- call EDXEIX
- call AADE
- write database rows
- change cron behavior
- touch the V0 laptop/manual production helper
- enable live-submit

## Operational posture

V0 remains the laptop/manual helper. V3 remains the server-side development/automation path.

Operator judgment stays with Andreas. The queue focus page is only a visibility screen.
