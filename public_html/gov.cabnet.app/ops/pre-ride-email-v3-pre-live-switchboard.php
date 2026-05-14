<?php

declare(strict_types=1);

/**
 * V3 Pre-Live Switchboard
 *
 * Read-only Ops page.
 * Runs the matching CLI in JSON mode and renders the consolidated closed-gate state.
 */

$cli = '/home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_pre_live_switchboard.php';
$queueId = isset($_GET['queue_id']) ? preg_replace('/[^0-9]/', '', (string)$_GET['queue_id']) : '';
$args = $queueId !== '' ? ' --queue-id=' . escapeshellarg($queueId) : '';
$cmd = '/usr/local/bin/php ' . escapeshellarg($cli) . ' --json' . $args . ' 2>&1';
$json = shell_exec($cmd);
$data = json_decode((string)$json, true);
if (!is_array($data)) {
    $data = [
        'ok' => false,
        'version' => 'v3.0.63-v3-pre-live-switchboard',
        'mode' => 'ops_page_error',
        'safety' => 'No Bolt call. No EDXEIX call. No AADE call. No DB writes. No queue status changes. No production submission tables. V0 untouched.',
        'events' => [['level' => 'error', 'message' => 'Could not decode CLI JSON output']],
        'raw_output' => (string)$json,
        'final_blocks' => ['system: could not decode CLI JSON output'],
    ];
}

function h(mixed $v): string
{
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function yesno(bool $v): string
{
    return $v ? 'yes' : 'no';
}

function badge(bool $ok, string $yes = 'OK', string $no = 'BLOCKED'): string
{
    $class = $ok ? 'badge text-bg-success' : 'badge text-bg-danger';
    return '<span class="' . $class . '">' . h($ok ? $yes : $no) . '</span>';
}

$row = is_array($data['selected_queue_row'] ?? null) ? $data['selected_queue_row'] : [];
$gate = is_array($data['gate'] ?? null) ? $data['gate'] : [];
$payload = is_array($data['payload'] ?? null) ? $data['payload'] : [];
$start = is_array($data['starting_point'] ?? null) ? $data['starting_point'] : [];
$approval = is_array($data['approval'] ?? null) ? $data['approval'] : [];
$adapter = is_array($data['adapter'] ?? null) ? $data['adapter'] : [];
$pkg = is_array($data['package_export'] ?? null) ? $data['package_export'] : [];
$blocks = is_array($data['final_blocks'] ?? null) ? $data['final_blocks'] : [];

?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>V3 Pre-Live Switchboard</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    :root {
      --bg:#f5f7fb; --panel:#ffffff; --ink:#1f2937; --muted:#6b7280;
      --line:#e5e7eb; --blue:#174ea6; --green:#0f766e; --red:#b42318; --amber:#b45309;
    }
    body { margin:0; font-family: system-ui, -apple-system, Segoe UI, sans-serif; background:var(--bg); color:var(--ink); }
    .wrap { max-width:1180px; margin:0 auto; padding:22px; }
    .top { display:flex; justify-content:space-between; gap:16px; align-items:flex-start; margin-bottom:18px; }
    h1 { margin:0; font-size:26px; }
    .sub { color:var(--muted); margin-top:5px; }
    .grid { display:grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap:12px; margin:16px 0; }
    .card { background:var(--panel); border:1px solid var(--line); border-radius:14px; padding:16px; box-shadow:0 1px 2px rgba(0,0,0,.03); }
    .card h2 { font-size:15px; margin:0 0 10px; color:#111827; }
    .metric { font-size:22px; font-weight:700; margin-top:4px; }
    .small { font-size:13px; color:var(--muted); }
    .badge { display:inline-block; padding:4px 9px; border-radius:999px; font-size:12px; font-weight:700; }
    .text-bg-success { background:#dcfce7; color:#166534; }
    .text-bg-danger { background:#fee2e2; color:#991b1b; }
    .text-bg-warning { background:#fef3c7; color:#92400e; }
    table { width:100%; border-collapse:collapse; font-size:13px; }
    th,td { padding:8px 9px; border-bottom:1px solid var(--line); text-align:left; vertical-align:top; }
    th { color:#374151; background:#f9fafb; }
    code, pre { background:#0f172a; color:#e5e7eb; border-radius:10px; }
    pre { padding:12px; overflow:auto; font-size:12px; }
    .links a { display:inline-block; margin:4px 8px 4px 0; text-decoration:none; color:#174ea6; font-weight:600; }
    .alert { border-radius:14px; padding:14px; border:1px solid var(--line); background:#fff; margin:12px 0; }
    .alert-danger { border-color:#fecaca; background:#fff1f2; color:#991b1b; }
    .alert-success { border-color:#bbf7d0; background:#f0fdf4; color:#166534; }
    .alert-warning { border-color:#fde68a; background:#fffbeb; color:#92400e; }
    @media (max-width:900px){ .grid{ grid-template-columns:1fr 1fr; } .top{display:block;} }
    @media (max-width:560px){ .grid{ grid-template-columns:1fr; } .wrap{padding:14px;} }
  </style>
</head>
<body>
<div class="wrap">
  <div class="top">
    <div>
      <h1>V3 Pre-Live Switchboard</h1>
      <div class="sub">Read-only consolidated view before any future live adapter implementation.</div>
    </div>
    <div><?= badge(!empty($data['ok']), 'LIVE ELIGIBLE', 'LIVE BLOCKED') ?></div>
  </div>

  <div class="alert alert-warning">
    <strong>Safety:</strong> <?= h($data['safety'] ?? '') ?>
  </div>

  <div class="grid">
    <div class="card">
      <h2>Master gate</h2>
      <div class="metric"><?= h(($gate['mode'] ?? '-') . ' / ' . ($gate['adapter'] ?? '-')) ?></div>
      <div class="small">
        enabled=<?= yesno(!empty($gate['enabled'])) ?>,
        hard=<?= yesno(!empty($gate['hard_enable_live_submit'])) ?>,
        ack=<?= yesno(!empty($gate['acknowledgement_phrase_present'])) ?>
      </div>
    </div>
    <div class="card">
      <h2>Selected row</h2>
      <div class="metric">#<?= h($row['id'] ?? '-') ?></div>
      <div class="small"><?= h($row['queue_status'] ?? '-') ?> · pickup <?= h($row['pickup_datetime'] ?? '-') ?></div>
    </div>
    <div class="card">
      <h2>Approval</h2>
      <div class="metric"><?= !empty($approval['valid']) ? 'valid' : 'not valid' ?></div>
      <div class="small"><?= h($approval['reason'] ?? '') ?></div>
    </div>
    <div class="card">
      <h2>Adapter</h2>
      <div class="metric"><?= h($adapter['selected_adapter'] ?? '-') ?></div>
      <div class="small">live-capable=<?= yesno(!empty($adapter['is_live_capable'])) ?></div>
    </div>
  </div>

  <?php if ($blocks === []): ?>
    <div class="alert alert-success"><strong>No blocks.</strong> This should only happen when all future live gates are deliberately open.</div>
  <?php else: ?>
    <div class="alert alert-danger">
      <strong>Final blocks:</strong>
      <ul>
        <?php foreach ($blocks as $block): ?><li><?= h($block) ?></li><?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <div class="card">
    <h2>Queue row</h2>
    <table>
      <tr><th>Customer</th><td><?= h($row['customer_name'] ?? '') ?></td></tr>
      <tr><th>Driver / Vehicle</th><td><?= h(($row['driver_name'] ?? '') . ' / ' . ($row['vehicle_plate'] ?? '')) ?></td></tr>
      <tr><th>IDs</th><td>lessor=<?= h($row['lessor_id'] ?? '') ?> driver=<?= h($row['driver_id'] ?? '') ?> vehicle=<?= h($row['vehicle_id'] ?? '') ?> start=<?= h($row['starting_point_id'] ?? '') ?></td></tr>
      <tr><th>Pickup</th><td><?= h($row['pickup_datetime'] ?? '') ?> · minutes_until=<?= h($row['minutes_until_now'] ?? '') ?></td></tr>
      <tr><th>Route</th><td><?= h(($row['pickup_address'] ?? '') . ' → ' . ($row['dropoff_address'] ?? '')) ?></td></tr>
    </table>
  </div>

  <div class="grid">
    <div class="card">
      <h2>Payload</h2>
      <div><?= badge(!empty($payload['complete']), 'complete', 'missing') ?></div>
      <div class="small">Missing: <?= h(implode(', ', (array)($payload['missing'] ?? [])) ?: 'none') ?></div>
    </div>
    <div class="card">
      <h2>Starting point</h2>
      <div><?= badge(!empty($start['ok']), 'verified', 'not verified') ?></div>
      <div class="small"><?= h($start['reason'] ?? '') ?></div>
    </div>
    <div class="card">
      <h2>Package export</h2>
      <div class="metric"><?= h($pkg['queue_artifact_count'] ?? '0') ?></div>
      <div class="small">artifacts for selected row</div>
    </div>
    <div class="card">
      <h2>Version</h2>
      <div class="small"><?= h($data['version'] ?? '') ?></div>
      <div class="small"><?= h($data['finished_at'] ?? '') ?></div>
    </div>
  </div>

  <div class="card">
    <h2>Quick links</h2>
    <div class="links">
      <a href="/ops/pre-ride-email-v3-proof.php">Proof Dashboard</a>
      <a href="/ops/pre-ride-email-v3-live-package-export.php">Package Export</a>
      <a href="/ops/pre-ride-email-v3-operator-approval-workflow.php">Operator Approval</a>
      <a href="/ops/pre-ride-email-v3-closed-gate-adapter-diagnostics.php">Adapter Diagnostics</a>
      <a href="/ops/pre-ride-email-v3-live-adapter-kill-switch-check.php">Kill-Switch Check</a>
      <a href="/ops/pre-ride-email-v3-monitor.php">Compact Monitor</a>
    </div>
  </div>

  <div class="card">
    <h2>Raw JSON</h2>
    <pre><?= h(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></pre>
  </div>
</div>
</body>
</html>
