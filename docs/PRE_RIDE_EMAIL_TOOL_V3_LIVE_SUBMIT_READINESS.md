# V3 live-submit readiness gate

This patch adds the final V3-only readiness layer before any future live EDXEIX submit worker can be considered.

## Purpose

The live-submit readiness worker reads rows that already passed `submit_dry_run_ready` and validates them again before marking them `live_submit_ready`.

This is not live submission. It does not call EDXEIX.

## Files

- `gov.cabnet.app_app/cli/pre_ride_email_v3_live_submit_readiness.php`
- `gov.cabnet.app_app/cli/pre_ride_email_v3_live_submit_readiness_cron_worker.php`
- `public_html/gov.cabnet.app/ops/pre-ride-email-v3-live-readiness.php`

## Safety boundaries

- No EDXEIX calls.
- No AADE calls.
- No production `submission_jobs` writes.
- No production `submission_attempts` writes.
- No production `pre-ride-email-tool.php` changes.
- Commit mode writes only to V3 queue/status/events.

## Recommended flow

1. V3 intake cron queues eligible email.
2. V3 starting-point guard blocks known-invalid start IDs.
3. V3 submit dry-run cron marks safe rows `submit_dry_run_ready`.
4. V3 live-submit readiness worker marks safe rows `live_submit_ready`.
5. A future live submit worker can later be designed, but must remain disabled until explicitly approved.
