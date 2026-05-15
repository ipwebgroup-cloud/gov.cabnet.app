# gov.cabnet.app Patch — v3.0.97 Legacy Stats Source Audit Navigation

## What changed

Adds the read-only Legacy Stats Source Audit page to the Developer Archive navigation in the shared `/ops` shell.

## Files included

- `public_html/gov.cabnet.app/ops/_shell.php`
- `docs/LIVE_LEGACY_PUBLIC_UTILITY_STATS_SOURCE_NAV_20260515.md`
- `HANDOFF.md`
- `CONTINUE_PROMPT.md`

## Upload path

Upload:

`public_html/gov.cabnet.app/ops/_shell.php`

to:

`/home/cabnet/public_html/gov.cabnet.app/ops/_shell.php`

Docs/continuity files are for the repository.

## SQL

None.

## Verification commands

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/_shell.php

curl -I --max-time 10 https://gov.cabnet.app/ops/legacy-public-utility-stats-source-audit.php

grep -n "v3.0.97\|Legacy Stats Source Audit" \
  /home/cabnet/public_html/gov.cabnet.app/ops/_shell.php
```

## Expected result

- No syntax errors.
- Unauthenticated curl returns `302` to `/ops/login.php`.
- Developer Archive shows `Legacy Stats Source Audit` after login.

## Safety

No route moves, no route deletions, no redirects, no SQL changes, no Bolt calls, no EDXEIX calls, no AADE calls. Production Pre-Ride Tool remains untouched.
