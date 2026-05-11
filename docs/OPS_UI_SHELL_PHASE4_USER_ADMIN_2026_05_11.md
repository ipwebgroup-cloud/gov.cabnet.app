# Ops UI Shell Phase 4 — User Administration — 2026-05-11

This patch continues the unified `/ops` GUI and expands the user/profile area into controlled admin user management.

## Safety contract

- Does not modify `/ops/pre-ride-email-tool.php`.
- Does not call Bolt.
- Does not call EDXEIX.
- Does not call AADE.
- Does not stage jobs.
- Does not enable live EDXEIX submission.
- Does not delete users.

## Added/updated routes

- `/ops/users-control.php` — admin-only users overview with actions.
- `/ops/users-new.php` — admin-only local operator creation.
- `/ops/users-edit.php` — admin-only user metadata update and password reset.
- `/ops/_shell.php` — shared shell v1.3 with Create User navigation.

## Notes

The user administration pages manage local `ops_users` login accounts only. They do not affect driver mappings, bookings, queue state, or EDXEIX submission behavior.

At least one active admin must remain. The web UI blocks self-demotion/self-deactivation for the current admin account.
