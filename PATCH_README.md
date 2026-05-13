# gov.cabnet.app Patch — V3 Live-Submit Adapter Contract Probe

## Files included

```text
gov.cabnet.app_app/src/BoltMailV3/LiveSubmitAdapterV3.php
gov.cabnet.app_app/src/BoltMailV3/DisabledLiveSubmitAdapterV3.php
gov.cabnet.app_app/src/BoltMailV3/DryRunLiveSubmitAdapterV3.php
gov.cabnet.app_app/cli/pre_ride_email_v3_live_submit_adapter_probe.php
public_html/gov.cabnet.app/ops/pre-ride-email-v3-live-submit-adapter.php
docs/PRE_RIDE_EMAIL_TOOL_V3_LIVE_SUBMIT_ADAPTER_CONTRACT.md
PATCH_README.md
```

## Upload paths

```text
gov.cabnet.app_app/src/BoltMailV3/LiveSubmitAdapterV3.php
→ /home/cabnet/gov.cabnet.app_app/src/BoltMailV3/LiveSubmitAdapterV3.php

gov.cabnet.app_app/src/BoltMailV3/DisabledLiveSubmitAdapterV3.php
→ /home/cabnet/gov.cabnet.app_app/src/BoltMailV3/DisabledLiveSubmitAdapterV3.php

gov.cabnet.app_app/src/BoltMailV3/DryRunLiveSubmitAdapterV3.php
→ /home/cabnet/gov.cabnet.app_app/src/BoltMailV3/DryRunLiveSubmitAdapterV3.php

gov.cabnet.app_app/cli/pre_ride_email_v3_live_submit_adapter_probe.php
→ /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_live_submit_adapter_probe.php

public_html/gov.cabnet.app/ops/pre-ride-email-v3-live-submit-adapter.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-live-submit-adapter.php
```

## SQL

None.

## Verify

```bash
php -l /home/cabnet/gov.cabnet.app_app/src/BoltMailV3/LiveSubmitAdapterV3.php
php -l /home/cabnet/gov.cabnet.app_app/src/BoltMailV3/DisabledLiveSubmitAdapterV3.php
php -l /home/cabnet/gov.cabnet.app_app/src/BoltMailV3/DryRunLiveSubmitAdapterV3.php
php -l /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_live_submit_adapter_probe.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-live-submit-adapter.php

php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_live_submit_adapter_probe.php --limit=20
php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_live_submit_adapter_probe.php --limit=20 --adapter=dry-run
```

## Safety

- Production `public_html/gov.cabnet.app/ops/pre-ride-email-tool.php` is untouched.
- No EDXEIX calls.
- No AADE calls.
- No DB writes.
- No production submission table writes.
