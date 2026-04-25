# Patch: Replace legacy ops index with safe landing page

## Files included

```text
public_html/gov.cabnet.app/ops/index.php
docs/SAFE_OPS_INDEX.md
HANDOFF.md
CONTINUE_PROMPT.md
PATCH_README.md
```

## Upload path

```text
public_html/gov.cabnet.app/ops/index.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/index.php
```

## SQL

No SQL required.

## Verify

Open:

```text
https://gov.cabnet.app/ops/index.php
```

Expected:

- read-only Operations Console landing page loads
- current safe workflow tools are linked
- no POST forms are present
- no manual booking/session/queue actions are present

## Safety

No Bolt request, EDXEIX request, database write, queue creation, or live submission behavior is introduced.
