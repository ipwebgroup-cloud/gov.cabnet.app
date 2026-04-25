<?php
/**
 * gov.cabnet.app — EDXEIX live submit config example.
 *
 * Copy to:
 *   /home/cabnet/gov.cabnet.app_config/live_submit.php
 *
 * Keep the real file server-only. Do not commit real config.
 */

return [
    // Keep false until Andreas explicitly authorizes one controlled live submit.
    'live_submit_enabled' => false,

    // Keep false in the preparatory patch. The current gate still blocks live HTTP.
    'http_submit_enabled' => false,

    'require_post' => true,
    'require_confirmation_phrase' => true,
    'confirmation_phrase' => 'I UNDERSTAND SUBMIT LIVE TO EDXEIX',

    'require_real_bolt_source' => true,
    'require_future_guard' => true,
    'require_no_lab_or_test_flags' => true,
    'require_no_duplicate_success' => true,

    // Optional one-shot lock for the first live test. Fill after the real future
    // Bolt candidate is visible and reviewed.
    'allowed_booking_id' => null,
    'allowed_order_reference' => null,

    // Fill only when the exact EDXEIX submit endpoint has been confirmed.
    'edxeix_submit_url' => '',
    'edxeix_form_method' => 'POST',

    // Server-only saved session/cookie/CSRF storage. Do not commit contents.
    'edxeix_session_file' => '/home/cabnet/gov.cabnet.app_app/storage/runtime/edxeix_session.json',
    'curl_timeout_seconds' => 45,

    'write_audit_rows' => true,
];
