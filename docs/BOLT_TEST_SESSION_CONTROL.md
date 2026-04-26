# Bolt Test Session Control v1.5

## Purpose

Adds a low-risk operator page for the next real future Bolt ride test:

```text
/ops/test-session.php
```

The page consolidates the current safe workflow into one control surface:

1. Confirm readiness.
2. Open Dev Accelerator.
3. Capture accepted / pickup / started / completed snapshots.
4. Review Evidence Bundle.
5. Export the Markdown Evidence Report.
6. Review preflight JSON only.
7. Stop before live EDXEIX submission.

## Safety contract

The page itself:

- does not call Bolt
- does not call EDXEIX
- does not stage jobs
- does not update mappings
- does not write database rows
- does not write files
- does not enable live submission

It reads the existing readiness audit only.

The capture links intentionally send the operator to the existing Dev Accelerator dry-run probe URLs. Those probes remain sanitized and dry-run only.

## Verification

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/test-session.php
```

Browser:

```text
https://gov.cabnet.app/ops/test-session.php
https://gov.cabnet.app/ops/test-session.php?format=json
```

## Expected current state

Before a real future Bolt ride exists, the page should show:

- readiness verdict: `READY_FOR_REAL_BOLT_FUTURE_TEST`
- real future candidates: `0`
- live submit off
- no local queue jobs
- no live attempts

## Recommended operational use

Use this page as the primary entry point during the next real test ride.
