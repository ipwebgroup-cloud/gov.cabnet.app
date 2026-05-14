<?php
/**
 * gov.cabnet.app — V3 closed-gate live adapter diagnostics
 *
 * Read-only diagnostic for the future V3 live-submit adapter path.
 * No Bolt call. No EDXEIX call. No AADE call. No queue mutation. No V0 changes.
 */

declare(strict_types=1);

const V3CGAD_VERSION = 'v3.0.54-v3-closed-gate-adapter-diagnostics';

function v3cgad_cfg(array $config, string $path, $default = null)
{
    $cursor = $config;
    foreach (explode('.', $path) as $part) {
        if (!is_array($cursor) || !array_key_exists($part, $cursor)) {
            return $default;
        }
        $cursor = $cursor[$part];
    }
    return $cursor;
}

function v3cgad_bool($value): bool
{
    if (is_bool($value)) { return $value; }
    if (is_int($value)) { return $value === 1; }
    if (is_string($value)) {
        return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on', 'live'], true);
    }
    return false;
}

function v3cgad_app_root(): string
{
    $env = getenv('GOV_CABNET_APP_ROOT');
    if (is_string($env) && trim($env) !== '') {
        return rtrim(trim($env), '/');
    }
    return dirname(__DIR__);
}

function v3cgad_config_root(): string
{
    $env = getenv('GOV_CABNET_CONFIG_ROOT');
    if (is_string($env) && trim($env) !== '') {
        return rtrim(trim($env), '/');
    }
    return dirname(v3cgad_app_root()) . '/gov.cabnet.app_config';
}

function v3cgad_load_config(): array
{
    $configFile = getenv('GOV_CABNET_CONFIG');
    if (!is_string($configFile) || trim($configFile) === '') {
        $configFile = v3cgad_config_root() . '/config.php';
    }

    if (!is_file($configFile) || !is_readable($configFile)) {
        throw new RuntimeException('Missing or unreadable external config file: ' . $configFile);
    }

    $config = require $configFile;
    if (!is_array($config)) {
        throw new RuntimeException('External config did not return an array: ' . $configFile);
    }

    return $config;
}

function v3cgad_db(array $config): mysqli
{
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $db = new mysqli(
        (string)v3cgad_cfg($config, 'db.host', 'localhost'),
        (string)v3cgad_cfg($config, 'db.username', ''),
        (string)v3cgad_cfg($config, 'db.password', ''),
        (string)v3cgad_cfg($config, 'db.database', ''),
        (int)v3cgad_cfg($config, 'db.port', 3306)
    );
    $db->set_charset((string)v3cgad_cfg($config, 'db.charset', 'utf8mb4'));
    return $db;
}

function v3cgad_table_exists(mysqli $db, string $table): bool
{
    $stmt = $db->prepare('SELECT COUNT(*) AS c FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
    $stmt->bind_param('s', $table);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc() ?: [];
    return (int)($row['c'] ?? 0) > 0;
}

function v3cgad_columns(mysqli $db, string $table): array
{
    if (!v3cgad_table_exists($db, $table)) {
        return [];
    }
    $stmt = $db->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION');
    $stmt->bind_param('s', $table);
    $stmt->execute();
    $cols = [];
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $cols[] = (string)$row['COLUMN_NAME'];
    }
    return $cols;
}

function v3cgad_fetch_one(mysqli $db, string $sql, array $params = [], string $types = ''): ?array
{
    $rows = v3cgad_fetch_all($db, $sql, $params, $types);
    return $rows[0] ?? null;
}

function v3cgad_fetch_all(mysqli $db, string $sql, array $params = [], string $types = ''): array
{
    $stmt = $db->prepare($sql);
    if ($params) {
        if ($types === '') {
            foreach ($params as $param) {
                $types .= is_int($param) ? 'i' : (is_float($param) ? 'd' : 's');
            }
        }
        $bind = [$types];
        foreach ($params as $i => $param) {
            $bind[] = &$params[$i];
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

function v3cgad_load_live_config(): array
{
    $path = v3cgad_config_root() . '/pre_ride_email_v3_live_submit.php';
    $state = [
        'path' => $path,
        'exists' => is_file($path),
        'readable' => is_file($path) && is_readable($path),
        'loaded' => false,
        'values' => [],
        'error' => '',
    ];

    if (!$state['exists']) {
        $state['error'] = 'config file missing';
        return $state;
    }
    if (!$state['readable']) {
        $state['error'] = 'config file not readable by current user';
        return $state;
    }

    try {
        $values = require $path;
        if (!is_array($values)) {
            $state['error'] = 'config did not return array';
            return $state;
        }
        $state['loaded'] = true;
        $state['values'] = $values;
    } catch (Throwable $e) {
        $state['error'] = $e->getMessage();
    }

    return $state;
}

function v3cgad_gate_state(array $liveConfig): array
{
    $values = is_array($liveConfig['values'] ?? null) ? $liveConfig['values'] : [];
    $enabled = v3cgad_bool($values['enabled'] ?? false);
    $mode = strtolower(trim((string)($values['mode'] ?? 'disabled')));
    $adapter = strtolower(trim((string)($values['adapter'] ?? 'disabled')));
    $hard = v3cgad_bool($values['hard_enable_live_submit'] ?? false);
    $ack = trim((string)($values['required_acknowledgement_phrase'] ?? $values['acknowledgement_phrase'] ?? ''));

    $blocks = [];
    if (empty($liveConfig['loaded'])) { $blocks[] = 'server live-submit config is missing or invalid'; }
    if (!$enabled) { $blocks[] = 'enabled is false'; }
    if ($mode !== 'live') { $blocks[] = 'mode is not live'; }
    if ($adapter === '' || $adapter === 'disabled') { $blocks[] = 'adapter is disabled'; }
    if (!$hard) { $blocks[] = 'hard_enable_live_submit is false'; }
    if ($ack === '') { $blocks[] = 'required acknowledgement phrase is not present'; }

    return [
        'loaded' => !empty($liveConfig['loaded']),
        'enabled' => $enabled,
        'mode' => $mode,
        'adapter' => $adapter === '' ? 'disabled' : $adapter,
        'hard_enable_live_submit' => $hard,
        'acknowledgement_phrase_present' => $ack !== '',
        'ok_for_live_submit' => empty($blocks),
        'blocks' => $blocks,
    ];
}

function v3cgad_adapter_files(array $gate): array
{
    $src = v3cgad_app_root() . '/src/BoltMailV3';
    $files = [
        'interface' => $src . '/LiveSubmitAdapterV3.php',
        'disabled_adapter' => $src . '/DisabledLiveSubmitAdapterV3.php',
        'dry_run_adapter' => $src . '/DryRunLiveSubmitAdapterV3.php',
        'future_real_adapter' => $src . '/EdxeixLiveSubmitAdapterV3.php',
    ];

    $out = [];
    foreach ($files as $key => $path) {
        $out[$key] = [
            'path' => $path,
            'exists' => is_file($path),
            'readable' => is_file($path) && is_readable($path),
        ];
    }

    $selected = (string)($gate['adapter'] ?? 'disabled');
    $selectedKey = 'disabled_adapter';
    if ($selected === 'dry_run' || $selected === 'dry-run') {
        $selectedKey = 'dry_run_adapter';
    } elseif ($selected === 'edxeix_live' || $selected === 'live' || $selected === 'real') {
        $selectedKey = 'future_real_adapter';
    }

    return [
        'selected_adapter' => $selected,
        'selected_file_key' => $selectedKey,
        'selected_file_exists' => !empty($out[$selectedKey]['exists']),
        'files' => $out,
    ];
}

function v3cgad_pick_queue_row(mysqli $db, ?int $queueId): ?array
{
    if (!v3cgad_table_exists($db, 'pre_ride_email_v3_queue')) {
        return null;
    }
    if ($queueId !== null && $queueId > 0) {
        return v3cgad_fetch_one($db, 'SELECT * FROM pre_ride_email_v3_queue WHERE id = ? LIMIT 1', [$queueId], 'i');
    }
    $row = v3cgad_fetch_one($db, "SELECT * FROM pre_ride_email_v3_queue WHERE queue_status = 'live_submit_ready' ORDER BY pickup_datetime ASC, id ASC LIMIT 1");
    if ($row) { return $row; }
    return v3cgad_fetch_one($db, 'SELECT * FROM pre_ride_email_v3_queue ORDER BY id DESC LIMIT 1');
}

function v3cgad_verified_start(mysqli $db, array $row): array
{
    if (!v3cgad_table_exists($db, 'pre_ride_email_v3_starting_point_options')) {
        return ['ok' => false, 'label' => '', 'reason' => 'starting point options table missing'];
    }
    $lessor = trim((string)($row['lessor_id'] ?? ''));
    $start = trim((string)($row['starting_point_id'] ?? ''));
    if ($lessor === '' || $start === '') {
        return ['ok' => false, 'label' => '', 'reason' => 'lessor_id or starting_point_id missing'];
    }
    $match = v3cgad_fetch_one(
        $db,
        'SELECT label FROM pre_ride_email_v3_starting_point_options WHERE edxeix_lessor_id = ? AND edxeix_starting_point_id = ? AND is_active = 1 LIMIT 1',
        [$lessor, $start],
        'ss'
    );
    if (!$match) {
        return ['ok' => false, 'label' => '', 'reason' => 'starting point not operator-verified for this lessor'];
    }
    return ['ok' => true, 'label' => (string)($match['label'] ?? ''), 'reason' => 'operator-verified starting point'];
}

function v3cgad_payload_from_row(array $row): array
{
    $payload = [];
    $raw = trim((string)($row['payload_json'] ?? ''));
    if ($raw !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $payload = $decoded;
        }
    }
    return $payload;
}

function v3cgad_required_fields(array $row): array
{
    $payload = v3cgad_payload_from_row($row);
    $required = [
        'lessor_id' => (string)($row['lessor_id'] ?? ($payload['lessorId'] ?? '')),
        'driver_id' => (string)($row['driver_id'] ?? ($payload['driverId'] ?? '')),
        'vehicle_id' => (string)($row['vehicle_id'] ?? ($payload['vehicleId'] ?? '')),
        'starting_point_id' => (string)($row['starting_point_id'] ?? ($payload['startingPointId'] ?? '')),
        'customer_name' => (string)($row['customer_name'] ?? ($payload['passengerName'] ?? '')),
        'customer_phone' => (string)($row['customer_phone'] ?? ($payload['passengerPhone'] ?? '')),
        'pickup_datetime' => (string)($row['pickup_datetime'] ?? ($payload['pickupDateTime'] ?? '')),
        'estimated_end_datetime' => (string)($row['estimated_end_datetime'] ?? ($payload['endDateTime'] ?? '')),
        'pickup_address' => (string)($row['pickup_address'] ?? ($payload['pickupAddress'] ?? '')),
        'dropoff_address' => (string)($row['dropoff_address'] ?? ($payload['dropoffAddress'] ?? '')),
        'price_amount' => (string)($row['price_amount'] ?? ($payload['priceAmount'] ?? '')),
    ];
    $missing = [];
    foreach ($required as $key => $value) {
        if (trim($value) === '') {
            $missing[] = $key;
        }
    }
    return ['values' => $required, 'missing' => $missing];
}

function v3cgad_queue_metrics(mysqli $db): array
{
    if (!v3cgad_table_exists($db, 'pre_ride_email_v3_queue')) {
        return ['table_exists' => false];
    }
    $row = v3cgad_fetch_one($db, "
        SELECT
          COUNT(*) AS total,
          SUM(CASE WHEN queue_status NOT IN ('blocked','submitted','cancelled','terminal','expired') THEN 1 ELSE 0 END) AS active,
          SUM(CASE WHEN queue_status = 'live_submit_ready' THEN 1 ELSE 0 END) AS live_ready,
          SUM(CASE WHEN queue_status = 'submit_dry_run_ready' THEN 1 ELSE 0 END) AS dry_ready,
          SUM(CASE WHEN queue_status = 'blocked' THEN 1 ELSE 0 END) AS blocked
        FROM pre_ride_email_v3_queue
    ") ?: [];
    $row['table_exists'] = true;
    return $row;
}

function v3cgad_approval_state(mysqli $db, ?array $queueRow): array
{
    $table = 'pre_ride_email_v3_live_submit_approvals';
    $exists = v3cgad_table_exists($db, $table);
    $state = [
        'table_exists' => $exists,
        'columns' => $exists ? v3cgad_columns($db, $table) : [],
        'total' => 0,
        'valid_like_total' => 0,
        'queue_has_approval' => false,
        'queue_valid_like_approval' => false,
        'latest' => [],
    ];
    if (!$exists) {
        return $state;
    }

    $state['total'] = (int)((v3cgad_fetch_one($db, "SELECT COUNT(*) AS c FROM {$table}") ?: [])['c'] ?? 0);
    $cols = $state['columns'];
    $hasStatus = in_array('approval_status', $cols, true);
    $hasRevoked = in_array('revoked_at', $cols, true);
    $hasExpires = in_array('expires_at', $cols, true);
    $where = [];
    if ($hasStatus) { $where[] = "approval_status IN ('approved','valid','active')"; }
    if ($hasRevoked) { $where[] = 'revoked_at IS NULL'; }
    if ($hasExpires) { $where[] = '(expires_at IS NULL OR expires_at > NOW())'; }
    $validWhere = $where ? implode(' AND ', $where) : '1=0';
    $state['valid_like_total'] = (int)((v3cgad_fetch_one($db, "SELECT COUNT(*) AS c FROM {$table} WHERE {$validWhere}") ?: [])['c'] ?? 0);

    if ($queueRow && isset($queueRow['id']) && in_array('queue_id', $cols, true)) {
        $queueId = (int)$queueRow['id'];
        $state['queue_has_approval'] = (int)((v3cgad_fetch_one($db, "SELECT COUNT(*) AS c FROM {$table} WHERE queue_id = ?", [$queueId], 'i') ?: [])['c'] ?? 0) > 0;
        $state['queue_valid_like_approval'] = (int)((v3cgad_fetch_one($db, "SELECT COUNT(*) AS c FROM {$table} WHERE queue_id = ? AND {$validWhere}", [$queueId], 'i') ?: [])['c'] ?? 0) > 0;
    }

    $selectCols = array_slice($cols, 0, 12);
    $orderCol = in_array('created_at', $cols, true) ? 'created_at' : (in_array('id', $cols, true) ? 'id' : $cols[0]);
    if ($selectCols) {
        $state['latest'] = v3cgad_fetch_all($db, 'SELECT `' . implode('`,`', $selectCols) . "` FROM {$table} ORDER BY {$orderCol} DESC LIMIT 5");
    }
    return $state;
}

function v3cgad_package_export_state(?array $queueRow): array
{
    $cli = v3cgad_app_root() . '/cli/pre_ride_email_v3_live_package_export.php';
    $artifactDir = v3cgad_app_root() . '/storage/artifacts/v3_live_submit_packages';
    $queueId = $queueRow ? (int)($queueRow['id'] ?? 0) : 0;
    $matches = [];
    if ($queueId > 0 && is_dir($artifactDir)) {
        $glob = glob($artifactDir . '/queue_' . $queueId . '_*');
        $matches = is_array($glob) ? array_map('basename', $glob) : [];
        rsort($matches);
    }
    return [
        'cli_path' => $cli,
        'cli_exists' => is_file($cli),
        'cli_readable' => is_file($cli) && is_readable($cli),
        'artifact_dir' => $artifactDir,
        'artifact_dir_exists' => is_dir($artifactDir),
        'artifact_dir_writable' => is_dir($artifactDir) && is_writable($artifactDir),
        'queue_artifact_count' => count($matches),
        'latest_queue_artifacts' => array_slice($matches, 0, 8),
    ];
}

function v3cgad_build_report(?int $queueId = null): array
{
    $started = date('c');
    $report = [
        'ok' => false,
        'version' => V3CGAD_VERSION,
        'mode' => 'read_only_closed_gate_diagnostics',
        'started_at' => $started,
        'safety' => 'No Bolt call. No EDXEIX call. No AADE call. No DB writes. No queue status changes. No production submission tables. V0 untouched.',
        'events' => [],
    ];

    try {
        $config = v3cgad_load_config();
        $db = v3cgad_db($config);
        $liveConfig = v3cgad_load_live_config();
        $gate = v3cgad_gate_state($liveConfig);
        $row = v3cgad_pick_queue_row($db, $queueId);
        $required = $row ? v3cgad_required_fields($row) : ['values' => [], 'missing' => ['no_queue_row_selected']];
        $start = $row ? v3cgad_verified_start($db, $row) : ['ok' => false, 'label' => '', 'reason' => 'no queue row selected'];
        $approval = v3cgad_approval_state($db, $row);
        $adapter = v3cgad_adapter_files($gate);
        $package = v3cgad_package_export_state($row);
        $queueMetrics = v3cgad_queue_metrics($db);

        $submitBlocks = [];
        foreach ($gate['blocks'] as $block) { $submitBlocks[] = 'master_gate: ' . $block; }
        if (!$row) { $submitBlocks[] = 'queue: no queue row selected'; }
        if ($row && (string)($row['queue_status'] ?? '') !== 'live_submit_ready') { $submitBlocks[] = 'queue: row is not currently live_submit_ready'; }
        if ($required['missing']) { $submitBlocks[] = 'payload: missing required fields: ' . implode(', ', $required['missing']); }
        if (empty($start['ok'])) { $submitBlocks[] = 'starting_point: ' . (string)$start['reason']; }
        if (empty($approval['queue_valid_like_approval'])) { $submitBlocks[] = 'approval: no valid operator approval found for selected row'; }
        if (empty($adapter['selected_file_exists'])) { $submitBlocks[] = 'adapter: selected adapter file is not present'; }

        $report += [
            'ok' => true,
            'config' => [
                'app_root' => v3cgad_app_root(),
                'config_root' => v3cgad_config_root(),
            ],
            'live_submit_config' => [
                'path' => $liveConfig['path'],
                'exists' => $liveConfig['exists'],
                'readable' => $liveConfig['readable'],
                'loaded' => $liveConfig['loaded'],
                'error' => $liveConfig['error'],
            ],
            'gate' => $gate,
            'adapter' => $adapter,
            'queue_metrics' => $queueMetrics,
            'selected_queue_row' => $row,
            'required_fields' => $required,
            'starting_point' => $start,
            'approval' => $approval,
            'package_export' => $package,
            'eligible_for_live_submit_now' => empty($submitBlocks),
            'final_blocks' => $submitBlocks,
        ];
    } catch (Throwable $e) {
        $report['events'][] = ['level' => 'error', 'message' => $e->getMessage()];
        $report['error'] = $e->getMessage();
    }

    $report['finished_at'] = date('c');
    return $report;
}

function v3cgad_print_text(array $report): void
{
    echo 'V3 closed-gate adapter diagnostics ' . V3CGAD_VERSION . PHP_EOL;
    echo 'Mode: ' . ($report['mode'] ?? 'read_only') . PHP_EOL;
    echo 'Safety: ' . ($report['safety'] ?? '') . PHP_EOL;
    echo 'OK: ' . (!empty($report['ok']) ? 'yes' : 'no') . PHP_EOL;

    if (!empty($report['error'])) {
        echo 'ERROR: ' . $report['error'] . PHP_EOL;
        return;
    }

    $row = $report['selected_queue_row'] ?? null;
    if (is_array($row)) {
        echo 'Selected queue row: #' . (string)($row['id'] ?? '') . ' status=' . (string)($row['queue_status'] ?? '') . PHP_EOL;
        echo 'Transfer: ' . (string)($row['customer_name'] ?? '') . ' | ' . (string)($row['driver_name'] ?? '') . ' | ' . (string)($row['vehicle_plate'] ?? '') . PHP_EOL;
        echo 'Pickup: ' . (string)($row['pickup_datetime'] ?? '') . PHP_EOL;
    } else {
        echo 'Selected queue row: none' . PHP_EOL;
    }

    $gate = $report['gate'] ?? [];
    echo 'Gate: loaded=' . (!empty($gate['loaded']) ? 'yes' : 'no') . ' enabled=' . (!empty($gate['enabled']) ? 'yes' : 'no') . ' mode=' . (string)($gate['mode'] ?? '') . ' adapter=' . (string)($gate['adapter'] ?? '') . ' hard=' . (!empty($gate['hard_enable_live_submit']) ? 'yes' : 'no') . ' ok=' . (!empty($gate['ok_for_live_submit']) ? 'yes' : 'no') . PHP_EOL;

    $adapter = $report['adapter'] ?? [];
    echo 'Adapter selected: ' . (string)($adapter['selected_adapter'] ?? '') . ' file_exists=' . (!empty($adapter['selected_file_exists']) ? 'yes' : 'no') . PHP_EOL;

    $start = $report['starting_point'] ?? [];
    echo 'Starting point verified: ' . (!empty($start['ok']) ? 'yes' : 'no') . ' — ' . (string)($start['reason'] ?? '') . PHP_EOL;

    $required = $report['required_fields']['missing'] ?? [];
    echo 'Missing required fields: ' . (empty($required) ? 'none' : implode(', ', $required)) . PHP_EOL;

    $approval = $report['approval'] ?? [];
    echo 'Approval table: ' . (!empty($approval['table_exists']) ? 'exists' : 'missing') . ' valid_like_total=' . (int)($approval['valid_like_total'] ?? 0) . ' selected_row_valid=' . (!empty($approval['queue_valid_like_approval']) ? 'yes' : 'no') . PHP_EOL;

    $package = $report['package_export'] ?? [];
    echo 'Package export CLI: ' . (!empty($package['cli_exists']) ? 'present' : 'missing') . ' artifacts_for_row=' . (int)($package['queue_artifact_count'] ?? 0) . PHP_EOL;

    echo 'Eligible for live submit now: ' . (!empty($report['eligible_for_live_submit_now']) ? 'yes' : 'no') . PHP_EOL;
    if (!empty($report['final_blocks'])) {
        echo 'Final blocks:' . PHP_EOL;
        foreach ($report['final_blocks'] as $block) {
            echo '  - ' . $block . PHP_EOL;
        }
    }
}

function v3cgad_cli_main(array $argv): int
{
    $json = in_array('--json', $argv, true);
    $queueId = null;
    foreach ($argv as $arg) {
        if (strpos($arg, '--queue-id=') === 0) {
            $queueId = (int)substr($arg, strlen('--queue-id='));
        }
    }

    $report = v3cgad_build_report($queueId);
    if ($json) {
        echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    } else {
        v3cgad_print_text($report);
    }
    return !empty($report['ok']) ? 0 : 1;
}

if (PHP_SAPI === 'cli' && isset($_SERVER['SCRIPT_FILENAME']) && realpath((string)$_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    exit(v3cgad_cli_main($argv ?? []));
}
