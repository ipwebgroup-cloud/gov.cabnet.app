# HANDOFF — gov.cabnet.app Bolt → EDXEIX Bridge

Version: v3.0.70-v3-payload-consistency-proof-checkpoint
Date: 2026-05-14

## Project identity

Domain: https://gov.cabnet.app
Repository: https://github.com/ipwebgroup-cloud/gov.cabnet.app
Stack: plain PHP, mysqli/MariaDB, cPanel/manual upload workflow.

Expected server layout:

```text
/home/cabnet/public_html/gov.cabnet.app
/home/cabnet/gov.cabnet.app_app
/home/cabnet/gov.cabnet.app_config
/home/cabnet/gov.cabnet.app_sql
```

## Current V3 state

V3 closed-gate automation proof is complete through payload consistency harness verification.

Verified v3.0.69 result:

```text
OK: yes
Simulation safe: yes
DB payload hash matches latest package artifact hash
Adapter payload hash matches DB-built payload hash
Adapter live_capable=no
Adapter submitted=no
No Bolt call
No EDXEIX call
No AADE call
No DB writes
No queue status changes
No production submission tables
V0 untouched
```

## Verified row

Queue row `427` was used as a historical/expired proof row after it had already safely moved to blocked. The payload and package artifacts remained consistent.

## Critical safety rules

- Do not enable live EDXEIX submission unless Andreas explicitly asks for a live-submit update.
- Live submission must remain blocked unless there is a real eligible future Bolt trip, all preflight checks pass, operator approval exists, and the trip is sufficiently in the future.
- Historical, cancelled, terminal, expired, invalid, or past Bolt orders must never be submitted to EDXEIX.
- V0 production and dependencies must not be touched.
- AADE receipt issuing must not be changed by V3 automation work.
- Never request or expose real API keys, DB passwords, tokens, cookies, sessions, or private credentials.

## Next recommended phase

`v3.0.71-v3-pre-live-proof-bundle-export`

Create a read-only proof bundle exporter that writes local JSON/TXT artifacts under:

```text
/home/cabnet/gov.cabnet.app_app/storage/artifacts/v3_pre_live_proof_bundles
```

The exporter should gather V3 switchboard, payload consistency, adapter simulation, readiness, storage, and final block status into one audit package.

