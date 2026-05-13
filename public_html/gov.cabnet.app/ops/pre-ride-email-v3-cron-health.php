<?php
/**
 * gov.cabnet.app — V3 Pre-Ride Email Cron Health
 *
 * Read-only health screen for isolated V3 intake and submit dry-run cron logs.
 * Safety: no DB writes, no EDXEIX calls, no AADE calls, no production route changes.
 */

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Robots-Tag: noindex, nofollow', true);

function v3h_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function v3h_badge(string $text, string $type = 'neutral'): string
{
    return '<span class="badge badge-' . v3h_h($type) . '">' . v3h_h($text) . '</span>';
}

function v3h_tail_lines(string $file, int $maxLines = 80): array
{
    if (!is_file($file) || !is_readable($file)) {
        return [];
    }

    $lines = @file($file, FILE_IGNORE_NEW_LINES);
    if (!is_array($lines)) {
        return [];
    }

    return array_slice($lines, -$maxLines);
}

function v3h_latest_matching(array $lines, string $needle): string
{
    for ($i = count($lines) - 1; $i >= 0; $i--) {
        if (strpos((string)$lines[$i], $needle) !== false) {
            return (string)$lines[$i];
        }
    }
    return '';
}

function v3h_log_info(string $label, string $file): array
{
    $exists = is_file($file);
    $readable = $exists && is_readable($file);
    $mtime = $exists ? (int)(filemtime($file) ?: 0) : 0;
    $age = $mtime > 0 ? time() - $mtime : null;
    $tail = $readable ? v3h_tail_lines($file, 100) : [];
    $summary = v3h_latest_matching($tail, 'SUMMARY');
    $finish = v3h_latest_matching($tail, 'finish exit_code=');
    $blocked = array_values(array_filter($tail, static fn($line): bool => strpos((string)$line, 'BLOCKED') !== false));
    $inserted = array_values(array_filter($tail, static fn($line): bool => strpos((string)$line, 'INSERT') !== false || strpos((string)$line, 'inserted') !== false));

    $status = 'missing';
    $statusType = 'bad';
    if ($readable) {
        if ($age !== null && $age <= 180) {
            $status = 'fresh';
            $statusType = 'good';
        } elseif ($age !== null && $age <= 600) {
            $status = 'stale';
            $statusType = 'warn';
        } else {
            $status = 'old';
            $statusType = 'bad';
        }
    }

    return [
        'label' => $label,
        'file' => $file,
        'exists' => $exists,
        'readable' => $readable,
        'mtime' => $mtime,
        'mtime_text' => $mtime > 0 ? date('Y-m-d H:i:s T', $mtime) : '',
        'age_seconds' => $age,
        'age_text' => $age === null ? '-' : (string)$age . ' sec',
        'status' => $status,
        'status_type' => $statusType,
        'summary' => $summary,
        'finish' => $finish,
        'blocked_examples' => array_slice($blocked, -5),
        'insert_examples' => array_slice($inserted, -5),
        'tail' => array_slice($tail, -80),
    ];
}

function v3h_app_context(?string &$error = null): ?array
{
    static $ctx = null;
    static $loaded = false;
    static $loadError = null;

    if ($loaded) {
        $error = $loadError;
        return is_array($ctx) ? $ctx : null;
    }

    $loaded = true;
    $bootstrap = dirname(__DIR__, 3) . '/gov.cabnet.app_app/src/bootstrap.php';
    if (!is_file($bootstrap)) {
        $loadError = 'Private app bootstrap not found.';
        $error = $loadError;
        return null;
    }

    try {
        $ctx = require $bootstrap;
        if (!is_array($ctx)) {
            $loadError = 'Private app bootstrap did not return context array.';
            $error = $loadError;
            return null;
        }
        $error = null;
        return $ctx;
    } catch (Throwable $e) {
        $loadError = $e->getMessage();
        $error = $loadError;
        return null;
    }
}

function v3h_table_exists(mysqli $db, string $table): bool
{
    try {
        $stmt = $db->prepare('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1');
        if (!$stmt) { return false; }
        $stmt->bind_param('s', $table);
        $stmt->execute();
        return (bool)$stmt->get_result()->fetch_assoc();
    } catch (Throwable) {
        return false;
    }
}

function v3h_db_stats(): array
{
    $stats = [
        'ok' => false,
        'db' => '',
        'error' => '',
        'queue_table' => false,
        'events_table' => false,
        'total_rows' => 0,
        'future_rows' => 0,
        'ready_rows' => 0,
        'queued_rows' => 0,
        'latest_rows' => [],
    ];

    $ctxError = null;
    $ctx = v3h_app_context($ctxError);
    if (!$ctx || !isset($ctx['db']) || !method_exists($ctx['db'], 'connection')) {
        $stats['error'] = $ctxError ?: 'DB connection unavailable.';
        return $stats;
    }

    try {
        /** @var mysqli $db */
        $db = $ctx['db']->connection();
        $res = $db->query('SELECT DATABASE() AS db');
        $row = $res ? $res->fetch_assoc() : null;
        $stats['db'] = (string)($row['db'] ?? '');
        $stats['queue_table'] = v3h_table_exists($db, 'pre_ride_email_v3_queue');
        $stats['events_table'] = v3h_table_exists($db, 'pre_ride_email_v3_queue_events');

        if (!$stats['queue_table']) {
            $stats['error'] = 'V3 queue table is not installed.';
            return $stats;
        }

        $stats['total_rows'] = (int)(($db->query('SELECT COUNT(*) AS c FROM pre_ride_email_v3_queue')->fetch_assoc()['c'] ?? 0));
        $stats['future_rows'] = (int)(($db->query("SELECT COUNT(*) AS c FROM pre_ride_email_v3_queue WHERE pickup_datetime > NOW()")->fetch_assoc()['c'] ?? 0));
        $stats['ready_rows'] = (int)(($db->query("SELECT COUNT(*) AS c FROM pre_ride_email_v3_queue WHERE queue_status = 'submit_dry_run_ready'")->fetch_assoc()['c'] ?? 0));
        $stats['queued_rows'] = (int)(($db->query("SELECT COUNT(*) AS c FROM pre_ride_email_v3_queue WHERE queue_status = 'queued'")->fetch_assoc()['c'] ?? 0));

        $rows = [];
        $rs = $db->query("SELECT id, dedupe_key, queue_status, customer_name, pickup_datetime, driver_name, vehicle_plate, lessor_id, driver_id, vehicle_id, starting_point_id, created_at FROM pre_ride_email_v3_queue ORDER BY id DESC LIMIT 10");
        if ($rs) {
            while ($r = $rs->fetch_assoc()) { $rows[] = $r; }
        }
        $stats['latest_rows'] = $rows;
        $stats['ok'] = true;
    } catch (Throwable $e) {
        $stats['error'] = $e->getMessage();
    }

    return $stats;
}

$logs = [
    v3h_log_info('V3 intake cron', '/home/cabnet/gov.cabnet.app_app/logs/pre_ride_email_v3_cron.log'),
    v3h_log_info('V3 submit dry-run cron', '/home/cabnet/gov.cabnet.app_app/logs/pre_ride_email_v3_submit_dry_run_cron.log'),
];
$dbStats = v3h_db_stats();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>V3 Cron Health | gov.cabnet.app</title>
<style>
:root{--bg:#f3f6fb;--panel:#fff;--ink:#07152f;--muted:#41577a;--line:#d7e1ef;--nav:#081225;--green:#07875a;--red:#b42318;--orange:#b85c00;--blue:#2563eb;--purple:#6d28d9;--soft:#f8fbff}*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--ink);font-family:Arial,Helvetica,sans-serif}.nav{background:var(--nav);color:#fff;min-height:56px;display:flex;align-items:center;gap:18px;padding:0 26px;position:sticky;top:0;z-index:5;overflow:auto}.nav strong{white-space:nowrap}.nav a{color:#fff;text-decoration:none;font-size:14px;white-space:nowrap}.wrap{width:min(1480px,calc(100% - 48px));margin:26px auto 60px}.card{background:var(--panel);border:1px solid var(--line);border-radius:14px;padding:18px;margin-bottom:18px;box-shadow:0 10px 26px rgba(8,18,37,.04)}.hero{border-left:7px solid var(--purple)}h1{font-size:32px;margin:0 0 10px}h2{font-size:22px;margin:0 0 14px}h3{font-size:17px;margin:18px 0 8px}p{color:var(--muted);line-height:1.45}.badge{display:inline-block;padding:5px 9px;border-radius:999px;font-size:12px;font-weight:700;margin:1px 3px 1px 0;white-space:nowrap}.badge-good{background:#dcfce7;color:#166534}.badge-warn{background:#fff7ed;color:#b45309}.badge-bad{background:#fee2e2;color:#991b1b}.badge-neutral{background:#eaf1ff;color:#1e40af}.grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}.two{display:grid;grid-template-columns:1fr 1fr;gap:18px}.metric{border:1px solid var(--line);border-radius:10px;padding:14px;background:var(--soft);min-height:86px}.metric strong{display:block;font-size:27px;line-height:1.08}.metric span{color:var(--muted);font-size:14px}.logbox{background:#07111f;color:#dbeafe;border-radius:10px;padding:13px;white-space:pre-wrap;overflow:auto;max-height:340px;font-family:Consolas,Menlo,Monaco,monospace;font-size:12px;line-height:1.35}.small{font-size:13px;color:var(--muted)}table{width:100%;border-collapse:collapse;border:1px solid var(--line);border-radius:10px;overflow:hidden;background:#fff}th,td{border-bottom:1px solid var(--line);padding:9px 10px;text-align:left;font-size:13px;vertical-align:top}th{background:#f8fafc}tr:last-child td{border-bottom:0}.goodline{color:#166534}.warnline{color:#b45309}.badline{color:#991b1b}.actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:12px}.btn{display:inline-block;border:0;border-radius:8px;padding:10px 13px;background:#2563eb;color:#fff;text-decoration:none;font-weight:700;font-size:13px}.btn.dark{background:#334155}@media(max-width:980px){.grid,.two{grid-template-columns:1fr}.wrap{width:calc(100% - 24px);margin-top:14px}.nav{padding:0 14px}}
</style>
</head>
<body>
<nav class="nav">
  <strong>GC gov.cabnet.app</strong>
  <a href="/ops/index.php">Operations Console</a>
  <a href="/ops/pre-ride-email-toolv3.php">V3 Intake</a>
  <a href="/ops/pre-ride-email-toolv3.php?watch=1">V3 Watch</a>
  <a href="/ops/pre-ride-email-v3-queue.php">V3 Queue</a>
  <a href="/ops/pre-ride-email-v3-cron-health.php">V3 Cron Health</a>
</nav>
<main class="wrap">
  <section class="card hero">
    <h1>V3 Cron Health</h1>
    <p>Read-only visibility for the isolated V3 pre-ride email intake cron and submit dry-run cron. This page does not write to the database and does not call EDXEIX or AADE.</p>
    <div>
      <?= v3h_badge('V3 ISOLATED', 'neutral') ?>
      <?= v3h_badge('READ ONLY', 'good') ?>
      <?= v3h_badge('NO EDXEIX CALL', 'good') ?>
      <?= v3h_badge('NO AADE CALL', 'good') ?>
    </div>
    <div class="actions">
      <a class="btn" href="/ops/pre-ride-email-v3-cron-health.php">Refresh</a>
      <a class="btn dark" href="/ops/pre-ride-email-v3-queue.php">Back to V3 queue</a>
    </div>
  </section>

  <section class="card">
    <h2>V3 queue snapshot</h2>
    <?php if (!$dbStats['ok']): ?>
      <p class="badline"><strong>DB status:</strong> <?= v3h_h($dbStats['error']) ?></p>
    <?php else: ?>
      <p><strong>Database:</strong> <?= v3h_h($dbStats['db']) ?> <?= v3h_badge($dbStats['queue_table'] ? 'queue table OK' : 'queue table missing', $dbStats['queue_table'] ? 'good' : 'bad') ?> <?= v3h_badge($dbStats['events_table'] ? 'events table OK' : 'events table missing', $dbStats['events_table'] ? 'good' : 'bad') ?></p>
      <div class="grid">
        <div class="metric"><strong><?= (int)$dbStats['total_rows'] ?></strong><span>Total V3 queue rows</span></div>
        <div class="metric"><strong><?= (int)$dbStats['future_rows'] ?></strong><span>Future rows</span></div>
        <div class="metric"><strong><?= (int)$dbStats['queued_rows'] ?></strong><span>Queued rows</span></div>
        <div class="metric"><strong><?= (int)$dbStats['ready_rows'] ?></strong><span>Submit dry-run ready</span></div>
      </div>
      <h3>Latest V3 queue rows</h3>
      <table>
        <thead><tr><th>ID</th><th>Status</th><th>Pickup</th><th>Transfer</th><th>IDs</th><th>Created</th></tr></thead>
        <tbody>
        <?php if (empty($dbStats['latest_rows'])): ?>
          <tr><td colspan="6">No V3 queue rows yet.</td></tr>
        <?php else: foreach ($dbStats['latest_rows'] as $r): ?>
          <tr>
            <td><?= v3h_h($r['id'] ?? '') ?><br><span class="small"><?= v3h_h($r['dedupe_key'] ?? '') ?></span></td>
            <td><?= v3h_badge((string)($r['queue_status'] ?? ''), 'neutral') ?></td>
            <td><?= v3h_h($r['pickup_datetime'] ?? '') ?></td>
            <td><?= v3h_h($r['customer_name'] ?? '') ?><br><?= v3h_h($r['driver_name'] ?? '') ?> / <?= v3h_h($r['vehicle_plate'] ?? '') ?></td>
            <td>Lessor: <?= v3h_h($r['lessor_id'] ?? '') ?><br>Driver: <?= v3h_h($r['driver_id'] ?? '') ?><br>Vehicle: <?= v3h_h($r['vehicle_id'] ?? '') ?><br>Start: <?= v3h_h($r['starting_point_id'] ?? '') ?></td>
            <td><?= v3h_h($r['created_at'] ?? '') ?></td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </section>

  <section class="two">
  <?php foreach ($logs as $log): ?>
    <article class="card">
      <h2><?= v3h_h($log['label']) ?></h2>
      <p>
        Status: <?= v3h_badge((string)$log['status'], (string)$log['status_type']) ?>
        <?= $log['readable'] ? v3h_badge('readable', 'good') : v3h_badge('not readable', 'bad') ?>
      </p>
      <p class="small"><strong>File:</strong> <?= v3h_h($log['file']) ?></p>
      <p class="small"><strong>Last update:</strong> <?= v3h_h($log['mtime_text'] ?: '-') ?> · <strong>Age:</strong> <?= v3h_h($log['age_text']) ?></p>
      <h3>Latest summary</h3>
      <?php if ($log['summary']): ?>
        <div class="logbox"><?= v3h_h($log['summary']) ?></div>
      <?php else: ?>
        <p class="warnline">No SUMMARY line found yet.</p>
      <?php endif; ?>
      <?php if ($log['finish']): ?>
        <p class="small"><strong>Latest finish:</strong> <?= v3h_h($log['finish']) ?></p>
      <?php endif; ?>
      <h3>Recent blocked examples</h3>
      <?php if (!empty($log['blocked_examples'])): ?>
        <div class="logbox"><?= v3h_h(implode("\n", $log['blocked_examples'])) ?></div>
      <?php else: ?>
        <p class="small">No recent blocked examples in the current tail.</p>
      <?php endif; ?>
      <details>
        <summary><strong>Show log tail</strong></summary>
        <div class="logbox"><?= v3h_h(implode("\n", $log['tail'])) ?></div>
      </details>
    </article>
  <?php endforeach; ?>
  </section>
</main>
</body>
</html>
