<?php
/**
 * V3 Adapter Row Simulation Ops page.
 *
 * Direct PHP renderer. No shell_exec/exec/local command runner.
 * Calls the local V3 adapter skeleton with a selected queue row package only.
 * The adapter skeleton must remain non-live-capable and must return submitted=false.
 */

declare(strict_types=1);

if (!headers_sent()) {
    header('Content-Type: text/html; charset=UTF-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('X-Robots-Tag: noindex, nofollow', true);
}

const V3_ADAPTER_ROW_SIM_PAGE_VERSION = 'v3.0.67-v3-adapter-row-simulation';
const V3_ADAPTER_ROW_SIM_CLI = '/home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_adapter_row_simulation.php';

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function badge(string $text, string $type = 'neutral'): string
{
    $classes = [
        'good' => 'badge good',
        'bad' => 'badge bad',
        'warn' => 'badge warn',
        'neutral' => 'badge neutral',
        'dark' => 'badge dark',
    ];
    return '<span class="' . h($classes[$type] ?? $classes['neutral']) . '">' . h($text) . '</span>';
}

function yesno($value): string
{
    if ($value === null) {
        return badge('n/a', 'neutral');
    }
    return !empty($value) ? badge('yes', 'good') : badge('no', 'bad');
}

$queueIdRaw = isset($_GET['queue_id']) ? trim((string)$_GET['queue_id']) : '';
$queueId = $queueIdRaw !== '' && ctype_digit($queueIdRaw) ? (int)$queueIdRaw : null;

$report = [
    'ok' => false,
    'simulation_safe' => false,
    'version' => V3_ADAPTER_ROW_SIM_PAGE_VERSION,
    'mode' => 'ops_page_error',
    'safety' => 'No Bolt call. No EDXEIX call. No AADE call. No DB writes. No queue status changes. No production submission tables. V0 untouched.',
    'events' => [],
    'final_blocks' => ['ops_page: simulation CLI not loaded'],
];

if (is_file(V3_ADAPTER_ROW_SIM_CLI) && is_readable(V3_ADAPTER_ROW_SIM_CLI)) {
    try {
        require_once V3_ADAPTER_ROW_SIM_CLI;
        if (function_exists('v3sim_run')) {
            $report = v3sim_run($queueId);
            $report['finished_at'] = gmdate('c');
        } else {
            $report['events'][] = ['level' => 'error', 'message' => 'v3sim_run() not available after include.'];
        }
    } catch (Throwable $e) {
        $report['events'][] = ['level' => 'error', 'message' => $e->getMessage()];
        $report['final_blocks'][] = 'ops_page: ' . $e->getMessage();
    }
} else {
    $report['events'][] = ['level' => 'error', 'message' => 'Simulation CLI missing or unreadable: ' . V3_ADAPTER_ROW_SIM_CLI];
}

$row = is_array($report['selected_queue_row'] ?? null) ? $report['selected_queue_row'] : [];
$gate = is_array($report['gate'] ?? null) ? $report['gate'] : [];
$payload = is_array($report['payload'] ?? null) ? $report['payload'] : [];
$start = is_array($report['starting_point'] ?? null) ? $report['starting_point'] : [];
$approval = is_array($report['approval'] ?? null) ? $report['approval'] : [];
$sim = is_array($report['adapter_simulation'] ?? null) ? $report['adapter_simulation'] : [];
$pkg = is_array($report['package_export'] ?? null) ? $report['package_export'] : [];
$edxeixPayload = is_array($report['edxeix_payload'] ?? null) ? $report['edxeix_payload'] : [];
$blocks = (array)($report['final_blocks'] ?? []);
$events = (array)($report['events'] ?? []);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>V3 Adapter Row Simulation | gov.cabnet.app</title>
<style>
:root{--bg:#eef3fa;--panel:#fff;--ink:#071a3d;--muted:#42577f;--line:#d5e0f0;--nav:#30395f;--green:#4caf64;--red:#d9534f;--amber:#d69226;--blue:#5563b7;--dark:#162041;--soft:#f7faff}*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--ink);font-family:Arial,Helvetica,sans-serif}.wrap{display:grid;grid-template-columns:260px 1fr;min-height:100vh}.side{background:var(--nav);color:#fff;padding:24px 18px}.side h2{margin:0 0 8px;font-size:18px}.side p{color:#dce5ff;font-size:14px;line-height:1.4}.side a{display:block;color:#fff;text-decoration:none;padding:10px;border-radius:8px;margin:4px 0}.side a.active,.side a:hover{background:rgba(255,255,255,.14)}.main{padding:24px}.hero,.card,.metric{background:var(--panel);border:1px solid var(--line);border-radius:14px;box-shadow:0 8px 24px rgba(24,39,72,.04)}.hero{padding:22px;border-left:6px solid var(--amber);margin-bottom:16px}.hero.good{border-left-color:var(--green)}h1{font-size:34px;margin:8px 0 8px}h2{margin:0 0 14px}.actions a{display:inline-block;text-decoration:none;background:var(--blue);color:white;font-weight:800;padding:10px 14px;border-radius:8px;margin:4px}.actions a.green{background:var(--green)}.actions a.dark{background:var(--dark)}.grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-bottom:16px}.metric{padding:16px}.metric strong{font-size:30px;display:block;line-height:1.1}.metric span{color:var(--muted);font-size:13px}.card{padding:18px;margin-bottom:16px}.two{display:grid;grid-template-columns:1fr 1fr;gap:14px}.table{width:100%;border-collapse:collapse}.table th,.table td{border-bottom:1px solid var(--line);padding:9px 10px;text-align:left;vertical-align:top}.table th{background:#f4f7fc;color:#34486f}.badge{display:inline-block;border-radius:999px;padding:5px 9px;font-size:12px;font-weight:800;margin:2px}.badge.good{background:#dcfce7;color:#166534}.badge.bad{background:#fee2e2;color:#991b1b}.badge.warn{background:#fff7ed;color:#9a5a00}.badge.neutral{background:#eaf1ff;color:#1e3a8a}.badge.dark{background:#e7e9f3;color:#17214b}.code{font-family:Consolas,Monaco,monospace;font-size:12px;background:#f7faff;border:1px solid var(--line);border-radius:8px;padding:10px;white-space:pre-wrap;overflow:auto}.blockbox{background:#fff8ef;border:1px solid #f5be74;border-radius:12px;padding:14px}.small{font-size:12px;color:var(--muted)}@media(max-width:1000px){.wrap{grid-template-columns:1fr}.grid,.two{grid-template-columns:1fr}.main{padding:14px}}
</style>
</head>
<body>
<div class="wrap">
    <aside class="side">
        <h2>V3 Automation</h2>
        <p>Read-only adapter row simulation. The future adapter skeleton must stay non-live-capable.</p>
        <a href="/ops/pre-ride-email-v3-pre-live-switchboard.php">Pre-Live Switchboard</a>
        <a class="active" href="/ops/pre-ride-email-v3-adapter-row-simulation.php">Adapter Simulation</a>
        <a href="/ops/pre-ride-email-v3-live-adapter-kill-switch-check.php">Kill-Switch Check</a>
        <a href="/ops/pre-ride-email-v3-adapter-contract-probe.php">Adapter Contract Probe</a>
        <a href="/ops/pre-ride-email-v3-proof.php">Proof Dashboard</a>
        <a href="/ops/pre-ride-email-v3-storage-check.php">Storage Check</a>
    </aside>
    <main class="main">
        <section class="hero <?= !empty($report['simulation_safe']) ? 'good' : '' ?>">
            <?= !empty($report['simulation_safe']) ? badge('SIMULATION SAFE', 'good') : badge('CHECK REQUIRED', 'warn') ?>
            <?= !empty($sim['submitted']) ? badge('SUBMITTED TRUE - UNSAFE', 'bad') : badge('SUBMITTED FALSE', 'good') ?>
            <?= !empty($sim['is_live_capable']) ? badge('LIVE CAPABLE - UNSAFE', 'bad') : badge('NOT LIVE CAPABLE', 'good') ?>
            <?= badge('NO EDXEIX CALL', 'good') ?>
            <?= badge('V0 UNTOUCHED', 'good') ?>
            <h1>V3 Adapter Row Simulation</h1>
            <p><?= h((string)($report['safety'] ?? '')) ?></p>
            <div class="actions">
                <a class="green" href="/ops/pre-ride-email-v3-adapter-row-simulation.php<?= !empty($row['id']) ? '?queue_id=' . h((string)$row['id']) : '' ?>">Refresh selected row</a>
                <a href="/ops/pre-ride-email-v3-pre-live-switchboard.php">Pre-Live Switchboard</a>
                <a class="dark" href="/ops/pre-ride-email-v3-adapter-contract-probe.php">Contract Probe</a>
            </div>
        </section>

        <section class="grid">
            <div class="metric"><strong><?= !empty($report['simulation_safe']) ? 'OK' : 'CHECK' ?></strong><span>Simulation safety</span></div>
            <div class="metric"><strong>#<?= h((string)($row['id'] ?? '-')) ?></strong><span><?= h((string)($row['queue_status'] ?? 'no row')) ?> · pickup <?= h((string)($row['pickup_datetime'] ?? '-')) ?></span></div>
            <div class="metric"><strong><?= !empty($sim['submitted']) ? 'YES' : 'NO' ?></strong><span>Adapter returned submitted=true</span></div>
            <div class="metric"><strong><?= h((string)($pkg['queue_artifact_count'] ?? '0')) ?></strong><span>Package artifacts for row</span></div>
        </section>

        <?php if ($blocks !== []): ?>
        <section class="blockbox card">
            <h2>Final blocks / safety controls</h2>
            <ul>
                <?php foreach ($blocks as $block): ?><li><?= h((string)$block) ?></li><?php endforeach; ?>
            </ul>
        </section>
        <?php endif; ?>

        <section class="two">
            <div class="card">
                <h2>Queue Row</h2>
                <table class="table">
                    <tr><th>Customer</th><td><?= h((string)($row['customer_name'] ?? '')) ?> / <?= h((string)($row['customer_phone'] ?? '')) ?></td></tr>
                    <tr><th>Driver / Vehicle</th><td><?= h((string)($row['driver_name'] ?? '')) ?> / <?= h((string)($row['vehicle_plate'] ?? '')) ?></td></tr>
                    <tr><th>EDXEIX IDs</th><td>lessor=<?= h((string)($row['lessor_id'] ?? '')) ?> driver=<?= h((string)($row['driver_id'] ?? '')) ?> vehicle=<?= h((string)($row['vehicle_id'] ?? '')) ?> start=<?= h((string)($row['starting_point_id'] ?? '')) ?></td></tr>
                    <tr><th>Route</th><td><?= h((string)($row['pickup_address'] ?? '')) ?> → <?= h((string)($row['dropoff_address'] ?? '')) ?></td></tr>
                    <tr><th>Time</th><td><?= h((string)($row['pickup_datetime'] ?? '')) ?> → <?= h((string)($row['estimated_end_datetime'] ?? '')) ?> · minutes <?= h((string)($row['minutes_until_now'] ?? '')) ?></td></tr>
                    <tr><th>Last error</th><td><?= h((string)($row['last_error'] ?? '')) ?></td></tr>
                </table>
            </div>
            <div class="card">
                <h2>Checks</h2>
                <table class="table">
                    <tr><th>Gate</th><td><?= h((string)($gate['mode'] ?? '-')) ?> / <?= h((string)($gate['adapter'] ?? '-')) ?> · enabled=<?= h(!empty($gate['enabled']) ? 'yes' : 'no') ?> hard=<?= h(!empty($gate['hard_enable_live_submit']) ? 'yes' : 'no') ?></td></tr>
                    <tr><th>Payload</th><td><?= !empty($payload['complete']) ? badge('complete', 'good') : badge('missing', 'bad') ?> <?= h(implode(', ', (array)($payload['missing'] ?? [])) ?: 'none missing') ?></td></tr>
                    <tr><th>Starting point</th><td><?= !empty($start['ok']) ? badge('verified', 'good') : badge('not verified', 'bad') ?> <?= h((string)($start['label'] ?? ($start['reason'] ?? ''))) ?></td></tr>
                    <tr><th>Approval</th><td><?= !empty($approval['valid']) ? badge('valid', 'good') : badge('not valid', 'bad') ?> count=<?= h((string)($approval['count'] ?? '0')) ?> <?= h((string)($approval['reason'] ?? '')) ?></td></tr>
                    <tr><th>Adapter simulation</th><td><?= !empty($sim['safe_for_simulation']) ? badge('safe', 'good') : badge('not safe', 'bad') ?> live-capable=<?= h(!empty($sim['is_live_capable']) ? 'yes' : 'no') ?> submitted=<?= h(!empty($sim['submitted']) ? 'yes' : 'no') ?></td></tr>
                    <tr><th>Version</th><td><?= h((string)($report['version'] ?? V3_ADAPTER_ROW_SIM_PAGE_VERSION)) ?></td></tr>
                </table>
            </div>
        </section>

        <section class="card">
            <h2>Adapter Result</h2>
            <table class="table">
                <tr><th>Class</th><td class="code"><?= h((string)($sim['class'] ?? '')) ?></td></tr>
                <tr><th>Name</th><td><?= h((string)($sim['name'] ?? '')) ?></td></tr>
                <tr><th>Class exists / instantiated</th><td><?= yesno($sim['class_exists'] ?? null) ?> <?= yesno($sim['instantiated'] ?? null) ?></td></tr>
                <tr><th>Submit called / returned</th><td><?= yesno($sim['submit_called'] ?? null) ?> <?= yesno($sim['submit_returned'] ?? null) ?></td></tr>
                <tr><th>Reason</th><td><?= h((string)($sim['reason'] ?? '')) ?></td></tr>
                <tr><th>Message</th><td><?= h((string)($sim['message'] ?? '')) ?></td></tr>
                <tr><th>Payload SHA256</th><td class="code"><?= h((string)($sim['payload_sha256'] ?? '')) ?></td></tr>
                <tr><th>Error</th><td><?= h((string)($sim['error'] ?? '')) ?></td></tr>
            </table>
        </section>

        <section class="two">
            <div class="card">
                <h2>EDXEIX Field Package</h2>
                <table class="table">
                    <?php foreach ($edxeixPayload as $key => $value): ?>
                        <tr><th><?= h((string)$key) ?></th><td><?= h((string)$value) ?></td></tr>
                    <?php endforeach; ?>
                </table>
            </div>
            <div class="card">
                <h2>Package Artifacts</h2>
                <p class="small"><?= h((string)($pkg['artifact_dir'] ?? '')) ?></p>
                <ul>
                    <?php foreach ((array)($pkg['latest_queue_artifacts'] ?? []) as $file): ?><li class="code"><?= h((string)$file) ?></li><?php endforeach; ?>
                </ul>
            </div>
        </section>

        <?php if ($events !== []): ?>
        <section class="card">
            <h2>Events</h2>
            <ul>
                <?php foreach ($events as $event): ?>
                    <li><?= h(is_array($event) ? (($event['level'] ?? 'info') . ': ' . ($event['message'] ?? '')) : (string)$event) ?></li>
                <?php endforeach; ?>
            </ul>
        </section>
        <?php endif; ?>
    </main>
</div>
</body>
</html>
