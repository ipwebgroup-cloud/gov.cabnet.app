# gov.cabnet.app patch — V3 automation readiness report

## What changed

Adds a consolidated read-only V3 automation readiness report for the Bolt pre-ride email automation chain.

## Files included

```text
gov.cabnet.app_app/src/BoltMailV3/AutomationReadinessReportV3.php
gov.cabnet.app_app/cli/pre_ride_email_v3_automation_readiness.php
public_html/gov.cabnet.app/ops/pre-ride-email-v3-automation-readiness.php
docs/PRE_RIDE_EMAIL_TOOL_V3_AUTOMATION_READINESS.md
PATCH_README.md
```

## Upload paths

```text
gov.cabnet.app_app/src/BoltMailV3/AutomationReadinessReportV3.php
→ /home/cabnet/gov.cabnet.app_app/src/BoltMailV3/AutomationReadinessReportV3.php

gov.cabnet.app_app/cli/pre_ride_email_v3_automation_readiness.php
→ /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_automation_readiness.php

public_html/gov.cabnet.app/ops/pre-ride-email-v3-automation-readiness.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-automation-readiness.php
```

## SQL

None.

## Verify

```bash
php -l /home/cabnet/gov.cabnet.app_app/src/BoltMailV3/AutomationReadinessReportV3.php
php -l /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_automation_readiness.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-automation-readiness.php

php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_automation_readiness.php
php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_automation_readiness.php --json
```

Open:

```text
https://gov.cabnet.app/ops/pre-ride-email-v3-automation-readiness.php
```

## Expected result

The page should show the full V3 chain status in one place:

- Schema status.
- Queue counts.
- Cron freshness.
- Disabled live-submit config state.
- Safety state.
- Next recommended action.

## Safety

- Production `pre-ride-email-tool.php` is untouched.
- No EDXEIX calls.
- No AADE calls.
- No database writes.
- No production `submission_jobs` writes.
- No production `submission_attempts` writes.
