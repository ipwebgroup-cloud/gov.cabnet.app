<?php
/**
 * gov.cabnet.app — Bolt direct live order audit v6.2.7
 *
 * Purpose:
 * - Call Bolt getFleetOrders directly and print sanitized live order state.
 * - Prove whether Bolt exposes order_pickup_timestamp before order_finished_timestamp.
 * - Classify possible pickup-receipt candidates without issuing receipts.
 *
 * Safety:
 * - Does not call EDXEIX.
 * - Does not create submission_jobs or submission_attempts.
 * - Does not issue AADE/myDATA receipts.
 * - Does not store Bolt payloads.
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

function audit_text($value): string
{
    if ($value === null || is_array($value) || is_object($value)) {
        return '';
    }
    $text = trim((string)$value);
    return $text !== '' ? (preg_replace('/\s+/u', ' ', $text) ?: $text) : '';
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
        $text = audit_text($value);
        if ($text !== '') {
            return $text;
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
        return audit_text($value) ?: null;
    }
}

function audit_price_text(array $order): string
{
    foreach ([
        'ride_price', 'price', 'total_price', 'amount', 'booking_price',
        ['order_price', 'ride_price'], ['order_price', 'total_price'], ['order_price', 'amount'],
        ['price', 'amount'], ['ride_price', 'amount'], ['total_price', 'amount'],
    ] as $path) {
        $value = $order;
        foreach ((array)$path as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) {
                $value = null;
                break;
            }
            $value = $value[$part];
        }
        if (is_array($value) && function_exists('gov_bolt_extract_price')) {
            $price = gov_bolt_extract_price($value);
            if ($price !== '0.00') {
                return $price;
            }
        }
        $text = audit_text($value);
        if ($text !== '') {
            return $text;
        }
    }
    return '';
}

function audit_order_summary(array $order, DateTimeZone $tz, string $source, string $observedEest, string $observedUtc): array
{
    $driverName = audit_nested_text($order, [
        'driver_name', 'driverName', ['driver', 'name'], ['driver', 'full_name'], ['driver', 'driver_name'],
    ]);
    $vehiclePlate = strtoupper(audit_nested_text($order, [
        'vehicle_plate', 'vehiclePlate', 'vehicle_license_plate', 'license_plate', 'licence_plate', 'reg_number',
        ['vehicle', 'plate'], ['vehicle', 'license_plate'], ['vehicle', 'licence_plate'], ['vehicle', 'reg_number'],
    ]));

    $pickup = audit_nested_text($order, [
        'pickup_address', 'pickup', 'boarding_point', 'origin_address',
        ['pickup', 'address'], ['pickup', 'name'], ['pickup_location', 'address'], ['route', 'pickup'],
    ]);
    $destination = audit_nested_text($order, [
        'destination_address', 'dropoff_address', 'drop_off_address', 'destination', 'disembark_point',
        ['destination', 'address'], ['dropoff', 'address'], ['drop_off', 'address'], ['route', 'destination'],
    ]);

    $summary = [
        'source' => $source,
        'observed_at_eest' => $observedEest,
        'observed_at_utc' => $observedUtc,
        'order_reference' => audit_nested_text($order, ['order_reference', 'order_ref', 'reference', 'order_id', 'id', 'uuid']),
        'driver_name' => $driverName,
        'vehicle_plate' => $vehiclePlate,
        'order_status' => audit_nested_text($order, ['order_status', 'status', 'state', 'state_display']),
        'payment_method' => audit_nested_text($order, ['payment_method', 'paymentMethod', ['payment', 'method']]),
        'order_accepted_timestamp' => audit_timestamp($order['order_accepted_timestamp'] ?? $order['accepted_at'] ?? null, $tz),
        'order_pickup_timestamp' => audit_timestamp($order['order_pickup_timestamp'] ?? $order['pickup_timestamp'] ?? $order['picked_up_at'] ?? null, $tz),
        'order_drop_off_timestamp' => audit_timestamp($order['order_drop_off_timestamp'] ?? $order['dropoff_timestamp'] ?? $order['drop_off_timestamp'] ?? null, $tz),
        'order_finished_timestamp' => audit_timestamp($order['order_finished_timestamp'] ?? $order['finished_at'] ?? null, $tz),
        'ride_price' => audit_price_text($order),
        'pickup' => $pickup,
        'destination' => $destination,
    ];

    $summary['state_analysis'] = audit_state_analysis($summary);
    return $summary;
}

function audit_state_analysis(array $order): array
{
    $status = strtolower(trim((string)($order['order_status'] ?? '')));
    $pickupPresent = trim((string)($order['order_pickup_timestamp'] ?? '')) !== '';
    $dropoffPresent = trim((string)($order['order_drop_off_timestamp'] ?? '')) !== '';
    $finishedPresent = trim((string)($order['order_finished_timestamp'] ?? '')) !== '';

    $unsafeStatuses = [
        'client_cancelled',
        'driver_cancelled_after_accept',
        'driver_did_not_respond',
        'client_did_not_show',
        'no_show',
        'cancelled',
        'canceled',
    ];
    $finishedStatuses = ['finished', 'completed', 'complete', 'done'];

    $unsafe = in_array($status, $unsafeStatuses, true);
    $finished = $finishedPresent || in_array($status, $finishedStatuses, true);
    $activePickedUpBeforeFinish = $pickupPresent && !$dropoffPresent && !$finishedPresent && !$unsafe && !$finished;

    $group = 'unknown_or_pending';
    $reason = 'No pickup evidence yet in this poll.';

    if ($unsafe) {
        $group = 'unsafe_terminal_or_cancelled';
        $reason = 'Unsafe terminal/cancelled/no-show status; must never issue receipt from this row.';
    } elseif ($finished) {
        $group = 'finished';
        $reason = 'Finished row; useful as history, not proof of pickup-time visibility.';
    } elseif ($activePickedUpBeforeFinish) {
        $group = 'active_picked_up_before_finish';
        $reason = 'This is the evidence needed: pickup timestamp is visible before finish.';
    } elseif ($pickupPresent) {
        $group = 'pickup_present_but_not_candidate';
        $reason = 'Pickup timestamp exists, but status/timestamps do not satisfy safe live pickup criteria.';
    }

    return [
        'status_group' => $group,
        'pickup_timestamp_present' => $pickupPresent,
        'dropoff_timestamp_present' => $dropoffPresent,
        'finished_timestamp_present' => $finishedPresent,
        'unsafe_status' => $unsafe,
        'pickup_receipt_probe_candidate' => $activePickedUpBeforeFinish,
        'reason' => $reason,
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

function audit_match_mail_intake(mysqli $db, array $order): array
{
    $orderReference = trim((string)($order['order_reference'] ?? ''));
    $linked = null;

    if ($orderReference !== '' && gov_bridge_table_exists($db, 'normalized_bookings')) {
        $columns = gov_bridge_table_columns($db, 'normalized_bookings');
        $orderColumns = array_values(array_filter(
            ['order_reference', 'source_trip_id', 'source_trip_reference', 'external_order_id'],
            static fn($c) => isset($columns[$c])
        ));

        foreach ($orderColumns as $column) {
            $booking = gov_bridge_fetch_one(
                $db,
                'SELECT id FROM normalized_bookings WHERE `' . $column . '`=? ORDER BY id DESC LIMIT 1',
                [$orderReference]
            );
            if (is_array($booking)) {
                $linked = ['matched' => true, 'booking_id' => (int)$booking['id'], 'intake_id' => null, 'reason' => 'booking_found_but_intake_not_linked'];
                if (gov_bridge_table_exists($db, 'bolt_mail_intake')) {
                    $intake = gov_bridge_fetch_one(
                        $db,
                        'SELECT id, customer_name, driver_name, vehicle_plate, estimated_price_raw FROM bolt_mail_intake WHERE linked_booking_id=? ORDER BY id DESC LIMIT 1',
                        [(string)(int)$booking['id']]
                    );
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
                }
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

    return $linked ?? ['matched' => false, 'reason' => $orderReference === '' ? 'order_reference_missing' : 'no_booking_or_intake_match'];
}

function audit_direct_live_orders(mysqli $db, int $hours, int $limit, bool $onlyCandidates): array
{
    $tz = new DateTimeZone('Europe/Athens');
    $utc = new DateTimeZone('UTC');
    $now = new DateTimeImmutable('now');
    $observedEest = $now->setTimezone($tz)->format('Y-m-d H:i:s T');
    $observedUtc = $now->setTimezone($utc)->format('Y-m-d H:i:s T');

    if (!function_exists('gov_bolt_get_fleet_orders')) {
        return ['ok' => false, 'error' => 'gov_bolt_get_fleet_orders_not_available', 'orders' => []];
    }

    $result = gov_bolt_get_fleet_orders($hours);
    $items = is_array($result['items'] ?? null) ? $result['items'] : [];
    $orders = [];

    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        $summary = audit_order_summary($item, $tz, 'live_api_direct_no_store', $observedEest, $observedUtc);
        $summary['matching_mail_intake'] = audit_match_mail_intake($db, $summary);
        $candidate = !empty($summary['state_analysis']['pickup_receipt_probe_candidate']);
        $hasMail = !empty($summary['matching_mail_intake']['matched']) && !empty($summary['matching_mail_intake']['intake_id']);
        $hasEstimate = trim((string)($summary['matching_mail_intake']['estimated_price_raw'] ?? '')) !== '';
        $summary['pickup_receipt_readiness'] = [
            'would_be_candidate_if_worker_saw_this_now' => $candidate && $hasMail && $hasEstimate,
            'requires_active_pickup_before_finish' => $candidate,
            'requires_matched_mail_intake' => $hasMail,
            'requires_email_estimated_price' => $hasEstimate,
            'would_not_issue_reason' => $candidate && $hasMail && $hasEstimate ? null : audit_not_ready_reason($candidate, $hasMail, $hasEstimate),
        ];

        if ($onlyCandidates && empty($summary['pickup_receipt_readiness']['would_be_candidate_if_worker_saw_this_now'])) {
            continue;
        }

        $orders[] = $summary;
        if (count($orders) >= $limit) {
            break;
        }
    }

    return [
        'ok' => true,
        'orders_seen_by_api' => count($items),
        'orders_returned' => count($orders),
        'pagination' => $result['pages'] ?? null,
        'orders' => $orders,
    ];
}

function audit_not_ready_reason(bool $candidate, bool $hasMail, bool $hasEstimate): string
{
    if (!$candidate) {
        return 'not_active_picked_up_before_finish_or_unsafe_status';
    }
    if (!$hasMail) {
        return 'matched_mail_intake_missing';
    }
    if (!$hasEstimate) {
        return 'email_estimated_price_missing';
    }
    return 'unknown';
}

function audit_proof_summary(array $orders): array
{
    $counts = [
        'returned_orders' => count($orders),
        'active_picked_up_before_finish' => 0,
        'would_be_pickup_receipt_candidate' => 0,
        'finished_with_pickup' => 0,
        'unsafe_with_pickup' => 0,
        'no_pickup_timestamp' => 0,
    ];

    foreach ($orders as $order) {
        $analysis = $order['state_analysis'] ?? [];
        if (!empty($analysis['pickup_receipt_probe_candidate'])) {
            $counts['active_picked_up_before_finish']++;
        }
        if (!empty($order['pickup_receipt_readiness']['would_be_candidate_if_worker_saw_this_now'])) {
            $counts['would_be_pickup_receipt_candidate']++;
        }
        if (($analysis['status_group'] ?? '') === 'finished' && !empty($analysis['pickup_timestamp_present'])) {
            $counts['finished_with_pickup']++;
        }
        if (!empty($analysis['unsafe_status']) && !empty($analysis['pickup_timestamp_present'])) {
            $counts['unsafe_with_pickup']++;
        }
        if (empty($analysis['pickup_timestamp_present'])) {
            $counts['no_pickup_timestamp']++;
        }
    }

    $conclusion = 'No active picked-up-before-finish order was visible in this poll. This is not conclusive unless the command was running exactly during/after pickup and before finish.';
    if ($counts['would_be_pickup_receipt_candidate'] > 0) {
        $conclusion = 'Bolt getFleetOrders exposed a safe pickup-time candidate before finish in this poll.';
    } elseif ($counts['active_picked_up_before_finish'] > 0) {
        $conclusion = 'Bolt getFleetOrders exposed pickup before finish, but mail intake/estimate matching was not ready for issuance.';
    }

    return $counts + ['conclusion' => $conclusion];
}

function audit_once(int $hours, int $limit, bool $onlyCandidates): array
{
    $tz = new DateTimeZone('Europe/Athens');
    $db = gov_bridge_db();
    $live = audit_direct_live_orders($db, $hours, $limit, $onlyCandidates);
    $orders = is_array($live['orders'] ?? null) ? $live['orders'] : [];

    return [
        'ok' => (bool)($live['ok'] ?? false),
        'script' => 'cli/bolt_live_order_audit.php',
        'generated_at_eest' => (new DateTimeImmutable('now', $tz))->format('Y-m-d H:i:s T'),
        'mode' => [
            'live_api_direct' => true,
            'stores_payloads' => false,
            'hours' => $hours,
            'limit' => $limit,
            'only_candidates' => $onlyCandidates,
        ],
        'safety' => array_merge([
            'does_not_call_edxeix' => true,
            'does_not_issue_aade_receipts' => true,
            'does_not_store_bolt_payloads' => true,
            'does_not_print_credentials' => true,
        ], audit_submission_counts($db)),
        'proof_summary' => audit_proof_summary($orders),
        'live_api_result' => [
            'ok' => (bool)($live['ok'] ?? false),
            'orders_seen_by_api' => (int)($live['orders_seen_by_api'] ?? 0),
            'orders_returned' => (int)($live['orders_returned'] ?? 0),
            'pagination' => $live['pagination'] ?? null,
            'error' => $live['error'] ?? null,
        ],
        'orders' => $orders,
        'interpretation_hint' => 'Run --watch during a real live ride. The decisive row is status_group=active_picked_up_before_finish with would_be_candidate_if_worker_saw_this_now=true. Historical finished rows do not prove pickup-time visibility.',
    ];
}

$hours = audit_int('hours', 24, 1, 720);
$limit = audit_int('limit', 80, 1, 500);
$watch = audit_bool('watch', false);
$onlyCandidates = audit_bool('only-candidates', false);
$iterations = audit_int('iterations', $watch ? 0 : 1, 0, 10000);
$sleep = audit_int('sleep', 30, 5, 3600);

try {
    $i = 0;
    do {
        $i++;
        echo json_encode(audit_once($hours, $limit, $onlyCandidates), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
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
