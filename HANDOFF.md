# HANDOFF — gov.cabnet.app Bolt → EDXEIX Bridge

Updated: 2026-05-14  
Milestone: V3 closed-gate pre-live canary rehearsal validated

## Project identity

- Domain: https://gov.cabnet.app
- Repo: https://github.com/ipwebgroup-cloud/gov.cabnet.app
- Stack: plain PHP, mysqli/MariaDB, cPanel/manual upload workflow
- Live server is not a cloned Git repo.

Expected server layout:

```text
/home/cabnet/public_html/gov.cabnet.app
/home/cabnet/gov.cabnet.app_app
/home/cabnet/gov.cabnet.app_config
/home/cabnet/gov.cabnet.app_sql
/home/cabnet/tools/firefox-edxeix-autofill-helper
```

Workflow:

1. Code with Sophion/ChatGPT.
2. Download zip patch/package.
3. Extract into local GitHub Desktop repo.
4. Upload manually to server when package contains deployable code.
5. Test on server.
6. Commit via GitHub Desktop after production confirmation.

## Critical safety rules

- Do not enable live EDXEIX submission unless Andreas explicitly asks for a live-submit update.
- Live submission must remain blocked unless there is a real eligible future Bolt trip, preflight passes, and the trip is sufficiently in the future.
- Historical, cancelled, terminal, expired, invalid, or past Bolt orders must never be submitted to EDXEIX.
- Never request or expose real API keys, DB passwords, tokens, cookies, session files, or private credentials.
- Config examples may be committed; real config files must remain server-only and ignored by Git.
- Public endpoints must remain thin; reusable logic belongs in `/home/cabnet/gov.cabnet.app_app`.
- Preserve plain PHP, mysqli/MariaDB, cPanel/manual upload workflow.
- No frameworks, Composer, Node build tools, or heavy dependencies unless Andreas explicitly approves.

## Latest validated milestone

A V3 closed-gate canary rehearsal was completed successfully using queue row `#716`.

Canary row summary:

```text
queue_id: 716
queue_status: live_submit_ready
customer_name: V3 Canary Marina 1778760875
vehicle_plate: ITK7702
driver_name: Efthymios Giakis
lessor_id: 2307
driver_id: 17852
vehicle_id: 11187
starting_point_id: 1455969
starting_point_label: ΧΩΡΑ ΜΥΚΟΝΟΥ
approval_status: approved
approval_scope: closed_gate_rehearsal_only
submitted_at: NULL
failed_at: NULL
last_error: NULL
```

Latest validated proof bundle:

```text
/home/cabnet/gov.cabnet.app_app/storage/artifacts/v3_pre_live_proof_bundles/bundle_20260514_125023_summary.json
/home/cabnet/gov.cabnet.app_app/storage/artifacts/v3_pre_live_proof_bundles/bundle_20260514_125023_summary.txt
```

Latest local live package artifacts for row `#716`:

```text
/home/cabnet/gov.cabnet.app_app/storage/artifacts/v3_live_submit_packages/queue_716_20260514_151600_payload.json
/home/cabnet/gov.cabnet.app_app/storage/artifacts/v3_live_submit_packages/queue_716_20260514_151600_edxeix_fields.json
/home/cabnet/gov.cabnet.app_app/storage/artifacts/v3_live_submit_packages/queue_716_20260514_151600_safety_report.json
/home/cabnet/gov.cabnet.app_app/storage/artifacts/v3_live_submit_packages/queue_716_20260514_151600_safety_report.txt
```

These server artifacts are intentionally not included in the commit package because they may contain payload details and operational data.

## Validated V3 components

Confirmed working in closed-gate mode:

- V3 Maildir demo/canary intake
- V3 parser and normalized queue insert
- V3 mapping to local EDXEIX IDs
- V3 starting-point guard
- V3 submit dry-run readiness worker
- V3 live-submit readiness marker
- V3 operator approval workflow
- V3 payload audit
- V3 local live package export
- V3 final rehearsal blocker check
- V3 live adapter kill-switch check
- V3 pre-live switchboard CLI
- V3 adapter row simulation
- V3 adapter payload consistency harness
- V3 proof bundle exporter
- V3 live gate drift guard
- V3 live operator console page

## Live gate posture

Current expected pre-live gate posture:

```text
config_path: /home/cabnet/gov.cabnet.app_config/pre_ride_email_v3_live_submit.php
config_loaded: yes
enabled: false
mode: disabled
adapter: disabled
hard_enable_live_submit: false
acknowledgement_phrase_present: true
expected_closed_pre_live: true
live_risk_detected: false
adapter_looks_live_capable: false
full_live_switch_looks_open: false
```

Expected master gate live blockers:

```text
master_gate: enabled is false
master_gate: mode is not live
master_gate: adapter is not edxeix_live
master_gate: hard_enable_live_submit is false
```

## Adapter status

The `edxeix_live` adapter file exists but is intentionally non-live:

```text
/home/cabnet/gov.cabnet.app_app/src/BoltMailV3/EdxeixLiveSubmitAdapterV3.php
```

Validated adapter behavior:

```text
class_exists: true
instantiated: true
name: edxeix_live_skeleton
is_live_capable: false
submit_called: true
submitted: false
blocked: true
reason: edxeix_live_adapter_skeleton_not_implemented
message: V3 EDXEIX live adapter skeleton is present, but real EDXEIX submission is not implemented or enabled. No EDXEIX call was made.
```

## Proof bundle summary for row #716

The latest validated proof bundle reported:

```text
OK: yes
Bundle safe: yes
storage_ok: yes
automation_readiness_seen: yes
switchboard_seen: yes
adapter_simulation_seen: yes
payload_consistency_seen: yes
payload_consistency_ok: yes
db_vs_artifact_match: yes
adapter_hash_match: yes
adapter_live_capable: no
adapter_submitted: no
simulation_safe: yes
edxeix_call_made: no
aade_call_made: no
db_write_made: no
v0_touched: no
```

Notes:

- `pre_live_switchboard exit=1` is expected while the master live gate is intentionally disabled.
- `closed_gate_diagnostics exit=1` is expected while the master live gate is intentionally disabled.
- These exit codes represent safe live-submit blockers, not a failed proof bundle.

## Ops pages verified

Live operator console syntax and auth redirect verified:

```text
/home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-live-operator-console.php
```

Unauthenticated check:

```text
curl -I https://gov.cabnet.app/ops/pre-ride-email-v3-live-operator-console.php
```

Expected:

```text
HTTP/1.1 302 Found
Location: /ops/login.php?next=%2Fops%2Fpre-ride-email-v3-live-operator-console.php
```

Authenticated view tested:

```text
https://gov.cabnet.app/ops/pre-ride-email-v3-live-operator-console.php?queue_id=716
```

Observed badges:

```text
EXPECTED CLOSED PRE-LIVE GATE
NO LIVE RISK DETECTED
NO EDXEIX CALL
NO DB WRITES
```

## Useful verification commands

```bash
su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_live_gate_drift_guard.php"

su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_pre_live_switchboard.php --queue-id=716 --json"

su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_adapter_payload_consistency.php --queue-id=716 --json"

su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_pre_live_proof_bundle_export.php --queue-id=716 --write"
```

Read-only queue verification:

```sql
SELECT
  id,
  queue_status,
  customer_name,
  pickup_datetime,
  driver_name,
  vehicle_plate,
  lessor_id,
  driver_id,
  vehicle_id,
  starting_point_id,
  submitted_at,
  failed_at,
  last_error
FROM pre_ride_email_v3_queue
WHERE id = 716;

SELECT
  id,
  queue_id,
  approval_status,
  approval_scope,
  approved_by,
  approved_at,
  expires_at,
  revoked_at
FROM pre_ride_email_v3_live_submit_approvals
WHERE queue_id = 716
ORDER BY id DESC;
```

## Next safest step

The next safe development step is to create a **non-submitting live adapter contract test** that defines the exact request body, headers, endpoint selection, timeout behavior, and response normalization for the future EDXEIX submitter, while keeping the adapter blocked by `is_live_capable=false` and while continuing to forbid network calls.

Do not implement real EDXEIX network submission until Andreas explicitly requests it.
