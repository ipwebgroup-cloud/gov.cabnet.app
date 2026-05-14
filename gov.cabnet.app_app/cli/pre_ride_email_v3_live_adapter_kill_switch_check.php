<?php
/**
 * V3 live adapter kill-switch check.
 *
 * Read-only pre-live switchboard for the future V3 EDXEIX live adapter path.
 * This script makes no external calls and performs no writes.
 */

declare(strict_types=1);

date_default_timezone_set('Europe/Athens');

const V3_KILL_SWITCH_VERSION = 'v3.0.61-v3-kill-switch-table-exists-fix';
const V3_APPROVAL_SCOPE = 'closed_gate_rehearsal_only';
const V3_EXPECTED_ADAPTER = 'edxeix_live';
const V3_REQUIRED_APPROVAL_PHRASE = 'I APPROVE V3 ROW FOR CLOSED-GATE REHEARSAL ONLY';

function v3ks_arg_value(array $argv, string $name, string $default = ''): string
{
    $prefix = $name . '=';
    foreach ($argv as $arg) {
        if (strpos($arg, $prefix) === 0) {
            return substr($arg, strlen($prefix));
        }
    }
    return $default;
}

function v3ks_has_arg(array $argv, string $name): bool
{
    return in_array($name, $argv, true);
}

function v3ks_table_exists(mysqli $db, string $table): bool
{
    // MariaDB on this cPanel host does not reliably accept placeholders in
    // SHOW TABLES LIKE ?. Use INFORMATION_SCHEMA with prepared parameters.
    $stmt = $db->prepare('SELECT COUNT(*) AS c FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1');
    $stmt->bind_param('s', $table);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result === false) {
        return false;
    }
    $row = $result->fetch_assoc();
    return (int)($row['c'] ?? 0) > 0;
}

function v3ks_fetch_one(mysqli $db, string $sql, array $params = []): ?array
{
    $stmt = $db->prepare($sql);
    if ($params !== []) {
        $types = '';
        foreach ($params as $param) {
            $types .= is_int($param) ? 'i' : 's';
        }
        $bind = [$types];
        foreach ($params as $index => $param) {
            $bind[] = &$params[$index];
        }
        call_user_func_array([$stmt, 'bind_param'], $bind);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result === false) {
        return null;
    }
    $row = $result->fetch_assoc();
    return is_array($row) ? $row : null;
}

function v3ks_fetch_all(mysqli $db, string $sql, array $params = []): array
{
    $stmt = $db->prepare($sql);
    if ($params !== []) {
        $types = '';
        foreach ($params as $param) {
            $types .= is_int($param) ? 'i' : 's';
        }
        $bind = [$types];
        foreach ($params as $index => $param) {
            $bind[] = &$params[$index];
        }
        call_user_func_array([$stmt, 'bind_param'], $bind);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result === false) {
        return [];
    }
    return $result->fetch_all(MYSQLI_ASSOC);
}

function v3ks_live_submit_config_path(string $appRoot): string
{
    $configRoot = dirname($appRoot) . '/gov.cabnet.app_config';
    return $configRoot . '/pre_ride_email_v3_live_submit.php';
}

function v3ks_load_live_submit_config(string $appRoot): array
{
    $path = v3ks_live_submit_config_path($appRoot);
    $out = [
        'path' => $path,
        'exists' => is_file($path),
        'readable' => is_readable($path),
        'loaded' => false,
        'error' => '',
        'raw' => [],
        'safe' => [],
    ];

    if (!$out['exists']) {
        $out['error'] = 'config file missing';
        return $out;
    }
    if (!$out['readable']) {
        $out['error'] = 'config file not readable by current user';
        return $out;
    }

    try {
        $config = require $path;
        if (!is_array($config)) {
            $out['error'] = 'config file did not return an array';
            return $out;
        }
        $out['loaded'] = true;
        $out['raw'] = $config;
        $out['safe'] = [
            'enabled' => !empty($config['enabled']),
            'mode' => trim((string)($config['mode'] ?? 'disabled')),
            'adapter' => trim((string)($config['adapter'] ?? 'disabled')),
            'hard_enable_live_submit' => !empty($config['hard_enable_live_submit']),
            'acknowledgement_phrase_present' => v3ks_ack_phrase_present($config),
        ];
        return $out;
    } catch (Throwable $e) {
        $out['error'] = $e->getMessage();
        return $out;
    }
}

function v3ks_ack_phrase_present(array $config): bool
{
    foreach (['acknowledgement_phrase', 'required_acknowledgement_phrase', 'live_submit_acknowledgement_phrase', 'ack_phrase'] as $key) {
        if (isset($config[$key]) && trim((string)$config[$key]) !== '') {
            return true;
        }
    }
    return false;
}

function v3ks_gate_blocks(array $safeConfig): array
{
    $blocks = [];
    if (empty($safeConfig['enabled'])) {
        $blocks[] = 'master_gate: enabled is false';
    }
    if (($safeConfig['mode'] ?? '') !== 'live') {
        $blocks[] = 'master_gate: mode is not live';
    }
    if (($safeConfig['adapter'] ?? '') !== V3_EXPECTED_ADAPTER) {
        $blocks[] = 'master_gate: adapter is not ' . V3_EXPECTED_ADAPTER;
    }
    if (empty($safeConfig['hard_enable_live_submit'])) {
        $blocks[] = 'master_gate: hard_enable_live_submit is false';
    }
    if (empty($safeConfig['acknowledgement_phrase_present'])) {
        $blocks[] = 'master_gate: required acknowledgement phrase is not present';
    }
    return $blocks;
}

function v3ks_pick_queue_row(mysqli $db, ?int $queueId): ?array
{
    if (!v3ks_table_exists($db, 'pre_ride_email_v3_queue')) {
        return null;
    }
    if ($queueId !== null && $queueId > 0) {
        return v3ks_fetch_one($db, 'SELECT * FROM pre_ride_email_v3_queue WHERE id = ? LIMIT 1', [$queueId]);
    }
    $row = v3ks_fetch_one($db, "SELECT * FROM pre_ride_email_v3_queue WHERE queue_status = 'live_submit_ready' ORDER BY pickup_datetime ASC, id ASC LIMIT 1");
    if (is_array($row)) {
        return $row;
    }
    return v3ks_fetch_one($db, 'SELECT * FROM pre_ride_email_v3_queue ORDER BY id DESC LIMIT 1');
}

function v3ks_minutes_until(?string $dt): ?int
{
    $dt = trim((string)$dt);
    if ($dt === '') {
        return null;
    }
    try {
        $pickup = new DateTimeImmutable($dt, new DateTimeZone('Europe/Athens'));
        $now = new DateTimeImmutable('now', new DateTimeZone('Europe/Athens'));
        return (int)floor(($pickup->getTimestamp() - $now->getTimestamp()) / 60);
    } catch (Throwable $e) {
        return null;
    }
}

function v3ks_required_field_values(array $row): array
{
    return [
        'lessor_id' => trim((string)($row['lessor_id'] ?? '')),
        'driver_id' => trim((string)($row['driver_id'] ?? '')),
        'vehicle_id' => trim((string)($row['vehicle_id'] ?? '')),
        'starting_point_id' => trim((string)($row['starting_point_id'] ?? '')),
        'customer_name' => trim((string)($row['customer_name'] ?? '')),
        'customer_phone' => trim((string)($row['customer_phone'] ?? '')),
        'pickup_datetime' => trim((string)($row['pickup_datetime'] ?? '')),
        'estimated_end_datetime' => trim((string)($row['estimated_end_datetime'] ?? '')),
        'pickup_address' => trim((string)($row['pickup_address'] ?? '')),
        'dropoff_address' => trim((string)($row['dropoff_address'] ?? '')),
        'price_amount' => trim((string)($row['price_amount'] ?? '')),
        'payload_json' => trim((string)($row['payload_json'] ?? '')),
    ];
}

function v3ks_missing_required_fields(array $values): array
{
    $missing = [];
    foreach ($values as $key => $value) {
        if (trim((string)$value) === '') {
            $missing[] = $key;
        }
    }
    return $missing;
}

function v3ks_starting_point_check(mysqli $db, array $row): array
{
    $lessorId = trim((string)($row['lessor_id'] ?? ''));
    $startId = trim((string)($row['starting_point_id'] ?? ''));
    $out = ['ok' => false, 'label' => '', 'reason' => 'not_checked'];
    if ($lessorId === '' || $startId === '') {
        $out['reason'] = 'lessor_id or starting_point_id missing';
        return $out;
    }
    if (!v3ks_table_exists($db, 'pre_ride_email_v3_starting_point_options')) {
        $out['reason'] = 'starting-point options table missing';
        return $out;
    }
    $option = v3ks_fetch_one(
        $db,
        'SELECT label FROM pre_ride_email_v3_starting_point_options WHERE edxeix_lessor_id = ? AND edxeix_starting_point_id = ? AND is_active = 1 LIMIT 1',
        [$lessorId, $startId]
    );
    if (is_array($option)) {
        return ['ok' => true, 'label' => (string)($option['label'] ?? ''), 'reason' => 'operator_verified'];
    }
    return ['ok' => false, 'label' => '', 'reason' => 'starting point is not operator-verified for lessor'];
}

function v3ks_approval_check(mysqli $db, array $row): array
{
    $queueId = (int)($row['id'] ?? 0);
    $out = ['table_exists' => false, 'valid' => false, 'count' => 0, 'latest' => null, 'reason' => 'approval table missing'];
    if (!v3ks_table_exists($db, 'pre_ride_email_v3_live_submit_approvals')) {
        return $out;
    }
    $out['table_exists'] = true;
    $out['reason'] = 'no valid approval found';
    $rows = v3ks_fetch_all(
        $db,
        "SELECT * FROM pre_ride_email_v3_live_submit_approvals WHERE queue_id = ? ORDER BY id DESC LIMIT 5",
        [$queueId]
    );
    $out['count'] = count($rows);
    $out['latest'] = $rows[0] ?? null;

    foreach ($rows as $approval) {
        $statusOk = (string)($approval['approval_status'] ?? '') === 'approved';
        $scopeOk = (string)($approval['approval_scope'] ?? '') === V3_APPROVAL_SCOPE;
        $notRevoked = trim((string)($approval['revoked_at'] ?? '')) === '';
        $expiresAt = trim((string)($approval['expires_at'] ?? ''));
        $future = false;
        if ($expiresAt !== '') {
            try {
                $expires = new DateTimeImmutable($expiresAt, new DateTimeZone('Europe/Athens'));
                $now = new DateTimeImmutable('now', new DateTimeZone('Europe/Athens'));
                $future = $expires->getTimestamp() > $now->getTimestamp();
            } catch (Throwable $e) {
                $future = false;
            }
        }
        if ($statusOk && $scopeOk && $notRevoked && $future) {
            $out['valid'] = true;
            $out['latest_valid'] = $approval;
            $out['reason'] = 'valid closed-gate rehearsal approval found';
            return $out;
        }
    }
    return $out;
}

function v3ks_adapter_check(string $appRoot, array $safeConfig): array
{
    $files = [
        'interface' => $appRoot . '/src/BoltMailV3/LiveSubmitAdapterV3.php',
        'disabled' => $appRoot . '/src/BoltMailV3/DisabledLiveSubmitAdapterV3.php',
        'dry_run' => $appRoot . '/src/BoltMailV3/DryRunLiveSubmitAdapterV3.php',
        'edxeix_live' => $appRoot . '/src/BoltMailV3/EdxeixLiveSubmitAdapterV3.php',
    ];
    $out = [
        'selected_adapter' => (string)($safeConfig['adapter'] ?? 'disabled'),
        'expected_adapter' => V3_EXPECTED_ADAPTER,
        'files' => [],
        'class_exists' => false,
        'instantiated' => false,
        'name' => '',
        'is_live_capable' => false,
        'reason' => '',
    ];
    foreach ($files as $key => $path) {
        $out['files'][$key] = ['path' => $path, 'exists' => is_file($path), 'readable' => is_readable($path)];
    }

    $selected = $out['selected_adapter'];
    if ($selected !== V3_EXPECTED_ADAPTER) {
        $out['reason'] = 'selected adapter is not ' . V3_EXPECTED_ADAPTER;
        return $out;
    }
    if (!$out['files']['edxeix_live']['readable']) {
        $out['reason'] = 'edxeix live adapter file missing or unreadable';
        return $out;
    }

    try {
        require_once $files['interface'];
        require_once $files['edxeix_live'];
        $class = 'Bridge\\BoltMailV3\\EdxeixLiveSubmitAdapterV3';
        $out['class_exists'] = class_exists($class);
        if (!$out['class_exists']) {
            $out['reason'] = 'edxeix live adapter class not found';
            return $out;
        }
        $adapter = new $class();
        $out['instantiated'] = true;
        if (method_exists($adapter, 'name')) {
            $out['name'] = (string)$adapter->name();
        }
        if (method_exists($adapter, 'isLiveCapable')) {
            $out['is_live_capable'] = (bool)$adapter->isLiveCapable();
        }
        if (!$out['is_live_capable']) {
            $out['reason'] = 'selected adapter is present but not live-capable';
        }
        return $out;
    } catch (Throwable $e) {
        $out['reason'] = $e->getMessage();
        return $out;
    }
}

function v3ks_package_export_check(string $appRoot, array $row): array
{
    $queueId = (int)($row['id'] ?? 0);
    $dir = $appRoot . '/storage/artifacts/v3_live_submit_packages';
    $files = [];
    if (is_dir($dir)) {
        $matches = glob($dir . '/queue_' . $queueId . '_*') ?: [];
        foreach ($matches as $path) {
            $files[] = basename($path);
        }
        rsort($files);
    }
    return [
        'artifact_dir' => $dir,
        'artifact_dir_exists' => is_dir($dir),
        'artifact_dir_writable' => is_dir($dir) && is_writable($dir),
        'queue_artifact_count' => count($files),
        'latest_queue_artifacts' => array_slice($files, 0, 8),
    ];
}

function v3ks_build_report(array $argv): array
{
    $appRoot = dirname(__DIR__);
    $report = [
        'ok' => false,
        'version' => V3_KILL_SWITCH_VERSION,
        'mode' => 'read_only_live_adapter_kill_switch_check',
        'started_at' => gmdate('c'),
        'safety' => 'No Bolt call. No EDXEIX call. No AADE call. No DB writes. No queue status changes. No production submission tables. V0 untouched.',
        'app_root' => $appRoot,
        'events' => [],
    ];

    try {
        /** @var array{db:object} $boot */
        $boot = require $appRoot . '/src/bootstrap.php';
        $dbWrapper = $boot['db'] ?? null;
        if (!is_object($dbWrapper) || !method_exists($dbWrapper, 'connection')) {
            throw new RuntimeException('bootstrap did not provide a database connection');
        }
        /** @var mysqli $db */
        $db = $dbWrapper->connection();

        $queueIdArg = v3ks_arg_value($argv, '--queue-id', '');
        $queueId = ctype_digit($queueIdArg) ? (int)$queueIdArg : null;
        $row = v3ks_pick_queue_row($db, $queueId);
        $liveConfig = v3ks_load_live_submit_config($appRoot);
        $safeConfig = (array)($liveConfig['safe'] ?? []);
        $gateBlocks = v3ks_gate_blocks($safeConfig);

        $report['live_submit_config'] = [
            'path' => $liveConfig['path'],
            'exists' => $liveConfig['exists'],
            'readable' => $liveConfig['readable'],
            'loaded' => $liveConfig['loaded'],
            'error' => $liveConfig['error'],
            'safe' => $safeConfig,
            'blocks' => $gateBlocks,
        ];
        $report['queue_metrics'] = [
            'table_exists' => v3ks_table_exists($db, 'pre_ride_email_v3_queue'),
        ];

        if (!is_array($row)) {
            $report['selected_queue_row'] = null;
            $report['final_blocks'] = array_merge($gateBlocks, ['queue: no row found']);
            return $report;
        }

        $row['minutes_until_now'] = v3ks_minutes_until((string)($row['pickup_datetime'] ?? ''));
        $requiredValues = v3ks_required_field_values($row);
        $missing = v3ks_missing_required_fields($requiredValues);
        $startCheck = v3ks_starting_point_check($db, $row);
        $approval = v3ks_approval_check($db, $row);
        $adapter = v3ks_adapter_check($appRoot, $safeConfig);
        $package = v3ks_package_export_check($appRoot, $row);

        $finalBlocks = $gateBlocks;
        if ((string)($row['queue_status'] ?? '') !== 'live_submit_ready') {
            $finalBlocks[] = 'queue: row is not live_submit_ready';
        }
        if ($row['minutes_until_now'] === null || $row['minutes_until_now'] < 1) {
            $finalBlocks[] = 'queue: pickup is not future-safe';
        }
        if ($missing !== []) {
            $finalBlocks[] = 'payload: missing required fields: ' . implode(', ', $missing);
        }
        if (empty($startCheck['ok'])) {
            $finalBlocks[] = 'starting_point: ' . (string)($startCheck['reason'] ?? 'not verified');
        }
        if (empty($approval['valid'])) {
            $finalBlocks[] = 'approval: no valid closed-gate rehearsal approval found';
        }
        if ((string)($adapter['selected_adapter'] ?? '') !== V3_EXPECTED_ADAPTER) {
            $finalBlocks[] = 'adapter: selected adapter is not ' . V3_EXPECTED_ADAPTER;
        } elseif (empty($adapter['is_live_capable'])) {
            $finalBlocks[] = 'adapter: selected adapter is not live-capable';
        }

        $report['selected_queue_row'] = $row;
        $report['required_fields'] = ['values' => $requiredValues, 'missing' => $missing];
        $report['starting_point'] = $startCheck;
        $report['approval'] = $approval;
        $report['adapter'] = $adapter;
        $report['package_export'] = $package;
        $report['eligible_for_live_submit_now'] = $finalBlocks === [];
        $report['final_blocks'] = $finalBlocks;
        $report['ok'] = $finalBlocks === [];
        return $report;
    } catch (Throwable $e) {
        $report['events'][] = ['level' => 'error', 'message' => $e->getMessage()];
        $report['final_blocks'] = ['system: ' . $e->getMessage()];
        return $report;
    }
}

function v3ks_print_text(array $report): void
{
    echo 'V3 live adapter kill-switch check ' . V3_KILL_SWITCH_VERSION . PHP_EOL;
    echo 'Mode: ' . $report['mode'] . PHP_EOL;
    echo 'Safety: ' . $report['safety'] . PHP_EOL;
    echo 'OK: ' . (!empty($report['ok']) ? 'yes' : 'no') . PHP_EOL;

    $gate = $report['live_submit_config']['safe'] ?? [];
    echo 'Gate: loaded=' . (!empty($report['live_submit_config']['loaded']) ? 'yes' : 'no')
        . ' enabled=' . (!empty($gate['enabled']) ? 'yes' : 'no')
        . ' mode=' . (string)($gate['mode'] ?? '')
        . ' adapter=' . (string)($gate['adapter'] ?? '')
        . ' hard=' . (!empty($gate['hard_enable_live_submit']) ? 'yes' : 'no')
        . ' ack=' . (!empty($gate['acknowledgement_phrase_present']) ? 'yes' : 'no') . PHP_EOL;

    $row = $report['selected_queue_row'] ?? null;
    if (is_array($row)) {
        echo 'Selected row: #' . (string)($row['id'] ?? '') . ' status=' . (string)($row['queue_status'] ?? '')
            . ' pickup=' . (string)($row['pickup_datetime'] ?? '')
            . ' minutes_until=' . (string)($row['minutes_until_now'] ?? '') . PHP_EOL;
        echo 'Transfer: ' . (string)($row['customer_name'] ?? '') . ' | ' . (string)($row['driver_name'] ?? '') . ' | ' . (string)($row['vehicle_plate'] ?? '') . PHP_EOL;
        echo 'IDs: lessor=' . (string)($row['lessor_id'] ?? '') . ' driver=' . (string)($row['driver_id'] ?? '') . ' vehicle=' . (string)($row['vehicle_id'] ?? '') . ' start=' . (string)($row['starting_point_id'] ?? '') . PHP_EOL;
    } else {
        echo 'Selected row: none' . PHP_EOL;
    }

    $adapter = $report['adapter'] ?? [];
    if (is_array($adapter)) {
        echo 'Adapter: selected=' . (string)($adapter['selected_adapter'] ?? '')
            . ' expected=' . (string)($adapter['expected_adapter'] ?? '')
            . ' live_capable=' . (!empty($adapter['is_live_capable']) ? 'yes' : 'no')
            . ' reason=' . (string)($adapter['reason'] ?? '') . PHP_EOL;
    }

    $approval = $report['approval'] ?? [];
    if (is_array($approval)) {
        echo 'Approval: valid=' . (!empty($approval['valid']) ? 'yes' : 'no') . ' reason=' . (string)($approval['reason'] ?? '') . PHP_EOL;
    }

    $start = $report['starting_point'] ?? [];
    if (is_array($start)) {
        echo 'Starting point: ' . (!empty($start['ok']) ? 'verified' : 'not verified') . ' — ' . (string)($start['reason'] ?? '') . PHP_EOL;
    }

    echo 'Final blocks:' . PHP_EOL;
    $blocks = (array)($report['final_blocks'] ?? []);
    if ($blocks === []) {
        echo '  - none' . PHP_EOL;
    } else {
        foreach ($blocks as $block) {
            echo '  - ' . (string)$block . PHP_EOL;
        }
    }
}

$report = v3ks_build_report($argv ?? []);
if (v3ks_has_arg($argv ?? [], '--json')) {
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} else {
    v3ks_print_text($report);
}
exit(!empty($report['ok']) ? 0 : 1);
