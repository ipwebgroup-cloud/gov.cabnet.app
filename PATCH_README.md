# gov.cabnet.app V3 Live-Submit Payload Audit Patch

## Files included

```text
gov.cabnet.app_app/cli/pre_ride_email_v3_live_submit_payload_audit.php
public_html/gov.cabnet.app/ops/pre-ride-email-v3-live-payload-audit.php
docs/PRE_RIDE_EMAIL_TOOL_V3_LIVE_SUBMIT_PAYLOAD_AUDIT.md
PATCH_README.md
```

## Upload paths

```text
gov.cabnet.app_app/cli/pre_ride_email_v3_live_submit_payload_audit.php
→ /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_live_submit_payload_audit.php

public_html/gov.cabnet.app/ops/pre-ride-email-v3-live-payload-audit.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-live-payload-audit.php
```

## SQL

None.

## Verify

```bash
php -l /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_live_submit_payload_audit.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-live-payload-audit.php
php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_live_submit_payload_audit.php --limit=20
```

## Safety

This patch does not call EDXEIX, does not call AADE, does not touch production submission tables, and does not modify `/ops/pre-ride-email-tool.php`.
