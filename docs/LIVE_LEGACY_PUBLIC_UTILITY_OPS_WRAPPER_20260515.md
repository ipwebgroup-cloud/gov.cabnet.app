# gov.cabnet.app — Legacy Public Utility Ops Wrapper v3.0.89

Date: 2026-05-15

## Purpose

Adds a safe `/ops` landing wrapper for legacy guarded public-root utility endpoints.

This is a preparation step after the Phase 2 reference cleanup preview showed:

- `ok=true`
- `version=v3.0.88-public-utility-reference-cleanup-phase2-preview`
- `actionable=32`
- `safe_phase2=0`
- `final_blocks=[]`

Because `safe_phase2=0`, no route should be moved or deleted yet.

## Added routes

- `/ops/legacy-public-utility.php`

## Added private helper

- `/home/cabnet/gov.cabnet.app_app/src/Support/LegacyPublicUtilityRegistry.php`

## Safety

The wrapper and registry are metadata-only:

- No Bolt call
- No EDXEIX call
- No AADE call
- No database connection
- No filesystem writes
- No route moves
- No route deletions
- No redirects
- No inclusion/execution of legacy public-root utility files

## Registered legacy utilities

- `/bolt-api-smoke-test.php`
- `/bolt-fleet-orders-watch.php`
- `/bolt_stage_edxeix_jobs.php`
- `/bolt_submission_worker.php`
- `/bolt_sync_orders.php`
- `/bolt_sync_reference.php`

## Recommended next step

Use this wrapper as a stable `/ops` target for later reference cleanup. Do not move or delete public-root utilities until dependency checks, wrappers/CLI equivalents, and Andreas approval are complete.
