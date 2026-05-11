# Ops UI Shell Phase 14 — Operator Guides — 2026-05-11

Adds read-only operator guidance pages inside the shared /ops GUI.

## Added routes

- `/ops/workflow-guide.php`
- `/ops/safety-checklist.php`

## Updated route

- `/ops/_shell.php`

## Safety

- Does not modify `/ops/pre-ride-email-tool.php`.
- Does not call Bolt.
- Does not call EDXEIX.
- Does not call AADE.
- Does not write database rows.
- Does not stage queue jobs.
- Does not enable live submission.

## Production rule

`/ops/pre-ride-email-tool.php` remains the production page.
Development continues through dedicated V2/support pages.
