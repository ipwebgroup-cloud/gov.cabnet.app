<?php
/**
 * gov.cabnet.app — Bolt Preflight Review Assistant
 *
 * Purpose:
 * - Explain preflight readiness in operator language.
 * - Help decide whether a real future Bolt row is ready for preflight-only review.
 *
 * Safety contract:
 * - Does not call Bolt.
 * - Does not call EDXEIX.
 * - Does not stage jobs.
 * - Does not update mappings.
 * - Does not write database rows or files.
 * - Does not enable live submission.
 */

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Robots-Tag: noindex,nofollow', true);

function pra_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function pra_param(string $key, string $default = ''): string
{
    $value = $_GET[$key] ?? $_POST[$key] ?? $default;
    if (is_array($value)) {
        return $default;
    }
    return trim((string)$value);
}

function pra_int_param(string $key, int $default, int $min, int $max): int
{
    $raw = $_GET[$key] ?? $_POST[$key] ?? $default;
    $value = filter_var($raw, FILTER_VALIDATE_INT, ['options' => ['default' => $default]]);
    return max($min, min($max, (int)$value));
}

function pra_bool_word(bool $value): string
{
    return $value ? 'YES' : 'NO';
}

function pra_badge(string $text, string $type = 'neutral'): string
{
    return '<span class="badge badge-' . pra_h($type) . '">' . pra_h($text) . '</span>';
}

function pra_bool_badge(bool $value, string $yes = 'YES', string $no = 'NO'): string
{
    return pra_badge($value ? $yes : $no, $value ? 'good' : 'bad');
}

function pra_status_type(?string $status): string
{
    $status = strtoupper(trim((string)$status));
    if ($status === '') {
        return 'neutral';
    }
    if (strpos($status, 'CANCEL') !== false || in_array($status, ['EXPIRED', 'FAILED', 'REJECTED'], true)) {
        return 'bad';
    }
    if (strpos($status, 'COMPLETE') !== false || strpos($status, 'FINISH') !== false || $status === 'DONE') {
        return 'warn';
    }
    if (in_array($status, ['ACCEPTED', 'ACTIVE', 'STARTED', 'IN_PROGRESS', 'ARRIVED', 'WAITING', 'ASSIGNED', 'PENDING'], true)) {
        return 'good';
    }
    return 'neutral';
}

function pra_load_readiness(): array
{
    $path = dirname(__DIR__) . '/bolt_readiness_audit.php';
    $out = [
        'ok' => false,
        'audit' => null,
        'error' => null,
        'path' => $path,
    ];

    try {
        if (!is_file($path) || !is_readable($path)) {
            throw new RuntimeException('Readiness audit file is missing or unreadable.');
        }

        require_once $path;
        if (!function_exists('gov_readiness_build_audit')) {
            throw new RuntimeException('gov_readiness_build_audit() is unavailable.');
        }

        $out['audit'] = gov_readiness_build_audit([
            'limit' => pra_int_param('limit', 60, 1, 200),
            'analysis_limit' => 350,
        ]);
        $out['ok'] = true;
    } catch (Throwable $e) {
        $out['error'] = $e->getMessage();
    }

    return $out;
}

function pra_candidate_rows(array $rows): array
{
    $candidates = [];
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
        $candidates[] = $row;
    }
    return $candidates;
}

function pra_value(array $row, string $key, string $default = ''): string
{
    return isset($row[$key]) && $row[$key] !== null ? (string)$row[$key] : $default;
}

function pra_blocker_label(string $blocker): string
{
    $labels = [
        'lab_row' => 'LAB/test row; never submit live.',
        'driver_not_mapped' => 'Driver is not mapped to EDXEIX.',
        'vehicle_not_mapped' => 'Vehicle is not mapped to EDXEIX.',
        'missing_started_at' => 'Pickup/start time is missing.',
        'started_at_not_30_min_future' => 'Trip is not safely in the future.',
        'terminal_order_status' => 'Trip status is terminal/cancelled/finished.',
        'preview_error' => 'Preview payload build failed.',
    ];
    return $labels[$blocker] ?? str_replace('_', ' ', $blocker);
}

function pra_blocker_type(string $blocker): string
{
    if (in_array($blocker, ['driver_not_mapped', 'vehicle_not_mapped', 'missing_started_at', 'preview_error'], true)) {
        return 'bad';
    }
    if (in_array($blocker, ['started_at_not_30_min_future', 'terminal_order_status', 'lab_row'], true)) {
        return 'warn';
    }
    return 'neutral';
}

function pra_human_decision(array $state): array
{
    if (!$state['readiness_loaded']) {
        return [
            'SYSTEM_ERROR',
            'The readiness audit could not be loaded. Fix this before preflight review.',
            'bad',
        ];
    }

    if (!$state['ready_for_future_test']) {
        return [
            'SETUP_NOT_READY',
            'System setup is not clean enough for a real future preflight test.',
            'bad',
        ];
    }

    if ($state['candidate_count'] <= 0) {
        return [
            'WAITING_FOR_REAL_FUTURE_CANDIDATE',
            'The system is clean, but no real future Bolt candidate exists yet.',
            'warn',
        ];
    }

    return [
        'PREFLIGHT_REVIEW_READY',
        'A real future candidate exists for preflight-only review. Live submission remains disabled.',
        'good',
    ];
}

function pra_json_response(array $payload, int $statusCode = 200): void
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

$limit = pra_int_param('limit', 60, 1, 200);
$format = strtolower(pra_param('format', 'html'));

$readiness = pra_load_readiness();
$audit = is_array($readiness['audit'] ?? null) ? $readiness['audit'] : [];
$config = is_array($audit['config_state'] ?? null) ? $audit['config_state'] : [];
$drivers = is_array($audit['reference_counts']['drivers'] ?? null) ? $audit['reference_counts']['drivers'] : ['total' => 0, 'mapped' => 0, 'unmapped' => 0, 'placeholder' => 0];
$vehicles = is_array($audit['reference_counts']['vehicles'] ?? null) ? $audit['reference_counts']['vehicles'] : ['total' => 0, 'mapped' => 0, 'unmapped' => 0, 'placeholder' => 0];
$recent = is_array($audit['recent_bookings'] ?? null) ? $audit['recent_bookings'] : ['rows' => [], 'submission_safe_rows' => 0];
$rows = is_array($recent['rows'] ?? null) ? $recent['rows'] : [];
$candidates = pra_candidate_rows($rows);
$lab = is_array($audit['lab_safety'] ?? null) ? $audit['lab_safety'] : [];
$queue = is_array($audit['queue_safety'] ?? null) ? $audit['queue_safety'] : [];
$attempts = is_array($audit['submission_attempt_safety'] ?? null) ? $audit['submission_attempt_safety'] : [];

$dryRun = !empty($config['dry_run_enabled']);
$boltConfig = !empty($config['bolt_credentials_present']);
$edxeixConfig = !empty($config['edxeix_lessor_present']) && !empty($config['edxeix_default_starting_point_present']);
$hasMappedDriver = (int)($drivers['mapped'] ?? 0) > 0;
$hasMappedVehicle = (int)($vehicles['mapped'] ?? 0) > 0;
$cleanLab = (int)($lab['normalized_lab_rows'] ?? 0) === 0 && (int)($lab['staged_lab_jobs'] ?? 0) === 0;
$cleanQueue = (int)($queue['submission_jobs_total'] ?? 0) === 0;
$noLiveAttempts = (int)($attempts['confirmed_live_indicated'] ?? 0) === 0;
$readyForFutureTest = $readiness['ok'] && $dryRun && $boltConfig && $edxeixConfig && $hasMappedDriver && $hasMappedVehicle && $cleanLab && $cleanQueue && $noLiveAttempts;
$candidateCount = count($candidates);
$realCandidateReady = $readyForFutureTest && $candidateCount > 0;

$state = [
    'readiness_loaded' => $readiness['ok'],
    'ready_for_future_test' => $readyForFutureTest,
    'candidate_count' => $candidateCount,
];

[$decision, $decisionText, $decisionType] = pra_human_decision($state);

$blockerCounts = [];
foreach ($rows as $row) {
    foreach (($row['blockers'] ?? []) as $blocker) {
        $blocker = (string)$blocker;
        $blockerCounts[$blocker] = ($blockerCounts[$blocker] ?? 0) + 1;
    }
}
ksort($blockerCounts);

$payload = [
    'ok' => $readiness['ok'],
    'script' => 'ops/preflight-review.php',
    'generated_at' => date('c'),
    'safety_contract' => [
        'calls_bolt' => false,
        'calls_edxeix' => false,
        'stages_jobs' => false,
        'updates_mappings' => false,
        'writes_database' => false,
        'live_edxeix_submission' => 'disabled_not_used',
        'purpose' => 'operator read-only preflight explanation',
    ],
    'decision' => [
        'code' => $decision,
        'type' => $decisionType,
        'text' => $decisionText,
        'live_submission_authorized' => false,
        'preflight_review_allowed' => $realCandidateReady,
    ],
    'readiness' => [
        'loaded' => $readiness['ok'],
        'error' => $readiness['error'],
        'verdict' => $audit['verdict'] ?? null,
        'ready_for_future_test' => $readyForFutureTest,
        'real_candidate_ready' => $realCandidateReady,
        'candidate_count' => $candidateCount,
        'dry_run_enabled' => $dryRun,
        'bolt_config_present' => $boltConfig,
        'edxeix_config_present' => $edxeixConfig,
        'mapped_drivers' => $drivers,
        'mapped_vehicles' => $vehicles,
        'clean_lab' => $cleanLab,
        'clean_queue' => $cleanQueue,
        'no_live_attempts' => $noLiveAttempts,
    ],
    'preflight_summary' => [
        'rows_analyzed' => count($rows),
        'candidate_count' => $candidateCount,
        'mapping_ready_rows' => (int)($recent['mapping_ready_rows'] ?? 0),
        'future_guard_passed_rows' => (int)($recent['future_guard_passed_rows'] ?? 0),
        'terminal_rows' => (int)($recent['terminal_rows'] ?? 0),
        'blocked_rows' => (int)($recent['blocked_rows'] ?? 0),
        'blocker_counts' => $blockerCounts,
    ],
    'candidates' => array_values($candidates),
    'recent_rows' => array_slice($rows, 0, min($limit, 60)),
    'links' => [
        'html' => '/ops/preflight-review.php',
        'json' => '/ops/preflight-review.php?format=json',
        'test_session' => '/ops/test-session.php',
        'dev_accelerator' => '/ops/dev-accelerator.php',
        'evidence_bundle' => '/ops/evidence-bundle.php',
        'evidence_report_markdown' => '/ops/evidence-report.php?format=md',
        'readiness' => '/ops/readiness.php',
        'preflight_json' => '/bolt_edxeix_preflight.php?limit=30',
        'jobs' => '/ops/jobs.php',
    ],
    'note' => 'Read-only operator explanation. Use /bolt_edxeix_preflight.php?limit=30 for raw preflight JSON. Do not submit live.',
];

if ($format === 'json') {
    pra_json_response($payload, $payload['ok'] ? 200 : 500);
}

$heroClass = $decisionType;
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Preflight Review Assistant | gov.cabnet.app</title>
    <style>
        :root{--bg:#f3f6fb;--panel:#fff;--ink:#07152f;--muted:#475569;--line:#d7e1ef;--nav:#081225;--blue:#2563eb;--green:#07875a;--orange:#b85c00;--red:#b42318;--slate:#334155;--soft:#f8fbff}*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--ink);font-family:Arial,Helvetica,sans-serif}.nav{background:var(--nav);color:#fff;min-height:56px;display:flex;align-items:center;gap:18px;padding:0 26px;position:sticky;top:0;z-index:5;overflow:auto}.nav strong{white-space:nowrap}.nav a{color:#fff;text-decoration:none;font-size:15px;white-space:nowrap;opacity:.92}.nav a:hover{opacity:1;text-decoration:underline}.wrap{width:min(1500px,calc(100% - 48px));margin:26px auto 60px}.card{background:var(--panel);border:1px solid var(--line);border-radius:14px;padding:18px;margin-bottom:18px;box-shadow:0 10px 26px rgba(8,18,37,.04)}h1{font-size:34px;margin:0 0 12px}h2{font-size:23px;margin:0 0 14px}h3{font-size:18px;margin:0 0 10px}p{color:var(--muted);line-height:1.45}.safety{background:#ecfdf3;border:1px solid #bbf7d0;border-left:7px solid var(--green);border-radius:14px;padding:16px;margin-bottom:18px}.safety strong{color:#166534}.hero{border-left:7px solid var(--orange)}.hero.good{border-left-color:var(--green)}.hero.bad{border-left-color:var(--red)}.hero.neutral{border-left-color:var(--slate)}.grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}.two{display:grid;grid-template-columns:1fr 1fr;gap:18px}.three{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}.metric{border:1px solid var(--line);border-radius:10px;padding:14px;background:var(--soft);min-height:82px}.metric strong{display:block;font-size:30px;line-height:1.05;word-break:break-word}.metric span{color:var(--muted);font-size:14px}.badge{display:inline-block;padding:5px 9px;border-radius:999px;font-size:12px;font-weight:700;margin:1px 3px 1px 0;white-space:nowrap}.badge-good{background:#dcfce7;color:#166534}.badge-warn{background:#fff7ed;color:#b45309}.badge-bad{background:#fee2e2;color:#991b1b}.badge-neutral{background:#eaf1ff;color:#1e40af}.actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:14px}.btn{display:inline-block;padding:10px 14px;border-radius:8px;color:#fff;text-decoration:none;font-weight:700;background:var(--blue);border:0;cursor:pointer;font-size:14px}.btn.good{background:var(--green)}.btn.warn{background:var(--orange)}.btn.dark{background:var(--slate)}.table-wrap{overflow:auto;border:1px solid var(--line);border-radius:10px}table{width:100%;border-collapse:collapse;min-width:980px;background:#fff}th,td{border-bottom:1px solid var(--line);padding:10px;text-align:left;vertical-align:top;font-size:14px}th{background:#f1f5f9;color:#334155}.check-card{border:1px solid var(--line);border-radius:12px;background:#fff;padding:14px;min-height:118px}.check-card strong{display:block;margin-bottom:8px}.small{font-size:13px;color:var(--muted)}.mono{font-family:Consolas,Menlo,monospace;font-size:12px;word-break:break-all}code{background:#eef2ff;padding:2px 5px;border-radius:5px}.goodline{color:#166534}.warnline{color:#b45309}.badline{color:#991b1b}.list{margin:0;padding-left:18px;color:var(--muted)}.list li{margin:7px 0}@media(max-width:1150px){.grid,.three,.two{grid-template-columns:1fr 1fr}}@media(max-width:760px){.grid,.three,.two{grid-template-columns:1fr}.wrap{width:calc(100% - 24px);margin-top:14px}.nav{padding:0 14px}}
    </style>
    <link rel="stylesheet" href="/assets/css/gov-ops-edxeix.css?v=1.7">
</head>
<body>
<nav class="nav">
    <strong>GC gov.cabnet.app</strong>
    <a href="/ops/index.php">Operations Console</a>
    <a href="/ops/test-session.php">Test Session</a>
    <a href="/ops/preflight-review.php">Preflight Review</a>
    <a href="/ops/dev-accelerator.php">Dev Accelerator</a>
    <a href="/ops/evidence-bundle.php">Evidence Bundle</a>
    <a href="/ops/evidence-report.php">Evidence Report</a>
    <a href="/ops/readiness.php">Readiness</a>
    <a href="/ops/future-test.php">Future Test</a>
    <a href="/ops/mappings.php">Mappings</a>
    <a href="/ops/jobs.php">Jobs</a>
</nav>

<main class="wrap">
    <section class="safety">
        <strong>READ-ONLY PREFLIGHT REVIEW.</strong>
        This page explains preflight status only. It does not call Bolt, does not call EDXEIX, does not stage jobs, and does not enable live submission.
    </section>

    <section class="card hero <?= pra_h($heroClass) ?>">
        <h1>Bolt Preflight Review Assistant</h1>
        <p><?= pra_h($decisionText) ?></p>
        <div>
            <?= pra_badge($decision, $decisionType) ?>
            <?= pra_badge('LIVE SUBMIT OFF', 'good') ?>
            <?= pra_badge('NO EDXEIX HTTP CALL', 'good') ?>
            <?= pra_badge('NO JOB STAGING', 'good') ?>
        </div>
        <div class="grid" style="margin-top:14px">
            <div class="metric"><strong><?= pra_h((string)($audit['verdict'] ?? 'UNKNOWN')) ?></strong><span>Readiness verdict</span></div>
            <div class="metric"><strong><?= pra_h((string)$candidateCount) ?></strong><span>Real future candidates</span></div>
            <div class="metric"><strong><?= pra_h((string)($recent['mapping_ready_rows'] ?? 0)) ?></strong><span>Mapping-ready rows</span></div>
            <div class="metric"><strong><?= pra_h((string)($recent['blocked_rows'] ?? 0)) ?></strong><span>Blocked analyzed rows</span></div>
        </div>
        <div class="actions">
            <a class="btn" href="/ops/preflight-review.php?format=json">Open JSON</a>
            <a class="btn good" href="/ops/test-session.php">Test Session</a>
            <a class="btn dark" href="/ops/evidence-report.php?format=md">Evidence Markdown</a>
            <a class="btn warn" href="/bolt_edxeix_preflight.php?limit=30">Raw Preflight JSON</a>
        </div>
    </section>

    <?php if (!$readiness['ok']): ?>
        <section class="card">
            <h2>Readiness load error</h2>
            <p class="badline"><strong><?= pra_h((string)$readiness['error']) ?></strong></p>
        </section>
    <?php endif; ?>

    <section class="card">
        <h2>Operator decision checks</h2>
        <div class="three">
            <div class="check-card">
                <strong>1. System clean</strong>
                <?= pra_bool_badge($readyForFutureTest, 'READY', 'CHECK') ?>
                <p class="small">Dry-run, configs, mappings, LAB cleanup, queue, and live-attempt safety.</p>
            </div>
            <div class="check-card">
                <strong>2. Real future candidate</strong>
                <?= pra_bool_badge($realCandidateReady, 'FOUND', 'WAITING') ?>
                <p class="small">Candidate must be real Bolt, mapped, non-terminal, non-lab, and future-safe.</p>
            </div>
            <div class="check-card">
                <strong>3. Live submission</strong>
                <?= pra_badge('NOT AUTHORIZED', 'good') ?>
                <p class="small">This project remains preflight/dry-run only until explicitly changed later.</p>
            </div>
        </div>
    </section>

    <section class="two">
        <div class="card">
            <h2>Readiness passport</h2>
            <p>Dry-run enabled <?= pra_bool_badge($dryRun) ?></p>
            <p>Bolt config present <?= pra_bool_badge($boltConfig) ?></p>
            <p>EDXEIX config present <?= pra_bool_badge($edxeixConfig) ?></p>
            <p>Drivers mapped <strong><?= pra_h((string)($drivers['mapped'] ?? 0)) ?>/<?= pra_h((string)($drivers['total'] ?? 0)) ?></strong></p>
            <p>Vehicles mapped <strong><?= pra_h((string)($vehicles['mapped'] ?? 0)) ?>/<?= pra_h((string)($vehicles['total'] ?? 0)) ?></strong></p>
            <p>Clean LAB state <?= pra_bool_badge($cleanLab) ?></p>
            <p>Clean queue <?= pra_bool_badge($cleanQueue) ?></p>
            <p>No live attempts <?= pra_bool_badge($noLiveAttempts) ?></p>
        </div>
        <div class="card">
            <h2>Blocker summary</h2>
            <?php if (!$blockerCounts): ?>
                <p class="goodline"><strong>No blocker counts are present in the analyzed recent rows.</strong></p>
            <?php else: ?>
                <ul class="list">
                    <?php foreach ($blockerCounts as $blocker => $count): ?>
                        <li><?= pra_badge((string)$count, pra_blocker_type((string)$blocker)) ?> <strong><?= pra_h((string)$blocker) ?>:</strong> <?= pra_h(pra_blocker_label((string)$blocker)) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <p class="small">A blocker does not mean the system is broken. It often means the row is historical, terminal, not future-safe, or intentionally unmapped.</p>
        </div>
    </section>

    <section class="card">
        <h2>Preflight candidates</h2>
        <?php if (!$candidates): ?>
            <p class="warnline"><strong>No candidate is ready yet.</strong></p>
            <p>Create or wait for one real Bolt ride with a mapped driver and mapped vehicle at least the configured future guard window ahead. Then capture the ride stages from the Test Session page.</p>
        <?php else: ?>
            <p class="goodline"><strong><?= pra_h((string)count($candidates)) ?> candidate(s) are ready for preflight-only review.</strong></p>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>ID</th><th>Order Ref</th><th>Status</th><th>Started</th><th>Driver</th><th>Plate</th><th>Mapping</th><th>Future Guard</th><th>Decision</th></tr></thead>
                    <tbody>
                    <?php foreach ($candidates as $row): ?>
                        <tr>
                            <td class="mono"><?= pra_h(pra_value($row, 'id')) ?></td>
                            <td class="mono"><?= pra_h(pra_value($row, 'order_reference')) ?></td>
                            <td><?= pra_badge(pra_value($row, 'status', 'UNKNOWN'), pra_status_type(pra_value($row, 'status'))) ?></td>
                            <td><?= pra_h(pra_value($row, 'started_at')) ?></td>
                            <td><?= pra_h(pra_value($row, 'driver_name')) ?></td>
                            <td><?= pra_h(pra_value($row, 'plate')) ?></td>
                            <td><?= pra_bool_badge(!empty($row['mapping_ready']), 'READY', 'BLOCKED') ?></td>
                            <td><?= pra_bool_badge(!empty($row['future_guard_passed']), 'PASS', 'BLOCKED') ?></td>
                            <td><?= pra_badge('PREFLIGHT ONLY', 'good') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="card">
        <h2>Recent analyzed rows</h2>
        <?php if (!$rows): ?>
            <p>No recent normalized booking rows were available for analysis.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>ID</th><th>Source</th><th>Order Ref</th><th>Status</th><th>Started</th><th>Driver</th><th>Plate</th><th>Mapping</th><th>Future</th><th>Terminal</th><th>Safe</th><th>Blockers</th></tr></thead>
                    <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td class="mono"><?= pra_h(pra_value($row, 'id')) ?></td>
                            <td><?= pra_h(pra_value($row, 'source_system')) ?></td>
                            <td class="mono"><?= pra_h(pra_value($row, 'order_reference')) ?></td>
                            <td><?= pra_badge(pra_value($row, 'status', 'UNKNOWN'), pra_status_type(pra_value($row, 'status'))) ?></td>
                            <td><?= pra_h(pra_value($row, 'started_at')) ?></td>
                            <td><?= pra_h(pra_value($row, 'driver_name')) ?></td>
                            <td><?= pra_h(pra_value($row, 'plate')) ?></td>
                            <td><?= pra_bool_badge(!empty($row['mapping_ready']), 'YES', 'NO') ?></td>
                            <td><?= pra_bool_badge(!empty($row['future_guard_passed']), 'PASS', 'NO') ?></td>
                            <td><?= !empty($row['terminal_status']) ? pra_badge('YES', 'warn') : pra_badge('NO', 'good') ?></td>
                            <td><?= pra_bool_badge(!empty($row['submission_safe']), 'YES', 'NO') ?></td>
                            <td>
                                <?php foreach (($row['blockers'] ?? []) as $blocker): ?>
                                    <?= pra_badge((string)$blocker, pra_blocker_type((string)$blocker)) ?>
                                <?php endforeach; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="card">
        <h2>Recommended next action</h2>
        <?php if (!$readyForFutureTest): ?>
            <p class="badline"><strong>Open Readiness and resolve setup blockers first.</strong></p>
            <div class="actions"><a class="btn" href="/ops/readiness.php">Open Readiness</a></div>
        <?php elseif (!$realCandidateReady): ?>
            <p class="warnline"><strong>Wait for a real future Bolt ride.</strong></p>
            <p>Use <code>/ops/test-session.php</code> during the ride, then return here after captures are recorded.</p>
            <div class="actions"><a class="btn good" href="/ops/test-session.php">Open Test Session</a><a class="btn dark" href="/ops/dev-accelerator.php">Open Dev Accelerator</a></div>
        <?php else: ?>
            <p class="goodline"><strong>Review the raw preflight JSON only. Do not submit live.</strong></p>
            <div class="actions"><a class="btn warn" href="/bolt_edxeix_preflight.php?limit=30">Open Raw Preflight JSON</a><a class="btn dark" href="/ops/evidence-report.php?format=md">Open Evidence Markdown</a></div>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
