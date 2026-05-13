<?php
/**
 * gov.cabnet.app — V3 live-submit adapter contract probe page.
 * Read-only. No EDXEIX call. No AADE call. No DB writes.
 */

declare(strict_types=1);

const PRV3_ADAPTER_PAGE_VERSION = 'v3.0.28-live-submit-adapter-contract-page';

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function prv3_adapter_app_root(): string
{
    $env = getenv('GOV_CABNET_APP_ROOT');
    if (is_string($env) && trim($env) !== '') {
        return rtrim(trim($env), '/');
    }
    return '/home/cabnet/gov.cabnet.app_app';
}

/** @return array{db:?mysqli,error:string} */
function prv3_adapter_db(): array
{
    $bootstrap = prv3_adapter_app_root() . '/src/bootstrap.php';
    if (!is_file($bootstrap)) {
        return ['db' => null, 'error' => 'Missing bootstrap: ' . $bootstrap];
    }
    try {
        $ctx = require $bootstrap;
        if (!is_array($ctx) || !isset($ctx['db']) || !method_exists($ctx['db'], 'connection')) {
            return ['db' => null, 'error' => 'Bootstrap did not return DB context.'];
        }
        $db = $ctx['db']->connection();
        if (!$db instanceof mysqli) {
            return ['db' => null, 'error' => 'DB connection is not mysqli.'];
        }
        return ['db' => $db, 'error' => ''];
    } catch (Throwable $e) {
        return ['db' => null, 'error' => $e->getMessage()];
    }
}

function prv3_adapter_table_exists(mysqli $db, string $table): bool
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

/** @return array<int,array<string,mixed>> */
function prv3_adapter_rows(mysqli $db): array
{
    $rows = [];
    if (!prv3_adapter_table_exists($db, 'pre_ride_email_v3_queue')) {
        return $rows;
    }
    $sql = "SELECT id, dedupe_key, queue_status, pickup_datetime, customer_name, driver_name, vehicle_plate, lessor_id, driver_id, vehicle_id, starting_point_id, price_amount, pickup_address, dropoff_address, payload_json FROM pre_ride_email_v3_queue WHERE queue_status='live_submit_ready' ORDER BY pickup_datetime ASC, id ASC LIMIT 20";
    $res = $db->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
    }
    return $rows;
}

function prv3_adapter_latest_approval_status(mysqli $db, int $queueId): string
{
    if (!prv3_adapter_table_exists($db, 'pre_ride_email_v3_live_submit_approvals')) {
        return 'approval table missing';
    }
    $stmt = $db->prepare('SELECT * FROM pre_ride_email_v3_live_submit_approvals WHERE queue_id=? ORDER BY id DESC LIMIT 1');
    if (!$stmt) {
        return 'approval query failed';
    }
    $stmt->bind_param('i', $queueId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if (!is_array($row)) {
        return 'missing';
    }
    return trim((string)($row['approval_status'] ?? $row['status'] ?? 'unknown')) ?: 'unknown';
}

function prv3_adapter_start_valid(mysqli $db, string $lessorId, string $startId): bool
{
    if (!prv3_adapter_table_exists($db, 'pre_ride_email_v3_starting_point_options')) {
        return false;
    }
    $stmt = $db->prepare('SELECT id FROM pre_ride_email_v3_starting_point_options WHERE edxeix_lessor_id=? AND edxeix_starting_point_id=? AND is_active=1 LIMIT 1');
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('ss', $lessorId, $startId);
    $stmt->execute();
    return is_array($stmt->get_result()->fetch_assoc());
}

function prv3_adapter_field_hash(array $row): string
{
    $package = [
        'lessor' => (string)($row['lessor_id'] ?? ''),
        'lessee_name' => (string)($row['customer_name'] ?? ''),
        'driver' => (string)($row['driver_id'] ?? ''),
        'vehicle' => (string)($row['vehicle_id'] ?? ''),
        'starting_point_id' => (string)($row['starting_point_id'] ?? ''),
        'boarding_point' => (string)($row['pickup_address'] ?? ''),
        'disembark_point' => (string)($row['dropoff_address'] ?? ''),
        'started_at' => (string)($row['pickup_datetime'] ?? ''),
        'price' => (string)($row['price_amount'] ?? ''),
    ];
    return substr(hash('sha256', json_encode($package, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: ''), 0, 16);
}

$root = prv3_adapter_app_root();
$adapterFiles = [
    'LiveSubmitAdapterV3.php' => $root . '/src/BoltMailV3/LiveSubmitAdapterV3.php',
    'DisabledLiveSubmitAdapterV3.php' => $root . '/src/BoltMailV3/DisabledLiveSubmitAdapterV3.php',
    'DryRunLiveSubmitAdapterV3.php' => $root . '/src/BoltMailV3/DryRunLiveSubmitAdapterV3.php',
];
$fileStatus = [];
foreach ($adapterFiles as $label => $path) {
    $fileStatus[$label] = is_file($path);
}

$dbInfo = prv3_adapter_db();
$db = $dbInfo['db'];
$error = $dbInfo['error'];
$schema = ['queue' => false, 'approvals' => false, 'start_options' => false];
$rows = [];
if ($db instanceof mysqli) {
    $schema = [
        'queue' => prv3_adapter_table_exists($db, 'pre_ride_email_v3_queue'),
        'approvals' => prv3_adapter_table_exists($db, 'pre_ride_email_v3_live_submit_approvals'),
        'start_options' => prv3_adapter_table_exists($db, 'pre_ride_email_v3_starting_point_options'),
    ];
    $rows = prv3_adapter_rows($db);
}
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>V3 Live-Submit Adapter Probe</title>
<style>
body{margin:0;background:#f4f7fb;color:#001b44;font-family:Arial,Helvetica,sans-serif}.nav{background:#071226;color:#fff;padding:14px 24px;font-weight:700}.nav a{color:#fff;text-decoration:none;margin-right:18px}.wrap{max-width:1320px;margin:22px auto;padding:0 18px}.card{background:#fff;border:1px solid #cfe0f5;border-radius:14px;padding:20px;margin-bottom:18px;box-shadow:0 8px 24px rgba(0,20,70,.04)}.hero{border-left:6px solid #6d28d9}.badges span{display:inline-block;border-radius:999px;padding:6px 10px;margin:3px;font-size:12px;font-weight:700}.green{background:#d8f8e3;color:#00651f}.blue{background:#dbeafe;color:#123c9c}.red{background:#fde2e2;color:#9b1111}.orange{background:#fff3d7;color:#9a5300}.grid{display:grid;grid-template-columns:repeat(4,1fr);gap:10px}.metric{background:#f8fbff;border:1px solid #cfe0f5;border-radius:10px;padding:16px}.metric b{display:block;font-size:26px}table{width:100%;border-collapse:collapse;font-size:13px}th,td{border:1px solid #d8e5f7;padding:8px;text-align:left;vertical-align:top}th{background:#f4f8ff}.mono{font-family:Consolas,monospace}.small{font-size:12px;color:#234}.ok{color:#007a2d;font-weight:700}.no{color:#b00020;font-weight:700}@media(max-width:900px){.grid{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="nav"><a href="/ops/pre-ride-email-v3-queue-watch.php">V3 Watch</a><a href="/ops/pre-ride-email-v3-live-submit.php">V3 Live Submit</a><a href="/ops/pre-ride-email-v3-live-submit-gate.php">V3 Submit Gate</a><a href="/ops/pre-ride-email-v3-live-submit-adapter.php">V3 Adapter Probe</a></div>
<div class="wrap">
  <div class="card hero">
    <h1>V3 Live-Submit Adapter Probe</h1>
    <p>Read-only adapter contract visibility. This page verifies the disabled/dry-run adapter layer exists. It does not submit to EDXEIX.</p>
    <div class="badges"><span class="blue">V3 ISOLATED</span><span class="green">READ ONLY</span><span class="red">NO EDXEIX CALL</span><span class="green">NO AADE CALL</span><span class="orange"><?= h(PRV3_ADAPTER_PAGE_VERSION) ?></span></div>
  </div>

  <?php if ($error !== ''): ?><div class="card"><b>Error:</b> <?= h($error) ?></div><?php endif; ?>

  <div class="card">
    <h2>Status</h2>
    <div class="grid">
      <div class="metric"><b><?= h($db instanceof mysqli ? ($db->query('SELECT DATABASE() AS db')->fetch_assoc()['db'] ?? 'connected') : 'no') ?></b>Database</div>
      <div class="metric"><b><?= h((string)count(array_filter($fileStatus))) ?></b>Adapter files present</div>
      <div class="metric"><b><?= h((string)count($rows)) ?></b>live_submit_ready rows</div>
      <div class="metric"><b>no</b>Live-capable adapter</div>
    </div>
    <p class="small"><b>Safety:</b> no EDXEIX POST, no AADE call, no DB writes, no production submission tables.</p>
  </div>

  <div class="card">
    <h2>Adapter contract files</h2>
    <table><thead><tr><th>File</th><th>Status</th><th>Path</th></tr></thead><tbody>
    <?php foreach ($adapterFiles as $label => $path): ?><tr><td><?= h($label) ?></td><td class="<?= $fileStatus[$label] ? 'ok' : 'no' ?>"><?= $fileStatus[$label] ? 'present' : 'missing' ?></td><td class="mono small"><?= h($path) ?></td></tr><?php endforeach; ?>
    </tbody></table>
  </div>

  <div class="card">
    <h2>Schema</h2>
    <table><tbody>
      <tr><th>V3 queue</th><td><?= !empty($schema['queue']) ? '<span class="ok">OK</span>' : '<span class="no">missing</span>' ?></td></tr>
      <tr><th>V3 approvals</th><td><?= !empty($schema['approvals']) ? '<span class="ok">OK</span>' : '<span class="no">missing</span>' ?></td></tr>
      <tr><th>V3 starting-point options</th><td><?= !empty($schema['start_options']) ? '<span class="ok">OK</span>' : '<span class="no">missing</span>' ?></td></tr>
    </tbody></table>
  </div>

  <div class="card">
    <h2>Rows available to adapter probe</h2>
    <table><thead><tr><th>ID</th><th>Status</th><th>Transfer</th><th>Start guard</th><th>Approval</th><th>Payload hash</th></tr></thead><tbody>
    <?php if (!$rows): ?><tr><td colspan="6">No live_submit_ready rows.</td></tr><?php endif; ?>
    <?php foreach ($rows as $row): $qid=(int)($row['id']??0); $startOk=$db instanceof mysqli ? prv3_adapter_start_valid($db,(string)($row['lessor_id']??''),(string)($row['starting_point_id']??'')) : false; $approval=$db instanceof mysqli ? prv3_adapter_latest_approval_status($db,$qid) : 'unknown'; ?>
      <tr><td class="mono"><?= h($qid) ?></td><td><?= h($row['queue_status'] ?? '') ?></td><td><?= h(($row['customer_name'] ?? '') . ' / ' . ($row['driver_name'] ?? '') . ' / ' . ($row['vehicle_plate'] ?? '')) ?><br><span class="small"><?= h($row['pickup_datetime'] ?? '') ?></span></td><td class="<?= $startOk ? 'ok' : 'no' ?>"><?= $startOk ? 'valid' : 'invalid/missing' ?> <span class="mono"><?= h($row['starting_point_id'] ?? '') ?></span></td><td><?= h($approval) ?></td><td class="mono"><?= h(prv3_adapter_field_hash($row)) ?></td></tr>
    <?php endforeach; ?>
    </tbody></table>
  </div>
</div>
</body>
</html>
