<?php
/**
 * gov.cabnet.app — V3 live-submit gate example config.
 *
 * Copy this file on the server ONLY to:
 *   /home/cabnet/gov.cabnet.app_config/pre_ride_email_v3_live_submit.php
 *
 * Keep the real file out of Git.
 *
 * IMPORTANT:
 * - The default below is hard-disabled.
 * - Changing this file alone should never be enough to submit to EDXEIX.
 * - Future live submit code must still require explicit implementation and approval.
 */

return [
    // Master switch. Keep false until Andreas explicitly approves live EDXEIX submit.
    'enabled' => false,

    // Allowed values: disabled, dry_run, live.
    // Current V3 workers must treat anything other than live as no-submit.
    'mode' => 'disabled',

    // Extra acknowledgement required before any future worker may consider live submit.
    // Future live code should require this exact phrase together with enabled=true and mode=live.
    'acknowledgement' => '',
    'required_acknowledgement' => 'I EXPLICITLY APPROVE V3 LIVE EDXEIX SUBMIT',

    // Final queue status a row must have before any future live submit worker may consider it.
    'required_queue_status' => 'live_submit_ready',

    // Minimum future buffer. Keep low only because Bolt emails can arrive very close to pickup.
    'min_future_minutes' => 1,

    // Optional allow-list. Empty means no lessor restriction at config level.
    // Example: ['2307', '3814']
    'allowed_lessors' => [],

    // Operator approval remains required until a later explicitly approved patch removes this.
    'operator_approval_required' => true,

    // Current adapter remains disabled. Future adapter names must be explicit.
    'adapter' => 'disabled',
];
