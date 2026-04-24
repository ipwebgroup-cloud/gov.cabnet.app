<?php
/**
 * gov.cabnet.app — Operations Readiness Dashboard
 * Read-only page. Does not call Bolt or EDXEIX and does not modify database rows.
 */

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Robots-Tag: noindex, nofollow', true);

require_once dirname(__DIR__) . '/bolt_readiness_audit.php';

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function badge(string $text, string $type = 'neutral'): string
{
    return '<span class="badge badge-' . h($type) . '">' . h($text) . '</span>';
}

function yes_badge($value): string
{
    return $value ? badge('yes', 'good') : badge('no', 'bad');
}

function verdict_class(string $verdict): string
{
    if ($verdict === 'READY_FOR_REAL_BOLT_FUTURE_TEST') { return 'good'; }
    if ($verdict === 'READY_FOR_PREFLIGHT_ONLY') { return 'warn'; }
    return 'bad';
}

$audit = null;
$error = null;
try {
    $audit = gov_readiness_build_audit(['limit' => 30, 'analysis_limit' => 200]);
} catch (Throwable $e) {
    $error = $e->getMessage();
}

$verdict = $audit['verdict'] ?? 'NOT_READY';
$drivers = $audit['reference_counts']['drivers'] ?? ['mapped' => 0, 'total' => 0, 'unmapped' => 0];
$vehicles = $audit['reference_counts']['vehicles'] ?? ['mapped' => 0, 'total' => 0, 'unmapped' => 0];
$recent = $audit['recent_bookings'] ?? [];
$queue = $audit['queue_safety'] ?? [];
$lab = $audit['lab_safety'] ?? [];
$attempts = $audit['submission_attempt_safety'] ?? [];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Readiness Audit | gov.cabnet.app</title>
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
        h1 { font-size:34px; margin:0 0 12px; }
        h2 { font-size:23px; margin:0 0 14px; }
        h3 { margin:16px 0 8px; }
        p { color:var(--muted); line-height:1.45; }
        .hero { border-left:7px solid var(--slate); }
        .hero.good { border-left-color:var(--green); }
        .hero.warn { border-left-color:var(--orange); }
        .hero.bad { border-left-color:var(--red); }
        .actions { display:flex; flex-wrap:wrap; gap:10px; margin-top:14px; }
        .btn { display:inline-block; padding:11px 15px; border-radius:8px; color:#fff; text-decoration:none; font-weight:700; background:var(--blue); }
        .btn.green { background:var(--green); } .btn.orange { background:var(--orange); } .btn.dark { background:var(--slate); }
        .grid { display:grid; grid-template-columns:repeat(4, minmax(0,1fr)); gap:12px; margin-top:14px; }
        .metric { border:1px solid var(--line); border-radius:10px; padding:14px; background:#f8fbff; min-height:80px; }
        .metric strong { display:block; font-size:30px; line-height:1.05; word-break:break-word; }
        .metric span { color:var(--muted); font-size:14px; }
        .two { display:grid; grid-template-columns:1fr 1fr; gap:18px; }
        .badge { display:inline-block; padding:5px 9px; border-radius:999px; font-size:12px; font-weight:700; margin:1px 3px 1px 0; }
        .badge-good { background:#dcfce7; color:#166534; } .badge-warn { background:#fff7ed; color:#b45309; } .badge-bad { background:#fee2e2; color:#991b1b; } .badge-neutral { background:#eaf1ff; color:#1e40af; }
        .table-wrap { overflow:auto; border:1px solid var(--line); border-radius:10px; }
        table { width:100%; border-collapse:collapse; min-width:760px; }
        th, td { text-align:left; padding:10px 12px; border-bottom:1px solid var(--line); vertical-align:top; font-size:14px; }
        th { background:#f8fafc; font-size:12px; text-transform:uppercase; letter-spacing:.02em; }
        .list { margin:0; padding-left:18px; color:var(--muted); }
        .list li { margin:6px 0; }
        .goodline { color:#166534; } .warnline { color:#b45309; } .badline { color:#991b1b; }
        .small { font-size:13px; color:var(--muted); }
        code { background:#eef2ff; padding:2px 5px; border-radius:5px; }
        @media (max-width:1100px) { .grid { grid-template-columns:repeat(2, minmax(0,1fr)); } .two { grid-template-columns:1fr; } }
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
    <a href="/bolt_readiness_audit.php">Readiness JSON</a>
    <a href="/bolt_edxeix_preflight.php?limit=30">Preflight JSON</a>
    <a href="/bolt_jobs_queue.php?limit=50">Jobs JSON</a>
</nav>

<main class="wrap">
    <section class="card hero <?= h(verdict_class($verdict)) ?>">
        <h1>Bolt / EDXEIX Readiness Audit</h1>
        <p>Read-only operational audit. This page does not call Bolt, does not call EDXEIX, does not post forms, does not create jobs, and does not modify database rows.</p>
        <?php if ($error): ?>
            <p class="badline"><strong>Error:</strong> <?= h($error) ?></p>
        <?php else: ?>
            <p><strong>Verdict:</strong> <?= badge($verdict, verdict_class($verdict)) ?> <span class="small"><?= h($audit['verdict_reason'] ?? '') ?></span></p>
            <div class="actions">
                <a class="btn" href="/bolt_readiness_audit.php">Open JSON Audit</a>
                <a class="btn green" href="/ops/bolt-live.php">Open Bolt Live</a>
                <a class="btn dark" href="/ops/jobs.php">Open Jobs Queue</a>
                <a class="btn orange" href="/bolt_edxeix_preflight.php?limit=30">Open Preflight JSON</a>
            </div>
            <div class="grid">
                <div class="metric"><strong><?= h(($drivers['mapped'] ?? 0) . '/' . ($drivers['total'] ?? 0)) ?></strong><span>Driver mappings ready</span></div>
                <div class="metric"><strong><?= h(($vehicles['mapped'] ?? 0) . '/' . ($vehicles['total'] ?? 0)) ?></strong><span>Vehicle mappings ready</span></div>
                <div class="metric"><strong><?= h($recent['submission_safe_rows'] ?? 0) ?></strong><span>Current submission-safe candidates</span></div>
                <div class="metric"><strong><?= h($queue['submission_jobs_total'] ?? 0) ?></strong><span>Local submission jobs</span></div>
            </div>
        <?php endif; ?>
    </section>

    <?php if (!$error && $audit): ?>
    <section class="two">
        <div class="card">
            <h2>Configuration / Bootstrap</h2>
            <p class="small">Secrets are intentionally redacted; this only checks presence and safety flags.</p>
            <ul class="list">
                <li>Bridge library loaded: <?= yes_badge($audit['bootstrap']['loaded'] ?? false) ?></li>
                <li>Environment: <strong><?= h($audit['config_state']['app_env'] ?? '') ?></strong></li>
                <li>Debug enabled: <?= ($audit['config_state']['debug_enabled'] ?? false) ? badge('yes', 'bad') : badge('no', 'good') ?></li>
                <li>Dry run enabled: <?= ($audit['config_state']['dry_run_enabled'] ?? false) ? badge('yes', 'good') : badge('no', 'bad') ?></li>
                <li>Bolt gateway verified: <?= yes_badge($audit['config_state']['bolt_base_verified'] ?? false) ?></li>
                <li>Bolt token endpoint verified: <?= yes_badge($audit['config_state']['bolt_token_endpoint_verified'] ?? false) ?></li>
                <li>Bolt scope verified: <?= yes_badge($audit['config_state']['bolt_scope_verified'] ?? false) ?></li>
                <li>Bolt credentials present: <?= yes_badge($audit['config_state']['bolt_credentials_present'] ?? false) ?></li>
                <li>EDXEIX lessor configured: <?= yes_badge($audit['config_state']['edxeix_lessor_present'] ?? false) ?></li>
                <li>EDXEIX starting point configured: <?= yes_badge($audit['config_state']['edxeix_default_starting_point_present'] ?? false) ?></li>
                <li>Future guard: <strong><?= h($audit['config_state']['future_start_guard_minutes'] ?? 30) ?> minutes</strong></li>
            </ul>
        </div>

        <div class="card">
            <h2>Lab / Attempt Safety</h2>
            <ul class="list">
                <li>LAB normalized rows: <?= (int)($lab['normalized_lab_rows'] ?? 0) === 0 ? badge('0', 'good') : badge((string)$lab['normalized_lab_rows'], 'bad') ?></li>
                <li>Staged LAB jobs: <?= (int)($lab['staged_lab_jobs'] ?? 0) === 0 ? badge('0', 'good') : badge((string)$lab['staged_lab_jobs'], 'bad') ?></li>
                <li>Submission attempts total: <strong><?= h($attempts['total'] ?? 0) ?></strong></li>
                <li>Dry-run attempts indicated: <strong><?= h($attempts['dry_run_indicated'] ?? 0) ?></strong></li>
                <li>Live attempts indicated: <?= (int)($attempts['confirmed_live_indicated'] ?? 0) === 0 ? badge('0', 'good') : badge((string)$attempts['confirmed_live_indicated'], 'bad') ?></li>
                <li>Attempt confidence: <code><?= h($attempts['confidence'] ?? '') ?></code></li>
            </ul>
        </div>
    </section>

    <section class="card">
        <h2>Warnings / Blocking Issues / Recommendations</h2>
        <div class="two">
            <div>
                <h3>Blocking Issues</h3>
                <?php if (empty($audit['blocking_issues'])): ?>
                    <p class="goodline">No blocking issues detected.</p>
                <?php else: ?>
                    <ul class="list"><?php foreach ($audit['blocking_issues'] as $item): ?><li class="badline"><?= h($item) ?></li><?php endforeach; ?></ul>
                <?php endif; ?>
                <h3>Cautions</h3>
                <?php if (empty($audit['cautions'])): ?>
                    <p class="goodline">No cautions.</p>
                <?php else: ?>
                    <ul class="list"><?php foreach ($audit['cautions'] as $item): ?><li class="warnline"><?= h($item) ?></li><?php endforeach; ?></ul>
                <?php endif; ?>
            </div>
            <div>
                <h3>Warnings</h3>
                <?php if (empty($audit['warnings'])): ?>
                    <p class="goodline">No warnings.</p>
                <?php else: ?>
                    <ul class="list"><?php foreach ($audit['warnings'] as $item): ?><li class="warnline"><?= h($item) ?></li><?php endforeach; ?></ul>
                <?php endif; ?>
                <h3>Recommendations</h3>
                <?php if (empty($audit['recommendations'])): ?>
                    <p>No recommendations.</p>
                <?php else: ?>
                    <ul class="list"><?php foreach ($audit['recommendations'] as $item): ?><li><?= h($item) ?></li><?php endforeach; ?></ul>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <section class="card">
        <h2>Table / Schema Checks</h2>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Table</th><th>Status</th><th>Missing Columns</th><th>Missing Column Groups</th></tr></thead>
                <tbody>
                <?php foreach (($audit['tables'] ?? []) as $table => $info): ?>
                    <tr>
                        <td><strong><?= h($table) ?></strong></td>
                        <td><?= !empty($info['ok']) ? badge('ok', 'good') : badge('check', 'bad') ?></td>
                        <td><?= empty($info['missing_required_columns']) ? badge('none', 'good') : h(implode(', ', $info['missing_required_columns'])) ?></td>
                        <td>
                            <?php if (empty($info['missing_required_column_groups'])): ?>
                                <?= badge('none', 'good') ?>
                            <?php else: ?>
                                <?php foreach ($info['missing_required_column_groups'] as $group): ?>
                                    <div><strong><?= h($group['label'] ?? 'group') ?>:</strong> <?= h(implode(' / ', $group['any_of'] ?? [])) ?></div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="card">
        <h2>Recent Normalized Bookings</h2>
        <p class="small">Window: <?= h($recent['window_hours'] ?? 168) ?> hours. A row is submission-safe only if it is real Bolt, mapped, non-terminal, non-lab, and starts after the configured future guard.</p>
        <div class="grid">
            <div class="metric"><strong><?= h($recent['total_recent_rows'] ?? 0) ?></strong><span>Total recent rows</span></div>
            <div class="metric"><strong><?= h($recent['mapping_ready_rows'] ?? 0) ?></strong><span>Mapping-ready analyzed rows</span></div>
            <div class="metric"><strong><?= h($recent['submission_safe_rows'] ?? 0) ?></strong><span>Submission-safe rows</span></div>
            <div class="metric"><strong><?= h($recent['blocked_rows'] ?? 0) ?></strong><span>Blocked analyzed rows</span></div>
        </div>
        <?php if (empty($recent['rows'])): ?>
            <p>No recent normalized bookings found in the selected window.</p>
        <?php else: ?>
            <div class="table-wrap" style="margin-top:14px;">
                <table>
                    <thead><tr><th>ID</th><th>Source</th><th>Order Ref</th><th>Status</th><th>Started</th><th>Driver</th><th>Plate</th><th>Mapping</th><th>Future Guard</th><th>Submission Safe</th><th>Blockers</th></tr></thead>
                    <tbody>
                    <?php foreach ($recent['rows'] as $row): ?>
                        <tr>
                            <td><?= h($row['id'] ?? '') ?></td>
                            <td><?= h($row['source_system'] ?? '') ?></td>
                            <td><?= h($row['order_reference'] ?? '') ?></td>
                            <td><?= h($row['status'] ?? '') ?></td>
                            <td><?= h($row['started_at'] ?? '') ?></td>
                            <td><?= h($row['driver_name'] ?? '') ?></td>
                            <td><?= h($row['plate'] ?? '') ?></td>
                            <td><?= !empty($row['mapping_ready']) ? badge('yes', 'good') : badge('no', 'bad') ?></td>
                            <td><?= !empty($row['future_guard_passed']) ? badge('pass', 'good') : badge('blocked', 'warn') ?></td>
                            <td><?= !empty($row['submission_safe']) ? badge('yes', 'good') : badge('no', 'bad') ?></td>
                            <td><?= h(implode(', ', $row['blockers'] ?? [])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="card">
        <h2>File Presence / Temporary Helpers</h2>
        <div class="two">
            <div>
                <h3>Key Files</h3>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>Key</th><th>Required</th><th>Status</th></tr></thead>
                        <tbody>
                        <?php foreach (($audit['file_checks'] ?? []) as $file): ?>
                            <tr>
                                <td><?= h($file['key'] ?? '') ?></td>
                                <td><?= !empty($file['required']) ? badge('yes', 'neutral') : badge('no', 'neutral') ?></td>
                                <td><?= (!empty($file['exists']) && !empty($file['readable'])) ? badge('present', 'good') : badge('missing/unreadable', !empty($file['required']) ? 'bad' : 'warn') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div>
                <h3>Temporary Public Helpers</h3>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>File</th><th>Status</th><th>Purpose</th></tr></thead>
                        <tbody>
                        <?php foreach (($audit['public_helper_files'] ?? []) as $helper): ?>
                            <tr>
                                <td><?= h($helper['file'] ?? '') ?></td>
                                <td><?= !empty($helper['exists']) ? badge('exists', 'warn') : badge('not present', 'good') ?></td>
                                <td><?= h($helper['purpose'] ?? '') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>
    <?php endif; ?>
</main>
</body>
</html>
