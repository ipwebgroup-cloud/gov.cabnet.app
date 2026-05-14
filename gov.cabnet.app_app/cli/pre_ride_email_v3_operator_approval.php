<?php
/**
 * V3 operator approval workflow for closed-gate rehearsal only.
 *
 * Safety:
 * - No Bolt call.
 * - No EDXEIX call.
 * - No AADE call.
 * - No queue status changes.
 * - No production submission tables.
 * - Does not open or modify the V3 live-submit master gate.
 * - V0 is untouched.
 */

declare(strict_types=1);

const PRV3_APPROVAL_VERSION = 'v3.0.58-v3-operator-approval-workflow';
const PRV3_APPROVAL_PHRASE = 'I APPROVE V3 ROW FOR CLOSED-GATE REHEARSAL ONLY';
const PRV3_REVOKE_PHRASE = 'I REVOKE V3 ROW APPROVAL';
const PRV3_APPROVAL_TABLE = 'pre_ride_email_v3_live_submit_approvals';
const PRV3_QUEUE_TABLE = 'pre_ride_email_v3_queue';
const PRV3_START_OPTIONS_TABLE = 'pre_ride_email_v3_starting_point_options';

function prv3a_args(array $argv): array
{
    $out = [
        'json' => false,
        'approve' => false,
        'revoke' => false,
        'queue_id' => 0,
        'phrase' => '',
        'approved_by' => '',
        'minutes' => 15,
        'help' => false,
    ];

    foreach (array_slice($argv, 1) as $arg) {
        if ($arg === '--json') { $out['json'] = true; continue; }
        if ($arg === '--approve') { $out['approve'] = true; continue; }
        if ($arg === '--revoke') { $out['revoke'] = true; continue; }
        if ($arg === '--help' || $arg === '-h') { $out['help'] = true; continue; }
        if (str_starts_with($arg, '--queue-id=')) { $out['queue_id'] = max(0, (int)substr($arg, 11)); continue; }
        if (str_starts_with($arg, '--phrase=')) { $out['phrase'] = trim((string)substr($arg, 9)); continue; }
        if (str_starts_with($arg, '--approved-by=')) { $out['approved_by'] = trim((string)substr($arg, 14)); continue; }
        if (str_starts_with($arg, '--minutes=')) { $out['minutes'] = max(1, min(240, (int)substr($arg, 10))); continue; }
    }

    if ($out['approve'] && $out['revoke']) {
        $out['approve'] = false;
        $out['revoke'] = false;
    }

    return $out;
}

function prv3a_usage(): string
{
    return implode(PHP_EOL, [
        'V3 operator approval workflow ' . PRV3_APPROVAL_VERSION,
        '',
        'Preview latest/selected row:',
        '  php pre_ride_email_v3_operator_approval.php [--queue-id=56] [--json]',
        '',
        'Approve a currently live_submit_ready row for closed-gate rehearsal only:',
        '  php pre_ride_email_v3_operator_approval.php --queue-id=123 --approve --phrase="' . PRV3_APPROVAL_PHRASE . '" --approved-by="Andreas" --minutes=15',
        '',
        'Revoke valid approvals for a row:',
        '  php pre_ride_email_v3_operator_approval.php --queue-id=123 --revoke --phrase="' . PRV3_REVOKE_PHRASE . '" --approved-by="Andreas"',
        '',
        'Safety: no Bolt, no EDXEIX, no AADE, no queue status changes, no V0 changes.',
    ]) . PHP_EOL;
}

function prv3a_db(): mysqli
{
    $bootstrap = require dirname(__DIR__) . '/src/bootstrap.php';
    if (!isset($bootstrap['db']) || !is_object($bootstrap['db']) || !method_exists($bootstrap['db'], 'connection')) {
        throw new RuntimeException('Unable to load Bridge database connection from bootstrap.');
    }
    /** @var mysqli $db */
    $db = $bootstrap['db']->connection();
    return $db;
}

function prv3a_table_exists(mysqli $db, string $table): bool
{
    $stmt = $db->prepare('SELECT COUNT(*) AS c FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
    $stmt->bind_param('s', $table);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc() ?: [];
    return (int)($row['c'] ?? 0) > 0;
}

function prv3a_columns(mysqli $db, string $table): array
{
    $stmt = $db->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION');
    $stmt->bind_param('s', $table);
    $stmt->execute();
    $result = $stmt->get_result();
    $cols = [];
    while ($row = $result->fetch_assoc()) {
        $cols[] = (string)$row['COLUMN_NAME'];
    }
    return $cols;
}

function prv3a_fetch_row(mysqli $db, int $queueId): ?array
{
    if ($queueId > 0) {
        $stmt = $db->prepare('SELECT *, TIMESTAMPDIFF(MINUTE, NOW(), pickup_datetime) AS minutes_until_now FROM ' . PRV3_QUEUE_TABLE . ' WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $queueId);
    } else {
        $stmt = $db->prepare("SELECT *, TIMESTAMPDIFF(MINUTE, NOW(), pickup_datetime) AS minutes_until_now FROM " . PRV3_QUEUE_TABLE . " ORDER BY CASE WHEN queue_status = 'live_submit_ready' THEN 0 WHEN queue_status = 'submit_dry_run_ready' THEN 1 WHEN queue_status = 'queued' THEN 2 ELSE 3 END, COALESCE(pickup_datetime, created_at) DESC, id DESC LIMIT 1");
    }
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return is_array($row) ? $row : null;
}

function prv3a_starting_point_verified(mysqli $db, string $lessorId, string $startId): array
{
    if ($lessorId === '' || $startId === '') {
        return ['ok' => false, 'label' => '', 'reason' => 'missing_lesssor_or_starting_point'];
    }
    if (!prv3a_table_exists($db, PRV3_START_OPTIONS_TABLE)) {
        return ['ok' => false, 'label' => '', 'reason' => 'starting_point_options_table_missing'];
    }
    $stmt = $db->prepare('SELECT label FROM ' . PRV3_START_OPTIONS_TABLE . ' WHERE edxeix_lessor_id = ? AND edxeix_starting_point_id = ? AND is_active = 1 LIMIT 1');
    $stmt->bind_param('ss', $lessorId, $startId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if (!is_array($row)) {
        return ['ok' => false, 'label' => '', 'reason' => 'starting_point_not_operator_verified'];
    }
    return ['ok' => true, 'label' => (string)($row['label'] ?? ''), 'reason' => 'operator_verified'];
}

function prv3a_required_field_values(array $row): array
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

function prv3a_missing_required(array $values): array
{
    $missing = [];
    foreach ($values as $key => $value) {
        if ($value === '') { $missing[] = $key; }
    }
    return $missing;
}

function prv3a_valid_approval(mysqli $db, int $queueId): array
{
    if (!prv3a_table_exists($db, PRV3_APPROVAL_TABLE)) {
        return ['exists' => false, 'valid' => false, 'rows' => [], 'count' => 0];
    }
    $stmt = $db->prepare("SELECT * FROM " . PRV3_APPROVAL_TABLE . " WHERE queue_id = ? AND approval_status = 'approved' AND revoked_at IS NULL AND (expires_at IS NULL OR expires_at > NOW()) ORDER BY approved_at DESC, id DESC LIMIT 5");
    $stmt->bind_param('i', $queueId);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];
    while ($row = $result->fetch_assoc()) { $rows[] = $row; }
    return ['exists' => true, 'valid' => count($rows) > 0, 'rows' => $rows, 'count' => count($rows)];
}

function prv3a_build_report(mysqli $db, array $opts): array
{
    $report = [
        'ok' => false,
        'version' => PRV3_APPROVAL_VERSION,
        'mode' => $opts['approve'] ? 'approve' : ($opts['revoke'] ? 'revoke' : 'preview'),
        'started_at' => gmdate('c'),
        'safety' => 'No Bolt call. No EDXEIX call. No AADE call. No queue status changes. No production submission tables. V0 untouched.',
        'required_approval_phrase' => PRV3_APPROVAL_PHRASE,
        'required_revoke_phrase' => PRV3_REVOKE_PHRASE,
        'events' => [],
        'schema' => [
            'queue_table' => prv3a_table_exists($db, PRV3_QUEUE_TABLE),
            'approval_table' => prv3a_table_exists($db, PRV3_APPROVAL_TABLE),
            'start_options_table' => prv3a_table_exists($db, PRV3_START_OPTIONS_TABLE),
            'approval_columns' => prv3a_table_exists($db, PRV3_APPROVAL_TABLE) ? prv3a_columns($db, PRV3_APPROVAL_TABLE) : [],
        ],
        'selected_queue_row' => null,
        'eligibility' => [
            'eligible_for_closed_gate_approval' => false,
            'blocks' => [],
            'required_fields' => [],
            'missing_required_fields' => [],
            'starting_point' => ['ok' => false, 'label' => '', 'reason' => 'not_checked'],
            'minutes_until_now' => null,
        ],
        'approval' => ['valid_existing' => false, 'count' => 0, 'rows' => []],
        'write' => ['attempted' => false, 'inserted' => false, 'revoked_count' => 0, 'message' => 'preview only'],
        'finished_at' => '',
    ];

    if (!$report['schema']['queue_table']) {
        $report['eligibility']['blocks'][] = 'queue table missing';
        $report['finished_at'] = gmdate('c');
        return $report;
    }
    if (!$report['schema']['approval_table']) {
        $report['eligibility']['blocks'][] = 'approval table missing';
    }

    $row = prv3a_fetch_row($db, (int)$opts['queue_id']);
    if (!$row) {
        $report['eligibility']['blocks'][] = (int)$opts['queue_id'] > 0 ? 'selected queue row not found' : 'no queue rows found';
        $report['finished_at'] = gmdate('c');
        return $report;
    }

    $report['selected_queue_row'] = $row;
    $queueId = (int)$row['id'];
    $status = (string)($row['queue_status'] ?? '');
    $minutesUntil = isset($row['minutes_until_now']) ? (int)$row['minutes_until_now'] : null;
    $report['eligibility']['minutes_until_now'] = $minutesUntil;

    $values = prv3a_required_field_values($row);
    $missing = prv3a_missing_required($values);
    $start = prv3a_starting_point_verified($db, (string)($row['lessor_id'] ?? ''), (string)($row['starting_point_id'] ?? ''));
    $approval = prv3a_valid_approval($db, $queueId);

    $report['eligibility']['required_fields'] = $values;
    $report['eligibility']['missing_required_fields'] = $missing;
    $report['eligibility']['starting_point'] = $start;
    $report['approval'] = [
        'valid_existing' => (bool)($approval['valid'] ?? false),
        'count' => (int)($approval['count'] ?? 0),
        'rows' => (array)($approval['rows'] ?? []),
    ];

    if ($status !== 'live_submit_ready') {
        $report['eligibility']['blocks'][] = 'queue row is not live_submit_ready';
    }
    if ($minutesUntil === null || $minutesUntil < 1) {
        $report['eligibility']['blocks'][] = 'pickup is not future-safe';
    }
    if ($missing !== []) {
        $report['eligibility']['blocks'][] = 'missing required fields: ' . implode(', ', $missing);
    }
    if (!$start['ok']) {
        $report['eligibility']['blocks'][] = 'starting point is not operator-verified: ' . (string)$start['reason'];
    }

    $report['eligibility']['eligible_for_closed_gate_approval'] = $report['eligibility']['blocks'] === [];

    if ($opts['approve']) {
        $report['write']['attempted'] = true;
        if ($opts['phrase'] !== PRV3_APPROVAL_PHRASE) {
            $report['write']['message'] = 'approval phrase mismatch; no write';
            $report['eligibility']['blocks'][] = 'approval phrase mismatch';
        } elseif (!$report['schema']['approval_table']) {
            $report['write']['message'] = 'approval table missing; no write';
        } elseif (!$report['eligibility']['eligible_for_closed_gate_approval']) {
            $report['write']['message'] = 'row not eligible for approval; no write';
        } elseif (!empty($approval['valid'])) {
            $report['write']['message'] = 'row already has a valid approval; no duplicate write';
        } else {
            $approvedBy = $opts['approved_by'] !== '' ? $opts['approved_by'] : ('cli:' . get_current_user());
            $minutes = max(1, min(240, (int)$opts['minutes']));
            $scope = 'closed_gate_rehearsal_only';
            $note = 'V3 closed-gate rehearsal approval only. This does not open the live-submit gate and does not submit to EDXEIX.';
            $snapshot = json_encode([
                'approved_with_version' => PRV3_APPROVAL_VERSION,
                'queue_row' => $row,
                'required_fields' => $values,
                'starting_point' => $start,
                'safety' => $report['safety'],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
            if ($snapshot === false) { $snapshot = '{}'; }
            $dedupe = (string)($row['dedupe_key'] ?? '');
            $stmt = $db->prepare('INSERT INTO ' . PRV3_APPROVAL_TABLE . ' (queue_id, dedupe_key, approval_status, approval_scope, approved_by, approved_at, expires_at, approval_note, row_snapshot_json, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL ? MINUTE), ?, ?, NOW(), NOW())');
            $statusApproved = 'approved';
            $stmt->bind_param('issssiss', $queueId, $dedupe, $statusApproved, $scope, $approvedBy, $minutes, $note, $snapshot);
            $stmt->execute();
            $report['write']['inserted'] = $stmt->affected_rows > 0;
            $report['write']['message'] = $report['write']['inserted'] ? 'approval inserted for closed-gate rehearsal only' : 'approval insert affected no rows';
            $report['approval'] = [
                'valid_existing' => true,
                'count' => 1,
                'rows' => (array)(prv3a_valid_approval($db, $queueId)['rows'] ?? []),
            ];
        }
    }

    if ($opts['revoke']) {
        $report['write']['attempted'] = true;
        if ($opts['phrase'] !== PRV3_REVOKE_PHRASE) {
            $report['write']['message'] = 'revoke phrase mismatch; no write';
        } elseif (!$report['schema']['approval_table']) {
            $report['write']['message'] = 'approval table missing; no write';
        } else {
            $approvedBy = $opts['approved_by'] !== '' ? $opts['approved_by'] : ('cli:' . get_current_user());
            $note = 'Revoked by ' . $approvedBy . ' at ' . gmdate('c') . '. Previous note: ';
            $stmt = $db->prepare("UPDATE " . PRV3_APPROVAL_TABLE . " SET approval_status = 'revoked', revoked_at = NOW(), approval_note = CONCAT(?, COALESCE(approval_note, '')), updated_at = NOW() WHERE queue_id = ? AND approval_status = 'approved' AND revoked_at IS NULL AND (expires_at IS NULL OR expires_at > NOW())");
            $stmt->bind_param('si', $note, $queueId);
            $stmt->execute();
            $report['write']['revoked_count'] = max(0, (int)$stmt->affected_rows);
            $report['write']['message'] = 'valid approvals revoked: ' . (string)$report['write']['revoked_count'];
            $report['approval'] = [
                'valid_existing' => false,
                'count' => 0,
                'rows' => (array)(prv3a_valid_approval($db, $queueId)['rows'] ?? []),
            ];
        }
    }

    $report['ok'] = $report['schema']['queue_table'] && $report['schema']['approval_table'] && $row !== null;
    $report['finished_at'] = gmdate('c');
    return $report;
}

function prv3a_echo_text(array $r): void
{
    echo 'V3 operator approval workflow ' . (string)$r['version'] . PHP_EOL;
    echo 'Mode: ' . (string)$r['mode'] . PHP_EOL;
    echo 'Safety: ' . (string)$r['safety'] . PHP_EOL;
    echo 'OK: ' . (!empty($r['ok']) ? 'yes' : 'no') . PHP_EOL;
    $row = is_array($r['selected_queue_row'] ?? null) ? $r['selected_queue_row'] : [];
    if ($row !== []) {
        echo 'Selected row: #' . (string)($row['id'] ?? '') . ' status=' . (string)($row['queue_status'] ?? '') . PHP_EOL;
        echo 'Transfer: ' . (string)($row['customer_name'] ?? '') . ' | ' . (string)($row['driver_name'] ?? '') . ' | ' . (string)($row['vehicle_plate'] ?? '') . PHP_EOL;
        echo 'Pickup: ' . (string)($row['pickup_datetime'] ?? '') . ' | minutes_until_now=' . (string)($r['eligibility']['minutes_until_now'] ?? '') . PHP_EOL;
    }
    echo 'Eligible for closed-gate approval: ' . (!empty($r['eligibility']['eligible_for_closed_gate_approval']) ? 'yes' : 'no') . PHP_EOL;
    echo 'Starting point: ' . (!empty($r['eligibility']['starting_point']['ok']) ? 'verified' : 'not verified') . ' — ' . (string)($r['eligibility']['starting_point']['reason'] ?? '') . PHP_EOL;
    $missing = (array)($r['eligibility']['missing_required_fields'] ?? []);
    echo 'Missing required fields: ' . ($missing === [] ? 'none' : implode(', ', $missing)) . PHP_EOL;
    $blocks = (array)($r['eligibility']['blocks'] ?? []);
    if ($blocks !== []) {
        echo 'Blocks:' . PHP_EOL;
        foreach ($blocks as $block) { echo '  - ' . (string)$block . PHP_EOL; }
    }
    echo 'Valid approval exists: ' . (!empty($r['approval']['valid_existing']) ? 'yes' : 'no') . ' count=' . (string)($r['approval']['count'] ?? 0) . PHP_EOL;
    echo 'Write attempted: ' . (!empty($r['write']['attempted']) ? 'yes' : 'no') . ' | inserted=' . (!empty($r['write']['inserted']) ? 'yes' : 'no') . ' | revoked=' . (string)($r['write']['revoked_count'] ?? 0) . PHP_EOL;
    echo 'Message: ' . (string)($r['write']['message'] ?? '') . PHP_EOL;
    echo 'Approval phrase: ' . PRV3_APPROVAL_PHRASE . PHP_EOL;
    echo 'Revoke phrase: ' . PRV3_REVOKE_PHRASE . PHP_EOL;
}

$args = prv3a_args($argv);
if (!empty($args['help'])) {
    echo prv3a_usage();
    exit(0);
}

try {
    $report = prv3a_build_report(prv3a_db(), $args);
    if ($args['json']) {
        echo json_encode($report, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;
    } else {
        prv3a_echo_text($report);
    }
    $mode = (string)$report['mode'];
    if (($mode === 'approve' || $mode === 'revoke') && empty($report['write']['inserted']) && (int)($report['write']['revoked_count'] ?? 0) < 1) {
        exit(2);
    }
    exit(!empty($report['ok']) ? 0 : 1);
} catch (Throwable $e) {
    $err = [
        'ok' => false,
        'version' => PRV3_APPROVAL_VERSION,
        'error' => $e->getMessage(),
        'safety' => 'No Bolt call. No EDXEIX call. No AADE call. No queue status changes. No production submission tables. V0 untouched.',
    ];
    if (!empty($args['json'])) {
        echo json_encode($err, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;
    } else {
        echo 'ERROR: ' . $e->getMessage() . PHP_EOL;
    }
    exit(1);
}
