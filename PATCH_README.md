# v5.6.1 AADE Payload Polish + First Send Gate

## Upload paths

```text
gov.cabnet.app_app/src/Receipts/AadeReceiptPayloadBuilder.php
→ /home/cabnet/gov.cabnet.app_app/src/Receipts/AadeReceiptPayloadBuilder.php

gov.cabnet.app_app/cli/aade_mydata_receipt_payload.php
→ /home/cabnet/gov.cabnet.app_app/cli/aade_mydata_receipt_payload.php

public_html/gov.cabnet.app/ops/aade-receipt-payload.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/aade-receipt-payload.php
```

## SQL

None.

## Verify

```bash
php -l /home/cabnet/gov.cabnet.app_app/src/Receipts/AadeReceiptPayloadBuilder.php
php -l /home/cabnet/gov.cabnet.app_app/cli/aade_mydata_receipt_payload.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/aade-receipt-payload.php
```

Preview:

```bash
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/aade_mydata_receipt_payload.php --booking-id=16
```

Record prepared only:

```bash
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/aade_mydata_receipt_payload.php --booking-id=16 --record-prepared --by=Andreas
```

Dashboard:

```text
https://gov.cabnet.app/ops/aade-receipt-payload.php?key=INTERNAL_API_KEY&booking_id=16&format=json
```

## Expected

- Amounts display as `35.40`, `4.60`, `40.00`.
- `send_invoices_status` shows `DISABLED_IN_CONFIG_PREVIEW_ONLY` while `allow_send_invoices=false`.
- No AADE invoice is sent.
- No receipt email is sent.
- No EDXEIX job/attempt is created.
