<?php
/**
 * Example only. Do not commit real AADE/myDATA credentials.
 * Add/merge into /home/cabnet/gov.cabnet.app_config/config.php.
 */
return [
    'receipts' => [
        'mode' => 'aade_mydata',
        'vat_rate_percent' => 13,
        'aade_mydata' => [
            'enabled' => false,
            'environment' => 'test', // test | production
            'endpoint_base' => '', // optional override; normally leave blank
            'timeout' => 30,
            'connect_timeout' => 15,
            'user_id' => 'SERVER_ONLY_AADE_REST_API_USER_ID',
            'subscription_key' => 'SERVER_ONLY_AADE_SUBSCRIPTION_KEY',
            'issuer_vat_number' => '802653254',
            'issuer_name' => 'LUXLIMO Ι Κ Ε',
            'issuer_country' => 'GR',
            'vat_category_percent' => 13,
        ],
    ],
    'mail' => [
        'driver_notifications' => [
            // Keep normal driver pre-ride copy active, but keep receipt copies off
            // until AADE/myDATA official issuance succeeds.
            'enabled' => true,
            'receipt_copy_enabled' => false,
            'receipt_pdf_mode' => 'aade_mydata',
            'receipt_pdf_attachment_required' => true,
        ],
    ],
];
