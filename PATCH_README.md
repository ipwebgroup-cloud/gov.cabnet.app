# gov.cabnet.app — V3 live-submit gate config hygiene patch

## What changed

This patch improves the V3 live-submit master gate after the disabled server-only config was installed.

- Removes the blank `Config load error:` block when config loads successfully.
- Supports both acknowledgement key names used by current/future disabled config files.
- Adds `hard_enable_live_submit` to the gate output and read-only dashboard.
- Keeps the gate closed by default.

## Files included

```text
gov.cabnet.app_app/src/BoltMailV3/LiveSubmitGateV3.php
gov.cabnet.app_app/cli/pre_ride_email_v3_live_submit_gate_check.php
public_html/gov.cabnet.app/ops/pre-ride-email-v3-live-submit-gate.php
docs/PRE_RIDE_EMAIL_TOOL_V3_GATE_CONFIG_HYGIENE.md
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

## SQL

None.

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

## Expected result

```text
Config loaded: yes
Config error: -
Enabled: no
Mode: disabled
Adapter: disabled
Hard enable live submit: no
OK for future live submit: no
```

## Safety

- No EDXEIX call.
- No AADE call.
- No DB write.
- No production submission table write.
- Production pre-ride-email-tool.php is untouched.
