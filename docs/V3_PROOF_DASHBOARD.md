# V3 Proof Dashboard — Historical Proof Preservation

Version: v3.0.51-proof-dashboard-history-fix

## Purpose

The V3 proof dashboard is a read-only Ops page that summarizes the verified forwarded-email readiness proof while keeping live EDXEIX submission disabled.

The dashboard now distinguishes between:

- a **current** `live_submit_ready` queue row, and
- a **historical** proof row that previously reached live-readiness but was later safely blocked by the expiry guard after pickup time passed.

This is important because V3 rows are intentionally expired/blocked after the pickup time is no longer future-safe. A successful proof row should not be treated as a failure simply because the expiry guard later did its job.

## Verified behavior

A forwarded Gmail/Bolt-style pre-ride email was processed through:

```text
server mailbox
→ V3 intake
→ parser
→ mapping
→ future-safe guard
→ verified starting-point guard
→ submit_dry_run_ready
→ live_submit_ready
→ payload audit ready
→ final rehearsal blocked by master gate
```

The final rehearsal correctly remained blocked because live submit is disabled by configuration and operator approval is absent.

## Safety

The dashboard is read-only:

- no Bolt call
- no EDXEIX call
- no AADE call
- no DB writes
- no queue mutation
- no live-submit gate changes
- no V0 laptop/manual helper changes

## Page

```text
/ops/pre-ride-email-v3-proof.php
```

## Expected after proof row expiry

It is acceptable for the dashboard to show:

```text
Historical live-ready proof found
No current live-ready row
Proof row status: blocked
Reason: pickup expired / no longer future-safe
Master gate: closed
```

This means the proof was achieved and the expiry guard later blocked the row safely.
