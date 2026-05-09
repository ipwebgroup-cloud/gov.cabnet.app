<?php
/**
 * gov.cabnet.app — EDXEIX mail-live/browser-fill config control v6.8.1
 *
 * Server-only CLI helper for arming or disarming one exact pre-ride mail booking.
 * It writes no secrets and performs no EDXEIX/AADE/network call.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "CLI only\n";
    exit(1);
}

require_once '/home/cabnet/gov.cabnet.app_app/lib/edxeix_live_submit_gate.php';

$options = getopt('', [
    'status',
    'arm-mail-live',
    'arm-browser-fill',
    'booking-id::',
    'order-reference::',
    'by::',
    'disarm',
    'json',
    'help',
]);

$json = array_key_exists('json', $options);

if (isset($options['help'])) {
    echo "EDXEIX Mail Live Config Control v6.8.1\n";
    echo "Usage:\n";
    echo "  php edxeix_live_mail_config_control.php --status --json\n";
    echo "  php edxeix_live_mail_config_control.php --arm-browser-fill --booking-id=123 --by=Andreas --json\n  php edxeix_live_mail_config_control.php --arm-mail-live --booking-id=123 --by=Andreas --json\n";
    echo "  php edxeix_live_mail_config_control.php --disarm --by=Andreas --json\n";
    exit(0);
}

$out = [
    'ok' => false,
    'script' => 'cli/edxeix_live_mail_config_control.php',
    'version' => 'v6.8.1',
    'generated_at' => date('c'),
    'action' => null,
    'config_path' => gov_live_config_path(),
    'safety' => [
        'does_not_call_edxeix' => true,
        'does_not_issue_aade_receipts' => true,
        'does_not_create_submission_jobs' => true,
        'does_not_create_submission_attempts' => true,
        'does_not_write_secrets' => true,
        'requires_one_exact_booking' => true,
        'supports_browser_fill_without_server_submit' => true,
    ],
    'summary' => [],
    'error' => null,
];

try {
    $status = array_key_exists('status', $options);
    $armMailLive = array_key_exists('arm-mail-live', $options);
    $armBrowserFill = array_key_exists('arm-browser-fill', $options);
    $disarm = array_key_exists('disarm', $options);
    $actions = (int)$status + (int)$armMailLive + (int)$armBrowserFill + (int)$disarm;
    if ($actions !== 1) {
        throw new RuntimeException('choose_exactly_one_action_status_arm_browser_fill_arm_mail_live_or_disarm');
    }

    $path = gov_live_config_path();
    $config = gov_live_load_config();
    $db = gov_bridge_db();

    if ($status) {
        $session = gov_live_session_state($config);
        $out['action'] = 'status';
        $out['summary'] = safe_config_summary($config, $session);
        $out['ok'] = true;
    } elseif ($disarm) {
        $by = trim((string)($options['by'] ?? 'Andreas')) ?: 'Andreas';
        $config['live_submit_enabled'] = false;
        $config['http_submit_enabled'] = false;
        $config['edxeix_session_connected'] = false;
        $config['browser_fill_enabled'] = false;
        $config['require_one_shot_lock'] = true;
        $config['allowed_booking_id'] = null;
        $config['allowed_order_reference'] = null;
        $config['last_disarmed_at'] = date(DATE_ATOM);
        $config['last_disarmed_by'] = $by;
        write_live_config($path, $config);
        $out['action'] = 'disarm';
        $out['summary'] = safe_config_summary($config, gov_live_session_state($config));
        $out['ok'] = true;
    } else {
        $isBrowserFill = $armBrowserFill;
        $bookingId = trim((string)($options['booking-id'] ?? ''));
        $orderReference = trim((string)($options['order-reference'] ?? ''));
        $by = trim((string)($options['by'] ?? 'Andreas')) ?: 'Andreas';

        if ($bookingId === '' || !preg_match('/^[1-9][0-9]*$/', $bookingId)) {
            throw new RuntimeException('booking_id_required_positive_integer');
        }

        $booking = gov_live_booking_by_id($db, $bookingId);
        if (!$booking) {
            throw new RuntimeException('booking_not_found');
        }

        $analysis = analyze_mail_booking_for_live($db, $booking, $config);
        if (empty($analysis['preflight_ready'])) {
            $out['action'] = 'arm-mail-live-blocked';
            $out['summary'] = [
                'booking_id' => (int)$bookingId,
                'preflight_ready' => false,
                'blockers' => $analysis['blockers'],
                'categories' => $analysis['categories'],
            ];
            throw new RuntimeException('booking_not_preflight_ready_for_mail_live');
        }

        $config['live_submit_enabled'] = $isBrowserFill ? false : true;
        $config['http_submit_enabled'] = $isBrowserFill ? false : true;
        $config['edxeix_session_connected'] = $isBrowserFill ? false : true;
        $config['browser_fill_enabled'] = $isBrowserFill ? true : false;
        $config['require_one_shot_lock'] = true;
        $config['require_confirmation_phrase'] = true;
        $config['require_post'] = true;
        $config['allowed_booking_id'] = $bookingId;
        $config['allowed_order_reference'] = $orderReference !== '' ? $orderReference : (string)($booking['order_reference'] ?? '');
        $config['mail_live_one_shot_armed_at'] = date(DATE_ATOM);
        $config['mail_live_one_shot_armed_by'] = $by;
        $config['mail_live_one_shot_policy'] = 'pre_ride_bolt_email_only';
        $config['browser_fill_armed_at'] = $isBrowserFill ? date(DATE_ATOM) : ($config['browser_fill_armed_at'] ?? null);
        $config['browser_fill_armed_by'] = $isBrowserFill ? $by : ($config['browser_fill_armed_by'] ?? null);
        write_live_config($path, $config);

        $out['action'] = $isBrowserFill ? 'arm-browser-fill' : 'arm-mail-live';
        $out['summary'] = [
            'booking_id' => (int)$bookingId,
            'order_reference_present' => trim((string)($config['allowed_order_reference'] ?? '')) !== '',
            'armed' => true,
            'browser_fill_enabled' => $isBrowserFill,
            'server_live_submit_enabled' => !$isBrowserFill,
            'preflight_ready' => true,
            'config' => safe_config_summary($config, gov_live_session_state($config)),
            'submit_command' => "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/live_submit_one_mail_booking.php --booking-id={$bookingId} --confirm='" . (string)($config['confirmation_phrase'] ?? 'I UNDERSTAND SUBMIT LIVE TO EDXEIX') . "' --json",
        ];
        $out['ok'] = true;
    }
} catch (Throwable $e) {
    $out['error'] = $e->getMessage();
}

print_output($out, $json);
exit(!empty($out['ok']) ? 0 : 1);

function write_live_config(string $path, array $config): void
{
    $dir = dirname($path);
    if (!is_dir($dir)) {
        throw new RuntimeException('config_directory_missing');
    }
    $tmp = $path . '.tmp.' . getmypid();
    $php = "<?php\n/**\n * gov.cabnet.app — server-only live submit config.\n * Do not commit this file. Does not contain secrets.\n */\n\nreturn " . var_export($config, true) . ";\n";
    if (file_put_contents($tmp, $php, LOCK_EX) === false) {
        throw new RuntimeException('unable_to_write_temp_config');
    }
    chmod($tmp, 0640);
    rename($tmp, $path);
    @chown($path, 'cabnet');
    @chgrp($path, 'cabnet');
}

function safe_config_summary(array $config, array $session): array
{
    return [
        'live_submit_enabled' => !empty($config['live_submit_enabled']),
        'http_submit_enabled' => !empty($config['http_submit_enabled']),
        'edxeix_session_connected' => !empty($config['edxeix_session_connected']),
        'require_one_shot_lock' => !empty($config['require_one_shot_lock']),
        'allowed_booking_id_present' => trim((string)($config['allowed_booking_id'] ?? '')) !== '',
        'allowed_order_reference_present' => trim((string)($config['allowed_order_reference'] ?? '')) !== '',
        'submit_url_present' => trim((string)($config['edxeix_submit_url'] ?? '')) !== '',
        'session_file_exists' => !empty($session['session_file_exists']),
        'session_ready' => !empty($session['ready']),
        'session_updated_at' => $session['updated_at'] ?? null,
        'session_placeholders_detected' => !empty($session['placeholder_detected']),
        'browser_fill_enabled' => !empty($config['browser_fill_enabled']),
    ];
}

function print_output(array $out, bool $json): void
{
    if ($json) {
        echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        return;
    }
    echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
}

function analyze_mail_booking_for_live(mysqli $db, array $booking, array $liveConfig): array
{
    $blockers = [];
    $categories = [];
    $source = strtolower(trim((string)gov_live_value($booking, ['source_system', 'source_type', 'source'], '')));
    $ref = strtolower(trim((string)gov_live_value($booking, ['order_reference', 'external_order_id', 'source_trip_reference', 'source_trip_id'], '')));
    $status = strtolower(trim((string)gov_live_value($booking, ['order_status', 'status'], '')));
    $startedAt = trim((string)gov_live_value($booking, ['started_at'], ''));
    $neverLive = gov_live_bool(gov_live_value($booking, ['never_submit_live'], false));
    $blockReason = strtolower(trim((string)gov_live_value($booking, ['live_submit_block_reason'], '')));

    $isMail = $source === 'bolt_mail' || str_starts_with($ref, 'mail:');
    if (!$isMail) { $blockers[] = 'edxeix_source_must_be_pre_ride_bolt_email'; $categories[] = 'wrong_source'; }
    if ($neverLive) { $blockers[] = 'never_submit_live_flag_set'; $categories[] = 'never_submit_live'; }
    foreach (['receipt_only', 'aade_receipt_only', 'no_edxeix', 'terminal_status', 'finished', 'past', 'cancel'] as $needle) {
        if ($blockReason !== '' && str_contains($blockReason, $needle)) {
            $blockers[] = 'live_submit_block_reason_blocks:' . $needle;
            $categories[] = 'policy_blocked';
        }
    }
    if ($status === '' || gov_live_terminal_status($status)) { $blockers[] = 'terminal_or_missing_status'; $categories[] = 'terminal_or_missing_status'; }
    if ($startedAt === '') { $blockers[] = 'missing_started_at'; $categories[] = 'missing_time'; }
    elseif (!gov_live_future_guard_passes($startedAt)) { $blockers[] = 'started_at_not_sufficiently_future'; $categories[] = 'not_future'; }

    $payload = [];
    try {
        if (!function_exists('gov_build_edxeix_preview_payload')) {
            throw new RuntimeException('payload_builder_missing');
        }
        $payload = gov_build_edxeix_preview_payload($db, $booking);
        if (function_exists('gov_live_normalize_edxeix_payload_field_names')) {
            $payload = gov_live_normalize_edxeix_payload_field_names($payload);
        }
    } catch (Throwable $e) {
        $blockers[] = 'payload_preview_error:' . $e->getMessage();
        $categories[] = 'payload_error';
    }

    $driver = trim((string)($payload['driver'] ?? ''));
    $vehicle = trim((string)($payload['vehicle'] ?? ''));
    $startingPoint = trim((string)($payload['starting_point'] ?? $payload['starting_point_id'] ?? ''));
    $boarding = trim((string)($payload['boarding_point'] ?? ''));
    $disembark = trim((string)($payload['disembark_point'] ?? ''));
    $payloadStarted = trim((string)($payload['started_at'] ?? ''));
    $payloadEnded = trim((string)($payload['ended_at'] ?? ''));

    if ($driver === '') { $blockers[] = 'driver_not_mapped'; $categories[] = 'missing_mapping'; }
    if ($vehicle === '') { $blockers[] = 'vehicle_not_mapped'; $categories[] = 'missing_mapping'; }
    if ($startingPoint === '') { $blockers[] = 'starting_point_not_mapped'; $categories[] = 'missing_mapping'; }
    if ($boarding === '') { $blockers[] = 'missing_boarding_point'; $categories[] = 'missing_payload_field'; }
    if ($disembark === '') { $blockers[] = 'missing_disembark_point'; $categories[] = 'missing_payload_field'; }
    if ($payloadStarted === '') { $blockers[] = 'missing_payload_started_at'; $categories[] = 'missing_payload_field'; }
    if ($payloadEnded === '') { $blockers[] = 'missing_payload_ended_at'; $categories[] = 'missing_payload_field'; }

    if ($payload) {
        $duplicate = gov_live_duplicate_checks($db, $booking, $payload);
        foreach ((array)($duplicate['blockers'] ?? []) as $dupBlocker) {
            $blockers[] = 'duplicate:' . (string)$dupBlocker;
            $categories[] = 'duplicate';
        }
    }

    $session = gov_live_session_state($liveConfig);
    if (trim((string)($liveConfig['edxeix_submit_url'] ?? '')) === '') { $blockers[] = 'edxeix_submit_url_missing'; $categories[] = 'config_blocked'; }
    if (empty($session['ready'])) { $blockers[] = 'edxeix_session_not_ready'; $categories[] = 'session_blocked'; }

    return [
        'preflight_ready' => empty($blockers),
        'blockers' => array_values(array_unique($blockers)),
        'categories' => array_values(array_unique($categories)),
        'payload_hash' => $payload ? gov_live_payload_hash($payload) : '',
    ];
}
