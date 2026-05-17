<?php
/**
 * gov.cabnet.app — CLI EDXEIX submit diagnostic v3.2.22
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
$candidateLimit = (int)gov_edxdiag_cli_value($argv, 'limit', '75');
$listCandidates = gov_edxdiag_bool(gov_edxdiag_cli_value($argv, 'list-candidates', '0'));
$preRideLatest = gov_edxdiag_bool(gov_edxdiag_cli_value($argv, 'pre-ride-latest', '0'));
$preRideEmailFile = trim((string)gov_edxdiag_cli_value($argv, 'pre-ride-email-file', ''));

try {
    $result = gov_edxdiag_run([
        'booking_id' => $bookingId,
        'order_reference' => $orderReference,
        'transport' => $transport,
        'follow_redirects' => $follow,
        'confirmation_phrase' => $confirm,
        'candidate_limit' => $candidateLimit,
        'list_candidates' => $listCandidates,
        'pre_ride_latest' => $preRideLatest,
        'pre_ride_email_file' => $preRideEmailFile,
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

echo "gov.cabnet.app — EDXEIX Submit Diagnostic v3.2.22\n";
echo "Started: " . (string)($result['started_at'] ?? '') . "\n";
echo "Booking ID: " . (string)($analysis['booking_id'] ?? '') . "\n";
echo "Order reference: " . (string)($analysis['order_reference'] ?? '') . "\n";
echo "Transport requested: " . (!empty($result['transport_requested']) ? 'YES' : 'NO') . "\n";
echo "Transport performed: " . (!empty($result['transport_performed']) ? 'YES' : 'NO') . "\n";
echo "Classification: " . (string)($class['code'] ?? '') . "\n";
echo "Message: " . (string)($class['message'] ?? '') . "\n";
echo "Next action: " . (string)($result['next_action'] ?? '') . "\n";

$candidateReport = is_array($result['candidate_report'] ?? null) ? $result['candidate_report'] : [];
if ($candidateReport) {
    echo "Candidate report:\n";
    echo "- Checked: " . (string)($candidateReport['checked_count'] ?? 0) . "\n";
    echo "- Ready candidates: " . (string)($candidateReport['ready_candidate_count'] ?? 0) . "\n";
    echo "- Configured guard minutes: " . (string)($candidateReport['configured_future_guard_minutes'] ?? '') . "\n";
    echo "- Effective guard minutes: " . (string)($candidateReport['effective_future_guard_minutes'] ?? '') . "\n";
    if (!empty($candidateReport['future_guard_floor_applied'])) {
        echo "- Guard floor applied: YES\n";
    }
    $rows = is_array($candidateReport['rows'] ?? null) ? $candidateReport['rows'] : [];
    if ($listCandidates && $rows) {
        echo "Recent candidate rows:\n";
        foreach ($rows as $row) {
            echo "  #" . (string)($row['booking_id'] ?? '')
                . " " . (string)($row['started_at'] ?? '')
                . " " . (string)($row['status'] ?? '')
                . " " . (string)($row['plate'] ?? '')
                . " ready=" . (!empty($row['diagnostic_ready_candidate']) ? 'YES' : 'NO')
                . "\n";
            $rowBlockers = is_array($row['diagnostic_safety_blockers'] ?? null) ? $row['diagnostic_safety_blockers'] : [];
            if ($rowBlockers) {
                echo "    blockers: " . implode(', ', $rowBlockers) . "\n";
            }
        }
    }
}


$preRideReport = is_array($result['pre_ride_candidate_report'] ?? null) ? $result['pre_ride_candidate_report'] : [];
if ($preRideReport) {
    $preClass = is_array($preRideReport['classification'] ?? null) ? $preRideReport['classification'] : [];
    $preCandidate = is_array($preRideReport['candidate'] ?? null) ? $preRideReport['candidate'] : [];
    echo "Pre-ride candidate report:
";
    echo "- Classification: " . (string)($preClass['code'] ?? '') . "
";
    echo "- Message: " . (string)($preClass['message'] ?? '') . "
";
    if ($preCandidate) {
        echo "- Pickup: " . (string)($preCandidate['pickup_datetime'] ?? '') . "
";
        echo "- Driver: " . (string)($preCandidate['driver_name'] ?? '') . "
";
        echo "- Vehicle: " . (string)($preCandidate['vehicle_plate'] ?? '') . "
";
        echo "- Ready: " . (!empty($preCandidate['ready_for_edxeix']) ? 'YES' : 'NO') . "
";
        $preBlockers = is_array($preCandidate['safety_blockers'] ?? null) ? $preCandidate['safety_blockers'] : [];
        if ($preBlockers) {
            echo "  blockers: " . implode(', ', $preBlockers) . "
";
        }
    }
}

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
