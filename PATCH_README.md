# gov.cabnet.app Patch — Ops UI Shell Phase 5 Activity Logs

## What changed

Adds admin-only read-only activity visibility pages to the unified `/ops` GUI:

- `audit-log.php` for `ops_audit_log` events.
- `login-attempts.php` for `ops_login_attempts` visibility.
- Updates shared shell navigation to expose Audit Log and Login Attempts for admin users.
- Adds shared CSS for log filters, result badges, and compact tables.

## Files included

```text
public_html/gov.cabnet.app/assets/css/gov-ops-shell.css
public_html/gov.cabnet.app/ops/_shell.php
public_html/gov.cabnet.app/ops/audit-log.php
public_html/gov.cabnet.app/ops/login-attempts.php
docs/OPS_UI_SHELL_PHASE5_ACTIVITY_LOGS_2026_05_11.md
PATCH_README.md
```

## Upload paths

```text
public_html/gov.cabnet.app/assets/css/gov-ops-shell.css
→ /home/cabnet/public_html/gov.cabnet.app/assets/css/gov-ops-shell.css

public_html/gov.cabnet.app/ops/_shell.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/_shell.php

public_html/gov.cabnet.app/ops/audit-log.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/audit-log.php

public_html/gov.cabnet.app/ops/login-attempts.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/login-attempts.php
```

## SQL to run

None.

Uses existing tables:

```text
ops_users
ops_login_attempts
ops_audit_log
```

## Verification commands

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/_shell.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/audit-log.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/login-attempts.php
```

## Verification URLs

```text
https://gov.cabnet.app/ops/audit-log.php
https://gov.cabnet.app/ops/login-attempts.php
```

## Expected result

- Pages require login.
- Pages require an admin role.
- Audit Log shows recent `ops_audit_log` events.
- Login Attempts shows recent `ops_login_attempts` events.
- No records are written by these pages.
- Production pre-ride email tool remains unchanged.

## Git commit title

```text
Add ops audit and login attempt visibility
```

## Git commit description

```text
Continues the unified EDXEIX-style /ops GUI by adding admin-only read-only pages for operator activity audit events and login attempt visibility. Updates the shared shell navigation and CSS for log filters and result tables.

The production pre-ride email tool remains unchanged. No Bolt calls, EDXEIX calls, AADE calls, queue staging, database writes, or live submission behavior are added.
```
