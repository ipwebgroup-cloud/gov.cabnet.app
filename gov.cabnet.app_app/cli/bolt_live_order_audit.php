<?php
/**
 * gov.cabnet.app — Bolt live/raw order audit
 *
 * Purpose:
 * - Inspect recent Bolt raw payloads already collected by sync_bolt.php.
 * - Optionally run the canonical Bolt sync function in dry-run mode first.
 * - Print sanitized order state/timestamp fields needed to prove whether Bolt
 *   exposes pickup state before trip finish.
 *
 * Safety:
 * - Does not call EDXEIX.
 * - Does not create submission_jobs or submission_attempts.
 * - Does not issue AADE/myDATA receipts.
 * - Does not print tokens, credentials, cookies, full raw payloads, or mobile numbers.
 */

declare(strict_types=1);

require_once __DIR__ . '/../lib/bolt_sync_lib.php';

function audit_arg(string $name, string $default): string
{
    global $argv;
    foreach (($argv ?? []) as $arg) {
        if ($arg === '--' . $name) {
            return '1';
        }
        if (strpos($arg, '--' . $name . '=') === 0) {
            return substr($arg, strlen('--' . $name . '='));
        }
    }
    return $default;
}

function audit_bool(string $name, bool $default = false): bool
{
    $value = strtolower(audit_arg($name, $default ? '1' : '0'));
    return in_array($value, ['1', 'true', 'yes', 'on'], true);
}

function audit_int(string $name, int $default, int $min, int $max): int
{
    $raw = audit_arg($name, (string)$default);
    $value = filter_var($raw, FILTER_VALIDATE_INT, ['options' => ['default' => $default]]);
    return max($min, min($max, (int)$value));
}

function audit_first_text(array $values, string $fallback = ''): string
{
    foreach ($values as $value) {
        if ($value === null || is_array($value) || is_object($value)) {
            continue;
        }
        $text = trim((string)$value);
        if ($text !== '') {
            return preg_replace('/\s+/u', ' ', $text) ?: $text;
        }
    }
    return $fallback;
}

function audit_nested_text(array $row, array $paths): string
{
    foreach ($paths as $path) {
        $value = $row;
        foreach ((array)$path as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) {
                $value = null;
                break;
            }
            $value = $value[$part];
        }
        if ($value !== null && !is_array($value) && !is_object($value)) {
            $text = trim((string)$value);
            if ($text !== '') {
                return preg_replace('/\s+/u', ' ', $text) ?: $text;
            }
        }
    }
    return '';
}

function audit_timestamp($value, DateTimeZone $tz): ?string
{
    if ($value === null || $value === '') {
        return null;
    }
    try {
        if (is_numeric($value)) {
            $n = (int)$value;
            if ($n > 20000000000) { // milliseconds
                $n = (int)floor($n / 1000);
            }
            return (new DateTimeImmutable('@' . $n))->setTimezone($tz)->format('Y-m-d H:i:s T');
        }
        return (new DateTimeImmutable((string)$value, $tz))->setTimezone($tz)->format('Y-m-d H:i:s T');
    } catch (Throwable) {
        return (string)$value;
    }
}

function audit_decode_json(?string $json): ?array
{
    if ($json === null || trim($json) === '') {
        return null;
    }
    $decoded = json_decode($json, true);
    return is_array($decoded) ? $decoded : null;
}

function audit_collect_orders($node, array &$orders, int $depth = 0): void
{
    if (!is_array($node) || $depth > 8) {
        return;
    }

    $keys = array_change_key_case(array_keys($node), CASE_LOWER);
    $keyMap = array_flip($keys);
    $looksLikeOrder = false;
    foreach ([
        'order_reference', 'order_ref', 'order_id', 'order_status', 'status',
        'order_pickup_timestamp', 'order_drop_off_timestamp', 'order_finished_timestamp',
        'driver_name', 'vehicle_plate', 'ride_price', 'pickup_address', 'destination_address'
    ] as $needle) {
        if (isset($keyMap[$needle])) {
            $looksLikeOrder = true;
            break;
        }
    }

    if ($looksLikeOrder) {
        $orders[] = $node;
        return;
    }

    foreach ($node as $child) {
        audit_collect_orders($child, $orders, $depth + 1);
    }
}

function audit_order_summary(array $order, DateTimeZone $tz): array
{
    $driverName = audit_nested_text($order, [
        'driver_name', 'driverName', ['driver', 'name'], ['driver', 'full_name'], ['driver', 'driver_name'],
    ]);
    $vehiclePlate = audit_nested_text($order, [
        'vehicle_plate', 'vehiclePlate', 'vehicle_license_plate', 'license_plate', 'licence_plate', ['vehicle', 'plate'], ['vehicle', 'license_plate'], ['vehicle', 'licence_plate'],
    ]);

    $pickup = audit_nested_text($order, [
        'pickup_address', 'pickup', 'boarding_point', ['pickup', 'address'], ['pickup', 'name'], ['pickup_location', 'address'], ['route', 'pickup'],
    ]);
    $destination = audit_nested_text($order, [
        'destination_address', 'dropoff_address', 'drop_off_address', 'destination', 'disembark_point', ['destination', 'address'], ['dropoff', 'address'], ['drop_off', 'address'], ['route', 'destination'],
    ]);

    return [
        'order_reference' => audit_nested_text($order, ['order_reference', 'order_ref', 'reference', 'order_id', 'id', 'uuid']),
        'driver_name' => $driverName,
        'vehicle_plate' => $vehiclePlate,
        'order_status' => audit_nested_text($order, ['order_status', 'status', 'state', 'state_display']),
        'payment_method' => audit_nested_text($order, ['payment_method', 'paymentMethod', ['payment', 'method']]),
        'order_accepted_timestamp' => audit_timestamp($order['order_accepted_timestamp'] ?? $order['accepted_at'] ?? null, $tz),
        'order_pickup_timestamp' => audit_timestamp($order['order_pickup_timestamp'] ?? $order['pickup_timestamp'] ?? $order['picked_up_at'] ?? null, $tz),
        'order_drop_off_timestamp' => audit_timestamp($order['order_drop_off_timestamp'] ?? $order['dropoff_timestamp'] ?? $order['drop_off_timestamp'] ?? null, $tz),
        'order_finished_timestamp' => audit_timestamp($order['order_finished_timestamp'] ?? $order['finished_at'] ?? null, $tz),
        'ride_price' => audit_nested_text($order, ['ride_price', 'price', 'total_price', 'amount', ['order_price', 'ride_price'], ['order_price', 'total_price'], ['price', 'amount'], ['ride_price', 'amount']]),
        'pickup' => $pickup,
        'destination' => $destination,
    ];
}

function audit_submission_counts(mysqli $db): array
{
    $out = ['submission_jobs' => null, 'submission_attempts' => null];
    foreach (array_keys($out) as $table) {
        try {
            if (!gov_bridge_table_exists($db, $table)) {
                continue;
            }
            $row = gov_bridge_fetch_one($db, 'SELECT COUNT(*) AS c FROM ' . gov_bridge_quote_identifier($table));
            $out[$table] = (int)($row['c'] ?? 0);
        } catch (Throwable $e) {
            $out[$table] = 'unavailable: ' . $e->getMessage();
        }
    }
    return $out;
}

function audit_payload_column(array $columns): ?string
{
    foreach (['payload_json', 'raw_payload_json', 'response_json', 'body_json', 'payload', 'raw_payload'] as $candidate) {
        if (isset($columns[$candidate])) {
            return $candidate;
        }
    }
    return null;
}

function audit_recent_raw_payload_orders(mysqli $db, int $minutes, int $limit, DateTimeZone $tz): array
{
    if (!gov_bridge_table_exists($db, 'bolt_raw_payloads')) {
        return ['available' => false, 'reason' => 'bolt_raw_payloads_table_missing', 'orders' => []];
    }

    $columns = gov_bridge_table_columns($db, 'bolt_raw_payloads');
    $payloadColumn = audit_payload_column($columns);
    if ($payloadColumn === null) {
        return ['available' => false, 'reason' => 'payload_json_column_missing', 'orders' => []];
    }

    $select = ['id', '`' . $payloadColumn . '` AS payload_json'];
    foreach (['endpoint', 'external_reference', 'created_at', 'captured_at', 'received_at'] as $column) {
        if (isset($columns[$column])) {
            $select[] = '`' . $column . '`';
        }
    }

    $timeColumn = isset($columns['created_at']) ? 'created_at' : (isset($columns['captured_at']) ? 'captured_at' : null);
    $where = '';
    $params = [];
    if ($timeColumn !== null) {
        $where = ' WHERE `' . $timeColumn . '` >= DATE_SUB(NOW(), INTERVAL ? MINUTE)';
        $params[] = (string)$minutes;
    }
    if (isset($columns['endpoint'])) {
        $where .= ($where === '' ? ' WHERE ' : ' AND ') . '`endpoint` LIKE ?';
        $params[] = '%getFleetOrders%';
    }

    $sql = 'SELECT ' . implode(', ', $select) . ' FROM bolt_raw_payloads' . $where . ' ORDER BY id DESC LIMIT ' . max(1, min(500, $limit));
    $rows = gov_bridge_fetch_all($db, $sql, $params);
    $orders = [];

    foreach ($rows as $row) {
        $decoded = audit_decode_json($row['payload_json'] ?? null);
        if (!is_array($decoded)) {
            continue;
        }
        $found = [];
        audit_collect_orders($decoded, $found);
        foreach ($found as $order) {
            $summary = audit_order_summary($order, $tz);
            $summary['raw_payload_id'] = (int)($row['id'] ?? 0);
            $summary['raw_payload_captured_at'] = $row['created_at'] ?? $row['captured_at'] ?? $row['received_at'] ?? null;
            $orders[] = $summary;
        }
    }

    return ['available' => true, 'rows_scanned' => count($rows), 'orders' => $orders];
}

function audit_match_mail_intake(mysqli $db, array $order): array
{
    $orderReference = trim((string)($order['order_reference'] ?? ''));
    if ($orderReference === '') {
        return ['matched' => false, 'reason' => 'order_reference_missing'];
    }

    if (gov_bridge_table_exists($db, 'normalized_bookings')) {
        $columns = gov_bridge_table_columns($db, 'normalized_bookings');
        $orderColumns = array_values(array_filter(['order_reference', 'source_trip_id', 'source_trip_reference', 'external_order_id'], static fn($c) => isset($columns[$c])));
        foreach ($orderColumns as $column) {
            $booking = gov_bridge_fetch_one($db, 'SELECT id FROM normalized_bookings WHERE `' . $column . '`=? ORDER BY id DESC LIMIT 1', [$orderReference]);
            if (is_array($booking) && gov_bridge_table_exists($db, 'bolt_mail_intake')) {
                $intake = gov_bridge_fetch_one($db, 'SELECT id, customer_name, driver_name, vehicle_plate, estimated_price_raw FROM bolt_mail_intake WHERE linked_booking_id=? ORDER BY id DESC LIMIT 1', [(string)(int)$booking['id']]);
                if (is_array($intake)) {
                    return [
                        'matched' => true,
                        'method' => 'linked_booking_id',
                        'booking_id' => (int)$booking['id'],
                        'intake_id' => (int)$intake['id'],
                        'customer_name_present' => trim((string)($intake['customer_name'] ?? '')) !== '',
                        'customer_name' => trim((string)($intake['customer_name'] ?? '')),
                        'estimated_price_raw' => trim((string)($intake['estimated_price_raw'] ?? '')),
                    ];
                }
                $linked = ['matched' => true, 'booking_id' => (int)$booking['id'], 'intake_id' => null, 'reason' => 'booking_found_but_intake_not_linked'];
                break;
            }
        }
    }

    if (gov_bridge_table_exists($db, 'bolt_mail_intake')) {
        $driver = trim((string)($order['driver_name'] ?? ''));
        $plate = trim((string)($order['vehicle_plate'] ?? ''));
        $pickup = trim((string)($order['order_pickup_timestamp'] ?? ''));
        $pickup = $pickup !== '' ? substr($pickup, 0, 19) : '';

        if ($driver !== '' && $plate !== '' && $pickup !== '') {
            $intake = gov_bridge_fetch_one(
                $db,
                "SELECT id, linked_booking_id, customer_name, driver_name, vehicle_plate, estimated_price_raw
                 FROM bolt_mail_intake
                 WHERE parse_status='parsed'
                   AND vehicle_plate=?
                   AND driver_name=?
                   AND parsed_pickup_at IS NOT NULL
                   AND ABS(TIMESTAMPDIFF(MINUTE, parsed_pickup_at, ?)) <= 180
                 ORDER BY ABS(TIMESTAMPDIFF(MINUTE, parsed_pickup_at, ?)) ASC, id DESC
                 LIMIT 1",
                [$plate, $driver, $pickup, $pickup]
            );
            if (is_array($intake)) {
                return [
                    'matched' => true,
                    'method' => 'driver_plate_pickup_window',
                    'booking_id' => (int)($intake['linked_booking_id'] ?? 0) ?: null,
                    'intake_id' => (int)$intake['id'],
                    'customer_name_present' => trim((string)($intake['customer_name'] ?? '')) !== '',
                    'customer_name' => trim((string)($intake['customer_name'] ?? '')),
                    'estimated_price_raw' => trim((string)($intake['estimated_price_raw'] ?? '')),
                ];
            }
        }
    }

    return $linked ?? ['matched' => false, 'reason' => 'no_booking_or_intake_match'];
}

function audit_once(int $hours, int $minutes, int $limit, bool $apiDryRun): array
{
    $tz = new DateTimeZone('Europe/Athens');
    $db = gov_bridge_db();
    $apiResult = null;

    if ($apiDryRun && function_exists('gov_bolt_sync_orders')) {
        try {
            $apiResult = gov_bolt_sync_orders($hours, true);
        } catch (Throwable $e) {
            $apiResult = ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    $raw = audit_recent_raw_payload_orders($db, $minutes, $limit, $tz);
    $orders = $raw['orders'] ?? [];
    $seen = [];
    $clean = [];
    foreach ($orders as $order) {
        $key = sha1(json_encode([
            $order['order_reference'] ?? '',
            $order['order_status'] ?? '',
            $order['order_pickup_timestamp'] ?? '',
            $order['order_finished_timestamp'] ?? '',
            $order['raw_payload_id'] ?? '',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $order['matching_mail_intake'] = audit_match_mail_intake($db, $order);
        $clean[] = $order;
        if (count($clean) >= $limit) {
            break;
        }
    }

    return [
        'ok' => true,
        'script' => 'cli/bolt_live_order_audit.php',
        'generated_at' => (new DateTimeImmutable('now', $tz))->format('Y-m-d H:i:s T'),
        'mode' => [
            'api_dry_run_first' => $apiDryRun,
            'hours' => $hours,
            'raw_payload_minutes' => $minutes,
            'limit' => $limit,
        ],
        'safety' => array_merge([
            'does_not_call_edxeix' => true,
            'does_not_issue_aade_receipts' => true,
            'does_not_print_credentials' => true,
        ], audit_submission_counts($db)),
        'api_dry_run_result_summary' => is_array($apiResult) ? array_intersect_key($apiResult, array_flip(['ok', 'dry_run', 'hours', 'orders_seen', 'orders_upserted', 'raw_payloads_stored', 'error'])) : null,
        'raw_payload_audit' => [
            'available' => (bool)($raw['available'] ?? false),
            'reason' => $raw['reason'] ?? null,
            'rows_scanned' => (int)($raw['rows_scanned'] ?? 0),
            'orders_returned' => count($clean),
        ],
        'orders' => $clean,
        'interpretation_hint' => 'Run during a live ride. If order_pickup_timestamp appears before order_finished_timestamp, the pickup worker can issue at pickup. If rows only appear after finish, Bolt getFleetOrders is not enough for pickup-time receipts.',
    ];
}

$hours = audit_int('hours', 6, 1, 720);
$minutes = audit_int('minutes', 240, 1, 10080);
$limit = audit_int('limit', 50, 1, 500);
$watch = audit_bool('watch', false);
$apiDryRun = audit_bool('api-dry-run', false);
$iterations = audit_int('iterations', $watch ? 0 : 1, 0, 10000);
$sleep = audit_int('sleep', 60, 5, 3600);

try {
    $i = 0;
    do {
        $i++;
        echo json_encode(audit_once($hours, $minutes, $limit, $apiDryRun), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        if (!$watch || ($iterations > 0 && $i >= $iterations)) {
            break;
        }
        sleep($sleep);
    } while (true);
    exit(0);
} catch (Throwable $e) {
    echo json_encode([
        'ok' => false,
        'script' => 'cli/bolt_live_order_audit.php',
        'error' => $e->getMessage(),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(1);
}
