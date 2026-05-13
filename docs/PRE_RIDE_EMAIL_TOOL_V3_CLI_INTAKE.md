# Pre-Ride Email Tool V3 — CLI Queue Intake

Adds a private CLI script for V3-only queue intake.

## File

```text
gov.cabnet.app_app/cli/pre_ride_email_v3_intake.php
```

## Purpose

The CLI scans recent Bolt pre-ride Maildir emails, parses them with the isolated V3 parser, resolves EDXEIX IDs with the isolated V3 mapping lookup, applies the future-time gate, and can insert only eligible future-ready candidates into the V3-only queue tables.

## Safety

Default mode is dry-run. It does not write to the database.

With `--commit`, it writes only to:

```text
pre_ride_email_v3_queue
pre_ride_email_v3_queue_events
```

It never writes to production `submission_jobs` or `submission_attempts`, never calls EDXEIX, never calls AADE, and does not move, delete, or mark emails as read.

## Commands

Dry run:

```bash
php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_intake.php --limit=20
```

JSON dry run:

```bash
php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_intake.php --limit=20 --json
```

Commit eligible future-ready rows to the V3 queue only:

```bash
php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_intake.php --limit=20 --commit
```

## Future cron idea

Do not schedule until manually verified. Later, a safe cron could call the script with `--commit` every minute or two. This queues eligible rows only; it does not submit them to EDXEIX.
