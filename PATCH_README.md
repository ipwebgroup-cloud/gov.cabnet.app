# PATCH README — Ioannis Kounter EDXEIX Mapping

## What changed

This package adds one SQL migration to map the verified Ioannis Kounter EDXEIX IDs:

```text
Lessor/company: 2183 ΚΟΥΝΤΕΡ ΙΩΑΝΝΗΣ
Driver:         7329 ΚΟΥΝΤΕΡ ΙΩΑΝΝΗΣ
Vehicle:        3160 XZA3232 / ΧΖΑ3232
Alt vehicle:    13191 XRM5435 / ΧΡΜ5435
```

## Files included

```text
gov.cabnet.app_sql/2026_05_16_kounter_driver_vehicle_lessor_mapping.sql
docs/KOUNTER_EDXEIX_MAPPING_PATCH_2026_05_16.md
PATCH_README.md
```

## Exact upload paths

Upload/run this SQL file on the server:

```text
/home/cabnet/gov.cabnet.app_sql/2026_05_16_kounter_driver_vehicle_lessor_mapping.sql
```

For GitHub Desktop/local repo, extract the package at the repo root so the files land here:

```text
gov.cabnet.app_sql/2026_05_16_kounter_driver_vehicle_lessor_mapping.sql
docs/KOUNTER_EDXEIX_MAPPING_PATCH_2026_05_16.md
PATCH_README.md
```

## SQL to run

Run:

```sql
SOURCE /home/cabnet/gov.cabnet.app_sql/2026_05_16_kounter_driver_vehicle_lessor_mapping.sql;
```

If using phpMyAdmin, open/import the same SQL file.

## Verification URLs

```text
https://gov.cabnet.app/ops/mappings.php?view=unmapped
https://gov.cabnet.app/ops/mappings.php?view=unmapped&limit=200&format=json
```

## Expected result

```text
Ioannis Kounter is mapped as driver 7329 under lessor 2183.
XZA3232 is mapped as vehicle 3160 under lessor 2183.
XRM5435 is mapped as vehicle 13191 under lessor 2183.
No EDXEIX submission is enabled by this patch.
```

## Git commit title

```text
Add Ioannis Kounter EDXEIX mapping
```

## Git commit description

```text
Added verified SQL mapping for Ioannis Kounter, including EDXEIX lessor/company, driver, and vehicle IDs.

This is an idempotent database mapping patch only. It does not change live-submit gates, call EDXEIX, call Bolt, call AADE, or create submission jobs.
```
