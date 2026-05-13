# V3 Pre-Ride Email Tool — Queue Foundation

This patch prepares the future V3 queue layer without enabling queue writes.

## Production isolation

The production tool is not included and must not be touched:

```text
public_html/gov.cabnet.app/ops/pre-ride-email-tool.php
```

## Added/changed files

```text
public_html/gov.cabnet.app/ops/pre-ride-email-toolv3.php
gov.cabnet.app_sql/2026_05_13_pre_ride_email_v3_queue_tables.sql
docs/PRE_RIDE_EMAIL_TOOL_V3_QUEUE_FOUNDATION.md
PATCH_README.md
```

## Behavior

V3 now shows a queue foundation status panel under the dry-run queue preview.
It checks, read-only, whether these V3-only tables exist:

```text
pre_ride_email_v3_queue
pre_ride_email_v3_queue_events
```

This patch does not insert queue rows and does not call EDXEIX.

## SQL

The included SQL is additive and repeatable. It creates V3-only tables and does not touch production `submission_jobs` or `submission_attempts`.

Run only after approval:

```bash
mysql < /home/cabnet/gov.cabnet.app_sql/2026_05_13_pre_ride_email_v3_queue_tables.sql
```

Or paste the file contents into phpMyAdmin for the correct gov.cabnet.app database.

## Safety

- No DB writes from the V3 page.
- No queue inserts.
- No EDXEIX server-side calls.
- No AADE calls.
- No email is marked processed.
- Existing production route remains unchanged.
