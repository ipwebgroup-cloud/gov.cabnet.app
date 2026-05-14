<?php
/**
 * V3 live package export v3.0.52-v3-live-package-export
 *
 * Builds the exact local package that a future V3 live-submit adapter would use,
 * but never calls EDXEIX, never calls AADE, never changes queue state, and never
 * writes to production submission tables.
 *
 * Default mode is dry-run preview only. Add --write to write local artifacts.
 */

declare(strict_types=1);

const PRV3_LIVE_PACKAGE_EXPORT_VERSION = 'v3.0.52-v3-live-package-export';
const PRV3_QUEUE_TABLE = 'pre_ride_email_v3_queue';
const PRV3_OPTIONS_TABLE = 'pre_ride_email_v3_starting_point_options';

function prv3_usage(): string
{
    return "Usage:\n"
        . "  php pre_ride_email_v3_live_package_export.php [--queue-id=ID] [--write] [--allow-historical-proof] [--json] [--out-dir=/path]\n\n"
        . "Examples:\n"
        . "  php pre_ride_email_v3_live_package_export.php\n"
        . "  php pre_ride_email_v3_live_package_export.php --queue-id=56 --allow-historical-proof --write\n";
}

function prv3_arg_value(array $args, string $name, ?string $default = null): ?string
{
    $prefix = '--' . $name . '=';
    foreach ($args as $arg) {
        if (strpos($arg, $prefix) === 0) {
            return substr($arg, strlen($prefix));
        }
    }
    return $default;
}

function prv3_has_flag(array $args, string $flag): bool
{
    return in_array('--' . $flag, $args, true);
}

function prv3_bootstrap(): mysqli
{
    $bootstrap = dirname(__DIR__) . '/src/bootstrap.php';
    if (!is_file($bootstrap)) {
        throw new RuntimeException('Bootstrap not found: ' . $bootstrap);
    }

    $app = require $bootstrap;
    if (!is_array($app) || !isset($app['db'])) {
        throw new RuntimeException('Bootstrap did not return db service.');
    }

    $db = $app['db'];
    if (is_object($db) && method_exists($db, 'connection')) {
        $mysqli = $db->connection();
        if ($mysqli instanceof mysqli) {
            return $mysqli;
        }
    }

    if ($db instanceof mysqli) {
        return $db;
    }

    throw new RuntimeException('Could not resolve mysqli connection from bootstrap.');
}

function prv3_table_exists(mysqli $db, string $table): bool
{
    $stmt = $db->prepare('SELECT COUNT(*) AS c FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
    $stmt->bind_param('s', $table);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return (int)($row['c'] ?? 0) > 0;
}

function prv3_fetch_queue_row(mysqli $db, ?int $queueId): ?array
{
    if ($queueId !== null) {
        $stmt = $db->prepare('SELECT * FROM ' . PRV3_QUEUE_TABLE . ' WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $queueId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        return is_array($row) ? $row : null;
    }

    $sql = "SELECT * FROM " . PRV3_QUEUE_TABLE . "\n"
        . "WHERE queue_status = 'live_submit_ready'\n"
        . "ORDER BY COALESCE(pickup_datetime, created_at) ASC, id ASC\n"
        . "LIMIT 1";
    $result = $db->query($sql);
    $row = $result ? $result->fetch_assoc() : null;
    return is_array($row) ? $row : null;
}

function prv3_fetch_start_label(mysqli $db, string $lessorId, string $startingPointId): string
{
    if ($lessorId === '' || $startingPointId === '' || !prv3_table_exists($db, PRV3_OPTIONS_TABLE)) {
        return '';
    }

    $stmt = $db->prepare(
        'SELECT label FROM ' . PRV3_OPTIONS_TABLE . ' '
        . 'WHERE edxeix_lessor_id = ? AND edxeix_starting_point_id = ? AND is_active = 1 LIMIT 1'
    );
    $stmt->bind_param('ss', $lessorId, $startingPointId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return trim((string)($row['label'] ?? ''));
}

function prv3_json_decode_array(?string $json): array
{
    $json = trim((string)$json);
    if ($json === '') {
        return [];
    }
    $decoded = json_decode($json, true);
    return is_array($decoded) ? $decoded : [];
}

function prv3_value(array $row, array $payload, string $rowKey, string $payloadKey = ''): string
{
    $value = $row[$rowKey] ?? null;
    if (($value === null || $value === '') && $payloadKey !== '') {
        $value = $payload[$payloadKey] ?? null;
    }
    return trim((string)$value);
}

function prv3_build_package(mysqli $db, array $row, bool $allowHistoricalProof): array
{
    $payload = prv3_json_decode_array((string)($row['payload_json'] ?? ''));

    $lessorId = prv3_value($row, $payload, 'lessor_id', 'lessorId');
    $driverId = prv3_value($row, $payload, 'driver_id', 'driverId');
    $vehicleId = prv3_value($row, $payload, 'vehicle_id', 'vehicleId');
    $startingPointId = prv3_value($row, $payload, 'starting_point_id', 'startingPointId');
    $startLabel = prv3_fetch_start_label($db, $lessorId, $startingPointId);
    if ($startLabel === '') {
        $startLabel = trim((string)($payload['startingPointLabel'] ?? ''));
    }

    $customerName = prv3_value($row, $payload, 'customer_name', 'passengerName');
    $customerPhone = prv3_value($row, $payload, 'customer_phone', 'passengerPhone');
    $pickupAddress = prv3_value($row, $payload, 'pickup_address', 'pickupAddress');
    $dropoffAddress = prv3_value($row, $payload, 'dropoff_address', 'dropoffAddress');
    $pickupDateTime = prv3_value($row, $payload, 'pickup_datetime', 'pickupDateTime');
    $endDateTime = prv3_value($row, $payload, 'estimated_end_datetime', 'endDateTime');
    $priceAmount = prv3_value($row, $payload, 'price_amount', 'priceAmount');
    $priceText = prv3_value($row, $payload, 'price_text', 'priceText');
    if ($priceAmount === '' && $priceText !== '') {
        $priceAmount = $priceText;
    }

    $status = trim((string)($row['queue_status'] ?? ''));
    $currentLiveReady = ($status === 'live_submit_ready');
    $eligibleForPackage = $currentLiveReady || $allowHistoricalProof;

    $required = [
        'lessor' => $lessorId,
        'driver' => $driverId,
        'vehicle' => $vehicleId,
        'starting_point_id' => $startingPointId,
        'lessee_name' => $customerName,
        'boarding_point' => $pickupAddress,
        'disembark_point' => $dropoffAddress,
        'started_at' => $pickupDateTime,
        'ended_at' => $endDateTime,
        'price' => $priceAmount,
    ];

    $missing = [];
    foreach ($required as $key => $value) {
        if (trim((string)$value) === '') {
            $missing[] = $key;
        }
    }

    $edxeixFields = [
        'lessor' => $lessorId,
        'driver' => $driverId,
        'vehicle' => $vehicleId,
        'starting_point_id' => $startingPointId,
        'starting_point_label' => $startLabel,
        'lessee_name' => $customerName,
        'lessee_phone' => $customerPhone,
        'boarding_point' => $pickupAddress,
        'disembark_point' => $dropoffAddress,
        'started_at' => $pickupDateTime,
        'ended_at' => $endDateTime,
        'price' => $priceAmount,
        'price_text' => $priceText,
    ];

    $safety = [
        'version' => PRV3_LIVE_PACKAGE_EXPORT_VERSION,
        'generated_at' => date('c'),
        'queue_id' => (int)($row['id'] ?? 0),
        'queue_status' => $status,
        'current_live_submit_ready' => $currentLiveReady,
        'allow_historical_proof' => $allowHistoricalProof,
        'eligible_for_live_submit_now' => false,
        'eligible_for_package_export' => $eligibleForPackage && empty($missing),
        'missing_required_fields' => $missing,
        'safety_boundary' => [
            'no_edxeix_call' => true,
            'no_aade_call' => true,
            'no_queue_status_change' => true,
            'no_production_submission_table_write' => true,
            'v0_untouched' => true,
            'live_submit_gate_not_opened' => true,
        ],
        'operator_note' => $currentLiveReady
            ? 'This package is for closed-gate adapter preparation only. It is not a live submission.'
            : 'Historical/non-current package export only. This row is not currently eligible for live submit.',
    ];

    return [
        'queue_id' => (int)($row['id'] ?? 0),
        'status' => $status,
        'row' => $row,
        'payload' => $payload,
        'edxeix_fields' => $edxeixFields,
        'safety_report' => $safety,
    ];
}

function prv3_safe_slug(string $value): string
{
    $value = preg_replace('/[^A-Za-z0-9_\-]+/', '_', $value);
    $value = trim((string)$value, '_');
    return $value !== '' ? $value : 'v3_package';
}

function prv3_write_artifacts(array $package, string $outDir): array
{
    if (!is_dir($outDir)) {
        if (!mkdir($outDir, 0750, true) && !is_dir($outDir)) {
            throw new RuntimeException('Could not create artifact directory: ' . $outDir);
        }
    }

    if (!is_writable($outDir)) {
        throw new RuntimeException('Artifact directory is not writable: ' . $outDir);
    }

    $queueId = (int)$package['queue_id'];
    $stamp = date('Ymd_His');
    $base = 'queue_' . $queueId . '_' . $stamp;
    $files = [
        'payload' => rtrim($outDir, '/') . '/' . $base . '_payload.json',
        'edxeix_fields' => rtrim($outDir, '/') . '/' . $base . '_edxeix_fields.json',
        'safety_report_json' => rtrim($outDir, '/') . '/' . $base . '_safety_report.json',
        'safety_report_txt' => rtrim($outDir, '/') . '/' . $base . '_safety_report.txt',
    ];

    $jsonFlags = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
    file_put_contents($files['payload'], json_encode($package['payload'], $jsonFlags) . "\n", LOCK_EX);
    file_put_contents($files['edxeix_fields'], json_encode($package['edxeix_fields'], $jsonFlags) . "\n", LOCK_EX);
    file_put_contents($files['safety_report_json'], json_encode($package['safety_report'], $jsonFlags) . "\n", LOCK_EX);

    $s = $package['safety_report'];
    $txt = [];
    $txt[] = 'V3 live-submit local package safety report';
    $txt[] = 'Version: ' . PRV3_LIVE_PACKAGE_EXPORT_VERSION;
    $txt[] = 'Generated: ' . (string)$s['generated_at'];
    $txt[] = 'Queue ID: ' . (string)$s['queue_id'];
    $txt[] = 'Queue status: ' . (string)$s['queue_status'];
    $txt[] = 'Current live_submit_ready: ' . (!empty($s['current_live_submit_ready']) ? 'yes' : 'no');
    $txt[] = 'Eligible for live submit now: no';
    $txt[] = 'EDXEIX call: no';
    $txt[] = 'AADE call: no';
    $txt[] = 'Queue status change: no';
    $txt[] = 'V0 touched: no';
    $txt[] = 'Missing required fields: ' . (empty($s['missing_required_fields']) ? 'none' : implode(', ', $s['missing_required_fields']));
    $txt[] = 'Operator note: ' . (string)$s['operator_note'];
    file_put_contents($files['safety_report_txt'], implode("\n", $txt) . "\n", LOCK_EX);

    foreach ($files as $file) {
        @chmod($file, 0640);
    }

    return $files;
}

$args = array_slice($argv ?? [], 1);
if (prv3_has_flag($args, 'help') || prv3_has_flag($args, 'h')) {
    echo prv3_usage();
    exit(0);
}

$json = prv3_has_flag($args, 'json');
$write = prv3_has_flag($args, 'write');
$allowHistoricalProof = prv3_has_flag($args, 'allow-historical-proof');
$queueIdValue = prv3_arg_value($args, 'queue-id');
$queueId = ($queueIdValue !== null && $queueIdValue !== '') ? (int)$queueIdValue : null;
$outDir = prv3_arg_value($args, 'out-dir', dirname(__DIR__) . '/storage/artifacts/v3_live_submit_packages');

$response = [
    'ok' => false,
    'version' => PRV3_LIVE_PACKAGE_EXPORT_VERSION,
    'mode' => $write ? 'write_local_artifacts' : 'dry_run_preview_only',
    'safety' => 'No EDXEIX call. No AADE call. No queue status change. No production submission tables. V0 untouched.',
];

try {
    $db = prv3_bootstrap();
    if (!prv3_table_exists($db, PRV3_QUEUE_TABLE)) {
        throw new RuntimeException('Missing V3 queue table: ' . PRV3_QUEUE_TABLE);
    }

    $row = prv3_fetch_queue_row($db, $queueId);
    if ($row === null) {
        throw new RuntimeException($queueId !== null ? 'Queue row not found: ' . $queueId : 'No current live_submit_ready V3 row found.');
    }

    $package = prv3_build_package($db, $row, $allowHistoricalProof);
    $eligibleForExport = !empty($package['safety_report']['eligible_for_package_export']);
    if (!$eligibleForExport) {
        $reason = 'Row is not eligible for package export.';
        if (($package['status'] ?? '') !== 'live_submit_ready' && !$allowHistoricalProof) {
            $reason .= ' Use --allow-historical-proof only for documented proof artifacts, not live eligibility.';
        }
        if (!empty($package['safety_report']['missing_required_fields'])) {
            $reason .= ' Missing: ' . implode(', ', $package['safety_report']['missing_required_fields']);
        }
        throw new RuntimeException($reason);
    }

    $response['ok'] = true;
    $response['queue_id'] = $package['queue_id'];
    $response['queue_status'] = $package['status'];
    $response['edxeix_fields'] = $package['edxeix_fields'];
    $response['safety_report'] = $package['safety_report'];

    if ($write) {
        $response['files'] = prv3_write_artifacts($package, (string)$outDir);
    }
} catch (Throwable $e) {
    $response['ok'] = false;
    $response['error'] = $e->getMessage();
}

if ($json) {
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
    exit(!empty($response['ok']) ? 0 : 1);
}

echo 'V3 live package export ' . PRV3_LIVE_PACKAGE_EXPORT_VERSION . "\n";
echo 'Mode: ' . $response['mode'] . "\n";
echo 'Safety: ' . $response['safety'] . "\n";
if (empty($response['ok'])) {
    echo 'ERROR: ' . (string)($response['error'] ?? 'unknown error') . "\n";
    echo prv3_usage();
    exit(1);
}

echo 'OK: yes' . "\n";
echo 'Queue ID: ' . (string)$response['queue_id'] . "\n";
echo 'Queue status: ' . (string)$response['queue_status'] . "\n";
echo 'EDXEIX fields:' . "\n";
foreach ($response['edxeix_fields'] as $key => $value) {
    echo '  ' . $key . ': ' . (string)$value . "\n";
}
echo 'Safety:' . "\n";
echo '  current_live_submit_ready: ' . (!empty($response['safety_report']['current_live_submit_ready']) ? 'yes' : 'no') . "\n";
echo '  eligible_for_live_submit_now: no' . "\n";
echo '  missing_required_fields: ' . (empty($response['safety_report']['missing_required_fields']) ? 'none' : implode(', ', $response['safety_report']['missing_required_fields'])) . "\n";
if (!empty($response['files']) && is_array($response['files'])) {
    echo 'Artifacts written:' . "\n";
    foreach ($response['files'] as $file) {
        echo '  ' . $file . "\n";
    }
} else {
    echo 'Artifacts written: no (dry-run preview only; add --write)' . "\n";
}
exit(0);
