# gov.cabnet.app patch — V3 queue watch alert

## Files included

```text
public_html/gov.cabnet.app/ops/pre-ride-email-v3-queue-watch.php
docs/PRE_RIDE_EMAIL_TOOL_V3_QUEUE_WATCH_ALERT.md
PATCH_README.md
```

## Production file not included

```text
public_html/gov.cabnet.app/ops/pre-ride-email-tool.php
```

## Upload path

```text
public_html/gov.cabnet.app/ops/pre-ride-email-v3-queue-watch.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-queue-watch.php
```

## SQL

None.

## Verify

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-queue-watch.php
```

Open:

```text
https://gov.cabnet.app/ops/pre-ride-email-v3-queue-watch.php
```

## Safety

This page is read-only. It does not write to DB, does not call EDXEIX, does not call AADE, and does not touch the production pre-ride email tool.
