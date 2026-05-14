<?php

declare(strict_types=1);

/**
 * V3 live adapter contract test CLI.
 *
 * Read-only, non-submitting harness that defines the exact future EDXEIX
 * request envelope shape for a selected V3 queue row.
 *
 * Safety: no Bolt call, no EDXEIX call, no AADE call, no DB writes,
 * no queue status changes, no adapter submit call, no production submission
 * tables, V0 untouched.
 */

const V3CT_VERSION = 'v3.0.75-v3-live-adapter-contract-test';
const V3CT_MODE = 'read_only_non_submitting_live_adapter_contract_test';
const V3CT_SAFETY = 'No Bolt call. No EDXEIX call. No AADE call. No DB writes. No queue status changes. Adapter submit is not called. No production submission tables. V0 untouched.';
const V3CT_GATE_CONFIG_PATH = '/home/cabnet/gov.cabnet.app_config/pre_ride_email_v3_live_submit.php';

/** @return array<string,string> */
function v3ct_cli_args(array $argv): array
{
    $out = [];
    foreach ($argv as $arg) {
        if (strpos((string)$arg, '--') !== 0) {
            continue;
        }
        $arg = substr((string)$arg, 2);
        if (strpos($arg, '=') !== false) {
            [$k, $v] = explode('=', $arg, 2);
            $out[$k] = $v;
        } else {
            $out[$arg] = '1';
        }
    }
    return $out;
}

function v3ct_bool(mixed $value, bool $default = false): bool
{
    if (is_bool($value)) {
        return $value;
    }
    if (is_int($value) || is_float($value)) {
        return (int)$value === 1;
    }
    if (is_string($value)) {
        $v = strtolower(trim($value));
        if (in_array($v, ['1', 'true', 'yes', 'on', 'enabled', 'live'], true)) {
            return true;
        }
        if (in_array($v, ['0', 'false', 'no', 'off', 'disabled', ''], true)) {
            return false;
        }
    }
    return $default;
}

function v3ct_yesno(mixed $value): string
{
    return v3ct_bool($value) ? 'yes' : 'no';
}

function v3ct_app_root(): string
{
    return dirname(__DIR__);
}

function v3ct_config_root(): string
{
    return dirname(v3ct_app_root()) . '/gov.cabnet.app_config';
}

/** @return array<int,array<string,mixed>> */
function v3ct_load_config_candidates(array &$report): array
{
    $appRoot = v3ct_app_root();
    $configRoot = v3ct_config_root();
    $paths = [
        $configRoot . '/database.php',
        $configRoot . '/db.php',
        $configRoot . '/config.php',
        $configRoot . '/app.php',
        $appRoot . '/config/database.php',
        $appRoot . '/config/config.php',
    ];

    $configs = [];
    foreach ($paths as $path) {
        if (!is_readable($path)) {
            continue;
        }
        $config = null;
        $db = null;
        $database = null;
        $settings = null;
        $included = include $path;
        $report['config_files'][] = ['path' => $path, 'readable' => true, 'returned_array' => is_array($included)];
        if (is_array($included)) {
            $configs[] = $included;
        }
        foreach (['config' => $config, 'db' => $db, 'database' => $database, 'settings' => $settings] as $var) {
            if (is_array($var)) {
                $configs[] = $var;
            }
        }
    }

    return $configs;
}

/** @param array<string,mixed> $config @return array<string,mixed>|null */
function v3ct_extract_db_config(array $config): ?array
{
    $candidates = [$config];
    foreach (['db', 'database', 'mysqli', 'mysql'] as $key) {
        if (isset($config[$key]) && is_array($config[$key])) {
            $candidates[] = $config[$key];
        }
    }

    foreach ($candidates as $candidate) {
        $name = $candidate['database'] ?? $candidate['dbname'] ?? $candidate['db_name'] ?? $candidate['name'] ?? null;
        $user = $candidate['username'] ?? $candidate['user'] ?? $candidate['db_user'] ?? null;
        if ($name !== null && $user !== null) {
            return $candidate;
        }
    }

    return null;
}

function v3ct_env(string $key): string
{
    $v = getenv($key);
    return $v === false ? '' : (string)$v;
}

function v3ct_connect(array &$report): mysqli
{
    $configs = v3ct_load_config_candidates($report);
    $dbConfig = null;
    foreach ($configs as $cfg) {
        $dbConfig = v3ct_extract_db_config($cfg);
        if ($dbConfig !== null) {
            break;
        }
    }

    $host = 'localhost';
    $user = '';
    $pass = '';
    $name = '';
    $port = 3306;
    $socket = null;

    if ($dbConfig !== null) {
        $host = (string)($dbConfig['host'] ?? $dbConfig['hostname'] ?? 'localhost');
        $user = (string)($dbConfig['username'] ?? $dbConfig['user'] ?? $dbConfig['db_user'] ?? '');
        $pass = (string)($dbConfig['password'] ?? $dbConfig['pass'] ?? $dbConfig['db_pass'] ?? '');
        $name = (string)($dbConfig['database'] ?? $dbConfig['dbname'] ?? $dbConfig['db_name'] ?? $dbConfig['name'] ?? '');
        $port = (int)($dbConfig['port'] ?? 3306);
        $socket = isset($dbConfig['socket']) ? (string)$dbConfig['socket'] : null;
    } else {
        $host = v3ct_env('GOV_DB_HOST') ?: v3ct_env('DB_HOST') ?: 'localhost';
        $user = v3ct_env('GOV_DB_USER') ?: v3ct_env('DB_USER');
        $pass = v3ct_env('GOV_DB_PASS') ?: v3ct_env('DB_PASS');
        $name = v3ct_env('GOV_DB_NAME') ?: v3ct_env('DB_NAME');
        $port = (int)(v3ct_env('GOV_DB_PORT') ?: v3ct_env('DB_PORT') ?: 3306);
    }

    if ($user === '' || $name === '') {
        throw new RuntimeException('DB config not found. Checked server config candidates and environment variables without exposing credentials.');
    }

    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $db = new mysqli($host, $user, $pass, $name, $port, $socket ?: null);
    $db->set_charset('utf8mb4');
    $report['database'] = ['connected' => true, 'name' => $name, 'host' => $host];
    return $db;
}

function v3ct_table_exists(mysqli $db, string $table): bool
{
    $stmt = $db->prepare('SELECT COUNT(*) AS c FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1');
    $stmt->bind_param('s', $table);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc() ?: [];
    return (int)($row['c'] ?? 0) > 0;
}

/** @return array<string,mixed>|null */
function v3ct_select_row(mysqli $db, ?int $queueId): ?array
{
    if ($queueId !== null && $queueId > 0) {
        $stmt = $db->prepare('SELECT *, TIMESTAMPDIFF(MINUTE, NOW(), pickup_datetime) AS minutes_until_now FROM pre_ride_email_v3_queue WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $queueId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        return is_array($row) ? $row : null;
    }

    $sql = "SELECT *, TIMESTAMPDIFF(MINUTE, NOW(), pickup_datetime) AS minutes_until_now
            FROM pre_ride_email_v3_queue
            ORDER BY
              CASE WHEN queue_status = 'live_submit_ready' THEN 0 ELSE 1 END,
              COALESCE(pickup_datetime, created_at) DESC,
              id DESC
            LIMIT 1";
    $res = $db->query($sql);
    $row = $res->fetch_assoc();
    return is_array($row) ? $row : null;
}

function v3ct_money(mixed $value): string
{
    $s = trim((string)$value);
    if ($s === '') {
        return '';
    }
    if (is_numeric($s)) {
        return number_format((float)$s, 2, '.', '');
    }
    return $s;
}

/** @param array<string,mixed> $row @return array<string,string> */
function v3ct_build_edxeix_payload(array $row): array
{
    return [
        'lessor' => trim((string)($row['lessor_id'] ?? '')),
        'driver' => trim((string)($row['driver_id'] ?? '')),
        'vehicle' => trim((string)($row['vehicle_id'] ?? '')),
        'starting_point_id' => trim((string)($row['starting_point_id'] ?? '')),
        'lessee_name' => trim((string)($row['customer_name'] ?? '')),
        'lessee_phone' => trim((string)($row['customer_phone'] ?? '')),
        'boarding_point' => trim((string)($row['pickup_address'] ?? '')),
        'disembark_point' => trim((string)($row['dropoff_address'] ?? '')),
        'started_at' => trim((string)($row['pickup_datetime'] ?? '')),
        'ended_at' => trim((string)($row['estimated_end_datetime'] ?? '')),
        'price' => v3ct_money($row['price_amount'] ?? ''),
        'price_text' => trim((string)($row['price_text'] ?? '')),
    ];
}

/** @param array<string,mixed> $payload @return array<string,string> */
function v3ct_normalize_payload(array $payload): array
{
    $keys = ['lessor', 'driver', 'vehicle', 'starting_point_id', 'lessee_name', 'lessee_phone', 'boarding_point', 'disembark_point', 'started_at', 'ended_at', 'price', 'price_text'];
    $out = [];
    foreach ($keys as $key) {
        $value = $payload[$key] ?? '';
        $out[$key] = $key === 'price' ? v3ct_money($value) : trim((string)$value);
    }
    return $out;
}

/** @param array<string,string> $payload @return array<int,string> */
function v3ct_missing_required(array $payload): array
{
    $required = ['lessor', 'driver', 'vehicle', 'starting_point_id', 'lessee_name', 'lessee_phone', 'boarding_point', 'disembark_point', 'started_at', 'ended_at', 'price'];
    $missing = [];
    foreach ($required as $key) {
        if (!isset($payload[$key]) || trim((string)$payload[$key]) === '') {
            $missing[] = $key;
        }
    }
    return $missing;
}

/** @param array<string,mixed> $data */
function v3ct_json_hash(array $data): string
{
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return hash('sha256', $json === false ? '' : $json);
}

/** @param array<string,mixed> $source */
function v3ct_pick(array $source, array $keys, mixed $default = null): mixed
{
    foreach ($keys as $key) {
        $parts = explode('.', (string)$key);
        $cur = $source;
        $found = true;
        foreach ($parts as $part) {
            if (!is_array($cur) || !array_key_exists($part, $cur)) {
                $found = false;
                break;
            }
            $cur = $cur[$part];
        }
        if ($found) {
            return $cur;
        }
    }
    return $default;
}

/** @return array<string,mixed> */
function v3ct_load_gate_config(string $path = V3CT_GATE_CONFIG_PATH): array
{
    $out = [
        'path' => $path,
        'exists' => is_file($path),
        'readable' => is_readable($path),
        'loaded' => false,
        'returned_array' => false,
        'error' => '',
        'raw_keys' => [],
        'safe' => [
            'enabled' => false,
            'mode' => 'missing',
            'adapter' => 'missing',
            'hard_enable_live_submit' => false,
            'acknowledgement_phrase_present' => false,
            'required_queue_status' => 'live_submit_ready',
            'min_future_minutes' => 1,
            'operator_approval_required' => true,
        ],
    ];

    if (!is_file($path)) {
        $out['error'] = 'gate config file missing';
        return $out;
    }
    if (!is_readable($path)) {
        $out['error'] = 'gate config file not readable';
        return $out;
    }

    try {
        $config = include $path;
    } catch (Throwable $e) {
        $out['error'] = 'gate config include failed: ' . $e->getMessage();
        return $out;
    }

    $out['loaded'] = true;
    if (!is_array($config)) {
        $out['error'] = 'gate config did not return an array';
        return $out;
    }

    $out['returned_array'] = true;
    $out['raw_keys'] = array_values(array_map('strval', array_keys($config)));

    $requiredAck = (string)v3ct_pick($config, [
        'required_acknowledgement',
        'required_acknowledgement_phrase',
        'acknowledgement_phrase',
        'required_ack_phrase',
        'live_submit.required_acknowledgement',
        'live_submit.required_acknowledgement_phrase',
        'pre_ride_email_v3.required_acknowledgement',
        'v3_live_submit.required_acknowledgement',
    ], '');

    $providedAck = (string)v3ct_pick($config, [
        'acknowledgement',
        'acknowledgement_phrase_value',
        'live_submit.acknowledgement',
        'pre_ride_email_v3.acknowledgement',
        'v3_live_submit.acknowledgement',
    ], '');

    $out['safe'] = [
        'enabled' => v3ct_bool(v3ct_pick($config, ['enabled', 'live_submit.enabled', 'pre_ride_email_v3.enabled', 'v3_live_submit.enabled'], false)),
        'mode' => strtolower(trim((string)v3ct_pick($config, ['mode', 'live_submit.mode', 'pre_ride_email_v3.mode', 'v3_live_submit.mode'], 'missing'))),
        'adapter' => strtolower(trim((string)v3ct_pick($config, ['adapter', 'live_submit.adapter', 'pre_ride_email_v3.adapter', 'v3_live_submit.adapter'], 'missing'))),
        'hard_enable_live_submit' => v3ct_bool(v3ct_pick($config, ['hard_enable_live_submit', 'hard_enabled', 'live_submit.hard_enable_live_submit', 'pre_ride_email_v3.hard_enable_live_submit', 'v3_live_submit.hard_enable_live_submit'], false)),
        'acknowledgement_phrase_present' => trim($requiredAck) !== '',
        'acknowledgement_matches_required' => trim($requiredAck) !== '' && hash_equals(trim($requiredAck), trim($providedAck)),
        'required_queue_status' => (string)v3ct_pick($config, ['required_queue_status', 'live_submit.required_queue_status', 'pre_ride_email_v3.required_queue_status', 'v3_live_submit.required_queue_status'], 'live_submit_ready'),
        'min_future_minutes' => (int)v3ct_pick($config, ['min_future_minutes', 'live_submit.min_future_minutes', 'pre_ride_email_v3.min_future_minutes', 'v3_live_submit.min_future_minutes'], 1),
        'operator_approval_required' => v3ct_bool(v3ct_pick($config, ['operator_approval_required', 'live_submit.operator_approval_required', 'pre_ride_email_v3.operator_approval_required', 'v3_live_submit.operator_approval_required'], true), true),
    ];

    return $out;
}

/** @return array<string,mixed> */
function v3ct_starting_point_check(mysqli $db, string $lessorId, string $startId): array
{
    $out = ['ok' => false, 'label' => '', 'reason' => 'not checked'];
    if ($lessorId === '' || $startId === '') {
        $out['reason'] = 'lessor or starting point missing';
        return $out;
    }
    if (!v3ct_table_exists($db, 'pre_ride_email_v3_starting_point_options')) {
        $out['reason'] = 'starting-point options table missing';
        return $out;
    }
    $stmt = $db->prepare('SELECT label FROM pre_ride_email_v3_starting_point_options WHERE edxeix_lessor_id = ? AND edxeix_starting_point_id = ? AND is_active = 1 LIMIT 1');
    $stmt->bind_param('ss', $lessorId, $startId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if (!is_array($row)) {
        $out['reason'] = 'operator-verified starting point not found';
        return $out;
    }
    $out['ok'] = true;
    $out['label'] = (string)($row['label'] ?? '');
    $out['reason'] = 'operator_verified';
    return $out;
}

/** @return array<string,mixed> */
function v3ct_approval_check(mysqli $db, int $queueId): array
{
    $out = ['ok' => false, 'table_exists' => false, 'latest' => null, 'reason' => 'not checked'];
    if (!v3ct_table_exists($db, 'pre_ride_email_v3_live_submit_approvals')) {
        $out['reason'] = 'approval table missing';
        return $out;
    }
    $out['table_exists'] = true;
    $stmt = $db->prepare("SELECT id, queue_id, approval_status, approval_scope, approved_by, approved_at, expires_at, revoked_at
        FROM pre_ride_email_v3_live_submit_approvals
        WHERE queue_id = ?
        ORDER BY id DESC
        LIMIT 1");
    $stmt->bind_param('i', $queueId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if (!is_array($row)) {
        $out['reason'] = 'no approval row found';
        return $out;
    }
    $out['latest'] = $row;
    $status = strtolower(trim((string)($row['approval_status'] ?? '')));
    $revoked = trim((string)($row['revoked_at'] ?? '')) !== '';
    $expired = false;
    if (trim((string)($row['expires_at'] ?? '')) !== '') {
        $expired = strtotime((string)$row['expires_at']) !== false && (int)strtotime((string)$row['expires_at']) < time();
    }
    if ($status !== 'approved') {
        $out['reason'] = 'latest approval status is not approved';
        return $out;
    }
    if ($revoked) {
        $out['reason'] = 'latest approval has been revoked';
        return $out;
    }
    if ($expired) {
        $out['reason'] = 'latest approval has expired';
        return $out;
    }
    $out['ok'] = true;
    $out['reason'] = 'approved_not_expired_not_revoked';
    return $out;
}

/** @param array<string,string> $payload @param array<string,mixed> $row @param array<string,mixed> $gate @return array<string,mixed> */
function v3ct_build_request_contract(array $payload, array $row, array $gate): array
{
    $payloadHash = v3ct_json_hash($payload);
    $queueId = (int)($row['id'] ?? 0);
    $requestId = 'v3-queue-' . $queueId . '-' . substr($payloadHash, 0, 12);

    return [
        'network_allowed' => false,
        'adapter_submit_allowed' => false,
        'adapter_submit_called' => false,
        'edxeix_call_made' => false,
        'method' => 'POST',
        'endpoint_label' => 'edxeix_transport_submit_future_placeholder',
        'endpoint_url_loaded' => false,
        'endpoint_url_redacted' => '[not loaded by contract test]',
        'headers_without_secrets' => [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json; charset=utf-8',
            'X-Bridge-Version' => V3CT_VERSION,
            'X-Bridge-Mode' => V3CT_MODE,
            'X-Request-Id' => $requestId,
            'Authorization' => '[redacted/not-loaded]',
        ],
        'timeout_policy' => [
            'connect_timeout_seconds' => 5,
            'request_timeout_seconds' => 20,
            'retry_attempts' => 0,
            'retry_backoff_seconds' => 0,
            'idempotency_required' => true,
        ],
        'idempotency' => [
            'request_id' => $requestId,
            'queue_id' => $queueId,
            'dedupe_key' => trim((string)($row['dedupe_key'] ?? '')),
            'payload_sha256' => $payloadHash,
        ],
        'payload' => $payload,
        'payload_sha256' => $payloadHash,
        'payload_field_count' => count($payload),
        'response_normalization_contract' => [
            'ok' => 'boolean, true only when EDXEIX confirms acceptance',
            'submitted' => 'boolean, true only after a real confirmed submit',
            'blocked' => 'boolean, true when prevented locally before network call',
            'http_status' => 'integer HTTP status when network is later enabled',
            'remote_reference' => 'string EDXEIX reference/id when accepted',
            'remote_status' => 'string normalized status from EDXEIX response',
            'error_code' => 'string safe machine code when failed or blocked',
            'error_message' => 'string safe operator message without secrets',
            'raw_response_sha256' => 'sha256 hash of raw response; raw body must not be logged by default',
        ],
        'future_live_preconditions' => [
            'master_gate_enabled' => !empty($gate['enabled']),
            'master_gate_mode' => (string)($gate['mode'] ?? 'missing'),
            'master_gate_adapter' => (string)($gate['adapter'] ?? 'missing'),
            'hard_enable_live_submit' => !empty($gate['hard_enable_live_submit']),
            'acknowledgement_phrase_present' => !empty($gate['acknowledgement_phrase_present']),
            'acknowledgement_matches_required' => !empty($gate['acknowledgement_matches_required']),
            'required_queue_status' => (string)($gate['required_queue_status'] ?? 'live_submit_ready'),
            'min_future_minutes' => (int)($gate['min_future_minutes'] ?? 1),
            'operator_approval_required' => !empty($gate['operator_approval_required']),
        ],
    ];
}

/** @return array<string,mixed> */
function v3ct_adapter_probe(): array
{
    $appRoot = v3ct_app_root();
    $files = [
        'interface' => $appRoot . '/src/BoltMailV3/LiveSubmitAdapterV3.php',
        'edxeix_live' => $appRoot . '/src/BoltMailV3/EdxeixLiveSubmitAdapterV3.php',
    ];
    $out = [
        'files' => [],
        'class' => 'Bridge\\BoltMailV3\\EdxeixLiveSubmitAdapterV3',
        'class_exists' => false,
        'instantiated' => false,
        'name' => '',
        'is_live_capable' => false,
        'submit_called' => false,
        'submit_returned' => false,
        'submitted' => false,
        'network_aware_tokens_detected' => false,
        'safe_for_contract_test' => true,
        'reason' => '',
        'error' => '',
    ];

    foreach ($files as $key => $path) {
        $raw = is_readable($path) ? (string)file_get_contents($path) : '';
        $scan = [
            'contains_curl' => stripos($raw, 'curl_') !== false,
            'contains_file_get_contents_http' => stripos($raw, 'file_get_contents(') !== false && stripos($raw, 'http') !== false,
            'contains_stream_context' => stripos($raw, 'stream_context_create') !== false,
            'contains_live_capable_true' => stripos($raw, 'isLiveCapable') !== false && (bool)preg_match('/isLiveCapable\s*\([^)]*\)\s*:\s*bool\s*\{[^}]*return\s+true\s*;/is', $raw),
        ];
        $out['files'][$key] = ['path' => $path, 'exists' => is_file($path), 'readable' => is_readable($path), 'scan' => $scan];
        if ($key === 'edxeix_live' && ($scan['contains_curl'] || $scan['contains_file_get_contents_http'] || $scan['contains_stream_context'])) {
            $out['network_aware_tokens_detected'] = true;
        }
    }

    foreach ($files as $path) {
        if (is_readable($path)) {
            require_once $path;
        }
    }

    $class = $out['class'];
    $out['class_exists'] = class_exists($class);
    if (!$out['class_exists']) {
        $out['error'] = 'adapter class does not exist';
        $out['safe_for_contract_test'] = false;
        return $out;
    }

    try {
        $adapter = new $class();
        $out['instantiated'] = true;
        if (method_exists($adapter, 'name')) {
            $out['name'] = (string)$adapter->name();
        }
        if (method_exists($adapter, 'isLiveCapable')) {
            $out['is_live_capable'] = (bool)$adapter->isLiveCapable();
        }
        if ($out['is_live_capable']) {
            $out['safe_for_contract_test'] = false;
            $out['reason'] = 'adapter reports live capability; submit intentionally not called';
        }
        if ($out['network_aware_tokens_detected']) {
            $out['safe_for_contract_test'] = false;
            $out['reason'] = trim($out['reason'] . '; network-aware tokens detected in edxeix_live adapter', '; ');
        }
    } catch (Throwable $e) {
        $out['error'] = $e->getMessage();
        $out['safe_for_contract_test'] = false;
    }

    return $out;
}

/** @return array<string,mixed> */
function v3ct_run(?int $queueId = null): array
{
    $report = [
        'ok' => false,
        'contract_safe' => false,
        'version' => V3CT_VERSION,
        'mode' => V3CT_MODE,
        'started_at' => gmdate('c'),
        'safety' => V3CT_SAFETY,
        'app_root' => v3ct_app_root(),
        'config_files' => [],
        'final_blocks' => [],
    ];

    try {
        $db = v3ct_connect($report);
        if (!v3ct_table_exists($db, 'pre_ride_email_v3_queue')) {
            $report['final_blocks'][] = 'queue table missing';
            $report['finished_at'] = gmdate('c');
            return $report;
        }

        $row = v3ct_select_row($db, $queueId);
        if ($row === null) {
            $report['final_blocks'][] = 'no V3 queue row selected';
            $report['finished_at'] = gmdate('c');
            return $report;
        }

        $row['id'] = (int)$row['id'];
        $row['minutes_until_now'] = isset($row['minutes_until_now']) ? (int)$row['minutes_until_now'] : null;
        $report['selected_queue_row'] = $row;

        $gateConfig = v3ct_load_gate_config();
        $gate = is_array($gateConfig['safe'] ?? null) ? $gateConfig['safe'] : [];
        $report['gate_config'] = $gateConfig;
        if (empty($gateConfig['loaded']) || empty($gateConfig['returned_array'])) {
            $report['final_blocks'][] = 'gate_config: ' . ((string)($gateConfig['error'] ?? 'not loaded') ?: 'not loaded');
        }

        $payload = v3ct_normalize_payload(v3ct_build_edxeix_payload($row));
        $missing = v3ct_missing_required($payload);
        $payloadHash = v3ct_json_hash($payload);
        $report['payload'] = [
            'fields' => $payload,
            'complete' => $missing === [],
            'missing' => $missing,
            'hash_sha256' => $payloadHash,
        ];
        if ($missing !== []) {
            $report['final_blocks'][] = 'payload: missing required fields: ' . implode(', ', $missing);
        }

        $requiredStatus = (string)($gate['required_queue_status'] ?? 'live_submit_ready');
        if ((string)($row['queue_status'] ?? '') !== $requiredStatus) {
            $report['final_blocks'][] = 'queue_status: selected row is not ' . $requiredStatus;
        }

        $minFutureMinutes = (int)($gate['min_future_minutes'] ?? 1);
        if ($row['minutes_until_now'] !== null && (int)$row['minutes_until_now'] < $minFutureMinutes) {
            $report['final_blocks'][] = 'time: selected row is not sufficiently in the future';
        }

        $report['starting_point'] = v3ct_starting_point_check($db, $payload['lessor'], $payload['starting_point_id']);
        if (empty($report['starting_point']['ok'])) {
            $report['final_blocks'][] = 'starting_point: not operator verified';
        }

        $report['approval'] = v3ct_approval_check($db, (int)$row['id']);
        if (!empty($gate['operator_approval_required']) && empty($report['approval']['ok'])) {
            $report['final_blocks'][] = 'approval: ' . (string)($report['approval']['reason'] ?? 'not valid');
        }

        $report['request_contract'] = v3ct_build_request_contract($payload, $row, $gate);
        $adapterProbe = v3ct_adapter_probe();
        $report['adapter_probe'] = $adapterProbe;
        if (empty($adapterProbe['safe_for_contract_test'])) {
            $report['final_blocks'][] = 'adapter_probe: ' . ((string)($adapterProbe['reason'] ?? '') ?: (string)($adapterProbe['error'] ?? 'not safe'));
        }
        if (!empty($adapterProbe['submit_called']) || !empty($adapterProbe['submitted'])) {
            $report['final_blocks'][] = 'adapter_probe: submit/submitted flags must remain false';
        }

        $report['gate_closed_expected'] = empty($gate['enabled'])
            && in_array((string)($gate['mode'] ?? 'missing'), ['disabled', 'missing', ''], true)
            && in_array((string)($gate['adapter'] ?? 'missing'), ['disabled', 'missing', ''], true)
            && empty($gate['hard_enable_live_submit']);

        $report['contract_safe'] = !empty($report['request_contract']['network_allowed']) === false
            && empty($report['request_contract']['adapter_submit_allowed'])
            && empty($report['request_contract']['adapter_submit_called'])
            && empty($report['request_contract']['edxeix_call_made'])
            && empty($adapterProbe['is_live_capable'])
            && empty($adapterProbe['submit_called'])
            && empty($adapterProbe['submitted'])
            && !empty($adapterProbe['safe_for_contract_test']);

        $report['ok'] = !empty($report['contract_safe']) && empty($report['final_blocks']);
        $report['finished_at'] = gmdate('c');
        return $report;
    } catch (Throwable $e) {
        $report['final_blocks'][] = 'system: ' . $e->getMessage();
        $report['finished_at'] = gmdate('c');
        return $report;
    }
}

function v3ct_print_text(array $report): void
{
    $row = is_array($report['selected_queue_row'] ?? null) ? $report['selected_queue_row'] : [];
    $payload = is_array($report['payload'] ?? null) ? $report['payload'] : [];
    $contract = is_array($report['request_contract'] ?? null) ? $report['request_contract'] : [];
    $adapter = is_array($report['adapter_probe'] ?? null) ? $report['adapter_probe'] : [];
    $starting = is_array($report['starting_point'] ?? null) ? $report['starting_point'] : [];
    $approval = is_array($report['approval'] ?? null) ? $report['approval'] : [];
    $gateConfig = is_array($report['gate_config'] ?? null) ? $report['gate_config'] : [];
    $gate = is_array($gateConfig['safe'] ?? null) ? $gateConfig['safe'] : [];

    echo 'V3 live adapter contract test ' . V3CT_VERSION . PHP_EOL;
    echo 'Mode: ' . V3CT_MODE . PHP_EOL;
    echo 'Safety: ' . V3CT_SAFETY . PHP_EOL;
    echo 'OK: ' . (!empty($report['ok']) ? 'yes' : 'no') . PHP_EOL;
    echo 'Contract safe: ' . (!empty($report['contract_safe']) ? 'yes' : 'no') . PHP_EOL;
    echo 'Gate closed expected: ' . (!empty($report['gate_closed_expected']) ? 'yes' : 'no') . PHP_EOL;

    if ($row) {
        echo 'Selected row: #' . (string)($row['id'] ?? '') . ' status=' . (string)($row['queue_status'] ?? '') . ' pickup=' . (string)($row['pickup_datetime'] ?? '') . ' minutes_until=' . (string)($row['minutes_until_now'] ?? '') . PHP_EOL;
        echo 'Transfer: ' . (string)($row['customer_name'] ?? '') . ' | ' . (string)($row['driver_name'] ?? '') . ' | ' . (string)($row['vehicle_plate'] ?? '') . PHP_EOL;
    } else {
        echo 'Selected row: none' . PHP_EOL;
    }

    echo 'Payload: complete=' . (!empty($payload['complete']) ? 'yes' : 'no') . ' hash=' . (string)($payload['hash_sha256'] ?? '') . PHP_EOL;
    echo 'Gate: enabled=' . v3ct_yesno($gate['enabled'] ?? false)
        . ' mode=' . (string)($gate['mode'] ?? '-')
        . ' adapter=' . (string)($gate['adapter'] ?? '-')
        . ' hard=' . v3ct_yesno($gate['hard_enable_live_submit'] ?? false)
        . ' ack_phrase=' . v3ct_yesno($gate['acknowledgement_phrase_present'] ?? false) . PHP_EOL;
    echo 'Starting point: ' . (!empty($starting['ok']) ? 'verified' : 'not verified') . ' — ' . (string)($starting['reason'] ?? '') . PHP_EOL;
    echo 'Approval: ' . (!empty($approval['ok']) ? 'valid' : 'not valid') . ' — ' . (string)($approval['reason'] ?? '') . PHP_EOL;
    echo 'Request: method=' . (string)($contract['method'] ?? '') . ' endpoint_label=' . (string)($contract['endpoint_label'] ?? '') . ' network_allowed=' . v3ct_yesno($contract['network_allowed'] ?? false) . PHP_EOL;
    echo 'Adapter: class_exists=' . v3ct_yesno($adapter['class_exists'] ?? false)
        . ' instantiated=' . v3ct_yesno($adapter['instantiated'] ?? false)
        . ' name=' . (string)($adapter['name'] ?? '')
        . ' live_capable=' . v3ct_yesno($adapter['is_live_capable'] ?? false)
        . ' submit_called=' . v3ct_yesno($adapter['submit_called'] ?? false) . PHP_EOL;

    $blocks = is_array($report['final_blocks'] ?? null) ? $report['final_blocks'] : [];
    if ($blocks) {
        echo 'Final blocks:' . PHP_EOL;
        foreach ($blocks as $block) {
            echo '  - ' . (string)$block . PHP_EOL;
        }
    }
}

function v3ct_main(array $argv): int
{
    $args = v3ct_cli_args($argv);
    $queueId = isset($args['queue-id']) ? (int)$args['queue-id'] : null;
    $report = v3ct_run($queueId);

    if (isset($args['json'])) {
        echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    } else {
        v3ct_print_text($report);
    }

    return !empty($report['ok']) ? 0 : 1;
}

if (PHP_SAPI === 'cli' && realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === realpath(__FILE__)) {
    exit(v3ct_main($argv));
}
