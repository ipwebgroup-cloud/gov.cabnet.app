<?php
/**
 * gov.cabnet.app — EDXEIX Auto Live Worker v6.8.2
 *
 * Purpose:
 * - One command for production EDXEIX submission from Bolt pre-ride email.
 * - Imports Bolt mail, creates exactly one mail-derived normalized booking, submits
 *   to the EDXEIX lease agreement form, verifies that the record appears in the
 *   EDXEIX UI/list, then protects the booking from repeat submission.
 *
 * Safety:
 * - EDXEIX source is pre-ride Bolt email only.
 * - Bolt API is not used as EDXEIX source.
 * - AADE is never called.
 * - submission_jobs and submission_attempts are never created.
 * - Does not print cookie headers, CSRF tokens, API keys, or response bodies.
 * - Submits at most one currently-future, mapped, unlinked mail candidate per run.
 * - HTTP redirect alone is not success; UI/list verification is required.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "CLI only\n";
    exit(1);
}

require_once '/home/cabnet/gov.cabnet.app_app/lib/edxeix_live_submit_gate.php';

$options = getopt('', [
    'json',
    'dry-run',
    'limit::',
    'confirm-live::',
    'help',
]);

$json = array_key_exists('json', $options);
$dryRun = array_key_exists('dry-run', $options);
$limit = max(1, min(20, (int)($options['limit'] ?? 10)));
$confirm = trim((string)($options['confirm-live'] ?? ''));
$requiredConfirm = 'I UNDERSTAND AUTO LIVE EDXEIX';

if (isset($options['help'])) {
    echo "EDXEIX Auto Live Worker v6.8.2\n";
    echo "Usage:\n";
    echo "  php edxeix_auto_live_worker.php --dry-run --json\n";
    echo "  php edxeix_auto_live_worker.php --confirm-live='I UNDERSTAND AUTO LIVE EDXEIX' --json\n";
    echo "Safety: one currently-future pre-ride Bolt email only; no AADE; no queue rows; UI verification required.\n";
    exit(0);
}

$config = gov_bridge_load_config();
if (!empty($config['app']['timezone'])) {
    date_default_timezone_set((string)$config['app']['timezone']);
}

$out = [
    'ok' => false,
    'submitted' => false,
    'confirmed_in_edxeix_ui' => false,
    'script' => 'cli/edxeix_auto_live_worker.php',
    'version' => 'v6.8.2',
    'generated_at' => date('c'),
    'mode' => [
        'dry_run' => $dryRun,
        'limit' => $limit,
        'live_requires_confirm_phrase' => $requiredConfirm,
    ],
    'source_policy' => [
        'edxeix_submission_source' => 'pre_ride_bolt_email_only',
        'edxeix_uses_bolt_api_as_source' => false,
        'aade_invoice_source' => 'bolt_api_pickup_timestamp_worker_only',
        'pre_ride_email_may_issue_aade' => false,
    ],
    'safety' => [
        'one_candidate_only' => true,
        'requires_currently_future_pickup' => true,
        'requires_mapped_driver_vehicle_starting_point' => true,
        'does_not_call_aade' => true,
        'does_not_create_submission_jobs' => true,
        'does_not_create_submission_attempts' => true,
        'does_not_print_session_cookies_or_tokens' => true,
        'does_not_treat_302_as_success_without_ui_verification' => true,
    ],
    'queue_counts' => null,
    'steps' => [],
    'candidate' => null,
    'booking' => null,
    'payload_preview_safe' => null,
    'edxeix_http' => null,
    'verification' => null,
    'error' => null,
    'next_action' => null,
];

try {
    $db = gov_bridge_db();
    $beforeJobs = auto_count_rows($db, 'submission_jobs');
    $beforeAttempts = auto_count_rows($db, 'submission_attempts');

    if (!$dryRun && $confirm !== $requiredConfirm) {
        throw new RuntimeException('confirm_live_phrase_required');
    }

    $import = run_json_cli('/home/cabnet/gov.cabnet.app_app/cli/import_bolt_mail.php', ['--json']);
    $out['steps']['import_bolt_mail'] = safe_cli_summary($import);

    $preview = run_json_cli('/home/cabnet/gov.cabnet.app_app/cli/edxeix_mail_preflight_bridge.php', ['--limit=' . $limit, '--json']);
    $out['steps']['preflight_preview'] = safe_cli_summary($preview);

    $items = is_array($preview['items'] ?? null) ? $preview['items'] : [];
    $readyItems = [];
    foreach ($items as $item) {
        if (($item['status'] ?? '') === 'preview_ready' && !empty($item['preview']['create_allowed'])) {
            $readyItems[] = $item;
        }
    }

    if (count($readyItems) === 0) {
        $out['next_action'] = 'No currently-future pre-ride Bolt email candidate is ready. Re-run when the next pre-ride email arrives.';
        throw new RuntimeException('no_ready_pre_ride_mail_candidate');
    }
    if (count($readyItems) > 1) {
        $out['next_action'] = 'Multiple ready candidates found. Auto live worker refuses to choose automatically.';
        throw new RuntimeException('multiple_ready_candidates_refusing_auto_submit');
    }

    $candidate = $readyItems[0];
    $intakeId = (int)($candidate['intake_id'] ?? 0);
    if ($intakeId <= 0) {
        throw new RuntimeException('ready_candidate_missing_intake_id');
    }
    $out['candidate'] = safe_candidate($candidate);

    if ($dryRun) {
        $out['ok'] = true;
        $out['submitted'] = false;
        $out['confirmed_in_edxeix_ui'] = false;
        $out['next_action'] = "Dry-run only. To submit this one candidate, re-run with --confirm-live='{$requiredConfirm}'.";
        $out['queue_counts'] = auto_queue_counts($db, $beforeJobs, $beforeAttempts);
        print_output($out, $json);
        exit(0);
    }

    $create = run_json_cli('/home/cabnet/gov.cabnet.app_app/cli/edxeix_mail_preflight_bridge.php', ['--intake-id=' . $intakeId, '--create', '--json']);
    $out['steps']['preflight_create'] = safe_cli_summary($create);

    $createdItems = is_array($create['items'] ?? null) ? $create['items'] : [];
    $bookingId = 0;
    foreach ($createdItems as $createdItem) {
        if ((int)($createdItem['intake_id'] ?? 0) === $intakeId && (int)($createdItem['booking_id'] ?? 0) > 0) {
            $bookingId = (int)$createdItem['booking_id'];
            break;
        }
    }
    if ($bookingId <= 0) {
        throw new RuntimeException('preflight_create_did_not_return_booking_id');
    }

    $booking = gov_live_booking_by_id($db, (string)$bookingId);
    if (!$booking) {
        throw new RuntimeException('created_booking_not_found');
    }

    $analysis = analyze_auto_live_booking($db, $booking);
    $out['booking'] = $analysis['booking_safe'];
    $out['payload_preview_safe'] = $analysis['payload_safe'];
    if (!$analysis['ready']) {
        $out['verification'] = ['ready' => false, 'blockers' => $analysis['blockers']];
        throw new RuntimeException('created_booking_not_auto_live_ready');
    }

    $liveConfig = gov_live_load_config();
    $session = read_edxeix_session_secret($liveConfig);
    $submit = submit_to_edxeix_create_form($liveConfig, $session, $analysis['payload']);
    $out['edxeix_http'] = [
        'create_get_status' => $submit['create_get_status'],
        'submit_url_host_path' => safe_url_host_path($submit['submit_url']),
        'post_status' => $submit['post_status'],
        'post_redirect_location_present' => $submit['post_redirect_location'] !== '',
        'post_transport_ok' => $submit['post_transport_ok'],
        'note' => 'No cookie, CSRF token, or response body is printed.',
    ];

    $verify = verify_edxeix_ui_creation($liveConfig, $session, $analysis['payload'], $submit);
    $out['verification'] = $verify['safe'];

    insert_auto_audit($db, $booking, $analysis['payload'], $submit, $verify);

    if (empty($verify['confirmed'])) {
        $out['queue_counts'] = auto_queue_counts($db, $beforeJobs, $beforeAttempts);
        $out['next_action'] = 'Auto POST was attempted, but UI/list verification did not confirm creation. Do not count as submitted.';
        throw new RuntimeException('edxeix_ui_verification_failed_after_post');
    }

    mark_booking_submitted_no_repeat($db, $bookingId, 'auto_live_worker_confirmed_edxeix_ui_no_repeat_allowed');

    $out['ok'] = true;
    $out['submitted'] = true;
    $out['confirmed_in_edxeix_ui'] = true;
    $out['next_action'] = 'Confirmed in EDXEIX UI/list. No repeat submission allowed for this booking.';
    $out['queue_counts'] = auto_queue_counts($db, $beforeJobs, $beforeAttempts);
} catch (Throwable $e) {
    if ($out['error'] === null) {
        $out['error'] = $e->getMessage();
    }
    if ($out['queue_counts'] === null) {
        try {
            $db = $db ?? gov_bridge_db();
            $out['queue_counts'] = auto_queue_counts($db, $beforeJobs ?? null, $beforeAttempts ?? null);
        } catch (Throwable) {
            $out['queue_counts'] = null;
        }
    }
}

print_output($out, $json);
exit(!empty($out['ok']) ? 0 : 1);

function run_json_cli(string $script, array $args): array
{
    $cmd = array_merge([PHP_BINARY, $script], $args);
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $proc = proc_open($cmd, $descriptors, $pipes);
    if (!is_resource($proc)) {
        throw new RuntimeException('unable_to_start_cli:' . basename($script));
    }
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $code = proc_close($proc);
    $decoded = json_decode(trim((string)$stdout), true);
    if (!is_array($decoded)) {
        throw new RuntimeException('cli_returned_non_json:' . basename($script) . ':' . trim((string)$stderr));
    }
    $decoded['_exit_code'] = $code;
    if ($code !== 0 && empty($decoded['ok'])) {
        throw new RuntimeException('cli_failed:' . basename($script) . ':' . (string)($decoded['error'] ?? 'unknown'));
    }
    return $decoded;
}

function safe_cli_summary(array $result): array
{
    $out = [
        'ok' => !empty($result['ok']),
        'script' => (string)($result['script'] ?? ''),
        'version' => (string)($result['version'] ?? ''),
        'exit_code' => (int)($result['_exit_code'] ?? 0),
        'error' => $result['error'] ?? null,
    ];
    if (isset($result['summary']) && is_array($result['summary'])) {
        $out['summary'] = $result['summary'];
    }
    return $out;
}

function analyze_auto_live_booking(mysqli $db, array $booking): array
{
    $blockers = [];
    $bookingId = (string)gov_live_value($booking, ['id'], '');
    $orderRef = (string)gov_live_value($booking, ['order_reference', 'external_order_id', 'source_trip_reference', 'source_trip_id'], '');
    $source = strtolower(trim((string)gov_live_value($booking, ['source_system', 'source_type', 'source'], '')));
    $status = strtolower(trim((string)gov_live_value($booking, ['order_status', 'status'], '')));
    $startedAt = trim((string)gov_live_value($booking, ['started_at'], ''));
    $neverLive = gov_live_bool(gov_live_value($booking, ['never_submit_live'], false));
    $blockReason = strtolower(trim((string)gov_live_value($booking, ['live_submit_block_reason'], '')));

    if ($source !== 'bolt_mail' && !str_starts_with(strtolower($orderRef), 'mail:')) { $blockers[] = 'source_must_be_bolt_mail'; }
    if ($neverLive) { $blockers[] = 'never_submit_live_flag_set'; }
    foreach (['receipt_only', 'aade_receipt_only', 'no_edxeix', 'manual_browser_confirmed', 'auto_live_worker_confirmed', 'terminal_status', 'finished', 'past', 'cancel'] as $needle) {
        if ($blockReason !== '' && str_contains($blockReason, $needle)) { $blockers[] = 'live_submit_block_reason_blocks:' . $needle; }
    }
    if ($status === '' || gov_live_terminal_status($status)) { $blockers[] = 'terminal_or_missing_status'; }
    if ($startedAt === '' || !gov_live_future_guard_passes($startedAt, 0)) { $blockers[] = 'started_at_not_future'; }

    $payload = build_edxeix_post_payload_from_booking($db, $booking);
    foreach (['lessor', 'driver', 'vehicle', 'boarding_point', 'disembark_point', 'started_at', 'ended_at', 'price'] as $field) {
        if (trim((string)($payload[$field] ?? '')) === '') { $blockers[] = 'missing_payload_' . $field; }
    }
    if (trim((string)($payload['starting_point'] ?? $payload['starting_point_id'] ?? '')) === '') { $blockers[] = 'missing_payload_starting_point'; }
    if (trim((string)($payload['lessee[name]'] ?? $payload['lessee_name'] ?? '')) === '') { $blockers[] = 'missing_payload_lessee_name'; }

    $dup = gov_live_duplicate_checks($db, $booking, $payload);
    foreach ((array)($dup['blockers'] ?? []) as $dupBlocker) { $blockers[] = 'duplicate:' . (string)$dupBlocker; }

    return [
        'ready' => $blockers === [],
        'blockers' => array_values(array_unique($blockers)),
        'payload' => $payload,
        'payload_safe' => safe_payload($payload),
        'booking_safe' => [
            'booking_id' => (int)$bookingId,
            'order_reference' => $orderRef,
            'source_system' => (string)gov_live_value($booking, ['source_system', 'source_type', 'source'], ''),
            'order_status' => (string)gov_live_value($booking, ['order_status', 'status'], ''),
            'started_at' => $startedAt,
            'customer_name' => (string)gov_live_value($booking, ['customer_name', 'passenger_name', 'lessee_name'], ''),
            'driver_name' => (string)gov_live_value($booking, ['driver_name'], ''),
            'vehicle_plate' => (string)gov_live_value($booking, ['vehicle_plate', 'plate'], ''),
        ],
    ];
}

function build_edxeix_post_payload_from_booking(mysqli $db, array $booking): array
{
    $config = gov_bridge_load_config();
    $customer = (string)gov_live_value($booking, ['customer_name', 'passenger_name', 'lessee_name'], 'Bolt Passenger');
    $driverName = trim((string)gov_live_value($booking, ['driver_name'], ''));
    $plate = strtoupper(trim((string)gov_live_value($booking, ['vehicle_plate', 'plate'], '')));
    $startedAt = (string)gov_live_value($booking, ['started_at'], '');
    $endedAt = (string)gov_live_value($booking, ['ended_at'], '');
    $boarding = (string)gov_live_value($booking, ['boarding_point', 'pickup_address'], '');
    $disembark = (string)gov_live_value($booking, ['disembark_point', 'destination_address'], '');
    $price = (float)gov_live_value($booking, ['price', 'total_price', 'estimated_price'], '0');
    $startingKey = (string)gov_live_value($booking, ['starting_point_key'], 'edra_mas');

    $driverId = '';
    if ($driverName !== '' && gov_bridge_table_exists($db, 'mapping_drivers')) {
        $row = gov_bridge_fetch_one($db, 'SELECT edxeix_driver_id FROM mapping_drivers WHERE external_driver_name = ? AND is_active = 1 LIMIT 1', [$driverName]);
        $driverId = (string)($row['edxeix_driver_id'] ?? '');
    }
    $vehicleId = '';
    if ($plate !== '' && gov_bridge_table_exists($db, 'mapping_vehicles')) {
        $row = gov_bridge_fetch_one($db, 'SELECT edxeix_vehicle_id FROM mapping_vehicles WHERE UPPER(plate) = ? AND is_active = 1 LIMIT 1', [$plate]);
        $vehicleId = (string)($row['edxeix_vehicle_id'] ?? '');
    }
    $startingPointId = '';
    if ($startingKey !== '' && gov_bridge_table_exists($db, 'mapping_starting_points')) {
        $row = gov_bridge_fetch_one($db, 'SELECT edxeix_starting_point_id FROM mapping_starting_points WHERE internal_key = ? AND is_active = 1 LIMIT 1', [$startingKey]);
        $startingPointId = (string)($row['edxeix_starting_point_id'] ?? '');
    }
    if ($startingPointId === '') {
        $startingPointId = (string)($config['edxeix']['default_starting_point_id'] ?? '');
    }

    $lessor = (string)($config['edxeix']['lessor_id'] ?? '3814');
    $broker = (string)($config['edxeix']['default_broker'] ?? 'Bolt');

    return [
        'broker' => $broker !== '' ? $broker : 'Bolt',
        'lessor' => $lessor,
        'lessee[type]' => 'natural',
        'lessee[name]' => $customer,
        'lessee_name' => $customer,
        'lessee[vat_number]' => '',
        'lessee[legal_representative]' => '',
        'driver' => $driverId,
        'vehicle' => $vehicleId,
        'starting_point' => $startingPointId,
        'starting_point_id' => $startingPointId,
        'boarding_point' => $boarding,
        'coordinates' => '',
        'disembark_point' => $disembark,
        'drafted_at' => date('d/m/Y H:i'),
        'started_at' => format_edxeix_datetime($startedAt),
        'ended_at' => format_edxeix_datetime($endedAt),
        'price' => number_format($price, 2, '.', ''),
    ];
}

function format_edxeix_datetime(string $value): string
{
    $ts = strtotime($value);
    return $ts === false ? $value : date('d/m/Y H:i', $ts);
}

function read_edxeix_session_secret(array $liveConfig): array
{
    $sessionState = gov_live_session_state($liveConfig);
    if (empty($sessionState['ready'])) {
        throw new RuntimeException('edxeix_session_not_ready_refresh_firefox_session');
    }
    $file = (string)($liveConfig['edxeix_session_file'] ?? '/home/cabnet/gov.cabnet.app_app/storage/runtime/edxeix_session.json');
    $raw = is_file($file) ? file_get_contents($file) : false;
    $decoded = $raw !== false ? json_decode((string)$raw, true) : null;
    if (!is_array($decoded)) {
        throw new RuntimeException('edxeix_session_file_invalid');
    }
    $cookie = trim((string)($decoded['cookie_header'] ?? ''));
    $csrf = trim((string)($decoded['csrf_token'] ?? ''));
    if ($cookie === '' || $csrf === '') {
        throw new RuntimeException('edxeix_session_cookie_or_csrf_missing');
    }
    return [
        'cookie_header' => $cookie,
        'csrf_token' => $csrf,
        'updated_at' => $decoded['updated_at'] ?? $decoded['saved_at'] ?? null,
    ];
}

function submit_to_edxeix_create_form(array $liveConfig, array $session, array $payload): array
{
    $createUrl = trim((string)($liveConfig['edxeix_create_url'] ?? ''));
    if ($createUrl === '') { $createUrl = 'https://edxeix.yme.gov.gr/dashboard/lease-agreement/create'; }
    $fallbackSubmitUrl = trim((string)($liveConfig['edxeix_submit_url'] ?? ''));
    if ($fallbackSubmitUrl === '') { $fallbackSubmitUrl = 'https://edxeix.yme.gov.gr/dashboard/lease-agreement'; }

    $create = http_edxeix('GET', $createUrl, $session, null);
    $form = parse_edxeix_form((string)$create['body'], $createUrl, $fallbackSubmitUrl);
    $csrf = $form['csrf_token'] !== '' ? $form['csrf_token'] : (string)$session['csrf_token'];
    $postPayload = $payload;
    $postPayload['_token'] = $csrf;

    $post = http_edxeix('POST', $form['action'], $session, http_build_query($postPayload));

    return [
        'create_url' => $createUrl,
        'create_get_status' => (int)$create['status'],
        'submit_url' => $form['action'],
        'post_status' => (int)$post['status'],
        'post_redirect_location' => $post['location'],
        'post_transport_ok' => ((int)$post['status'] >= 200 && (int)$post['status'] < 400),
        'post_body_marker_summary' => body_marker_summary((string)$post['body']),
    ];
}

function http_edxeix(string $method, string $url, array $session, ?string $body): array
{
    if (!function_exists('curl_init')) { throw new RuntimeException('curl_extension_missing'); }
    $ch = curl_init($url);
    $headers = [
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        'User-Agent: gov.cabnet.app EDXEIX auto live worker',
        'Cookie: ' . $session['cookie_header'],
        'Referer: https://edxeix.yme.gov.gr/dashboard/lease-agreement/create',
    ];
    if (strtoupper($method) === 'POST') {
        $headers[] = 'Content-Type: application/x-www-form-urlencoded';
    }
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 45,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
        CURLOPT_HTTPHEADER => $headers,
    ]);
    if ($body !== null) { curl_setopt($ch, CURLOPT_POSTFIELDS, $body); }
    $raw = curl_exec($ch);
    if ($raw === false) {
        $err = curl_error($ch);
        $no = curl_errno($ch);
        curl_close($ch);
        throw new RuntimeException('edxeix_http_error:' . $no . ':' . $err);
    }
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    $headerRaw = substr((string)$raw, 0, $headerSize);
    $responseBody = substr((string)$raw, $headerSize);
    $location = '';
    if (preg_match('/^Location:\s*(.+)$/mi', $headerRaw, $m)) { $location = trim((string)$m[1]); }
    return ['status' => $status, 'headers' => $headerRaw, 'body' => $responseBody, 'location' => $location];
}

function parse_edxeix_form(string $html, string $baseUrl, string $fallbackSubmitUrl): array
{
    $action = '';
    if (preg_match('/<form\b[^>]*action=["\']([^"\']+)["\'][^>]*>/i', $html, $m)) {
        $action = html_entity_decode((string)$m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    if ($action === '') { $action = $fallbackSubmitUrl; }
    $action = absolute_url($action, $baseUrl);

    $csrf = '';
    if (preg_match('/<input\b[^>]*name=["\']_token["\'][^>]*value=["\']([^"\']+)["\'][^>]*>/i', $html, $m)
        || preg_match('/<input\b[^>]*value=["\']([^"\']+)["\'][^>]*name=["\']_token["\'][^>]*>/i', $html, $m)) {
        $csrf = html_entity_decode((string)$m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    return ['action' => $action, 'csrf_token' => $csrf];
}

function absolute_url(string $url, string $base): string
{
    if (preg_match('#^https?://#i', $url)) { return $url; }
    $parts = parse_url($base);
    $scheme = $parts['scheme'] ?? 'https';
    $host = $parts['host'] ?? 'edxeix.yme.gov.gr';
    if (str_starts_with($url, '/')) { return $scheme . '://' . $host . $url; }
    $path = $parts['path'] ?? '/';
    $dir = rtrim(str_replace('\\', '/', dirname($path)), '/');
    return $scheme . '://' . $host . ($dir !== '' ? $dir : '') . '/' . $url;
}

function verify_edxeix_ui_creation(array $liveConfig, array $session, array $payload, array $submit): array
{
    $urls = [];
    if (!empty($submit['post_redirect_location'])) {
        $urls[] = absolute_url((string)$submit['post_redirect_location'], (string)$submit['submit_url']);
    }
    $listUrl = trim((string)($liveConfig['edxeix_list_url'] ?? ''));
    if ($listUrl === '') { $listUrl = 'https://edxeix.yme.gov.gr/dashboard/lease-agreement'; }
    $urls[] = $listUrl;
    $urls = array_values(array_unique($urls));

    $markers = [
        trim((string)($payload['lessee[name]'] ?? $payload['lessee_name'] ?? '')),
        trim((string)($payload['boarding_point'] ?? '')),
        trim((string)($payload['disembark_point'] ?? '')),
        trim((string)($payload['started_at'] ?? '')),
    ];
    $markers = array_values(array_filter($markers, static fn($v) => $v !== ''));

    $checks = [];
    $confirmed = false;
    foreach ($urls as $url) {
        $resp = http_edxeix('GET', $url, $session, null);
        $body = (string)$resp['body'];
        $found = [];
        foreach ($markers as $marker) {
            $found[$marker] = mb_stripos_safe($body, $marker) !== false;
        }
        $hitCount = count(array_filter($found));
        if ((int)$resp['status'] >= 200 && (int)$resp['status'] < 400 && $hitCount >= 2) {
            $confirmed = true;
        }
        $checks[] = [
            'url_host_path' => safe_url_host_path($url),
            'http_status' => (int)$resp['status'],
            'marker_hits' => $hitCount,
            'marker_count' => count($markers),
            'body_marker_summary' => body_marker_summary($body),
        ];
    }
    return [
        'confirmed' => $confirmed,
        'safe' => [
            'confirmed' => $confirmed,
            'checks' => $checks,
            'rule' => 'Confirmed only if EDXEIX redirect/list page contains at least two expected booking markers.',
        ],
    ];
}

function mb_stripos_safe(string $haystack, string $needle)
{
    if (function_exists('mb_stripos')) { return mb_stripos($haystack, $needle, 0, 'UTF-8'); }
    return stripos($haystack, $needle);
}

function body_marker_summary(string $body): array
{
    $lower = strtolower($body);
    return [
        'length' => strlen($body),
        'has_login' => str_contains($lower, 'login'),
        'has_error' => str_contains($lower, 'error') || str_contains($lower, 'σφάλ') || str_contains($lower, 'λάθος'),
        'has_required' => str_contains($lower, 'required') || str_contains($lower, 'υποχρεω'),
        'has_csrf' => str_contains($lower, 'csrf') || str_contains($lower, '419'),
    ];
}

function insert_auto_audit(mysqli $db, array $booking, array $payload, array $submit, array $verify): void
{
    if (!gov_bridge_table_exists($db, 'edxeix_live_submission_audit')) { return; }
    gov_bridge_insert_row($db, 'edxeix_live_submission_audit', [
        'normalized_booking_id' => (string)gov_live_value($booking, ['id'], ''),
        'order_reference' => (string)gov_live_value($booking, ['order_reference', 'external_order_id', 'source_trip_reference', 'source_trip_id'], ''),
        'source_system' => (string)gov_live_value($booking, ['source_system', 'source_type', 'source'], ''),
        'payload_hash' => gov_live_payload_hash($payload),
        'request_payload_json' => gov_bridge_json_encode_db(safe_payload($payload)),
        'response_status' => (string)($submit['post_status'] ?? 0),
        'response_body' => 'Auto EDXEIX worker attempted HTTP POST. Response body intentionally not stored; verification summary stored in response_json.',
        'response_json' => gov_bridge_json_encode_db([
            'auto_live_worker' => true,
            'post_status' => (int)($submit['post_status'] ?? 0),
            'post_transport_ok' => !empty($submit['post_transport_ok']),
            'confirmed_in_edxeix_ui' => !empty($verify['confirmed']),
            'verification' => $verify['safe'] ?? [],
        ]),
        'success' => !empty($verify['confirmed']) ? '1' : '0',
        'remote_reference' => !empty($verify['confirmed']) ? 'AUTO_WORKER_CONFIRMED_IN_EDXEIX_UI' : '',
        'mode' => 'auto_live_worker_v6_8_2',
        'live_blockers_json' => gov_bridge_json_encode_db([]),
        'created_at' => date('Y-m-d H:i:s'),
    ]);
}

function mark_booking_submitted_no_repeat(mysqli $db, int $bookingId, string $reason): void
{
    gov_bridge_update_row($db, 'normalized_bookings', [
        'never_submit_live' => '1',
        'live_submit_block_reason' => $reason,
        'notes' => append_note($db, $bookingId, 'EDXEIX AUTO LIVE CONFIRMED: submitted and verified by v6.8.2 auto worker at ' . date('Y-m-d H:i:s') . '. No repeat allowed.'),
        'updated_at' => date('Y-m-d H:i:s'),
    ], 'id = ?', [$bookingId]);
}

function append_note(mysqli $db, int $bookingId, string $note): string
{
    $row = gov_bridge_fetch_one($db, 'SELECT notes FROM normalized_bookings WHERE id = ? LIMIT 1', [(string)$bookingId]);
    $existing = (string)($row['notes'] ?? '');
    return $existing . "\n" . $note;
}

function safe_payload(array $payload): array
{
    $out = $payload;
    unset($out['_token'], $out['csrf_token'], $out['cookie'], $out['cookie_header']);
    return $out;
}

function safe_candidate(array $item): array
{
    return [
        'intake_id' => (int)($item['intake_id'] ?? 0),
        'customer_name' => (string)($item['customer_name'] ?? ''),
        'driver_name' => (string)($item['driver_name'] ?? ''),
        'vehicle_plate' => (string)($item['vehicle_plate'] ?? ''),
        'parsed_pickup_at' => (string)($item['parsed_pickup_at'] ?? ''),
        'pickup_address' => (string)($item['pickup_address'] ?? ''),
        'dropoff_address' => (string)($item['dropoff_address'] ?? ''),
    ];
}

function safe_url_host_path(string $url): string
{
    $p = parse_url($url);
    if (!$p) { return ''; }
    return ($p['host'] ?? '') . ($p['path'] ?? '');
}

function auto_count_rows(mysqli $db, string $table): int
{
    if (!gov_bridge_table_exists($db, $table)) { return 0; }
    $row = gov_bridge_fetch_one($db, 'SELECT COUNT(*) AS c FROM ' . gov_bridge_quote_identifier($table));
    return (int)($row['c'] ?? 0);
}

function auto_queue_counts(mysqli $db, ?int $beforeJobs, ?int $beforeAttempts): array
{
    $afterJobs = auto_count_rows($db, 'submission_jobs');
    $afterAttempts = auto_count_rows($db, 'submission_attempts');
    return [
        'submission_jobs_before' => $beforeJobs,
        'submission_jobs_after' => $afterJobs,
        'submission_attempts_before' => $beforeAttempts,
        'submission_attempts_after' => $afterAttempts,
        'queues_unchanged' => $beforeJobs === $afterJobs && $beforeAttempts === $afterAttempts,
    ];
}

function print_output(array $out, bool $json): void
{
    echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
}
