<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bolt_sync_lib.php';

$out = [
    'ok' => false,
    'script' => 'cli/edxeix_mapping_worklist.php',
    'generated_at' => date('c'),
    'safety' => [
        'read_only' => true,
        'does_not_call_edxeix' => true,
        'does_not_create_submission_jobs' => true,
        'does_not_create_submission_attempts' => true,
    ],
];

try {
    $db = gov_bridge_db();

    $drivers = gov_bridge_fetch_all($db, "
        SELECT
            md.id AS mapping_id,
            md.external_driver_name,
            md.driver_identifier,
            md.active_vehicle_plate,
            md.driver_email,
            md.edxeix_driver_id,
            MAX(b.started_at) AS last_booking_at,
            COUNT(DISTINCT b.id) AS recent_booking_count
        FROM mapping_drivers md
        LEFT JOIN normalized_bookings b
          ON b.source_system='bolt'
         AND b.created_at >= NOW() - INTERVAL 7 DAY
         AND (
              b.driver_external_id = md.driver_identifier
              OR LOWER(TRIM(b.driver_name)) = LOWER(TRIM(md.external_driver_name))
         )
        WHERE md.is_active=1
        GROUP BY
            md.id,
            md.external_driver_name,
            md.driver_identifier,
            md.active_vehicle_plate,
            md.driver_email,
            md.edxeix_driver_id
        ORDER BY
            CASE WHEN MAX(b.started_at) IS NULL THEN 1 ELSE 0 END,
            CASE WHEN md.edxeix_driver_id IS NULL OR md.edxeix_driver_id=0 THEN 0 ELSE 1 END,
            MAX(b.started_at) DESC,
            md.external_driver_name
    ");

    $vehicles = gov_bridge_fetch_all($db, "
        SELECT
            mv.id AS mapping_id,
            mv.plate,
            mv.external_vehicle_name,
            mv.vehicle_model,
            mv.external_vehicle_id,
            mv.edxeix_vehicle_id,
            MAX(b.started_at) AS last_booking_at,
            COUNT(DISTINCT b.id) AS recent_booking_count
        FROM mapping_vehicles mv
        LEFT JOIN normalized_bookings b
          ON b.source_system='bolt'
         AND b.created_at >= NOW() - INTERVAL 7 DAY
         AND (
              UPPER(TRIM(b.vehicle_plate)) = UPPER(TRIM(mv.plate))
              OR b.vehicle_external_id = mv.external_vehicle_id
         )
        WHERE mv.is_active=1
        GROUP BY
            mv.id,
            mv.plate,
            mv.external_vehicle_name,
            mv.vehicle_model,
            mv.external_vehicle_id,
            mv.edxeix_vehicle_id
        ORDER BY
            CASE WHEN MAX(b.started_at) IS NULL THEN 1 ELSE 0 END,
            CASE WHEN mv.edxeix_vehicle_id IS NULL OR mv.edxeix_vehicle_id=0 THEN 0 ELSE 1 END,
            MAX(b.started_at) DESC,
            mv.plate
    ");

    $missingDrivers = array_values(array_filter($drivers, static function ($r): bool {
        return (int)($r['recent_booking_count'] ?? 0) > 0 && (int)($r['edxeix_driver_id'] ?? 0) <= 0;
    }));

    $missingVehicles = array_values(array_filter($vehicles, static function ($r): bool {
        return (int)($r['recent_booking_count'] ?? 0) > 0 && (int)($r['edxeix_vehicle_id'] ?? 0) <= 0;
    }));

    $paths = gov_bridge_paths();
    $artifactDir = $paths['artifacts'] . '/edxeix';
    if (!is_dir($artifactDir)) {
        @mkdir($artifactDir, 0750, true);
    }

    $stamp = date('Ymd_His');
    $driverCsv = $artifactDir . "/missing_driver_mappings_{$stamp}.csv";
    $vehicleCsv = $artifactDir . "/missing_vehicle_mappings_{$stamp}.csv";

    $fh = fopen($driverCsv, 'wb');
    fputcsv($fh, ['mapping_id', 'external_driver_name', 'driver_identifier', 'active_vehicle_plate', 'driver_email', 'current_edxeix_driver_id', 'last_booking_at', 'recent_booking_count', 'fill_edxeix_driver_id']);
    foreach ($missingDrivers as $r) {
        fputcsv($fh, [
            $r['mapping_id'],
            $r['external_driver_name'],
            $r['driver_identifier'],
            $r['active_vehicle_plate'],
            $r['driver_email'],
            $r['edxeix_driver_id'],
            $r['last_booking_at'],
            $r['recent_booking_count'],
            '',
        ]);
    }
    fclose($fh);

    $fh = fopen($vehicleCsv, 'wb');
    fputcsv($fh, ['mapping_id', 'plate', 'external_vehicle_name', 'vehicle_model', 'external_vehicle_id', 'current_edxeix_vehicle_id', 'last_booking_at', 'recent_booking_count', 'fill_edxeix_vehicle_id']);
    foreach ($missingVehicles as $r) {
        fputcsv($fh, [
            $r['mapping_id'],
            $r['plate'],
            $r['external_vehicle_name'],
            $r['vehicle_model'],
            $r['external_vehicle_id'],
            $r['edxeix_vehicle_id'],
            $r['last_booking_at'],
            $r['recent_booking_count'],
            '',
        ]);
    }
    fclose($fh);

    $jobs = gov_bridge_fetch_one($db, "SELECT COUNT(*) AS c FROM submission_jobs");
    $attempts = gov_bridge_fetch_one($db, "SELECT COUNT(*) AS c FROM submission_attempts");

    $out['ok'] = true;
    $out['summary'] = [
        'active_drivers_total' => count($drivers),
        'active_vehicles_total' => count($vehicles),
        'missing_recent_driver_mappings' => count($missingDrivers),
        'missing_recent_vehicle_mappings' => count($missingVehicles),
        'submission_jobs' => (int)($jobs['c'] ?? 0),
        'submission_attempts' => (int)($attempts['c'] ?? 0),
    ];
    $out['files'] = [
        'missing_driver_mappings_csv' => $driverCsv,
        'missing_vehicle_mappings_csv' => $vehicleCsv,
    ];
    $out['missing_recent_driver_mappings'] = $missingDrivers;
    $out['missing_recent_vehicle_mappings'] = $missingVehicles;
} catch (Throwable $e) {
    $out['error'] = $e->getMessage();
}

echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
exit(!empty($out['ok']) ? 0 : 1);
