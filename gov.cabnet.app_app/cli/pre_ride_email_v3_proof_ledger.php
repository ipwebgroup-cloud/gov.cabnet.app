<?php

declare(strict_types=1);

/**
 * V3 proof ledger CLI.
 *
 * Read-only evidence index for V3 pre-live automation artifacts.
 * Safety: no Bolt call, no EDXEIX call, no AADE call, no DB writes,
 * no queue status changes, no production submission tables, V0 untouched.
 */

const V3_PROOF_LEDGER_VERSION = 'v3.0.73-v3-proof-ledger';
const V3_PROOF_LEDGER_SAFETY = 'No Bolt call. No EDXEIX call. No AADE call. No DB writes. No queue status changes. No production submission tables. V0 untouched.';

function v3pl_arg_value(string $name, ?string $default = null): ?string
{
    global $argv;
    foreach ($argv as $arg) {
        if ($arg === $name) {
            return '1';
        }
        if (str_starts_with($arg, $name . '=')) {
            return substr($arg, strlen($name) + 1);
        }
    }
    return $default;
}

function v3pl_flag(string $name): bool
{
    return v3pl_arg_value($name) !== null;
}

function v3pl_limit(): int
{
    $raw = v3pl_arg_value('--limit', '20');
    $limit = is_numeric($raw) ? (int)$raw : 20;
    if ($limit < 1) {
        return 1;
    }
    if ($limit > 100) {
        return 100;
    }
    return $limit;
}

/** @return array<string,mixed> */
function v3pl_safe_file(string $path): array
{
    return [
        'path' => $path,
        'exists' => is_file($path),
        'readable' => is_readable($path),
        'size' => is_file($path) ? (int)filesize($path) : 0,
        'modified_at' => is_file($path) ? date('Y-m-d H:i:s', (int)filemtime($path)) : null,
    ];
}

/** @return array<string,mixed>|null */
function v3pl_json_file(string $path): ?array
{
    if (!is_file($path) || !is_readable($path)) {
        return null;
    }
    $raw = file_get_contents($path);
    if (!is_string($raw) || trim($raw) === '') {
        return null;
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : null;
}

/** @return list<string> */
function v3pl_glob(string $pattern): array
{
    $files = glob($pattern);
    if (!is_array($files)) {
        return [];
    }
    usort($files, static fn(string $a, string $b): int => (int)filemtime($b) <=> (int)filemtime($a));
    return array_values($files);
}

/** @return array<string,mixed> */
function v3pl_extract_bundle_summary(string $jsonPath): array
{
    $json = v3pl_json_file($jsonPath) ?? [];
    $base = basename($jsonPath);
    $txtPath = preg_replace('/_summary\.json$/', '_summary.txt', $jsonPath) ?: '';

    $summary = is_array($json['summary'] ?? null) ? $json['summary'] : [];
    $commands = is_array($json['commands'] ?? null) ? $json['commands'] : [];
    $finalBlocks = is_array($json['final_blocks_observed'] ?? null)
        ? $json['final_blocks_observed']
        : (is_array($json['final_blocks'] ?? null) ? $json['final_blocks'] : []);

    $safe = $json['bundle_safe'] ?? $summary['bundle_safe'] ?? null;
    $ok = $json['ok'] ?? null;

    return [
        'file' => $base,
        'path' => $jsonPath,
        'txt_path' => is_file($txtPath) ? $txtPath : null,
        'modified_at' => date('Y-m-d H:i:s', (int)filemtime($jsonPath)),
        'size' => (int)filesize($jsonPath),
        'json_ok' => $json !== [],
        'ok' => $ok,
        'bundle_safe' => $safe,
        'storage_ok' => $summary['storage_ok'] ?? null,
        'payload_consistency_ok' => $summary['payload_consistency_ok'] ?? null,
        'db_vs_artifact_match' => $summary['db_vs_artifact_match'] ?? null,
        'adapter_hash_match' => $summary['adapter_hash_match'] ?? null,
        'adapter_live_capable' => $summary['adapter_live_capable'] ?? null,
        'adapter_submitted' => $summary['adapter_submitted'] ?? null,
        'edxeix_call_made' => $summary['edxeix_call_made'] ?? null,
        'aade_call_made' => $summary['aade_call_made'] ?? null,
        'db_write_made' => $summary['db_write_made'] ?? null,
        'v0_touched' => $summary['v0_touched'] ?? null,
        'command_count' => count($commands),
        'final_blocks_count' => count($finalBlocks),
        'final_blocks' => array_values(array_map('strval', $finalBlocks)),
    ];
}

/** @return array<string,mixed> */
function v3pl_extract_live_package(string $fieldsPath): array
{
    $payload = v3pl_json_file($fieldsPath) ?? [];
    $base = basename($fieldsPath);
    $queueId = '';
    $stamp = '';
    if (preg_match('/^queue_(\d+)_(\d{8}_\d{6})_edxeix_fields\.json$/', $base, $m)) {
        $queueId = $m[1];
        $stamp = $m[2];
    }

    $prefix = preg_replace('/_edxeix_fields\.json$/', '', $fieldsPath) ?: $fieldsPath;
    $safetyJsonPath = $prefix . '_safety_report.json';
    $safetyTxtPath = $prefix . '_safety_report.txt';
    $safety = v3pl_json_file($safetyJsonPath) ?? [];

    return [
        'file' => $base,
        'path' => $fieldsPath,
        'queue_id' => $queueId,
        'stamp' => $stamp,
        'modified_at' => date('Y-m-d H:i:s', (int)filemtime($fieldsPath)),
        'size' => (int)filesize($fieldsPath),
        'json_ok' => $payload !== [],
        'hash_sha256' => hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: ''),
        'lessor' => (string)($payload['lessor'] ?? ''),
        'driver' => (string)($payload['driver'] ?? ''),
        'vehicle' => (string)($payload['vehicle'] ?? ''),
        'starting_point_id' => (string)($payload['starting_point_id'] ?? ''),
        'lessee_name' => (string)($payload['lessee_name'] ?? ''),
        'started_at' => (string)($payload['started_at'] ?? ''),
        'ended_at' => (string)($payload['ended_at'] ?? ''),
        'price' => (string)($payload['price'] ?? ''),
        'safety_report_json' => is_file($safetyJsonPath) ? $safetyJsonPath : null,
        'safety_report_txt' => is_file($safetyTxtPath) ? $safetyTxtPath : null,
        'eligible_for_live_submit_now' => $safety['eligible_for_live_submit_now'] ?? null,
        'current_live_submit_ready' => $safety['current_live_submit_ready'] ?? null,
        'missing_required_fields' => is_array($safety['missing_required_fields'] ?? null) ? $safety['missing_required_fields'] : [],
    ];
}

/** @return array<string,mixed> */
function v3pl_load_config(string $configRoot): array
{
    $candidates = [
        $configRoot . '/config.php',
        $configRoot . '/database.php',
    ];

    foreach ($candidates as $path) {
        if (!is_file($path) || !is_readable($path)) {
            continue;
        }
        $value = include $path;
        if (!is_array($value)) {
            continue;
        }
        return ['path' => $path, 'config' => $value, 'loaded' => true, 'error' => ''];
    }

    return ['path' => '', 'config' => [], 'loaded' => false, 'error' => 'no readable config array found'];
}

/** @param array<string,mixed> $config */
function v3pl_config_value(array $config, array $keys, string $default = ''): string
{
    foreach ($keys as $key) {
        $parts = explode('.', $key);
        $cur = $config;
        $ok = true;
        foreach ($parts as $part) {
            if (!is_array($cur) || !array_key_exists($part, $cur)) {
                $ok = false;
                break;
            }
            $cur = $cur[$part];
        }
        if ($ok && is_scalar($cur) && (string)$cur !== '') {
            return (string)$cur;
        }
    }
    return $default;
}

/** @return array<string,mixed> */
function v3pl_db_metrics(string $configRoot, int $limit): array
{
    $out = [
        'available' => false,
        'connected' => false,
        'config_loaded' => false,
        'config_path' => '',
        'error' => '',
        'queue_table_exists' => false,
        'approval_table_exists' => false,
        'queue_counts' => [],
        'approval_counts' => [],
        'latest_rows' => [],
    ];

    if (!class_exists('mysqli')) {
        $out['error'] = 'mysqli extension not available';
        return $out;
    }

    $loaded = v3pl_load_config($configRoot);
    $out['config_loaded'] = (bool)$loaded['loaded'];
    $out['config_path'] = (string)$loaded['path'];
    if (empty($loaded['loaded']) || !is_array($loaded['config'])) {
        $out['error'] = (string)$loaded['error'];
        return $out;
    }

    $config = $loaded['config'];
    $host = v3pl_config_value($config, ['db.host', 'database.host', 'DB_HOST'], 'localhost');
    $name = v3pl_config_value($config, ['db.name', 'db.database', 'database.name', 'database.database', 'DB_NAME', 'DB_DATABASE']);
    $user = v3pl_config_value($config, ['db.user', 'db.username', 'database.user', 'database.username', 'DB_USER', 'DB_USERNAME']);
    $pass = v3pl_config_value($config, ['db.pass', 'db.password', 'database.pass', 'database.password', 'DB_PASS', 'DB_PASSWORD']);

    if ($name === '' || $user === '') {
        $out['error'] = 'database config missing db name or user';
        return $out;
    }

    mysqli_report(MYSQLI_REPORT_OFF);
    $db = @new mysqli($host, $user, $pass, $name);
    if ($db->connect_errno) {
        $out['error'] = 'database connection failed: ' . $db->connect_error;
        return $out;
    }
    $db->set_charset('utf8mb4');
    $out['available'] = true;
    $out['connected'] = true;
    $out['database'] = ['host' => $host, 'name' => $name];

    $tableExists = static function (mysqli $db, string $table): bool {
        $safe = $db->real_escape_string($table);
        $res = $db->query("SHOW TABLES LIKE '" . $safe . "'");
        return $res instanceof mysqli_result && $res->num_rows > 0;
    };

    $out['queue_table_exists'] = $tableExists($db, 'pre_ride_email_v3_queue');
    $out['approval_table_exists'] = $tableExists($db, 'pre_ride_email_v3_live_submit_approvals');

    if ($out['queue_table_exists']) {
        $sql = "SELECT
                    COUNT(*) AS total,
                    SUM(queue_status NOT IN ('blocked','submitted','failed','cancelled','expired')) AS active,
                    SUM(queue_status = 'live_submit_ready') AS live_submit_ready,
                    SUM(queue_status = 'submit_dry_run_ready') AS submit_dry_run_ready,
                    SUM(queue_status = 'blocked') AS blocked,
                    SUM(queue_status = 'submitted') AS submitted
                FROM pre_ride_email_v3_queue";
        $res = $db->query($sql);
        if ($res instanceof mysqli_result) {
            $row = $res->fetch_assoc() ?: [];
            $out['queue_counts'] = $row;
        }

        $safeLimit = max(1, min(100, $limit));
        $res = $db->query("SELECT id, queue_status, customer_name, pickup_datetime, driver_name, vehicle_plate, lessor_id, driver_id, vehicle_id, starting_point_id, last_error, created_at, updated_at FROM pre_ride_email_v3_queue ORDER BY id DESC LIMIT " . $safeLimit);
        if ($res instanceof mysqli_result) {
            $rows = [];
            while ($row = $res->fetch_assoc()) {
                $rows[] = $row;
            }
            $out['latest_rows'] = $rows;
        }
    }

    if ($out['approval_table_exists']) {
        $res = $db->query("SELECT COUNT(*) AS total, SUM(approval_status = 'approved') AS approved, SUM(revoked_at IS NOT NULL) AS revoked, SUM(expires_at IS NOT NULL AND expires_at < NOW()) AS expired FROM pre_ride_email_v3_live_submit_approvals");
        if ($res instanceof mysqli_result) {
            $out['approval_counts'] = $res->fetch_assoc() ?: [];
        }
    }

    $db->close();
    return $out;
}

/** @return array<string,mixed> */
function v3pl_build_report(int $limit): array
{
    $appRoot = dirname(__DIR__);
    $configRoot = getenv('GOVCABNET_CONFIG_ROOT') ?: '/home/cabnet/gov.cabnet.app_config';
    $artifactRoot = $appRoot . '/storage/artifacts';
    $proofBundleDir = $artifactRoot . '/v3_pre_live_proof_bundles';
    $livePackageDir = $artifactRoot . '/v3_live_submit_packages';

    $bundleFiles = array_slice(v3pl_glob($proofBundleDir . '/*_summary.json'), 0, $limit);
    $packageFiles = array_slice(v3pl_glob($livePackageDir . '/queue_*_*_edxeix_fields.json'), 0, $limit);

    $bundles = [];
    foreach ($bundleFiles as $file) {
        $bundles[] = v3pl_extract_bundle_summary($file);
    }

    $packages = [];
    foreach ($packageFiles as $file) {
        $packages[] = v3pl_extract_live_package($file);
    }

    $latestBundle = $bundles[0] ?? null;
    $summary = [
        'proof_bundle_dir_exists' => is_dir($proofBundleDir),
        'proof_bundle_dir_readable' => is_readable($proofBundleDir),
        'proof_bundle_count' => count(v3pl_glob($proofBundleDir . '/*_summary.json')),
        'live_package_dir_exists' => is_dir($livePackageDir),
        'live_package_dir_readable' => is_readable($livePackageDir),
        'live_package_edxeix_fields_count' => count(v3pl_glob($livePackageDir . '/queue_*_*_edxeix_fields.json')),
        'latest_bundle_safe' => is_array($latestBundle) ? ($latestBundle['bundle_safe'] ?? null) : null,
        'latest_bundle_ok' => is_array($latestBundle) ? ($latestBundle['ok'] ?? null) : null,
        'latest_edxeix_call_made' => is_array($latestBundle) ? ($latestBundle['edxeix_call_made'] ?? null) : null,
        'latest_aade_call_made' => is_array($latestBundle) ? ($latestBundle['aade_call_made'] ?? null) : null,
        'latest_db_write_made' => is_array($latestBundle) ? ($latestBundle['db_write_made'] ?? null) : null,
        'latest_v0_touched' => is_array($latestBundle) ? ($latestBundle['v0_touched'] ?? null) : null,
    ];

    $ok = (bool)$summary['proof_bundle_dir_exists'] && (bool)$summary['proof_bundle_dir_readable'];

    return [
        'ok' => $ok,
        'version' => V3_PROOF_LEDGER_VERSION,
        'mode' => 'read_only_proof_ledger',
        'started_at' => date('c'),
        'safety' => V3_PROOF_LEDGER_SAFETY,
        'app_root' => $appRoot,
        'config_root' => $configRoot,
        'artifact_root' => $artifactRoot,
        'proof_bundle_dir' => $proofBundleDir,
        'live_package_dir' => $livePackageDir,
        'summary' => $summary,
        'db' => v3pl_db_metrics($configRoot, min(10, $limit)),
        'proof_bundles' => $bundles,
        'live_packages' => $packages,
        'finished_at' => date('c'),
    ];
}

function v3pl_yesno(mixed $value): string
{
    if ($value === true || $value === 1 || $value === '1' || $value === 'yes') {
        return 'yes';
    }
    if ($value === false || $value === 0 || $value === '0' || $value === 'no') {
        return 'no';
    }
    if ($value === null || $value === '') {
        return '-';
    }
    return (string)$value;
}

$limit = v3pl_limit();
$report = v3pl_build_report($limit);

if (v3pl_flag('--json')) {
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(!empty($report['ok']) ? 0 : 1);
}

echo 'V3 proof ledger ' . V3_PROOF_LEDGER_VERSION . PHP_EOL;
echo 'Mode: read_only_proof_ledger' . PHP_EOL;
echo 'Safety: ' . V3_PROOF_LEDGER_SAFETY . PHP_EOL;
echo 'OK: ' . (!empty($report['ok']) ? 'yes' : 'no') . PHP_EOL;
echo 'Artifact root: ' . (string)$report['artifact_root'] . PHP_EOL;
echo PHP_EOL;

$summary = $report['summary'];
echo 'Summary:' . PHP_EOL;
echo '  proof_bundle_dir: exists=' . v3pl_yesno($summary['proof_bundle_dir_exists'] ?? null) . ' readable=' . v3pl_yesno($summary['proof_bundle_dir_readable'] ?? null) . ' count=' . (string)($summary['proof_bundle_count'] ?? 0) . PHP_EOL;
echo '  live_package_dir: exists=' . v3pl_yesno($summary['live_package_dir_exists'] ?? null) . ' readable=' . v3pl_yesno($summary['live_package_dir_readable'] ?? null) . ' edxeix_fields=' . (string)($summary['live_package_edxeix_fields_count'] ?? 0) . PHP_EOL;
echo '  latest_bundle_safe=' . v3pl_yesno($summary['latest_bundle_safe'] ?? null) . ' ok=' . v3pl_yesno($summary['latest_bundle_ok'] ?? null) . PHP_EOL;
echo '  latest_edxeix_call_made=' . v3pl_yesno($summary['latest_edxeix_call_made'] ?? null) . ' latest_aade_call_made=' . v3pl_yesno($summary['latest_aade_call_made'] ?? null) . ' latest_db_write_made=' . v3pl_yesno($summary['latest_db_write_made'] ?? null) . ' latest_v0_touched=' . v3pl_yesno($summary['latest_v0_touched'] ?? null) . PHP_EOL;
echo PHP_EOL;

$db = $report['db'];
echo 'Database snapshot:' . PHP_EOL;
echo '  connected=' . v3pl_yesno($db['connected'] ?? false) . ' queue_table=' . v3pl_yesno($db['queue_table_exists'] ?? false) . ' approvals_table=' . v3pl_yesno($db['approval_table_exists'] ?? false) . PHP_EOL;
if (!empty($db['queue_counts']) && is_array($db['queue_counts'])) {
    $qc = $db['queue_counts'];
    echo '  queue: total=' . (string)($qc['total'] ?? '-') . ' active=' . (string)($qc['active'] ?? '-') . ' live_ready=' . (string)($qc['live_submit_ready'] ?? '-') . ' dry_ready=' . (string)($qc['submit_dry_run_ready'] ?? '-') . ' blocked=' . (string)($qc['blocked'] ?? '-') . ' submitted=' . (string)($qc['submitted'] ?? '-') . PHP_EOL;
}
if (!empty($db['approval_counts']) && is_array($db['approval_counts'])) {
    $ac = $db['approval_counts'];
    echo '  approvals: total=' . (string)($ac['total'] ?? '-') . ' approved=' . (string)($ac['approved'] ?? '-') . ' revoked=' . (string)($ac['revoked'] ?? '-') . ' expired=' . (string)($ac['expired'] ?? '-') . PHP_EOL;
}
if (!empty($db['error'])) {
    echo '  note=' . (string)$db['error'] . PHP_EOL;
}
echo PHP_EOL;

echo 'Latest proof bundles:' . PHP_EOL;
if (empty($report['proof_bundles'])) {
    echo '  none found' . PHP_EOL;
} else {
    foreach ($report['proof_bundles'] as $i => $bundle) {
        echo '  #' . ($i + 1) . ' ' . (string)$bundle['file'] . ' modified=' . (string)$bundle['modified_at'] . ' safe=' . v3pl_yesno($bundle['bundle_safe'] ?? null) . ' ok=' . v3pl_yesno($bundle['ok'] ?? null) . ' edxeix=' . v3pl_yesno($bundle['edxeix_call_made'] ?? null) . ' aade=' . v3pl_yesno($bundle['aade_call_made'] ?? null) . ' db_write=' . v3pl_yesno($bundle['db_write_made'] ?? null) . ' v0=' . v3pl_yesno($bundle['v0_touched'] ?? null) . PHP_EOL;
    }
}
echo PHP_EOL;

echo 'Latest live package field exports:' . PHP_EOL;
if (empty($report['live_packages'])) {
    echo '  none found' . PHP_EOL;
} else {
    foreach ($report['live_packages'] as $i => $pkg) {
        echo '  #' . ($i + 1) . ' queue=' . (string)$pkg['queue_id'] . ' file=' . (string)$pkg['file'] . ' hash=' . substr((string)$pkg['hash_sha256'], 0, 12) . ' lessor=' . (string)$pkg['lessor'] . ' driver=' . (string)$pkg['driver'] . ' vehicle=' . (string)$pkg['vehicle'] . ' start=' . (string)$pkg['starting_point_id'] . PHP_EOL;
    }
}

exit(!empty($report['ok']) ? 0 : 1);
