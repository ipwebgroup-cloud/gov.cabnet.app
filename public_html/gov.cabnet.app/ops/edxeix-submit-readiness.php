<?php
/**
 * gov.cabnet.app — EDXEIX Submit Readiness Probe v2.6
 *
 * Purpose:
 * - Verify that the local app can prepare for an EDXEIX submission without submitting.
 * - Inspect config/session presence, payload builder availability, and preflight eligibility.
 *
 * Safety contract:
 * - Does not call Bolt.
 * - Does not POST to EDXEIX.
 * - Does not stage jobs.
 * - Does not update mappings.
 * - Does not write database rows or files.
 * - Reads local configuration metadata, local session-file metadata, and recent normalized bookings only.
 */

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Robots-Tag: noindex,nofollow', true);

require_once '/home/cabnet/gov.cabnet.app_app/lib/bolt_sync_lib.php';

function esr_h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function esr_badge(string $text, string $type = 'neutral'): string { return '<span class="badge badge-' . esr_h($type) . '">' . esr_h($text) . '</span>'; }
function esr_yes(bool $v, string $yes = 'YES', string $no = 'NO'): string { return $v ? esr_badge($yes, 'good') : esr_badge($no, 'bad'); }
function esr_warn(bool $v, string $yes = 'YES', string $no = 'NO'): string { return $v ? esr_badge($yes, 'warn') : esr_badge($no, 'good'); }
function esr_metric($value, string $label): string { return '<div class="metric"><strong>' . esr_h((string)$value) . '</strong><span>' . esr_h($label) . '</span></div>'; }
function esr_value(array $row, array $keys, $default = '') { foreach ($keys as $k) { if (array_key_exists($k, $row) && $row[$k] !== null && $row[$k] !== '') return $row[$k]; } return $default; }
function esr_boolish($value): bool { if (is_bool($value)) return $value; return in_array(strtolower(trim((string)$value)), ['1','true','yes','on'], true); }
function esr_terminal(string $status): bool {
    $s = strtolower(trim($status));
    if ($s === '') return false;
    $terminal = ['finished','completed','client_cancelled','driver_cancelled','driver_cancelled_after_accept','cancelled','canceled','expired','rejected','failed'];
    return in_array($s, $terminal, true) || strpos($s, 'cancel') !== false || strpos($s, 'finished') !== false || strpos($s, 'complete') !== false;
}
function esr_redact_payload(array $payload): array {
    foreach (['_token', 'csrf_token', 'token', 'authenticity_token'] as $k) {
        if (array_key_exists($k, $payload)) $payload[$k] = '[redacted / loaded only at submit time]';
    }
    return $payload;
}
function esr_config_presence(array $config): array {
    $edx = is_array($config['edxeix'] ?? null) ? $config['edxeix'] : [];
    $keys = ['lessor_id','default_starting_point_id','future_start_guard_minutes','base_url','login_url','dashboard_url','form_url','submit_url','session_cookie_file','cookie_file'];
    $out = [];
    foreach ($keys as $key) {
        $exists = array_key_exists($key, $edx) && $edx[$key] !== null && $edx[$key] !== '';
        $display = '';
        if ($exists && in_array($key, ['lessor_id','default_starting_point_id','future_start_guard_minutes'], true)) {
            $display = (string)$edx[$key];
        } elseif ($exists) {
            $display = '[configured]';
        }
        $out[$key] = ['present' => $exists, 'display' => $display];
    }
    return $out;
}
function esr_session_candidates(array $config): array {
    $paths = function_exists('gov_bridge_paths') ? gov_bridge_paths() : ['runtime' => '/home/cabnet/gov.cabnet.app_app/storage/runtime', 'storage' => '/home/cabnet/gov.cabnet.app_app/storage'];
    $dirs = [];
    foreach (['runtime','storage','artifacts'] as $k) {
        if (!empty($paths[$k]) && is_dir((string)$paths[$k])) $dirs[] = (string)$paths[$k];
    }
    $edx = is_array($config['edxeix'] ?? null) ? $config['edxeix'] : [];
    foreach (['session_cookie_file','cookie_file','session_file'] as $key) {
        if (!empty($edx[$key]) && is_string($edx[$key])) {
            $dirs[] = dirname($edx[$key]);
        }
    }
    $dirs = array_values(array_unique(array_filter($dirs, 'is_dir')));
    $files = [];
    foreach ($dirs as $dir) {
        foreach (['*edxeix*','*EDXEIX*','*cookie*','*session*'] as $pattern) {
            foreach (glob(rtrim($dir, '/') . '/' . $pattern) ?: [] as $file) {
                if (!is_file($file)) continue;
                $real = realpath($file) ?: $file;
                $files[$real] = [
                    'basename' => basename($file),
                    'path_hint' => dirname($file),
                    'readable' => is_readable($file),
                    'size_bytes' => (int)@filesize($file),
                    'modified_at' => date('Y-m-d H:i:s', (int)@filemtime($file)),
                ];
            }
        }
    }
    return array_values($files);
}
function esr_analyze_booking(mysqli $db, array $booking, array $config): array {
    $preview = function_exists('gov_build_edxeix_preview_payload') ? gov_build_edxeix_preview_payload($db, $booking) : [];
    $mapping = is_array($preview['_mapping_status'] ?? null) ? $preview['_mapping_status'] : [];
    $status = (string)esr_value($booking, ['order_status','status'], '');
    $startedAt = (string)esr_value($booking, ['started_at'], '');
    $orderRef = (string)esr_value($booking, ['order_reference','external_order_id','external_reference','source_trip_reference','source_trip_id'], '');
    $source = strtolower((string)esr_value($booking, ['source_system','source_type','source'], ''));
    $refUpper = strtoupper($orderRef);
    $lab = strpos($source, 'lab') !== false || strpos($refUpper, 'LAB-') === 0;
    $test = esr_boolish($booking['is_test_booking'] ?? false);
    $never = esr_boolish($booking['never_submit_live'] ?? false) || $test;
    $driverMapped = !empty($mapping['driver_mapped']);
    $vehicleMapped = !empty($mapping['vehicle_mapped']);
    $futureGuard = !empty($mapping['passes_future_guard']);
    $terminal = esr_terminal($status);
    $blockers = [];
    if (!$driverMapped) $blockers[] = 'driver_not_mapped';
    if (!$vehicleMapped) $blockers[] = 'vehicle_not_mapped';
    if ($startedAt === '') $blockers[] = 'missing_started_at';
    elseif (!$futureGuard) $blockers[] = 'started_at_not_30_min_future';
    if ($terminal) $blockers[] = 'terminal_order_status';
    if ($lab) $blockers[] = 'lab_row_blocked';
    if ($never) $blockers[] = 'never_submit_live';
    return [
        'id' => $booking['id'] ?? null,
        'order_reference' => $orderRef,
        'status' => $status,
        'started_at' => $startedAt,
        'driver_name' => esr_value($booking, ['driver_name','external_driver_name'], ''),
        'plate' => esr_value($booking, ['vehicle_plate','plate'], ''),
        'edxeix_driver_id' => $preview['driver'] ?? '',
        'edxeix_vehicle_id' => $preview['vehicle'] ?? '',
        'payload_built' => !empty($preview),
        'mapping_ready' => $driverMapped && $vehicleMapped,
        'future_guard_passed' => $futureGuard,
        'terminal_status' => $terminal,
        'blocked' => !empty($blockers),
        'live_submission_allowed' => empty($blockers),
        'blockers' => $blockers,
        'payload_preview' => esr_redact_payload($preview),
    ];
}

$format = strtolower(trim((string)($_GET['format'] ?? 'html')));
$limit = max(1, min(50, (int)($_GET['limit'] ?? 10)));
$state = [
    'config_loaded' => false,
    'db_loaded' => false,
    'error' => null,
    'config' => [],
    'config_presence' => [],
    'session_candidates' => [],
    'rows' => [],
];

try {
    $config = gov_bridge_load_config();
    if (!empty($config['app']['timezone'])) date_default_timezone_set((string)$config['app']['timezone']);
    $state['config_loaded'] = true;
    $state['config_presence'] = esr_config_presence($config);
    $state['session_candidates'] = esr_session_candidates($config);
    $db = gov_bridge_db();
    $state['db_loaded'] = true;
    $bookings = gov_recent_rows($db, 'normalized_bookings', $limit);
    foreach ($bookings as $booking) $state['rows'][] = esr_analyze_booking($db, $booking, $config);
} catch (Throwable $e) {
    $state['error'] = $e->getMessage();
}

$payloadBuilt = count(array_filter($state['rows'], static fn($r) => !empty($r['payload_built'])));
$eligible = count(array_filter($state['rows'], static fn($r) => !empty($r['live_submission_allowed'])));
$blocked = count($state['rows']) - $eligible;
$sessionFiles = count($state['session_candidates']);
$sessionPresent = $sessionFiles > 0;
$configOk = !empty($state['config_presence']['lessor_id']['present']) && !empty($state['config_presence']['default_starting_point_id']['present']);
$curlOk = extension_loaded('curl');
$builderOk = function_exists('gov_build_edxeix_preview_payload');
$mechanicsReady = $state['config_loaded'] && $state['db_loaded'] && $configOk && $builderOk && $payloadBuilt > 0;
$liveReadyNow = $mechanicsReady && $sessionPresent && $eligible > 0;

$decision = $liveReadyNow ? 'LIVE_CANDIDATE_READY_FOR_EXPLICIT_APPROVAL' : ($mechanicsReady ? 'SUBMIT_MECHANICS_READY_BUT_NO_ELIGIBLE_CANDIDATE' : 'SUBMIT_MECHANICS_NOT_READY');
$decisionType = $liveReadyNow ? 'warn' : ($mechanicsReady ? 'warn' : 'bad');
$decisionText = $liveReadyNow
    ? 'A live-eligible candidate appears present. Do not submit without explicit final approval.'
    : ($mechanicsReady ? 'The local app can build EDXEIX payload previews, but no eligible future-safe candidate is available for live submission.' : 'One or more local submit-preparation requirements are missing.');

$payload = [
    'ok' => $state['error'] === null,
    'script' => 'ops/edxeix-submit-readiness.php',
    'generated_at' => date('c'),
    'safety_contract' => [
        'calls_bolt' => false,
        'posts_to_edxeix' => false,
        'stages_jobs' => false,
        'updates_mappings' => false,
        'writes_database' => false,
        'live_edxeix_submission' => 'disabled_not_used',
    ],
    'decision' => [
        'code' => $decision,
        'text' => $decisionText,
        'live_ready_now' => $liveReadyNow,
        'mechanics_ready' => $mechanicsReady,
    ],
    'checks' => [
        'config_loaded' => $state['config_loaded'],
        'database_read_ok' => $state['db_loaded'],
        'curl_extension_loaded' => $curlOk,
        'payload_builder_available' => $builderOk,
        'edxeix_config_core_present' => $configOk,
        'session_file_candidates_found' => $sessionFiles,
        'payloads_built' => $payloadBuilt,
        'eligible_live_candidates' => $eligible,
        'blocked_rows' => $blocked,
    ],
    'edxeix_config_presence' => $state['config_presence'],
    'session_file_candidates' => $state['session_candidates'],
    'rows' => $state['rows'],
    'error' => $state['error'],
    'links' => [
        'html' => '/ops/edxeix-submit-readiness.php',
        'json' => '/ops/edxeix-submit-readiness.php?format=json',
        'preflight_review' => '/ops/preflight-review.php',
        'preflight_json' => '/bolt_edxeix_preflight.php?limit=30',
        'route_index' => '/ops/route-index.php',
    ],
];

if ($format === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>EDXEIX Submit Readiness | gov.cabnet.app</title>
    <link rel="stylesheet" href="/assets/css/gov-ops-edxeix.css?v=2.6">
</head>
<body>
<div class="gov-topbar">
    <div class="gov-brand"><div class="gov-brand-crest">ΕΔ</div><div class="gov-brand-text"><strong>gov.cabnet.app</strong><span>Bolt → EDXEIX operational console</span></div></div>
    <div class="gov-top-links"><a href="/ops/home.php">Αρχική</a><a href="/ops/preflight-review.php">Preflight</a><a href="/ops/route-index.php">Route Index</a><a class="gov-logout" href="/ops/index.php">Original Console</a></div>
</div>
<div class="gov-shell">
    <aside class="gov-sidebar">
        <h3>Submit Readiness</h3><p>Preparation probe without live submission</p>
        <div class="gov-side-group">
            <div class="gov-side-group-title">Preparation</div>
            <a class="gov-side-link active" href="/ops/edxeix-submit-readiness.php">Submit Readiness</a>
            <a class="gov-side-link" href="/ops/preflight-review.php">Preflight Review</a>
            <a class="gov-side-link" href="/ops/completed-visibility.php">Completed Visibility</a>
            <a class="gov-side-link" href="/ops/route-index.php">Route Index</a>
        </div>
        <div class="gov-side-note">No live submit. No EDXEIX POST. No job staging.</div>
    </aside>
    <div class="gov-content">
        <div class="gov-page-header">
            <div><h1 class="gov-page-title">Έλεγχος ετοιμότητας υποβολής EDXEIX</h1><div class="gov-breadcrumbs">Αρχική / Διαχειριστικό / Έλεγχος ετοιμότητας υποβολής EDXEIX</div></div>
            <div class="gov-tabs"><a class="gov-tab active" href="/ops/edxeix-submit-readiness.php">Καρτέλα</a><a class="gov-tab" href="/ops/edxeix-submit-readiness.php?format=json">JSON</a><a class="gov-tab" href="/ops/preflight-review.php">Preflight</a></div>
        </div>
        <main class="wrap wrap-shell">
            <section class="safety"><strong>SUBMIT PREPARATION ONLY.</strong> This page verifies local readiness and payload preparation. It does not POST to EDXEIX, does not stage jobs, and does not write data.</section>
            <section class="card hero <?= esr_h($decisionType) ?>">
                <h1>EDXEIX Submit Readiness Probe</h1>
                <p><?= esr_h($decisionText) ?></p>
                <div><?= esr_badge($decision, $decisionType) ?> <?= esr_badge('LIVE SUBMIT OFF','good') ?> <?= esr_badge('NO EDXEIX POST','good') ?></div>
                <div class="grid" style="margin-top:14px">
                    <?= esr_metric($payloadBuilt, 'Payload previews built') ?>
                    <?= esr_metric($eligible, 'Eligible live candidates') ?>
                    <?= esr_metric($blocked, 'Blocked rows') ?>
                    <?= esr_metric($sessionFiles, 'Session file candidates') ?>
                </div>
                <div class="actions"><a class="btn" href="/ops/edxeix-submit-readiness.php?format=json">Open JSON</a><a class="btn warn" href="/ops/preflight-review.php">Preflight Review</a><a class="btn dark" href="/bolt_edxeix_preflight.php?limit=30">Raw Preflight JSON</a></div>
            </section>
            <?php if ($state['error']): ?><section class="card"><h2>Error</h2><p class="badline"><strong><?= esr_h($state['error']) ?></strong></p></section><?php endif; ?>
            <section class="two">
                <div class="card"><h2>Preparation checks</h2><div class="kv">
                    <div class="k">Config loaded</div><div><?= esr_yes($state['config_loaded']) ?></div>
                    <div class="k">Database read OK</div><div><?= esr_yes($state['db_loaded']) ?></div>
                    <div class="k">cURL extension loaded</div><div><?= esr_yes($curlOk) ?></div>
                    <div class="k">Payload builder available</div><div><?= esr_yes($builderOk) ?></div>
                    <div class="k">EDXEIX core config</div><div><?= esr_yes($configOk) ?></div>
                    <div class="k">Session file candidates</div><div><?= $sessionPresent ? esr_badge((string)$sessionFiles, 'good') : esr_badge('0', 'warn') ?></div>
                    <div class="k">Mechanics ready</div><div><?= $mechanicsReady ? esr_badge('YES', 'good') : esr_badge('NO', 'bad') ?></div>
                    <div class="k">Live ready now</div><div><?= $liveReadyNow ? esr_badge('REQUIRES APPROVAL', 'warn') : esr_badge('NO', 'good') ?></div>
                </div></div>
                <div class="card"><h2>Today’s answer</h2>
                    <p><strong>Preparation can be completed today.</strong></p>
                    <p>Actual live EDXEIX test submission is allowed only if an eligible real future-safe candidate appears and Andreas explicitly approves the final submit step.</p>
                    <p class="badline"><strong>Historical/completed/cancelled rows must not be submitted.</strong></p>
                </div>
            </section>
            <section class="card"><h2>EDXEIX config presence</h2><div class="table-wrap"><table><thead><tr><th>Key</th><th>Present</th><th>Value</th></tr></thead><tbody><?php foreach ($state['config_presence'] as $key => $info): ?><tr><td><code><?= esr_h($key) ?></code></td><td><?= !empty($info['present']) ? esr_badge('YES','good') : esr_badge('NO','warn') ?></td><td><?= esr_h($info['display'] ?? '') ?></td></tr><?php endforeach; ?></tbody></table></div></section>
            <section class="card"><h2>Session file candidates</h2><?php if (!$state['session_candidates']): ?><p class="warnline"><strong>No obvious saved EDXEIX session/cookie file candidates found by filename scan.</strong></p><?php else: ?><div class="table-wrap"><table><thead><tr><th>File</th><th>Directory</th><th>Readable</th><th>Size</th><th>Modified</th></tr></thead><tbody><?php foreach ($state['session_candidates'] as $file): ?><tr><td><code><?= esr_h($file['basename']) ?></code></td><td><code><?= esr_h($file['path_hint']) ?></code></td><td><?= esr_yes(!empty($file['readable'])) ?></td><td><?= esr_h($file['size_bytes']) ?></td><td><?= esr_h($file['modified_at']) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section>
            <section class="card"><h2>Recent payload preparation rows</h2><div class="table-wrap"><table><thead><tr><th>ID</th><th>Status</th><th>Started</th><th>Driver</th><th>Plate</th><th>Payload</th><th>Eligible</th><th>Blockers</th></tr></thead><tbody><?php foreach ($state['rows'] as $row): ?><tr><td><?= esr_h($row['id']) ?></td><td><?= esr_h($row['status']) ?></td><td><?= esr_h($row['started_at']) ?></td><td><?= esr_h($row['driver_name']) ?><br><code><?= esr_h($row['edxeix_driver_id']) ?></code></td><td><?= esr_h($row['plate']) ?><br><code><?= esr_h($row['edxeix_vehicle_id']) ?></code></td><td><?= !empty($row['payload_built']) ? esr_badge('BUILT','good') : esr_badge('NO','bad') ?></td><td><?= !empty($row['live_submission_allowed']) ? esr_badge('YES','warn') : esr_badge('NO','good') ?></td><td><?= esr_h(implode(', ', $row['blockers'])) ?></td></tr><?php endforeach; ?></tbody></table></div></section>
            <section class="card"><h2>Safe next steps</h2><ol class="timeline"><li>Use this page to confirm local preparation mechanics.</li><li>Do not submit any current historical/completed/cancelled row.</li><li>If another real future-safe Bolt candidate appears, rerun Preflight Review first.</li><li>Only after preflight passes should a separate live-submit patch/test be considered with explicit approval.</li></ol></section>
        </main>
    </div>
</div>
</body>
</html>
