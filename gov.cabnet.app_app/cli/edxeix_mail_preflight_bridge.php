<?php
/**
 * gov.cabnet.app — EDXEIX mail preflight bridge v6.7.0
 *
 * Purpose:
 * - Find future pre-ride Bolt email intake rows that are not yet linked to a
 *   normalized booking.
 * - Preview whether each row can safely create a local normalized EDXEIX
 *   preflight booking.
 * - Create the normalized booking only when --create is explicitly supplied.
 *
 * Safety:
 * - Does not call EDXEIX.
 * - Does not issue AADE receipts.
 * - Does not create submission_jobs.
 * - Does not create submission_attempts.
 * - Does not expose session cookies, CSRF tokens, API keys, or private config.
 *
 * Source policy:
 * - EDXEIX source is pre-ride Bolt email only.
 * - AADE source remains Bolt API pickup timestamp worker only.
 */

declare(strict_types=1);

use Bridge\Mail\BoltMailIntakeBookingBridge;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "CLI only\n";
    exit(1);
}

$container = require __DIR__ . '/../src/bootstrap.php';
$config = $container['config'];
$db = $container['db'];

$options = getopt('', [
    'limit::',
    'intake-id::',
    'create',
    'json',
    'help',
]);

if (isset($options['help'])) {
    echo "EDXEIX Mail Preflight Bridge v6.7.0\n";
    echo "Usage:\n";
    echo "  php edxeix_mail_preflight_bridge.php --json\n";
    echo "  php edxeix_mail_preflight_bridge.php --intake-id=123 --json\n";
    echo "  php edxeix_mail_preflight_bridge.php --intake-id=123 --create --json\n";
    echo "  php edxeix_mail_preflight_bridge.php --limit=20 --create --json\n";
    echo "Default mode is preview only. --create is required to write a normalized preflight booking.\n";
    echo "Safety: no EDXEIX calls, no AADE calls, no submission jobs/attempts.\n";
    exit(0);
}

$limit = max(1, min(100, (int)($options['limit'] ?? 20)));
$intakeId = isset($options['intake-id']) ? (int)$options['intake-id'] : 0;
$create = array_key_exists('create', $options);
$json = array_key_exists('json', $options);

if (!empty($config->get('app.timezone', ''))) {
    date_default_timezone_set((string)$config->get('app.timezone', 'Europe/Athens'));
}

$futureGuardMinutes = (int)$config->get('edxeix.future_start_guard_minutes', 30);
$futureGuardMinutes = max(0, min(1440, $futureGuardMinutes));

$out = [
    'ok' => false,
    'script' => 'cli/edxeix_mail_preflight_bridge.php',
    'version' => 'v6.7.0',
    'generated_at' => date('c'),
    'source_policy' => [
        'edxeix_submission_source' => 'pre_ride_bolt_email_only',
        'edxeix_uses_bolt_api_as_source' => false,
        'aade_invoice_source' => 'bolt_api_pickup_timestamp_worker_only',
        'pre_ride_email_may_issue_aade' => false,
    ],
    'mode' => [
        'create_enabled' => $create,
        'preview_only' => !$create,
        'limit' => $limit,
        'intake_id' => $intakeId > 0 ? $intakeId : null,
        'future_guard_minutes' => $futureGuardMinutes,
    ],
    'safety' => [
        'does_not_call_edxeix' => true,
        'does_not_issue_aade_receipts' => true,
        'does_not_create_submission_jobs' => true,
        'does_not_create_submission_attempts' => true,
        'does_not_print_session_cookies_or_tokens' => true,
    ],
    'queue_counts' => [],
    'summary' => [
        'candidate_rows' => 0,
        'preview_ready' => 0,
        'preview_blocked' => 0,
        'created_bookings' => 0,
        'linked_existing_bookings' => 0,
        'errors' => 0,
    ],
    'items' => [],
    'next_safe_steps' => [],
    'error' => null,
];

try {
    $beforeJobs = countRows($db, 'submission_jobs');
    $beforeAttempts = countRows($db, 'submission_attempts');

    $bridge = new BoltMailIntakeBookingBridge($db, $config);
    $rows = $intakeId > 0 ? findSingleIntakeRow($db, $intakeId) : listOpenFutureMailRows($db, $limit, $futureGuardMinutes);

    $out['summary']['candidate_rows'] = count($rows);

    foreach ($rows as $row) {
        $rowId = (int)($row['id'] ?? 0);
        $item = [
            'intake_id' => $rowId,
            'linked_booking_id_before' => isset($row['linked_booking_id']) && $row['linked_booking_id'] !== null ? (int)$row['linked_booking_id'] : null,
            'customer_name' => (string)($row['customer_name'] ?? ''),
            'driver_name' => (string)($row['driver_name'] ?? ''),
            'vehicle_plate' => (string)($row['vehicle_plate'] ?? ''),
            'parsed_pickup_at' => (string)($row['parsed_pickup_at'] ?? ''),
            'pickup_address' => (string)($row['pickup_address'] ?? ''),
            'dropoff_address' => (string)($row['dropoff_address'] ?? ''),
            'preview' => null,
            'created' => false,
            'booking_id' => null,
            'status' => 'pending',
            'message' => '',
        ];

        if ($rowId <= 0) {
            $out['summary']['errors']++;
            $item['status'] = 'error';
            $item['message'] = 'Invalid intake row id.';
            $out['items'][] = $item;
            continue;
        }

        $preview = $bridge->previewById($rowId);
        $item['preview'] = safePreview($preview);

        if (!empty($preview['create_allowed'])) {
            $out['summary']['preview_ready']++;
        } else {
            $out['summary']['preview_blocked']++;
            $item['status'] = 'blocked_preview';
            $item['message'] = (string)($preview['message'] ?? 'Preview blocked.');
            $out['items'][] = $item;
            continue;
        }

        if (!$create) {
            $item['status'] = 'preview_ready';
            $item['message'] = 'This intake row can create a local normalized EDXEIX preflight booking. Re-run with --create to write it.';
            $out['items'][] = $item;
            continue;
        }

        $result = $bridge->createLocalPreflightBooking($rowId);
        $item['booking_id'] = isset($result['booking_id']) && $result['booking_id'] !== null ? (int)$result['booking_id'] : null;
        $item['created'] = !empty($result['created']);
        $item['status'] = !empty($result['ok']) ? 'created_or_linked' : 'error';
        $item['message'] = (string)($result['message'] ?? '');

        if (!empty($result['ok'])) {
            if (!empty($result['created'])) {
                $out['summary']['created_bookings']++;
            } else {
                $out['summary']['linked_existing_bookings']++;
            }
        } else {
            $out['summary']['errors']++;
        }

        $out['items'][] = $item;
    }

    $afterJobs = countRows($db, 'submission_jobs');
    $afterAttempts = countRows($db, 'submission_attempts');

    $out['queue_counts'] = [
        'submission_jobs_before' => $beforeJobs,
        'submission_jobs_after' => $afterJobs,
        'submission_attempts_before' => $beforeAttempts,
        'submission_attempts_after' => $afterAttempts,
        'queues_unchanged' => $beforeJobs === $afterJobs && $beforeAttempts === $afterAttempts,
    ];

    $out['next_safe_steps'] = [
        'If preview_ready is greater than zero, review the items before using --create.',
        'After creating a normalized mail-derived booking, run edxeix_readiness_report.php --only-ready --json.',
        'For a ready mail-derived booking, run live_submit_one_booking.php --booking-id=ID --analyze-only before any live action.',
        'Do not enable live EDXEIX submission or one-shot locks unless Andreas explicitly approves one exact future booking.',
    ];

    $out['ok'] = $out['summary']['errors'] === 0 && !empty($out['queue_counts']['queues_unchanged']);
} catch (Throwable $e) {
    $out['error'] = $e->getMessage();
}

if ($json) {
    echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} else {
    echo 'EDXEIX Mail Preflight Bridge v6.7.0' . PHP_EOL;
    echo 'OK: ' . (!empty($out['ok']) ? 'yes' : 'no') . PHP_EOL;
    echo 'Mode: ' . ($create ? 'create' : 'preview') . PHP_EOL;
    echo 'Candidate rows: ' . (int)($out['summary']['candidate_rows'] ?? 0) . PHP_EOL;
    echo 'Preview ready: ' . (int)($out['summary']['preview_ready'] ?? 0) . PHP_EOL;
    echo 'Created bookings: ' . (int)($out['summary']['created_bookings'] ?? 0) . PHP_EOL;
    echo 'Linked existing: ' . (int)($out['summary']['linked_existing_bookings'] ?? 0) . PHP_EOL;
    echo 'Queues unchanged: ' . (!empty($out['queue_counts']['queues_unchanged']) ? 'yes' : 'no') . PHP_EOL;
    if (!empty($out['error'])) {
        echo 'ERROR: ' . $out['error'] . PHP_EOL;
    }
}

exit(!empty($out['ok']) ? 0 : 1);

/** @return array<int,array<string,mixed>> */
function listOpenFutureMailRows($db, int $limit, int $futureGuardMinutes): array
{
    if (!tableExists($db, 'bolt_mail_intake')) {
        return [];
    }

    return $db->fetchAll(
        "SELECT *
         FROM bolt_mail_intake
         WHERE parse_status = 'parsed'
           AND safety_status = 'future_candidate'
           AND linked_booking_id IS NULL
           AND parsed_pickup_at IS NOT NULL
           AND parsed_pickup_at >= DATE_ADD(NOW(), INTERVAL ? MINUTE)
           AND UPPER(COALESCE(customer_name,'')) NOT LIKE '%CABNET TEST%'
           AND UPPER(COALESCE(customer_name,'')) NOT LIKE '%DO NOT SUBMIT%'
         ORDER BY parsed_pickup_at ASC, id ASC
         LIMIT " . (int)$limit,
        [(string)$futureGuardMinutes]
    );
}

/** @return array<int,array<string,mixed>> */
function findSingleIntakeRow($db, int $intakeId): array
{
    if (!tableExists($db, 'bolt_mail_intake')) {
        return [];
    }

    $row = $db->fetchOne('SELECT * FROM bolt_mail_intake WHERE id = ? LIMIT 1', [$intakeId], 'i');
    return $row ? [$row] : [];
}

function tableExists($db, string $table): bool
{
    $row = $db->fetchOne(
        'SELECT COUNT(*) AS c FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
        [$table]
    );
    return ((int)($row['c'] ?? 0)) > 0;
}

function countRows($db, string $table): int
{
    if (!tableExists($db, $table)) {
        return 0;
    }

    $row = $db->fetchOne('SELECT COUNT(*) AS c FROM `' . safeIdentifier($table) . '`');
    return (int)($row['c'] ?? 0);
}

function safeIdentifier(string $identifier): string
{
    if (!preg_match('/^[A-Za-z0-9_]+$/', $identifier)) {
        throw new RuntimeException('Unsafe SQL identifier: ' . $identifier);
    }
    return $identifier;
}

/** @return array<string,mixed> */
function safePreview(array $preview): array
{
    $mapping = is_array($preview['mapping'] ?? null) ? $preview['mapping'] : [];
    $candidate = is_array($preview['normalized_candidate'] ?? null) ? $preview['normalized_candidate'] : [];
    $payload = is_array($preview['edxeix_preview_payload'] ?? null) ? $preview['edxeix_preview_payload'] : [];

    return [
        'ok' => !empty($preview['ok']),
        'create_allowed' => !empty($preview['create_allowed']),
        'existing_booking_id' => isset($preview['existing_booking_id']) && $preview['existing_booking_id'] !== null ? (int)$preview['existing_booking_id'] : null,
        'errors' => array_values(array_map('strval', (array)($preview['errors'] ?? []))),
        'warnings' => array_values(array_map('strval', (array)($preview['warnings'] ?? []))),
        'time_check' => is_array($preview['time_check'] ?? null) ? $preview['time_check'] : [],
        'mapping' => [
            'driver_ok' => !empty($mapping['driver']['ok']),
            'vehicle_ok' => !empty($mapping['vehicle']['ok']),
            'starting_point_ok' => !empty($mapping['starting_point']['ok']),
            'driver_lookup' => (string)($mapping['driver']['lookup'] ?? ''),
            'vehicle_lookup' => (string)($mapping['vehicle']['lookup'] ?? ''),
            'starting_point_lookup' => (string)($mapping['starting_point']['lookup'] ?? ''),
        ],
        'normalized_booking_safe' => [
            'source_system' => (string)($candidate['source_system'] ?? ''),
            'order_reference' => (string)($candidate['order_reference'] ?? ''),
            'order_status' => (string)($candidate['order_status'] ?? ''),
            'customer_name' => (string)($candidate['customer_name'] ?? ''),
            'driver_name' => (string)($candidate['driver_name'] ?? ''),
            'vehicle_plate' => (string)($candidate['vehicle_plate'] ?? ''),
            'started_at' => (string)($candidate['started_at'] ?? ''),
            'ended_at' => (string)($candidate['ended_at'] ?? ''),
            'boarding_point' => (string)($candidate['boarding_point'] ?? ''),
            'disembark_point' => (string)($candidate['disembark_point'] ?? ''),
        ],
        'edxeix_payload_safe' => [
            'broker' => (string)($payload['broker'] ?? ''),
            'lessor' => (string)($payload['lessor'] ?? ''),
            'lessee_name' => (string)($payload['lessee[name]'] ?? $payload['lessee_name'] ?? ''),
            'driver' => (string)($payload['driver'] ?? ''),
            'vehicle' => (string)($payload['vehicle'] ?? ''),
            'starting_point_id' => (string)($payload['starting_point_id'] ?? $payload['starting_point'] ?? ''),
            'boarding_point' => (string)($payload['boarding_point'] ?? ''),
            'disembark_point' => (string)($payload['disembark_point'] ?? ''),
            'started_at' => (string)($payload['started_at'] ?? ''),
            'ended_at' => (string)($payload['ended_at'] ?? ''),
            'price' => (string)($payload['price'] ?? ''),
        ],
        'message' => (string)($preview['message'] ?? ''),
    ];
}
