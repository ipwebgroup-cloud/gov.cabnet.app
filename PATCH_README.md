# gov.cabnet.app patch — Ops UI Shell Phase 3 User Area

## What changed

Adds the next safe user/profile layer for the unified `/ops` GUI:

- shared shell v1.2 with user area navigation,
- updated profile dashboard,
- self-service Change Password page,
- admin-only read-only Users Control page,
- CSS additions for profile/password/user list views.

## Files included

```text
public_html/gov.cabnet.app/assets/css/gov-ops-shell.css
public_html/gov.cabnet.app/ops/_shell.php
public_html/gov.cabnet.app/ops/profile.php
public_html/gov.cabnet.app/ops/profile-password.php
public_html/gov.cabnet.app/ops/users-control.php
docs/OPS_UI_SHELL_PHASE3_USER_AREA_2026_05_11.md
PATCH_README.md
```

## Upload paths

```text
public_html/gov.cabnet.app/assets/css/gov-ops-shell.css
→ /home/cabnet/public_html/gov.cabnet.app/assets/css/gov-ops-shell.css

public_html/gov.cabnet.app/ops/_shell.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/_shell.php

public_html/gov.cabnet.app/ops/profile.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/profile.php

public_html/gov.cabnet.app/ops/profile-password.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/profile-password.php

public_html/gov.cabnet.app/ops/users-control.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/users-control.php
```

## SQL to run

None. This uses the existing `ops_users`, `ops_login_attempts`, and `ops_audit_log` tables.

## Verify

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/_shell.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/profile.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/profile-password.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/users-control.php
```

## URLs

```text
https://gov.cabnet.app/ops/profile.php
https://gov.cabnet.app/ops/profile-password.php
https://gov.cabnet.app/ops/users-control.php
```

## Safety

This patch does not modify `/ops/pre-ride-email-tool.php`.
It does not call Bolt, EDXEIX, or AADE.
It does not stage jobs or enable live submission.
