# Real Future Bolt Test Checklist

This patch adds `/ops/future-test.php`, a guarded read-only checklist page for the next real Bolt future-ride preflight.

## Purpose

The page answers one operational question:

> Is the bridge clean and ready for the next real Bolt future-trip preflight test?

It separates readiness for **preflight validation** from any idea of **live EDXEIX submission**.

## Safety contract

The page does not:

- call Bolt
- call EDXEIX
- create queue jobs
- update the database
- print secrets
- authorize live submission

## Checks shown

The page checks:

- dry-run mode enabled
- Bolt config present
- EDXEIX lessor configured
- EDXEIX starting point configured
- at least one mapped driver exists
- at least one mapped vehicle exists
- no LAB/test normalized rows remain
- no staged LAB jobs remain
- no local submission jobs are queued
- no live EDXEIX attempts are indicated
- whether a real future Bolt candidate exists
- live submission remains unauthorized

## Verification URLs

```text
https://gov.cabnet.app/ops/future-test.php
https://gov.cabnet.app/ops/future-test.php?format=json
```

## Expected state before a real Bolt future ride

The page should show the system is clean and waiting for a real future Bolt row.

## Expected state after a real future Bolt ride exists

If the real row is mapped, non-terminal, non-LAB, and at least the configured guard window in the future, the page should show a real future candidate ready for preflight-only validation.

## Live submission

A passing checklist does not enable live submission. Live EDXEIX submission remains disabled until Andreas explicitly approves a separate live-submit patch.
