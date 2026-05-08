<?php
/**
 * gov.cabnet.app — EDXEIX pre-live audit v6.3.0
 *
 * Read-only CLI for getting closer to live EDXEIX submission without submitting.
 * It analyzes recent/future normalized Bolt bookings through the same guarded
 * live-submit gate used by live_submit_one_booking.php.
 *
 * Safety:
 * - no EDXEIX HTTP request
 * - no submission_jobs inserts
 * - no submission_attempts inserts
 * - no AADE receipt issuing
 * - no session/cookie/token output
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
    'only-candidates',
    'json',
    'help',
]);

if (isset($options['help'])) {
    echo "EDXEIX Pre-live Audit v6.3.0\n";
    echo "Usage: php edxeix_prelive_audit.php [--future-hours=24] [--past-minutes=60] [--limit=50] [--only-candidates] [--include-receipt-only] [--json]\n";
    echo "Read-only: analyzes live-submit readiness; never submits to EDXEIX.\n";
    exit(0);
}

$limit = max(1, min(200, (int)($options['limit'] ?? 50)));
$futureHours = max(1, min(168, (int)($options['future-hours'] ?? 24)));
$pastMinutes = max(0, min(1440, (int)($options['past-minutes'] ?? 60)));
$includeReceiptOnly = array_key_exists('include-receipt-only', $options);
$onlyCandidates = array_key_exists('only-candidates', $options);
$json = array_key_exists('json', $options);

$config = gov_bridge_load_config();
if (!empty($config['app']['timezone'])) {
    date_default_timezone_set((string)$config['app']['timezone']);
}

$out = [
    'ok' => false,
    'script' => 'cli/edxeix_prelive_audit.php',
    'version' => 'v6.3.0',
    'generated_at' => date('c'),
    'mode' => [
        'read_only' => true,
        'future_hours' => $futureHours,
        'past_minutes' => $pastMinutes,
        'limit' => $limit,
        'include_receipt_only' => $includeReceiptOnly,
        'only_candidates' => $onlyCandidates,
    ],
    'safety' => [
        'does_not_call_edxeix' => true,
        'does_not_issue_aade_receipts' => true,
        'does_not_create_submission_jobs' => true,
        'does_not_create_submission_attempts' => true,
        'does_not_print_session_cookies_or_tokens' => true,
    ],
    'live_config_summary' => [],
    'summary' => [],
    'items' => [],
    'next_safe_steps' => [],
    'error' => null,
];

try {
    $db = gov_bridge_db();
    $liveConfig = gov_live_load_config();
    $sessionState = gov_live_session_state($liveConfig);

    $beforeJobs = gov_bridge_fetch_one($db, 'SELECT COUNT(*) AS c FROM submission_jobs') ?: ['c' => 0];
    $beforeAttempts = gov_bridge_fetch_one($db, 'SELECT COUNT(*) AS c FROM submission_attempts') ?: ['c' => 0];

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

    $rows = prelive_candidate_rows($db, $futureHours, $pastMinutes, $limit, $includeReceiptOnly);
    $items = [];
    $summary = [
        'rows_scanned' => count($rows),
        'prelive_candidates' => 0,
        'live_submission_allowed_now' => 0,
        'blocked_receipt_only' => 0,
        'blocked_terminal_or_past' => 0,
        'blocked_missing_mapping' => 0,
        'blocked_duplicate' => 0,
        'blocked_session_or_one_shot' => 0,
        'other_blocked' => 0,
        'submission_jobs_before' => (int)($beforeJobs['c'] ?? 0),
        'submission_attempts_before' => (int)($beforeAttempts['c'] ?? 0),
    ];

    foreach ($rows as $row) {
        $analysis = gov_live_analyze_booking($db, $row, $liveConfig);
        $blockers = array_values(array_unique(array_map('strval', (array)($analysis['live_blockers'] ?? []))));
        $tech = array_values(array_unique(array_map('strval', (array)($analysis['technical_blockers'] ?? []))));
        $duplicateBlockers = array_values(array_unique(array_map('strval', (array)($analysis['duplicate_check']['blockers'] ?? []))));

        $preliveCandidate = !empty($analysis['is_real_bolt'])
            && empty($analysis['is_receipt_only_booking'])
            && empty($analysis['is_lab_or_test'])
            && empty($analysis['terminal_status'])
            && !empty($analysis['future_guard_passes'])
            && !empty($analysis['technical_payload_valid'])
            && empty($duplicateBlockers);

        $liveAllowedNow = !empty($analysis['live_submission_allowed']);

        if ($preliveCandidate) {
            $summary['prelive_candidates']++;
        }
        if ($liveAllowedNow) {
            $summary['live_submission_allowed_now']++;
        }
        if (!empty($analysis['is_receipt_only_booking']) || in_array('receipt_only_booking_blocked_from_edxeix', $blockers, true)) {
            $summary['blocked_receipt_only']++;
        }
        if (!empty($analysis['terminal_status']) || in_array('terminal_order_status', $tech, true) || preg_grep('/started_at_not_/', $tech)) {
            $summary['blocked_terminal_or_past']++;
        }
        if (in_array('driver_not_mapped', $tech, true) || in_array('vehicle_not_mapped', $tech, true) || in_array('starting_point_not_mapped', $tech, true)) {
            $summary['blocked_missing_mapping']++;
        }
        if ($duplicateBlockers !== []) {
            $summary['blocked_duplicate']++;
        }
        if (array_intersect($blockers, ['edxeix_session_not_connected', 'edxeix_session_not_ready', 'one_shot_live_lock_missing'])) {
            $summary['blocked_session_or_one_shot']++;
        }
        if (!$preliveCandidate && !$liveAllowedNow && $blockers !== []) {
            $summary['other_blocked']++;
        }

        if ($onlyCandidates && !$preliveCandidate && !$liveAllowedNow) {
            continue;
        }

        $preview = is_array($analysis['edxeix_payload_preview'] ?? null) ? $analysis['edxeix_payload_preview'] : [];
        $items[] = [
            'booking_id' => (int)($analysis['booking_id'] ?? 0),
            'order_reference' => (string)($analysis['order_reference'] ?? ''),
            'source_system' => (string)($analysis['source_system'] ?? ''),
            'order_status' => (string)($analysis['status'] ?? ''),
            'started_at' => (string)($analysis['started_at'] ?? ''),
            'driver_name' => (string)($analysis['driver_name'] ?? ''),
            'vehicle_plate' => (string)($analysis['plate'] ?? ''),
            'is_real_bolt' => !empty($analysis['is_real_bolt']),
            'is_receipt_only_booking' => !empty($analysis['is_receipt_only_booking']),
            'future_guard_passes' => !empty($analysis['future_guard_passes']),
            'technical_payload_valid' => !empty($analysis['technical_payload_valid']),
            'prelive_candidate' => $preliveCandidate,
            'live_submission_allowed_now' => $liveAllowedNow,
            'technical_blockers' => $tech,
            'live_blockers' => $blockers,
            'duplicate_blockers' => $duplicateBlockers,
            'payload_hash' => (string)($analysis['payload_hash'] ?? ''),
            'payload_preview_safe' => prelive_safe_payload_preview($preview),
            'recommended_analyze_command' => '/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/live_submit_one_booking.php --booking-id=' . (int)($analysis['booking_id'] ?? 0) . ' --analyze-only',
            'recommended_one_shot_lock_command' => $preliveCandidate
                ? '/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/set_live_submit_one_shot_lock.php --booking-id=' . (int)($analysis['booking_id'] ?? 0) . ' --by=Andreas'
                : null,
        ];
    }

    $afterJobs = gov_bridge_fetch_one($db, 'SELECT COUNT(*) AS c FROM submission_jobs') ?: ['c' => 0];
    $afterAttempts = gov_bridge_fetch_one($db, 'SELECT COUNT(*) AS c FROM submission_attempts') ?: ['c' => 0];

    $summary['submission_jobs_after'] = (int)($afterJobs['c'] ?? 0);
    $summary['submission_attempts_after'] = (int)($afterAttempts['c'] ?? 0);
    $summary['edxeix_queues_unchanged'] = $summary['submission_jobs_before'] === $summary['submission_jobs_after']
        && $summary['submission_attempts_before'] === $summary['submission_attempts_after'];

    $out['summary'] = $summary;
    $out['items'] = $items;
    $out['next_safe_steps'] = [
        'Keep EDXEIX session disconnected until there is a real future Bolt API booking that is prelive_candidate=true.',
        'Do not submit receipt-only bolt_mail bookings; v6.3.0 blocks them from EDXEIX.',
        'For a future eligible booking, run the recommended analyze command first and inspect blockers/payload.',
        'Only after payload and session are correct, set one-shot lock for that exact booking. Live submit still requires the exact confirmation phrase.',
    ];
    $out['ok'] = true;
} catch (Throwable $e) {
    $out['error'] = $e->getMessage();
}

if ($json) {
    echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} else {
    echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
}

exit(!empty($out['ok']) ? 0 : 1);

/** @return array<int,array<string,mixed>> */
function prelive_candidate_rows(mysqli $db, int $futureHours, int $pastMinutes, int $limit, bool $includeReceiptOnly): array
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

/** @return array<string,mixed> */
function prelive_safe_payload_preview(array $payload): array
{
    unset($payload['_token']);
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
