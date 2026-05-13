# V3 Submit Preflight Dry-Run

Adds a private CLI preflight for the isolated V3 queue.

## File

```text
gov.cabnet.app_app/cli/pre_ride_email_v3_submit_preflight.php
```

## Purpose

The script reads V3-only queue rows and reports which rows would be ready for a future submit worker.

## Safety

- SELECT only.
- No DB writes.
- No EDXEIX calls.
- No AADE calls.
- No production `submission_jobs` access.
- No production `submission_attempts` access.

## Commands

```bash
php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_submit_preflight.php --limit=20
php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_submit_preflight.php --limit=20 --json
php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_submit_preflight.php --status=all --limit=50
```

## Meaning

`Submit-ready` means a V3 queue row has passed the local dry-run checks needed before a later controlled EDXEIX submit stage is built.

This does not submit anything.
