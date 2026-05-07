<?php
/**
 * gov.cabnet.app — Bolt Driver Directory Sync CLI
 *
 * Pulls Bolt drivers/vehicles into local mapping tables, including driver_email
 * when the Bolt API exposes it. No EDXEIX jobs and no live submission.
 *
 * Usage:
 *   php sync_bolt_driver_directory.php --hours=720
 *   php sync_bolt_driver_directory.php --json
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "CLI only\n";
    exit(1);
}

require_once '/home/cabnet/gov.cabnet.app_app/lib/bolt_sync_lib.php';

$options = getopt('', ['hours::', 'json', 'help']);
if (isset($options['help'])) {
    echo "Bolt Driver Directory Sync CLI\n";
    echo "Usage: php sync_bolt_driver_directory.php [--hours=720] [--json]\n";
    echo "Safety: syncs local driver/vehicle mapping rows only; no EDXEIX jobs; no live submit.\n";
    exit(0);
}

$hours = isset($options['hours']) ? max(24, min(8760, (int)$options['hours'])) : 720;
$json = array_key_exists('json', $options);
$result = [
    'ok' => false,
    'script' => 'sync_bolt_driver_directory.php',
    'started_at' => date('c'),
    'finished_at' => null,
    'hours_back' => $hours,
    'drivers_seen' => 0,
    'vehicles_seen' => 0,
    'driver_actions' => [],
    'vehicle_actions' => [],
    'error' => null,
];

try {
    $sync = gov_bolt_sync_reference($hours, false);
    $result['ok'] = (bool)($sync['ok'] ?? false);
    $result['drivers_seen'] = (int)($sync['drivers_seen'] ?? 0);
    $result['vehicles_seen'] = (int)($sync['vehicles_seen'] ?? 0);
    $result['driver_actions'] = $sync['drivers'] ?? [];
    $result['vehicle_actions'] = $sync['vehicles'] ?? [];
} catch (Throwable $e) {
    $result['error'] = $e->getMessage();
}

$result['finished_at'] = date('c');

if ($json) {
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;
} else {
    echo '[' . $result['finished_at'] . '] Bolt Driver Directory Sync CLI' . PHP_EOL;
    if ($result['ok']) {
        echo 'OK drivers_seen=' . $result['drivers_seen'] . ' vehicles_seen=' . $result['vehicles_seen'] . PHP_EOL;
        echo 'Safety: local mapping sync only; no EDXEIX jobs; no live submit.' . PHP_EOL;
    } else {
        echo 'ERROR: ' . $result['error'] . PHP_EOL;
    }
}

exit($result['ok'] ? 0 : 1);
