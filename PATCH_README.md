# Patch: Real Future Bolt Test Checklist

## What changed

Adds a guarded read-only operations page:

```text
public_html/gov.cabnet.app/ops/future-test.php
```

The page uses the existing readiness audit and displays a focused checklist for the next real Bolt future-ride preflight test.

## Files included

```text
public_html/gov.cabnet.app/ops/future-test.php
docs/REAL_FUTURE_TEST_CHECKLIST.md
HANDOFF.md
CONTINUE_PROMPT.md
PATCH_README.md
```

## Upload path

```text
public_html/gov.cabnet.app/ops/future-test.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/future-test.php
```

## SQL

No SQL required.

## Verification

Open:

```text
https://gov.cabnet.app/ops/future-test.php
https://gov.cabnet.app/ops/future-test.php?format=json
```

Expected before a real future Bolt ride exists:

```text
READY TO CREATE REAL FUTURE TEST RIDE
candidate_count: 0
live_submission_authorized: false
```

Expected after a real future Bolt ride exists and passes checks:

```text
REAL FUTURE CANDIDATE READY FOR PREFLIGHT
candidate_count >= 1
live_submission_authorized: false
```

## Safety

This patch does not call Bolt, does not call EDXEIX, does not create jobs, and does not write to the database.
