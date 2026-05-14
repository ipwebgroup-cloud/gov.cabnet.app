# V3 Storage and Pulse Check

## Why this exists

On 2026-05-14 the V3 fast pipeline pulse cron failed repeatedly with:

```text
ERROR: Could not open lock file:
/home/cabnet/gov.cabnet.app_app/storage/locks/pre_ride_email_v3_fast_pipeline_pulse.lock
```

The issue was infrastructure/storage-related, not a live-submit issue.

The lock directory was repaired manually with:

```bash
install -d -o cabnet -g cabnet -m 750 /home/cabnet/gov.cabnet.app_app/storage/locks
install -d -o cabnet -g cabnet -m 750 /home/cabnet/gov.cabnet.app_app/logs
```

After that, the pulse cron worker started successfully again.

## Added V3-only utility

This patch adds:

```text
gov.cabnet.app_app/cli/pre_ride_email_v3_storage_check.php
```

Default mode is read-only:

```bash
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_storage_check.php
```

JSON mode:

```bash
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_storage_check.php --json
```

Optional repair mode:

```bash
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_storage_check.php --fix --owner=cabnet --group=cabnet
```

## Added Ops page

```text
https://gov.cabnet.app/ops/pre-ride-email-v3-storage-check.php
```

This page is read-only and checks the V3 storage/log/lock directories and pulse files.

## Safety

This utility does not call:

- Bolt
- EDXEIX
- AADE
- Gmail
- production submission tables

It does not touch V0 production or browser-helper dependencies.
