# V3 Real Adapter Implementation Gates

Version: v3.0.66-v3-real-adapter-design-spec

## Gate 0 — Current state

Current verified state:

```text
V3 closed-gate automation proof: complete
V3 approval workflow: complete
V3 package export: complete
V3 switchboard: complete
V3 kill-switch check: complete
Future adapter skeleton: present but non-live-capable
Master gate: closed
Live submit: disabled
V0: untouched
```

## Gate 1 — Design only

Before any live-capable code is written:

- Design must be committed.
- Handoff and continue prompts must identify live submit as disabled.
- No production credentials may be requested or committed.
- No EDXEIX call paths may be enabled.

## Gate 2 — Adapter dry constructor

Next implementation may only add safe non-live behavior:

- load adapter class
- validate payload
- return blocked envelope
- confirm `isLiveCapable() === false`
- confirm no network call exists

## Gate 3 — Adapter simulation mode

A later patch may add local-only simulation:

- no external HTTP
- local fixture response only
- result envelope only
- `submitted` must remain false
- worker must not write production submission rows

## Gate 4 — Live-capable code present but unreachable

Only with explicit approval, code for the real submit transport may be added while still unreachable:

- config remains disabled
- adapter remains not selected
- kill-switch must still fail
- final rehearsal must still fail
- no cron may call it live

## Gate 5 — First controlled live-submit preparation

Before first real submit, the following must be true:

- real future-safe Bolt pre-ride row exists
- row is `live_submit_ready`
- pickup is sufficiently in the future
- package export exists
- payload audit passes
- operator approval is valid
- kill-switch shows only intentionally controlled master-gate conditions before gate opening
- Andreas explicitly approves live-submit gate opening for that specific row/test

## Gate 6 — Emergency stop

Emergency stop is always:

1. set config to disabled
2. set adapter to disabled
3. set hard enable to false
4. revoke approvals if needed
5. keep pulse cron safe
6. inspect logs/artifacts

No live adapter implementation should bypass this stop procedure.
