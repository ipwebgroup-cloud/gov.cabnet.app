# gov.cabnet.app — V3 Cron Health Page Patch

## What changed

Adds a new read-only V3 cron health page:

```text
https://gov.cabnet.app/ops/pre-ride-email-v3-cron-health.php
```

The page reads V3 queue tables and V3 cron logs to show intake/submit-dry-run automation health.

## Files included

```text
public_html/gov.cabnet.app/ops/pre-ride-email-v3-cron-health.php
docs/PRE_RIDE_EMAIL_TOOL_V3_CRON_HEALTH_PAGE.md
PATCH_README.md
```

## Upload path

```text
public_html/gov.cabnet.app/ops/pre-ride-email-v3-cron-health.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-cron-health.php
```

Docs stay in the local GitHub Desktop repo.

## SQL

None.

## Verify

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-cron-health.php
```

Open:

```text
https://gov.cabnet.app/ops/pre-ride-email-v3-cron-health.php
```

## Safety

- Production `/ops/pre-ride-email-tool.php` is not included.
- Read-only page.
- No DB writes.
- No EDXEIX calls.
- No AADE calls.
- No production queue writes.
