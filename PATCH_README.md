# gov.cabnet.app v5.7 AADE/myDATA First Controlled SendInvoices Gate

## Files

- `gov.cabnet.app_app/src/Receipts/AadeMyDataClient.php`
- `gov.cabnet.app_app/cli/aade_mydata_receipt_payload.php`
- `gov.cabnet.app_config_examples/aade_mydata_first_send_gate.example.php`
- `docs/BOLT_AADE_FIRST_CONTROLLED_SEND_GATE_V5_7.md`
- `HANDOFF.md`
- `CONTINUE_PROMPT.md`

## SQL

None.

## Verify

```bash
php -l /home/cabnet/gov.cabnet.app_app/src/Receipts/AadeMyDataClient.php
php -l /home/cabnet/gov.cabnet.app_app/cli/aade_mydata_receipt_payload.php
```

## Preview

```bash
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/aade_mydata_receipt_payload.php --booking-id=16
```

## Manual send gate

SendInvoices remains blocked unless:

- `receipts.aade_mydata.allow_send_invoices=true`
- `mail.driver_notifications.receipt_copy_enabled=false`
- `mail.driver_notifications.receipt_pdf_mode=aade_mydata`
- no prior issued receipt exists for the booking
- no prior issued receipt exists for the XML hash
- exact confirmation phrase is supplied

No automatic send is added.
