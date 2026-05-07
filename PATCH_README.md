# gov.cabnet.app v5.5.1 AADE/myDATA Privacy Hardening

## Upload paths

```text
gov.cabnet.app_app/src/Receipts/AadeMyDataClient.php
→ /home/cabnet/gov.cabnet.app_app/src/Receipts/AadeMyDataClient.php

gov.cabnet.app_app/cli/aade_mydata_readiness.php
→ /home/cabnet/gov.cabnet.app_app/cli/aade_mydata_readiness.php

public_html/gov.cabnet.app/ops/aade-mydata-readiness.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/aade-mydata-readiness.php
```

## SQL

None.

## Verify

```bash
php -l /home/cabnet/gov.cabnet.app_app/src/Receipts/AadeMyDataClient.php
php -l /home/cabnet/gov.cabnet.app_app/cli/aade_mydata_readiness.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/aade-mydata-readiness.php

/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/aade_mydata_readiness.php --ping --record --by=Andreas
```

Expected ping output should include `response_excerpt_suppressed=true` and should not include real AADE document text.

## Safety

No SendInvoices call is performed by the readiness ping. Receipt emails remain disabled until official AADE issuance is implemented and validated.
