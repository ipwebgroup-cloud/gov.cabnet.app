# Patch README — v3.1.1 V3 Real-Mail Queue Health Navigation

## What changed

Adds navigation links for the read-only V3 Real-Mail Queue Health audit:

- Top Pre-Ride dropdown: `V3 Real-Mail Queue Health`
- Daily operations sidebar: `Real-Mail Queue Health`

## Files included

- `public_html/gov.cabnet.app/ops/_shell.php`
- `docs/V3_REAL_MAIL_QUEUE_HEALTH_NAV_20260515.md`
- `PATCH_README.md`
- `HANDOFF.md`
- `CONTINUE_PROMPT.md`

## Upload path

Upload:

- `public_html/gov.cabnet.app/ops/_shell.php`

to:

- `/home/cabnet/public_html/gov.cabnet.app/ops/_shell.php`

Docs/continuity files are for the repo.

## SQL

None.

## Verification

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/_shell.php
curl -I --max-time 10 https://gov.cabnet.app/ops/pre-ride-email-v3-real-mail-queue-health.php
grep -n "v3.1.1\|Real-Mail Queue Health\|real-mail queue health navigation" /home/cabnet/public_html/gov.cabnet.app/ops/_shell.php
```

## Expected result

- No syntax errors.
- HTTP 302 to `/ops/login.php` when unauthenticated.
- v3.1.1 markers present.
- The Real-Mail Queue Health page appears in the Pre-Ride dropdown and daily sidebar after login.

## Commit title

Add V3 real-mail queue health navigation

## Commit description

Adds navigation links for the read-only V3 Real-Mail Queue Health audit page.

The page is added to the Pre-Ride top dropdown and Daily Operations sidebar for supervised monitoring of real-mail intake and queue health.

No routes are moved or deleted. No redirects are added. No SQL changes are made. No Bolt, EDXEIX, AADE, database write, queue mutation, or filesystem write actions are performed.

Live EDXEIX submission remains disabled and the production pre-ride tool is untouched.
