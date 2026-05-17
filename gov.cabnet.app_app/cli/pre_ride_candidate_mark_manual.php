#!/usr/bin/env php
<?php
/**
 * gov.cabnet.app — CLI mark pre-ride candidate manually submitted via V0/laptop.
 * v3.2.32
 */

declare(strict_types=1);

require_once '/home/cabnet/gov.cabnet.app_app/lib/edxeix_pre_ride_candidate_closure_lib.php';

$options = [
    'candidate_id' => 0,
    'method' => 'v0_laptop_manual',
    'submitted_by' => 'operator',
    'submitted_at' => '', // Empty is valid; library defaults it to current server time.
    'note' => 'Manually submitted via V0/laptop. Server-side retry blocked.',
];
$json = false;

foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--json') { $json = true; continue; }
    if (preg_match('/^--candidate-id=(\d+)$/', $arg, $m)) { $options['candidate_id'] = (int)$m[1]; continue; }
    if (strpos($arg, '--method=') === 0) { $options['method'] = substr($arg, strlen('--method=')); continue; }
    if (strpos($arg, '--submitted-by=') === 0) { $options['submitted_by'] = substr($arg, strlen('--submitted-by=')); continue; }
    if (strpos($arg, '--submitted-at=') === 0) { $options['submitted_at'] = substr($arg, strlen('--submitted-at=')); continue; }
    if (strpos($arg, '--note=') === 0) { $options['note'] = substr($arg, strlen('--note=')); continue; }
    if ($arg === '--help' || $arg === '-h') {
        echo "Usage:\n";
        echo "  php pre_ride_candidate_mark_manual.php --candidate-id=N --submitted-by=Andreas --note='Submitted via V0 laptop' --json\n";
        echo "\nWrites diagnostic closure metadata only. No EDXEIX/AADE/queue action.\n";
        exit(0);
    }
}

try {
    if ((int)$options['candidate_id'] <= 0) {
        throw new RuntimeException('candidate_id is required.');
    }
    $db = gov_bridge_db();
    $result = gov_prcl_mark_manual($db, (int)$options['candidate_id'], $options);
    $result['version'] = 'v3.2.32-pre-ride-candidate-manual-closure';
    $result['safety'] = [
        'edxeix_transport' => false,
        'aade_call' => false,
        'queue_job' => false,
        'normalized_booking_write' => false,
        'live_config_write' => false,
        'v0_production_changed' => false,
    ];
    if ($json) {
        echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    } else {
        echo ($result['ok'] ? 'OK' : 'BLOCKED') . ': ' . ($result['message'] ?? '') . PHP_EOL;
        if (!empty($result['closure_id'])) { echo 'Closure ID: ' . $result['closure_id'] . PHP_EOL; }
        if (!empty($result['payload_hash'])) { echo 'Payload hash: ' . $result['payload_hash'] . PHP_EOL; }
    }
    exit(!empty($result['ok']) ? 0 : 1);
} catch (Throwable $e) {
    $error = [
        'ok' => false,
        'version' => 'v3.2.32-pre-ride-candidate-manual-closure',
        'message' => $e->getMessage(),
        'safety' => [
            'edxeix_transport' => false,
            'aade_call' => false,
            'queue_job' => false,
            'normalized_booking_write' => false,
            'live_config_write' => false,
            'v0_production_changed' => false,
        ],
    ];
    if ($json) {
        echo json_encode($error, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    } else {
        fwrite(STDERR, 'ERROR: ' . $e->getMessage() . PHP_EOL);
    }
    exit(1);
}
