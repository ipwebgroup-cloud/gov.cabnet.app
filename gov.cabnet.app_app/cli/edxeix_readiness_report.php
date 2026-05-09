<?php
/**
 * gov.cabnet.app — EDXEIX readiness report v6.6.0
 *
 * Read-only CLI report for pre-live EDXEIX readiness.
 *
 * Safety guarantees:
 * - does not call EDXEIX
 * - does not create submission_jobs rows
 * - does not create submission_attempts rows
 * - does not issue AADE receipts
 * - does not print cookies, CSRF tokens, API keys, or private config values
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "CLI only\n";
    exit(1);
}

require_once '/home/cabnet/gov.cabnet.app_app/lib/edxeix_live_submit_gate.php';

$options = getopt('', [
    'limit::',
    'future-hours::',
    'past-minutes::',
    'include-receipt-only',
    'only-ready',
    'json',
    'help',
]);

if (array_key_exists('help', $options)) {
    echo "EDXEIX Readiness Report v6.6.0\n";
    echo "Usage:\n";
    echo "  php edxeix_readiness_report.php --future-hours=72 --past-minutes=60 --limit=50 --json\n";
    echo "  php edxeix_readiness_report.php --only-ready --json\n\n";
    echo "Read-only: analyzes candidate readiness; never submits to EDXEIX and never writes queue rows.\n";
    exit(0);
}

$limit = max(1, min(250, (int)($options['limit'] ?? 50)));
$futureHours = max(1, min(720, (int)($options['future-hours'] ?? 72)));
$pastMinutes = max(0, min(1440, (int)($options['past-minutes'] ?? 60)));
$includeReceiptOnly = array_key_exists('include-receipt-only', $options);
$onlyReady = array_key_exists('only-ready', $options);
$json = array_key_exists('json', $options);

$config = gov_bridge_load_config();
if (!empty($config['app']['timezone'])) {
    date_default_timezone_set((string)$config['app']['timezone']);
}

$out = [
    'ok' => false,
    'script' => 'cli/edxeix_readiness_report.php',
    'version' => 'v6.6.0',
    'generated_at' => date('c'),
    'mode' => [
        'read_only' => true,
        'future_hours' => $futureHours,
        'past_minutes' => $pastMinutes,
        'limit' => $limit,
        'include_receipt_only' => $includeReceiptOnly,
        'only_ready' => $onlyReady,
    ],
    'safety' => [
        'does_not_call_edxeix' => true,
        'does_not_issue_aade_receipts' => true,
        'does_not_create_submission_jobs' => true,
        'does_not_create_submission_attempts' => true,
        'does_not_print_session_cookies_or_tokens' => true,
    ],
    'live_config_summary' => [],
    'queue_counts' => [],
    'summary' => [],
    'items' => [],
    'next_safe_steps' => [],
    'error' => null,
];

try {
    $db = gov_bridge_db();
    $liveConfig = gov_live_load_config();
    $sessionState = gov_live_session_state($liveConfig);

    $jobsBefore = edxeix_report_count_table($db, 'submission_jobs');
    $attemptsBefore = edxeix_report_count_table($db, 'submission_attempts');

    $out['live_config_summary'] = [
        'live_submit_enabled' => !empty($liveConfig['live_submit_enabled']),
        'http_submit_enabled' => !empty($liveConfig['http_submit_enabled']),
        'edxeix_session_connected' => !empty($liveConfig['edxeix_session_connected']),
        'require_one_shot_lock' => !empty($liveConfig['require_one_shot_lock']),
        'allowed_booking_id_present' => trim((string)($liveConfig['allowed_booking_id'] ?? '')) !== '',
        'allowed_order_reference_present' => trim((string)($liveConfig['allowed_order_reference'] ?? '')) !== '',
        'submit_url_present' => trim((string)($liveConfig['edxeix_submit_url'] ?? '')) !== '',
        'session_file_exists' => !empty($sessionState['session_file_exists']),
        'session_ready' => !empty($sessionState['ready']),
        'session_updated_at' => $sessionState['updated_at'] ?? null,
        'session_placeholders_detected' => !empty($sessionState['placeholder_detected']),
    ];

    $rows = edxeix_report_candidate_rows($db, $futureHours, $pastMinutes, $limit, $includeReceiptOnly);

    $summary = [
        'rows_scanned' => count($rows),
        'items_returned' => 0,
        'preflight_ready' => 0,
        'live_submission_allowed_now' => 0,
        'blocked_receipt_only' => 0,
        'blocked_not_real_bolt' => 0,
        'blocked_lab_or_test' => 0,
        'blocked_terminal_or_past' => 0,
        'blocked_missing_started_at' => 0,
        'blocked_missing_mapping' => 0,
        'blocked_duplicate' => 0,
        'blocked_live_config_or_session' => 0,
        'other_blocked' => 0,
    ];

    $items = [];
    foreach ($rows as $row) {
        $analysis = gov_live_analyze_booking($db, $row, $liveConfig);
        $readiness = edxeix_report_readiness($analysis);

        if ($readiness['preflight_ready']) {
            $summary['preflight_ready']++;
        }
        if (!empty($analysis['live_submission_allowed'])) {
            $summary['live_submission_allowed_now']++;
        }

        foreach ($readiness['categories'] as $category) {
            if (array_key_exists($category, $summary)) {
                $summary[$category]++;
            } else {
                $summary['other_blocked']++;
            }
        }

        if ($onlyReady && !$readiness['preflight_ready']) {
            continue;
        }

        $items[] = edxeix_report_item($analysis, $readiness);
    }

    $jobsAfter = edxeix_report_count_table($db, 'submission_jobs');
    $attemptsAfter = edxeix_report_count_table($db, 'submission_attempts');
    $summary['items_returned'] = count($items);

    $out['queue_counts'] = [
        'submission_jobs_before' => $jobsBefore,
        'submission_jobs_after' => $jobsAfter,
        'submission_attempts_before' => $attemptsBefore,
        'submission_attempts_after' => $attemptsAfter,
        'queues_unchanged' => $jobsBefore === $jobsAfter && $attemptsBefore === $attemptsAfter,
    ];

    $out['summary'] = $summary;
    $out['items'] = $items;
    $out['next_safe_steps'] = [
        'Keep EDXEIX live submission disabled until a real future Bolt API booking shows preflight_ready=true.',
        'For a ready booking, run live_submit_one_booking.php with --analyze-only before any live action.',
        'Do not stage or submit receipt-only, mail-only, cancelled, finished, expired, historical, duplicate, or unmapped bookings.',
        'Only enable one-shot live submission for one exact eligible future booking after Andreas explicitly approves.',
    ];
    $out['ok'] = true;
} catch (Throwable $e) {
    $out['error'] = $e->getMessage();
}

if ($json) {
    echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} else {
    edxeix_report_print_text($out);
}

exit(!empty($out['ok']) ? 0 : 1);

function edxeix_report_count_table(mysqli $db, string $table): int
{
    if (!gov_bridge_table_exists($db, $table)) {
        return 0;
    }
    $row = gov_bridge_fetch_one($db, 'SELECT COUNT(*) AS c FROM ' . gov_bridge_quote_identifier($table));
    return (int)($row['c'] ?? 0);
}

/** @return array<int,array<string,mixed>> */
function edxeix_report_candidate_rows(mysqli $db, int $futureHours, int $pastMinutes, int $limit, bool $includeReceiptOnly): array
{
    if (!gov_bridge_table_exists($db, 'normalized_bookings')) {
        return [];
    }

    $columns = gov_bridge_table_columns($db, 'normalized_bookings');
    $where = [];
    $params = [];

    if (isset($columns['started_at'])) {
        $where[] = '(started_at IS NULL OR started_at BETWEEN DATE_SUB(NOW(), INTERVAL ? MINUTE) AND DATE_ADD(NOW(), INTERVAL ? HOUR))';
        $params[] = (string)$pastMinutes;
        $params[] = (string)$futureHours;
    }

    if (!$includeReceiptOnly) {
        $receiptConds = [];
        foreach (['source_system', 'source_type', 'source'] as $col) {
            if (isset($columns[$col])) {
                $receiptConds[] = "LOWER(COALESCE(`{$col}`,'')) <> 'bolt_mail'";
                $receiptConds[] = "LOWER(COALESCE(`{$col}`,'')) <> 'mail'";
            }
        }
        if (isset($columns['order_reference'])) {
            $receiptConds[] = "COALESCE(order_reference,'') NOT LIKE 'mail:%'";
        }
        if ($receiptConds) {
            $where[] = '(' . implode(' AND ', $receiptConds) . ')';
        }
    }

    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $orderCol = isset($columns['started_at']) ? 'started_at' : (isset($columns['updated_at']) ? 'updated_at' : 'id');
    $sql = 'SELECT * FROM normalized_bookings ' . $whereSql . ' ORDER BY `' . $orderCol . '` ASC, id ASC LIMIT ' . (int)$limit;

    return gov_bridge_fetch_all($db, $sql, $params);
}

/** @return array{preflight_ready:bool,categories:array<int,string>,blockers:array<int,string>,technical_blockers:array<int,string>,live_blockers:array<int,string>,duplicate_blockers:array<int,string>} */
function edxeix_report_readiness(array $analysis): array
{
    $technical = array_values(array_unique(array_map('strval', (array)($analysis['technical_blockers'] ?? []))));
    $live = array_values(array_unique(array_map('strval', (array)($analysis['live_blockers'] ?? []))));
    $duplicate = array_values(array_unique(array_map('strval', (array)($analysis['duplicate_check']['blockers'] ?? []))));
    $futureGuard = !empty($analysis['future_guard_passed']) || !empty($analysis['future_guard_passes']);

    $categories = [];
    if (!empty($analysis['is_receipt_only_booking']) || in_array('receipt_only_booking_blocked_from_edxeix', $live, true)) {
        $categories[] = 'blocked_receipt_only';
    }
    if (empty($analysis['is_real_bolt'])) {
        $categories[] = 'blocked_not_real_bolt';
    }
    if (!empty($analysis['is_lab_or_test']) || in_array('lab_or_test_booking_blocked', $live, true)) {
        $categories[] = 'blocked_lab_or_test';
    }
    if (!empty($analysis['terminal_status']) || in_array('terminal_order_status', $technical, true)) {
        $categories[] = 'blocked_terminal_or_past';
    }
    if (in_array('missing_started_at', $technical, true)) {
        $categories[] = 'blocked_missing_started_at';
    }
    foreach ($technical as $tech) {
        if (str_starts_with($tech, 'started_at_not_')) {
            $categories[] = 'blocked_terminal_or_past';
            break;
        }
    }
    if (array_intersect($technical, ['driver_not_mapped', 'vehicle_not_mapped', 'starting_point_not_mapped'])) {
        $categories[] = 'blocked_missing_mapping';
    }
    if ($duplicate !== []) {
        $categories[] = 'blocked_duplicate';
    }
    if (array_intersect($live, [
        'live_submit_config_disabled',
        'http_submit_config_disabled',
        'edxeix_session_not_connected',
        'edxeix_session_not_ready',
        'edxeix_submit_url_missing',
        'one_shot_live_lock_missing',
        'booking_not_explicitly_allowed',
        'order_reference_not_explicitly_allowed',
    ])) {
        $categories[] = 'blocked_live_config_or_session';
    }

    $preflightReady = !empty($analysis['is_real_bolt'])
        && empty($analysis['is_receipt_only_booking'])
        && empty($analysis['is_lab_or_test'])
        && empty($analysis['terminal_status'])
        && $futureGuard
        && !empty($analysis['technical_payload_valid'])
        && $duplicate === [];

    $blockers = array_values(array_unique(array_merge($technical, $duplicate, $live)));

    return [
        'preflight_ready' => $preflightReady,
        'categories' => array_values(array_unique($categories)),
        'blockers' => $blockers,
        'technical_blockers' => $technical,
        'live_blockers' => $live,
        'duplicate_blockers' => $duplicate,
    ];
}

/** @return array<string,mixed> */
function edxeix_report_item(array $analysis, array $readiness): array
{
    $preview = is_array($analysis['edxeix_payload_preview'] ?? null) ? $analysis['edxeix_payload_preview'] : [];
    $bookingId = (int)($analysis['booking_id'] ?? 0);

    return [
        'booking_id' => $bookingId,
        'order_reference' => (string)($analysis['order_reference'] ?? ''),
        'source_system' => (string)($analysis['source_system'] ?? ''),
        'order_status' => (string)($analysis['status'] ?? ''),
        'started_at' => (string)($analysis['started_at'] ?? ''),
        'driver_name' => (string)($analysis['driver_name'] ?? ''),
        'vehicle_plate' => (string)($analysis['plate'] ?? ''),
        'is_real_bolt' => !empty($analysis['is_real_bolt']),
        'is_receipt_only_booking' => !empty($analysis['is_receipt_only_booking']),
        'is_lab_or_test' => !empty($analysis['is_lab_or_test']),
        'terminal_status' => !empty($analysis['terminal_status']),
        'future_guard_passed' => !empty($analysis['future_guard_passed']) || !empty($analysis['future_guard_passes']),
        'technical_payload_valid' => !empty($analysis['technical_payload_valid']),
        'preflight_ready' => !empty($readiness['preflight_ready']),
        'live_submission_allowed_now' => !empty($analysis['live_submission_allowed']),
        'categories' => $readiness['categories'],
        'technical_blockers' => $readiness['technical_blockers'],
        'duplicate_blockers' => $readiness['duplicate_blockers'],
        'live_blockers' => $readiness['live_blockers'],
        'payload_hash' => (string)($analysis['payload_hash'] ?? ''),
        'payload_preview_safe' => edxeix_report_safe_payload_preview($preview),
        'recommended_analyze_command' => $bookingId > 0
            ? '/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/live_submit_one_booking.php --booking-id=' . $bookingId . ' --analyze-only'
            : null,
    ];
}

/** @return array<string,mixed> */
function edxeix_report_safe_payload_preview(array $payload): array
{
    unset($payload['_token'], $payload['csrf'], $payload['csrf_token'], $payload['cookie'], $payload['cookie_header']);
    $lessee = is_array($payload['lessee'] ?? null) ? $payload['lessee'] : [];

    return [
        'broker' => (string)($payload['broker'] ?? ''),
        'lessor' => (string)($payload['lessor'] ?? ''),
        'lessee_name' => (string)($lessee['name'] ?? ''),
        'driver' => (string)($payload['driver'] ?? ''),
        'vehicle' => (string)($payload['vehicle'] ?? ''),
        'starting_point' => (string)($payload['starting_point'] ?? $payload['starting_point_id'] ?? ''),
        'boarding_point' => (string)($payload['boarding_point'] ?? ''),
        'disembark_point' => (string)($payload['disembark_point'] ?? ''),
        'started_at' => (string)($payload['started_at'] ?? ''),
        'ended_at' => (string)($payload['ended_at'] ?? ''),
        'price' => (string)($payload['price'] ?? ''),
        'mapping_status' => is_array($payload['_mapping_status'] ?? null) ? $payload['_mapping_status'] : [],
    ];
}

function edxeix_report_print_text(array $out): void
{
    echo "EDXEIX Readiness Report " . (string)($out['version'] ?? '') . PHP_EOL;
    echo "Generated: " . (string)($out['generated_at'] ?? '') . PHP_EOL;
    echo "Read-only: yes" . PHP_EOL . PHP_EOL;

    if (empty($out['ok'])) {
        echo "ERROR: " . (string)($out['error'] ?? 'unknown error') . PHP_EOL;
        return;
    }

    $summary = is_array($out['summary'] ?? null) ? $out['summary'] : [];
    $queues = is_array($out['queue_counts'] ?? null) ? $out['queue_counts'] : [];

    echo "Summary" . PHP_EOL;
    foreach ($summary as $key => $value) {
        echo "- {$key}: {$value}" . PHP_EOL;
    }
    echo PHP_EOL . "Queues" . PHP_EOL;
    foreach ($queues as $key => $value) {
        echo "- {$key}: " . (is_bool($value) ? ($value ? 'true' : 'false') : (string)$value) . PHP_EOL;
    }
    echo PHP_EOL;

    foreach ((array)($out['items'] ?? []) as $item) {
        echo '# ' . (string)($item['booking_id'] ?? '') . ' | ' . (string)($item['order_reference'] ?? '') . ' | ' . (string)($item['started_at'] ?? '') . PHP_EOL;
        echo '  preflight_ready: ' . (!empty($item['preflight_ready']) ? 'yes' : 'no') . PHP_EOL;
        $cats = (array)($item['categories'] ?? []);
        if ($cats) {
            echo '  categories: ' . implode(', ', array_map('strval', $cats)) . PHP_EOL;
        }
    }
}
