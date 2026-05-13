# gov.cabnet.app — V3 Manual Queue Intake Patch

## Production file not touched

This patch does not include or modify:

```text
public_html/gov.cabnet.app/ops/pre-ride-email-tool.php
```

## Files included

```text
public_html/gov.cabnet.app/ops/pre-ride-email-toolv3.php
docs/PRE_RIDE_EMAIL_TOOL_V3_MANUAL_QUEUE_INTAKE.md
PATCH_README.md
```

## Upload path

```text
public_html/gov.cabnet.app/ops/pre-ride-email-toolv3.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-toolv3.php
```

## SQL

No new SQL. Requires the V3 queue tables already created:

```text
pre_ride_email_v3_queue
pre_ride_email_v3_queue_events
```

## Verify

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-toolv3.php
```

Open:

```text
https://gov.cabnet.app/ops/pre-ride-email-toolv3.php
```

## Expected result

If no future-ready candidate exists, the queue button is disabled and no rows are written.

If a future-ready candidate exists, the operator can click **Queue ready candidates to V3 table**. The tool inserts only eligible candidates into `pre_ride_email_v3_queue`, using `INSERT IGNORE` for dedupe safety, and writes a V3 queue event. It does not call EDXEIX or AADE and does not touch production submission tables.
