<?php
/**
 * gov.cabnet.app — V3 queue expiry guard dashboard.
 * Read-only. No EDXEIX calls. No AADE calls. No DB writes.
 */

declare(strict_types=1);

date_default_timezone_set('Europe/Athens');

const PRV3_EXPIRY_DASH_VERSION = 'v3.0.34-v3-queue-expiry-guard-dashboard';
const PRV3_EXPIRY_DASH_QUEUE = 'pre_ride_email_v3_queue';
const PRV3_EXPIRY_DASH_EVENTS = 'pre_ride_email_v3_queue_events';

function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

function dash_bootstrap(): array
{
    $paths = [
        '/home/cabnet/gov.cabnet.app_app/src/bootstrap.php',
        dirname(__DIR__, 3) . '/gov.cabnet.app_app/src/bootstrap.php',
    ];
    foreach ($paths as $path) {
        if (is_file($path)) {
            $ctx = require $path;
            if (is_array($ctx) && isset($ctx['db']) && method_exists($ctx['db'], 'connection')) {
                return $ctx;
            }
        }
    }
    throw new RuntimeException('Private app bootstrap not found.');
}

function table_exists(mysqli $db, string $table): bool
{
    $stmt = $db->prepare('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1');
    if (!$stmt) { return false; }
    $stmt->bind_param('s', $table);
    $stmt->execute();
    return (bool)$stmt->get_result()->fetch_assoc();
}

function db_name(mysqli $db): string
{
    $res = $db->query('SELECT DATABASE() AS db');
    $row = $res ? $res->fetch_assoc() : null;
    return is_array($row) ? (string)($row['db'] ?? '') : '';
}

function minutes_until(?string $pickup): ?int
{
    $pickup = trim((string)$pickup);
    if ($pickup === '' || $pickup === '0000-00-00 00:00:00') { return null; }
    try {
        $tz = new DateTimeZone('Europe/Athens');
        $dt = new DateTimeImmutable($pickup, $tz);
        $now = new DateTimeImmutable('now', $tz);
        return (int)floor(($dt->getTimestamp() - $now->getTimestamp()) / 60);
    } catch (Throwable) {
        return null;
    }
}

$report = [
    'ok' => false,
    'database' => '',
    'schema_ok' => false,
    'active_rows' => [],
    'expired_active_rows' => [],
    'recent_expiry_events' => [],
    'error' => '',
];

try {
    $ctx = dash_bootstrap();
    /** @var mysqli $db */
    $db = $ctx['db']->connection();
    $db->set_charset('utf8mb4');
    $report['database'] = db_name($db);
    $queueOk = table_exists($db, PRV3_EXPIRY_DASH_QUEUE);
    $eventsOk = table_exists($db, PRV3_EXPIRY_DASH_EVENTS);
    $report['schema_ok'] = $queueOk && $eventsOk;
    if (!$report['schema_ok']) {
        throw new RuntimeException('V3 queue/events schema is missing.');
    }

    $sql = "SELECT id, dedupe_key, queue_status, customer_name, driver_name, vehicle_plate, lessor_id, starting_point_id, pickup_datetime, created_at, last_error
            FROM " . PRV3_EXPIRY_DASH_QUEUE . "
            WHERE queue_status IN ('queued','ready','submit_dry_run_ready','live_submit_ready','live_submit_pending')
            ORDER BY pickup_datetime ASC, id ASC
            LIMIT 50";
    $res = $db->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $row['minutes_until'] = minutes_until($row['pickup_datetime'] ?? '');
            $report['active_rows'][] = $row;
            if ($row['minutes_until'] !== null && (int)$row['minutes_until'] < 0) {
                $report['expired_active_rows'][] = $row;
            }
        }
    }

    $sql = "SELECT id, queue_id, dedupe_key, event_type, event_status, event_message, created_by, created_at
            FROM " . PRV3_EXPIRY_DASH_EVENTS . "
            WHERE event_type = 'v3_expiry_guard_blocked'
            ORDER BY id DESC
            LIMIT 20";
    $res = $db->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $report['recent_expiry_events'][] = $row;
        }
    }

    $report['ok'] = true;
} catch (Throwable $e) {
    $report['error'] = $e->getMessage();
}

$statusClass = !$report['ok'] ? 'danger' : (count($report['expired_active_rows']) > 0 ? 'warning' : 'success');
$statusText = !$report['ok'] ? 'Error' : (count($report['expired_active_rows']) > 0 ? 'Expired active rows need blocking' : 'No expired active V3 rows');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta http-equiv="refresh" content="30">
  <title>V3 Expiry Guard</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>body{background:#f6f7fb}.card{border-radius:1rem}.mono{font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace}.small-table{font-size:.875rem}</style>
</head>
<body>
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-start mb-3">
    <div>
      <h1 class="h3 mb-1">V3 Queue Expiry Guard</h1>
      <div class="text-muted small mono"><?= h(PRV3_EXPIRY_DASH_VERSION) ?></div>
    </div>
    <span class="badge text-bg-<?= h($statusClass) ?> fs-6"><?= h($statusText) ?></span>
  </div>

  <div class="alert alert-secondary small">
    Read-only dashboard. No DB writes, no EDXEIX calls, no AADE calls, no production submission table writes.
    Auto-refreshes every 30 seconds.
  </div>

  <?php if (!$report['ok']): ?>
    <div class="alert alert-danger"><strong>Error:</strong> <?= h($report['error']) ?></div>
  <?php endif; ?>

  <div class="row g-3 mb-3">
    <div class="col-md-3"><div class="card p-3"><div class="text-muted small">Database</div><div class="h5 mono"><?= h($report['database'] ?: '-') ?></div></div></div>
    <div class="col-md-3"><div class="card p-3"><div class="text-muted small">Schema</div><div class="h5"><?= $report['schema_ok'] ? 'OK' : 'Missing' ?></div></div></div>
    <div class="col-md-3"><div class="card p-3"><div class="text-muted small">Active rows</div><div class="h5"><?= count($report['active_rows']) ?></div></div></div>
    <div class="col-md-3"><div class="card p-3"><div class="text-muted small">Expired active</div><div class="h5"><?= count($report['expired_active_rows']) ?></div></div></div>
  </div>

  <div class="card mb-3">
    <div class="card-header fw-semibold">Active V3 rows watched by expiry guard</div>
    <div class="table-responsive">
      <table class="table table-sm table-striped align-middle mb-0 small-table">
        <thead><tr><th>ID</th><th>Status</th><th>Pickup</th><th>Min</th><th>Customer</th><th>Driver / Vehicle</th><th>Start</th></tr></thead>
        <tbody>
        <?php if (!$report['active_rows']): ?>
          <tr><td colspan="7" class="text-muted">No active V3 rows.</td></tr>
        <?php else: foreach ($report['active_rows'] as $row): ?>
          <?php $expired = $row['minutes_until'] !== null && (int)$row['minutes_until'] < 0; ?>
          <tr class="<?= $expired ? 'table-warning' : '' ?>">
            <td class="mono"><?= h($row['id']) ?></td>
            <td><span class="badge text-bg-<?= $expired ? 'warning' : 'primary' ?>"><?= h($row['queue_status']) ?></span></td>
            <td class="mono"><?= h($row['pickup_datetime']) ?></td>
            <td class="mono"><?= $row['minutes_until'] === null ? '-' : h((string)$row['minutes_until']) ?></td>
            <td><?= h($row['customer_name']) ?></td>
            <td><?= h(trim((string)$row['driver_name'] . ' / ' . (string)$row['vehicle_plate'])) ?></td>
            <td class="mono"><?= h($row['lessor_id']) ?> / <?= h($row['starting_point_id']) ?></td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="card">
    <div class="card-header fw-semibold">Recent expiry events</div>
    <div class="table-responsive">
      <table class="table table-sm table-striped align-middle mb-0 small-table">
        <thead><tr><th>ID</th><th>Queue</th><th>Status</th><th>Message</th><th>By</th><th>Created</th></tr></thead>
        <tbody>
        <?php if (!$report['recent_expiry_events']): ?>
          <tr><td colspan="6" class="text-muted">No expiry guard events yet.</td></tr>
        <?php else: foreach ($report['recent_expiry_events'] as $row): ?>
          <tr>
            <td class="mono"><?= h($row['id']) ?></td>
            <td class="mono"><?= h($row['queue_id']) ?></td>
            <td><?= h($row['event_status']) ?></td>
            <td><?= h($row['event_message']) ?></td>
            <td><?= h($row['created_by']) ?></td>
            <td class="mono"><?= h($row['created_at']) ?></td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="mt-3 small text-muted">Generated at <?= h((new DateTimeImmutable('now', new DateTimeZone('Europe/Athens')))->format('Y-m-d H:i:s T')) ?></div>
</div>
</body>
</html>
