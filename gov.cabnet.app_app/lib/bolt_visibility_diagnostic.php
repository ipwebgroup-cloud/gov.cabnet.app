<?php
/**
 * Bolt API Visibility Diagnostic helpers.
 *
 * Purpose:
 * - Probe the existing Bolt order sync path in dry-run mode.
 * - Summarize what the Bolt Fleet orders endpoint exposes at a given moment.
 * - Optionally record sanitized snapshot summaries to private artifact storage.
 *
 * Safety:
 * - Does not submit to EDXEIX.
 * - Does not stage EDXEIX jobs.
 * - Calls gov_bolt_sync_orders(..., true) only, so the Bolt sync runs as dry-run.
 * - Never stores raw Bolt payloads, tokens, cookies, CSRF values, phone numbers, or emails.
 */

declare(strict_types=1);

require_once '/home/cabnet/gov.cabnet.app_app/lib/bolt_sync_lib.php';

if (!function_exists('gov_bolt_visibility_is_assoc')) {
    function gov_bolt_visibility_is_assoc(array $value): bool
    {
        if ($value === []) {
            return false;
        }
        return array_keys($value) !== range(0, count($value) - 1);
    }
}

if (!function_exists('gov_bolt_visibility_redact_string')) {
    function gov_bolt_visibility_redact_string(string $value): string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return $trimmed;
        }

        if (filter_var($trimmed, FILTER_VALIDATE_EMAIL)) {
            return '[redacted-email]';
        }

        $digits = preg_replace('/\D+/', '', $trimmed);
        if (is_string($digits) && strlen($digits) >= 8 && strlen($digits) <= 18 && preg_match('/^[+\d\s().-]+$/', $trimmed)) {
            return '[redacted-phone]';
        }

        if (strlen($trimmed) > 240) {
            return substr($trimmed, 0, 120) . '…[truncated:' . strlen($trimmed) . ']';
        }

        return $trimmed;
    }
}

if (!function_exists('gov_bolt_visibility_is_sensitive_key')) {
    function gov_bolt_visibility_is_sensitive_key(string $key): bool
    {
        $key = strtolower($key);
        foreach ([
            'token', 'secret', 'password', 'authorization', 'cookie', 'csrf', 'session',
            'email', 'phone', 'mobile', 'telephone', 'passenger', 'rider', 'customer',
            'client_name', 'full_name', 'first_name', 'last_name', 'name_surname'
        ] as $needle) {
            if (strpos($key, $needle) !== false) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('gov_bolt_visibility_sanitize_value')) {
    function gov_bolt_visibility_sanitize_value($value, int $depth = 0)
    {
        if ($depth > 8) {
            return '[max-depth]';
        }

        if (is_string($value)) {
            return gov_bolt_visibility_redact_string($value);
        }

        if (!is_array($value)) {
            return $value;
        }

        $out = [];
        foreach ($value as $key => $child) {
            $safeKey = is_string($key) ? $key : (string)$key;
            if (gov_bolt_visibility_is_sensitive_key($safeKey)) {
                $out[$safeKey] = '[redacted]';
                continue;
            }
            $out[$safeKey] = gov_bolt_visibility_sanitize_value($child, $depth + 1);
        }
        return $out;
    }
}

if (!function_exists('gov_bolt_visibility_pick_path')) {
    function gov_bolt_visibility_pick_path(array $row, string $path)
    {
        $cursor = $row;
        foreach (explode('.', $path) as $part) {
            if (!is_array($cursor) || !array_key_exists($part, $cursor)) {
                return null;
            }
            $cursor = $cursor[$part];
        }
        return $cursor;
    }
}

if (!function_exists('gov_bolt_visibility_pick_first')) {
    function gov_bolt_visibility_pick_first(array $row, array $paths)
    {
        foreach ($paths as $path) {
            $value = gov_bolt_visibility_pick_path($row, $path);
            if ($value !== null && $value !== '') {
                return $value;
            }
        }
        return null;
    }
}

if (!function_exists('gov_bolt_visibility_find_first_key')) {
    function gov_bolt_visibility_find_first_key($value, array $keys, int $depth = 0)
    {
        if ($depth > 6 || !is_array($value)) {
            return null;
        }

        foreach ($value as $key => $child) {
            $normalized = strtolower((string)$key);
            if (in_array($normalized, $keys, true) && $child !== null && $child !== '') {
                return $child;
            }
        }

        foreach ($value as $child) {
            if (is_array($child)) {
                $found = gov_bolt_visibility_find_first_key($child, $keys, $depth + 1);
                if ($found !== null && $found !== '') {
                    return $found;
                }
            }
        }

        return null;
    }
}

if (!function_exists('gov_bolt_visibility_is_order_like')) {
    function gov_bolt_visibility_is_order_like(array $row): bool
    {
        if (!gov_bolt_visibility_is_assoc($row)) {
            return false;
        }

        $keys = array_map('strtolower', array_keys($row));
        $hasId = (bool)array_intersect($keys, [
            'id', 'uuid', 'order_id', 'external_order_id', 'order_reference', 'reference', 'source_trip_reference'
        ]);
        $hasStatus = (bool)array_intersect($keys, ['status', 'state', 'order_status', 'status_name']);
        $hasTripSignal = (bool)array_intersect($keys, [
            'driver', 'driver_id', 'driver_uuid', 'vehicle', 'vehicle_id', 'vehicle_plate',
            'pickup', 'pickup_address', 'dropoff', 'destination', 'destination_address',
            'created_at', 'started_at', 'ended_at', 'scheduled_for'
        ]);

        return ($hasId && ($hasStatus || $hasTripSignal)) || ($hasStatus && $hasTripSignal);
    }
}

if (!function_exists('gov_bolt_visibility_extract_orders')) {
    function gov_bolt_visibility_extract_orders($payload, int $max = 20, array &$out = [], int $depth = 0): array
    {
        if ($depth > 8 || count($out) >= $max || !is_array($payload)) {
            return $out;
        }

        if (gov_bolt_visibility_is_order_like($payload)) {
            $out[] = $payload;
            return $out;
        }

        foreach ($payload as $child) {
            if (count($out) >= $max) {
                break;
            }
            if (is_array($child)) {
                gov_bolt_visibility_extract_orders($child, $max, $out, $depth + 1);
            }
        }

        return $out;
    }
}

if (!function_exists('gov_bolt_visibility_scalar')) {
    function gov_bolt_visibility_scalar($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_scalar($value)) {
            return gov_bolt_visibility_redact_string((string)$value);
        }
        return null;
    }
}

if (!function_exists('gov_bolt_visibility_order_summary')) {
    function gov_bolt_visibility_order_summary(array $order, array $watch = []): array
    {
        $id = gov_bolt_visibility_scalar(gov_bolt_visibility_pick_first($order, [
            'external_order_id', 'order_id', 'order.id', 'id', 'uuid', 'reference', 'order_reference', 'source_trip_reference'
        ]) ?? gov_bolt_visibility_find_first_key($order, ['external_order_id', 'order_id', 'uuid', 'reference']));

        $status = gov_bolt_visibility_scalar(gov_bolt_visibility_pick_first($order, [
            'status', 'state', 'order_status', 'status_name', 'order.status', 'order.state'
        ]) ?? gov_bolt_visibility_find_first_key($order, ['status', 'state', 'order_status', 'status_name']));

        $driverUuid = gov_bolt_visibility_scalar(gov_bolt_visibility_pick_first($order, [
            'driver.uuid', 'driver.id', 'driver_id', 'driver_uuid', 'driver.external_id'
        ]) ?? gov_bolt_visibility_find_first_key($order, ['driver_uuid', 'driver_id']));

        $vehiclePlate = gov_bolt_visibility_scalar(gov_bolt_visibility_pick_first($order, [
            'vehicle.plate', 'vehicle.license_plate', 'vehicle.registration_plate', 'vehicle.reg_number',
            'vehicle_plate', 'license_plate', 'registration_plate', 'plate'
        ]) ?? gov_bolt_visibility_find_first_key($order, ['vehicle_plate', 'license_plate', 'registration_plate', 'plate']));

        $vehicleId = gov_bolt_visibility_scalar(gov_bolt_visibility_pick_first($order, [
            'vehicle.id', 'vehicle.uuid', 'vehicle_id', 'vehicle_uuid'
        ]) ?? gov_bolt_visibility_find_first_key($order, ['vehicle_id', 'vehicle_uuid']));

        $scheduledFor = gov_bolt_visibility_scalar(gov_bolt_visibility_pick_first($order, [
            'scheduled_for', 'scheduled_at', 'pickup_time', 'pickup.time', 'requested_pickup_time', 'planned_start_time'
        ]) ?? gov_bolt_visibility_find_first_key($order, ['scheduled_for', 'scheduled_at', 'pickup_time', 'requested_pickup_time']));

        $createdAt = gov_bolt_visibility_scalar(gov_bolt_visibility_pick_first($order, [
            'created_at', 'order_created_at', 'created', 'order.created_at'
        ]) ?? gov_bolt_visibility_find_first_key($order, ['created_at', 'order_created_at']));

        $startedAt = gov_bolt_visibility_scalar(gov_bolt_visibility_pick_first($order, [
            'started_at', 'start_time', 'trip_started_at', 'actual_start_time'
        ]) ?? gov_bolt_visibility_find_first_key($order, ['started_at', 'trip_started_at']));

        $endedAt = gov_bolt_visibility_scalar(gov_bolt_visibility_pick_first($order, [
            'ended_at', 'finished_at', 'completed_at', 'trip_finished_at', 'actual_end_time'
        ]) ?? gov_bolt_visibility_find_first_key($order, ['ended_at', 'finished_at', 'completed_at', 'trip_finished_at']));

        $watchOrderId = trim((string)($watch['order_id'] ?? ''));
        $watchDriverUuid = strtolower(trim((string)($watch['driver_uuid'] ?? '')));
        $watchVehiclePlate = strtoupper(str_replace([' ', '-'], '', trim((string)($watch['vehicle_plate'] ?? ''))));
        $normalizedPlate = strtoupper(str_replace([' ', '-'], '', (string)$vehiclePlate));

        return [
            'external_order_id' => $id,
            'status' => $status,
            'driver_uuid_or_id' => $driverUuid,
            'vehicle_id' => $vehicleId,
            'vehicle_plate' => $vehiclePlate,
            'created_at' => $createdAt,
            'scheduled_for' => $scheduledFor,
            'started_at' => $startedAt,
            'ended_at' => $endedAt,
            'watch_match' => [
                'order_id' => $watchOrderId !== '' && $id !== null && stripos((string)$id, $watchOrderId) !== false,
                'driver_uuid' => $watchDriverUuid !== '' && $driverUuid !== null && strtolower((string)$driverUuid) === $watchDriverUuid,
                'vehicle_plate' => $watchVehiclePlate !== '' && $normalizedPlate !== '' && $normalizedPlate === $watchVehiclePlate,
            ],
            'top_level_keys' => array_slice(array_map('strval', array_keys($order)), 0, 18),
        ];
    }
}

if (!function_exists('gov_bolt_visibility_count_orders')) {
    function gov_bolt_visibility_count_orders(array $syncResult, array $extracted): int
    {
        if (isset($syncResult['orders_seen']) && is_numeric($syncResult['orders_seen'])) {
            return (int)$syncResult['orders_seen'];
        }
        foreach (['orders', 'items', 'data', 'results'] as $key) {
            if (isset($syncResult[$key]) && is_array($syncResult[$key]) && !gov_bolt_visibility_is_assoc($syncResult[$key])) {
                return count($syncResult[$key]);
            }
        }
        return count($extracted);
    }
}

if (!function_exists('gov_bolt_visibility_artifact_dir')) {
    function gov_bolt_visibility_artifact_dir(): string
    {
        gov_bridge_ensure_dirs();
        $paths = gov_bridge_paths();
        $dir = rtrim($paths['artifacts'], '/') . '/bolt-api-visibility';
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }
        return $dir;
    }
}

if (!function_exists('gov_bolt_visibility_snapshot_file')) {
    function gov_bolt_visibility_snapshot_file(?string $date = null): string
    {
        $date = $date ?: date('Y-m-d');
        $date = preg_replace('/[^0-9-]/', '', $date) ?: date('Y-m-d');
        return gov_bolt_visibility_artifact_dir() . '/' . $date . '.jsonl';
    }
}

if (!function_exists('gov_bolt_visibility_append_snapshot')) {
    function gov_bolt_visibility_append_snapshot(array $snapshot): ?string
    {
        $file = gov_bolt_visibility_snapshot_file();
        $line = json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        if (@file_put_contents($file, $line, FILE_APPEND | LOCK_EX) === false) {
            return null;
        }
        @chmod($file, 0640);
        return $file;
    }
}

if (!function_exists('gov_bolt_visibility_recent_snapshots')) {
    function gov_bolt_visibility_recent_snapshots(int $max = 50, ?string $date = null): array
    {
        $file = gov_bolt_visibility_snapshot_file($date);
        if (!is_file($file) || !is_readable($file)) {
            return [];
        }

        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!is_array($lines)) {
            return [];
        }
        $lines = array_slice($lines, -1 * max(1, min(300, $max)));
        $rows = [];
        foreach ($lines as $line) {
            $decoded = json_decode($line, true);
            if (is_array($decoded)) {
                $rows[] = $decoded;
            }
        }
        return $rows;
    }
}


if (!function_exists('gov_bolt_visibility_pick_existing_column')) {
    function gov_bolt_visibility_pick_existing_column(array $columns, array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            if (isset($columns[$candidate])) {
                return $candidate;
            }
        }
        return null;
    }
}

if (!function_exists('gov_bolt_visibility_normalize_plate')) {
    function gov_bolt_visibility_normalize_plate(?string $plate): string
    {
        return strtoupper(str_replace([' ', '-', '_'], '', trim((string)$plate)));
    }
}

if (!function_exists('gov_bolt_visibility_local_booking_summary')) {
    function gov_bolt_visibility_local_booking_summary(array $row, array $watch = []): array
    {
        $id = gov_bolt_visibility_scalar($row['external_order_id'] ?? null);
        $driver = gov_bolt_visibility_scalar($row['driver_external_id'] ?? null);
        $vehiclePlate = gov_bolt_visibility_scalar($row['vehicle_plate'] ?? null);
        $vehicleId = gov_bolt_visibility_scalar($row['vehicle_external_id'] ?? null);

        $watchOrderId = trim((string)($watch['order_id'] ?? ''));
        $watchDriverUuid = strtolower(trim((string)($watch['driver_uuid'] ?? '')));
        $watchVehiclePlate = gov_bolt_visibility_normalize_plate((string)($watch['vehicle_plate'] ?? ''));
        $normalizedPlate = gov_bolt_visibility_normalize_plate((string)$vehiclePlate);

        return [
            'local_id' => gov_bolt_visibility_scalar($row['local_id'] ?? null),
            'external_order_id' => $id,
            'status' => gov_bolt_visibility_scalar($row['status'] ?? null),
            'driver_external_id' => $driver,
            'vehicle_external_id' => $vehicleId,
            'vehicle_plate' => $vehiclePlate,
            'order_created_at' => gov_bolt_visibility_scalar($row['order_created_at'] ?? null),
            'scheduled_for' => gov_bolt_visibility_scalar($row['scheduled_for'] ?? null),
            'started_at' => gov_bolt_visibility_scalar($row['started_at'] ?? null),
            'ended_at' => gov_bolt_visibility_scalar($row['ended_at'] ?? null),
            'edxeix_ready' => gov_bolt_visibility_scalar($row['edxeix_ready'] ?? null),
            'edxeix_driver_id' => gov_bolt_visibility_scalar($row['edxeix_driver_id'] ?? null),
            'edxeix_vehicle_id' => gov_bolt_visibility_scalar($row['edxeix_vehicle_id'] ?? null),
            'watch_match' => [
                'order_id' => $watchOrderId !== '' && $id !== null && stripos((string)$id, $watchOrderId) !== false,
                'driver_uuid' => $watchDriverUuid !== '' && $driver !== null && strtolower((string)$driver) === $watchDriverUuid,
                'vehicle_plate' => $watchVehiclePlate !== '' && $normalizedPlate !== '' && $normalizedPlate === $watchVehiclePlate,
            ],
        ];
    }
}

if (!function_exists('gov_bolt_visibility_recent_local_bookings')) {
    function gov_bolt_visibility_recent_local_bookings(int $limit = 10, array $watch = []): array
    {
        $limit = max(1, min(50, $limit));
        try {
            $db = gov_bridge_db();
            if (!gov_bridge_table_exists($db, 'normalized_bookings')) {
                return [
                    'available' => false,
                    'rows' => [],
                    'columns_used' => [],
                    'note' => 'normalized_bookings table was not found.',
                ];
            }

            $columns = gov_bridge_table_columns($db, 'normalized_bookings');
            $aliases = [
                'local_id' => ['id', 'booking_id'],
                'external_order_id' => ['external_order_id', 'order_reference', 'source_trip_reference', 'external_reference'],
                'status' => ['status', 'booking_status', 'order_status', 'source_status'],
                'driver_external_id' => ['driver_external_id', 'driver_uuid', 'bolt_driver_uuid', 'driver_id'],
                'vehicle_external_id' => ['vehicle_external_id', 'vehicle_uuid', 'bolt_vehicle_uuid', 'vehicle_id'],
                'vehicle_plate' => ['vehicle_plate', 'license_plate', 'registration_plate', 'plate', 'vehicle_registration'],
                'order_created_at' => ['order_created_at', 'created_at', 'captured_at'],
                'scheduled_for' => ['scheduled_for', 'scheduled_at', 'pickup_time', 'requested_pickup_time', 'planned_start_time'],
                'started_at' => ['started_at', 'start_time', 'trip_started_at'],
                'ended_at' => ['ended_at', 'finished_at', 'completed_at', 'trip_finished_at'],
                'edxeix_ready' => ['edxeix_ready'],
                'edxeix_driver_id' => ['edxeix_driver_id'],
                'edxeix_vehicle_id' => ['edxeix_vehicle_id'],
            ];

            $select = [];
            $used = [];
            foreach ($aliases as $alias => $candidates) {
                $column = gov_bolt_visibility_pick_existing_column($columns, $candidates);
                if ($column !== null) {
                    $select[] = gov_bridge_quote_identifier($column) . ' AS ' . gov_bridge_quote_identifier($alias);
                    $used[$alias] = $column;
                }
            }

            if (!$select) {
                return [
                    'available' => true,
                    'rows' => [],
                    'columns_used' => [],
                    'note' => 'normalized_bookings exists, but no known summary columns were found.',
                ];
            }

            $where = [];
            $params = [];
            $sourceSystem = gov_bolt_visibility_pick_existing_column($columns, ['source_system', 'source_type']);
            if ($sourceSystem !== null) {
                $where[] = gov_bridge_quote_identifier($sourceSystem) . ' = ?';
                $params[] = 'bolt';
            }

            $orderBy = gov_bolt_visibility_pick_existing_column($columns, ['id', 'updated_at', 'created_at', 'order_created_at', 'started_at']);
            $sql = 'SELECT ' . implode(', ', $select) . ' FROM `normalized_bookings`';
            if ($where) {
                $sql .= ' WHERE ' . implode(' AND ', $where);
            }
            if ($orderBy !== null) {
                $sql .= ' ORDER BY ' . gov_bridge_quote_identifier($orderBy) . ' DESC';
            }
            $sql .= ' LIMIT ' . $limit;

            $rows = gov_bridge_fetch_all($db, $sql, $params);
            $summaries = [];
            foreach ($rows as $row) {
                $summaries[] = gov_bolt_visibility_local_booking_summary($row, $watch);
            }

            return [
                'available' => true,
                'rows' => $summaries,
                'columns_used' => $used,
                'source_filter_column' => $sourceSystem,
                'order_by_column' => $orderBy,
                'note' => 'Read-only summary from normalized_bookings after the dry-run Bolt probe.',
            ];
        } catch (Throwable $e) {
            return [
                'available' => false,
                'rows' => [],
                'columns_used' => [],
                'note' => 'Local normalized booking read failed: ' . $e->getMessage(),
            ];
        }
    }
}

if (!function_exists('gov_bolt_visibility_merge_watch_matches')) {
    function gov_bolt_visibility_merge_watch_matches(array $base, array $rows): array
    {
        foreach ($rows as $row) {
            $matches = $row['watch_match'] ?? [];
            foreach (['order_id', 'driver_uuid', 'vehicle_plate'] as $key) {
                $base[$key] = !empty($base[$key]) || !empty($matches[$key]);
            }
        }
        return $base;
    }
}

if (!function_exists('gov_bolt_visibility_build_snapshot')) {
    function gov_bolt_visibility_build_snapshot(array $options = []): array
    {
        if (!function_exists('gov_bolt_sync_orders')) {
            throw new RuntimeException('Required function gov_bolt_sync_orders() is unavailable. Confirm bolt_sync_lib.php is current.');
        }

        $hoursBack = max(1, min(2160, (int)($options['hours_back'] ?? 24)));
        $sampleLimit = max(1, min(50, (int)($options['sample_limit'] ?? 20)));
        $record = !empty($options['record']);
        $label = trim((string)($options['label'] ?? ''));
        $watch = [
            'order_id' => trim((string)($options['watch_order_id'] ?? '')),
            'driver_uuid' => trim((string)($options['watch_driver_uuid'] ?? '')),
            'vehicle_plate' => trim((string)($options['watch_vehicle_plate'] ?? '')),
        ];

        $started = microtime(true);
        $config = gov_bridge_load_config();
        $syncResult = gov_bolt_sync_orders($hoursBack, true);
        $durationMs = (int)round((microtime(true) - $started) * 1000);

        $extractedOrders = gov_bolt_visibility_extract_orders($syncResult, $sampleLimit);
        $samples = [];
        $watchMatches = [
            'order_id' => false,
            'driver_uuid' => false,
            'vehicle_plate' => false,
        ];

        foreach ($extractedOrders as $order) {
            $summary = gov_bolt_visibility_order_summary($order, $watch);
            foreach ($summary['watch_match'] as $key => $matched) {
                $watchMatches[$key] = $watchMatches[$key] || (bool)$matched;
            }
            $samples[] = $summary;
        }

        $statusCounts = [];
        foreach ($samples as $sample) {
            $status = strtoupper((string)($sample['status'] ?? 'UNKNOWN'));
            $status = $status !== '' ? $status : 'UNKNOWN';
            $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;
        }
        ksort($statusCounts);

        $localBookings = gov_bolt_visibility_recent_local_bookings(min(10, $sampleLimit), $watch);
        $localRows = is_array($localBookings['rows'] ?? null) ? $localBookings['rows'] : [];
        $localStatusCounts = [];
        foreach ($localRows as $localRow) {
            $status = strtoupper((string)($localRow['status'] ?? 'UNKNOWN'));
            $status = $status !== '' ? $status : 'UNKNOWN';
            $localStatusCounts[$status] = ($localStatusCounts[$status] ?? 0) + 1;
        }
        ksort($localStatusCounts);
        $watchMatches = gov_bolt_visibility_merge_watch_matches($watchMatches, $localRows);

        $safeSyncKeys = [];
        foreach (array_keys($syncResult) as $key) {
            if (!gov_bolt_visibility_is_sensitive_key((string)$key)) {
                $safeSyncKeys[] = (string)$key;
            }
        }

        $sampleExtractionNote = 'Order samples are extracted only when the dry-run sync result exposes order-like arrays.';
        if (gov_bolt_visibility_count_orders($syncResult, $extractedOrders) > 0 && count($samples) === 0) {
            $sampleExtractionNote = 'The dry-run sync result reported orders_seen > 0 but did not expose order-like arrays. Use the local normalized bookings summary below to inspect the last imported Bolt rows without printing raw payloads.';
        }

        $snapshot = [
            'ok' => true,
            'script' => 'bolt_api_visibility_diagnostic',
            'diagnostic_version' => '1.1.0',
            'safety' => [
                'edxeix_live_submission' => 'not_used',
                'bolt_sync_mode' => 'dry_run_only',
                'db_write_intent' => 'none_from_diagnostic',
                'stored_payload_type' => $record ? 'sanitized_summary_jsonl' : 'not_recorded',
            ],
            'captured_at' => date('c'),
            'probe_id' => bin2hex(random_bytes(6)),
            'label' => $label !== '' ? $label : null,
            'hours_back' => $hoursBack,
            'duration_ms' => $durationMs,
            'bolt' => [
                'api_base' => $config['bolt']['api_base'] ?? null,
                'company_id' => $config['bolt']['company_id'] ?? null,
            ],
            'visibility' => [
                'orders_seen' => gov_bolt_visibility_count_orders($syncResult, $extractedOrders),
                'sample_count' => count($samples),
                'local_recent_count' => count($localRows),
                'status_counts_from_samples' => $statusCounts,
                'status_counts_from_local_recent' => $localStatusCounts,
                'watch' => [
                    'driver_uuid_set' => $watch['driver_uuid'] !== '',
                    'vehicle_plate_set' => $watch['vehicle_plate'] !== '',
                    'order_id_set' => $watch['order_id'] !== '',
                    'matches' => $watchMatches,
                ],
            ],
            'order_samples' => $samples,
            'local_recent_bookings' => $localRows,
            'local_recent_bookings_meta' => [
                'available' => (bool)($localBookings['available'] ?? false),
                'columns_used' => $localBookings['columns_used'] ?? [],
                'source_filter_column' => $localBookings['source_filter_column'] ?? null,
                'order_by_column' => $localBookings['order_by_column'] ?? null,
                'note' => $localBookings['note'] ?? null,
            ],
            'sync_result_summary' => [
                'safe_top_level_keys' => $safeSyncKeys,
                'orders_seen' => isset($syncResult['orders_seen']) ? (int)$syncResult['orders_seen'] : null,
                'inserted' => isset($syncResult['inserted']) ? (int)$syncResult['inserted'] : null,
                'updated' => isset($syncResult['updated']) ? (int)$syncResult['updated'] : null,
                'skipped' => isset($syncResult['skipped']) ? (int)$syncResult['skipped'] : null,
                'dry_run' => $syncResult['dry_run'] ?? true,
                'sample_extraction_note' => $sampleExtractionNote,
                'note' => 'Raw sync result is intentionally not stored or printed by this diagnostic.',
            ],
        ];

        if ($record) {
            $path = gov_bolt_visibility_append_snapshot($snapshot);
            $snapshot['recorded'] = $path !== null;
            $snapshot['recorded_file'] = $path !== null ? $path : null;
        } else {
            $snapshot['recorded'] = false;
            $snapshot['recorded_file'] = null;
        }

        return $snapshot;
    }
}
