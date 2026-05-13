<?php
/**
 * gov.cabnet.app — V3 live-submit worker scaffold with master gate + approval enforcement.
 *
 * Purpose:
 * - Final pre-live worker shape for the isolated V3 queue.
 * - Reads live_submit_ready rows and performs strict final checks.
 * - Enforces the V3 master gate and V3 operator approval ledger.
 * - DOES NOT submit to EDXEIX. Live submit remains hard-disabled in this scaffold.
 *
 * Safety:
 * - No EDXEIX calls.
 * - No AADE calls.
 * - No production submission_jobs writes.
 * - No production submission_attempts writes.
 * - No production pre-ride-email-tool.php changes.
 * - Optional --commit-disabled-event writes only V3 queue events, throttled.
 */

declare(strict_types=1);

const PRV3_LIVE_SUBMIT_WORKER_VERSION = 'v3.0.27-live-submit-gate-approval-scaffold';
const PRV3_LIVE_QUEUE_TABLE = 'pre_ride_email_v3_queue';
const PRV3_LIVE_EVENTS_TABLE = 'pre_ride_email_v3_queue_events';
const PRV3_LIVE_START_OPTIONS_TABLE = 'pre_ride_email_v3_starting_point_options';
const PRV3_LIVE_APPROVAL_TABLE = 'pre_ride_email_v3_live_submit_approvals';
const PRV3_LIVE_SUBMIT_HARD_ENABLED = false;

date_default_timezone_set('Europe/Athens');

/** @return array<string,mixed> */
function prv3ls_parse_args(array $argv): array
{
    $opts = [
        'help' => false,
        'json' => false,
        'limit' => 20,
        'status' => 'live_submit_ready',
        'min_future_minutes' => 0,
        'commit_disabled_event' => false,
    ];

    foreach (array_slice($argv, 1) as $arg) {
        if ($arg === '--help' || $arg === '-h') {
            $opts['help'] = true;
        } elseif ($arg === '--json') {
            $opts['json'] = true;
        } elseif ($arg === '--commit-disabled-event') {
            $opts['commit_disabled_event'] = true;
        } elseif (str_starts_with($arg, '--limit=')) {
            $opts['limit'] = max(1, min(100, (int)substr($arg, 8)));
        } elseif (str_starts_with($arg, '--status=')) {
            $status = trim(substr($arg, 9));
            if ($status !== '') {
                $opts['status'] = $status;
            }
        } elseif (str_starts_with($arg, '--min-future-minutes=')) {
            $opts['min_future_minutes'] = max(0, min(240, (int)substr($arg, 21)));
        }
    }

    return $opts;
}

function prv3ls_print_help(): void
{
    echo "V3 live-submit worker scaffold " . PRV3_LIVE_SUBMIT_WORKER_VERSION . "\n\n";
    echo "Usage:\n";
    echo "  php pre_ride_email_v3_live_submit_worker.php [--limit=20] [--json] [--commit-disabled-event]\n\n";
    echo "Safety:\n";
    echo "  Live EDXEIX submit is hard-disabled in this scaffold.\n";
    echo "  The worker now requires the master gate and a valid per-row approval before a row is considered final-live eligible.\n";
    echo "  --commit-disabled-event records throttled V3-only audit events; no queue status changes.\n";
}

/** @return array<string,mixed> */
function prv3ls_bootstrap_context(): array
{
    $appRoot = dirname(__DIR__);
    $bootstrap = $appRoot . '/src/bootstrap.php';
    if (!is_file($bootstrap)) {
        $bootstrap = dirname(__DIR__, 2) . '/src/bootstrap.php';
        $appRoot = dirname($bootstrap, 2);
    }
    if (!is_file($bootstrap)) {
        throw new RuntimeException('Private app bootstrap not found from CLI path.');
    }

    $gateFile = $appRoot . '/src/BoltMailV3/LiveSubmitGateV3.php';
    if (!is_file($gateFile)) {
        throw new RuntimeException('V3 LiveSubmitGateV3.php is missing. Install the live-submit master gate patch first.');
    }
    require_once $gateFile;

    $ctx = require $bootstrap;
    if (!is_array($ctx) || !isset($ctx['db']) || !method_exists($ctx['db'], 'connection')) {
        throw new RuntimeException('Private app bootstrap did not return a usable DB context.');
    }
    return $ctx;
}

function prv3ls_table_exists(mysqli $db, string $table): bool
{
    $stmt = $db->prepare('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1');
    if (!$stmt) { return false; }
    $stmt->bind_param('s', $table);
    $stmt->execute();
    return (bool)$stmt->get_result()->fetch_assoc();
}

function prv3ls_db_name(mysqli $db): string
{
    $res = $db->query('SELECT DATABASE() AS db');
    $row = $res ? $res->fetch_assoc() : null;
    return is_array($row) ? (string)($row['db'] ?? '') : '';
}

/** @return array<int,array<string,mixed>> */
function prv3ls_fetch_rows(mysqli $db, string $status, int $limit): array
{
    $sql = 'SELECT * FROM ' . PRV3_LIVE_QUEUE_TABLE . ' WHERE queue_status = ? ORDER BY pickup_datetime ASC, id ASC LIMIT ?';
    $stmt = $db->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException('Failed to prepare V3 live queue select: ' . $db->error);
    }
    $stmt->bind_param('si', $status, $limit);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($row = $res->fetch_assoc()) {
        $rows[] = $row;
    }
    return $rows;
}

/** @return array{ok:bool,minutes_until:int|null,message:string} */
function prv3ls_pickup_future_check(?string $pickup, int $minFutureMinutes): array
{
    $pickup = trim((string)$pickup);
    if ($pickup === '' || $pickup === '0000-00-00 00:00:00') {
        return ['ok' => false, 'minutes_until' => null, 'message' => 'Pickup datetime is missing.'];
    }
    try {
        $tz = new DateTimeZone('Europe/Athens');
        $pickupDt = new DateTimeImmutable($pickup, $tz);
        $now = new DateTimeImmutable('now', $tz);
        $minutes = (int)floor(($pickupDt->getTimestamp() - $now->getTimestamp()) / 60);
        if ($minutes < $minFutureMinutes) {
            return ['ok' => false, 'minutes_until' => $minutes, 'message' => 'Pickup is only ' . $minutes . ' minutes from now. Live-submit scaffold requires at least ' . $minFutureMinutes . ' minute(s) in the future.'];
        }
        return ['ok' => true, 'minutes_until' => $minutes, 'message' => 'Pickup is future-safe for live-submit scaffold.'];
    } catch (Throwable $e) {
        return ['ok' => false, 'minutes_until' => null, 'message' => 'Pickup datetime could not be parsed: ' . $e->getMessage()];
    }
}

/** @return array{ok:bool,message:string} */
function prv3ls_end_after_pickup_check(?string $pickup, ?string $end): array
{
    $pickup = trim((string)$pickup);
    $end = trim((string)$end);
    if ($pickup === '' || $end === '') {
        return ['ok' => false, 'message' => 'Pickup or estimated end datetime is missing.'];
    }
    try {
        $tz = new DateTimeZone('Europe/Athens');
        $pickupDt = new DateTimeImmutable($pickup, $tz);
        $endDt = new DateTimeImmutable($end, $tz);
        if ($endDt->getTimestamp() <= $pickupDt->getTimestamp()) {
            return ['ok' => false, 'message' => 'Estimated end datetime is not after pickup datetime.'];
        }
        return ['ok' => true, 'message' => 'Estimated end is after pickup.'];
    } catch (Throwable $e) {
        return ['ok' => false, 'message' => 'Estimated end datetime could not be parsed: ' . $e->getMessage()];
    }
}

function prv3ls_valid_json(?string $json): bool
{
    $json = trim((string)$json);
    if ($json === '') { return false; }
    json_decode($json, true);
    return json_last_error() === JSON_ERROR_NONE;
}

/** @return array{ok:bool,message:string} */
function prv3ls_start_option_check(mysqli $db, string $lessorId, string $startingPointId): array
{
    $lessorId = trim($lessorId);
    $startingPointId = trim($startingPointId);
    if ($lessorId === '' || $startingPointId === '') {
        return ['ok' => false, 'message' => 'Missing lessor or starting point ID for verified starting-point guard.'];
    }
    if (!prv3ls_table_exists($db, PRV3_LIVE_START_OPTIONS_TABLE)) {
        return ['ok' => false, 'message' => 'Verified starting-point options table is missing.'];
    }
    $stmt = $db->prepare('SELECT id FROM ' . PRV3_LIVE_START_OPTIONS_TABLE . ' WHERE edxeix_lessor_id = ? AND edxeix_starting_point_id = ? AND is_active = 1 LIMIT 1');
    if (!$stmt) {
        return ['ok' => false, 'message' => 'Could not prepare verified starting-point lookup: ' . $db->error];
    }
    $stmt->bind_param('ss', $lessorId, $startingPointId);
    $stmt->execute();
    if ($stmt->get_result()->fetch_assoc()) {
        return ['ok' => true, 'message' => 'Starting point is verified for lessor ' . $lessorId . '.'];
    }
    return ['ok' => false, 'message' => 'Starting point ' . $startingPointId . ' is not verified for lessor ' . $lessorId . '.'];
}

/** @return array<string,mixed> */
function prv3ls_gate_status(): array
{
    if (!class_exists('Bridge\\BoltMailV3\\LiveSubmitGateV3')) {
        return [
            'ok_for_future_live_submit' => false,
            'min_future_minutes' => 1,
            'required_queue_status' => 'live_submit_ready',
            'operator_approval_required' => true,
            'allowed_lessors' => [],
            'blocks' => ['LiveSubmitGateV3 class is not loaded.'],
            'warnings' => [],
            'version' => 'missing',
        ];
    }
    return \Bridge\BoltMailV3\LiveSubmitGateV3::evaluate();
}

/** @return array<string,mixed> */
function prv3ls_approval_status(mysqli $db, array $row, bool $approvalRequired): array
{
    $queueId = (int)($row['id'] ?? 0);
    $dedupeKey = (string)($row['dedupe_key'] ?? '');
    $out = [
        'required' => $approvalRequired,
        'table_ok' => prv3ls_table_exists($db, PRV3_LIVE_APPROVAL_TABLE),
        'present' => false,
        'valid' => false,
        'status' => '',
        'approved_by' => '',
        'approved_at' => '',
        'expires_at' => '',
        'revoked_at' => '',
        'blocks' => [],
        'warnings' => [],
    ];

    if (!$out['table_ok']) {
        if ($approvalRequired) {
            $out['blocks'][] = 'V3 live-submit approval table is missing.';
        } else {
            $out['warnings'][] = 'V3 live-submit approval table is missing, but approval is not required by gate.';
        }
        return $out;
    }

    $stmt = $db->prepare('SELECT approval_status, approved_by, approved_at, expires_at, revoked_at, approval_note, dedupe_key FROM ' . PRV3_LIVE_APPROVAL_TABLE . ' WHERE queue_id = ? ORDER BY id DESC LIMIT 1');
    if (!$stmt) {
        $out['blocks'][] = 'Could not prepare approval lookup: ' . $db->error;
        return $out;
    }
    $stmt->bind_param('i', $queueId);
    $stmt->execute();
    $approval = $stmt->get_result()->fetch_assoc();
    if (!is_array($approval)) {
        if ($approvalRequired) {
            $out['blocks'][] = 'Operator approval is required but no V3 live-submit approval exists for this row.';
        }
        return $out;
    }

    $out['present'] = true;
    $out['status'] = (string)($approval['approval_status'] ?? '');
    $out['approved_by'] = (string)($approval['approved_by'] ?? '');
    $out['approved_at'] = (string)($approval['approved_at'] ?? '');
    $out['expires_at'] = (string)($approval['expires_at'] ?? '');
    $out['revoked_at'] = (string)($approval['revoked_at'] ?? '');

    if ((string)($approval['dedupe_key'] ?? '') !== $dedupeKey) {
        $out['blocks'][] = 'Approval dedupe_key does not match current queue row.';
    }
    if ($out['status'] !== 'approved') {
        $out['blocks'][] = 'Approval status is not approved.';
    }
    if (trim($out['revoked_at']) !== '') {
        $out['blocks'][] = 'Approval was revoked.';
    }
    if (trim($out['expires_at']) === '') {
        $out['blocks'][] = 'Approval expires_at is missing.';
    } else {
        $expiryTs = strtotime($out['expires_at']);
        if ($expiryTs === false || $expiryTs <= time()) {
            $out['blocks'][] = 'Approval is expired.';
        }
    }

    $out['valid'] = count($out['blocks']) === 0;
    return $out;
}

/** @return array<string,mixed> */
function prv3ls_check_row(mysqli $db, array $row, int $minFutureMinutes, array $gate): array
{
    $blocks = [];
    $warnings = [];

    $gateOk = !empty($gate['ok_for_future_live_submit']);
    $operatorApprovalRequired = filter_var($gate['operator_approval_required'] ?? true, FILTER_VALIDATE_BOOLEAN);
    $requiredStatus = trim((string)($gate['required_queue_status'] ?? 'live_submit_ready'));
    if ($requiredStatus === '') { $requiredStatus = 'live_submit_ready'; }

    if (!$gateOk) {
        foreach ((array)($gate['blocks'] ?? []) as $block) {
            $blocks[] = 'Master gate: ' . (string)$block;
        }
    }
    foreach ((array)($gate['warnings'] ?? []) as $warning) {
        $warnings[] = 'Master gate: ' . (string)$warning;
    }

    $lessorId = (string)($row['lessor_id'] ?? '');
    $allowedLessors = array_map('strval', (array)($gate['allowed_lessors'] ?? []));
    if ($allowedLessors !== [] && !in_array($lessorId, $allowedLessors, true)) {
        $blocks[] = 'Lessor ' . $lessorId . ' is not allowed by live-submit gate config.';
    }

    if ((string)($row['queue_status'] ?? '') !== $requiredStatus) {
        $blocks[] = 'queue_status is not required gate status ' . $requiredStatus . '.';
    }
    if ((int)($row['parser_ok'] ?? 0) !== 1) { $blocks[] = 'parser_ok is not 1.'; }
    if ((int)($row['mapping_ok'] ?? 0) !== 1) { $blocks[] = 'mapping_ok is not 1.'; }
    if ((int)($row['future_ok'] ?? 0) !== 1) { $blocks[] = 'future_ok is not 1.'; }

    foreach ([
        'lessor_id' => 'lessor ID',
        'driver_id' => 'driver ID',
        'vehicle_id' => 'vehicle ID',
        'starting_point_id' => 'starting point ID',
        'customer_name' => 'customer name',
        'customer_phone' => 'customer phone',
        'pickup_address' => 'pickup address',
        'dropoff_address' => 'drop-off address',
        'pickup_datetime' => 'pickup datetime',
        'estimated_end_datetime' => 'estimated end datetime',
    ] as $key => $label) {
        if (trim((string)($row[$key] ?? '')) === '') { $blocks[] = 'Missing ' . $label . '.'; }
    }

    $future = prv3ls_pickup_future_check($row['pickup_datetime'] ?? null, $minFutureMinutes);
    if (!$future['ok']) { $blocks[] = $future['message']; }

    $end = prv3ls_end_after_pickup_check($row['pickup_datetime'] ?? null, $row['estimated_end_datetime'] ?? null);
    if (!$end['ok']) { $blocks[] = $end['message']; }

    $priceRaw = $row['price_amount'] ?? null;
    if ($priceRaw === null || trim((string)$priceRaw) === '' || (float)$priceRaw <= 0) {
        $blocks[] = 'Missing or invalid positive price amount.';
    }
    if (!prv3ls_valid_json($row['payload_json'] ?? '')) {
        $blocks[] = 'payload_json is missing or invalid JSON.';
    }

    $start = prv3ls_start_option_check($db, (string)($row['lessor_id'] ?? ''), (string)($row['starting_point_id'] ?? ''));
    if (!$start['ok']) { $blocks[] = $start['message']; }

    $approval = prv3ls_approval_status($db, $row, $operatorApprovalRequired);
    foreach ((array)$approval['blocks'] as $block) { $blocks[] = (string)$block; }
    foreach ((array)$approval['warnings'] as $warning) { $warnings[] = (string)$warning; }

    if (!PRV3_LIVE_SUBMIT_HARD_ENABLED) {
        $warnings[] = 'Live EDXEIX submit is hard-disabled in this scaffold.';
    }

    $rowPassesAllPreLiveGates = count($blocks) === 0;

    return [
        'queue_id' => (int)($row['id'] ?? 0),
        'dedupe_key' => (string)($row['dedupe_key'] ?? ''),
        'customer_name' => (string)($row['customer_name'] ?? ''),
        'pickup_datetime' => (string)($row['pickup_datetime'] ?? ''),
        'minutes_until' => $future['minutes_until'],
        'driver_name' => (string)($row['driver_name'] ?? ''),
        'vehicle_plate' => (string)($row['vehicle_plate'] ?? ''),
        'ids' => [
            'lessor_id' => (string)($row['lessor_id'] ?? ''),
            'driver_id' => (string)($row['driver_id'] ?? ''),
            'vehicle_id' => (string)($row['vehicle_id'] ?? ''),
            'starting_point_id' => (string)($row['starting_point_id'] ?? ''),
        ],
        'gate_ok' => $gateOk,
        'operator_approval_required' => $operatorApprovalRequired,
        'approval' => $approval,
        'pre_live_gates_passed' => $rowPassesAllPreLiveGates,
        'eligible_for_future_live_worker' => $rowPassesAllPreLiveGates && PRV3_LIVE_SUBMIT_HARD_ENABLED,
        'eligible_but_hard_disabled' => $rowPassesAllPreLiveGates && !PRV3_LIVE_SUBMIT_HARD_ENABLED,
        'live_submit_hard_enabled' => PRV3_LIVE_SUBMIT_HARD_ENABLED,
        'blocked_from_submit' => true,
        'block_reasons' => array_values(array_unique($blocks)),
        'warnings' => array_values(array_unique($warnings)),
    ];
}

function prv3ls_recent_event_exists(mysqli $db, int $queueId, string $type, int $minutes = 60): bool
{
    $stmt = $db->prepare('SELECT id FROM ' . PRV3_LIVE_EVENTS_TABLE . ' WHERE queue_id = ? AND event_type = ? AND created_at >= DATE_SUB(NOW(), INTERVAL ? MINUTE) LIMIT 1');
    if (!$stmt) { return true; }
    $stmt->bind_param('isi', $queueId, $type, $minutes);
    $stmt->execute();
    return (bool)$stmt->get_result()->fetch_assoc();
}

function prv3ls_insert_event(mysqli $db, int $queueId, string $dedupeKey, string $type, string $status, string $message, array $context): void
{
    $json = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($json)) { $json = '{}'; }
    $createdBy = 'v3_live_submit_worker_scaffold';
    $stmt = $db->prepare('INSERT INTO ' . PRV3_LIVE_EVENTS_TABLE . ' (queue_id, dedupe_key, event_type, event_status, event_message, event_context_json, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)');
    if (!$stmt) { throw new RuntimeException('Failed to prepare V3 live event insert: ' . $db->error); }
    $stmt->bind_param('issssss', $queueId, $dedupeKey, $type, $status, $message, $json, $createdBy);
    if (!$stmt->execute()) { throw new RuntimeException('Failed to insert V3 live event: ' . $stmt->error); }
}

$opts = prv3ls_parse_args($argv);
if ($opts['help']) {
    prv3ls_print_help();
    exit(0);
}

$summary = [
    'ok' => false,
    'version' => PRV3_LIVE_SUBMIT_WORKER_VERSION,
    'mode' => $opts['commit_disabled_event'] ? 'commit_v3_disabled_event_only' : 'dry_run_select_only',
    'started_at' => (new DateTimeImmutable('now', new DateTimeZone('Europe/Athens')))->format(DATE_ATOM),
    'finished_at' => '',
    'database' => '',
    'limit' => (int)$opts['limit'],
    'status_filter' => (string)$opts['status'],
    'min_future_minutes' => (int)$opts['min_future_minutes'],
    'schema_ok' => false,
    'start_options_ok' => false,
    'approval_table_ok' => false,
    'gate_ok' => false,
    'gate_version' => '',
    'gate_mode' => '',
    'gate_adapter' => '',
    'gate_config_loaded' => false,
    'operator_approval_required' => true,
    'rows_checked' => 0,
    'pre_live_passed_count' => 0,
    'eligible_count' => 0,
    'eligible_but_hard_disabled_count' => 0,
    'blocked_count' => 0,
    'valid_approval_count' => 0,
    'warning_count' => 0,
    'events_inserted' => 0,
    'live_submit_hard_enabled' => PRV3_LIVE_SUBMIT_HARD_ENABLED,
    'safety' => [
        'edxeix_server_call' => false,
        'aade_call' => false,
        'production_submission_jobs' => false,
        'production_submission_attempts' => false,
        'queue_status_changed' => false,
        'v3_events_only_when_commit_disabled_event' => true,
    ],
    'error' => '',
];
$results = [];
$gate = [];

try {
    $ctx = prv3ls_bootstrap_context();
    /** @var mysqli $db */
    $db = $ctx['db']->connection();
    $db->set_charset('utf8mb4');
    $summary['database'] = prv3ls_db_name($db);

    $gate = prv3ls_gate_status();
    $summary['gate_ok'] = !empty($gate['ok_for_future_live_submit']);
    $summary['gate_version'] = (string)($gate['version'] ?? '');
    $summary['gate_mode'] = (string)($gate['mode'] ?? '');
    $summary['gate_adapter'] = (string)($gate['adapter'] ?? '');
    $summary['gate_config_loaded'] = !empty($gate['config_loaded']);
    $summary['operator_approval_required'] = filter_var($gate['operator_approval_required'] ?? true, FILTER_VALIDATE_BOOLEAN);

    $gateMinFuture = max(0, (int)($gate['min_future_minutes'] ?? 1));
    $summary['min_future_minutes'] = max((int)$opts['min_future_minutes'], $gateMinFuture);
    $requiredStatus = trim((string)($gate['required_queue_status'] ?? 'live_submit_ready'));
    if ($requiredStatus !== '' && (string)$opts['status'] === 'live_submit_ready') {
        $opts['status'] = $requiredStatus;
        $summary['status_filter'] = $requiredStatus;
    }

    $summary['schema_ok'] = prv3ls_table_exists($db, PRV3_LIVE_QUEUE_TABLE) && prv3ls_table_exists($db, PRV3_LIVE_EVENTS_TABLE);
    $summary['start_options_ok'] = prv3ls_table_exists($db, PRV3_LIVE_START_OPTIONS_TABLE);
    $summary['approval_table_ok'] = prv3ls_table_exists($db, PRV3_LIVE_APPROVAL_TABLE);
    if (!$summary['schema_ok']) { throw new RuntimeException('V3 queue schema is not installed.'); }

    $rows = prv3ls_fetch_rows($db, (string)$opts['status'], (int)$opts['limit']);
    $summary['rows_checked'] = count($rows);
    foreach ($rows as $row) {
        $check = prv3ls_check_row($db, $row, (int)$summary['min_future_minutes'], $gate);
        $results[] = $check;
        if ($check['pre_live_gates_passed']) { $summary['pre_live_passed_count']++; }
        if ($check['eligible_for_future_live_worker']) { $summary['eligible_count']++; }
        if ($check['eligible_but_hard_disabled']) { $summary['eligible_but_hard_disabled_count']++; }
        if (!$check['pre_live_gates_passed']) { $summary['blocked_count']++; }
        if (!empty($check['approval']['valid'])) { $summary['valid_approval_count']++; }
        $summary['warning_count'] += count($check['warnings']);

        if ($opts['commit_disabled_event'] && !prv3ls_recent_event_exists($db, (int)$check['queue_id'], 'live_submit_hard_disabled', 60)) {
            prv3ls_insert_event(
                $db,
                (int)$check['queue_id'],
                (string)$check['dedupe_key'],
                'live_submit_hard_disabled',
                'blocked',
                'V3 live-submit scaffold reached this row after gate/approval enforcement, but live EDXEIX submit is hard-disabled. No EDXEIX call was made.',
                ['gate' => $gate, 'row' => $check]
            );
            $summary['events_inserted']++;
        }
    }
    $summary['ok'] = true;
} catch (Throwable $e) {
    $summary['error'] = $e->getMessage();
}

$summary['finished_at'] = (new DateTimeImmutable('now', new DateTimeZone('Europe/Athens')))->format(DATE_ATOM);

if ($opts['json']) {
    echo json_encode(['summary' => $summary, 'gate' => $gate, 'rows' => $results], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "\n";
    exit($summary['ok'] ? 0 : 1);
}

echo 'V3 live-submit worker scaffold ' . PRV3_LIVE_SUBMIT_WORKER_VERSION . "\n";
echo 'Mode: ' . $summary['mode'] . "\n";
echo 'Database: ' . ($summary['database'] ?: '-') . "\n";
echo 'Schema OK: ' . ($summary['schema_ok'] ? 'yes' : 'no') . ' | Starting-point options: ' . ($summary['start_options_ok'] ? 'yes' : 'no') . ' | Approval table: ' . ($summary['approval_table_ok'] ? 'yes' : 'no') . "\n";
echo 'Master gate OK: ' . ($summary['gate_ok'] ? 'yes' : 'no') . ' | config_loaded=' . ($summary['gate_config_loaded'] ? 'yes' : 'no') . ' | mode=' . ($summary['gate_mode'] ?: '-') . ' | adapter=' . ($summary['gate_adapter'] ?: '-') . "\n";
echo 'Rows checked: ' . $summary['rows_checked'] . ' | Pre-live passed: ' . $summary['pre_live_passed_count'] . ' | Eligible hard-enabled: ' . $summary['eligible_count'] . ' | Eligible but disabled: ' . $summary['eligible_but_hard_disabled_count'] . ' | Blocked: ' . $summary['blocked_count'] . "\n";
echo 'Valid approvals: ' . $summary['valid_approval_count'] . ' | Warnings: ' . $summary['warning_count'] . ' | Events inserted: ' . $summary['events_inserted'] . ' | Live submit hard-enabled: ' . ($summary['live_submit_hard_enabled'] ? 'yes' : 'no') . "\n";

if ($summary['error'] !== '') {
    echo 'ERROR: ' . $summary['error'] . "\n";
    exit(1);
}

echo "No EDXEIX call. No AADE call. No production submission tables.\n";
if (!$opts['commit_disabled_event']) {
    echo "SELECT-only dry-run. Add --commit-disabled-event only to record throttled V3 disabled-submit audit events.\n";
}

if (empty($results)) {
    echo 'No V3 queue rows matched status filter: ' . $opts['status'] . "\n";
    exit(0);
}

foreach ($results as $i => $row) {
    $state = $row['eligible_for_future_live_worker'] ? 'ELIGIBLE-HARD-ENABLED ' : ($row['eligible_but_hard_disabled'] ? 'ELIGIBLE-BUT-HARD-DISABLED ' : 'BLOCKED ');
    echo '#' . ($i + 1) . ' ' . $state . $row['dedupe_key'] . "\n";
    echo '  Queue ID: ' . $row['queue_id'] . ' | Pickup: ' . $row['pickup_datetime'] . ' (' . ($row['minutes_until'] === null ? '-' : $row['minutes_until'] . ' min') . ")\n";
    echo '  Transfer: ' . $row['customer_name'] . ' | ' . $row['driver_name'] . ' | ' . $row['vehicle_plate'] . "\n";
    echo '  IDs: lessor=' . $row['ids']['lessor_id'] . ' driver=' . $row['ids']['driver_id'] . ' vehicle=' . $row['ids']['vehicle_id'] . ' start=' . $row['ids']['starting_point_id'] . "\n";
    echo '  Approval: required=' . ($row['approval']['required'] ? 'yes' : 'no') . ' present=' . ($row['approval']['present'] ? 'yes' : 'no') . ' valid=' . ($row['approval']['valid'] ? 'yes' : 'no') . "\n";
    foreach ($row['block_reasons'] as $reason) { echo '  Block: ' . $reason . "\n"; }
    foreach ($row['warnings'] as $warning) { echo '  Warning: ' . $warning . "\n"; }
}

exit(0);
