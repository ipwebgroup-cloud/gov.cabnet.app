# V3 Pre-Ride Email Fast Pipeline

Version: `v3.0.35-fast-pipeline-runner`

This patch adds a single ordered V3 pipeline runner so intake, expiry, starting-point validation, submit dry-run readiness, and live-readiness run in one controlled sequence.

## Safety

- No EDXEIX call.
- No AADE call.
- No production `submission_jobs` writes.
- No production `submission_attempts` writes.
- No production `pre-ride-email-tool.php` change.
- Commit mode only delegates to existing V3-only workers.

## Recommended sequence

The fast pipeline runs:

1. Expiry guard before intake.
2. Maildir intake.
3. Expiry guard after intake.
4. Starting-point guard.
5. Submit dry-run worker.
6. Expiry guard after submit dry-run.
7. Live-readiness worker.
8. Optional readiness report.

## CLI

```bash
php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_fast_pipeline.php --limit=50
php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_fast_pipeline.php --limit=50 --commit
```

## Cron

```bash
* * * * * /usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_fast_pipeline_cron_worker.php >> /home/cabnet/gov.cabnet.app_app/logs/pre_ride_email_v3_fast_pipeline.log 2>&1
```

## Dashboard

```text
https://gov.cabnet.app/ops/pre-ride-email-v3-fast-pipeline.php
```

## Notes

Keep the existing individual V3 crons in place for the first verification run. After the fast pipeline is confirmed stable, the individual stage crons can be disabled to reduce duplicate work, leaving the fast pipeline as the primary V3 automation cron.
