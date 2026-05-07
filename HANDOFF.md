# gov.cabnet.app HANDOFF - v5.8 Automatic AADE Receipt Issuance

Current phase: v5.8 Automatic AADE/myDATA receipt issuance and driver receipt email.

The bridge can now automatically issue an AADE/myDATA receipt from a real Bolt mail normalized booking during the existing auto dry-run cron flow, then email the AADE-issued receipt PDF to the driver.

Live EDXEIX remains guarded/session-disconnected. The patch does not create EDXEIX submission jobs or attempts.

Required private config:

```php
'receipts' => [
  'mode' => 'aade_mydata',
  'aade_mydata' => [
    'enabled' => true,
    'allow_send_invoices' => true,
    'auto_send_invoices' => true,
        'auto_issue_not_before' => '2026-05-07 23:59:00 Europe/Athens',
  ],
],
'mail' => [
  'driver_notifications' => [
    'receipt_copy_enabled' => false,
    'receipt_pdf_mode' => 'aade_mydata',
    'official_receipt_email_enabled' => true,
  ],
],
```

Next real Bolt future order should:

1. import via mail cron;
2. send normal driver copy;
3. link/create `normalized_bookings`;
4. record/confirm dry-run evidence;
5. send AADE/myDATA `SendInvoices` automatically;
6. store MARK/UID/QR metadata;
7. email AADE receipt PDF to the driver.

Rollback: set `auto_send_invoices=false` and `official_receipt_email_enabled=false`.
