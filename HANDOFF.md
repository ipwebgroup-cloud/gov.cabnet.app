# HANDOFF — gov.cabnet.app Bolt → EDXEIX Bridge

Updated: 2026-05-14  
Milestone: V3 closed-gate pre-live canary rehearsal validated; V3 live adapter contract test prepared

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

- Default to read-only, dry-run, preview, audit, queue visibility, and preflight behavior.
- Do not enable live EDXEIX submission unless Andreas explicitly asks for a live-submit update.
- Live submission must remain blocked unless there is a real eligible future Bolt trip, preflight passes, and the trip is sufficiently in the future.
- Historical, cancelled, terminal, expired, invalid, or past Bolt orders must never be submitted to EDXEIX.
- Never request or expose real API keys, DB passwords, tokens, cookies, session files, or private credentials.
- Config examples may be committed; real config files must remain server-only and ignored by Git.
- Public endpoints must remain thin; reusable logic belongs in `/home/cabnet/gov.cabnet.app_app`.
- Preserve plain PHP, mysqli/MariaDB, cPanel/manual upload workflow.
- No frameworks, Composer, Node build tools, or heavy dependencies unless Andreas explicitly approves.
- V0 / existing production workflows must remain untouched unless Andreas explicitly requests otherwise.

## Latest validated closed-gate milestone

A V3 closed-gate canary rehearsal was completed successfully using queue row `#716`.

Canary row summary:

```text
queue_id: 716
queue_status: live_submit_ready
customer_name: V3 Canary Marina 1778760875
pickup_datetime: 2026-05-14 16:34:35
vehicle_plate: ITK7702
driver_name: Efthymios Giakis
lessor_id: 2307
driver_id: 17852
vehicle_id: 11187
starting_point_id: 1455969
starting_point_label: ΧΩΡΑ ΜΥΚΟΝΟΥ
approval_status: approved
approval_scope: closed_gate_rehearsal_only
approved_by: Andreas
approval_expiry: 2026-05-14 16:16:00
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

These server artifacts are intentionally not included in commit packages because they may contain payload details and operational data.

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
- V3 live adapter contract test CLI and ops page

## Current live gate posture

Config path:

```text
/home/cabnet/gov.cabnet.app_config/pre_ride_email_v3_live_submit.php
```

Expected closed pre-live gate posture:

```text
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
submitted: false
blocked: true
reason: edxeix_live_adapter_skeleton_not_implemented
message: V3 EDXEIX live adapter skeleton is present, but real EDXEIX submission is not implemented or enabled. No EDXEIX call was made.
```

## New V3 live adapter contract test

Patch prepared:

```text
gov_v3_live_adapter_contract_test_20260514.zip
```

New files:

```text
gov.cabnet.app_app/cli/pre_ride_email_v3_live_adapter_contract_test.php
public_html/gov.cabnet.app/ops/pre-ride-email-v3-live-adapter-contract-test.php
docs/V3_LIVE_ADAPTER_CONTRACT_TEST_20260514.md
```

Purpose:

- Build the would-be future EDXEIX request envelope from a selected queue row.
- Display method, endpoint label, headers without secrets, timeout policy, idempotency shape, normalized payload, payload hash, future live preconditions, and response-normalization contract.
- Confirm the current adapter class is present but non-live.
- Do **not** call adapter `submit()`.
- Do **not** make network calls.
- Do **not** write DB rows.

Expected safe values:

```text
network_allowed=false
adapter_submit_allowed=false
adapter_submit_called=false
edxeix_call_made=false
adapter is_live_capable=false
adapter submitted=false
```

Important note: `ok` may become `false` when row #716 is no longer future-safe or its closed-gate rehearsal approval expires. That is safe. The relevant contract safety values must remain non-network and non-submitting.

## Ops pages

Live operator console:

```text
https://gov.cabnet.app/ops/pre-ride-email-v3-live-operator-console.php?queue_id=716
```

Live adapter contract test:

```text
https://gov.cabnet.app/ops/pre-ride-email-v3-live-adapter-contract-test.php?queue_id=716
```

Unauthenticated ops requests should redirect to `/ops/login.php`.

## Useful verification commands

```bash
php -l /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_live_adapter_contract_test.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-live-adapter-contract-test.php

curl -I https://gov.cabnet.app/ops/pre-ride-email-v3-live-adapter-contract-test.php

su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_live_gate_drift_guard.php"

su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_pre_live_switchboard.php --queue-id=716 --json"

su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_adapter_payload_consistency.php --queue-id=716 --json"

su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_live_adapter_contract_test.php --queue-id=716 --json"
```

## Next safest step

After this contract test is uploaded and verified, the next safe step is to add a fixture-driven version of the contract test that can run without a live/future DB row. This preserves no-network/no-submit behavior and avoids depending on canary row #716 after it expires.

Do not implement real EDXEIX network submission until Andreas explicitly requests it.
