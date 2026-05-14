<?php
/**
 * Read-only V3 adapter contract probe page.
 */

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Robots-Tag: noindex, nofollow', true);

$cli = '/home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_adapter_contract_probe.php';
if (is_file($cli) && is_readable($cli)) {
    require_once $cli;
}

function h($value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function badge(string $text, string $type = 'neutral'): string { return '<span class="badge badge-' . h($type) . '">' . h($text) . '</span>'; }
function yesno($value): string { if ($value === null) { return badge('n/a', 'neutral'); } return $value ? badge('yes', 'good') : badge('no', 'bad'); }

$report = function_exists('prv3_adapter_probe_run') ? prv3_adapter_probe_run() : [
    'ok' => false,
    'version' => 'missing_probe_cli',
    'mode' => 'error',
    'safety' => 'Probe CLI missing or unreadable. No action was taken.',
    'files' => [],
    'adapters' => [],
    'events' => ['Probe CLI missing: ' . $cli],
];
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>V3 Adapter Contract Probe | gov.cabnet.app</title>
<style>
:root{--bg:#f3f6fb;--panel:#fff;--ink:#07152f;--muted:#40547a;--line:#d6e0ef;--nav:#30395f;--blue:#5563b7;--green:#4caf64;--red:#c94b4b;--amber:#d69226;--soft:#f8fbff}*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--ink);font-family:Arial,Helvetica,sans-serif}.top{height:56px;background:#fff;border-bottom:1px solid var(--line);display:flex;align-items:center;gap:20px;padding:0 22px;position:sticky;top:0;z-index:5}.top a{color:#1d2e66;text-decoration:none;font-weight:700;font-size:14px}.layout{display:grid;grid-template-columns:260px 1fr;min-height:calc(100vh - 56px)}.side{background:#30395f;color:#fff;padding:22px}.side h2{font-size:18px;margin:0 0 8px}.side p{color:#dbe4ff;line-height:1.35}.side a{display:block;color:#fff;text-decoration:none;padding:9px 10px;border-radius:8px;margin:4px 0}.side a.active,.side a:hover{background:rgba(255,255,255,.14)}.main{padding:24px}.hero,.card{background:var(--panel);border:1px solid var(--line);border-radius:14px;padding:20px;margin-bottom:16px;box-shadow:0 8px 24px rgba(31,45,77,.04)}.hero{border-left:7px solid var(--green)}h1{font-size:34px;margin:0 0 10px}h2{margin:0 0 14px}.grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}.metric{background:var(--soft);border:1px solid var(--line);border-radius:12px;padding:16px}.metric strong{font-size:30px;display:block}.badge{display:inline-block;padding:5px 9px;border-radius:999px;font-size:12px;font-weight:800;margin:2px}.badge-good{background:#dcfce7;color:#166534}.badge-bad{background:#fee2e2;color:#991b1b}.badge-warn{background:#fff7ed;color:#a15c00}.badge-neutral{background:#eaf1ff;color:#1e3a8a}.table{width:100%;border-collapse:collapse}.table th,.table td{border-bottom:1px solid var(--line);padding:10px;text-align:left;vertical-align:top}.table th{background:#f6f8fc;color:#33466f}.code{font-family:Consolas,Monaco,monospace;font-size:12px;background:#f6f8fc;border:1px solid var(--line);border-radius:8px;padding:10px;white-space:pre-wrap;overflow:auto}.actions a{display:inline-block;text-decoration:none;background:var(--blue);color:white;font-weight:800;padding:10px 14px;border-radius:8px;margin:4px}.actions a.dark{background:#20294d}.actions a.amber{background:var(--amber)}@media(max-width:900px){.layout{grid-template-columns:1fr}.side{position:static}.grid{grid-template-columns:1fr}.main{padding:14px}}
</style>
</head>
<body>
<div class="top">
    <strong>EA / gov.cabnet.app</strong>
    <a href="/ops/index.php">Ops Index</a>
    <a href="/ops/pre-ride-email-v3-dashboard.php">V3 Control Center</a>
    <a href="/ops/pre-ride-email-v3-proof.php">Proof</a>
    <a href="/ops/pre-ride-email-v3-closed-gate-adapter-diagnostics.php">Closed-Gate Diagnostics</a>
</div>
<div class="layout">
    <aside class="side">
        <h2>V3 Automation</h2>
        <p>Read-only adapter contract probe. No live submit.</p>
        <a href="/ops/pre-ride-email-v3-proof.php">Proof Dashboard</a>
        <a href="/ops/pre-ride-email-v3-live-package-export.php">Package Export</a>
        <a href="/ops/pre-ride-email-v3-closed-gate-adapter-diagnostics.php">Closed-Gate Diagnostics</a>
        <a class="active" href="/ops/pre-ride-email-v3-adapter-contract-probe.php">Adapter Contract Probe</a>
        <a href="/ops/pre-ride-email-v3-storage-check.php">Storage Check</a>
    </aside>
    <main class="main">
        <section class="hero">
            <h1>V3 Adapter Contract Probe</h1>
            <p><?= h((string)($report['safety'] ?? '')) ?></p>
            <?= !empty($report['ok']) ? badge('contract safe', 'good') : badge('check required', 'warn') ?>
            <?= badge('no EDXEIX call', 'good') ?>
            <?= badge('no AADE call', 'good') ?>
            <?= badge('V0 untouched', 'good') ?>
            <div class="actions">
                <a href="/ops/pre-ride-email-v3-closed-gate-adapter-diagnostics.php">Open Closed-Gate Diagnostics</a>
                <a class="amber" href="/ops/pre-ride-email-v3-live-package-export.php">Open Package Export</a>
                <a class="dark" href="/ops/pre-ride-email-v3-proof.php">Open Proof</a>
            </div>
        </section>

        <section class="grid">
            <div class="metric"><strong><?= !empty($report['ok']) ? 'OK' : 'CHECK' ?></strong><span>Overall contract probe</span></div>
            <div class="metric"><strong><?= h(count((array)($report['adapters'] ?? []))) ?></strong><span>Adapters probed</span></div>
            <div class="metric"><strong><?= h(count((array)($report['events'] ?? []))) ?></strong><span>Events</span></div>
            <div class="metric"><strong>0</strong><span>Live submit calls</span></div>
        </section>

        <section class="card">
            <h2>Adapter results</h2>
            <table class="table">
                <thead><tr><th>Adapter</th><th>Class</th><th>Name</th><th>Live capable</th><th>Submitted</th><th>Safe</th><th>Message</th></tr></thead>
                <tbody>
                <?php foreach ((array)($report['adapters'] ?? []) as $adapter): ?>
                    <tr>
                        <td><strong><?= h((string)($adapter['key'] ?? '')) ?></strong></td>
                        <td class="code"><?= h((string)($adapter['class'] ?? '')) ?></td>
                        <td><?= h((string)($adapter['name'] ?? '')) ?></td>
                        <td><?= yesno($adapter['is_live_capable'] ?? null) ?></td>
                        <td><?= yesno($adapter['submitted'] ?? null) ?></td>
                        <td><?= yesno($adapter['safe_for_closed_gate'] ?? null) ?></td>
                        <td><?= h((string)($adapter['message'] ?? ($adapter['error'] ?? ''))) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </section>

        <section class="card">
            <h2>Files</h2>
            <table class="table">
                <thead><tr><th>File</th><th>Exists</th><th>Readable</th><th>Path</th></tr></thead>
                <tbody>
                <?php foreach ((array)($report['files'] ?? []) as $key => $file): ?>
                    <tr><td><?= h((string)$key) ?></td><td><?= yesno($file['exists'] ?? false) ?></td><td><?= yesno($file['readable'] ?? false) ?></td><td class="code"><?= h((string)($file['path'] ?? '')) ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </section>

        <?php if (!empty($report['events'])): ?>
        <section class="card">
            <h2>Events</h2>
            <div class="code"><?= h(implode("\n", (array)$report['events'])) ?></div>
        </section>
        <?php endif; ?>
    </main>
</div>
</body>
</html>
