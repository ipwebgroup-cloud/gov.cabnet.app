# V3.1 Scope — Closed-Gate Live Adapter Preparation

## Purpose

Prepare the V3 live-submit path technically while keeping the master gate closed and live EDXEIX submission disabled.

This phase is not the live-submit phase. It is the preparation, auditability, packaging, and rehearsal phase.

## Non-negotiable safety rules

- Do not touch V0 laptop/manual production helper files or dependencies.
- Do not enable live EDXEIX submission.
- Do not change the live-submit config to enabled/live.
- Do not remove the master gate, acknowledgement phrase, adapter disabled state, hard-enable requirement, or operator approval requirement.
- Do not submit historical, expired, cancelled, terminal, invalid, synthetic, or past rides.
- Do not submit forwarded demo emails live.
- Do not expose credentials, session files, cookies, tokens, logs containing secrets, or raw dumps.
- Keep patches plain PHP/mysqli/cPanel-compatible.

## Current proven baseline

The V3 pipeline has proven the readiness path using a forwarded email:

```text
maildir intake → parser → mapping → future guard → starting-point guard → submit_dry_run_ready → live_submit_ready
```

Payload audit passed for the proof row. Final rehearsal correctly blocked because the master gate remains closed.

## In scope for V3.1

### 1. V3 proof dashboard

Add:

```text
public_html/gov.cabnet.app/ops/pre-ride-email-v3-proof.php
```

The page should show:

- latest `live_submit_ready` row
- payload audit summary
- final rehearsal block reasons
- gate state
- mapping IDs
- starting-point verification
- no-live-call safety statement

No DB writes.

### 2. EDXEIX live adapter field map

Add:

```text
docs/V3_EDXEIX_LIVE_ADAPTER_FIELD_MAP.md
```

Map V3 payload fields to EDXEIX form fields:

```text
lessorId           → lessor
driverId           → driver
vehicleId          → vehicle
startingPointId    → starting_point
passengerName      → lessee_name
pickupAddress      → boarding_point
dropoffAddress     → disembark_point
pickupDateTime     → started_at
endDateTime        → ended_at
priceAmount        → price
```

### 3. Dry-run package export

Add a CLI/page that exports what the future live adapter would submit without calling EDXEIX.

Suggested artifacts:

```text
/home/cabnet/gov.cabnet.app_app/storage/artifacts/v3_live_submit_packages/queue_<id>_payload.json
/home/cabnet/gov.cabnet.app_app/storage/artifacts/v3_live_submit_packages/queue_<id>_edxeix_fields.json
/home/cabnet/gov.cabnet.app_app/storage/artifacts/v3_live_submit_packages/queue_<id>_safety_report.txt
```

### 4. Operator approval visibility

Add:

```text
/ops/pre-ride-email-v3-operator-approvals.php
```

Show approval state only. Do not approve from the page unless a later explicitly approved patch adds that behavior.

### 5. Closed-gate live adapter skeleton

Add a future adapter class or wrapper that can be wired into rehearsal, but always blocks unless the master gate is open.

Expected initial behavior:

```text
adapter exists: yes
can submit: no
reason: master gate disabled
```

## Out of scope for V3.1

- Enabling live submit.
- Real EDXEIX POST/submit action.
- AADE receipt changes.
- V0 production helper changes.
- Browser automation changes.
- cPanel cron schedule changes unless specifically needed and reviewed.
- Schema-destructive SQL.
- Removing old V3 crons before another real future-safe production-style email test.

## Exit criteria for V3.1

V3.1 is complete when:

```text
live_submit_ready row exists
payload audit passes
live-package export produces expected artifacts
final rehearsal blocks only due to intentional gate/approval blocks
closed-gate adapter skeleton blocks safely
Ops pages clearly show proof and gate state
```

## Later V3.2 candidate

Only after Andreas explicitly approves:

```text
V3.2 — Real Live Adapter Gate Opening Plan
```

That phase must require a real eligible future Bolt trip and explicit gate opening approval.
