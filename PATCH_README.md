# Patch README — v3.0.81 Public Route Exposure Audit

## What changed

Adds a read-only public route exposure audit for the live gov.cabnet.app site.

The new audit checks public-root PHP endpoints and verifies the `.user.ini` / `_auth_prepend.php` global authentication posture.

## Files included

```text
gov.cabnet.app_app/cli/public_route_exposure_audit.php
public_html/gov.cabnet.app/ops/public-route-exposure-audit.php
public_html/gov.cabnet.app/ops/_shell.php
public_html/gov.cabnet.app/ops/route-index.php
docs/LIVE_PUBLIC_ROUTE_EXPOSURE_AUDIT_20260515.md
HANDOFF.md
CONTINUE_PROMPT.md
PATCH_README.md
```

## Upload paths

```text
/home/cabnet/gov.cabnet.app_app/cli/public_route_exposure_audit.php
/home/cabnet/public_html/gov.cabnet.app/ops/public-route-exposure-audit.php
/home/cabnet/public_html/gov.cabnet.app/ops/_shell.php
/home/cabnet/public_html/gov.cabnet.app/ops/route-index.php
```

## SQL

None.

## Verification commands

```bash
php -l /home/cabnet/gov.cabnet.app_app/cli/public_route_exposure_audit.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/public-route-exposure-audit.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/_shell.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/route-index.php

curl -I --max-time 10 https://gov.cabnet.app/ops/public-route-exposure-audit.php

su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/public_route_exposure_audit.php --json"

grep -n "v3.0.81\|Public Route Exposure Audit\|public_route_exposure_audit" \
  /home/cabnet/gov.cabnet.app_app/cli/public_route_exposure_audit.php \
  /home/cabnet/public_html/gov.cabnet.app/ops/public-route-exposure-audit.php \
  /home/cabnet/public_html/gov.cabnet.app/ops/_shell.php \
  /home/cabnet/public_html/gov.cabnet.app/ops/route-index.php
```

## Expected result

- Syntax checks pass.
- Ops page redirects unauthenticated users to `/ops/login.php`.
- CLI JSON returns `ok=true` if global auth-prepend posture is intact.
- `delete_recommended_now=0`.
- No live EDXEIX submission is enabled.

## Commit title

```text
Add public route exposure audit
```

## Commit description

```text
Adds a read-only public route exposure audit for the live gov.cabnet.app deployment.

The audit checks public-root PHP endpoints, global .user.ini auto_prepend authentication posture, helper-file protection, and route risk tokens for write/network/submit behavior.

Updates the ops shell and Route Index to link the audit under Developer Archive.

No routes are deleted. No SQL changes are made. No Bolt, EDXEIX, AADE, or DB calls are performed. Live EDXEIX submission remains disabled and the production pre-ride tool is untouched.
```
