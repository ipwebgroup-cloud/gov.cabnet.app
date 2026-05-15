# HANDOFF — gov.cabnet.app Bolt → EDXEIX Bridge

Updated: 2026-05-15  
Milestone: v3.0.81 public route exposure audit prepared

## Current live state

- Production pre-ride tool remains `/ops/pre-ride-email-tool.php` and was not modified.
- V3 live EDXEIX submission remains disabled.
- V3 live adapter remains skeleton-only and non-live.
- Handoff Center package hygiene was hardened through v3.0.77/v3.0.78.
- Ops navigation was de-bloated through v3.0.80 without deleting routes.

## New prepared patch

v3.0.81 adds a read-only public route exposure audit:

```text
/home/cabnet/gov.cabnet.app_app/cli/public_route_exposure_audit.php
/home/cabnet/public_html/gov.cabnet.app/ops/public-route-exposure-audit.php
```

The audit checks:

- public-root PHP endpoints,
- `.user.ini` auto-prepend authentication posture,
- `_auth_prepend.php` login guard markers,
- `.htaccess` helper-file deny posture,
- write/network/submit/stage/sync route tokens,
- route classification and no-delete recommendations.

## Safety

The audit is read-only:

```text
No Bolt call.
No EDXEIX call.
No AADE call.
No DB connection.
No filesystem writes.
No route deletion.
No live-submit enablement.
```

## Next verification

```bash
php -l /home/cabnet/gov.cabnet.app_app/cli/public_route_exposure_audit.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/public-route-exposure-audit.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/_shell.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/route-index.php

curl -I --max-time 10 https://gov.cabnet.app/ops/public-route-exposure-audit.php

su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/public_route_exposure_audit.php --json"
```
