# Ops UI Shell Phase 20 — Top Navigation Dropdowns — 2026-05-11

This patch polishes the shared `/ops` top navigation by replacing the long wrapping top menu with compact CSS-only dropdown groups.

## Safety

- Production pre-ride tool is not modified.
- No Bolt calls.
- No EDXEIX calls.
- No AADE calls.
- No database writes.
- No queue staging.
- No live submission changes.

## Changed file

- `public_html/gov.cabnet.app/ops/_shell.php`

## Behavior

Top navigation now groups routes into:

- Home / My Start
- Pre-Ride
- Workflow
- Helper
- Docs
- Admin, for admin users only
- Profile

The sidebar remains unchanged and continues to provide full route access.
