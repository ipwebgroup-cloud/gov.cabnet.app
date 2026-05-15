# gov.cabnet.app Patch — v3.0.99 Legacy Public Utility Readiness Board Navigation

## What changed

Adds a Developer Archive navigation link for:

`/ops/legacy-public-utility-readiness-board.php`

## Files included

- `public_html/gov.cabnet.app/ops/_shell.php`
- `docs/LIVE_LEGACY_PUBLIC_UTILITY_READINESS_BOARD_NAV_20260515.md`
- `PATCH_README.md`
- `HANDOFF.md`
- `CONTINUE_PROMPT.md`

## Upload path

Upload:

`public_html/gov.cabnet.app/ops/_shell.php`

to:

`/home/cabnet/public_html/gov.cabnet.app/ops/_shell.php`

## SQL

None.

## Verification

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/_shell.php

curl -I --max-time 10 https://gov.cabnet.app/ops/legacy-public-utility-readiness-board.php

grep -n "v3.0.99\|Legacy Utility Readiness Board"   /home/cabnet/public_html/gov.cabnet.app/ops/_shell.php
```

## Expected result

- No syntax errors.
- Ops page remains auth-protected.
- Developer Archive includes the Legacy Utility Readiness Board link.

## Commit title

Add legacy readiness board navigation

## Commit description

Adds a Developer Archive navigation link for the read-only Legacy Public Utility Readiness Board.

This provides supervised access to the v3.0.98 aggregate checkpoint board without changing any production or legacy utility behavior.

No routes are moved or deleted. No redirects are added. No SQL changes are made. No Bolt, EDXEIX, AADE, database, or filesystem write actions are performed.

Live EDXEIX submission remains disabled and the production pre-ride tool is untouched.
