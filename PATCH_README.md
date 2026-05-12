# gov.cabnet.app patch — WHITEBLUE starting point hotfix

## What changed

- Updates `EdxeixMappingLookup.php` to resolve starting points by lessor first.
- Adds a WHITEBLUE lessor-specific starting point mapping:
  - lessor `1756`
  - starting point `612164`
- Leaves global starting point defaults unchanged.
- Does not modify `/ops/pre-ride-email-tool.php`.

## Files included

```text
gov.cabnet.app_app/src/BoltMail/EdxeixMappingLookup.php
gov.cabnet.app_sql/2026_05_12_whiteblue_starting_point_612164.sql
docs/OPS_WHITEBLUE_STARTING_POINT_HOTFIX_2026_05_12.md
PATCH_README.md
```

## Upload paths

```text
gov.cabnet.app_app/src/BoltMail/EdxeixMappingLookup.php
→ /home/cabnet/gov.cabnet.app_app/src/BoltMail/EdxeixMappingLookup.php

gov.cabnet.app_sql/2026_05_12_whiteblue_starting_point_612164.sql
→ /home/cabnet/gov.cabnet.app_sql/2026_05_12_whiteblue_starting_point_612164.sql
```

## SQL to run

```bash
mysql -u cabnet_gov -p cabnet_gov < /home/cabnet/gov.cabnet.app_sql/2026_05_12_whiteblue_starting_point_612164.sql
```

## Verify

```bash
php -l /home/cabnet/gov.cabnet.app_app/src/BoltMail/EdxeixMappingLookup.php
```

Then reload the correct Bolt pre-ride email in:

```text
https://gov.cabnet.app/ops/pre-ride-email-tool.php
```

Expected helper IDs:

```text
Company ID: 1756
Driver ID: 4382
Vehicle ID: 4327
Starting point ID: 612164
```

Do not POST unless the ride is future and the map point is confirmed.
