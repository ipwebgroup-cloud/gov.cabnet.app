# HANDOFF — gov.cabnet.app Bolt → EDXEIX Bridge

Updated: 2026-05-14 11:55 UTC  
Current patch: `v3.0.74-v3-live-gate-drift-guard`

## Project identity

- Domain: `https://gov.cabnet.app`
- Repo: `https://github.com/ipwebgroup-cloud/gov.cabnet.app`
- Stack: plain PHP, mysqli/MariaDB, cPanel/manual upload workflow
- Expected server layout:
  - `/home/cabnet/public_html/gov.cabnet.app`
  - `/home/cabnet/gov.cabnet.app_app`
  - `/home/cabnet/gov.cabnet.app_config`
  - `/home/cabnet/gov.cabnet.app_sql`

## Current V3 state

V3 forwarded-email automation has proven the pre-live path:

- Forwarded Bolt pre-ride emails are parsed and queued.
- Future eligible rows can reach `live_submit_ready`.
- Past/expired rows are blocked by the expiry guard.
- Starting points are verified against `pre_ride_email_v3_starting_point_options`.
- Operator approvals exist for closed-gate rehearsal only.
- Package export writes local private artifacts only.
- Adapter contract probe is safe.
- Future real adapter file exists as a skeleton and is not live-capable.
- Adapter simulation does not submit.
- Payload consistency confirms DB payload, exported EDXEIX fields, and adapter simulation hash match.
- Proof bundle export writes private summary artifacts.
- Proof ledger indexes proof and package artifacts.
- v3.0.74 adds a live gate drift guard to confirm the master gate remains closed.

## Critical safety rules

- Do not enable live EDXEIX submission unless Andreas explicitly asks for a live-submit update.
- Live submission must remain blocked unless a real eligible future Bolt trip exists, preflight passes, and the trip is sufficiently in the future.
- Historical, cancelled, terminal, expired, invalid, or past Bolt rows must never be submitted to EDXEIX.
- No real credentials should be requested or exposed.
- Config examples may be committed; real config stays server-only.
- Keep V0 untouched unless explicitly requested.

## New files in v3.0.74

```text
gov.cabnet.app_app/cli/pre_ride_email_v3_live_gate_drift_guard.php
public_html/gov.cabnet.app/ops/pre-ride-email-v3-live-gate-drift-guard.php
docs/V3_AUTOMATION_PRE_LIVE_STATUS.md
HANDOFF.md
CONTINUE_PROMPT.md
PATCH_README.md
```

## Verification after upload

```bash
php -l /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_live_gate_drift_guard.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-live-gate-drift-guard.php

su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_live_gate_drift_guard.php"

su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_live_gate_drift_guard.php --json"
```

Open:

```text
https://gov.cabnet.app/ops/pre-ride-email-v3-live-gate-drift-guard.php
```

Expected result while gate is safely closed:

```text
OK: yes
Expected disabled pre-live posture: yes
Live risk detected: no
Full live switch looks open: no
```

## Next best step

After this patch is verified, the next safest phase is a read-only final cutover checklist that combines:

- current future `live_submit_ready` row
- valid non-expired operator approval
- starting-point verification
- payload consistency hash
- package export existence
- proof bundle freshness
- master gate still closed until explicit live approval

Do not implement live EDXEIX network submission yet.
