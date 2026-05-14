# gov.cabnet.app — V3 Compact Monitor

Patch: `v3.0.41-v3-compact-monitor`

## Purpose

Adds a single fast, read-only operator page for V3 status:

```text
/ops/pre-ride-email-v3-monitor.php
```

The page is intended for quick visibility during development and test rides. It does not decide whether to use V0 or V3. Andreas/operator judgment remains the operating rule.

## What it shows

- Pulse cron health from the V3 pulse log.
- Pulse lock file owner/group and permissions.
- Queue metrics.
- Newest V3 queue row.
- Latest `last_error`, when present.
- Live-submit gate state, visibly disabled.
- Fast links to Queue Watch, Pulse Monitor, Automation Readiness, Locked Gate, and Storage Check.

## Safety

This page is read-only:

- No Bolt API call.
- No EDXEIX call.
- No AADE call.
- No Gmail/mailbox mutation.
- No database writes.
- No production submission table writes.
- No V0 production helper changes.

## V0 / V3 boundary

- V0 remains the laptop/manual production helper.
- V3 remains the PC/server development and automation path.
- This patch does not touch V0 files or dependencies.
