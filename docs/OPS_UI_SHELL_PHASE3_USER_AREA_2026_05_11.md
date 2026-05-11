# Ops UI Shell Phase 3 User Area — 2026-05-11

Adds the next safe user-profile layer for the unified `/ops` GUI.

## Scope

- Update shared shell to v1.2.
- Add Change Password route for the logged-in operator.
- Update Profile page with user-area actions.
- Add admin-only Users Control page in read-only mode.

## Safety

- Does not modify `/ops/pre-ride-email-tool.php`.
- Does not call Bolt.
- Does not call EDXEIX.
- Does not call AADE.
- Does not stage jobs.
- Does not enable live EDXEIX submission.
- Users Control is read-only; user creation remains CLI-controlled.

## New URLs

- `/ops/profile-password.php`
- `/ops/users-control.php` admin only

## Password change behavior

The password form:

- requires current password confirmation,
- uses CSRF validation through the existing `OpsAuth` session,
- requires at least 10 characters,
- writes only `ops_users.password_hash`,
- records audit rows when possible.
