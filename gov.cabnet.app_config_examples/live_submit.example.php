<?php
/**
 * gov.cabnet.app — EDXEIX live submit config example.
 *
 * Copy to:
 *   /home/cabnet/gov.cabnet.app_config/live_submit.php
 *
 * Keep the real file server-only. Do not commit real config.
 * This file contains no secrets.
 */

return [
    // v5.0 can be armed while the EDXEIX session remains disconnected.
    // live_submit_enabled + http_submit_enabled true does not by itself submit.
    // The live gate also requires edxeix_session_connected=true, a ready session,
    // one-shot booking lock, future guard, real Bolt source, duplicate checks, and confirmation phrase.
    'live_submit_enabled' => false,
    'http_submit_enabled' => false,

    // Keep false for the current safety-net phase. The live gate will block with
    // edxeix_session_not_connected even if a session file exists.
    'edxeix_session_connected' => false,

    // Strong one-shot lock: the first live submit must be explicitly limited to
    // a single reviewed booking id and/or order reference.
    'require_one_shot_lock' => true,
    'allowed_booking_id' => null,
    'allowed_order_reference' => null,

    'require_post' => true,
    'require_confirmation_phrase' => true,
    'confirmation_phrase' => 'I UNDERSTAND SUBMIT LIVE TO EDXEIX',

    'require_real_bolt_source' => true,
    'require_future_guard' => true,
    'require_no_lab_or_test_flags' => true,
    'require_no_duplicate_success' => true,

    'edxeix_submit_url' => 'https://edxeix.yme.gov.gr/dashboard/lease-agreement',
    'edxeix_form_method' => 'POST',
    'edxeix_session_file' => '/home/cabnet/gov.cabnet.app_app/storage/runtime/edxeix_session.json',
    'curl_timeout_seconds' => 45,

    // Writes to edxeix_live_submission_audit if the table exists.
    'write_audit_rows' => true,

    'note' => 'Server-only config. No secrets here. Do not commit real config.',
];
