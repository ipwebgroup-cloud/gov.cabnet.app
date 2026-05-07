# gov.cabnet.app v5.5 — AADE/myDATA Test Adapter Readiness

## What changed

This patch adds the safe foundation for official AADE/myDATA receipt issuance:

- AADE/myDATA client wrapper with no-secret readiness output.
- CLI readiness and optional connectivity ping.
- Read-only ops readiness page.
- Additive receipt issuance audit table.
- Driver receipt service hard-blocks `aade_mydata` mode from falling back to generated/static PDFs.

## Files included

```text
gov.cabnet.app_app/src/Receipts/AadeMyDataClient.php
gov.cabnet.app_app/cli/aade_mydata_readiness.php
gov.cabnet.app_app/src/Mail/BoltMailDriverNotificationService.php
public_html/gov.cabnet.app/ops/aade-mydata-readiness.php
gov.cabnet.app_sql/2026_05_07_receipt_issuance_attempts.sql
gov.cabnet.app_config_examples/aade_mydata.example.php
docs/BOLT_AADE_MYDATA_TEST_ADAPTER_V5_5.md
HANDOFF.md
CONTINUE_PROMPT.md
PATCH_README.md
```

## Upload paths

```text
gov.cabnet.app_app/src/Receipts/AadeMyDataClient.php
→ /home/cabnet/gov.cabnet.app_app/src/Receipts/AadeMyDataClient.php

gov.cabnet.app_app/cli/aade_mydata_readiness.php
→ /home/cabnet/gov.cabnet.app_app/cli/aade_mydata_readiness.php

gov.cabnet.app_app/src/Mail/BoltMailDriverNotificationService.php
→ /home/cabnet/gov.cabnet.app_app/src/Mail/BoltMailDriverNotificationService.php

public_html/gov.cabnet.app/ops/aade-mydata-readiness.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/aade-mydata-readiness.php

gov.cabnet.app_sql/2026_05_07_receipt_issuance_attempts.sql
→ /home/cabnet/gov.cabnet.app_sql/2026_05_07_receipt_issuance_attempts.sql
```

## SQL

```bash
DB_NAME=$(php -r '$c=require "/home/cabnet/gov.cabnet.app_config/config.php"; echo $c["db"]["database"];')
mysql "$DB_NAME" < /home/cabnet/gov.cabnet.app_sql/2026_05_07_receipt_issuance_attempts.sql
```

## Verify syntax

```bash
php -l /home/cabnet/gov.cabnet.app_app/src/Receipts/AadeMyDataClient.php
php -l /home/cabnet/gov.cabnet.app_app/cli/aade_mydata_readiness.php
php -l /home/cabnet/gov.cabnet.app_app/src/Mail/BoltMailDriverNotificationService.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/aade-mydata-readiness.php
```

## CLI readiness

```bash
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/aade_mydata_readiness.php
```

## Connectivity ping

```bash
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/aade_mydata_readiness.php --ping --record --by=Andreas
```

## Safety

This patch does not enable receipt emails, send AADE invoices, call EDXEIX, create EDXEIX jobs/attempts, import mail, or change live-submit gates.
