<?php
/**
 * gov.cabnet.app — v5.0 Live-submit arming, session disconnected.
 *
 * Writes server-only live_submit.php flags for guarded live mode while keeping
 * the EDXEIX session explicitly disconnected. No secrets are written. No Bolt
 * or EDXEIX network call is made. No jobs or attempts are created.
 */

declare(strict_types=1);

$path = '/home/cabnet/gov.cabnet.app_config/live_submit.php';
$options = getopt('', ['by::']);
$by = trim((string)($options['by'] ?? 'ops')) ?: 'ops';

$default = [
    'live_submit_enabled' => false,
    'http_submit_enabled' => false,
    'edxeix_session_connected' => false,
    'require_one_shot_lock' => true,
    'require_post' => true,
    'require_confirmation_phrase' => true,
    'confirmation_phrase' => 'I UNDERSTAND SUBMIT LIVE TO EDXEIX',
    'require_real_bolt_source' => true,
    'require_future_guard' => true,
    'require_no_lab_or_test_flags' => true,
    'require_no_duplicate_success' => true,
    'allowed_booking_id' => null,
    'allowed_order_reference' => null,
    'edxeix_submit_url' => 'https://edxeix.yme.gov.gr/dashboard/lease-agreement',
    'edxeix_form_method' => 'POST',
    'edxeix_session_file' => '/home/cabnet/gov.cabnet.app_app/storage/runtime/edxeix_session.json',
    'curl_timeout_seconds' => 45,
    'write_audit_rows' => true,
    'note' => 'Real config is server-only. Do not commit this file.',
];

$current = [];
if (is_file($path) && is_readable($path)) {
    $loaded = require $path;
    if (is_array($loaded)) {
        $current = $loaded;
    }
}

$config = array_replace_recursive($default, $current, [
    'live_submit_enabled' => true,
    'http_submit_enabled' => true,
    'edxeix_session_connected' => false,
    'require_one_shot_lock' => true,
    'allowed_booking_id' => null,
    'allowed_order_reference' => null,
    'armed_mode' => 'LIVE_ARMED_SESSION_DISCONNECTED',
    'armed_at' => date(DATE_ATOM),
    'armed_by' => $by,
    'note' => 'LIVE ARMED, but EDXEIX session intentionally disconnected. No live POST can occur until edxeix_session_connected=true and one-shot booking lock is set.',
]);

$dir = dirname($path);
if (!is_dir($dir)) {
    mkdir($dir, 0750, true);
}

$tmp = $path . '.tmp.' . getmypid();
$php = "<?php\n/**\n * gov.cabnet.app — server-only live submit config.\n * Do not commit this file. Does not contain secrets.\n */\n\nreturn " . var_export($config, true) . ";\n";
file_put_contents($tmp, $php, LOCK_EX);
chmod($tmp, 0640);
rename($tmp, $path);

if (function_exists('posix_getpwnam')) {
    $owner = posix_getpwnam('cabnet');
    if (is_array($owner)) {
        @chown($path, 'cabnet');
        @chgrp($path, 'cabnet');
    }
}

echo '[' . date(DATE_ATOM) . "] Live submit armed with EDXEIX session disconnected.\n";
echo "Config: {$path}\n";
echo "live_submit_enabled=true\n";
echo "http_submit_enabled=true\n";
echo "edxeix_session_connected=false\n";
echo "require_one_shot_lock=true\n";
echo "Safety: no secrets written; no Bolt/EDXEIX calls; no jobs/attempts created.\n";
