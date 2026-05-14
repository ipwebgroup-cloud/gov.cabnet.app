# V3 Live Adapter Runbook — Closed-Gate Preparation

Version: `v3.0.57-v3-live-adapter-runbook`
Project: `gov.cabnet.app` Bolt pre-ride email V3 automation
Status: planning / runbook only

## Current verified state

V3 has proven the full readiness path using a forwarded Gmail/Bolt-style pre-ride email:

```text
forwarded Gmail email
→ server mailbox
→ V3 intake
→ parser
→ driver / vehicle / lessor mapping
→ future-safe guard
→ verified starting-point guard
→ submit_dry_run_ready
→ live_submit_ready
→ payload audit PAYLOAD-READY
→ final rehearsal blocked by master gate
```

Verified proof row:

```text
queue_id: 56
customer: Arnaud BAGORO
driver: Filippos Giannakopoulos
vehicle: EHA2545
lessor_id: 3814
driver_id: 17585
vehicle_id: 5949
starting_point_id: 6467495
historical status reached: live_submit_ready
current status after expiry: blocked
```

The expiry guard later blocked the row because pickup time passed. That is correct and safe.

## Current safety posture

```text
V0 laptop/manual helper: untouched
Live EDXEIX submit: disabled
Master gate: closed
Adapter selected: disabled
Future adapter skeleton: installed, not live-capable
Operator approval records: none valid
AADE behavior: untouched
Production submission tables: untouched
Cron schedules: unchanged
SQL schema: unchanged
```

## Verified V3 components

```text
V3 pulse cron: healthy
Storage/lock ownership: cabnet:cabnet / 0660
Forwarded-email intake: proven
Parser: proven
Mapping: proven
Starting-point verification: proven
Submit dry-run readiness: proven
Live readiness: proven
Payload audit: proven
Final rehearsal: blocked correctly by gate
Package export: proven
Operator approval visibility: installed
Closed-gate adapter diagnostics: installed
Future real adapter skeleton: installed
Adapter contract probe: proven
```

## What must remain true before live adapter implementation

1. No V0 files or V0 dependencies may be changed.
2. No live EDXEIX submission may be enabled without explicit Andreas approval.
3. No historical, expired, cancelled, terminal, invalid, test, or past row may ever be submitted.
4. EMT8640 remains permanently exempt and must never enter V3 live submit.
5. The master gate must remain closed while developing the adapter.
6. All work must continue as plain PHP / mysqli / cPanel-compatible code.
7. No Composer, Node, frameworks, or heavy dependencies.
8. No real credentials may be committed or exposed.

## Future live adapter implementation direction

The future adapter class is now present at:

```text
gov.cabnet.app_app/src/BoltMailV3/EdxeixLiveSubmitAdapterV3.php
```

Current behavior:

```text
name(): edxeix_live_skeleton
isLiveCapable(): false
submit(): blocked, no EDXEIX call
```

The future implementation should only become live-capable after all gate and approval conditions are satisfied and Andreas explicitly approves the live-submit change.

## Adapter contract

The adapter implements:

```php
Bridge\BoltMailV3\LiveSubmitAdapterV3
```

Required methods:

```php
public function name(): string;
public function isLiveCapable(): bool;
public function submit(array $edxeixPayload, array $context = []): array;
```

A real adapter must never return `submitted=true` unless a real confirmed EDXEIX submission occurred.

## Future adapter safety envelope

A real adapter must reject submission unless all of these are true:

```text
queue_status = live_submit_ready
pickup_datetime is still future-safe
row is not expired
row is not cancelled/terminal/invalid
vehicle is not exempt
driver_id is present
vehicle_id is present
lessor_id is present
starting_point_id is present and verified for lessor
payload audit passes
operator approval exists and is valid
master gate enabled=true
master gate mode=live
master gate adapter=edxeix_live
hard_enable_live_submit=true
required acknowledgement phrase is present
adapter confirms live-capable=true
```

## First implementation should still be closed-gate

The next coding phase should not jump directly to live submit. It should first add:

```text
1. Adapter package loader
2. Adapter dry-run verifier using exported package fields
3. Explicit gate check before adapter selection
4. Result envelope validation
5. Local evidence artifact writing
6. No EDXEIX network call until final approved phase
```

## Suggested next patch sequence

```text
v3.0.58-v3-live-adapter-result-envelope
v3.0.59-v3-live-adapter-evidence-artifacts
v3.0.60-v3-real-adapter-http-plan-docs
v3.0.61-v3-operator-approval-write-scaffold-closed
v3.0.62-v3-final-prelive-dry-run-test
```

## Do not do yet

```text
Do not set enabled=true
Do not set mode=live
Do not set adapter=edxeix_live
Do not set hard_enable_live_submit=true
Do not add credentials to repo
Do not submit synthetic/forwarded emails live
Do not submit expired proof rows live
```
