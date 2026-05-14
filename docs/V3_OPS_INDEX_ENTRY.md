# V3 Ops Index Entry

Patch: `v3.0.46-ops-index-v3-entry`

## Purpose

This patch updates `/ops/index.php` so the main Operations Console clearly points to the verified V3 monitoring pages:

- V3 Control Center
- Compact Monitor
- Queue Focus
- Pulse Focus
- Readiness Focus
- Storage Check

## Boundary

This is a V3-only UI/navigation patch.

It does not touch:

- V0 laptop/manual production helper files
- V0 dependencies
- Live-submit behavior
- EDXEIX calls
- AADE receipt behavior
- Queue mutation logic
- Cron behavior
- SQL schema

## Verified pages linked

```text
/ops/pre-ride-email-v3-dashboard.php
/ops/pre-ride-email-v3-monitor.php
/ops/pre-ride-email-v3-queue-focus.php
/ops/pre-ride-email-v3-pulse-focus.php
/ops/pre-ride-email-v3-readiness-focus.php
/ops/pre-ride-email-v3-storage-check.php
```

## Operator note

The V3 pulse cron lock issue was caused by a root-owned pulse lock file. Manual pulse cron-worker testing should be done as `cabnet`, not root.
