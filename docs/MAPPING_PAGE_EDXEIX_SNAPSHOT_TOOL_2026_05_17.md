# gov.cabnet.app — Mapping Page EDXEIX Snapshot Tool

Date: 2026-05-17

## Purpose

This patch upgrades `/ops/mappings.php` so the mapping page can support the same EDXEIX dropdown-ID extraction workflow previously used from the browser console.

The page now includes:

- A copyable EDXEIX console scraper.
- A JSON snapshot import form.
- Local snapshot counters for EDXEIX lessors, drivers, vehicles, and starting points.
- Optional `edxeix_lessor_id` editing beside driver and vehicle EDXEIX IDs.

## Safety posture

The console scraper:

- Runs inside the logged-in EDXEIX browser tab.
- Performs read-only `GET` requests against EDXEIX create-form pages.
- Extracts select option values and labels only.
- Does not submit a form.
- Does not save a contract.
- Does not export cookies, tokens, CSRF values, passwords, or sessions.

The gov.cabnet.app import:

- Stores local reference snapshot rows only.
- Does not call Bolt.
- Does not call EDXEIX.
- Does not call AADE.
- Does not create submission jobs.
- Does not enable live submission.

## Operator workflow

1. Upload the patched `mappings.php`.
2. Run the SQL migration.
3. Open `https://gov.cabnet.app/ops/mappings.php`.
4. Copy the console scraper from the new EDXEIX dropdown scraper section.
5. Open EDXEIX create contract page while logged in.
6. Open Firefox DevTools Console.
7. Paste and run the scraper.
8. Download/copy the exported JSON.
9. Paste the JSON into the mapping page import form.
10. Type `IMPORT EDXEIX SNAPSHOT` and import.
11. Use the refreshed dropdown reference data to update driver/vehicle mappings safely.

## Exact upload paths

```text
/home/cabnet/public_html/gov.cabnet.app/ops/mappings.php
/home/cabnet/gov.cabnet.app_sql/2026_05_17_edxeix_mapping_page_snapshot_tools.sql
```

## Verification URLs

```text
https://gov.cabnet.app/ops/mappings.php
https://gov.cabnet.app/ops/mappings.php?view=unmapped
https://gov.cabnet.app/ops/mappings.php?view=unmapped&limit=200&format=json
```

## Expected result

The mapping page shows a new section named:

```text
EDXEIX dropdown scraper + snapshot import
```

The page also shows `EDXEIX Lessor ID` columns in both driver and vehicle mapping tables.

## Commit title

```text
Add EDXEIX dropdown snapshot tools to mappings page
```

## Commit description

```text
Enhanced the Bolt to EDXEIX mapping page with a safe browser-console EDXEIX dropdown scraper, JSON snapshot import, snapshot status counters, and optional EDXEIX lessor/company ID editing for driver and vehicle mappings.

The patch is additive and guarded. It does not call Bolt, does not submit to EDXEIX, does not call AADE, does not create jobs, and does not modify live-submit gates.
```
