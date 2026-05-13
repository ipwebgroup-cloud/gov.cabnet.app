# V3 Starting-Point Options Alias Fix

Fixes a V3 submit dry-run/preflight bug where the workers queried the verified options table using display names:

- `lessor_id`
- `starting_point_id`

The actual table columns are:

- `edxeix_lessor_id`
- `edxeix_starting_point_id`

The patcher updates both V3 submit preflight and submit dry-run worker queries to select the real columns with aliases:

```sql
edxeix_lessor_id AS lessor_id,
edxeix_starting_point_id AS starting_point_id
```

## Safety

- No DB writes.
- No EDXEIX calls.
- No AADE calls.
- Does not touch production `public_html/gov.cabnet.app/ops/pre-ride-email-tool.php`.
- Backs up patched files before writing.

## Files patched by script

- `/home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_submit_dry_run_worker.php`
- `/home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_submit_preflight.php`
