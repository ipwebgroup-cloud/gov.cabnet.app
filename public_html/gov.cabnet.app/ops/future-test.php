<?php
/**
 * gov.cabnet.app — Real Future Bolt Test Checklist
 *
 * Read-only operations page for verifying whether the bridge is ready for the
 * next real Bolt future-ride preflight test. This page does not call Bolt,
 * does not call EDXEIX, does not create jobs, and does not modify database rows.
 */

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Robots-Tag: noindex, nofollow', true);

require_once dirname(__DIR__) . '/bolt_readiness_audit.php';

function ft_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function ft_badge(string $text, string $type = 'neutral'): string
{
    return '<span class="badge badge-' . ft_h($type) . '">' . ft_h($text) . '</span>';
}

function ft_bool_badge(bool $value, string $yes = 'pass', string $no = 'blocked'): string
{
    return $value ? ft_badge($yes, 'good') : ft_badge($no, 'bad');
}

function ft_warn_badge(bool $value, string $yes = 'pass', string $no = 'waiting'): string
{
    return $value ? ft_badge($yes, 'good') : ft_badge($no, 'warn');
}

function ft_value(array $row, array $keys, $default = '')
{
    foreach ($keys as $key) {
        if (array_key_exists($key, $row) && $row[$key] !== null && $row[$key] !== '') {
            return $row[$key];
        }
    }
    return $default;
}

function ft_is_lab_row(array $row): bool
{
    $source = strtolower((string)ft_value($row, ['source_system', 'source_type', 'source'], ''));
    $ref = strtoupper((string)ft_value($row, ['order_reference', 'external_order_id', 'external_reference', 'source_trip_id', 'source_trip_reference'], ''));
    return strpos($source, 'lab') !== false || strpos($ref, 'LAB-') === 0;
}

function ft_is_real_bolt_row(array $row): bool
{
    $source = strtolower((string)ft_value($row, ['source_system', 'source_type', 'source'], ''));
    return strpos($source, 'bolt') !== false && !ft_is_lab_row($row);
}

function ft_candidate_rows(array $recentRows): array
{
    $candidates = [];
    foreach ($recentRows as $row) {
        if (!ft_is_real_bolt_row($row)) {
            continue;
        }
        if (empty($row['mapping_ready'])) {
            continue;
        }
        if (empty($row['future_guard_passed'])) {
            continue;
        }
        if (!empty($row['terminal_status'])) {
            continue;
        }
        if (empty($row['submission_safe'])) {
            continue;
        }
        $candidates[] = $row;
    }
    return $candidates;
}

function ft_check_row(string $label, bool $pass, string $detail, bool $waiting = false): string
{
    $badge = $waiting && !$pass ? ft_badge('waiting', 'warn') : ft_bool_badge($pass);
    return '<tr><td><strong>' . ft_h($label) . '</strong></td><td>' . $badge . '</td><td>' . ft_h($detail) . '</td></tr>';
}

function ft_json_response(array $payload): void
{
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('X-Robots-Tag: noindex, nofollow', true);
    }
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit;
}

$audit = null;
$error = null;
try {
    $audit = gov_readiness_build_audit(['limit' => 50, 'analysis_limit' => 300]);
} catch (Throwable $e) {
    $error = $e->getMessage();
}

$drivers = $audit['reference_counts']['drivers'] ?? ['mapped' => 0, 'total' => 0, 'unmapped' => 0];
$vehicles = $audit['reference_counts']['vehicles'] ?? ['mapped' => 0, 'total' => 0, 'unmapped' => 0];
$recent = $audit['recent_bookings'] ?? ['rows' => [], 'submission_safe_rows' => 0];
$queue = $audit['queue_safety'] ?? [];
$lab = $audit['lab_safety'] ?? [];
$attempts = $audit['submission_attempt_safety'] ?? [];
$config = $audit['config_state'] ?? [];
$candidates = $audit ? ft_candidate_rows($recent['rows'] ?? []) : [];
$bestCandidate = $candidates[0] ?? null;

$checks = [
    'guard_minutes' => (int)($config['future_start_guard_minutes'] ?? 30),
    'dry_run_enabled' => !empty($config['dry_run_enabled']),
    'bolt_credentials_present' => !empty($config['bolt_credentials_present']),
    'edxeix_lessor_present' => !empty($config['edxeix_lessor_present']),
    'edxeix_starting_point_present' => !empty($config['edxeix_default_starting_point_present']),
    'mapped_driver_available' => (int)($drivers['mapped'] ?? 0) > 0,
    'mapped_vehicle_available' => (int)($vehicles['mapped'] ?? 0) > 0,
    'no_lab_rows' => (int)($lab['normalized_lab_rows'] ?? 0) === 0,
    'no_staged_lab_jobs' => (int)($lab['staged_lab_jobs'] ?? 0) === 0,
    'no_local_jobs' => (int)($queue['submission_jobs_total'] ?? 0) === 0,
    'no_live_attempts' => (int)($attempts['confirmed_live_indicated'] ?? 0) === 0,
    'real_future_candidate_exists' => count($candidates) > 0,
    'live_submission_authorized' => false,
];

$readyForPreflight = $checks['dry_run_enabled']
    && $checks['bolt_credentials_present']
    && $checks['edxeix_lessor_present']
    && $checks['edxeix_starting_point_present']
    && $checks['mapped_driver_available']
    && $checks['mapped_vehicle_available']
    && $checks['no_lab_rows']
    && $checks['no_staged_lab_jobs']
    && $checks['no_local_jobs']
    && $checks['no_live_attempts'];

$realCandidateReady = $readyForPreflight && $checks['real_future_candidate_exists'];

if (($_GET['format'] ?? '') === 'json') {
    ft_json_response([
        'ok' => $error === null,
        'script' => 'ops/future-test.php',
        'generated_at' => date('Y-m-d H:i:s'),
        'read_only' => true,
        'calls_bolt' => false,
        'calls_edxeix' => false,
        'writes_database' => false,
        'creates_jobs' => false,
        'live_submission_authorized' => false,
        'ready_for_real_future_preflight_setup' => $readyForPreflight,
        'real_future_candidate_ready' => $realCandidateReady,
        'checks' => $checks,
        'mapping_counts' => [
            'drivers' => $drivers,
            'vehicles' => $vehicles,
        ],
        'candidate_count' => count($candidates),
        'candidates' => array_map(static function (array $row): array {
            return [
                'id' => ft_value($row, ['id'], ''),
                'source_system' => ft_value($row, ['source_system'], ''),
                'order_reference' => ft_value($row, ['order_reference'], ''),
                'status' => ft_value($row, ['status'], ''),
                'started_at' => ft_value($row, ['started_at'], ''),
                'driver_name' => ft_value($row, ['driver_name'], ''),
                'plate' => ft_value($row, ['plate'], ''),
                'mapping_ready' => !empty($row['mapping_ready']),
                'future_guard_passed' => !empty($row['future_guard_passed']),
                'terminal_status' => !empty($row['terminal_status']),
                'submission_safe' => !empty($row['submission_safe']),
                'blockers' => $row['blockers'] ?? [],
            ];
        }, $candidates),
        'error' => $error,
        'note' => 'Read-only future-test checklist. A real future candidate permits preflight validation only; it does not authorize live EDXEIX submission.',
    ]);
}

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Real Future Test Checklist | gov.cabnet.app</title>
    <style>
        :root { --bg:#f3f6fb; --panel:#fff; --ink:#07152f; --muted:#41577a; --line:#d7e1ef; --nav:#081225; --blue:#2563eb; --green:#07875a; --orange:#b85c00; --red:#b42318; --slate:#334155; }
        * { box-sizing:border-box; }
        body { margin:0; background:var(--bg); color:var(--ink); font-family:Arial, Helvetica, sans-serif; }
        .nav { background:var(--nav); color:#fff; min-height:56px; display:flex; align-items:center; gap:18px; padding:0 26px; position:sticky; top:0; z-index:5; overflow:auto; }
        .nav strong { white-space:nowrap; }
        .nav a { color:#fff; text-decoration:none; font-size:15px; white-space:nowrap; opacity:.92; }
        .nav a:hover { opacity:1; text-decoration:underline; }
        .wrap { width:min(1440px, calc(100% - 48px)); margin:26px auto 60px; }
        .card { background:var(--panel); border:1px solid var(--line); border-radius:14px; padding:18px; margin-bottom:18px; box-shadow:0 10px 26px rgba(8,18,37,.04); }
        h1 { font-size:34px; margin:0 0 12px; } h2 { font-size:23px; margin:0 0 14px; } p { color:var(--muted); line-height:1.45; }
        .hero { border-left:7px solid var(--slate); }
        .hero.good { border-left-color:var(--green); } .hero.warn { border-left-color:var(--orange); } .hero.bad { border-left-color:var(--red); }
        .actions { display:flex; flex-wrap:wrap; gap:10px; margin-top:14px; }
        .btn { display:inline-block; padding:11px 15px; border-radius:8px; color:#fff; text-decoration:none; font-weight:700; background:var(--blue); }
        .btn.green { background:var(--green); } .btn.orange { background:var(--orange); } .btn.dark { background:var(--slate); }
        .grid { display:grid; grid-template-columns:repeat(4, minmax(0,1fr)); gap:12px; margin-top:14px; }
        .metric { border:1px solid var(--line); border-radius:10px; padding:14px; background:#f8fbff; min-height:80px; }
        .metric strong { display:block; font-size:30px; line-height:1.05; word-break:break-word; }
        .metric span { color:var(--muted); font-size:14px; }
        .badge { display:inline-block; padding:5px 9px; border-radius:999px; font-size:12px; font-weight:700; margin:1px 3px 1px 0; }
        .badge-good { background:#dcfce7; color:#166534; } .badge-warn { background:#fff7ed; color:#b45309; } .badge-bad { background:#fee2e2; color:#991b1b; } .badge-neutral { background:#eaf1ff; color:#1e40af; }
        .table-wrap { overflow:auto; border:1px solid var(--line); border-radius:10px; }
        table { width:100%; border-collapse:collapse; min-width:850px; }
        th, td { text-align:left; padding:10px 12px; border-bottom:1px solid var(--line); vertical-align:top; font-size:14px; }
        th { background:#f8fafc; font-size:12px; text-transform:uppercase; letter-spacing:.02em; }
        .goodline { color:#166534; } .warnline { color:#b45309; } .badline { color:#991b1b; }
        .small { font-size:13px; color:var(--muted); }
        code { background:#eef2ff; padding:2px 5px; border-radius:5px; }
        @media (max-width:1100px) { .grid { grid-template-columns:repeat(2, minmax(0,1fr)); } }
        @media (max-width:720px) { .grid { grid-template-columns:1fr; } .wrap { width:calc(100% - 24px); margin-top:14px; } .nav { padding:0 14px; } }
    </style>
</head>
<body>
<nav class="nav">
    <strong>GC gov.cabnet.app</strong>
    <a href="/ops/index.php">Operations Console</a>
    <a href="/ops/bolt-live.php">Bolt Live</a>
    <a href="/ops/jobs.php">Jobs Queue</a>
    <a href="/ops/readiness.php">Readiness</a>
    <a href="/ops/future-test.php">Future Test</a>
    <a href="/ops/mappings.php">Mappings</a>
    <a href="/ops/test-booking.php">Local Test Booking</a>
    <a href="/ops/cleanup-lab.php">LAB Cleanup</a>
    <a href="/bolt_readiness_audit.php">Readiness JSON</a>
</nav>

<main class="wrap">
    <section class="card hero <?= $error ? 'bad' : ($realCandidateReady ? 'good' : ($readyForPreflight ? 'warn' : 'bad')) ?>">
        <h1>Real Future Bolt Test Checklist</h1>
        <p>Read-only checklist for the next real Bolt future-ride preflight. This does not authorize live EDXEIX submission.</p>
        <?php if ($error): ?>
            <p class="badline"><strong>Error:</strong> <?= ft_h($error) ?></p>
        <?php elseif ($realCandidateReady): ?>
            <p><strong>Status:</strong> <?= ft_badge('REAL FUTURE CANDIDATE READY FOR PREFLIGHT', 'good') ?> <span class="small">A real Bolt row appears technically ready for preflight only. Live submission remains disabled.</span></p>
        <?php elseif ($readyForPreflight): ?>
            <p><strong>Status:</strong> <?= ft_badge('READY TO CREATE REAL FUTURE TEST RIDE', 'warn') ?> <span class="small">System is clean. Waiting for a real Bolt trip at least <?= ft_h($checks['guard_minutes']) ?> minutes in the future.</span></p>
        <?php else: ?>
            <p><strong>Status:</strong> <?= ft_badge('NOT READY FOR REAL FUTURE TEST', 'bad') ?> <span class="small">Fix blocking readiness items before scheduling the real test ride.</span></p>
        <?php endif; ?>
        <div class="actions">
            <a class="btn" href="/ops/future-test.php?format=json">Open Checklist JSON</a>
            <a class="btn green" href="/ops/readiness.php">Open Readiness</a>
            <a class="btn dark" href="/ops/mappings.php">Open Mappings</a>
            <a class="btn orange" href="/bolt_edxeix_preflight.php?limit=30">Open Preflight JSON</a>
        </div>
        <div class="grid">
            <div class="metric"><strong><?= ft_h(count($candidates)) ?></strong><span>Real future candidates</span></div>
            <div class="metric"><strong><?= ft_h(($drivers['mapped'] ?? 0) . '/' . ($drivers['total'] ?? 0)) ?></strong><span>Driver mappings ready</span></div>
            <div class="metric"><strong><?= ft_h(($vehicles['mapped'] ?? 0) . '/' . ($vehicles['total'] ?? 0)) ?></strong><span>Vehicle mappings ready</span></div>
            <div class="metric"><strong>0</strong><span>Live submission authorization</span></div>
        </div>
    </section>

    <?php if (!$error && $audit): ?>
    <section class="card">
        <h2>Preflight Readiness Checklist</h2>
        <p class="small">The first group confirms the system is clean enough to schedule a real future Bolt test. The second group only passes after a real future Bolt row exists and is eligible.</p>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Check</th><th>Status</th><th>Detail</th></tr></thead>
                <tbody>
                    <?= ft_check_row('Dry-run mode enabled', $checks['dry_run_enabled'], 'Live submit must remain disabled while testing.') ?>
                    <?= ft_check_row('Bolt credentials/config present', $checks['bolt_credentials_present'], 'Required for sync/preflight context; secrets are not displayed.') ?>
                    <?= ft_check_row('EDXEIX lessor configured', $checks['edxeix_lessor_present'], 'Required lessor value is configured externally.') ?>
                    <?= ft_check_row('EDXEIX starting point configured', $checks['edxeix_starting_point_present'], 'Default starting point is configured externally.') ?>
                    <?= ft_check_row('At least one mapped driver exists', $checks['mapped_driver_available'], ($drivers['mapped'] ?? 0) . '/' . ($drivers['total'] ?? 0) . ' drivers mapped.') ?>
                    <?= ft_check_row('At least one mapped vehicle exists', $checks['mapped_vehicle_available'], ($vehicles['mapped'] ?? 0) . '/' . ($vehicles['total'] ?? 0) . ' vehicles mapped.') ?>
                    <?= ft_check_row('No LAB/test normalized rows remain', $checks['no_lab_rows'], (string)($lab['normalized_lab_rows'] ?? 0) . ' LAB rows detected.') ?>
                    <?= ft_check_row('No staged LAB jobs remain', $checks['no_staged_lab_jobs'], (string)($lab['staged_lab_jobs'] ?? 0) . ' staged LAB jobs detected.') ?>
                    <?= ft_check_row('No local submission jobs are queued', $checks['no_local_jobs'], (string)($queue['submission_jobs_total'] ?? 0) . ' local submission jobs detected.') ?>
                    <?= ft_check_row('No live EDXEIX attempts indicated', $checks['no_live_attempts'], (string)($attempts['confirmed_live_indicated'] ?? 0) . ' live attempts indicated.') ?>
                    <?= ft_check_row('Real future Bolt candidate exists', $checks['real_future_candidate_exists'], count($candidates) . ' real future candidate rows found.', true) ?>
                    <?= ft_check_row('Live submission authorization', false, 'Live submission is intentionally disabled and not authorized.') ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="card">
        <h2>Real Future Candidate Details</h2>
        <?php if (!$candidates): ?>
            <p class="warnline"><strong>No real future Bolt candidate exists yet.</strong></p>
            <p>To continue the live-path validation safely, create or wait for a real Bolt ride using a mapped driver and mapped vehicle. The ride should start at least <strong><?= ft_h($checks['guard_minutes']) ?> minutes</strong> in the future. Recommended buffer: 40–60 minutes.</p>
            <p class="small">Ideal first test: Filippos Giannakopoulos / EDXEIX driver 17585 with mapped vehicle EMX6874 / EDXEIX 13799, or EHA2545 / EDXEIX 5949.</p>
        <?php else: ?>
            <p class="goodline"><strong><?= count($candidates) ?> real future candidate(s) found for preflight-only validation.</strong></p>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>ID</th><th>Source</th><th>Order Ref</th><th>Status</th><th>Started</th><th>Driver</th><th>Plate</th><th>Mapping</th><th>Future Guard</th><th>Blockers</th></tr></thead>
                    <tbody>
                    <?php foreach ($candidates as $row): ?>
                        <tr>
                            <td><?= ft_h(ft_value($row, ['id'], '')) ?></td>
                            <td><?= ft_h(ft_value($row, ['source_system'], '')) ?></td>
                            <td><?= ft_h(ft_value($row, ['order_reference'], '')) ?></td>
                            <td><?= ft_h(ft_value($row, ['status'], '')) ?></td>
                            <td><?= ft_h(ft_value($row, ['started_at'], '')) ?></td>
                            <td><?= ft_h(ft_value($row, ['driver_name'], '')) ?></td>
                            <td><?= ft_h(ft_value($row, ['plate'], '')) ?></td>
                            <td><?= ft_bool_badge(!empty($row['mapping_ready']), 'ready', 'blocked') ?></td>
                            <td><?= ft_bool_badge(!empty($row['future_guard_passed']), 'pass', 'blocked') ?></td>
                            <td><?= ft_h(implode(', ', $row['blockers'] ?? [])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="card">
        <h2>Safety Boundary</h2>
        <p>This page is intentionally read-only. A passing future candidate means the payload can be inspected with preflight tools only.</p>
        <ul>
            <li>No Bolt request is made by this page.</li>
            <li>No EDXEIX request is made by this page.</li>
            <li>No queue job is created by this page.</li>
            <li>No database row is written by this page.</li>
            <li>Live EDXEIX submission remains disabled until Andreas explicitly approves a separate live-submit patch.</li>
        </ul>
    </section>
    <?php endif; ?>
</main>
</body>
</html>
