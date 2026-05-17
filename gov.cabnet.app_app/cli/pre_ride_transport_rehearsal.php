#!/usr/bin/env php
<?php
/**
 * gov.cabnet.app — CLI pre-ride transport rehearsal packet v3.2.29.
 * Read-only. No EDXEIX transport.
 */

declare(strict_types=1);

require_once '/home/cabnet/gov.cabnet.app_app/lib/edxeix_pre_ride_transport_rehearsal_lib.php';

$options = [
    'candidate_id' => 0,
    'latest_ready' => true,
];
$json = false;

foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--json') { $json = true; continue; }
    if ($arg === '--latest-ready=1' || $arg === '--latest-ready') { $options['latest_ready'] = true; $options['candidate_id'] = 0; continue; }
    if (preg_match('/^--candidate-id=(\d+)$/', $arg, $m)) { $options['candidate_id'] = (int)$m[1]; $options['latest_ready'] = false; continue; }
    if ($arg === '--help' || $arg === '-h') {
        echo "Usage: php pre_ride_transport_rehearsal.php [--candidate-id=N|--latest-ready=1] [--json]\n";
        echo "Read-only rehearsal only. No EDXEIX transport is performed.\n";
        exit(0);
    }
}

try {
    $result = gov_prt_run($options);
    if ($json) {
        echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        exit(0);
    }

    echo 'Classification: ' . ($result['classification']['code'] ?? 'UNKNOWN') . PHP_EOL;
    echo ($result['classification']['message'] ?? '') . PHP_EOL;
    echo 'Transport performed: ' . (!empty($result['transport_performed']) ? 'YES' : 'NO') . PHP_EOL;
    echo 'Ready for later supervised transport patch: ' . (!empty($result['ready_for_later_supervised_transport_patch']) ? 'YES' : 'NO') . PHP_EOL;
    $packet = is_array($result['operator_rehearsal_packet'] ?? null) ? $result['operator_rehearsal_packet'] : [];
    if ($packet) {
        echo 'Rehearsal ID: ' . ($packet['rehearsal_id'] ?? '') . PHP_EOL;
        echo 'Candidate ID: ' . ($packet['candidate_id'] ?? '') . PHP_EOL;
        echo 'Pickup: ' . ($packet['pickup_datetime'] ?? '') . PHP_EOL;
        echo 'Future guard expires at: ' . ($packet['future_guard_expires_at'] ?? '') . PHP_EOL;
        echo 'Payload hash: ' . ($packet['payload_hash'] ?? '') . PHP_EOL;
    }
    if (!empty($result['rehearsal_blockers'])) {
        echo 'Blockers:' . PHP_EOL;
        foreach ($result['rehearsal_blockers'] as $blocker) { echo '- ' . $blocker . PHP_EOL; }
    }
    echo 'Next action: ' . ($result['next_action'] ?? '') . PHP_EOL;
    exit(0);
} catch (Throwable $e) {
    $error = [
        'ok' => false,
        'classification' => [
            'code' => 'PRE_RIDE_TRANSPORT_REHEARSAL_ERROR',
            'message' => $e->getMessage(),
        ],
        'transport_performed' => false,
    ];
    if ($json) {
        echo json_encode($error, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    } else {
        fwrite(STDERR, 'ERROR: ' . $e->getMessage() . PHP_EOL);
    }
    exit(1);
}
