<?php
/**
 * gov.cabnet.app — Safe Operations Console Landing Page
 *
 * Read-only route index for the guarded operations area.
 * Does not call Bolt, does not call EDXEIX, does not write to the database,
 * does not create jobs, and does not process submissions.
 */

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Robots-Tag: noindex, nofollow', true);

function ops_index_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function ops_index_exists(string $relativePath): bool
{
    $path = realpath(__DIR__ . '/../' . ltrim($relativePath, '/'));
    if ($path === false) {
        return false;
    }
    $publicRoot = realpath(__DIR__ . '/..');
    return $publicRoot !== false && str_starts_with($path, $publicRoot) && is_file($path) && is_readable($path);
}

$tools = [
    [
        'title' => 'Readiness Audit',
        'url' => '/ops/readiness.php',
        'status' => 'Primary',
        'type' => 'read-only',
        'description' => 'Main operational readiness dashboard. Confirms config, mappings, LAB cleanup state, queue safety, and live-attempt safety.',
        'file' => 'ops/readiness.php',
    ],
    [
        'title' => 'Future Test Checklist',
        'url' => '/ops/future-test.php',
        'status' => 'Primary',
        'type' => 'read-only',
        'description' => 'Checklist for the next real Bolt future-ride preflight. It does not authorize or perform live EDXEIX submission.',
        'file' => 'ops/future-test.php',
    ],
    [
        'title' => 'Mapping Coverage / Editor',
        'url' => '/ops/mappings.php',
        'status' => 'Operational',
        'type' => 'guarded-post',
        'description' => 'Driver and vehicle mapping coverage. GET is read-only; POST updates are limited to EDXEIX ID fields and audit-logged.',
        'file' => 'ops/mappings.php',
    ],
    [
        'title' => 'Jobs Queue',
        'url' => '/ops/jobs.php',
        'status' => 'Read-only',
        'type' => 'read-only',
        'description' => 'Local submission job and attempt viewer. Does not create jobs and does not call EDXEIX.',
        'file' => 'ops/jobs.php',
    ],
    [
        'title' => 'Bolt Live',
        'url' => '/ops/bolt-live.php',
        'status' => 'Operational',
        'type' => 'diagnostic',
        'description' => 'Bolt-side operational view for current sync/status context.',
        'file' => 'ops/bolt-live.php',
    ],
    [
        'title' => 'Local Test Booking',
        'url' => '/ops/test-booking.php',
        'status' => 'LAB only',
        'type' => 'guarded-post',
        'description' => 'Creates explicit LAB/local dry-run bookings only. Never for live EDXEIX submission.',
        'file' => 'ops/test-booking.php',
    ],
    [
        'title' => 'LAB Cleanup',
        'url' => '/ops/cleanup-lab.php',
        'status' => 'Maintenance',
        'type' => 'guarded-post',
        'description' => 'Safely removes LAB/local dry-run bookings, jobs, and attempts after validation.',
        'file' => 'ops/cleanup-lab.php',
    ],
];

$jsonLinks = [
    ['title' => 'Readiness JSON', 'url' => '/bolt_readiness_audit.php', 'file' => 'bolt_readiness_audit.php'],
    ['title' => 'Preflight JSON', 'url' => '/bolt_edxeix_preflight.php?limit=30', 'file' => 'bolt_edxeix_preflight.php'],
    ['title' => 'Jobs JSON', 'url' => '/bolt_jobs_queue.php?limit=50', 'file' => 'bolt_jobs_queue.php'],
    ['title' => 'Mappings JSON', 'url' => '/ops/mappings.php?format=json', 'file' => 'ops/mappings.php'],
    ['title' => 'Future Test JSON', 'url' => '/ops/future-test.php?format=json', 'file' => 'ops/future-test.php'],
];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Operations Console | gov.cabnet.app</title>
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
        .hero { border-left:7px solid var(--green); }
        h1 { font-size:34px; margin:0 0 12px; }
        h2 { font-size:23px; margin:0 0 14px; }
        p { color:var(--muted); line-height:1.45; }
        .grid { display:grid; grid-template-columns:repeat(3, minmax(0, 1fr)); gap:14px; }
        .tool { border:1px solid var(--line); border-radius:12px; padding:16px; background:#f8fbff; min-height:185px; display:flex; flex-direction:column; gap:10px; }
        .tool h3 { margin:0; font-size:20px; }
        .tool p { margin:0; }
        .actions { display:flex; flex-wrap:wrap; gap:10px; margin-top:auto; }
        .btn { display:inline-block; padding:10px 13px; border-radius:8px; color:#fff; text-decoration:none; font-weight:700; background:var(--blue); font-size:14px; }
        .btn.green { background:var(--green); } .btn.orange { background:var(--orange); } .btn.dark { background:var(--slate); }
        .badge { display:inline-block; padding:5px 9px; border-radius:999px; font-size:12px; font-weight:700; margin:1px 3px 1px 0; }
        .badge-good { background:#dcfce7; color:#166534; } .badge-warn { background:#fff7ed; color:#b45309; } .badge-bad { background:#fee2e2; color:#991b1b; } .badge-neutral { background:#eaf1ff; color:#1e40af; }
        .notice { border-left:5px solid var(--orange); background:#fff7ed; padding:12px 14px; border-radius:10px; color:#7c2d12; }
        .safe { border-left-color:var(--green); background:#ecfdf3; color:#14532d; }
        .links { display:flex; flex-wrap:wrap; gap:10px; }
        code { background:#eef2ff; padding:2px 5px; border-radius:5px; }
        @media (max-width:1100px) { .grid { grid-template-columns:repeat(2, minmax(0, 1fr)); } }
        @media (max-width:720px) { .grid { grid-template-columns:1fr; } .wrap { width:calc(100% - 24px); margin-top:14px; } .nav { padding:0 14px; } }
    </style>
</head>
<body>
<nav class="nav">
    <strong>GC gov.cabnet.app</strong>
    <a href="/ops/index.php">Operations Console</a>
    <a href="/ops/readiness.php">Readiness</a>
    <a href="/ops/future-test.php">Future Test</a>
    <a href="/ops/mappings.php">Mappings</a>
    <a href="/ops/jobs.php">Jobs Queue</a>
    <a href="/ops/bolt-live.php">Bolt Live</a>
    <a href="/bolt_readiness_audit.php">Readiness JSON</a>
</nav>

<main class="wrap">
    <section class="card hero">
        <h1>Operations Console</h1>
        <p>Safe landing page for the gov.cabnet.app Bolt → EDXEIX bridge. This page is read-only and only links to the current guarded workflow tools.</p>
        <div class="notice safe"><strong>Safety posture:</strong> live EDXEIX submission is still disabled. This index page does not save sessions, create bookings, create jobs, process queues, call Bolt, call EDXEIX, or write to the database.</div>
    </section>

    <section class="card">
        <h2>Current workflow tools</h2>
        <div class="grid">
            <?php foreach ($tools as $tool): $exists = ops_index_exists($tool['file']); ?>
                <article class="tool">
                    <div>
                        <?= $exists ? '<span class="badge badge-good">available</span>' : '<span class="badge badge-bad">missing</span>' ?>
                        <span class="badge badge-neutral"><?= ops_index_h($tool['status']) ?></span>
                        <span class="badge <?= $tool['type'] === 'read-only' ? 'badge-good' : ($tool['type'] === 'guarded-post' ? 'badge-warn' : 'badge-neutral') ?>"><?= ops_index_h($tool['type']) ?></span>
                    </div>
                    <h3><?= ops_index_h($tool['title']) ?></h3>
                    <p><?= ops_index_h($tool['description']) ?></p>
                    <div class="actions">
                        <a class="btn" href="<?= ops_index_h($tool['url']) ?>">Open</a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="card">
        <h2>Diagnostic JSON endpoints</h2>
        <p>These endpoints are protected by the ops access guard and remain diagnostic only.</p>
        <div class="links">
            <?php foreach ($jsonLinks as $link): $exists = ops_index_exists($link['file']); ?>
                <a class="btn <?= $exists ? 'dark' : 'orange' ?>" href="<?= ops_index_h($link['url']) ?>"><?= ops_index_h($link['title']) ?></a>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="card">
        <h2>Recommended next operational step</h2>
        <p>Use <strong>Future Test</strong> to confirm the bridge is clean, then create a real Bolt ride at least 40–60 minutes in the future using a mapped driver and mapped vehicle. Continue with preflight only. Live submission remains blocked unless explicitly approved later.</p>
        <p><code>/ops/future-test.php</code> is the next gate before real future-ride preflight testing.</p>
    </section>
</main>
</body>
</html>
