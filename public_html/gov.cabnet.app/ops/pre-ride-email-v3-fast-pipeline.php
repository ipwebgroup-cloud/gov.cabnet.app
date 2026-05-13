<?php
/**
 * gov.cabnet.app — V3 Fast Pipeline dashboard.
 * Read-only page. No DB writes, no EDXEIX calls, no AADE calls.
 */

declare(strict_types=1);

const V3_FAST_PIPELINE_PAGE_VERSION = 'v3.0.35-fast-pipeline-dashboard';

date_default_timezone_set('Europe/Athens');

function h($value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

$privateRoot = dirname(__DIR__, 3) . '/gov.cabnet.app_app';
$bootstrap = $privateRoot . '/src/bootstrap.php';
$db = null;
$dbName = '-';
$error = '';
$rows = [];
$statusCounts = [];
$events = [];

try {
    if (!is_file($bootstrap)) {
        throw new RuntimeException('Private app bootstrap not found.');
    }
    $ctx = require $bootstrap;
    if (!is_array($ctx) || !isset($ctx['db']) || !method_exists($ctx['db'], 'connection')) {
        throw new RuntimeException('Private app bootstrap did not return DB context.');
    }
    /** @var mysqli $db */
    $db = $ctx['db']->connection();
    $db->set_charset('utf8mb4');
    $res = $db->query('SELECT DATABASE() AS db');
    $dbName = ($res && ($r = $res->fetch_assoc())) ? (string)$r['db'] : '-';

    $res = $db->query("SELECT queue_status, COUNT(*) AS total, SUM(CASE WHEN pickup_datetime > NOW() THEN 1 ELSE 0 END) AS future_total FROM pre_ride_email_v3_queue GROUP BY queue_status ORDER BY queue_status ASC");
    if ($res) { while ($r = $res->fetch_assoc()) { $statusCounts[] = $r; } }

    $res = $db->query("SELECT id, queue_status, customer_name, driver_name, vehicle_plate, lessor_id, driver_id, vehicle_id, starting_point_id, pickup_datetime, created_at, last_error FROM pre_ride_email_v3_queue ORDER BY id DESC LIMIT 10");
    if ($res) { while ($r = $res->fetch_assoc()) { $rows[] = $r; } }

    $res = $db->query("SELECT id, queue_id, event_type, event_status, event_message, created_by, created_at FROM pre_ride_email_v3_queue_events ORDER BY id DESC LIMIT 10");
    if ($res) { while ($r = $res->fetch_assoc()) { $events[] = $r; } }
} catch (Throwable $e) {
    $error = $e->getMessage();
}

$logPath = $privateRoot . '/logs/pre_ride_email_v3_fast_pipeline.log';
$logExists = is_file($logPath);
$logAge = $logExists ? (time() - filemtime($logPath)) : null;
$logFresh = $logAge !== null && $logAge <= 180;
$logTail = '';
if ($logExists && is_readable($logPath)) {
    $lines = @file($logPath, FILE_IGNORE_NEW_LINES);
    if (is_array($lines)) {
        $logTail = implode("\n", array_slice($lines, -80));
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta http-equiv="refresh" content="30">
<title>V3 Fast Pipeline</title>
<style>
body{font-family:Arial,sans-serif;background:#f4f7fb;color:#06183d;margin:0}.top{background:#071125;color:#fff;padding:14px 22px}.top a{color:#fff;text-decoration:none;margin-right:18px;font-weight:700}.wrap{max-width:1320px;margin:22px auto;padding:0 16px}.card{background:#fff;border:1px solid #d7e1ef;border-radius:14px;padding:18px;margin-bottom:16px;box-shadow:0 8px 24px rgba(9,30,66,.04)}.pill{display:inline-block;border-radius:999px;padding:5px 10px;font-size:12px;font-weight:700;margin:3px}.ok{background:#d9fbe6;color:#075d25}.bad{background:#ffe0e0;color:#9b1111}.warn{background:#fff2cd;color:#8a5300}.muted{color:#53627c}.grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}.metric{background:#f7fbff;border:1px solid #d7e1ef;border-radius:12px;padding:14px}.metric b{font-size:28px}table{width:100%;border-collapse:collapse;font-size:13px}th,td{border-bottom:1px solid #d7e1ef;padding:8px;text-align:left;vertical-align:top}pre{background:#071125;color:#e6f1ff;padding:14px;border-radius:10px;white-space:pre-wrap;overflow:auto;font-size:12px}.small{font-size:12px}@media(max-width:900px){.grid{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="top"><strong>GC gov.cabnet.app</strong> <a href="/ops/pre-ride-email-v3-queue-watch.php">V3 Watch</a><a href="/ops/pre-ride-email-v3-automation-readiness.php">V3 Readiness</a><a href="/ops/pre-ride-email-v3-expiry-guard.php">V3 Expiry</a><a href="/ops/pre-ride-email-v3-fast-pipeline.php">V3 Fast Pipeline</a></div>
<div class="wrap">
  <div class="card">
    <h1>V3 Fast Pipeline</h1>
    <p>Read-only visibility for the ordered V3 automation pipeline. This page does not run the pipeline, write to the database, or call EDXEIX/AADE.</p>
    <span class="pill ok">READ ONLY</span><span class="pill ok">NO EDXEIX CALL</span><span class="pill ok">NO AADE CALL</span><span class="pill warn">v3.0.35-fast-pipeline-dashboard</span>
  </div>
  <?php if ($error !== ''): ?><div class="card"><span class="pill bad">Error</span> <?=h($error)?></div><?php endif; ?>
  <div class="card">
    <h2>Status</h2>
    <div class="grid">
      <div class="metric"><b><?=h($dbName)?></b><br><span class="muted">Database</span></div>
      <div class="metric"><b><?= $logExists ? 'yes' : 'no' ?></b><br><span class="muted">Fast pipeline log exists</span></div>
      <div class="metric"><b><?= $logFresh ? 'yes' : 'no' ?></b><br><span class="muted">Log fresh &lt;= 180 sec</span></div>
      <div class="metric"><b><?= $logAge === null ? '-' : (int)$logAge ?></b><br><span class="muted">Log age seconds</span></div>
    </div>
  </div>
  <div class="card">
    <h2>V3 queue status counts</h2>
    <table><thead><tr><th>Status</th><th>Total</th><th>Future</th></tr></thead><tbody>
    <?php if (!$statusCounts): ?><tr><td colspan="3">No queue rows.</td></tr><?php endif; ?>
    <?php foreach ($statusCounts as $r): ?><tr><td><?=h($r['queue_status'] ?? '')?></td><td><?=h($r['total'] ?? 0)?></td><td><?=h($r['future_total'] ?? 0)?></td></tr><?php endforeach; ?>
    </tbody></table>
  </div>
  <div class="card">
    <h2>Recent V3 queue rows</h2>
    <table><thead><tr><th>ID</th><th>Status</th><th>Pickup</th><th>Transfer</th><th>IDs</th><th>Last error</th></tr></thead><tbody>
    <?php if (!$rows): ?><tr><td colspan="6">No rows.</td></tr><?php endif; ?>
    <?php foreach ($rows as $r): ?><tr><td><?=h($r['id'] ?? '')?></td><td><?=h($r['queue_status'] ?? '')?></td><td><?=h($r['pickup_datetime'] ?? '')?></td><td><?=h(($r['customer_name'] ?? '').' / '.($r['driver_name'] ?? '').' / '.($r['vehicle_plate'] ?? ''))?></td><td class="small">lessor <?=h($r['lessor_id'] ?? '')?><br>driver <?=h($r['driver_id'] ?? '')?><br>vehicle <?=h($r['vehicle_id'] ?? '')?><br>start <?=h($r['starting_point_id'] ?? '')?></td><td><?=h($r['last_error'] ?? '')?></td></tr><?php endforeach; ?>
    </tbody></table>
  </div>
  <div class="card">
    <h2>Fast pipeline log tail</h2>
    <p class="muted small"><?=h($logPath)?></p>
    <pre><?=h($logTail !== '' ? $logTail : 'No fast pipeline log output yet.')?></pre>
  </div>
  <div class="card">
    <h2>Recent V3 events</h2>
    <table><thead><tr><th>ID</th><th>Queue</th><th>Type</th><th>Status</th><th>Message</th><th>By</th><th>Created</th></tr></thead><tbody>
    <?php if (!$events): ?><tr><td colspan="7">No events.</td></tr><?php endif; ?>
    <?php foreach ($events as $e): ?><tr><td><?=h($e['id'] ?? '')?></td><td><?=h($e['queue_id'] ?? '')?></td><td><?=h($e['event_type'] ?? '')?></td><td><?=h($e['event_status'] ?? '')?></td><td><?=h($e['event_message'] ?? '')?></td><td><?=h($e['created_by'] ?? '')?></td><td><?=h($e['created_at'] ?? '')?></td></tr><?php endforeach; ?>
    </tbody></table>
  </div>
</div>
</body>
</html>
