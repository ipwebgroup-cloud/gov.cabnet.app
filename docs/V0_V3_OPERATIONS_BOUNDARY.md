# gov.cabnet.app — V0 / V3 Operations Boundary

## Current operating rule

V0 and V3 are intentionally separate during this stage.

- **V0** is installed on the laptop and remains the current manual/production helper.
- **V3** is installed on the PC/server path and remains the development/test automation path.
- Do not modify V0 production files, browser helper dependencies, or the operator's current working laptop setup as part of V3 patches.
- Do not make V3 responsible for deciding whether the operator should use V0. Andreas uses operational judgment.

## V3 purpose right now

V3 should continue moving toward safe automation:

```text
Bolt pre-ride email intake
→ V3 queue
→ starting-point guard
→ submit dry-run readiness
→ live readiness
→ payload audit
→ locked live-submit scaffold
```

Live EDXEIX submission remains disabled unless Andreas explicitly requests a live-submit gate change.

## V3 safety posture

V3 patches must remain safe by default:

- no live EDXEIX submission
- no AADE calls
- no production submission table writes
- no secrets in Git/package files
- no changes to V0 helper files/dependencies
- no automatic decision-making that replaces operator judgment

## Operational fallback rule

During real rides, the business must continue functioning. If V3 is not immediately useful, Andreas may use V0/manual without waiting for V3 diagnostics.

V3 should be improved in the background as a separate, safe automation track.
