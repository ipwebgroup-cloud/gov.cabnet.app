# gov.cabnet.app — V3 CLI Queue Intake Patch

## Production file not touched

This patch does not include or modify:

```text
public_html/gov.cabnet.app/ops/pre-ride-email-tool.php
```

## Files included

```text
gov.cabnet.app_app/cli/pre_ride_email_v3_intake.php
docs/PRE_RIDE_EMAIL_TOOL_V3_CLI_INTAKE.md
PATCH_README.md
```

## Upload path

```text
gov.cabnet.app_app/cli/pre_ride_email_v3_intake.php
→ /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_intake.php
```

Docs remain in the local GitHub Desktop repo.

## SQL

None. This uses the V3 queue tables already created:

```text
pre_ride_email_v3_queue
pre_ride_email_v3_queue_events
```

## Verify syntax

```bash
php -l /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_intake.php
```

## Dry-run test

```bash
php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_intake.php --limit=20
```

Expected for current historical emails:

```text
Candidates found
Ready: 0
Blocked: many
No DB rows inserted
```

## JSON dry-run

```bash
php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_intake.php --limit=20 --json
```

## Commit mode

Only after dry-run is clean:

```bash
php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_intake.php --limit=20 --commit
```

Commit mode writes only future-ready rows into V3-only tables using `INSERT IGNORE` and deterministic V3 dedupe keys.

## Safety

- No production route change.
- No production `submission_jobs` writes.
- No production `submission_attempts` writes.
- No EDXEIX server call.
- No AADE call.
- No email delete/move/mark-read.
- Default mode is dry-run.
