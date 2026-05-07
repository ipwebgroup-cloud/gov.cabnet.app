<?php
/**
 * Example only. Do not put real AADE/myDATA credentials in Git.
 * Copy the needed keys into /home/cabnet/gov.cabnet.app_config/config.php.
 */
return [
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
            'vat_category_percent' => 13,

            // Confirm with accountant before first production SendInvoices.
            'invoice_type' => '11.2',
            'series' => 'BOLT',
            'aa_prefix' => 'BOLT-',
            'payment_method_type' => 1,
            'vat_category' => 2,
            'income_classification_type' => 'E3_561_003',
            'income_classification_category' => 'category1_3',
            'line_description' => 'Transfer services',

            // Keep false until a controlled production receipt test is explicitly approved.
            'allow_send_invoices' => false,
            'manual_send_confirm_phrase' => 'I UNDERSTAND SEND AADE MYDATA PRODUCTION RECEIPT',
        ],
    ],
];
