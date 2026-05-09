<?php
/**
 * gov.cabnet.app — EDXEIX Firefox extension payload export v6.8.1
 *
 * Browser-fill only. Returns one locked, future-safe, pre-ride Bolt mail payload
 * for manual review/submission in the EDXEIX browser form.
 *
 * Safety:
 * - Does NOT call EDXEIX.
 * - Does NOT submit.
 * - Does NOT create submission_jobs.
 * - Does NOT create submission_attempts.
 * - Does NOT expose cookies, CSRF tokens, or session files.
 * - Requires browser_fill_enabled=true and exact one-shot allowed booking.
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

function eep_bool($value): bool
{
    if (is_bool($value)) {
        return $value;
    }
    return in_array(strtolower((string)$value), ['1', 'true', 'yes', 'on'], true);
}

function eep_block_reason_blocks(string $reason): array
{
    $reason = strtolower(trim($reason));
    if ($reason === '') {
        return [];
    }
    $out = [];
    foreach (['receipt_only', 'aade_receipt_only', 'no_edxeix', 'terminal_status', 'finished', 'past', 'cancel'] as $needle) {
        if (str_contains($reason, $needle)) {
            $out[] = 'live_submit_block_reason_blocks:' . $needle;
        }
    }
    return $out;
}

function eep_analyze_mail_booking_for_browser_fill(mysqli $db, array $booking, array $liveConfig): array
{
    $blockers = [];
    $bookingId = eep_value($booking, ['id']);
    $orderRef = eep_value($booking, ['order_reference', 'external_order_id', 'source_trip_reference', 'source_trip_id']);
    $source = strtolower(trim(eep_value($booking, ['source_system', 'source_type', 'source'])));
    $refLower = strtolower(trim($orderRef));
    $status = strtolower(trim(eep_value($booking, ['order_status', 'status'])));
    $startedAt = trim(eep_value($booking, ['started_at']));
    $neverLive = eep_bool(eep_value($booking, ['never_submit_live'], '0'));
    $blockReason = eep_value($booking, ['live_submit_block_reason']);

    $isMail = $source === 'bolt_mail' || str_starts_with($refLower, 'mail:');
    if (!$isMail) { $blockers[] = 'edxeix_source_must_be_pre_ride_bolt_email'; }
    if ($neverLive) { $blockers[] = 'never_submit_live_flag_set'; }
    $blockers = array_merge($blockers, eep_block_reason_blocks($blockReason));
    if ($status === '' || gov_live_terminal_status($status)) { $blockers[] = 'terminal_or_missing_status'; }
    if ($startedAt === '') { $blockers[] = 'missing_started_at'; }
    elseif (!gov_live_future_guard_passes($startedAt)) { $blockers[] = 'started_at_not_sufficiently_future'; }

    $payload = [];
    try {
        if (!function_exists('gov_build_edxeix_preview_payload')) {
            throw new RuntimeException('payload_builder_missing');
        }
        $payload = gov_build_edxeix_preview_payload($db, $booking);
        if (function_exists('gov_live_normalize_edxeix_payload_field_names')) {
            $payload = gov_live_normalize_edxeix_payload_field_names($payload);
        }
        unset($payload['_token']);
    } catch (Throwable $e) {
        $blockers[] = 'payload_preview_error:' . $e->getMessage();
    }

    foreach ([
        'driver' => 'driver_not_mapped',
        'vehicle' => 'vehicle_not_mapped',
        'boarding_point' => 'missing_boarding_point',
        'disembark_point' => 'missing_disembark_point',
        'started_at' => 'missing_payload_started_at',
        'ended_at' => 'missing_payload_ended_at',
    ] as $field => $code) {
        if (trim((string)($payload[$field] ?? '')) === '') {
            $blockers[] = $code;
        }
    }
    if (trim((string)($payload['starting_point'] ?? $payload['starting_point_id'] ?? '')) === '') {
        $blockers[] = 'starting_point_not_mapped';
    }

    if ($payload) {
        $duplicate = gov_live_duplicate_checks($db, $booking, $payload);
        foreach ((array)($duplicate['blockers'] ?? []) as $dup) {
            $blockers[] = 'duplicate:' . (string)$dup;
        }
    }

    return [
        'booking_id' => $bookingId,
        'order_reference' => $orderRef,
        'source_system' => eep_value($booking, ['source_system', 'source_type', 'source']),
        'status' => $status,
        'started_at' => $startedAt,
        'driver_name' => eep_value($booking, ['driver_name', 'external_driver_name']),
        'vehicle_plate' => eep_value($booking, ['vehicle_plate', 'plate']),
        'ready_for_browser_fill' => empty($blockers),
        'blockers' => array_values(array_unique($blockers)),
        'payload_hash' => $payload ? gov_live_payload_hash($payload) : '',
        'payload' => $payload,
    ];
}

$outBase = [
    'ok' => false,
    'script' => 'edxeix-extension-payload.php',
    'version' => 'v6.8.1',
    'generated_at' => date('c'),
    'source_policy' => [
        'edxeix_submission_source' => 'pre_ride_bolt_email_only',
        'edxeix_uses_bolt_api_as_source' => false,
        'aade_invoice_source' => 'bolt_api_pickup_timestamp_worker_only',
        'pre_ride_email_may_issue_aade' => false,
    ],
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

    $analysis = eep_analyze_mail_booking_for_browser_fill($db, $booking, $liveConfig);
    if (empty($analysis['ready_for_browser_fill']) || empty($analysis['payload'])) {
        eep_json($outBase + [
            'error' => 'booking_not_safe_for_browser_fill',
            'booking_id' => $actualBookingId,
            'order_reference' => $actualOrderRef,
            'analysis_summary' => [
                'source_system' => (string)($analysis['source_system'] ?? ''),
                'status' => (string)($analysis['status'] ?? ''),
                'started_at' => (string)($analysis['started_at'] ?? ''),
                'driver_name' => (string)($analysis['driver_name'] ?? ''),
                'vehicle_plate' => (string)($analysis['vehicle_plate'] ?? ''),
                'blockers' => (array)($analysis['blockers'] ?? []),
            ],
        ], 409);
    }

    eep_json($outBase + [
        'ok' => true,
        'ready_for_browser_fill' => true,
        'booking_id' => $actualBookingId,
        'order_reference' => $actualOrderRef,
        'payload_hash' => (string)($analysis['payload_hash'] ?? ''),
        'payload' => $analysis['payload'],
        'analysis_summary' => [
            'source_system' => (string)($analysis['source_system'] ?? ''),
            'status' => (string)($analysis['status'] ?? ''),
            'started_at' => (string)($analysis['started_at'] ?? ''),
            'driver_name' => (string)($analysis['driver_name'] ?? ''),
            'vehicle_plate' => (string)($analysis['vehicle_plate'] ?? ''),
            'blockers' => [],
        ],
        'operator_note' => 'Open the EDXEIX create form in Firefox, use the extension to fill the form, review all fields, then submit manually in the browser.',
    ]);
} catch (Throwable $e) {
    eep_json($outBase + [
        'error' => 'exception',
        'message' => $e->getMessage(),
    ], 500);
}
