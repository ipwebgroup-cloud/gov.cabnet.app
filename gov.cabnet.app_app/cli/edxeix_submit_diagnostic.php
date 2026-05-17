<?php
/**
 * gov.cabnet.app — CLI EDXEIX submit diagnostic v3.2.20
 *
 * Default: dry-run analysis only. No EDXEIX HTTP transport.
 * Transport requires --transport=1 and the exact server-only confirmation phrase.
 */

declare(strict_types=1);

require_once '/home/cabnet/gov.cabnet.app_app/lib/edxeix_submit_diagnostic_lib.php';

$argv = is_array($argv ?? null) ? $argv : [];
$json = gov_edxdiag_bool(gov_edxdiag_cli_value($argv, 'json', '0'));
$transport = gov_edxdiag_bool(gov_edxdiag_cli_value($argv, 'transport', '0'));
$follow = !gov_edxdiag_bool(gov_edxdiag_cli_value($argv, 'no-follow', '0'));
$bookingId = trim((string)gov_edxdiag_cli_value($argv, 'booking-id', ''));
$orderReference = trim((string)gov_edxdiag_cli_value($argv, 'order-reference', ''));
$confirm = (string)gov_edxdiag_cli_value($argv, 'confirm', '');

try {
    $result = gov_edxdiag_run([
        'booking_id' => $bookingId,
        'order_reference' => $orderReference,
        'transport' => $transport,
        'follow_redirects' => $follow,
        'confirmation_phrase' => $confirm,
    ]);
} catch (Throwable $e) {
    $result = [
        'ok' => false,
        'started_at' => date('Y-m-d H:i:s'),
        'transport_requested' => $transport,
        'transport_performed' => false,
        'classification' => [
            'code' => 'DIAGNOSTIC_EXCEPTION',
            'message' => $e->getMessage(),
        ],
    ];
}

if ($json) {
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(!empty($result['ok']) ? 0 : 1);
}

$analysis = is_array($result['analysis'] ?? null) ? $result['analysis'] : [];
$class = is_array($result['classification'] ?? null) ? $result['classification'] : [];

echo "gov.cabnet.app — EDXEIX Submit Diagnostic v3.2.20\n";
echo "Started: " . (string)($result['started_at'] ?? '') . "\n";
echo "Booking ID: " . (string)($analysis['booking_id'] ?? '') . "\n";
echo "Order reference: " . (string)($analysis['order_reference'] ?? '') . "\n";
echo "Transport requested: " . (!empty($result['transport_requested']) ? 'YES' : 'NO') . "\n";
echo "Transport performed: " . (!empty($result['transport_performed']) ? 'YES' : 'NO') . "\n";
echo "Classification: " . (string)($class['code'] ?? '') . "\n";
echo "Message: " . (string)($class['message'] ?? '') . "\n";
echo "Next action: " . (string)($result['next_action'] ?? '') . "\n";

$blockers = is_array($result['transport_blockers'] ?? null) ? $result['transport_blockers'] : [];
if ($blockers) {
    echo "Transport blockers:\n";
    foreach ($blockers as $blocker) {
        echo "- " . (string)$blocker . "\n";
    }
}

$liveBlockers = is_array($analysis['live_blockers'] ?? null) ? $analysis['live_blockers'] : [];
if ($liveBlockers) {
    echo "Live gate blockers:\n";
    foreach ($liveBlockers as $blocker) {
        echo "- " . (string)$blocker . "\n";
    }
}

$trace = is_array($result['trace'] ?? null) ? $result['trace'] : [];
$steps = is_array($trace['steps'] ?? null) ? $trace['steps'] : [];
if ($steps) {
    echo "Trace steps:\n";
    foreach ($steps as $idx => $step) {
        echo '- #' . ($idx + 1) . ' ' . (string)($step['method'] ?? '') . ' ' . (string)($step['status'] ?? '') . ' ' . (string)($step['url'] ?? '') . "\n";
        if (!empty($step['location'])) {
            echo "  Location: " . (string)$step['location'] . "\n";
        }
        $fp = is_array($step['body_fingerprint'] ?? null) ? $step['body_fingerprint'] : [];
        if (!empty($fp['title'])) {
            echo "  Title: " . (string)$fp['title'] . "\n";
        }
    }
}

exit(!empty($result['ok']) ? 0 : 1);
