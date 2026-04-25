# Patch: Live Submit Gate Production Readiness Refinement

## Purpose

Refines the disabled live EDXEIX submit gate so operators can clearly see:

- why live submission is currently blocked
- what requirements are still missing
- what must pass before the first live EDXEIX submission
- the safe runbook for production by the 1st

## Files included

```text
public_html/gov.cabnet.app/ops/live-submit.php
docs/LIVE_SUBMIT_PRODUCTION_READINESS.md
docs/PRODUCTION_BY_FIRST_CHECKLIST.md
HANDOFF.md
CONTINUE_PROMPT.md
PATCH_README.md
```

## Upload path

```text
public_html/gov.cabnet.app/ops/live-submit.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/live-submit.php
```

Commit the docs/root files to GitHub.

## SQL

No SQL required for this patch. The live audit table was created in the previous patch.

## Verification

Open:

```text
https://gov.cabnet.app/ops/live-submit.php
https://gov.cabnet.app/ops/live-submit.php?format=json
```

Expected:

```text
LIVE HTTP TRANSPORT BLOCKED
Live-ready rows: 0
Why live submission is blocked now section visible
First Live Submit Requirements section visible
No EDXEIX HTTP request performed
```

## Safety

This patch does not call Bolt, does not call EDXEIX, does not write to the database on GET, and does not enable live HTTP transport.
