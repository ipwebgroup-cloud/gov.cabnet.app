# V3 First Live Submit Checklist

Version: `v3.0.57-v3-live-adapter-runbook`
Status: future checklist only — live submit remains disabled

This checklist must be completed before any first real EDXEIX live submit is allowed.

## 1. Required real ride conditions

```text
[ ] Ride is a real Bolt pre-ride email, not a synthetic/forwarded test.
[ ] Ride pickup is sufficiently in the future.
[ ] Ride is not historical or expired.
[ ] Ride is not cancelled, terminal, invalid, or duplicated.
[ ] Vehicle is not EMT8640.
[ ] Vehicle is not otherwise exempt.
[ ] Driver is mapped.
[ ] Vehicle is mapped.
[ ] Lessor is mapped.
[ ] Starting point is operator-verified for the lessor.
```

## 2. Required V3 queue state

```text
[ ] V3 queue row exists.
[ ] parser_ok = 1.
[ ] mapping_ok = 1.
[ ] future_ok = 1.
[ ] queue_status = live_submit_ready.
[ ] last_error is NULL or irrelevant/non-blocking.
[ ] payload_json contains all expected fields.
```

## 3. Required payload audit

Command:

```bash
su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_live_submit_payload_audit.php --limit=10"
```

Expected:

```text
Rows checked: >= 1
Payload-ready: >= 1
Blocked: 0 for the selected row
No EDXEIX call
No AADE call
```

## 4. Required package export

Command:

```bash
su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_live_package_export.php --queue-id=<ID> --write"
```

Expected:

```text
OK: yes
current_live_submit_ready: yes
eligible_for_live_submit_now: yes
missing_required_fields: none
```

## 5. Required final rehearsal

Command:

```bash
su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_live_submit_rehearsal.php --limit=10"
```

Before live gate opening, the expected result is blocked by gate only.

Acceptable pre-live block reasons:

```text
master_gate: enabled is false
master_gate: mode is not live
master_gate: adapter is disabled
master_gate: hard_enable_live_submit is false
approval: no valid operator approval found
```

Not acceptable:

```text
missing required field
unverified starting point
expired/past pickup
invalid vehicle/driver/lessor
EMT8640/exempt vehicle
payload malformed
```

## 6. Required operator approval

```text
[ ] Approval belongs to the exact queue_id.
[ ] Approval belongs to the exact dedupe_key.
[ ] Approval status is approved/valid according to V3 logic.
[ ] Approval is not revoked.
[ ] Approval is not expired.
[ ] Approval was made by an authorized operator.
[ ] Approval snapshot matches the current row.
```

## 7. Required master gate conditions — future only

These must remain false until Andreas explicitly approves live submit.

```text
[ ] enabled = true
[ ] mode = live
[ ] adapter = edxeix_live
[ ] hard_enable_live_submit = true
[ ] acknowledgement phrase present
```

## 8. Required rollback plan before live submit

Before any live attempt, the operator must know how to close the gate immediately.

```text
[ ] Config file path known.
[ ] Disable command ready.
[ ] Backup of config exists.
[ ] V0 manual helper remains available.
[ ] No cron changes required to stop live submit.
```

Config path:

```text
/home/cabnet/gov.cabnet.app_config/pre_ride_email_v3_live_submit.php
```

## 9. Required post-attempt checks

```text
[ ] Queue row status reviewed.
[ ] Submission evidence reviewed.
[ ] Duplicate protection checked.
[ ] No second attempt queued accidentally.
[ ] EDXEIX-side result manually verified.
[ ] V0 remains available as fallback.
```

## Final note

A first live submit must only happen on a real eligible future ride, never on row 56 or any historical/forwarded/synthetic proof row.
