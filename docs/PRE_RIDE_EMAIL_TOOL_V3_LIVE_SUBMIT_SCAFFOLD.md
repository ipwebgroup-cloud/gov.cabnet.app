# V3 Live Submit Scaffold

Adds the disabled final-worker scaffold for the isolated V3 pre-ride email flow.

## Safety

- No EDXEIX calls.
- No AADE calls.
- No production `submission_jobs` writes.
- No production `submission_attempts` writes.
- No production `pre-ride-email-tool.php` change.
- Live submit is hard-disabled by `PRV3_LIVE_SUBMIT_HARD_ENABLED = false`.

## Files

- `gov.cabnet.app_app/cli/pre_ride_email_v3_live_submit_worker.php`
- `gov.cabnet.app_app/cli/pre_ride_email_v3_live_submit_cron_worker.php`
- `public_html/gov.cabnet.app/ops/pre-ride-email-v3-live-submit.php`

## Purpose

This proves the final automation layer can scan `live_submit_ready` rows, run final checks, and log cron health without submitting to EDXEIX.
