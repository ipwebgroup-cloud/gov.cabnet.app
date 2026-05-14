<?php
/**
 * V3 adapter row simulation.
 *
 * Read-only simulation of the future EDXEIX live adapter skeleton using a real V3 queue row.
 * It builds the final EDXEIX field package and calls the local adapter skeleton only.
 * The skeleton must remain non-live-capable and must not call EDXEIX.
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

declare(strict_types=1);

date_default_timezone_set('Europe/Athens');

const V3_ADAPTER_ROW_SIM_VERSION = 'v3.0.67-v3-adapter-row-simulation';
const V3_ADAPTER_ROW_SIM_EXPECTED_ADAPTER = 'edxeix_live';
const V3_ADAPTER_ROW_SIM_APPROVAL_SCOPE = 'closed_gate_rehearsal_only';
const V3_ADAPTER_ROW_SIM_SAFETY = 'No Bolt call. No EDXEIX call. No AADE call. No DB writes. No queue status changes. No production submission tables. V0 untouched.';

/** @param array<int,string> $argv */
function v3sim_arg_value(array $argv, string $name, string $default = ''): string
{
    $prefix = $name . '=';
    foreach ($argv as $arg) {
        if (strpos($arg, $prefix) === 0) {
            return substr($arg, strlen($prefix));
        }
    }
    return $default;
}

/** @param array<int,string> $argv */
function v3sim_has_arg(array $argv, string $name): bool
{
    return in_array($name, $argv, true);
}

/** @return array<string,mixed> */
function v3sim_base_report(string $appRoot): array
{
    return [
        'ok' => false,
        'simulation_safe' => false,
        'version' => V3_ADAPTER_ROW_SIM_VERSION,
        'mode' => 'read_only_adapter_row_simulation',
        'started_at' => gmdate('c'),
        'safety' => V3_ADAPTER_ROW_SIM_SAFETY,
        'app_root' => $appRoot,
        'events' => [],
        'final_blocks' => [],
    ];
}

/** @param array<string,mixed> $report */
function v3sim_event(array &$report, string $level, string $message): void
{
    $report['events'][] = ['level' => $level, 'message' => $message];
}

function v3sim_value($value): string
{
    return trim((string)($value ?? ''));
}

function v3sim_bool($value): string
{
    if ($value === null) {
        return 'n/a';
    }
    return !empty($value) ? 'yes' : 'no';
}

/** @param array<string,mixed> $report */
function v3sim_db(array &$report, string $appRoot): mysqli
{
    $bootstrap = $appRoot . '/src/bootstrap.php';
    if (!is_readable($bootstrap)) {
        throw new RuntimeException('bootstrap.php is not readable: ' . $bootstrap);
    }

    $loaded = require $bootstrap;
    if (!is_array($loaded) || !isset($loaded['db']) || !is_object($loaded['db']) || !method_exists($loaded['db'], 'connection')) {
        throw new RuntimeException('bootstrap.php did not return a usable db connection object');
    }

    /** @var mysqli $db */
    $db = $loaded['db']->connection();
    $db->set_charset('utf8mb4');
    $report['database'] = ['connected' => true];

    return $db;
}

/** @param array<int,mixed> $params @return array<int,array<string,mixed>> */
function v3sim_fetch_all(mysqli $db, string $sql, array $params = [], string $types = ''): array
{
    $stmt = $db->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException('prepare failed: ' . $db->error);
    }
    if ($params !== []) {
        if ($types === '') {
            foreach ($params as $p) {
                $types .= is_int($p) ? 'i' : (is_float($p) ? 'd' : 's');
            }
        }
        $bind = [$types];
        foreach ($params as $i => $p) {
            $bind[] = &$params[$i];
        }
        call_user_func_array([$stmt, 'bind_param'], $bind);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    if (!$res) {
        return [];
    }
    return $res->fetch_all(MYSQLI_ASSOC);
}

/** @param array<int,mixed> $params @return array<string,mixed>|null */
function v3sim_fetch_one(mysqli $db, string $sql, array $params = [], string $types = ''): ?array
{
    $rows = v3sim_fetch_all($db, $sql, $params, $types);
    return $rows[0] ?? null;
}

function v3sim_table_exists(mysqli $db, string $table): bool
{
    $row = v3sim_fetch_one(
        $db,
        'SELECT COUNT(*) AS c FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1',
        [$table]
    );
    return (int)($row['c'] ?? 0) > 0;
}

/** @return array<string,bool> */
function v3sim_table_columns(mysqli $db, string $table): array
{
    $rows = v3sim_fetch_all($db, 'SHOW COLUMNS FROM `' . str_replace('`', '``', $table) . '`');
    $out = [];
    foreach ($rows as $row) {
        $field = (string)($row['Field'] ?? '');
        if ($field !== '') {
            $out[$field] = true;
        }
    }
    return $out;
}

/** @return array<string,mixed> */
function v3sim_load_gate(string $appRoot): array
{
    $path = dirname($appRoot) . '/gov.cabnet.app_config/pre_ride_email_v3_live_submit.php';
    $out = [
        'path' => $path,
        'exists' => is_file($path),
        'readable' => is_readable($path),
        'loaded' => false,
        'enabled' => false,
        'mode' => 'disabled',
        'adapter' => 'disabled',
        'hard_enable_live_submit' => false,
        'acknowledgement_phrase_present' => false,
        'blocks' => [],
        'error' => '',
    ];

    if (!$out['exists'] || !$out['readable']) {
        $out['error'] = 'live-submit config missing or unreadable';
        $out['blocks'][] = 'master_gate: config missing or unreadable';
        return $out;
    }

    try {
        $cfg = require $path;
        if (!is_array($cfg)) {
            $out['error'] = 'live-submit config did not return an array';
            $out['blocks'][] = 'master_gate: config invalid';
            return $out;
        }
        $out['loaded'] = true;
        $out['enabled'] = !empty($cfg['enabled']);
        $out['mode'] = v3sim_value($cfg['mode'] ?? 'disabled');
        $out['adapter'] = v3sim_value($cfg['adapter'] ?? 'disabled');
        $out['hard_enable_live_submit'] = !empty($cfg['hard_enable_live_submit']);
        $out['acknowledgement_phrase_present'] = v3sim_ack_present($cfg);
    } catch (Throwable $e) {
        $out['error'] = $e->getMessage();
        $out['blocks'][] = 'master_gate: config load failed';
        return $out;
    }

    if (empty($out['enabled'])) {
        $out['blocks'][] = 'master_gate: enabled is false';
    }
    if (($out['mode'] ?? '') !== 'live') {
        $out['blocks'][] = 'master_gate: mode is not live';
    }
    if (($out['adapter'] ?? '') !== V3_ADAPTER_ROW_SIM_EXPECTED_ADAPTER) {
        $out['blocks'][] = 'master_gate: adapter is not ' . V3_ADAPTER_ROW_SIM_EXPECTED_ADAPTER;
    }
    if (empty($out['hard_enable_live_submit'])) {
        $out['blocks'][] = 'master_gate: hard_enable_live_submit is false';
    }
    if (empty($out['acknowledgement_phrase_present'])) {
        $out['blocks'][] = 'master_gate: required acknowledgement phrase is not present';
    }

    return $out;
}

/** @param array<string,mixed> $cfg */
function v3sim_ack_present(array $cfg): bool
{
    foreach (['acknowledgement_phrase', 'required_acknowledgement_phrase', 'live_submit_acknowledgement_phrase', 'ack_phrase'] as $key) {
        if (isset($cfg[$key]) && trim((string)$cfg[$key]) !== '') {
            return true;
        }
    }
    return false;
}

/** @return array<string,mixed>|null */
function v3sim_pick_row(mysqli $db, ?int $queueId): ?array
{
    if (!v3sim_table_exists($db, 'pre_ride_email_v3_queue')) {
        return null;
    }

    if ($queueId !== null && $queueId > 0) {
        return v3sim_fetch_one($db, 'SELECT * FROM pre_ride_email_v3_queue WHERE id = ? LIMIT 1', [$queueId]);
    }

    $row = v3sim_fetch_one($db, "SELECT * FROM pre_ride_email_v3_queue WHERE queue_status = 'live_submit_ready' ORDER BY pickup_datetime ASC, id ASC LIMIT 1");
    if (is_array($row)) {
        return $row;
    }

    return v3sim_fetch_one($db, 'SELECT * FROM pre_ride_email_v3_queue ORDER BY id DESC LIMIT 1');
}

/** @param array<string,mixed> $row @return array<string,mixed> */
function v3sim_enrich_row(array $row): array
{
    $pickup = v3sim_value($row['pickup_datetime'] ?? '');
    $minutes = null;
    if ($pickup !== '') {
        try {
            $diffSeconds = (new DateTimeImmutable($pickup))->getTimestamp() - time();
            $minutes = (int)floor($diffSeconds / 60);
        } catch (Throwable $e) {
            $minutes = null;
        }
    }
    $row['minutes_until_now'] = $minutes;
    return $row;
}

/** @param array<string,mixed> $row @return array<string,mixed> */
function v3sim_payload_check(array $row): array
{
    $values = [
        'lessor_id' => v3sim_value($row['lessor_id'] ?? ''),
        'driver_id' => v3sim_value($row['driver_id'] ?? ''),
        'vehicle_id' => v3sim_value($row['vehicle_id'] ?? ''),
        'starting_point_id' => v3sim_value($row['starting_point_id'] ?? ''),
        'customer_name' => v3sim_value($row['customer_name'] ?? ''),
        'customer_phone' => v3sim_value($row['customer_phone'] ?? ''),
        'pickup_datetime' => v3sim_value($row['pickup_datetime'] ?? ''),
        'estimated_end_datetime' => v3sim_value($row['estimated_end_datetime'] ?? ''),
        'pickup_address' => v3sim_value($row['pickup_address'] ?? ''),
        'dropoff_address' => v3sim_value($row['dropoff_address'] ?? ''),
        'price_amount' => v3sim_value($row['price_amount'] ?? ''),
        'payload_json' => v3sim_value($row['payload_json'] ?? ''),
    ];

    $missing = [];
    foreach ($values as $key => $value) {
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

/** @param array<string,mixed> $row @return array<string,string> */
function v3sim_edxeix_payload(array $row): array
{
    return [
        'lessor' => v3sim_value($row['lessor_id'] ?? ''),
        'driver' => v3sim_value($row['driver_id'] ?? ''),
        'vehicle' => v3sim_value($row['vehicle_id'] ?? ''),
        'starting_point_id' => v3sim_value($row['starting_point_id'] ?? ''),
        'lessee_name' => v3sim_value($row['customer_name'] ?? ''),
        'lessee_phone' => v3sim_value($row['customer_phone'] ?? ''),
        'boarding_point' => v3sim_value($row['pickup_address'] ?? ''),
        'disembark_point' => v3sim_value($row['dropoff_address'] ?? ''),
        'started_at' => v3sim_value($row['pickup_datetime'] ?? ''),
        'ended_at' => v3sim_value($row['estimated_end_datetime'] ?? ''),
        'price' => v3sim_value($row['price_amount'] ?? ''),
        'price_text' => v3sim_value($row['price_text'] ?? ''),
    ];
}

/** @param array<string,mixed> $row @return array<string,mixed> */
function v3sim_starting_point_check(mysqli $db, array $row): array
{
    $lessor = v3sim_value($row['lessor_id'] ?? '');
    $start = v3sim_value($row['starting_point_id'] ?? '');
    if ($lessor === '' || $start === '') {
        return ['ok' => false, 'label' => '', 'reason' => 'lessor_id or starting_point_id missing'];
    }
    if (!v3sim_table_exists($db, 'pre_ride_email_v3_starting_point_options')) {
        return ['ok' => false, 'label' => '', 'reason' => 'starting point options table missing'];
    }

    $row = v3sim_fetch_one(
        $db,
        'SELECT label FROM pre_ride_email_v3_starting_point_options WHERE edxeix_lessor_id = ? AND edxeix_starting_point_id = ? AND is_active = 1 LIMIT 1',
        [$lessor, $start]
    );
    if (!is_array($row)) {
        return ['ok' => false, 'label' => '', 'reason' => 'starting point not operator verified for lessor'];
    }

    return ['ok' => true, 'label' => (string)($row['label'] ?? ''), 'reason' => 'operator_verified'];
}

/** @param array<string,mixed> $row @return array<string,mixed> */
function v3sim_approval_check(mysqli $db, array $row): array
{
    $out = ['table_exists' => false, 'valid' => false, 'count' => 0, 'latest' => null, 'reason' => 'approval table missing'];
    if (!v3sim_table_exists($db, 'pre_ride_email_v3_live_submit_approvals')) {
        return $out;
    }
    $out['table_exists'] = true;
    $queueId = v3sim_value($row['id'] ?? '');
    $dedupe = v3sim_value($row['dedupe_key'] ?? '');

    $columns = v3sim_table_columns($db, 'pre_ride_email_v3_live_submit_approvals');
    $where = [];
    $params = [];
    if (isset($columns['queue_id']) && $queueId !== '') {
        $where[] = 'queue_id = ?';
        $params[] = $queueId;
    }
    if (isset($columns['dedupe_key']) && $dedupe !== '') {
        $where[] = 'dedupe_key = ?';
        $params[] = $dedupe;
    }
    if ($where === []) {
        $out['reason'] = 'approval table has no queue_id/dedupe_key match available';
        return $out;
    }

    $statusSql = isset($columns['approval_status']) ? " AND approval_status IN ('approved','valid','active')" : '';
    $scopeSql = isset($columns['approval_scope']) ? ' AND approval_scope = ?' : '';
    if ($scopeSql !== '') {
        $params[] = V3_ADAPTER_ROW_SIM_APPROVAL_SCOPE;
    }
    $revokedSql = isset($columns['revoked_at']) ? " AND (revoked_at IS NULL OR revoked_at = '0000-00-00 00:00:00')" : '';
    $expirySql = isset($columns['expires_at']) ? ' AND (expires_at IS NULL OR expires_at >= NOW())' : '';
    $order = isset($columns['approved_at']) ? 'approved_at DESC, id DESC' : 'id DESC';

    $countRow = v3sim_fetch_one($db, 'SELECT COUNT(*) AS c FROM pre_ride_email_v3_live_submit_approvals WHERE (' . implode(' OR ', $where) . ')', array_slice($params, 0, count($where)));
    $out['count'] = (int)($countRow['c'] ?? 0);

    $sql = 'SELECT * FROM pre_ride_email_v3_live_submit_approvals WHERE (' . implode(' OR ', $where) . ')' . $statusSql . $scopeSql . $revokedSql . $expirySql . ' ORDER BY ' . $order . ' LIMIT 1';
    $validRow = v3sim_fetch_one($db, $sql, $params);
    if (!is_array($validRow)) {
        $out['reason'] = 'no valid approval found';
        return $out;
    }

    $out['valid'] = true;
    $out['latest'] = $validRow;
    $out['reason'] = 'valid closed-gate rehearsal approval found';
    return $out;
}

/** @return array<string,mixed> */
function v3sim_package_artifacts(string $appRoot, string $queueId): array
{
    $dir = $appRoot . '/storage/artifacts/v3_live_submit_packages';
    $files = [];
    if ($queueId !== '' && is_dir($dir)) {
        $matches = glob($dir . '/queue_' . preg_replace('/[^0-9]/', '', $queueId) . '_*') ?: [];
        rsort($matches);
        foreach (array_slice($matches, 0, 8) as $path) {
            $files[] = basename($path);
        }
    }
    return [
        'artifact_dir' => $dir,
        'artifact_dir_exists' => is_dir($dir),
        'artifact_dir_writable' => is_dir($dir) && is_writable($dir),
        'queue_artifact_count' => count($files),
        'latest_queue_artifacts' => $files,
    ];
}

/** @param array<string,mixed> $context @param array<string,string> $payload @return array<string,mixed> */
function v3sim_adapter_simulation(string $appRoot, array $payload, array $context): array
{
    $src = $appRoot . '/src/BoltMailV3';
    $files = [
        'interface' => $src . '/LiveSubmitAdapterV3.php',
        'disabled' => $src . '/DisabledLiveSubmitAdapterV3.php',
        'dry_run' => $src . '/DryRunLiveSubmitAdapterV3.php',
        'edxeix_live' => $src . '/EdxeixLiveSubmitAdapterV3.php',
    ];
    $fileStatus = [];
    foreach ($files as $key => $path) {
        $readable = is_file($path) && is_readable($path);
        $fileStatus[$key] = ['path' => $path, 'exists' => is_file($path), 'readable' => $readable];
        if ($readable) {
            require_once $path;
        }
    }

    $class = 'Bridge\\BoltMailV3\\EdxeixLiveSubmitAdapterV3';
    $out = [
        'adapter_key' => 'edxeix_live',
        'class' => $class,
        'files' => $fileStatus,
        'class_exists' => class_exists($class),
        'instantiated' => false,
        'name' => '',
        'is_live_capable' => false,
        'submit_called' => false,
        'submit_returned' => false,
        'submitted' => false,
        'blocked' => true,
        'ok' => false,
        'reason' => '',
        'message' => '',
        'payload_sha256' => hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: ''),
        'safe_for_simulation' => false,
        'error' => '',
    ];

    if (!class_exists($class)) {
        $out['error'] = 'class_missing';
        return $out;
    }

    try {
        $adapter = new $class();
        $out['instantiated'] = true;
        if (!method_exists($adapter, 'name') || !method_exists($adapter, 'isLiveCapable') || !method_exists($adapter, 'submit')) {
            $out['error'] = 'contract_methods_missing';
            return $out;
        }
        $out['name'] = (string)$adapter->name();
        $out['is_live_capable'] = (bool)$adapter->isLiveCapable();
        $out['submit_called'] = true;
        $result = $adapter->submit($payload, $context);
        $out['submit_returned'] = true;
        $out['submitted'] = (bool)($result['submitted'] ?? false);
        $out['blocked'] = (bool)($result['blocked'] ?? false);
        $out['ok'] = (bool)($result['ok'] ?? false);
        $out['reason'] = (string)($result['reason'] ?? '');
        $out['message'] = (string)($result['message'] ?? '');
        $out['payload_sha256'] = (string)($result['payload_sha256'] ?? $out['payload_sha256']);
        $out['safe_for_simulation'] = ($out['submitted'] === false && $out['is_live_capable'] === false);
    } catch (Throwable $e) {
        $out['error'] = $e->getMessage();
    }

    return $out;
}

/** @return array<string,mixed> */
function v3sim_run(?int $queueId = null): array
{
    $appRoot = dirname(__DIR__);
    $report = v3sim_base_report($appRoot);

    try {
        $db = v3sim_db($report, $appRoot);
        $gate = v3sim_load_gate($appRoot);
        $report['gate'] = [
            'loaded' => $gate['loaded'],
            'enabled' => $gate['enabled'],
            'mode' => $gate['mode'],
            'adapter' => $gate['adapter'],
            'hard_enable_live_submit' => $gate['hard_enable_live_submit'],
            'acknowledgement_phrase_present' => $gate['acknowledgement_phrase_present'],
            'blocks' => $gate['blocks'],
        ];

        $row = v3sim_pick_row($db, $queueId);
        if (!is_array($row)) {
            $report['final_blocks'][] = 'queue: no V3 queue row selected';
            $report['adapter_simulation'] = v3sim_adapter_simulation($appRoot, [], ['queue_id' => 'none']);
            return $report;
        }

        $row = v3sim_enrich_row($row);
        $report['selected_queue_row'] = $row;
        $report['payload'] = v3sim_payload_check($row);
        $report['starting_point'] = v3sim_starting_point_check($db, $row);
        $report['approval'] = v3sim_approval_check($db, $row);
        $report['package_export'] = v3sim_package_artifacts($appRoot, v3sim_value($row['id'] ?? ''));

        $edxeixPayload = v3sim_edxeix_payload($row);
        $context = [
            'queue_id' => v3sim_value($row['id'] ?? ''),
            'dedupe_key' => v3sim_value($row['dedupe_key'] ?? ''),
            'lessor_id' => v3sim_value($row['lessor_id'] ?? ''),
            'vehicle_plate' => v3sim_value($row['vehicle_plate'] ?? ''),
            'simulation_only' => true,
            'source' => 'V3 adapter row simulation. No EDXEIX call allowed.',
        ];
        $report['edxeix_payload'] = $edxeixPayload;
        $report['adapter_simulation'] = v3sim_adapter_simulation($appRoot, $edxeixPayload, $context);

        $finalBlocks = [];
        $finalBlocks = array_merge($finalBlocks, (array)($gate['blocks'] ?? []));
        if (v3sim_value($row['queue_status'] ?? '') !== 'live_submit_ready') {
            $finalBlocks[] = 'queue: row is not live_submit_ready';
        }
        if (($row['minutes_until_now'] ?? null) === null || (int)$row['minutes_until_now'] < 0) {
            $finalBlocks[] = 'queue: pickup is not future-safe';
        }
        if (empty($report['payload']['complete'])) {
            $finalBlocks[] = 'payload: required fields missing';
        }
        if (empty($report['starting_point']['ok'])) {
            $finalBlocks[] = 'starting_point: not operator verified';
        }
        if (empty($report['approval']['valid'])) {
            $finalBlocks[] = 'approval: no valid closed-gate rehearsal approval found';
        }
        if (empty($report['adapter_simulation']['safe_for_simulation'])) {
            $finalBlocks[] = 'adapter: simulation was not safe';
        }
        if (!empty($report['adapter_simulation']['submitted'])) {
            $finalBlocks[] = 'adapter: UNSAFE submitted=true returned';
        }
        if (!empty($report['adapter_simulation']['is_live_capable'])) {
            $finalBlocks[] = 'adapter: UNSAFE adapter is live-capable during simulation phase';
        }

        $report['final_blocks'] = array_values(array_unique($finalBlocks));
        $report['simulation_safe'] = !empty($report['adapter_simulation']['safe_for_simulation']) && empty($report['adapter_simulation']['submitted']);
        $report['ok'] = !empty($report['simulation_safe']);
        $report['eligible_for_live_submit_now'] = false;
        return $report;
    } catch (Throwable $e) {
        v3sim_event($report, 'error', $e->getMessage());
        $report['final_blocks'][] = 'system: ' . $e->getMessage();
        return $report;
    }
}

/** @param array<string,mixed> $report */
function v3sim_print_human(array $report): void
{
    echo 'V3 adapter row simulation ' . (string)($report['version'] ?? V3_ADAPTER_ROW_SIM_VERSION) . PHP_EOL;
    echo 'Mode: ' . (string)($report['mode'] ?? '') . PHP_EOL;
    echo 'Safety: ' . (string)($report['safety'] ?? '') . PHP_EOL;
    echo 'Simulation safe: ' . (!empty($report['simulation_safe']) ? 'yes' : 'no') . PHP_EOL;
    echo 'OK: ' . (!empty($report['ok']) ? 'yes' : 'no') . PHP_EOL;

    $row = $report['selected_queue_row'] ?? null;
    if (is_array($row)) {
        echo 'Selected row: #' . v3sim_value($row['id'] ?? '') . ' status=' . v3sim_value($row['queue_status'] ?? '') . ' pickup=' . v3sim_value($row['pickup_datetime'] ?? '') . ' minutes_until=' . v3sim_value($row['minutes_until_now'] ?? '') . PHP_EOL;
        echo 'Transfer: ' . v3sim_value($row['customer_name'] ?? '') . ' | ' . v3sim_value($row['driver_name'] ?? '') . ' | ' . v3sim_value($row['vehicle_plate'] ?? '') . PHP_EOL;
        echo 'IDs: lessor=' . v3sim_value($row['lessor_id'] ?? '') . ' driver=' . v3sim_value($row['driver_id'] ?? '') . ' vehicle=' . v3sim_value($row['vehicle_id'] ?? '') . ' start=' . v3sim_value($row['starting_point_id'] ?? '') . PHP_EOL;
    } else {
        echo 'Selected row: none' . PHP_EOL;
    }

    $gate = $report['gate'] ?? [];
    if (is_array($gate)) {
        echo 'Gate: loaded=' . v3sim_bool($gate['loaded'] ?? false) . ' enabled=' . v3sim_bool($gate['enabled'] ?? false) . ' mode=' . v3sim_value($gate['mode'] ?? '') . ' adapter=' . v3sim_value($gate['adapter'] ?? '') . ' hard=' . v3sim_bool($gate['hard_enable_live_submit'] ?? false) . PHP_EOL;
    }

    $payload = $report['payload'] ?? [];
    if (is_array($payload)) {
        echo 'Payload: complete=' . v3sim_bool($payload['complete'] ?? false) . ' missing=' . implode(',', (array)($payload['missing'] ?? [])) . PHP_EOL;
    }

    $start = $report['starting_point'] ?? [];
    if (is_array($start)) {
        echo 'Starting point: ' . (!empty($start['ok']) ? 'verified' : 'not verified') . ' — ' . v3sim_value($start['reason'] ?? '') . PHP_EOL;
    }

    $approval = $report['approval'] ?? [];
    if (is_array($approval)) {
        echo 'Approval: valid=' . v3sim_bool($approval['valid'] ?? false) . ' count=' . v3sim_value($approval['count'] ?? 0) . ' reason=' . v3sim_value($approval['reason'] ?? '') . PHP_EOL;
    }

    $sim = $report['adapter_simulation'] ?? [];
    if (is_array($sim)) {
        echo 'Adapter simulation: class_exists=' . v3sim_bool($sim['class_exists'] ?? false) . ' instantiated=' . v3sim_bool($sim['instantiated'] ?? false) . ' live_capable=' . v3sim_bool($sim['is_live_capable'] ?? false) . ' submitted=' . v3sim_bool($sim['submitted'] ?? false) . ' safe=' . v3sim_bool($sim['safe_for_simulation'] ?? false) . PHP_EOL;
        echo 'Adapter message: ' . v3sim_value($sim['message'] ?? '') . PHP_EOL;
    }

    $pkg = $report['package_export'] ?? [];
    if (is_array($pkg)) {
        echo 'Package artifacts: count=' . v3sim_value($pkg['queue_artifact_count'] ?? 0) . ' dir=' . v3sim_value($pkg['artifact_dir'] ?? '') . PHP_EOL;
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

    if (!empty($report['events'])) {
        echo 'Events:' . PHP_EOL;
        foreach ((array)$report['events'] as $event) {
            if (is_array($event)) {
                echo '  - ' . (string)($event['level'] ?? 'info') . ': ' . (string)($event['message'] ?? '') . PHP_EOL;
            } else {
                echo '  - ' . (string)$event . PHP_EOL;
            }
        }
    }
}

function v3sim_cli(): int
{
    $argv = $_SERVER['argv'] ?? [];
    $json = v3sim_has_arg($argv, '--json');
    $queueIdRaw = v3sim_arg_value($argv, '--queue-id', '');
    $queueId = $queueIdRaw !== '' && ctype_digit($queueIdRaw) ? (int)$queueIdRaw : null;

    $report = v3sim_run($queueId);
    $report['finished_at'] = gmdate('c');

    if ($json) {
        echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        return !empty($report['ok']) ? 0 : 1;
    }

    v3sim_print_human($report);
    return !empty($report['ok']) ? 0 : 1;
}

if (PHP_SAPI === 'cli' && realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    exit(v3sim_cli());
}
