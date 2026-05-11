# Ops UI Shell Phase 7 — Profile Edit — 2026-05-11

Adds a safe self-service profile edit page for logged-in operators.

## Added/updated

- `/ops/profile-edit.php` lets the logged-in operator update display name and email only.
- `/ops/profile.php` now links to Edit Profile, Change Password, My Activity, and the production pre-ride tool.
- `/ops/_shell.php` adds Edit Profile to the user area navigation and bumps the shell CSS cache key to `v1.7`.
- `/assets/css/gov-ops-shell.css` adds form layout helpers.

## Safety

- Does not modify `/ops/pre-ride-email-tool.php`.
- Does not call Bolt.
- Does not call EDXEIX.
- Does not call AADE.
- Does not stage jobs.
- Does not enable live EDXEIX submission.
- Only updates the logged-in user's `display_name` and `email` in `ops_users`.
- Records a lightweight `profile_updated` event in `ops_audit_log` when available.

## Verification

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/_shell.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/profile.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/profile-edit.php
```

Open:

- `https://gov.cabnet.app/ops/profile.php`
- `https://gov.cabnet.app/ops/profile-edit.php`
- `https://gov.cabnet.app/ops/profile-activity.php`
