# Ops UI Shell Phase 37 Hotfix — Mapping Verification 500 Fix

Fixes a 500 error on `/ops/mapping-verification.php` caused by schema assumptions in the EDXEIX export snapshot tables.

The page now detects table columns through `information_schema.COLUMNS` before building queries and safely falls back when optional snapshot columns are not present.

Production pre-ride workflow is unchanged.
