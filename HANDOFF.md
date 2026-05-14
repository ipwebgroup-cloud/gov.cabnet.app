# HANDOFF.md — gov.cabnet.app V3 Adapter Simulation Proof Checkpoint

Project: gov.cabnet.app Bolt → EDXEIX bridge  
Stack: plain PHP, mysqli/MariaDB, cPanel/manual upload workflow  
Safety posture: V3-only, closed-gate, read-only/proof-first. V0 remains untouched.

## Current verified state

V3 automation has proven the full closed-gate path:

- pre-ride email intake works
- parser/mapping works
- starting-point guard works
- submit dry-run readiness works
- live-submit readiness works
- payload audit works
- package export works
- operator approval workflow works
- final rehearsal accepts valid approval then blocks on master gate
- kill-switch checker works and aligns approval validation
- pre-live switchboard works in browser using direct DB/config renderer
- future EDXEIX adapter skeleton exists and remains non-live-capable
- adapter row simulation works and is safe

## Latest verified milestone: v3.0.67 adapter row simulation

Verified command output showed:

- `Simulation safe: yes`
- `OK: yes`
- `Adapter simulation: class_exists=yes instantiated=yes live_capable=no submitted=no safe=yes`
- adapter message: skeleton present, real EDXEIX submission not implemented/enabled
- no Bolt call
- no EDXEIX call
- no AADE call
- no DB writes
- no queue status changes
- no production submission table writes
- V0 untouched

The simulation selected row `427`, which was already expired/blocked by the time of the test. That is acceptable because the objective was to prove the adapter skeleton can be called with a real local V3 row package while remaining non-live-capable and returning `submitted=false`.

## Key current routes

- `/ops/pre-ride-email-v3-pre-live-switchboard.php`
- `/ops/pre-ride-email-v3-adapter-row-simulation.php`
- `/ops/pre-ride-email-v3-live-adapter-kill-switch-check.php`
- `/ops/pre-ride-email-v3-proof.php`
- `/ops/pre-ride-email-v3-operator-approval-workflow.php`
- `/ops/pre-ride-email-v3-live-package-export.php`

## Critical safety rules

Do not enable live submit unless Andreas explicitly requests a live-submit update.

Live submission must remain blocked unless all are true:

- real eligible future Bolt trip exists
- row is `live_submit_ready`
- pickup is sufficiently future-safe
- payload is complete
- starting point is operator-verified
- package export exists
- operator approval is valid
- master config has `enabled=true`
- mode is `live`
- adapter is `edxeix_live`
- `hard_enable_live_submit=true`
- required acknowledgement phrase is present
- adapter is truly live-capable
- Andreas explicitly approves opening the gate

## Next safest step

Prepare v3.0.69: a dry-run adapter harness that compares:

1. package export payload,
2. adapter simulation payload,
3. final rehearsal payload expectations,

and confirms all hashes/fields match, without making external calls or writes.
