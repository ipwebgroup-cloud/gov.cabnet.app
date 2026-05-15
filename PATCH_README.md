# gov.cabnet.app patch — v3.1.3 Real-Mail Expiry Reason Audit Navigation

## What changed

Adds navigation links for the read-only V3 Real-Mail Expiry Reason Audit page:

- `/ops/pre-ride-email-v3-real-mail-expiry-reason-audit.php`

The link is added to:

- Pre-Ride top dropdown
- Daily Operations sidebar

## Files included

- `public_html/gov.cabnet.app/ops/_shell.php`
- `docs/V3_REAL_MAIL_EXPIRY_REASON_AUDIT_NAV_20260515.md`
- `PATCH_README.md`
- `HANDOFF.md`
- `CONTINUE_PROMPT.md`

## Upload path

Upload:

- `public_html/gov.cabnet.app/ops/_shell.php`

To:

- `/home/cabnet/public_html/gov.cabnet.app/ops/_shell.php`

## SQL

None.

## Verification

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/_shell.php

curl -I --max-time 10 https://gov.cabnet.app/ops/pre-ride-email-v3-real-mail-expiry-reason-audit.php

grep -n "v3.1.3\|Expiry Reason Audit\|real-mail expiry reason audit navigation" \
  /home/cabnet/public_html/gov.cabnet.app/ops/_shell.php
```

Expected:

- No syntax errors.
- HTTP 302 to `/ops/login.php` when unauthenticated.
- v3.1.3 marker present.
- Expiry Reason Audit navigation links present.

## Safety

Navigation only. No routes moved/deleted/redirected. No SQL. No DB writes. No queue mutation. No Bolt/EDXEIX/AADE calls.
