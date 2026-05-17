<?php
/**
 * gov.cabnet.app — CLI pre-ride one-shot readiness packet v3.2.27
 *
 * Safety: readiness only. No EDXEIX transport, no AADE call, no queue job.
 */

declare(strict_types=1);

require_once '/home/cabnet/gov.cabnet.app_app/lib/edxeix_pre_ride_one_shot_readiness_lib.php';

function pror_cli_value(array $argv, string $key, ?string $default = null): ?string
{
    foreach ($argv as $arg) {
        if ($arg === '--' . $key) { return '1'; }
        if (strpos($arg, '--' . $key . '=') === 0) { return substr($arg, strlen('--' . $key . '=')); }
    }
    return $default;
}

$argv = is_array($argv ?? null) ? $argv : [];
$json = gov_pror_bool(pror_cli_value($argv, 'json', '0'));
$candidateId = (int)pror_cli_value($argv, 'candidate-id', '0');
$latestReady = gov_pror_bool(pror_cli_value($argv, 'latest-ready', '0'));
$latestMail = gov_pror_bool(pror_cli_value($argv, 'latest-mail', '0'));

try {
    $result = gov_pror_run([
        'candidate_id' => $candidateId,
        'latest_ready' => $latestReady,
        'latest_mail' => $latestMail,
    ]);
} catch (Throwable $e) {
    $result = [
        'ok' => false,
        'classification' => [
            'code' => 'PRE_RIDE_ONE_SHOT_READINESS_EXCEPTION',
            'message' => $e->getMessage(),
        ],
        'transport_performed' => false,
    ];
}

if ($json) {
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(!empty($result['ok']) ? 0 : 1);
}

$class = is_array($result['classification'] ?? null) ? $result['classification'] : [];
$packet = is_array($result['operator_packet'] ?? null) ? $result['operator_packet'] : [];
$blockers = is_array($result['readiness_blockers'] ?? null) ? $result['readiness_blockers'] : [];

echo "gov.cabnet.app — Pre-Ride One-Shot Readiness Packet v3.2.27\n";
echo "Classification: " . (string)($class['code'] ?? '') . "\n";
echo "Message: " . (string)($class['message'] ?? '') . "\n";
echo "Transport performed: " . (!empty($result['transport_performed']) ? 'YES' : 'NO') . "\n";
echo "Ready for supervised one-shot: " . (!empty($result['ready_for_supervised_one_shot']) ? 'YES' : 'NO') . "\n";

if ($packet) {
    echo "Packet ID: " . (string)($packet['packet_id'] ?? '') . "\n";
    echo "Candidate ID: " . (string)($packet['candidate_id'] ?? '') . "\n";
    echo "Pickup: " . (string)($packet['pickup_datetime'] ?? '') . "\n";
    echo "Minutes until pickup: " . (string)($packet['minutes_until_pickup'] ?? '') . "\n";
    echo "Driver: " . (string)($packet['driver_name'] ?? '') . "\n";
    echo "Vehicle: " . (string)($packet['vehicle_plate'] ?? '') . "\n";
    echo "Payload hash: " . (string)($packet['payload_hash'] ?? '') . "\n";
}

if ($blockers) {
    echo "Readiness blockers:\n";
    foreach ($blockers as $blocker) { echo "- " . (string)$blocker . "\n"; }
}

echo "Next action: " . (string)($result['next_action'] ?? '') . "\n";
exit(!empty($result['ok']) ? 0 : 1);
