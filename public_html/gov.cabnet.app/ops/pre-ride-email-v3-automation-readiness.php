<?php
declare(strict_types=1);

const PRV3_AUTOMATION_READINESS_PAGE_VERSION = 'v3.0.32-automation-readiness-page';

$baseRoot = dirname(__DIR__, 3);
$appRoot = $baseRoot . '/gov.cabnet.app_app';
$bootstrapFile = $appRoot . '/src/bootstrap.php';
$reportFile = $appRoot . '/src/BoltMailV3/AutomationReadinessReportV3.php';

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function badge(bool $ok, string $yes = 'yes', string $no = 'no'): string
{
    $class = $ok ? 'ok' : 'bad';
    $text = $ok ? $yes : $no;
    return '<span class="badge ' . $class . '">' . h($text) . '</span>';
}

function soft_badge(string $text, string $class = 'neutral'): string
{
    return '<span class="badge ' . h($class) . '">' . h($text) . '</span>';
}

$report = null;
$error = '';
try {
    foreach ([$bootstrapFile, $reportFile] as $file) {
        if (!is_file($file)) {
            throw new RuntimeException('Missing required file: ' . $file);
        }
        require_once $file;
    }
    $ctx = require $bootstrapFile;
    if (!is_array($ctx) || !isset($ctx['db']) || !method_exists($ctx['db'], 'connection')) {
        throw new RuntimeException('Bootstrap did not return DB context.');
    }
    $db = $ctx['db']->connection();
    if (!$db instanceof mysqli) {
        throw new RuntimeException('DB connection is not mysqli.');
    }
    $report = (new Bridge\BoltMailV3\AutomationReadinessReportV3($db, $appRoot))->build();
} catch (Throwable $e) {
    $error = $e->getMessage();
}

$r = is_array($report) ? (array)($report['readiness'] ?? []) : [];
$q = is_array($report) ? (array)($report['queue'] ?? []) : [];
$schema = is_array($report) ? (array)($report['schema'] ?? []) : [];
$cron = is_array($report) ? (array)($report['cron'] ?? []) : [];
$gate = is_array($report) ? (array)($report['live_submit_config'] ?? []) : [];
$safety = is_array($report) ? (array)($report['safety'] ?? []) : [];
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>V3 Automation Readiness</title>
  <style>
    :root{--ink:#071b46;--muted:#31527f;--line:#cfe0f5;--bg:#f3f7fc;--card:#fff;--good:#d8f8e1;--bad:#ffe2e2;--warn:#fff1d2;--blue:#e7f0ff;--purple:#7c2de8}
    *{box-sizing:border-box} body{margin:0;background:var(--bg);font:14px/1.45 Arial,Helvetica,sans-serif;color:var(--ink)}
    .nav{background:#071225;color:#fff;padding:16px 24px;display:flex;gap:20px;align-items:center;position:sticky;top:0;z-index:2}.nav a{color:#fff;text-decoration:none;font-weight:700}.brand{font-weight:800;margin-right:10px}
    .wrap{max-width:1325px;margin:20px auto;padding:0 18px}.hero,.card{background:var(--card);border:1px solid var(--line);border-radius:14px;padding:20px;margin-bottom:18px}.hero{border-left:6px solid var(--purple)}
    h1{margin:0 0 10px;font-size:30px}.sub{color:var(--muted);font-size:15px}.grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px}.grid3{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px}.metric{border:1px solid var(--line);border-radius:10px;padding:14px;background:#f8fbff}.metric .num{font-size:28px;font-weight:800}.metric .label{color:var(--muted)}
    .badge{display:inline-block;border-radius:999px;padding:5px 9px;font-size:12px;font-weight:800;margin:3px}.ok{background:var(--good);color:#04712d}.bad{background:var(--bad);color:#aa0000}.warn{background:var(--warn);color:#a45b00}.neutral{background:var(--blue);color:#0645a8}.purple{background:#eadbff;color:#5511aa}
    table{width:100%;border-collapse:collapse;margin-top:10px} th,td{border:1px solid var(--line);padding:9px;text-align:left;vertical-align:top} th{background:#f6f9ff} code,pre{background:#071225;color:#e8f0ff;border-radius:8px;padding:10px;display:block;white-space:pre-wrap;overflow:auto}.small{font-size:12px;color:var(--muted)}.alert{border:1px solid #ffbf80;background:#fff7ec;border-radius:10px;padding:12px;margin:12px 0}.goodbox{border:1px solid #9ee2b2;background:#f1fff5;border-radius:10px;padding:12px;margin:12px 0}
    @media(max-width:900px){.grid,.grid3{grid-template-columns:1fr}.nav{flex-wrap:wrap}.wrap{padding:0 10px}}
  </style>
</head>
<body>
  <div class="nav">
    <div class="brand">GC gov.cabnet.app</div>
    <a href="/ops/pre-ride-email-v3-queue-watch.php">V3 Watch</a>
    <a href="/ops/pre-ride-email-v3-queue.php">V3 Queue</a>
    <a href="/ops/pre-ride-email-v3-cron-health.php">V3 Cron Health</a>
    <a href="/ops/pre-ride-email-v3-live-readiness.php">V3 Live Readiness</a>
    <a href="/ops/pre-ride-email-v3-live-submit.php">V3 Live Submit</a>
    <a href="/ops/pre-ride-email-v3-live-submit-gate.php">V3 Gate</a>
  </div>

  <main class="wrap">
    <section class="hero">
      <h1>V3 Automation Readiness</h1>
      <div class="sub">Single read-only overview of the V3 pre-ride email automation chain. Version <code style="display:inline;padding:2px 6px"><?= h(PRV3_AUTOMATION_READINESS_PAGE_VERSION) ?></code></div>
      <p>
        <?= soft_badge('V3 ISOLATED', 'purple') ?>
        <?= soft_badge('READ ONLY', 'ok') ?>
        <?= soft_badge('NO EDXEIX CALL', 'ok') ?>
        <?= soft_badge('NO AADE CALL', 'ok') ?>
        <?= soft_badge('NO DB WRITE', 'ok') ?>
      </p>
    </section>

    <?php if ($error !== ''): ?>
      <section class="card"><div class="alert"><strong>Error:</strong> <?= h($error) ?></div></section>
    <?php else: ?>
      <section class="card">
        <h2>Readiness summary</h2>
        <div class="grid">
          <div class="metric"><div class="num"><?= !empty($r['ready_for_v3_manual_handoff']) ? 'yes' : 'no' ?></div><div class="label">Ready for V3 manual handoff</div></div>
          <div class="metric"><div class="num"><?= !empty($r['ready_for_future_live_submit']) ? 'yes' : 'no' ?></div><div class="label">Ready for future live submit</div></div>
          <div class="metric"><div class="num"><?= !empty($gate['hard_enable_live_submit']) ? 'yes' : 'no' ?></div><div class="label">Hard live submit enabled</div></div>
          <div class="metric"><div class="num"><?= h((string)($q['live_submit_ready'] ?? 0)) ?></div><div class="label">live_submit_ready rows</div></div>
        </div>
        <div class="<?= !empty($r['ready_for_v3_manual_handoff']) ? 'goodbox' : 'alert' ?>"><strong>Next action:</strong> <?= h((string)($r['current_next_action'] ?? '')) ?></div>
      </section>

      <section class="card">
        <h2>Safety status</h2>
        <p>
          <?= badge(empty($safety['edxeix_call_from_report']), 'no EDXEIX call', 'EDXEIX call risk') ?>
          <?= badge(empty($safety['aade_call_from_report']), 'no AADE call', 'AADE call risk') ?>
          <?= badge(empty($safety['db_writes_from_report']), 'no DB writes', 'DB write risk') ?>
          <?= badge(empty($safety['production_submission_jobs']), 'no production jobs', 'production jobs risk') ?>
          <?= badge(empty($safety['production_submission_attempts']), 'no production attempts', 'production attempts risk') ?>
        </p>
        <div class="alert"><strong>Live submit state:</strong> <?= h((string)($safety['safety_message'] ?? '')) ?></div>
      </section>

      <section class="card">
        <h2>Schema and gate</h2>
        <div class="grid3">
          <div class="metric"><div class="num"><?= !empty($schema['queue']) && !empty($schema['events']) ? 'yes' : 'no' ?></div><div class="label">Queue schema</div></div>
          <div class="metric"><div class="num"><?= !empty($schema['start_options']) ? 'yes' : 'no' ?></div><div class="label">Start guard schema</div></div>
          <div class="metric"><div class="num"><?= !empty($schema['approvals']) ? 'yes' : 'no' ?></div><div class="label">Approval schema</div></div>
        </div>
        <table>
          <tr><th>Gate setting</th><th>Value</th></tr>
          <tr><td>Config loaded</td><td><?= badge(!empty($gate['config_loaded'])) ?></td></tr>
          <tr><td>Config path</td><td><?= h((string)($gate['config_path'] ?? '')) ?></td></tr>
          <tr><td>Config error</td><td><?= h((string)(($gate['config_error'] ?? '') !== '' ? $gate['config_error'] : '-')) ?></td></tr>
          <tr><td>Enabled</td><td><?= badge(!empty($gate['enabled'])) ?></td></tr>
          <tr><td>Mode</td><td><?= h((string)($gate['mode'] ?? '')) ?></td></tr>
          <tr><td>Adapter</td><td><?= h((string)($gate['adapter'] ?? '')) ?></td></tr>
          <tr><td>Hard enable live submit</td><td><?= badge(!empty($gate['hard_enable_live_submit'])) ?></td></tr>
          <tr><td>Operator approval required</td><td><?= badge(!empty($gate['operator_approval_required'])) ?></td></tr>
          <tr><td>Gate OK</td><td><?= badge(!empty($gate['ok_for_future_live_submit'])) ?></td></tr>
        </table>
        <?php if (!empty($gate['blocks']) && is_array($gate['blocks'])): ?>
          <h3>Gate blocks</h3>
          <?php foreach ($gate['blocks'] as $block): ?><?= soft_badge((string)$block, 'bad') ?><?php endforeach; ?>
        <?php endif; ?>
      </section>

      <section class="card">
        <h2>Queue snapshot</h2>
        <div class="grid">
          <div class="metric"><div class="num"><?= h((string)($q['total'] ?? 0)) ?></div><div class="label">Total V3 queue rows</div></div>
          <div class="metric"><div class="num"><?= h((string)($q['active'] ?? 0)) ?></div><div class="label">Active rows</div></div>
          <div class="metric"><div class="num"><?= h((string)($q['future_active'] ?? 0)) ?></div><div class="label">Future active rows</div></div>
          <div class="metric"><div class="num"><?= h((string)($q['blocked'] ?? 0)) ?></div><div class="label">Blocked rows</div></div>
        </div>
        <h3>Status counts</h3>
        <table>
          <tr><th>Status</th><th>Total</th><th>Future</th></tr>
          <?php if (!empty($q['status_counts']) && is_array($q['status_counts'])): foreach ($q['status_counts'] as $status => $count): $count = is_array($count) ? $count : []; ?>
            <tr><td><?= h((string)$status) ?></td><td><?= h((string)($count['total'] ?? 0)) ?></td><td><?= h((string)($count['future'] ?? 0)) ?></td></tr>
          <?php endforeach; else: ?>
            <tr><td colspan="3">No V3 queue rows yet.</td></tr>
          <?php endif; ?>
        </table>
      </section>

      <section class="card">
        <h2>V3 cron chain</h2>
        <p>
          <?= badge(!empty($cron['all_required_present']), 'all logs present', 'missing log') ?>
          <?= badge(!empty($cron['critical_fresh']), 'fresh', 'stale') ?>
          <?= soft_badge('fresh threshold ' . h((string)($cron['fresh_threshold_seconds'] ?? 180)) . ' sec', 'neutral') ?>
        </p>
        <table>
          <tr><th>Worker</th><th>Status</th><th>Age</th><th>Latest summary</th></tr>
          <?php $items = is_array($cron['items'] ?? null) ? $cron['items'] : []; foreach ($items as $name => $item): $item = is_array($item) ? $item : []; ?>
            <tr>
              <td><strong><?= h((string)$name) ?></strong><br><span class="small"><?= h((string)($item['file'] ?? '')) ?></span></td>
              <td><?= badge(!empty($item['exists']) && !empty($item['readable']), 'readable', 'missing') ?> <?= badge(!empty($item['fresh']), 'fresh', 'stale') ?></td>
              <td><?= h((string)($item['age_seconds'] ?? '-')) ?> sec</td>
              <td><code><?= h((string)($item['latest_summary'] ?? '')) ?></code></td>
            </tr>
          <?php endforeach; ?>
        </table>
      </section>

      <section class="card">
        <h2>Recent V3 queue rows</h2>
        <table>
          <tr><th>ID</th><th>Status</th><th>Pickup</th><th>Transfer</th><th>IDs</th><th>Created</th></tr>
          <?php if (!empty($q['recent_rows']) && is_array($q['recent_rows'])): foreach ($q['recent_rows'] as $row): $row = is_array($row) ? $row : []; ?>
            <tr>
              <td><?= h((string)($row['id'] ?? '')) ?></td>
              <td><?= h((string)($row['queue_status'] ?? '')) ?></td>
              <td><?= h((string)($row['pickup_datetime'] ?? '')) ?></td>
              <td><?= h((string)($row['customer_name'] ?? '')) ?><br><?= h((string)($row['driver_name'] ?? '')) ?> / <?= h((string)($row['vehicle_plate'] ?? '')) ?></td>
              <td>Lessor <?= h((string)($row['lessor_id'] ?? '')) ?><br>Driver <?= h((string)($row['driver_id'] ?? '')) ?><br>Vehicle <?= h((string)($row['vehicle_id'] ?? '')) ?><br>Start <?= h((string)($row['starting_point_id'] ?? '')) ?></td>
              <td><?= h((string)($row['created_at'] ?? '')) ?></td>
            </tr>
          <?php endforeach; else: ?>
            <tr><td colspan="6">No recent V3 queue rows.</td></tr>
          <?php endif; ?>
        </table>
      </section>
    <?php endif; ?>
  </main>
</body>
</html>
