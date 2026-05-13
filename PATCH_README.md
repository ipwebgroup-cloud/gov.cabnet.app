# gov.cabnet.app patch — V3 Live-Submit Master Gate

## What changed

Adds a central read-only master gate for any future V3 live EDXEIX submit worker.

## Files included

```text
gov.cabnet.app_app/src/BoltMailV3/LiveSubmitGateV3.php
gov.cabnet.app_app/cli/pre_ride_email_v3_live_submit_gate_check.php
public_html/gov.cabnet.app/ops/pre-ride-email-v3-live-submit-gate.php
gov.cabnet.app_config_examples/pre_ride_email_v3_live_submit.example.php
docs/PRE_RIDE_EMAIL_TOOL_V3_LIVE_SUBMIT_GATE.md
PATCH_README.md
```

## Upload paths

```text
gov.cabnet.app_app/src/BoltMailV3/LiveSubmitGateV3.php
→ /home/cabnet/gov.cabnet.app_app/src/BoltMailV3/LiveSubmitGateV3.php

gov.cabnet.app_app/cli/pre_ride_email_v3_live_submit_gate_check.php
→ /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_live_submit_gate_check.php

public_html/gov.cabnet.app/ops/pre-ride-email-v3-live-submit-gate.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-live-submit-gate.php
```

The config example stays in Git only. Do not upload it as the real live config unless explicitly approved.

## Verify

```bash
php -l /home/cabnet/gov.cabnet.app_app/src/BoltMailV3/LiveSubmitGateV3.php
php -l /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_live_submit_gate_check.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-live-submit-gate.php
php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_live_submit_gate_check.php
```

Open:

```text
https://gov.cabnet.app/ops/pre-ride-email-v3-live-submit-gate.php
```

## Safety

- Production `pre-ride-email-tool.php` is untouched.
- No EDXEIX call.
- No AADE call.
- No DB writes.
- No production submission tables.
- Default gate state is closed.
