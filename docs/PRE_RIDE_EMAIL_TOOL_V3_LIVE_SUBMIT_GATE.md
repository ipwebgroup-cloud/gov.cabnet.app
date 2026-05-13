# V3 Live-Submit Master Gate

This patch adds a read-only master gate for any future V3 live EDXEIX submit worker.

## Files

- `gov.cabnet.app_app/src/BoltMailV3/LiveSubmitGateV3.php`
- `gov.cabnet.app_app/cli/pre_ride_email_v3_live_submit_gate_check.php`
- `public_html/gov.cabnet.app/ops/pre-ride-email-v3-live-submit-gate.php`
- `gov.cabnet.app_config_examples/pre_ride_email_v3_live_submit.example.php`

## Safety

The gate does not submit to EDXEIX, does not call AADE, and does not write to the database.

The default posture is closed/hard-disabled unless a real server-only config file exists and contains all required live-submit approvals.

## Real config path

The real config file, if ever approved, belongs only on the server:

```text
/home/cabnet/gov.cabnet.app_config/pre_ride_email_v3_live_submit.php
```

The committed file is only an example and keeps `enabled => false`.

## Verification

```bash
php -l /home/cabnet/gov.cabnet.app_app/src/BoltMailV3/LiveSubmitGateV3.php
php -l /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_live_submit_gate_check.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-live-submit-gate.php
php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_live_submit_gate_check.php
```

Expected current output: gate closed/hard-disabled.
