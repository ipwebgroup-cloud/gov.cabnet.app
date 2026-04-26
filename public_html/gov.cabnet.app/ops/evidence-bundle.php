<?php
/**
 * gov.cabnet.app — Bolt Test Evidence Bundle
 *
 * Read-only session report for the real future Bolt ride test.
 *
 * Safety contract:
 * - Does not call Bolt.
 * - Does not call EDXEIX.
 * - Does not stage jobs.
 * - Does not update mappings.
 * - Does not write database rows.
 * - Reads only existing readiness state and sanitized Bolt API Visibility JSONL snapshots.
 */

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Robots-Tag: noindex, nofollow', true);

function beb_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function beb_param(string $key, string $default = ''): string
{
    $value = $_GET[$key] ?? $_POST[$key] ?? $default;
    if (is_array($value)) {
        return $default;
    }
    return trim((string)$value);
}

function beb_int_param(string $key, int $default, int $min, int $max): int
{
    $raw = $_GET[$key] ?? $_POST[$key] ?? $default;
    $value = filter_var($raw, FILTER_VALIDATE_INT, ['options' => ['default' => $default]]);
    return max($min, min($max, (int)$value));
}

function beb_badge(string $text, string $type = 'neutral'): string
{
    return '<span class="badge badge-' . beb_h($type) . '">' . beb_h($text) . '</span>';
}

function beb_bool_badge(bool $value, string $yes = 'YES', string $no = 'NO'): string
{
    return beb_badge($value ? $yes : $no, $value ? 'good' : 'neutral');
}

function beb_metric($value, string $label): string
{
    return '<div class="metric"><strong>' . beb_h((string)$value) . '</strong><span>' . beb_h($label) . '</span></div>';
}

function beb_json_response(array $payload, int $statusCode = 200): void
{
    if (!headers_sent()) {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('X-Robots-Tag: noindex,nofollow', true);
    }
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit;
}

function beb_load_readiness(): array
{
    $path = dirname(__DIR__) . '/bolt_readiness_audit.php';
    $out = ['ok' => false, 'audit' => null, 'error' => null, 'path' => $path];

    if (!is_file($path) || !is_readable($path)) {
        $out['error'] = 'bolt_readiness_audit.php was not found or is not readable.';
        return $out;
    }

    try {
        require_once $path;
        if (!function_exists('gov_readiness_build_audit')) {
            $out['error'] = 'gov_readiness_build_audit() is unavailable.';
            return $out;
        }
        $out['audit'] = gov_readiness_build_audit(['limit' => 60, 'analysis_limit' => 350]);
        $out['ok'] = true;
    } catch (Throwable $e) {
        $out['error'] = $e->getMessage();
    }

    return $out;
}

function beb_load_bridge(): array
{
    $path = '/home/cabnet/gov.cabnet.app_app/lib/bolt_visibility_diagnostic.php';
    $out = ['ok' => false, 'error' => null, 'path' => $path];

    if (!is_file($path) || !is_readable($path)) {
        $out['error'] = 'bolt_visibility_diagnostic.php was not found or is not readable.';
        return $out;
    }

    try {
        require_once $path;
        if (!function_exists('gov_bridge_paths')) {
            $out['error'] = 'gov_bridge_paths() is unavailable after loading bridge library.';
            return $out;
        }
        $out['ok'] = true;
    } catch (Throwable $e) {
        $out['error'] = $e->getMessage();
    }

    return $out;
}

function beb_safe_date(string $date): string
{
    $date = trim($date);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return date('Y-m-d');
    }
    return $date;
}

function beb_snapshot_file(string $date): ?string
{
    if (!function_exists('gov_bridge_paths')) {
        return null;
    }
    $paths = gov_bridge_paths();
    $artifacts = (string)($paths['artifacts'] ?? '');
    if ($artifacts === '') {
        return null;
    }
    return rtrim($artifacts, '/') . '/bolt-api-visibility/' . $date . '.jsonl';
}

function beb_read_snapshots(string $date, int $limit): array
{
    $file = beb_snapshot_file($date);
    if ($file === null || !is_file($file) || !is_readable($file)) {
        return ['file' => $file, 'rows' => [], 'error' => null];
    }

    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!is_array($lines)) {
        return ['file' => $file, 'rows' => [], 'error' => 'Snapshot file could not be read.'];
    }

    $lines = array_slice($lines, -1 * max(1, min(500, $limit)));
    $rows = [];
    foreach ($lines as $line) {
        $decoded = json_decode($line, true);
        if (is_array($decoded)) {
            $rows[] = $decoded;
        }
    }

    return ['file' => $file, 'rows' => $rows, 'error' => null];
}

function beb_value(array $row, array $keys, $default = '')
{
    foreach ($keys as $key) {
        if (array_key_exists($key, $row) && $row[$key] !== null && $row[$key] !== '') {
            return $row[$key];
        }
    }
    return $default;
}

function beb_candidate_rows(array $rows): array
{
    $out = [];
    foreach ($rows as $row) {
        if (empty($row['mapping_ready'])) { continue; }
        if (empty($row['future_guard_passed'])) { continue; }
        if (!empty($row['terminal_status']) || !empty($row['is_lab_row'])) { continue; }
        if (empty($row['submission_safe'])) { continue; }
        $out[] = $row;
    }
    return $out;
}

function beb_stage_from_label(?string $label): string
{
    $label = strtolower((string)$label);
    if (strpos($label, 'accepted') !== false || strpos($label, 'assigned') !== false) {
        return 'accepted-assigned';
    }
    if (strpos($label, 'pickup') !== false || strpos($label, 'waiting') !== false || strpos($label, 'picked') !== false) {
        return 'pickup-waiting';
    }
    if (strpos($label, 'started') !== false || strpos($label, 'trip-start') !== false || strpos($label, 'in-progress') !== false) {
        return 'trip-started';
    }
    if (strpos($label, 'completed') !== false || strpos($label, 'finished') !== false) {
        return 'completed';
    }
    if (strpos($label, 'watch') !== false) {
        return 'auto-watch';
    }
    return 'manual-other';
}

function beb_int_from_path(array $row, array $path, int $default = 0): int
{
    $cursor = $row;
    foreach ($path as $part) {
        if (!is_array($cursor) || !array_key_exists($part, $cursor)) {
            return $default;
        }
        $cursor = $cursor[$part];
    }
    return is_numeric($cursor) ? (int)$cursor : $default;
}

function beb_bool_from_path(array $row, array $path): bool
{
    $cursor = $row;
    foreach ($path as $part) {
        if (!is_array($cursor) || !array_key_exists($part, $cursor)) {
            return false;
        }
        $cursor = $cursor[$part];
    }
    return !empty($cursor);
}

function beb_analyze_snapshots(array $rows): array
{
    $stages = [];
    foreach (['accepted-assigned', 'pickup-waiting', 'trip-started', 'completed', 'auto-watch', 'manual-other'] as $stage) {
        $stages[$stage] = [
            'count' => 0,
            'latest_at' => null,
            'max_orders_seen' => 0,
            'max_sample_count' => 0,
            'max_local_recent_count' => 0,
            'driver_match' => false,
            'vehicle_match' => false,
            'order_match' => false,
        ];
    }

    $analysis = [
        'total_snapshots' => count($rows),
        'first_captured_at' => null,
        'latest_captured_at' => null,
        'latest_snapshot' => null,
        'max_orders_seen' => 0,
        'max_sample_count' => 0,
        'max_local_recent_count' => 0,
        'ever_orders_seen' => false,
        'ever_samples_seen' => false,
        'ever_local_recent_seen' => false,
        'ever_driver_match' => false,
        'ever_vehicle_match' => false,
        'ever_order_match' => false,
        'stages' => $stages,
    ];

    foreach ($rows as $row) {
        $capturedAt = (string)($row['captured_at'] ?? '');
        if ($analysis['first_captured_at'] === null && $capturedAt !== '') {
            $analysis['first_captured_at'] = $capturedAt;
        }
        if ($capturedAt !== '') {
            $analysis['latest_captured_at'] = $capturedAt;
        }
        $analysis['latest_snapshot'] = $row;

        $stage = beb_stage_from_label($row['label'] ?? '');
        $ordersSeen = beb_int_from_path($row, ['visibility', 'orders_seen']);
        $sampleCount = beb_int_from_path($row, ['visibility', 'sample_count']);
        $localCount = beb_int_from_path($row, ['visibility', 'local_recent_count']);
        $driverMatch = beb_bool_from_path($row, ['visibility', 'watch', 'matches', 'driver_uuid']);
        $vehicleMatch = beb_bool_from_path($row, ['visibility', 'watch', 'matches', 'vehicle_plate']);
        $orderMatch = beb_bool_from_path($row, ['visibility', 'watch', 'matches', 'order_id']);

        $analysis['max_orders_seen'] = max($analysis['max_orders_seen'], $ordersSeen);
        $analysis['max_sample_count'] = max($analysis['max_sample_count'], $sampleCount);
        $analysis['max_local_recent_count'] = max($analysis['max_local_recent_count'], $localCount);
        $analysis['ever_orders_seen'] = $analysis['ever_orders_seen'] || $ordersSeen > 0;
        $analysis['ever_samples_seen'] = $analysis['ever_samples_seen'] || $sampleCount > 0;
        $analysis['ever_local_recent_seen'] = $analysis['ever_local_recent_seen'] || $localCount > 0;
        $analysis['ever_driver_match'] = $analysis['ever_driver_match'] || $driverMatch;
        $analysis['ever_vehicle_match'] = $analysis['ever_vehicle_match'] || $vehicleMatch;
        $analysis['ever_order_match'] = $analysis['ever_order_match'] || $orderMatch;

        $analysis['stages'][$stage]['count']++;
        $analysis['stages'][$stage]['latest_at'] = $capturedAt !== '' ? $capturedAt : $analysis['stages'][$stage]['latest_at'];
        $analysis['stages'][$stage]['max_orders_seen'] = max($analysis['stages'][$stage]['max_orders_seen'], $ordersSeen);
        $analysis['stages'][$stage]['max_sample_count'] = max($analysis['stages'][$stage]['max_sample_count'], $sampleCount);
        $analysis['stages'][$stage]['max_local_recent_count'] = max($analysis['stages'][$stage]['max_local_recent_count'], $localCount);
        $analysis['stages'][$stage]['driver_match'] = $analysis['stages'][$stage]['driver_match'] || $driverMatch;
        $analysis['stages'][$stage]['vehicle_match'] = $analysis['stages'][$stage]['vehicle_match'] || $vehicleMatch;
        $analysis['stages'][$stage]['order_match'] = $analysis['stages'][$stage]['order_match'] || $orderMatch;
    }

    return $analysis;
}

function beb_status_class(?string $status): string
{
    $status = strtoupper((string)$status);
    if (in_array($status, ['ACCEPTED', 'ACTIVE', 'STARTED', 'IN_PROGRESS', 'ARRIVED', 'WAITING'], true)) {
        return 'good';
    }
    if (strpos($status, 'CANCEL') !== false || in_array($status, ['EXPIRED', 'FAILED', 'REJECTED'], true)) {
        return 'bad';
    }
    if (strpos($status, 'COMPLETE') !== false || strpos($status, 'FINISH') !== false) {
        return 'warn';
    }
    return 'neutral';
}

$date = beb_safe_date(beb_param('date', date('Y-m-d')));
$limit = beb_int_param('limit', 200, 1, 500);
$format = beb_param('format', 'html');

$readiness = beb_load_readiness();
$audit = is_array($readiness['audit'] ?? null) ? $readiness['audit'] : [];
$config = $audit['config_state'] ?? [];
$drivers = $audit['reference_counts']['drivers'] ?? ['mapped' => 0, 'total' => 0, 'unmapped' => 0];
$vehicles = $audit['reference_counts']['vehicles'] ?? ['mapped' => 0, 'total' => 0, 'unmapped' => 0];
$recent = $audit['recent_bookings'] ?? ['rows' => [], 'submission_safe_rows' => 0];
$queue = $audit['queue_safety'] ?? [];
$lab = $audit['lab_safety'] ?? [];
$attempts = $audit['submission_attempt_safety'] ?? [];
$candidates = beb_candidate_rows($recent['rows'] ?? []);

$dryRun = !empty($config['dry_run_enabled']);
$boltConfig = !empty($config['bolt_credentials_present']);
$edxeixConfig = !empty($config['edxeix_lessor_present']) && !empty($config['edxeix_default_starting_point_present']);
$hasMappedDriver = (int)($drivers['mapped'] ?? 0) > 0;
$hasMappedVehicle = (int)($vehicles['mapped'] ?? 0) > 0;
$cleanLab = (int)($lab['normalized_lab_rows'] ?? 0) === 0 && (int)($lab['staged_lab_jobs'] ?? 0) === 0;
$cleanQueue = (int)($queue['submission_jobs_total'] ?? 0) === 0;
$noLiveAttempts = (int)($attempts['confirmed_live_indicated'] ?? 0) === 0;
$readyForFutureTest = $readiness['ok'] && $dryRun && $boltConfig && $edxeixConfig && $hasMappedDriver && $hasMappedVehicle && $cleanLab && $cleanQueue && $noLiveAttempts;
$realCandidateReady = $readyForFutureTest && count($candidates) > 0;

$bridge = beb_load_bridge();
$snapshotRead = $bridge['ok'] ? beb_read_snapshots($date, $limit) : ['file' => null, 'rows' => [], 'error' => $bridge['error']];
$snapshots = $snapshotRead['rows'];
$evidence = beb_analyze_snapshots($snapshots);
$latest = is_array($evidence['latest_snapshot'] ?? null) ? $evidence['latest_snapshot'] : [];
$latestVisibility = $latest['visibility'] ?? [];
$latestMatches = $latestVisibility['watch']['matches'] ?? [];

$bundleVerdict = 'WAITING_FOR_EVIDENCE';
$bundleText = 'No sanitized Bolt visibility snapshots have been recorded for this date yet.';
$bundleType = 'warn';
if ($evidence['total_snapshots'] > 0) {
    $bundleVerdict = 'EVIDENCE_RECORDED';
    $bundleText = 'Sanitized visibility snapshots exist. Continue capturing each ride stage.';
    $bundleType = 'good';
}
if ($evidence['ever_orders_seen'] && !$evidence['ever_samples_seen']) {
    $bundleVerdict = 'BOLT_COUNT_ONLY_VISIBILITY';
    $bundleText = 'Bolt orders were counted, but no order-like sample arrays were exposed to the diagnostic. Use local normalized rows and readiness data for confirmation.';
    $bundleType = 'warn';
}
if ($evidence['ever_driver_match'] || $evidence['ever_vehicle_match']) {
    $bundleVerdict = 'WATCH_MATCH_RECORDED';
    $bundleText = 'At least one watched driver or vehicle match was recorded in the sanitized evidence timeline.';
    $bundleType = 'good';
}
if ($realCandidateReady) {
    $bundleVerdict = 'REAL_CANDIDATE_READY_FOR_PREFLIGHT';
    $bundleText = 'A real future candidate appears ready for preflight-only review. Live EDXEIX submission remains disabled.';
    $bundleType = 'good';
}

$copyRecap = [
    'gov.cabnet.app Bolt Evidence Bundle',
    'Generated: ' . date('c'),
    'Date inspected: ' . $date,
    'Readiness verdict: ' . (string)($audit['verdict'] ?? 'UNKNOWN'),
    'Bundle verdict: ' . $bundleVerdict,
    'Snapshots: ' . (string)$evidence['total_snapshots'],
    'Max orders seen: ' . (string)$evidence['max_orders_seen'],
    'Max sanitized samples: ' . (string)$evidence['max_sample_count'],
    'Max local recent rows: ' . (string)$evidence['max_local_recent_count'],
    'Driver match recorded: ' . ($evidence['ever_driver_match'] ? 'YES' : 'NO'),
    'Vehicle match recorded: ' . ($evidence['ever_vehicle_match'] ? 'YES' : 'NO'),
    'Real future candidates: ' . (string)count($candidates),
    'Live EDXEIX submit: DISABLED',
];

$jsonPayload = [
    'ok' => $readiness['ok'] && $bridge['ok'] && $snapshotRead['error'] === null,
    'script' => 'ops/evidence-bundle.php',
    'generated_at' => date('c'),
    'date' => $date,
    'safety_contract' => [
        'calls_bolt' => false,
        'calls_edxeix' => false,
        'stages_jobs' => false,
        'updates_mappings' => false,
        'writes_database' => false,
        'reads_sanitized_visibility_snapshots_only' => true,
        'live_edxeix_submission' => 'disabled_not_used',
    ],
    'readiness' => [
        'loaded' => $readiness['ok'],
        'error' => $readiness['error'],
        'verdict' => $audit['verdict'] ?? null,
        'ready_for_future_test' => $readyForFutureTest,
        'real_candidate_ready' => $realCandidateReady,
        'candidate_count' => count($candidates),
        'mapped_drivers' => $drivers,
        'mapped_vehicles' => $vehicles,
        'clean_lab' => $cleanLab,
        'clean_queue' => $cleanQueue,
        'no_live_attempts' => $noLiveAttempts,
    ],
    'evidence' => $evidence,
    'bundle_verdict' => $bundleVerdict,
    'bundle_text' => $bundleText,
    'snapshot_file' => $snapshotRead['file'],
    'snapshot_read_error' => $snapshotRead['error'],
    'links' => [
        'html' => '/ops/evidence-bundle.php',
        'json' => '/ops/evidence-bundle.php?format=json',
        'dev_accelerator' => '/ops/dev-accelerator.php',
        'bolt_visibility' => '/ops/bolt-api-visibility.php',
        'future_test' => '/ops/future-test.php',
        'readiness' => '/ops/readiness.php',
        'preflight_json' => '/bolt_edxeix_preflight.php?limit=30',
    ],
];

if ($format === 'json') {
    beb_json_response($jsonPayload, $jsonPayload['ok'] ? 200 : 500);
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Bolt Evidence Bundle | gov.cabnet.app</title>
    <style>
        :root{--bg:#f3f6fb;--panel:#fff;--ink:#07152f;--muted:#475569;--line:#d7e1ef;--nav:#081225;--blue:#2563eb;--green:#07875a;--orange:#b85c00;--red:#b42318;--slate:#334155;--soft:#f8fbff}*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--ink);font-family:Arial,Helvetica,sans-serif}.nav{background:var(--nav);color:#fff;min-height:56px;display:flex;align-items:center;gap:18px;padding:0 26px;position:sticky;top:0;z-index:5;overflow:auto}.nav strong{white-space:nowrap}.nav a{color:#fff;text-decoration:none;font-size:15px;white-space:nowrap;opacity:.92}.nav a:hover{opacity:1;text-decoration:underline}.wrap{width:min(1500px,calc(100% - 48px));margin:26px auto 60px}.card{background:var(--panel);border:1px solid var(--line);border-radius:14px;padding:18px;margin-bottom:18px;box-shadow:0 10px 26px rgba(8,18,37,.04)}h1{font-size:34px;margin:0 0 12px}h2{font-size:23px;margin:0 0 14px}h3{font-size:18px;margin:0 0 8px}p{color:var(--muted);line-height:1.45}.hero{border-left:7px solid var(--orange)}.hero.good{border-left-color:var(--green)}.hero.bad{border-left-color:var(--red)}.safety{background:#ecfdf3;border:1px solid #bbf7d0;border-left:7px solid var(--green);border-radius:14px;padding:16px;margin-bottom:18px}.safety strong{color:#166534}.grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}.two{display:grid;grid-template-columns:1fr 1fr;gap:18px}.stage-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}.metric,.stage-card{border:1px solid var(--line);border-radius:10px;padding:14px;background:var(--soft);min-height:82px}.metric strong{display:block;font-size:30px;line-height:1.05;word-break:break-word}.metric span,.stage-card span{color:var(--muted);font-size:14px}.stage-card{border-top:5px solid var(--slate)}.stage-card.done{border-top-color:var(--green)}.stage-card.wait{border-top-color:var(--orange)}.stage-card strong{font-size:18px}.badge{display:inline-block;padding:5px 9px;border-radius:999px;font-size:12px;font-weight:700;margin:1px 3px 1px 0;white-space:nowrap}.badge-good{background:#dcfce7;color:#166534}.badge-warn{background:#fff7ed;color:#b45309}.badge-bad{background:#fee2e2;color:#991b1b}.badge-neutral{background:#eaf1ff;color:#1e40af}.actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:14px}.btn{display:inline-block;padding:10px 14px;border-radius:8px;color:#fff;text-decoration:none;font-weight:700;background:var(--blue);border:0;cursor:pointer;font-size:14px}.btn.good{background:var(--green)}.btn.warn{background:var(--orange)}.btn.dark{background:var(--slate)}label{display:block;font-size:13px;font-weight:700;color:var(--slate);margin:10px 0 5px}input{width:100%;padding:10px;border:1px solid var(--line);border-radius:8px;background:#fff;color:var(--ink)}.form-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}.table-wrap{overflow:auto;border:1px solid var(--line);border-radius:10px}table{width:100%;border-collapse:collapse;min-width:960px;background:#fff}th,td{border-bottom:1px solid var(--line);padding:10px;text-align:left;vertical-align:top;font-size:14px}th{background:#f1f5f9;color:#334155}.mono{font-family:Consolas,Menlo,monospace;font-size:12px;word-break:break-all}.small{font-size:13px;color:var(--muted)}.goodline{color:#166534}.warnline{color:#b45309}.badline{color:#991b1b}code,pre{background:#eef2ff;border-radius:6px}code{padding:2px 5px}pre{padding:12px;overflow:auto;white-space:pre-wrap}.copybox{font-family:Consolas,Menlo,monospace;font-size:12px;line-height:1.45}.list{margin:0;padding-left:18px;color:var(--muted)}.list li{margin:7px 0}@media(max-width:1150px){.grid,.stage-grid,.two,.form-grid{grid-template-columns:1fr 1fr}}@media(max-width:760px){.grid,.stage-grid,.two,.form-grid{grid-template-columns:1fr}.wrap{width:calc(100% - 24px);margin-top:14px}.nav{padding:0 14px}}
    </style>
    <link rel="stylesheet" href="/assets/css/gov-ops-edxeix.css?v=1.9">
</head>
<body>
<div class="gov-topbar">
    <div class="gov-brand">
        <div class="gov-brand-crest">ΕΔ</div>
        <div class="gov-brand-text">
            <strong>gov.cabnet.app</strong>
            <span>Bolt → EDXEIX operational console</span>
        </div>
    </div>
    <div class="gov-top-links">
        <a href="/ops/index.php">Αρχική</a>
        <a href="/ops/readiness.php">Readiness</a>
        <a href="/ops/test-session.php">Test Session</a>
        <a href="/ops/preflight-review.php">Preflight Review</a>
        <a class="gov-logout" href="/ops/index.php">Safe Ops</a>
    </div>
</div>
<div class="gov-shell">
    <aside class="gov-sidebar">
        <h3>Evidence Bundle</h3>
        <p>Sanitized timeline review</p>
        <div class="gov-side-group"><div class="gov-side-group-title">Workflow control</div><a class="gov-side-link" href="/ops/test-session.php">Test Session Control</a><a class="gov-side-link" href="/ops/dev-accelerator.php">Dev Accelerator</a><a class="gov-side-link" href="/ops/preflight-review.php">Preflight Review</a><a class="gov-side-link active" href="/ops/evidence-bundle.php">Evidence Bundle</a><a class="gov-side-link" href="/ops/evidence-report.php">Evidence Report</a><a class="gov-side-link" href="/ops/readiness.php">Readiness</a><a class="gov-side-link" href="/ops/mappings.php">Mappings</a><a class="gov-side-link" href="/ops/jobs.php">Jobs</a><a class="gov-side-link" href="/ops/bolt-api-visibility.php">Bolt Visibility</a></div>
        <div class="gov-side-note">Read-only / dry-run operator console. Live EDXEIX submission remains blocked.</div>
    </aside>
    <div class="gov-content">
        <div class="gov-page-header">
            <div>
                <h1 class="gov-page-title">Δέσμη αποδεικτικών Bolt</h1>
                <div class="gov-breadcrumbs">Αρχική / Διαχειριστικό / Δέσμη αποδεικτικών Bolt</div>
            </div>
            <div class="gov-tabs"><a class="gov-tab active" href="/ops/evidence-bundle.php">Αποδείξεις</a><a class="gov-tab" href="/ops/evidence-report.php">Αναφορά</a><a class="gov-tab" href="/ops/test-session.php">Συνεδρία</a><a class="gov-tab" href="/ops/preflight-review.php">Προανασκόπηση</a></div>
        </div>
        <main class="wrap wrap-shell">
    <section class="safety">
        <strong>READ-ONLY EVIDENCE BUNDLE.</strong>
        This page reads readiness state and existing sanitized Bolt visibility timeline entries only. It does not call Bolt, does not call EDXEIX, and does not write data.
    </section>

    <section class="card hero <?= beb_h($bundleType) ?>">
        <h1>Bolt Test Evidence Bundle</h1>
        <p><?= beb_h($bundleText) ?></p>
        <div>
            <?= beb_badge($bundleVerdict, $bundleType) ?>
            <?= beb_badge('LIVE SUBMIT OFF', 'good') ?>
            <?= beb_badge('NO BOLT CALLS HERE', 'good') ?>
            <?= beb_badge('SANITIZED TIMELINE ONLY', 'good') ?>
        </div>
        <div class="grid" style="margin-top:14px">
            <?= beb_metric((string)$evidence['total_snapshots'], 'Snapshots for ' . $date) ?>
            <?= beb_metric((string)$evidence['max_orders_seen'], 'Max orders seen') ?>
            <?= beb_metric((string)$evidence['max_sample_count'], 'Max samples') ?>
            <?= beb_metric((string)$evidence['max_local_recent_count'], 'Max local rows') ?>
        </div>
        <div class="actions">
            <a class="btn good" href="/ops/dev-accelerator.php">Open Dev Accelerator</a>
            <a class="btn" href="/ops/bolt-api-visibility.php">Open Bolt Visibility</a>
            <a class="btn dark" href="/ops/evidence-bundle.php?format=json">Open JSON</a>
            <a class="btn warn" href="/bolt_edxeix_preflight.php?limit=30">Open Preflight JSON</a>
        </div>
    </section>

    <section class="two">
        <div class="card">
            <h2>Inspect another evidence date</h2>
            <form method="get" action="/ops/evidence-bundle.php">
                <div class="form-grid">
                    <div>
                        <label for="date">Date</label>
                        <input id="date" name="date" type="date" value="<?= beb_h($date) ?>">
                    </div>
                    <div>
                        <label for="limit">Snapshot limit</label>
                        <input id="limit" name="limit" type="number" min="1" max="500" value="<?= beb_h((string)$limit) ?>">
                    </div>
                    <div>
                        <label>&nbsp;</label>
                        <button class="btn" type="submit">Load Evidence</button>
                    </div>
                </div>
            </form>
            <p class="small">Private source file: <code><?= beb_h((string)($snapshotRead['file'] ?? 'unavailable')) ?></code></p>
        </div>
        <div class="card">
            <h2>Readiness passport</h2>
            <p>Verdict: <?= beb_badge((string)($audit['verdict'] ?? 'UNKNOWN'), $readyForFutureTest ? 'good' : 'warn') ?></p>
            <p>Ready for future test <?= beb_bool_badge($readyForFutureTest) ?></p>
            <p>Real candidate ready <?= beb_bool_badge($realCandidateReady) ?></p>
            <p>Driver mappings: <strong><?= beb_h(($drivers['mapped'] ?? 0) . '/' . ($drivers['total'] ?? 0)) ?></strong></p>
            <p>Vehicle mappings: <strong><?= beb_h(($vehicles['mapped'] ?? 0) . '/' . ($vehicles['total'] ?? 0)) ?></strong></p>
            <p>No live attempts <?= beb_bool_badge($noLiveAttempts) ?></p>
        </div>
    </section>

    <section class="card">
        <h2>Stage coverage</h2>
        <p class="small">The ideal real-ride evidence set has at least one snapshot for accepted/assigned, pickup/waiting, trip started, and completed.</p>
        <div class="stage-grid">
            <?php foreach (['accepted-assigned' => 'Accepted / Assigned', 'pickup-waiting' => 'Pickup / Waiting', 'trip-started' => 'Trip Started', 'completed' => 'Completed'] as $stageKey => $stageTitle): ?>
                <?php $stageData = $evidence['stages'][$stageKey] ?? []; $done = (int)($stageData['count'] ?? 0) > 0; ?>
                <article class="stage-card <?= $done ? 'done' : 'wait' ?>">
                    <strong><?= beb_h($stageTitle) ?></strong><br>
                    <span><?= beb_h((string)($stageData['count'] ?? 0)) ?> snapshot(s)</span>
                    <p class="small">Latest: <code><?= beb_h((string)($stageData['latest_at'] ?? 'not captured')) ?></code></p>
                    <p>
                        <?= beb_badge('orders ' . (string)($stageData['max_orders_seen'] ?? 0), ((int)($stageData['max_orders_seen'] ?? 0) > 0) ? 'good' : 'neutral') ?>
                        <?= beb_badge('samples ' . (string)($stageData['max_sample_count'] ?? 0), ((int)($stageData['max_sample_count'] ?? 0) > 0) ? 'good' : 'neutral') ?>
                        <?= beb_badge('local ' . (string)($stageData['max_local_recent_count'] ?? 0), ((int)($stageData['max_local_recent_count'] ?? 0) > 0) ? 'good' : 'neutral') ?>
                    </p>
                    <p>
                        <?= beb_badge('driver ' . (!empty($stageData['driver_match']) ? 'YES' : 'NO'), !empty($stageData['driver_match']) ? 'good' : 'neutral') ?>
                        <?= beb_badge('vehicle ' . (!empty($stageData['vehicle_match']) ? 'YES' : 'NO'), !empty($stageData['vehicle_match']) ? 'good' : 'neutral') ?>
                    </p>
                </article>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="two">
        <div class="card">
            <h2>Watch match summary</h2>
            <p>Driver match recorded <?= beb_bool_badge($evidence['ever_driver_match']) ?></p>
            <p>Vehicle match recorded <?= beb_bool_badge($evidence['ever_vehicle_match']) ?></p>
            <p>Order fragment match recorded <?= beb_bool_badge($evidence['ever_order_match']) ?></p>
            <p>Any local normalized rows seen <?= beb_bool_badge($evidence['ever_local_recent_seen']) ?></p>
            <p class="small">A vehicle match without a sample may still be useful when the local normalized booking table shows the expected plate/driver after sync.</p>
        </div>
        <div class="card">
            <h2>Recommended next action</h2>
            <?php if (!$readyForFutureTest): ?>
                <p class="badline"><strong>Open Readiness and resolve safety blockers first.</strong></p>
            <?php elseif ($realCandidateReady): ?>
                <p class="goodline"><strong>Open Preflight JSON and review only. Do not submit live.</strong></p>
            <?php elseif ($evidence['total_snapshots'] === 0): ?>
                <p class="warnline"><strong>Use Dev Accelerator during the real Bolt ride and capture each stage.</strong></p>
            <?php else: ?>
                <p class="warnline"><strong>Continue capturing the missing ride stages until the timeline is complete.</strong></p>
            <?php endif; ?>
            <ul class="list">
                <li>Recommended driver: Filippos Giannakopoulos → EDXEIX 17585.</li>
                <li>Recommended vehicle: EMX6874 → EDXEIX 13799.</li>
                <li>Live EDXEIX submission remains disabled.</li>
            </ul>
        </div>
    </section>

    <section class="card">
        <h2>Latest snapshot</h2>
        <?php if (!$latest): ?>
            <p class="warnline"><strong>No snapshots found for <?= beb_h($date) ?>.</strong></p>
        <?php else: ?>
            <div class="grid">
                <?= beb_metric((string)($latestVisibility['orders_seen'] ?? 0), 'Latest orders seen') ?>
                <?= beb_metric((string)($latestVisibility['sample_count'] ?? 0), 'Latest samples') ?>
                <?= beb_metric((string)($latestVisibility['local_recent_count'] ?? 0), 'Latest local rows') ?>
                <?= beb_metric((string)($latest['label'] ?? 'unlabeled'), 'Latest label') ?>
            </div>
            <p class="small">Captured at <code><?= beb_h((string)($latest['captured_at'] ?? '')) ?></code> · Probe ID <code><?= beb_h((string)($latest['probe_id'] ?? '')) ?></code></p>
            <p>
                <?= beb_badge('order match: ' . (!empty($latestMatches['order_id']) ? 'YES' : 'NO'), !empty($latestMatches['order_id']) ? 'good' : 'neutral') ?>
                <?= beb_badge('driver match: ' . (!empty($latestMatches['driver_uuid']) ? 'YES' : 'NO'), !empty($latestMatches['driver_uuid']) ? 'good' : 'neutral') ?>
                <?= beb_badge('vehicle match: ' . (!empty($latestMatches['vehicle_plate']) ? 'YES' : 'NO'), !empty($latestMatches['vehicle_plate']) ? 'good' : 'neutral') ?>
            </p>
        <?php endif; ?>
    </section>

    <section class="card">
        <h2>Timeline</h2>
        <?php if (!$snapshots): ?>
            <p>No sanitized visibility entries found for this date.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Captured</th><th>Label</th><th>Stage</th><th>Orders</th><th>Samples</th><th>Local rows</th><th>Status counts</th><th>Watch matches</th></tr></thead>
                    <tbody>
                    <?php foreach (array_reverse($snapshots) as $row): ?>
                        <?php $vis = $row['visibility'] ?? []; $matches = $vis['watch']['matches'] ?? []; ?>
                        <tr>
                            <td><code><?= beb_h((string)($row['captured_at'] ?? '')) ?></code><br><span class="mono"><?= beb_h((string)($row['probe_id'] ?? '')) ?></span></td>
                            <td><?= beb_h((string)($row['label'] ?? '')) ?></td>
                            <td><?= beb_badge(beb_stage_from_label($row['label'] ?? ''), 'neutral') ?></td>
                            <td><?= beb_badge((string)($vis['orders_seen'] ?? 0), ((int)($vis['orders_seen'] ?? 0) > 0) ? 'good' : 'neutral') ?></td>
                            <td><?= beb_badge((string)($vis['sample_count'] ?? 0), ((int)($vis['sample_count'] ?? 0) > 0) ? 'good' : 'neutral') ?></td>
                            <td><?= beb_badge((string)($vis['local_recent_count'] ?? 0), ((int)($vis['local_recent_count'] ?? 0) > 0) ? 'good' : 'neutral') ?></td>
                            <td class="small">
                                <?php foreach (($vis['status_counts_from_samples'] ?? []) as $status => $count): ?>
                                    <?= beb_badge((string)$status . ': ' . (string)$count, beb_status_class((string)$status)) ?>
                                <?php endforeach; ?>
                                <?php foreach (($vis['status_counts_from_local_recent'] ?? []) as $status => $count): ?>
                                    <?= beb_badge('local ' . (string)$status . ': ' . (string)$count, beb_status_class((string)$status)) ?>
                                <?php endforeach; ?>
                            </td>
                            <td>
                                <?= beb_badge('order ' . (!empty($matches['order_id']) ? 'YES' : 'NO'), !empty($matches['order_id']) ? 'good' : 'neutral') ?>
                                <?= beb_badge('driver ' . (!empty($matches['driver_uuid']) ? 'YES' : 'NO'), !empty($matches['driver_uuid']) ? 'good' : 'neutral') ?>
                                <?= beb_badge('vehicle ' . (!empty($matches['vehicle_plate']) ? 'YES' : 'NO'), !empty($matches['vehicle_plate']) ? 'good' : 'neutral') ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="card">
        <h2>Copy/paste recap</h2>
        <pre class="copybox"><?= beb_h(implode("\n", $copyRecap)) ?></pre>
    </section>
        </main>
    </div>
</div>
</body>
</html>
