<?php
/**
 * gov.cabnet.app — Local dry-run future booking test factory.
 *
 * SAFETY:
 * - Creates only LAB/LOCAL normalized_bookings rows.
 * - Does not call Bolt.
 * - Does not call EDXEIX.
 * - Marks rows as dry-run-only when the optional safety columns exist.
 * - Uses order references beginning with LAB- so existing staging/worker gates
 *   keep the row blocked unless allow_lab=1 is explicitly used for local tests.
 */

declare(strict_types=1);

require_once '/home/cabnet/gov.cabnet.app_app/lib/bolt_sync_lib.php';

if (!class_exists('GovTestBookingFactory')) {
    final class GovTestBookingFactory
    {
        public const SOURCE_SYSTEM = 'lab_local_test';
        public const ORDER_PREFIX = 'LAB-LOCAL-FUTURE';
        public const DEFAULT_MINUTES_AHEAD = 75;
        public const DEFAULT_DURATION_MINUTES = 20;

        public static function preview(mysqli $db, int $minutesAhead = self::DEFAULT_MINUTES_AHEAD): array
        {
            $minutesAhead = self::sanitizeMinutesAhead($minutesAhead);
            $driver = self::firstMappedDriver($db);
            $vehicle = self::firstMappedVehicle($db);

            $errors = [];
            if (!$driver) {
                $errors[] = 'No active mapped driver was found. Run Bolt reference sync and map at least one driver to an EDXEIX driver ID.';
            }
            if (!$vehicle) {
                $errors[] = 'No active mapped vehicle was found. Run Bolt reference sync and map at least one vehicle to an EDXEIX vehicle ID.';
            }

            $row = null;
            if (!$errors) {
                $row = self::buildRow($driver, $vehicle, $minutesAhead);
            }

            return [
                'ok' => !$errors,
                'mode' => 'preview_only',
                'minutes_ahead' => $minutesAhead,
                'driver' => self::publicDriver($driver),
                'vehicle' => self::publicVehicle($vehicle),
                'errors' => $errors,
                'row_preview' => $row,
                'safety' => self::safetyStatement(),
            ];
        }

        public static function create(mysqli $db, int $minutesAhead = self::DEFAULT_MINUTES_AHEAD): array
        {
            $preview = self::preview($db, $minutesAhead);
            if (!$preview['ok'] || empty($preview['row_preview']) || !is_array($preview['row_preview'])) {
                return [
                    'ok' => false,
                    'action' => 'blocked',
                    'errors' => $preview['errors'] ?? ['Unable to build local test booking preview.'],
                    'preview' => $preview,
                ];
            }

            if (!gov_bridge_table_exists($db, 'normalized_bookings')) {
                return [
                    'ok' => false,
                    'action' => 'blocked',
                    'errors' => ['normalized_bookings table does not exist.'],
                    'preview' => $preview,
                ];
            }

            $row = $preview['row_preview'];
            $row['dedupe_hash'] = gov_bolt_normalized_booking_dedupe_hash($row);
            $row['normalized_payload_json'] = gov_bridge_json_encode_db([
                'factory' => 'GovTestBookingFactory',
                'created_for' => 'local_dry_run_future_booking_test',
                'never_submit_live' => true,
                'source_system' => self::SOURCE_SYSTEM,
                'order_reference' => $row['order_reference'] ?? null,
            ]);
            $row['raw_payload_json'] = gov_bridge_json_encode_db([
                'source' => 'local_test_factory',
                'note' => 'Synthetic local row. Not received from Bolt and not eligible for live EDXEIX submission.',
            ]);

            $id = gov_bridge_insert_row($db, 'normalized_bookings', $row);

            return [
                'ok' => true,
                'action' => 'created_local_test_booking',
                'normalized_booking_id' => $id,
                'order_reference' => $row['order_reference'] ?? '',
                'started_at' => $row['started_at'] ?? '',
                'ended_at' => $row['ended_at'] ?? '',
                'driver' => $preview['driver'],
                'vehicle' => $preview['vehicle'],
                'row' => array_merge($row, ['id' => $id]),
                'safety' => self::safetyStatement(),
                'next_urls' => [
                    'preflight_json' => '/bolt_edxeix_preflight.php?limit=30',
                    'stage_dry_run_blocked_lab' => '/bolt_stage_edxeix_jobs.php?limit=30',
                    'stage_dry_run_allow_lab_preview' => '/bolt_stage_edxeix_jobs.php?limit=30&allow_lab=1',
                    'stage_local_job_allow_lab' => '/bolt_stage_edxeix_jobs.php?limit=30&create=1&allow_lab=1',
                    'worker_dry_run_preview' => '/bolt_submission_worker.php?limit=30&allow_lab=1',
                    'worker_record_local_attempt' => '/bolt_submission_worker.php?limit=30&record=1&allow_lab=1',
                    'readiness' => '/ops/readiness.php',
                ],
            ];
        }

        private static function sanitizeMinutesAhead(int $minutesAhead): int
        {
            if ($minutesAhead < 35) {
                return 35;
            }
            if ($minutesAhead > 10080) {
                return 10080;
            }
            return $minutesAhead;
        }

        private static function firstMappedDriver(mysqli $db): ?array
        {
            if (!gov_bridge_table_exists($db, 'mapping_drivers')) {
                return null;
            }
            $columns = gov_bridge_table_columns($db, 'mapping_drivers');
            if (!isset($columns['external_driver_id'], $columns['edxeix_driver_id'])) {
                return null;
            }

            $where = [
                "external_driver_id IS NOT NULL",
                "external_driver_id <> ''",
                "edxeix_driver_id IS NOT NULL",
                "CAST(edxeix_driver_id AS CHAR) <> ''",
                "CAST(edxeix_driver_id AS CHAR) <> '0'",
            ];
            if (isset($columns['is_active'])) {
                $where[] = 'is_active = 1';
            }
            $order = isset($columns['updated_at']) ? 'updated_at DESC, id DESC' : 'id DESC';

            return gov_bridge_fetch_one(
                $db,
                'SELECT * FROM mapping_drivers WHERE ' . implode(' AND ', $where) . ' ORDER BY ' . $order . ' LIMIT 1'
            );
        }

        private static function firstMappedVehicle(mysqli $db): ?array
        {
            if (!gov_bridge_table_exists($db, 'mapping_vehicles')) {
                return null;
            }
            $columns = gov_bridge_table_columns($db, 'mapping_vehicles');
            if (!isset($columns['external_vehicle_id'], $columns['edxeix_vehicle_id'])) {
                return null;
            }

            $where = [
                "external_vehicle_id IS NOT NULL",
                "external_vehicle_id <> ''",
                "edxeix_vehicle_id IS NOT NULL",
                "CAST(edxeix_vehicle_id AS CHAR) <> ''",
                "CAST(edxeix_vehicle_id AS CHAR) <> '0'",
            ];
            if (isset($columns['plate'])) {
                $where[] = "plate IS NOT NULL";
                $where[] = "plate <> ''";
            }
            if (isset($columns['is_active'])) {
                $where[] = 'is_active = 1';
            }
            $order = isset($columns['updated_at']) ? 'updated_at DESC, id DESC' : 'id DESC';

            return gov_bridge_fetch_one(
                $db,
                'SELECT * FROM mapping_vehicles WHERE ' . implode(' AND ', $where) . ' ORDER BY ' . $order . ' LIMIT 1'
            );
        }

        private static function buildRow(array $driver, array $vehicle, int $minutesAhead): array
        {
            $now = new DateTimeImmutable('now');
            $start = $now->modify('+' . $minutesAhead . ' minutes');
            $end = $start->modify('+' . self::DEFAULT_DURATION_MINUTES . ' minutes');
            $stamp = $now->format('YmdHis');
            $orderRef = self::ORDER_PREFIX . '-' . $stamp . '-' . random_int(1000, 9999);

            $driverName = (string)($driver['external_driver_name'] ?? $driver['driver_name'] ?? 'LAB mapped driver');
            $vehiclePlate = (string)($vehicle['plate'] ?? $vehicle['active_vehicle_plate'] ?? 'LAB-PLATE');
            $vehicleModel = (string)($vehicle['external_vehicle_name'] ?? $vehicle['vehicle_model'] ?? 'LAB mapped vehicle');

            return [
                'source' => self::SOURCE_SYSTEM,
                'source_system' => self::SOURCE_SYSTEM,
                'source_type' => self::SOURCE_SYSTEM,
                'source_trip_id' => $orderRef,
                'source_booking_id' => $orderRef,
                'external_order_id' => $orderRef,
                'order_reference' => $orderRef,
                'source_trip_reference' => $orderRef,
                'status' => 'accepted',
                'order_status' => 'accepted',
                'customer_type' => 'private',
                'customer_name' => 'LAB Test Passenger',
                'passenger_name' => 'LAB Test Passenger',
                'lessee_name' => 'LAB Test Passenger',
                'customer_vat_number' => null,
                'customer_representative' => null,
                'driver_external_id' => (string)$driver['external_driver_id'],
                'driver_name' => $driverName,
                'driver_phone' => (string)($driver['driver_phone'] ?? ''),
                'vehicle_external_id' => (string)$vehicle['external_vehicle_id'],
                'vehicle_plate' => $vehiclePlate,
                'vehicle_model' => $vehicleModel,
                'starting_point_key' => 'default',
                'boarding_point' => 'LAB TEST PICKUP — dry-run only, do not submit live',
                'pickup_address' => 'LAB TEST PICKUP — dry-run only, do not submit live',
                'coordinates' => null,
                'disembark_point' => 'LAB TEST DESTINATION — dry-run only, do not submit live',
                'destination_address' => 'LAB TEST DESTINATION — dry-run only, do not submit live',
                'drafted_at' => $now->format('Y-m-d H:i:s'),
                'order_created_at' => $now->format('Y-m-d H:i:s'),
                'started_at' => $start->format('Y-m-d H:i:s'),
                'ended_at' => $end->format('Y-m-d H:i:s'),
                'price' => '0.00',
                'currency' => 'EUR',
                'broker_key' => 'lab_local_test',
                'notes' => 'LOCAL DRY-RUN TEST BOOKING. Synthetic row. Never submit live to EDXEIX.',
                'is_scheduled' => '1',
                'edxeix_ready' => '0',
                'is_test_booking' => '1',
                'never_submit_live' => '1',
                'live_submit_block_reason' => 'Synthetic LAB/local dry-run row created by GovTestBookingFactory.',
                'created_at' => $now->format('Y-m-d H:i:s'),
                'updated_at' => $now->format('Y-m-d H:i:s'),
            ];
        }

        private static function publicDriver(?array $driver): ?array
        {
            if (!$driver) {
                return null;
            }
            return [
                'mapping_id' => $driver['id'] ?? null,
                'external_driver_id' => $driver['external_driver_id'] ?? null,
                'external_driver_name' => $driver['external_driver_name'] ?? $driver['driver_name'] ?? null,
                'edxeix_driver_id' => $driver['edxeix_driver_id'] ?? null,
                'active_vehicle_plate' => $driver['active_vehicle_plate'] ?? null,
            ];
        }

        private static function publicVehicle(?array $vehicle): ?array
        {
            if (!$vehicle) {
                return null;
            }
            return [
                'mapping_id' => $vehicle['id'] ?? null,
                'external_vehicle_id' => $vehicle['external_vehicle_id'] ?? null,
                'plate' => $vehicle['plate'] ?? null,
                'vehicle_model' => $vehicle['vehicle_model'] ?? $vehicle['external_vehicle_name'] ?? null,
                'edxeix_vehicle_id' => $vehicle['edxeix_vehicle_id'] ?? null,
            ];
        }

        private static function safetyStatement(): array
        {
            return [
                'calls_bolt' => false,
                'calls_edxeix' => false,
                'creates_normalized_booking_only' => true,
                'source_system' => self::SOURCE_SYSTEM,
                'order_reference_prefix' => self::ORDER_PREFIX,
                'existing_stage_gate' => 'LAB rows require allow_lab=1 and are for dry-run/local queue tests only.',
                'live_submission' => 'Never submit rows from this factory to EDXEIX live.',
            ];
        }
    }
}
