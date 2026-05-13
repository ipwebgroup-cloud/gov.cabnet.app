# gov.cabnet.app — V3 Submit Dry-Run Worker Patch

## Production file not touched

This patch does not include or modify:

```text
public_html/gov.cabnet.app/ops/pre-ride-email-tool.php
```

## Files included

```text
gov.cabnet.app_app/cli/pre_ride_email_v3_submit_dry_run_worker.php
docs/PRE_RIDE_EMAIL_TOOL_V3_SUBMIT_DRY_RUN_WORKER.md
PATCH_README.md
```

## Upload path

```text
gov.cabnet.app_app/cli/pre_ride_email_v3_submit_dry_run_worker.php
→ /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_submit_dry_run_worker.php
```

Docs remain in the local repo.

## SQL

None.

## Verify

```bash
php -l /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_submit_dry_run_worker.php
```

## Dry-run

```bash
php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_submit_dry_run_worker.php --limit=20
```

## Commit V3-only dry-run status/events

Run only after a future-safe row appears in the V3 queue:

```bash
php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_submit_dry_run_worker.php --limit=20 --commit
```

## Safety

- No EDXEIX calls.
- No AADE calls.
- No production `submission_jobs` writes.
- No production `submission_attempts` writes.
- Writes only V3 dry-run status/events when `--commit` is used.
