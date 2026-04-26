<?php
/**
 * gov.cabnet.app — Bolt Real Test Session Control
 *
 * Purpose:
 * - Speed up the next real future Bolt test by putting the full safe workflow
 *   on one operator page.
 * - Avoid broad nav rewrites across many already-working ops pages.
 *
 * Safety contract:
 * - Does not call Bolt.
 * - Does not call EDXEIX.
 * - Does not stage jobs.
 * - Does not update mappings.
 * - Does not write database rows or files.
 * - Reads readiness audit state only.
 */

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Robots-Tag: noindex,nofollow', true);

function tsc_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function tsc_param(string $key, string $default = ''): string
{
    $value = $_GET[$key] ?? $_POST[$key] ?? $default;
    if (is_array($value)) {
        return $default;
    }
    return trim((string)$value);
}

function tsc_badge(string $text, string $type = 'neutral'): string
{
    return '<span class="badge badge-' . tsc_h($type) . '">' . tsc_h($text) . '</span>';
}

function tsc_bool_badge(bool $value, string $yes = 'YES', string $no = 'NO'): string
{
    return tsc_badge($value ? $yes : $no, $value ? 'good' : 'bad');
}

function tsc_metric($value, string $label): string
{
    return '<div class="metric"><strong>' . tsc_h((string)$value) . '</strong><span>' . tsc_h($label) . '</span></div>';
}

function tsc_json_response(array $payload, int $statusCode = 200): void
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

function tsc_load_readiness(): array
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
            throw new RuntimeException('bolt_readiness_audit.php was not found or is not readable.');
        }
        require_once $path;
        if (!function_exists('gov_readiness_build_audit')) {
            throw new RuntimeException('gov_readiness_build_audit() is unavailable after loading readiness audit.');
        }
        $out['audit'] = gov_readiness_build_audit(['limit' => 60, 'analysis_limit' => 350]);
        $out['ok'] = true;
    } catch (Throwable $e) {
        $out['error'] = $e->getMessage();
    }

    return $out;
}

function tsc_candidate_rows(array $rows): array
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

function tsc_stage_url(string $stage): string
{
    $labels = [
        'accepted-assigned' => 'dev-accelerator-accepted-assigned',
        'pickup-waiting' => 'dev-accelerator-pickup-waiting',
        'trip-started' => 'dev-accelerator-trip-started',
        'completed' => 'dev-accelerator-completed',
    ];

    return '/ops/dev-accelerator.php?' . http_build_query([
        'probe' => '1',
        'record' => '1',
        'stage' => $stage,
        'label' => $labels[$stage] ?? ('dev-accelerator-' . $stage),
        'watch_driver_uuid' => '57256761-d21b-4940-a3ca-bdcec5ef6af1',
        'watch_vehicle_plate' => 'EMX6874',
        'hours_back' => '24',
        'sample_limit' => '20',
    ]);
}

function tsc_step_card(int $n, string $title, string $status, string $body, string $href, string $button, string $secondaryHref = '', string $secondaryButton = ''): string
{
    $html = '<article class="step step-' . tsc_h($status) . '">';
    $html .= '<div class="step-head"><span class="step-no">' . $n . '</span><div><h3>' . tsc_h($title) . '</h3>';
    $html .= tsc_badge(strtoupper(str_replace('-', ' ', $status)), $status === 'ready' || $status === 'safe' ? 'good' : ($status === 'waiting' ? 'warn' : 'neutral'));
    $html .= '</div></div>';
    $html .= '<p>' . tsc_h($body) . '</p>';
    $html .= '<div class="actions"><a class="btn" href="' . tsc_h($href) . '">' . tsc_h($button) . '</a>';
    if ($secondaryHref !== '' && $secondaryButton !== '') {
        $html .= '<a class="btn dark" href="' . tsc_h($secondaryHref) . '">' . tsc_h($secondaryButton) . '</a>';
    }
    $html .= '</div></article>';
    return $html;
}

$format = strtolower(tsc_param('format', 'html'));
$readiness = tsc_load_readiness();
$audit = is_array($readiness['audit'] ?? null) ? $readiness['audit'] : [];
$config = is_array($audit['config_state'] ?? null) ? $audit['config_state'] : [];
$drivers = is_array($audit['reference_counts']['drivers'] ?? null) ? $audit['reference_counts']['drivers'] : ['mapped' => 0, 'total' => 0, 'unmapped' => 0];
$vehicles = is_array($audit['reference_counts']['vehicles'] ?? null) ? $audit['reference_counts']['vehicles'] : ['mapped' => 0, 'total' => 0, 'unmapped' => 0];
$recent = is_array($audit['recent_bookings'] ?? null) ? $audit['recent_bookings'] : ['rows' => [], 'submission_safe_rows' => 0];
$queue = is_array($audit['queue_safety'] ?? null) ? $audit['queue_safety'] : [];
$lab = is_array($audit['lab_safety'] ?? null) ? $audit['lab_safety'] : [];
$attempts = is_array($audit['submission_attempt_safety'] ?? null) ? $audit['submission_attempt_safety'] : [];
$candidates = tsc_candidate_rows(is_array($recent['rows'] ?? null) ? $recent['rows'] : []);

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

$watchUrl = '/ops/bolt-api-visibility.php?' . http_build_query([
    'run' => '1',
    'record' => '1',
    'hours_back' => '24',
    'sample_limit' => '20',
    'watch_driver_uuid' => '57256761-d21b-4940-a3ca-bdcec5ef6af1',
    'watch_vehicle_plate' => 'EMX6874',
    'label' => 'test-session-watch',
    'refresh' => '20',
]);

$payload = [
    'ok' => $readiness['ok'],
    'script' => 'ops/test-session.php',
    'generated_at' => date('c'),
    'safety_contract' => [
        'calls_bolt' => false,
        'calls_edxeix' => false,
        'stages_jobs' => false,
        'updates_mappings' => false,
        'writes_database' => false,
        'live_edxeix_submission' => 'disabled_not_used',
        'purpose' => 'operator navigation and test workflow control only',
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
        'dry_run_enabled' => $dryRun,
        'bolt_config_present' => $boltConfig,
        'edxeix_config_present' => $edxeixConfig,
        'clean_lab' => $cleanLab,
        'clean_queue' => $cleanQueue,
        'no_live_attempts' => $noLiveAttempts,
    ],
    'recommended_flow' => [
        'open_test_session_control',
        'confirm_ready_for_future_test',
        'create_real_bolt_ride_40_to_60_minutes_future',
        'capture_accepted_assigned',
        'capture_pickup_waiting',
        'capture_trip_started',
        'capture_completed',
        'open_evidence_bundle',
        'export_markdown_report',
        'review_preflight_json_only',
        'stop_before_live_submission',
    ],
    'links' => [
        'html' => '/ops/test-session.php',
        'json' => '/ops/test-session.php?format=json',
        'dev_accelerator' => '/ops/dev-accelerator.php',
        'evidence_bundle' => '/ops/evidence-bundle.php',
        'evidence_report' => '/ops/evidence-report.php',
        'evidence_report_markdown' => '/ops/evidence-report.php?format=md',
        'readiness' => '/ops/readiness.php',
        'future_test' => '/ops/future-test.php',
        'mappings' => '/ops/mappings.php',
        'jobs' => '/ops/jobs.php',
        'bolt_visibility' => '/ops/bolt-api-visibility.php',
        'watch_auto_refresh' => $watchUrl,
        'preflight_json' => '/bolt_edxeix_preflight.php?limit=30',
        'capture_urls' => [
            'accepted_assigned' => tsc_stage_url('accepted-assigned'),
            'pickup_waiting' => tsc_stage_url('pickup-waiting'),
            'trip_started' => tsc_stage_url('trip-started'),
            'completed' => tsc_stage_url('completed'),
        ],
    ],
];

if ($format === 'json') {
    tsc_json_response($payload, $payload['ok'] ? 200 : 500);
}

$heroType = $realCandidateReady ? 'good' : ($readyForFutureTest ? 'warn' : 'bad');
$heroText = 'System needs attention before the next real future Bolt test.';
if ($realCandidateReady) {
    $heroText = 'A real future candidate appears ready for preflight-only review. Live submit remains blocked.';
} elseif ($readyForFutureTest) {
    $heroText = 'System is clean. Waiting for a real Bolt ride at least the configured future guard window ahead.';
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Bolt Test Session Control | gov.cabnet.app</title>
    <style>
        :root{--bg:#f3f6fb;--panel:#fff;--ink:#07152f;--muted:#475569;--line:#d7e1ef;--nav:#081225;--blue:#2563eb;--green:#07875a;--orange:#b85c00;--red:#b42318;--slate:#334155;--soft:#f8fbff}*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--ink);font-family:Arial,Helvetica,sans-serif}.nav{background:var(--nav);color:#fff;min-height:56px;display:flex;align-items:center;gap:18px;padding:0 26px;position:sticky;top:0;z-index:5;overflow:auto}.nav strong{white-space:nowrap}.nav a{color:#fff;text-decoration:none;font-size:15px;white-space:nowrap;opacity:.92}.nav a:hover{opacity:1;text-decoration:underline}.wrap{width:min(1500px,calc(100% - 48px));margin:26px auto 60px}.card{background:var(--panel);border:1px solid var(--line);border-radius:14px;padding:18px;margin-bottom:18px;box-shadow:0 10px 26px rgba(8,18,37,.04)}h1{font-size:34px;margin:0 0 12px}h2{font-size:23px;margin:0 0 14px}h3{font-size:18px;margin:0 0 8px}p{color:var(--muted);line-height:1.45}.safety{background:#ecfdf3;border:1px solid #bbf7d0;border-left:7px solid var(--green);border-radius:14px;padding:16px;margin-bottom:18px}.safety strong{color:#166534}.hero{border-left:7px solid var(--orange)}.hero.good{border-left-color:var(--green)}.hero.bad{border-left-color:var(--red)}.grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}.two{display:grid;grid-template-columns:1fr 1fr;gap:18px}.workflow{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}.metric{border:1px solid var(--line);border-radius:10px;padding:14px;background:var(--soft);min-height:82px}.metric strong{display:block;font-size:30px;line-height:1.05;word-break:break-word}.metric span{color:var(--muted);font-size:14px}.badge{display:inline-block;padding:5px 9px;border-radius:999px;font-size:12px;font-weight:700;margin:1px 3px 1px 0;white-space:nowrap}.badge-good{background:#dcfce7;color:#166534}.badge-warn{background:#fff7ed;color:#b45309}.badge-bad{background:#fee2e2;color:#991b1b}.badge-neutral{background:#eaf1ff;color:#1e40af}.actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:14px}.btn{display:inline-block;padding:10px 14px;border-radius:8px;color:#fff;text-decoration:none;font-weight:700;background:var(--blue);border:0;cursor:pointer;font-size:14px}.btn.good{background:var(--green)}.btn.warn{background:var(--orange)}.btn.dark{background:var(--slate)}.step{border:1px solid var(--line);border-radius:14px;background:#fff;padding:16px;min-height:245px;border-top:5px solid var(--blue);display:flex;flex-direction:column;justify-content:space-between}.step-ready,.step-safe{border-top-color:var(--green)}.step-waiting{border-top-color:var(--orange)}.step-head{display:flex;gap:12px;align-items:flex-start}.step-no{width:34px;height:34px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;background:#eaf1ff;color:#1e40af;font-weight:800;flex:0 0 34px}.timeline{counter-reset:t;list-style:none;margin:0;padding:0}.timeline li{counter-increment:t;border:1px solid var(--line);border-radius:10px;background:#f8fbff;margin:8px 0;padding:10px 12px 10px 48px;position:relative;color:var(--muted)}.timeline li:before{content:counter(t);position:absolute;left:12px;top:9px;width:26px;height:26px;border-radius:50%;display:flex;align-items:center;justify-content:center;background:#eaf1ff;color:#1e40af;font-weight:800}.table-wrap{overflow:auto;border:1px solid var(--line);border-radius:10px}table{width:100%;border-collapse:collapse;min-width:760px;background:#fff}th,td{border-bottom:1px solid var(--line);padding:10px;text-align:left;vertical-align:top;font-size:14px}th{background:#f1f5f9;color:#334155}.small{font-size:13px;color:var(--muted)}code{background:#eef2ff;padding:2px 5px;border-radius:5px}.goodline{color:#166534}.warnline{color:#b45309}.badline{color:#991b1b}@media(max-width:1200px){.workflow{grid-template-columns:repeat(2,minmax(0,1fr))}.grid,.two{grid-template-columns:1fr 1fr}}@media(max-width:760px){.workflow,.grid,.two{grid-template-columns:1fr}.wrap{width:calc(100% - 24px);margin-top:14px}.nav{padding:0 14px}}
    </style>
    <link rel="stylesheet" href="/assets/css/gov-ops-edxeix.css?v=1.7">
</head>
<body>
<nav class="nav">
    <strong>GC gov.cabnet.app</strong>
    <a href="/ops/index.php">Operations Console</a>
    <a href="/ops/test-session.php">Test Session</a>
    <a href="/ops/dev-accelerator.php">Dev Accelerator</a>
    <a href="/ops/evidence-bundle.php">Evidence Bundle</a>
    <a href="/ops/evidence-report.php">Evidence Report</a>
    <a href="/ops/readiness.php">Readiness</a>
    <a href="/ops/future-test.php">Future Test</a>
    <a href="/ops/mappings.php">Mappings</a>
    <a href="/ops/jobs.php">Jobs</a>
    <a href="/ops/bolt-api-visibility.php">Bolt Visibility</a>
</nav>

<main class="wrap">
    <section class="safety">
        <strong>READ-ONLY TEST SESSION CONTROL.</strong>
        This page is a workflow launcher only. It does not call Bolt, does not call EDXEIX, does not stage jobs, and does not write data.
    </section>

    <section class="card hero <?= tsc_h($heroType) ?>">
        <h1>Bolt Real Test Session Control</h1>
        <p><?= tsc_h($heroText) ?></p>
        <div>
            <?= tsc_badge('LIVE SUBMIT OFF', 'good') ?>
            <?= tsc_badge('NO JOB STAGING', 'good') ?>
            <?= tsc_badge('NO BOLT CALL HERE', 'good') ?>
            <?= tsc_badge('NO EDXEIX CALL HERE', 'good') ?>
        </div>
        <div class="grid" style="margin-top:14px">
            <?= tsc_metric($audit['verdict'] ?? 'UNKNOWN', 'Readiness verdict') ?>
            <?= tsc_metric(($drivers['mapped'] ?? 0) . '/' . ($drivers['total'] ?? 0), 'Drivers mapped') ?>
            <?= tsc_metric(($vehicles['mapped'] ?? 0) . '/' . ($vehicles['total'] ?? 0), 'Vehicles mapped') ?>
            <?= tsc_metric((string)count($candidates), 'Real future candidates') ?>
        </div>
        <div class="actions">
            <a class="btn" href="/ops/test-session.php?format=json">Open JSON</a>
            <a class="btn good" href="/ops/dev-accelerator.php">Open Dev Accelerator</a>
            <a class="btn dark" href="/ops/evidence-report.php?format=md">Open Markdown Report</a>
            <a class="btn warn" href="/bolt_edxeix_preflight.php?limit=30">Open Preflight JSON</a>
        </div>
    </section>

    <?php if (!$readiness['ok']): ?>
        <section class="card">
            <h2>Readiness load problem</h2>
            <p class="badline"><strong><?= tsc_h((string)$readiness['error']) ?></strong></p>
        </section>
    <?php endif; ?>

    <section class="card">
        <h2>One-page real ride workflow</h2>
        <div class="workflow">
            <?= tsc_step_card(1, 'Confirm readiness', $readyForFutureTest ? 'ready' : 'waiting', $readyForFutureTest ? 'System is clean for a real future test ride.' : 'Open readiness and resolve any blocker before testing.', '/ops/readiness.php', 'Open Readiness', '/ops/future-test.php', 'Future Test') ?>
            <?= tsc_step_card(2, 'Capture live stages', 'waiting', 'During the real ride, use Dev Accelerator buttons to record accepted, pickup, started, and completed snapshots.', '/ops/dev-accelerator.php', 'Open Dev Accelerator', $watchUrl, 'Auto-watch 20s') ?>
            <?= tsc_step_card(3, 'Review evidence', 'waiting', 'After snapshots are recorded, open the Evidence Bundle to confirm what was seen.', '/ops/evidence-bundle.php', 'Open Evidence Bundle', '/ops/evidence-report.php', 'Evidence Report') ?>
            <?= tsc_step_card(4, 'Export report', 'safe', 'Copy the Markdown report and paste it into chat for the next implementation decision.', '/ops/evidence-report.php?format=md', 'Open Markdown', '/ops/evidence-report.php?format=json', 'Report JSON') ?>
        </div>
    </section>

    <section class="two">
        <div class="card">
            <h2>Fast capture links</h2>
            <p class="small">These links run the existing Dev Accelerator dry-run probe and record sanitized snapshots. They are the only links on this page that intentionally move you to a page that can call Bolt in dry-run mode.</p>
            <div class="actions">
                <a class="btn good" href="<?= tsc_h(tsc_stage_url('accepted-assigned')) ?>">Capture Accepted / Assigned</a>
                <a class="btn warn" href="<?= tsc_h(tsc_stage_url('pickup-waiting')) ?>">Capture Pickup / Waiting</a>
                <a class="btn" href="<?= tsc_h(tsc_stage_url('trip-started')) ?>">Capture Trip Started</a>
                <a class="btn dark" href="<?= tsc_h(tsc_stage_url('completed')) ?>">Capture Completed</a>
                <a class="btn" href="<?= tsc_h($watchUrl) ?>">Auto-watch every 20s</a>
            </div>
        </div>

        <div class="card">
            <h2>Readiness passport</h2>
            <p>Ready for future test <?= tsc_bool_badge($readyForFutureTest) ?></p>
            <p>Real candidate ready <?= tsc_bool_badge($realCandidateReady) ?></p>
            <p>Dry-run enabled <?= tsc_bool_badge($dryRun) ?></p>
            <p>Bolt config present <?= tsc_bool_badge($boltConfig) ?></p>
            <p>EDXEIX config present <?= tsc_bool_badge($edxeixConfig) ?></p>
            <p>No LAB rows/jobs <?= tsc_bool_badge($cleanLab) ?></p>
            <p>No local queue jobs <?= tsc_bool_badge($cleanQueue) ?></p>
            <p>No live attempts <?= tsc_bool_badge($noLiveAttempts) ?></p>
        </div>
    </section>

    <section class="card">
        <h2>Recommended sequence during the real test</h2>
        <ol class="timeline">
            <li>Open this page and confirm the verdict is <strong>READY_FOR_REAL_BOLT_FUTURE_TEST</strong>.</li>
            <li>Create one real Bolt ride 40–60 minutes in the future, ideally Filippos + EMX6874.</li>
            <li>Click <strong>Capture Accepted / Assigned</strong> after the ride is accepted/assigned.</li>
            <li>Click <strong>Capture Pickup / Waiting</strong> when the driver arrives or passenger is waiting/picked up.</li>
            <li>Click <strong>Capture Trip Started</strong> when the ride is in progress.</li>
            <li>Click <strong>Capture Completed</strong> after the ride is completed.</li>
            <li>Open <strong>Evidence Bundle</strong> to inspect the stage coverage.</li>
            <li>Open <strong>Evidence Report Markdown</strong>, copy it, and paste it into chat.</li>
            <li>Only then review <strong>Preflight JSON</strong>. Do not live submit.</li>
        </ol>
    </section>

    <section class="card">
        <h2>Key links</h2>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Purpose</th><th>URL</th><th>Safety note</th></tr></thead>
                <tbody>
                <tr><td>Test Session Control</td><td><code>/ops/test-session.php</code></td><td>Read-only workflow launcher.</td></tr>
                <tr><td>Dev Accelerator</td><td><code>/ops/dev-accelerator.php</code></td><td>Default read-only; capture buttons run dry-run Bolt visibility probes.</td></tr>
                <tr><td>Evidence Bundle</td><td><code>/ops/evidence-bundle.php</code></td><td>Reads sanitized timeline only.</td></tr>
                <tr><td>Evidence Report</td><td><code>/ops/evidence-report.php?format=md</code></td><td>Copy/paste Markdown report.</td></tr>
                <tr><td>Bolt Visibility</td><td><code>/ops/bolt-api-visibility.php</code></td><td>Dry-run diagnostic only when run buttons are used.</td></tr>
                <tr><td>Preflight JSON</td><td><code>/bolt_edxeix_preflight.php?limit=30</code></td><td>Preview only. No live submit.</td></tr>
                </tbody>
            </table>
        </div>
    </section>
</main>
</body>
</html>
