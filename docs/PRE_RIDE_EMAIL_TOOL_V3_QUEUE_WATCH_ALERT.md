# V3 Pre-Ride Email Queue Watch Alert

Adds a new independent read-only watch page:

```text
/ops/pre-ride-email-v3-queue-watch.php
```

## Purpose

The page watches the isolated V3 queue without requiring repeated terminal checks.

It auto-refreshes every 10 seconds while no active future V3 queue row exists. When a future queued or submit-dry-run-ready row appears, the page stops refreshing, changes the title to `READY - V3 Queue Watch`, attempts a short browser beep, and requests/uses browser notification permission.

## Safety

- Read-only page.
- No DB writes.
- No EDXEIX calls.
- No AADE calls.
- No writes to production `submission_jobs`.
- No writes to production `submission_attempts`.
- Does not modify `/ops/pre-ride-email-tool.php`.

## Upload path

```text
public_html/gov.cabnet.app/ops/pre-ride-email-v3-queue-watch.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-queue-watch.php
```

## Verification

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-queue-watch.php
```

Open:

```text
https://gov.cabnet.app/ops/pre-ride-email-v3-queue-watch.php
```

Expected while no row is queued:

```text
Waiting for future-safe V3 queue row
Auto-refresh every 10 seconds
V3 cron status visible
No active future rows
```

Expected when V3 queue has a future row:

```text
V3 Queue Ready
Page stops refreshing
Title changes to READY - V3 Queue Watch
Browser alert/beep attempts
Active row links to V3 queue dashboard
```
