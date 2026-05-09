<?php
/**
 * gov.cabnet.app — Bolt pickup-swipe AADE receipt worker
 *
 * Issues AADE receipt when Bolt API confirms order_pickup_timestamp.
 *
 * Production rule:
 * - If matching Bolt pre-ride email exists, use the FIRST amount in Estimated price range.
 *   Example: "40.00 - 44.00 eur" => 40.00
 * - Fallback to Bolt API order_price.ride_price only if no mail estimate is matched.
 *
 * Safety:
 * - Does not call EDXEIX.
 * - Does not create submission_jobs.
 * - Does not create submission_attempts.
 * - Skips cancelled / no-show / non-responded orders.
 * - Skips if an issued AADE receipt already exists for the matched mail booking.
 */

declare(strict_types=1);

/*
 * AADE_PICKUP_WORKER_PROCESS_LOCK_V6_5_2
 *
 * Prevent overlapping cron/manual runs from issuing the same pickup-swipe receipt twice.
 */
$__pickupRuntimeDir = '/home/cabnet/gov.cabnet.app_app/storage/runtime';
if (!is_dir($__pickupRuntimeDir)) {
    @mkdir($__pickupRuntimeDir, 0775, true);
}
$__pickupLockHandle = @fopen($__pickupRuntimeDir . '/bolt_pickup_receipt_worker.process.lock', 'c');
if (!$__pickupLockHandle || !flock($__pickupLockHandle, LOCK_EX | LOCK_NB)) {
    $payload = [
        'ok' => true,
        'locked' => true,
        'script' => 'bolt_pickup_receipt_worker.php',
        'message' => 'Another pickup-swipe receipt worker is already running. No AADE call performed by this process.',
        'safety' => [
            'does_not_call_aade_when_locked' => true,
            'does_not_email_receipts_when_locked' => true,
        ],
        'generated_at' => gmdate('c'),
    ];
    if (in_array('--json', $argv ?? [], true)) {
        echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    } else {
        echo $payload['message'] . PHP_EOL;
    }
    exit(0);
}
register_shutdown_function(static function () use ($__pickupLockHandle): void {
    if (is_resource($__pickupLockHandle)) {
        @flock($__pickupLockHandle, LOCK_UN);
        @fclose($__pickupLockHandle);
    }
});



/*
 * AADE_RECEIPT_EMERGENCY_LOCK_GUARD_V6_4_6
 *
 * This guard keeps the pickup-swipe worker parse-valid but disabled until
 * Andreas explicitly removes the emergency lock after verification.
 */
$govAadeEmergencyLock = '/home/cabnet/gov.cabnet.app_app/storage/runtime/aade_receipts_DISABLED.lock';
if (is_file($govAadeEmergencyLock)) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => true,
        'disabled' => true,
        'version' => 'v6.4.6',
        'emergency_lock' => 'AADE_RECEIPT_EMERGENCY_DISABLED',
        'script' => 'bolt_pickup_receipt_worker.php',
        'message' => 'Pickup-swipe AADE worker is restored but disabled by emergency lock. No AADE call performed.',
        'strict_rule' => 'AADE may only issue after Bolt API confirms order_pickup_timestamp.',
        'safety' => [
            'does_not_call_aade' => true,
            'does_not_email_receipts' => true,
            'does_not_call_edxeix' => true,
            'does_not_create_submission_jobs' => true,
            'does_not_create_submission_attempts' => true,
            'does_not_print_secrets' => true,
        ],
        'generated_at' => gmdate('c'),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(0);
}


require_once __DIR__ . '/../lib/bolt_sync_lib.php';

$bootstrap = require __DIR__ . '/../src/bootstrap.php';

$db = gov_bridge_db();
$bridgeDb = $bootstrap['db'];
$config = $bootstrap['config'];

$limit = 20;
$minutes = 180;
$dryRun = false;

foreach ($argv ?? [] as $arg) {
    if ($arg === '--dry-run') {
        $dryRun = true;
        continue;
    }
    if (preg_match('/^--limit=(\d+)$/', $arg, $m)) {
        $limit = max(1, min(100, (int)$m[1]));
        continue;
    }
    if (preg_match('/^--minutes=(\d+)$/', $arg, $m)) {
        $minutes = max(1, min(1440, (int)$m[1]));
        continue;
    }
}

function q1(mysqli $db, string $sql, array $params = [], string $types = ''): ?array
{
    $rows = qall($db, $sql, $params, $types);
    return $rows[0] ?? null;
}

function qall(mysqli $db, string $sql, array $params = [], string $types = ''): array
{
    if (!$params) {
        $res = $db->query($sql);
        $out = [];
        while ($row = $res->fetch_assoc()) {
            $out[] = $row;
        }
        return $out;
    }

    $stmt = $db->prepare($sql);
    if ($types === '') {
        $types = str_repeat('s', count($params));
    }
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();

    $out = [];
    while ($row = $res->fetch_assoc()) {
        $out[] = $row;
    }
    $stmt->close();

    return $out;
}

function execq(mysqli $db, string $sql, array $params = [], string $types = ''): void
{
    $stmt = $db->prepare($sql);
    if ($types === '') {
        $types = str_repeat('s', count($params));
    }
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $stmt->close();
}

function athens_from_ts($value): ?string
{
    $ts = is_numeric($value) ? (int)$value : 0;
    if ($ts <= 0) {
        return null;
    }

    return (new DateTimeImmutable('@' . $ts))
        ->setTimezone(new DateTimeZone('Europe/Athens'))
        ->format('Y-m-d H:i:s');
}

function money_first_number(?string $raw): float
{
    $raw = trim((string)$raw);
    if ($raw === '') {
        return 0.0;
    }

    if (!preg_match('/(\d+(?:[.,]\d+)?)/u', $raw, $m)) {
        return 0.0;
    }

    return round((float)str_replace(',', '.', $m[1]), 2);
}

function amount_from_bolt_order(array $order): float
{
    $price = $order['order_price'] ?? [];

    if (is_array($price)) {
        foreach (['ride_price', 'price', 'total_price', 'final_price'] as $key) {
            if (isset($price[$key]) && is_numeric($price[$key]) && (float)$price[$key] > 0) {
                return round((float)$price[$key], 2);
            }
        }
    }

    foreach (['price', 'ride_price', 'total_price'] as $key) {
        if (isset($order[$key]) && is_numeric($order[$key]) && (float)$order[$key] > 0) {
            return round((float)$order[$key], 2);
        }
    }

    return 0.0;
}

function is_cancelled_or_unsafe_status(string $status): bool
{
    $status = strtolower(trim($status));

    return in_array($status, [
        'client_cancelled',
        'driver_cancelled',
        'driver_cancelled_after_accept',
        'driver_did_not_respond',
        'client_did_not_show',
        'no_show',
        'cancelled',
        'canceled',
    ], true);
}

function raw_payload_orders(array $payload): array
{
    if (isset($payload['order_reference'])) {
        return [$payload];
    }

    if (isset($payload['data']['orders']) && is_array($payload['data']['orders'])) {
        return $payload['data']['orders'];
    }

    if (isset($payload['orders']) && is_array($payload['orders'])) {
        return $payload['orders'];
    }

    if (array_is_list($payload)) {
        return $payload;
    }

    return [];
}

function issued_for_booking(mysqli $db, int $bookingId): bool
{
    $row = q1(
        $db,
        "SELECT COUNT(*) AS c
         FROM receipt_issuance_attempts
         WHERE normalized_booking_id=?
           AND provider='aade_mydata'
           AND provider_status='issued'",
        [$bookingId],
        'i'
    );

    return (int)($row['c'] ?? 0) > 0;
}

function find_booking(mysqli $db, string $orderReference): ?array
{
    return q1(
        $db,
        "SELECT *
         FROM normalized_bookings
         WHERE source_system='bolt'
           AND (external_order_id=? OR order_reference=?)
         ORDER BY id DESC
         LIMIT 1",
        [$orderReference, $orderReference],
        'ss'
    );
}

function find_matching_mail_intake(mysqli $db, array $booking, string $pickupAthens): ?array
{
    $driver = trim((string)($booking['driver_name'] ?? ''));
    $plate = trim((string)($booking['vehicle_plate'] ?? ''));

    if ($driver === '' || $plate === '') {
        return null;
    }

    return q1(
        $db,
        "SELECT *
         FROM bolt_mail_intake
         WHERE parse_status='parsed'
           AND vehicle_plate=?
           AND driver_name=?
           AND parsed_pickup_at IS NOT NULL
           AND ABS(TIMESTAMPDIFF(MINUTE, parsed_pickup_at, ?)) <= 180
         ORDER BY ABS(TIMESTAMPDIFF(MINUTE, parsed_pickup_at, ?)) ASC, id DESC
         LIMIT 1",
        [$plate, $driver, $pickupAthens, $pickupAthens],
        'ssss'
    );
}

$out = [
    'ok' => true,
    'script' => 'cli/bolt_pickup_receipt_worker.php',
    'dry_run' => $dryRun,
    'limit' => $limit,
    'minutes' => $minutes,
    'started_at' => date(DATE_ATOM),
    'summary' => [
        'raw_rows_scanned' => 0,
        'orders_seen' => 0,
        'candidates' => 0,
        'issued' => 0,
        'emailed' => 0,
        'skipped' => 0,
        'failed' => 0,
    ],
    'items' => [],
    'safety' => [
        'does_not_call_edxeix' => true,
        'does_not_create_submission_jobs' => true,
        'does_not_create_submission_attempts' => true,
    ],
];

$rawRows = qall(
    $db,
    "SELECT id, source_type, source_id, external_reference, payload_json, raw_json, created_at
     FROM bolt_raw_payloads
     ORDER BY id DESC
     LIMIT 800"
);

$out['summary']['raw_rows_scanned'] = count($rawRows);

$issuer = new \Bridge\Receipts\AadeReceiptAutoIssuer($bridgeDb, $config);

$now = new DateTimeImmutable('now', new DateTimeZone('Europe/Athens'));
$seenReferences = [];
$processed = 0;

foreach ($rawRows as $rawRow) {
    $jsonText = (string)($rawRow['payload_json'] ?: $rawRow['raw_json'] ?: '');
    if ($jsonText === '') {
        continue;
    }

    $decoded = json_decode($jsonText, true);
    if (!is_array($decoded)) {
        continue;
    }

    foreach (raw_payload_orders($decoded) as $order) {
        if (!is_array($order)) {
            continue;
        }

        $out['summary']['orders_seen']++;

        $reference = trim((string)($order['order_reference'] ?? $order['reference'] ?? $order['id'] ?? ''));
        if ($reference === '' || isset($seenReferences[$reference])) {
            continue;
        }
        $seenReferences[$reference] = true;

        $status = strtolower(trim((string)($order['order_status'] ?? $order['status'] ?? '')));
        $pickupTs = $order['order_pickup_timestamp'] ?? null;
        $pickupAthens = athens_from_ts($pickupTs);

        if (!$pickupAthens) {
            continue;
        }

        if (is_cancelled_or_unsafe_status($status)) {
            $out['summary']['skipped']++;
            $out['items'][] = [
                'order_reference' => $reference,
                'status' => 'skipped',
                'reason' => 'unsafe_order_status',
                'order_status' => $status,
            ];
            continue;
        }

        $pickupDt = new DateTimeImmutable($pickupAthens, new DateTimeZone('Europe/Athens'));
        $ageMinutes = (int)(($now->getTimestamp() - $pickupDt->getTimestamp()) / 60);

        if ($ageMinutes < 0 || $ageMinutes > $minutes) {
            continue;
        }

        $booking = find_booking($db, $reference);
        if (!$booking) {
            $out['summary']['skipped']++;
            $out['items'][] = [
                'order_reference' => $reference,
                'status' => 'skipped',
                'reason' => 'normalized_booking_not_found_run_sync_first',
                'pickup_at' => $pickupAthens,
            ];
            continue;
        }

        $apiBookingId = (int)$booking['id'];
        $mail = find_matching_mail_intake($db, $booking, $pickupAthens);
        $effectiveBookingId = $apiBookingId;
        $mailEstimateAmount = 0.0;

        if ($mail) {
            $mailEstimateAmount = money_first_number($mail['estimated_price_raw'] ?? '');

            if ((int)($mail['linked_booking_id'] ?? 0) > 0) {
                $effectiveBookingId = (int)$mail['linked_booking_id'];
            }
        }

        if (issued_for_booking($db, $effectiveBookingId)) {
            $out['summary']['skipped']++;
            $out['items'][] = [
                'order_reference' => $reference,
                'booking_id' => $effectiveBookingId,
                'api_booking_id' => $apiBookingId,
                'mail_intake_id' => $mail['id'] ?? null,
                'status' => 'skipped',
                'reason' => 'already_issued_for_effective_booking',
            ];
            continue;
        }

        $amount = $mailEstimateAmount > 0 ? $mailEstimateAmount : amount_from_bolt_order($order);

        if ($amount <= 0) {
            $out['summary']['skipped']++;
            $out['items'][] = [
                'order_reference' => $reference,
                'booking_id' => $effectiveBookingId,
                'api_booking_id' => $apiBookingId,
                'mail_intake_id' => $mail['id'] ?? null,
                'status' => 'skipped',
                'reason' => 'missing_receipt_amount',
                'estimated_price_raw' => $mail['estimated_price_raw'] ?? null,
            ];
            continue;
        }

        $out['summary']['candidates']++;
        $processed++;

        if ($dryRun) {
            $out['items'][] = [
                'order_reference' => $reference,
                'booking_id' => $effectiveBookingId,
                'api_booking_id' => $apiBookingId,
                'mail_intake_id' => $mail['id'] ?? null,
                'pickup_at' => $pickupAthens,
                'age_minutes' => $ageMinutes,
                'amount' => number_format($amount, 2, '.', ''),
                'amount_source' => $mailEstimateAmount > 0 ? 'bolt_mail_estimated_price_first_number' : 'bolt_api_ride_price_fallback',
                'status' => 'dry_run_candidate',
            ];
        } else {
            execq(
                $db,
                "UPDATE normalized_bookings
                 SET price=?,
                     started_at=?,
                     status=COALESCE(NULLIF(status,''), ?),
                     order_status=COALESCE(NULLIF(order_status,''), ?),
                     updated_at=NOW()
                 WHERE id=?",
                [number_format($amount, 2, '.', ''), $pickupAthens, $status, $status, $effectiveBookingId],
                'ssssi'
            );

            if ($mail && (int)($mail['linked_booking_id'] ?? 0) <= 0) {
                execq(
                    $db,
                    "UPDATE bolt_mail_intake
                     SET linked_booking_id=?, updated_at=NOW()
                     WHERE id=?",
                    [$effectiveBookingId, (int)$mail['id']],
                    'ii'
                );
            }

            $result = $issuer->issueAndEmailForBooking($effectiveBookingId, 'auto-bolt-pickup-swipe-v6.2.4');

            if (!empty($result['issued'])) {
                $out['summary']['issued']++;
            }
            if (!empty($result['emailed'])) {
                $out['summary']['emailed']++;
            }
            if (empty($result['issued'])) {
                $out['summary']['failed']++;
            }

            $out['items'][] = [
                'order_reference' => $reference,
                'booking_id' => $effectiveBookingId,
                'api_booking_id' => $apiBookingId,
                'mail_intake_id' => $mail['id'] ?? null,
                'pickup_at' => $pickupAthens,
                'age_minutes' => $ageMinutes,
                'amount' => number_format($amount, 2, '.', ''),
                'amount_source' => $mailEstimateAmount > 0 ? 'bolt_mail_estimated_price_first_number' : 'bolt_api_ride_price_fallback',
                'status' => $result['status'] ?? 'unknown',
                'issued' => !empty($result['issued']),
                'emailed' => !empty($result['emailed']),
                'attempt_id' => $result['attempt_id'] ?? null,
                'error' => $result['error'] ?? null,
            ];
        }

        if ($processed >= $limit) {
            break 2;
        }
    }
}

$out['finished_at'] = date(DATE_ATOM);

echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
