<?php
declare(strict_types=1);

/**
 * gov.cabnet.app — V3 live-submit final rehearsal page
 *
 * Read-only dashboard. Does not submit to EDXEIX, does not call AADE, and does not write to DB.
 */

const PRV3_REHEARSAL_PAGE_VERSION = 'v3.0.29-live-submit-final-rehearsal-page';

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function badge(string $text, string $kind = 'neutral'): string
{
    $colors = [
        'ok' => '#dcfce7;color:#166534',
        'bad' => '#fee2e2;color:#991b1b',
        'warn' => '#ffedd5;color:#9a3412',
        'info' => '#dbeafe;color:#1d4ed8',
        'neutral' => '#eef2ff;color:#3730a3',
    ];
    $style = $colors[$kind] ?? $colors['neutral'];
    return '<span class="badge" style="' . $style . '">' . h($text) . '</span>';
}

function db_ctx(): array
{
    $bootstrap = dirname(__DIR__, 3) . '/gov.cabnet.app_app/src/bootstrap.php';
    if (!is_file($bootstrap)) {
        throw new RuntimeException('Missing bootstrap: ' . $bootstrap);
    }
    $ctx = require $bootstrap;
    if (!is_array($ctx) || !isset($ctx['db']) || !method_exists($ctx['db'], 'connection')) {
        throw new RuntimeException('Bootstrap did not return usable DB context.');
    }
    $db = $ctx['db']->connection();
    if (!$db instanceof mysqli) {
        throw new RuntimeException('DB connection is not mysqli.');
    }
    return [$ctx, $db];
}

function table_exists(mysqli $db, string $table): bool
{
    $stmt = $db->prepare('SELECT COUNT(*) AS c FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('s', $table);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return (int)($row['c'] ?? 0) > 0;
}

function table_columns(mysqli $db, string $table): array
{
    $cols = [];
    $stmt = $db->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
    if (!$stmt) {
        return $cols;
    }
    $stmt->bind_param('s', $table);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $name = (string)($row['COLUMN_NAME'] ?? '');
        if ($name !== '') {
            $cols[$name] = true;
        }
    }
    return $cols;
}

function master_gate_snapshot(): array
{
    $path = dirname(__DIR__, 3) . '/gov.cabnet.app_config/pre_ride_email_v3_live_submit.php';
    $loaded = false;
    $config = [];
    $error = '';
    if (is_file($path)) {
        try {
            $loadedConfig = require $path;
            if (is_array($loadedConfig)) {
                $loaded = true;
                $config = $loadedConfig;
            } else {
                $error = 'Config did not return an array.';
            }
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    } else {
        $error = 'Config file not found.';
    }

    $enabled = filter_var($config['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
    $mode = strtolower(trim((string)($config['mode'] ?? 'disabled')));
    $adapter = strtolower(trim((string)($config['adapter'] ?? 'disabled')));
    $ack = trim((string)($config['acknowledgement'] ?? $config['acknowledgement_phrase'] ?? ''));
    $hard = filter_var($config['hard_enable_live_submit'] ?? false, FILTER_VALIDATE_BOOLEAN);
    $minFuture = max(1, (int)($config['min_future_minutes'] ?? 1));
    $blocks = [];
    if (!$loaded) { $blocks[] = 'server live-submit config is missing or invalid'; }
    if (!$enabled) { $blocks[] = 'enabled is false'; }
    if ($mode !== 'live') { $blocks[] = 'mode is not live'; }
    if ($ack === '') { $blocks[] = 'required acknowledgement phrase is not present'; }
    if ($adapter === '' || $adapter === 'disabled') { $blocks[] = 'adapter is disabled'; }
    if (!$hard) { $blocks[] = 'hard_enable_live_submit is false'; }

    return [
        'ok' => $blocks === [],
        'config_loaded' => $loaded,
        'config_error' => $error,
        'enabled' => $enabled,
        'mode' => $mode,
        'adapter' => $adapter === '' ? 'disabled' : $adapter,
        'hard_enable_live_submit' => $hard,
        'min_future_minutes' => $minFuture,
        'blocks' => $blocks,
    ];
}

function fetch_rows(mysqli $db, int $limit): array
{
    if (!table_exists($db, 'pre_ride_email_v3_queue')) {
        return [];
    }
    $status = 'live_submit_ready';
    $stmt = $db->prepare("SELECT id,dedupe_key,queue_status,lessor_id,driver_id,vehicle_id,starting_point_id,customer_name,pickup_datetime,estimated_end_datetime,price_amount,pickup_address,dropoff_address FROM pre_ride_email_v3_queue WHERE queue_status = ? ORDER BY pickup_datetime ASC, id ASC LIMIT ?");
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('si', $status, $limit);
    $stmt->execute();
    $rows = [];
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $rows[] = $row;
    }
    return $rows;
}

function verified_start(mysqli $db, string $lessor, string $start): bool
{
    if ($lessor === '' || $start === '' || !table_exists($db, 'pre_ride_email_v3_starting_point_options')) {
        return false;
    }
    $stmt = $db->prepare("SELECT id FROM pre_ride_email_v3_starting_point_options WHERE edxeix_lessor_id=? AND edxeix_starting_point_id=? AND is_active=1 LIMIT 1");
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('ss', $lessor, $start);
    $stmt->execute();
    return is_array($stmt->get_result()->fetch_assoc());
}

function has_approval(mysqli $db, string $queueId, string $dedupe): bool
{
    if (!table_exists($db, 'pre_ride_email_v3_live_submit_approvals')) {
        return false;
    }
    $cols = table_columns($db, 'pre_ride_email_v3_live_submit_approvals');
    $where = [];
    $params = [];
    $types = '';
    if (isset($cols['queue_id'])) {
        $where[] = 'queue_id=?';
        $params[] = $queueId;
        $types .= 's';
    }
    if (isset($cols['dedupe_key']) && $dedupe !== '') {
        $where[] = 'dedupe_key=?';
        $params[] = $dedupe;
        $types .= 's';
    }
    if ($where === []) {
        return false;
    }
    $status = isset($cols['approval_status']) ? " AND approval_status IN ('approved','valid','active')" : (isset($cols['status']) ? " AND status IN ('approved','valid','active')" : '');
    $expiry = isset($cols['expires_at']) ? " AND (expires_at IS NULL OR expires_at >= NOW())" : '';
    $approved = isset($cols['approved_at']) ? " AND approved_at IS NOT NULL" : '';
    $sql = 'SELECT 1 FROM pre_ride_email_v3_live_submit_approvals WHERE (' . implode(' OR ', $where) . ')' . $status . $expiry . $approved . ' LIMIT 1';
    $stmt = $db->prepare($sql);
    if (!$stmt) {
        return false;
    }
    $refs = [];
    $refs[] = &$types;
    foreach ($params as $i => &$v) {
        $refs[] = &$v;
    }
    $stmt->bind_param(...$refs);
    $stmt->execute();
    return is_array($stmt->get_result()->fetch_assoc());
}

$error = '';
$rows = [];
$checks = [];
$gate = master_gate_snapshot();
$schema = [];
$dbName = '';

try {
    [, $db] = db_ctx();
    $dbName = (string)($db->query('SELECT DATABASE() AS db')->fetch_assoc()['db'] ?? '');
    $schema = [
        'queue' => table_exists($db, 'pre_ride_email_v3_queue'),
        'events' => table_exists($db, 'pre_ride_email_v3_queue_events'),
        'start_options' => table_exists($db, 'pre_ride_email_v3_starting_point_options'),
        'approvals' => table_exists($db, 'pre_ride_email_v3_live_submit_approvals'),
    ];
    $rows = fetch_rows($db, 50);
    foreach ($rows as $row) {
        $blocks = [];
        if (!$gate['ok']) {
            foreach ($gate['blocks'] as $b) {
                $blocks[] = 'gate: ' . $b;
            }
        }
        $approval = has_approval($db, (string)$row['id'], (string)$row['dedupe_key']);
        $start = verified_start($db, (string)$row['lessor_id'], (string)$row['starting_point_id']);
        if (!$approval) { $blocks[] = 'operator approval missing'; }
        if (!$start) { $blocks[] = 'starting point not verified'; }
        foreach (['lessor_id','driver_id','vehicle_id','starting_point_id','customer_name','pickup_datetime','estimated_end_datetime','price_amount'] as $field) {
            if (trim((string)($row[$field] ?? '')) === '') {
                $blocks[] = 'missing ' . $field;
            }
        }
        $checks[] = ['row' => $row, 'ok' => $blocks === [], 'blocks' => $blocks, 'approval' => $approval, 'start' => $start];
    }
} catch (Throwable $e) {
    $error = $e->getMessage();
}

$passed = count(array_filter($checks, static fn($c) => !empty($c['ok'])));
$blocked = count($checks) - $passed;
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>V3 Live Submit Final Rehearsal</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
:root{--ink:#001b44;--muted:#244064;--line:#cfe0f4;--bg:#f4f8fd;--card:#fff;--nav:#061226;--purple:#6d28d9}
*{box-sizing:border-box}body{margin:0;background:var(--bg);font-family:Arial,Helvetica,sans-serif;color:var(--ink)}.nav{background:var(--nav);color:#fff;padding:16px 28px;display:flex;gap:24px;align-items:center}.nav a{color:#fff;text-decoration:none;font-weight:700}.wrap{max-width:1325px;margin:20px auto;padding:0 18px}.hero,.card{background:var(--card);border:1px solid var(--line);border-radius:12px;padding:20px;margin-bottom:18px}.hero{border-left:6px solid var(--purple)}h1{margin:0 0 10px;font-size:31px}h2{margin:0 0 14px;font-size:22px}.badge{display:inline-block;border-radius:999px;padding:6px 10px;font-size:12px;font-weight:700;margin:3px}.grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px}.metric{border:1px solid var(--line);border-radius:10px;padding:16px;background:#f8fbff}.metric strong{display:block;font-size:26px}table{width:100%;border-collapse:collapse}th,td{border:1px solid var(--line);padding:10px;text-align:left;vertical-align:top;font-size:13px}th{background:#f8fbff}.alert{padding:13px;border:1px solid #fed7aa;background:#fff7ed;border-radius:9px}.pre{background:#07111f;color:#e8f1ff;padding:12px;border-radius:8px;white-space:pre-wrap;font-size:12px}.btn{display:inline-block;background:#2563eb;color:#fff;text-decoration:none;padding:10px 14px;border-radius:8px;font-weight:700}.btn.dark{background:#263449}
@media(max-width:850px){.grid{grid-template-columns:1fr}.nav{overflow:auto}.wrap{padding:0 10px}}
</style>
</head>
<body>
<div class="nav">
  <strong>GC gov.cabnet.app</strong>
  <a href="/ops/pre-ride-email-v3-queue-watch.php">V3 Watch</a>
  <a href="/ops/pre-ride-email-v3-live-submit.php">V3 Live Submit</a>
  <a href="/ops/pre-ride-email-v3-live-submit-gate.php">V3 Submit Gate</a>
  <a href="/ops/pre-ride-email-v3-live-submit-adapter.php">V3 Adapter</a>
  <a href="/ops/pre-ride-email-v3-live-submit-rehearsal.php">V3 Rehearsal</a>
</div>
<div class="wrap">
  <section class="hero">
    <h1>V3 Live-Submit Final Rehearsal</h1>
    <p>Read-only rehearsal of the final live-submit chain. This page does not submit to EDXEIX, does not call AADE, and does not write to the database.</p>
    <?= badge('V3 ISOLATED') ?> <?= badge('READ ONLY','ok') ?> <?= badge('NO EDXEIX CALL','ok') ?> <?= badge('NO AADE CALL','ok') ?> <?= badge(PRV3_REHEARSAL_PAGE_VERSION,'info') ?>
    <div style="margin-top:12px"><a class="btn" href="">Refresh</a> <a class="btn dark" href="/ops/pre-ride-email-v3-live-submit.php">Back to Live Submit</a></div>
  </section>

  <?php if ($error !== ''): ?>
    <section class="card"><div class="alert"><strong>Error:</strong> <?= h($error) ?></div></section>
  <?php endif; ?>

  <section class="card">
    <h2>Status</h2>
    <p><strong>Database:</strong> <?= h($dbName) ?>
      <?= badge(!empty($schema['queue']) ? 'queue OK' : 'queue missing', !empty($schema['queue']) ? 'ok' : 'bad') ?>
      <?= badge(!empty($schema['start_options']) ? 'start options OK' : 'start options missing', !empty($schema['start_options']) ? 'ok' : 'bad') ?>
      <?= badge(!empty($schema['approvals']) ? 'approvals OK' : 'approvals missing', !empty($schema['approvals']) ? 'ok' : 'bad') ?>
    </p>
    <div class="grid">
      <div class="metric"><strong><?= h((string)count($checks)) ?></strong>Rows checked</div>
      <div class="metric"><strong><?= h((string)$passed) ?></strong>Pre-live passed</div>
      <div class="metric"><strong><?= h((string)$blocked) ?></strong>Blocked</div>
      <div class="metric"><strong><?= !empty($gate['ok']) ? 'yes' : 'no' ?></strong>Master gate OK</div>
    </div>
  </section>

  <section class="card">
    <h2>Master gate</h2>
    <div class="grid">
      <div class="metric"><strong><?= !empty($gate['config_loaded']) ? 'yes' : 'no' ?></strong>Config loaded</div>
      <div class="metric"><strong><?= h((string)$gate['mode']) ?></strong>Mode</div>
      <div class="metric"><strong><?= h((string)$gate['adapter']) ?></strong>Adapter</div>
      <div class="metric"><strong><?= !empty($gate['hard_enable_live_submit']) ? 'yes' : 'no' ?></strong>Hard enabled</div>
    </div>
    <?php if (!empty($gate['blocks'])): ?>
      <div class="pre" style="margin-top:12px"><?php foreach ($gate['blocks'] as $b) { echo h('BLOCK: ' . $b) . "\n"; } ?></div>
    <?php endif; ?>
  </section>

  <section class="card">
    <h2>Rows in final rehearsal</h2>
    <table>
      <thead><tr><th>ID</th><th>Status</th><th>Transfer</th><th>Final package</th><th>Blocks</th></tr></thead>
      <tbody>
      <?php if ($checks === []): ?>
        <tr><td colspan="5">No live_submit_ready rows ready for final rehearsal.</td></tr>
      <?php endif; ?>
      <?php foreach ($checks as $c): $r = $c['row']; ?>
        <tr>
          <td><?= h($r['id'] ?? '') ?></td>
          <td><?= !empty($c['ok']) ? badge('pre-live pass','ok') : badge('blocked','bad') ?><br><?= !empty($c['approval']) ? badge('approval','ok') : badge('no approval','warn') ?><br><?= !empty($c['start']) ? badge('start verified','ok') : badge('start not verified','bad') ?></td>
          <td><?= h($r['customer_name'] ?? '') ?><br><?= h($r['pickup_datetime'] ?? '') ?></td>
          <td><code>lessor=<?= h($r['lessor_id'] ?? '') ?></code><br><code>driver=<?= h($r['driver_id'] ?? '') ?></code><br><code>vehicle=<?= h($r['vehicle_id'] ?? '') ?></code><br><code>start=<?= h($r['starting_point_id'] ?? '') ?></code><br><code>price=<?= h($r['price_amount'] ?? '') ?></code></td>
          <td><?php foreach ($c['blocks'] as $b) { echo '• ' . h($b) . '<br>'; } ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </section>
</div>
</body>
</html>
