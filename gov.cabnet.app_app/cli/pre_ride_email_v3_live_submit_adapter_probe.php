<?php
/**
 * gov.cabnet.app — V3 live-submit adapter probe.
 *
 * Purpose:
 * - Prove the final adapter contract path without live submission.
 * - Build final EDXEIX field packages from live_submit_ready rows.
 * - Run only disabled or dry-run adapters.
 *
 * Safety:
 * - No EDXEIX calls.
 * - No AADE calls.
 * - No DB writes.
 * - No production submission_jobs/submission_attempts writes.
 */

declare(strict_types=1);

const PRV3_ADAPTER_PROBE_VERSION = 'v3.0.28-live-submit-adapter-contract-probe';
const PRV3_ADAPTER_PROBE_DEFAULT_LIMIT = 20;

$appRoot = dirname(__DIR__);
$files = [
    $appRoot . '/src/bootstrap.php',
    $appRoot . '/src/BoltMailV3/LiveSubmitAdapterV3.php',
    $appRoot . '/src/BoltMailV3/DisabledLiveSubmitAdapterV3.php',
    $appRoot . '/src/BoltMailV3/DryRunLiveSubmitAdapterV3.php',
];
foreach ($files as $file) {
    if (!is_file($file)) {
        fwrite(STDERR, "Missing required file: {$file}\n");
        exit(2);
    }
    require_once $file;
}

use Bridge\BoltMailV3\DisabledLiveSubmitAdapterV3;
use Bridge\BoltMailV3\DryRunLiveSubmitAdapterV3;
use Bridge\BoltMailV3\LiveSubmitAdapterV3;

/** @return array<string,mixed> */
function prv3_adapter_probe_options(array $argv): array
{
    $opts = [
        'limit' => PRV3_ADAPTER_PROBE_DEFAULT_LIMIT,
        'status' => 'live_submit_ready',
        'adapter' => 'disabled',
        'json' => false,
        'help' => false,
    ];

    foreach (array_slice($argv, 1) as $arg) {
        if ($arg === '--json') {
            $opts['json'] = true;
        } elseif ($arg === '--help' || $arg === '-h') {
            $opts['help'] = true;
        } elseif (preg_match('/^--limit=(\d+)$/', $arg, $m)) {
            $opts['limit'] = max(1, min(100, (int)$m[1]));
        } elseif (preg_match('/^--status=([a-zA-Z0-9_\-]+)$/', $arg, $m)) {
            $opts['status'] = $m[1];
        } elseif (preg_match('/^--adapter=([a-zA-Z0-9_\-]+)$/', $arg, $m)) {
            $opts['adapter'] = $m[1];
        } else {
            fwrite(STDERR, "Unknown option: {$arg}\n");
            $opts['help'] = true;
        }
    }

    if (!in_array($opts['adapter'], ['disabled', 'dry-run', 'dry_run'], true)) {
        fwrite(STDERR, "Only --adapter=disabled or --adapter=dry-run are allowed in this probe.\n");
        $opts['help'] = true;
    }

    return $opts;
}

function prv3_adapter_probe_help(): string
{
    return <<<TEXT
V3 live-submit adapter probe

Usage:
  php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_live_submit_adapter_probe.php [options]

Options:
  --limit=N             Rows to inspect. Default: 20, max: 100.
  --status=STATUS       Queue status filter. Default: live_submit_ready.
  --adapter=disabled    Use hard-disabled adapter. Default.
  --adapter=dry-run     Use dry-run adapter validation. Still no EDXEIX call.
  --json                Print JSON.
  --help                Show this help.

Safety: no EDXEIX call, no AADE call, no DB writes, no production submission tables.

TEXT;
}

/** @return mysqli */
function prv3_adapter_probe_db(): mysqli
{
    $ctx = require dirname(__DIR__) . '/src/bootstrap.php';
    if (!is_array($ctx) || !isset($ctx['db']) || !method_exists($ctx['db'], 'connection')) {
        throw new RuntimeException('Private app bootstrap did not return a usable DB context.');
    }
    $db = $ctx['db']->connection();
    if (!$db instanceof mysqli) {
        throw new RuntimeException('DB connection is not mysqli.');
    }
    return $db;
}

function prv3_adapter_probe_table_exists(mysqli $db, string $table): bool
{
    $stmt = $db->prepare('SELECT COUNT(*) AS c FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('s', $table);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return (int)($row['c'] ?? 0) > 0;
}

/** @return array<string,bool> */
function prv3_adapter_probe_schema(mysqli $db): array
{
    return [
        'queue' => prv3_adapter_probe_table_exists($db, 'pre_ride_email_v3_queue'),
        'events' => prv3_adapter_probe_table_exists($db, 'pre_ride_email_v3_queue_events'),
        'start_options' => prv3_adapter_probe_table_exists($db, 'pre_ride_email_v3_starting_point_options'),
        'approvals' => prv3_adapter_probe_table_exists($db, 'pre_ride_email_v3_live_submit_approvals'),
    ];
}

function prv3_adapter_probe_adapter(string $name): LiveSubmitAdapterV3
{
    if ($name === 'dry-run' || $name === 'dry_run') {
        return new DryRunLiveSubmitAdapterV3();
    }
    return new DisabledLiveSubmitAdapterV3();
}

/** @return array<int,array<string,mixed>> */
function prv3_adapter_probe_rows(mysqli $db, string $status, int $limit): array
{
    $safeStatus = $status;
    $stmt = $db->prepare("SELECT * FROM pre_ride_email_v3_queue WHERE queue_status = ? ORDER BY pickup_datetime ASC, id ASC LIMIT ?");
    if (!$stmt) {
        throw new RuntimeException('Queue select prepare failed: ' . $db->error);
    }
    $stmt->bind_param('si', $safeStatus, $limit);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($row = $res->fetch_assoc()) {
        $rows[] = $row;
    }
    return $rows;
}

/** @return array<string,mixed> */
function prv3_adapter_probe_latest_approval(mysqli $db, int $queueId): array
{
    if (!prv3_adapter_probe_table_exists($db, 'pre_ride_email_v3_live_submit_approvals')) {
        return ['ok' => false, 'reason' => 'approval_table_missing'];
    }
    $stmt = $db->prepare('SELECT * FROM pre_ride_email_v3_live_submit_approvals WHERE queue_id = ? ORDER BY id DESC LIMIT 1');
    if (!$stmt) {
        return ['ok' => false, 'reason' => 'approval_select_prepare_failed', 'error' => $db->error];
    }
    $stmt->bind_param('i', $queueId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if (!is_array($row)) {
        return ['ok' => false, 'reason' => 'no_approval_found'];
    }

    $status = strtolower(trim((string)($row['approval_status'] ?? $row['status'] ?? '')));
    $approvedAt = trim((string)($row['approved_at'] ?? $row['created_at'] ?? ''));
    $expiresAt = trim((string)($row['expires_at'] ?? ''));
    $notExpired = true;
    if ($expiresAt !== '') {
        try {
            $notExpired = (new DateTimeImmutable($expiresAt)) > new DateTimeImmutable('now');
        } catch (Throwable) {
            $notExpired = false;
        }
    }

    $validStatuses = ['approved', 'valid', 'active', 'live_submit_approved'];
    $ok = in_array($status, $validStatuses, true) && $notExpired;
    return [
        'ok' => $ok,
        'reason' => $ok ? 'approval_valid' : ($notExpired ? 'approval_status_not_valid' : 'approval_expired'),
        'status' => $status,
        'approved_at' => $approvedAt,
        'expires_at' => $expiresAt,
        'approved_by' => (string)($row['approved_by'] ?? $row['created_by'] ?? ''),
    ];
}

/** @return array{ok:bool,label:string} */
function prv3_adapter_probe_start_option(mysqli $db, string $lessorId, string $startId): array
{
    if ($lessorId === '' || $startId === '') {
        return ['ok' => false, 'label' => ''];
    }
    if (!prv3_adapter_probe_table_exists($db, 'pre_ride_email_v3_starting_point_options')) {
        return ['ok' => false, 'label' => ''];
    }
    $stmt = $db->prepare("SELECT label FROM pre_ride_email_v3_starting_point_options WHERE edxeix_lessor_id = ? AND edxeix_starting_point_id = ? AND is_active = 1 LIMIT 1");
    if (!$stmt) {
        return ['ok' => false, 'label' => ''];
    }
    $stmt->bind_param('ss', $lessorId, $startId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if (!is_array($row)) {
        return ['ok' => false, 'label' => ''];
    }
    return ['ok' => true, 'label' => (string)($row['label'] ?? '')];
}

/** @param array<string,mixed> $row @return array<string,mixed> */
function prv3_adapter_probe_payload(array $row): array
{
    $payloadJson = trim((string)($row['payload_json'] ?? ''));
    $helperPayload = [];
    if ($payloadJson !== '') {
        $decoded = json_decode($payloadJson, true);
        if (is_array($decoded)) {
            $helperPayload = $decoded;
        }
    }

    $startedAt = trim((string)($row['pickup_datetime'] ?? ''));
    $endedAt = trim((string)($row['estimated_end_datetime'] ?? ''));

    return [
        'lessor' => trim((string)($row['lessor_id'] ?? $helperPayload['lessorId'] ?? '')),
        'lessee_type' => 'natural_person',
        'lessee_name' => trim((string)($row['customer_name'] ?? $helperPayload['passengerName'] ?? '')),
        'customer_phone' => trim((string)($row['customer_phone'] ?? $helperPayload['passengerPhone'] ?? '')),
        'driver' => trim((string)($row['driver_id'] ?? $helperPayload['driverId'] ?? '')),
        'vehicle' => trim((string)($row['vehicle_id'] ?? $helperPayload['vehicleId'] ?? '')),
        'starting_point_id' => trim((string)($row['starting_point_id'] ?? $helperPayload['startingPointId'] ?? '')),
        'boarding_point' => trim((string)($row['pickup_address'] ?? $helperPayload['pickupAddress'] ?? '')),
        'disembark_point' => trim((string)($row['dropoff_address'] ?? $helperPayload['dropoffAddress'] ?? '')),
        'drafted_at' => $startedAt,
        'started_at' => $startedAt,
        'ended_at' => $endedAt,
        'price' => trim((string)($row['price_amount'] ?? $helperPayload['priceAmount'] ?? '')),
        'source_queue_id' => (string)($row['id'] ?? ''),
        'source_dedupe_key' => (string)($row['dedupe_key'] ?? ''),
    ];
}

/** @param array<string,mixed> $payload @return array<int,string> */
function prv3_adapter_probe_missing(array $payload): array
{
    $missing = [];
    foreach (['lessor', 'lessee_name', 'driver', 'vehicle', 'starting_point_id', 'boarding_point', 'disembark_point', 'started_at', 'ended_at', 'price'] as $key) {
        if (!isset($payload[$key]) || trim((string)$payload[$key]) === '') {
            $missing[] = $key;
        }
    }
    return $missing;
}

/** @return array<string,mixed> */
function prv3_adapter_probe_run(array $argv): array
{
    $opts = prv3_adapter_probe_options($argv);
    if (!empty($opts['help'])) {
        return ['help' => true, 'text' => prv3_adapter_probe_help()];
    }

    $db = prv3_adapter_probe_db();
    $schema = prv3_adapter_probe_schema($db);
    $schemaOk = $schema['queue'] && $schema['start_options'];
    $adapter = prv3_adapter_probe_adapter((string)$opts['adapter']);
    $rows = $schemaOk ? prv3_adapter_probe_rows($db, (string)$opts['status'], (int)$opts['limit']) : [];

    $outRows = [];
    $passed = 0;
    $blocked = 0;
    foreach ($rows as $row) {
        $queueId = (int)($row['id'] ?? 0);
        $lessorId = trim((string)($row['lessor_id'] ?? ''));
        $startId = trim((string)($row['starting_point_id'] ?? ''));
        $approval = prv3_adapter_probe_latest_approval($db, $queueId);
        $start = prv3_adapter_probe_start_option($db, $lessorId, $startId);
        $payload = prv3_adapter_probe_payload($row);
        $missing = prv3_adapter_probe_missing($payload);

        $context = [
            'queue_id' => (string)$queueId,
            'dedupe_key' => (string)($row['dedupe_key'] ?? ''),
            'lessor_id' => $lessorId,
            'vehicle_plate' => (string)($row['vehicle_plate'] ?? ''),
        ];
        $adapterResult = $adapter->submit($payload, $context);

        $preOk = $approval['ok'] === true && $start['ok'] === true && $missing === [];
        if ($preOk) {
            $passed++;
        } else {
            $blocked++;
        }

        $outRows[] = [
            'queue_id' => $queueId,
            'dedupe_key' => (string)($row['dedupe_key'] ?? ''),
            'pickup_datetime' => (string)($row['pickup_datetime'] ?? ''),
            'transfer' => trim((string)($row['customer_name'] ?? '') . ' / ' . (string)($row['driver_name'] ?? '') . ' / ' . (string)($row['vehicle_plate'] ?? '')),
            'lessor_id' => $lessorId,
            'starting_point_id' => $startId,
            'starting_point_valid' => $start['ok'],
            'starting_point_label' => $start['label'],
            'approval' => $approval,
            'missing_payload_fields' => $missing,
            'pre_adapter_ok' => $preOk,
            'adapter_result' => $adapterResult,
        ];
    }

    return [
        'help' => false,
        'summary' => [
            'ok' => true,
            'version' => PRV3_ADAPTER_PROBE_VERSION,
            'mode' => 'dry_run_no_submit',
            'database' => $db->query('SELECT DATABASE() AS db')->fetch_assoc()['db'] ?? '',
            'status_filter' => (string)$opts['status'],
            'limit' => (int)$opts['limit'],
            'schema_ok' => $schemaOk,
            'schema' => $schema,
            'adapter' => $adapter->name(),
            'adapter_live_capable' => $adapter->isLiveCapable(),
            'rows_checked' => count($rows),
            'pre_adapter_passed' => $passed,
            'blocked' => $blocked,
            'safety' => [
                'edxeix_call' => false,
                'aade_call' => false,
                'db_writes' => false,
                'production_submission_jobs' => false,
                'production_submission_attempts' => false,
            ],
        ],
        'rows' => $outRows,
    ];
}

/** @param array<string,mixed> $result */
function prv3_adapter_probe_print_text(array $result): void
{
    if (!empty($result['help'])) {
        echo (string)$result['text'];
        return;
    }
    $s = (array)$result['summary'];
    echo "V3 live-submit adapter probe " . ($s['version'] ?? PRV3_ADAPTER_PROBE_VERSION) . "\n";
    echo "Mode: " . ($s['mode'] ?? '') . "\n";
    echo "Database: " . ($s['database'] ?? '') . "\n";
    echo "Schema OK: " . (!empty($s['schema_ok']) ? 'yes' : 'no') . " | Approval table: " . (!empty($s['schema']['approvals']) ? 'yes' : 'no') . " | Start options: " . (!empty($s['schema']['start_options']) ? 'yes' : 'no') . "\n";
    echo "Adapter: " . ($s['adapter'] ?? 'disabled') . " | Live capable: " . (!empty($s['adapter_live_capable']) ? 'yes' : 'no') . "\n";
    echo "Rows checked: " . (int)($s['rows_checked'] ?? 0) . " | Pre-adapter passed: " . (int)($s['pre_adapter_passed'] ?? 0) . " | Blocked: " . (int)($s['blocked'] ?? 0) . "\n";
    echo "No EDXEIX call. No AADE call. No DB writes.\n";

    if (empty($result['rows'])) {
        echo "No V3 queue rows matched status filter: " . ($s['status_filter'] ?? '') . "\n";
        return;
    }

    foreach ((array)$result['rows'] as $row) {
        echo "#" . (int)($row['queue_id'] ?? 0) . ' ' . (!empty($row['pre_adapter_ok']) ? 'PRE-OK' : 'BLOCKED') . ' ' . (string)($row['dedupe_key'] ?? '') . "\n";
        echo "  Transfer: " . (string)($row['transfer'] ?? '') . "\n";
        echo "  Start: " . (string)($row['starting_point_id'] ?? '') . ' ' . (!empty($row['starting_point_valid']) ? 'valid' : 'invalid') . "\n";
        $approval = (array)($row['approval'] ?? []);
        echo "  Approval: " . (!empty($approval['ok']) ? 'valid' : 'missing/invalid') . ' (' . (string)($approval['reason'] ?? '') . ")\n";
        if (!empty($row['missing_payload_fields'])) {
            echo "  Missing payload: " . implode(', ', array_map('strval', (array)$row['missing_payload_fields'])) . "\n";
        }
        $adapterResult = (array)($row['adapter_result'] ?? []);
        echo "  Adapter: " . (string)($adapterResult['adapter'] ?? '') . ' submitted=' . (!empty($adapterResult['submitted']) ? 'yes' : 'no') . ' reason=' . (string)($adapterResult['reason'] ?? '') . "\n";
    }
}

try {
    $result = prv3_adapter_probe_run($argv);
    if (!empty($result['summary']['json'] ?? false)) {
        // unreachable; kept intentionally harmless
    }
    $opts = prv3_adapter_probe_options($argv);
    if (!empty($opts['json'])) {
        echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    } else {
        prv3_adapter_probe_print_text($result);
    }
    exit(0);
} catch (Throwable $e) {
    $error = [
        'ok' => false,
        'version' => PRV3_ADAPTER_PROBE_VERSION,
        'error' => $e->getMessage(),
        'safety' => [
            'edxeix_call' => false,
            'aade_call' => false,
            'db_writes' => false,
        ],
    ];
    $opts = prv3_adapter_probe_options($argv);
    if (!empty($opts['json'])) {
        echo json_encode($error, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    } else {
        fwrite(STDERR, 'ERROR: ' . $e->getMessage() . PHP_EOL);
    }
    exit(1);
}
