<?php
/**
 * gov.cabnet.app — V3 Real Future Candidate Capture Readiness CLI.
 *
 * v3.2.0:
 * - Read-only readiness board for detecting a real future possible-real pre-ride row.
 * - Shows minutes until pickup, completeness, missing fields, closed-gate review qualification,
 *   urgency/expiry posture, and whether an operator alert would be appropriate.
 * - No Bolt calls, no EDXEIX calls, no AADE calls, no DB writes, no queue mutations, no filesystem writes.
 *
 * v3.2.1:
 * - Adds compact one-shot watch snapshot output for operator polling.
 * - Keeps the same read-only/no-submit safety posture.
 *
 * v3.2.2:
 * - Adds sanitized candidate evidence snapshot export for closed-gate operator review.
 * - Hides raw payloads, parsed JSON, hashes, and unnecessary passenger data from the export.
 *
 * v3.2.3:
 * - Adds read-only EDXEIX payload preview / dry-run preflight output.
 * - Shows normalized submission fields in sanitized form while live submit remains blocked.
 */

declare(strict_types=1);

const GOV_V3_REAL_FUTURE_CANDIDATE_CAPTURE_READINESS_VERSION = 'v3.2.3-v3-edxeix-payload-preview-dry-run-preflight';
const GOV_V3_REAL_FUTURE_CANDIDATE_CAPTURE_READINESS_MODE = 'read_only_v3_edxeix_payload_preview_dry_run_preflight';
const GOV_V3_REAL_FUTURE_CANDIDATE_CAPTURE_READINESS_SAFETY = 'No Bolt call. No EDXEIX call. No AADE call. No DB writes. No queue status changes. No filesystem writes. Read-only queue/config inspection only. Watch, evidence, and EDXEIX payload preview snapshots are one-shot output only.';
const GOV_V3RFCCR_QUEUE_TABLE = 'pre_ride_email_v3_queue';
const GOV_V3RFCCR_MIN_FUTURE_MINUTES = 1;
const GOV_V3RFCCR_OPERATOR_ALERT_WINDOW_MINUTES = 60;
const GOV_V3RFCCR_EXPIRY_WARNING_MINUTES = 30;
const GOV_V3RFCCR_URGENT_MINUTES = 15;

function gov_v3rfccr_app_root(): string
{
    return dirname(__DIR__);
}

function gov_v3rfccr_home_root(): string
{
    return dirname(gov_v3rfccr_app_root());
}

function gov_v3rfccr_public_root(): string
{
    return gov_v3rfccr_home_root() . '/public_html/gov.cabnet.app';
}

function gov_v3rfccr_config_root(): string
{
    return gov_v3rfccr_home_root() . '/gov.cabnet.app_config';
}

/** @return array<string,mixed> */
function gov_v3rfccr_db(): array
{
    $bootstrapFile = gov_v3rfccr_app_root() . '/src/bootstrap.php';
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

function gov_v3rfccr_identifier(string $name): string
{
    if (!preg_match('/^[A-Za-z0-9_]+$/', $name)) {
        throw new InvalidArgumentException('Invalid SQL identifier.');
    }
    return '`' . $name . '`';
}

function gov_v3rfccr_table_exists(mysqli $mysqli, string $table): bool
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
function gov_v3rfccr_columns(mysqli $mysqli, string $table): array
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

function gov_v3rfccr_has(array $columns, string $name): bool
{
    return !empty($columns[$name]);
}

/** @return array<string,mixed> */
function gov_v3rfccr_live_gate_state(): array
{
    $file = gov_v3rfccr_config_root() . '/pre_ride_email_v3_live_submit.php';
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

/** @return array<int,string> */
function gov_v3rfccr_required_fields(): array
{
    return [
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
}

/** @return array<int,string> */
function gov_v3rfccr_terminal_statuses(): array
{
    return [
        'submitted',
        'blocked',
        'failed',
        'expired',
        'cancelled',
        'canceled',
        'invalid',
        'duplicate',
        'possible_real_expired_guard',
        'expired_guard',
        'not_future_safe',
    ];
}

function gov_v3rfccr_bool_value(mixed $value, bool $default = false): bool
{
    if ($value === null) { return $default; }
    if (is_bool($value)) { return $value; }
    $text = strtolower(trim((string)$value));
    if ($text === '') { return $default; }
    return in_array($text, ['1', 'true', 'yes', 'y', 'ok'], true);
}

function gov_v3rfccr_pickup_timestamp(?string $pickup): ?int
{
    $pickup = trim((string)$pickup);
    if ($pickup === '') { return null; }
    $ts = strtotime($pickup);
    return $ts === false ? null : $ts;
}

function gov_v3rfccr_minutes_until(?string $pickup): ?int
{
    $ts = gov_v3rfccr_pickup_timestamp($pickup);
    if ($ts === null) { return null; }
    return (int)floor(($ts - time()) / 60);
}

function gov_v3rfccr_is_canary(array $row): bool
{
    $haystack = implode(' ', [
        (string)($row['customer_name'] ?? ''),
        (string)($row['order_reference'] ?? ''),
        (string)($row['source_mailbox'] ?? ''),
        (string)($row['operator_note'] ?? ''),
        (string)($row['last_error'] ?? ''),
        (string)($row['raw_email_preview'] ?? ''),
    ]);
    return (bool)preg_match('/\bcanary\b|synthetic|V3 Canary/i', $haystack);
}

/** @return array<int,string> */
function gov_v3rfccr_missing_required(array $row, array $columns): array
{
    $missing = [];
    foreach (gov_v3rfccr_required_fields() as $field) {
        if (!gov_v3rfccr_has($columns, $field)) {
            $missing[] = $field . ' (column missing)';
            continue;
        }
        if (!array_key_exists($field, $row) || trim((string)($row[$field] ?? '')) === '') {
            $missing[] = $field;
        }
    }
    return $missing;
}

/** @return array<int,array<string,mixed>> */
function gov_v3rfccr_fetch_rows(mysqli $mysqli, string $table, array $columns, int $limit = 250): array
{
    $wanted = [
        'id', 'dedupe_key', 'source_mailbox', 'source_mtime', 'source_hash', 'email_hash',
        'order_reference', 'queue_status', 'parser_ok', 'mapping_ok', 'future_ok', 'lessor_id',
        'lessor_source', 'driver_id', 'vehicle_id', 'starting_point_id', 'customer_name',
        'customer_phone', 'driver_name', 'vehicle_plate', 'pickup_datetime', 'estimated_end_datetime',
        'minutes_until_at_intake', 'pickup_address', 'dropoff_address', 'price_text', 'price_amount',
        'block_reasons_json', 'parsed_fields_json', 'payload_json', 'raw_email_preview',
        'operator_note', 'queued_at', 'locked_at', 'submitted_at', 'failed_at', 'last_error',
        'created_at', 'updated_at',
    ];

    $select = [];
    foreach ($wanted as $name) {
        if (gov_v3rfccr_has($columns, $name)) {
            $select[] = gov_v3rfccr_identifier($name);
        }
    }
    if ($select === []) { return []; }

    $limit = max(1, min(500, $limit));
    if (gov_v3rfccr_has($columns, 'pickup_datetime')) {
        $order = 'CASE WHEN `pickup_datetime` IS NOT NULL AND `pickup_datetime` >= NOW() THEN 0 ELSE 1 END ASC, `pickup_datetime` ASC, `id` DESC';
    } elseif (gov_v3rfccr_has($columns, 'id')) {
        $order = '`id` DESC';
    } else {
        $order = '1 DESC';
    }

    try {
        $sql = 'SELECT ' . implode(', ', $select) . ' FROM ' . gov_v3rfccr_identifier($table) . ' ORDER BY ' . $order . ' LIMIT ' . $limit;
        $result = $mysqli->query($sql);
        if (!$result) { return []; }
        return $result->fetch_all(MYSQLI_ASSOC) ?: [];
    } catch (Throwable) {
        return [];
    }
}

/** @return array<string,mixed> */
function gov_v3rfccr_classify_row(array $row, array $columns, array $liveGate): array
{
    $status = trim((string)($row['queue_status'] ?? ''));
    $statusLower = strtolower($status);
    $minutesUntil = gov_v3rfccr_minutes_until((string)($row['pickup_datetime'] ?? ''));
    $isFuture = $minutesUntil !== null && $minutesUntil >= GOV_V3RFCCR_MIN_FUTURE_MINUTES;
    $isCanary = gov_v3rfccr_is_canary($row);
    $possibleReal = !$isCanary;
    $terminalStatus = in_array($statusLower, gov_v3rfccr_terminal_statuses(), true);
    $submitted = trim((string)($row['submitted_at'] ?? '')) !== '' || $statusLower === 'submitted';
    $failedOrBlocked = trim((string)($row['failed_at'] ?? '')) !== '' || $terminalStatus;
    $lastError = trim((string)($row['last_error'] ?? ''));

    $parserOk = !gov_v3rfccr_has($columns, 'parser_ok') || gov_v3rfccr_bool_value($row['parser_ok'] ?? null, false);
    $mappingOk = !gov_v3rfccr_has($columns, 'mapping_ok') || gov_v3rfccr_bool_value($row['mapping_ok'] ?? null, false);
    $futureOkFlag = !gov_v3rfccr_has($columns, 'future_ok') || gov_v3rfccr_bool_value($row['future_ok'] ?? null, false);
    $missing = gov_v3rfccr_missing_required($row, $columns);
    $complete = $missing === [] && $parserOk && $mappingOk && $futureOkFlag && $lastError === '';
    $gateClosed = !empty($liveGate['expected_closed_pre_live_posture']) && empty($liveGate['live_risk_detected']);

    $closedGateReviewQualified = $possibleReal
        && $isFuture
        && $complete
        && !$submitted
        && !$failedOrBlocked
        && $gateClosed;

    $urgent = $isFuture && $minutesUntil !== null && $minutesUntil <= GOV_V3RFCCR_URGENT_MINUTES;
    $aboutToExpire = $isFuture && $minutesUntil !== null && $minutesUntil <= GOV_V3RFCCR_EXPIRY_WARNING_MINUTES;
    $insideAlertWindow = $isFuture && $minutesUntil !== null && $minutesUntil <= GOV_V3RFCCR_OPERATOR_ALERT_WINDOW_MINUTES;
    $incompleteFuture = $possibleReal && $isFuture && !$complete && !$submitted && !$failedOrBlocked;
    $operatorAlertAppropriate = $closedGateReviewQualified || ($incompleteFuture && $insideAlertWindow);

    $captureReadiness = 'not_ready';
    $reason = 'not_candidate';
    if ($isCanary) {
        $captureReadiness = 'test_or_canary_row';
        $reason = 'generated_canary_or_test_row';
    } elseif (!$isFuture) {
        $captureReadiness = 'not_future_or_expired';
        $reason = 'pickup_not_future_safe_now';
    } elseif ($submitted) {
        $captureReadiness = 'already_submitted';
        $reason = 'already_submitted';
    } elseif ($failedOrBlocked) {
        $captureReadiness = 'terminal_or_blocked';
        $reason = 'terminal_status_or_failed_at_present';
    } elseif (!$gateClosed) {
        $captureReadiness = 'blocked_by_live_gate_risk';
        $reason = 'live_gate_not_confirmed_closed';
    } elseif ($missing !== []) {
        $captureReadiness = 'future_possible_real_incomplete';
        $reason = 'missing_required_fields';
    } elseif (!$parserOk) {
        $captureReadiness = 'future_possible_real_incomplete';
        $reason = 'parser_not_ok';
    } elseif (!$mappingOk) {
        $captureReadiness = 'future_possible_real_incomplete';
        $reason = 'mapping_not_ok';
    } elseif (!$futureOkFlag) {
        $captureReadiness = 'future_possible_real_incomplete';
        $reason = 'future_ok_flag_not_set';
    } elseif ($lastError !== '') {
        $captureReadiness = 'future_possible_real_incomplete';
        $reason = 'last_error_present';
    } elseif ($closedGateReviewQualified) {
        $captureReadiness = 'ready_for_closed_gate_operator_review';
        $reason = 'complete_future_possible_real_closed_gate_review_only';
    }

    $alertPriority = 'none';
    if ($operatorAlertAppropriate) {
        if ($urgent) {
            $alertPriority = 'urgent';
        } elseif ($insideAlertWindow) {
            $alertPriority = 'soon';
        } else {
            $alertPriority = 'normal';
        }
    }

    return [
        'id' => isset($row['id']) ? (int)$row['id'] : null,
        'dedupe_key' => (string)($row['dedupe_key'] ?? ''),
        'queue_status' => $status,
        'order_reference' => (string)($row['order_reference'] ?? ''),
        'source_mailbox' => (string)($row['source_mailbox'] ?? ''),
        'pickup_datetime' => (string)($row['pickup_datetime'] ?? ''),
        'estimated_end_datetime' => (string)($row['estimated_end_datetime'] ?? ''),
        'minutes_until_pickup_now' => $minutesUntil,
        'minutes_until_at_intake' => isset($row['minutes_until_at_intake']) ? (string)$row['minutes_until_at_intake'] : '',
        'is_future_now' => $isFuture,
        'urgent_about_to_expire' => $urgent,
        'expiry_warning_window' => $aboutToExpire,
        'inside_operator_alert_window' => $insideAlertWindow,
        'is_canary_or_test' => $isCanary,
        'possible_real_mail' => $possibleReal,
        'parser_ok' => $parserOk,
        'mapping_ok' => $mappingOk,
        'future_ok_flag' => $futureOkFlag,
        'complete' => $complete,
        'missing_required_fields' => $missing,
        'submitted' => $submitted,
        'terminal_or_failed_or_blocked' => $failedOrBlocked,
        'last_error' => $lastError,
        'lessor_id' => (string)($row['lessor_id'] ?? ''),
        'lessor_source' => (string)($row['lessor_source'] ?? ''),
        'driver_id' => (string)($row['driver_id'] ?? ''),
        'vehicle_id' => (string)($row['vehicle_id'] ?? ''),
        'starting_point_id' => (string)($row['starting_point_id'] ?? ''),
        'customer_name' => (string)($row['customer_name'] ?? ''),
        'customer_phone' => (string)($row['customer_phone'] ?? ''),
        'driver_name' => (string)($row['driver_name'] ?? ''),
        'vehicle_plate' => (string)($row['vehicle_plate'] ?? ''),
        'pickup_address' => (string)($row['pickup_address'] ?? ''),
        'dropoff_address' => (string)($row['dropoff_address'] ?? ''),
        'price_text' => (string)($row['price_text'] ?? ''),
        'price_amount' => (string)($row['price_amount'] ?? ''),
        'capture_readiness' => $captureReadiness,
        'reason' => $reason,
        'qualifies_for_closed_gate_operator_review' => $closedGateReviewQualified,
        'operator_alert_appropriate' => $operatorAlertAppropriate,
        'operator_alert_priority' => $alertPriority,
        'safe_interpretation' => $closedGateReviewQualified
            ? 'Complete future possible-real row is ready for closed-gate operator review only. Live EDXEIX submission remains disabled.'
            : ($operatorAlertAppropriate ? 'Operator attention is useful before expiry, but live submission remains disabled.' : 'No operator action required by this read-only readiness layer.'),
        'created_at' => $row['created_at'] ?? null,
        'updated_at' => $row['updated_at'] ?? null,
        'queued_at' => $row['queued_at'] ?? null,
        'submitted_at' => $row['submitted_at'] ?? null,
        'failed_at' => $row['failed_at'] ?? null,
    ];
}

/** @return array<string,mixed> */
function gov_v3_real_future_candidate_capture_readiness_run(): array
{
    $liveGate = gov_v3rfccr_live_gate_state();
    $report = [
        'ok' => false,
        'version' => GOV_V3_REAL_FUTURE_CANDIDATE_CAPTURE_READINESS_VERSION,
        'mode' => GOV_V3_REAL_FUTURE_CANDIDATE_CAPTURE_READINESS_MODE,
        'started_at' => date('c'),
        'finished_at' => null,
        'safety' => GOV_V3_REAL_FUTURE_CANDIDATE_CAPTURE_READINESS_SAFETY,
        'thresholds' => [
            'min_future_minutes' => GOV_V3RFCCR_MIN_FUTURE_MINUTES,
            'operator_alert_window_minutes' => GOV_V3RFCCR_OPERATOR_ALERT_WINDOW_MINUTES,
            'expiry_warning_minutes' => GOV_V3RFCCR_EXPIRY_WARNING_MINUTES,
            'urgent_minutes' => GOV_V3RFCCR_URGENT_MINUTES,
        ],
        'app_root' => gov_v3rfccr_app_root(),
        'public_root' => gov_v3rfccr_public_root(),
        'database' => [
            'connected' => false,
            'name' => '',
            'host' => '',
            'error' => '',
        ],
        'live_gate' => $liveGate,
        'queue' => [
            'table' => GOV_V3RFCCR_QUEUE_TABLE,
            'exists' => false,
            'columns_loaded' => 0,
            'rows_scanned' => 0,
            'required_fields' => gov_v3rfccr_required_fields(),
            'next_future_possible_real_row' => null,
            'closed_gate_operator_review_rows' => [],
            'operator_alert_rows' => [],
            'future_possible_real_rows' => [],
            'latest_rows' => [],
        ],
        'summary' => [
            'rows_scanned' => 0,
            'possible_real_rows_scanned' => 0,
            'canary_or_test_rows_scanned' => 0,
            'future_possible_real_rows' => 0,
            'complete_future_possible_real_rows' => 0,
            'incomplete_future_possible_real_rows' => 0,
            'closed_gate_operator_review_candidates' => 0,
            'operator_alerts_appropriate' => 0,
            'urgent_or_about_to_expire_rows' => 0,
            'terminal_or_submitted_future_rows' => 0,
            'live_gate_expected_closed' => !empty($liveGate['expected_closed_pre_live_posture']),
            'live_risk_detected' => !empty($liveGate['live_risk_detected']),
            'live_submit_recommended_now' => 0,
            'db_write_made' => false,
            'queue_mutation_made' => false,
            'bolt_call_made' => false,
            'edxeix_call_made' => false,
            'aade_call_made' => false,
        ],
        'warnings' => [],
        'recommended_next_step' => 'Continue observation. Wait for a real future possible-real row, then use closed-gate operator review only. Do not enable live submit.',
        'final_blocks' => [],
    ];

    if (!empty($report['summary']['live_risk_detected'])) {
        $report['final_blocks'][] = 'live_gate: unexpected open/risky posture detected';
    }

    $db = gov_v3rfccr_db();
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
    $report['queue']['exists'] = gov_v3rfccr_table_exists($mysqli, GOV_V3RFCCR_QUEUE_TABLE);
    if (!$report['queue']['exists']) {
        $report['final_blocks'][] = 'queue: pre_ride_email_v3_queue table missing';
        $report['finished_at'] = date('c');
        return $report;
    }

    $columns = gov_v3rfccr_columns($mysqli, GOV_V3RFCCR_QUEUE_TABLE);
    $report['queue']['columns_loaded'] = count($columns);

    $rows = gov_v3rfccr_fetch_rows($mysqli, GOV_V3RFCCR_QUEUE_TABLE, $columns, 250);
    $classified = [];
    $futurePossible = [];
    $reviewRows = [];
    $alertRows = [];

    foreach ($rows as $row) {
        $item = gov_v3rfccr_classify_row($row, $columns, $liveGate);
        $classified[] = $item;
        $report['summary']['rows_scanned']++;

        if (!empty($item['possible_real_mail'])) {
            $report['summary']['possible_real_rows_scanned']++;
        } else {
            $report['summary']['canary_or_test_rows_scanned']++;
        }

        if (!empty($item['possible_real_mail']) && !empty($item['is_future_now'])) {
            $futurePossible[] = $item;
            $report['summary']['future_possible_real_rows']++;
            if (!empty($item['complete'])) {
                $report['summary']['complete_future_possible_real_rows']++;
            } else {
                $report['summary']['incomplete_future_possible_real_rows']++;
            }
            if (!empty($item['submitted']) || !empty($item['terminal_or_failed_or_blocked'])) {
                $report['summary']['terminal_or_submitted_future_rows']++;
            }
            if (!empty($item['urgent_about_to_expire']) || !empty($item['expiry_warning_window'])) {
                $report['summary']['urgent_or_about_to_expire_rows']++;
            }
        }

        if (!empty($item['qualifies_for_closed_gate_operator_review'])) {
            $reviewRows[] = $item;
            $report['summary']['closed_gate_operator_review_candidates']++;
        }

        if (!empty($item['operator_alert_appropriate'])) {
            $alertRows[] = $item;
            $report['summary']['operator_alerts_appropriate']++;
        }
    }

    $report['queue']['rows_scanned'] = count($classified);
    $report['queue']['latest_rows'] = array_slice($classified, 0, 25);
    $report['queue']['future_possible_real_rows'] = array_slice($futurePossible, 0, 25);
    $report['queue']['closed_gate_operator_review_rows'] = array_slice($reviewRows, 0, 25);
    $report['queue']['operator_alert_rows'] = array_slice($alertRows, 0, 25);
    $report['queue']['next_future_possible_real_row'] = $futurePossible[0] ?? null;

    if ((int)$report['summary']['closed_gate_operator_review_candidates'] > 0) {
        $report['warnings'][] = 'A complete future possible-real row exists for closed-gate operator review only. Live submit remains disabled.';
        $report['recommended_next_step'] = 'Inspect the candidate immediately with closed-gate tools, confirm missing_fields is empty, and keep live EDXEIX submission disabled.';
    } elseif ((int)$report['summary']['incomplete_future_possible_real_rows'] > 0) {
        $report['warnings'][] = 'Future possible-real row exists but is incomplete. Review missing fields before expiry; do not submit.';
        $report['recommended_next_step'] = 'Review missing fields and mapping/driver/vehicle data while the V3 live gate remains closed.';
    } elseif ((int)$report['summary']['future_possible_real_rows'] === 0) {
        $report['recommended_next_step'] = 'No real future candidate is currently visible. Continue observing until a real future pre-ride email arrives before pickup expiry.';
    }

    $report['ok'] = ($report['final_blocks'] === []);
    $report['finished_at'] = date('c');
    return $report;
}


/** @return array<string,mixed> */
function gov_v3rfccr_watch_snapshot(array $report): array
{
    $summary = is_array($report['summary'] ?? null) ? $report['summary'] : [];
    $queue = is_array($report['queue'] ?? null) ? $report['queue'] : [];
    $nextCandidate = is_array($queue['next_future_possible_real_row'] ?? null) ? $queue['next_future_possible_real_row'] : null;
    $reviewRows = is_array($queue['closed_gate_operator_review_rows'] ?? null) ? $queue['closed_gate_operator_review_rows'] : [];
    $alertRows = is_array($queue['operator_alert_rows'] ?? null) ? $queue['operator_alert_rows'] : [];
    $finalBlocks = is_array($report['final_blocks'] ?? null) ? $report['final_blocks'] : [];
    $warnings = is_array($report['warnings'] ?? null) ? $report['warnings'] : [];

    $futureCount = (int)($summary['future_possible_real_rows'] ?? 0);
    $completeFutureCount = (int)($summary['complete_future_possible_real_rows'] ?? 0);
    $incompleteFutureCount = (int)($summary['incomplete_future_possible_real_rows'] ?? 0);
    $reviewCount = (int)($summary['closed_gate_operator_review_candidates'] ?? count($reviewRows));
    $alertCount = (int)($summary['operator_alerts_appropriate'] ?? count($alertRows));
    $urgentCount = (int)($summary['urgent_or_about_to_expire_rows'] ?? 0);
    $liveRisk = !empty($summary['live_risk_detected']);

    $actionCode = 'WAIT_NO_CANDIDATE';
    $actionLabel = 'No real future candidate is visible. Keep observing.';
    $severity = 'clear';

    if ($finalBlocks !== []) {
        $actionCode = 'BLOCKED_FINAL_BLOCKS';
        $actionLabel = 'Audit has final blocks. Fix blocks before relying on candidate capture.';
        $severity = 'blocked';
    } elseif ($liveRisk) {
        $actionCode = 'BLOCKED_LIVE_GATE_RISK';
        $actionLabel = 'Live gate posture is risky. Do not proceed until gate is closed again.';
        $severity = 'blocked';
    } elseif ($reviewCount > 0) {
        $actionCode = 'REVIEW_COMPLETE_FUTURE_CANDIDATE';
        $actionLabel = 'A complete real future candidate is ready for closed-gate operator review only. Do not submit live.';
        $severity = $urgentCount > 0 ? 'urgent' : 'review';
    } elseif ($alertCount > 0) {
        $actionCode = 'REVIEW_OPERATOR_ALERT';
        $actionLabel = 'A future candidate needs operator attention before expiry. Review missing fields/mapping; do not submit live.';
        $severity = $urgentCount > 0 ? 'urgent' : 'warning';
    } elseif ($futureCount > 0) {
        $actionCode = 'REVIEW_INCOMPLETE_FUTURE_CANDIDATE';
        $actionLabel = 'A future possible-real candidate exists but is not complete. Inspect missing fields before expiry.';
        $severity = $urgentCount > 0 ? 'urgent' : 'warning';
    }

    $next = null;
    if ($nextCandidate) {
        $missing = is_array($nextCandidate['missing_required_fields'] ?? null) ? $nextCandidate['missing_required_fields'] : [];
        $next = [
            'id' => $nextCandidate['id'] ?? null,
            'pickup_datetime' => (string)($nextCandidate['pickup_datetime'] ?? ''),
            'minutes_until_pickup_now' => $nextCandidate['minutes_until_pickup_now'] ?? null,
            'complete' => !empty($nextCandidate['complete']),
            'missing_required_fields' => array_values(array_map('strval', $missing)),
            'capture_readiness' => (string)($nextCandidate['capture_readiness'] ?? ''),
            'reason' => (string)($nextCandidate['reason'] ?? ''),
            'operator_alert_appropriate' => !empty($nextCandidate['operator_alert_appropriate']),
            'operator_alert_priority' => (string)($nextCandidate['operator_alert_priority'] ?? 'none'),
            'customer_name_present' => trim((string)($nextCandidate['customer_name'] ?? '')) !== '',
            'customer_phone_present' => trim((string)($nextCandidate['customer_phone'] ?? '')) !== '',
            'driver_id' => (string)($nextCandidate['driver_id'] ?? ''),
            'vehicle_id' => (string)($nextCandidate['vehicle_id'] ?? ''),
            'lessor_id' => (string)($nextCandidate['lessor_id'] ?? ''),
            'starting_point_id' => (string)($nextCandidate['starting_point_id'] ?? ''),
        ];
    }

    return [
        'ok' => !empty($report['ok']),
        'version' => GOV_V3_REAL_FUTURE_CANDIDATE_CAPTURE_READINESS_VERSION,
        'generated_at' => date('c'),
        'snapshot_mode' => 'read_only_one_shot_operator_watch_snapshot',
        'action_code' => $actionCode,
        'action_label' => $actionLabel,
        'severity' => $severity,
        'counts' => [
            'future_possible_real_rows' => $futureCount,
            'complete_future_possible_real_rows' => $completeFutureCount,
            'incomplete_future_possible_real_rows' => $incompleteFutureCount,
            'closed_gate_operator_review_candidates' => $reviewCount,
            'operator_alerts_appropriate' => $alertCount,
            'urgent_or_about_to_expire_rows' => $urgentCount,
        ],
        'next_candidate' => $next,
        'warnings' => array_values(array_map('strval', $warnings)),
        'final_blocks' => array_values(array_map('strval', $finalBlocks)),
        'safety_confirmed' => [
            'live_gate_expected_closed' => !empty($summary['live_gate_expected_closed']),
            'live_risk_detected' => $liveRisk,
            'live_submit_recommended_now' => (int)($summary['live_submit_recommended_now'] ?? 0),
            'db_write_made' => !empty($summary['db_write_made']),
            'queue_mutation_made' => !empty($summary['queue_mutation_made']),
            'bolt_call_made' => !empty($summary['bolt_call_made']),
            'edxeix_call_made' => !empty($summary['edxeix_call_made']),
            'aade_call_made' => !empty($summary['aade_call_made']),
        ],
        'safe_operator_command' => '/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_real_future_candidate_capture_readiness.php --watch-json',
        'safe_polling_hint' => 'For manual terminal monitoring only: watch -n 30 \'/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_real_future_candidate_capture_readiness.php --status-line\'',
    ];
}


function gov_v3rfccr_mask_phone(string $phone): string
{
    $phone = trim($phone);
    if ($phone === '') { return ''; }
    $digits = preg_replace('/\D+/', '', $phone) ?? '';
    if ($digits === '') { return 'present'; }
    $prefix = str_starts_with($phone, '+') ? '+' : '';
    if (strlen($digits) <= 5) {
        return $prefix . str_repeat('•', max(0, strlen($digits) - 2)) . substr($digits, -2);
    }
    return $prefix . substr($digits, 0, 3) . '…' . substr($digits, -2);
}

function gov_v3rfccr_source_basename(string $source): string
{
    $source = trim(str_replace('\\', '/', $source));
    if ($source === '') { return ''; }
    return basename($source);
}

function gov_v3rfccr_mask_name(string $name): string
{
    $name = trim(preg_replace('/\s+/', ' ', $name) ?? '');
    if ($name === '') { return ''; }
    $parts = preg_split('/\s+/', $name) ?: [];
    if (count($parts) === 1) { return $parts[0]; }
    $first = (string)$parts[0];
    $last = (string)end($parts);
    $initial = mb_substr($last, 0, 1, 'UTF-8');
    return $first . ($initial !== '' ? ' ' . $initial . '.' : '');
}

/** @return array<string,mixed> */
function gov_v3rfccr_candidate_evidence_snapshot(array $report): array
{
    $summary = is_array($report['summary'] ?? null) ? $report['summary'] : [];
    $queue = is_array($report['queue'] ?? null) ? $report['queue'] : [];
    $reviewRows = is_array($queue['closed_gate_operator_review_rows'] ?? null) ? $queue['closed_gate_operator_review_rows'] : [];
    $futureRows = is_array($queue['future_possible_real_rows'] ?? null) ? $queue['future_possible_real_rows'] : [];
    $candidate = null;
    if (isset($reviewRows[0]) && is_array($reviewRows[0])) {
        $candidate = $reviewRows[0];
    } elseif (is_array($queue['next_future_possible_real_row'] ?? null)) {
        $candidate = $queue['next_future_possible_real_row'];
    } elseif (isset($futureRows[0]) && is_array($futureRows[0])) {
        $candidate = $futureRows[0];
    }

    $warnings = is_array($report['warnings'] ?? null) ? $report['warnings'] : [];
    $finalBlocks = is_array($report['final_blocks'] ?? null) ? $report['final_blocks'] : [];
    $watch = gov_v3rfccr_watch_snapshot($report);

    $base = [
        'ok' => !empty($report['ok']),
        'version' => GOV_V3_REAL_FUTURE_CANDIDATE_CAPTURE_READINESS_VERSION,
        'generated_at' => date('c'),
        'snapshot_mode' => 'read_only_sanitized_candidate_evidence_snapshot',
        'candidate_found' => $candidate !== null,
        'safety_notice' => 'Evidence snapshot is read-only. It hides raw payloads, parsed JSON, hashes, and unnecessary passenger data. Live EDXEIX submission remains disabled.',
        'watch_action_code' => (string)($watch['action_code'] ?? 'UNKNOWN'),
        'watch_severity' => (string)($watch['severity'] ?? 'unknown'),
        'warnings' => array_values(array_map('strval', $warnings)),
        'final_blocks' => array_values(array_map('strval', $finalBlocks)),
        'safety_confirmed' => [
            'live_gate_expected_closed' => !empty($summary['live_gate_expected_closed']),
            'live_risk_detected' => !empty($summary['live_risk_detected']),
            'live_submit_recommended_now' => (int)($summary['live_submit_recommended_now'] ?? 0),
            'db_write_made' => !empty($summary['db_write_made']),
            'queue_mutation_made' => !empty($summary['queue_mutation_made']),
            'bolt_call_made' => !empty($summary['bolt_call_made']),
            'edxeix_call_made' => !empty($summary['edxeix_call_made']),
            'aade_call_made' => !empty($summary['aade_call_made']),
        ],
        'hidden_from_snapshot' => [
            'payload_json',
            'parsed_fields_json',
            'raw_email_preview',
            'source_hash',
            'email_hash',
            'full_source_mailbox_path',
            'unmasked_customer_phone',
            'raw_message_headers',
            'secrets_or_credentials',
        ],
        'operator_review_outcome' => 'no_candidate_visible',
    ];

    if (!$candidate) {
        $base['candidate'] = null;
        return $base;
    }

    $missing = is_array($candidate['missing_required_fields'] ?? null) ? array_values(array_map('strval', $candidate['missing_required_fields'])) : [];
    $complete = !empty($candidate['complete']);
    $reviewQualified = !empty($candidate['qualifies_for_closed_gate_operator_review']);
    $lastError = trim((string)($candidate['last_error'] ?? ''));

    $base['operator_review_outcome'] = $reviewQualified
        ? 'eligible_for_closed_gate_operator_review_only'
        : ($complete ? 'complete_but_not_review_qualified' : 'incomplete_or_not_ready');

    $base['candidate'] = [
        'queue_id' => $candidate['id'] ?? null,
        'dedupe_key_present' => trim((string)($candidate['dedupe_key'] ?? '')) !== '',
        'source_mailbox_file' => gov_v3rfccr_source_basename((string)($candidate['source_mailbox'] ?? '')),
        'queue_status' => (string)($candidate['queue_status'] ?? ''),
        'timing' => [
            'pickup_datetime' => (string)($candidate['pickup_datetime'] ?? ''),
            'estimated_end_datetime' => (string)($candidate['estimated_end_datetime'] ?? ''),
            'minutes_until_pickup_now' => $candidate['minutes_until_pickup_now'] ?? null,
            'minutes_until_at_intake' => (string)($candidate['minutes_until_at_intake'] ?? ''),
            'is_future_now' => !empty($candidate['is_future_now']),
            'expiry_warning_window' => !empty($candidate['expiry_warning_window']),
            'urgent_about_to_expire' => !empty($candidate['urgent_about_to_expire']),
            'inside_operator_alert_window' => !empty($candidate['inside_operator_alert_window']),
        ],
        'readiness' => [
            'complete' => $complete,
            'missing_required_fields' => $missing,
            'parser_ok' => !empty($candidate['parser_ok']),
            'mapping_ok' => !empty($candidate['mapping_ok']),
            'future_ok_flag' => !empty($candidate['future_ok_flag']),
            'capture_readiness' => (string)($candidate['capture_readiness'] ?? ''),
            'reason' => (string)($candidate['reason'] ?? ''),
            'closed_gate_operator_review' => $reviewQualified,
            'operator_alert_appropriate' => !empty($candidate['operator_alert_appropriate']),
            'operator_alert_priority' => (string)($candidate['operator_alert_priority'] ?? 'none'),
            'safe_interpretation' => (string)($candidate['safe_interpretation'] ?? ''),
        ],
        'identity_presence' => [
            'customer_name_present' => trim((string)($candidate['customer_name'] ?? '')) !== '',
            'customer_phone_present' => trim((string)($candidate['customer_phone'] ?? '')) !== '',
            'customer_phone_masked' => gov_v3rfccr_mask_phone((string)($candidate['customer_phone'] ?? '')),
        ],
        'operator_and_mapping' => [
            'lessor_id' => (string)($candidate['lessor_id'] ?? ''),
            'lessor_source' => (string)($candidate['lessor_source'] ?? ''),
            'driver_name' => (string)($candidate['driver_name'] ?? ''),
            'driver_id' => (string)($candidate['driver_id'] ?? ''),
            'vehicle_plate' => (string)($candidate['vehicle_plate'] ?? ''),
            'vehicle_id' => (string)($candidate['vehicle_id'] ?? ''),
            'starting_point_id' => (string)($candidate['starting_point_id'] ?? ''),
        ],
        'route_and_price' => [
            'pickup_address' => (string)($candidate['pickup_address'] ?? ''),
            'dropoff_address' => (string)($candidate['dropoff_address'] ?? ''),
            'price_text' => (string)($candidate['price_text'] ?? ''),
            'price_amount' => (string)($candidate['price_amount'] ?? ''),
        ],
        'negative_checks' => [
            'is_canary_or_test' => !empty($candidate['is_canary_or_test']),
            'possible_real_mail' => !empty($candidate['possible_real_mail']),
            'submitted' => !empty($candidate['submitted']),
            'terminal_or_failed_or_blocked' => !empty($candidate['terminal_or_failed_or_blocked']),
            'last_error_present' => $lastError !== '',
        ],
    ];

    return $base;
}

/** @return array<string,mixed> */
function gov_v3rfccr_edxeix_payload_preview(array $report): array
{
    $summary = is_array($report['summary'] ?? null) ? $report['summary'] : [];
    $queue = is_array($report['queue'] ?? null) ? $report['queue'] : [];
    $reviewRows = is_array($queue['closed_gate_operator_review_rows'] ?? null) ? $queue['closed_gate_operator_review_rows'] : [];
    $futureRows = is_array($queue['future_possible_real_rows'] ?? null) ? $queue['future_possible_real_rows'] : [];
    $candidate = null;
    if (isset($reviewRows[0]) && is_array($reviewRows[0])) {
        $candidate = $reviewRows[0];
    } elseif (is_array($queue['next_future_possible_real_row'] ?? null)) {
        $candidate = $queue['next_future_possible_real_row'];
    } elseif (isset($futureRows[0]) && is_array($futureRows[0])) {
        $candidate = $futureRows[0];
    }

    $warnings = is_array($report['warnings'] ?? null) ? $report['warnings'] : [];
    $finalBlocks = is_array($report['final_blocks'] ?? null) ? $report['final_blocks'] : [];
    $liveGateClosed = !empty($summary['live_gate_expected_closed']) && empty($summary['live_risk_detected']);

    $base = [
        'ok' => !empty($report['ok']),
        'version' => GOV_V3_REAL_FUTURE_CANDIDATE_CAPTURE_READINESS_VERSION,
        'generated_at' => date('c'),
        'snapshot_mode' => 'read_only_edxeix_payload_preview_dry_run_preflight',
        'candidate_found' => $candidate !== null,
        'dry_run_only' => true,
        'live_submit_allowed_now' => false,
        'live_submit_blocked_by_design' => true,
        'edxeix_call_made' => false,
        'queue_mutation_made' => false,
        'db_write_made' => false,
        'safety_notice' => 'Dry-run preview only. This does not submit to EDXEIX, does not open the live gate, does not mutate queue state, and does not write to the database.',
        'warnings' => array_values(array_map('strval', $warnings)),
        'final_blocks' => array_values(array_map('strval', $finalBlocks)),
        'safety_confirmed' => [
            'live_gate_expected_closed' => !empty($summary['live_gate_expected_closed']),
            'live_risk_detected' => !empty($summary['live_risk_detected']),
            'live_submit_recommended_now' => (int)($summary['live_submit_recommended_now'] ?? 0),
            'db_write_made' => !empty($summary['db_write_made']),
            'queue_mutation_made' => !empty($summary['queue_mutation_made']),
            'bolt_call_made' => !empty($summary['bolt_call_made']),
            'edxeix_call_made' => !empty($summary['edxeix_call_made']),
            'aade_call_made' => !empty($summary['aade_call_made']),
        ],
        'hidden_from_preview' => [
            'payload_json',
            'parsed_fields_json',
            'raw_email_preview',
            'source_hash',
            'email_hash',
            'full_source_mailbox_path',
            'unmasked_customer_phone',
            'raw_message_headers',
            'secrets_or_credentials',
        ],
        'preflight_outcome' => 'no_candidate_visible',
        'preflight_blocks' => [],
        'candidate' => null,
        'normalized_payload_preview' => null,
    ];

    if (!$candidate) {
        $base['preflight_blocks'][] = 'candidate: no future possible-real candidate visible';
        return $base;
    }

    $missing = is_array($candidate['missing_required_fields'] ?? null) ? array_values(array_map('strval', $candidate['missing_required_fields'])) : [];
    $complete = !empty($candidate['complete']);
    $reviewQualified = !empty($candidate['qualifies_for_closed_gate_operator_review']);
    $isFuture = !empty($candidate['is_future_now']);
    $parserOk = !empty($candidate['parser_ok']);
    $mappingOk = !empty($candidate['mapping_ok']);
    $futureOk = !empty($candidate['future_ok_flag']);
    $possibleReal = !empty($candidate['possible_real_mail']);
    $isCanary = !empty($candidate['is_canary_or_test']);
    $submitted = !empty($candidate['submitted']);
    $terminal = !empty($candidate['terminal_or_failed_or_blocked']);
    $lastError = trim((string)($candidate['last_error'] ?? ''));

    $checks = [
        'candidate_found' => true,
        'complete' => $complete,
        'missing_required_fields' => $missing,
        'is_future_now' => $isFuture,
        'parser_ok' => $parserOk,
        'mapping_ok' => $mappingOk,
        'future_ok_flag' => $futureOk,
        'possible_real_mail' => $possibleReal,
        'is_canary_or_test' => $isCanary,
        'not_submitted' => !$submitted,
        'not_terminal_or_blocked' => !$terminal,
        'last_error_empty' => $lastError === '',
        'closed_gate_operator_review' => $reviewQualified,
        'live_gate_expected_closed' => $liveGateClosed,
        'live_submit_allowed_now' => false,
    ];

    if (!$complete) { $base['preflight_blocks'][] = 'candidate: incomplete'; }
    if ($missing !== []) { $base['preflight_blocks'][] = 'candidate: missing required fields'; }
    if (!$isFuture) { $base['preflight_blocks'][] = 'candidate: pickup is not future-safe now'; }
    if (!$parserOk) { $base['preflight_blocks'][] = 'candidate: parser_ok is false'; }
    if (!$mappingOk) { $base['preflight_blocks'][] = 'candidate: mapping_ok is false'; }
    if (!$futureOk) { $base['preflight_blocks'][] = 'candidate: future_ok flag is false'; }
    if (!$possibleReal) { $base['preflight_blocks'][] = 'candidate: possible_real_mail is false'; }
    if ($isCanary) { $base['preflight_blocks'][] = 'candidate: canary/test row'; }
    if ($submitted) { $base['preflight_blocks'][] = 'candidate: already submitted'; }
    if ($terminal) { $base['preflight_blocks'][] = 'candidate: terminal/failed/blocked'; }
    if ($lastError !== '') { $base['preflight_blocks'][] = 'candidate: last_error is present'; }
    if (!$reviewQualified) { $base['preflight_blocks'][] = 'candidate: not qualified for closed-gate review'; }
    if (!$liveGateClosed) { $base['preflight_blocks'][] = 'safety: live gate is not confirmed closed'; }

    $priceAmount = trim((string)($candidate['price_amount'] ?? ''));
    $normalized = [
        'target_system' => 'EDXEIX',
        'operation' => 'pre_ride_contract_create_preview_only',
        'dry_run_only' => true,
        'identifiers' => [
            'queue_id' => $candidate['id'] ?? null,
            'source_mailbox_file' => gov_v3rfccr_source_basename((string)($candidate['source_mailbox'] ?? '')),
        ],
        'edxeix_mapping_ids' => [
            'lessor_id' => (string)($candidate['lessor_id'] ?? ''),
            'driver_id' => (string)($candidate['driver_id'] ?? ''),
            'vehicle_id' => (string)($candidate['vehicle_id'] ?? ''),
            'starting_point_id' => (string)($candidate['starting_point_id'] ?? ''),
        ],
        'ride_times' => [
            'pickup_datetime' => (string)($candidate['pickup_datetime'] ?? ''),
            'estimated_end_datetime' => (string)($candidate['estimated_end_datetime'] ?? ''),
            'minutes_until_pickup_now' => $candidate['minutes_until_pickup_now'] ?? null,
        ],
        'route' => [
            'pickup_address' => (string)($candidate['pickup_address'] ?? ''),
            'dropoff_address' => (string)($candidate['dropoff_address'] ?? ''),
        ],
        'price' => [
            'amount' => $priceAmount,
            'currency' => 'EUR',
            'source_text' => (string)($candidate['price_text'] ?? ''),
        ],
        'passenger_fields' => [
            'customer_name_present' => trim((string)($candidate['customer_name'] ?? '')) !== '',
            'customer_name_preview' => gov_v3rfccr_mask_name((string)($candidate['customer_name'] ?? '')),
            'customer_phone_present' => trim((string)($candidate['customer_phone'] ?? '')) !== '',
            'customer_phone_preview' => gov_v3rfccr_mask_phone((string)($candidate['customer_phone'] ?? '')),
            'unmasked_values_hidden_in_preview' => true,
        ],
        'context' => [
            'driver_name' => (string)($candidate['driver_name'] ?? ''),
            'vehicle_plate' => (string)($candidate['vehicle_plate'] ?? ''),
            'lessor_source' => (string)($candidate['lessor_source'] ?? ''),
            'queue_status' => (string)($candidate['queue_status'] ?? ''),
        ],
    ];

    $base['candidate'] = [
        'queue_id' => $candidate['id'] ?? null,
        'queue_status' => (string)($candidate['queue_status'] ?? ''),
        'capture_readiness' => (string)($candidate['capture_readiness'] ?? ''),
        'reason' => (string)($candidate['reason'] ?? ''),
        'operator_alert_priority' => (string)($candidate['operator_alert_priority'] ?? 'none'),
    ];
    $base['dry_run_preflight_checks'] = $checks;
    $base['normalized_payload_preview'] = $normalized;
    $base['preview_hash_sha256'] = hash('sha256', json_encode($normalized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    $base['preflight_outcome'] = $base['preflight_blocks'] === []
        ? 'dry_run_preview_passed_live_submit_still_blocked'
        : 'dry_run_preview_blocked_or_incomplete';

    return $base;
}

function gov_v3rfccr_status_line(array $snapshot): string
{
    $counts = is_array($snapshot['counts'] ?? null) ? $snapshot['counts'] : [];
    $next = is_array($snapshot['next_candidate'] ?? null) ? $snapshot['next_candidate'] : null;
    $parts = [
        'action=' . (string)($snapshot['action_code'] ?? 'UNKNOWN'),
        'severity=' . (string)($snapshot['severity'] ?? 'unknown'),
        'future=' . (string)($counts['future_possible_real_rows'] ?? 0),
        'review=' . (string)($counts['closed_gate_operator_review_candidates'] ?? 0),
        'alerts=' . (string)($counts['operator_alerts_appropriate'] ?? 0),
        'urgent=' . (string)($counts['urgent_or_about_to_expire_rows'] ?? 0),
        'live_risk=' . (!empty($snapshot['safety_confirmed']['live_risk_detected']) ? 'yes' : 'no'),
    ];
    if ($next) {
        $parts[] = 'next_id=' . (string)($next['id'] ?? '');
        $parts[] = 'minutes=' . (string)($next['minutes_until_pickup_now'] ?? '');
        $parts[] = 'complete=' . (!empty($next['complete']) ? 'yes' : 'no');
        $parts[] = 'priority=' . (string)($next['operator_alert_priority'] ?? 'none');
    }
    return implode(' | ', $parts);
}

/** @param array<int,string> $argv */
function gov_v3_real_future_candidate_capture_readiness_main(array $argv): int
{
    $json = in_array('--json', $argv, true);
    $watchJson = in_array('--watch-json', $argv, true) || in_array('--snapshot-json', $argv, true);
    $evidenceJson = in_array('--evidence-json', $argv, true) || in_array('--candidate-evidence-json', $argv, true);
    $edxeixPreviewJson = in_array('--edxeix-preview-json', $argv, true) || in_array('--payload-preview-json', $argv, true) || in_array('--dry-run-preflight-json', $argv, true);
    $statusLine = in_array('--status-line', $argv, true);
    $report = gov_v3_real_future_candidate_capture_readiness_run();
    $snapshot = gov_v3rfccr_watch_snapshot($report);
    $evidence = gov_v3rfccr_candidate_evidence_snapshot($report);
    $edxeixPreview = gov_v3rfccr_edxeix_payload_preview($report);

    if ($watchJson) {
        echo json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
        return !empty($report['ok']) ? 0 : 1;
    }

    if ($evidenceJson) {
        echo json_encode($evidence, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
        return !empty($report['ok']) ? 0 : 1;
    }

    if ($edxeixPreviewJson) {
        echo json_encode($edxeixPreview, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
        return !empty($report['ok']) ? 0 : 1;
    }

    if ($statusLine) {
        echo gov_v3rfccr_status_line($snapshot) . PHP_EOL;
        return !empty($report['ok']) ? 0 : 1;
    }

    if ($json) {
        $report['watch_snapshot'] = $snapshot;
        $report['candidate_evidence_snapshot'] = $evidence;
        $report['edxeix_payload_preview'] = $edxeixPreview;
        echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
        return !empty($report['ok']) ? 0 : 1;
    }

    $summary = is_array($report['summary'] ?? null) ? $report['summary'] : [];
    echo 'V3 real future candidate capture readiness ' . GOV_V3_REAL_FUTURE_CANDIDATE_CAPTURE_READINESS_VERSION . PHP_EOL;
    echo 'OK: ' . (!empty($report['ok']) ? 'yes' : 'no') . PHP_EOL;
    echo 'Watch snapshot action: ' . (string)($snapshot['action_code'] ?? 'UNKNOWN') . PHP_EOL;
    echo 'Watch snapshot severity: ' . (string)($snapshot['severity'] ?? 'unknown') . PHP_EOL;
    echo 'Future possible-real rows: ' . (string)($summary['future_possible_real_rows'] ?? 0) . PHP_EOL;
    echo 'Complete future rows: ' . (string)($summary['complete_future_possible_real_rows'] ?? 0) . PHP_EOL;
    echo 'Closed-gate review candidates: ' . (string)($summary['closed_gate_operator_review_candidates'] ?? 0) . PHP_EOL;
    echo 'Operator alerts appropriate: ' . (string)($summary['operator_alerts_appropriate'] ?? 0) . PHP_EOL;
    echo 'Urgent/about-to-expire rows: ' . (string)($summary['urgent_or_about_to_expire_rows'] ?? 0) . PHP_EOL;
    echo 'Live risk: ' . (!empty($summary['live_risk_detected']) ? 'yes' : 'no') . PHP_EOL;
    echo 'Final blocks: ' . implode('; ', $report['final_blocks'] ?? []) . PHP_EOL;
    echo 'Manual watch command: watch -n 30 \'/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_real_future_candidate_capture_readiness.php --status-line\'' . PHP_EOL;
    echo 'Evidence snapshot command: /usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_real_future_candidate_capture_readiness.php --evidence-json' . PHP_EOL;
    echo 'EDXEIX dry-run preview command: /usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_real_future_candidate_capture_readiness.php --edxeix-preview-json' . PHP_EOL;

    return !empty($report['ok']) ? 0 : 1;
}

if (PHP_SAPI === 'cli' && realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    exit(gov_v3_real_future_candidate_capture_readiness_main($argv ?? []));
}
