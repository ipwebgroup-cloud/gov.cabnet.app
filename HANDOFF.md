# HANDOFF — gov.cabnet.app Bolt → EDXEIX Bridge

Current milestone: v3.0.66-v3-real-adapter-design-spec

## Project identity

- Domain: https://gov.cabnet.app
- Repo: https://github.com/ipwebgroup-cloud/gov.cabnet.app
- Stack: plain PHP, mysqli/MariaDB, cPanel/manual upload workflow.
- Server layout:
  - /home/cabnet/public_html/gov.cabnet.app
  - /home/cabnet/gov.cabnet.app_app
  - /home/cabnet/gov.cabnet.app_config
  - /home/cabnet/gov.cabnet.app_sql
  - /home/cabnet/tools/firefox-edxeix-autofill-helper

## Critical safety state

- V0/manual helper must remain untouched.
- Live submit remains disabled.
- Do not enable real EDXEIX submission unless Andreas explicitly asks for a live-submit update.
- Historical, cancelled, terminal, expired, invalid, or past Bolt orders must never be submitted.
- No real credentials should be requested, exposed, or committed.

## Latest verified V3 status

V3 closed-gate automation path is proven:

- future-safe rows reached `live_submit_ready`
- operator approvals were inserted for closed-gate rehearsal
- payload audit passed
- package export wrote artifacts
- final rehearsal accepted approval and blocked only by master gate
- kill-switch accepted approval and blocked on gate/adapter
- pre-live switchboard page loads and renders from DB/config without command runner

## Current config posture

Server-only config:

```text
/home/cabnet/gov.cabnet.app_config/pre_ride_email_v3_live_submit.php
```

Expected closed state:

```text
enabled=false
mode=disabled
adapter=disabled
hard_enable_live_submit=false
```

## Current safe next phase

Next safe runtime development phase is adapter validation/simulation only, still with:

```text
isLiveCapable=false
submitted=false
No EDXEIX call
No AADE call
No V0 changes
```

Do not write real network submit behavior yet unless Andreas explicitly approves.
