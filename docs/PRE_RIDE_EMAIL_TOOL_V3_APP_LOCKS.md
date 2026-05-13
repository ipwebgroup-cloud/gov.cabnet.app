# V3 Cron App-Owned Lock Files

This patch moves V3 cron worker lock files out of `/tmp` and into the app-owned private storage folder:

```text
/home/cabnet/gov.cabnet.app_app/storage/locks/
```

## Why

The first V3 cron tests were run manually as `root`, which created root-owned lock files under `/tmp`. Later cPanel cron ran as `cabnet` and could not open those files, causing permission errors.

## Changed files

- `gov.cabnet.app_app/cli/pre_ride_email_v3_cron_worker.php`
- `gov.cabnet.app_app/cli/pre_ride_email_v3_submit_dry_run_cron_worker.php`

## Safety

- No production pre-ride tool changes.
- No EDXEIX calls.
- No AADE calls.
- No production `submission_jobs` writes.
- No production `submission_attempts` writes.
- Lock files are private app runtime files only.
