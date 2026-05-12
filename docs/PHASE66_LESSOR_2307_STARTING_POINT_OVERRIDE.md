# Phase 66 — Lessor 2307 Starting Point Override

## Purpose

Adds a lessor-specific starting point override for EDXEIX lessor `2307` so the mobile/server-side dry-run gate no longer treats this lessor as a starting-point risk when the resolver returns starting point `6467495`.

## Verified before adding

- `mapping_lessor_starting_points` for lessor `2307` was empty.
- `mapping_starting_points` row `6467495` exists and is active.
- Driver `20999` belongs to lessor `2307`.
- Vehicle `13868` belongs to lessor `2307`.

## Inserted override

```text
lessor: 2307
internal_key: edra_mas
starting point: 6467495
active: 1
```

## Safety contract

This is a mapping metadata update only.

It does not:

- submit to EDXEIX
- call Bolt
- call AADE
- stage a workflow queue job
- enable live server-side submit
- modify `/ops/pre-ride-email-tool.php`

## Expected trial-run result after insert

For the old blocked test email, the result should still be blocked because the ride is in the past:

```text
NO-GO / REVIEW REQUIRED
LIVE SUBMIT BLOCKED
pickup_not_future
preflight_pickup_not_future
```

The previous blocker should disappear:

```text
lessor_specific_starting_point_not_verified
```
