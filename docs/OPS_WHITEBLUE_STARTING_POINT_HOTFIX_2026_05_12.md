# WHITEBLUE Starting Point Hotfix — 2026-05-12

## Purpose

Fix the WHITEBLUE / EDXEIX lessor `1756` starting point resolution after live EDXEIX verification showed the correct `starting_point_id` is `612164`, not the global fallback `464343` or older local defaults.

## Confirmed live EDXEIX facts

- Lessor: `WHITEBLUE PREMIUM E E`
- Lessor ID: `1756`
- Driver shown under lessor: `ΤΣΑΤΣΑΣ ΓΕΩΡΓΙΟΣ`
- Driver ID: `4382`
- Vehicle: `XZO1837`
- Vehicle ID: `4327`
- Correct starting point: `Ομβροδέκτης, Κοινότητα Μυκόνου, Mykonos 84600`
- Correct starting point ID: `612164`

## Files

- `gov.cabnet.app_app/src/BoltMail/EdxeixMappingLookup.php`
- `gov.cabnet.app_sql/2026_05_12_whiteblue_starting_point_612164.sql`

## Behavior change

`EdxeixMappingLookup` now resolves the starting point after the EDXEIX lessor has been determined.

Resolution order:

1. `mapping_lessor_starting_points` for the resolved `edxeix_lessor_id`.
2. `mapping_starting_points` global fallback only if no lessor-specific row exists.

The lookup `ok` result now requires a starting point ID as part of the ready state.

## Safety

- No production route is modified.
- No EDXEIX call is made.
- No AADE behavior is touched.
- Global starting point defaults are not changed.
- The SQL only adds/refreshes a lessor-specific WHITEBLUE row.
