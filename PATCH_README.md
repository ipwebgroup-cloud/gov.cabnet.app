# Patch: EDXEIX dropdown snapshot tools for mapping page

## What changed

- Updated `public_html/gov.cabnet.app/ops/mappings.php`.
- Added a copyable EDXEIX console scraper to the mapping page.
- Added JSON snapshot import for EDXEIX lessors, drivers, vehicles, and starting points.
- Added local snapshot counters.
- Added editable `EDXEIX Lessor ID` fields beside driver and vehicle EDXEIX IDs.
- Added an additive SQL migration for snapshot tables and `edxeix_lessor_id` columns.

## Files included

```text
public_html/gov.cabnet.app/ops/mappings.php
gov.cabnet.app_sql/2026_05_17_edxeix_mapping_page_snapshot_tools.sql
docs/MAPPING_PAGE_EDXEIX_SNAPSHOT_TOOL_2026_05_17.md
PATCH_README.md
```

## Exact upload paths

```text
/home/cabnet/public_html/gov.cabnet.app/ops/mappings.php
/home/cabnet/gov.cabnet.app_sql/2026_05_17_edxeix_mapping_page_snapshot_tools.sql
```

Documentation may be committed locally under:

```text
docs/MAPPING_PAGE_EDXEIX_SNAPSHOT_TOOL_2026_05_17.md
PATCH_README.md
```

## SQL to run

Run/import:

```text
gov.cabnet.app_sql/2026_05_17_edxeix_mapping_page_snapshot_tools.sql
```

## Verification URLs

```text
https://gov.cabnet.app/ops/mappings.php
https://gov.cabnet.app/ops/mappings.php?view=unmapped
https://gov.cabnet.app/ops/mappings.php?view=unmapped&limit=200&format=json
```

## Expected result

- `/ops/mappings.php` loads without PHP errors.
- A new `EDXEIX dropdown scraper + snapshot import` section appears.
- Snapshot counters appear after migration.
- Driver and vehicle tables show `EDXEIX Lessor ID`.
- Existing driver/vehicle ID editing still works with the same confirmation phrases:

```text
UPDATE DRIVER MAPPING
UPDATE VEHICLE MAPPING
```

- Snapshot import requires this exact phrase:

```text
IMPORT EDXEIX SNAPSHOT
```

## Safety

This patch does not enable live EDXEIX submission. It does not call Bolt, EDXEIX, AADE, or create queue jobs. The browser console scraper is read-only GET/select-option extraction only.

## Git commit title

```text
Add EDXEIX dropdown snapshot tools to mappings page
```

## Git commit description

```text
Enhanced the Bolt to EDXEIX mapping page with a safe browser-console EDXEIX dropdown scraper, JSON snapshot import, snapshot counters, and optional EDXEIX lessor/company ID editing for driver and vehicle mappings.

The patch is additive and guarded. It does not call Bolt, submit to EDXEIX, call AADE, create jobs, or modify live-submit gates.
```
