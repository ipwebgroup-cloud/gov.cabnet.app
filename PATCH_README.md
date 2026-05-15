# Patch README — v3.0.90 Legacy Public Utility Navigation Links

## What changed

Updates the shared ops shell to add Developer Archive links for:

- Public Utility Phase 2 Preview
- Legacy Public Utility Wrapper

This is a navigation-only patch. It does not execute legacy utilities and does not change production routes.

## Upload path

Upload:

```text
public_html/gov.cabnet.app/ops/_shell.php
```

to:

```text
/home/cabnet/public_html/gov.cabnet.app/ops/_shell.php
```

## SQL

None.

## Verification

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/_shell.php

curl -I --max-time 10 https://gov.cabnet.app/ops/legacy-public-utility.php
curl -I --max-time 10 https://gov.cabnet.app/ops/public-utility-reference-cleanup-phase2-preview.php

grep -n "v3.0.90\|Legacy Public Utility Wrapper\|Public Utility Phase 2 Preview" \
  /home/cabnet/public_html/gov.cabnet.app/ops/_shell.php
```

Expected: syntax passes, unauthenticated requests redirect to `/ops/login.php`, and the Developer Archive shows both links after login.

## Safety

No route moves, route deletions, redirects, DB writes, external calls, or live-submit changes.
