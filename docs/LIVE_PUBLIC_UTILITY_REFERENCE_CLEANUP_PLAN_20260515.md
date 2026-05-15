# gov.cabnet.app — Live Public Utility Reference Cleanup Plan

Date: 2026-05-15
Version: v3.0.86-public-utility-reference-cleanup-plan

## Purpose

This patch enhances the existing read-only Public Utility Relocation Plan so it now includes a no-break reference cleanup plan for the six guarded public-root Bolt/EDXEIX utility endpoints.

The goal is to reduce dependency on public-root utility URLs without moving or deleting routes prematurely.

## Target public-root utilities

- `/bolt-api-smoke-test.php`
- `/bolt-fleet-orders-watch.php`
- `/bolt_stage_edxeix_jobs.php`
- `/bolt_submission_worker.php`
- `/bolt_sync_orders.php`
- `/bolt_sync_reference.php`

## What changed

The planner now groups blocking references by kind:

- ops/admin page references
- server documentation references
- private app code references
- public-root code references
- other project references

It also adds a staged cleanup sequence:

1. Documentation cleanup first.
2. Ops link review second.
3. Private CLI equivalents third.
4. Authenticated compatibility stubs fourth.
5. Quiet-period removal review last.

## Safety

No routes are moved.
No routes are deleted.
No files are written by the planner.
No SQL changes are made.
No database connection is opened.
No Bolt call is made.
No EDXEIX call is made.
No AADE call is made.
Live EDXEIX submission remains disabled.
The production pre-ride tool is untouched.

## Recommended interpretation

Because dependency checks found active ops/docs/code references, the six public-root utility endpoints should remain in place for now.

The next cleanup should be documentation and operator guidance cleanup, not route relocation.
