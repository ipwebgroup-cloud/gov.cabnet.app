# Patch README — v3.0.82 Public Route Exposure Audit Detection Hotfix

## What changed

Updates the read-only public route exposure audit CLI so it correctly detects `.htaccess` deny rules for `.user.ini` and `_auth_prepend.php` when they appear inside escaped `FilesMatch` patterns.

## Files included

```text
gov.cabnet.app_app/cli/public_route_exposure_audit.php
docs/LIVE_PUBLIC_ROUTE_EXPOSURE_AUDIT_HTACCESS_DETECTION_HOTFIX_20260515.md
PATCH_README.md
HANDOFF.md
CONTINUE_PROMPT.md
```

## Upload path

```text
/home/cabnet/gov.cabnet.app_app/cli/public_route_exposure_audit.php
```

## SQL

None.

## Verification

```bash
php -l /home/cabnet/gov.cabnet.app_app/cli/public_route_exposure_audit.php

su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/public_route_exposure_audit.php --json" | grep -E 'v3.0.82|htaccess_denies_user_ini|warnings'
```

Expected:

```text
No syntax errors detected
version: v3.0.82-public-route-exposure-audit-htaccess-detection
htaccess_denies_user_ini: true
```

## Safety

No routes are deleted. No SQL changes are made. No Bolt, EDXEIX, AADE, or DB calls are performed. Live EDXEIX submission remains disabled and the production pre-ride tool is untouched.
