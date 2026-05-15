# Live Legacy Public Utility Readiness Board Navigation — 2026-05-15

## Version

v3.0.99-legacy-public-utility-readiness-board-navigation

## Purpose

Adds the read-only Legacy Public Utility Readiness Board to the Developer Archive navigation.

## Safety

- Navigation-only change.
- No route moves.
- No route deletions.
- No redirects.
- No SQL changes.
- No Bolt calls.
- No EDXEIX calls.
- No AADE calls.
- Production Pre-Ride Tool remains untouched.
- Live EDXEIX submission remains disabled.

## File changed

- `public_html/gov.cabnet.app/ops/_shell.php`

## Verification

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/_shell.php

curl -I --max-time 10 https://gov.cabnet.app/ops/legacy-public-utility-readiness-board.php

grep -n "v3.0.99\|Legacy Utility Readiness Board"   /home/cabnet/public_html/gov.cabnet.app/ops/_shell.php
```

Expected:

- Syntax passes.
- Unauthenticated request redirects to `/ops/login.php`.
- `v3.0.99` marker is present.
- `Legacy Utility Readiness Board` link is present.
