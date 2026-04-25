# Patch: Add mapping coverage dashboard

## Purpose

Adds a read-only operations page for Bolt → EDXEIX mapping coverage.

New page:

```text
/ops/mappings.php
```

## Files included

```text
public_html/gov.cabnet.app/ops/mappings.php
docs/MAPPING_COVERAGE_DASHBOARD.md
HANDOFF.md
CONTINUE_PROMPT.md
PATCH_README.md
```

## Upload paths

```text
public_html/gov.cabnet.app/ops/mappings.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/mappings.php
```

For GitHub, commit:

```text
docs/MAPPING_COVERAGE_DASHBOARD.md
HANDOFF.md
CONTINUE_PROMPT.md
PATCH_README.md
```

## SQL

No SQL is required.

## Verification

Open:

```text
https://gov.cabnet.app/ops/mappings.php
```

Expected:

```text
Drivers mapped: 1/2
Vehicles mapped: 2/15
```

Open unmapped view:

```text
https://gov.cabnet.app/ops/mappings.php?view=unmapped
```

Open JSON:

```text
https://gov.cabnet.app/ops/mappings.php?format=json
```

## Safety

The page is read-only. It does not call Bolt, does not call EDXEIX, does not write to the database, and does not enable live submission.
