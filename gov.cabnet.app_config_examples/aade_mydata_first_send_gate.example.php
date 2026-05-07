<?php
// Example only. Do not commit or paste real AADE credentials.
// Place equivalent values in /home/cabnet/gov.cabnet.app_config/config.php.

'receipts' => [
    'mode' => 'aade_mydata',
    'vat_rate_percent' => 13,
    'aade_mydata' => [
        'enabled' => true,
        'environment' => 'production',
        'endpoint_base' => 'https://mydatapi.aade.gr/myDATA',
        'user_id' => 'SERVER_ONLY_AADE_USER_ID',
        'subscription_key' => 'SERVER_ONLY_AADE_SUBSCRIPTION_KEY',
        'issuer_vat_number' => '802653254',
        'issuer_name' => 'LUXLIMO Ι Κ Ε',
        'issuer_country' => 'GR',
        'issuer_branch' => 0,

        // Keep false until accountant/authority confirms payload fields.
        'allow_send_invoices' => false,

        // Required when allow_send_invoices=true.
        // The CLI will not print this phrase. Store it server-side only.
        'manual_send_confirm_phrase' => 'I UNDERSTAND SEND AADE MYDATA PRODUCTION RECEIPT',

        // Accountant-confirmed defaults before first SendInvoices.
        'invoice_type' => '11.2',
        'series' => 'BOLT',
        'aa_prefix' => 'BOLT-',
        'vat_category' => 2,
        'payment_method_type' => 1,
        'income_classification_type' => 'E3_561_003',
        'income_classification_category' => 'category1_3',
        'line_description' => 'Transfer services',
    ],
],

'mail' => [
    'driver_notifications' => [
        // Driver pre-ride copy may remain enabled.
        'enabled' => true,

        // Receipt emails must remain disabled until official AADE issuance and official PDF flow are complete.
        'receipt_copy_enabled' => false,
        'receipt_pdf_mode' => 'aade_mydata',
    ],
],
