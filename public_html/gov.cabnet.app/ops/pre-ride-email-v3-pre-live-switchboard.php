<?php

declare(strict_types=1);

/**
 * V3 Pre-Live Switchboard — Ops 500 hotfix
 *
 * Read-only Ops page. Executes the matching CLI in JSON mode when allowed and
 * renders a defensive consolidated state. This page must never 500 just because
 * a local command runner is unavailable or the JSON payload is unexpectedly large.
 */

$version = 'v3.0.64-v3-pre-live-switchboard-ops-500-hotfix';
$safety = 'No Bolt call. No EDXEIX call. No AADE call. No DB writes. No queue status changes. No production submission tables. V0 untouched.';

function h(mixed $v): string
{
    if (is_array($v) || is_object($v)) {
        $v = json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function yn(bool $v): string
{
    return $v ? 'yes' : 'no';
}

function pill(bool $ok, string $yes = 'OK', string $no = 'BLOCKED'): string
{
    $class = $ok ? 'ok' : 'bad';
    return '<span class="pill ' . $class . '">' . h($ok ? $yes : $no) . '</span>';
}

/** @return array{raw:string, method:string, error:string} */
function run_switchboard_cli(string $cli, string $queueId): array
{
    if (!is_file($cli) || !is_readable($cli)) {
        return ['raw' => '', 'method' => 'none', 'error' => 'CLI file is missing or unreadable: ' . $cli];
    }

    $arg = $queueId !== '' ? ' --queue-id=' . escapeshellarg($queueId) : '';
    $cmd = '/usr/local/bin/php ' . escapeshellarg($cli) . ' --json' . $arg . ' 2>&1';

    if (function_exists('shell_exec')) {
        $out = shell_exec($cmd);
        return ['raw' => is_string($out) ? $out : '', 'method' => 'shell_exec', 'error' => ''];
    }

    if (function_exists('exec')) {
        $lines = [];
        $exit = 0;
        exec($cmd, $lines, $exit);
        return ['raw' => implode("\n", $lines), 'method' => 'exec', 'error' => $exit === 0 ? '' : 'exec exit code ' . $exit];
    }

    return ['raw' => '', 'method' => 'none', 'error' => 'No allowed local command runner is available for this PHP context.'];
}

$cli = '/home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_pre_live_switchboard.php';
$queueId = isset($_GET['queue_id']) ? preg_replace('/[^0-9]/', '', (string)$_GET['queue_id']) : '';
$exec = ['raw' => '', 'method' => 'not_run', 'error' => ''];
$data = [];

try {
    $exec = run_switchboard_cli($cli, $queueId);
    $decoded = json_decode($exec['raw'], true);
    if (is_array($decoded)) {
        $data = $decoded;
    } else {
        $data = [
            'ok' => false,
            'version' => $version,
            'mode' => 'ops_page_cli_decode_error',
            'safety' => $safety,
            'events' => [['level' => 'error', 'message' => 'Could not decode CLI JSON output. ' . $exec['error']]],
            'final_blocks' => ['ops_page: could not decode CLI JSON output'],
            'raw_output_preview' => mb_substr($exec['raw'], 0, 4000),
        ];
    }
} catch (Throwable $e) {
    $data = [
        'ok' => false,
        'version' => $version,
        'mode' => 'ops_page_exception',
        'safety' => $safety,
        'events' => [['level' => 'error', 'message' => $e->getMessage()]],
        'final_blocks' => ['ops_page: ' . $e->getMessage()],
    ];
}

$row = is_array($data['selected_queue_row'] ?? null) ? $data['selected_queue_row'] : [];
$gate = is_array($data['gate'] ?? null) ? $data['gate'] : [];
$payload = is_array($data['payload'] ?? null) ? $data['payload'] : [];
$start = is_array($data['starting_point'] ?? null) ? $data['starting_point'] : [];
$approval = is_array($data['approval'] ?? null) ? $data['approval'] : [];
$adapter = is_array($data['adapter'] ?? null) ? $data['adapter'] : [];
$pkg = is_array($data['package_export'] ?? null) ? $data['package_export'] : [];
$blocks = is_array($data['final_blocks'] ?? null) ? $data['final_blocks'] : [];
$events = is_array($data['events'] ?? null) ? $data['events'] : [];
$missing = is_array($payload['missing'] ?? null) ? $payload['missing'] : [];
$artifacts = is_array($pkg['latest_queue_artifacts'] ?? null) ? $pkg['latest_queue_artifacts'] : [];

$compact = [
    'ok' => !empty($data['ok']),
    'version' => (string)($data['version'] ?? $version),
    'mode' => (string)($data['mode'] ?? ''),
    'queue_id' => (string)($row['id'] ?? ''),
    'queue_status' => (string)($row['queue_status'] ?? ''),
    'pickup_datetime' => (string)($row['pickup_datetime'] ?? ''),
    'minutes_until_now' => (string)($row['minutes_until_now'] ?? ''),
    'gate' => [
        'loaded' => !empty($gate['loaded']),
        'enabled' => !empty($gate['enabled']),
        'mode' => (string)($gate['mode'] ?? ''),
        'adapter' => (string)($gate['adapter'] ?? ''),
        'hard_enable_live_submit' => !empty($gate['hard_enable_live_submit']),
        'acknowledgement_phrase_present' => !empty($gate['acknowledgement_phrase_present']),
    ],
    'approval_valid' => !empty($approval['valid']),
    'payload_complete' => !empty($payload['complete']),
    'starting_point_ok' => !empty($start['ok']),
    'adapter_selected' => (string)($adapter['selected_adapter'] ?? ''),
    'adapter_live_capable' => !empty($adapter['is_live_capable']),
    'package_artifact_count' => (int)($pkg['queue_artifact_count'] ?? 0),
    'final_blocks' => $blocks,
];

?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>V3 Pre-Live Switchboard</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    :root{--bg:#eef2f7;--panel:#fff;--ink:#061b44;--muted:#52607a;--line:#d5deec;--nav:#2f3a62;--ok:#166534;--bad:#991b1b;--warn:#92400e;--blue:#4f5fb8;}
    *{box-sizing:border-box} body{margin:0;background:var(--bg);color:var(--ink);font-family:system-ui,-apple-system,Segoe UI,sans-serif;font-size:14px}.layout{display:grid;grid-template-columns:260px 1fr;min-height:100vh}.side{background:var(--nav);color:#fff;padding:18px}.side h1{font-size:18px;margin:0 0 8px}.side p{font-size:13px;line-height:1.4;color:#e0e6ff}.side a{display:block;color:#fff;text-decoration:none;padding:9px 10px;border-radius:8px;margin:5px 0}.side a.active,.side a:hover{background:rgba(255,255,255,.14)}.main{padding:24px}.hero,.card{background:var(--panel);border:1px solid var(--line);border-radius:16px;box-shadow:0 1px 2px rgba(0,0,0,.03)}.hero{padding:22px;margin-bottom:16px;border-left:6px solid <?= !empty($data['ok']) ? '#16a34a' : '#d97706' ?>}.hero h2{font-size:28px;margin:0 0 8px}.sub{color:var(--muted);line-height:1.5}.actions a{display:inline-block;margin:12px 8px 0 0;background:var(--blue);color:#fff;text-decoration:none;border-radius:10px;padding:10px 13px;font-weight:700}.actions a.green{background:#38a169}.actions a.dark{background:#172554}.grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin:16px 0}.card{padding:16px;margin-bottom:14px}.card h3{margin:0 0 10px;font-size:18px}.metric{font-size:26px;font-weight:800}.small{font-size:12px;color:var(--muted);line-height:1.4}.pill{display:inline-block;border-radius:999px;padding:4px 10px;font-weight:800;font-size:12px}.pill.ok{background:#dcfce7;color:var(--ok)}.pill.bad{background:#fee2e2;color:var(--bad)}.pill.warn{background:#fef3c7;color:var(--warn)}table{width:100%;border-collapse:collapse}th,td{padding:9px 10px;border-bottom:1px solid var(--line);text-align:left;vertical-align:top}th{background:#f8fafc;color:#344269;width:220px}.blocks{background:#fff7ed;border:1px solid #fed7aa;color:#7c2d12;border-radius:14px;padding:14px}.blocks ul{margin:8px 0 0 18px;padding:0}pre{white-space:pre-wrap;overflow:auto;background:#0f172a;color:#e5e7eb;border-radius:12px;padding:12px;font-size:12px;max-height:380px}.two{display:grid;grid-template-columns:1fr 1fr;gap:14px}.muted{color:var(--muted)}@media(max-width:1000px){.layout{grid-template-columns:1fr}.side{position:relative}.grid,.two{grid-template-columns:1fr 1fr}}@media(max-width:640px){.grid,.two{grid-template-columns:1fr}.main{padding:14px}}
  </style>
</head>
<body>
<div class="layout">
  <aside class="side">
    <h1>V3 Automation</h1>
    <p>Read-only pre-live switchboard. Live submit remains disabled.</p>
    <a href="/ops/pre-ride-email-v3-pre-live-switchboard.php" class="active">Pre-Live Switchboard</a>
    <a href="/ops/pre-ride-email-v3-proof.php">Proof Dashboard</a>
    <a href="/ops/pre-ride-email-v3-live-package-export.php">Package Export</a>
    <a href="/ops/pre-ride-email-v3-operator-approval-workflow.php">Operator Approval</a>
    <a href="/ops/pre-ride-email-v3-live-adapter-kill-switch-check.php">Kill-Switch Check</a>
    <a href="/ops/pre-ride-email-v3-monitor.php">Compact Monitor</a>
  </aside>
  <main class="main">
    <section class="hero">
      <div><?= pill(!empty($data['ok']), 'LIVE ELIGIBLE', 'LIVE BLOCKED') ?> <?= pill(!empty($approval['valid']), 'APPROVAL VALID', 'NO VALID APPROVAL') ?> <?= pill(!empty($start['ok']), 'START VERIFIED', 'START NOT VERIFIED') ?> <?= pill(!empty($adapter['is_live_capable']), 'LIVE ADAPTER CAPABLE', 'ADAPTER NOT LIVE') ?></div>
      <h2>V3 Pre-Live Switchboard</h2>
      <div class="sub"><?= h($data['safety'] ?? $safety) ?></div>
      <div class="actions">
        <a class="green" href="?queue_id=<?= h($row['id'] ?? '') ?>">Refresh selected row</a>
        <a href="/ops/pre-ride-email-v3-closed-gate-adapter-diagnostics.php<?= !empty($row['id']) ? '?queue_id=' . h($row['id']) : '' ?>">Adapter Diagnostics</a>
        <a class="dark" href="/ops/pre-ride-email-v3-storage-check.php">Storage Check</a>
      </div>
    </section>

    <?php if ($events !== []): ?>
      <div class="card"><h3>Events</h3><ul><?php foreach ($events as $event): ?><li><?= h($event['level'] ?? '') ?>: <?= h($event['message'] ?? $event) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>

    <div class="grid">
      <div class="card"><h3>Master Gate</h3><div class="metric"><?= h(($gate['mode'] ?? '-') . ' / ' . ($gate['adapter'] ?? '-')) ?></div><div class="small">loaded=<?= yn(!empty($gate['loaded'])) ?> enabled=<?= yn(!empty($gate['enabled'])) ?> hard=<?= yn(!empty($gate['hard_enable_live_submit'])) ?> ack=<?= yn(!empty($gate['acknowledgement_phrase_present'])) ?></div></div>
      <div class="card"><h3>Selected Row</h3><div class="metric">#<?= h($row['id'] ?? '-') ?></div><div class="small"><?= h($row['queue_status'] ?? '-') ?> · pickup <?= h($row['pickup_datetime'] ?? '-') ?> · minutes <?= h($row['minutes_until_now'] ?? '-') ?></div></div>
      <div class="card"><h3>Approval</h3><div class="metric"><?= !empty($approval['valid']) ? 'valid' : 'not valid' ?></div><div class="small"><?= h($approval['reason'] ?? '') ?></div></div>
      <div class="card"><h3>Artifacts</h3><div class="metric"><?= h($pkg['queue_artifact_count'] ?? '0') ?></div><div class="small">local package exports for selected row</div></div>
    </div>

    <div class="blocks"><strong>Final blocks</strong><ul><?php foreach ($blocks as $block): ?><li><?= h($block) ?></li><?php endforeach; ?><?php if ($blocks === []): ?><li>No blocks reported.</li><?php endif; ?></ul></div>

    <div class="two">
      <div class="card"><h3>Queue Row</h3><table><tr><th>Customer</th><td><?= h(($row['customer_name'] ?? '') . (!empty($row['customer_phone']) ? ' / ' . $row['customer_phone'] : '')) ?></td></tr><tr><th>Driver / Vehicle</th><td><?= h(($row['driver_name'] ?? '') . ' / ' . ($row['vehicle_plate'] ?? '')) ?></td></tr><tr><th>EDXEIX IDs</th><td>lessor=<?= h($row['lessor_id'] ?? '') ?> driver=<?= h($row['driver_id'] ?? '') ?> vehicle=<?= h($row['vehicle_id'] ?? '') ?> start=<?= h($row['starting_point_id'] ?? '') ?></td></tr><tr><th>Route</th><td><?= h(($row['pickup_address'] ?? '') . ' → ' . ($row['dropoff_address'] ?? '')) ?></td></tr><tr><th>Last error</th><td><?= h($row['last_error'] ?? '') ?></td></tr></table></div>
      <div class="card"><h3>Checks</h3><table><tr><th>Payload</th><td><?= pill(!empty($payload['complete']), 'complete', 'missing') ?> <span class="small"><?= h($missing ? implode(', ', array_map('strval', $missing)) : 'none missing') ?></span></td></tr><tr><th>Starting point</th><td><?= pill(!empty($start['ok']), 'verified', 'not verified') ?> <?= h($start['reason'] ?? '') ?></td></tr><tr><th>Adapter</th><td><?= h($adapter['selected_adapter'] ?? '') ?> · expected <?= h($adapter['expected_adapter'] ?? 'edxeix_live') ?> · live-capable=<?= yn(!empty($adapter['is_live_capable'])) ?> · <?= h($adapter['reason'] ?? '') ?></td></tr><tr><th>Command method</th><td><?= h($exec['method'] ?? '') ?> <?= h($exec['error'] ?? '') ?></td></tr><tr><th>Version</th><td><?= h($data['version'] ?? $version) ?></td></tr></table></div>
    </div>

    <div class="card"><h3>Latest package artifacts</h3><?php if ($artifacts === []): ?><p class="muted">No artifacts for selected row.</p><?php else: ?><ul><?php foreach ($artifacts as $artifact): ?><li><code><?= h($artifact) ?></code></li><?php endforeach; ?></ul><?php endif; ?></div>

    <div class="card"><h3>Compact JSON</h3><pre><?= h(json_encode($compact, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></pre></div>
  </main>
</div>
</body>
</html>
