# V3 Real-Mail Queue Health Navigation — 2026-05-15

## Summary

Adds navigation access for the read-only V3 Real-Mail Queue Health audit page.

## Safety posture

- Navigation-only update.
- No Bolt calls.
- No EDXEIX calls.
- No AADE calls.
- No database writes.
- No queue mutations.
- No route moves, deletions, or redirects.
- Live EDXEIX submit remains disabled.
- Production Pre-Ride Tool remains untouched.

## Updated route

- `/ops/pre-ride-email-v3-real-mail-queue-health.php`

## Updated shell areas

- Pre-Ride top dropdown.
- Daily operations sidebar.

## Verification

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/_shell.php
curl -I --max-time 10 https://gov.cabnet.app/ops/pre-ride-email-v3-real-mail-queue-health.php
grep -n "v3.1.1\|Real-Mail Queue Health\|real-mail queue health navigation" /home/cabnet/public_html/gov.cabnet.app/ops/_shell.php
```

Expected:

- PHP syntax clean.
- Ops route redirects to login when unauthenticated.
- v3.1.1 markers present.
