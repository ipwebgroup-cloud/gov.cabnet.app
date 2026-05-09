<?php
/**
 * gov.cabnet.app — guarded one-shot EDXEIX live submit for pre-ride mail bookings v6.8.0
 *
 * This is the live path for EDXEIX when Andreas explicitly approves one exact
 * eligible future booking created from pre-ride Bolt email intake.
 *
 * Safety:
 * - one booking only
 * - pre-ride mail source only
 * - future guard required
 * - terminal/past/receipt-only rows blocked
 * - one-shot lock required
 * - exact confirmation phrase required
 * - auto-disarms server live config after any actual submit attempt
 * - no submission_jobs or submission_attempts are created by this script
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "CLI only\n";
    exit(1);
}

require_once '/home/cabnet/gov.cabnet.app_app/lib/edxeix_live_submit_gate.php';

$options = getopt('', ['booking-id::', 'confirm::', 'json', 'help']);
$json = array_key_exists('json', $options);

if (isset($options['help'])) {
    echo "Live Submit One Mail Booking v6.8.0\n";
    echo "Usage: php live_submit_one_mail_booking.php --booking-id=123 --confirm='I UNDERSTAND SUBMIT LIVE TO EDXEIX' --json\n";
    exit(0);
}

$bookingId = trim((string)($options['booking-id'] ?? ''));
$confirm = (string)($options['confirm'] ?? '');

$out = [
    'ok' => false,
    'submitted' => false,
    'blocked' => true,
    'script' => 'cli/live_submit_one_mail_booking.php',
    'version' => 'v6.8.1',
    'generated_at' => date('c'),
    'source_policy' => [
        'edxeix_submission_source' => 'pre_ride_bolt_email_only',
        'edxeix_uses_bolt_api_as_source' => false,
        'aade_invoice_source' => 'bolt_api_pickup_timestamp_worker_only',
        'pre_ride_email_may_issue_aade' => false,
    ],
    'safety' => [
        'one_booking_only' => true,
        'requires_pre_ride_mail_source' => true,
        'requires_future_guard' => true,
        'requires_one_shot_lock' => true,
        'requires_exact_confirmation_phrase' => true,
        'does_not_issue_aade_receipts' => true,
        'does_not_create_submission_jobs' => true,
        'does_not_create_submission_attempts' => true,
        'auto_disarms_after_submit_attempt' => true,
        'does_not_treat_redirect_as_confirmed_creation' => true,
        'does_not_print_session_cookies_or_tokens' => true,
    ],
    'booking_id' => $bookingId,
    'analysis' => null,
    'response' => null,
    'queue_counts' => null,
    'error' => null,
];

try {
    if ($bookingId === '' || !preg_match('/^[1-9][0-9]*$/', $bookingId)) {
        throw new RuntimeException('booking_id_required_positive_integer');
    }

    $config = gov_bridge_load_config();
    if (!empty($config['app']['timezone'])) {
        date_default_timezone_set((string)$config['app']['timezone']);
    }

    $db = gov_bridge_db();
    $beforeJobs = fetch_count($db, 'submission_jobs');
    $beforeAttempts = fetch_count($db, 'submission_attempts');

    $booking = gov_live_booking_by_id($db, $bookingId);
    if (!$booking) {
        throw new RuntimeException('booking_not_found');
    }

    $liveConfig = gov_live_load_config();
    $analysis = analyze_mail_booking_for_submit($db, $booking, $liveConfig, $confirm);
    $out['analysis'] = $analysis;

    if (empty($analysis['live_submission_allowed'])) {
        $out['blocked'] = true;
        $out['error'] = 'blocked_by_mail_live_safety_gate';
        $out['queue_counts'] = queue_counts($db, $beforeJobs, $beforeAttempts);
        print_output($out, $json);
        exit(1);
    }

    $session = read_live_session_secret($liveConfig);
    $response = gov_live_http_submit($liveConfig, $session, $analysis['payload']);
    $out['response'] = [
        'http_status' => (int)($response['status'] ?? 0),
        'transport_success' => !empty($response['success']),
        'success' => false,
        'confirmed_creation' => false,
        'remote_reference_present' => trim((string)($response['remote_reference'] ?? '')) !== '',
        'remote_reference' => (string)($response['remote_reference'] ?? ''),
        'note' => 'EDXEIX HTTP POST attempted. Response body is intentionally not printed.',
    ];

    if (!empty($liveConfig['write_audit_rows'])) {
        gov_live_insert_audit($db, [
            'booking_id' => $analysis['booking_id'],
            'order_reference' => $analysis['order_reference'],
            'source_system' => $analysis['source_system'],
            'payload_hash' => $analysis['payload_hash'],
            'edxeix_payload_preview' => $analysis['payload'],
            'live_blockers' => [],
        ], ['mode' => 'mail_one_shot_live_submit_v6_8_1_transport_attempt'], $response);
    }

    disarm_live_config('auto_after_mail_live_submit_attempt');

    $out['ok'] = false;
    $out['submitted'] = false;
    $out['blocked'] = false;
    $out['error'] = 'edxeix_transport_attempt_not_counted_as_confirmed_creation_use_browser_ui_confirmation';
    $out['queue_counts'] = queue_counts($db, $beforeJobs, $beforeAttempts);
} catch (Throwable $e) {
    $out['error'] = $e->getMessage();
}

print_output($out, $json);
exit(!empty($out['submitted']) ? 0 : 1);

function analyze_mail_booking_for_submit(mysqli $db, array $booking, array $liveConfig, string $confirm): array
{
    $blockers = [];
    $technical = [];
    $policy = [];

    $bookingId = (string)gov_live_value($booking, ['id'], '');
    $orderRef = (string)gov_live_value($booking, ['order_reference', 'external_order_id', 'source_trip_reference', 'source_trip_id'], '');
    $source = strtolower(trim((string)gov_live_value($booking, ['source_system', 'source_type', 'source'], '')));
    $refLower = strtolower(trim($orderRef));
    $status = strtolower(trim((string)gov_live_value($booking, ['order_status', 'status'], '')));
    $startedAt = trim((string)gov_live_value($booking, ['started_at'], ''));
    $neverLive = gov_live_bool(gov_live_value($booking, ['never_submit_live'], false));
    $blockReason = strtolower(trim((string)gov_live_value($booking, ['live_submit_block_reason'], '')));

    $expected = (string)($liveConfig['confirmation_phrase'] ?? 'I UNDERSTAND SUBMIT LIVE TO EDXEIX');
    if ($confirm !== $expected) { $blockers[] = 'confirmation_phrase_mismatch'; $policy[] = 'confirmation_phrase_mismatch'; }

    if (empty($liveConfig['live_submit_enabled'])) { $blockers[] = 'live_submit_config_disabled'; $policy[] = 'config_disabled'; }
    if (empty($liveConfig['http_submit_enabled'])) { $blockers[] = 'http_submit_config_disabled'; $policy[] = 'config_disabled'; }
    if (empty($liveConfig['edxeix_session_connected'])) { $blockers[] = 'edxeix_session_not_connected'; $policy[] = 'config_disabled'; }
    if (trim((string)($liveConfig['edxeix_submit_url'] ?? '')) === '') { $blockers[] = 'edxeix_submit_url_missing'; $policy[] = 'config_missing'; }

    if (!empty($liveConfig['require_one_shot_lock'])) {
        if (trim((string)($liveConfig['allowed_booking_id'] ?? '')) !== $bookingId) {
            $blockers[] = 'booking_not_explicitly_allowed_by_one_shot_lock';
            $policy[] = 'one_shot_lock_mismatch';
        }
    }

    $isMail = $source === 'bolt_mail' || str_starts_with($refLower, 'mail:');
    if (!$isMail) { $blockers[] = 'edxeix_source_must_be_pre_ride_bolt_email'; $policy[] = 'wrong_source'; }
    if ($neverLive) { $blockers[] = 'never_submit_live_flag_set'; $policy[] = 'never_submit_live'; }
    foreach (['receipt_only', 'aade_receipt_only', 'no_edxeix', 'terminal_status', 'finished', 'past', 'cancel'] as $needle) {
        if ($blockReason !== '' && str_contains($blockReason, $needle)) {
            $blockers[] = 'live_submit_block_reason_blocks:' . $needle;
            $policy[] = 'live_submit_block_reason';
        }
    }
    if ($status === '' || gov_live_terminal_status($status)) { $blockers[] = 'terminal_or_missing_status'; $technical[] = 'terminal_or_missing_status'; }
    if ($startedAt === '') { $blockers[] = 'missing_started_at'; $technical[] = 'missing_started_at'; }
    elseif (!gov_live_future_guard_passes($startedAt)) { $blockers[] = 'started_at_not_sufficiently_future'; $technical[] = 'started_at_not_sufficiently_future'; }

    $sessionState = gov_live_session_state($liveConfig);
    if (empty($sessionState['ready'])) { $blockers[] = 'edxeix_session_not_ready'; $policy[] = 'session_not_ready'; }

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
        $technical[] = 'payload_preview_error';
    }

    foreach ([
        'driver' => 'driver_not_mapped',
        'vehicle' => 'vehicle_not_mapped',
        'boarding_point' => 'missing_boarding_point',
        'disembark_point' => 'missing_disembark_point',
        'started_at' => 'missing_payload_started_at',
        'ended_at' => 'missing_payload_ended_at',
    ] as $field => $code) {
        if (trim((string)($payload[$field] ?? '')) === '') {
            $blockers[] = $code;
            $technical[] = $code;
        }
    }
    if (trim((string)($payload['starting_point'] ?? $payload['starting_point_id'] ?? '')) === '') {
        $blockers[] = 'starting_point_not_mapped';
        $technical[] = 'starting_point_not_mapped';
    }

    if ($payload) {
        $duplicate = gov_live_duplicate_checks($db, $booking, $payload);
        foreach ((array)($duplicate['blockers'] ?? []) as $dup) {
            $blockers[] = 'duplicate:' . (string)$dup;
            $policy[] = 'duplicate';
        }
    }

    return [
        'booking_id' => $bookingId,
        'order_reference' => $orderRef,
        'source_system' => (string)gov_live_value($booking, ['source_system', 'source_type', 'source'], ''),
        'status' => $status,
        'started_at' => $startedAt,
        'live_submission_allowed' => empty($blockers),
        'blockers' => array_values(array_unique($blockers)),
        'technical_blockers' => array_values(array_unique($technical)),
        'policy_blockers' => array_values(array_unique($policy)),
        'payload_hash' => $payload ? gov_live_payload_hash($payload) : '',
        'payload_preview_safe' => safe_payload_preview($payload),
        'payload' => $payload,
    ];
}

function safe_payload_preview(array $payload): array
{
    return [
        'broker' => (string)($payload['broker'] ?? ''),
        'lessor' => (string)($payload['lessor'] ?? ''),
        'driver' => (string)($payload['driver'] ?? ''),
        'vehicle' => (string)($payload['vehicle'] ?? ''),
        'starting_point' => (string)($payload['starting_point'] ?? $payload['starting_point_id'] ?? ''),
        'boarding_point' => (string)($payload['boarding_point'] ?? ''),
        'disembark_point' => (string)($payload['disembark_point'] ?? ''),
        'started_at' => (string)($payload['started_at'] ?? ''),
        'ended_at' => (string)($payload['ended_at'] ?? ''),
        'price' => (string)($payload['price'] ?? ''),
        'mapping_status' => is_array($payload['_mapping_status'] ?? null) ? $payload['_mapping_status'] : [],
    ];
}

function read_live_session_secret(array $liveConfig): array
{
    $file = (string)($liveConfig['edxeix_session_file'] ?? '');
    if ($file === '' || !is_file($file) || !is_readable($file)) {
        throw new RuntimeException('edxeix_session_file_not_readable');
    }
    $decoded = json_decode((string)file_get_contents($file), true);
    if (!is_array($decoded)) {
        throw new RuntimeException('edxeix_session_file_invalid_json');
    }
    if (trim((string)($decoded['cookie_header'] ?? '')) === '' || trim((string)($decoded['csrf_token'] ?? '')) === '') {
        throw new RuntimeException('edxeix_session_cookie_or_csrf_missing');
    }
    return $decoded;
}

function disarm_live_config(string $reason): void
{
    $path = gov_live_config_path();
    $config = gov_live_load_config();
    $config['live_submit_enabled'] = false;
    $config['http_submit_enabled'] = false;
    $config['edxeix_session_connected'] = false;
    $config['allowed_booking_id'] = null;
    $config['allowed_order_reference'] = null;
    $config['last_auto_disarmed_at'] = date(DATE_ATOM);
    $config['last_auto_disarmed_reason'] = $reason;
    $tmp = $path . '.tmp.' . getmypid();
    $php = "<?php\n/**\n * gov.cabnet.app — server-only live submit config.\n * Do not commit this file. Does not contain secrets.\n */\n\nreturn " . var_export($config, true) . ";\n";
    file_put_contents($tmp, $php, LOCK_EX);
    chmod($tmp, 0640);
    rename($tmp, $path);
    @chown($path, 'cabnet');
    @chgrp($path, 'cabnet');
}

function fetch_count(mysqli $db, string $table): int
{
    if (!gov_bridge_table_exists($db, $table)) { return 0; }
    $row = gov_bridge_fetch_one($db, 'SELECT COUNT(*) AS c FROM ' . gov_bridge_quote_identifier($table));
    return (int)($row['c'] ?? 0);
}

function queue_counts(mysqli $db, int $beforeJobs, int $beforeAttempts): array
{
    $afterJobs = fetch_count($db, 'submission_jobs');
    $afterAttempts = fetch_count($db, 'submission_attempts');
    return [
        'submission_jobs_before' => $beforeJobs,
        'submission_jobs_after' => $afterJobs,
        'submission_attempts_before' => $beforeAttempts,
        'submission_attempts_after' => $afterAttempts,
        'queues_unchanged' => $beforeJobs === $afterJobs && $beforeAttempts === $afterAttempts,
    ];
}

function print_output(array $out, bool $json): void
{
    if (isset($out['analysis']['payload'])) {
        unset($out['analysis']['payload']);
    }
    echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
}
