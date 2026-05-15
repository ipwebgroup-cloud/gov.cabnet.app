# Live Public Utility Reference Cleanup Preview Noise Filter — 2026-05-15

## Purpose

The v3.0.89 legacy wrapper and v3.0.90 navigation patch intentionally added references to legacy public utility filenames in the wrapper registry, wrapper page, navigation, and milestone documentation.

The Phase 2 preview is a cleanup scanner, so these intentional inventory/wrapper references should not be counted as actionable cleanup work.

## Change

v3.0.91 updates the read-only Phase 2 preview scanner to ignore:

- `/ops/legacy-public-utility.php`
- `/ops/_shell.php`
- `/gov.cabnet.app_app/src/Support/LegacyPublicUtilityRegistry.php`
- `docs/LIVE_LEGACY_PUBLIC_UTILITY_*`

It also labels ignored references as inventory/planner/wrapper/audit references.

## Safety

- No route moves.
- No route deletions.
- No redirects.
- No database connection.
- No filesystem writes.
- No Bolt call.
- No EDXEIX call.
- No AADE call.
- Production Pre-Ride Tool untouched.
