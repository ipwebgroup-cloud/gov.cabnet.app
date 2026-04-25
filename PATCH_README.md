# Patch: Guided Ops Dashboard and Novice Help

## What changed

This patch refines the guarded operations GUI for novice operators.

It updates:

```text
public_html/gov.cabnet.app/ops/index.php
public_html/gov.cabnet.app/ops/future-test.php
```

It adds:

```text
public_html/gov.cabnet.app/ops/help.php
docs/NOVICE_OPERATOR_GUIDE.md
```

## Safety posture

This patch is read-only.

It does not:

- call Bolt
- call EDXEIX
- write to the database
- create queue jobs
- update mappings
- enable live submission

## Upload paths

```text
public_html/gov.cabnet.app/ops/index.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/index.php

public_html/gov.cabnet.app/ops/future-test.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/future-test.php

public_html/gov.cabnet.app/ops/help.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/help.php
```

Commit docs/root files to GitHub:

```text
docs/NOVICE_OPERATOR_GUIDE.md
HANDOFF.md
CONTINUE_PROMPT.md
PATCH_README.md
```

## SQL

No SQL required.

## Verification URLs

```text
https://gov.cabnet.app/ops/index.php
https://gov.cabnet.app/ops/help.php
https://gov.cabnet.app/ops/future-test.php
https://gov.cabnet.app/ops/future-test.php?format=json
```

## Expected result

- `/ops/index.php` shows a 1–6 guided workflow.
- `/ops/help.php` explains the workflow, glossary, blockers, and safety rules.
- `/ops/future-test.php` shows a visual progress rail and novice-friendly next steps.
- Live submission remains disabled.
