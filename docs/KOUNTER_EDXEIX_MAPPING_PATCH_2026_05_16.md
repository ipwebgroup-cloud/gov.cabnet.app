# gov.cabnet.app — Ioannis Kounter EDXEIX Mapping Patch

Date: 2026-05-16

## Purpose

This patch records the verified Bolt → EDXEIX mapping for Ioannis Kounter and the two vehicles visible in the EDXEIX form screenshots.

## Verified values

| Entity | EDXEIX value |
|---|---:|
| Lessor/company ΚΟΥΝΤΕΡ ΙΩΑΝΝΗΣ | `2183` |
| Driver ΚΟΥΝΤΕΡ ΙΩΑΝΝΗΣ | `7329` |
| Vehicle ΧΖΑ3232 / XZA3232 | `3160` |
| Vehicle ΧΡΜ5435 / XRM5435 | `13191` |

## Files included

```text
gov.cabnet.app_sql/2026_05_16_kounter_driver_vehicle_lessor_mapping.sql
docs/KOUNTER_EDXEIX_MAPPING_PATCH_2026_05_16.md
PATCH_README.md
```

## Safety posture

- No PHP runtime files are changed.
- No public endpoint is added.
- No credentials, tokens, cookies, session files, logs, or raw payload dumps are included.
- The SQL is additive/update-only and safe to run more than once.
- The SQL does not call Bolt, EDXEIX, or AADE.
- The SQL does not create or submit EDXEIX jobs.

## Upload paths

Upload the SQL file to:

```text
/home/cabnet/gov.cabnet.app_sql/2026_05_16_kounter_driver_vehicle_lessor_mapping.sql
```

Optional documentation path in the local GitHub Desktop repo:

```text
docs/KOUNTER_EDXEIX_MAPPING_PATCH_2026_05_16.md
```

## Run SQL

From cPanel/phpMyAdmin, import/run:

```text
gov.cabnet.app_sql/2026_05_16_kounter_driver_vehicle_lessor_mapping.sql
```

Or from SSH, if you have the usual DB access method available on the server, run the file against the `cabnet_gov` database.

## Expected verification result

After running the SQL:

```text
Driver Ioannis Kounter -> edxeix_driver_id 7329, edxeix_lessor_id 2183
Vehicle XZA3232        -> edxeix_vehicle_id 3160, edxeix_lessor_id 2183
Vehicle XRM5435        -> edxeix_vehicle_id 13191, edxeix_lessor_id 2183
```

Open:

```text
https://gov.cabnet.app/ops/mappings.php?view=unmapped
```

Expected:

- `Ioannis Kounter` should remain absent from unmapped drivers if the driver row was already updated through the UI.
- `XZA3232` and `XRM5435` should disappear from unmapped vehicles.
- Vehicle coverage should increase by 2 rows.

## Git commit title

```text
Add Ioannis Kounter EDXEIX mapping
```

## Git commit description

```text
Added verified Bolt to EDXEIX mapping for Ioannis Kounter.

Maps:
- EDXEIX lessor/company 2183 ΚΟΥΝΤΕΡ ΙΩΑΝΝΗΣ
- EDXEIX driver 7329 ΚΟΥΝΤΕΡ ΙΩΑΝΝΗΣ
- Vehicle XZA3232 / ΧΖΑ3232 to EDXEIX vehicle 3160
- Vehicle XRM5435 / ΧΡΜ5435 to EDXEIX vehicle 13191

The SQL patch is update-only, idempotent, and does not call Bolt, EDXEIX, AADE, or submit jobs.
```
