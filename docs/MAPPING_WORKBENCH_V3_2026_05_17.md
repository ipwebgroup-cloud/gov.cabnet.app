# Mapping Workbench V3 — 2026-05-17

## Purpose

This patch adds a Version 3 mapping workflow page for the gov.cabnet.app Bolt → EDXEIX bridge.

The page groups a Bolt driver with the active Bolt vehicle and the EDXEIX lessor/company ID so mapping can be verified and updated as one operational unit.

## New page

```text
/ops/mapping-workbench-v3.php
```

## Safety posture

- GET requests are read-only.
- POST updates are guarded by confirmation phrases.
- Updates are limited to local mapping tables and local audit rows.
- No Bolt API call is made.
- No EDXEIX submit/save call is made.
- No AADE call is made.
- No submission jobs are created.
- No live-submit gates are changed.
- EDXEIX snapshot exports contain dropdown IDs/labels only. They must not include cookies, CSRF tokens, credentials, or session data.

## Added workflow

1. Open Mapping Workbench V3 from the Version 3 navigation.
2. Copy the EDXEIX snapshot scraper.
3. Run it only inside an already logged-in EDXEIX lease-agreement create page.
4. Import the sanitized JSON snapshot into Mapping Workbench V3.
5. Review grouped driver + active vehicle rows.
6. Confirm the EDXEIX lessor, driver, and vehicle IDs.
7. Type the confirmation phrase:

```text
UPDATE VERIFIED MAPPING
```

8. Submit the guarded local update.

## Snapshot validation

When snapshot tables exist and contain data, normal updates validate that:

- the EDXEIX lessor ID exists in the imported snapshot,
- the EDXEIX driver ID exists under the selected lessor,
- the EDXEIX vehicle ID exists under the selected lessor.

Manual override is possible only with:

```text
OVERRIDE SNAPSHOT VALIDATION
```

Use this only after independent visual confirmation in EDXEIX.

## Navigation changes

The Version 3 shared shell now includes:

```text
Mapping Workbench V3
```

in the Admin dropdown, sidebar, and main tab row.

The Mapping Center and mapping navigation partial also link to the new workbench.

## SQL migration

Run:

```text
gov.cabnet.app_sql/2026_05_17_mapping_workbench_v3.sql
```

The migration is additive and cPanel/phpMyAdmin-safe. It does not use `information_schema` verification queries.

## Confirmation phrases

Snapshot import:

```text
IMPORT EDXEIX SNAPSHOT
```

Verified mapping update:

```text
UPDATE VERIFIED MAPPING
```

Snapshot validation override:

```text
OVERRIDE SNAPSHOT VALIDATION
```
