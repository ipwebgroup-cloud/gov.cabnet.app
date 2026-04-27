<?php
/**
 * gov.cabnet.app — EDXEIX Final Submission Gate v3.3
 *
 * Purpose:
 * - Provide a final read-only go/no-go gate before any future live-submit handler.
 * - Combine session freshness, authenticated form contract, payload shape, preflight eligibility, and hard-stop blockers.
 *
 * Safety contract:
 * - Does not call Bolt.
 * - Does not call EDXEIX.
 * - Does not POST.
 * - Reads local config/session metadata and recent normalized bookings only.
 * - Does not write database rows or files.
 * - Does not stage jobs.
 * - Does not update mappings.
 * - Does not print cookies, token values, raw session JSON, or passenger payload values.
 * - Live EDXEIX submission remains disabled.
 */

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Robots-Tag: noindex,nofollow', true);

require_once '/home/cabnet/gov.cabnet.app_app/lib/bolt_sync_lib.php';

function fsg_h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function fsg_badge(string $text, string $type = 'neutral'): string { return '<span class="badge badge-' . fsg_h($type) . '">' . fsg_h($text) . '</span>'; }
function fsg_yes(bool $v, string $yes = 'YES', string $no = 'NO'): string { return $v ? fsg_badge($yes, 'good') : fsg_badge($no, 'bad'); }
function fsg_warn(bool $v, string $yes = 'YES', string $no = 'NO'): string { return $v ? fsg_badge($yes, 'warn') : fsg_badge($no, 'good'); }
function fsg_metric($value, string $label): string { return '<div class="metric"><strong>' . fsg_h((string)$value) . '</strong><span>' . fsg_h($label) . '</span></div>'; }

function fsg_value(array $row, array $keys, $default = '') {
    foreach ($keys as $key) {
        if (array_key_exists($key, $row) && $row[$key] !== null && $row[$key] !== '') return $row[$key];
    }
    return $default;
}

function fsg_boolish($value): bool {
    if (is_bool($value)) return $value;
    return in_array(strtolower(trim((string)$value)), ['1','true','yes','on'], true);
}

function fsg_terminal(string $status): bool {
    $s = strtolower(trim($status));
    if ($s === '') return false;
    $terminal = ['finished','completed','client_cancelled','driver_cancelled','driver_cancelled_after_accept','cancelled','canceled','expired','rejected','failed'];
    return in_array($s, $terminal, true) || strpos($s, 'cancel') !== false || strpos($s, 'finished') !== false || strpos($s, 'complete') !== false;
}

function fsg_safe_url_display($url): string {
    $url = trim((string)$url);
    if ($url === '') return '';
    $parts = parse_url($url);
    if (!is_array($parts)) return '[configured]';
    $scheme = $parts['scheme'] ?? 'https';
    $host = $parts['host'] ?? '';
    $path = $parts['path'] ?? '';
    if ($host === '') return '[configured]';
    return $scheme . '://' . $host . $path;
}

function fsg_has_key_like($value, array $needles): bool {
    if (!is_array($value)) return false;
    foreach ($value as $key => $item) {
        foreach ($needles as $needle) {
            if (stripos((string)$key, $needle) !== false && $item !== null && $item !== '') return true;
        }
        if (is_array($item) && fsg_has_key_like($item, $needles)) return true;
    }
    return false;
}

function fsg_find_session_path(array $config): string {
    $edx = is_array($config['edxeix'] ?? null) ? $config['edxeix'] : [];
    foreach (['session_file', 'session_cookie_file', 'cookie_file'] as $key) {
        if (!empty($edx[$key]) && is_string($edx[$key]) && basename((string)$edx[$key]) === 'edxeix_session.json') {
            return (string)$edx[$key];
        }
    }
    $paths = function_exists('gov_bridge_paths') ? gov_bridge_paths() : [];
    if (!empty($paths['runtime'])) return rtrim((string)$paths['runtime'], '/') . '/edxeix_session.json';
    return '/home/cabnet/gov.cabnet.app_app/storage/runtime/edxeix_session.json';
}

function fsg_session_metadata(array $config, int $freshMinutes): array {
    $path = fsg_find_session_path($config);
    $out = [
        'basename' => basename($path),
        'path_hint' => dirname($path),
        'exists' => is_file($path),
        'readable' => is_readable($path),
        'modified_at' => is_file($path) ? date('Y-m-d H:i:s', (int)@filemtime($path)) : '',
        'age_seconds' => is_file($path) ? max(0, time() - (int)@filemtime($path)) : null,
        'fresh_minutes' => $freshMinutes,
        'fresh_enough' => false,
        'json_valid' => false,
        'csrf_token_present' => false,
        'cookie_like_present' => false,
        'lease_create_url_confirmed' => false,
        'submit_url_confirmed' => false,
        'safe_metadata' => [],
        'json_keys' => [],
    ];

    if (!$out['exists'] || !$out['readable']) return $out;
    $out['fresh_enough'] = $out['age_seconds'] !== null && $out['age_seconds'] <= ($freshMinutes * 60);

    $raw = @file_get_contents($path);
    $json = is_string($raw) ? json_decode($raw, true) : null;
    if (!is_array($json)) return $out;

    $out['json_valid'] = true;
    $out['json_keys'] = array_slice(array_keys($json), 0, 50);
    $out['csrf_token_present'] = fsg_has_key_like($json, ['csrf_token', 'csrf', 'xsrf', '_token']);
    $out['cookie_like_present'] = fsg_has_key_like($json, ['cookie', 'session']);

    foreach (['saved_at','updated_at','source','source_url','detected_form_action','fixed_submit_url_used','extension_version','note'] as $key) {
        if (!array_key_exists($key, $json)) continue;
        if (in_array($key, ['source_url','detected_form_action','fixed_submit_url_used'], true)) {
            $out['safe_metadata'][$key] = fsg_safe_url_display((string)$json[$key]);
        } else {
            $out['safe_metadata'][$key] = is_scalar($json[$key]) ? (string)$json[$key] : '[non-scalar metadata]';
        }
    }

    $sourceUrl = (string)($out['safe_metadata']['source_url'] ?? '');
    $formAction = (string)($out['safe_metadata']['detected_form_action'] ?? '');
    $fixedSubmit = (string)($out['safe_metadata']['fixed_submit_url_used'] ?? '');
    $out['lease_create_url_confirmed'] = (
        strpos($sourceUrl, '/dashboard/lease-agreement/create') !== false ||
        strpos($formAction, '/dashboard/lease-agreement/create') !== false
    );
    $out['submit_url_confirmed'] = strpos($fixedSubmit, '/dashboard/lease-agreement') !== false;

    return $out;
}

function fsg_flatten_keys($value, string $prefix = ''): array {
    $keys = [];
    if (!is_array($value)) return $keys;
    foreach ($value as $key => $item) {
        $key = (string)$key;
        if ($key === '_mapping_status') continue;
        $flat = $prefix === '' ? $key : $prefix . '[' . $key . ']';
        $keys[$flat] = true;
        if (is_array($item)) {
            foreach (fsg_flatten_keys($item, $flat) as $nested => $true) $keys[$nested] = true;
        }
    }
    return $keys;
}

function fsg_contract_required(): array {
    return [
        '_token' => 'session_csrf',
        'broker' => 'payload',
        'lessor' => 'payload',
        'lessee[type]' => 'payload',
        'lessee[name]' => 'payload',
        'driver' => 'payload',
        'vehicle' => 'payload',
        'starting_point_id' => 'payload',
        'boarding_point' => 'payload',
        'disembark_point' => 'payload',
        'drafted_at' => 'payload',
        'started_at' => 'payload',
        'ended_at' => 'payload',
        'price' => 'payload',
    ];
}

function fsg_contract_optional(): array {
    return [
        'lessee[vat_number]' => 'payload',
        'lessee[legal_representative]' => 'payload',
        'coordinates' => 'payload',
    ];
}

function fsg_analyze_booking(mysqli $db, array $booking, array $session): array {
    $preview = function_exists('gov_build_edxeix_preview_payload') ? gov_build_edxeix_preview_payload($db, $booking) : [];
    $flat = fsg_flatten_keys($preview);

    $missingRequired = [];
    $requiredStatus = [];
    foreach (fsg_contract_required() as $field => $source) {
        $present = $field === '_token' ? !empty($session['csrf_token_present']) : isset($flat[$field]);
        $requiredStatus[$field] = ['source' => $source, 'present' => $present];
        if (!$present) $missingRequired[] = $field;
    }

    $mapping = is_array($preview['_mapping_status'] ?? null) ? $preview['_mapping_status'] : [];
    $status = (string)fsg_value($booking, ['order_status','status'], '');
    $startedAt = (string)fsg_value($booking, ['started_at'], '');
    $orderRef = (string)fsg_value($booking, ['order_reference','external_order_id','external_reference','source_trip_reference','source_trip_id'], '');
    $source = strtolower((string)fsg_value($booking, ['source_system','source_type','source'], ''));
    $refUpper = strtoupper($orderRef);
    $lab = strpos($source, 'lab') !== false || strpos($refUpper, 'LAB-') === 0;
    $test = fsg_boolish($booking['is_test_booking'] ?? false);
    $never = fsg_boolish($booking['never_submit_live'] ?? false) || $test;

    $driverMapped = !empty($mapping['driver_mapped']);
    $vehicleMapped = !empty($mapping['vehicle_mapped']);
    $futureGuard = !empty($mapping['passes_future_guard']);
    $terminal = fsg_terminal($status);

    $blockers = [];
    if (!$driverMapped) $blockers[] = 'driver_not_mapped';
    if (!$vehicleMapped) $blockers[] = 'vehicle_not_mapped';
    if ($startedAt === '') $blockers[] = 'missing_started_at';
    elseif (!$futureGuard) $blockers[] = 'started_at_not_30_min_future';
    if ($terminal) $blockers[] = 'terminal_order_status';
    if ($lab) $blockers[] = 'lab_row_blocked';
    if ($never) $blockers[] = 'never_submit_live';
    if (!empty($missingRequired)) $blockers[] = 'form_contract_missing_required_fields';

    $contractReady = !empty($preview) && empty($missingRequired);

    return [
        'id' => $booking['id'] ?? null,
        'order_reference_hint' => $orderRef !== '' ? substr($orderRef, 0, 16) . '…' : '',
        'status' => $status,
        'started_at' => $startedAt,
        'driver_name' => fsg_value($booking, ['driver_name','external_driver_name'], ''),
        'plate' => fsg_value($booking, ['vehicle_plate','plate'], ''),
        'payload_built' => !empty($preview),
        'contract_ready' => $contractReady,
        'required_status' => $requiredStatus,
        'missing_required' => $missingRequired,
        'mapping_ready' => $driverMapped && $vehicleMapped,
        'future_guard_passed' => $futureGuard,
        'terminal_status' => $terminal,
        'never_submit_live' => $never,
        'is_lab_row' => $lab,
        'live_candidate' => empty($blockers),
        'blockers' => $blockers,
    ];
}

function fsg_age_label($seconds): string {
    if ($seconds === null) return 'n/a';
    $seconds = (int)$seconds;
    if ($seconds < 60) return $seconds . ' sec';
    if ($seconds < 3600) return floor($seconds / 60) . ' min';
    if ($seconds < 86400) return floor($seconds / 3600) . ' hr';
    return floor($seconds / 86400) . ' days';
}

function fsg_json_response(array $payload): void {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit;
}

$format = strtolower(trim((string)($_GET['format'] ?? 'html')));
$limit = max(1, min(50, (int)($_GET['limit'] ?? 20)));
$freshMinutes = max(5, min(1440, (int)($_GET['fresh_minutes'] ?? 120)));

$state = [
    'config_loaded' => false,
    'db_loaded' => false,
    'session' => [],
    'rows' => [],
    'error' => null,
];

try {
    $config = gov_bridge_load_config();
    if (!empty($config['app']['timezone'])) date_default_timezone_set((string)$config['app']['timezone']);
    $state['config_loaded'] = true;
    $state['session'] = fsg_session_metadata($config, $freshMinutes);
    $db = gov_bridge_db();
    $state['db_loaded'] = true;
    foreach (gov_recent_rows($db, 'normalized_bookings', $limit) as $booking) {
        $state['rows'][] = fsg_analyze_booking($db, $booking, $state['session']);
    }
} catch (Throwable $e) {
    $state['error'] = $e->getMessage();
}

$rowsChecked = count($state['rows']);
$payloadsBuilt = count(array_filter($state['rows'], static fn($r) => !empty($r['payload_built'])));
$contractReadyRows = count(array_filter($state['rows'], static fn($r) => !empty($r['contract_ready'])));
$liveCandidates = array_values(array_filter($state['rows'], static fn($r) => !empty($r['live_candidate'])));
$eligibleCount = count($liveCandidates);
$blockedRows = $rowsChecked - $eligibleCount;

$globalBlockers = [];
if (!$state['config_loaded']) $globalBlockers[] = 'config_not_loaded';
if (!$state['db_loaded']) $globalBlockers[] = 'database_not_available';
if (!function_exists('gov_build_edxeix_preview_payload')) $globalBlockers[] = 'payload_builder_missing';
if (empty($state['session']['exists'])) $globalBlockers[] = 'session_file_missing';
if (empty($state['session']['readable'])) $globalBlockers[] = 'session_file_not_readable';
if (empty($state['session']['json_valid'])) $globalBlockers[] = 'session_json_invalid';
if (empty($state['session']['cookie_like_present'])) $globalBlockers[] = 'session_cookie_missing';
if (empty($state['session']['csrf_token_present'])) $globalBlockers[] = 'session_csrf_missing';
if (empty($state['session']['fresh_enough'])) $globalBlockers[] = 'session_not_fresh';
if (empty($state['session']['lease_create_url_confirmed'])) $globalBlockers[] = 'lease_create_url_not_confirmed';
if (empty($state['session']['submit_url_confirmed'])) $globalBlockers[] = 'submit_url_not_confirmed';
if ($payloadsBuilt < 1) $globalBlockers[] = 'no_payload_previews_built';
if ($contractReadyRows < 1) $globalBlockers[] = 'no_contract_ready_rows';

$gateMechanicsReady = empty($globalBlockers);
$readyForExplicitApproval = $gateMechanicsReady && $eligibleCount > 0;

$decision = 'FINAL_SUBMIT_GATE_BLOCKED';
$decisionType = 'bad';
$decisionText = 'One or more submit-gate prerequisites are not ready.';
if ($readyForExplicitApproval) {
    $decision = 'FINAL_SUBMIT_GATE_READY_FOR_EXPLICIT_APPROVAL';
    $decisionType = 'warn';
    $decisionText = 'A future-safe candidate appears eligible and mechanics are ready. Do not POST without explicit final approval.';
} elseif ($gateMechanicsReady) {
    $decision = 'FINAL_SUBMIT_GATE_PREPARED_NO_ELIGIBLE_CANDIDATE';
    $decisionType = 'good';
    $decisionText = 'Submission mechanics are prepared, but no eligible future-safe Bolt candidate is available.';
} elseif (!empty($state['session']['json_valid']) && empty($state['session']['fresh_enough'])) {
    $decision = 'FINAL_SUBMIT_GATE_BLOCKED_SESSION_STALE';
    $decisionType = 'warn';
    $decisionText = 'Session metadata exists, but it is older than the selected freshness window. Refresh before any final live test.';
}

$payload = [
    'ok' => $state['error'] === null,
    'script' => 'ops/edxeix-final-submit-gate.php',
    'generated_at' => date('c'),
    'safety_contract' => [
        'calls_bolt' => false,
        'calls_edxeix' => false,
        'posts_to_edxeix' => false,
        'reads_database' => true,
        'writes_database' => false,
        'writes_files' => false,
        'stages_jobs' => false,
        'updates_mappings' => false,
        'prints_secrets' => false,
        'raw_session_json_returned' => false,
        'passenger_payload_values_returned' => false,
        'live_edxeix_submission' => 'disabled_not_used',
        'purpose' => 'final read-only go/no-go gate before any future submit handler',
    ],
    'decision' => [
        'code' => $decision,
        'type' => $decisionType,
        'text' => $decisionText,
    ],
    'checks' => [
        'config_loaded' => $state['config_loaded'],
        'database_read_ok' => $state['db_loaded'],
        'payload_builder_available' => function_exists('gov_build_edxeix_preview_payload'),
        'session_file_exists' => !empty($state['session']['exists']),
        'session_json_valid' => !empty($state['session']['json_valid']),
        'session_cookie_like_present' => !empty($state['session']['cookie_like_present']),
        'session_csrf_token_present' => !empty($state['session']['csrf_token_present']),
        'session_fresh_enough' => !empty($state['session']['fresh_enough']),
        'lease_create_url_confirmed' => !empty($state['session']['lease_create_url_confirmed']),
        'submit_url_confirmed' => !empty($state['session']['submit_url_confirmed']),
        'rows_checked' => $rowsChecked,
        'payloads_built' => $payloadsBuilt,
        'contract_ready_rows' => $contractReadyRows,
        'eligible_live_candidates' => $eligibleCount,
        'blocked_rows' => $blockedRows,
        'gate_mechanics_ready' => $gateMechanicsReady,
        'ready_for_explicit_approval' => $readyForExplicitApproval,
    ],
    'global_blockers' => $globalBlockers,
    'session_metadata' => [
        'basename' => $state['session']['basename'] ?? '',
        'path_hint' => $state['session']['path_hint'] ?? '',
        'modified_at' => $state['session']['modified_at'] ?? '',
        'age_seconds' => $state['session']['age_seconds'] ?? null,
        'age_label' => fsg_age_label($state['session']['age_seconds'] ?? null),
        'fresh_minutes' => $freshMinutes,
        'safe_metadata' => $state['session']['safe_metadata'] ?? [],
    ],
    'submit_envelope_design' => [
        'method' => 'POST',
        'form_page_get_url' => '/dashboard/lease-agreement/create',
        'submit_action_url' => '/dashboard/lease-agreement',
        'csrf_source' => 'saved_session_csrf_token',
        'field_contract' => [
            'required' => fsg_contract_required(),
            'optional_or_empty_allowed' => fsg_contract_optional(),
        ],
        'post_will_not_run_from_this_page' => true,
    ],
    'rows' => $state['rows'],
    'error' => $state['error'],
    'links' => [
        'html' => '/ops/edxeix-final-submit-gate.php',
        'json' => '/ops/edxeix-final-submit-gate.php?format=json',
        'form_contract_json' => '/ops/edxeix-form-contract.php?format=json',
        'target_matrix_json' => '/ops/edxeix-target-matrix.php?probe=1&follow=1&format=json',
        'submit_readiness_json' => '/ops/edxeix-submit-readiness.php?format=json',
        'preflight_review' => '/ops/preflight-review.php',
        'test_session' => '/ops/test-session.php',
        'route_index' => '/ops/route-index.php',
    ],
];

if ($format === 'json') fsg_json_response($payload);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>EDXEIX Final Submit Gate | gov.cabnet.app</title>
    <link rel="stylesheet" href="/assets/css/gov-ops-edxeix.css?v=3.3">
</head>
<body>
<div class="gov-topbar">
    <div class="gov-brand">
        <div class="gov-brand-crest">ΕΔ</div>
        <div class="gov-brand-text"><strong>gov.cabnet.app</strong><span>Bolt → EDXEIX operational console</span></div>
    </div>
    <div class="gov-top-links">
        <a href="/ops/home.php">Αρχική</a>
        <a href="/ops/edxeix-form-contract.php">Form Contract</a>
        <a href="/ops/test-session.php">Test Session</a>
        <a href="/ops/preflight-review.php">Preflight</a>
        <a class="gov-logout" href="/ops/route-index.php">Route Index</a>
    </div>
</div>

<div class="gov-shell">
    <aside class="gov-sidebar">
        <h3>Final Submit Gate</h3>
        <p>Read-only go/no-go gate before any future live-submit handler</p>
        <div class="gov-side-group">
            <div class="gov-side-group-title">EDXEIX preparation</div>
            <a class="gov-side-link active" href="/ops/edxeix-final-submit-gate.php">Final Submit Gate</a>
            <a class="gov-side-link" href="/ops/edxeix-form-contract.php">Form Contract</a>
            <a class="gov-side-link" href="/ops/extension-session-write-verification.php">Extension Write Verify</a>
            <a class="gov-side-link" href="/ops/edxeix-target-matrix.php">Target Matrix</a>
            <a class="gov-side-link" href="/ops/edxeix-submit-readiness.php">Submit Readiness</a>
            <div class="gov-side-group-title">Safe operations</div>
            <a class="gov-side-link" href="/ops/test-session.php">Test Session</a>
            <a class="gov-side-link" href="/ops/preflight-review.php">Preflight Review</a>
            <a class="gov-side-link" href="/ops/route-index.php">Route Index</a>
        </div>
        <div class="gov-side-note">This page cannot submit. No POST, no EDXEIX call, no jobs.</div>
    </aside>

    <div class="gov-content">
        <div class="gov-page-header">
            <div>
                <h1 class="gov-page-title">Τελική πύλη ελέγχου υποβολής EDXEIX</h1>
                <div class="gov-breadcrumbs">Αρχική / Διαχειριστικό / Τελική πύλη ελέγχου υποβολής</div>
            </div>
            <div class="gov-tabs">
                <a class="gov-tab active" href="/ops/edxeix-final-submit-gate.php">Καρτέλα</a>
                <a class="gov-tab" href="/ops/edxeix-final-submit-gate.php?format=json">JSON</a>
                <a class="gov-tab" href="/ops/edxeix-form-contract.php?format=json">Contract JSON</a>
                <a class="gov-tab" href="/ops/test-session.php">Test Session</a>
            </div>
        </div>

        <main class="wrap wrap-shell">
            <section class="safety">
                <strong>FINAL GATE ONLY — NO SUBMIT.</strong>
                This page does not call Bolt, does not call EDXEIX, does not POST, does not stage jobs, and does not write data. It only decides whether a future submit handler would be allowed to proceed after explicit approval.
            </section>

            <section class="card hero <?= fsg_h($decisionType) ?>">
                <h1>EDXEIX Final Submission Gate</h1>
                <p><?= fsg_h($decisionText) ?></p>
                <div>
                    <?= fsg_badge($decision, $decisionType) ?>
                    <?= fsg_badge('NO POST HERE', 'good') ?>
                    <?= fsg_badge('LIVE SUBMIT OFF', 'good') ?>
                    <?= $readyForExplicitApproval ? fsg_badge('EXPLICIT APPROVAL REQUIRED', 'warn') : fsg_badge('NOT LIVE READY', 'good') ?>
                </div>
                <div class="grid" style="margin-top:14px">
                    <?= fsg_metric($gateMechanicsReady ? 'yes' : 'no', 'Mechanics ready') ?>
                    <?= fsg_metric($eligibleCount, 'Eligible candidates') ?>
                    <?= fsg_metric($contractReadyRows . '/' . $rowsChecked, 'Contract-ready rows') ?>
                    <?= fsg_metric(fsg_age_label($state['session']['age_seconds'] ?? null), 'Session age') ?>
                </div>
                <div class="actions">
                    <a class="btn" href="/ops/edxeix-final-submit-gate.php?format=json">Open JSON</a>
                    <a class="btn warn" href="/ops/test-session.php">Open Test Session</a>
                    <a class="btn dark" href="/ops/preflight-review.php">Preflight Review</a>
                    <a class="btn good" href="/ops/edxeix-form-contract.php">Form Contract</a>
                </div>
            </section>

            <section class="two">
                <div class="card">
                    <h2>Gate prerequisites</h2>
                    <div class="kv">
                        <div class="k">Config loaded</div><div><?= fsg_yes($state['config_loaded']) ?></div>
                        <div class="k">Database read OK</div><div><?= fsg_yes($state['db_loaded']) ?></div>
                        <div class="k">Payload builder</div><div><?= fsg_yes(function_exists('gov_build_edxeix_preview_payload')) ?></div>
                        <div class="k">Session JSON valid</div><div><?= fsg_yes(!empty($state['session']['json_valid'])) ?></div>
                        <div class="k">Session cookie-like data</div><div><?= fsg_yes(!empty($state['session']['cookie_like_present'])) ?></div>
                        <div class="k">Session CSRF/token</div><div><?= fsg_yes(!empty($state['session']['csrf_token_present'])) ?></div>
                        <div class="k">Session fresh</div><div><?= fsg_yes(!empty($state['session']['fresh_enough'])) ?></div>
                        <div class="k">Lease create URL confirmed</div><div><?= fsg_yes(!empty($state['session']['lease_create_url_confirmed'])) ?></div>
                        <div class="k">Submit URL confirmed</div><div><?= fsg_yes(!empty($state['session']['submit_url_confirmed'])) ?></div>
                    </div>
                </div>

                <div class="card">
                    <h2>Global blockers</h2>
                    <?php if (empty($globalBlockers)): ?>
                        <p><?= fsg_badge('NO GLOBAL MECHANIC BLOCKERS', 'good') ?></p>
                        <p>Submission mechanics are prepared. Live submit is still blocked unless an eligible future-safe Bolt candidate exists and explicit approval is given.</p>
                    <?php else: ?>
                        <ul class="timeline">
                            <?php foreach ($globalBlockers as $blocker): ?>
                                <li><code><?= fsg_h($blocker) ?></code></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </section>

            <section class="card">
                <h2>Submit envelope design</h2>
                <p>This is the intended form envelope only. This page does not send it.</p>
                <div class="kv">
                    <div class="k">Method</div><div><code>POST</code></div>
                    <div class="k">GET form page</div><div><code>/dashboard/lease-agreement/create</code></div>
                    <div class="k">Submit action URL</div><div><code>/dashboard/lease-agreement</code></div>
                    <div class="k">CSRF source</div><div><code>saved_session_csrf_token</code></div>
                    <div class="k">Current session modified</div><div><strong><?= fsg_h((string)($state['session']['modified_at'] ?? '')) ?></strong></div>
                    <?php foreach (($state['session']['safe_metadata'] ?? []) as $key => $value): ?>
                        <div class="k"><?= fsg_h($key) ?></div><div><?= fsg_h($value) ?></div>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="card">
                <h2>Recent candidate gate rows</h2>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Status</th>
                                <th>Started</th>
                                <th>Driver</th>
                                <th>Plate</th>
                                <th>Payload</th>
                                <th>Contract</th>
                                <th>Mapping</th>
                                <th>Future</th>
                                <th>Terminal</th>
                                <th>Live candidate</th>
                                <th>Blockers</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($state['rows'] as $row): ?>
                            <tr>
                                <td><?= fsg_h((string)($row['id'] ?? '')) ?></td>
                                <td><?= fsg_h((string)$row['status']) ?></td>
                                <td><?= fsg_h((string)$row['started_at']) ?></td>
                                <td><?= fsg_h((string)$row['driver_name']) ?></td>
                                <td><?= fsg_h((string)$row['plate']) ?></td>
                                <td><?= !empty($row['payload_built']) ? fsg_badge('YES','good') : fsg_badge('NO','bad') ?></td>
                                <td><?= !empty($row['contract_ready']) ? fsg_badge('YES','good') : fsg_badge('NO','bad') ?></td>
                                <td><?= !empty($row['mapping_ready']) ? fsg_badge('YES','good') : fsg_badge('NO','bad') ?></td>
                                <td><?= !empty($row['future_guard_passed']) ? fsg_badge('YES','good') : fsg_badge('NO','warn') ?></td>
                                <td><?= !empty($row['terminal_status']) ? fsg_badge('YES','warn') : fsg_badge('NO','good') ?></td>
                                <td><?= !empty($row['live_candidate']) ? fsg_badge('YES','warn') : fsg_badge('NO','good') ?></td>
                                <td><?= fsg_h(implode(', ', (array)$row['blockers'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="card">
                <h2>Hard stop rules</h2>
                <ul class="timeline">
                    <li>This page cannot submit and contains no POST operation.</li>
                    <li>Do not submit completed, cancelled, terminal, expired, historical, lab, test, or not-future-safe rows.</li>
                    <li>Refresh the EDXEIX session shortly before any approved live test.</li>
                    <li>A future live-submit handler must require explicit approval and a single eligible candidate.</li>
                    <li>No live EDXEIX submission should be enabled from this patch.</li>
                </ul>
            </section>
        </main>
    </div>
</div>
</body>
</html>
