<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bolt_sync_lib.php';

$apply = false;
$driversCsv = '';
$vehiclesCsv = '';

foreach ($argv ?? [] as $arg) {
    if ($arg === '--apply') {
        $apply = true;
    } elseif (preg_match('/^--drivers=(.+)$/', $arg, $m)) {
        $driversCsv = trim($m[1]);
    } elseif (preg_match('/^--vehicles=(.+)$/', $arg, $m)) {
        $vehiclesCsv = trim($m[1]);
    }
}

function newest_file(string $pattern): string
{
    $files = glob($pattern) ?: [];
    if (!$files) {
        return '';
    }
    usort($files, static fn($a, $b) => filemtime($b) <=> filemtime($a));
    return $files[0];
}

function read_csv_assoc(string $file): array
{
    if ($file === '' || !is_file($file) || !is_readable($file)) {
        throw new RuntimeException('CSV file not readable: ' . $file);
    }

    $fh = fopen($file, 'rb');
    if (!$fh) {
        throw new RuntimeException('Cannot open CSV file: ' . $file);
    }

    $headers = fgetcsv($fh);
    if (!is_array($headers)) {
        fclose($fh);
        throw new RuntimeException('CSV file has no header row: ' . $file);
    }

    $headers = array_map(static fn($v) => trim((string)$v), $headers);
    $rows = [];

    while (($row = fgetcsv($fh)) !== false) {
        $assoc = [];
        foreach ($headers as $i => $key) {
            $assoc[$key] = $row[$i] ?? '';
        }
        $rows[] = $assoc;
    }

    fclose($fh);
    return $rows;
}

$out = [
    'ok' => false,
    'script' => 'cli/apply_edxeix_mapping_worklist.php',
    'version' => 'v6.3.3',
    'mode' => [
        'apply' => $apply,
    ],
    'safety' => [
        'does_not_call_edxeix' => true,
        'does_not_issue_aade_receipts' => true,
        'does_not_create_submission_jobs' => true,
        'does_not_create_submission_attempts' => true,
        'updates_mapping_tables_only_when_apply_is_used' => true,
    ],
];

try {
    $paths = gov_bridge_paths();
    $artifactDir = $paths['artifacts'] . '/edxeix';

    if ($driversCsv === '') {
        $driversCsv = newest_file($artifactDir . '/missing_driver_mappings_*.csv');
    }
    if ($vehiclesCsv === '') {
        $vehiclesCsv = newest_file($artifactDir . '/missing_vehicle_mappings_*.csv');
    }

    $db = gov_bridge_db();

    $beforeJobs = gov_bridge_fetch_one($db, 'SELECT COUNT(*) AS c FROM submission_jobs');
    $beforeAttempts = gov_bridge_fetch_one($db, 'SELECT COUNT(*) AS c FROM submission_attempts');

    $driverRows = read_csv_assoc($driversCsv);
    $vehicleRows = read_csv_assoc($vehiclesCsv);

    $driverUpdates = [];
    foreach ($driverRows as $row) {
        $mappingId = (int)($row['mapping_id'] ?? 0);
        $edxeixId = trim((string)($row['fill_edxeix_driver_id'] ?? ''));

        if ($mappingId <= 0 || $edxeixId === '') {
            continue;
        }
        if (!preg_match('/^\d+$/', $edxeixId) || (int)$edxeixId <= 0) {
            $driverUpdates[] = [
                'mapping_id' => $mappingId,
                'status' => 'invalid_edxeix_driver_id',
                'value' => $edxeixId,
            ];
            continue;
        }

        $existing = gov_bridge_fetch_one($db, 'SELECT id, external_driver_name, edxeix_driver_id FROM mapping_drivers WHERE id=? LIMIT 1', [$mappingId]);
        if (!$existing) {
            $driverUpdates[] = [
                'mapping_id' => $mappingId,
                'status' => 'mapping_row_not_found',
                'value' => $edxeixId,
            ];
            continue;
        }

        if ($apply) {
            gov_bridge_update_row($db, 'mapping_drivers', [
                'edxeix_driver_id' => (string)(int)$edxeixId,
                'updated_at' => date('Y-m-d H:i:s'),
            ], 'id = ?', [$mappingId]);
        }

        $driverUpdates[] = [
            'mapping_id' => $mappingId,
            'external_driver_name' => $existing['external_driver_name'] ?? '',
            'old_edxeix_driver_id' => $existing['edxeix_driver_id'] ?? null,
            'new_edxeix_driver_id' => (int)$edxeixId,
            'status' => $apply ? 'updated' : 'dry_run_would_update',
        ];
    }

    $vehicleUpdates = [];
    foreach ($vehicleRows as $row) {
        $mappingId = (int)($row['mapping_id'] ?? 0);
        $edxeixId = trim((string)($row['fill_edxeix_vehicle_id'] ?? ''));

        if ($mappingId <= 0 || $edxeixId === '') {
            continue;
        }
        if (!preg_match('/^\d+$/', $edxeixId) || (int)$edxeixId <= 0) {
            $vehicleUpdates[] = [
                'mapping_id' => $mappingId,
                'status' => 'invalid_edxeix_vehicle_id',
                'value' => $edxeixId,
            ];
            continue;
        }

        $existing = gov_bridge_fetch_one($db, 'SELECT id, plate, external_vehicle_name, edxeix_vehicle_id FROM mapping_vehicles WHERE id=? LIMIT 1', [$mappingId]);
        if (!$existing) {
            $vehicleUpdates[] = [
                'mapping_id' => $mappingId,
                'status' => 'mapping_row_not_found',
                'value' => $edxeixId,
            ];
            continue;
        }

        if ($apply) {
            gov_bridge_update_row($db, 'mapping_vehicles', [
                'edxeix_vehicle_id' => (string)(int)$edxeixId,
                'updated_at' => date('Y-m-d H:i:s'),
            ], 'id = ?', [$mappingId]);
        }

        $vehicleUpdates[] = [
            'mapping_id' => $mappingId,
            'plate' => $existing['plate'] ?? '',
            'external_vehicle_name' => $existing['external_vehicle_name'] ?? '',
            'old_edxeix_vehicle_id' => $existing['edxeix_vehicle_id'] ?? null,
            'new_edxeix_vehicle_id' => (int)$edxeixId,
            'status' => $apply ? 'updated' : 'dry_run_would_update',
        ];
    }

    $afterJobs = gov_bridge_fetch_one($db, 'SELECT COUNT(*) AS c FROM submission_jobs');
    $afterAttempts = gov_bridge_fetch_one($db, 'SELECT COUNT(*) AS c FROM submission_attempts');

    $out['ok'] = true;
    $out['files'] = [
        'drivers_csv' => $driversCsv,
        'vehicles_csv' => $vehiclesCsv,
    ];
    $out['summary'] = [
        'driver_rows_read' => count($driverRows),
        'vehicle_rows_read' => count($vehicleRows),
        'driver_updates_ready' => count(array_filter($driverUpdates, static fn($r) => in_array($r['status'], ['dry_run_would_update', 'updated'], true))),
        'vehicle_updates_ready' => count(array_filter($vehicleUpdates, static fn($r) => in_array($r['status'], ['dry_run_would_update', 'updated'], true))),
        'submission_jobs_before' => (int)($beforeJobs['c'] ?? 0),
        'submission_jobs_after' => (int)($afterJobs['c'] ?? 0),
        'submission_attempts_before' => (int)($beforeAttempts['c'] ?? 0),
        'submission_attempts_after' => (int)($afterAttempts['c'] ?? 0),
    ];
    $out['driver_updates'] = $driverUpdates;
    $out['vehicle_updates'] = $vehicleUpdates;
} catch (Throwable $e) {
    $out['error'] = $e->getMessage();
}

echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
exit(!empty($out['ok']) ? 0 : 1);
