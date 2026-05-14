<?php

declare(strict_types=1);

foreach ([__DIR__ . '/_ops-auth.php', __DIR__ . '/ops-auth.php', __DIR__ . '/_auth.php'] as $authFile) {
    if (is_readable($authFile)) {
        require_once $authFile;
        break;
    }
}

$homeRoot = dirname(__DIR__, 3);
$cliPath = $homeRoot . '/gov.cabnet.app_app/cli/pre_ride_email_v3_adapter_payload_consistency.php';
if (!is_readable($cliPath)) {
    http_response_code(500);
    echo 'V3 adapter payload consistency CLI is missing or unreadable.';
    exit;
}
require_once $cliPath;

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function badge(bool $ok, string $yes = 'yes', string $no = 'no'): string
{
    $class = $ok ? 'ok' : 'bad';
    return '<span class="badge ' . $class . '">' . h($ok ? $yes : $no) . '</span>';
}

$queueId = isset($_GET['queue_id']) ? (int)$_GET['queue_id'] : null;
$report = v3pc_run($queueId);
$row = is_array($report['selected_queue_row'] ?? null) ? $report['selected_queue_row'] : [];
$dbPayload = is_array($report['db_payload'] ?? null) ? $report['db_payload'] : [];
$artifact = is_array($report['artifact_payload'] ?? null) ? $report['artifact_payload'] : [];
$consistency = is_array($report['consistency'] ?? null) ? $report['consistency'] : [];
$adapter = is_array($report['adapter_simulation'] ?? null) ? $report['adapter_simulation'] : [];
$starting = is_array($report['starting_point'] ?? null) ? $report['starting_point'] : [];
$blocks = is_array($report['final_blocks'] ?? null) ? $report['final_blocks'] : [];
$diffs = is_array($consistency['db_vs_artifact_differences'] ?? null) ? $consistency['db_vs_artifact_differences'] : [];
$latestFiles = is_array($artifact['latest_files'] ?? null) ? $artifact['latest_files'] : [];

?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>V3 Adapter Payload Consistency</title>
    <style>
        :root{--nav:#2f3a63;--ink:#061a44;--muted:#42527a;--bg:#eef3fb;--card:#fff;--line:#d7dfef;--good:#dff5e8;--goodText:#05712b;--bad:#ffe4e4;--badText:#9c1717;--warn:#fff5df;--warnText:#8a5200;--blue:#5262b6;--green:#34a853;--orange:#d99020}
        *{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--ink);font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;font-size:14px}.layout{display:grid;grid-template-columns:250px 1fr;min-height:100vh}.side{background:var(--nav);color:#fff;padding:24px 16px}.side h1{font-size:18px;margin:0 0 10px}.side p{font-size:13px;line-height:1.45;margin:0 0 22px}.side a{display:block;color:#fff;text-decoration:none;padding:10px 12px;border-radius:8px;margin:4px 0}.side a.active,.side a:hover{background:rgba(255,255,255,.14)}.main{padding:28px 28px 60px}.hero,.card,.notice{background:var(--card);border:1px solid var(--line);border-radius:16px;box-shadow:0 1px 2px rgba(0,0,0,.03)}.hero{padding:24px;border-left:5px solid var(--orange);margin-bottom:16px}.hero.ok{border-left-color:var(--green)}.hero h2{font-size:28px;line-height:1.1;margin:8px 0 8px}.sub{color:var(--muted)}.badges{display:flex;flex-wrap:wrap;gap:8px}.badge{display:inline-flex;align-items:center;border-radius:999px;padding:5px 10px;font-size:12px;font-weight:800;text-transform:uppercase}.badge.ok{background:var(--good);color:var(--goodText)}.badge.bad{background:var(--bad);color:var(--badText)}.badge.warn{background:var(--warn);color:var(--warnText)}.actions{display:flex;flex-wrap:wrap;gap:10px;margin-top:16px}.btn{display:inline-block;border-radius:8px;padding:10px 14px;text-decoration:none;font-weight:800;background:var(--blue);color:#fff}.btn.green{background:var(--green)}.btn.dark{background:#18234f}.grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin:16px 0}.metric{background:var(--card);border:1px solid var(--line);border-radius:14px;padding:18px}.metric strong{display:block;font-size:25px;line-height:1.1}.metric span{display:block;color:var(--muted);font-size:13px;margin-top:5px}.two{display:grid;grid-template-columns:1fr 1fr;gap:12px}.card{padding:18px;margin-bottom:12px}.card h3{margin:0 0 12px;font-size:18px}table{width:100%;border-collapse:collapse}td,th{border-bottom:1px solid var(--line);padding:9px;text-align:left;vertical-align:top}td:first-child{background:#f5f7fb;color:var(--muted);font-weight:700;width:210px}.notice{padding:14px 18px;margin:14px 0;background:#fff8ee;border-color:#ffc878;color:#803e00}.mono{font-family:ui-monospace,SFMono-Regular,Consolas,monospace;font-size:12px;word-break:break-all}ul{margin:8px 0 0 20px;padding:0}@media(max-width:900px){.layout{grid-template-columns:1fr}.side{position:static}.grid,.two{grid-template-columns:1fr}.main{padding:16px}}
    </style>
</head>
<body>
<div class="layout">
    <aside class="side">
        <h1>V3 Automation</h1>
        <p>Read-only adapter payload consistency harness. Live submit remains disabled.</p>
        <a href="/ops/pre-ride-email-v3-pre-live-switchboard.php">Pre-Live Switchboard</a>
        <a href="/ops/pre-ride-email-v3-adapter-row-simulation.php">Adapter Simulation</a>
        <a class="active" href="/ops/pre-ride-email-v3-adapter-payload-consistency.php">Payload Consistency</a>
        <a href="/ops/pre-ride-email-v3-live-package-export.php">Package Export</a>
        <a href="/ops/pre-ride-email-v3-live-adapter-kill-switch-check.php">Kill-Switch Check</a>
        <a href="/ops/pre-ride-email-v3-proof.php">Proof Dashboard</a>
    </aside>
    <main class="main">
        <section class="hero <?= !empty($report['ok']) ? 'ok' : '' ?>">
            <div class="badges">
                <?= badge(!empty($report['ok']), 'consistency ok', 'consistency blocked') ?>
                <?= badge(!empty($report['simulation_safe']), 'simulation safe', 'simulation unsafe') ?>
                <?= badge(!empty($consistency['db_vs_artifact_match']), 'artifact matches', 'artifact mismatch') ?>
                <?= badge(empty($adapter['is_live_capable']), 'adapter not live', 'adapter live capable') ?>
            </div>
            <h2>V3 Adapter Payload Consistency</h2>
            <div class="sub"><?= h((string)($report['safety'] ?? '')) ?></div>
            <div class="actions">
                <a class="btn green" href="/ops/pre-ride-email-v3-adapter-payload-consistency.php<?= $row ? '?queue_id=' . (int)$row['id'] : '' ?>">Refresh selected row</a>
                <a class="btn" href="/ops/pre-ride-email-v3-adapter-row-simulation.php<?= $row ? '?queue_id=' . (int)$row['id'] : '' ?>">Adapter Simulation</a>
                <a class="btn dark" href="/ops/pre-ride-email-v3-pre-live-switchboard.php<?= $row ? '?queue_id=' . (int)$row['id'] : '' ?>">Switchboard</a>
            </div>
        </section>

        <section class="grid">
            <div class="metric"><strong>#<?= h($row['id'] ?? '-') ?></strong><span><?= h($row['queue_status'] ?? '-') ?> · pickup <?= h($row['pickup_datetime'] ?? '-') ?></span></div>
            <div class="metric"><strong><?= !empty($dbPayload['complete']) ? 'complete' : 'missing' ?></strong><span>DB-built payload</span></div>
            <div class="metric"><strong><?= !empty($consistency['db_vs_artifact_match']) ? 'match' : 'check' ?></strong><span>DB vs latest package artifact</span></div>
            <div class="metric"><strong><?= !empty($adapter['hash_matches_expected']) ? 'match' : 'check' ?></strong><span>Adapter returned hash</span></div>
        </section>

        <?php if ($blocks): ?>
        <section class="notice">
            <strong>Final blocks</strong>
            <ul><?php foreach ($blocks as $block): ?><li><?= h($block) ?></li><?php endforeach; ?></ul>
        </section>
        <?php endif; ?>

        <section class="two">
            <div class="card">
                <h3>Selected Queue Row</h3>
                <table>
                    <tr><td>Customer</td><td><?= h($row['customer_name'] ?? '') ?> / <?= h($row['customer_phone'] ?? '') ?></td></tr>
                    <tr><td>Driver / Vehicle</td><td><?= h($row['driver_name'] ?? '') ?> / <?= h($row['vehicle_plate'] ?? '') ?></td></tr>
                    <tr><td>EDXEIX IDs</td><td>lessor=<?= h($row['lessor_id'] ?? '') ?> driver=<?= h($row['driver_id'] ?? '') ?> vehicle=<?= h($row['vehicle_id'] ?? '') ?> start=<?= h($row['starting_point_id'] ?? '') ?></td></tr>
                    <tr><td>Route</td><td><?= h($row['pickup_address'] ?? '') ?> → <?= h($row['dropoff_address'] ?? '') ?></td></tr>
                    <tr><td>Last error</td><td><?= h($row['last_error'] ?? '') ?></td></tr>
                </table>
            </div>
            <div class="card">
                <h3>Checks</h3>
                <table>
                    <tr><td>DB payload</td><td><?= badge(!empty($dbPayload['complete']), 'complete', 'missing') ?> <span class="mono"><?= h($dbPayload['hash_sha256'] ?? '') ?></span></td></tr>
                    <tr><td>Artifact payload</td><td><?= badge(!empty($artifact['edxeix_fields_found']), 'found', 'missing') ?> count=<?= h($artifact['artifact_count_for_row'] ?? 0) ?></td></tr>
                    <tr><td>DB vs artifact</td><td><?= badge(!empty($consistency['db_vs_artifact_match']), 'match', 'mismatch') ?></td></tr>
                    <tr><td>Starting point</td><td><?= badge(!empty($starting['ok']), 'verified', 'not verified') ?> <?= h($starting['label'] ?? '') ?></td></tr>
                    <tr><td>Adapter</td><td><?= h($adapter['name'] ?? '') ?> · live-capable=<?= !empty($adapter['is_live_capable']) ? 'yes' : 'no' ?> · submitted=<?= !empty($adapter['submitted']) ? 'yes' : 'no' ?></td></tr>
                    <tr><td>Version</td><td><?= h($report['version'] ?? '') ?></td></tr>
                </table>
            </div>
        </section>

        <?php if ($diffs): ?>
        <section class="card">
            <h3>Field Differences</h3>
            <ul><?php foreach ($diffs as $diff): ?><li class="mono"><?= h($diff) ?></li><?php endforeach; ?></ul>
        </section>
        <?php endif; ?>

        <section class="two">
            <div class="card">
                <h3>EDXEIX Payload</h3>
                <table>
                    <?php foreach ((array)($dbPayload['payload'] ?? []) as $key => $value): ?>
                    <tr><td><?= h($key) ?></td><td><?= h($value) ?></td></tr>
                    <?php endforeach; ?>
                </table>
            </div>
            <div class="card">
                <h3>Latest Package Artifacts</h3>
                <?php if ($latestFiles): ?>
                    <ul><?php foreach ($latestFiles as $file): ?><li class="mono"><?= h($file) ?></li><?php endforeach; ?></ul>
                <?php else: ?>
                    <p class="sub">No package artifacts found for selected row.</p>
                <?php endif; ?>
                <h3 style="margin-top:18px">Adapter Message</h3>
                <p><?= h($adapter['message'] ?? '') ?></p>
                <p class="mono">payload_sha256: <?= h($adapter['payload_sha256'] ?? '') ?></p>
            </div>
        </section>
    </main>
</div>
</body>
</html>
