# Ops UI Shell Phase 35 — Mapping Governance

Adds focused mapping governance pages to prevent failures like a correct lessor/driver/vehicle pair being combined with a wrong starting point fallback.

## New routes

- `/ops/company-mapping-detail.php?lessor=1756`
- `/ops/starting-point-control.php`
- `/ops/starting-point-control.php?lessor=1756`

## Safety

- Does not modify `/ops/pre-ride-email-tool.php`.
- Does not call Bolt.
- Does not call EDXEIX.
- Does not call AADE.
- Does not stage jobs.
- Does not enable live EDXEIX submission.

`company-mapping-detail.php` is read-only.

`starting-point-control.php` allows admin-only local edits to `mapping_lessor_starting_points` only, with CSRF validation and audit attempt when `ops_audit_log` is available.

## Why

The WHITEBLUE incident showed that global fallback starting point rows can be dangerous. Operational lessors should have explicit rows in `mapping_lessor_starting_points`.
