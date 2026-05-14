# V3 Storage and Pulse Check

Version: v3.0.40-pulse-lock-owner-hardening

This document covers the V3-only storage prerequisites used by the fast pipeline pulse cron.

## Boundary

- V0 laptop/manual production helper is untouched.
- V3 server/PC-side automation path continues development.
- Live EDXEIX submit remains disabled.
- No AADE calls are changed.
- No SQL is required.

## What is checked

The storage checker verifies:

- `/home/cabnet/gov.cabnet.app_app/storage`
- `/home/cabnet/gov.cabnet.app_app/storage/locks`
- `/home/cabnet/gov.cabnet.app_app/logs`
- V3 pulse CLI file
- V3 pulse cron worker file
- V3 pulse lock file:
  `/home/cabnet/gov.cabnet.app_app/storage/locks/pre_ride_email_v3_fast_pipeline_pulse.lock`

The pulse lock file must be writable by the `cabnet` account and should be owned by `cabnet:cabnet`.

## Why this matters

A root-owned pulse lock file caused the V3 pulse cron to fail with:

```text
ERROR: Could not open lock file: /home/cabnet/gov.cabnet.app_app/storage/locks/pre_ride_email_v3_fast_pipeline_pulse.lock
```

The normal cPanel cron runs under the `cabnet` account. If the lock file is created or modified by root with restrictive ownership, the cron cannot write to it.

## Correct repair command

Run as root only when repairing permissions:

```bash
install -d -o cabnet -g cabnet -m 750 /home/cabnet/gov.cabnet.app_app/storage/locks
install -d -o cabnet -g cabnet -m 750 /home/cabnet/gov.cabnet.app_app/logs
touch /home/cabnet/gov.cabnet.app_app/storage/locks/pre_ride_email_v3_fast_pipeline_pulse.lock
chown cabnet:cabnet /home/cabnet/gov.cabnet.app_app/storage/locks/pre_ride_email_v3_fast_pipeline_pulse.lock
chmod 660 /home/cabnet/gov.cabnet.app_app/storage/locks/pre_ride_email_v3_fast_pipeline_pulse.lock
```

Then verify:

```bash
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_storage_check.php
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_storage_check.php --json
```

## Correct manual cron-worker test

Do not run the pulse cron worker as root. Test it as the cPanel user:

```bash
su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_fast_pipeline_pulse_cron_worker.php"
```

Or, if available:

```bash
sudo -u cabnet /usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_fast_pipeline_pulse_cron_worker.php
```

## Expected healthy log markers

```text
V3 fast pipeline pulse cron start
Pulse summary: cycles_run=5 ok=5 failed=0
V3 fast pipeline pulse cron finish exit_code=0
```

## Expected bad marker

```text
ERROR: Could not open lock file
```

If that appears again, check owner and permissions on the pulse lock file first.
