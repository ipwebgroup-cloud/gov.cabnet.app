# Patch: Add LAB Dry-Run Cleanup Tool

## Files included

```text
public_html/gov.cabnet.app/ops/cleanup-lab.php
gov.cabnet.app_sql/2026_04_25_lab_cleanup_preview.sql
docs/LAB_DRY_RUN_CLEANUP.md
HANDOFF.md
CONTINUE_PROMPT.md
PATCH_README.md
```

## Upload paths

```text
public_html/gov.cabnet.app/ops/cleanup-lab.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/cleanup-lab.php

gov.cabnet.app_sql/2026_04_25_lab_cleanup_preview.sql
→ /home/cabnet/gov.cabnet.app_sql/2026_04_25_lab_cleanup_preview.sql
```

For GitHub, also commit:

```text
docs/LAB_DRY_RUN_CLEANUP.md
HANDOFF.md
CONTINUE_PROMPT.md
PATCH_README.md
```

## SQL

No required migration.

Optional read-only preview command:

```bash
mysql cabnet_gov < /home/cabnet/gov.cabnet.app_sql/2026_04_25_lab_cleanup_preview.sql
```

## Verification

Open:

```text
https://gov.cabnet.app/ops/cleanup-lab.php
```

Expected before cleanup:

```text
LAB/test normalized bookings: 1
Linked local jobs: 1
Linked attempts: 1
Unsafe/unclassified attempts: 0
```

To delete, type exactly:

```text
DELETE LOCAL LAB DRY RUN DATA
```

Expected after cleanup:

```text
LAB/test normalized bookings: 0
Linked local jobs: 0
Linked attempts: 0
Unsafe/unclassified attempts: 0
```

Then verify:

```text
https://gov.cabnet.app/ops/readiness.php
https://gov.cabnet.app/ops/jobs.php
```

## Safety

- No Bolt call.
- No EDXEIX call.
- No live submission.
- No GET deletion.
- Exact POST confirmation required.
- Refuses cleanup if linked attempts are not clearly dry-run/no-live.
