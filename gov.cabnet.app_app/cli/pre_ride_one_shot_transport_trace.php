#!/usr/bin/env php
<?php
/**
 * gov.cabnet.app — CLI supervised pre-ride one-shot EDXEIX transport trace v3.2.37.
 * Default is dry-run/armable packet. HTTP POST requires candidate-id, payload hash,
 * exact confirmation phrase, and strict identity expectations.
 */

declare(strict_types=1);

require_once '/home/cabnet/gov.cabnet.app_app/lib/edxeix_pre_ride_one_shot_transport_trace_lib.php';

$options = [
    'candidate_id' => 0,
    'transport' => false,
    'follow_redirects' => true,
    'confirmation_phrase' => '',
    'expected_payload_hash' => '',
    'expected_customer' => '',
    'expected_driver' => '',
    'expected_vehicle' => '',
    'expected_pickup' => '',
];
$json = false;
$listRecent = false;
$listLimit = 10;

foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--json') { $json = true; continue; }
    if ($arg === '--transport=1' || $arg === '--transport') { $options['transport'] = true; continue; }
    if ($arg === '--no-follow-redirects') { $options['follow_redirects'] = false; continue; }
    if ($arg === '--list-recent-candidates') { $listRecent = true; continue; }
    if (preg_match('/^--list-limit=(\d+)$/', $arg, $m)) { $listLimit = (int)$m[1]; continue; }
    if (preg_match('/^--candidate-id=(\d+)$/', $arg, $m)) { $options['candidate_id'] = (int)$m[1]; continue; }
    if (strpos($arg, '--expected-payload-hash=') === 0) { $options['expected_payload_hash'] = substr($arg, strlen('--expected-payload-hash=')); continue; }
    if (strpos($arg, '--confirm=') === 0) { $options['confirmation_phrase'] = substr($arg, strlen('--confirm=')); continue; }
    if (strpos($arg, '--expect-customer=') === 0) { $options['expected_customer'] = substr($arg, strlen('--expect-customer=')); continue; }
    if (strpos($arg, '--expect-driver=') === 0) { $options['expected_driver'] = substr($arg, strlen('--expect-driver=')); continue; }
    if (strpos($arg, '--expect-vehicle=') === 0) { $options['expected_vehicle'] = substr($arg, strlen('--expect-vehicle=')); continue; }
    if (strpos($arg, '--expect-pickup=') === 0) { $options['expected_pickup'] = substr($arg, strlen('--expect-pickup=')); continue; }
    if ($arg === '--help' || $arg === '-h') {
        echo "Usage:\n";
        echo "  php pre_ride_one_shot_transport_trace.php --list-recent-candidates --json\n";
        echo "  php pre_ride_one_shot_transport_trace.php --candidate-id=N --json\n";
        echo "  php pre_ride_one_shot_transport_trace.php --candidate-id=N --transport=1 \\\n";
        echo "    --expect-customer='Customer Name' --expect-driver='Driver Name' --expect-vehicle='PLATE' --expect-pickup='YYYY-MM-DD HH:MM:SS' \\\n";
        echo "    --expected-payload-hash=HASH --confirm='" . gov_prtx_confirmation_phrase() . "' --json\n";
        echo "\nDefault is dry-run/armable packet. v3.2.37 blocks POST unless strict candidate identity matches the intended ride.\n";
        exit(0);
    }
}

try {
    if ($listRecent) {
        $result = [
            'ok' => true,
            'version' => 'v3.2.37-strict-identity-lock-validation-capture',
            'mode' => 'recent_candidates',
            'transport_performed' => false,
            'recent_candidates' => gov_prtx_recent_candidates($listLimit),
            'next_action' => 'Choose the intended candidate_id explicitly. Do not use latest-ready for transport.',
        ];
    } else {
        $result = gov_prtx_run($options);
    }

    if ($json) {
        echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        exit(0);
    }

    echo 'Classification: ' . ($result['classification']['code'] ?? 'RECENT_CANDIDATES') . PHP_EOL;
    echo ($result['classification']['message'] ?? ($result['next_action'] ?? '')) . PHP_EOL;
    echo 'Transport performed: ' . (!empty($result['transport_performed']) ? 'YES' : 'NO') . PHP_EOL;
    $packet = is_array($result['operator_transport_packet'] ?? null) ? $result['operator_transport_packet'] : [];
    if ($packet) {
        echo 'Candidate ID: ' . ($packet['candidate_id'] ?? '') . PHP_EOL;
        echo 'Payload hash: ' . ($packet['payload_hash'] ?? '') . PHP_EOL;
        echo 'Pickup: ' . ($packet['pickup_datetime'] ?? '') . PHP_EOL;
        echo 'Driver: ' . ($packet['driver_name'] ?? '') . PHP_EOL;
        echo 'Vehicle: ' . ($packet['vehicle_plate'] ?? '') . PHP_EOL;
    }
    if (!empty($result['identity_lock']['checks'])) {
        echo 'Identity lock:' . PHP_EOL;
        foreach ($result['identity_lock']['checks'] as $check) {
            echo '- ' . ($check['field'] ?? '') . ': ' . (!empty($check['match']) ? 'MATCH' : 'NOT LOCKED/MISMATCH') . PHP_EOL;
        }
    }
    if (!empty($result['transport_blockers'])) {
        echo 'Blockers:' . PHP_EOL;
        foreach ($result['transport_blockers'] as $blocker) { echo '- ' . $blocker . PHP_EOL; }
    }
    echo 'Required confirmation phrase: ' . (function_exists('gov_prtx_confirmation_phrase') ? gov_prtx_confirmation_phrase() : '') . PHP_EOL;
    echo 'Next action: ' . ($result['next_action'] ?? '') . PHP_EOL;
    exit(0);
} catch (Throwable $e) {
    $error = [
        'ok' => false,
        'classification' => [
            'code' => 'PRE_RIDE_TRANSPORT_TRACE_ERROR',
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
