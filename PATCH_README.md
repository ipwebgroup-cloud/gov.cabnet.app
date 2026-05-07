# gov.cabnet.app v5.6 — AADE/myDATA Official Receipt Payload Builder

## Upload paths

```text
gov.cabnet.app_app/src/Receipts/AadeMyDataClient.php
→ /home/cabnet/gov.cabnet.app_app/src/Receipts/AadeMyDataClient.php

gov.cabnet.app_app/src/Receipts/AadeReceiptPayloadBuilder.php
→ /home/cabnet/gov.cabnet.app_app/src/Receipts/AadeReceiptPayloadBuilder.php

gov.cabnet.app_app/cli/aade_mydata_receipt_payload.php
→ /home/cabnet/gov.cabnet.app_app/cli/aade_mydata_receipt_payload.php

public_html/gov.cabnet.app/ops/aade-receipt-payload.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/aade-receipt-payload.php
```

Docs/example:

```text
gov.cabnet.app_config_examples/aade_mydata_send_invoices.example.php
docs/BOLT_AADE_MYDATA_RECEIPT_PAYLOAD_BUILDER_V5_6.md
HANDOFF.md
CONTINUE_PROMPT.md
PATCH_README.md
```

## SQL

None.

## Syntax check

```bash
php -l /home/cabnet/gov.cabnet.app_app/src/Receipts/AadeMyDataClient.php
php -l /home/cabnet/gov.cabnet.app_app/src/Receipts/AadeReceiptPayloadBuilder.php
php -l /home/cabnet/gov.cabnet.app_app/cli/aade_mydata_receipt_payload.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/aade-receipt-payload.php
```

## Preview payload

```bash
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/aade_mydata_receipt_payload.php --booking-id=BOOKING_ID
```

## Safety

This patch does not automatically call AADE SendInvoices, email receipts, call EDXEIX, create submission jobs, or create submission attempts.
