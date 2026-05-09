<?php
/**
 * gov.cabnet.app — EDXEIX readiness report v6.6.1
 *
 * Read-only CLI report for pre-live EDXEIX readiness.
 *
 * v6.6.1 source policy:
 * - EDXEIX source is strictly pre-ride Bolt email intake / mail-derived normalized bookings.
 * - Bolt API pickup/finalized data is not an EDXEIX submission source.
 * - AADE invoice issuing remains strictly Bolt API pickup timestamp worker only.
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
    'include-non-mail',
    'only-ready',
    'json',
    'help',
]);

if (array_key_exists('help', $options)) {
    echo "EDXEIX Readiness Report v6.6.1\n";
    echo "Usage:\n";
    echo "  php edxeix_readiness_report.php --future-hours=72 --past-minutes=60 --limit=50 --json\n";
    echo "  php edxeix_readiness_report.php --only-ready --json\n";
    echo "  php edxeix_readiness_report.php --include-non-mail --json   # diagnostic only\n\n";
    echo "Read-only: analyzes pre-ride Bolt email readiness; never submits to EDXEIX and never writes queue rows.\n";
    exit(0);
}

$limit = max(1, min(250, (int)($options['limit'] ?? 50)));
$futureHours = max(1, min(720, (int)($options['future-hours'] ?? 72)));
$pastMinutes = max(0, min(1440, (int)($options['past-minutes'] ?? 60)));
$includeReceiptOnly = array_key_exists('include-receipt-only', $options);
$includeNonMail = array_key_exists('include-non-mail', $options);
$onlyReady = array_key_exists('only-ready', $options);
$json = array_key_exists('json', $options);

$config = gov_bridge_load_config();
if (!empty($config['app']['timezone'])) {
    date_default_timezone_set((string)$config['app']['timezone']);
}

$out = [
    'ok' => false,
    'script' => 'cli/edxeix_readiness_report.php',
    'version' => 'v6.6.1',
    'generated_at' => date('c'),
    'source_policy' => [
        'edxeix_submission_source' => 'pre_ride_bolt_email_only',
        'edxeix_uses_bolt_api_as_source' => false,
        'aade_invoice_source' => 'bolt_api_pickup_timestamp_worker_only',
        'pre_ride_email_may_issue_aade' => false,
    ],
    'mode' => [
        'read_only' => true,
        'future_hours' => $futureHours,
        'past_minutes' => $pastMinutes,
        'limit' => $limit,
        'include_receipt_only' => $includeReceiptOnly,
        'include_non_mail_diagnostic' => $includeNonMail,
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
    'mail_intake_summary' => [],
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

    $out['mail_intake_summary'] = edxeix_report_mail_intake_summary($db);
    $rows = edxeix_report_candidate_rows($db, $futureHours, $pastMinutes, $limit, $includeReceiptOnly, $includeNonMail);

    $summary = [
        'rows_scanned' => count($rows),
        'items_returned' => 0,
        'preflight_ready' => 0,
        'live_submission_allowed_now' => 0,
        'blocked_wrong_source' => 0,
        'blocked_receipt_only' => 0,
        'blocked_never_submit_live' => 0,
        'blocked_lab_or_test' => 0,
        'blocked_terminal_or_past' => 0,
        'blocked_missing_started_at' => 0,
        'blocked_missing_mapping' => 0,
        'blocked_duplicate' => 0,
        'live_activation_blocked' => 0,
        'other_blocked' => 0,
    ];

    $items = [];
    foreach ($rows as $row) {
        $analysis = gov_live_analyze_booking($db, $row, $liveConfig);
        $readiness = edxeix_report_readiness($row, $analysis);

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

        $items[] = edxeix_report_item($row, $analysis, $readiness);
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
        'Keep EDXEIX live submission disabled until a future pre-ride Bolt email creates a mail-derived normalized booking with preflight_ready=true.',
        'If mail_intake_summary.future_unlinked_candidates is greater than zero, preview/create the local normalized preflight booking through the existing mail intake bridge before live review.',
        'For a ready mail-derived booking, run live_submit_one_booking.php with --analyze-only before any live action.',
        'Do not stage or submit Bolt API finished rides, receipt-only recovery rows, mail-only past rows, cancelled, finished, expired, historical, duplicate, or unmapped bookings.',
        'Only enable one-shot live submission for one exact eligible future pre-ride email booking after Andreas explicitly approves.',
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

/** @return array<string,int|bool> */
function edxeix_report_mail_intake_summary(mysqli $db): array
{
    $empty = [
        'table_exists' => false,
        'total_rows' => 0,
        'parsed_rows' => 0,
        'future_candidates' => 0,
        'future_unlinked_candidates' => 0,
        'future_linked_candidates' => 0,
        'blocked_timing_rows' => 0,
        'synthetic_or_do_not_submit_rows' => 0,
    ];

    if (!gov_bridge_table_exists($db, 'bolt_mail_intake')) {
        return $empty;
    }

    $row = gov_bridge_fetch_one($db, "SELECT
        COUNT(*) AS total_rows,
        SUM(CASE WHEN parse_status='parsed' THEN 1 ELSE 0 END) AS parsed_rows,
        SUM(CASE WHEN parse_status='parsed' AND safety_status='future_candidate' THEN 1 ELSE 0 END) AS future_candidates,
        SUM(CASE WHEN parse_status='parsed' AND safety_status='future_candidate' AND linked_booking_id IS NULL THEN 1 ELSE 0 END) AS future_unlinked_candidates,
        SUM(CASE WHEN parse_status='parsed' AND safety_status='future_candidate' AND linked_booking_id IS NOT NULL THEN 1 ELSE 0 END) AS future_linked_candidates,
        SUM(CASE WHEN parse_status='parsed' AND safety_status IN ('blocked_past','blocked_too_soon') THEN 1 ELSE 0 END) AS blocked_timing_rows,
        SUM(CASE WHEN UPPER(COALESCE(customer_name,'')) LIKE '%CABNET TEST%' OR UPPER(COALESCE(customer_name,'')) LIKE '%DO NOT SUBMIT%' THEN 1 ELSE 0 END) AS synthetic_or_do_not_submit_rows
        FROM bolt_mail_intake") ?: [];

    return [
        'table_exists' => true,
        'total_rows' => (int)($row['total_rows'] ?? 0),
        'parsed_rows' => (int)($row['parsed_rows'] ?? 0),
        'future_candidates' => (int)($row['future_candidates'] ?? 0),
        'future_unlinked_candidates' => (int)($row['future_unlinked_candidates'] ?? 0),
        'future_linked_candidates' => (int)($row['future_linked_candidates'] ?? 0),
        'blocked_timing_rows' => (int)($row['blocked_timing_rows'] ?? 0),
        'synthetic_or_do_not_submit_rows' => (int)($row['synthetic_or_do_not_submit_rows'] ?? 0),
    ];
}

/** @return array<int,array<string,mixed>> */
function edxeix_report_candidate_rows(mysqli $db, int $futureHours, int $pastMinutes, int $limit, bool $includeReceiptOnly, bool $includeNonMail): array
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

    if (!$includeNonMail) {
        $mailConds = edxeix_report_mail_source_sql_conditions($columns);
        if ($mailConds) {
            $where[] = '(' . implode(' OR ', $mailConds) . ')';
        } else {
            return [];
        }
    }

    if (!$includeReceiptOnly) {
        $receiptBlockConds = [];
        if (isset($columns['live_submit_block_reason'])) {
            foreach (['receipt_only', 'aade_receipt_only', 'no_edxeix_job', 'emergency_aade_receipt_only'] as $needle) {
                $receiptBlockConds[] = "LOWER(COALESCE(live_submit_block_reason,'')) NOT LIKE '%" . $needle . "%'";
            }
        }
        if (isset($columns['notes'])) {
            foreach (['receipt_only', 'aade receipt recovery', 'late bolt mail aade receipt recovery', 'not_edxeix_live_safe'] as $needle) {
                $receiptBlockConds[] = "LOWER(COALESCE(notes,'')) NOT LIKE '%" . $needle . "%'";
            }
        }
        if ($receiptBlockConds) {
            $where[] = '(' . implode(' AND ', $receiptBlockConds) . ')';
        }
    }

    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $orderCol = isset($columns['started_at']) ? 'started_at' : (isset($columns['updated_at']) ? 'updated_at' : 'id');
    $sql = 'SELECT * FROM normalized_bookings ' . $whereSql . ' ORDER BY ' . gov_bridge_quote_identifier($orderCol) . ' ASC, id ASC LIMIT ' . (int)$limit;

    return gov_bridge_fetch_all($db, $sql, $params);
}

/** @param array<string,array<string,mixed>> $columns @return array<int,string> */
function edxeix_report_mail_source_sql_conditions(array $columns): array
{
    $conds = [];
    foreach (['source_system', 'source_type', 'source'] as $col) {
        if (isset($columns[$col])) {
            $quoted = gov_bridge_quote_identifier($col);
            $conds[] = "LOWER(COALESCE({$quoted},'')) IN ('bolt_mail','mail','pre_ride_email','bolt_pre_ride_email')";
        }
    }
    foreach (['order_reference', 'external_order_id', 'external_reference', 'source_trip_id', 'source_trip_reference', 'source_booking_id'] as $col) {
        if (isset($columns[$col])) {
            $quoted = gov_bridge_quote_identifier($col);
            $conds[] = "LOWER(COALESCE({$quoted},'')) LIKE 'mail:%'";
            $conds[] = "LOWER(COALESCE({$quoted},'')) LIKE 'mail-intake-%'";
        }
    }
    return $conds;
}

/** @return array{preflight_ready:bool,categories:array<int,string>,policy_blockers:array<int,string>,technical_blockers:array<int,string>,live_blockers:array<int,string>,duplicate_blockers:array<int,string>,is_pre_ride_mail_source:bool,is_receipt_only_or_recovery:bool,never_submit_live:bool,is_lab_or_test:bool} */
function edxeix_report_readiness(array $row, array $analysis): array
{
    $technical = array_values(array_unique(array_map('strval', (array)($analysis['technical_blockers'] ?? []))));
    $live = array_values(array_unique(array_map('strval', (array)($analysis['live_blockers'] ?? []))));
    $duplicate = array_values(array_unique(array_map('strval', (array)($analysis['duplicate_check']['blockers'] ?? []))));
    $futureGuard = !empty($analysis['future_guard_passed']) || !empty($analysis['future_guard_passes']);

    $isMailSource = edxeix_report_is_pre_ride_mail_source($row, $analysis);
    $receiptOnly = edxeix_report_is_receipt_only_or_recovery($row);
    $neverSubmitLive = edxeix_report_bool_value($row['never_submit_live'] ?? false);
    $isLabOrTest = edxeix_report_is_lab_or_test_for_edxeix($row, $analysis);
    $terminal = !empty($analysis['terminal_status']) || in_array('terminal_order_status', $technical, true);

    $categories = [];
    $policyBlockers = [];

    if (!$isMailSource) {
        $categories[] = 'blocked_wrong_source';
        $policyBlockers[] = 'edxeix_source_must_be_pre_ride_bolt_email';
    }
    if ($receiptOnly) {
        $categories[] = 'blocked_receipt_only';
        $policyBlockers[] = 'receipt_only_or_late_recovery_mail_row_blocked_from_edxeix';
    }
    if ($neverSubmitLive) {
        $categories[] = 'blocked_never_submit_live';
        $policyBlockers[] = 'never_submit_live_flag_set';
    }
    if ($isLabOrTest) {
        $categories[] = 'blocked_lab_or_test';
        $policyBlockers[] = 'lab_or_test_booking_blocked';
    }
    if ($terminal) {
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
        $categories[] = 'live_activation_blocked';
    }

    $preflightReady = $isMailSource
        && !$receiptOnly
        && !$neverSubmitLive
        && !$isLabOrTest
        && !$terminal
        && $futureGuard
        && !empty($analysis['technical_payload_valid'])
        && $duplicate === [];

    $blockers = array_values(array_unique(array_merge($policyBlockers, $technical, $duplicate, $live)));

    return [
        'preflight_ready' => $preflightReady,
        'categories' => array_values(array_unique($categories)),
        'policy_blockers' => array_values(array_unique($policyBlockers)),
        'blockers' => $blockers,
        'technical_blockers' => $technical,
        'live_blockers' => $live,
        'duplicate_blockers' => $duplicate,
        'is_pre_ride_mail_source' => $isMailSource,
        'is_receipt_only_or_recovery' => $receiptOnly,
        'never_submit_live' => $neverSubmitLive,
        'is_lab_or_test' => $isLabOrTest,
    ];
}

function edxeix_report_is_pre_ride_mail_source(array $row, array $analysis): bool
{
    $sourceValues = [];
    foreach (['source_system', 'source_type', 'source'] as $key) {
        if (array_key_exists($key, $row)) {
            $sourceValues[] = strtolower(trim((string)$row[$key]));
        }
    }
    if (!empty($analysis['source_system'])) {
        $sourceValues[] = strtolower(trim((string)$analysis['source_system']));
    }
    foreach ($sourceValues as $source) {
        if (in_array($source, ['bolt_mail', 'mail', 'pre_ride_email', 'bolt_pre_ride_email'], true)) {
            return true;
        }
    }

    foreach (['order_reference', 'external_order_id', 'external_reference', 'source_trip_id', 'source_trip_reference', 'source_booking_id'] as $key) {
        $value = strtolower(trim((string)($row[$key] ?? '')));
        if (str_starts_with($value, 'mail:') || str_starts_with($value, 'mail-intake-')) {
            return true;
        }
    }

    return false;
}

function edxeix_report_is_receipt_only_or_recovery(array $row): bool
{
    $haystacks = [];
    foreach (['live_submit_block_reason', 'notes', 'raw_payload_json', 'normalized_payload_json'] as $key) {
        if (isset($row[$key])) {
            $haystacks[] = strtolower((string)$row[$key]);
        }
    }

    foreach ($haystacks as $text) {
        foreach ([
            'receipt_only',
            'aade_receipt_only',
            'no_edxeix_job',
            'emergency_aade_receipt_only',
            'late bolt mail aade receipt recovery',
            'late_bolt_mail_receipt_recovery',
            'not_edxeix_live_safe',
        ] as $needle) {
            if ($text !== '' && str_contains($text, $needle)) {
                return true;
            }
        }
    }

    return false;
}

function edxeix_report_is_lab_or_test_for_edxeix(array $row, array $analysis): bool
{
    if (edxeix_report_bool_value($row['is_test_booking'] ?? false)) {
        return true;
    }

    $source = strtolower(trim((string)($row['source_system'] ?? $row['source'] ?? $analysis['source_system'] ?? '')));
    $ref = strtoupper(trim((string)($row['order_reference'] ?? $analysis['order_reference'] ?? '')));

    return str_contains($source, 'lab') || str_starts_with($ref, 'LAB-');
}

function edxeix_report_bool_value($value): bool
{
    if (is_bool($value)) {
        return $value;
    }
    return in_array(strtolower(trim((string)$value)), ['1', 'true', 'yes', 'on'], true);
}

/** @return array<string,mixed> */
function edxeix_report_item(array $row, array $analysis, array $readiness): array
{
    $preview = is_array($analysis['edxeix_payload_preview'] ?? null) ? $analysis['edxeix_payload_preview'] : [];
    $bookingId = (int)($analysis['booking_id'] ?? ($row['id'] ?? 0));

    return [
        'booking_id' => $bookingId,
        'order_reference' => (string)($analysis['order_reference'] ?? ($row['order_reference'] ?? '')),
        'edxeix_data_source' => !empty($readiness['is_pre_ride_mail_source']) ? 'pre_ride_bolt_email' : 'blocked_non_mail_source',
        'aade_data_source' => 'not_this_report_bolt_api_pickup_timestamp_worker_only',
        'source_system' => (string)($analysis['source_system'] ?? ($row['source_system'] ?? '')),
        'order_status' => (string)($analysis['status'] ?? ($row['order_status'] ?? '')),
        'started_at' => (string)($analysis['started_at'] ?? ($row['started_at'] ?? '')),
        'driver_name' => (string)($analysis['driver_name'] ?? ($row['driver_name'] ?? '')),
        'vehicle_plate' => (string)($analysis['plate'] ?? ($row['vehicle_plate'] ?? '')),
        'is_pre_ride_mail_source' => !empty($readiness['is_pre_ride_mail_source']),
        'is_receipt_only_or_recovery' => !empty($readiness['is_receipt_only_or_recovery']),
        'never_submit_live' => !empty($readiness['never_submit_live']),
        'live_submit_block_reason' => (string)($row['live_submit_block_reason'] ?? ''),
        'is_lab_or_test' => !empty($readiness['is_lab_or_test']),
        'terminal_status' => !empty($analysis['terminal_status']),
        'future_guard_passed' => !empty($analysis['future_guard_passed']) || !empty($analysis['future_guard_passes']),
        'technical_payload_valid' => !empty($analysis['technical_payload_valid']),
        'preflight_ready' => !empty($readiness['preflight_ready']),
        'live_submission_allowed_now' => !empty($analysis['live_submission_allowed']),
        'categories' => $readiness['categories'],
        'policy_blockers' => $readiness['policy_blockers'],
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
        'lessee_name' => (string)($lessee['name'] ?? $payload['lessee[name]'] ?? ''),
        'driver' => (string)($payload['driver'] ?? ''),
        'vehicle' => (string)($payload['vehicle'] ?? ''),
        'starting_point' => (string)($payload['starting_point'] ?? $payload['starting_point_id'] ?? ''),
        'boarding_point' => (string)($payload['boarding_point'] ?? ''),
        'disembark_point' => (string)($payload['disembark_point'] ?? ''),
        'drafted_at' => (string)($payload['drafted_at'] ?? ''),
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
    echo "Read-only: yes" . PHP_EOL;
    echo "EDXEIX source: pre-ride Bolt email only" . PHP_EOL;
    echo "AADE source: Bolt API pickup timestamp worker only" . PHP_EOL . PHP_EOL;

    if (empty($out['ok'])) {
        echo "ERROR: " . (string)($out['error'] ?? 'unknown error') . PHP_EOL;
        return;
    }

    $summary = is_array($out['summary'] ?? null) ? $out['summary'] : [];
    $intake = is_array($out['mail_intake_summary'] ?? null) ? $out['mail_intake_summary'] : [];
    $queues = is_array($out['queue_counts'] ?? null) ? $out['queue_counts'] : [];

    echo "Mail intake" . PHP_EOL;
    foreach ($intake as $key => $value) {
        echo "- {$key}: " . (is_bool($value) ? ($value ? 'true' : 'false') : (string)$value) . PHP_EOL;
    }
    echo PHP_EOL . "Summary" . PHP_EOL;
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
        echo '  source: ' . (string)($item['edxeix_data_source'] ?? '') . PHP_EOL;
        echo '  preflight_ready: ' . (!empty($item['preflight_ready']) ? 'yes' : 'no') . PHP_EOL;
        $cats = (array)($item['categories'] ?? []);
        if ($cats) {
            echo '  categories: ' . implode(', ', array_map('strval', $cats)) . PHP_EOL;
        }
    }
}
