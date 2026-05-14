<?php

declare(strict_types=1);

const V3PC_VERSION = 'v3.0.69-v3-adapter-payload-consistency-harness';
const V3PC_MODE = 'read_only_adapter_payload_consistency_harness';
const V3PC_SAFETY = 'No Bolt call. No EDXEIX call. No AADE call. No DB writes. No queue status changes. No production submission tables. V0 untouched.';

/** @return array<string,string> */
function v3pc_cli_args(array $argv): array
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

function v3pc_app_root(): string
{
    return dirname(__DIR__);
}

function v3pc_config_root(): string
{
    return dirname(v3pc_app_root()) . '/gov.cabnet.app_config';
}

/** @param array<string,mixed> $event */
function v3pc_event(array &$report, string $level, string $message): void
{
    $report['events'][] = ['level' => $level, 'message' => $message];
}

/** @return array<int,array<string,mixed>> */
function v3pc_load_config_candidates(array &$report): array
{
    $appRoot = v3pc_app_root();
    $configRoot = v3pc_config_root();
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
function v3pc_extract_db_config(array $config): ?array
{
    $candidates = [];
    $candidates[] = $config;
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

function v3pc_env(string $key): string
{
    $v = getenv($key);
    return $v === false ? '' : (string)$v;
}

function v3pc_connect(array &$report): mysqli
{
    $configs = v3pc_load_config_candidates($report);
    $dbConfig = null;
    foreach ($configs as $cfg) {
        $dbConfig = v3pc_extract_db_config($cfg);
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
        $host = v3pc_env('GOV_DB_HOST') ?: v3pc_env('DB_HOST') ?: 'localhost';
        $user = v3pc_env('GOV_DB_USER') ?: v3pc_env('DB_USER');
        $pass = v3pc_env('GOV_DB_PASS') ?: v3pc_env('DB_PASS');
        $name = v3pc_env('GOV_DB_NAME') ?: v3pc_env('DB_NAME');
        $port = (int)(v3pc_env('GOV_DB_PORT') ?: v3pc_env('DB_PORT') ?: 3306);
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

function v3pc_table_exists(mysqli $db, string $table): bool
{
    $stmt = $db->prepare('SELECT COUNT(*) AS c FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1');
    $stmt->bind_param('s', $table);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc() ?: [];
    return (int)($row['c'] ?? 0) > 0;
}

/** @return array<string,mixed>|null */
function v3pc_select_row(mysqli $db, ?int $queueId): ?array
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

function v3pc_money($value): string
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
function v3pc_build_edxeix_payload(array $row): array
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
        'price' => v3pc_money($row['price_amount'] ?? ''),
        'price_text' => trim((string)($row['price_text'] ?? '')),
    ];
}

/** @param array<string,mixed> $payload @return array<string,string> */
function v3pc_normalize_payload(array $payload): array
{
    $keys = ['lessor', 'driver', 'vehicle', 'starting_point_id', 'lessee_name', 'lessee_phone', 'boarding_point', 'disembark_point', 'started_at', 'ended_at', 'price', 'price_text'];
    $out = [];
    foreach ($keys as $key) {
        $value = $payload[$key] ?? '';
        if ($key === 'price') {
            $out[$key] = v3pc_money($value);
        } else {
            $out[$key] = trim((string)$value);
        }
    }
    return $out;
}

/** @param array<string,string> $payload @return array<int,string> */
function v3pc_missing_required(array $payload): array
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
function v3pc_json_hash(array $data): string
{
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return hash('sha256', $json === false ? '' : $json);
}

/** @return array<string,mixed> */
function v3pc_latest_export_artifact(int $queueId): array
{
    $dir = v3pc_app_root() . '/storage/artifacts/v3_live_submit_packages';
    $out = [
        'artifact_dir' => $dir,
        'artifact_dir_exists' => is_dir($dir),
        'artifact_dir_readable' => is_readable($dir),
        'artifact_count_for_row' => 0,
        'edxeix_fields_file' => '',
        'edxeix_fields_found' => false,
        'edxeix_fields_readable' => false,
        'latest_files' => [],
        'payload' => null,
        'error' => '',
    ];
    if (!is_dir($dir)) {
        return $out;
    }
    $files = glob($dir . '/queue_' . $queueId . '_*') ?: [];
    rsort($files, SORT_STRING);
    $out['artifact_count_for_row'] = count($files);
    $out['latest_files'] = array_slice(array_map('basename', $files), 0, 8);

    $fieldFiles = glob($dir . '/queue_' . $queueId . '_*_edxeix_fields.json') ?: [];
    rsort($fieldFiles, SORT_STRING);
    if (!$fieldFiles) {
        return $out;
    }
    $file = $fieldFiles[0];
    $out['edxeix_fields_file'] = $file;
    $out['edxeix_fields_found'] = true;
    $out['edxeix_fields_readable'] = is_readable($file);
    if (!is_readable($file)) {
        $out['error'] = 'latest edxeix fields artifact is not readable';
        return $out;
    }
    $raw = file_get_contents($file);
    $decoded = json_decode($raw === false ? '' : $raw, true);
    if (!is_array($decoded)) {
        $out['error'] = 'latest edxeix fields artifact is not valid JSON';
        return $out;
    }
    $out['payload'] = v3pc_normalize_payload($decoded);
    return $out;
}

/** @return array<string,mixed> */
function v3pc_starting_point_check(mysqli $db, string $lessorId, string $startId): array
{
    $out = ['ok' => false, 'label' => '', 'reason' => 'not checked'];
    if ($lessorId === '' || $startId === '') {
        $out['reason'] = 'lessor or starting point missing';
        return $out;
    }
    if (!v3pc_table_exists($db, 'pre_ride_email_v3_starting_point_options')) {
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
function v3pc_adapter_probe(array $edxeixPayload, array $row): array
{
    $appRoot = v3pc_app_root();
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
        'blocked' => false,
        'ok' => false,
        'payload_sha256' => '',
        'expected_payload_sha256' => v3pc_json_hash($edxeixPayload),
        'hash_matches_expected' => false,
        'safe_for_consistency_harness' => true,
        'reason' => '',
        'message' => '',
        'error' => '',
    ];
    foreach ($files as $key => $path) {
        $out['files'][$key] = ['path' => $path, 'exists' => is_file($path), 'readable' => is_readable($path)];
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
            $out['safe_for_consistency_harness'] = false;
            $out['reason'] = 'adapter_is_live_capable_submit_skipped';
            $out['message'] = 'Adapter reports live capability; harness intentionally did not call submit.';
            return $out;
        }
        if (!method_exists($adapter, 'submit')) {
            $out['error'] = 'adapter submit method missing';
            return $out;
        }
        $context = [
            'queue_id' => (string)($row['id'] ?? ''),
            'dedupe_key' => (string)($row['dedupe_key'] ?? ''),
            'lessor_id' => (string)($row['lessor_id'] ?? ''),
            'vehicle_plate' => (string)($row['vehicle_plate'] ?? ''),
            'harness' => V3PC_VERSION,
        ];
        $out['submit_called'] = true;
        $result = $adapter->submit($edxeixPayload, $context);
        if (is_array($result)) {
            $out['submit_returned'] = true;
            $out['submitted'] = !empty($result['submitted']);
            $out['blocked'] = !empty($result['blocked']);
            $out['ok'] = !empty($result['ok']);
            $out['payload_sha256'] = (string)($result['payload_sha256'] ?? '');
            $out['hash_matches_expected'] = $out['payload_sha256'] !== '' && hash_equals($out['expected_payload_sha256'], $out['payload_sha256']);
            $out['reason'] = (string)($result['reason'] ?? '');
            $out['message'] = (string)($result['message'] ?? '');
        } else {
            $out['error'] = 'adapter submit did not return an array';
        }
    } catch (Throwable $e) {
        $out['error'] = $e->getMessage();
    }

    return $out;
}

/** @param array<string,string> $a @param array<string,string> $b @return array<int,string> */
function v3pc_payload_differences(array $a, array $b): array
{
    $diffs = [];
    foreach ($a as $key => $value) {
        if (!array_key_exists($key, $b)) {
            $diffs[] = $key . ': missing in artifact';
            continue;
        }
        if ((string)$value !== (string)$b[$key]) {
            $diffs[] = $key . ': db=' . (string)$value . ' artifact=' . (string)$b[$key];
        }
    }
    foreach ($b as $key => $_value) {
        if (!array_key_exists($key, $a)) {
            $diffs[] = $key . ': extra in artifact';
        }
    }
    return $diffs;
}

/** @return array<string,mixed> */
function v3pc_run(?int $queueId = null): array
{
    $report = [
        'ok' => false,
        'simulation_safe' => false,
        'version' => V3PC_VERSION,
        'mode' => V3PC_MODE,
        'started_at' => gmdate('c'),
        'safety' => V3PC_SAFETY,
        'app_root' => v3pc_app_root(),
        'events' => [],
        'config_files' => [],
        'final_blocks' => [],
    ];

    try {
        $db = v3pc_connect($report);
        if (!v3pc_table_exists($db, 'pre_ride_email_v3_queue')) {
            $report['final_blocks'][] = 'queue table missing';
            $report['finished_at'] = gmdate('c');
            return $report;
        }
        $row = v3pc_select_row($db, $queueId);
        if ($row === null) {
            $report['final_blocks'][] = 'no V3 queue row selected';
            $report['finished_at'] = gmdate('c');
            return $report;
        }
        $row['id'] = (int)$row['id'];
        $row['minutes_until_now'] = isset($row['minutes_until_now']) ? (int)$row['minutes_until_now'] : null;
        $report['selected_queue_row'] = $row;

        $dbPayload = v3pc_build_edxeix_payload($row);
        $dbPayload = v3pc_normalize_payload($dbPayload);
        $missing = v3pc_missing_required($dbPayload);
        $dbHash = v3pc_json_hash($dbPayload);
        $report['db_payload'] = [
            'payload' => $dbPayload,
            'complete' => $missing === [],
            'missing' => $missing,
            'hash_sha256' => $dbHash,
        ];
        if ($missing !== []) {
            $report['final_blocks'][] = 'db_payload: missing required fields: ' . implode(', ', $missing);
        }

        $report['starting_point'] = v3pc_starting_point_check($db, $dbPayload['lessor'], $dbPayload['starting_point_id']);
        if (empty($report['starting_point']['ok'])) {
            $report['final_blocks'][] = 'starting_point: not operator verified';
        }

        $artifact = v3pc_latest_export_artifact((int)$row['id']);
        $report['artifact_payload'] = $artifact;
        $artifactHash = '';
        $artifactDiffs = [];
        if (!empty($artifact['payload']) && is_array($artifact['payload'])) {
            $artifactHash = v3pc_json_hash($artifact['payload']);
            $artifactDiffs = v3pc_payload_differences($dbPayload, $artifact['payload']);
        } else {
            $report['final_blocks'][] = 'artifact_payload: latest edxeix_fields artifact not available for selected row';
        }
        if ($artifactHash !== '' && !hash_equals($dbHash, $artifactHash)) {
            $report['final_blocks'][] = 'artifact_payload: hash does not match DB-built payload';
        }
        if ($artifactDiffs !== []) {
            $report['final_blocks'][] = 'artifact_payload: field differences detected';
        }
        $report['consistency'] = [
            'db_payload_hash' => $dbHash,
            'artifact_payload_hash' => $artifactHash,
            'db_vs_artifact_match' => $artifactHash !== '' && hash_equals($dbHash, $artifactHash) && $artifactDiffs === [],
            'db_vs_artifact_differences' => $artifactDiffs,
        ];

        $adapter = v3pc_adapter_probe($dbPayload, $row);
        $report['adapter_simulation'] = $adapter;
        if (empty($adapter['safe_for_consistency_harness'])) {
            $report['final_blocks'][] = 'adapter_simulation: adapter reports live capability, submit skipped';
        }
        if (!empty($adapter['submitted'])) {
            $report['final_blocks'][] = 'adapter_simulation: submitted=true is not allowed in this harness';
        }
        if (!empty($adapter['is_live_capable'])) {
            $report['final_blocks'][] = 'adapter_simulation: live_capable=true is not allowed in this harness';
        }
        if (!empty($adapter['submit_called']) && empty($adapter['hash_matches_expected'])) {
            $report['final_blocks'][] = 'adapter_simulation: returned payload hash does not match DB-built payload';
        }
        if (!empty($adapter['error'])) {
            $report['final_blocks'][] = 'adapter_simulation: ' . (string)$adapter['error'];
        }

        $report['simulation_safe'] = empty($adapter['is_live_capable']) && empty($adapter['submitted']) && !empty($adapter['safe_for_consistency_harness']);
        $report['ok'] = $report['simulation_safe'] && empty($report['final_blocks']);
        $report['finished_at'] = gmdate('c');
        return $report;
    } catch (Throwable $e) {
        v3pc_event($report, 'error', $e->getMessage());
        $report['final_blocks'][] = 'system: ' . $e->getMessage();
        $report['finished_at'] = gmdate('c');
        return $report;
    }
}

function v3pc_print_text(array $report): void
{
    $row = is_array($report['selected_queue_row'] ?? null) ? $report['selected_queue_row'] : [];
    $dbPayload = is_array($report['db_payload'] ?? null) ? $report['db_payload'] : [];
    $artifact = is_array($report['artifact_payload'] ?? null) ? $report['artifact_payload'] : [];
    $consistency = is_array($report['consistency'] ?? null) ? $report['consistency'] : [];
    $adapter = is_array($report['adapter_simulation'] ?? null) ? $report['adapter_simulation'] : [];
    $starting = is_array($report['starting_point'] ?? null) ? $report['starting_point'] : [];

    echo 'V3 adapter payload consistency harness ' . V3PC_VERSION . PHP_EOL;
    echo 'Mode: ' . V3PC_MODE . PHP_EOL;
    echo 'Safety: ' . V3PC_SAFETY . PHP_EOL;
    echo 'OK: ' . (!empty($report['ok']) ? 'yes' : 'no') . PHP_EOL;
    echo 'Simulation safe: ' . (!empty($report['simulation_safe']) ? 'yes' : 'no') . PHP_EOL;

    if ($row) {
        echo 'Selected row: #' . (string)($row['id'] ?? '') . ' status=' . (string)($row['queue_status'] ?? '') . ' pickup=' . (string)($row['pickup_datetime'] ?? '') . ' minutes_until=' . (string)($row['minutes_until_now'] ?? '') . PHP_EOL;
        echo 'Transfer: ' . (string)($row['customer_name'] ?? '') . ' | ' . (string)($row['driver_name'] ?? '') . ' | ' . (string)($row['vehicle_plate'] ?? '') . PHP_EOL;
        echo 'IDs: lessor=' . (string)($row['lessor_id'] ?? '') . ' driver=' . (string)($row['driver_id'] ?? '') . ' vehicle=' . (string)($row['vehicle_id'] ?? '') . ' start=' . (string)($row['starting_point_id'] ?? '') . PHP_EOL;
    } else {
        echo 'Selected row: none' . PHP_EOL;
    }

    echo 'DB payload: complete=' . (!empty($dbPayload['complete']) ? 'yes' : 'no') . ' hash=' . (string)($dbPayload['hash_sha256'] ?? '') . PHP_EOL;
    echo 'Artifact: fields_found=' . (!empty($artifact['edxeix_fields_found']) ? 'yes' : 'no') . ' count=' . (string)($artifact['artifact_count_for_row'] ?? 0) . ' hash=' . (string)($consistency['artifact_payload_hash'] ?? '') . PHP_EOL;
    echo 'Consistency: db_vs_artifact_match=' . (!empty($consistency['db_vs_artifact_match']) ? 'yes' : 'no') . PHP_EOL;
    echo 'Starting point: ' . (!empty($starting['ok']) ? 'verified' : 'not verified') . ' — ' . (string)($starting['reason'] ?? '') . PHP_EOL;
    echo 'Adapter: class_exists=' . (!empty($adapter['class_exists']) ? 'yes' : 'no') . ' instantiated=' . (!empty($adapter['instantiated']) ? 'yes' : 'no') . ' live_capable=' . (!empty($adapter['is_live_capable']) ? 'yes' : 'no') . ' submitted=' . (!empty($adapter['submitted']) ? 'yes' : 'no') . PHP_EOL;
    echo 'Adapter hash match: ' . (!empty($adapter['hash_matches_expected']) ? 'yes' : 'no') . PHP_EOL;
    if (!empty($adapter['message'])) {
        echo 'Adapter message: ' . (string)$adapter['message'] . PHP_EOL;
    }

    $diffs = is_array($consistency['db_vs_artifact_differences'] ?? null) ? $consistency['db_vs_artifact_differences'] : [];
    if ($diffs) {
        echo 'Differences:' . PHP_EOL;
        foreach ($diffs as $diff) {
            echo '  - ' . (string)$diff . PHP_EOL;
        }
    }

    $blocks = is_array($report['final_blocks'] ?? null) ? $report['final_blocks'] : [];
    if ($blocks) {
        echo 'Final blocks:' . PHP_EOL;
        foreach ($blocks as $block) {
            echo '  - ' . (string)$block . PHP_EOL;
        }
    }
}

function v3pc_main(array $argv): int
{
    $args = v3pc_cli_args($argv);
    $queueId = isset($args['queue-id']) ? (int)$args['queue-id'] : null;
    $report = v3pc_run($queueId);

    if (isset($args['json'])) {
        echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    } else {
        v3pc_print_text($report);
    }

    return !empty($report['ok']) ? 0 : 1;
}

if (PHP_SAPI === 'cli' && realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === realpath(__FILE__)) {
    exit(v3pc_main($argv));
}
