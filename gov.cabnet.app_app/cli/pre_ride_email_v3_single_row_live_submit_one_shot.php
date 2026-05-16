<?php
/**
 * gov.cabnet.app — V3 single-row controlled live EDXEIX submit one-shot.
 * v3.2.15
 *
 * Purpose:
 * - Manually submit exactly one already-reviewed V3 pre-ride queue row to EDXEIX.
 * - Intended first-use target: queue_id 1590 only, with explicit Andreas authorization.
 *
 * Safety:
 * - CLI only.
 * - Requires exact queue id.
 * - Requires explicit live flag and exact confirmation phrase.
 * - Requires matching dry-run preview hash.
 * - Requires V3 server-only live gate config to be armed for the same queue id.
 * - Requires legacy/live HTTP transport config and session to be ready.
 * - Requires row to still be future-safe and complete.
 * - Auto-disarms V3 gate and legacy live flags after any live transport attempt.
 * - Does not call Bolt.
 * - Does not call AADE.
 * - Does not change the production Pre-Ride Tool.
 * - Does not submit batches or scan for multiple rows.
 * - Does not print cookies, CSRF tokens, or raw response bodies.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "CLI only\n";
    exit(1);
}

const GOV_V3_SINGLE_ROW_LIVE_SUBMIT_VERSION = 'v3.2.15-v3-single-row-live-submit-one-shot';
const GOV_V3_SINGLE_ROW_REQUIRED_CONFIRM = 'I_UNDERSTAND_SUBMIT_QUEUE_1590_TO_EDXEIX_ONCE';
const GOV_V3_SINGLE_ROW_DEFAULT_ALLOWED_QUEUE_ID = 1590;
const GOV_V3_SINGLE_ROW_DEFAULT_EXPECTED_HASH = '109473d72b6799287e3ef5fadf155238532516f47ef6817362beb48ff56de022';
const GOV_V3_SINGLE_ROW_QUEUE_TABLE = 'pre_ride_email_v3_queue';

date_default_timezone_set('Europe/Athens');

/** @return array<string,mixed> */
function v3srls_args(array $argv): array
{
    $out = [
        'help' => false,
        'queue_id' => 0,
        'json' => false,
        'analyze_only' => true,
        'live_submit_one' => false,
        'confirm' => '',
        'expected_hash' => '',
        'min_future_minutes' => 5,
    ];
    foreach (array_slice($argv, 1) as $arg) {
        if ($arg === '--help' || $arg === '-h') { $out['help'] = true; }
        elseif ($arg === '--json') { $out['json'] = true; }
        elseif ($arg === '--analyze-only' || $arg === '--dry-run') { $out['analyze_only'] = true; }
        elseif ($arg === '--live-submit-one') { $out['live_submit_one'] = true; $out['analyze_only'] = false; }
        elseif (str_starts_with($arg, '--queue-id=')) { $out['queue_id'] = (int)substr($arg, 11); }
        elseif (str_starts_with($arg, '--confirm-single-row-live-submit=')) { $out['confirm'] = (string)substr($arg, 33); }
        elseif (str_starts_with($arg, '--expected-preview-sha256=')) { $out['expected_hash'] = trim((string)substr($arg, 26)); }
        elseif (str_starts_with($arg, '--min-future-minutes=')) { $out['min_future_minutes'] = max(1, min(240, (int)substr($arg, 21))); }
    }
    if ((int)$out['queue_id'] <= 0) { $out['queue_id'] = GOV_V3_SINGLE_ROW_DEFAULT_ALLOWED_QUEUE_ID; }
    if ((string)$out['expected_hash'] === '') { $out['expected_hash'] = GOV_V3_SINGLE_ROW_DEFAULT_EXPECTED_HASH; }
    return $out;
}

function v3srls_print_help(): void
{
    echo "V3 single-row live-submit one-shot " . GOV_V3_SINGLE_ROW_LIVE_SUBMIT_VERSION . "\n\n";
    echo "Analyze only:\n";
    echo "  php pre_ride_email_v3_single_row_live_submit_one_shot.php --queue-id=1590 --expected-preview-sha256=" . GOV_V3_SINGLE_ROW_DEFAULT_EXPECTED_HASH . " --json\n\n";
    echo "Live one-shot, only after server config is armed and operator is supervising:\n";
    echo "  php pre_ride_email_v3_single_row_live_submit_one_shot.php --queue-id=1590 --expected-preview-sha256=" . GOV_V3_SINGLE_ROW_DEFAULT_EXPECTED_HASH . " --live-submit-one --confirm-single-row-live-submit=" . GOV_V3_SINGLE_ROW_REQUIRED_CONFIRM . " --json\n\n";
}

/** @return array<string,mixed> */
function v3srls_bootstrap(): array
{
    $appRoot = dirname(__DIR__);
    $bootstrap = $appRoot . '/src/bootstrap.php';
    if (!is_file($bootstrap)) {
        $bootstrap = dirname(__DIR__, 2) . '/src/bootstrap.php';
    }
    if (!is_file($bootstrap)) {
        throw new RuntimeException('private_app_bootstrap_not_found');
    }
    require_once '/home/cabnet/gov.cabnet.app_app/lib/edxeix_live_submit_gate.php';
    $ctx = require $bootstrap;
    if (!is_array($ctx) || !isset($ctx['db']) || !method_exists($ctx['db'], 'connection')) {
        throw new RuntimeException('bootstrap_db_context_unavailable');
    }
    return $ctx;
}

function v3srls_db(array $ctx): mysqli
{
    $db = $ctx['db']->connection();
    if (!$db instanceof mysqli) { throw new RuntimeException('mysqli_connection_unavailable'); }
    return $db;
}

function v3srls_table_exists(mysqli $db, string $table): bool
{
    $stmt = $db->prepare('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1');
    if (!$stmt) { return false; }
    $stmt->bind_param('s', $table);
    $stmt->execute();
    return (bool)$stmt->get_result()->fetch_assoc();
}

/** @return array<string,mixed>|null */
function v3srls_fetch_queue_row(mysqli $db, int $queueId): ?array
{
    if (!v3srls_table_exists($db, GOV_V3_SINGLE_ROW_QUEUE_TABLE)) { return null; }
    $stmt = $db->prepare('SELECT * FROM ' . GOV_V3_SINGLE_ROW_QUEUE_TABLE . ' WHERE id = ? LIMIT 1');
    if (!$stmt) { throw new RuntimeException('queue_select_prepare_failed: ' . $db->error); }
    $stmt->bind_param('i', $queueId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return is_array($row) ? $row : null;
}

function v3srls_v3_config_path(): string
{
    return '/home/cabnet/gov.cabnet.app_config/pre_ride_email_v3_live_submit.php';
}

/** @return array<string,mixed> */
function v3srls_load_v3_config(): array
{
    $path = v3srls_v3_config_path();
    $default = [
        'enabled' => false,
        'mode' => 'disabled',
        'adapter' => 'disabled',
        'hard_enable_live_submit' => false,
        'acknowledgement' => '',
        'required_acknowledgement' => 'I EXPLICITLY APPROVE V3 LIVE EDXEIX SUBMIT',
        'allowed_queue_id' => null,
        'expected_preview_sha256' => '',
        'min_future_minutes' => 5,
        'auto_disarm_after_attempt' => true,
        'legacy_live_config_path' => '/home/cabnet/gov.cabnet.app_config/live_submit.php',
    ];
    if (is_file($path) && is_readable($path)) {
        $loaded = require $path;
        if (is_array($loaded)) { return array_replace($default, $loaded); }
    }
    return $default;
}

function v3srls_disarm_v3_config(string $reason): bool
{
    $path = v3srls_v3_config_path();
    $config = v3srls_load_v3_config();
    $config['enabled'] = false;
    $config['mode'] = 'disabled';
    $config['adapter'] = 'disabled';
    $config['hard_enable_live_submit'] = false;
    $config['allowed_queue_id'] = null;
    $config['expected_preview_sha256'] = '';
    $config['last_auto_disarmed_at'] = date(DATE_ATOM);
    $config['last_auto_disarmed_reason'] = $reason;
    $php = "<?php\n/** Server-only V3 live-submit config. Do not commit. */\n\nreturn " . var_export($config, true) . ";\n";
    $tmp = $path . '.tmp.' . getmypid();
    if (file_put_contents($tmp, $php, LOCK_EX) === false) { return false; }
    @chmod($tmp, 0640);
    $ok = @rename($tmp, $path);
    @chown($path, 'cabnet');
    @chgrp($path, 'cabnet');
    return $ok;
}

function v3srls_disarm_legacy_live_config(string $reason): bool
{
    if (!function_exists('gov_live_config_path') || !function_exists('gov_live_load_config')) { return false; }
    $path = gov_live_config_path();
    $config = gov_live_load_config();
    $config['live_submit_enabled'] = false;
    $config['http_submit_enabled'] = false;
    $config['allowed_booking_id'] = null;
    $config['allowed_order_reference'] = null;
    $config['last_auto_disarmed_at'] = date(DATE_ATOM);
    $config['last_auto_disarmed_reason'] = $reason;
    $php = "<?php\n/** Server-only live submit config. Do not commit. */\n\nreturn " . var_export($config, true) . ";\n";
    $tmp = $path . '.tmp.' . getmypid();
    if (file_put_contents($tmp, $php, LOCK_EX) === false) { return false; }
    @chmod($tmp, 0640);
    $ok = @rename($tmp, $path);
    @chown($path, 'cabnet');
    @chgrp($path, 'cabnet');
    return $ok;
}

/** @return array{ok:bool,minutes:int|null,message:string} */
function v3srls_future_check(string $pickup, int $minFuture): array
{
    $pickup = trim($pickup);
    if ($pickup === '' || $pickup === '0000-00-00 00:00:00') {
        return ['ok' => false, 'minutes' => null, 'message' => 'pickup_datetime_missing'];
    }
    try {
        $tz = new DateTimeZone('Europe/Athens');
        $p = new DateTimeImmutable($pickup, $tz);
        $now = new DateTimeImmutable('now', $tz);
        $minutes = (int)floor(($p->getTimestamp() - $now->getTimestamp()) / 60);
        if ($minutes < $minFuture) {
            return ['ok' => false, 'minutes' => $minutes, 'message' => 'pickup_not_future_safe_min_' . $minFuture];
        }
        return ['ok' => true, 'minutes' => $minutes, 'message' => 'future_safe'];
    } catch (Throwable $e) {
        return ['ok' => false, 'minutes' => null, 'message' => 'pickup_parse_error'];
    }
}

/** @return array<int,string> */
function v3srls_required_missing(array $row): array
{
    $missing = [];
    foreach (['lessor_id','driver_id','vehicle_id','starting_point_id','customer_name','customer_phone','pickup_datetime','estimated_end_datetime','pickup_address','dropoff_address','price_amount'] as $key) {
        if (trim((string)($row[$key] ?? '')) === '') { $missing[] = $key; }
    }
    return $missing;
}

/** @return array<string,string> */
function v3srls_payload_from_row(array $row, array $session): array
{
    $pickup = (string)($row['pickup_datetime'] ?? '');
    $end = (string)($row['estimated_end_datetime'] ?? '');
    $price = number_format((float)((string)($row['price_amount'] ?? '0')), 2, '.', '');
    $startingPoint = (string)($row['starting_point_id'] ?? '');
    $payload = [
        '_token' => (string)($session['csrf_token'] ?? ''),
        'broker' => '',
        'lessor' => (string)($row['lessor_id'] ?? ''),
        'lessee[type]' => 'natural',
        'lessee[name]' => (string)($row['customer_name'] ?? ''),
        'lessee[vat_number]' => '',
        'lessee[legal_representative]' => '',
        'driver' => (string)($row['driver_id'] ?? ''),
        'vehicle' => (string)($row['vehicle_id'] ?? ''),
        'starting_point' => $startingPoint,
        'starting_point_id' => $startingPoint,
        'boarding_point' => (string)($row['pickup_address'] ?? ''),
        'coordinates' => '',
        'disembark_point' => (string)($row['dropoff_address'] ?? ''),
        'drafted_at' => v3srls_format_edxeix_datetime($pickup),
        'started_at' => v3srls_format_edxeix_datetime($pickup),
        'ended_at' => v3srls_format_edxeix_datetime($end),
        'price' => $price,
    ];
    return $payload;
}

function v3srls_format_edxeix_datetime(string $raw): string
{
    $ts = strtotime($raw);
    if ($ts === false) { return $raw; }
    return date('d/m/Y H:i', $ts);
}

/** @return array<string,mixed> */
function v3srls_safe_payload_preview(array $payload): array
{
    return [
        'lessor' => (string)($payload['lessor'] ?? ''),
        'driver' => (string)($payload['driver'] ?? ''),
        'vehicle' => (string)($payload['vehicle'] ?? ''),
        'starting_point' => (string)($payload['starting_point'] ?? ''),
        'boarding_point' => (string)($payload['boarding_point'] ?? ''),
        'disembark_point' => (string)($payload['disembark_point'] ?? ''),
        'started_at' => (string)($payload['started_at'] ?? ''),
        'ended_at' => (string)($payload['ended_at'] ?? ''),
        'price' => (string)($payload['price'] ?? ''),
        'csrf_present' => trim((string)($payload['_token'] ?? '')) !== '',
    ];
}

/** @return array<string,mixed> */
function v3srls_read_session(array $legacyConfig): array
{
    $file = (string)($legacyConfig['edxeix_session_file'] ?? '/home/cabnet/gov.cabnet.app_app/storage/runtime/edxeix_session.json');
    if ($file === '' || !is_file($file) || !is_readable($file)) { return ['ok' => false, 'file' => $file, 'data' => [], 'error' => 'session_file_not_readable']; }
    $data = json_decode((string)file_get_contents($file), true);
    if (!is_array($data)) { return ['ok' => false, 'file' => $file, 'data' => [], 'error' => 'session_file_invalid_json']; }
    $cookie = trim((string)($data['cookie_header'] ?? ''));
    $csrf = trim((string)($data['csrf_token'] ?? ''));
    return [
        'ok' => $cookie !== '' && $csrf !== '',
        'file' => $file,
        'data' => $data,
        'error' => $cookie === '' || $csrf === '' ? 'session_cookie_or_csrf_missing' : '',
        'cookie_present' => $cookie !== '',
        'csrf_present' => $csrf !== '',
        'updated_at' => $data['updated_at'] ?? $data['saved_at'] ?? '',
    ];
}

/** @return array<string,mixed> */
function v3srls_build_report(array $opts): array
{
    $ctx = v3srls_bootstrap();
    $db = v3srls_db($ctx);
    $queueId = (int)$opts['queue_id'];
    $expectedHash = (string)$opts['expected_hash'];
    $minFuture = (int)$opts['min_future_minutes'];
    $confirm = (string)$opts['confirm'];
    $liveRequested = !empty($opts['live_submit_one']);

    $row = v3srls_fetch_queue_row($db, $queueId);
    $v3Config = v3srls_load_v3_config();
    $legacyConfig = function_exists('gov_live_load_config') ? gov_live_load_config() : [];
    $session = v3srls_read_session($legacyConfig);
    $payload = $row ? v3srls_payload_from_row($row, is_array($session['data'] ?? null) ? $session['data'] : []) : [];
    $payloadHash = $payload ? hash('sha256', json_encode(v3srls_safe_payload_preview($payload), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) : '';

    $future = $row ? v3srls_future_check((string)($row['pickup_datetime'] ?? ''), $minFuture) : ['ok' => false, 'minutes' => null, 'message' => 'row_not_found'];
    $missing = $row ? v3srls_required_missing($row) : ['queue_row'];
    $status = strtolower(trim((string)($row['queue_status'] ?? '')));

    $blocks = [];
    if (!$row) { $blocks[] = 'queue_row_not_found'; }
    if ($queueId !== GOV_V3_SINGLE_ROW_DEFAULT_ALLOWED_QUEUE_ID) { $blocks[] = 'queue_id_not_this_authorized_patch'; }
    if ($status !== 'live_submit_ready') { $blocks[] = 'queue_status_not_live_submit_ready'; }
    if ($missing) { $blocks[] = 'missing_required_fields:' . implode(',', $missing); }
    if (empty($future['ok'])) { $blocks[] = 'future_guard_failed:' . (string)$future['message']; }
    if (!empty($row['submitted_at'])) { $blocks[] = 'row_already_has_submitted_at'; }
    if (!empty($row['failed_at'])) { $blocks[] = 'row_has_failed_at'; }
    if (trim((string)($row['last_error'] ?? '')) !== '') { $blocks[] = 'row_has_last_error'; }

    $requiredAck = (string)($v3Config['required_acknowledgement'] ?? 'I EXPLICITLY APPROVE V3 LIVE EDXEIX SUBMIT');
    if (empty($v3Config['enabled'])) { $blocks[] = 'v3_config_enabled_false'; }
    if ((string)($v3Config['mode'] ?? '') !== 'live') { $blocks[] = 'v3_config_mode_not_live'; }
    if ((string)($v3Config['adapter'] ?? '') === '' || (string)($v3Config['adapter'] ?? 'disabled') === 'disabled') { $blocks[] = 'v3_config_adapter_disabled'; }
    if (empty($v3Config['hard_enable_live_submit'])) { $blocks[] = 'v3_config_hard_enable_false'; }
    if ((string)($v3Config['acknowledgement'] ?? '') !== $requiredAck) { $blocks[] = 'v3_config_acknowledgement_missing'; }
    if ((int)($v3Config['allowed_queue_id'] ?? 0) !== $queueId) { $blocks[] = 'v3_config_allowed_queue_id_mismatch'; }
    if (trim((string)($v3Config['expected_preview_sha256'] ?? '')) !== '' && trim((string)$v3Config['expected_preview_sha256']) !== $expectedHash) { $blocks[] = 'v3_config_expected_preview_hash_mismatch'; }

    if (empty($legacyConfig['live_submit_enabled'])) { $blocks[] = 'legacy_live_config_live_submit_enabled_false'; }
    if (empty($legacyConfig['http_submit_enabled'])) { $blocks[] = 'legacy_live_config_http_submit_enabled_false'; }
    if (trim((string)($legacyConfig['edxeix_submit_url'] ?? '')) === '') { $blocks[] = 'legacy_live_config_submit_url_missing'; }
    if (empty($legacyConfig['edxeix_session_connected'])) { $blocks[] = 'legacy_live_config_session_connected_false'; }
    if (empty($session['ok'])) { $blocks[] = 'edxeix_session_not_ready:' . (string)($session['error'] ?? ''); }

    if ($expectedHash === '') { $blocks[] = 'expected_preview_hash_missing'; }
    // The externally recorded dry-run hash is from the V3 preview object, not the transport payload.
    // Require it to be supplied exactly so the operator explicitly anchors the run to a reviewed packet.
    if ($expectedHash !== GOV_V3_SINGLE_ROW_DEFAULT_EXPECTED_HASH) { $blocks[] = 'expected_preview_hash_not_authorized_for_queue_1590'; }

    if ($liveRequested && $confirm !== GOV_V3_SINGLE_ROW_REQUIRED_CONFIRM) { $blocks[] = 'confirmation_phrase_mismatch'; }
    if (!$liveRequested) { $blocks[] = 'live_submit_one_flag_not_present_preview_only'; }

    $attempted = false;
    $response = null;
    $disarm = ['v3_config_disarmed' => false, 'legacy_config_disarmed' => false];
    if ($liveRequested && !$blocks) {
        $attempted = true;
        try {
            if (!function_exists('gov_live_http_submit')) { throw new RuntimeException('gov_live_http_submit_unavailable'); }
            $response = gov_live_http_submit($legacyConfig, is_array($session['data'] ?? null) ? $session['data'] : [], $payload);
        } catch (Throwable $e) {
            $response = [
                'status' => 0,
                'success' => false,
                'remote_reference' => '',
                'error' => $e->getMessage(),
                'json' => ['ok' => false, 'submitted' => false, 'error' => $e->getMessage()],
            ];
        }
        $disarm['v3_config_disarmed'] = v3srls_disarm_v3_config('auto_after_v3_single_row_live_submit_attempt_queue_' . $queueId);
        $disarm['legacy_config_disarmed'] = v3srls_disarm_legacy_live_config('auto_after_v3_single_row_live_submit_attempt_queue_' . $queueId);
    }

    $safeResponse = null;
    if (is_array($response)) {
        $safeResponse = [
            'http_status' => (int)($response['status'] ?? 0),
            'transport_success_heuristic' => !empty($response['success']),
            'remote_reference_present' => trim((string)($response['remote_reference'] ?? '')) !== '',
            'remote_reference' => (string)($response['remote_reference'] ?? ''),
            'error' => (string)($response['error'] ?? ''),
            'body_hidden' => true,
            'headers_hidden' => true,
        ];
    }

    return [
        'ok' => true,
        'version' => GOV_V3_SINGLE_ROW_LIVE_SUBMIT_VERSION,
        'generated_at' => date(DATE_ATOM),
        'mode' => $liveRequested ? 'single_row_live_submit_requested' : 'analyze_only_preview',
        'queue_id' => $queueId,
        'authorized_queue_id_for_this_patch' => GOV_V3_SINGLE_ROW_DEFAULT_ALLOWED_QUEUE_ID,
        'live_submit_requested' => $liveRequested,
        'live_submit_attempted' => $attempted,
        'submitted_confirmed_by_script' => false,
        'edxeix_http_call_made' => $attempted,
        'auto_disarm_after_attempt' => true,
        'blocks' => array_values(array_unique(array_filter($blocks))),
        'ready_to_attempt_live_now' => $liveRequested && !$blocks,
        'row' => $row ? [
            'queue_status' => (string)($row['queue_status'] ?? ''),
            'pickup_datetime' => (string)($row['pickup_datetime'] ?? ''),
            'estimated_end_datetime' => (string)($row['estimated_end_datetime'] ?? ''),
            'minutes_until_pickup' => $future['minutes'],
            'lessor_id' => (string)($row['lessor_id'] ?? ''),
            'driver_id' => (string)($row['driver_id'] ?? ''),
            'vehicle_id' => (string)($row['vehicle_id'] ?? ''),
            'starting_point_id' => (string)($row['starting_point_id'] ?? ''),
            'driver_name' => (string)($row['driver_name'] ?? ''),
            'vehicle_plate' => (string)($row['vehicle_plate'] ?? ''),
            'pickup_address' => (string)($row['pickup_address'] ?? ''),
            'dropoff_address' => (string)($row['dropoff_address'] ?? ''),
            'price_amount' => (string)($row['price_amount'] ?? ''),
            'customer_name_present' => trim((string)($row['customer_name'] ?? '')) !== '',
            'customer_phone_present' => trim((string)($row['customer_phone'] ?? '')) !== '',
        ] : null,
        'expected_preview_sha256' => $expectedHash,
        'transport_payload_preview' => v3srls_safe_payload_preview($payload),
        'transport_payload_preview_hash' => $payloadHash,
        'v3_gate' => [
            'config_path' => v3srls_v3_config_path(),
            'enabled' => !empty($v3Config['enabled']),
            'mode' => (string)($v3Config['mode'] ?? ''),
            'adapter' => (string)($v3Config['adapter'] ?? ''),
            'hard_enable_live_submit' => !empty($v3Config['hard_enable_live_submit']),
            'allowed_queue_id' => $v3Config['allowed_queue_id'] ?? null,
            'expected_preview_sha256_present' => trim((string)($v3Config['expected_preview_sha256'] ?? '')) !== '',
        ],
        'legacy_transport_gate' => [
            'live_submit_enabled' => !empty($legacyConfig['live_submit_enabled']),
            'http_submit_enabled' => !empty($legacyConfig['http_submit_enabled']),
            'edxeix_session_connected' => !empty($legacyConfig['edxeix_session_connected']),
            'submit_url_present' => trim((string)($legacyConfig['edxeix_submit_url'] ?? '')) !== '',
            'session_file' => (string)($legacyConfig['edxeix_session_file'] ?? ''),
            'session_ready' => !empty($session['ok']),
            'session_cookie_present' => !empty($session['cookie_present']),
            'session_csrf_present' => !empty($session['csrf_present']),
        ],
        'response_safe' => $safeResponse,
        'post_attempt_disarm' => $disarm,
        'safety' => [
            'one_queue_id_only' => true,
            'requires_confirmation_phrase' => true,
            'requires_expected_preview_hash' => true,
            'does_not_print_cookies_or_csrf' => true,
            'does_not_print_response_body' => true,
            'does_not_call_bolt' => true,
            'does_not_call_aade' => true,
            'does_not_touch_production_pre_ride_tool' => true,
            'does_not_submit_batches' => true,
            'queue_mutation_made' => false,
            'db_write_made_by_this_script_before_transport' => false,
        ],
        'live_command_template' => '/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_single_row_live_submit_one_shot.php --queue-id=1590 --expected-preview-sha256=' . GOV_V3_SINGLE_ROW_DEFAULT_EXPECTED_HASH . ' --live-submit-one --confirm-single-row-live-submit=' . GOV_V3_SINGLE_ROW_REQUIRED_CONFIRM . ' --json',
    ];
}

$args = v3srls_args($argv);
if (!empty($args['help'])) { v3srls_print_help(); exit(0); }

try {
    $report = v3srls_build_report($args);
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(empty($report['live_submit_requested']) || empty($report['blocks']) ? 0 : 1);
} catch (Throwable $e) {
    $out = [
        'ok' => false,
        'version' => GOV_V3_SINGLE_ROW_LIVE_SUBMIT_VERSION,
        'generated_at' => date(DATE_ATOM),
        'error' => $e->getMessage(),
        'edxeix_http_call_made' => false,
        'submitted_confirmed_by_script' => false,
    ];
    echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(1);
}
