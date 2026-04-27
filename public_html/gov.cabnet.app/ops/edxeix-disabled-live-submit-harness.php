<?php
/**
 * gov.cabnet.app — Disabled Live Submit Harness / Approval Runbook v3.4
 *
 * Purpose:
 * - Preview the exact future live-submit sequence without implementing or enabling live submission.
 * - Provide a hard-disabled harness showing what gates must pass before a future POST handler may exist.
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
 * - Live EDXEIX submission remains disabled by design.
 */

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Robots-Tag: noindex,nofollow', true);

require_once '/home/cabnet/gov.cabnet.app_app/lib/bolt_sync_lib.php';

function dlh_h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function dlh_badge(string $text, string $type = 'neutral'): string { return '<span class="badge badge-' . dlh_h($type) . '">' . dlh_h($text) . '</span>'; }
function dlh_yes(bool $v, string $yes = 'YES', string $no = 'NO'): string { return $v ? dlh_badge($yes, 'good') : dlh_badge($no, 'bad'); }
function dlh_metric($value, string $label): string { return '<div class="metric"><strong>' . dlh_h((string)$value) . '</strong><span>' . dlh_h($label) . '</span></div>'; }

function dlh_value(array $row, array $keys, $default = '') {
    foreach ($keys as $key) {
        if (array_key_exists($key, $row) && $row[$key] !== null && $row[$key] !== '') return $row[$key];
    }
    return $default;
}

function dlh_boolish($value): bool {
    if (is_bool($value)) return $value;
    return in_array(strtolower(trim((string)$value)), ['1','true','yes','on'], true);
}

function dlh_terminal(string $status): bool {
    $s = strtolower(trim($status));
    if ($s === '') return false;
    $terminal = ['finished','completed','client_cancelled','driver_cancelled','driver_cancelled_after_accept','cancelled','canceled','expired','rejected','failed'];
    return in_array($s, $terminal, true) || strpos($s, 'cancel') !== false || strpos($s, 'finished') !== false || strpos($s, 'complete') !== false;
}

function dlh_safe_url_display($url): string {
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

function dlh_has_key_like($value, array $needles): bool {
    if (!is_array($value)) return false;
    foreach ($value as $key => $item) {
        foreach ($needles as $needle) {
            if (stripos((string)$key, $needle) !== false && $item !== null && $item !== '') return true;
        }
        if (is_array($item) && dlh_has_key_like($item, $needles)) return true;
    }
    return false;
}

function dlh_find_session_path(array $config): string {
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

function dlh_session_metadata(array $config, int $freshMinutes): array {
    $path = dlh_find_session_path($config);
    $out = [
        'basename' => basename($path),
        'path_hint' => dirname($path),
        'exists' => is_file($path),
        'readable' => is_readable($path),
        'modified_at' => is_file($path) ? date('Y-m-d H:i:s', (int)@filemtime($path)) : '',
        'age_seconds' => is_file($path) ? max(0, time() - (int)@filemtime($path)) : null,
        'fresh_enough' => false,
        'json_valid' => false,
        'csrf_token_present' => false,
        'cookie_like_present' => false,
        'lease_create_url_confirmed' => false,
        'submit_url_confirmed' => false,
        'safe_metadata' => [],
    ];

    if (!$out['exists'] || !$out['readable']) return $out;
    $out['fresh_enough'] = $out['age_seconds'] !== null && $out['age_seconds'] <= ($freshMinutes * 60);

    $raw = @file_get_contents($path);
    $json = is_string($raw) ? json_decode($raw, true) : null;
    if (!is_array($json)) return $out;

    $out['json_valid'] = true;
    $out['csrf_token_present'] = dlh_has_key_like($json, ['csrf_token', 'csrf', 'xsrf', '_token']);
    $out['cookie_like_present'] = dlh_has_key_like($json, ['cookie', 'session']);

    foreach (['saved_at','updated_at','source','source_url','detected_form_action','fixed_submit_url_used','extension_version','note'] as $key) {
        if (!array_key_exists($key, $json)) continue;
        if (in_array($key, ['source_url','detected_form_action','fixed_submit_url_used'], true)) {
            $out['safe_metadata'][$key] = dlh_safe_url_display((string)$json[$key]);
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

function dlh_flatten_keys($value, string $prefix = ''): array {
    $keys = [];
    if (!is_array($value)) return $keys;
    foreach ($value as $key => $item) {
        $key = (string)$key;
        if ($key === '_mapping_status') continue;
        $flat = $prefix === '' ? $key : $prefix . '[' . $key . ']';
        $keys[$flat] = true;
        if (is_array($item)) {
            foreach (dlh_flatten_keys($item, $flat) as $nested => $true) $keys[$nested] = true;
        }
    }
    return $keys;
}

function dlh_required_contract(): array {
    return ['_token','broker','lessor','lessee[type]','lessee[name]','driver','vehicle','starting_point_id','boarding_point','disembark_point','drafted_at','started_at','ended_at','price'];
}

function dlh_analyze_booking(mysqli $db, array $booking, array $session): array {
    $preview = function_exists('gov_build_edxeix_preview_payload') ? gov_build_edxeix_preview_payload($db, $booking) : [];
    $flat = dlh_flatten_keys($preview);

    $missing = [];
    foreach (dlh_required_contract() as $field) {
        $present = $field === '_token' ? !empty($session['csrf_token_present']) : isset($flat[$field]);
        if (!$present) $missing[] = $field;
    }

    $mapping = is_array($preview['_mapping_status'] ?? null) ? $preview['_mapping_status'] : [];
    $status = (string)dlh_value($booking, ['order_status','status'], '');
    $startedAt = (string)dlh_value($booking, ['started_at'], '');
    $orderRef = (string)dlh_value($booking, ['order_reference','external_order_id','external_reference','source_trip_reference','source_trip_id'], '');
    $source = strtolower((string)dlh_value($booking, ['source_system','source_type','source'], ''));
    $refUpper = strtoupper($orderRef);
    $lab = strpos($source, 'lab') !== false || strpos($refUpper, 'LAB-') === 0;
    $test = dlh_boolish($booking['is_test_booking'] ?? false);
    $never = dlh_boolish($booking['never_submit_live'] ?? false) || $test;

    $driverMapped = !empty($mapping['driver_mapped']);
    $vehicleMapped = !empty($mapping['vehicle_mapped']);
    $futureGuard = !empty($mapping['passes_future_guard']);
    $terminal = dlh_terminal($status);

    $blockers = [];
    if (!$driverMapped) $blockers[] = 'driver_not_mapped';
    if (!$vehicleMapped) $blockers[] = 'vehicle_not_mapped';
    if ($startedAt === '') $blockers[] = 'missing_started_at';
    elseif (!$futureGuard) $blockers[] = 'started_at_not_30_min_future';
    if ($terminal) $blockers[] = 'terminal_order_status';
    if ($lab) $blockers[] = 'lab_row_blocked';
    if ($never) $blockers[] = 'never_submit_live';
    if (!empty($missing)) $blockers[] = 'form_contract_missing_required_fields';

    return [
        'id' => $booking['id'] ?? null,
        'order_reference_hint' => $orderRef !== '' ? substr($orderRef, 0, 16) . '…' : '',
        'status' => $status,
        'started_at' => $startedAt,
        'driver_name' => dlh_value($booking, ['driver_name','external_driver_name'], ''),
        'plate' => dlh_value($booking, ['vehicle_plate','plate'], ''),
        'payload_built' => !empty($preview),
        'contract_ready' => !empty($preview) && empty($missing),
        'mapping_ready' => $driverMapped && $vehicleMapped,
        'future_guard_passed' => $futureGuard,
        'terminal_status' => $terminal,
        'live_candidate' => empty($blockers),
        'blockers' => $blockers,
    ];
}

function dlh_age_label($seconds): string {
    if ($seconds === null) return 'n/a';
    $seconds = (int)$seconds;
    if ($seconds < 60) return $seconds . ' sec';
    if ($seconds < 3600) return floor($seconds / 60) . ' min';
    if ($seconds < 86400) return floor($seconds / 3600) . ' hr';
    return floor($seconds / 86400) . ' days';
}

function dlh_json_response(array $payload): void {
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
    $state['session'] = dlh_session_metadata($config, $freshMinutes);
    $db = gov_bridge_db();
    $state['db_loaded'] = true;
    foreach (gov_recent_rows($db, 'normalized_bookings', $limit) as $booking) {
        $state['rows'][] = dlh_analyze_booking($db, $booking, $state['session']);
    }
} catch (Throwable $e) {
    $state['error'] = $e->getMessage();
}

$rowsChecked = count($state['rows']);
$payloadsBuilt = count(array_filter($state['rows'], static fn($r) => !empty($r['payload_built'])));
$contractReadyRows = count(array_filter($state['rows'], static fn($r) => !empty($r['contract_ready'])));
$eligibleRows = array_values(array_filter($state['rows'], static fn($r) => !empty($r['live_candidate'])));
$eligibleCount = count($eligibleRows);

$mechanicsReady =
    $state['config_loaded'] &&
    $state['db_loaded'] &&
    function_exists('gov_build_edxeix_preview_payload') &&
    !empty($state['session']['json_valid']) &&
    !empty($state['session']['cookie_like_present']) &&
    !empty($state['session']['csrf_token_present']) &&
    !empty($state['session']['fresh_enough']) &&
    !empty($state['session']['lease_create_url_confirmed']) &&
    !empty($state['session']['submit_url_confirmed']) &&
    $payloadsBuilt > 0 &&
    $contractReadyRows > 0;

$approvalRequired = 'ANDREAS_EXPLICIT_LIVE_SUBMIT_APPROVAL_REQUIRED';
$livePostImplemented = false;
$disabledByDesign = true;

$decision = 'LIVE_SUBMIT_HARNESS_DISABLED_PREPARED_NO_CANDIDATE';
$decisionType = 'good';
$decisionText = 'The future live-submit sequence is documented and disabled. Mechanics are prepared, but no eligible future-safe candidate exists.';
if (!$mechanicsReady) {
    $decision = 'LIVE_SUBMIT_HARNESS_DISABLED_MECHANICS_NOT_READY';
    $decisionType = 'warn';
    $decisionText = 'The future live-submit sequence is documented and disabled, but one or more mechanics are not ready.';
} elseif ($eligibleCount > 0) {
    $decision = 'LIVE_SUBMIT_HARNESS_DISABLED_CANDIDATE_PRESENT';
    $decisionType = 'warn';
    $decisionText = 'A candidate appears eligible, but this harness is disabled and cannot submit. Explicit approval and a separate live-submit patch would be required.';
}

$futureSequence = [
    '1_refresh_edxeix_session' => 'Refresh EDXEIX session shortly before test and verify freshness.',
    '2_fetch_form_get' => 'GET /dashboard/lease-agreement/create to confirm token/form access.',
    '3_select_single_candidate' => 'Select exactly one real future-safe normalized Bolt booking.',
    '4_rebuild_payload' => 'Rebuild payload from current database row, not stale cached data.',
    '5_validate_contract' => 'Confirm required EDXEIX fields are present.',
    '6_final_hard_stops' => 'Reject terminal, cancelled, historical, lab, test, expired, or not-future-safe rows.',
    '7_operator_approval' => 'Require Andreas explicit live-submit approval and confirmation phrase.',
    '8_post_once' => 'POST once to confirmed EDXEIX action URL.',
    '9_record_attempt' => 'Record sanitized attempt metadata only.',
    '10_stop' => 'Stop after one attempt and inspect EDXEIX manually.',
];

$payload = [
    'ok' => $state['error'] === null,
    'script' => 'ops/edxeix-disabled-live-submit-harness.php',
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
        'live_post_implemented' => $livePostImplemented,
        'disabled_by_design' => $disabledByDesign,
        'purpose' => 'document future live-submit runbook without enabling submission',
    ],
    'decision' => [
        'code' => $decision,
        'type' => $decisionType,
        'text' => $decisionText,
    ],
    'checks' => [
        'mechanics_ready' => $mechanicsReady,
        'config_loaded' => $state['config_loaded'],
        'database_read_ok' => $state['db_loaded'],
        'payload_builder_available' => function_exists('gov_build_edxeix_preview_payload'),
        'session_fresh_enough' => !empty($state['session']['fresh_enough']),
        'session_csrf_token_present' => !empty($state['session']['csrf_token_present']),
        'lease_create_url_confirmed' => !empty($state['session']['lease_create_url_confirmed']),
        'submit_url_confirmed' => !empty($state['session']['submit_url_confirmed']),
        'payloads_built' => $payloadsBuilt,
        'contract_ready_rows' => $contractReadyRows,
        'eligible_live_candidates' => $eligibleCount,
        'live_post_implemented' => $livePostImplemented,
        'ready_for_live_patch_after_explicit_approval' => $mechanicsReady && $eligibleCount > 0,
    ],
    'session_summary' => [
        'basename' => $state['session']['basename'] ?? '',
        'path_hint' => $state['session']['path_hint'] ?? '',
        'modified_at' => $state['session']['modified_at'] ?? '',
        'age_seconds' => $state['session']['age_seconds'] ?? null,
        'age_label' => dlh_age_label($state['session']['age_seconds'] ?? null),
        'safe_metadata' => $state['session']['safe_metadata'] ?? [],
    ],
    'approval_model' => [
        'approval_required' => $approvalRequired,
        'future_confirmation_phrase' => 'I APPROVE ONE LIVE EDXEIX SUBMISSION FOR BOOKING ID {id}',
        'future_submit_method' => 'POST only, never GET',
        'future_submit_scope' => 'one candidate, one attempt, then stop',
        'current_page_can_submit' => false,
    ],
    'future_submit_sequence' => $futureSequence,
    'hard_stop_rules' => [
        'No live POST from this patch.',
        'No completed/historical rows.',
        'No cancelled/terminal rows.',
        'No lab/test/never_submit_live rows.',
        'No candidate failing future guard.',
        'No bulk submit.',
        'No retry loop.',
        'No raw secrets in output.',
    ],
    'candidate_summary' => [
        'rows_checked' => $rowsChecked,
        'eligible_live_candidates' => $eligibleCount,
        'blocked_rows' => $rowsChecked - $eligibleCount,
        'rows' => $state['rows'],
    ],
    'error' => $state['error'],
    'links' => [
        'html' => '/ops/edxeix-disabled-live-submit-harness.php',
        'json' => '/ops/edxeix-disabled-live-submit-harness.php?format=json',
        'final_submit_gate_json' => '/ops/edxeix-final-submit-gate.php?format=json',
        'form_contract_json' => '/ops/edxeix-form-contract.php?format=json',
        'preflight_review' => '/ops/preflight-review.php',
        'test_session' => '/ops/test-session.php',
        'route_index' => '/ops/route-index.php',
    ],
];

if ($format === 'json') dlh_json_response($payload);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Disabled Live Submit Harness | gov.cabnet.app</title>
    <link rel="stylesheet" href="/assets/css/gov-ops-edxeix.css?v=3.4">
</head>
<body>
<div class="gov-topbar">
    <div class="gov-brand">
        <div class="gov-brand-crest">ΕΔ</div>
        <div class="gov-brand-text"><strong>gov.cabnet.app</strong><span>Bolt → EDXEIX operational console</span></div>
    </div>
    <div class="gov-top-links">
        <a href="/ops/home.php">Αρχική</a>
        <a href="/ops/edxeix-final-submit-gate.php">Final Gate</a>
        <a href="/ops/test-session.php">Test Session</a>
        <a href="/ops/preflight-review.php">Preflight</a>
        <a class="gov-logout" href="/ops/route-index.php">Route Index</a>
    </div>
</div>

<div class="gov-shell">
    <aside class="gov-sidebar">
        <h3>Disabled Harness</h3>
        <p>Future live-submit runbook, disabled by design</p>
        <div class="gov-side-group">
            <div class="gov-side-group-title">EDXEIX preparation</div>
            <a class="gov-side-link active" href="/ops/edxeix-disabled-live-submit-harness.php">Disabled Submit Harness</a>
            <a class="gov-side-link" href="/ops/edxeix-final-submit-gate.php">Final Submit Gate</a>
            <a class="gov-side-link" href="/ops/edxeix-form-contract.php">Form Contract</a>
            <a class="gov-side-link" href="/ops/extension-session-write-verification.php">Extension Write Verify</a>
            <a class="gov-side-link" href="/ops/edxeix-submit-readiness.php">Submit Readiness</a>
            <div class="gov-side-group-title">Safe operations</div>
            <a class="gov-side-link" href="/ops/test-session.php">Test Session</a>
            <a class="gov-side-link" href="/ops/preflight-review.php">Preflight Review</a>
            <a class="gov-side-link" href="/ops/route-index.php">Route Index</a>
        </div>
        <div class="gov-side-note">Hard-disabled. No POST code path. No live submission.</div>
    </aside>

    <div class="gov-content">
        <div class="gov-page-header">
            <div>
                <h1 class="gov-page-title">Απενεργοποιημένος οδηγός τελικής υποβολής</h1>
                <div class="gov-breadcrumbs">Αρχική / Διαχειριστικό / Απενεργοποιημένος οδηγός υποβολής</div>
            </div>
            <div class="gov-tabs">
                <a class="gov-tab active" href="/ops/edxeix-disabled-live-submit-harness.php">Καρτέλα</a>
                <a class="gov-tab" href="/ops/edxeix-disabled-live-submit-harness.php?format=json">JSON</a>
                <a class="gov-tab" href="/ops/edxeix-final-submit-gate.php?format=json">Final Gate JSON</a>
                <a class="gov-tab" href="/ops/test-session.php">Test Session</a>
            </div>
        </div>

        <main class="wrap wrap-shell">
            <section class="safety">
                <strong>DISABLED HARNESS ONLY.</strong>
                This page documents the future live-submit sequence but contains no EDXEIX POST path. It does not call Bolt, does not call EDXEIX, does not stage jobs, and does not write data.
            </section>

            <section class="card hero <?= dlh_h($decisionType) ?>">
                <h1>Disabled Live Submit Harness / Approval Runbook</h1>
                <p><?= dlh_h($decisionText) ?></p>
                <div>
                    <?= dlh_badge($decision, $decisionType) ?>
                    <?= dlh_badge('NO POST CODE PATH', 'good') ?>
                    <?= dlh_badge('LIVE SUBMIT OFF', 'good') ?>
                    <?= dlh_badge('EXPLICIT APPROVAL REQUIRED LATER', 'warn') ?>
                </div>
                <div class="grid" style="margin-top:14px">
                    <?= dlh_metric($mechanicsReady ? 'yes' : 'no', 'Mechanics ready') ?>
                    <?= dlh_metric($eligibleCount, 'Eligible candidates') ?>
                    <?= dlh_metric('no', 'POST implemented') ?>
                    <?= dlh_metric(dlh_age_label($state['session']['age_seconds'] ?? null), 'Session age') ?>
                </div>
                <div class="actions">
                    <a class="btn" href="/ops/edxeix-disabled-live-submit-harness.php?format=json">Open JSON</a>
                    <a class="btn warn" href="/ops/edxeix-final-submit-gate.php?format=json">Final Gate JSON</a>
                    <a class="btn dark" href="/ops/test-session.php">Test Session</a>
                    <a class="btn good" href="/ops/preflight-review.php">Preflight Review</a>
                </div>
            </section>

            <section class="two">
                <div class="card">
                    <h2>Harness status</h2>
                    <div class="kv">
                        <div class="k">Disabled by design</div><div><?= dlh_yes(true) ?></div>
                        <div class="k">Live POST implemented</div><div><?= dlh_yes(false) ?></div>
                        <div class="k">Mechanics ready</div><div><?= dlh_yes($mechanicsReady) ?></div>
                        <div class="k">Eligible candidates</div><div><strong><?= dlh_h((string)$eligibleCount) ?></strong></div>
                        <div class="k">Session fresh</div><div><?= dlh_yes(!empty($state['session']['fresh_enough'])) ?></div>
                        <div class="k">Lease create URL</div><div><?= dlh_yes(!empty($state['session']['lease_create_url_confirmed'])) ?></div>
                        <div class="k">Submit URL</div><div><?= dlh_yes(!empty($state['session']['submit_url_confirmed'])) ?></div>
                    </div>
                </div>
                <div class="card">
                    <h2>Approval model</h2>
                    <div class="kv">
                        <div class="k">Approval required</div><div><code><?= dlh_h($approvalRequired) ?></code></div>
                        <div class="k">Future phrase</div><div><code>I APPROVE ONE LIVE EDXEIX SUBMISSION FOR BOOKING ID {id}</code></div>
                        <div class="k">Future method</div><div><code>POST only, never GET</code></div>
                        <div class="k">Scope</div><div><code>one candidate, one attempt, then stop</code></div>
                    </div>
                </div>
            </section>

            <section class="card">
                <h2>Future live-submit sequence</h2>
                <ol class="timeline">
                    <?php foreach ($futureSequence as $key => $text): ?>
                        <li><strong><?= dlh_h(str_replace('_', ' ', $key)) ?>:</strong> <?= dlh_h($text) ?></li>
                    <?php endforeach; ?>
                </ol>
            </section>

            <section class="card">
                <h2>Current candidate summary</h2>
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
                                <td><?= dlh_h((string)($row['id'] ?? '')) ?></td>
                                <td><?= dlh_h((string)$row['status']) ?></td>
                                <td><?= dlh_h((string)$row['started_at']) ?></td>
                                <td><?= dlh_h((string)$row['driver_name']) ?></td>
                                <td><?= dlh_h((string)$row['plate']) ?></td>
                                <td><?= !empty($row['payload_built']) ? dlh_badge('YES','good') : dlh_badge('NO','bad') ?></td>
                                <td><?= !empty($row['contract_ready']) ? dlh_badge('YES','good') : dlh_badge('NO','bad') ?></td>
                                <td><?= !empty($row['mapping_ready']) ? dlh_badge('YES','good') : dlh_badge('NO','bad') ?></td>
                                <td><?= !empty($row['future_guard_passed']) ? dlh_badge('YES','good') : dlh_badge('NO','warn') ?></td>
                                <td><?= !empty($row['terminal_status']) ? dlh_badge('YES','warn') : dlh_badge('NO','good') ?></td>
                                <td><?= !empty($row['live_candidate']) ? dlh_badge('YES','warn') : dlh_badge('NO','good') ?></td>
                                <td><?= dlh_h(implode(', ', (array)$row['blockers'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="card">
                <h2>Hard stop rules</h2>
                <ul class="timeline">
                    <li>No live POST from this patch.</li>
                    <li>No completed, historical, cancelled, terminal, expired, lab, test, or never-submit rows.</li>
                    <li>No candidate failing the future guard.</li>
                    <li>No bulk submit and no retry loop.</li>
                    <li>No raw cookies, CSRF tokens, session JSON, or passenger payload values in output.</li>
                    <li>Future live submit requires a separate patch and explicit instruction.</li>
                </ul>
            </section>
        </main>
    </div>
</div>
</body>
</html>
