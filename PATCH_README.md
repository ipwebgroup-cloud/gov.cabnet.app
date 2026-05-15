# gov.cabnet.app patch — v3.1.8 shell note typo cleanup

## What changed

This patch replaces `public_html/gov.cabnet.app/ops/_shell.php` with a corrected v3.1.8 shared ops shell.

It fixes only text in the sidebar note:

- `legacystats source audit navigation` -> `legacy stats source audit navigation`
- `added inv3.1.6` -> `added in v3.1.6`

The v3.1.6 Next Candidate Watch navigation links remain unchanged.

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

curl -I --max-time 10 https://gov.cabnet.app/ops/pre-ride-email-v3-next-real-mail-candidate-watch.php

echo "GOOD MARKERS:"
grep -n "v3.1.8\|legacy stats source audit navigation\|added in v3.1.6" /home/cabnet/public_html/gov.cabnet.app/ops/_shell.php

echo "BAD MARKERS:"
grep -n "legacystats\|inv3.1.6" /home/cabnet/public_html/gov.cabnet.app/ops/_shell.php || echo "OLD TYPO NOT FOUND"
```

Expected:

- No syntax errors
- HTTP 302 to `/ops/login.php` when unauthenticated
- Good markers present
- Bad markers returns `OLD TYPO NOT FOUND`

## Safety

No route behavior changes. No routes moved or deleted. No redirects. No SQL. No DB writes. No queue mutations. No Bolt, EDXEIX, or AADE calls. Live EDXEIX submission remains disabled.
