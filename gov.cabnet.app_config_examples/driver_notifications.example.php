<?php
/**
 * Example server-only config snippet for v4.5 driver email copies.
 *
 * Merge the returned 'mail' array into:
 * /home/cabnet/gov.cabnet.app_config/config.php
 *
 * Use real driver email addresses only on the server. Do not commit or paste them into chat.
 */

return [
    'mail' => [
        'bolt_bridge_maildir' => '/home/cabnet/mail/gov.cabnet.app/bolt-bridge',
        'driver_notifications' => [
            'enabled' => true,
            'from_email' => 'bolt-bridge@gov.cabnet.app',
            'from_name' => 'Cabnet Bolt Bridge',
            'reply_to' => 'bolt-bridge@gov.cabnet.app',
            'bcc' => '',
            'subject_prefix' => 'Bolt pre-ride details',
            'driver_emails' => [
                'Filippos Giannakopoulos' => 'REPLACE_WITH_DRIVER_EMAIL',
                'Nikolaos Vidakis' => 'REPLACE_WITH_DRIVER_EMAIL',
            ],
            'vehicle_plate_emails' => [
                'EHA2545' => 'REPLACE_WITH_DRIVER_EMAIL',
                'EMX6874' => 'REPLACE_WITH_DRIVER_EMAIL',
            ],
        ],
    ],
];
