<?php
/**
 * gov.cabnet.app — V3 Closed-Gate Adapter Diagnostics
 * Read-only Ops page. No Bolt, EDXEIX, AADE, DB writes, queue mutation, or V0 changes.
 */

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Robots-Tag: noindex, nofollow', true);

$cli = '/home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_closed_gate_adapter_diagnostics.php';
$error = '';
$report = [];

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function badge($text, string $type = 'neutral'): string
{
    return '<span class="badge badge-' . h($type) . '">' . h($text) . '</span>';
}

function yesno($value, string $yes = 'yes', string $no = 'no'): string
{
    return !empty($value) ? badge($yes, 'good') : badge($no, 'bad');
}

function status_badge($status): string
{
    $s = strtolower((string)$status);
    if (in_array($s, ['ok', 'ready', 'live_submit_ready', 'present', 'exists', 'readable'], true)) {
        return badge((string)$status, 'good');
    }
    if (in_array($s, ['blocked', 'disabled', 'missing', 'error'], true)) {
        return badge((string)$status, 'bad');
    }
    if (strpos($s, 'ready') !== false) { return badge((string)$status, 'good'); }
    if (strpos($s, 'blocked') !== false) { return badge((string)$status, 'bad'); }
    return badge((string)$status, 'neutral');
}

function row_value(array $row, string $key, string $default = ''): string
{
    return isset($row[$key]) ? (string)$row[$key] : $default;
}

try {
    if (!is_file($cli) || !is_readable($cli)) {
        throw new RuntimeException('Missing or unreadable CLI diagnostic: ' . $cli);
    }
    require_once $cli;
    if (!function_exists('v3cgad_build_report')) {
        throw new RuntimeException('Diagnostic builder function not available.');
    }
    $queueId = isset($_GET['queue_id']) ? (int)$_GET['queue_id'] : null;
    $report = v3cgad_build_report($queueId);
} catch (Throwable $e) {
    $error = $e->getMessage();
}

$gate = is_array($report['gate'] ?? null) ? $report['gate'] : [];
$adapter = is_array($report['adapter'] ?? null) ? $report['adapter'] : [];
$row = is_array($report['selected_queue_row'] ?? null) ? $report['selected_queue_row'] : [];
$required = is_array($report['required_fields'] ?? null) ? $report['required_fields'] : ['missing' => [], 'values' => []];
$start = is_array($report['starting_point'] ?? null) ? $report['starting_point'] : [];
$approval = is_array($report['approval'] ?? null) ? $report['approval'] : [];
$package = is_array($report['package_export'] ?? null) ? $report['package_export'] : [];
$metrics = is_array($report['queue_metrics'] ?? null) ? $report['queue_metrics'] : [];
$blocks = is_array($report['final_blocks'] ?? null) ? $report['final_blocks'] : [];
$files = is_array($adapter['files'] ?? null) ? $adapter['files'] : [];
$missing = is_array($required['missing'] ?? null) ? $required['missing'] : [];
$values = is_array($required['values'] ?? null) ? $required['values'] : [];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>V3 Closed-Gate Adapter Diagnostics | gov.cabnet.app</title>
    <style>
        :root{--bg:#eef2f8;--panel:#fff;--ink:#08204b;--muted:#40517a;--line:#d3dbea;--side:#2f3659;--side2:#444d77;--blue:#5563b7;--green:#53ad63;--amber:#d99a2b;--red:#cb4b4b;--dark:#101b3f;}
        *{box-sizing:border-box} body{margin:0;background:var(--bg);color:var(--ink);font-family:Arial,Helvetica,sans-serif;font-size:15px}.top{height:64px;background:#fff;border-bottom:1px solid var(--line);display:flex;align-items:center;gap:22px;padding:0 26px;position:sticky;top:0;z-index:3}.brand{font-weight:800;font-size:18px}.top a{color:#0a2560;text-decoration:none;font-weight:700;font-size:14px}.layout{display:grid;grid-template-columns:300px 1fr;min-height:calc(100vh - 64px)}.side{background:var(--side);color:#fff;padding:26px 18px}.side h2{font-size:18px;margin:0 0 8px}.side p{color:#e1e7ff;line-height:1.4}.side .group{margin-top:24px;color:#c8d0ff;text-transform:uppercase;letter-spacing:.06em;font-size:12px;font-weight:800}.side a{display:block;color:#fff;text-decoration:none;padding:10px 12px;border-radius:8px;margin:4px 0}.side a.active,.side a:hover{background:var(--side2)}.main{padding:28px}.hero,.card{background:var(--panel);border:1px solid var(--line);border-radius:14px;box-shadow:0 8px 18px rgba(18,32,75,.04)}.hero{padding:24px;border-left:7px solid var(--amber);margin-bottom:18px}.hero.good{border-left-color:var(--green)}.hero.bad{border-left-color:var(--red)}h1{font-size:34px;line-height:1.05;margin:0 0 14px}h2{margin:0 0 14px;font-size:22px}p{color:var(--muted);line-height:1.45}.actions{display:flex;flex-wrap:wrap;gap:10px;margin-top:16px}.btn{display:inline-block;text-decoration:none;color:#fff;background:var(--blue);padding:11px 14px;border-radius:8px;font-weight:800}.btn.green{background:var(--green)}.btn.amber{background:var(--amber)}.btn.dark{background:var(--dark)}.badge{display:inline-block;padding:5px 9px;border-radius:999px;font-size:12px;font-weight:800;margin:2px 4px 2px 0}.badge-good{background:#dcf8e4;color:#086b25}.badge-bad{background:#ffe1e1;color:#9c1717}.badge-warn{background:#fff1d0;color:#935900}.badge-neutral{background:#e8eeff;color:#253f91}.grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin-bottom:18px}.metric{background:#f8faff;border:1px solid var(--line);border-radius:12px;padding:16px}.metric strong{display:block;font-size:32px}.metric span{font-size:13px;color:var(--muted)}.two{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:18px}.card{padding:20px}.table{width:100%;border-collapse:collapse}.table th,.table td{border-bottom:1px solid var(--line);padding:10px;text-align:left;vertical-align:top}.table th{background:#f4f7fc;color:#46557a;width:230px}.list{margin:0;padding-left:20px;color:var(--muted)}.list li{margin:6px 0}.code{font-family:Consolas,Menlo,monospace;font-size:13px;background:#f4f7fc;border:1px solid var(--line);border-radius:10px;padding:12px;white-space:pre-wrap;overflow:auto}.small{font-size:13px;color:var(--muted)}.nowrap{white-space:nowrap}@media(max-width:1100px){.layout{grid-template-columns:1fr}.side{display:none}.grid{grid-template-columns:repeat(2,minmax(0,1fr))}.two{grid-template-columns:1fr}}@media(max-width:700px){.top{overflow:auto;padding:0 14px}.main{padding:16px}.grid{grid-template-columns:1fr}h1{font-size:28px}}
    </style>
</head>
<body>
<header class="top">
    <div class="brand">EA / gov.cabnet.app</div>
    <a href="/ops/index.php">Ops Index</a>
    <a href="/ops/pre-ride-email-v3-dashboard.php">V3 Control Center</a>
    <a href="/ops/pre-ride-email-v3-proof.php">Proof</a>
    <a href="/ops/pre-ride-email-v3-live-package-export.php">Package Export</a>
    <a href="/ops/pre-ride-email-v3-operator-approvals.php">Approvals</a>
</header>
<div class="layout">
    <aside class="side">
        <h2>V3 Automation</h2>
        <p>Closed-gate diagnostics for future live adapter preparation. Live submit remains disabled.</p>
        <div class="group">Diagnostics</div>
        <a class="active" href="/ops/pre-ride-email-v3-closed-gate-adapter-diagnostics.php">Adapter Diagnostics</a>
        <a href="/ops/pre-ride-email-v3-proof.php">Proof Dashboard</a>
        <a href="/ops/pre-ride-email-v3-live-package-export.php">Package Export</a>
        <a href="/ops/pre-ride-email-v3-operator-approvals.php">Operator Approvals</a>
        <a href="/ops/pre-ride-email-v3-storage-check.php">Storage Check</a>
        <div class="group">Monitors</div>
        <a href="/ops/pre-ride-email-v3-monitor.php">Compact Monitor</a>
        <a href="/ops/pre-ride-email-v3-queue-focus.php">Queue Focus</a>
        <a href="/ops/pre-ride-email-v3-pulse-focus.php">Pulse Focus</a>
    </aside>
    <main class="main">
        <section class="hero <?= !empty($report['eligible_for_live_submit_now']) ? 'good' : 'bad' ?>">
            <h1>V3 Closed-Gate Adapter Diagnostics</h1>
            <p>Read-only visibility for the future live adapter path. This page explains why the selected row remains blocked before any live EDXEIX submission.</p>
            <div>
                <?= badge('V3 only', 'neutral') ?>
                <?= badge('No EDXEIX call', 'good') ?>
                <?= badge('No AADE call', 'good') ?>
                <?= badge('No DB writes', 'good') ?>
                <?= badge('V0 untouched', 'good') ?>
                <?= !empty($report['eligible_for_live_submit_now']) ? badge('eligible now', 'warn') : badge('live submit blocked', 'bad') ?>
            </div>
            <div class="actions">
                <a class="btn green" href="/ops/pre-ride-email-v3-proof.php">Open Proof</a>
                <a class="btn" href="/ops/pre-ride-email-v3-live-package-export.php">Open Package Export</a>
                <a class="btn amber" href="/ops/pre-ride-email-v3-operator-approvals.php">Open Approvals</a>
                <a class="btn dark" href="/ops/pre-ride-email-v3-live-submit-gate.php">Open Locked Gate</a>
            </div>
        </section>

        <?php if ($error): ?>
            <section class="card"><h2>Error</h2><p><?= h($error) ?></p></section>
        <?php else: ?>
        <section class="grid">
            <div class="metric"><strong><?= h($metrics['total'] ?? '0') ?></strong><span>Total V3 queue rows</span></div>
            <div class="metric"><strong><?= h($metrics['live_ready'] ?? '0') ?></strong><span>Current live-submit-ready rows</span></div>
            <div class="metric"><strong><?= !empty($start['ok']) ? 'OK' : 'NO' ?></strong><span>Selected row starting point</span></div>
            <div class="metric"><strong><?= !empty($approval['queue_valid_like_approval']) ? 'YES' : 'NO' ?></strong><span>Selected row valid approval</span></div>
        </section>

        <section class="two">
            <div class="card">
                <h2>Selected Queue Row</h2>
                <?php if (!$row): ?>
                    <p>No queue row selected.</p>
                <?php else: ?>
                <table class="table">
                    <tr><th>Queue ID / status</th><td>#<?= h(row_value($row, 'id')) ?> <?= status_badge(row_value($row, 'queue_status')) ?></td></tr>
                    <tr><th>Customer</th><td><?= h(row_value($row, 'customer_name')) ?> / <?= h(row_value($row, 'customer_phone')) ?></td></tr>
                    <tr><th>Pickup</th><td><?= h(row_value($row, 'pickup_datetime')) ?></td></tr>
                    <tr><th>Driver / vehicle</th><td><?= h(row_value($row, 'driver_name')) ?> / <?= h(row_value($row, 'vehicle_plate')) ?></td></tr>
                    <tr><th>EDXEIX IDs</th><td>lessor=<?= h(row_value($row, 'lessor_id')) ?> driver=<?= h(row_value($row, 'driver_id')) ?> vehicle=<?= h(row_value($row, 'vehicle_id')) ?> start=<?= h(row_value($row, 'starting_point_id')) ?></td></tr>
                    <tr><th>Starting point</th><td><?= !empty($start['ok']) ? badge('verified', 'good') : badge('not verified', 'bad') ?> <?= h($start['label'] ?? $start['reason'] ?? '') ?></td></tr>
                    <tr><th>Last error</th><td><?= h(row_value($row, 'last_error', '')) ?></td></tr>
                </table>
                <?php endif; ?>
            </div>
            <div class="card">
                <h2>Master Gate State</h2>
                <table class="table">
                    <tr><th>Config loaded</th><td><?= yesno($gate['loaded'] ?? false) ?></td></tr>
                    <tr><th>Enabled</th><td><?= yesno($gate['enabled'] ?? false) ?></td></tr>
                    <tr><th>Mode</th><td><?= h($gate['mode'] ?? '') ?></td></tr>
                    <tr><th>Adapter</th><td><?= h($gate['adapter'] ?? '') ?></td></tr>
                    <tr><th>Hard enable</th><td><?= yesno($gate['hard_enable_live_submit'] ?? false) ?></td></tr>
                    <tr><th>Ack phrase</th><td><?= !empty($gate['acknowledgement_phrase_present']) ? badge('present', 'good') : badge('absent', 'bad') ?></td></tr>
                    <tr><th>OK for live submit</th><td><?= yesno($gate['ok_for_live_submit'] ?? false) ?></td></tr>
                </table>
                <h3>Gate blocks</h3>
                <ul class="list"><?php foreach (($gate['blocks'] ?? []) as $block): ?><li><?= h($block) ?></li><?php endforeach; ?></ul>
            </div>
        </section>

        <section class="two">
            <div class="card">
                <h2>Adapter Wiring</h2>
                <table class="table">
                    <tr><th>Selected adapter</th><td><?= h($adapter['selected_adapter'] ?? '') ?></td></tr>
                    <tr><th>Selected file key</th><td><?= h($adapter['selected_file_key'] ?? '') ?></td></tr>
                    <tr><th>Selected file exists</th><td><?= yesno($adapter['selected_file_exists'] ?? false) ?></td></tr>
                    <?php foreach ($files as $key => $info): ?>
                        <tr><th><?= h($key) ?></th><td><?= !empty($info['exists']) ? badge('exists', 'good') : badge('missing', 'warn') ?> <span class="small"><?= h($info['path'] ?? '') ?></span></td></tr>
                    <?php endforeach; ?>
                </table>
            </div>
            <div class="card">
                <h2>Package Export State</h2>
                <table class="table">
                    <tr><th>Export CLI</th><td><?= !empty($package['cli_exists']) ? badge('present', 'good') : badge('missing', 'bad') ?> <span class="small"><?= h($package['cli_path'] ?? '') ?></span></td></tr>
                    <tr><th>Artifact directory</th><td><?= !empty($package['artifact_dir_exists']) ? badge('exists', 'good') : badge('missing', 'warn') ?> <?= !empty($package['artifact_dir_writable']) ? badge('writable', 'good') : badge('not writable', 'warn') ?></td></tr>
                    <tr><th>Artifacts for selected row</th><td><?= h($package['queue_artifact_count'] ?? 0) ?></td></tr>
                </table>
                <?php if (!empty($package['latest_queue_artifacts'])): ?>
                    <div class="code"><?= h(implode("\n", $package['latest_queue_artifacts'])) ?></div>
                <?php endif; ?>
            </div>
        </section>

        <section class="two">
            <div class="card">
                <h2>Required Fields</h2>
                <p><?= empty($missing) ? badge('missing required fields: none', 'good') : badge('missing required fields', 'bad') ?></p>
                <?php if (!empty($missing)): ?><p><?= h(implode(', ', $missing)) ?></p><?php endif; ?>
                <table class="table">
                    <?php foreach ($values as $key => $value): ?>
                        <tr><th><?= h($key) ?></th><td><?= trim((string)$value) !== '' ? h($value) : badge('missing', 'bad') ?></td></tr>
                    <?php endforeach; ?>
                </table>
            </div>
            <div class="card">
                <h2>Operator Approval</h2>
                <table class="table">
                    <tr><th>Approval table</th><td><?= !empty($approval['table_exists']) ? badge('exists', 'good') : badge('missing', 'bad') ?></td></tr>
                    <tr><th>Total approvals</th><td><?= h($approval['total'] ?? 0) ?></td></tr>
                    <tr><th>Valid-like approvals</th><td><?= h($approval['valid_like_total'] ?? 0) ?></td></tr>
                    <tr><th>Selected row has approval</th><td><?= yesno($approval['queue_has_approval'] ?? false) ?></td></tr>
                    <tr><th>Selected row valid approval</th><td><?= yesno($approval['queue_valid_like_approval'] ?? false) ?></td></tr>
                </table>
                <?php if (!empty($approval['latest'])): ?>
                    <div class="code"><?= h(json_encode($approval['latest'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?></div>
                <?php endif; ?>
            </div>
        </section>

        <section class="card">
            <h2>Final Submit Blocks</h2>
            <p>This diagnostic is expected to be blocked while the V3 master gate remains closed.</p>
            <?php if (!$blocks): ?>
                <p><?= badge('No blocks detected — do not proceed without explicit approval', 'warn') ?></p>
            <?php else: ?>
                <ul class="list"><?php foreach ($blocks as $block): ?><li><?= h($block) ?></li><?php endforeach; ?></ul>
            <?php endif; ?>
        </section>
        <?php endif; ?>
    </main>
</div>
</body>
</html>
