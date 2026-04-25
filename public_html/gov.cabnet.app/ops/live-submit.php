<?php
/**
 * gov.cabnet.app — Live EDXEIX Submit Gate + Production Readiness
 *
 * Guarded operations page for reviewing the final live-submit gate.
 * This preparatory page is still intentionally blocked: no EDXEIX HTTP request
 * is performed by this patch.
 */

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Robots-Tag: noindex, nofollow', true);

require_once '/home/cabnet/gov.cabnet.app_app/lib/edxeix_live_submit_gate.php';

function ls_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function ls_badge(string $text, string $type = 'neutral'): string
{
    return '<span class="badge badge-' . ls_h($type) . '">' . ls_h($text) . '</span>';
}

function ls_bool_badge(bool $value, string $yes = 'pass', string $no = 'blocked'): string
{
    return $value ? ls_badge($yes, 'good') : ls_badge($no, 'bad');
}

function ls_request_param(string $key, string $default = ''): string
{
    $value = $_GET[$key] ?? $_POST[$key] ?? $default;
    $value = is_scalar($value) ? trim((string)$value) : $default;
    return mb_substr($value, 0, 255, 'UTF-8');
}

function ls_json_response(array $payload): void
{
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('X-Robots-Tag: noindex, nofollow', true);
    }
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit;
}

function ls_secret_looks_placeholder(string $value): bool
{
    $value = trim($value);
    if ($value === '') {
        return false;
    }
    if (function_exists('gov_live_secret_looks_placeholder')) {
        return (bool)gov_live_secret_looks_placeholder($value);
    }
    $upper = strtoupper($value);
    $markers = [
        'PASTE', 'REPLACE', 'EXAMPLE', 'DUMMY', 'DEMO', 'TODO', 'SERVER_ONLY',
        'DO_NOT_COMMIT', 'COOKIE_HEADER', 'CSRF_TOKEN', 'PLACEHOLDER', 'YYYY-MM-DD', 'HH:MM:SS',
    ];
    foreach ($markers as $marker) {
        if (strpos($upper, $marker) !== false) {
            return true;
        }
    }
    return false;
}

function ls_public_config_state(array $config): array
{
    $submitUrl = trim((string)($config['edxeix_submit_url'] ?? ''));
    $sessionFile = (string)($config['edxeix_session_file'] ?? '/home/cabnet/gov.cabnet.app_app/storage/runtime/edxeix_session.json');
    return [
        'config_file' => gov_live_config_path(),
        'config_file_exists' => is_file(gov_live_config_path()),
        'config_file_readable' => is_readable(gov_live_config_path()),
        'live_submit_enabled' => !empty($config['live_submit_enabled']),
        'http_submit_enabled' => !empty($config['http_submit_enabled']),
        'edxeix_submit_url_configured' => $submitUrl !== '',
        'edxeix_submit_url_host' => $submitUrl !== '' ? (parse_url($submitUrl, PHP_URL_HOST) ?: '') : '',
        'edxeix_form_method' => (string)($config['edxeix_form_method'] ?? 'POST'),
        'edxeix_session_file' => $sessionFile,
        'confirmation_phrase_required' => !empty($config['require_confirmation_phrase']),
        'allowed_booking_id' => $config['allowed_booking_id'] ?? null,
        'allowed_order_reference' => $config['allowed_order_reference'] ?? null,
        'transport_note' => 'This preparatory patch still blocks live HTTP transport even if config is toggled.',
    ];
}

function ls_read_global_session_state(array $config): array
{
    $file = (string)($config['edxeix_session_file'] ?? '/home/cabnet/gov.cabnet.app_app/storage/runtime/edxeix_session.json');
    $state = [
        'file_configured' => $file !== '',
        'file_exists' => false,
        'file_readable' => false,
        'json_valid' => false,
        'cookie_raw_present' => false,
        'csrf_raw_present' => false,
        'cookie_present' => false,
        'csrf_present' => false,
        'cookie_placeholder_detected' => false,
        'csrf_placeholder_detected' => false,
        'timestamp_placeholder_detected' => false,
        'placeholder_detected' => false,
        'cookie_length' => 0,
        'csrf_length' => 0,
        'updated_at' => null,
        'saved_at' => null,
        'age_minutes' => null,
        'ready' => false,
        'error' => null,
    ];

    if ($file === '') {
        $state['error'] = 'session_file_not_configured';
        return $state;
    }

    $state['file_exists'] = is_file($file);
    $state['file_readable'] = $state['file_exists'] && is_readable($file);
    if (!$state['file_readable']) {
        $state['error'] = $state['file_exists'] ? 'session_file_not_readable' : 'session_file_missing';
        return $state;
    }

    $raw = file_get_contents($file);
    $decoded = json_decode((string)$raw, true);
    if (!is_array($decoded)) {
        $state['error'] = 'session_json_invalid';
        return $state;
    }

    $cookie = trim((string)($decoded['cookie_header'] ?? ''));
    $csrf = trim((string)($decoded['csrf_token'] ?? ''));
    $updatedAt = $decoded['updated_at'] ?? $decoded['saved_at'] ?? null;

    $cookiePlaceholder = ls_secret_looks_placeholder($cookie);
    $csrfPlaceholder = ls_secret_looks_placeholder($csrf);
    $timestampPlaceholder = is_string($updatedAt) && ls_secret_looks_placeholder($updatedAt);

    $state['json_valid'] = true;
    $state['cookie_raw_present'] = $cookie !== '';
    $state['csrf_raw_present'] = $csrf !== '';
    $state['cookie_placeholder_detected'] = $cookiePlaceholder;
    $state['csrf_placeholder_detected'] = $csrfPlaceholder;
    $state['timestamp_placeholder_detected'] = $timestampPlaceholder;
    $state['placeholder_detected'] = $cookiePlaceholder || $csrfPlaceholder || $timestampPlaceholder;
    $state['cookie_present'] = $cookie !== '' && !$cookiePlaceholder;
    $state['csrf_present'] = $csrf !== '' && !$csrfPlaceholder;
    $state['cookie_length'] = strlen($cookie);
    $state['csrf_length'] = strlen($csrf);
    $state['updated_at'] = $decoded['updated_at'] ?? null;
    $state['saved_at'] = $decoded['saved_at'] ?? null;

    if (is_string($updatedAt) && $updatedAt !== '') {
        $ts = strtotime($updatedAt);
        if ($ts !== false) {
            $state['age_minutes'] = max(0, (int)floor((time() - $ts) / 60));
        }
    }

    $state['ready'] = $state['json_valid'] && $state['cookie_present'] && $state['csrf_present'] && !$state['placeholder_detected'];
    if ($state['placeholder_detected']) {
        $state['error'] = 'placeholder_session_values_detected';
    } elseif (!$state['cookie_present'] || !$state['csrf_present']) {
        $state['error'] = 'missing_cookie_or_csrf';
    }

    return $state;
}

function ls_blocker_meaning(string $blocker): string
{
    $map = [
        'live_submit_config_disabled' => 'Server-only live_submit_enabled is false. This is expected until the final approved live run.',
        'http_submit_config_disabled' => 'Server-only http_submit_enabled is false. This prevents accidental HTTP transport.',
        'edxeix_session_not_ready' => 'The saved EDXEIX cookie/CSRF session is missing, incomplete, or still a placeholder.',
        'edxeix_submit_url_missing' => 'The exact EDXEIX submit endpoint/action URL has not been configured.',
        'started_at_not_30_min_future' => 'The ride is not far enough in the future for safe review.',
        'terminal_order_status' => 'The Bolt order is finished/cancelled/terminal and must never be submitted.',
        'lab_row_blocked' => 'LAB/local test bookings are never allowed to submit live.',
        'never_submit_live' => 'This row is explicitly marked as never-submit-live.',
        'driver_not_mapped' => 'The Bolt driver does not have a confirmed EDXEIX driver ID.',
        'vehicle_not_mapped' => 'The Bolt vehicle does not have a confirmed EDXEIX vehicle ID.',
        'duplicate_successful_submission' => 'A prior successful live submission appears to exist for this booking/payload.',
        'duplicate_live_audit_success_detected' => 'A prior successful live audit row appears to exist for this booking/payload.',
        'duplicate_submission_attempt_success_detected' => 'A prior successful submission attempt appears to exist for this booking/payload.',
        'http_transport_not_enabled_in_this_patch' => 'This preparatory patch intentionally cannot send the live HTTP request.',
        'no_real_future_candidate' => 'No analyzed row currently qualifies as a real future Bolt candidate.',
        'no_selected_real_future_candidate' => 'No real future Bolt candidate is selected for live review.',
        'selected_row_not_real_future_candidate' => 'The selected analyzed row is not a real future candidate because it is blocked by technical safety checks.',
    ];
    return $map[$blocker] ?? 'Safety blocker reported by the live-submit gate.';
}

function ls_status_row(string $label, bool $pass, string $detail, bool $waiting = false): string
{
    $badge = $waiting && !$pass ? ls_badge('waiting', 'warn') : ls_bool_badge($pass);
    return '<tr><td><strong>' . ls_h($label) . '</strong></td><td>' . $badge . '</td><td>' . ls_h($detail) . '</td></tr>';
}

function ls_is_real_future_candidate(?array $selected): bool
{
    if ($selected === null) {
        return false;
    }
    $source = strtolower((string)($selected['source_system'] ?? ''));
    if (strpos($source, 'bolt') === false) {
        return false;
    }
    if (!empty($selected['is_lab_or_test'])) {
        return false;
    }
    if (empty($selected['technical_payload_valid'])) {
        return false;
    }
    if (empty($selected['future_guard_passed']) || !empty($selected['terminal_status'])) {
        return false;
    }
    if (empty($selected['mapping_ready'])) {
        return false;
    }
    return true;
}

function ls_duplicate_clear(?array $selected): bool
{
    if ($selected === null) {
        return false;
    }
    $blockers = array_map('strval', array_merge($selected['live_blockers'] ?? [], $selected['technical_blockers'] ?? []));
    $duplicates = [
        'duplicate_successful_submission',
        'duplicate_live_audit_success_detected',
        'duplicate_submission_attempt_success_detected',
    ];
    return count(array_intersect($duplicates, $blockers)) === 0;
}

function ls_first_live_requirements(?array $selected, array $config, array $globalSession): array
{
    $candidateReady = ls_is_real_future_candidate($selected);
    $sessionReady = !empty($globalSession['ready']);
    $technicalValid = $selected ? !empty($selected['technical_payload_valid']) : false;
    $liveAllowed = $selected ? !empty($selected['live_submission_allowed']) : false;

    $candidateDetail = 'No real future Bolt candidate selected yet.';
    if ($selected && $candidateReady) {
        $candidateDetail = 'Selected booking #' . (string)$selected['booking_id'] . ' is a real future technical candidate.';
    } elseif ($selected) {
        $candidateDetail = 'Selected booking #' . (string)$selected['booking_id'] . ' is only an analyzed row, not a real future candidate.';
    }

    $sessionDetail = $sessionReady
        ? 'Server-side EDXEIX cookie/CSRF session is saved and placeholder-free.'
        : 'Server-side EDXEIX session must be saved/confirmed.';

    return [
        ['label' => 'Real future Bolt candidate exists', 'pass' => $candidateReady, 'detail' => $candidateDetail, 'waiting' => true],
        ['label' => 'Payload technically valid', 'pass' => $technicalValid, 'detail' => $technicalValid ? 'Preflight payload passes technical checks.' : 'Preflight blockers must be cleared.', 'waiting' => true],
        ['label' => 'EDXEIX session ready', 'pass' => $sessionReady, 'detail' => $sessionDetail, 'waiting' => !$sessionReady],
        ['label' => 'EDXEIX submit URL configured', 'pass' => trim((string)($config['edxeix_submit_url'] ?? '')) !== '', 'detail' => 'The exact EDXEIX form action/submit URL must be configured server-side.', 'waiting' => true],
        ['label' => 'Duplicate protection clear', 'pass' => ls_duplicate_clear($selected), 'detail' => 'No successful live submission should already exist for the booking/payload.', 'waiting' => true],
        ['label' => 'Server live flag enabled', 'pass' => !empty($config['live_submit_enabled']), 'detail' => 'Must be enabled only for the approved one-shot live test.', 'waiting' => true],
        ['label' => 'Server HTTP flag enabled', 'pass' => !empty($config['http_submit_enabled']), 'detail' => 'Must be enabled only after final approval.', 'waiting' => true],
        ['label' => 'HTTP transport implemented', 'pass' => false, 'detail' => 'Still intentionally blocked in this preparatory patch.', 'waiting' => false],
        ['label' => 'Live submission currently allowed', 'pass' => $liveAllowed, 'detail' => $liveAllowed ? 'All configured live gates pass.' : 'Live submission remains blocked.', 'waiting' => true],
    ];
}

function ls_blocked_reasons(?array $selected, array $config, array $globalSession, int $realFutureCandidateCount): array
{
    $reasons = [];
    if (empty($config['live_submit_enabled'])) {
        $reasons[] = 'live_submit_config_disabled';
    }
    if (empty($config['http_submit_enabled'])) {
        $reasons[] = 'http_submit_config_disabled';
    }
    if (trim((string)($config['edxeix_submit_url'] ?? '')) === '') {
        $reasons[] = 'edxeix_submit_url_missing';
    }
    if (empty($globalSession['ready'])) {
        $reasons[] = 'edxeix_session_not_ready';
    }
    if ($realFutureCandidateCount === 0) {
        $reasons[] = 'no_real_future_candidate';
    }
    if ($selected === null) {
        $reasons[] = 'no_selected_real_future_candidate';
    } else {
        if (!ls_is_real_future_candidate($selected)) {
            $reasons[] = 'selected_row_not_real_future_candidate';
        }
        foreach (($selected['live_blockers'] ?? []) as $blocker) {
            $reasons[] = (string)$blocker;
        }
        foreach (($selected['technical_blockers'] ?? []) as $blocker) {
            $reasons[] = (string)$blocker;
        }
    }
    $reasons[] = 'http_transport_not_enabled_in_this_patch';
    return array_values(array_unique($reasons));
}

function ls_pick_default_selection(array $analyzedRows): ?array
{
    foreach ($analyzedRows as $candidate) {
        if (!empty($candidate['live_submission_allowed'])) {
            return $candidate;
        }
    }
    foreach ($analyzedRows as $candidate) {
        if (ls_is_real_future_candidate($candidate)) {
            return $candidate;
        }
    }
    return null;
}

function ls_public_selected(?array $selected): ?array
{
    if (!$selected) {
        return null;
    }
    return [
        'booking_id' => $selected['booking_id'] ?? '',
        'order_reference' => $selected['order_reference'] ?? '',
        'source_system' => $selected['source_system'] ?? '',
        'status' => $selected['status'] ?? '',
        'started_at' => $selected['started_at'] ?? '',
        'driver_name' => $selected['driver_name'] ?? '',
        'plate' => $selected['plate'] ?? '',
        'mapping_ready' => !empty($selected['mapping_ready']),
        'future_guard_passed' => !empty($selected['future_guard_passed']),
        'terminal_status' => !empty($selected['terminal_status']),
        'technical_payload_valid' => !empty($selected['technical_payload_valid']),
        'live_submission_allowed' => !empty($selected['live_submission_allowed']),
        'technical_blockers' => $selected['technical_blockers'] ?? [],
        'live_blockers' => $selected['live_blockers'] ?? [],
        'payload_hash' => $selected['payload_hash'] ?? '',
    ];
}

function ls_safe_payload_preview(?array $selected): array
{
    if (!$selected || !is_array($selected['edxeix_payload_preview'] ?? null)) {
        return [];
    }
    $payload = $selected['edxeix_payload_preview'];
    foreach (['_token', 'csrf', 'csrf_token', 'cookie', 'cookie_header'] as $secretKey) {
        if (array_key_exists($secretKey, $payload)) {
            $payload[$secretKey] = '[redacted/loaded server-side only]';
        }
    }
    return $payload;
}

$error = null;
$config = [];
$configState = [];
$globalSession = [];
$db = null;
$analyzedRows = [];
$selected = null;
$postResult = null;
$explicitBookingSelected = false;
$limit = 50;

try {
    $bridgeConfig = gov_bridge_load_config();
    if (!empty($bridgeConfig['app']['timezone'])) {
        date_default_timezone_set((string)$bridgeConfig['app']['timezone']);
    }
    $config = gov_live_load_config();
    $configState = ls_public_config_state($config);
    $globalSession = ls_read_global_session_state($config);
    $db = gov_bridge_db();
    $limit = gov_bridge_int_param('limit', 50, 1, 200);
    $analyzedRows = gov_live_analyzed_candidates($db, $limit);

    $bookingId = ls_request_param('booking_id', '');
    if ($bookingId !== '') {
        $explicitBookingSelected = true;
        $booking = gov_live_booking_by_id($db, $bookingId);
        if ($booking) {
            $selected = gov_live_analyze_booking($db, $booking, $config);
        }
    } else {
        $selected = ls_pick_default_selection($analyzedRows);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $postResult = [
            'ok' => false,
            'submitted' => false,
            'blocked' => true,
            'response' => [
                'status' => 0,
                'success' => false,
                'body' => 'Live HTTP transport is disabled in this preparatory patch. No EDXEIX request was performed.',
            ],
        ];
    }
} catch (Throwable $e) {
    $error = $e->getMessage();
}

$config = $config ?: gov_live_load_config();
$configState = $configState ?: ls_public_config_state($config);
$globalSession = $globalSession ?: ls_read_global_session_state($config);
$phrase = (string)($config['confirmation_phrase'] ?? 'I UNDERSTAND SUBMIT LIVE TO EDXEIX');
$liveEnabled = !empty($config['live_submit_enabled']);
$httpEnabled = !empty($config['http_submit_enabled']);
$submitUrlConfigured = trim((string)($config['edxeix_submit_url'] ?? '')) !== '';
$realFutureCandidateRows = array_values(array_filter($analyzedRows, static fn(array $row): bool => ls_is_real_future_candidate($row)));
$realFutureCandidateCount = count($realFutureCandidateRows);
$liveReadyCount = count(array_filter($analyzedRows, static fn(array $row): bool => !empty($row['live_submission_allowed'])));
$selectedIsRealFutureCandidate = ls_is_real_future_candidate($selected);
$blockedReasons = ls_blocked_reasons($selected, $config, $globalSession, $realFutureCandidateCount);
$requirements = ls_first_live_requirements($selected, $config, $globalSession);

if (ls_request_param('format', '') === 'json') {
    ls_json_response([
        'ok' => $error === null,
        'script' => 'ops/live-submit.php',
        'generated_at' => date('Y-m-d H:i:s'),
        'read_only_when_get' => $_SERVER['REQUEST_METHOD'] !== 'POST',
        'calls_bolt' => false,
        'calls_edxeix' => false,
        'writes_database_on_get' => false,
        'live_http_transport_enabled_in_this_patch' => false,
        'config_state' => $configState,
        'global_session_state' => $globalSession,
        'analyzed_rows' => count($analyzedRows),
        'real_future_candidate_rows' => $realFutureCandidateCount,
        'live_ready_rows' => $liveReadyCount,
        'auto_selected_only_real_future_candidates' => true,
        'explicit_booking_selected' => $explicitBookingSelected,
        'selected_is_real_future_candidate' => $selectedIsRealFutureCandidate,
        'why_live_is_blocked' => $blockedReasons,
        'first_live_submit_requirements' => $requirements,
        'selected' => ls_public_selected($selected),
        'post_result' => $postResult,
        'error' => $error,
        'note' => 'Production readiness refinement only. No EDXEIX HTTP request is performed by this patch.',
    ]);
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Live EDXEIX Submit Gate | gov.cabnet.app</title>
    <style>
        :root { --bg:#f3f6fb; --panel:#fff; --ink:#07152f; --muted:#41577a; --line:#d7e1ef; --nav:#081225; --blue:#2563eb; --green:#07875a; --orange:#b85c00; --red:#b42318; --slate:#334155; --soft:#f8fbff; }
        *{box-sizing:border-box} body{margin:0;background:var(--bg);color:var(--ink);font-family:Arial,Helvetica,sans-serif}.nav{background:var(--nav);color:#fff;min-height:56px;display:flex;align-items:center;gap:18px;padding:0 26px;position:sticky;top:0;z-index:5;overflow:auto}.nav strong{white-space:nowrap}.nav a{color:#fff;text-decoration:none;font-size:15px;white-space:nowrap;opacity:.92}.nav a:hover{opacity:1;text-decoration:underline}.wrap{width:min(1480px,calc(100% - 48px));margin:26px auto 60px}.card{background:var(--panel);border:1px solid var(--line);border-radius:14px;padding:18px;margin-bottom:18px;box-shadow:0 10px 26px rgba(8,18,37,.04)}h1{font-size:34px;margin:0 0 12px}h2{font-size:23px;margin:0 0 14px}h3{margin:0 0 8px}p{color:var(--muted);line-height:1.45}.hero{border-left:7px solid var(--red)}.safe{border-left:7px solid var(--green)}.warn{border-left:7px solid var(--orange)}.grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-top:14px}.metric{border:1px solid var(--line);border-radius:10px;padding:14px;background:var(--soft);min-height:82px}.metric strong{display:block;font-size:28px;line-height:1.05;word-break:break-word}.metric span{color:var(--muted);font-size:14px}.badge{display:inline-block;padding:5px 9px;border-radius:999px;font-size:12px;font-weight:700;margin:1px 3px 1px 0;white-space:nowrap}.badge-good{background:#dcfce7;color:#166534}.badge-warn{background:#fff7ed;color:#b45309}.badge-bad{background:#fee2e2;color:#991b1b}.badge-neutral{background:#eaf1ff;color:#1e40af}.actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:12px}.btn,button{display:inline-block;padding:10px 14px;border-radius:8px;color:#fff;text-decoration:none;font-weight:700;background:var(--blue);font-size:14px;border:0;cursor:pointer}.btn.dark{background:var(--slate)}.btn.orange{background:var(--orange)}button.danger{background:var(--red)}button[disabled]{opacity:.45;cursor:not-allowed}.table-wrap{overflow:auto;border:1px solid var(--line);border-radius:10px}table{width:100%;border-collapse:collapse;min-width:950px}th,td{text-align:left;padding:10px 12px;border-bottom:1px solid var(--line);vertical-align:top;font-size:14px}th{background:#f8fafc;font-size:12px;text-transform:uppercase;letter-spacing:.02em}.list{margin:0;padding-left:18px;color:var(--muted)}.list li{margin:7px 0}.badline{color:#991b1b}.goodline{color:#166534}.warnline{color:#b45309}.small{font-size:13px;color:var(--muted)}code{background:#eef2ff;padding:2px 5px;border-radius:5px}pre{background:#0b1020;color:#d7e3ff;padding:14px;border-radius:12px;max-height:420px;overflow:auto}.two{display:grid;grid-template-columns:1fr 1fr;gap:18px}.three{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}.callout{background:#fff7ed;border:1px solid #fed7aa;border-left:7px solid var(--orange);border-radius:14px;padding:16px;margin-bottom:18px}.callout.danger{background:#fef3f2;border-color:#fecaca;border-left-color:var(--red)}.callout.good{background:#ecfdf3;border-color:#bbf7d0;border-left-color:var(--green)}input{width:100%;padding:10px 12px;border:1px solid var(--line);border-radius:8px;font-size:14px}label{display:block;font-weight:700;font-size:13px;margin-bottom:5px}@media(max-width:1100px){.grid{grid-template-columns:repeat(2,minmax(0,1fr))}.two,.three{grid-template-columns:1fr}}@media(max-width:720px){.grid{grid-template-columns:1fr}.wrap{width:calc(100% - 24px);margin-top:14px}.nav{padding:0 14px}}
    </style>
</head>
<body>
<nav class="nav">
    <strong>GC gov.cabnet.app</strong>
    <a href="/ops/index.php">Operations Console</a>
    <a href="/ops/readiness.php">Readiness</a>
    <a href="/ops/future-test.php">Future Test</a>
    <a href="/ops/mappings.php">Mappings</a>
    <a href="/ops/jobs.php">Jobs</a>
    <a href="/ops/live-submit.php">Live Submit Gate</a>
    <a href="/ops/edxeix-session.php">EDXEIX Session</a>
    <a href="/ops/help.php">Help</a>
</nav>

<main class="wrap">
    <section class="callout danger">
        <strong>LIVE HTTP TRANSPORT IS STILL BLOCKED.</strong>
        This page prepares the production workflow, but this patch cannot submit to EDXEIX. It is safe to run and safe to review.
    </section>

    <section class="card hero">
        <h1>Live EDXEIX Submit Gate</h1>
        <p>Final production control panel scaffold. It explains exactly why live submission is blocked now and what must be completed before the first approved live submit.</p>
        <div>
            <?= ls_badge('LIVE HTTP TRANSPORT BLOCKED', 'bad') ?>
            <?= $liveEnabled ? ls_badge('CONFIG LIVE ENABLED', 'warn') : ls_badge('CONFIG LIVE DISABLED', 'good') ?>
            <?= $httpEnabled ? ls_badge('HTTP CONFIG ENABLED', 'warn') : ls_badge('HTTP CONFIG DISABLED', 'good') ?>
            <?= $submitUrlConfigured ? ls_badge('EDXEIX URL CONFIGURED', 'good') : ls_badge('EDXEIX URL MISSING', 'bad') ?>
            <?= !empty($globalSession['ready']) ? ls_badge('EDXEIX SESSION READY', 'good') : ls_badge('EDXEIX SESSION NOT READY', 'bad') ?>
            <?= ls_badge('OPS GUARDED', 'good') ?>
        </div>
        <?php if ($error): ?><p class="badline"><strong>Error:</strong> <?= ls_h($error) ?></p><?php endif; ?>
        <?php if ($postResult): ?>
            <p class="warnline"><strong>POST result:</strong> <?= ls_h($postResult['response']['body'] ?? 'Blocked. No EDXEIX request performed.') ?></p>
        <?php endif; ?>
        <div class="actions">
            <a class="btn" href="/ops/live-submit.php?format=json">Open Gate JSON</a>
            <a class="btn dark" href="/ops/edxeix-session.php">Open EDXEIX Session</a>
            <a class="btn dark" href="/ops/future-test.php">Open Future Test</a>
            <a class="btn orange" href="/bolt_edxeix_preflight.php?limit=30">Open Preflight</a>
        </div>
        <div class="grid">
            <div class="metric"><strong><?= count($analyzedRows) ?></strong><span>Analyzed recent rows</span></div>
            <div class="metric"><strong><?= $realFutureCandidateCount ?></strong><span>Real future candidates</span></div>
            <div class="metric"><strong><?= $liveReadyCount ?></strong><span>Live-eligible rows</span></div>
            <div class="metric"><strong>no</strong><span>Live HTTP execution</span></div>
        </div>
    </section>

    <?php if (!$selected && $realFutureCandidateCount === 0): ?>
    <section class="callout good">
        <strong>No live candidate is selected.</strong>
        This is correct right now. Historical finished/cancelled Bolt rows remain visible below as analyzed rows, but they are not selected automatically and must never be submitted.
    </section>
    <?php elseif ($selected && !$selectedIsRealFutureCandidate): ?>
    <section class="callout danger">
        <strong>Selected row is not a real future candidate.</strong>
        It is shown for review only because it was opened explicitly or has blockers. Do not treat this as live-ready.
    </section>
    <?php endif; ?>

    <section class="card warn">
        <h2>Why live submission is blocked now</h2>
        <p class="small">These are the current blocker reasons. EDXEIX session/config can be ready while live submission still remains blocked until a real future Bolt candidate and final transport patch exist.</p>
        <div class="table-wrap"><table>
            <thead><tr><th>Blocker</th><th>Meaning</th></tr></thead>
            <tbody>
            <?php foreach ($blockedReasons as $reason): ?>
                <tr><td><code><?= ls_h($reason) ?></code></td><td><?= ls_h(ls_blocker_meaning($reason)) ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    </section>

    <section class="card safe">
        <h2>First Live Submit Requirements</h2>
        <p class="small">Global EDXEIX prerequisites can pass before a Bolt candidate exists. Candidate-specific items stay waiting until the real future Bolt ride appears.</p>
        <div class="table-wrap"><table>
            <thead><tr><th>Requirement</th><th>Status</th><th>Detail</th></tr></thead>
            <tbody>
            <?php foreach ($requirements as $row): ?>
                <?= ls_status_row($row['label'], (bool)$row['pass'], (string)$row['detail'], (bool)$row['waiting']) ?>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    </section>

    <section class="card safe">
        <h2>Current Safety Gates</h2>
        <div class="table-wrap"><table>
            <thead><tr><th>Gate</th><th>Status</th><th>Meaning</th></tr></thead>
            <tbody>
                <tr><td><strong>Server config live_submit_enabled</strong></td><td><?= ls_bool_badge($liveEnabled, 'enabled', 'disabled') ?></td><td>Must be true in server-only config for a future live patch.</td></tr>
                <tr><td><strong>Server config http_submit_enabled</strong></td><td><?= ls_bool_badge($httpEnabled, 'enabled', 'disabled') ?></td><td>Must be true in server-only config for a future live patch.</td></tr>
                <tr><td><strong>EDXEIX URL configured</strong></td><td><?= ls_bool_badge($submitUrlConfigured, 'configured', 'missing') ?></td><td>Host: <?= ls_h($configState['edxeix_submit_url_host'] ?: 'not configured') ?></td></tr>
                <tr><td><strong>EDXEIX session ready</strong></td><td><?= ls_bool_badge(!empty($globalSession['ready']), 'ready', 'not ready') ?></td><td>Global server-side cookie/CSRF readiness. Secrets are never displayed.</td></tr>
                <tr><td><strong>Real future Bolt candidate</strong></td><td><?= ls_bool_badge($realFutureCandidateCount > 0, 'available', 'waiting') ?></td><td>Requires a valid future Bolt row with mapping and future guard passing.</td></tr>
                <tr><td><strong>HTTP transport in this patch</strong></td><td><?= ls_badge('blocked', 'bad') ?></td><td>This patch intentionally refuses live HTTP even if other gates are toggled.</td></tr>
            </tbody>
        </table></div>
    </section>

    <section class="three">
        <div class="card">
            <h2>Before the real test</h2>
            <ul class="list">
                <li>Use Filippos only: <strong>EDXEIX driver 17585</strong>.</li>
                <li>Use mapped vehicle: <strong>EMX6874 → 13799</strong> or <strong>EHA2545 → 5949</strong>.</li>
                <li>Schedule/create the Bolt ride <strong>40–60 minutes in the future</strong>.</li>
                <li>Do not use Georgios until his exact EDXEIX ID is confirmed.</li>
            </ul>
        </div>
        <div class="card">
            <h2>After the ride appears</h2>
            <ul class="list">
                <li>Run/order sync.</li>
                <li>Open Future Test.</li>
                <li>Open Preflight and verify the payload.</li>
                <li>Run dry-run queue/worker first.</li>
                <li>Confirm live attempts remain zero.</li>
            </ul>
        </div>
        <div class="card">
            <h2>Final live phase later</h2>
            <ul class="list">
                <li>Enable one booking/order lock in config.</li>
                <li>Apply final HTTP transport patch.</li>
                <li>Enable live flags only for the approved one-shot test.</li>
                <li>Submit once, audit, then disable again.</li>
            </ul>
        </div>
    </section>

    <section class="two">
        <div class="card">
            <h2>Selected Booking Review</h2>
            <?php if (!$selected): ?>
                <p class="warnline"><strong>No booking selected.</strong> A technically valid real future Bolt candidate is needed before live submission can ever be considered.</p>
            <?php else: ?>
                <ul class="list">
                    <li>Booking ID: <strong><?= ls_h($selected['booking_id']) ?></strong></li>
                    <li>Order reference: <code><?= ls_h($selected['order_reference']) ?></code></li>
                    <li>Source: <strong><?= ls_h($selected['source_system']) ?></strong></li>
                    <li>Status: <strong><?= ls_h($selected['status']) ?></strong></li>
                    <li>Started at: <strong><?= ls_h($selected['started_at']) ?></strong></li>
                    <li>Driver: <strong><?= ls_h($selected['driver_name']) ?></strong></li>
                    <li>Plate: <strong><?= ls_h($selected['plate']) ?></strong></li>
                    <li>Real future candidate: <?= ls_bool_badge($selectedIsRealFutureCandidate, 'yes', 'no') ?></li>
                    <li>Technical payload: <?= ls_bool_badge(!empty($selected['technical_payload_valid']), 'valid', 'blocked') ?></li>
                    <li>Live allowed: <?= ls_bool_badge(!empty($selected['live_submission_allowed']), 'allowed', 'blocked') ?></li>
                </ul>
                <?php if (!empty($selected['technical_blockers'])): ?><p class="badline"><strong>Technical blockers:</strong> <?= ls_h(implode(', ', $selected['technical_blockers'])) ?></p><?php endif; ?>
                <?php if (!empty($selected['live_blockers'])): ?><p class="badline"><strong>Live blockers:</strong> <?= ls_h(implode(', ', $selected['live_blockers'])) ?></p><?php endif; ?>
            <?php endif; ?>
        </div>
        <div class="card warn">
            <h2>Disabled Submit Form</h2>
            <p>This form validates the final operator flow. In this patch it remains disabled and performs no EDXEIX HTTP request.</p>
            <form method="post">
                <label for="booking_id">Booking ID</label>
                <input id="booking_id" name="booking_id" value="<?= ls_h($selected['booking_id'] ?? '') ?>" placeholder="real normalized booking id" required>
                <label for="confirm" style="margin-top:10px;">Confirmation phrase</label>
                <input id="confirm" name="confirm" value="" placeholder="<?= ls_h($phrase) ?>" required>
                <p class="small">Required phrase later: <code><?= ls_h($phrase) ?></code></p>
                <button class="danger" type="submit" disabled>Submit to EDXEIX — Disabled in this patch</button>
            </form>
        </div>
    </section>

    <section class="card">
        <h2>Analyzed Recent Bookings</h2>
        <p class="small">Rows listed here are analyzed for live-submission safety. Historical or terminal rows should remain blocked and are not selected automatically.</p>
        <?php if (!$analyzedRows): ?>
            <p>No rows are currently available for analysis. This is expected until the real Bolt test ride exists.</p>
        <?php else: ?>
            <div class="table-wrap"><table>
                <thead><tr><th>Booking</th><th>Order Ref</th><th>Source</th><th>Status</th><th>Started</th><th>Driver</th><th>Plate</th><th>Real Future</th><th>Live</th><th>Open</th></tr></thead>
                <tbody>
                <?php foreach ($analyzedRows as $row): ?>
                    <?php $isFutureCandidate = ls_is_real_future_candidate($row); ?>
                    <tr>
                        <td><?= ls_h($row['booking_id']) ?></td>
                        <td><code><?= ls_h($row['order_reference']) ?></code></td>
                        <td><?= ls_h($row['source_system']) ?></td>
                        <td><?= ls_h($row['status']) ?></td>
                        <td><?= ls_h($row['started_at']) ?></td>
                        <td><?= ls_h($row['driver_name']) ?></td>
                        <td><?= ls_h($row['plate']) ?></td>
                        <td><?= ls_bool_badge($isFutureCandidate, 'yes', 'no') ?></td>
                        <td><?= ls_bool_badge(!empty($row['live_submission_allowed']), 'allowed', 'blocked') ?></td>
                        <td><a class="btn dark" href="/ops/live-submit.php?booking_id=<?= urlencode((string)$row['booking_id']) ?>">Open</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
        <?php endif; ?>
    </section>

    <?php if ($selected): ?>
    <section class="card">
        <h2>Sanitized Payload Preview</h2>
        <p class="small">Secret/session fields are redacted or loaded server-side only. Review values, but do not submit from this patch.</p>
        <pre><?= ls_h(json_encode(ls_safe_payload_preview($selected), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></pre>
    </section>
    <?php endif; ?>
</main>
</body>
</html>
