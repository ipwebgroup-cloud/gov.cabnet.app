# Phase 65 — EDXEIX Capture Field Alias Fix

## Purpose

Phase 64 proved the safety chain with an old Bolt ride-details email and saved sanitized evidence. It also revealed two capture metadata issues:

1. Bracketed EDXEIX field names such as `lessee[type]` were converted into `lesseetype` by the compatibility trigger.
2. The compatibility layer did not expose `select_field_names`, so dry-run validation reported no select/dropdown field names.

This phase fixes those metadata issues without enabling live submission.

## What the SQL does

- Adds `select_field_names` as a compatibility alias if it is missing.
- Recreates the capture compatibility triggers.
- Preserves brackets inside field names.
- Normalizes the latest `/dashboard/lease-agreement` capture so optional `broker`, `lessee[vat_number]`, and `lessee[legal_representative]` do not block dry-run validation.
- Backfills aliases on existing capture rows.

## Safety contract

The SQL is metadata-only. It does not call Bolt, EDXEIX, or AADE. It does not enable live submit. It does not change normalized bookings or the production pre-ride tool.

## Verification

After running the SQL, verify:

```sql
SELECT
  id,
  required_field_names,
  select_field_names,
  coordinate_field_names,
  updated_at
FROM ops_edxeix_submit_captures
ORDER BY id DESC
LIMIT 1;
```

Expected `required_field_names` should preserve bracketed names:

```text
lessee[type]
lessee[name]
```

Expected `select_field_names` should include:

```text
lessor
lessee[type]
driver
vehicle
starting_point_id
```

Then re-run `/ops/mobile-submit-trial-run.php` with the same old email. The expected blockers should narrow to the intended blockers such as `pickup_not_future`, `lessor_specific_starting_point_not_verified`, and live-submit/map/session blockers.
