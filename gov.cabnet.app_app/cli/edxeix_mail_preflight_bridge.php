<?php
/**
 * gov.cabnet.app — EDXEIX mail preflight bridge v6.8.1
 *
 * Purpose:
 * - Find future pre-ride Bolt email intake rows that are not yet linked to a
 *   normalized booking.
 * - Preview whether each row can safely create a local normalized EDXEIX
 *   preflight booking.
 * - Create one normalized booking only when --create and a numeric --intake-id are explicitly supplied.
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


$argvList = $_SERVER['argv'] ?? [];
$rawJsonRequested = in_array('--json', $argvList, true);
$allowedLongOptions = ['limit', 'intake-id', 'create', 'json', 'help'];
$optionErrors = [];
foreach (array_slice($argvList, 1) as $arg) {
    if (str_starts_with($arg, '--')) {
        $name = substr($arg, 2);
        $eqPos = strpos($name, '=');
        if ($eqPos !== false) {
            $name = substr($name, 0, $eqPos);
        }
        if (!in_array($name, $allowedLongOptions, true)) {
            $optionErrors[] = 'unknown_or_malformed_option:' . $arg;
        }
    } elseif ($arg !== '') {
        $optionErrors[] = 'unexpected_positional_argument:' . $arg;
    }
}

$options = getopt('', [
    'limit::',
    'intake-id::',
    'create',
    'json',
    'help',
]);

if (isset($options['help'])) {
    echo "EDXEIX Mail Preflight Bridge v6.8.1\n";
    echo "Usage:\n";
    echo "  php edxeix_mail_preflight_bridge.php --json\n";
    echo "  php edxeix_mail_preflight_bridge.php --intake-id=123 --json\n";
    echo "  php edxeix_mail_preflight_bridge.php --intake-id=123 --create --json\n";
    echo "Default mode is preview only. --create requires a numeric --intake-id and writes at most one normalized preflight booking.\n";
    echo "Safety: no EDXEIX calls, no AADE calls, no submission jobs/attempts.\n";
    exit(0);
}

$json = array_key_exists('json', $options) || $rawJsonRequested;

if (isset($options['limit']) && !preg_match('/^[0-9]+$/', (string)$options['limit'])) {
    $optionErrors[] = 'invalid_limit_must_be_numeric';
}
if (isset($options['intake-id']) && !preg_match('/^[1-9][0-9]*$/', (string)$options['intake-id'])) {
    $optionErrors[] = 'invalid_intake_id_must_be_positive_integer';
}

$limit = max(1, min(100, (int)($options['limit'] ?? 20)));
$intakeId = isset($options['intake-id']) && preg_match('/^[1-9][0-9]*$/', (string)$options['intake-id']) ? (int)$options['intake-id'] : 0;
$create = array_key_exists('create', $options);

if ($create && $intakeId <= 0) {
    $optionErrors[] = 'create_requires_explicit_numeric_intake_id';
}

if (!empty($config->get('app.timezone', ''))) {
    date_default_timezone_set((string)$config->get('app.timezone', 'Europe/Athens'));
}

$futureGuardMinutes = (int)$config->get('edxeix.future_start_guard_minutes', 30);
$futureGuardMinutes = max(0, min(1440, $futureGuardMinutes));

$out = [
    'ok' => false,
    'script' => 'cli/edxeix_mail_preflight_bridge.php',
    'version' => 'v6.8.1',
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
        'clears_old_no_edxeix_block_for_exact_future_mail_booking' => true,
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

if ($optionErrors !== []) {
    $out['ok'] = false;
    $out['error'] = implode(', ', array_values(array_unique($optionErrors)));
    $out['summary']['errors'] = count(array_unique($optionErrors));
    $out['queue_counts'] = [
        'submission_jobs_before' => null,
        'submission_jobs_after' => null,
        'submission_attempts_before' => null,
        'submission_attempts_after' => null,
        'queues_unchanged' => true,
    ];
    $out['next_safe_steps'] = [
        'Use --create only with one explicit numeric --intake-id, for example: --intake-id=123 --create --json.',
        'Do not use placeholders such as --intake-id=ID.',
        'Run preview mode first, then create exactly one reviewed intake row.',
    ];

    if ($json) {
        echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    } else {
        echo 'EDXEIX Mail Preflight Bridge v6.8.1' . PHP_EOL;
        echo 'ERROR: ' . $out['error'] . PHP_EOL;
    }
    exit(1);
}

try {
    $beforeJobs = countRows($db, 'submission_jobs');
    $beforeAttempts = countRows($db, 'submission_attempts');

    $bridge = new BoltMailIntakeBookingBridge($db, $config);
    $rows = $intakeId > 0 ? findSingleIntakeRow($db, $intakeId) : listOpenFutureMailRows($db, $limit, $futureGuardMinutes);

    $out['summary']['candidate_rows'] = count($rows);

    if ($intakeId > 0 && count($rows) === 0) {
        $out['summary']['errors']++;
        $out['error'] = 'requested_intake_id_not_found';
        $out['items'][] = [
            'intake_id' => $intakeId,
            'status' => 'error',
            'message' => 'Requested intake row was not found. No booking was created or linked.',
        ];
    }

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

            if ($item['booking_id'] !== null) {
                $clearResult = clearOldMailNoEdxeixBlockForExactFutureBooking($db, $item['booking_id']);
                $item['edxeix_live_eligibility_update'] = $clearResult;
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
        'If preview_ready is greater than zero, review the item before using --create with one explicit numeric --intake-id.',
        'After creating a normalized mail-derived booking, run edxeix_readiness_report.php --only-ready --json.',
        'For a ready mail-derived booking, run live_submit_one_booking.php --booking-id=ID --analyze-only before any live action.',
        'Do not enable live EDXEIX submission or one-shot locks unless Andreas explicitly approves one exact future booking.',
        'Never run --create without a reviewed numeric intake id.',
        'If requested_intake_id_not_found appears, run preview mode and choose an intake id from the returned items.',
    ];

    $out['ok'] = $out['summary']['errors'] === 0 && !empty($out['queue_counts']['queues_unchanged']);
} catch (Throwable $e) {
    $out['error'] = $e->getMessage();
}

if ($json) {
    echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} else {
    echo 'EDXEIX Mail Preflight Bridge v6.8.1' . PHP_EOL;
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


function clearOldMailNoEdxeixBlockForExactFutureBooking($db, int $bookingId): array
{
    if ($bookingId <= 0 || !tableExists($db, 'normalized_bookings')) {
        return [
            'ok' => false,
            'updated_rows' => 0,
            'message' => 'normalized booking not available',
        ];
    }

    $sql = "UPDATE normalized_bookings
            SET never_submit_live = 0,
                live_submit_block_reason = NULL,
                notes = CONCAT(
                    COALESCE(notes, ''),
                    '\nEDXEIX PREFLIGHT: cleared old no_edxeix/aade_receipt_only block for exact future pre-ride mail booking at ',
                    NOW(),
                    '. Source policy: EDXEIX uses pre-ride Bolt email only; AADE remains pickup-worker only.'
                ),
                updated_at = NOW()
            WHERE id = ?
              AND source_system = 'bolt_mail'
              AND order_status = 'confirmed'
              AND started_at > NOW()
              AND (
                    never_submit_live = 1
                    OR LOWER(COALESCE(live_submit_block_reason,'')) LIKE '%no_edxeix%'
                    OR LOWER(COALESCE(live_submit_block_reason,'')) LIKE '%aade_receipt_only%'
                    OR LOWER(COALESCE(live_submit_block_reason,'')) LIKE '%receipt_only%'
              )
            LIMIT 1";

    $updated = $db->execute($sql, [$bookingId], 'i');

    return [
        'ok' => true,
        'updated_rows' => (int)$updated,
        'message' => $updated > 0
            ? 'Old no_edxeix/aade_receipt_only block cleared for this exact future mail booking.'
            : 'No legacy live block needed clearing.',
    ];
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
