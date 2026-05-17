# gov.cabnet.app patch — Mapping Workbench V3

## What changed

Added a Version 3 mapping workflow page and wired it into the shared V3 operations interface/navigation.

The new page groups:

- Bolt driver
- Bolt active vehicle
- EDXEIX lessor/company
- EDXEIX driver ID
- EDXEIX vehicle ID
- snapshot-based suggestions and validation

## Files included

```text
public_html/gov.cabnet.app/ops/mapping-workbench-v3.php
public_html/gov.cabnet.app/ops/_shell.php
public_html/gov.cabnet.app/ops/_mapping_nav.php
public_html/gov.cabnet.app/ops/mapping-center.php
gov.cabnet.app_sql/2026_05_17_mapping_workbench_v3.sql
docs/MAPPING_WORKBENCH_V3_2026_05_17.md
PATCH_README.md
```

## Upload paths

Upload:

```text
public_html/gov.cabnet.app/ops/mapping-workbench-v3.php
```

to:

```text
/home/cabnet/public_html/gov.cabnet.app/ops/mapping-workbench-v3.php
```

Upload:

```text
public_html/gov.cabnet.app/ops/_shell.php
public_html/gov.cabnet.app/ops/_mapping_nav.php
public_html/gov.cabnet.app/ops/mapping-center.php
```

to:

```text
/home/cabnet/public_html/gov.cabnet.app/ops/_shell.php
/home/cabnet/public_html/gov.cabnet.app/ops/_mapping_nav.php
/home/cabnet/public_html/gov.cabnet.app/ops/mapping-center.php
```

Upload:

```text
gov.cabnet.app_sql/2026_05_17_mapping_workbench_v3.sql
```

to:

```text
/home/cabnet/gov.cabnet.app_sql/2026_05_17_mapping_workbench_v3.sql
```

## SQL to run

Run/import:

```text
/home/cabnet/gov.cabnet.app_sql/2026_05_17_mapping_workbench_v3.sql
```

## Verification commands

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/mapping-workbench-v3.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/_shell.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/_mapping_nav.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/mapping-center.php
```

## Verification URLs

```text
https://gov.cabnet.app/ops/mapping-workbench-v3.php
https://gov.cabnet.app/ops/mapping-workbench-v3.php?view=needs_map
https://gov.cabnet.app/ops/mapping-workbench-v3.php?view=suggestions
https://gov.cabnet.app/ops/mapping-workbench-v3.php?format=json&view=needs_map
https://gov.cabnet.app/ops/mapping-center.php
```

## Expected result

- The V3 shell loads normally.
- Navigation includes `Mapping Workbench V3`.
- Mapping Center links to the new workbench.
- The workbench shows grouped driver + active vehicle cards.
- Snapshot counters show available EDXEIX exports after JSON import.
- Guarded local updates require `UPDATE VERIFIED MAPPING`.
- No Bolt, EDXEIX, AADE, live-submit, or queue-job action is performed.

## Git commit title

```text
Add Mapping Workbench V3 to ops interface
```

## Git commit description

```text
Added a Version 3 Mapping Workbench page for grouped Bolt driver, active vehicle, and EDXEIX lessor mapping.

The workbench supports EDXEIX dropdown snapshot import, snapshot-based driver/vehicle suggestions, lessor validation, conflict visibility, and guarded one-shot verified mapping updates with local audit rows.

Updated the shared V3 operations shell, mapping navigation partial, and Mapping Center to include the new workbench.

This patch is additive and guarded. It does not call Bolt, submit to EDXEIX, call AADE, create queue jobs, or change live-submit gates.
```
