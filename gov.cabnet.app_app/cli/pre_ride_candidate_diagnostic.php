<?php
/**
 * gov.cabnet.app — CLI pre-ride future EDXEIX candidate diagnostic v3.2.23
 *
 * Default: dry-run parse/readiness only. No EDXEIX, no AADE, no queue jobs.
 * Optional --write=1 stores sanitized candidate metadata only if the additive table exists.
 */

declare(strict_types=1);

require_once '/home/cabnet/gov.cabnet.app_app/lib/edxeix_pre_ride_candidate_lib.php';

function prc_cli_value(array $argv, string $key, ?string $default = null): ?string
{
    foreach ($argv as $arg) {
        if ($arg === '--' . $key) { return '1'; }
        if (strpos($arg, '--' . $key . '=') === 0) { return substr($arg, strlen('--' . $key . '=')); }
    }
    return $default;
}

$argv = is_array($argv ?? null) ? $argv : [];
$json = gov_prc_bool(prc_cli_value($argv, 'json', '0'));
$latestMail = gov_prc_bool(prc_cli_value($argv, 'latest-mail', '0'));
$emailFile = trim((string)prc_cli_value($argv, 'email-file', ''));
$write = gov_prc_bool(prc_cli_value($argv, 'write', '0'));

try {
    $result = gov_prc_run([
        'latest_mail' => $latestMail,
        'email_file' => $emailFile,
        'write' => $write,
    ]);
} catch (Throwable $e) {
    $result = [
        'ok' => false,
        'classification' => [
            'code' => 'PRE_RIDE_CANDIDATE_EXCEPTION',
            'message' => $e->getMessage(),
        ],
    ];
}

if ($json) {
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(!empty($result['ok']) ? 0 : 1);
}

$class = is_array($result['classification'] ?? null) ? $result['classification'] : [];
$candidate = is_array($result['candidate'] ?? null) ? $result['candidate'] : [];
$writeResult = is_array($result['write'] ?? null) ? $result['write'] : [];

echo "gov.cabnet.app — Pre-Ride Candidate Diagnostic v3.2.23\n";
echo "Classification: " . (string)($class['code'] ?? '') . "\n";
echo "Message: " . (string)($class['message'] ?? '') . "\n";

if ($candidate) {
    echo "Source system: " . (string)($candidate['source_system'] ?? '') . "\n";
    echo "Pickup: " . (string)($candidate['pickup_datetime'] ?? '') . "\n";
    echo "Driver: " . (string)($candidate['driver_name'] ?? '') . "\n";
    echo "Vehicle: " . (string)($candidate['vehicle_plate'] ?? '') . "\n";
    echo "Ready: " . (!empty($candidate['ready_for_edxeix']) ? 'YES' : 'NO') . "\n";
    $blockers = is_array($candidate['safety_blockers'] ?? null) ? $candidate['safety_blockers'] : [];
    if ($blockers) {
        echo "Safety blockers:\n";
        foreach ($blockers as $blocker) { echo "- " . (string)$blocker . "\n"; }
    }
}

if ($writeResult) {
    echo "Write requested: " . (!empty($writeResult['requested']) ? 'YES' : 'NO') . "\n";
    echo "Written: " . (!empty($writeResult['written']) ? 'YES' : 'NO') . "\n";
    echo "Write message: " . (string)($writeResult['message'] ?? '') . "\n";
}

echo "Next action: " . (string)($result['next_action'] ?? '') . "\n";
exit(!empty($result['ok']) ? 0 : 1);
