# gov.cabnet.app — Live Navigation De-Bloat Patch

Date: 2026-05-15  
Patch: v3.0.80-navigation-debloat

## Purpose

This patch tidies the live `/ops` operator shell without deleting routes, changing database state, enabling EDXEIX live submission, or touching V0 production workflows.

The live audit showed that the app is functional but too wide for daily operators. The main risk is operator clutter, not current live-submit exposure.

## What changed

- Reduced the default sidebar to daily operational pages.
- Kept V3 proof/readiness tools visible under a dedicated V3 proof section.
- Moved V2/dev/test/mobile/evidence/package/helper tools into a collapsible Developer Archive section.
- Rebuilt `/ops/route-index.php` as a static live route inventory using the private Sophion audit classification.
- Added JSON export to `/ops/route-index.php?format=json`.

## Safety

- No routes were deleted.
- No SQL changes were made.
- No DB cleanup was performed.
- No Bolt call is introduced.
- No EDXEIX call is introduced.
- No AADE call is introduced.
- Live submit remains disabled.

## Verification

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/_shell.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/route-index.php
curl -I --max-time 10 https://gov.cabnet.app/ops/route-index.php
curl -I --max-time 10 https://gov.cabnet.app/ops/handoff-center.php
```

Expected unauthenticated result is HTTP 302 to `/ops/login.php`.

After login, verify:

- Sidebar is shorter and daily-focused.
- Developer Archive is collapsed by default.
- V3 proof/readiness links are still available.
- Route Index loads and lists the live route inventory.
