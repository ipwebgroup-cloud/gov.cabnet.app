<?php
/**
 * gov.cabnet.app — V3 pre-ride email submit preflight.
 *
 * Purpose:
 * - Read V3-only queue rows and report which rows would be ready for the later submit worker.
 * - This is a dry-run/preflight tool only.
 * - Enforces verified V3 EDXEIX starting-point options before a row can be submit-ready.
 *
 * Safety:
 * - SELECT only.
 * - No DB writes.
 * - No EDXEIX calls.
 * - No AADE calls.
 * - No production submission_jobs/submission_attempts access.
 */

declare(strict_types=1);

const V3_SUBMIT_PREFLIGHT_VERSION = 'v3.0.19-submit-preflight-starting-point-guard';
const V3_QUEUE_TABLE = 'pre_ride_email_v3_queue';
const V3_EVENTS_TABLE = 'pre_ride_email_v3_queue_events';
const V3_STARTING_POINT_OPTIONS_TABLE = 'pre_ride_email_v3_starting_point_options';

date_default_timezone_set('Europe/Athens');

function v3sp_usage(): string
{
    return "Usage:\n"
        . "  php pre_ride_email_v3_submit_preflight.php [--limit=20] [--status=queued|ready|submit_dry_run_ready|all] [--json]\n\n"
        . "Safety:\n"
        . "  SELECT only; no DB writes; no EDXEIX calls; no AADE calls.\n";
}

function v3sp_arg_value(array $argv, string $name, ?string $default = null): ?string
{
    foreach ($argv as $arg) {
        if ($arg === $name) {
            return '1';
        }
        if (str_starts_with($arg, $name . '=')) {
            return substr($arg, strlen($name) + 1);
        }
    }
    return $default;
}

function v3sp_has_arg(array $argv, string $name): bool
{
    foreach ($argv as $arg) {
        if ($arg === $name || str_starts_with($arg, $name . '=')) {
            return true;
        }
    }
    return false;
}

function v3sp_private_file(string $relative): string
{
    $relative = ltrim($relative, '/');
    return dirname(__DIR__) . '/' . $relative;
}

function v3sp_table_exists(mysqli $db, string $table): bool
{
    $stmt = $db->prepare('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1');
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('s', $table);
    $stmt->execute();
    return (bool)$stmt->get_result()->fetch_assoc();
}

function v3sp_db_name(mysqli $db): string
{
    $res = $db->query('SELECT DATABASE() AS db');
    $row = $res ? $res->fetch_assoc() : null;
    return is_array($row) ? (string)($row['db'] ?? '') : '';
}

/** @return array<int,array<string,mixed>> */
function v3sp_fetch_rows(mysqli $db, string $status, int $limit): array
{
    $allowed = ['queued', 'ready', 'submit_dry_run_ready', 'all'];
    if (!in_array($status, $allowed, true)) {
        $status = 'queued';
    }

    if ($status === 'all') {
        $sql = "
            SELECT *
            FROM " . V3_QUEUE_TABLE . "
            WHERE queue_status IN ('queued', 'ready', 'submit_dry_run_ready')
            ORDER BY COALESCE(pickup_datetime, created_at) ASC, id ASC
            LIMIT ?
        ";
        $stmt = $db->prepare($sql);
        if (!$stmt) { return []; }
        $stmt->bind_param('i', $limit);
    } else {
        $sql = "
            SELECT *
            FROM " . V3_QUEUE_TABLE . "
            WHERE queue_status = ?
            ORDER BY COALESCE(pickup_datetime, created_at) ASC, id ASC
            LIMIT ?
        ";
        $stmt = $db->prepare($sql);
        if (!$stmt) { return []; }
        $stmt->bind_param('si', $status, $limit);
    }

    $stmt->execute();
    $rows = [];
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $rows[] = $row;
    }
    return $rows;
}

/** @return array<int,array<string,string>> */
function v3sp_starting_point_options(mysqli $db, string $lessorId): array
{
    $lessorId = trim($lessorId);
    if ($lessorId === '' || !v3sp_table_exists($db, V3_STARTING_POINT_OPTIONS_TABLE)) {
        return [];
    }

    $stmt = $db->prepare(
        'SELECT lessor_id, starting_point_id, label, is_active, source '
        . 'FROM ' . V3_STARTING_POINT_OPTIONS_TABLE . ' '
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
function v3sp_starting_point_guard(mysqli $db, string $lessorId, string $startingPointId): array
{
    $lessorId = trim($lessorId);
    $startingPointId = trim($startingPointId);

    if (!v3sp_table_exists($db, V3_STARTING_POINT_OPTIONS_TABLE)) {
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

    $options = v3sp_starting_point_options($db, $lessorId);
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

/** @return array{ready:bool,blocks:array<int,string>,warnings:array<int,string>,minutes_until:int|null,starting_point_guard:array<string,mixed>} */
function v3sp_preflight_row(mysqli $db, array $row): array
{
    $blocks = [];
    $warnings = [];
    $minutesUntil = null;

    if (empty($row['parser_ok'])) {
        $blocks[] = 'parser gate is not OK';
    }
    if (empty($row['mapping_ok'])) {
        $blocks[] = 'mapping gate is not OK';
    }
    if (empty($row['future_ok'])) {
        $blocks[] = 'future gate was not OK at intake';
    }

    foreach ([
        'lessor_id' => 'lessor/company ID',
        'driver_id' => 'driver ID',
        'vehicle_id' => 'vehicle ID',
        'starting_point_id' => 'starting point ID',
        'customer_name' => 'customer name',
        'driver_name' => 'driver name',
        'vehicle_plate' => 'vehicle plate',
        'pickup_address' => 'pickup address',
        'dropoff_address' => 'drop-off address',
    ] as $key => $label) {
        if (trim((string)($row[$key] ?? '')) === '') {
            $blocks[] = $label . ' is missing';
        }
    }

    $guard = v3sp_starting_point_guard($db, (string)($row['lessor_id'] ?? ''), (string)($row['starting_point_id'] ?? ''));
    if (!$guard['ok']) {
        $blocks[] = 'Starting-point guard: ' . $guard['message'];
    }

    $pickup = trim((string)($row['pickup_datetime'] ?? ''));
    if ($pickup === '') {
        $blocks[] = 'pickup datetime is missing';
    } else {
        $pickupTs = strtotime($pickup);
        if ($pickupTs === false) {
            $blocks[] = 'pickup datetime is invalid';
        } else {
            $minutesUntil = (int)floor(($pickupTs - time()) / 60);
            if ($pickupTs <= time()) {
                $blocks[] = 'pickup is no longer in the future';
            }
        }
    }

    $end = trim((string)($row['estimated_end_datetime'] ?? ''));
    if ($end === '') {
        $warnings[] = 'estimated end datetime is missing';
    } elseif ($pickup !== '') {
        $pickupTs = strtotime($pickup);
        $endTs = strtotime($end);
        if ($pickupTs !== false && $endTs !== false && $endTs <= $pickupTs) {
            $warnings[] = 'estimated end datetime is not after pickup';
        }
    }

    $priceAmount = trim((string)($row['price_amount'] ?? ''));
    if ($priceAmount === '' || (float)$priceAmount <= 0.0) {
        $warnings[] = 'price amount is missing or zero';
    }

    $payloadJson = trim((string)($row['payload_json'] ?? ''));
    if ($payloadJson === '') {
        $warnings[] = 'payload_json is empty; dashboard can reconstruct basic payload but submit worker should prefer complete payload';
    } else {
        json_decode($payloadJson, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $blocks[] = 'payload_json is invalid JSON';
        }
    }

    return [
        'ready' => count($blocks) === 0,
        'blocks' => array_values(array_unique($blocks)),
        'warnings' => array_values(array_unique($warnings)),
        'minutes_until' => $minutesUntil,
        'starting_point_guard' => $guard,
    ];
}

$help = v3sp_has_arg($argv, '--help') || v3sp_has_arg($argv, '-h');
$json = v3sp_has_arg($argv, '--json');
$limit = max(1, min(200, (int)(v3sp_arg_value($argv, '--limit', '20') ?: 20)));
$status = strtolower((string)(v3sp_arg_value($argv, '--status', 'queued') ?: 'queued'));
if (!in_array($status, ['queued', 'ready', 'submit_dry_run_ready', 'all'], true)) {
    $status = 'queued';
}

if ($help) {
    echo v3sp_usage();
    exit(0);
}

$startedAt = date(DATE_ATOM);
$result = [
    'ok' => false,
    'version' => V3_SUBMIT_PREFLIGHT_VERSION,
    'mode' => 'dry_run_select_only',
    'started_at' => $startedAt,
    'finished_at' => null,
    'database' => '',
    'limit' => $limit,
    'status_filter' => $status,
    'schema_ok' => false,
    'starting_point_options_schema_ok' => false,
    'candidate_count' => 0,
    'submit_ready_count' => 0,
    'blocked_count' => 0,
    'warning_count' => 0,
    'starting_point_guard_block_count' => 0,
    'safety' => [
        'select_only' => true,
        'db_writes' => false,
        'edxeix_server_call' => false,
        'aade_call' => false,
        'production_submission_jobs' => false,
        'production_submission_attempts' => false,
    ],
    'rows' => [],
    'error' => '',
];

try {
    $bootstrap = v3sp_private_file('src/bootstrap.php');
    if (!is_file($bootstrap)) {
        throw new RuntimeException('Private app bootstrap not found: ' . $bootstrap);
    }

    $ctx = require $bootstrap;
    if (!is_array($ctx) || !isset($ctx['db']) || !method_exists($ctx['db'], 'connection')) {
        throw new RuntimeException('Private app bootstrap did not return a usable DB context.');
    }

    /** @var mysqli $db */
    $db = $ctx['db']->connection();
    $db->set_charset('utf8mb4');
    $result['database'] = v3sp_db_name($db);
    $queueOk = v3sp_table_exists($db, V3_QUEUE_TABLE);
    $eventsOk = v3sp_table_exists($db, V3_EVENTS_TABLE);
    $optionsOk = v3sp_table_exists($db, V3_STARTING_POINT_OPTIONS_TABLE);
    $schemaOk = $queueOk && $eventsOk;
    $result['schema_ok'] = $schemaOk;
    $result['starting_point_options_schema_ok'] = $optionsOk;

    if (!$schemaOk) {
        throw new RuntimeException('V3 queue schema is not installed.');
    }
    if (!$optionsOk) {
        throw new RuntimeException('V3 starting-point options schema is not installed.');
    }

    $rows = v3sp_fetch_rows($db, $status, $limit);
    $result['candidate_count'] = count($rows);

    foreach ($rows as $index => $row) {
        $preflight = v3sp_preflight_row($db, $row);
        if ($preflight['ready']) {
            $result['submit_ready_count']++;
        } else {
            $result['blocked_count']++;
        }
        if (!$preflight['starting_point_guard']['ok']) {
            $result['starting_point_guard_block_count']++;
        }
        if (!empty($preflight['warnings'])) {
            $result['warning_count'] += count($preflight['warnings']);
        }

        $result['rows'][] = [
            'number' => $index + 1,
            'id' => (int)($row['id'] ?? 0),
            'dedupe_key' => (string)($row['dedupe_key'] ?? ''),
            'queue_status' => (string)($row['queue_status'] ?? ''),
            'submit_preflight_ready' => $preflight['ready'],
            'minutes_until' => $preflight['minutes_until'],
            'pickup_datetime' => (string)($row['pickup_datetime'] ?? ''),
            'transfer' => [
                'customer' => (string)($row['customer_name'] ?? ''),
                'driver' => (string)($row['driver_name'] ?? ''),
                'vehicle' => (string)($row['vehicle_plate'] ?? ''),
            ],
            'ids' => [
                'lessor_id' => (string)($row['lessor_id'] ?? ''),
                'driver_id' => (string)($row['driver_id'] ?? ''),
                'vehicle_id' => (string)($row['vehicle_id'] ?? ''),
                'starting_point_id' => (string)($row['starting_point_id'] ?? ''),
            ],
            'starting_point_guard' => $preflight['starting_point_guard'],
            'blocks' => $preflight['blocks'],
            'warnings' => $preflight['warnings'],
        ];
    }

    $result['ok'] = true;
} catch (Throwable $e) {
    $result['error'] = $e->getMessage();
}

$result['finished_at'] = date(DATE_ATOM);

if ($json) {
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;
    exit($result['ok'] ? 0 : 1);
}

echo "V3 submit preflight " . V3_SUBMIT_PREFLIGHT_VERSION . PHP_EOL;
echo "Mode: dry_run_select_only" . PHP_EOL;
echo "Database: " . ($result['database'] ?: '-') . PHP_EOL;
echo "Schema OK: " . (!empty($result['schema_ok']) ? 'yes' : 'no') . " | Starting-point options: " . (!empty($result['starting_point_options_schema_ok']) ? 'yes' : 'no') . PHP_EOL;
if ($result['error'] !== '') {
    echo "ERROR: " . $result['error'] . PHP_EOL;
    exit(1);
}
echo "Rows checked: " . $result['candidate_count']
    . " | Submit-ready: " . $result['submit_ready_count']
    . " | Blocked: " . $result['blocked_count']
    . " | Start-guard blocks: " . $result['starting_point_guard_block_count']
    . " | Warnings: " . $result['warning_count'] . PHP_EOL;
echo "SELECT only. No DB writes. No EDXEIX calls. No AADE calls." . PHP_EOL . PHP_EOL;

if (empty($result['rows'])) {
    echo "No V3 queue rows matched status filter: " . $status . PHP_EOL;
    exit(0);
}

foreach ($result['rows'] as $row) {
    echo '#' . $row['number'] . ' ' . ($row['submit_preflight_ready'] ? 'READY' : 'BLOCKED')
        . ' queue_id=' . $row['id']
        . ' ' . $row['dedupe_key'] . PHP_EOL;
    echo '  Pickup: ' . $row['pickup_datetime'] . ' (' . (string)($row['minutes_until'] ?? '-') . " min)" . PHP_EOL;
    echo '  Transfer: ' . $row['transfer']['customer'] . ' | ' . $row['transfer']['driver'] . ' | ' . $row['transfer']['vehicle'] . PHP_EOL;
    echo '  IDs: lessor=' . $row['ids']['lessor_id'] . ' driver=' . $row['ids']['driver_id'] . ' vehicle=' . $row['ids']['vehicle_id'] . ' start=' . $row['ids']['starting_point_id'] . PHP_EOL;
    echo '  Starting-point guard: ' . $row['starting_point_guard']['status'] . ' — ' . $row['starting_point_guard']['message'] . PHP_EOL;
    foreach ($row['blocks'] as $block) {
        echo '  Block: ' . $block . PHP_EOL;
    }
    foreach ($row['warnings'] as $warning) {
        echo '  Warning: ' . $warning . PHP_EOL;
    }
}

exit(0);
