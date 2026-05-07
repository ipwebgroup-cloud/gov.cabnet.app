# gov.cabnet.app v6.0 EDXEIX field compatibility patch

## What changed

- Supports the current EDXEIX start point form field: `starting_point`.
- Keeps `starting_point_id` as a backwards-compatible alias.
- Adds a safety check that starting point mapping is present before guarded live submit is allowed.
- Seeds/updates `mapping_starting_points.internal_key='edra_mas'` to EDXEIX value `6467495`.

## Files included

```text
HANDOFF.md
CONTINUE_PROMPT.md
PATCH_README.md
docs/V6_0_EDXEIX_FIELD_COMPAT.md
gov.cabnet.app_app/lib/edxeix_live_submit_gate.php
gov.cabnet.app_app/src/Edxeix/EdxeixFormReader.php
gov.cabnet.app_app/src/Edxeix/EdxeixPayloadBuilder.php
gov.cabnet.app_sql/2026_05_08_v6_0_edxeix_starting_point_6467495.sql
```

## Exact upload paths

```text
gov.cabnet.app_app/lib/edxeix_live_submit_gate.php
→ /home/cabnet/gov.cabnet.app_app/lib/edxeix_live_submit_gate.php

gov.cabnet.app_app/src/Edxeix/EdxeixFormReader.php
→ /home/cabnet/gov.cabnet.app_app/src/Edxeix/EdxeixFormReader.php

gov.cabnet.app_app/src/Edxeix/EdxeixPayloadBuilder.php
→ /home/cabnet/gov.cabnet.app_app/src/Edxeix/EdxeixPayloadBuilder.php

gov.cabnet.app_sql/2026_05_08_v6_0_edxeix_starting_point_6467495.sql
→ /home/cabnet/gov.cabnet.app_sql/2026_05_08_v6_0_edxeix_starting_point_6467495.sql

docs/V6_0_EDXEIX_FIELD_COMPAT.md
→ repository docs/V6_0_EDXEIX_FIELD_COMPAT.md

HANDOFF.md, CONTINUE_PROMPT.md, PATCH_README.md
→ repository/project root
```

## SQL to run

```bash
DB_NAME=$(php -r '$c=require "/home/cabnet/gov.cabnet.app_config/config.php"; echo $c["db"]["database"] ?? $c["database"]["database"];')
mysql "$DB_NAME" < /home/cabnet/gov.cabnet.app_sql/2026_05_08_v6_0_edxeix_starting_point_6467495.sql
```

## Verification commands

```bash
php -l /home/cabnet/gov.cabnet.app_app/src/Edxeix/EdxeixFormReader.php
php -l /home/cabnet/gov.cabnet.app_app/src/Edxeix/EdxeixPayloadBuilder.php
php -l /home/cabnet/gov.cabnet.app_app/lib/edxeix_live_submit_gate.php

DB_NAME=$(php -r '$c=require "/home/cabnet/gov.cabnet.app_config/config.php"; echo $c["db"]["database"] ?? $c["database"]["database"];')
mysql "$DB_NAME" -e "SELECT internal_key,label,edxeix_starting_point_id,is_active FROM mapping_starting_points WHERE internal_key='edra_mas';"
```

## Analyze-only test

```bash
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/live_submit_one_booking.php --booking-id=BOOKING_ID --analyze-only
```

Expected payload fields:

```text
starting_point = 6467495
starting_point_id = 6467495
```

## Expected result

The guarded live-submit payload now sends a real selected `Σημείο έναρξης` value instead of relying on the old bridge-only field name.

## Git commit title

```text
Add v6.0 EDXEIX starting point field compatibility
```

## Git commit description

```text
Support the current EDXEIX form field name `starting_point` while retaining the older `starting_point_id` alias. Seed the confirmed `edra_mas` mapping to EDXEIX starting point value 6467495 and require a starting point value in guarded live-submit analysis before one-shot production submission can proceed.
```
