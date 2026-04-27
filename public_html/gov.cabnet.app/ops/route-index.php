<?php
/**
 * gov.cabnet.app — Ops Route Index / Safety Matrix v2.3
 *
 * Static/read-only route catalog.
 * Does not call Bolt, EDXEIX, the database, job staging, or mapping update logic.
 */

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Robots-Tag: noindex,nofollow', true);

function ri_h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function ri_badge(string $text, string $type = 'neutral'): string { return '<span class="badge badge-' . ri_h($type) . '">' . ri_h($text) . '</span>'; }
function ri_level(string $level): string {
    $map = ['safe'=>'good', 'guarded'=>'warn', 'developer'=>'neutral', 'avoid'=>'bad'];
    $level = strtolower($level);
    return ri_badge(strtoupper($level), $map[$level] ?? 'neutral');
}
function ri_flag(string $value): string {
    $v = strtolower($value);
    if (in_array($v, ['no','none','false'], true)) { return ri_badge('NO', 'good'); }
    if (in_array($v, ['yes','true'], true)) { return ri_badge('YES', 'bad'); }
    if (in_array($v, ['guarded','dry-run','linked'], true)) { return ri_badge(strtoupper($v), 'warn'); }
    return ri_badge(strtoupper($value), 'neutral');
}

$routes = [
    ['Primary', 'Ops Home', '/ops/home.php', 'safe', 'yes', 'no', 'no', 'no', 'no', 'no', 'Polished EDXEIX-style landing page. Reads readiness state only.'],
    ['Primary', 'Test Session Control', '/ops/test-session.php', 'safe', 'yes', 'no', 'no', 'no', 'no', 'no', 'Main workflow launcher for the next real future Bolt ride test.'],
    ['Primary', 'Preflight Review', '/ops/preflight-review.php', 'safe', 'yes', 'no', 'no', 'no', 'no', 'no', 'Plain-language preflight explanation. Does not submit.'],
    ['Evidence', 'Dev Accelerator', '/ops/dev-accelerator.php', 'guarded', 'supervised', 'dry-run', 'no', 'no', 'no', 'no', 'Default page is safe. Capture buttons run Bolt dry-run visibility probes and save sanitized evidence files.'],
    ['Evidence', 'Bolt Visibility', '/ops/bolt-api-visibility.php', 'guarded', 'supervised', 'dry-run', 'no', 'no', 'no', 'no', 'Dry-run visibility diagnostic through existing sync path.'],
    ['Evidence', 'Evidence Bundle', '/ops/evidence-bundle.php', 'safe', 'yes', 'no', 'no', 'no', 'no', 'no', 'Reads existing sanitized visibility JSONL evidence only.'],
    ['Evidence', 'Evidence Report', '/ops/evidence-report.php', 'safe', 'yes', 'no', 'no', 'no', 'no', 'no', 'Exports Markdown/JSON summaries from sanitized evidence only.'],
    ['Administration', 'Admin Control', '/ops/admin-control.php', 'safe', 'yes', 'no', 'no', 'no', 'no', 'no', 'Read-only administration hub.'],
    ['Administration', 'Readiness Control', '/ops/readiness-control.php', 'safe', 'yes', 'no', 'no', 'no', 'no', 'no', 'Read-only readiness/control view using existing audit state.'],
    ['Administration', 'Mapping Review', '/ops/mapping-control.php', 'safe', 'yes', 'no', 'no', 'no', 'no', 'no', 'Read-only mapping coverage view.'],
    ['Administration', 'Jobs Review', '/ops/jobs-control.php', 'safe', 'yes', 'no', 'no', 'no', 'no', 'no', 'Read-only local jobs and attempts visibility.'],
    ['Original', 'Original Console', '/ops/index.php', 'safe', 'yes', 'no', 'no', 'no', 'no', 'no', 'Original guided console. Now links to Ops Home.'],
    ['Original', 'Original Mapping Editor', '/ops/mappings.php', 'guarded', 'supervised', 'no', 'no', 'guarded', 'no', 'guarded', 'Guarded editor. Use only with confirmed EDXEIX IDs.'],
    ['Original', 'Original Jobs Queue', '/ops/jobs.php', 'guarded', 'supervised', 'no', 'no', 'no', 'linked', 'no', 'Viewer page with links to dry-run/staging endpoints.'],
    ['Raw JSON', 'Readiness JSON', '/bolt_readiness_audit.php', 'developer', 'no', 'no', 'no', 'no', 'no', 'no', 'Raw readiness JSON.'],
    ['Raw JSON', 'Preflight JSON', '/bolt_edxeix_preflight.php?limit=30', 'developer', 'supervised', 'no', 'no', 'no', 'no', 'no', 'Raw preflight preview only. Does not submit.'],
    ['Raw JSON', 'Jobs JSON', '/bolt_jobs_queue.php?limit=50', 'developer', 'no', 'no', 'no', 'no', 'no', 'no', 'Raw local jobs JSON.'],
    ['Guarded action', 'Stage EDXEIX Jobs Dry Run', '/bolt_stage_edxeix_jobs.php?limit=30', 'guarded', 'no', 'no', 'no', 'no', 'dry-run', 'no', 'Dry-run staging preview only.'],
    ['Guarded action', 'Create Local Jobs', '/bolt_stage_edxeix_jobs.php?create=1&limit=30', 'guarded', 'no', 'no', 'no', 'yes', 'yes', 'no', 'Creates local jobs only. Use only after real future candidate passes preflight.'],
];

$format = strtolower(trim((string)($_GET['format'] ?? 'html')));

$payload = [
    'ok' => true,
    'script' => 'ops/route-index.php',
    'generated_at' => date('c'),
    'safety_contract' => [
        'calls_bolt' => false,
        'calls_edxeix' => false,
        'reads_database' => false,
        'writes_database' => false,
        'stages_jobs' => false,
        'updates_mappings' => false,
        'live_edxeix_submission' => 'disabled_not_used',
    ],
    'columns' => ['group','name','path','safety','novice','calls_bolt','calls_edxeix','writes_db','stages_jobs','updates_mappings','notes'],
    'routes' => $routes,
];

if ($format === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit;
}

$groups = [];
foreach ($routes as $r) { $groups[$r[0]][] = $r; }
$safe = count(array_filter($routes, static fn($r) => $r[3] === 'safe'));
$guarded = count(array_filter($routes, static fn($r) => $r[3] === 'guarded'));
$developer = count(array_filter($routes, static fn($r) => $r[3] === 'developer'));
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Route Index / Safety Matrix | gov.cabnet.app</title>
    <link rel="stylesheet" href="/assets/css/gov-ops-edxeix.css?v=2.3">
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
        <a href="/ops/home.php">Αρχική</a>
        <a href="/ops/admin-control.php">Administration</a>
        <a href="/ops/test-session.php">Test Session</a>
        <a href="/ops/preflight-review.php">Preflight Review</a>
        <a class="gov-logout" href="/ops/index.php">Original Console</a>
    </div>
</div>

<div class="gov-shell">
    <aside class="gov-sidebar">
        <h3>Route Index</h3>
        <p>Static safety matrix for operational routes</p>
        <div class="gov-side-group">
            <div class="gov-side-group-title">Safe entry points</div>
            <a class="gov-side-link" href="/ops/home.php">Ops Home</a>
            <a class="gov-side-link" href="/ops/test-session.php">Test Session</a>
            <a class="gov-side-link" href="/ops/admin-control.php">Admin Control</a>
            <a class="gov-side-link active" href="/ops/route-index.php">Route Index</a>
            <div class="gov-side-group-title">Fallback</div>
            <a class="gov-side-link" href="/ops/index.php">Original Console</a>
        </div>
        <div class="gov-side-note">Static/read-only. No Bolt, EDXEIX, DB, job, or mapping action.</div>
    </aside>

    <div class="gov-content">
        <div class="gov-page-header">
            <div>
                <h1 class="gov-page-title">Πίνακας διαδρομών και ασφάλειας</h1>
                <div class="gov-breadcrumbs">Αρχική / Διαχειριστικό / Πίνακας διαδρομών και ασφάλειας</div>
            </div>
            <div class="gov-tabs">
                <a class="gov-tab active" href="/ops/route-index.php">Καρτέλα</a>
                <a class="gov-tab" href="/ops/route-index.php?format=json">JSON</a>
                <a class="gov-tab" href="/ops/home.php">Ops Home</a>
                <a class="gov-tab" href="/ops/admin-control.php">Admin</a>
            </div>
        </div>

        <main class="wrap wrap-shell">
            <section class="safety">
                <strong>STATIC ROUTE INDEX.</strong>
                This page documents routes only. It does not call Bolt, call EDXEIX, read/write the database, stage jobs, or update mappings.
            </section>

            <section class="card hero">
                <h1>Ops Route Index / Safety Matrix</h1>
                <p>Use this page to decide which operator page is safe for routine use and which endpoints require supervision.</p>
                <div>
                    <?= ri_badge('LIVE SUBMIT OFF', 'good') ?>
                    <?= ri_badge('STATIC MATRIX', 'good') ?>
                    <?= ri_badge('NO BACKEND ACTIONS', 'good') ?>
                </div>
                <div class="grid" style="margin-top:14px">
                    <div class="metric"><strong><?= ri_h(count($routes)) ?></strong><span>Total routes listed</span></div>
                    <div class="metric"><strong><?= ri_h($safe) ?></strong><span>Safe routes</span></div>
                    <div class="metric"><strong><?= ri_h($guarded) ?></strong><span>Guarded routes</span></div>
                    <div class="metric"><strong><?= ri_h($developer) ?></strong><span>Developer/raw routes</span></div>
                </div>
                <div class="actions">
                    <a class="btn good" href="/ops/home.php">Open Ops Home</a>
                    <a class="btn" href="/ops/test-session.php">Open Test Session</a>
                    <a class="btn dark" href="/ops/route-index.php?format=json">Open JSON</a>
                </div>
            </section>

            <section class="card">
                <h2>Safety legend</h2>
                <div class="gov-admin-grid">
                    <div class="gov-admin-link"><strong><?= ri_level('safe') ?> Safe</strong><span>Normal operator use. No backend action or writes.</span></div>
                    <div class="gov-admin-link"><strong><?= ri_level('guarded') ?> Guarded</strong><span>Requires supervision or care. May include dry-run probes or guarded writes.</span></div>
                    <div class="gov-admin-link"><strong><?= ri_level('developer') ?> Developer</strong><span>Raw JSON or diagnostic route for developer review.</span></div>
                </div>
            </section>

            <?php foreach ($groups as $group => $items): ?>
                <section class="card">
                    <h2><?= ri_h($group) ?></h2>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Route</th>
                                    <th>Safety</th>
                                    <th>Novice</th>
                                    <th>Bolt</th>
                                    <th>EDXEIX</th>
                                    <th>DB Write</th>
                                    <th>Jobs</th>
                                    <th>Mappings</th>
                                    <th>Notes</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($items as $r): ?>
                                <tr>
                                    <td><strong><?= ri_h($r[1]) ?></strong><br><a href="<?= ri_h($r[2]) ?>"><code><?= ri_h($r[2]) ?></code></a></td>
                                    <td><?= ri_level($r[3]) ?></td>
                                    <td><?= ri_h(strtoupper($r[4])) ?></td>
                                    <td><?= ri_flag($r[5]) ?></td>
                                    <td><?= ri_flag($r[6]) ?></td>
                                    <td><?= ri_flag($r[7]) ?></td>
                                    <td><?= ri_flag($r[8]) ?></td>
                                    <td><?= ri_flag($r[9]) ?></td>
                                    <td><?= ri_h($r[10]) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            <?php endforeach; ?>

            <section class="card">
                <h2>Recommended current workflow</h2>
                <ol class="timeline">
                    <li>Use <strong>/ops/home.php</strong> as the primary safe entry point.</li>
                    <li>Use <strong>/ops/test-session.php</strong> when a real future Bolt ride can be created.</li>
                    <li>Use <strong>/ops/dev-accelerator.php</strong> capture buttons during a real ride only; they are dry-run visibility probes.</li>
                    <li>Use <strong>/ops/evidence-report.php?format=md</strong> to copy/paste final test evidence.</li>
                    <li>Do not use any live submit path unless Andreas explicitly asks for a future live-submit patch after preflight passes.</li>
                </ol>
            </section>
        </main>
    </div>
</div>
</body>
</html>
