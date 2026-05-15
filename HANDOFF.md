# gov.cabnet.app — HANDOFF

## Current milestone

v3.1.9–v3.1.11 V3 Real-Mail Observation Overview + navigation cleanup milestone.

This checkpoint documents the read-only V3 observation overview and the corrected shared operations shell navigation/note state.

## Project identity

- Domain: https://gov.cabnet.app
- Repository: https://github.com/ipwebgroup-cloud/gov.cabnet.app
- Stack: plain PHP, mysqli/MariaDB, cPanel/manual upload workflow.
- Live server is manually uploaded; it is not treated as a cloned Git repo.

Expected server layout:

```text
/home/cabnet/public_html/gov.cabnet.app
/home/cabnet/gov.cabnet.app_app
/home/cabnet/gov.cabnet.app_config
/home/cabnet/gov.cabnet.app_sql
/home/cabnet/tools/firefox-edxeix-autofill-helper
```

## Current verified production state

Latest verified outputs from Andreas:

```text
v3.1.9 Observation Overview:
ok=true
version=v3.1.9-v3-real-mail-observation-overview
queue_ok=true
expiry_ok=true
watch_ok=true
future_active=0
operator_candidates=0
live_risk=false
final_blocks=[]

v3.1.11 Shared Ops Shell:
PHP syntax clean
/ops/pre-ride-email-v3-observation-overview.php returns HTTP 302 to /ops/login.php when unauthenticated
V3 Observation Overview links present in Pre-Ride top dropdown and Daily Operations sidebar
old typo tokens absent:
  legacystats=false
  inv3.1.6=false
  utilityrelocation=false
  healthnavigation=false
  navigationadded=false
  notdeleted=false
```

## Safety posture

- Production Pre-Ride Tool `/ops/pre-ride-email-tool.php` remains untouched.
- V0 workflow remains untouched.
- V3 observation tools are read-only.
- No DB writes.
- No queue mutations.
- No SQL changes.
- No Bolt calls.
- No EDXEIX calls.
- No AADE calls.
- Live EDXEIX submit remains disabled.
- V3 live gate remains closed.

## Recent milestone details

### v3.1.9

Added:

```text
/home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_observation_overview.php
/home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-observation-overview.php
```

Purpose: consolidated read-only observation board combining queue health, expiry reason audit, and next candidate watch.

### v3.1.10

Added Observation Overview navigation to the shared ops shell.

### v3.1.11

Corrected shared shell side-note text and kept Observation Overview navigation intact.

## Next safest direction

Continue observation-only work. Do not enable live submission.

Recommended next step:

1. Add a read-only "V3 Observation Snapshot Export" that emits a sanitized JSON/Markdown summary of:
   - queue health
   - expiry audit
   - candidate watch
   - observation overview
   - live gate closed posture
2. No DB writes, no queue mutation, no network calls, no live submission.

Do not move/delete routes without a separate quiet-period plan and explicit approval.
