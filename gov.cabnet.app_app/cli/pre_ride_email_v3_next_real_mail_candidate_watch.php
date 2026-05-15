<?php
/**
 * gov.cabnet.app — V3 Next Real-Mail Candidate Watch CLI.
 *
 * v3.1.5:
 * - Read-only watcher for the next possible real V3 pre-ride email candidate.
 * - Highlights future/unsubmitted possible-real queue rows before they expire.
 * - Confirms closed live-gate posture.
 * - No Bolt calls, no EDXEIX calls, no AADE calls, no DB writes, no queue mutations.
 */

declare(strict_types=1);

const GOV_V3_NEXT_REAL_MAIL_CANDIDATE_WATCH_VERSION = 'v3.1.5-v3-next-real-mail-candidate-watch';
const GOV_V3_NEXT_REAL_MAIL_CANDIDATE_WATCH_MODE = 'read_only_v3_next_real_mail_candidate_watch';
const GOV_V3_NEXT_REAL_MAIL_CANDIDATE_WATCH_SAFETY = 'No Bolt call. No EDXEIX call. No AADE call. No DB writes. No queue status changes. No filesystem writes. Read-only queue/config inspection only.';

function gov_v3nrm_app_root(): string
{
    return dirname(__DIR__);
}

function gov_v3nrm_home_root(): string
{
    return dirname(gov_v3nrm_app_root());
}

function gov_v3nrm_public_root(): string
{
    return gov_v3nrm_home_root() . '/public_html/gov.cabnet.app';
}

function gov_v3nrm_config_root(): string
{
    return gov_v3nrm_home_root() . '/gov.cabnet.app_config';
}

/** @return array<string,mixed> */
function gov_v3nrm_db(): array
{
    $bootstrapFile = gov_v3nrm_app_root() . '/src/bootstrap.php';
    $out = [
        'ok' => false,
        'error' => '',
        'connection' => null,
        'name' => '',
        'host' => '',
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
        $out['host'] = (string)($mysqli->host_info ?? '');
        $res = $mysqli->query('SELECT DATABASE() AS db');
        $row = $res ? $res->fetch_assoc() : null;
        $out['name'] = (string)($row['db'] ?? '');
        return $out;
    } catch (Throwable $e) {
        $out['error'] = $e->getMessage();
        return $out;
    }
}

function gov_v3nrm_identifier(string $name): string
{
    if (!preg_match('/^[A-Za-z0-9_]+$/', $name)) {
        throw new InvalidArgumentException('Invalid SQL identifier.');
    }
    return '`' . $name . '`';
}

function gov_v3nrm_table_exists(mysqli $mysqli, string $table): bool
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
function gov_v3nrm_columns(mysqli $mysqli, string $table): array
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

function gov_v3nrm_has(array $columns, string $name): bool
{
    return !empty($columns[$name]);
}

function gov_v3nrm_count(mysqli $mysqli, string $table, string $where = '1=1'): int
{
    try {
        $sql = 'SELECT COUNT(*) AS c FROM ' . gov_v3nrm_identifier($table) . ' WHERE ' . $where;
        $result = $mysqli->query($sql);
        $row = $result ? $result->fetch_assoc() : null;
        return (int)($row['c'] ?? 0);
    } catch (Throwable) {
        return 0;
    }
}

/** @return array<string,mixed> */
function gov_v3nrm_live_gate_state(): array
{
    $file = gov_v3nrm_config_root() . '/pre_ride_email_v3_live_submit.php';
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
        $state['enabled'] = (bool)($config['enabled'] ?? false);
        $state['mode'] = (string)($config['mode'] ?? 'disabled');
        $state['adapter'] = (string)($config['adapter'] ?? 'disabled');
        $state['hard_enable_live_submit'] = (bool)($config['hard_enable_live_submit'] ?? false);
        $state['acknowledgement_phrase_present'] = trim((string)($config['acknowledgement_phrase'] ?? '')) !== '';

        $state['expected_closed_pre_live_posture'] = (
            $state['enabled'] === false
            && $state['mode'] !== 'live'
            && $state['adapter'] !== 'edxeix_live'
            && $state['hard_enable_live_submit'] === false
        );

        if ($state['enabled'] !== false) { $state['blocks'][] = 'master_gate: enabled is not false'; }
        if ($state['mode'] === 'live') { $state['blocks'][] = 'master_gate: mode is live'; }
        if ($state['adapter'] === 'edxeix_live') { $state['blocks'][] = 'master_gate: adapter is edxeix_live'; }
        if ($state['hard_enable_live_submit'] !== false) { $state['blocks'][] = 'master_gate: hard_enable_live_submit is not false'; }
        $state['live_risk_detected'] = !$state['expected_closed_pre_live_posture'];
    } catch (Throwable $e) {
        $state['error'] = $e->getMessage();
        $state['blocks'][] = 'live submit config load error';
    }

    return $state;
}

function gov_v3nrm_is_canary(array $row): bool
{
    $haystack = implode(' ', [
        (string)($row['customer_name'] ?? ''),
        (string)($row['order_reference'] ?? ''),
        (string)($row['source_mailbox'] ?? ''),
        (string)($row['operator_note'] ?? ''),
        (string)($row['last_error'] ?? ''),
    ]);
    return (bool)preg_match('/\bcanary\b|CANARY|V3 Canary/i', $haystack);
}

function gov_v3nrm_pickup_timestamp(?string $pickup): ?int
{
    $pickup = trim((string)$pickup);
    if ($pickup === '') { return null; }
    $ts = strtotime($pickup);
    return $ts === false ? null : $ts;
}

function gov_v3nrm_minutes_until(?string $pickup): ?int
{
    $ts = gov_v3nrm_pickup_timestamp($pickup);
    if ($ts === null) { return null; }
    return (int)floor(($ts - time()) / 60);
}

/** @return array<int,string> */
function gov_v3nrm_missing_required(array $row, array $columns): array
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
    ];
    if (gov_v3nrm_has($columns, 'payload_json')) {
        $required[] = 'payload_json';
    }

    $missing = [];
    foreach ($required as $field) {
        if (array_key_exists($field, $row) && trim((string)($row[$field] ?? '')) === '') {
            $missing[] = $field;
        }
    }
    return $missing;
}

/** @return array<int,array<string,mixed>> */
function gov_v3nrm_fetch_recent_rows(mysqli $mysqli, string $table, array $columns, int $limit = 100): array
{
    $wanted = [
        'id', 'dedupe_key', 'source_mailbox', 'source_mtime', 'source_hash', 'email_hash',
        'order_reference', 'queue_status', 'parser_ok', 'mapping_ok', 'future_ok', 'lessor_id',
        'lessor_source', 'driver_id', 'vehicle_id', 'starting_point_id', 'customer_name',
        'customer_phone', 'driver_name', 'vehicle_plate', 'pickup_datetime', 'estimated_end_datetime',
        'pickup_address', 'dropoff_address', 'price_text', 'price_amount', 'payload_json',
        'operator_note', 'queued_at', 'locked_at', 'submitted_at', 'failed_at', 'last_error',
        'created_at', 'updated_at',
    ];

    $select = [];
    foreach ($wanted as $name) {
        if (gov_v3nrm_has($columns, $name)) {
            $select[] = gov_v3nrm_identifier($name);
        }
    }
    if ($select === []) { return []; }

    $order = gov_v3nrm_has($columns, 'id') ? '`id` DESC' : (gov_v3nrm_has($columns, 'created_at') ? '`created_at` DESC' : '1 DESC');
    $limit = max(1, min(250, $limit));

    try {
        $sql = 'SELECT ' . implode(', ', $select) . ' FROM ' . gov_v3nrm_identifier($table) . ' ORDER BY ' . $order . ' LIMIT ' . $limit;
        $result = $mysqli->query($sql);
        if (!$result) { return []; }
        return $result->fetch_all(MYSQLI_ASSOC) ?: [];
    } catch (Throwable) {
        return [];
    }
}

/** @return array<string,mixed> */
function gov_v3nrm_classify_row(array $row, array $columns, int $minFutureMinutes): array
{
    $isCanary = gov_v3nrm_is_canary($row);
    $possibleReal = !$isCanary;
    $minutesUntil = gov_v3nrm_minutes_until((string)($row['pickup_datetime'] ?? ''));
    $pickupFuture = $minutesUntil !== null && $minutesUntil >= $minFutureMinutes;
    $missing = gov_v3nrm_missing_required($row, $columns);
    $status = (string)($row['queue_status'] ?? '');
    $lastError = trim((string)($row['last_error'] ?? ''));
    $submitted = trim((string)($row['submitted_at'] ?? '')) !== '';
    $failed = trim((string)($row['failed_at'] ?? '')) !== '';
    $parserOk = !array_key_exists('parser_ok', $row) || (string)($row['parser_ok'] ?? '') === '1';
    $mappingOk = !array_key_exists('mapping_ok', $row) || (string)($row['mapping_ok'] ?? '') === '1';
    $futureOkFlag = !array_key_exists('future_ok', $row) || (string)($row['future_ok'] ?? '') === '1';

    $candidate = $possibleReal
        && $pickupFuture
        && !$submitted
        && !$failed
        && $lastError === ''
        && $missing === []
        && $parserOk
        && $mappingOk
        && $futureOkFlag;

    $reason = 'not_candidate';
    if (!$possibleReal) {
        $reason = 'generated_canary_or_test_row';
    } elseif (!$pickupFuture) {
        $reason = 'pickup_not_future_safe_now';
    } elseif ($submitted) {
        $reason = 'already_submitted';
    } elseif ($failed) {
        $reason = 'already_failed_or_blocked';
    } elseif ($lastError !== '') {
        $reason = 'has_last_error';
    } elseif ($missing !== []) {
        $reason = 'missing_required_fields';
    } elseif (!$parserOk) {
        $reason = 'parser_not_ok';
    } elseif (!$mappingOk) {
        $reason = 'mapping_not_ok';
    } elseif (!$futureOkFlag) {
        $reason = 'future_ok_flag_not_set';
    } elseif ($candidate) {
        $reason = 'candidate_for_operator_review_only';
    }

    $watchClass = $candidate ? 'operator_review_candidate' : ($possibleReal ? 'possible_real_not_candidate' : 'canary_or_test');
    if ($candidate && $minutesUntil !== null && $minutesUntil <= 15) {
        $watchClass = 'urgent_operator_review_candidate';
    }

    return [
        'id' => isset($row['id']) ? (int)$row['id'] : null,
        'queue_status' => $status,
        'pickup_datetime' => (string)($row['pickup_datetime'] ?? ''),
        'minutes_until_pickup_now' => $minutesUntil,
        'pickup_future_safe_now' => $pickupFuture,
        'customer_name' => (string)($row['customer_name'] ?? ''),
        'customer_phone' => (string)($row['customer_phone'] ?? ''),
        'driver_name' => (string)($row['driver_name'] ?? ''),
        'vehicle_plate' => (string)($row['vehicle_plate'] ?? ''),
        'order_reference' => (string)($row['order_reference'] ?? ''),
        'lessor_id' => (string)($row['lessor_id'] ?? ''),
        'driver_id' => (string)($row['driver_id'] ?? ''),
        'vehicle_id' => (string)($row['vehicle_id'] ?? ''),
        'starting_point_id' => (string)($row['starting_point_id'] ?? ''),
        'is_canary' => $isCanary,
        'possible_real_mail' => $possibleReal,
        'missing_required_fields' => $missing,
        'parser_ok' => $parserOk,
        'mapping_ok' => $mappingOk,
        'future_ok_flag' => $futureOkFlag,
        'submitted' => $submitted,
        'failed_or_blocked' => $failed,
        'last_error' => $lastError,
        'candidate_for_operator_review' => $candidate,
        'watch_classification' => $watchClass,
        'reason' => $reason,
        'safe_interpretation' => $candidate
            ? 'Future possible-real row appears complete for operator review only. Live EDXEIX submission remains blocked by master gate.'
            : 'No action; continue observing.',
        'created_at' => $row['created_at'] ?? null,
        'updated_at' => $row['updated_at'] ?? null,
        'failed_at' => $row['failed_at'] ?? null,
        'submitted_at' => $row['submitted_at'] ?? null,
    ];
}

/** @return array<string,mixed> */
function gov_v3_next_real_mail_candidate_watch_run(): array
{
    $report = [
        'ok' => false,
        'version' => GOV_V3_NEXT_REAL_MAIL_CANDIDATE_WATCH_VERSION,
        'mode' => GOV_V3_NEXT_REAL_MAIL_CANDIDATE_WATCH_MODE,
        'started_at' => date('c'),
        'safety' => GOV_V3_NEXT_REAL_MAIL_CANDIDATE_WATCH_SAFETY,
        'app_root' => gov_v3nrm_app_root(),
        'public_root' => gov_v3nrm_public_root(),
        'database' => [
            'connected' => false,
            'name' => '',
            'host' => '',
            'error' => '',
        ],
        'live_gate' => gov_v3nrm_live_gate_state(),
        'queue' => [
            'table' => 'pre_ride_email_v3_queue',
            'exists' => false,
            'columns_loaded' => 0,
            'rows_scanned' => 0,
            'candidate_rows' => [],
            'latest_rows' => [],
        ],
        'summary' => [
            'rows_scanned' => 0,
            'possible_real_rows_scanned' => 0,
            'canary_rows_scanned' => 0,
            'future_possible_real_rows' => 0,
            'complete_future_possible_real_rows' => 0,
            'operator_review_candidates' => 0,
            'urgent_operator_review_candidates' => 0,
            'candidate_missing_required_fields' => 0,
            'candidate_rows_with_last_error' => 0,
            'future_rows_already_failed_or_blocked' => 0,
            'future_rows_already_submitted' => 0,
            'live_gate_expected_closed' => false,
            'live_risk_detected' => false,
            'live_submit_recommended_now' => 0,
            'db_write_made' => false,
            'queue_mutation_made' => false,
        ],
        'warnings' => [],
        'recommended_next_step' => 'Continue observing. If operator_review_candidates becomes greater than zero, inspect the row in the V3 closed-gate tools before pickup expires. Do not enable live submit.',
        'final_blocks' => [],
        'finished_at' => null,
    ];

    $db = gov_v3nrm_db();
    $report['database']['connected'] = (bool)($db['ok'] ?? false);
    $report['database']['error'] = (string)($db['error'] ?? '');
    $report['database']['name'] = (string)($db['name'] ?? '');
    $report['database']['host'] = (string)($db['host'] ?? '');

    $report['summary']['live_gate_expected_closed'] = !empty($report['live_gate']['expected_closed_pre_live_posture']);
    $report['summary']['live_risk_detected'] = !empty($report['live_gate']['live_risk_detected']);
    if (!empty($report['summary']['live_risk_detected'])) {
        $report['final_blocks'][] = 'live_gate: unexpected open/risky posture detected';
    }

    if (empty($db['ok']) || empty($db['connection']) || !($db['connection'] instanceof mysqli)) {
        $report['final_blocks'][] = 'database: unavailable';
        $report['finished_at'] = date('c');
        return $report;
    }

    $mysqli = $db['connection'];
    $table = 'pre_ride_email_v3_queue';
    $report['queue']['exists'] = gov_v3nrm_table_exists($mysqli, $table);
    if (!$report['queue']['exists']) {
        $report['final_blocks'][] = 'queue: pre_ride_email_v3_queue table missing';
        $report['finished_at'] = date('c');
        return $report;
    }

    $columns = gov_v3nrm_columns($mysqli, $table);
    $report['queue']['columns_loaded'] = count($columns);

    $rows = gov_v3nrm_fetch_recent_rows($mysqli, $table, $columns, 100);
    $minFutureMinutes = 1;
    $classified = [];
    $candidates = [];

    foreach ($rows as $row) {
        $item = gov_v3nrm_classify_row($row, $columns, $minFutureMinutes);
        $classified[] = $item;
        $report['summary']['rows_scanned']++;

        if (!empty($item['possible_real_mail'])) {
            $report['summary']['possible_real_rows_scanned']++;
        } else {
            $report['summary']['canary_rows_scanned']++;
        }

        if (!empty($item['possible_real_mail']) && !empty($item['pickup_future_safe_now'])) {
            $report['summary']['future_possible_real_rows']++;
            if (!empty($item['failed_or_blocked'])) {
                $report['summary']['future_rows_already_failed_or_blocked']++;
            }
            if (!empty($item['submitted'])) {
                $report['summary']['future_rows_already_submitted']++;
            }
            if (($item['missing_required_fields'] ?? []) === [] && (string)($item['last_error'] ?? '') === '') {
                $report['summary']['complete_future_possible_real_rows']++;
            }
        }

        if (!empty($item['possible_real_mail']) && (string)($item['last_error'] ?? '') !== '') {
            $report['summary']['candidate_rows_with_last_error']++;
        }

        if (!empty($item['possible_real_mail']) && ($item['missing_required_fields'] ?? []) !== []) {
            $report['summary']['candidate_missing_required_fields']++;
        }

        if (!empty($item['candidate_for_operator_review'])) {
            $report['summary']['operator_review_candidates']++;
            if (($item['watch_classification'] ?? '') === 'urgent_operator_review_candidate') {
                $report['summary']['urgent_operator_review_candidates']++;
            }
            $candidates[] = $item;
        }
    }

    $report['queue']['rows_scanned'] = count($classified);
    $report['queue']['latest_rows'] = array_slice($classified, 0, 25);
    $report['queue']['candidate_rows'] = $candidates;

    if ((int)$report['summary']['operator_review_candidates'] > 0) {
        $report['warnings'][] = 'Future possible-real row exists for operator review only. Live submit remains blocked; inspect closed-gate readiness before pickup expires.';
    }

    $report['ok'] = ($report['final_blocks'] === []);
    $report['finished_at'] = date('c');
    return $report;
}

/** @param array<int,string> $argv */
function gov_v3_next_real_mail_candidate_watch_main(array $argv): void
{
    $json = in_array('--json', $argv, true);
    $report = gov_v3_next_real_mail_candidate_watch_run();

    if ($json) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
        return;
    }

    echo 'V3 next real-mail candidate watch ' . GOV_V3_NEXT_REAL_MAIL_CANDIDATE_WATCH_VERSION . PHP_EOL;
    echo 'OK: ' . (!empty($report['ok']) ? 'yes' : 'no') . PHP_EOL;
    echo 'Possible real rows scanned: ' . (string)($report['summary']['possible_real_rows_scanned'] ?? 0) . PHP_EOL;
    echo 'Future possible real rows: ' . (string)($report['summary']['future_possible_real_rows'] ?? 0) . PHP_EOL;
    echo 'Operator review candidates: ' . (string)($report['summary']['operator_review_candidates'] ?? 0) . PHP_EOL;
    echo 'Live risk: ' . (!empty($report['summary']['live_risk_detected']) ? 'yes' : 'no') . PHP_EOL;
    echo 'Final blocks: ' . implode('; ', $report['final_blocks'] ?? []) . PHP_EOL;
}

if (PHP_SAPI === 'cli' && realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    gov_v3_next_real_mail_candidate_watch_main($argv ?? []);
}
