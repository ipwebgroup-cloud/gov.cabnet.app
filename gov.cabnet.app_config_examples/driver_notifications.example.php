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

            // Preferred production mode: resolve driver email from mapping_drivers.driver_email,
            // which is populated by the Bolt Driver Directory sync/API response.
            'resolve_from_bolt_driver_directory' => true,
            'sync_reference_on_miss' => true,
            'sync_reference_hours_back' => 720,

            // Emergency fallback only. Leave empty unless the Bolt API does not expose an email.
            'manual_driver_emails' => [],
            'manual_vehicle_plate_emails' => [],
        ],
    ],
];
