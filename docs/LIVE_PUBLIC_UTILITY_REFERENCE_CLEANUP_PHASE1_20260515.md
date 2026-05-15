# Live Public Utility Reference Cleanup — Phase 1

Date: 2026-05-15
Version: v3.0.87-public-utility-reference-cleanup-phase1

## Scope

This is a no-delete, no-move cleanup phase for guarded public-root utility endpoints.

It removes direct operator/documentation links to legacy public-root utilities from selected legacy ops pages and operator docs, replacing them with safer references to:

- `/ops/public-utility-relocation-plan.php`
- `/ops/public-route-exposure-audit.php`

## Safety contract

- No production pre-ride tool changes.
- No public-root utility file deletion.
- No public-root utility relocation.
- No SQL.
- No DB calls.
- No Bolt call.
- No EDXEIX call.
- No AADE call.
- Live EDXEIX submission remains disabled.

## Files adjusted

- `/ops/bolt-live.php`
- `/ops/jobs.php`
- `/ops/submit.php`
- `/ops/test-booking.php`
- `/ops/help.php`
- `/ops/future-test.php`
- `docs/OPS_SITEMAP_V3.md`
- `docs/NOVICE_OPERATOR_GUIDE.md`
- `docs/DRY_RUN_TEST_BOOKING_HARNESS.md`

## Result

Legacy public-root endpoints remain available behind auth for compatibility, but operator-facing guidance now points to the relocation/audit pages first.

This reduces accidental use of old guarded utility routes while preserving compatibility until dependency references are fully reviewed.
