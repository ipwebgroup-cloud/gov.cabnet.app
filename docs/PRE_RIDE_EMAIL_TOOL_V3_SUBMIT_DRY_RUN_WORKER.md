# V3 Pre-Ride Email Submit Dry-Run Worker

This patch adds a private CLI worker that moves the isolated V3 queue one controlled step closer to final EDXEIX automation.

## File

```text
/home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_submit_dry_run_worker.php
```

## Purpose

The worker reads rows from the V3-only queue table and runs strict submit preflight checks. It can optionally mark rows as `submit_dry_run_ready` and write V3-only events.

It does not submit to EDXEIX.

## Safety boundaries

- No EDXEIX calls.
- No AADE calls.
- No writes to `submission_jobs`.
- No writes to `submission_attempts`.
- No changes to `/ops/pre-ride-email-tool.php`.
- Commit mode writes only to:
  - `pre_ride_email_v3_queue`
  - `pre_ride_email_v3_queue_events`

## Commands

Syntax check:

```bash
php -l /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_submit_dry_run_worker.php
```

Dry-run, SELECT only:

```bash
php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_submit_dry_run_worker.php --limit=20
```

JSON dry-run:

```bash
php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_submit_dry_run_worker.php --limit=20 --json
```

Commit V3-only dry-run state/events:

```bash
php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_submit_dry_run_worker.php --limit=20 --commit
```

## What commit mode does

For submit-ready rows:

- updates queue status to `submit_dry_run_ready`
- sets `locked_at = NOW()`
- inserts a `submit_dry_run_ready` event

For blocked rows:

- leaves queue status unchanged
- inserts a `submit_dry_run_blocked` event

## Submit preflight checks

The worker checks:

- `parser_ok = 1`
- `mapping_ok = 1`
- `future_ok = 1`
- lessor ID exists
- driver ID exists
- vehicle ID exists
- starting point ID exists
- customer name/phone exist
- pickup/dropoff addresses exist
- pickup datetime exists and is still future-safe
- estimated end datetime is after pickup
- positive price amount exists
- payload JSON is valid

