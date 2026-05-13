# V3 Pre-Ride Email Tool — Queue Expiry Guard

Version: `v3.0.34-v3-queue-expiry-guard`

## Purpose

The V3 queue can receive a valid future row, but if the operator or the downstream workers do not complete the flow before pickup time, that row must not remain actionable.

This patch adds a V3-only expiry guard that blocks active rows once their pickup time is no longer future-safe.

## Safety boundaries

- Production `public_html/gov.cabnet.app/ops/pre-ride-email-tool.php` is untouched.
- No EDXEIX calls.
- No AADE calls.
- No production `submission_jobs` writes.
- No production `submission_attempts` writes.
- Commit mode writes only to:
  - `pre_ride_email_v3_queue`
  - `pre_ride_email_v3_queue_events`

## Files

- `gov.cabnet.app_app/cli/pre_ride_email_v3_expiry_guard.php`
- `gov.cabnet.app_app/cli/pre_ride_email_v3_expiry_guard_cron_worker.php`
- `public_html/gov.cabnet.app/ops/pre-ride-email-v3-expiry-guard.php`

## CLI usage

Dry-run:

```bash
php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_expiry_guard.php --limit=500
```

Commit:

```bash
php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_expiry_guard.php --limit=500 --commit
```

The default guard blocks rows where `pickup_datetime` is already past.

## Cron

Recommended line:

```bash
* * * * * /usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_expiry_guard_cron_worker.php >> /home/cabnet/gov.cabnet.app_app/logs/pre_ride_email_v3_expiry_guard_cron.log 2>&1
```

## Dashboard

```text
https://gov.cabnet.app/ops/pre-ride-email-v3-expiry-guard.php
```

The page is read-only and auto-refreshes every 30 seconds.
