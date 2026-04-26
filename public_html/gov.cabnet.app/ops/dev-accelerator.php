<?php
/**
 * gov.cabnet.app — Bolt Dev Accelerator / Real Future Test Cockpit
 *
 * Purpose:
 * - Speed up the next real future Bolt ride test.
 * - Keep the operator on one guided page.
 * - Optionally run the existing Bolt API Visibility Diagnostic dry-run probe.
 *
 * Safety contract:
 * - Does not submit to EDXEIX.
 * - Does not stage jobs.
 * - Does not modify mappings.
 * - Default page load does not call Bolt.
 * - The optional probe uses gov_bolt_visibility_build_snapshot(), which calls the
 *   existing Bolt order sync in dry-run mode only and records sanitized summaries only.
 */

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Robots-Tag: noindex, nofollow', true);

function bda_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function bda_param(string $key, string $default = ''): string
{
    $value = $_GET[$key] ?? $_POST[$key] ?? $default;
    if (is_array($value)) {
        return $default;
    }
    return trim((string)$value);
}

function bda_bool_param(string $key, bool $default = false): bool
{
    $raw = $_GET[$key] ?? $_POST[$key] ?? null;
    if ($raw === null || $raw === '') {
        return $default;
    }
    $parsed = filter_var($raw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    return $parsed === null ? $default : $parsed;
}

function bda_int_param(string $key, int $default, int $min, int $max): int
{
    $raw = $_GET[$key] ?? $_POST[$key] ?? $default;
    $value = filter_var($raw, FILTER_VALIDATE_INT, ['options' => ['default' => $default]]);
    return max($min, min($max, (int)$value));
}

function bda_badge(string $text, string $type = 'neutral'): string
{
    return '<span class="badge badge-' . bda_h($type) . '">' . bda_h($text) . '</span>';
}

function bda_status_badge(bool $ok, string $yes = 'OK', string $no = 'CHECK'): string
{
    return bda_badge($ok ? $yes : $no, $ok ? 'good' : 'warn');
}

function bda_metric($value, string $label): string
{
    return '<div class="metric"><strong>' . bda_h((string)$value) . '</strong><span>' . bda_h($label) . '</span></div>';
}

function bda_json_response(array $payload, int $statusCode = 200): void
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

function bda_load_readiness(): array
{
    $path = dirname(__DIR__) . '/bolt_readiness_audit.php';
    $out = [
        'ok' => false,
        'path' => $path,
        'audit' => null,
        'error' => null,
    ];

    if (!is_file($path) || !is_readable($path)) {
        $out['error'] = 'bolt_readiness_audit.php was not found or is not readable.';
        return $out;
    }

    try {
        require_once $path;
        if (!function_exists('gov_readiness_build_audit')) {
            $out['error'] = 'gov_readiness_build_audit() is unavailable after loading readiness audit.';
            return $out;
        }
        $out['audit'] = gov_readiness_build_audit(['limit' => 60, 'analysis_limit' => 350]);
        $out['ok'] = true;
    } catch (Throwable $e) {
        $out['error'] = $e->getMessage();
    }

    return $out;
}

function bda_load_visibility_lib(): array
{
    $path = '/home/cabnet/gov.cabnet.app_app/lib/bolt_visibility_diagnostic.php';
    $out = [
        'ok' => false,
        'path' => $path,
        'error' => null,
    ];

    if (!is_file($path) || !is_readable($path)) {
        $out['error'] = 'bolt_visibility_diagnostic.php was not found or is not readable.';
        return $out;
    }

    try {
        require_once $path;
        if (!function_exists('gov_bolt_visibility_build_snapshot')) {
            $out['error'] = 'gov_bolt_visibility_build_snapshot() is unavailable after loading visibility diagnostic.';
            return $out;
        }
        $out['ok'] = true;
    } catch (Throwable $e) {
        $out['error'] = $e->getMessage();
    }

    return $out;
}

function bda_value(array $row, array $keys, $default = '')
{
    foreach ($keys as $key) {
        if (array_key_exists($key, $row) && $row[$key] !== null && $row[$key] !== '') {
            return $row[$key];
        }
    }
    return $default;
}

function bda_candidate_rows(array $rows): array
{
    $out = [];
    foreach ($rows as $row) {
        if (empty($row['mapping_ready'])) {
            continue;
        }
        if (empty($row['future_guard_passed'])) {
            continue;
        }
        if (!empty($row['terminal_status']) || !empty($row['is_lab_row'])) {
            continue;
        }
        if (empty($row['submission_safe'])) {
            continue;
        }
        $out[] = $row;
    }
    return $out;
}

function bda_status_type(?string $status): string
{
    $status = strtoupper(trim((string)$status));
    if ($status === '') {
        return 'neutral';
    }
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

function bda_stage_url(string $stage, string $driverUuid, string $plate, int $hoursBack, int $sampleLimit): string
{
    return '/ops/dev-accelerator.php?' . http_build_query([
        'probe' => '1',
        'record' => '1',
        'stage' => $stage,
        'label' => 'dev-accelerator-' . $stage,
        'watch_driver_uuid' => $driverUuid,
        'watch_vehicle_plate' => $plate,
        'hours_back' => (string)$hoursBack,
        'sample_limit' => (string)$sampleLimit,
    ]);
}

$defaultDriverUuid = '57256761-d21b-4940-a3ca-bdcec5ef6af1';
$defaultPlate = 'EMX6874';

$probe = bda_bool_param('probe', false);
$record = bda_bool_param('record', true);
$stage = preg_replace('/[^a-z0-9_-]+/i', '-', bda_param('stage', 'manual')) ?: 'manual';
$label = bda_param('label', 'dev-accelerator-' . $stage);
$hoursBack = bda_int_param('hours_back', 24, 1, 2160);
$sampleLimit = bda_int_param('sample_limit', 20, 1, 50);
$watchDriverUuid = bda_param('watch_driver_uuid', $defaultDriverUuid);
$watchPlate = bda_param('watch_vehicle_plate', $defaultPlate);
$watchOrderId = bda_param('watch_order_id', '');
$format = bda_param('format', 'html');

$readiness = bda_load_readiness();
$audit = is_array($readiness['audit'] ?? null) ? $readiness['audit'] : [];
$config = $audit['config_state'] ?? [];
$drivers = $audit['reference_counts']['drivers'] ?? ['mapped' => 0, 'total' => 0, 'unmapped' => 0];
$vehicles = $audit['reference_counts']['vehicles'] ?? ['mapped' => 0, 'total' => 0, 'unmapped' => 0];
$recent = $audit['recent_bookings'] ?? ['rows' => [], 'submission_safe_rows' => 0];
$queue = $audit['queue_safety'] ?? [];
$lab = $audit['lab_safety'] ?? [];
$attempts = $audit['submission_attempt_safety'] ?? [];
$candidates = bda_candidate_rows($recent['rows'] ?? []);

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

$snapshot = null;
$visibilityLoad = ['ok' => false, 'error' => null, 'path' => null];
$probeError = null;

if ($probe) {
    $visibilityLoad = bda_load_visibility_lib();
    if ($visibilityLoad['ok']) {
        try {
            $snapshot = gov_bolt_visibility_build_snapshot([
                'hours_back' => $hoursBack,
                'sample_limit' => $sampleLimit,
                'record' => $record,
                'label' => $label,
                'watch_driver_uuid' => $watchDriverUuid,
                'watch_vehicle_plate' => $watchPlate,
                'watch_order_id' => $watchOrderId,
            ]);
        } catch (Throwable $e) {
            $probeError = $e->getMessage();
        }
    } else {
        $probeError = $visibilityLoad['error'];
    }
}

$stageUrls = [
    'accepted-assigned' => bda_stage_url('accepted-assigned', $watchDriverUuid, $watchPlate, $hoursBack, $sampleLimit),
    'pickup-waiting' => bda_stage_url('pickup-waiting', $watchDriverUuid, $watchPlate, $hoursBack, $sampleLimit),
    'trip-started' => bda_stage_url('trip-started', $watchDriverUuid, $watchPlate, $hoursBack, $sampleLimit),
    'completed' => bda_stage_url('completed', $watchDriverUuid, $watchPlate, $hoursBack, $sampleLimit),
];

$watchUrl = '/ops/bolt-api-visibility.php?' . http_build_query([
    'run' => '1',
    'record' => '1',
    'hours_back' => (string)$hoursBack,
    'sample_limit' => (string)$sampleLimit,
    'watch_driver_uuid' => $watchDriverUuid,
    'watch_vehicle_plate' => $watchPlate,
    'label' => 'accelerator-watch',
    'refresh' => '20',
]);

$jsonPayload = [
    'ok' => $readiness['ok'] && $probeError === null,
    'script' => 'ops/dev-accelerator.php',
    'generated_at' => date('c'),
    'safety_contract' => [
        'live_edxeix_submission' => 'disabled_not_used',
        'stages_jobs' => false,
        'updates_mappings' => false,
        'default_calls_bolt' => false,
        'probe_calls_bolt_dry_run_only' => $probe,
        'stores_raw_payloads' => false,
    ],
    'readiness' => [
        'loaded' => $readiness['ok'],
        'error' => $readiness['error'],
        'ready_for_future_test' => $readyForFutureTest,
        'real_candidate_ready' => $realCandidateReady,
        'verdict' => $audit['verdict'] ?? null,
        'dry_run_enabled' => $dryRun,
        'bolt_config_present' => $boltConfig,
        'edxeix_config_present' => $edxeixConfig,
        'mapped_drivers' => $drivers,
        'mapped_vehicles' => $vehicles,
        'clean_lab' => $cleanLab,
        'clean_queue' => $cleanQueue,
        'no_live_attempts' => $noLiveAttempts,
        'candidate_count' => count($candidates),
    ],
    'probe' => [
        'requested' => $probe,
        'record_requested' => $record,
        'stage' => $stage,
        'label' => $label,
        'watch_driver_uuid' => $watchDriverUuid !== '' ? '[set]' : '[not-set]',
        'watch_vehicle_plate' => $watchPlate,
        'watch_order_id_set' => $watchOrderId !== '',
        'error' => $probeError,
        'snapshot_summary' => is_array($snapshot) ? [
            'diagnostic_version' => $snapshot['diagnostic_version'] ?? null,
            'captured_at' => $snapshot['captured_at'] ?? null,
            'probe_id' => $snapshot['probe_id'] ?? null,
            'orders_seen' => $snapshot['visibility']['orders_seen'] ?? null,
            'sample_count' => $snapshot['visibility']['sample_count'] ?? null,
            'local_recent_count' => $snapshot['visibility']['local_recent_count'] ?? null,
            'watch_matches' => $snapshot['visibility']['watch']['matches'] ?? null,
            'recorded' => $snapshot['recorded'] ?? false,
        ] : null,
    ],
    'links' => [
        'html' => '/ops/dev-accelerator.php',
        'json' => '/ops/dev-accelerator.php?format=json',
        'watch_auto_refresh' => $watchUrl,
        'stage_urls' => $stageUrls,
        'readiness' => '/ops/readiness.php',
        'future_test' => '/ops/future-test.php',
        'bolt_visibility' => '/ops/bolt-api-visibility.php',
        'preflight_json' => '/bolt_edxeix_preflight.php?limit=30',
    ],
];

if ($format === 'json') {
    bda_json_response($jsonPayload, $jsonPayload['ok'] ? 200 : 500);
}

$heroType = $realCandidateReady ? 'good' : ($readyForFutureTest ? 'warn' : 'bad');
$heroText = 'System needs attention before the accelerated real future test.';
if ($realCandidateReady) {
    $heroText = 'Real future candidate detected. Move to preflight preview only; live submit remains blocked.';
} elseif ($readyForFutureTest) {
    $heroText = 'System is clean and ready to capture the next real future Bolt ride quickly.';
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Dev Accelerator | gov.cabnet.app</title>
    <style>
        :root{--bg:#f3f6fb;--panel:#fff;--ink:#07152f;--muted:#475569;--line:#d7e1ef;--nav:#081225;--blue:#2563eb;--green:#07875a;--orange:#b85c00;--red:#b42318;--slate:#334155;--soft:#f8fbff}*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--ink);font-family:Arial,Helvetica,sans-serif}.nav{background:var(--nav);color:#fff;min-height:56px;display:flex;align-items:center;gap:18px;padding:0 26px;position:sticky;top:0;z-index:5;overflow:auto}.nav strong{white-space:nowrap}.nav a{color:#fff;text-decoration:none;font-size:15px;white-space:nowrap;opacity:.92}.nav a:hover{opacity:1;text-decoration:underline}.wrap{width:min(1500px,calc(100% - 48px));margin:26px auto 60px}.card{background:var(--panel);border:1px solid var(--line);border-radius:14px;padding:18px;margin-bottom:18px;box-shadow:0 10px 26px rgba(8,18,37,.04)}h1{font-size:34px;margin:0 0 12px}h2{font-size:23px;margin:0 0 14px}h3{font-size:18px;margin:0 0 8px}p{color:var(--muted);line-height:1.45}.hero{border-left:7px solid var(--orange)}.hero.good{border-left-color:var(--green)}.hero.bad{border-left-color:var(--red)}.safety{background:#ecfdf3;border:1px solid #bbf7d0;border-left:7px solid var(--green);border-radius:14px;padding:16px;margin-bottom:18px}.safety strong{color:#166534}.grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}.two{display:grid;grid-template-columns:1fr 1fr;gap:18px}.three{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}.metric{border:1px solid var(--line);border-radius:10px;padding:14px;background:var(--soft);min-height:82px}.metric strong{display:block;font-size:30px;line-height:1.05;word-break:break-word}.metric span{color:var(--muted);font-size:14px}.badge{display:inline-block;padding:5px 9px;border-radius:999px;font-size:12px;font-weight:700;margin:1px 3px 1px 0;white-space:nowrap}.badge-good{background:#dcfce7;color:#166534}.badge-warn{background:#fff7ed;color:#b45309}.badge-bad{background:#fee2e2;color:#991b1b}.badge-neutral{background:#eaf1ff;color:#1e40af}.actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:14px}.btn{display:inline-block;padding:10px 14px;border-radius:8px;color:#fff;text-decoration:none;font-weight:700;background:var(--blue);border:0;cursor:pointer;font-size:14px}.btn.good{background:var(--green)}.btn.warn{background:var(--orange)}.btn.dark{background:var(--slate)}.btn.danger{background:var(--red)}label{display:block;font-size:13px;font-weight:700;color:var(--slate);margin:10px 0 5px}input,select{width:100%;padding:10px;border:1px solid var(--line);border-radius:8px;background:#fff;color:var(--ink)}input[type=checkbox]{width:auto;margin-right:8px}.form-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}.stage{border:1px solid var(--line);border-radius:14px;padding:16px;background:#fff;min-height:205px;border-top:5px solid var(--blue)}.stage strong{display:block;font-size:18px;margin-bottom:8px}.stage small{color:var(--muted)}.stage.warn{border-top-color:var(--orange)}.stage.good{border-top-color:var(--green)}.stage.dark{border-top-color:var(--slate)}.table-wrap{overflow:auto;border:1px solid var(--line);border-radius:10px}table{width:100%;border-collapse:collapse;min-width:850px;background:#fff}th,td{border-bottom:1px solid var(--line);padding:10px;text-align:left;vertical-align:top;font-size:14px}th{background:#f1f5f9;color:#334155}.steps{counter-reset:s;list-style:none;margin:0;padding:0}.steps li{counter-increment:s;background:#f8fbff;border:1px solid var(--line);border-radius:10px;margin:8px 0;padding:10px 12px 10px 48px;position:relative;color:var(--muted)}.steps li:before{content:counter(s);position:absolute;left:12px;top:9px;width:26px;height:26px;border-radius:50%;display:flex;align-items:center;justify-content:center;background:#eaf1ff;color:#1e40af;font-weight:800}.mono{font-family:Consolas,Menlo,monospace;font-size:12px;word-break:break-all}.small{font-size:13px;color:var(--muted)}.goodline{color:#166534}.warnline{color:#b45309}.badline{color:#991b1b}code,pre{background:#eef2ff;border-radius:6px}code{padding:2px 5px}pre{padding:12px;overflow:auto;white-space:pre-wrap}.copybox{font-family:Consolas,Menlo,monospace;font-size:12px;line-height:1.45}@media(max-width:1150px){.grid,.three,.two,.form-grid{grid-template-columns:1fr 1fr}}@media(max-width:760px){.grid,.three,.two,.form-grid{grid-template-columns:1fr}.wrap{width:calc(100% - 24px);margin-top:14px}.nav{padding:0 14px}}
    </style>
</head>
<body>
<nav class="nav">
    <strong>GC gov.cabnet.app</strong>
    <a href="/ops/index.php">Operations Console</a>
    <a href="/ops/dev-accelerator.php">Dev Accelerator</a>
    <a href="/ops/readiness.php">Readiness</a>
    <a href="/ops/future-test.php">Future Test</a>
    <a href="/ops/mappings.php">Mappings</a>
    <a href="/ops/jobs.php">Jobs</a>
    <a href="/ops/bolt-api-visibility.php">Bolt Visibility</a>
</nav>

<main class="wrap">
    <section class="safety">
        <strong>SAFE DEVELOPMENT ACCELERATOR.</strong>
        Live EDXEIX submission is not used here. Optional probes are Bolt dry-run visibility snapshots only.
    </section>

    <section class="card hero <?= bda_h($heroType) ?>">
        <h1>Bolt Dev Accelerator</h1>
        <p><?= bda_h($heroText) ?></p>
        <div>
            <?= bda_badge('LIVE SUBMIT OFF', 'good') ?>
            <?= bda_badge('NO JOB STAGING', 'good') ?>
            <?= bda_badge('NO RAW PAYLOADS', 'good') ?>
            <?= $probe ? bda_badge('DRY-RUN PROBE EXECUTED', $probeError === null ? 'good' : 'bad') : bda_badge('NO BOLT CALL ON PAGE LOAD', 'neutral') ?>
        </div>
        <div class="grid" style="margin-top:14px">
            <?= bda_metric($audit['verdict'] ?? 'UNKNOWN', 'Readiness verdict') ?>
            <?= bda_metric(($drivers['mapped'] ?? 0) . '/' . ($drivers['total'] ?? 0), 'Drivers mapped') ?>
            <?= bda_metric(($vehicles['mapped'] ?? 0) . '/' . ($vehicles['total'] ?? 0), 'Vehicles mapped') ?>
            <?= bda_metric((string)count($candidates), 'Real future candidates') ?>
        </div>
    </section>

    <?php if (!$readiness['ok']): ?>
        <section class="card">
            <h2>Readiness load problem</h2>
            <p class="badline"><strong><?= bda_h((string)$readiness['error']) ?></strong></p>
        </section>
    <?php endif; ?>

    <section class="card">
        <h2>Fast capture buttons</h2>
        <p>Use these buttons during the real ride to record a clean private timeline. Each button runs the existing Bolt visibility diagnostic in dry-run mode and records a sanitized JSONL summary.</p>
        <div class="three">
            <article class="stage good">
                <strong>1. Accepted / Assigned</strong>
                <small>Click when Filippos accepts or is assigned to the Bolt ride.</small>
                <div class="actions"><a class="btn good" href="<?= bda_h($stageUrls['accepted-assigned']) ?>">Capture Accepted</a></div>
            </article>
            <article class="stage warn">
                <strong>2. Pickup / Waiting</strong>
                <small>Click when the driver has arrived or the passenger is waiting/picked up.</small>
                <div class="actions"><a class="btn warn" href="<?= bda_h($stageUrls['pickup-waiting']) ?>">Capture Pickup</a></div>
            </article>
            <article class="stage">
                <strong>3. Trip Started</strong>
                <small>Click when the ride starts and is clearly in progress.</small>
                <div class="actions"><a class="btn" href="<?= bda_h($stageUrls['trip-started']) ?>">Capture Started</a></div>
            </article>
            <article class="stage dark">
                <strong>4. Completed</strong>
                <small>Click after completion to compare visibility against the active-trip stages.</small>
                <div class="actions"><a class="btn dark" href="<?= bda_h($stageUrls['completed']) ?>">Capture Completed</a></div>
            </article>
            <article class="stage">
                <strong>Auto-watch</strong>
                <small>Use when you want the diagnostic page to refresh every 20 seconds while the ride evolves.</small>
                <div class="actions"><a class="btn" href="<?= bda_h($watchUrl) ?>">Watch every 20s</a></div>
            </article>
            <article class="stage dark">
                <strong>JSON status</strong>
                <small>Use this for copy/paste into chat or future automation checks.</small>
                <div class="actions"><a class="btn dark" href="/ops/dev-accelerator.php?format=json">Open JSON</a></div>
            </article>
        </div>
    </section>

    <section class="two">
        <div class="card">
            <h2>Probe settings</h2>
            <form method="get" action="/ops/dev-accelerator.php">
                <input type="hidden" name="probe" value="1">
                <div class="form-grid">
                    <div>
                        <label for="stage">Stage</label>
                        <select id="stage" name="stage">
                            <?php foreach (['manual', 'accepted-assigned', 'pickup-waiting', 'trip-started', 'completed'] as $option): ?>
                                <option value="<?= bda_h($option) ?>" <?= $stage === $option ? 'selected' : '' ?>><?= bda_h($option) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="label">Label</label>
                        <input id="label" name="label" value="<?= bda_h($label) ?>">
                    </div>
                    <div>
                        <label for="hours_back">Hours back</label>
                        <input id="hours_back" type="number" name="hours_back" min="1" max="2160" value="<?= bda_h((string)$hoursBack) ?>">
                    </div>
                    <div>
                        <label for="sample_limit">Sample limit</label>
                        <input id="sample_limit" type="number" name="sample_limit" min="1" max="50" value="<?= bda_h((string)$sampleLimit) ?>">
                    </div>
                    <div>
                        <label for="watch_driver_uuid">Watch driver UUID</label>
                        <input id="watch_driver_uuid" name="watch_driver_uuid" value="<?= bda_h($watchDriverUuid) ?>">
                    </div>
                    <div>
                        <label for="watch_vehicle_plate">Watch vehicle plate</label>
                        <input id="watch_vehicle_plate" name="watch_vehicle_plate" value="<?= bda_h($watchPlate) ?>">
                    </div>
                    <div>
                        <label for="watch_order_id">Watch order fragment</label>
                        <input id="watch_order_id" name="watch_order_id" value="<?= bda_h($watchOrderId) ?>" placeholder="optional">
                    </div>
                    <div>
                        <label>&nbsp;</label>
                        <label><input type="checkbox" name="record" value="1" <?= $record ? 'checked' : '' ?>>Record sanitized private snapshot</label>
                    </div>
                </div>
                <div class="actions">
                    <button class="btn good" type="submit">Run dry-run probe</button>
                    <a class="btn dark" href="/ops/dev-accelerator.php">Reset page</a>
                </div>
            </form>
        </div>

        <div class="card">
            <h2>Readiness passport</h2>
            <p class="small">This must be clean before the real future ride is worth testing.</p>
            <p>Dry-run mode <?= bda_status_badge($dryRun) ?></p>
            <p>Bolt config present <?= bda_status_badge($boltConfig) ?></p>
            <p>EDXEIX config present <?= bda_status_badge($edxeixConfig) ?></p>
            <p>Mapped driver exists <?= bda_status_badge($hasMappedDriver) ?></p>
            <p>Mapped vehicle exists <?= bda_status_badge($hasMappedVehicle) ?></p>
            <p>No LAB rows/jobs <?= bda_status_badge($cleanLab) ?></p>
            <p>No local submission jobs <?= bda_status_badge($cleanQueue) ?></p>
            <p>No live attempts indicated <?= bda_status_badge($noLiveAttempts) ?></p>
            <div class="actions">
                <a class="btn" href="/ops/readiness.php">Readiness UI</a>
                <a class="btn dark" href="/bolt_readiness_audit.php">Readiness JSON</a>
            </div>
        </div>
    </section>

    <?php if ($probe): ?>
        <section class="card">
            <h2>Latest dry-run probe result</h2>
            <?php if ($probeError !== null): ?>
                <p class="badline"><strong><?= bda_h($probeError) ?></strong></p>
            <?php elseif (is_array($snapshot)): ?>
                <?php $visibility = $snapshot['visibility'] ?? []; $matches = $visibility['watch']['matches'] ?? []; ?>
                <div class="grid">
                    <?= bda_metric($visibility['orders_seen'] ?? 0, 'Orders seen') ?>
                    <?= bda_metric($visibility['sample_count'] ?? 0, 'Sanitized samples') ?>
                    <?= bda_metric($visibility['local_recent_count'] ?? 0, 'Local recent rows') ?>
                    <?= bda_metric(!empty($snapshot['recorded']) ? 'YES' : 'NO', 'Recorded privately') ?>
                </div>
                <p class="small">Captured at <code><?= bda_h((string)($snapshot['captured_at'] ?? '')) ?></code> · Probe ID <code><?= bda_h((string)($snapshot['probe_id'] ?? '')) ?></code> · Version <code><?= bda_h((string)($snapshot['diagnostic_version'] ?? '')) ?></code></p>
                <p>
                    <?= bda_badge('order match: ' . (!empty($matches['order_id']) ? 'YES' : 'NO'), !empty($matches['order_id']) ? 'good' : 'neutral') ?>
                    <?= bda_badge('driver match: ' . (!empty($matches['driver_uuid']) ? 'YES' : 'NO'), !empty($matches['driver_uuid']) ? 'good' : 'neutral') ?>
                    <?= bda_badge('vehicle match: ' . (!empty($matches['vehicle_plate']) ? 'YES' : 'NO'), !empty($matches['vehicle_plate']) ? 'good' : 'neutral') ?>
                </p>
                <?php if ((int)($visibility['orders_seen'] ?? 0) > 0 && (int)($visibility['sample_count'] ?? 0) === 0): ?>
                    <p class="warnline"><strong>Bolt returned an order count but no order-like sanitized sample.</strong> Use the local recent rows and the Bolt Visibility page to compare what was normalized.</p>
                <?php endif; ?>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <section class="card">
        <h2>Real future candidates</h2>
        <?php if (!$candidates): ?>
            <p class="warnline"><strong>No real future candidate is detected yet.</strong> This is expected until a real mapped Bolt ride exists at least the configured guard window in the future.</p>
            <p class="small">First preferred test: Filippos Giannakopoulos / Bolt UUID <code><?= bda_h($defaultDriverUuid) ?></code> with EMX6874 / EDXEIX vehicle 13799. EHA2545 / EDXEIX vehicle 5949 is also known.</p>
        <?php else: ?>
            <div class="table-wrap"><table>
                <thead><tr><th>ID</th><th>Source</th><th>Order ref</th><th>Status</th><th>Start</th><th>Driver</th><th>Plate</th><th>Safety</th></tr></thead>
                <tbody>
                <?php foreach ($candidates as $row): ?>
                    <tr>
                        <td><?= bda_h((string)bda_value($row, ['id'], '')) ?></td>
                        <td><?= bda_h((string)bda_value($row, ['source_system'], '')) ?></td>
                        <td class="mono"><?= bda_h((string)bda_value($row, ['order_reference'], '')) ?></td>
                        <td><?= bda_badge((string)bda_value($row, ['status'], 'UNKNOWN'), bda_status_type((string)bda_value($row, ['status'], ''))) ?></td>
                        <td><?= bda_h((string)bda_value($row, ['started_at'], '')) ?></td>
                        <td><?= bda_h((string)bda_value($row, ['driver_name'], '')) ?></td>
                        <td><?= bda_h((string)bda_value($row, ['plate'], '')) ?></td>
                        <td><?= bda_badge('PREFLIGHT ONLY', 'good') ?> <?= bda_badge('LIVE OFF', 'good') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
        <?php endif; ?>
    </section>

    <section class="two">
        <div class="card">
            <h2>Fast test script</h2>
            <ol class="steps">
                <li>Open this page before creating the ride and confirm the readiness passport is clean.</li>
                <li>Create one real Bolt ride 40–60 minutes in the future with Filippos and EMX6874 where possible.</li>
                <li>Click <strong>Capture Accepted</strong> after assignment/acceptance.</li>
                <li>Click <strong>Capture Pickup</strong> when the driver arrives or pickup starts.</li>
                <li>Click <strong>Capture Started</strong> once the ride is in progress.</li>
                <li>Click <strong>Capture Completed</strong> after completion.</li>
                <li>Open Preflight JSON only after a real future candidate appears. Do not submit live.</li>
            </ol>
        </div>
        <div class="card">
            <h2>Copy/paste URLs</h2>
            <pre class="copybox"><?= bda_h(implode("\n", [
                'Dev Accelerator: https://gov.cabnet.app/ops/dev-accelerator.php',
                'Dev Accelerator JSON: https://gov.cabnet.app/ops/dev-accelerator.php?format=json',
                'Auto-watch Filippos + EMX6874: https://gov.cabnet.app' . $watchUrl,
                'Bolt Visibility: https://gov.cabnet.app/ops/bolt-api-visibility.php',
                'Future Test: https://gov.cabnet.app/ops/future-test.php',
                'Readiness: https://gov.cabnet.app/ops/readiness.php',
                'Preflight JSON: https://gov.cabnet.app/bolt_edxeix_preflight.php?limit=30',
            ])) ?></pre>
            <div class="actions">
                <a class="btn" href="/bolt_edxeix_preflight.php?limit=30">Preflight JSON</a>
                <a class="btn dark" href="/ops/bolt-api-visibility.php">Bolt Visibility</a>
                <a class="btn dark" href="/ops/future-test.php">Future Test</a>
            </div>
        </div>
    </section>
</main>
</body>
</html>
