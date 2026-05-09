<?php
/**
 * gov.cabnet.app — EDXEIX Firefox extension payload export v6.4.0
 *
 * Purpose:
 * - Return one locked, future-safe EDXEIX payload for browser-side form filling.
 *
 * Safety:
 * - Does NOT call EDXEIX.
 * - Does NOT submit.
 * - Does NOT create submission_jobs.
 * - Does NOT create submission_attempts.
 * - Does NOT expose cookies, CSRF tokens, or session files.
 * - Requires browser_fill_enabled=true and exact one-shot allowed_booking_id/order_reference in server-only live_submit.php.
 */

declare(strict_types=1);

require_once '/home/cabnet/gov.cabnet.app_app/lib/edxeix_live_submit_gate.php';

function eep_json(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit;
}

function eep_value(array $row, array $keys, string $default = ''): string
{
    foreach ($keys as $key) {
        if (array_key_exists($key, $row) && $row[$key] !== null && $row[$key] !== '') {
            return (string)$row[$key];
        }
    }
    return $default;
}

$outBase = [
    'ok' => false,
    'script' => 'edxeix-extension-payload.php',
    'version' => 'v6.4.0',
    'generated_at' => date('c'),
    'does_not_submit' => true,
    'does_not_call_edxeix' => true,
    'does_not_create_submission_jobs' => true,
    'does_not_create_submission_attempts' => true,
    'does_not_expose_session_or_csrf' => true,
];

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    eep_json($outBase + ['error' => 'GET_required'], 405);
}

try {
    $config = gov_bridge_load_config();
    if (!empty($config['app']['timezone'])) {
        date_default_timezone_set((string)$config['app']['timezone']);
    }

    $db = gov_bridge_db();
    $liveConfig = gov_live_load_config();

    $allowedBookingId = trim((string)($liveConfig['allowed_booking_id'] ?? ''));
    $allowedOrderRef = trim((string)($liveConfig['allowed_order_reference'] ?? ''));
    $browserFillEnabled = !empty($liveConfig['browser_fill_enabled']);

    if (!$browserFillEnabled) {
        eep_json($outBase + [
            'error' => 'browser_fill_not_enabled',
            'message' => 'Server-only config must explicitly arm browser_fill_enabled=true for one exact booking.',
            'lock' => [
                'allowed_booking_id_present' => $allowedBookingId !== '',
                'allowed_order_reference_present' => $allowedOrderRef !== '',
            ],
        ], 423);
    }

    if ($allowedBookingId === '' && $allowedOrderRef === '') {
        eep_json($outBase + [
            'error' => 'one_shot_lock_missing',
            'message' => 'No allowed_booking_id or allowed_order_reference is set.',
        ], 423);
    }

    $bookingId = trim((string)($_GET['booking_id'] ?? ''));
    $orderRef = trim((string)($_GET['order_reference'] ?? ''));

    if ($bookingId === '' && $orderRef === '') {
        $bookingId = $allowedBookingId;
        $orderRef = $allowedOrderRef;
    }

    $booking = null;

    if ($bookingId !== '') {
        $booking = gov_live_booking_by_id($db, $bookingId);
    }

    if (!$booking && $orderRef !== '' && gov_bridge_table_exists($db, 'normalized_bookings')) {
        $columns = gov_bridge_table_columns($db, 'normalized_bookings');
        foreach (['order_reference', 'external_order_id', 'source_trip_reference', 'source_trip_id', 'source_booking_id'] as $column) {
            if (!isset($columns[$column])) {
                continue;
            }
            $booking = gov_bridge_fetch_one(
                $db,
                'SELECT * FROM normalized_bookings WHERE ' . gov_bridge_quote_identifier($column) . ' = ? LIMIT 1',
                [$orderRef]
            );
            if ($booking) {
                break;
            }
        }
    }

    if (!$booking) {
        eep_json($outBase + [
            'error' => 'booking_not_found',
            'requested_booking_id' => $bookingId,
            'requested_order_reference' => $orderRef,
        ], 404);
    }

    $actualBookingId = eep_value($booking, ['id']);
    $actualOrderRef = eep_value($booking, ['order_reference', 'external_order_id', 'external_reference', 'source_trip_reference', 'source_trip_id']);

    $idMatches = $allowedBookingId !== '' && $actualBookingId === $allowedBookingId;
    $refMatches = $allowedOrderRef !== '' && $actualOrderRef === $allowedOrderRef;

    if (!$idMatches && !$refMatches) {
        eep_json($outBase + [
            'error' => 'booking_not_locked_for_browser_fill',
            'requested_booking_id' => $bookingId,
            'actual_booking_id' => $actualBookingId,
            'allowed_booking_id_present' => $allowedBookingId !== '',
            'allowed_order_reference_present' => $allowedOrderRef !== '',
        ], 403);
    }

    $analysis = gov_live_analyze_booking($db, $booking, $liveConfig);
    $duplicateBlockers = array_values(array_unique(array_map('strval', (array)($analysis['duplicate_check']['blockers'] ?? []))));
    $technicalBlockers = array_values(array_unique(array_map('strval', (array)($analysis['technical_blockers'] ?? []))));
    $liveBlockers = array_values(array_unique(array_map('strval', (array)($analysis['live_blockers'] ?? []))));

    $futureOk = !empty($analysis['future_guard_passed']) || !empty($analysis['future_guard_passes']);

    $eligible = !empty($analysis['is_real_bolt'])
        && empty($analysis['is_receipt_only_booking'])
        && empty($analysis['is_lab_or_test'])
        && empty($analysis['terminal_status'])
        && $futureOk
        && !empty($analysis['technical_payload_valid'])
        && $duplicateBlockers === [];

    $payload = is_array($analysis['edxeix_payload_preview'] ?? null) ? $analysis['edxeix_payload_preview'] : [];
    unset($payload['_token']);

    if (!$eligible || !$payload) {
        eep_json($outBase + [
            'error' => 'booking_not_safe_for_browser_fill',
            'booking_id' => $actualBookingId,
            'order_reference' => $actualOrderRef,
            'analysis_summary' => [
                'source_system' => (string)($analysis['source_system'] ?? ''),
                'status' => (string)($analysis['status'] ?? ''),
                'started_at' => (string)($analysis['started_at'] ?? ''),
                'driver_name' => (string)($analysis['driver_name'] ?? ''),
                'vehicle_plate' => (string)($analysis['plate'] ?? ''),
                'is_real_bolt' => !empty($analysis['is_real_bolt']),
                'is_receipt_only_booking' => !empty($analysis['is_receipt_only_booking']),
                'is_lab_or_test' => !empty($analysis['is_lab_or_test']),
                'future_guard_passed' => $futureOk,
                'terminal_status' => !empty($analysis['terminal_status']),
                'technical_payload_valid' => !empty($analysis['technical_payload_valid']),
                'technical_blockers' => $technicalBlockers,
                'live_blockers_ignored_for_browser_fill' => $liveBlockers,
                'duplicate_blockers' => $duplicateBlockers,
            ],
        ], 409);
    }

    eep_json($outBase + [
        'ok' => true,
        'ready_for_browser_fill' => true,
        'booking_id' => $actualBookingId,
        'order_reference' => $actualOrderRef,
        'payload_hash' => (string)($analysis['payload_hash'] ?? ''),
        'payload' => $payload,
        'analysis_summary' => [
            'source_system' => (string)($analysis['source_system'] ?? ''),
            'status' => (string)($analysis['status'] ?? ''),
            'started_at' => (string)($analysis['started_at'] ?? ''),
            'driver_name' => (string)($analysis['driver_name'] ?? ''),
            'vehicle_plate' => (string)($analysis['plate'] ?? ''),
            'technical_payload_valid' => !empty($analysis['technical_payload_valid']),
            'future_guard_passed' => $futureOk,
            'duplicate_blockers' => $duplicateBlockers,
        ],
        'operator_note' => 'Open the EDXEIX create form in Firefox, use the extension to fill the form, review all fields, then submit manually in the browser.',
    ]);
} catch (Throwable $e) {
    eep_json($outBase + [
        'error' => 'exception',
        'message' => $e->getMessage(),
    ], 500);
}
