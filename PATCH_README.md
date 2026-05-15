# PATCH README — v3.0.80 Navigation De-Bloat

## What changed

Adds a no-delete navigation de-bloat update for the live gov.cabnet.app `/ops` shell.

Daily operator navigation is now shorter. V3 proof/readiness tools remain visible. Dev/test/mobile/evidence/package/helper pages remain available under a collapsed Developer Archive and the Route Index.

## Files included

```text
public_html/gov.cabnet.app/ops/_shell.php
public_html/gov.cabnet.app/ops/route-index.php
docs/LIVE_NAVIGATION_DEBLOAT_20260515.md
HANDOFF.md
CONTINUE_PROMPT.md
PATCH_README.md
```

## Upload paths

```text
/home/cabnet/public_html/gov.cabnet.app/ops/_shell.php
/home/cabnet/public_html/gov.cabnet.app/ops/route-index.php
```

## SQL

None.

## Verification commands

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/_shell.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/route-index.php
curl -I --max-time 10 https://gov.cabnet.app/ops/route-index.php
curl -I --max-time 10 https://gov.cabnet.app/ops/handoff-center.php
grep -n "v3.0.80\|Developer archive\|Live Route Inventory" \
  /home/cabnet/public_html/gov.cabnet.app/ops/_shell.php \
  /home/cabnet/public_html/gov.cabnet.app/ops/route-index.php
```

Expected unauthenticated curl result:

```text
HTTP/1.1 302 Found
Location: /ops/login.php?next=...
```

## Expected browser result

After login:

- Sidebar is reduced and daily-focused.
- Developer Archive is collapsed by default.
- V3 proof/readiness links remain visible.
- Route Index shows the static live route inventory.

## Safety

No routes are deleted. No SQL changes are made. No live EDXEIX submit is enabled. No V0 workflow is changed.
