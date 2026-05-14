<?php

declare(strict_types=1);

/**
 * V3 pre-live switchboard v3.0.63-v3-pre-live-switchboard
 *
 * Read-only consolidated status check for the V3 closed-gate automation path.
 *
 * Safety:
 * - No Bolt call
 * - No EDXEIX call
 * - No AADE call
 * - No DB writes
 * - No queue status changes
 * - No production submission tables
 * - V0 untouched
 */

const V3_SWITCHBOARD_VERSION = 'v3.0.63-v3-pre-live-switchboard';
const V3_APPROVAL_SCOPE = 'closed_gate_rehearsal_only';

$options = getopt('', ['json', 'queue-id:']);
$jsonMode = array_key_exists('json', $options);
$queueIdOpt = isset($options['queue-id']) ? trim((string)$options['queue-id']) : '';

/** @return never */
function v3sb_exit(array $report, bool $jsonMode): void
{
    $report['finished_at'] = gmdate('c');

    if ($jsonMode) {
        echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        exit(!empty($report['ok']) ? 0 : 1);
    }

    echo 'V3 pre-live switchboard ' . (string)($report['version'] ?? V3_SWITCHBOARD_VERSION) . PHP_EOL;
    echo 'Mode: ' . (string)($report['mode'] ?? 'read_only_pre_live_switchboard') . PHP_EOL;
    echo 'Safety: ' . (string)($report['safety'] ?? '') . PHP_EOL;
    echo 'OK: ' . (!empty($report['ok']) ? 'yes' : 'no') . PHP_EOL;

    $row = $report['selected_queue_row'] ?? null;
    if (is_array($row) && !empty($row)) {
        echo 'Selected row: #' . (string)($row['id'] ?? '') .
            ' status=' . (string)($row['queue_status'] ?? '') .
            ' pickup=' . (string)($row['pickup_datetime'] ?? '') .
            ' minutes_until=' . (string)($row['minutes_until_now'] ?? '') . PHP_EOL;
        echo 'Transfer: ' . (string)($row['customer_name'] ?? '') . ' | ' .
            (string)($row['driver_name'] ?? '') . ' | ' .
            (string)($row['vehicle_plate'] ?? '') . PHP_EOL;
        echo 'IDs: lessor=' . (string)($row['lessor_id'] ?? '') .
            ' driver=' . (string)($row['driver_id'] ?? '') .
            ' vehicle=' . (string)($row['vehicle_id'] ?? '') .
            ' start=' . (string)($row['starting_point_id'] ?? '') . PHP_EOL;
    } else {
        echo 'Selected row: none' . PHP_EOL;
    }

    $gate = $report['gate'] ?? [];
    if (is_array($gate)) {
        echo 'Gate: loaded=' . (!empty($gate['loaded']) ? 'yes' : 'no') .
            ' enabled=' . (!empty($gate['enabled']) ? 'yes' : 'no') .
            ' mode=' . (string)($gate['mode'] ?? '') .
            ' adapter=' . (string)($gate['adapter'] ?? '') .
            ' hard=' . (!empty($gate['hard_enable_live_submit']) ? 'yes' : 'no') .
            ' ack=' . (!empty($gate['acknowledgement_phrase_present']) ? 'yes' : 'no') . PHP_EOL;
    }

    $approval = $report['approval'] ?? [];
    if (is_array($approval)) {
        echo 'Approval: valid=' . (!empty($approval['valid']) ? 'yes' : 'no') .
            ' reason=' . (string)($approval['reason'] ?? '') . PHP_EOL;
    }

    $payload = $report['payload'] ?? [];
    if (is_array($payload)) {
        echo 'Payload: complete=' . (!empty($payload['complete']) ? 'yes' : 'no') .
            ' missing=' . implode(',', (array)($payload['missing'] ?? [])) . PHP_EOL;
    }

    $start = $report['starting_point'] ?? [];
    if (is_array($start)) {
        echo 'Starting point: ' . (!empty($start['ok']) ? 'verified' : 'not verified') .
            ' — ' . (string)($start['reason'] ?? '') . PHP_EOL;
    }

    $adapter = $report['adapter'] ?? [];
    if (is_array($adapter)) {
        echo 'Adapter: selected=' . (string)($adapter['selected_adapter'] ?? '') .
            ' expected=edxeix_live live_capable=' . (!empty($adapter['is_live_capable']) ? 'yes' : 'no') .
            ' reason=' . (string)($adapter['reason'] ?? '') . PHP_EOL;
    }

    $pkg = $report['package_export'] ?? [];
    if (is_array($pkg)) {
        echo 'Package artifacts: count=' . (string)($pkg['queue_artifact_count'] ?? '0') .
            ' dir=' . (string)($pkg['artifact_dir'] ?? '') . PHP_EOL;
    }

    $blocks = (array)($report['final_blocks'] ?? []);
    if ($blocks !== []) {
        echo 'Final blocks:' . PHP_EOL;
        foreach ($blocks as $block) {
            echo '  - ' . (string)$block . PHP_EOL;
        }
    } else {
        echo 'Final blocks: none' . PHP_EOL;
    }

    exit(!empty($report['ok']) ? 0 : 1);
}

/** @return array<string,mixed> */
function v3sb_base_report(): array
{
    return [
        'ok' => false,
        'version' => V3_SWITCHBOARD_VERSION,
        'mode' => 'read_only_pre_live_switchboard',
        'started_at' => gmdate('c'),
        'safety' => 'No Bolt call. No EDXEIX call. No AADE call. No DB writes. No queue status changes. No production submission tables. V0 untouched.',
        'app_root' => dirname(__DIR__),
        'events' => [],
        'final_blocks' => [],
    ];
}

/** @return mysqli */
function v3sb_db(array &$report): mysqli
{
    $bootstrap = dirname(__DIR__) . '/src/bootstrap.php';
    if (!is_readable($bootstrap)) {
        throw new RuntimeException('bootstrap.php is not readable: ' . $bootstrap);
    }

    $loaded = require $bootstrap;
    if (!is_array($loaded) || !isset($loaded['db']) || !is_object($loaded['db']) || !method_exists($loaded['db'], 'connection')) {
        throw new RuntimeException('bootstrap.php did not return a usable db connection object');
    }

    /** @var mysqli $db */
    $db = $loaded['db']->connection();
    $report['database'] = ['connected' => true];

    return $db;
}

function v3sb_value(mixed $value): string
{
    return trim((string)($value ?? ''));
}

/** @return array<string,mixed> */
function v3sb_live_config(): array
{
    $path = dirname(__DIR__, 2) . '/gov.cabnet.app_config/pre_ride_email_v3_live_submit.php';
    $out = [
        'path' => $path,
        'exists' => is_file($path),
        'readable' => is_readable($path),
        'loaded' => false,
        'error' => '',
        'raw' => [],
        'safe' => [
            'enabled' => false,
            'mode' => '',
            'adapter' => '',
            'hard_enable_live_submit' => false,
            'acknowledgement_phrase_present' => false,
        ],
    ];

    if (!$out['exists'] || !$out['readable']) {
        $out['error'] = 'live-submit config missing or unreadable';
        return $out;
    }

    try {
        $cfg = require $path;
        if (!is_array($cfg)) {
            $out['error'] = 'config did not return an array';
            return $out;
        }

        $out['loaded'] = true;
        $out['raw'] = $cfg;
        $ack = '';
        foreach (['required_acknowledgement_phrase', 'acknowledgement_phrase', 'ack_phrase'] as $key) {
            if (isset($cfg[$key]) && trim((string)$cfg[$key]) !== '') {
                $ack = trim((string)$cfg[$key]);
                break;
            }
        }

        $out['safe'] = [
            'enabled' => !empty($cfg['enabled']),
            'mode' => v3sb_value($cfg['mode'] ?? 'disabled'),
            'adapter' => v3sb_value($cfg['adapter'] ?? 'disabled'),
            'hard_enable_live_submit' => !empty($cfg['hard_enable_live_submit']),
            'acknowledgement_phrase_present' => $ack !== '',
        ];
    } catch (Throwable $e) {
        $out['error'] = $e->getMessage();
    }

    return $out;
}

/** @return array<int,string> */
function v3sb_gate_blocks(array $gate): array
{
    $safe = is_array($gate['safe'] ?? null) ? $gate['safe'] : [];
    $blocks = [];

    if (empty($gate['loaded'])) {
        $blocks[] = 'master_gate: config not loaded';
    }
    if (empty($safe['enabled'])) {
        $blocks[] = 'master_gate: enabled is false';
    }
    if (($safe['mode'] ?? '') !== 'live') {
        $blocks[] = 'master_gate: mode is not live';
    }
    if (($safe['adapter'] ?? '') !== 'edxeix_live') {
        $blocks[] = 'master_gate: adapter is not edxeix_live';
    }
    if (empty($safe['hard_enable_live_submit'])) {
        $blocks[] = 'master_gate: hard_enable_live_submit is false';
    }
    if (empty($safe['acknowledgement_phrase_present'])) {
        $blocks[] = 'master_gate: required acknowledgement phrase is not present';
    }

    return $blocks;
}

/** @return bool */
function v3sb_table_exists(mysqli $db, string $table): bool
{
    $stmt = $db->prepare('SELECT COUNT(*) AS c FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1');
    $stmt->bind_param('s', $table);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc() ?: [];
    return ((int)($row['c'] ?? 0)) > 0;
}

/** @return array<string,mixed>|null */
function v3sb_select_row(mysqli $db, string $queueId = ''): ?array
{
    if (!v3sb_table_exists($db, 'pre_ride_email_v3_queue')) {
        return null;
    }

    if ($queueId !== '' && ctype_digit($queueId)) {
        $stmt = $db->prepare('SELECT * FROM pre_ride_email_v3_queue WHERE id = ? LIMIT 1');
        $id = (int)$queueId;
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
    } else {
        $sql = "
            SELECT *
            FROM pre_ride_email_v3_queue
            WHERE queue_status = 'live_submit_ready'
            ORDER BY pickup_datetime ASC, id ASC
            LIMIT 1
        ";
        $row = $db->query($sql)->fetch_assoc();
        if (!$row) {
            $row = $db->query('SELECT * FROM pre_ride_email_v3_queue ORDER BY id DESC LIMIT 1')->fetch_assoc();
        }
    }

    if (!is_array($row)) {
        return null;
    }

    $pickup = v3sb_value($row['pickup_datetime'] ?? '');
    $minutes = null;
    if ($pickup !== '') {
        try {
            $now = new DateTimeImmutable('now', new DateTimeZone('Europe/Athens'));
            $dt = new DateTimeImmutable($pickup, new DateTimeZone('Europe/Athens'));
            $minutes = (int)floor(($dt->getTimestamp() - $now->getTimestamp()) / 60);
        } catch (Throwable) {
            $minutes = null;
        }
    }

    $row['minutes_until_now'] = $minutes;
    return $row;
}

/** @return array<string,mixed> */
function v3sb_payload_check(array $row): array
{
    $required = [
        'lessor_id',
        'driver_id',
        'vehicle_id',
        'starting_point_id',
        'customer_name',
        'customer_phone',
        'pickup_datetime',
        'estimated_end_datetime',
        'pickup_address',
        'dropoff_address',
        'price_amount',
        'payload_json',
    ];

    $values = [];
    $missing = [];
    foreach ($required as $key) {
        $value = v3sb_value($row[$key] ?? '');
        $values[$key] = $value;
        if ($value === '') {
            $missing[] = $key;
        }
    }

    return [
        'complete' => $missing === [],
        'missing' => $missing,
        'values' => $values,
    ];
}

/** @return array<string,mixed> */
function v3sb_starting_point_check(mysqli $db, array $row): array
{
    $lessorId = v3sb_value($row['lessor_id'] ?? '');
    $startId = v3sb_value($row['starting_point_id'] ?? '');

    $out = ['ok' => false, 'label' => '', 'reason' => 'missing lessor or starting point'];
    if ($lessorId === '' || $startId === '') {
        return $out;
    }
    if (!v3sb_table_exists($db, 'pre_ride_email_v3_starting_point_options')) {
        $out['reason'] = 'start options table missing';
        return $out;
    }

    $stmt = $db->prepare(
        'SELECT label FROM pre_ride_email_v3_starting_point_options WHERE edxeix_lessor_id = ? AND edxeix_starting_point_id = ? AND is_active = 1 LIMIT 1'
    );
    $stmt->bind_param('ss', $lessorId, $startId);
    $stmt->execute();
    $found = $stmt->get_result()->fetch_assoc();

    if ($found) {
        return ['ok' => true, 'label' => (string)($found['label'] ?? ''), 'reason' => 'operator_verified'];
    }

    return ['ok' => false, 'label' => '', 'reason' => 'starting point not verified for lessor'];
}

/** @return array<string,mixed> */
function v3sb_approval_check(mysqli $db, array $row): array
{
    $out = ['table_exists' => false, 'valid' => false, 'count' => 0, 'latest' => null, 'reason' => 'approval table missing'];

    if (!v3sb_table_exists($db, 'pre_ride_email_v3_live_submit_approvals')) {
        return $out;
    }
    $out['table_exists'] = true;

    $queueId = (int)($row['id'] ?? 0);
    $dedupeKey = v3sb_value($row['dedupe_key'] ?? '');
    if ($queueId <= 0 && $dedupeKey === '') {
        $out['reason'] = 'queue id and dedupe key missing';
        return $out;
    }

    $sql = "
        SELECT *
        FROM pre_ride_email_v3_live_submit_approvals
        WHERE
            (queue_id = ? OR dedupe_key = ?)
            AND approval_status IN ('approved','valid','active')
            AND approval_scope = ?
            AND (revoked_at IS NULL OR revoked_at = '')
            AND (expires_at IS NULL OR expires_at >= NOW())
        ORDER BY id DESC
        LIMIT 1
    ";
    $stmt = $db->prepare($sql);
    $scope = V3_APPROVAL_SCOPE;
    $stmt->bind_param('iss', $queueId, $dedupeKey, $scope);
    $stmt->execute();
    $valid = $stmt->get_result()->fetch_assoc();

    $countStmt = $db->prepare(
        "SELECT COUNT(*) AS c FROM pre_ride_email_v3_live_submit_approvals WHERE queue_id = ? OR dedupe_key = ?"
    );
    $countStmt->bind_param('is', $queueId, $dedupeKey);
    $countStmt->execute();
    $countRow = $countStmt->get_result()->fetch_assoc() ?: [];
    $out['count'] = (int)($countRow['c'] ?? 0);

    if ($valid) {
        $out['valid'] = true;
        $out['latest'] = $valid;
        $out['reason'] = 'valid closed-gate rehearsal approval found';
        return $out;
    }

    $out['reason'] = 'no valid approval found';
    return $out;
}

/** @return array<string,mixed> */
function v3sb_adapter_check(array $gate): array
{
    $appRoot = dirname(__DIR__);
    $selected = (string)($gate['safe']['adapter'] ?? '');

    $files = [
        'interface' => $appRoot . '/src/BoltMailV3/LiveSubmitAdapterV3.php',
        'disabled' => $appRoot . '/src/BoltMailV3/DisabledLiveSubmitAdapterV3.php',
        'dry_run' => $appRoot . '/src/BoltMailV3/DryRunLiveSubmitAdapterV3.php',
        'edxeix_live' => $appRoot . '/src/BoltMailV3/EdxeixLiveSubmitAdapterV3.php',
    ];

    $fileInfo = [];
    foreach ($files as $key => $path) {
        $fileInfo[$key] = [
            'path' => $path,
            'exists' => is_file($path),
            'readable' => is_readable($path),
        ];
        if (is_readable($path)) {
            require_once $path;
        }
    }

    $class = '';
    if ($selected === 'edxeix_live') {
        $class = '\\Bridge\\BoltMailV3\\EdxeixLiveSubmitAdapterV3';
    } elseif ($selected === 'dry_run') {
        $class = '\\Bridge\\BoltMailV3\\DryRunLiveSubmitAdapterV3';
    } elseif ($selected === 'disabled') {
        $class = '\\Bridge\\BoltMailV3\\DisabledLiveSubmitAdapterV3';
    }

    $out = [
        'selected_adapter' => $selected,
        'expected_adapter' => 'edxeix_live',
        'files' => $fileInfo,
        'class_exists' => false,
        'instantiated' => false,
        'name' => '',
        'is_live_capable' => false,
        'reason' => '',
    ];

    if ($selected !== 'edxeix_live') {
        $out['reason'] = 'selected adapter is not edxeix_live';
        return $out;
    }

    if ($class === '' || !class_exists($class)) {
        $out['reason'] = 'edxeix_live adapter class is not loadable';
        return $out;
    }

    $out['class_exists'] = true;

    try {
        $obj = new $class();
        $out['instantiated'] = true;
        if (method_exists($obj, 'name')) {
            $out['name'] = (string)$obj->name();
        }
        if (method_exists($obj, 'isLiveCapable')) {
            $out['is_live_capable'] = (bool)$obj->isLiveCapable();
        }
        if (!$out['is_live_capable']) {
            $out['reason'] = 'edxeix_live adapter is not live-capable';
        }
    } catch (Throwable $e) {
        $out['reason'] = 'adapter instantiation failed: ' . $e->getMessage();
    }

    return $out;
}

/** @return array<string,mixed> */
function v3sb_package_export_state(array $row): array
{
    $dir = dirname(__DIR__) . '/storage/artifacts/v3_live_submit_packages';
    $queueId = (string)($row['id'] ?? '');
    $files = [];
    if ($queueId !== '' && is_dir($dir)) {
        $glob = glob($dir . '/queue_' . $queueId . '_*');
        if (is_array($glob)) {
            rsort($glob);
            $files = array_map('basename', array_slice($glob, 0, 8));
        }
    }

    return [
        'artifact_dir' => $dir,
        'artifact_dir_exists' => is_dir($dir),
        'artifact_dir_writable' => is_writable($dir),
        'queue_artifact_count' => count($files),
        'latest_queue_artifacts' => $files,
    ];
}

$report = v3sb_base_report();

try {
    $db = v3sb_db($report);

    $gate = v3sb_live_config();
    $gateBlocks = v3sb_gate_blocks($gate);
    $report['gate'] = [
        'loaded' => !empty($gate['loaded']),
        'enabled' => !empty($gate['safe']['enabled']),
        'mode' => (string)($gate['safe']['mode'] ?? ''),
        'adapter' => (string)($gate['safe']['adapter'] ?? ''),
        'hard_enable_live_submit' => !empty($gate['safe']['hard_enable_live_submit']),
        'acknowledgement_phrase_present' => !empty($gate['safe']['acknowledgement_phrase_present']),
        'config_path' => (string)($gate['path'] ?? ''),
        'blocks' => $gateBlocks,
    ];

    $row = v3sb_select_row($db, $queueIdOpt);
    $report['selected_queue_row'] = $row ?: null;

    if (!$row) {
        $report['final_blocks'][] = 'queue: no V3 queue row selected';
        v3sb_exit($report, $jsonMode);
    }

    $payload = v3sb_payload_check($row);
    $start = v3sb_starting_point_check($db, $row);
    $approval = v3sb_approval_check($db, $row);
    $adapter = v3sb_adapter_check($gate);
    $package = v3sb_package_export_state($row);

    $report['payload'] = $payload;
    $report['starting_point'] = $start;
    $report['approval'] = $approval;
    $report['adapter'] = $adapter;
    $report['package_export'] = $package;

    $blocks = $gateBlocks;

    if ((string)($row['queue_status'] ?? '') !== 'live_submit_ready') {
        $blocks[] = 'queue: row is not live_submit_ready';
    }
    if (!isset($row['minutes_until_now']) || (int)$row['minutes_until_now'] <= 0) {
        $blocks[] = 'queue: pickup is not future-safe';
    }
    if (empty($payload['complete'])) {
        foreach ((array)($payload['missing'] ?? []) as $missing) {
            $blocks[] = 'payload: missing ' . (string)$missing;
        }
    }
    if (empty($start['ok'])) {
        $blocks[] = 'starting_point: ' . (string)($start['reason'] ?? 'not verified');
    }
    if (empty($approval['valid'])) {
        $blocks[] = 'approval: no valid closed-gate rehearsal approval found';
    }
    if (empty($adapter['is_live_capable'])) {
        $blocks[] = 'adapter: ' . ((string)($adapter['reason'] ?? '') ?: 'adapter is not live-capable');
    }

    $report['final_blocks'] = array_values(array_unique($blocks));
    $report['eligible_for_live_submit_now'] = $report['final_blocks'] === [];
    $report['ok'] = !empty($report['eligible_for_live_submit_now']);

    v3sb_exit($report, $jsonMode);
} catch (Throwable $e) {
    $report['events'][] = ['level' => 'error', 'message' => $e->getMessage()];
    $report['final_blocks'][] = 'system: ' . $e->getMessage();
    v3sb_exit($report, $jsonMode);
}
