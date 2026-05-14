# V3 Operator Console Scope

## Patch identity

Version: `v3.0.76-v3-live-operator-console`

This patch adds a read-only V3 operator console for the Bolt pre-ride email automation path.

## Goal

Give the operator one protected Ops screen for checking V3 pre-live readiness without opening the live EDXEIX gate.

The console is intended to show:

- current `live_submit_ready`, `submit_dry_run_ready`, and `queued` V3 rows;
- selected queue row details;
- future-safety status;
- operator approval status;
- payload completeness;
- starting-point verification;
- local live package artifacts;
- local pre-live proof bundles;
- live gate posture;
- adapter file drift indicators;
- clear live-submit blocking posture.

## Safety boundaries

The console is read-only.

It does **not**:

- call Bolt;
- call EDXEIX;
- call AADE;
- write to the database;
- change queue statuses;
- approve or revoke rows;
- create proof bundles;
- create package artifacts;
- write production submission tables;
- touch V0.

## Added route

```text
/ops/pre-ride-email-v3-live-operator-console.php
```

Expected live URL:

```text
https://gov.cabnet.app/ops/pre-ride-email-v3-live-operator-console.php
```

Optional query parameters:

```text
?queue_id=716
?json=1
?json=1&queue_id=716
```

## Source-of-truth inputs shown by the console

The console reads:

```text
/home/cabnet/gov.cabnet.app_config/pre_ride_email_v3_live_submit.php
/home/cabnet/gov.cabnet.app_app/src/bootstrap.php
/home/cabnet/gov.cabnet.app_app/src/BoltMailV3/LiveSubmitAdapterV3.php
/home/cabnet/gov.cabnet.app_app/src/BoltMailV3/DisabledLiveSubmitAdapterV3.php
/home/cabnet/gov.cabnet.app_app/src/BoltMailV3/DryRunLiveSubmitAdapterV3.php
/home/cabnet/gov.cabnet.app_app/src/BoltMailV3/EdxeixLiveSubmitAdapterV3.php
/home/cabnet/gov.cabnet.app_app/storage/artifacts/v3_live_submit_packages
/home/cabnet/gov.cabnet.app_app/storage/artifacts/v3_pre_live_proof_bundles
```

Database tables read:

```text
pre_ride_email_v3_queue
pre_ride_email_v3_live_submit_approvals
pre_ride_email_v3_starting_point_options
```

## Expected current posture

Before explicit live-submit approval from Andreas, the gate should remain:

```text
enabled=false
mode=disabled
adapter=disabled
hard_enable_live_submit=false
```

The console should show:

```text
EXPECTED CLOSED PRE-LIVE GATE
NO LIVE RISK DETECTED
NO EDXEIX CALL
NO DB WRITES
```

## Success criteria

For the known canary flow, the console should be able to show row `#716` as:

```text
queue_status=live_submit_ready
payload complete
start verified
approval valid
closed-gate proof ready
```

The master gate should still block live submit.
