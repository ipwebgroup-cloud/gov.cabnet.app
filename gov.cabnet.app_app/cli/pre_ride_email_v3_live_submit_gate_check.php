<?php
/**
 * gov.cabnet.app — V3 live-submit master gate check.
 *
 * Read-only CLI check for the server-side live-submit gate.
 */

declare(strict_types=1);

date_default_timezone_set('Europe/Athens');

const PRV3_GATE_CHECK_VERSION = 'v3.0.31-live-submit-gate-config-hygiene-check';

function prv3_gate_arg(array $argv, string $name): bool
{
    return in_array($name, $argv, true);
}

function prv3_gate_private_file(string $relative): string
{
    return dirname(__DIR__) . '/' . ltrim($relative, '/');
}

$help = prv3_gate_arg($argv, '--help') || prv3_gate_arg($argv, '-h');
$json = prv3_gate_arg($argv, '--json');
if ($help) {
    echo "V3 live-submit gate check " . PRV3_GATE_CHECK_VERSION . "\n\n";
    echo "Usage:\n  php pre_ride_email_v3_live_submit_gate_check.php [--json]\n\n";
    echo "Read-only. No EDXEIX calls. No AADE calls. No DB writes.\n";
    exit(0);
}

$classFile = prv3_gate_private_file('src/BoltMailV3/LiveSubmitGateV3.php');
if (!is_file($classFile)) {
    $result = [
        'ok' => false,
        'version' => PRV3_GATE_CHECK_VERSION,
        'error' => 'LiveSubmitGateV3 class not found: ' . $classFile,
    ];
    echo $json ? json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n" : 'ERROR: ' . $result['error'] . "\n";
    exit(1);
}
require_once $classFile;

$result = \Bridge\BoltMailV3\LiveSubmitGateV3::evaluate();
$result['check_version'] = PRV3_GATE_CHECK_VERSION;
$result['checked_at'] = date(DATE_ATOM);

if ($json) {
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "\n";
    exit(0);
}

echo "V3 live-submit gate check " . PRV3_GATE_CHECK_VERSION . "\n";
echo "Gate version: " . ($result['version'] ?? '-') . "\n";
echo "Config loaded: " . (!empty($result['config_loaded']) ? 'yes' : 'no') . "\n";
echo "Config path: " . ((string)($result['config_path'] ?? '') ?: '-') . "\n";
echo "Config error: " . ((string)($result['config_error'] ?? '') ?: '-') . "\n";
echo "Enabled: " . (!empty($result['enabled']) ? 'yes' : 'no') . "\n";
echo "Mode: " . (string)($result['mode'] ?? '-') . "\n";
echo "Adapter: " . (string)($result['adapter'] ?? '-') . "\n";
echo "Hard enable live submit: " . (!empty($result['hard_enable_live_submit']) ? 'yes' : 'no') . "\n";
echo "Required status: " . (string)($result['required_queue_status'] ?? '-') . "\n";
echo "Min future minutes: " . (int)($result['min_future_minutes'] ?? 0) . "\n";
echo "OK for future live submit: " . (!empty($result['ok_for_future_live_submit']) ? 'yes' : 'no') . "\n";
echo "Safety: No EDXEIX call. No AADE call. No DB writes.\n";

foreach (($result['blocks'] ?? []) as $block) {
    $block = trim((string)$block);
    if ($block !== '') {
        echo "Block: " . $block . "\n";
    }
}
foreach (($result['warnings'] ?? []) as $warning) {
    $warning = trim((string)$warning);
    if ($warning !== '') {
        echo "Warning: " . $warning . "\n";
    }
}

exit(0);
