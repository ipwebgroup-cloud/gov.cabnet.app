<?php
/**
 * gov.cabnet.app — Operations Console Index
 *
 * V3 Ops entry integration.
 * Read-only page: no Bolt calls, no EDXEIX calls, no AADE calls, no DB writes.
 * V0 laptop/manual production helper is intentionally not touched.
 */

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Robots-Tag: noindex, nofollow', true);

function ops_h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function ops_badge(string $text, string $type = 'neutral'): string
{
    return '<span class="badge badge-' . ops_h($type) . '">' . ops_h($text) . '</span>';
}

function ops_link_card(string $title, string $href, string $description, string $tag = 'Open', string $tone = 'info'): string
{
    return '<article class="link-card tone-' . ops_h($tone) . '">'
        . '<div class="link-card-top"><h3>' . ops_h($title) . '</h3>' . ops_badge($tag, $tone) . '</div>'
        . '<p>' . ops_h($description) . '</p>'
        . '<a class="btn" href="' . ops_h($href) . '">Open</a>'
        . '</article>';
}

function ops_status_card(string $label, string $value, string $note, string $tone = 'neutral'): string
{
    return '<div class="metric metric-' . ops_h($tone) . '">'
        . '<span>' . ops_h($label) . '</span>'
        . '<strong>' . ops_h($value) . '</strong>'
        . '<small>' . ops_h($note) . '</small>'
        . '</div>';
}

$now = date('Y-m-d H:i:s T');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Operations Console | gov.cabnet.app</title>
    <style>
        :root {
            --ops-navy:#2f3659;
            --ops-navy-2:#25304f;
            --ops-blue:#5563b7;
            --ops-blue-2:#4159a8;
            --ops-bg:#f3f5fa;
            --ops-panel:#ffffff;
            --ops-line:#d9deea;
            --ops-text:#1f2d4d;
            --ops-muted:#5a6785;
            --ops-good:#5fae63;
            --ops-warn:#d39a31;
            --ops-bad:#c94b4b;
            --ops-info:#4d89d8;
            --shadow:0 10px 28px rgba(31,45,77,.07);
        }
        * { box-sizing:border-box; }
        body { margin:0; background:var(--ops-bg); color:var(--ops-text); font-family:Arial, Helvetica, sans-serif; }
        a { color:inherit; }
        .shell { min-height:100vh; display:grid; grid-template-columns:280px minmax(0,1fr); }
        .sidebar { background:linear-gradient(180deg,var(--ops-navy) 0%, var(--ops-navy-2) 100%); color:#fff; padding:24px 18px; }
        .brand { display:flex; gap:12px; align-items:center; margin-bottom:26px; }
        .brand-mark { width:44px; height:44px; border-radius:14px; background:#fff; color:var(--ops-navy); display:flex; align-items:center; justify-content:center; font-weight:900; letter-spacing:.02em; }
        .brand strong { display:block; font-size:18px; }
        .brand span { display:block; font-size:12px; opacity:.75; margin-top:2px; }
        .side-group { margin:22px 0; }
        .side-group-title { font-size:11px; text-transform:uppercase; letter-spacing:.08em; opacity:.62; margin:0 0 9px 10px; }
        .side-link { display:block; color:#fff; text-decoration:none; padding:11px 12px; border-radius:12px; margin:3px 0; font-size:14px; opacity:.86; }
        .side-link:hover, .side-link.active { background:rgba(255,255,255,.13); opacity:1; }
        .side-note { margin-top:24px; padding:13px; border-radius:14px; background:rgba(255,255,255,.10); font-size:13px; line-height:1.45; color:rgba(255,255,255,.82); }
        .main { min-width:0; }
        .topbar { height:66px; background:#fff; border-bottom:1px solid var(--ops-line); display:flex; justify-content:space-between; align-items:center; gap:16px; padding:0 28px; position:sticky; top:0; z-index:5; }
        .topbar-title { font-weight:800; color:var(--ops-navy); }
        .topbar-meta { display:flex; flex-wrap:wrap; gap:8px; justify-content:flex-end; }
        .content { width:min(1460px, calc(100% - 48px)); margin:26px auto 60px; }
        .hero { background:var(--ops-panel); border:1px solid var(--ops-line); border-radius:18px; box-shadow:var(--shadow); padding:26px; margin-bottom:18px; position:relative; overflow:hidden; }
        .hero:before { content:""; position:absolute; top:0; left:0; right:0; height:6px; background:linear-gradient(90deg,var(--ops-blue),var(--ops-info)); }
        .eyebrow { color:var(--ops-blue-2); font-weight:800; font-size:13px; letter-spacing:.08em; text-transform:uppercase; margin-bottom:8px; }
        h1 { font-size:40px; line-height:1.05; margin:0 0 12px; color:var(--ops-navy); }
        h2 { font-size:24px; margin:0 0 14px; color:var(--ops-navy); }
        h3 { font-size:17px; margin:0; color:var(--ops-navy); }
        p { color:var(--ops-muted); line-height:1.5; margin:9px 0; }
        .hero-actions, .actions { display:flex; flex-wrap:wrap; gap:10px; margin-top:16px; }
        .btn { display:inline-flex; align-items:center; justify-content:center; min-height:40px; padding:10px 14px; border-radius:11px; background:var(--ops-blue); color:#fff; text-decoration:none; font-weight:800; font-size:14px; border:0; }
        .btn.secondary { background:var(--ops-navy); }
        .btn.good { background:var(--ops-good); }
        .btn.warn { background:var(--ops-warn); }
        .btn.light { background:#edf1fb; color:var(--ops-navy); }
        .badge { display:inline-flex; align-items:center; min-height:26px; padding:5px 10px; border-radius:999px; font-size:12px; font-weight:800; white-space:nowrap; }
        .badge-good { background:#e6f5e7; color:#236b34; }
        .badge-warn { background:#fff4dd; color:#8b5a10; }
        .badge-bad { background:#fde8e8; color:#922626; }
        .badge-info { background:#e8f1ff; color:#245c9b; }
        .badge-neutral { background:#edf1fb; color:#344366; }
        .grid { display:grid; grid-template-columns:repeat(4, minmax(0,1fr)); gap:14px; margin-bottom:18px; }
        .metric { background:var(--ops-panel); border:1px solid var(--ops-line); border-radius:16px; box-shadow:0 6px 18px rgba(31,45,77,.045); padding:18px; min-height:120px; border-top:5px solid var(--ops-blue); }
        .metric-good { border-top-color:var(--ops-good); }
        .metric-warn { border-top-color:var(--ops-warn); }
        .metric-bad { border-top-color:var(--ops-bad); }
        .metric-info { border-top-color:var(--ops-info); }
        .metric span { display:block; color:var(--ops-muted); font-size:13px; font-weight:800; text-transform:uppercase; letter-spacing:.04em; }
        .metric strong { display:block; font-size:28px; margin:10px 0 6px; color:var(--ops-navy); }
        .metric small { color:var(--ops-muted); font-size:13px; line-height:1.35; }
        .panel { background:var(--ops-panel); border:1px solid var(--ops-line); border-radius:18px; box-shadow:var(--shadow); padding:22px; margin-bottom:18px; }
        .panel-head { display:flex; align-items:flex-start; justify-content:space-between; gap:16px; margin-bottom:16px; }
        .link-grid { display:grid; grid-template-columns:repeat(3, minmax(0,1fr)); gap:14px; }
        .link-card { border:1px solid var(--ops-line); border-radius:16px; padding:17px; background:#fff; min-height:190px; display:flex; flex-direction:column; justify-content:space-between; border-top:5px solid var(--ops-info); }
        .link-card.tone-good { border-top-color:var(--ops-good); }
        .link-card.tone-warn { border-top-color:var(--ops-warn); }
        .link-card.tone-bad { border-top-color:var(--ops-bad); }
        .link-card.tone-info { border-top-color:var(--ops-info); }
        .link-card.tone-neutral { border-top-color:var(--ops-navy); }
        .link-card-top { display:flex; justify-content:space-between; align-items:flex-start; gap:12px; }
        .list { margin:0; padding-left:18px; color:var(--ops-muted); line-height:1.55; }
        .list li { margin:6px 0; }
        .two { display:grid; grid-template-columns:1.2fr .8fr; gap:18px; }
        code { background:#edf1fb; color:var(--ops-navy); padding:2px 6px; border-radius:6px; }
        .footer-note { color:var(--ops-muted); font-size:13px; text-align:center; margin-top:24px; }
        @media (max-width:1180px) { .grid { grid-template-columns:repeat(2, minmax(0,1fr)); } .link-grid { grid-template-columns:repeat(2, minmax(0,1fr)); } .two { grid-template-columns:1fr; } }
        @media (max-width:860px) { .shell { grid-template-columns:1fr; } .sidebar { position:relative; padding:18px; } .side-group { margin:14px 0; } .topbar { position:relative; height:auto; min-height:64px; align-items:flex-start; flex-direction:column; padding:16px 18px; } .content { width:calc(100% - 24px); margin-top:14px; } h1 { font-size:31px; } .grid, .link-grid { grid-template-columns:1fr; } }
    </style>
</head>
<body>
<div class="shell">
    <aside class="sidebar">
        <div class="brand">
            <div class="brand-mark">EA</div>
            <div>
                <strong>gov.cabnet.app</strong>
                <span>Operations Console</span>
            </div>
        </div>

        <div class="side-group">
            <div class="side-group-title">Main</div>
            <a class="side-link active" href="/ops/index.php">Ops Index</a>
            <a class="side-link" href="/ops/home.php">Ops Home</a>
            <a class="side-link" href="/ops/readiness.php">Bridge Readiness</a>
        </div>

        <div class="side-group">
            <div class="side-group-title">V3 Pre-Ride</div>
            <a class="side-link" href="/ops/pre-ride-email-v3-dashboard.php">V3 Control Center</a>
            <a class="side-link" href="/ops/pre-ride-email-v3-monitor.php">Compact Monitor</a>
            <a class="side-link" href="/ops/pre-ride-email-v3-queue-focus.php">Queue Focus</a>
            <a class="side-link" href="/ops/pre-ride-email-v3-pulse-focus.php">Pulse Focus</a>
            <a class="side-link" href="/ops/pre-ride-email-v3-readiness-focus.php">Readiness Focus</a>
            <a class="side-link" href="/ops/pre-ride-email-v3-storage-check.php">Storage Check</a>
        </div>

        <div class="side-group">
            <div class="side-group-title">Legacy / Bridge</div>
            <a class="side-link" href="/ops/bolt-live.php">Bolt Live</a>
            <a class="side-link" href="/ops/jobs.php">Jobs Queue</a>
            <a class="side-link" href="/ops/mappings.php">Mappings</a>
        </div>

        <div class="side-note">
            V0 remains on the laptop/manual production helper path. This server Ops index is for V3 visibility and guarded bridge operations only.
        </div>
    </aside>

    <main class="main">
        <header class="topbar">
            <div class="topbar-title">Operations Index</div>
            <div class="topbar-meta">
                <?= ops_badge('Production', 'neutral') ?>
                <?= ops_badge('V3 visibility', 'info') ?>
                <?= ops_badge('Live submit disabled', 'bad') ?>
                <?= ops_badge('V0 untouched', 'good') ?>
            </div>
        </header>

        <section class="content">
            <article class="hero">
                <div class="eyebrow">V3 Ops Entry</div>
                <h1>One coherent entry point for V3 monitoring</h1>
                <p>This page links the verified V3 control center, compact monitor, queue focus, pulse focus, readiness focus, and storage check pages. It is a read-only navigation page and does not run workers, submit to EDXEIX, call AADE, or write queue data.</p>
                <div class="hero-actions">
                    <a class="btn" href="/ops/pre-ride-email-v3-dashboard.php">Open V3 Control Center</a>
                    <a class="btn good" href="/ops/pre-ride-email-v3-monitor.php">Open Compact Monitor</a>
                    <a class="btn secondary" href="/ops/pre-ride-email-v3-queue-focus.php">Open Queue Focus</a>
                    <a class="btn light" href="/ops/home.php">Open Ops Home</a>
                </div>
            </article>

            <section class="grid" aria-label="Safety posture">
                <?= ops_status_card('V3 pulse cron', 'Healthy', 'Verified as cabnet user with cycles_run=5, ok=5, failed=0.', 'good') ?>
                <?= ops_status_card('Pulse lock', 'cabnet:cabnet', 'Expected owner/group with 0660 permissions.', 'good') ?>
                <?= ops_status_card('Live submit', 'Disabled', 'Master gate remains closed. No live EDXEIX submission.', 'bad') ?>
                <?= ops_status_card('V0 production helper', 'Untouched', 'Laptop/manual V0 path remains separate.', 'good') ?>
            </section>

            <section class="panel">
                <div class="panel-head">
                    <div>
                        <h2>Verified V3 monitoring pages</h2>
                        <p>Use these pages for fast visibility while keeping operational judgment with the operator.</p>
                    </div>
                    <?= ops_badge('Read-only visibility', 'good') ?>
                </div>
                <div class="link-grid">
                    <?= ops_link_card('V3 Control Center', '/ops/pre-ride-email-v3-dashboard.php', 'Main V3 dashboard linking the queue, pulse, readiness, storage, guards, and locked submit views.', 'Main', 'info') ?>
                    <?= ops_link_card('Compact Monitor', '/ops/pre-ride-email-v3-monitor.php', 'Fast single-page overview of pulse health, queue metrics, newest row, latest error, and gate state.', 'Fast', 'good') ?>
                    <?= ops_link_card('Queue Focus', '/ops/pre-ride-email-v3-queue-focus.php', 'Detailed queue visibility: newest row, latest 25 rows, status distribution, pickup timing, and last errors.', 'Queue', 'info') ?>
                    <?= ops_link_card('Pulse Focus', '/ops/pre-ride-email-v3-pulse-focus.php', 'Pulse cron visibility: latest summary, start/finish times, exit code, lock state, and recent errors.', 'Pulse', 'info') ?>
                    <?= ops_link_card('Readiness Focus', '/ops/pre-ride-email-v3-readiness-focus.php', 'Readiness overview covering pulse, queue, starting-point facts, recent errors, and locked gate state.', 'Readiness', 'warn') ?>
                    <?= ops_link_card('Storage Check', '/ops/pre-ride-email-v3-storage-check.php', 'Verifies V3 storage, logs, locks, pulse files, and pulse lock ownership/writability.', 'Storage', 'good') ?>
                </div>
            </section>

            <section class="two">
                <article class="panel">
                    <h2>Legacy / bridge tools</h2>
                    <p>These remain available, but V3 pre-ride email monitoring should now start from the V3 Control Center or Compact Monitor.</p>
                    <div class="actions">
                        <a class="btn light" href="/ops/readiness.php">Bridge Readiness</a>
                        <a class="btn light" href="/ops/bolt-live.php">Bolt Live</a>
                        <a class="btn light" href="/ops/jobs.php">Jobs Queue</a>
                        <a class="btn light" href="/ops/mappings.php">Mappings</a>
                    </div>
                </article>

                <article class="panel">
                    <h2>Safety boundary</h2>
                    <ul class="list">
                        <li>No live EDXEIX submit is enabled from this page.</li>
                        <li>No V0 laptop/manual helper files are changed by this V3 work.</li>
                        <li>No AADE receipt issuing logic is touched here.</li>
                        <li>No SQL schema or queue mutation logic is changed.</li>
                        <li>Manual testing of the pulse cron worker should be done as <code>cabnet</code>, not root.</li>
                    </ul>
                </article>
            </section>

            <div class="footer-note">Last rendered: <?= ops_h($now) ?></div>
        </section>
    </main>
</div>
</body>
</html>
