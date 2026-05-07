<?php
// Server-only config excerpt for v5.8 automatic AADE/myDATA receipt issuance.
// Do not commit real credentials. Add these inside the existing config array.

return [
    'mail' => [
        'driver_notifications' => [
            // Normal pre-ride driver copy remains enabled.
            'enabled' => true,

            // Keep legacy/generated receipt copy disabled. Official receipt email
            // is sent only after AADE/myDATA SendInvoices succeeds.
            'receipt_copy_enabled' => false,
            'receipt_pdf_mode' => 'aade_mydata',
            'official_receipt_email_enabled' => true,
        ],
    ],

    'receipts' => [
        'mode' => 'aade_mydata',
        'aade_mydata' => [
            'enabled' => true,
            'environment' => 'production',
            'endpoint_base' => 'https://mydatapi.aade.gr/myDATA',

            // Accountant-approved production SendInvoices.
            'allow_send_invoices' => true,

            // v5.8 automatic mode: auto_bolt_mail_dry_run.php will issue AADE
            // only for real linked bolt_mail bookings with duplicate protection.
            'auto_send_invoices' => true,

            // Required. Set this to the moment you enable auto mode so old
            // historical rows are not submitted automatically.
            'auto_issue_not_before' => '2026-05-07 23:59:00 Europe/Athens',

            // Manual CLI still uses this phrase when --send is used manually.
            'manual_send_confirm_phrase' => 'I UNDERSTAND SEND AADE MYDATA PRODUCTION RECEIPT',
        ],
    ],
];
