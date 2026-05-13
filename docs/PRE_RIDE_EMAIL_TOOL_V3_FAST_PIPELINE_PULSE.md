# V3 Fast Pipeline Pulse

This V3-only patch adds a pulse runner around the existing V3 fast pipeline.

## Purpose

The normal cPanel cron interval is one minute. Bolt pre-ride emails sometimes arrive very close to pickup time. The pulse runner reduces waiting time by running the existing V3 fast pipeline repeatedly inside one cron minute.

Default cron mode:

```bash
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_fast_pipeline_pulse_cron_worker.php >> /home/cabnet/gov.cabnet.app_app/logs/pre_ride_email_v3_fast_pipeline_pulse.log 2>&1
```

The cron worker runs:

```bash
pre_ride_email_v3_fast_pipeline_pulse.php --commit --limit=50 --cycles=5 --sleep=10 --max-runtime=55
```

This means one cron invocation checks the mailbox roughly every 10 seconds for up to 55 seconds.

## Safety

The pulse runner delegates only to the existing V3 fast pipeline.

It does not introduce:
- EDXEIX calls
- AADE calls
- production submission_jobs writes
- production submission_attempts writes
- production pre-ride-email-tool.php changes

Commit mode remains V3-only because the child pipeline is V3-only.

## Read-only page

```text
https://gov.cabnet.app/ops/pre-ride-email-v3-fast-pipeline-pulse.php
```

## Recommended rollout

Keep the old V3 crons during first verification. After the pulse runner proves stable, the old separate stage crons can be disabled and the pulse runner can become the main V3 automation runner.
