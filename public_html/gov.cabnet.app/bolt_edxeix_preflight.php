<?php
/**
 * gov.cabnet.app Bolt → EDXEIX preflight report.
 *
 * Read-only diagnostic endpoint.
 * - Does not call EDXEIX.
 * - Does not create submission jobs.
 * - Separates mapping readiness from live submission safety.
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

function gov_preflight_analyze_row(mysqli $db, array $booking): array
{
    $preview = gov_build_edxeix_preview_payload($db, $booking);
    $mapping = $preview['_mapping_status'] ?? [];

    $status = (string)gov_preflight_value($booking, ['order_status', 'status'], '');
    $startedAt = (string)gov_preflight_value($booking, ['started_at'], '');
    $endedAt = (string)gov_preflight_value($booking, ['ended_at'], '');
    $orderRef = (string)gov_preflight_value($booking, ['order_reference', 'external_order_id', 'external_reference', 'source_trip_reference'], '');

    $driverMapped = !empty($mapping['driver_mapped']);
    $vehicleMapped = !empty($mapping['vehicle_mapped']);
    $futureGuard = !empty($mapping['passes_future_guard']);
    $terminal = gov_preflight_terminal_status($status);

    $blockers = [];
    if (!$driverMapped) {
        $blockers[] = 'driver_not_mapped';
    }
    if (!$vehicleMapped) {
        $blockers[] = 'vehicle_not_mapped';
    }
    if (!$startedAt) {
        $blockers[] = 'missing_started_at';
    } elseif (!$futureGuard) {
        $blockers[] = 'started_at_not_30_min_future';
    }
    if ($terminal) {
        $blockers[] = 'terminal_order_status';
    }

    $mappingReady = $driverMapped && $vehicleMapped;
    $submissionSafe = $mappingReady && $futureGuard && !$terminal;

    return [
        'id' => $booking['id'] ?? null,
        'order_reference' => $orderRef,
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
        'future_guard_passed' => $futureGuard,
        'terminal_status' => $terminal,
        'submission_safe' => $submissionSafe,
        'blockers' => $blockers,
        'edxeix_payload_preview' => $preview,
    ];
}

try {
    $config = gov_bridge_load_config();
    if (!empty($config['app']['timezone'])) {
        date_default_timezone_set((string)$config['app']['timezone']);
    }

    $limit = gov_bridge_int_param('limit', 20, 1, 100);
    $db = gov_bridge_db();
    $bookings = gov_recent_rows($db, 'normalized_bookings', $limit);

    $rows = [];
    $mappingReadyCount = 0;
    $submissionSafeCount = 0;
    foreach ($bookings as $booking) {
        $row = gov_preflight_analyze_row($db, $booking);
        $rows[] = $row;
        if ($row['mapping_ready']) {
            $mappingReadyCount++;
        }
        if ($row['submission_safe']) {
            $submissionSafeCount++;
        }
    }

    gov_bridge_json_response([
        'ok' => true,
        'script' => 'bolt_edxeix_preflight.php',
        'generated_at' => date('Y-m-d H:i:s'),
        'guard_minutes' => (int)($config['edxeix']['future_start_guard_minutes'] ?? 30),
        'summary' => [
            'rows_checked' => count($rows),
            'mapping_ready' => $mappingReadyCount,
            'submission_safe' => $submissionSafeCount,
            'blocked' => count($rows) - $submissionSafeCount,
        ],
        'rows' => $rows,
        'note' => 'Read-only preflight. No EDXEIX submission was performed. A row is submission_safe only when mappings exist, status is not terminal, and started_at is at least the configured future guard window away.',
    ]);
} catch (Throwable $e) {
    gov_bridge_json_response([
        'ok' => false,
        'script' => 'bolt_edxeix_preflight.php',
        'error' => $e->getMessage(),
    ], 500);
}
