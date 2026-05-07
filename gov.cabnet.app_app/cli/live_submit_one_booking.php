<?php
/**
 * gov.cabnet.app — v5.0 guarded one-shot live submit CLI.
 *
 * This is intentionally manual. It never scans and submits multiple rows.
 * It requires an exact booking id or order reference and the configured
 * confirmation phrase. If the EDXEIX session is not connected, it exits blocked
 * before any HTTP call.
 */

declare(strict_types=1);

require_once '/home/cabnet/gov.cabnet.app_app/lib/edxeix_live_submit_gate.php';

$options = getopt('', ['booking-id::', 'order-reference::', 'confirm::', 'analyze-only']);
$bookingId = trim((string)($options['booking-id'] ?? ''));
$orderReference = trim((string)($options['order-reference'] ?? ''));
$confirm = (string)($options['confirm'] ?? '');
$analyzeOnly = array_key_exists('analyze-only', $options);

if ($bookingId === '' && $orderReference === '') {
    fwrite(STDERR, "Usage: php live_submit_one_booking.php --booking-id=123 --confirm='I UNDERSTAND SUBMIT LIVE TO EDXEIX'\n");
    fwrite(STDERR, "   or: php live_submit_one_booking.php --order-reference=REF --confirm='I UNDERSTAND SUBMIT LIVE TO EDXEIX'\n");
    fwrite(STDERR, "   add --analyze-only to only print blockers and never submit.\n");
    exit(2);
}

$config = gov_bridge_load_config();
if (!empty($config['app']['timezone'])) {
    date_default_timezone_set((string)$config['app']['timezone']);
}

$db = gov_bridge_db();
$booking = null;
if ($bookingId !== '') {
    $booking = gov_live_booking_by_id($db, $bookingId);
}
if (!$booking && $orderReference !== '' && gov_bridge_table_exists($db, 'normalized_bookings')) {
    $columns = gov_bridge_table_columns($db, 'normalized_bookings');
    foreach (['order_reference', 'external_order_id', 'source_trip_reference', 'source_trip_id', 'source_booking_id'] as $column) {
        if (!isset($columns[$column])) {
            continue;
        }
        $booking = gov_bridge_fetch_one($db, 'SELECT * FROM normalized_bookings WHERE ' . gov_bridge_quote_identifier($column) . ' = ? LIMIT 1', [$orderReference]);
        if ($booking) {
            break;
        }
    }
}

if (!$booking) {
    echo json_encode([
        'ok' => false,
        'submitted' => false,
        'blocked' => true,
        'error' => 'booking_not_found',
        'booking_id' => $bookingId,
        'order_reference' => $orderReference,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(1);
}

$liveConfig = gov_live_load_config();
$analysis = gov_live_analyze_booking($db, $booking, $liveConfig);

if ($analyzeOnly) {
    echo json_encode([
        'ok' => true,
        'submitted' => false,
        'mode' => 'analyze_only',
        'analysis' => $analysis,
        'note' => 'Analyze-only mode. No EDXEIX HTTP request was performed.',
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(0);
}

$_SERVER['REQUEST_METHOD'] = 'POST';
$result = gov_live_submit_if_allowed($db, $booking, $confirm);
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
exit(!empty($result['submitted']) ? 0 : 1);
