# v3.0.41 — V3 Compact Monitor

## What changed

Adds a new fast, read-only V3 operator visibility page:

```text
public_html/gov.cabnet.app/ops/pre-ride-email-v3-monitor.php
```

Updates the shared Ops nav to include the new page.

## Files included

```text
public_html/gov.cabnet.app/ops/_ops-nav.php
public_html/gov.cabnet.app/ops/pre-ride-email-v3-monitor.php
docs/V3_COMPACT_MONITOR.md
HANDOFF.md
CONTINUE_PROMPT.md
PATCH_README.md
```

## Upload paths

```text
public_html/gov.cabnet.app/ops/_ops-nav.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/_ops-nav.php

public_html/gov.cabnet.app/ops/pre-ride-email-v3-monitor.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-monitor.php
```

Docs should be kept in the local GitHub Desktop repo.

## SQL

No SQL required.

## Verification commands

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/_ops-nav.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-monitor.php
```

Storage/pulse confirmation:

```bash
su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_storage_check.php"

tail -n 120 /home/cabnet/gov.cabnet.app_app/logs/pre_ride_email_v3_fast_pipeline_pulse.log | egrep "cron start|ERROR|Pulse summary|finish exit_code" || true
```

## Verification URL

```text
https://gov.cabnet.app/ops/pre-ride-email-v3-monitor.php
```

## Expected result

The page should load after Ops login and show:

- Pulse cron status.
- Pulse lock status.
- Queue metrics.
- Newest V3 queue row.
- Live-submit gate remains disabled.

## Safety

This patch is read-only and V3-only.

It does not touch:

- V0 laptop/manual production helper.
- V0 dependencies.
- Live-submit behavior.
- EDXEIX calls.
- AADE behavior.
- Queue mutation logic.
- SQL schema.
