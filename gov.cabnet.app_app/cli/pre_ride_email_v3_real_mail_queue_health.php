<?php
/**
 * gov.cabnet.app — V3 real-mail intake + queue health audit CLI.
 *
 * v3.1.0:
 * - Read-only queue health snapshot for V3 pre-ride email automation.
 * - Distinguishes generated canary rows from possible real-mail rows.
 * - Confirms live-submit gate posture without exposing secrets.
 * - Does not call Bolt, EDXEIX, AADE, or mutate the database/filesystem.
 */

declare(strict_types=1);

const GOV_V3_REAL_MAIL_QUEUE_HEALTH_VERSION = 'v3.1.0-v3-real-mail-queue-health';
const GOV_V3_REAL_MAIL_QUEUE_HEALTH_MODE = 'read_only_v3_real_mail_intake_queue_health';
const GOV_V3_REAL_MAIL_QUEUE_HEALTH_SAFETY = 'No Bolt call. No EDXEIX call. No AADE call. No DB writes. No queue status changes. No filesystem writes. Read-only queue/config inspection only.';

/** @return array<int,string> */
function gov_v3rm_args(array $argv): array
{
    $out = [];
    foreach ($argv as $arg) {
        if (is_string($arg) && str_starts_with($arg, '--')) {
            $out[] = $arg;
        }
    }
    return $out;
}

function gov_v3rm_app_root(): string
{
    return dirname(__DIR__);
}

function gov_v3rm_home_root(): string
{
    return dirname(gov_v3rm_app_root());
}

function gov_v3rm_public_root(): string
{
    return gov_v3rm_home_root() . '/public_html/gov.cabnet.app';
}

function gov_v3rm_config_root(): string
{
    return gov_v3rm_home_root() . '/gov.cabnet.app_config';
}

/** @return array<string,mixed> */
function gov_v3rm_db(): array
{
    $bootstrapFile = gov_v3rm_app_root() . '/src/bootstrap.php';
    $out = [
        'ok' => false,
        'error' => null,
        'connection' => null,
        'bootstrap_file' => $bootstrapFile,
    ];

    if (!is_file($bootstrapFile)) {
        $out['error'] = 'Missing bootstrap file.';
        return $out;
    }

    try {
        $app = require $bootstrapFile;
        if (!is_array($app)) {
            $out['error'] = 'Bootstrap did not return an app array.';
            return $out;
        }
        $db = $app['db'] ?? null;
        if (!is_object($db) || !method_exists($db, 'connection')) {
            $out['error'] = 'Database service is unavailable from bootstrap.';
            return $out;
        }
        $mysqli = $db->connection();
        if (!$mysqli instanceof mysqli) {
            $out['error'] = 'Database service did not return mysqli.';
            return $out;
        }
        $out['ok'] = true;
        $out['connection'] = $mysqli;
        return $out;
    } catch (Throwable $e) {
        $out['error'] = $e->getMessage();
        return $out;
    }
}

function gov_v3rm_identifier(string $name): string
{
    if (!preg_match('/^[A-Za-z0-9_]+$/', $name)) {
        throw new InvalidArgumentException('Invalid SQL identifier.');
    }
    return '`' . $name . '`';
}

function gov_v3rm_table_exists(mysqli $mysqli, string $table): bool
{
    try {
        $sql = 'SELECT COUNT(*) AS c FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?';
        $stmt = $mysqli->prepare($sql);
        if (!$stmt) { return false; }
        $stmt->bind_param('s', $table);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        $stmt->close();
        return (int)($row['c'] ?? 0) > 0;
    } catch (Throwable) {
        return false;
    }
}

/** @return array<string,bool> */
function gov_v3rm_columns(mysqli $mysqli, string $table): array
{
    $cols = [];
    try {
        $sql = 'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION';
        $stmt = $mysqli->prepare($sql);
        if (!$stmt) { return $cols; }
        $stmt->bind_param('s', $table);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $name = (string)($row['COLUMN_NAME'] ?? '');
                if ($name !== '') { $cols[$name] = true; }
            }
        }
        $stmt->close();
    } catch (Throwable) {
        return $cols;
    }
    return $cols;
}

function gov_v3rm_has(array $columns, string $name): bool
{
    return !empty($columns[$name]);
}

function gov_v3rm_count(mysqli $mysqli, string $table, string $where = '1=1'): int
{
    try {
        $sql = 'SELECT COUNT(*) AS c FROM ' . gov_v3rm_identifier($table) . ' WHERE ' . $where;
        $result = $mysqli->query($sql);
        $row = $result ? $result->fetch_assoc() : null;
        return (int)($row['c'] ?? 0);
    } catch (Throwable) {
        return 0;
    }
}

function gov_v3rm_scalar(mysqli $mysqli, string $sql): ?string
{
    try {
        $result = $mysqli->query($sql);
        if (!$result) { return null; }
        $row = $result->fetch_row();
        if (!$row) { return null; }
        $v = $row[0] ?? null;
        return $v === null ? null : (string)$v;
    } catch (Throwable) {
        return null;
    }
}

/** @return array<int,array<string,mixed>> */
function gov_v3rm_recent_rows(mysqli $mysqli, string $table, array $columns, int $limit = 25): array
{
    $wanted = [
        'id', 'queue_status', 'parser_ok', 'mapping_ok', 'future_ok', 'customer_name',
        'customer_phone', 'order_reference', 'source_mailbox', 'source_mtime', 'source_hash',
        'email_hash', 'pickup_datetime', 'estimated_end_datetime', 'driver_name', 'vehicle_plate',
        'lessor_id', 'driver_id', 'vehicle_id', 'starting_point_id', 'price_amount', 'queued_at',
        'locked_at', 'submitted_at', 'failed_at', 'last_error', 'created_at', 'updated_at',
    ];
    $select = [];
    foreach ($wanted as $name) {
        if (gov_v3rm_has($columns, $name)) {
            $select[] = gov_v3rm_identifier($name);
        }
    }
    if ($select === []) { return []; }

    $order = gov_v3rm_has($columns, 'id') ? '`id` DESC' : (gov_v3rm_has($columns, 'created_at') ? '`created_at` DESC' : '1 DESC');
    $limit = max(1, min(100, $limit));

    try {
        $sql = 'SELECT ' . implode(', ', $select) . ' FROM ' . gov_v3rm_identifier($table) . ' ORDER BY ' . $order . ' LIMIT ' . $limit;
        $result = $mysqli->query($sql);
        if (!$result) { return []; }
        return $result->fetch_all(MYSQLI_ASSOC) ?: [];
    } catch (Throwable) {
        return [];
    }
}

function gov_v3rm_is_canary(array $row): bool
{
    $haystack = implode(' ', [
        (string)($row['customer_name'] ?? ''),
        (string)($row['order_reference'] ?? ''),
        (string)($row['source_mailbox'] ?? ''),
        (string)($row['last_error'] ?? ''),
    ]);
    return (bool)preg_match('/\bcanary\b|CANARY|V3 Canary/i', $haystack);
}

function gov_v3rm_is_possible_real_mail(array $row): bool
{
    $source = (string)($row['source_mailbox'] ?? '');
    if ($source === '') { return !gov_v3rm_is_canary($row); }
    if (gov_v3rm_is_canary($row)) { return false; }
    return true;
}

/** @return array<string,mixed> */
function gov_v3rm_live_gate_state(): array
{
    $file = gov_v3rm_config_root() . '/pre_ride_email_v3_live_submit.php';
    $state = [
        'path' => $file,
        'exists' => is_file($file),
        'readable' => is_readable($file),
        'loaded' => false,
        'returned_array' => false,
        'enabled' => false,
        'mode' => 'missing',
        'adapter' => 'missing',
        'hard_enable_live_submit' => false,
        'acknowledgement_phrase_present' => false,
        'acknowledgement_matches_required' => false,
        'expected_closed_pre_live_posture' => false,
        'live_risk_detected' => false,
        'blocks' => [],
        'error' => '',
    ];

    if (!is_file($file) || !is_readable($file)) {
        $state['blocks'][] = 'live submit config missing or unreadable';
        return $state;
    }

    try {
        $config = require $file;
        $state['loaded'] = true;
        if (!is_array($config)) {
            $state['error'] = 'Config did not return an array.';
            $state['blocks'][] = 'live submit config did not return array';
            return $state;
        }
        $state['returned_array'] = true;
        $enabled = (bool)($config['enabled'] ?? false);
        $mode = (string)($config['mode'] ?? 'disabled');
        $adapter = (string)($config['adapter'] ?? 'disabled');
        $hardEnable = (bool)($config['hard_enable_live_submit'] ?? false);
        $ack = trim((string)($config['acknowledgement_phrase'] ?? ''));
        $requiredAck = trim((string)($config['required_acknowledgement_phrase'] ?? ''));

        $state['enabled'] = $enabled;
        $state['mode'] = $mode;
        $state['adapter'] = $adapter;
        $state['hard_enable_live_submit'] = $hardEnable;
        $state['acknowledgement_phrase_present'] = ($ack !== '');
        $state['acknowledgement_matches_required'] = ($requiredAck !== '' && hash_equals($requiredAck, $ack));
        $state['expected_closed_pre_live_posture'] = ($enabled === false && $mode !== 'live' && $adapter !== 'edxeix_live' && $hardEnable === false);
        $state['live_risk_detected'] = ($enabled === true || $mode === 'live' || $adapter === 'edxeix_live' || $hardEnable === true);

        if (!$state['expected_closed_pre_live_posture']) {
            if ($enabled !== false) { $state['blocks'][] = 'master_gate: enabled is not false'; }
            if ($mode === 'live') { $state['blocks'][] = 'master_gate: mode is live'; }
            if ($adapter === 'edxeix_live') { $state['blocks'][] = 'master_gate: adapter is edxeix_live'; }
            if ($hardEnable !== false) { $state['blocks'][] = 'master_gate: hard_enable_live_submit is not false'; }
        }
    } catch (Throwable $e) {
        $state['error'] = $e->getMessage();
        $state['blocks'][] = 'live submit config load error';
    }

    return $state;
}

/** @return array<string,mixed> */
function gov_v3rm_queue_health(mysqli $mysqli, string $queueTable, array $columns): array
{
    $terminal = "('blocked','submitted','cancelled','expired')";
    $health = [
        'total_rows' => gov_v3rm_count($mysqli, $queueTable),
        'status_counts' => [],
        'future_active_rows' => 0,
        'past_ready_rows' => 0,
        'rows_with_last_error' => 0,
        'stale_locked_rows' => 0,
        'missing_required_field_rows' => 0,
        'last_source_mtime' => null,
        'last_queued_at' => null,
        'last_created_at' => null,
        'latest_rows_loaded' => 0,
    ];

    if (gov_v3rm_has($columns, 'queue_status')) {
        foreach (['new','parsed','mapped','submit_dry_run_ready','live_submit_ready','blocked','submitted','cancelled','expired','failed'] as $status) {
            $health['status_counts'][$status] = gov_v3rm_count($mysqli, $queueTable, "queue_status = '" . $mysqli->real_escape_string($status) . "'");
        }
    }

    if (gov_v3rm_has($columns, 'pickup_datetime') && gov_v3rm_has($columns, 'queue_status')) {
        $health['future_active_rows'] = gov_v3rm_count($mysqli, $queueTable, 'queue_status NOT IN ' . $terminal . ' AND pickup_datetime > NOW()');
        $health['past_ready_rows'] = gov_v3rm_count($mysqli, $queueTable, "queue_status IN ('submit_dry_run_ready','live_submit_ready') AND pickup_datetime <= NOW()");
    }

    if (gov_v3rm_has($columns, 'last_error')) {
        $health['rows_with_last_error'] = gov_v3rm_count($mysqli, $queueTable, "last_error IS NOT NULL AND TRIM(last_error) <> ''");
    }

    if (gov_v3rm_has($columns, 'locked_at') && gov_v3rm_has($columns, 'queue_status')) {
        $submittedClause = gov_v3rm_has($columns, 'submitted_at') ? ' AND submitted_at IS NULL' : '';
        $failedClause = gov_v3rm_has($columns, 'failed_at') ? ' AND failed_at IS NULL' : '';
        $health['stale_locked_rows'] = gov_v3rm_count($mysqli, $queueTable, 'locked_at IS NOT NULL AND locked_at < DATE_SUB(NOW(), INTERVAL 30 MINUTE) AND queue_status NOT IN ' . $terminal . $submittedClause . $failedClause);
    }

    $required = ['lessor_id','driver_id','vehicle_id','starting_point_id','customer_name','customer_phone','pickup_datetime','estimated_end_datetime','pickup_address','dropoff_address','price_amount','payload_json'];
    $conditions = [];
    foreach ($required as $col) {
        if (gov_v3rm_has($columns, $col)) {
            $conditions[] = '(' . gov_v3rm_identifier($col) . ' IS NULL OR TRIM(CAST(' . gov_v3rm_identifier($col) . ' AS CHAR)) = \'\')';
        }
    }
    if ($conditions !== []) {
        $scope = gov_v3rm_has($columns, 'queue_status') ? "queue_status IN ('submit_dry_run_ready','live_submit_ready','mapped','parsed','new') AND " : '';
        $health['missing_required_field_rows'] = gov_v3rm_count($mysqli, $queueTable, $scope . '(' . implode(' OR ', $conditions) . ')');
    }

    if (gov_v3rm_has($columns, 'source_mtime')) {
        $health['last_source_mtime'] = gov_v3rm_scalar($mysqli, 'SELECT MAX(source_mtime) FROM ' . gov_v3rm_identifier($queueTable));
    }
    if (gov_v3rm_has($columns, 'queued_at')) {
        $health['last_queued_at'] = gov_v3rm_scalar($mysqli, 'SELECT MAX(queued_at) FROM ' . gov_v3rm_identifier($queueTable));
    }
    if (gov_v3rm_has($columns, 'created_at')) {
        $health['last_created_at'] = gov_v3rm_scalar($mysqli, 'SELECT MAX(created_at) FROM ' . gov_v3rm_identifier($queueTable));
    }

    return $health;
}

/** @return array<string,mixed> */
function gov_pre_ride_email_v3_real_mail_queue_health_run(): array
{
    $started = gmdate('c');
    $queueTable = 'pre_ride_email_v3_queue';
    $approvalTable = 'pre_ride_email_v3_live_submit_approvals';
    $finalBlocks = [];
    $warnings = [];
    $events = [];

    $gate = gov_v3rm_live_gate_state();
    if (!empty($gate['live_risk_detected'])) {
        $finalBlocks[] = 'live gate risk detected: config is no longer in expected closed pre-live posture';
    }

    $db = gov_v3rm_db();
    $database = [
        'connected' => false,
        'error' => $db['error'] ?? null,
        'name' => null,
        'host' => null,
    ];

    $queue = [
        'table' => $queueTable,
        'exists' => false,
        'columns' => [],
        'health' => [],
        'latest_rows' => [],
        'latest_row_classification' => [],
        'possible_real_mail_recent_count' => 0,
        'canary_recent_count' => 0,
        'submitted_recent_count' => 0,
        'failed_recent_count' => 0,
    ];
    $approvals = ['table' => $approvalTable, 'exists' => false, 'latest_count' => 0];

    if (empty($db['ok']) || !($db['connection'] ?? null) instanceof mysqli) {
        $finalBlocks[] = 'database connection unavailable';
    } else {
        /** @var mysqli $mysqli */
        $mysqli = $db['connection'];
        $database['connected'] = true;
        $database['name'] = gov_v3rm_scalar($mysqli, 'SELECT DATABASE()');
        $database['host'] = gov_v3rm_scalar($mysqli, 'SELECT @@hostname');

        $queue['exists'] = gov_v3rm_table_exists($mysqli, $queueTable);
        $approvals['exists'] = gov_v3rm_table_exists($mysqli, $approvalTable);

        if (!$queue['exists']) {
            $finalBlocks[] = 'V3 queue table missing';
        } else {
            $columns = gov_v3rm_columns($mysqli, $queueTable);
            $queue['columns'] = array_keys($columns);
            $queue['health'] = gov_v3rm_queue_health($mysqli, $queueTable, $columns);
            $recent = gov_v3rm_recent_rows($mysqli, $queueTable, $columns, 25);
            $queue['health']['latest_rows_loaded'] = count($recent);

            foreach ($recent as $row) {
                $id = (string)($row['id'] ?? '');
                $isCanary = gov_v3rm_is_canary($row);
                $possibleReal = gov_v3rm_is_possible_real_mail($row);
                if ($isCanary) { $queue['canary_recent_count']++; }
                if ($possibleReal) { $queue['possible_real_mail_recent_count']++; }
                if ((string)($row['submitted_at'] ?? '') !== '') { $queue['submitted_recent_count']++; }
                if ((string)($row['failed_at'] ?? '') !== '') { $queue['failed_recent_count']++; }
                $queue['latest_row_classification'][] = [
                    'id' => $id !== '' ? (int)$id : null,
                    'queue_status' => (string)($row['queue_status'] ?? ''),
                    'customer_name' => (string)($row['customer_name'] ?? ''),
                    'order_reference' => (string)($row['order_reference'] ?? ''),
                    'pickup_datetime' => (string)($row['pickup_datetime'] ?? ''),
                    'source_mtime' => (string)($row['source_mtime'] ?? ''),
                    'queued_at' => (string)($row['queued_at'] ?? ''),
                    'is_canary' => $isCanary,
                    'possible_real_mail' => $possibleReal,
                    'submitted_at' => $row['submitted_at'] ?? null,
                    'failed_at' => $row['failed_at'] ?? null,
                    'last_error' => $row['last_error'] ?? null,
                ];
            }
            $queue['latest_rows'] = array_slice($recent, 0, 10);

            $health = $queue['health'];
            if ((int)($health['past_ready_rows'] ?? 0) > 0) {
                $warnings[] = 'There are ready rows with pickup_datetime in the past. They must not be submitted.';
            }
            if ((int)($health['stale_locked_rows'] ?? 0) > 0) {
                $warnings[] = 'There are stale locked rows. Review before any automation changes.';
            }
            if ((int)($health['missing_required_field_rows'] ?? 0) > 0) {
                $warnings[] = 'Some active/ready rows are missing required payload fields.';
            }
        }

        if ($approvals['exists']) {
            $approvals['latest_count'] = gov_v3rm_count($mysqli, $approvalTable);
        }
    }

    $health = is_array($queue['health'] ?? null) ? $queue['health'] : [];
    $statusCounts = is_array($health['status_counts'] ?? null) ? $health['status_counts'] : [];
    $summary = [
        'queue_table_exists' => (bool)$queue['exists'],
        'total_rows' => (int)($health['total_rows'] ?? 0),
        'latest_rows_loaded' => (int)($health['latest_rows_loaded'] ?? 0),
        'possible_real_mail_recent_count' => (int)$queue['possible_real_mail_recent_count'],
        'canary_recent_count' => (int)$queue['canary_recent_count'],
        'future_active_rows' => (int)($health['future_active_rows'] ?? 0),
        'live_submit_ready' => (int)($statusCounts['live_submit_ready'] ?? 0),
        'submit_dry_run_ready' => (int)($statusCounts['submit_dry_run_ready'] ?? 0),
        'past_ready_rows' => (int)($health['past_ready_rows'] ?? 0),
        'stale_locked_rows' => (int)($health['stale_locked_rows'] ?? 0),
        'missing_required_field_rows' => (int)($health['missing_required_field_rows'] ?? 0),
        'rows_with_last_error' => (int)($health['rows_with_last_error'] ?? 0),
        'submitted_recent_count' => (int)$queue['submitted_recent_count'],
        'failed_recent_count' => (int)$queue['failed_recent_count'],
        'live_gate_expected_closed' => !empty($gate['expected_closed_pre_live_posture']),
        'live_risk_detected' => !empty($gate['live_risk_detected']),
        'move_recommended_now' => 0,
        'delete_recommended_now' => 0,
        'live_submit_recommended_now' => 0,
    ];

    if (!$summary['live_gate_expected_closed']) {
        $warnings[] = 'Live gate is not in expected closed pre-live posture.';
    }

    return [
        'ok' => $finalBlocks === [],
        'version' => GOV_V3_REAL_MAIL_QUEUE_HEALTH_VERSION,
        'mode' => GOV_V3_REAL_MAIL_QUEUE_HEALTH_MODE,
        'started_at' => $started,
        'safety' => GOV_V3_REAL_MAIL_QUEUE_HEALTH_SAFETY,
        'app_root' => gov_v3rm_app_root(),
        'public_root' => gov_v3rm_public_root(),
        'database' => $database,
        'live_gate' => $gate,
        'queue' => $queue,
        'approvals' => $approvals,
        'summary' => $summary,
        'warnings' => array_values(array_unique($warnings)),
        'events' => $events,
        'recommended_next_step' => 'Observe next real Bolt pre-ride email intake. Keep V3 closed-gate. Do not submit to EDXEIX.',
        'final_blocks' => $finalBlocks,
        'finished_at' => gmdate('c'),
    ];
}

function gov_v3rm_print_text(array $report): void
{
    $summary = is_array($report['summary'] ?? null) ? $report['summary'] : [];
    echo 'V3 Real-Mail Queue Health ' . GOV_V3_REAL_MAIL_QUEUE_HEALTH_VERSION . PHP_EOL;
    echo 'Safety: ' . GOV_V3_REAL_MAIL_QUEUE_HEALTH_SAFETY . PHP_EOL;
    echo 'OK: ' . (!empty($report['ok']) ? 'yes' : 'no') . PHP_EOL;
    echo 'Queue table exists: ' . (!empty($summary['queue_table_exists']) ? 'yes' : 'no') . PHP_EOL;
    echo 'Total rows: ' . (string)($summary['total_rows'] ?? 0) . PHP_EOL;
    echo 'Latest rows loaded: ' . (string)($summary['latest_rows_loaded'] ?? 0) . PHP_EOL;
    echo 'Possible real-mail recent rows: ' . (string)($summary['possible_real_mail_recent_count'] ?? 0) . PHP_EOL;
    echo 'Canary recent rows: ' . (string)($summary['canary_recent_count'] ?? 0) . PHP_EOL;
    echo 'Future active rows: ' . (string)($summary['future_active_rows'] ?? 0) . PHP_EOL;
    echo 'Live-submit-ready rows: ' . (string)($summary['live_submit_ready'] ?? 0) . PHP_EOL;
    echo 'Past ready rows: ' . (string)($summary['past_ready_rows'] ?? 0) . PHP_EOL;
    echo 'Live risk detected: ' . (!empty($summary['live_risk_detected']) ? 'yes' : 'no') . PHP_EOL;
    $blocks = is_array($report['final_blocks'] ?? null) ? $report['final_blocks'] : [];
    echo 'Final blocks: ' . json_encode($blocks, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
}

function gov_v3rm_main(array $argv): int
{
    $args = gov_v3rm_args($argv);
    $report = gov_pre_ride_email_v3_real_mail_queue_health_run();
    if (in_array('--json', $args, true)) {
        echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    } else {
        gov_v3rm_print_text($report);
    }
    return !empty($report['ok']) ? 0 : 1;
}

if (PHP_SAPI === 'cli' && realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === realpath(__FILE__)) {
    exit(gov_v3rm_main($argv));
}
