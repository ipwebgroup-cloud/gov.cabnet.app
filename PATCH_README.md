# Patch v3.0.42 — V3 Queue Focus

## What changed

Adds a V3-only read-only queue focus page:

```text
/ops/pre-ride-email-v3-queue-focus.php
```

The page shows queue metrics, newest V3 row, status distribution, latest 25 rows, pickup minutes, mapping IDs, and last_error previews.

It also updates the shared Ops sidebar navigation to include `V3 Queue Focus`.

## Files included

```text
public_html/gov.cabnet.app/ops/_ops-nav.php
public_html/gov.cabnet.app/ops/pre-ride-email-v3-queue-focus.php
docs/V3_QUEUE_FOCUS.md
HANDOFF.md
CONTINUE_PROMPT.md
PATCH_README.md
```

## Upload paths

```text
public_html/gov.cabnet.app/ops/_ops-nav.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/_ops-nav.php

public_html/gov.cabnet.app/ops/pre-ride-email-v3-queue-focus.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-queue-focus.php
```

Keep docs in the local GitHub Desktop repo.

## SQL

No SQL required.

## Verification commands

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/_ops-nav.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-queue-focus.php

su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_storage_check.php"

tail -n 120 /home/cabnet/gov.cabnet.app_app/logs/pre_ride_email_v3_fast_pipeline_pulse.log | egrep "cron start|ERROR|Pulse summary|finish exit_code" || true
```

## Verification URL

```text
https://gov.cabnet.app/ops/pre-ride-email-v3-queue-focus.php
```

## Expected result

After login, the page should show:

- total V3 rows
- active/future rows
- dry-run-ready/live-ready counts
- blocked count
- newest row summary
- latest 25 queue rows

## Safety

This patch is read-only.

It does not touch:

- V0 laptop/manual production helper
- V0 dependencies
- live-submit behavior
- EDXEIX calls
- AADE behavior
- queue mutation logic
- SQL schema

## Git commit title

Add V3 queue focus page

## Git commit description

Adds a V3-only read-only queue focus page for fast visibility into the current pre-ride queue state, newest row, pickup timing, mapping IDs, status distribution, and latest error previews.

Updates the shared Ops navigation and continuity documents.

No V0 helper files, live-submit behavior, EDXEIX calls, AADE behavior, queue mutation logic, or SQL schema are changed.
