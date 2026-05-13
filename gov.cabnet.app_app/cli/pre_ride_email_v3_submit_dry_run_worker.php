<?php
/**
 * gov.cabnet.app — V3 pre-ride email submit dry-run worker.
 *
 * Purpose:
 * - Move the isolated V3 queue one step closer to submit automation.
 * - Read V3 queue rows, run strict submit preflight, and optionally mark rows as
 *   submit_dry_run_ready in V3-only tables.
 * - Enforce operator-verified V3 EDXEIX starting-point options before dry-run readiness.
 *
 * Safety:
 * - No EDXEIX calls.
 * - No AADE calls.
 * - No production submission_jobs writes.
 * - No production submission_attempts writes.
 * - No production pre-ride-email-tool.php changes.
 * - Commit mode writes only to pre_ride_email_v3_queue and pre_ride_email_v3_queue_events.
 */

declare(strict_types=1);

const PRV3_SUBMIT_DRY_RUN_VERSION = 'v3.0.19-submit-dry-run-starting-point-guard';
const PRV3_QUEUE_TABLE = 'pre_ride_email_v3_queue';
const PRV3_EVENTS_TABLE = 'pre_ride_email_v3_queue_events';
const PRV3_STARTING_POINT_OPTIONS_TABLE = 'pre_ride_email_v3_starting_point_options';

date_default_timezone_set('Europe/Athens');

/** @return array<string,mixed> */
function prv3_parse_args(array $argv): array
{
    $opts = [
        'help' => false,
        'commit' => false,
        'json' => false,
        'limit' => 20,
        'status' => 'queued',
        'min_future_minutes' => 0,
    ];

    foreach (array_slice($argv, 1) as $arg) {
        if ($arg === '--help' || $arg === '-h') {
            $opts['help'] = true;
        } elseif ($arg === '--commit') {
            $opts['commit'] = true;
        } elseif ($arg === '--json') {
            $opts['json'] = true;
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

function prv3_print_help(): void
{
    echo "V3 submit dry-run worker " . PRV3_SUBMIT_DRY_RUN_VERSION . "\n\n";
    echo "Usage:\n";
    echo "  php pre_ride_email_v3_submit_dry_run_worker.php [--limit=20] [--status=queued] [--min-future-minutes=0] [--json] [--commit]\n\n";
    echo "Default mode is SELECT-only dry-run.\n";
    echo "--commit writes only V3 dry-run status/events. It does not call EDXEIX or AADE.\n";
}

/** @return array<string,mixed> */
function prv3_bootstrap_context(): array
{
    $bootstrap = dirname(__DIR__) . '/src/bootstrap.php';
    if (!is_file($bootstrap)) {
        $bootstrap = dirname(__DIR__, 2) . '/src/bootstrap.php';
    }
    if (!is_file($bootstrap)) {
        $bootstrap = dirname(__DIR__) . '/src/bootstrap.php';
    }
    if (!is_file($bootstrap)) {
        throw new RuntimeException('Private app bootstrap not found from CLI path.');
    }

    $ctx = require $bootstrap;
    if (!is_array($ctx) || !isset($ctx['db']) || !method_exists($ctx['db'], 'connection')) {
        throw new RuntimeException('Private app bootstrap did not return a usable DB context.');
    }
    return $ctx;
}

function prv3_table_exists(mysqli $db, string $table): bool
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
function prv3_fetch_rows(mysqli $db, string $status, int $limit): array
{
    $sql = 'SELECT * FROM ' . PRV3_QUEUE_TABLE . ' WHERE queue_status = ? ORDER BY pickup_datetime ASC, id ASC LIMIT ?';
    $stmt = $db->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException('Failed to prepare V3 queue select: ' . $db->error);
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

/** @return array<int,array<string,string>> */
function prv3_starting_point_options(mysqli $db, string $lessorId): array
{
    $lessorId = trim($lessorId);
    if ($lessorId === '' || !prv3_table_exists($db, PRV3_STARTING_POINT_OPTIONS_TABLE)) {
        return [];
    }

    $stmt = $db->prepare(
        'SELECT lessor_id, starting_point_id, label, is_active, source '
        . 'FROM ' . PRV3_STARTING_POINT_OPTIONS_TABLE . ' '
        . 'WHERE lessor_id = ? AND is_active = 1 '
        . 'ORDER BY label ASC, starting_point_id ASC'
    );
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('s', $lessorId);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($row = $res->fetch_assoc()) {
        $rows[] = [
            'lessor_id' => (string)($row['lessor_id'] ?? ''),
            'starting_point_id' => (string)($row['starting_point_id'] ?? ''),
            'label' => (string)($row['label'] ?? ''),
            'source' => (string)($row['source'] ?? ''),
        ];
    }
    return $rows;
}

/** @return array{ok:bool,status:string,message:string,options:array<int,array<string,string>>} */
function prv3_starting_point_guard(mysqli $db, string $lessorId, string $startingPointId): array
{
    $lessorId = trim($lessorId);
    $startingPointId = trim($startingPointId);

    if (!prv3_table_exists($db, PRV3_STARTING_POINT_OPTIONS_TABLE)) {
        return [
            'ok' => false,
            'status' => 'options_table_missing',
            'message' => 'Verified starting-point options table is missing.',
            'options' => [],
        ];
    }

    if ($lessorId === '' || $startingPointId === '') {
        return [
            'ok' => false,
            'status' => 'missing_ids',
            'message' => 'Lessor ID or starting point ID is missing for starting-point guard.',
            'options' => [],
        ];
    }

    $options = prv3_starting_point_options($db, $lessorId);
    if (count($options) === 0) {
        return [
            'ok' => false,
            'status' => 'unknown_lessor_options',
            'message' => 'No operator-verified EDXEIX starting-point options exist for lessor ' . $lessorId . '.',
            'options' => [],
        ];
    }

    foreach ($options as $option) {
        if ((string)$option['starting_point_id'] === $startingPointId) {
            return [
                'ok' => true,
                'status' => 'verified',
                'message' => 'Starting point ' . $startingPointId . ' is verified for lessor ' . $lessorId . '.',
                'options' => $options,
            ];
        }
    }

    $allowed = array_map(static fn(array $o): string => $o['starting_point_id'] . ' / ' . $o['label'], $options);
    return [
        'ok' => false,
        'status' => 'known_invalid',
        'message' => 'Starting point ' . $startingPointId . ' is not among verified EDXEIX options for lessor ' . $lessorId . '. Allowed: ' . implode(', ', $allowed),
        'options' => $options,
    ];
}

/** @return array{ok:bool,minutes_until:int|null,message:string} */
function prv3_pickup_future_check(?string $pickup, int $minFutureMinutes): array
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
                'message' => 'Pickup is only ' . $minutes . ' minutes from now. Submit dry-run requires at least ' . $minFutureMinutes . ' minute(s) in the future.',
            ];
        }
        return ['ok' => true, 'minutes_until' => $minutes, 'message' => 'Pickup is future-safe for dry-run submit preflight.'];
    } catch (Throwable $e) {
        return ['ok' => false, 'minutes_until' => null, 'message' => 'Pickup datetime could not be parsed: ' . $e->getMessage()];
    }
}

/** @return array{ok:bool,message:string} */
function prv3_end_after_pickup_check(?string $pickup, ?string $end): array
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

function prv3_valid_json(?string $json): bool
{
    $json = trim((string)$json);
    if ($json === '') {
        return false;
    }
    json_decode($json, true);
    return json_last_error() === JSON_ERROR_NONE;
}

/** @return array<string,mixed> */
function prv3_preflight_row(mysqli $db, array $row, int $minFutureMinutes): array
{
    $block = [];
    $warnings = [];

    if ((int)($row['parser_ok'] ?? 0) !== 1) {
        $block[] = 'parser_ok is not 1.';
    }
    if ((int)($row['mapping_ok'] ?? 0) !== 1) {
        $block[] = 'mapping_ok is not 1.';
    }
    if ((int)($row['future_ok'] ?? 0) !== 1) {
        $block[] = 'future_ok is not 1.';
    }

    $required = [
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
    ];

    foreach ($required as $key => $label) {
        if (trim((string)($row[$key] ?? '')) === '') {
            $block[] = 'Missing ' . $label . '.';
        }
    }

    $startGuard = prv3_starting_point_guard($db, (string)($row['lessor_id'] ?? ''), (string)($row['starting_point_id'] ?? ''));
    if (!$startGuard['ok']) {
        $block[] = 'Starting-point guard: ' . $startGuard['message'];
    }

    $future = prv3_pickup_future_check($row['pickup_datetime'] ?? null, $minFutureMinutes);
    if (!$future['ok']) {
        $block[] = $future['message'];
    }

    $end = prv3_end_after_pickup_check($row['pickup_datetime'] ?? null, $row['estimated_end_datetime'] ?? null);
    if (!$end['ok']) {
        $block[] = $end['message'];
    }

    $priceRaw = $row['price_amount'] ?? null;
    if ($priceRaw === null || trim((string)$priceRaw) === '' || (float)$priceRaw <= 0) {
        $block[] = 'Missing or invalid positive price amount.';
    }

    if (!prv3_valid_json($row['payload_json'] ?? '')) {
        $block[] = 'payload_json is missing or invalid JSON.';
    }
    if (!prv3_valid_json($row['parsed_fields_json'] ?? '')) {
        $warnings[] = 'parsed_fields_json is missing or invalid JSON.';
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

function prv3_insert_event(mysqli $db, int $queueId, string $dedupeKey, string $type, string $status, string $message, array $context): void
{
    $json = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($json)) {
        $json = '{}';
    }

    $createdBy = 'v3_submit_dry_run_worker';
    $stmt = $db->prepare('INSERT INTO ' . PRV3_EVENTS_TABLE . ' (queue_id, dedupe_key, event_type, event_status, event_message, event_context_json, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)');
    if (!$stmt) {
        throw new RuntimeException('Failed to prepare V3 event insert: ' . $db->error);
    }
    $stmt->bind_param('issssss', $queueId, $dedupeKey, $type, $status, $message, $json, $createdBy);
    if (!$stmt->execute()) {
        throw new RuntimeException('Failed to insert V3 event: ' . $stmt->error);
    }
}

function prv3_mark_ready(mysqli $db, int $queueId): void
{
    $status = 'submit_dry_run_ready';
    $stmt = $db->prepare('UPDATE ' . PRV3_QUEUE_TABLE . ' SET queue_status = ?, locked_at = NOW(), last_error = NULL WHERE id = ? AND queue_status = ? LIMIT 1');
    if (!$stmt) {
        throw new RuntimeException('Failed to prepare V3 queue update: ' . $db->error);
    }
    $old = 'queued';
    $stmt->bind_param('sis', $status, $queueId, $old);
    if (!$stmt->execute()) {
        throw new RuntimeException('Failed to update V3 queue row: ' . $stmt->error);
    }
}

function prv3_db_name(mysqli $db): string
{
    $res = $db->query('SELECT DATABASE() AS db');
    $row = $res ? $res->fetch_assoc() : null;
    return is_array($row) ? (string)($row['db'] ?? '') : '';
}

$opts = prv3_parse_args($argv);
if ($opts['help']) {
    prv3_print_help();
    exit(0);
}

$started = new DateTimeImmutable('now', new DateTimeZone('Europe/Athens'));
$summary = [
    'ok' => false,
    'version' => PRV3_SUBMIT_DRY_RUN_VERSION,
    'mode' => $opts['commit'] ? 'commit_v3_dry_run_status_only' : 'dry_run_select_only',
    'started_at' => $started->format(DATE_ATOM),
    'finished_at' => '',
    'database' => '',
    'limit' => (int)$opts['limit'],
    'status_filter' => (string)$opts['status'],
    'min_future_minutes' => (int)$opts['min_future_minutes'],
    'schema_ok' => false,
    'starting_point_options_schema_ok' => false,
    'rows_checked' => 0,
    'submit_dry_run_ready_count' => 0,
    'blocked_count' => 0,
    'starting_point_guard_block_count' => 0,
    'warning_count' => 0,
    'events_inserted' => 0,
    'rows_marked_ready' => 0,
    'safety' => [
        'v3_tables_only' => true,
        'edxeix_server_call' => false,
        'aade_call' => false,
        'production_submission_jobs' => false,
        'production_submission_attempts' => false,
    ],
    'error' => '',
];
$results = [];

try {
    $ctx = prv3_bootstrap_context();
    /** @var mysqli $db */
    $db = $ctx['db']->connection();
    $db->set_charset('utf8mb4');
    $summary['database'] = prv3_db_name($db);

    $queueExists = prv3_table_exists($db, PRV3_QUEUE_TABLE);
    $eventsExists = prv3_table_exists($db, PRV3_EVENTS_TABLE);
    $optionsExists = prv3_table_exists($db, PRV3_STARTING_POINT_OPTIONS_TABLE);
    $summary['schema_ok'] = $queueExists && $eventsExists;
    $summary['starting_point_options_schema_ok'] = $optionsExists;

    if (!$summary['schema_ok']) {
        throw new RuntimeException('V3 queue schema is not installed.');
    }
    if (!$optionsExists) {
        throw new RuntimeException('V3 starting-point options schema is not installed.');
    }

    $rows = prv3_fetch_rows($db, (string)$opts['status'], (int)$opts['limit']);
    $summary['rows_checked'] = count($rows);

    foreach ($rows as $row) {
        $check = prv3_preflight_row($db, $row, (int)$opts['min_future_minutes']);
        $results[] = $check;
        if ($check['ready']) {
            $summary['submit_dry_run_ready_count']++;
            if ($opts['commit']) {
                prv3_mark_ready($db, (int)$check['queue_id']);
                prv3_insert_event(
                    $db,
                    (int)$check['queue_id'],
                    (string)$check['dedupe_key'],
                    'submit_dry_run_ready',
                    'ready',
                    'V3 submit dry-run preflight passed including verified starting-point guard. No EDXEIX call was made.',
                    $check
                );
                $summary['events_inserted']++;
                $summary['rows_marked_ready']++;
            }
        } else {
            $summary['blocked_count']++;
            if (!$check['starting_point_guard']['ok']) {
                $summary['starting_point_guard_block_count']++;
            }
            if ($opts['commit']) {
                prv3_insert_event(
                    $db,
                    (int)$check['queue_id'],
                    (string)$check['dedupe_key'],
                    'submit_dry_run_blocked',
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

echo 'V3 submit dry-run worker ' . PRV3_SUBMIT_DRY_RUN_VERSION . "\n";
echo 'Mode: ' . $summary['mode'] . "\n";
echo 'Database: ' . ($summary['database'] ?: '-') . "\n";
echo 'Schema OK: ' . ($summary['schema_ok'] ? 'yes' : 'no') . ' | Starting-point options: ' . ($summary['starting_point_options_schema_ok'] ? 'yes' : 'no') . "\n";
echo 'Rows checked: ' . $summary['rows_checked'] . ' | Dry-run ready: ' . $summary['submit_dry_run_ready_count'] . ' | Blocked: ' . $summary['blocked_count'] . ' | Start-guard blocks: ' . $summary['starting_point_guard_block_count'] . ' | Warnings: ' . $summary['warning_count'] . "\n";
echo 'Events inserted: ' . $summary['events_inserted'] . ' | Rows marked ready: ' . $summary['rows_marked_ready'] . "\n";

if ($summary['error'] !== '') {
    echo 'ERROR: ' . $summary['error'] . "\n";
    exit(1);
}

if (!$opts['commit']) {
    echo "SELECT-only dry-run. Add --commit to write V3-only dry-run status/events.\n";
} else {
    echo "Commit mode wrote only V3 dry-run status/events. No EDXEIX call. No AADE call.\n";
}

if (empty($results)) {
    echo 'No V3 queue rows matched status filter: ' . $opts['status'] . "\n";
    exit(0);
}

foreach ($results as $i => $row) {
    echo '#' . ($i + 1) . ' ' . ($row['ready'] ? 'DRY-RUN-READY ' : 'BLOCKED ') . $row['dedupe_key'] . "\n";
    echo '  Queue ID: ' . $row['queue_id'] . ' | Pickup: ' . $row['pickup_datetime'] . ' (' . ($row['minutes_until'] === null ? '-' : $row['minutes_until'] . ' min') . ")\n";
    echo '  Transfer: ' . $row['customer_name'] . ' | ' . $row['driver_name'] . ' | ' . $row['vehicle_plate'] . "\n";
    echo '  IDs: lessor=' . $row['ids']['lessor_id'] . ' driver=' . $row['ids']['driver_id'] . ' vehicle=' . $row['ids']['vehicle_id'] . ' start=' . $row['ids']['starting_point_id'] . "\n";
    echo '  Starting-point guard: ' . $row['starting_point_guard']['status'] . ' — ' . $row['starting_point_guard']['message'] . "\n";
    foreach ($row['block_reasons'] as $reason) {
        echo '  Block: ' . $reason . "\n";
    }
    foreach ($row['warnings'] as $warning) {
        echo '  Warning: ' . $warning . "\n";
    }
}

exit(0);
