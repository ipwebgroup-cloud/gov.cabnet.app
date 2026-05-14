# V3 Ops Home Integration

Version: v3.0.45-v3-ops-home-integration

## Purpose

This patch integrates the verified V3 visibility pages into the V3 Control Center so the operator has one coherent launch point for the V3 server-side development and monitoring path.

## Boundary

- V0 laptop/manual production helper is untouched.
- V3 remains the PC/server-side development and automation path.
- Live EDXEIX submit remains disabled.
- No Bolt calls, EDXEIX calls, AADE calls, DB writes, cron behavior changes, queue mutation logic changes, or SQL schema changes are introduced by this UI patch.

## Primary V3 visibility pages

- `/ops/pre-ride-email-v3-dashboard.php` — V3 Control Center / integrated hub.
- `/ops/pre-ride-email-v3-monitor.php` — Compact Monitor.
- `/ops/pre-ride-email-v3-queue-focus.php` — Queue Focus.
- `/ops/pre-ride-email-v3-pulse-focus.php` — Pulse Focus.
- `/ops/pre-ride-email-v3-readiness-focus.php` — Readiness Focus.
- `/ops/pre-ride-email-v3-storage-check.php` — Storage and pulse-lock prerequisite check.

## Operational note

The V3 pulse cron worker should be tested as the `cabnet` user, not as root, to avoid root-owned lock files.

```bash
su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_storage_check.php"
```

## Current verified posture

- Pulse lock file owner/group: `cabnet:cabnet`.
- Pulse lock file permissions: `0660`.
- Pulse cron: `cycles_run=5 ok=5 failed=0`.
- Live submit: disabled.
- V0: untouched.
