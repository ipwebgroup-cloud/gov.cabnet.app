# HANDOFF — gov.cabnet.app V3 Bolt Pre-Ride Automation

You are Sophion assisting Andreas with the gov.cabnet.app Bolt → EDXEIX bridge project.

## Project identity

- Domain: https://gov.cabnet.app
- GitHub repo: https://github.com/ipwebgroup-cloud/gov.cabnet.app
- Stack: plain PHP, mysqli/MariaDB, cPanel/manual upload workflow
- Server layout:

```text
/home/cabnet/public_html/gov.cabnet.app
/home/cabnet/gov.cabnet.app_app
/home/cabnet/gov.cabnet.app_config
/home/cabnet/gov.cabnet.app_sql
/home/cabnet/tools/firefox-edxeix-autofill-helper
```

The live server is not a cloned Git repo. The working flow is:

```text
ChatGPT/Sophion patch zip → extract into local GitHub Desktop repo → upload manually to server → test → commit via GitHub Desktop
```

## Critical boundary

V0 is installed on the laptop and is the current manual production helper. Do not touch V0, V0 dependencies, or the V0 workflow.

V3 is installed on the PC/server path and is the development automation path.

Andreas will use his own judgment during operations. Do not add software that decides whether he should use V0 or V3.

## Safety rules

- Default to read-only, dry-run, preview, audit, queue visibility, and preflight behavior.
- Do not enable live EDXEIX submission unless Andreas explicitly asks for a live-submit gate-opening update.
- Historical, cancelled, terminal, expired, invalid, or past Bolt orders must never be submitted to EDXEIX.
- Demo/forwarded emails are valid for parser/readiness tests, not for opening the live-submit gate.
- Never request or expose real API keys, DB passwords, tokens, cookies, session files, or private credentials.
- Config examples may be committed; real config files remain server-only and ignored by Git.
- Patch zips must not include secrets, logs, sessions, raw data dumps, cache files, or temporary public diagnostic scripts unless explicitly needed and safe.

## Verified V3 state

As of the v3.0.48/v3.0.49 planning checkpoint:

```text
V3 mail intake: proven
V3 parser: proven
V3 mapping: proven
V3 future guard: proven
V3 starting-point guard: proven
V3 submit dry-run readiness: proven
V3 live-readiness status: proven
V3 payload audit: proven
V3 final rehearsal: correctly blocked by closed master gate
Live submit: disabled
V0: untouched
```

Proof row:

```text
id: 56
queue_status: live_submit_ready
customer_name: Arnaud BAGORO
pickup_datetime: 2026-05-14 10:45:47
driver_name: Filippos Giannakopoulos
vehicle_plate: EHA2545
lessor_id: 3814
driver_id: 17585
vehicle_id: 5949
starting_point_id: 6467495
last_error: NULL
```

Payload audit result:

```text
PAYLOAD-READY
lessor=3814
driver=17585
vehicle=5949
start=6467495
```

Final rehearsal result:

```text
BLOCKED by master gate, as intended.
```

Gate blocks:

```text
enabled is false
mode is not live
required acknowledgement phrase is not present
adapter is disabled
hard_enable_live_submit is false
approval: no valid operator approval found
```

## Key fixes completed

1. Pulse cron lock issue fixed.

```text
/home/cabnet/gov.cabnet.app_app/storage/locks/pre_ride_email_v3_fast_pipeline_pulse.lock
owner/group: cabnet:cabnet
perms: 0660
```

2. V3 storage check added and verified as `cabnet`.

3. Lessor 3814 starting-point option added to V3 verified options.

```text
pre_ride_email_v3_starting_point_options:
3814 / 6467495 / ΕΔΡΑ ΜΑΣ...
```

4. Live-readiness alias bug fixed.

```text
pre_ride_email_v3_live_submit_readiness.php now uses:
edxeix_lessor_id
edxeix_starting_point_id
```

## Current important URLs

```text
https://gov.cabnet.app/ops/pre-ride-email-v3-dashboard.php
https://gov.cabnet.app/ops/pre-ride-email-v3-monitor.php
https://gov.cabnet.app/ops/pre-ride-email-v3-queue-focus.php
https://gov.cabnet.app/ops/pre-ride-email-v3-pulse-focus.php
https://gov.cabnet.app/ops/pre-ride-email-v3-readiness-focus.php
https://gov.cabnet.app/ops/pre-ride-email-v3-storage-check.php
https://gov.cabnet.app/ops/pre-ride-email-v3-live-submit-gate.php
https://gov.cabnet.app/ops/pre-ride-email-v3-live-payload-audit.php
```

## Current next phase

Proceed with:

```text
Phase V3.1 — Closed-Gate Live Adapter Preparation
```

Recommended next patch:

```text
v3.0.49-v3-proof-dashboard
```

Add:

```text
public_html/gov.cabnet.app/ops/pre-ride-email-v3-proof.php
docs/V3_NEXT_PHASE_SCOPE.md
docs/V3_EDXEIX_LIVE_ADAPTER_FIELD_MAP.md
```

No SQL. No live-submit enabling. No V0 changes.

## Commit checkpoint title

```text
Prove V3 forwarded-email readiness path
```

## Commit checkpoint description

```text
Documents and preserves the verified V3 forwarded-email readiness proof.

The test proved Gmail/manual forward → server mailbox → V3 intake → parser → mapping → future-safe guard → verified starting-point guard → submit_dry_run_ready → live_submit_ready.

The payload audit confirmed the proof row was payload-ready. Final rehearsal correctly blocked the row because the master live-submit gate remains closed: enabled=false, mode disabled, adapter disabled, required acknowledgement absent, hard enable false, and no operator approval.

No V0 laptop/manual helper files, live-submit enabling, EDXEIX calls, AADE behavior, production submission tables, cron schedules, or SQL schema are changed.
```
