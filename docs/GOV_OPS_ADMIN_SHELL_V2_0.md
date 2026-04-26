# GOV Ops Admin Shell v2.0

Adds EDXEIX-style read-only companion pages for the remaining administration area.

## New pages

- `/ops/admin-control.php`
- `/ops/readiness-control.php`
- `/ops/mapping-control.php`
- `/ops/jobs-control.php`

## Why companion pages?

The existing `/ops/mappings.php` page contains guarded POST/audit behavior. To reduce risk, v2.0 does not replace the original editor. It adds read-only companion pages that mimic the EDXEIX-style shell while preserving the original operational pages.

## Safety

No SQL.
No live EDXEIX submission.
No Bolt calls added.
No EDXEIX calls added.
No queue staging.
No mapping updates.
No workflow rule changes.
