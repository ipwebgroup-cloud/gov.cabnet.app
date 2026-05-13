<?php
/**
 * gov.cabnet.app — V3 live-submit payload audit/export.
 *
 * Purpose:
 * - Build the exact EDXEIX form-field payload that a future V3 live-submit worker would use.
 * - Validate final payload shape from V3 live_submit_ready rows.
 * - Optionally record a V3-only audit event; no queue status changes.
 *
 * Safety:
 * - No EDXEIX calls.
 * - No AADE calls.
 * - No production submission_jobs writes.
 * - No production submission_attempts writes.
 * - No production pre-ride-email-tool.php changes.
 * - Optional --commit-audit-event writes only to pre_ride_email_v3_queue_events.
 */

declare(strict_types=1);

const PRV3_PAYLOAD_AUDIT_VERSION = 'v3.0.24-live-submit-payload-audit';
const PRV3_PAYLOAD_QUEUE_TABLE = 'pre_ride_email_v3_queue';
const PRV3_PAYLOAD_EVENTS_TABLE = 'pre_ride_email_v3_queue_events';
const PRV3_PAYLOAD_START_OPTIONS_TABLE = 'pre_ride_email_v3_starting_point_options';

date_default_timezone_set('Europe/Athens');

/** @return array<string,mixed> */
function prv3pa_parse_args(array $argv): array
{
    $opts = [
        'help' => false,
        'json' => false,
        'limit' => 20,
        'status' => 'live_submit_ready',
        'queue_id' => 0,
        'commit_audit_event' => false,
    ];

    foreach (array_slice($argv, 1) as $arg) {
        if ($arg === '--help' || $arg === '-h') {
            $opts['help'] = true;
        } elseif ($arg === '--json') {
            $opts['json'] = true;
        } elseif ($arg === '--commit-audit-event') {
            $opts['commit_audit_event'] = true;
        } elseif (str_starts_with($arg, '--limit=')) {
            $opts['limit'] = max(1, min(100, (int)substr($arg, 8)));
        } elseif (str_starts_with($arg, '--status=')) {
            $status = trim(substr($arg, 9));
            if ($status !== '') { $opts['status'] = $status; }
        } elseif (str_starts_with($arg, '--queue-id=')) {
            $opts['queue_id'] = max(0, (int)substr($arg, 11));
        }
    }

    return $opts;
}

function prv3pa_print_help(): void
{
    echo "V3 live-submit payload audit " . PRV3_PAYLOAD_AUDIT_VERSION . "\n\n";
    echo "Usage:\n";
    echo "  php pre_ride_email_v3_live_submit_payload_audit.php [--limit=20] [--status=live_submit_ready] [--queue-id=123] [--json] [--commit-audit-event]\n\n";
    echo "Safety:\n";
    echo "  Builds/audits final payload only. No EDXEIX call, no AADE call, no queue status change.\n";
    echo "  --commit-audit-event records a throttled V3-only event.\n";
}

/** @return array<string,mixed> */
function prv3pa_bootstrap_context(): array
{
    $bootstrap = dirname(__DIR__) . '/src/bootstrap.php';
    if (!is_file($bootstrap)) {
        $bootstrap = dirname(__DIR__, 2) . '/src/bootstrap.php';
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

function prv3pa_table_exists(mysqli $db, string $table): bool
{
    $stmt = $db->prepare('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1');
    if (!$stmt) { return false; }
    $stmt->bind_param('s', $table);
    $stmt->execute();
    return (bool)$stmt->get_result()->fetch_assoc();
}

function prv3pa_db_name(mysqli $db): string
{
    $res = $db->query('SELECT DATABASE() AS db');
    $row = $res ? $res->fetch_assoc() : null;
    return is_array($row) ? (string)($row['db'] ?? '') : '';
}

/** @return array<int,array<string,mixed>> */
function prv3pa_fetch_rows(mysqli $db, string $status, int $limit, int $queueId): array
{
    if ($queueId > 0) {
        $stmt = $db->prepare('SELECT * FROM ' . PRV3_PAYLOAD_QUEUE_TABLE . ' WHERE id = ? LIMIT 1');
        if (!$stmt) { throw new RuntimeException('Failed to prepare V3 queue-id select: ' . $db->error); }
        $stmt->bind_param('i', $queueId);
    } else {
        $stmt = $db->prepare('SELECT * FROM ' . PRV3_PAYLOAD_QUEUE_TABLE . ' WHERE queue_status = ? ORDER BY pickup_datetime ASC, id ASC LIMIT ?');
        if (!$stmt) { throw new RuntimeException('Failed to prepare V3 queue select: ' . $db->error); }
        $stmt->bind_param('si', $status, $limit);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($row = $res->fetch_assoc()) { $rows[] = $row; }
    return $rows;
}

function prv3pa_format_edxeix_datetime(?string $raw): string
{
    $raw = trim((string)$raw);
    if ($raw === '' || $raw === '0000-00-00 00:00:00') { return ''; }
    try {
        $dt = new DateTimeImmutable($raw, new DateTimeZone('Europe/Athens'));
        return $dt->format('d/m/Y H:i');
    } catch (Throwable) {
        $ts = strtotime($raw);
        return $ts ? date('d/m/Y H:i', $ts) : '';
    }
}

function prv3pa_clean_price($value): string
{
    $text = trim((string)$value);
    if ($text === '') { return ''; }
    $number = (float)str_replace(',', '.', $text);
    if ($number <= 0) { return ''; }
    return rtrim(rtrim(number_format($number, 2, '.', ''), '0'), '.');
}

/** @return array{ok:bool,message:string,label:string} */
function prv3pa_start_option(mysqli $db, string $lessorId, string $startingPointId): array
{
    $lessorId = trim($lessorId);
    $startingPointId = trim($startingPointId);
    if ($lessorId === '' || $startingPointId === '') {
        return ['ok' => false, 'message' => 'Missing lessor or starting point ID.', 'label' => ''];
    }
    if (!prv3pa_table_exists($db, PRV3_PAYLOAD_START_OPTIONS_TABLE)) {
        return ['ok' => false, 'message' => 'Verified starting-point options table is missing.', 'label' => ''];
    }
    $stmt = $db->prepare('SELECT label FROM ' . PRV3_PAYLOAD_START_OPTIONS_TABLE . ' WHERE edxeix_lessor_id = ? AND edxeix_starting_point_id = ? AND is_active = 1 LIMIT 1');
    if (!$stmt) {
        return ['ok' => false, 'message' => 'Could not prepare verified starting-point lookup: ' . $db->error, 'label' => ''];
    }
    $stmt->bind_param('ss', $lessorId, $startingPointId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if (is_array($row)) {
        return ['ok' => true, 'message' => 'Starting point verified for lessor ' . $lessorId . '.', 'label' => (string)($row['label'] ?? '')];
    }
    return ['ok' => false, 'message' => 'Starting point ' . $startingPointId . ' is not verified for lessor ' . $lessorId . '.', 'label' => ''];
}

/** @return array<string,mixed> */
function prv3pa_build_payload(mysqli $db, array $row): array
{
    $blocks = [];
    $warnings = [];

    $lessorId = trim((string)($row['lessor_id'] ?? ''));
    $driverId = trim((string)($row['driver_id'] ?? ''));
    $vehicleId = trim((string)($row['vehicle_id'] ?? ''));
    $startingPointId = trim((string)($row['starting_point_id'] ?? ''));
    $passengerName = trim((string)($row['customer_name'] ?? ''));
    $pickupAddress = trim((string)($row['pickup_address'] ?? ''));
    $dropoffAddress = trim((string)($row['dropoff_address'] ?? ''));
    $draftedAt = prv3pa_format_edxeix_datetime($row['pickup_datetime'] ?? '');
    $startedAt = $draftedAt;
    $endedAt = prv3pa_format_edxeix_datetime($row['estimated_end_datetime'] ?? '');
    $price = prv3pa_clean_price($row['price_amount'] ?? '');

    foreach ([
        'lessor' => $lessorId,
        'driver' => $driverId,
        'vehicle' => $vehicleId,
        'starting_point_id' => $startingPointId,
        'lessee[name]' => $passengerName,
        'boarding_point' => $pickupAddress,
        'disembark_point' => $dropoffAddress,
        'drafted_at' => $draftedAt,
        'started_at' => $startedAt,
        'ended_at' => $endedAt,
        'price' => $price,
    ] as $field => $value) {
        if (trim((string)$value) === '') { $blocks[] = 'Final EDXEIX payload field is missing: ' . $field; }
    }

    $start = prv3pa_start_option($db, $lessorId, $startingPointId);
    if (!$start['ok']) { $blocks[] = $start['message']; }

    if ((string)($row['queue_status'] ?? '') !== 'live_submit_ready') {
        $warnings[] = 'Queue status is not live_submit_ready. Payload can be audited, but future live submit must require live_submit_ready.';
    }

    $payload = [
        'lessor' => $lessorId,
        'lessee[type]' => 'natural',
        'lessee[name]' => $passengerName,
        'driver' => $driverId,
        'vehicle' => $vehicleId,
        'starting_point_id' => $startingPointId,
        'boarding_point' => $pickupAddress,
        'disembark_point' => $dropoffAddress,
        'drafted_at' => $draftedAt,
        'started_at' => $startedAt,
        'ended_at' => $endedAt,
        'price' => $price,
    ];

    return [
        'queue_id' => (int)($row['id'] ?? 0),
        'dedupe_key' => (string)($row['dedupe_key'] ?? ''),
        'queue_status' => (string)($row['queue_status'] ?? ''),
        'customer_name' => $passengerName,
        'pickup_datetime' => (string)($row['pickup_datetime'] ?? ''),
        'driver_name' => (string)($row['driver_name'] ?? ''),
        'vehicle_plate' => (string)($row['vehicle_plate'] ?? ''),
        'start_option_label' => $start['label'],
        'edxeix_form_payload' => $payload,
        'ready_for_future_submit_adapter' => count($blocks) === 0,
        'block_reasons' => array_values(array_unique($blocks)),
        'warnings' => array_values(array_unique($warnings)),
        'safety' => [
            'payload_build_only' => true,
            'edxeix_call' => false,
            'aade_call' => false,
            'queue_status_changed' => false,
        ],
    ];
}

function prv3pa_recent_event_exists(mysqli $db, int $queueId, string $type, int $minutes = 60): bool
{
    $stmt = $db->prepare('SELECT id FROM ' . PRV3_PAYLOAD_EVENTS_TABLE . ' WHERE queue_id = ? AND event_type = ? AND created_at >= DATE_SUB(NOW(), INTERVAL ? MINUTE) LIMIT 1');
    if (!$stmt) { return true; }
    $stmt->bind_param('isi', $queueId, $type, $minutes);
    $stmt->execute();
    return (bool)$stmt->get_result()->fetch_assoc();
}

function prv3pa_insert_event(mysqli $db, array $audit): void
{
    $queueId = (int)$audit['queue_id'];
    $dedupeKey = (string)$audit['dedupe_key'];
    if ($queueId <= 0 || $dedupeKey === '') { return; }
    if (prv3pa_recent_event_exists($db, $queueId, 'live_submit_payload_audited', 60)) { return; }

    $json = json_encode($audit, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($json)) { $json = '{}'; }
    $status = !empty($audit['ready_for_future_submit_adapter']) ? 'ready' : 'blocked';
    $message = !empty($audit['ready_for_future_submit_adapter'])
        ? 'V3 final live-submit payload audit passed. No EDXEIX call was made.'
        : 'V3 final live-submit payload audit found blocking issues. No EDXEIX call was made.';
    $type = 'live_submit_payload_audited';
    $createdBy = 'v3_live_submit_payload_audit';
    $stmt = $db->prepare('INSERT INTO ' . PRV3_PAYLOAD_EVENTS_TABLE . ' (queue_id, dedupe_key, event_type, event_status, event_message, event_context_json, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)');
    if (!$stmt) { throw new RuntimeException('Failed to prepare V3 payload audit event insert: ' . $db->error); }
    $stmt->bind_param('issssss', $queueId, $dedupeKey, $type, $status, $message, $json, $createdBy);
    if (!$stmt->execute()) { throw new RuntimeException('Failed to insert V3 payload audit event: ' . $stmt->error); }
}

$opts = prv3pa_parse_args($argv);
if ($opts['help']) { prv3pa_print_help(); exit(0); }

$summary = [
    'ok' => false,
    'version' => PRV3_PAYLOAD_AUDIT_VERSION,
    'mode' => $opts['commit_audit_event'] ? 'commit_v3_payload_audit_event_only' : 'dry_run_select_only',
    'started_at' => (new DateTimeImmutable('now', new DateTimeZone('Europe/Athens')))->format(DATE_ATOM),
    'finished_at' => '',
    'database' => '',
    'limit' => (int)$opts['limit'],
    'status_filter' => (string)$opts['status'],
    'queue_id_filter' => (int)$opts['queue_id'],
    'schema_ok' => false,
    'start_options_ok' => false,
    'rows_checked' => 0,
    'payload_ready_count' => 0,
    'blocked_count' => 0,
    'warning_count' => 0,
    'events_inserted_requested' => (bool)$opts['commit_audit_event'],
    'safety' => [
        'edxeix_server_call' => false,
        'aade_call' => false,
        'production_submission_jobs' => false,
        'production_submission_attempts' => false,
        'queue_status_changed' => false,
    ],
    'error' => '',
];
$audits = [];

try {
    $ctx = prv3pa_bootstrap_context();
    /** @var mysqli $db */
    $db = $ctx['db']->connection();
    $db->set_charset('utf8mb4');
    $summary['database'] = prv3pa_db_name($db);
    $summary['schema_ok'] = prv3pa_table_exists($db, PRV3_PAYLOAD_QUEUE_TABLE) && prv3pa_table_exists($db, PRV3_PAYLOAD_EVENTS_TABLE);
    $summary['start_options_ok'] = prv3pa_table_exists($db, PRV3_PAYLOAD_START_OPTIONS_TABLE);
    if (!$summary['schema_ok']) { throw new RuntimeException('V3 queue schema is not installed.'); }

    $rows = prv3pa_fetch_rows($db, (string)$opts['status'], (int)$opts['limit'], (int)$opts['queue_id']);
    $summary['rows_checked'] = count($rows);
    foreach ($rows as $row) {
        $audit = prv3pa_build_payload($db, $row);
        $audits[] = $audit;
        if ($audit['ready_for_future_submit_adapter']) { $summary['payload_ready_count']++; }
        else { $summary['blocked_count']++; }
        $summary['warning_count'] += count($audit['warnings']);
        if ($opts['commit_audit_event']) { prv3pa_insert_event($db, $audit); }
    }
    $summary['ok'] = true;
} catch (Throwable $e) {
    $summary['error'] = $e->getMessage();
}

$summary['finished_at'] = (new DateTimeImmutable('now', new DateTimeZone('Europe/Athens')))->format(DATE_ATOM);

if ($opts['json']) {
    echo json_encode(['summary' => $summary, 'audits' => $audits], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "\n";
    exit($summary['ok'] ? 0 : 1);
}

echo 'V3 live-submit payload audit ' . PRV3_PAYLOAD_AUDIT_VERSION . "\n";
echo 'Mode: ' . $summary['mode'] . "\n";
echo 'Database: ' . ($summary['database'] ?: '-') . "\n";
echo 'Schema OK: ' . ($summary['schema_ok'] ? 'yes' : 'no') . ' | Starting-point options: ' . ($summary['start_options_ok'] ? 'yes' : 'no') . "\n";
echo 'Rows checked: ' . $summary['rows_checked'] . ' | Payload-ready: ' . $summary['payload_ready_count'] . ' | Blocked: ' . $summary['blocked_count'] . ' | Warnings: ' . $summary['warning_count'] . "\n";
if ($summary['error'] !== '') { echo 'ERROR: ' . $summary['error'] . "\n"; exit(1); }
echo "No EDXEIX call. No AADE call. No queue status change.\n";
if (!$opts['commit_audit_event']) { echo "SELECT-only dry-run. Add --commit-audit-event only to record throttled V3 payload audit events.\n"; }
if (!$audits) { echo 'No V3 queue rows matched status filter: ' . $opts['status'] . "\n"; exit(0); }

foreach ($audits as $i => $audit) {
    echo '#' . ($i + 1) . ' ' . ($audit['ready_for_future_submit_adapter'] ? 'PAYLOAD-READY ' : 'PAYLOAD-BLOCKED ') . $audit['dedupe_key'] . "\n";
    echo '  Queue ID: ' . $audit['queue_id'] . ' | Status: ' . $audit['queue_status'] . ' | Pickup: ' . $audit['pickup_datetime'] . "\n";
    echo '  Transfer: ' . $audit['customer_name'] . ' | ' . $audit['driver_name'] . ' | ' . $audit['vehicle_plate'] . "\n";
    echo '  EDXEIX fields: lessor=' . $audit['edxeix_form_payload']['lessor'] . ' driver=' . $audit['edxeix_form_payload']['driver'] . ' vehicle=' . $audit['edxeix_form_payload']['vehicle'] . ' start=' . $audit['edxeix_form_payload']['starting_point_id'] . "\n";
    foreach ($audit['block_reasons'] as $reason) { echo '  Block: ' . $reason . "\n"; }
    foreach ($audit['warnings'] as $warning) { echo '  Warning: ' . $warning . "\n"; }
}

exit(0);
