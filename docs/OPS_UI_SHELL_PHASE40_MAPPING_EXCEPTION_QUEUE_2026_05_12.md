# Ops UI Shell Phase 40 — Mapping Exception Queue — 2026-05-12

Adds a read-only Mapping Exception Queue for Bolt → EDXEIX mapping governance.

## Added

- `/ops/mapping-exceptions.php`
- Updated `/ops/_mapping_nav.php`

## Purpose

The page prioritizes mapping failures that can break the production workflow, especially the confirmed class of issue where company, driver, and vehicle mappings are correct but the starting point falls back to a wrong global value.

## Safety

- No Bolt calls
- No EDXEIX calls
- No AADE calls
- No database writes
- No live submission behavior
- Production pre-ride route unchanged

## Checks

- Missing lessor-specific starting point override
- Multiple active overrides
- Empty override ID
- Override not found in export snapshot
- Active unmapped drivers
- Active unmapped vehicles
- WHITEBLUE / 1756 verified starting point mismatch
- Missing verification register entry
