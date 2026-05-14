<?php
/**
 * V3 live adapter kill-switch check page.
 * Read-only Ops view. It delegates to the V3 CLI checker and performs no writes.
 */

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Robots-Tag: noindex, nofollow', true);

foreach ([__DIR__ . '/_ops-auth.php', __DIR__ . '/ops-auth.php', __DIR__ . '/auth.php', __DIR__ . '/_auth.php'] as $authFile) {
    if (is_file($authFile)) {
        require_once $authFile;
        break;
    }
}

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function badge(string $label, string $kind = 'neutral'): string
{
    return '<span class="badge badge-' . h($kind) . '">' . h($label) . '</span>';
}

function run_kill_switch_cli(): array
{
    $queueId = isset($_GET['queue_id']) && ctype_digit((string)$_GET['queue_id']) ? (string)$_GET['queue_id'] : '';
    $cmd = '/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_live_adapter_kill_switch_check.php --json';
    if ($queueId !== '') {
        $cmd .= ' --queue-id=' . escapeshellarg($queueId);
    }
    $cmd .= ' 2>&1';

    if (!function_exists('shell_exec')) {
        return ['ok' => false, 'raw' => '', 'error' => 'shell_exec is disabled', 'cmd' => $cmd, 'json' => null];
    }

    $raw = shell_exec($cmd);
    $raw = is_string($raw) ? $raw : '';
    $json = json_decode($raw, true);
    return [
        'ok' => is_array($json),
        'raw' => $raw,
        'error' => is_array($json) ? '' : 'CLI output was not valid JSON',
        'cmd' => $cmd,
        'json' => is_array($json) ? $json : null,
    ];
}

$result = run_kill_switch_cli();
$data = is_array($result['json']) ? $result['json'] : [];
$blocks = isset($data['final_blocks']) && is_array($data['final_blocks']) ? $data['final_blocks'] : [];
$row = isset($data['selected_queue_row']) && is_array($data['selected_queue_row']) ? $data['selected_queue_row'] : [];
$gate = isset($data['live_submit_config']['safe']) && is_array($data['live_submit_config']['safe']) ? $data['live_submit_config']['safe'] : [];
$adapter = isset($data['adapter']) && is_array($data['adapter']) ? $data['adapter'] : [];
$approval = isset($data['approval']) && is_array($data['approval']) ? $data['approval'] : [];
$start = isset($data['starting_point']) && is_array($data['starting_point']) ? $data['starting_point'] : [];
$required = isset($data['required_fields']['missing']) && is_array($data['required_fields']['missing']) ? $data['required_fields']['missing'] : [];
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>V3 Live Adapter Kill-Switch Check | gov.cabnet.app</title>
<style>
:root{--bg:#f3f5fa;--panel:#fff;--ink:#1f2d4d;--muted:#5a6785;--line:#d9deea;--nav:#2f3659;--blue:#5563b7;--green:#198754;--amber:#b7791f;--red:#b42318;--shadow:0 8px 24px rgba(31,45,77,.08)}
*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--ink);font-family:Arial,Helvetica,sans-serif}.top{background:#fff;border-bottom:1px solid var(--line);padding:16px 24px;display:flex;justify-content:space-between;gap:16px;align-items:center;position:sticky;top:0;z-index:3}.brand{font-weight:800;color:var(--nav)}.wrap{width:min(1500px,calc(100% - 40px));margin:24px auto 60px}.grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}.two{display:grid;grid-template-columns:1fr 1fr;gap:16px}.card{background:var(--panel);border:1px solid var(--line);border-radius:14px;padding:18px;box-shadow:var(--shadow);margin-bottom:16px}.hero{border-left:7px solid var(--red)}.hero.ok{border-left-color:var(--green)}h1{margin:0 0 8px;font-size:30px}h2{margin:0 0 12px;font-size:20px}.muted{color:var(--muted)}.metric strong{display:block;font-size:28px;line-height:1}.metric span{color:var(--muted);font-size:13px}.badge{display:inline-block;padding:5px 9px;border-radius:999px;font-size:12px;font-weight:800;margin:2px 4px 2px 0}.badge-good{background:#dcfce7;color:#166534}.badge-bad{background:#fee2e2;color:#991b1b}.badge-warn{background:#fff7ed;color:#b45309}.badge-neutral{background:#eaf1ff;color:#1e40af}.badge-info{background:#e0f2fe;color:#075985}.btn{display:inline-block;background:var(--blue);color:#fff;text-decoration:none;border-radius:8px;padding:10px 13px;font-weight:700;margin:3px}.btn.gray{background:#475569}.list{margin:0;padding-left:18px}.list li{margin:7px 0}.blocks li{color:#991b1b;font-weight:700}.okline{color:#166534;font-weight:700}.badline{color:#991b1b;font-weight:700}.tablewrap{overflow:auto}table{border-collapse:collapse;width:100%;font-size:14px}th,td{border-bottom:1px solid var(--line);padding:9px 8px;text-align:left;vertical-align:top}th{background:#f8fafc;color:#334155}code,pre{background:#f8fafc;border:1px solid var(--line);border-radius:8px}code{padding:2px 5px}pre{padding:12px;overflow:auto;max-height:420px}@media(max-width:1000px){.grid,.two{grid-template-columns:1fr}.wrap{width:calc(100% - 24px)}}
</style>
</head>
<body>
<header class="top">
    <div class="brand">gov.cabnet.app · V3 Automation</div>
    <nav>
        <a class="btn gray" href="/ops/pre-ride-email-v3-proof.php">Proof</a>
        <a class="btn gray" href="/ops/pre-ride-email-v3-operator-approval-workflow.php">Approvals</a>
        <a class="btn gray" href="/ops/pre-ride-email-v3-closed-gate-adapter-diagnostics.php">Adapter Diagnostics</a>
    </nav>
</header>
<main class="wrap">
    <section class="card hero <?= !empty($data['ok']) ? 'ok' : '' ?>">
        <h1>V3 Live Adapter Kill-Switch Check</h1>
        <p class="muted">Read-only pre-live switchboard. It verifies every condition that must be true before any future real adapter could be eligible.</p>
        <div>
            <?= !empty($data['ok']) ? badge('ALL CONDITIONS PASS', 'good') : badge('BLOCKED', 'bad') ?>
            <?= badge('NO EDXEIX CALL', 'good') ?>
            <?= badge('NO AADE CALL', 'good') ?>
            <?= badge('V0 UNTOUCHED', 'good') ?>
            <?= badge('READ ONLY', 'info') ?>
        </div>
        <?php if (!$result['ok']): ?>
            <p class="badline">Could not load JSON report: <?= h($result['error']) ?></p>
        <?php endif; ?>
    </section>

    <section class="grid">
        <div class="card metric"><strong><?= !empty($gate['enabled']) ? 'yes' : 'no' ?></strong><span>Gate enabled</span></div>
        <div class="card metric"><strong><?= h($gate['mode'] ?? '-') ?></strong><span>Gate mode</span></div>
        <div class="card metric"><strong><?= h($gate['adapter'] ?? '-') ?></strong><span>Selected adapter</span></div>
        <div class="card metric"><strong><?= !empty($approval['valid']) ? 'yes' : 'no' ?></strong><span>Approval valid</span></div>
    </section>

    <section class="two">
        <div class="card">
            <h2>Selected row</h2>
            <?php if ($row): ?>
                <ul class="list">
                    <li><strong>ID:</strong> <?= h($row['id'] ?? '') ?></li>
                    <li><strong>Status:</strong> <?= h($row['queue_status'] ?? '') ?></li>
                    <li><strong>Customer:</strong> <?= h($row['customer_name'] ?? '') ?></li>
                    <li><strong>Pickup:</strong> <?= h($row['pickup_datetime'] ?? '') ?> (<?= h($row['minutes_until_now'] ?? '') ?> min)</li>
                    <li><strong>Driver / vehicle:</strong> <?= h($row['driver_name'] ?? '') ?> / <?= h($row['vehicle_plate'] ?? '') ?></li>
                    <li><strong>IDs:</strong> lessor=<?= h($row['lessor_id'] ?? '') ?> driver=<?= h($row['driver_id'] ?? '') ?> vehicle=<?= h($row['vehicle_id'] ?? '') ?> start=<?= h($row['starting_point_id'] ?? '') ?></li>
                </ul>
            <?php else: ?>
                <p class="muted">No row selected.</p>
            <?php endif; ?>
        </div>
        <div class="card">
            <h2>Adapter / approval / starting point</h2>
            <ul class="list">
                <li><strong>Expected adapter:</strong> <?= h($adapter['expected_adapter'] ?? 'edxeix_live') ?></li>
                <li><strong>Selected adapter:</strong> <?= h($adapter['selected_adapter'] ?? '-') ?></li>
                <li><strong>Adapter live-capable:</strong> <?= !empty($adapter['is_live_capable']) ? badge('yes','good') : badge('no','bad') ?></li>
                <li><strong>Approval:</strong> <?= !empty($approval['valid']) ? badge('valid','good') : badge('not valid','bad') ?> <?= h($approval['reason'] ?? '') ?></li>
                <li><strong>Starting point:</strong> <?= !empty($start['ok']) ? badge('verified','good') : badge('not verified','bad') ?> <?= h($start['reason'] ?? '') ?></li>
                <li><strong>Missing fields:</strong> <?= $required ? h(implode(', ', $required)) : 'none' ?></li>
            </ul>
        </div>
    </section>

    <section class="card">
        <h2>Final blocks</h2>
        <?php if (!$blocks): ?>
            <p class="okline">No blocks reported. This should only happen during an explicitly approved future live-submit opening test.</p>
        <?php else: ?>
            <ul class="list blocks">
                <?php foreach ($blocks as $block): ?><li><?= h($block) ?></li><?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <section class="card">
        <h2>CLI command</h2>
        <pre><?= h($result['cmd']) ?></pre>
    </section>

    <?php if (!$result['ok']): ?>
        <section class="card">
            <h2>Raw output</h2>
            <pre><?= h($result['raw']) ?></pre>
        </section>
    <?php endif; ?>
</main>
</body>
</html>
