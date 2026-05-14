# gov.cabnet.app — Bolt → EDXEIX Bridge Handoff

## Current project state

Project: `gov.cabnet.app` Bolt pre-ride email V3 automation path.

Stack: plain PHP, mysqli/MariaDB, cPanel/manual upload workflow.

Server layout:

```text
/home/cabnet/public_html/gov.cabnet.app
/home/cabnet/gov.cabnet.app_app
/home/cabnet/gov.cabnet.app_config
/home/cabnet/gov.cabnet.app_sql
```

## Current verified milestone

V3 closed-gate automation proof has been validated with canary queue `#716`.

Known canary row:

```text
id: 716
queue_status: live_submit_ready
customer_name: V3 Canary Marina 1778760875
pickup_datetime: 2026-05-14 16:34:35
driver_name: Efthymios Giakis
vehicle_plate: ITK7702
lessor_id: 2307
driver_id: 17852
vehicle_id: 11187
starting_point_id: 1455969
submitted_at: NULL
failed_at: NULL
last_error: NULL
```

Approval was inserted for closed-gate rehearsal only:

```text
approval_status: approved
approval_scope: closed_gate_rehearsal_only
approved_by: Andreas
```

Latest known proof bundle from the live server:

```text
/home/cabnet/gov.cabnet.app_app/storage/artifacts/v3_pre_live_proof_bundles/bundle_20260514_122344_summary.json
/home/cabnet/gov.cabnet.app_app/storage/artifacts/v3_pre_live_proof_bundles/bundle_20260514_122344_summary.txt
```

## Current gate posture

Live submission is intentionally blocked.

Expected config posture:

```text
enabled=false
mode=disabled
adapter=disabled
hard_enable_live_submit=false
```

The `EdxeixLiveSubmitAdapterV3.php` file exists but remains a non-live-capable skeleton.

## This patch

Adds:

```text
public_html/gov.cabnet.app/ops/pre-ride-email-v3-live-operator-console.php
```

The console is read-only and gives operators a single screen showing:

- current V3 queue metrics;
- active queue rows;
- selected row status;
- payload completeness;
- approval status;
- starting-point verification;
- local live package artifacts;
- proof bundles;
- gate posture;
- adapter drift signals.

## Safety guarantees

Do not enable live EDXEIX submission unless Andreas explicitly requests a live-submit update.

Historical, cancelled, terminal, expired, invalid, or past Bolt orders must never be submitted.

The current patch does not:

- call Bolt;
- call EDXEIX;
- call AADE;
- write to DB;
- change queue statuses;
- write production submission tables;
- touch V0.

## Next safest step

After this patch is uploaded and verified, use the new console to observe the next real future Bolt pre-ride row.

Then proceed to one of these safe next phases:

1. add a read-only one-click proof bundle link from the console;
2. add read-only package preview panels inside the console;
3. add a dedicated expired approval cleanup/report view;
4. prepare, but do not enable, a formal live-submit readiness checklist.
