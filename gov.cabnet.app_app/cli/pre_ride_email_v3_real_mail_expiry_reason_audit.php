<?php
/**
 * gov.cabnet.app — V3 Real-Mail Expiry Reason Audit CLI.
 *
 * v3.1.4:
 * - Read-only alignment update for queue-health vs expiry-audit counts.
 * - Exposes possible-real total rows, canary rows, non-expired-guard possible-real rows,
 *   mapping-correction rows, and a safe mismatch explanation.
 * - No Bolt calls, no EDXEIX calls, no AADE calls, no DB writes, no queue mutations.
 */

declare(strict_types=1);

const GOV_V3_REAL_MAIL_EXPIRY_AUDIT_VERSION = 'v3.1.4-v3-real-mail-expiry-audit-alignment';

/** @return array<string,mixed> */
function gov_v3_expiry_roots(): array
{
    $appRoot = dirname(__DIR__);
    $homeRoot = dirname($appRoot);

    return [
        'app_root' => $appRoot,
        'home_root' => $homeRoot,
        'public_root' => $homeRoot . '/public_html/gov.cabnet.app',
        'config_root' => $homeRoot . '/gov.cabnet.app_config',
    ];
}

/** @return array<string,mixed> */
function gov_v3_expiry_db(): array
{
    $roots = gov_v3_expiry_roots();
    $bootstrapFile = $roots['app_root'] . '/src/bootstrap.php';

    if (!is_file($bootstrapFile)) {
        return ['ok' => false, 'error' => 'Missing bootstrap file: ' . $bootstrapFile, 'connection' => null];
    }

    try {
        $app = require $bootstrapFile;
        $db = is_array($app) ? ($app['db'] ?? null) : null;

        if (!is_object($db) || !method_exists($db, 'connection')) {
            return ['ok' => false, 'error' => 'Bootstrap loaded, but database service is unavailable.', 'connection' => null];
        }

        $mysqli = $db->connection();
        if (!$mysqli instanceof mysqli) {
            return ['ok' => false, 'error' => 'Database service did not return a mysqli connection.', 'connection' => null];
        }

        $dbName = '';
        try {
            $result = $mysqli->query('SELECT DATABASE() AS db');
            $row = $result ? $result->fetch_assoc() : null;
            $dbName = (string)($row['db'] ?? '');
        } catch (Throwable) {
            $dbName = '';
        }

        return [
            'ok' => true,
            'error' => '',
            'connection' => $mysqli,
            'name' => $dbName,
            'host' => (string)($mysqli->host_info ?? ''),
        ];
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => $e->getMessage(), 'connection' => null];
    }
}

function gov_v3_expiry_identifier(string $name): string
{
    if (!preg_match('/^[A-Za-z0-9_]+$/', $name)) {
        throw new InvalidArgumentException('Invalid SQL identifier.');
    }
    return '`' . $name . '`';
}

function gov_v3_expiry_table_exists(mysqli $mysqli, string $table): bool
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
function gov_v3_expiry_columns(mysqli $mysqli, string $table): array
{
    $columns = [];
    try {
        $sql = 'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION';
        $stmt = $mysqli->prepare($sql);
        if (!$stmt) { return []; }
        $stmt->bind_param('s', $table);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($result && ($row = $result->fetch_assoc())) {
            $name = (string)($row['COLUMN_NAME'] ?? '');
            if ($name !== '') { $columns[$name] = true; }
        }
        $stmt->close();
    } catch (Throwable) {
        return [];
    }

    return $columns;
}

/** @return array<string,mixed> */
function gov_v3_expiry_live_gate_state(): array
{
    $roots = gov_v3_expiry_roots();
    $file = $roots['config_root'] . '/pre_ride_email_v3_live_submit.php';

    $state = [
        'path' => $file,
        'exists' => is_file($file),
        'readable' => is_readable($file),
        'loaded' => false,
        'enabled' => false,
        'mode' => 'missing',
        'adapter' => 'missing',
        'hard_enable_live_submit' => false,
        'acknowledgement_phrase_present' => false,
        'expected_closed' => true,
        'live_risk_detected' => false,
        'error' => '',
    ];

    if (!is_file($file) || !is_readable($file)) {
        return $state;
    }

    try {
        $config = require $file;
        if (!is_array($config)) {
            $state['error'] = 'Config file did not return an array.';
            return $state;
        }

        $state['loaded'] = true;
        $state['enabled'] = (bool)($config['enabled'] ?? false);
        $state['mode'] = (string)($config['mode'] ?? 'unknown');
        $state['adapter'] = (string)($config['adapter'] ?? 'unknown');
        $state['hard_enable_live_submit'] = (bool)($config['hard_enable_live_submit'] ?? false);
        $state['acknowledgement_phrase_present'] = trim((string)($config['acknowledgement_phrase'] ?? '')) !== '';

        $state['expected_closed'] = (
            $state['enabled'] === false
            && $state['mode'] !== 'live'
            && $state['adapter'] !== 'edxeix_live'
            && $state['hard_enable_live_submit'] === false
        );
        $state['live_risk_detected'] = !$state['expected_closed'];
    } catch (Throwable $e) {
        $state['error'] = $e->getMessage();
    }

    return $state;
}

/** @return array<int,array<string,mixed>> */
function gov_v3_expiry_fetch_rows(mysqli $mysqli, string $table, array $columns, int $limit = 100): array
{
    $wanted = [
        'id', 'dedupe_key', 'order_reference', 'queue_status', 'customer_name', 'customer_phone',
        'driver_name', 'vehicle_plate', 'lessor_id', 'driver_id', 'vehicle_id', 'starting_point_id',
        'pickup_datetime', 'estimated_end_datetime', 'pickup_address', 'dropoff_address', 'price_amount',
        'source_mailbox', 'operator_note', 'created_at', 'updated_at', 'locked_at', 'submitted_at',
        'failed_at', 'last_error',
    ];

    $select = [];
    foreach ($wanted as $column) {
        if (!empty($columns[$column])) { $select[] = gov_v3_expiry_identifier($column); }
    }
    if ($select === []) { return []; }

    $where = [];
    if (!empty($columns['queue_status'])) {
        $where[] = "queue_status = 'blocked'";
        $where[] = "queue_status = 'failed'";
    }
    if (!empty($columns['last_error'])) {
        $where[] = "last_error IS NOT NULL AND last_error <> ''";
    }

    $whereSql = $where ? '(' . implode(' OR ', $where) . ')' : '1=1';
    $orderBy = !empty($columns['id']) ? '`id` DESC' : (!empty($columns['created_at']) ? '`created_at` DESC' : '1 DESC');

    $limit = max(1, min(250, $limit));
    $sql = 'SELECT ' . implode(', ', $select)
        . ' FROM ' . gov_v3_expiry_identifier($table)
        . ' WHERE ' . $whereSql
        . ' ORDER BY ' . $orderBy
        . ' LIMIT ' . $limit;

    try {
        $result = $mysqli->query($sql);
        if (!$result) { return []; }
        return $result->fetch_all(MYSQLI_ASSOC) ?: [];
    } catch (Throwable) {
        return [];
    }
}

/** @return array<string,mixed> */
function gov_v3_expiry_classify_row(array $row): array
{
    $status = (string)($row['queue_status'] ?? '');
    $lastError = (string)($row['last_error'] ?? '');
    $customer = (string)($row['customer_name'] ?? '');
    $orderRef = (string)($row['order_reference'] ?? '');
    $operatorNote = (string)($row['operator_note'] ?? '');
    $sourceMailbox = (string)($row['source_mailbox'] ?? '');
    $pickup = (string)($row['pickup_datetime'] ?? '');

    $canaryHaystack = $customer . ' ' . $orderRef . ' ' . $operatorNote . ' ' . $sourceMailbox;
    $isCanary = (bool)preg_match('/\bcanary\b|CANARY|V3 Canary/i', $canaryHaystack);
    $isPossibleRealMail = !$isCanary && ($customer !== '' || $orderRef !== '' || $sourceMailbox !== '');

    $classification = 'other';
    $safeInterpretation = 'Keep for review; no action recommended.';

    if (stripos($lastError, 'v3_queue_row_expired_pickup_not_future_safe') !== false) {
        $classification = 'expired_by_future_safety_guard';
        $safeInterpretation = 'Past pickup blocked by V3 future-safety guard; this is expected closed-gate behavior after pickup time passes.';
    } elseif (
        stripos($lastError, 'mapping correction') !== false
        || stripos($lastError, 'old starting_point_id') !== false
        || stripos($lastError, 'pickup is now past') !== false
    ) {
        $classification = 'mapping_correction_after_pickup_past';
        $safeInterpretation = 'Historical correction row; keep blocked and do not submit.';
    } elseif ($status === 'blocked' && trim($lastError) !== '') {
        $classification = 'blocked_with_other_error';
        $safeInterpretation = 'Blocked row with non-expiry error; review only.';
    } elseif ($status === 'blocked') {
        $classification = 'blocked_without_error_text';
        $safeInterpretation = 'Blocked row has no clear error text; review only.';
    }

    $minutesPast = null;
    $pickupFuture = null;
    if ($pickup !== '') {
        $pickupTs = strtotime($pickup);
        if ($pickupTs !== false) {
            $now = time();
            $minutesPast = (int)floor(($now - $pickupTs) / 60);
            $pickupFuture = $pickupTs > $now;
        }
    }

    return [
        'id' => isset($row['id']) ? (int)$row['id'] : null,
        'queue_status' => $status,
        'pickup_datetime' => $pickup,
        'minutes_past_pickup_now' => $minutesPast,
        'pickup_future_now' => $pickupFuture,
        'customer_name' => $customer,
        'driver_name' => (string)($row['driver_name'] ?? ''),
        'vehicle_plate' => (string)($row['vehicle_plate'] ?? ''),
        'order_reference' => $orderRef,
        'lessor_id' => (string)($row['lessor_id'] ?? ''),
        'driver_id' => (string)($row['driver_id'] ?? ''),
        'vehicle_id' => (string)($row['vehicle_id'] ?? ''),
        'starting_point_id' => (string)($row['starting_point_id'] ?? ''),
        'is_canary' => $isCanary,
        'possible_real_mail' => $isPossibleRealMail,
        'classification' => $classification,
        'last_error' => $lastError,
        'safe_interpretation' => $safeInterpretation,
        'submitted_at' => $row['submitted_at'] ?? null,
        'failed_at' => $row['failed_at'] ?? null,
        'created_at' => $row['created_at'] ?? null,
        'updated_at' => $row['updated_at'] ?? null,
    ];
}

/** @return array<string,mixed> */
function gov_v3_real_mail_expiry_reason_audit_run(): array
{
    $roots = gov_v3_expiry_roots();

    $report = [
        'ok' => false,
        'version' => GOV_V3_REAL_MAIL_EXPIRY_AUDIT_VERSION,
        'mode' => 'read_only_v3_real_mail_expiry_reason_audit_alignment',
        'started_at' => date('c'),
        'safety' => 'No Bolt call. No EDXEIX call. No AADE call. No database writes. No queue mutations. No production submission tables. V0 untouched.',
        'app_root' => $roots['app_root'],
        'public_root' => $roots['public_root'],
        'database' => ['connected' => false, 'name' => '', 'host' => '', 'error' => ''],
        'live_gate' => gov_v3_expiry_live_gate_state(),
        'queue' => ['table' => 'pre_ride_email_v3_queue', 'exists' => false, 'rows_reviewed' => 0, 'rows' => []],
        'summary' => [
            'rows_reviewed' => 0,
            'blocked_rows_reviewed' => 0,
            'expired_by_future_safety_guard' => 0,
            'mapping_correction_after_pickup_past' => 0,
            'blocked_with_other_error' => 0,
            'blocked_without_error_text' => 0,
            'possible_real_mail_rows' => 0,
            'canary_rows' => 0,
            'possible_real_mail_expired_guard_rows' => 0,
            'canary_expired_guard_rows' => 0,
            'possible_real_mail_non_expired_guard_rows' => 0,
            'possible_real_mail_mapping_correction_rows' => 0,
            'possible_real_mail_other_blocked_rows' => 0,
            'queue_health_vs_expiry_count_mismatch_explained' => false,
            'queue_health_vs_expiry_count_mismatch_note' => '',
            'future_pickup_rows_in_error_set' => 0,
            'submitted_rows_in_error_set' => 0,
            'failed_rows_in_error_set' => 0,
            'live_risk_detected' => false,
            'live_submit_recommended_now' => 0,
            'move_recommended_now' => 0,
            'delete_recommended_now' => 0,
        ],
        'classification_counts' => [],
        'warnings' => [],
        'recommended_next_step' => 'Continue observing real-mail intake. Do not submit, move, delete, redirect, or mutate queue rows.',
        'final_blocks' => [],
        'finished_at' => null,
    ];

    $db = gov_v3_expiry_db();
    $report['database']['connected'] = (bool)($db['ok'] ?? false);
    $report['database']['error'] = (string)($db['error'] ?? '');
    $report['database']['name'] = (string)($db['name'] ?? '');
    $report['database']['host'] = (string)($db['host'] ?? '');

    if (empty($db['ok']) || empty($db['connection']) || !($db['connection'] instanceof mysqli)) {
        $report['final_blocks'][] = 'database: unavailable';
        $report['finished_at'] = date('c');
        return $report;
    }

    $mysqli = $db['connection'];
    $table = 'pre_ride_email_v3_queue';
    $report['queue']['exists'] = gov_v3_expiry_table_exists($mysqli, $table);

    if (!$report['queue']['exists']) {
        $report['final_blocks'][] = 'queue: pre_ride_email_v3_queue table missing';
        $report['finished_at'] = date('c');
        return $report;
    }

    $columns = gov_v3_expiry_columns($mysqli, $table);
    $rows = gov_v3_expiry_fetch_rows($mysqli, $table, $columns, 100);

    $classified = [];
    foreach ($rows as $row) {
        $item = gov_v3_expiry_classify_row($row);
        $classified[] = $item;

        $report['summary']['rows_reviewed']++;
        if (($item['queue_status'] ?? '') === 'blocked') { $report['summary']['blocked_rows_reviewed']++; }
        if (!empty($item['pickup_future_now'])) { $report['summary']['future_pickup_rows_in_error_set']++; }
        if (!empty($item['submitted_at'])) { $report['summary']['submitted_rows_in_error_set']++; }
        if (!empty($item['failed_at'])) { $report['summary']['failed_rows_in_error_set']++; }
        if (!empty($item['possible_real_mail'])) { $report['summary']['possible_real_mail_rows']++; }
        if (!empty($item['is_canary'])) { $report['summary']['canary_rows']++; }

        $classification = (string)($item['classification'] ?? 'other');
        $report['classification_counts'][$classification] = (int)($report['classification_counts'][$classification] ?? 0) + 1;
        if (array_key_exists($classification, $report['summary'])) { $report['summary'][$classification]++; }

        if ($classification === 'expired_by_future_safety_guard' && !empty($item['possible_real_mail'])) {
            $report['summary']['possible_real_mail_expired_guard_rows']++;
        }
        if ($classification === 'expired_by_future_safety_guard' && !empty($item['is_canary'])) {
            $report['summary']['canary_expired_guard_rows']++;
        }
        if ($classification !== 'expired_by_future_safety_guard' && !empty($item['possible_real_mail'])) {
            $report['summary']['possible_real_mail_non_expired_guard_rows']++;
        }
        if ($classification === 'mapping_correction_after_pickup_past' && !empty($item['possible_real_mail'])) {
            $report['summary']['possible_real_mail_mapping_correction_rows']++;
        }
        if (!in_array($classification, ['expired_by_future_safety_guard', 'mapping_correction_after_pickup_past'], true) && !empty($item['possible_real_mail'])) {
            $report['summary']['possible_real_mail_other_blocked_rows']++;
        }
    }

    $report['queue']['rows_reviewed'] = count($classified);
    $report['queue']['rows'] = $classified;

    $possibleReal = (int)$report['summary']['possible_real_mail_rows'];
    $possibleRealExpired = (int)$report['summary']['possible_real_mail_expired_guard_rows'];
    $possibleRealNonExpired = (int)$report['summary']['possible_real_mail_non_expired_guard_rows'];
    $report['summary']['queue_health_vs_expiry_count_mismatch_explained'] = ($possibleReal === ($possibleRealExpired + $possibleRealNonExpired));
    $report['summary']['queue_health_vs_expiry_count_mismatch_note'] = 'Queue-health possible_real counts all non-canary rows. Expiry audit possible_real_expired counts only non-canary rows classified as expired_by_future_safety_guard. The difference is represented by possible_real_mail_non_expired_guard_rows.';

    $report['summary']['live_risk_detected'] = !empty($report['live_gate']['live_risk_detected']);
    if (!empty($report['summary']['live_risk_detected'])) {
        $report['final_blocks'][] = 'live_gate: unexpected open/risky posture detected';
    }

    if ((int)$report['summary']['future_pickup_rows_in_error_set'] > 0) {
        $report['warnings'][] = 'One or more error/blocked rows still has a future pickup; inspect before any future live-readiness rehearsal.';
    }
    if ((int)$report['summary']['possible_real_mail_other_blocked_rows'] > 0) {
        $report['warnings'][] = 'One or more possible-real rows is blocked by a non-expiry/non-mapping reason; review only.';
    }

    $report['ok'] = ($report['final_blocks'] === []);
    $report['finished_at'] = date('c');

    return $report;
}

/** @param array<int,string> $argv */
function gov_v3_real_mail_expiry_reason_audit_main(array $argv): void
{
    $json = in_array('--json', $argv, true);
    $report = gov_v3_real_mail_expiry_reason_audit_run();

    if ($json) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
        return;
    }

    echo 'V3 real-mail expiry reason audit ' . GOV_V3_REAL_MAIL_EXPIRY_AUDIT_VERSION . PHP_EOL;
    echo 'OK: ' . (!empty($report['ok']) ? 'yes' : 'no') . PHP_EOL;
    echo 'Rows reviewed: ' . (string)($report['summary']['rows_reviewed'] ?? 0) . PHP_EOL;
    echo 'Possible real rows: ' . (string)($report['summary']['possible_real_mail_rows'] ?? 0) . PHP_EOL;
    echo 'Expired possible real rows: ' . (string)($report['summary']['possible_real_mail_expired_guard_rows'] ?? 0) . PHP_EOL;
    echo 'Non-expired-guard possible real rows: ' . (string)($report['summary']['possible_real_mail_non_expired_guard_rows'] ?? 0) . PHP_EOL;
    echo 'Live risk: ' . (!empty($report['summary']['live_risk_detected']) ? 'yes' : 'no') . PHP_EOL;
    echo 'Final blocks: ' . implode('; ', $report['final_blocks'] ?? []) . PHP_EOL;
}

if (PHP_SAPI === 'cli' && realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    gov_v3_real_mail_expiry_reason_audit_main($argv ?? []);
}
