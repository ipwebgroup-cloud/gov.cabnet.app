# gov.cabnet.app — V3 Handoff

Current focus: Bolt pre-ride email V3 automation path for gov.cabnet.app.

## Operating boundary

- V0 is installed on the laptop and remains the manual/production helper.
- V3 is installed on the PC/server-side path and remains the automation development path.
- Do not touch V0 production files or dependencies while continuing V3.
- Andreas uses operator judgment during live rides.
- Live EDXEIX submit remains disabled.

## Latest state

v3.0.40 hardens the V3 pulse storage check after discovering that the pulse cron failed because the lock file was owned by `root:root`.

Correct pulse lock file:

```text
/home/cabnet/gov.cabnet.app_app/storage/locks/pre_ride_email_v3_fast_pipeline_pulse.lock
owner:group = cabnet:cabnet
mode = 0660
```

Healthy pulse log markers:

```text
Pulse summary: cycles_run=5 ok=5 failed=0
V3 fast pipeline pulse cron finish exit_code=0
```

Avoid running this as root:

```text
/home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_fast_pipeline_pulse_cron_worker.php
```

Manual test should run as `cabnet`:

```bash
su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_fast_pipeline_pulse_cron_worker.php"
```

## Important URLs

```text
https://gov.cabnet.app/ops/pre-ride-email-v3-dashboard.php
https://gov.cabnet.app/ops/pre-ride-email-v3-storage-check.php
https://gov.cabnet.app/ops/pre-ride-email-v3-queue-watch.php
https://gov.cabnet.app/ops/pre-ride-email-v3-fast-pipeline-pulse.php
https://gov.cabnet.app/ops/pre-ride-email-v3-automation-readiness.php
```

## Safety posture

- No live EDXEIX submit.
- No AADE changes.
- No V0 changes.
- No SQL in v3.0.40.
- Storage check is read-only by default.
