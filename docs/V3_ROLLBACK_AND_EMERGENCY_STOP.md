# V3 Rollback and Emergency Stop Runbook

Version: `v3.0.57-v3-live-adapter-runbook`
Status: future runbook only — live submit remains disabled

## Current safe default

The current live-submit config is closed:

```text
enabled = false
mode = disabled
adapter = disabled
hard_enable_live_submit = false
ok_for_live_submit = false
```

## Emergency stop objective

If any future live-submit gate is ever opened, the emergency stop procedure must immediately restore:

```text
enabled = false
mode = disabled
adapter = disabled
hard_enable_live_submit = false
```

## Config path

```text
/home/cabnet/gov.cabnet.app_config/pre_ride_email_v3_live_submit.php
```

## Check current gate state

```bash
su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_closed_gate_adapter_diagnostics.php"
```

Expected safe state:

```text
Gate: loaded=yes enabled=no mode=disabled adapter=disabled hard=no ok=no
Eligible for live submit now: no
```

## Emergency stop by config edit

Edit only the server-only config file:

```bash
nano /home/cabnet/gov.cabnet.app_config/pre_ride_email_v3_live_submit.php
```

Set/restore:

```php
'enabled' => false,
'mode' => 'disabled',
'adapter' => 'disabled',
'hard_enable_live_submit' => false,
```

Then verify permissions:

```bash
chown cabnet:cabnet /home/cabnet/gov.cabnet.app_config/pre_ride_email_v3_live_submit.php
chmod 640 /home/cabnet/gov.cabnet.app_config/pre_ride_email_v3_live_submit.php
```

## Verify emergency stop

```bash
su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_closed_gate_adapter_diagnostics.php"
```

Expected:

```text
Eligible for live submit now: no
master_gate: enabled is false
master_gate: mode is not live
master_gate: adapter is disabled
master_gate: hard_enable_live_submit is false
```

## Optional hard stop by disabling live-submit cron only

Do not remove other V3 crons unless deliberately simplifying automation after a proven future ride.

If a future live-submit cron ever becomes active and must be stopped, remove/comment only:

```text
pre_ride_email_v3_live_submit_cron_worker.php
```

Do not stop these visibility/readiness paths unless troubleshooting requires it:

```text
pre_ride_email_v3_fast_pipeline_pulse_cron_worker.php
pre_ride_email_v3_expiry_guard_cron_worker.php
pre_ride_email_v3_starting_point_guard_cron_worker.php
pre_ride_email_v3_live_submit_readiness_cron_worker.php
```

## Never use root to run pulse cron worker manually

Use:

```bash
su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_fast_pipeline_pulse_cron_worker.php"
```

Root can create root-owned lock files and break the minute cron.

## Lock file recovery

```bash
chown cabnet:cabnet /home/cabnet/gov.cabnet.app_app/storage/locks/pre_ride_email_v3_fast_pipeline_pulse.lock
chmod 660 /home/cabnet/gov.cabnet.app_app/storage/locks/pre_ride_email_v3_fast_pipeline_pulse.lock
```

Verify:

```bash
su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_storage_check.php"
```

## V0 fallback boundary

V0 laptop/manual helper remains the operational fallback and must not be changed by V3 automation patches.
