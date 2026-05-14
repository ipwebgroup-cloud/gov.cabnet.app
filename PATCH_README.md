# v3.0.47 — Live Readiness Start Options Alias Fix

## What changed

Adds a V3-only one-time maintenance script:

```text
gov.cabnet.app_app/cli/fix_v3_live_readiness_start_options_aliases.php
```

It patches:

```text
gov.cabnet.app_app/cli/pre_ride_email_v3_live_submit_readiness.php
```

so the live-readiness worker queries `pre_ride_email_v3_starting_point_options` with:

```text
edxeix_lessor_id
edxeix_starting_point_id
```

instead of:

```text
lessor_id
starting_point_id
```

## Files included

```text
gov.cabnet.app_app/cli/fix_v3_live_readiness_start_options_aliases.php
docs/V3_LIVE_READINESS_ALIAS_FIX.md
HANDOFF.md
CONTINUE_PROMPT.md
PATCH_README.md
```

## Upload path

```text
gov.cabnet.app_app/cli/fix_v3_live_readiness_start_options_aliases.php
→ /home/cabnet/gov.cabnet.app_app/cli/fix_v3_live_readiness_start_options_aliases.php
```

## SQL

No SQL required for this patch.

The lessor 3814 starting-point option was already inserted separately:

```text
3814 / 6467495 = ΕΔΡΑ ΜΑΣ...
```

## Commands

```bash
php -l /home/cabnet/gov.cabnet.app_app/cli/fix_v3_live_readiness_start_options_aliases.php

su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/fix_v3_live_readiness_start_options_aliases.php --check"

su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/fix_v3_live_readiness_start_options_aliases.php --apply"

php -l /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_live_submit_readiness.php

su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_fast_pipeline.php --limit=50 --commit"

mysql cabnet_gov -e "
SELECT
  id,
  queue_status,
  customer_name,
  pickup_datetime,
  driver_name,
  vehicle_plate,
  lessor_id,
  driver_id,
  vehicle_id,
  starting_point_id,
  last_error,
  created_at,
  updated_at
FROM pre_ride_email_v3_queue
WHERE id IN (41,56)
ORDER BY id DESC;
"
```

## Expected result

```text
queue_status = live_submit_ready
```

for eligible future-safe rows.

Live submit remains disabled.

## Git commit title

```text
Fix V3 live-readiness start option aliases
```

## Git commit description

```text
Adds a V3-only maintenance script to patch the live-readiness worker so it queries pre_ride_email_v3_starting_point_options using edxeix_lessor_id and edxeix_starting_point_id.

This fixes the Unknown column 'lessor_id' error after forwarded-email test rows reached submit_dry_run_ready.

No V0 production helper files, live-submit enabling, EDXEIX calls, AADE behavior, queue mutation logic, cron schedules, or SQL schema are changed.
```
