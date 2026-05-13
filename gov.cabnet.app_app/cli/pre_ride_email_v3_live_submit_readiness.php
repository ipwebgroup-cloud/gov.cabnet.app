<?php
/**
 * gov.cabnet.app — V3 live-submit readiness worker.
 *
 * Purpose:
 * - Move isolated V3 queue rows one step closer to full automation.
 * - Read rows that already passed submit dry-run and verify they are safe to become
 *   live_submit_ready in V3-only tables.
 *
 * Safety:
 * - No EDXEIX calls.
 * - No AADE calls.
 * - No production submission_jobs writes.
 * - No production submission_attempts writes.
 * - No production pre-ride-email-tool.php changes.
 * - Commit mode writes only V3 queue status/events.
 */

declare(strict_types=1);

const LSR_VERSION = 'v3.0.21-live-submit-readiness';
const LSR_QUEUE_TABLE = 'pre_ride_email_v3_queue';
const LSR_EVENTS_TABLE = 'pre_ride_email_v3_queue_events';
const LSR_START_OPTIONS_TABLE = 'pre_ride_email_v3_starting_point_options';

date_default_timezone_set('Europe/Athens');

/** @return array<string,mixed> */
function lsr_parse_args(array $argv): array
{
    $opts = [
        'help' => false,
        'json' => false,
        'commit' => false,
        'limit' => 20,
        'status' => 'submit_dry_run_ready',
        'min_future_minutes' => 0,
    ];

    foreach (array_slice($argv, 1) as $arg) {
        if ($arg === '--help' || $arg === '-h') {
            $opts['help'] = true;
        } elseif ($arg === '--json') {
            $opts['json'] = true;
        } elseif ($arg === '--commit') {
            $opts['commit'] = true;
        } elseif (str_starts_with($arg, '--limit=')) {
            $opts['limit'] = max(1, min(200, (int)substr($arg, 8)));
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

function lsr_print_help(): void
{
    echo 'V3 live-submit readiness worker ' . LSR_VERSION . "\n\n";
    echo "Usage:\n";
    echo "  php pre_ride_email_v3_live_submit_readiness.php [--status=submit_dry_run_ready] [--limit=20] [--min-future-minutes=0] [--json] [--commit]\n\n";
    echo "Default mode is SELECT-only dry-run.\n";
    echo "--commit marks passing rows live_submit_ready in V3-only queue tables.\n";
    echo "This script does not call EDXEIX and does not submit anything.\n";
}

/** @return array<string,mixed> */
function lsr_bootstrap_context(): array
{
    $bootstrap = dirname(__DIR__) . '/src/bootstrap.php';
    if (!is_file($bootstrap)) {
        throw new RuntimeException('Private app bootstrap not found: ' . $bootstrap);
    }
    $ctx = require $bootstrap;
    if (!is_array($ctx) || !isset($ctx['db']) || !method_exists($ctx['db'], 'connection')) {
        throw new RuntimeException('Private app bootstrap did not return a usable DB context.');
    }
    return $ctx;
}

function lsr_db_name(mysqli $db): string
{
    $res = $db->query('SELECT DATABASE() AS db_name');
    $row = $res ? $res->fetch_assoc() : null;
    return is_array($row) ? (string)($row['db_name'] ?? '') : '';
}

function lsr_table_exists(mysqli $db, string $table): bool
{
    $stmt = $db->prepare('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1');
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('s', $table);
    $stmt->execute();
    return (bool)$stmt->get_result()->fetch_assoc();
}

/** @return array<int,array<string,mixed>> */
function lsr_fetch_rows(mysqli $db, string $status, int $limit): array
{
    $allowed = ['submit_dry_run_ready', 'live_submit_ready', 'queued', 'all'];
    if (!in_array($status, $allowed, true)) {
        $status = 'submit_dry_run_ready';
    }

    if ($status === 'all') {
        $sql = "SELECT * FROM " . LSR_QUEUE_TABLE . " WHERE queue_status IN ('submit_dry_run_ready', 'live_submit_ready') ORDER BY pickup_datetime ASC, id ASC LIMIT ?";
        $stmt = $db->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException('Failed to prepare queue select: ' . $db->error);
        }
        $stmt->bind_param('i', $limit);
    } else {
        $sql = 'SELECT * FROM ' . LSR_QUEUE_TABLE . ' WHERE queue_status = ? ORDER BY pickup_datetime ASC, id ASC LIMIT ?';
        $stmt = $db->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException('Failed to prepare queue select: ' . $db->error);
        }
        $stmt->bind_param('si', $status, $limit);
    }

    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($row = $res->fetch_assoc()) {
        $rows[] = $row;
    }
    return $rows;
}

/** @return array{ok:bool,known:bool,message:string,label:string} */
function lsr_starting_point_guard(mysqli $db, string $lessorId, string $startingPointId, bool $optionsTableExists): array
{
    $lessorId = trim($lessorId);
    $startingPointId = trim($startingPointId);
    if ($lessorId === '' || $startingPointId === '') {
        return ['ok' => false, 'known' => false, 'message' => 'Lessor ID or starting point ID is missing.', 'label' => ''];
    }

    if (!$optionsTableExists) {
        return ['ok' => false, 'known' => false, 'message' => 'Verified starting-point options table is missing.', 'label' => ''];
    }

    $countStmt = $db->prepare('SELECT COUNT(*) AS total FROM ' . LSR_START_OPTIONS_TABLE . ' WHERE lessor_id = ? AND is_active = 1');
    if (!$countStmt) {
        return ['ok' => false, 'known' => false, 'message' => 'Could not check verified starting-point options.', 'label' => ''];
    }
    $countStmt->bind_param('s', $lessorId);
    $countStmt->execute();
    $countRow = $countStmt->get_result()->fetch_assoc();
    $knownCount = (int)($countRow['total'] ?? 0);
    if ($knownCount <= 0) {
        return ['ok' => false, 'known' => false, 'message' => 'No verified starting-point options are registered for lessor ' . $lessorId . '.', 'label' => ''];
    }

    $stmt = $db->prepare('SELECT label FROM ' . LSR_START_OPTIONS_TABLE . ' WHERE lessor_id = ? AND starting_point_id = ? AND is_active = 1 LIMIT 1');
    if (!$stmt) {
        return ['ok' => false, 'known' => true, 'message' => 'Could not check this starting-point option.', 'label' => ''];
    }
    $stmt->bind_param('ss', $lessorId, $startingPointId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if (is_array($row)) {
        return [
            'ok' => true,
            'known' => true,
            'message' => 'Starting point is operator-verified for lessor ' . $lessorId . '.',
            'label' => (string)($row['label'] ?? ''),
        ];
    }

    return [
        'ok' => false,
        'known' => true,
        'message' => 'Starting point ' . $startingPointId . ' is not in the verified options for lessor ' . $lessorId . '.',
        'label' => '',
    ];
}

/** @return array{ok:bool,minutes_until:int|null,message:string} */
function lsr_future_check(?string $pickup, int $minFutureMinutes): array
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
            return [
                'ok' => false,
                'minutes_until' => $minutes,
                'message' => 'Pickup is only ' . $minutes . ' minutes from now. Live-submit readiness requires at least ' . $minFutureMinutes . ' minute(s) in the future.',
            ];
        }
        return ['ok' => true, 'minutes_until' => $minutes, 'message' => 'Pickup is future-safe for live-submit readiness.'];
    } catch (Throwable $e) {
        return ['ok' => false, 'minutes_until' => null, 'message' => 'Pickup datetime could not be parsed: ' . $e->getMessage()];
    }
}

/** @return array{ok:bool,message:string} */
function lsr_end_after_pickup_check(?string $pickup, ?string $end): array
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

function lsr_valid_json(?string $json): bool
{
    $json = trim((string)$json);
    if ($json === '') {
        return false;
    }
    json_decode($json, true);
    return json_last_error() === JSON_ERROR_NONE;
}

/** @return array<string,mixed> */
function lsr_check_row(mysqli $db, array $row, int $minFutureMinutes, bool $optionsTableExists): array
{
    $block = [];
    $warnings = [];

    if ((int)($row['parser_ok'] ?? 0) !== 1) { $block[] = 'parser_ok is not 1.'; }
    if ((int)($row['mapping_ok'] ?? 0) !== 1) { $block[] = 'mapping_ok is not 1.'; }
    if ((int)($row['future_ok'] ?? 0) !== 1) { $block[] = 'future_ok is not 1.'; }

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
        if (trim((string)($row[$key] ?? '')) === '') {
            $block[] = 'Missing ' . $label . '.';
        }
    }

    $future = lsr_future_check($row['pickup_datetime'] ?? null, $minFutureMinutes);
    if (!$future['ok']) { $block[] = $future['message']; }

    $end = lsr_end_after_pickup_check($row['pickup_datetime'] ?? null, $row['estimated_end_datetime'] ?? null);
    if (!$end['ok']) { $block[] = $end['message']; }

    if (!lsr_valid_json($row['payload_json'] ?? '')) { $block[] = 'payload_json is missing or invalid JSON.'; }
    if (!lsr_valid_json($row['parsed_fields_json'] ?? '')) { $warnings[] = 'parsed_fields_json is missing or invalid JSON.'; }

    $priceRaw = $row['price_amount'] ?? null;
    if ($priceRaw === null || trim((string)$priceRaw) === '' || (float)$priceRaw <= 0) {
        $block[] = 'Missing or invalid positive price amount.';
    }

    $startGuard = lsr_starting_point_guard(
        $db,
        (string)($row['lessor_id'] ?? ''),
        (string)($row['starting_point_id'] ?? ''),
        $optionsTableExists
    );
    if (!$startGuard['ok']) {
        $block[] = 'Starting-point guard: ' . $startGuard['message'];
    }

    return [
        'queue_id' => (int)($row['id'] ?? 0),
        'dedupe_key' => (string)($row['dedupe_key'] ?? ''),
        'queue_status' => (string)($row['queue_status'] ?? ''),
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
        'starting_point_guard' => $startGuard,
        'ready' => count($block) === 0,
        'block_reasons' => array_values(array_unique($block)),
        'warnings' => array_values(array_unique($warnings)),
    ];
}

function lsr_insert_event(mysqli $db, int $queueId, string $dedupeKey, string $type, string $status, string $message, array $context): void
{
    $json = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($json)) { $json = '{}'; }
    $createdBy = 'v3_live_submit_readiness';
    $stmt = $db->prepare('INSERT INTO ' . LSR_EVENTS_TABLE . ' (queue_id, dedupe_key, event_type, event_status, event_message, event_context_json, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)');
    if (!$stmt) {
        throw new RuntimeException('Failed to prepare V3 event insert: ' . $db->error);
    }
    $stmt->bind_param('issssss', $queueId, $dedupeKey, $type, $status, $message, $json, $createdBy);
    if (!$stmt->execute()) {
        throw new RuntimeException('Failed to insert V3 event: ' . $stmt->error);
    }
}

function lsr_mark_live_ready(mysqli $db, int $queueId): void
{
    $newStatus = 'live_submit_ready';
    $oldStatus = 'submit_dry_run_ready';
    $stmt = $db->prepare('UPDATE ' . LSR_QUEUE_TABLE . ' SET queue_status = ?, locked_at = NOW(), last_error = NULL WHERE id = ? AND queue_status = ? LIMIT 1');
    if (!$stmt) {
        throw new RuntimeException('Failed to prepare V3 live readiness update: ' . $db->error);
    }
    $stmt->bind_param('sis', $newStatus, $queueId, $oldStatus);
    if (!$stmt->execute()) {
        throw new RuntimeException('Failed to mark row live_submit_ready: ' . $stmt->error);
    }
}

$opts = lsr_parse_args($argv);
if ($opts['help']) {
    lsr_print_help();
    exit(0);
}

$started = new DateTimeImmutable('now', new DateTimeZone('Europe/Athens'));
$summary = [
    'ok' => false,
    'version' => LSR_VERSION,
    'mode' => $opts['commit'] ? 'commit_v3_live_readiness_only' : 'dry_run_select_only',
    'started_at' => $started->format(DATE_ATOM),
    'finished_at' => '',
    'database' => '',
    'status_filter' => (string)$opts['status'],
    'limit' => (int)$opts['limit'],
    'min_future_minutes' => (int)$opts['min_future_minutes'],
    'schema_ok' => false,
    'starting_point_options_ok' => false,
    'rows_checked' => 0,
    'live_ready_count' => 0,
    'blocked_count' => 0,
    'start_guard_blocks' => 0,
    'warning_count' => 0,
    'events_inserted' => 0,
    'rows_marked_live_ready' => 0,
    'safety' => [
        'v3_tables_only' => true,
        'edxeix_server_call' => false,
        'aade_call' => false,
        'production_submission_jobs' => false,
        'production_submission_attempts' => false,
        'live_submit_performed' => false,
    ],
    'error' => '',
];
$results = [];

try {
    $ctx = lsr_bootstrap_context();
    /** @var mysqli $db */
    $db = $ctx['db']->connection();
    $db->set_charset('utf8mb4');
    $summary['database'] = lsr_db_name($db);

    $queueOk = lsr_table_exists($db, LSR_QUEUE_TABLE);
    $eventsOk = lsr_table_exists($db, LSR_EVENTS_TABLE);
    $optionsOk = lsr_table_exists($db, LSR_START_OPTIONS_TABLE);
    $summary['schema_ok'] = $queueOk && $eventsOk;
    $summary['starting_point_options_ok'] = $optionsOk;

    if (!$summary['schema_ok']) {
        throw new RuntimeException('V3 queue schema is not installed.');
    }
    if (!$optionsOk) {
        throw new RuntimeException('V3 starting-point options table is not installed.');
    }

    $rows = lsr_fetch_rows($db, (string)$opts['status'], (int)$opts['limit']);
    $summary['rows_checked'] = count($rows);

    foreach ($rows as $row) {
        $check = lsr_check_row($db, $row, (int)$opts['min_future_minutes'], $optionsOk);
        $results[] = $check;
        if ($check['ready']) {
            $summary['live_ready_count']++;
            if ($opts['commit'] && $check['queue_status'] === 'submit_dry_run_ready') {
                lsr_mark_live_ready($db, (int)$check['queue_id']);
                lsr_insert_event(
                    $db,
                    (int)$check['queue_id'],
                    (string)$check['dedupe_key'],
                    'live_submit_readiness_ready',
                    'live_submit_ready',
                    'V3 live-submit readiness passed. No EDXEIX call was made.',
                    $check
                );
                $summary['events_inserted']++;
                $summary['rows_marked_live_ready']++;
            }
        } else {
            $summary['blocked_count']++;
            foreach ($check['block_reasons'] as $reason) {
                if (str_starts_with((string)$reason, 'Starting-point guard:')) {
                    $summary['start_guard_blocks']++;
                    break;
                }
            }
            if ($opts['commit']) {
                lsr_insert_event(
                    $db,
                    (int)$check['queue_id'],
                    (string)$check['dedupe_key'],
                    'live_submit_readiness_blocked',
                    'blocked',
                    implode(' | ', $check['block_reasons']),
                    $check
                );
                $summary['events_inserted']++;
            }
        }

        if (!empty($check['warnings'])) {
            $summary['warning_count'] += count($check['warnings']);
        }
    }

    $summary['ok'] = true;
} catch (Throwable $e) {
    $summary['error'] = $e->getMessage();
}

$summary['finished_at'] = (new DateTimeImmutable('now', new DateTimeZone('Europe/Athens')))->format(DATE_ATOM);

if ($opts['json']) {
    echo json_encode(['summary' => $summary, 'rows' => $results], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "\n";
    exit($summary['ok'] ? 0 : 1);
}

echo 'V3 live-submit readiness worker ' . LSR_VERSION . "\n";
echo 'Mode: ' . $summary['mode'] . "\n";
echo 'Database: ' . ($summary['database'] ?: '-') . "\n";
echo 'Schema OK: ' . ($summary['schema_ok'] ? 'yes' : 'no') . ' | Starting-point options: ' . ($summary['starting_point_options_ok'] ? 'yes' : 'no') . "\n";
echo 'Rows checked: ' . $summary['rows_checked']
    . ' | Live-ready: ' . $summary['live_ready_count']
    . ' | Blocked: ' . $summary['blocked_count']
    . ' | Start-guard blocks: ' . $summary['start_guard_blocks']
    . ' | Warnings: ' . $summary['warning_count'] . "\n";
echo 'Events inserted: ' . $summary['events_inserted'] . ' | Rows marked live-ready: ' . $summary['rows_marked_live_ready'] . "\n";

if ($summary['error'] !== '') {
    echo 'ERROR: ' . $summary['error'] . "\n";
    exit(1);
}

if (!$opts['commit']) {
    echo "SELECT-only dry-run. Add --commit to mark passing rows live_submit_ready in V3-only tables.\n";
} else {
    echo "Commit mode wrote only V3 live-readiness status/events. No EDXEIX call. No AADE call.\n";
}

if (empty($results)) {
    echo 'No V3 queue rows matched status filter: ' . $opts['status'] . "\n";
    exit(0);
}

foreach ($results as $i => $row) {
    echo '#' . ($i + 1) . ' ' . ($row['ready'] ? 'LIVE-READY ' : 'BLOCKED ') . $row['dedupe_key'] . "\n";
    echo '  Queue ID: ' . $row['queue_id'] . ' | Status: ' . $row['queue_status'] . ' | Pickup: ' . $row['pickup_datetime'] . ' (' . ($row['minutes_until'] === null ? '-' : $row['minutes_until'] . ' min') . ")\n";
    echo '  Transfer: ' . $row['customer_name'] . ' | ' . $row['driver_name'] . ' | ' . $row['vehicle_plate'] . "\n";
    echo '  IDs: lessor=' . $row['ids']['lessor_id'] . ' driver=' . $row['ids']['driver_id'] . ' vehicle=' . $row['ids']['vehicle_id'] . ' start=' . $row['ids']['starting_point_id'] . "\n";
    echo '  Starting-point guard: ' . ($row['starting_point_guard']['ok'] ? 'OK' : 'BLOCK') . ' — ' . $row['starting_point_guard']['message'] . "\n";
    foreach ($row['block_reasons'] as $reason) {
        echo '  Block: ' . $reason . "\n";
    }
    foreach ($row['warnings'] as $warning) {
        echo '  Warning: ' . $warning . "\n";
    }
}

exit(0);
