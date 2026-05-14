# V3 Pulse Focus

Version: v3.0.43-v3-pulse-focus

## Purpose

Adds a read-only V3 pulse focus page for fast visibility into the pulse cron log and lock state.

URL:

```text
/ops/pre-ride-email-v3-pulse-focus.php
```

The page shows:

- pulse health
- latest pulse summary
- latest cron start and finish lines
- last exit code
- seconds since last finish
- pulse lock owner/group and permissions
- recent error count
- recent pulse events
- raw recent log tail

## Safety boundary

This page is V3-only and read-only.

It does not:

- call Bolt
- call EDXEIX
- call AADE
- write database rows
- modify log or lock files
- change cron behavior
- touch the V0 laptop/manual production helper
- enable live-submit

## Operator note

Manually test the V3 pulse cron worker as the `cabnet` user, not as root, so root-owned lock files are not created.
