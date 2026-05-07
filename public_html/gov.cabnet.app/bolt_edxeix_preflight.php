<?php
/**
 * gov.cabnet.app Bolt → EDXEIX preflight report.
 * v4.4 aligns guard display and adds bolt_mail intake context.
 *
 * Read-only diagnostic endpoint.
 * - Does not call EDXEIX.
 * - Does not create submission jobs.
 * - Separates mapping/readiness from LAB/test/live submission safety.
 */

declare(strict_types=1);

require_once '/home/cabnet/gov.cabnet.app_app/lib/bolt_sync_lib.php';

function gov_preflight_value(array $row, array $keys, $default = '')
{
    foreach ($keys as $key) {
        if (array_key_exists($key, $row) && $row[$key] !== null && $row[$key] !== '') {
            return $row[$key];
        }
    }
    return $default;
}

function gov_preflight_boolish($value): bool
{
    if (is_bool($value)) {
        return $value;
    }
    return in_array(strtolower(trim((string)$value)), ['1', 'true', 'yes', 'on'], true);
}

function gov_preflight_terminal_status(string $status): bool
{
    $status = strtolower(trim($status));
    if ($status === '') {
        return false;
    }

    $terminalExact = [
        'finished',
        'completed',
        'client_cancelled',
        'driver_cancelled',
        'driver_cancelled_after_accept',
        'cancelled',
        'canceled',
        'expired',
        'rejected',
        'failed',
    ];

    if (in_array($status, $terminalExact, true)) {
        return true;
    }

    return strpos($status, 'cancel') !== false || strpos($status, 'finished') !== false || strpos($status, 'complete') !== false;
}

function gov_preflight_order_reference(array $booking): string
{
    return (string)gov_preflight_value($booking, ['order_reference', 'external_order_id', 'external_reference', 'source_trip_reference', 'source_trip_id'], '');
}

function gov_preflight_is_lab_row(array $booking): bool
{
    $source = strtolower((string)gov_preflight_value($booking, ['source_system', 'source_type', 'source'], ''));
    $ref = strtoupper(gov_preflight_order_reference($booking));
    return strpos($source, 'lab') !== false || strpos($ref, 'LAB-') === 0;
}

function gov_preflight_is_test_booking(array $booking): bool
{
    return gov_preflight_boolish($booking['is_test_booking'] ?? false);
}

function gov_preflight_never_submit_live(array $booking): bool
{
    return gov_preflight_boolish($booking['never_submit_live'] ?? false) || gov_preflight_is_test_booking($booking);
}

function gov_preflight_analyze_row(mysqli $db, array $booking, int $guardMinutes): array
{
    $preview = gov_build_edxeix_preview_payload($db, $booking);
    $mapping = $preview['_mapping_status'] ?? [];
    $bookingId = (int)($booking['id'] ?? 0);
    $mailIntake = $bookingId > 0
        ? gov_bridge_fetch_one(
            $db,
            'SELECT id, parse_status, safety_status, parsed_pickup_at, linked_booking_id, created_at, updated_at
             FROM bolt_mail_intake
             WHERE linked_booking_id = ?
             LIMIT 1',
            [$bookingId]
        )
        : null;

    $status = (string)gov_preflight_value($booking, ['order_status', 'status'], '');
    $startedAt = (string)gov_preflight_value($booking, ['started_at'], '');
    $endedAt = (string)gov_preflight_value($booking, ['ended_at'], '');
    $orderRef = gov_preflight_order_reference($booking);

    $driverMapped = !empty($mapping['driver_mapped']);
    $vehicleMapped = !empty($mapping['vehicle_mapped']);
    $futureGuard = !empty($mapping['passes_future_guard']);
    $terminal = gov_preflight_terminal_status($status);
    $labRow = gov_preflight_is_lab_row($booking);
    $testBooking = gov_preflight_is_test_booking($booking);
    $neverSubmitLive = gov_preflight_never_submit_live($booking);
    $source = (string)gov_preflight_value($booking, ['source_system', 'source_type', 'source'], '');
    $isBoltMail = strtolower($source) === 'bolt_mail' || $mailIntake !== null;

    $technicalBlockers = [];
    if (!$driverMapped) {
        $technicalBlockers[] = 'driver_not_mapped';
    }
    if (!$vehicleMapped) {
        $technicalBlockers[] = 'vehicle_not_mapped';
    }
    if (!$startedAt) {
        $technicalBlockers[] = 'missing_started_at';
    } elseif (!$futureGuard) {
        $technicalBlockers[] = 'started_at_not_future_guard_safe';
    }
    if ($terminal) {
        $technicalBlockers[] = 'terminal_order_status';
    }

    $liveBlockers = $technicalBlockers;
    if ($labRow) {
        $liveBlockers[] = 'lab_row_blocked';
    }
    if ($neverSubmitLive) {
        $liveBlockers[] = 'never_submit_live';
    }

    $mappingReady = $driverMapped && $vehicleMapped;
    $technicalPayloadValid = empty($technicalBlockers);
    $liveSubmissionAllowed = empty($liveBlockers);

    return [
        'id' => $booking['id'] ?? null,
        'order_reference' => $orderRef,
        'source_system' => $source,
        'source_flow' => $isBoltMail ? 'bolt_mail' : 'bolt_api_or_legacy',
        'is_bolt_mail' => $isBoltMail,
        'mail_intake' => $mailIntake ? [
            'id' => (int)$mailIntake['id'],
            'parse_status' => (string)$mailIntake['parse_status'],
            'safety_status' => (string)$mailIntake['safety_status'],
            'parsed_pickup_at' => (string)$mailIntake['parsed_pickup_at'],
            'linked_booking_id' => (int)$mailIntake['linked_booking_id'],
            'created_at' => (string)$mailIntake['created_at'],
            'updated_at' => (string)$mailIntake['updated_at'],
        ] : null,
        'status' => $status,
        'driver_uuid' => gov_preflight_value($booking, ['driver_external_id', 'external_driver_id'], ''),
        'driver_name' => gov_preflight_value($booking, ['driver_name', 'external_driver_name'], ''),
        'edxeix_driver_id' => $preview['driver'] ?? '',
        'vehicle_uuid' => gov_preflight_value($booking, ['vehicle_external_id', 'external_vehicle_id'], ''),
        'plate' => gov_preflight_value($booking, ['vehicle_plate', 'plate'], ''),
        'edxeix_vehicle_id' => $preview['vehicle'] ?? '',
        'boarding_point' => gov_preflight_value($booking, ['boarding_point', 'pickup_address'], ''),
        'disembark_point' => gov_preflight_value($booking, ['disembark_point', 'destination_address'], ''),
        'started_at' => $startedAt,
        'ended_at' => $endedAt,
        'price' => gov_preflight_value($booking, ['price'], '0'),
        'mapping_ready' => $mappingReady,
        'future_guard_minutes' => $guardMinutes,
        'future_guard_passed' => $futureGuard,
        'terminal_status' => $terminal,
        'is_lab_row' => $labRow,
        'is_test_booking' => $testBooking,
        'never_submit_live' => $neverSubmitLive,
        'technical_payload_valid' => $technicalPayloadValid,
        'dry_run_allowed' => $technicalPayloadValid,
        'live_submission_allowed' => $liveSubmissionAllowed,
        'submission_safe' => $liveSubmissionAllowed,
        'technical_blockers' => $technicalBlockers,
        'live_blockers' => $liveBlockers,
        'blockers' => $liveBlockers,
        'edxeix_payload_preview' => $preview,
    ];
}

try {
    $config = gov_bridge_load_config();
    if (!empty($config['app']['timezone'])) {
        date_default_timezone_set((string)$config['app']['timezone']);
    }

    $limit = gov_bridge_int_param('limit', 20, 1, 100);
    $guardMinutes = max(1, (int)($config['edxeix']['future_start_guard_minutes'] ?? 2));
    $db = gov_bridge_db();
    $bookings = gov_recent_rows($db, 'normalized_bookings', $limit);

    $rows = [];
    $mappingReadyCount = 0;
    $technicalValidCount = 0;
    $liveAllowedCount = 0;
    $labOrTestCount = 0;
    foreach ($bookings as $booking) {
        $row = gov_preflight_analyze_row($db, $booking, $guardMinutes);
        $rows[] = $row;
        if ($row['mapping_ready']) {
            $mappingReadyCount++;
        }
        if ($row['technical_payload_valid']) {
            $technicalValidCount++;
        }
        if ($row['live_submission_allowed']) {
            $liveAllowedCount++;
        }
        if ($row['is_lab_row'] || $row['is_test_booking'] || $row['never_submit_live']) {
            $labOrTestCount++;
        }
    }

    gov_bridge_json_response([
        'ok' => true,
        'script' => 'bolt_edxeix_preflight.php',
        'generated_at' => date('Y-m-d H:i:s'),
        'guard_minutes' => $guardMinutes,
        'summary' => [
            'rows_checked' => count($rows),
            'mapping_ready' => $mappingReadyCount,
            'technical_payload_valid' => $technicalValidCount,
            'lab_or_test_rows' => $labOrTestCount,
            'live_submission_allowed' => $liveAllowedCount,
            'submission_safe' => $liveAllowedCount,
            'blocked_from_live' => count($rows) - $liveAllowedCount,
        ],
        'rows' => $rows,
        'note' => 'Read-only preflight. guard_minutes is loaded from edxeix.future_start_guard_minutes. bolt_mail rows include mail_intake context when linked. technical_payload_valid means payload shape/mapping/time checks pass. live_submission_allowed is false for LAB/test/never_submit_live rows. No EDXEIX submission was performed.',
    ]);
} catch (Throwable $e) {
    gov_bridge_json_response([
        'ok' => false,
        'script' => 'bolt_edxeix_preflight.php',
        'error' => $e->getMessage(),
    ], 500);
}
