# V3 Canary Rehearsal Runbook — Closed-Gate Only

This runbook documents the safe closed-gate canary process used to validate the V3 pre-ride email automation pipeline.

## Safety rules

- Do not enable live EDXEIX submission.
- Do not place real credentials in any command, file, or commit.
- Use future pickup times only.
- Use a synthetic canary passenger and synthetic phone number.
- Keep the live gate disabled.
- Confirm drift guard before and after the rehearsal.

## 1. Create a future demo email

This command writes a synthetic pre-ride email into the Bolt bridge Maildir. It is designed to generate future timestamps dynamically.

```bash
cat >/root/create_v3_canary_demo_email.sh <<'SH'
#!/usr/bin/env bash
set -euo pipefail
su -s /bin/bash cabnet -c '
MAILDIR="/home/cabnet/mail/gov.cabnet.app/bolt-bridge/new"
TS="$(date +%s)"
HOST="$(hostname)"
FILE="${MAILDIR}/${TS}.M${RANDOM}P$$.${HOST},S=9500,W=9500"

START_TIME="$(TZ=Europe/Athens date -d "+70 minutes" "+%Y-%m-%d %H:%M:%S EEST")"
PICKUP_TIME="$(TZ=Europe/Athens date -d "+80 minutes" "+%Y-%m-%d %H:%M:%S EEST")"
END_TIME="$(TZ=Europe/Athens date -d "+111 minutes" "+%Y-%m-%d %H:%M:%S EEST")"

cat > "$FILE" <<EOF
From: bolt-demo@gov.cabnet.app
To: bolt-bridge@gov.cabnet.app
Subject: V3 Canary Demo ITK7702 ${TS}
Date: $(TZ=Europe/Athens date -R)
MIME-Version: 1.0
Content-Type: text/plain; charset=UTF-8

Operator: Fleet Mykonos LUXLIMO Ι Κ Ε||MYKONOS CAB

Customer: V3 Canary Marina ${TS}

Customer mobile: +306900000002

Driver: Efthymios Giakis

Vehicle: ITK7702

Pickup: Ornos 846 00, Greece

Drop-off: Mybrands Mykonos, Omvrodektis Airport, Mykonos, Greece

Start time: ${START_TIME}

Estimated pick-up time: ${PICKUP_TIME}

Estimated end time: ${END_TIME}

Estimated price: 40.00 - 44.00 eur

Order reference: V3-ITK7702-CANARY-${TS}

[image: Lux Limo]
[image: Mykonos Cab]
[image: Bolt]

Best regards,
MYKONOS CAB TEAM
LUX LIMO P.C
Transfer Services
MYKONOS - SANTORINI - ATHENS
Phone: +30 6946 540 444
Email: info@mykonoscab.gr
Web: www.mykonoscab.gr
EOF

echo "Demo email written:"
echo "$FILE"
echo "Start time:  $START_TIME"
echo "Pickup time: $PICKUP_TIME"
echo "End time:    $END_TIME"
'
SH
bash /root/create_v3_canary_demo_email.sh
```

## 2. Run the V3 fast pipeline

```bash
su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_fast_pipeline.php --limit=50 --commit"
```

Expected result:

```text
Steps: 8 | OK: 8 | Failed: 0
Inserted: 1
Rows marked live-ready: 1
Ready for V3 manual handoff: yes
Ready for future live submit: no
```

## 3. Identify the new canary queue row

```bash
mysql cabnet_gov -e "
SELECT
  id,
  queue_status,
  customer_name,
  pickup_datetime,
  TIMESTAMPDIFF(MINUTE, NOW(), pickup_datetime) AS minutes_until,
  driver_name,
  vehicle_plate,
  lessor_id,
  driver_id,
  vehicle_id,
  starting_point_id,
  last_error,
  created_at,
  updated_at
FROM pre_ride_email_v3_queue
ORDER BY id DESC
LIMIT 10;
"
```

Expected canary row shape:

```text
queue_status: live_submit_ready
customer_name: V3 Canary Marina <timestamp>
vehicle_plate: ITK7702
lessor_id: 2307
driver_id: 17852
vehicle_id: 11187
starting_point_id: 1455969
last_error: NULL
```

## 4. Approve the row for closed-gate rehearsal only

Replace `<QUEUE_ID>` with the new canary row ID.

```bash
su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_operator_approval.php --queue-id=<QUEUE_ID> --approve --phrase=\"I APPROVE V3 ROW FOR CLOSED-GATE REHEARSAL ONLY\" --approved-by=\"Andreas\" --minutes=120"
```

Expected result:

```text
OK: yes
Eligible for closed-gate approval: yes
Starting point: verified
Missing required fields: none
approval inserted for closed-gate rehearsal only
```

## 5. Run closed-gate proof checks

```bash
su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_live_submit_payload_audit.php --limit=10"

su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_live_package_export.php --queue-id=<QUEUE_ID> --write"

su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_live_submit_rehearsal.php --limit=10"

su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_live_adapter_kill_switch_check.php --queue-id=<QUEUE_ID>"

su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_pre_live_switchboard.php --queue-id=<QUEUE_ID> --json"

su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_adapter_row_simulation.php --queue-id=<QUEUE_ID> --json"

su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_adapter_payload_consistency.php --queue-id=<QUEUE_ID> --json"

su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_pre_live_proof_bundle_export.php --queue-id=<QUEUE_ID> --write"

su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_live_gate_drift_guard.php --json"
```

## 6. Expected closed-gate result

```text
payload_ready: yes
package_export: OK yes
approval: valid yes
starting_point: verified
payload_consistency_ok: yes
db_vs_artifact_match: yes
adapter_hash_match: yes
adapter_live_capable: no
adapter_submitted: no
simulation_safe: yes
proof_bundle_safe: yes
live_risk_detected: no
```

Expected live blockers:

```text
master_gate: enabled is false
master_gate: mode is not live
master_gate: adapter is not edxeix_live
master_gate: hard_enable_live_submit is false
adapter: selected adapter is not edxeix_live
```

## 7. Final read-only DB verification

```bash
mysql cabnet_gov -e "
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
WHERE id = <QUEUE_ID>;

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
WHERE queue_id = <QUEUE_ID>
ORDER BY id DESC;
"
```

Expected queue result:

```text
queue_status: live_submit_ready
submitted_at: NULL
failed_at: NULL
last_error: NULL
```

Expected approval result:

```text
approval_status: approved
approval_scope: closed_gate_rehearsal_only
revoked_at: NULL
```
