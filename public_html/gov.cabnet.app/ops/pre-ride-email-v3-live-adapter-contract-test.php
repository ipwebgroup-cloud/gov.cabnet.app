<?php

declare(strict_types=1);

foreach ([__DIR__ . '/_ops-auth.php', __DIR__ . '/ops-auth.php', __DIR__ . '/_auth.php'] as $authFile) {
    if (is_readable($authFile)) {
        require_once $authFile;
        break;
    }
}

$homeRoot = dirname(__DIR__, 3);
$cliPath = $homeRoot . '/gov.cabnet.app_app/cli/pre_ride_email_v3_live_adapter_contract_test.php';
if (!is_readable($cliPath)) {
    http_response_code(500);
    echo 'V3 live adapter contract test CLI is missing or unreadable.';
    exit;
}
require_once $cliPath;

function v3ct_ops_h(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function v3ct_ops_badge(bool $ok, string $yes = 'yes', string $no = 'no', string $warnClass = 'bad'): string
{
    $class = $ok ? 'ok' : $warnClass;
    return '<span class="badge ' . $class . '">' . v3ct_ops_h($ok ? $yes : $no) . '</span>';
}

$queueId = isset($_GET['queue_id']) ? (int)$_GET['queue_id'] : null;
$report = v3ct_run($queueId);
$row = is_array($report['selected_queue_row'] ?? null) ? $report['selected_queue_row'] : [];
$payload = is_array($report['payload'] ?? null) ? $report['payload'] : [];
$payloadFields = is_array($payload['fields'] ?? null) ? $payload['fields'] : [];
$contract = is_array($report['request_contract'] ?? null) ? $report['request_contract'] : [];
$headers = is_array($contract['headers_without_secrets'] ?? null) ? $contract['headers_without_secrets'] : [];
$timeouts = is_array($contract['timeout_policy'] ?? null) ? $contract['timeout_policy'] : [];
$idempotency = is_array($contract['idempotency'] ?? null) ? $contract['idempotency'] : [];
$responseContract = is_array($contract['response_normalization_contract'] ?? null) ? $contract['response_normalization_contract'] : [];
$preconditions = is_array($contract['future_live_preconditions'] ?? null) ? $contract['future_live_preconditions'] : [];
$adapter = is_array($report['adapter_probe'] ?? null) ? $report['adapter_probe'] : [];
$starting = is_array($report['starting_point'] ?? null) ? $report['starting_point'] : [];
$approval = is_array($report['approval'] ?? null) ? $report['approval'] : [];
$gateConfig = is_array($report['gate_config'] ?? null) ? $report['gate_config'] : [];
$gate = is_array($gateConfig['safe'] ?? null) ? $gateConfig['safe'] : [];
$blocks = is_array($report['final_blocks'] ?? null) ? $report['final_blocks'] : [];

?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>V3 Live Adapter Contract Test</title>
    <style>
        :root{--nav:#2f3a63;--ink:#061a44;--muted:#42527a;--bg:#eef3fb;--card:#fff;--line:#d7dfef;--good:#dff5e8;--goodText:#05712b;--bad:#ffe4e4;--badText:#9c1717;--warn:#fff5df;--warnText:#8a5200;--blue:#5262b6;--green:#34a853;--orange:#d99020;--purple:#6649b8}
        *{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--ink);font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;font-size:14px}.layout{display:grid;grid-template-columns:250px 1fr;min-height:100vh}.side{background:var(--nav);color:#fff;padding:24px 16px}.side h1{font-size:18px;margin:0 0 10px}.side p{font-size:13px;line-height:1.45;margin:0 0 22px}.side a{display:block;color:#fff;text-decoration:none;padding:10px 12px;border-radius:8px;margin:4px 0}.side a.active,.side a:hover{background:rgba(255,255,255,.14)}.main{padding:28px 28px 60px}.hero,.card,.notice{background:var(--card);border:1px solid var(--line);border-radius:16px;box-shadow:0 1px 2px rgba(0,0,0,.03)}.hero{padding:24px;border-left:5px solid var(--purple);margin-bottom:16px}.hero.ok{border-left-color:var(--green)}.hero h2{font-size:28px;line-height:1.1;margin:8px 0 8px}.sub{color:var(--muted)}.badges{display:flex;flex-wrap:wrap;gap:8px}.badge{display:inline-flex;align-items:center;border-radius:999px;padding:5px 10px;font-size:12px;font-weight:800;text-transform:uppercase}.badge.ok{background:var(--good);color:var(--goodText)}.badge.bad{background:var(--bad);color:var(--badText)}.badge.warn{background:var(--warn);color:var(--warnText)}.actions{display:flex;flex-wrap:wrap;gap:10px;margin-top:16px}.btn{display:inline-block;border-radius:8px;padding:10px 14px;text-decoration:none;font-weight:800;background:var(--blue);color:#fff}.btn.green{background:var(--green)}.btn.dark{background:#18234f}.grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin:16px 0}.metric{background:var(--card);border:1px solid var(--line);border-radius:14px;padding:18px}.metric strong{display:block;font-size:24px;line-height:1.1}.metric span{display:block;color:var(--muted);font-size:13px;margin-top:5px}.two{display:grid;grid-template-columns:1fr 1fr;gap:12px}.card{padding:18px;margin-bottom:12px}.card h3{margin:0 0 12px;font-size:18px}table{width:100%;border-collapse:collapse}td,th{border-bottom:1px solid var(--line);padding:9px;text-align:left;vertical-align:top}td:first-child{background:#f5f7fb;color:var(--muted);font-weight:700;width:220px}.notice{padding:14px 18px;margin:14px 0;background:#fff8ee;border-color:#ffc878;color:#803e00}.mono{font-family:ui-monospace,SFMono-Regular,Consolas,monospace;font-size:12px;word-break:break-all}ul{margin:8px 0 0 20px;padding:0}.small{font-size:12px;color:var(--muted)}@media(max-width:900px){.layout{grid-template-columns:1fr}.side{position:static}.grid,.two{grid-template-columns:1fr}.main{padding:16px}}
    </style>
</head>
<body>
<div class="layout">
    <aside class="side">
        <h1>V3 Automation</h1>
        <p>Read-only contract test. It defines the future EDXEIX request envelope without calling EDXEIX or adapter submit.</p>
        <a href="/ops/pre-ride-email-v3-live-operator-console.php<?= $row ? '?queue_id=' . (int)$row['id'] : '' ?>">Live Operator Console</a>
        <a href="/ops/pre-ride-email-v3-pre-live-switchboard.php<?= $row ? '?queue_id=' . (int)$row['id'] : '' ?>">Pre-Live Switchboard</a>
        <a href="/ops/pre-ride-email-v3-adapter-row-simulation.php<?= $row ? '?queue_id=' . (int)$row['id'] : '' ?>">Adapter Simulation</a>
        <a href="/ops/pre-ride-email-v3-adapter-payload-consistency.php<?= $row ? '?queue_id=' . (int)$row['id'] : '' ?>">Payload Consistency</a>
        <a class="active" href="/ops/pre-ride-email-v3-live-adapter-contract-test.php<?= $row ? '?queue_id=' . (int)$row['id'] : '' ?>">Live Adapter Contract</a>
        <a href="/ops/pre-ride-email-v3-live-gate-drift-guard.php">Gate Drift Guard</a>
    </aside>
    <main class="main">
        <section class="hero <?= !empty($report['ok']) ? 'ok' : '' ?>">
            <div class="badges">
                <?= v3ct_ops_badge(!empty($report['ok']), 'contract ok', 'contract blocked') ?>
                <?= v3ct_ops_badge(!empty($report['contract_safe']), 'contract safe', 'contract unsafe') ?>
                <?= v3ct_ops_badge(empty($contract['network_allowed']), 'network disabled', 'network enabled') ?>
                <?= v3ct_ops_badge(empty($contract['adapter_submit_called']), 'adapter submit not called', 'adapter submit called') ?>
                <?= v3ct_ops_badge(empty($adapter['is_live_capable']), 'adapter not live', 'adapter live capable') ?>
            </div>
            <h2>V3 Live Adapter Contract Test</h2>
            <div class="sub"><?= v3ct_ops_h((string)($report['safety'] ?? '')) ?></div>
            <div class="actions">
                <a class="btn green" href="/ops/pre-ride-email-v3-live-adapter-contract-test.php<?= $row ? '?queue_id=' . (int)$row['id'] : '' ?>">Refresh selected row</a>
                <a class="btn" href="/ops/pre-ride-email-v3-adapter-payload-consistency.php<?= $row ? '?queue_id=' . (int)$row['id'] : '' ?>">Payload Consistency</a>
                <a class="btn dark" href="/ops/pre-ride-email-v3-live-operator-console.php<?= $row ? '?queue_id=' . (int)$row['id'] : '' ?>">Operator Console</a>
            </div>
        </section>

        <section class="grid">
            <div class="metric"><strong>#<?= v3ct_ops_h($row['id'] ?? '-') ?></strong><span><?= v3ct_ops_h($row['queue_status'] ?? '-') ?> · pickup <?= v3ct_ops_h($row['pickup_datetime'] ?? '-') ?></span></div>
            <div class="metric"><strong><?= !empty($payload['complete']) ? 'complete' : 'missing' ?></strong><span>Would-be EDXEIX payload</span></div>
            <div class="metric"><strong><?= empty($contract['network_allowed']) ? 'disabled' : 'enabled' ?></strong><span>Network path in this test</span></div>
            <div class="metric"><strong><?= empty($contract['adapter_submit_called']) ? 'not called' : 'called' ?></strong><span>Adapter submit path</span></div>
        </section>

        <?php if ($blocks): ?>
        <section class="notice">
            <strong>Final blocks</strong>
            <ul><?php foreach ($blocks as $block): ?><li><?= v3ct_ops_h($block) ?></li><?php endforeach; ?></ul>
        </section>
        <?php endif; ?>

        <section class="two">
            <div class="card">
                <h3>Selected Queue Row</h3>
                <table>
                    <tr><td>Customer</td><td><?= v3ct_ops_h($row['customer_name'] ?? '') ?> / <?= v3ct_ops_h($row['customer_phone'] ?? '') ?></td></tr>
                    <tr><td>Driver / Vehicle</td><td><?= v3ct_ops_h($row['driver_name'] ?? '') ?> / <?= v3ct_ops_h($row['vehicle_plate'] ?? '') ?></td></tr>
                    <tr><td>EDXEIX IDs</td><td>lessor=<?= v3ct_ops_h($row['lessor_id'] ?? '') ?> driver=<?= v3ct_ops_h($row['driver_id'] ?? '') ?> vehicle=<?= v3ct_ops_h($row['vehicle_id'] ?? '') ?> start=<?= v3ct_ops_h($row['starting_point_id'] ?? '') ?></td></tr>
                    <tr><td>Route</td><td><?= v3ct_ops_h($row['pickup_address'] ?? '') ?> → <?= v3ct_ops_h($row['dropoff_address'] ?? '') ?></td></tr>
                    <tr><td>Payload hash</td><td class="mono"><?= v3ct_ops_h($payload['hash_sha256'] ?? '') ?></td></tr>
                </table>
            </div>
            <div class="card">
                <h3>Safety Checks</h3>
                <table>
                    <tr><td>Gate loaded</td><td><?= v3ct_ops_badge(!empty($gateConfig['loaded']), 'yes', 'no') ?> <?= v3ct_ops_h($gateConfig['path'] ?? '') ?></td></tr>
                    <tr><td>Gate posture</td><td>enabled=<?= !empty($gate['enabled']) ? 'yes' : 'no' ?> · mode=<?= v3ct_ops_h($gate['mode'] ?? '') ?> · adapter=<?= v3ct_ops_h($gate['adapter'] ?? '') ?> · hard=<?= !empty($gate['hard_enable_live_submit']) ? 'yes' : 'no' ?></td></tr>
                    <tr><td>Starting point</td><td><?= v3ct_ops_badge(!empty($starting['ok']), 'verified', 'not verified') ?> <?= v3ct_ops_h($starting['label'] ?? '') ?></td></tr>
                    <tr><td>Approval</td><td><?= v3ct_ops_badge(!empty($approval['ok']), 'valid', 'not valid') ?> <?= v3ct_ops_h($approval['reason'] ?? '') ?></td></tr>
                    <tr><td>Adapter</td><td><?= v3ct_ops_h($adapter['name'] ?? '') ?> · live-capable=<?= !empty($adapter['is_live_capable']) ? 'yes' : 'no' ?> · submit-called=<?= !empty($adapter['submit_called']) ? 'yes' : 'no' ?></td></tr>
                    <tr><td>Version</td><td><?= v3ct_ops_h($report['version'] ?? '') ?></td></tr>
                </table>
            </div>
        </section>

        <section class="two">
            <div class="card">
                <h3>Future Request Envelope</h3>
                <table>
                    <tr><td>Network allowed</td><td><?= v3ct_ops_badge(!empty($contract['network_allowed']), 'yes', 'no', 'ok') ?></td></tr>
                    <tr><td>Adapter submit allowed</td><td><?= v3ct_ops_badge(!empty($contract['adapter_submit_allowed']), 'yes', 'no', 'ok') ?></td></tr>
                    <tr><td>Method</td><td><?= v3ct_ops_h($contract['method'] ?? '') ?></td></tr>
                    <tr><td>Endpoint label</td><td><?= v3ct_ops_h($contract['endpoint_label'] ?? '') ?></td></tr>
                    <tr><td>Endpoint URL</td><td><?= v3ct_ops_h($contract['endpoint_url_redacted'] ?? '') ?></td></tr>
                    <tr><td>Payload SHA-256</td><td class="mono"><?= v3ct_ops_h($contract['payload_sha256'] ?? '') ?></td></tr>
                </table>
            </div>
            <div class="card">
                <h3>Headers Without Secrets</h3>
                <table>
                    <?php foreach ($headers as $key => $value): ?>
                    <tr><td><?= v3ct_ops_h($key) ?></td><td class="mono"><?= v3ct_ops_h($value) ?></td></tr>
                    <?php endforeach; ?>
                </table>
            </div>
        </section>

        <section class="two">
            <div class="card">
                <h3>Timeout / Idempotency Contract</h3>
                <table>
                    <?php foreach ($timeouts as $key => $value): ?>
                    <tr><td><?= v3ct_ops_h($key) ?></td><td><?= v3ct_ops_h(is_bool($value) ? ($value ? 'true' : 'false') : $value) ?></td></tr>
                    <?php endforeach; ?>
                    <?php foreach ($idempotency as $key => $value): ?>
                    <tr><td><?= v3ct_ops_h($key) ?></td><td class="mono"><?= v3ct_ops_h($value) ?></td></tr>
                    <?php endforeach; ?>
                </table>
            </div>
            <div class="card">
                <h3>Future Live Preconditions</h3>
                <table>
                    <?php foreach ($preconditions as $key => $value): ?>
                    <tr><td><?= v3ct_ops_h($key) ?></td><td><?= v3ct_ops_h(is_bool($value) ? ($value ? 'true' : 'false') : $value) ?></td></tr>
                    <?php endforeach; ?>
                </table>
            </div>
        </section>

        <section class="two">
            <div class="card">
                <h3>Would-be EDXEIX Payload</h3>
                <table>
                    <?php foreach ($payloadFields as $key => $value): ?>
                    <tr><td><?= v3ct_ops_h($key) ?></td><td><?= v3ct_ops_h($value) ?></td></tr>
                    <?php endforeach; ?>
                </table>
            </div>
            <div class="card">
                <h3>Response Normalization Contract</h3>
                <table>
                    <?php foreach ($responseContract as $key => $value): ?>
                    <tr><td><?= v3ct_ops_h($key) ?></td><td><?= v3ct_ops_h($value) ?></td></tr>
                    <?php endforeach; ?>
                </table>
                <p class="small">Raw EDXEIX response bodies must not be logged by default. Store only a hash and safe normalized fields unless Andreas explicitly approves a diagnostic exception.</p>
            </div>
        </section>
    </main>
</div>
</body>
</html>
