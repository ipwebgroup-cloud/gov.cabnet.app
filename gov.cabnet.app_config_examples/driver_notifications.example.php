<?php
/**
 * Example server-only config snippet for v4.5 driver email copies.
 *
 * Merge the returned 'mail' array into:
 * /home/cabnet/gov.cabnet.app_config/config.php
 *
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

            // Production mode: resolve by Bolt driver identity/name only, using
            // mapping_drivers.driver_email populated by the Bolt Driver Directory sync.
            // Vehicle plate is never used as a recipient resolver.
            'resolve_from_bolt_driver_directory' => true,
            'sync_reference_on_miss' => true,
            'sync_reference_hours_back' => 720,

            // Emergency fallback by driver name only. Leave empty unless needed.
            'manual_driver_emails' => [],
        ],
    ],
];
