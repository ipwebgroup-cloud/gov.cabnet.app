# gov.cabnet.app Patch — Ops UI Shell Phase 4 User Administration

## What changed

Adds controlled, admin-only user administration pages for the `/ops` login system:

- Create local operator accounts.
- Edit user metadata/role/status.
- Reset user passwords.
- View recent login attempts.
- Keep delete actions unavailable.
- Keep workflow safety separate from user access management.

## Files included

```text
public_html/gov.cabnet.app/assets/css/gov-ops-shell.css
public_html/gov.cabnet.app/ops/_shell.php
public_html/gov.cabnet.app/ops/users-control.php
public_html/gov.cabnet.app/ops/users-new.php
public_html/gov.cabnet.app/ops/users-edit.php
docs/OPS_UI_SHELL_PHASE4_USER_ADMIN_2026_05_11.md
PATCH_README.md
```

## Upload paths

```text
public_html/gov.cabnet.app/assets/css/gov-ops-shell.css
→ /home/cabnet/public_html/gov.cabnet.app/assets/css/gov-ops-shell.css

public_html/gov.cabnet.app/ops/_shell.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/_shell.php

public_html/gov.cabnet.app/ops/users-control.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/users-control.php

public_html/gov.cabnet.app/ops/users-new.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/users-new.php

public_html/gov.cabnet.app/ops/users-edit.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/users-edit.php
```

## SQL

None. Uses existing tables:

```text
ops_users
ops_login_attempts
ops_audit_log
```

## Verification commands

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/_shell.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/users-control.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/users-new.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/users-edit.php
```

## Verification URLs

```text
https://gov.cabnet.app/ops/users-control.php
https://gov.cabnet.app/ops/users-new.php
https://gov.cabnet.app/ops/users-edit.php?id=1
```

Expected:

- all pages require login
- user admin pages require `admin` role
- admin can create operator/viewer/admin accounts
- admin can edit account metadata and reset passwords
- no delete action exists
- at least one active admin remains
- `/ops/pre-ride-email-tool.php` remains unchanged

## Git commit title

```text
Add ops user administration pages
```

## Git commit description

```text
Continues the unified EDXEIX-style /ops GUI by adding admin-only local user management pages for the new login system. Admins can create operator accounts, edit metadata and roles, toggle active status, and reset passwords. Delete actions are intentionally unavailable and at least one active admin must remain.

The production pre-ride email tool remains unchanged. No Bolt calls, EDXEIX calls, AADE calls, queue staging, or live submission behavior are added.
```
