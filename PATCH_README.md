# gov.cabnet.app Patch — Ops UI Shell Phase 6 Activity Center

## What changed

Adds an admin-only activity center and a self-service read-only profile activity page.

Included files:

- `public_html/gov.cabnet.app/ops/_shell.php`
- `public_html/gov.cabnet.app/ops/activity-center.php`
- `public_html/gov.cabnet.app/ops/profile-activity.php`
- `public_html/gov.cabnet.app/assets/css/gov-ops-shell.css`
- `docs/OPS_UI_SHELL_PHASE6_ACTIVITY_CENTER_2026_05_11.md`

## Upload paths

Upload to:

- `/home/cabnet/public_html/gov.cabnet.app/ops/_shell.php`
- `/home/cabnet/public_html/gov.cabnet.app/ops/activity-center.php`
- `/home/cabnet/public_html/gov.cabnet.app/ops/profile-activity.php`
- `/home/cabnet/public_html/gov.cabnet.app/assets/css/gov-ops-shell.css`

## SQL

None.

Uses existing tables:

- `ops_users`
- `ops_login_attempts`
- `ops_audit_log`

## Verification

Run:

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/_shell.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/activity-center.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/profile-activity.php
```

Open:

- `https://gov.cabnet.app/ops/activity-center.php`
- `https://gov.cabnet.app/ops/profile-activity.php`

Expected:

- Both pages require login.
- Activity Center requires admin role.
- Profile Activity is available to the logged-in operator.
- No SQL syntax error.
- No production pre-ride tool changes.

## Safety

This patch does not modify `/ops/pre-ride-email-tool.php`. It does not call Bolt, EDXEIX, or AADE. It does not stage jobs or enable live submission.
