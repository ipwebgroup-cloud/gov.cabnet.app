#!/usr/bin/env php
<?php
/**
 * gov.cabnet.app — CLI supervised pre-ride one-shot EDXEIX transport trace v3.2.35.
 * Default is dry-run/armable packet. v3.2.35 is create-form token diagnostic/hold mode after the 419 test. Any later HTTP POST requires a new approved patch plus --transport=1,
 * --candidate-id=N, --expected-payload-hash=HASH, and exact --confirm phrase.
 */

declare(strict_types=1);

require_once '/home/cabnet/gov.cabnet.app_app/lib/edxeix_pre_ride_one_shot_transport_trace_lib.php';

$options = [
    'candidate_id' => 0,
    'transport' => false,
    'follow_redirects' => true,
    'confirmation_phrase' => '',
    'expected_payload_hash' => '',
];
$json = false;

foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--json') { $json = true; continue; }
    if ($arg === '--transport=1' || $arg === '--transport') { $options['transport'] = true; continue; }
    if ($arg === '--no-follow-redirects') { $options['follow_redirects'] = false; continue; }
    if (preg_match('/^--candidate-id=(\d+)$/', $arg, $m)) { $options['candidate_id'] = (int)$m[1]; continue; }
    if (strpos($arg, '--expected-payload-hash=') === 0) { $options['expected_payload_hash'] = substr($arg, strlen('--expected-payload-hash=')); continue; }
    if (strpos($arg, '--confirm=') === 0) { $options['confirmation_phrase'] = substr($arg, strlen('--confirm=')); continue; }
    if ($arg === '--help' || $arg === '-h') {
        echo "Usage:\n";
        echo "  php pre_ride_one_shot_transport_trace.php --candidate-id=N --json\n";
        echo "  php pre_ride_one_shot_transport_trace.php --candidate-id=N --transport=1 --expected-payload-hash=HASH --confirm='" . gov_prtx_confirmation_phrase() . "' --json\n";
        echo "\nDefault is dry-run/armable packet. v3.2.35 will report hold blockers/form-token diagnostics and will not perform another POST.\n";
        exit(0);
    }
}

try {
    $result = gov_prtx_run($options);
    if ($json) {
        echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        exit(0);
    }

    echo 'Classification: ' . ($result['classification']['code'] ?? 'UNKNOWN') . PHP_EOL;
    echo ($result['classification']['message'] ?? '') . PHP_EOL;
    echo 'Transport requested: ' . (!empty($result['transport_requested']) ? 'YES' : 'NO') . PHP_EOL;
    echo 'Transport performed: ' . (!empty($result['transport_performed']) ? 'YES' : 'NO') . PHP_EOL;
    $packet = is_array($result['operator_transport_packet'] ?? null) ? $result['operator_transport_packet'] : [];
    if ($packet) {
        echo 'Transport ID: ' . ($packet['transport_id'] ?? '') . PHP_EOL;
        echo 'Candidate ID: ' . ($packet['candidate_id'] ?? '') . PHP_EOL;
        echo 'Payload hash: ' . ($packet['payload_hash'] ?? '') . PHP_EOL;
        echo 'Pickup: ' . ($packet['pickup_datetime'] ?? '') . PHP_EOL;
        echo 'Minutes until pickup: ' . ($packet['minutes_until_pickup'] ?? '') . PHP_EOL;
    }
    if (!empty($result['transport_blockers'])) {
        echo 'Blockers:' . PHP_EOL;
        foreach ($result['transport_blockers'] as $blocker) { echo '- ' . $blocker . PHP_EOL; }
    }
    echo 'Required confirmation phrase: ' . ($result['required_confirmation_phrase'] ?? '') . PHP_EOL;
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
