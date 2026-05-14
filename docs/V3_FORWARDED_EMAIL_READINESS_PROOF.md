# V3 Forwarded-Email Readiness Proof

Date: 2026-05-14
Project: gov.cabnet.app Bolt → EDXEIX bridge
Scope: V3 pre-ride email automation readiness only

## Summary

A forwarded Gmail/Bolt pre-ride email test successfully proved the V3 readiness pipeline through `live_submit_ready` while the live-submit master gate remained closed.

This proves the V3 server-side automation path can receive a forwarded pre-ride email, parse it, map it, guard it, dry-run-ready it, and promote it to live-submit-ready behind the closed gate.

## Confirmed proof path

```text
Gmail/manual forward
→ server mailbox
→ V3 intake
→ parser
→ driver / vehicle / lessor mapping
→ future-safe guard
→ verified starting-point guard
→ submit_dry_run_ready
→ live_submit_ready
```

## Proof row

```text
queue_id: 56
queue_status: live_submit_ready
customer: Arnaud BAGORO
driver: Filippos Giannakopoulos
vehicle: EHA2545
lessor_id: 3814
driver_id: 17585
vehicle_id: 5949
starting_point_id: 6467495
last_error: NULL
```

## Payload audit result

```text
V3 live-submit payload audit v3.0.24-live-submit-payload-audit
Mode: dry_run_select_only
Rows checked: 1
Payload-ready: 1
Blocked: 0
Warnings: 0
No EDXEIX call. No AADE call. No queue status change.
```

Payload-ready row:

```text
Queue ID: 56
Status: live_submit_ready
Pickup: 2026-05-14 10:45:47
Transfer: Arnaud BAGORO | Filippos Giannakopoulos | EHA2545
EDXEIX fields: lessor=3814 driver=17585 vehicle=5949 start=6467495
```

## Final rehearsal result

The final rehearsal correctly blocked the row because the live-submit gate is still closed.

```text
Master gate OK: no
config_loaded: yes
adapter: disabled
hard_enabled: no
Rows checked: 1
Pre-live passed: 0
Blocked: 1
No EDXEIX call. No AADE call. No DB writes. No production submission tables.
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

This is the correct and safe result.

## Lessor 3814 starting-point option

The V3 verified starting-point options table now includes:

```text
edxeix_lessor_id: 3814
edxeix_starting_point_id: 6467495
label: ΕΔΡΑ ΜΑΣ, Δήμος Μυκόνου, Περιφερειακή Ενότητα Μυκόνου, Περιφέρεια Νοτίου Αιγαίου, Αποκεντρωμένη Διοίκηση Αιγαίου, 846 00, Ελλάδα
source: operator_verified
is_active: 1
```

This allowed row 56 to move past the starting-point guard and dry-run readiness.

## Bug fixed during proof

The V3 live-submit readiness worker was still querying old column names in `pre_ride_email_v3_starting_point_options`:

```text
lessor_id
starting_point_id
```

The real V3 columns are:

```text
edxeix_lessor_id
edxeix_starting_point_id
```

Patch applied:

```text
v3.0.47-live-readiness-start-options-alias-fix
```

Result after patch:

```text
[7/8] OK — Live-submit readiness gate
Rows checked: 1
Live-ready: 1
Rows marked live-ready: 1
```

## Safety posture

```text
V0 laptop/manual helper: untouched
V0 dependencies: untouched
Live EDXEIX submit: disabled
AADE: untouched
Production submission tables: untouched
Master gate: closed
Operator approval: not present
Adapter: disabled
```

## Current conclusion

```text
V3 readiness pipeline: PROVEN
V3 live auto-submit: NOT ENABLED
Next safe phase: retain proof, commit checkpoint, then design live adapter behind the closed gate only when Andreas explicitly approves.
```
