# V3 Next Phase Plan

## Phase name

V3.1 — Closed-Gate Live Adapter Preparation

## Why this phase exists

The V3 readiness path is proven. The next risk is not intake or parsing; it is preparing the live adapter without accidentally opening live submission.

This phase creates clarity, artifacts, and review points before any future live gate opening.

## Recommended patch sequence

### v3.0.49 — V3 proof dashboard

Add a read-only page:

```text
/ops/pre-ride-email-v3-proof.php
```

Show:

- latest `live_submit_ready` row
- payload audit status
- final rehearsal status
- master gate blocks
- operator approval status
- no-live-call safety statement

### v3.0.50 — EDXEIX live adapter field map

Add:

```text
docs/V3_EDXEIX_LIVE_ADAPTER_FIELD_MAP.md
```

Define exactly how V3 payload fields map to EDXEIX fields.

### v3.0.51 — V3 live package export

Add dry-run export artifacts for `live_submit_ready` rows.

No EDXEIX call.
No AADE call.
No queue status change unless explicitly reviewed later.

### v3.0.52 — Operator approval visibility

Add read-only operator approval page.

Do not add approval mutation unless Andreas explicitly asks.

### v3.0.53 — Closed-gate adapter skeleton

Add the real adapter class or wrapper shape, but force it to block unless master gate is fully open.

Expected result:

```text
adapter exists: yes
submission allowed: no
reason: master gate disabled
```

### v3.0.54 — Re-test with future forwarded email

Run another future forwarded pre-ride email and confirm:

```text
live_submit_ready
payload audit OK
package export OK
final rehearsal blocked by gate
closed-gate adapter skeleton blocked by gate
```

## What not to do yet

- Do not enable live-submit config.
- Do not create a real EDXEIX POST/submit action.
- Do not remove V0 fallback.
- Do not remove old V3 crons yet.
- Do not simplify production cron posture until at least one production-style future pre-ride email passes cleanly after V3.1 instrumentation.

## Live-submit opening conditions for a future phase

A future live-submit gate-opening phase must require:

```text
real eligible future Bolt trip
not forwarded/demo
not past
not cancelled
not EMT8640
driver mapped
vehicle mapped
lessor mapped
starting point verified
payload audit OK
package export OK
final rehearsal OK except intentional gate blocks
operator approval valid
master config enabled
mode live
adapter real
hard enable true
acknowledgement phrase present
trip sufficiently in future
Andreas explicitly approves
```
