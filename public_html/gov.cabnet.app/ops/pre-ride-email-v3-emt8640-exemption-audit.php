<?php
/**
 * gov.cabnet.app — EMT8640 V3 exemption audit page.
 * Read-only visibility for the permanent EMT8640 vehicle exemption.
 */

declare(strict_types=1);

const EMT8640_AUDIT_VERSION = 'v2026-05-13-emt8640-v3-exemption-audit-page';
const EMT8640_AUDIT_PLATE = 'EMT8640';
const EMT8640_AUDIT_UUID = 'f9170acc-3bc4-43c5-9eed-65d9cadee490';

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** @return mysqli */
function emt8640_audit_db(): mysqli
{
    $bootstrap = dirname(__DIR__, 3) . '/gov.cabnet.app_app/src/bootstrap.php';
    if (!is_file($bootstrap)) {
        throw new RuntimeException('Missing bootstrap: ' . $bootstrap);
    }
    $ctx = require $bootstrap;
    if (!is_array($ctx) || !isset($ctx['db']) || !method_exists($ctx['db'], 'connection')) {
        throw new RuntimeException('Bootstrap did not return DB context.');
    }
    $db = $ctx['db']->connection();
    if (!$db instanceof mysqli) {
        throw new RuntimeException('DB connection is not mysqli.');
    }
    return $db;
}

function emt8640_table_exists(mysqli $db, string $table): bool
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
function emt8640_audit_rows(mysqli $db): array
{
    $sql = "SELECT id, dedupe_key, queue_status, lessor_id, vehicle_id, vehicle_plate, customer_name, driver_name, pickup_datetime, last_error, created_at, updated_at
            FROM pre_ride_email_v3_queue
            WHERE UPPER(REPLACE(REPLACE(COALESCE(vehicle_plate,''), ' ', ''), '-', '')) = 'EMT8640'
               OR payload_json LIKE '%EMT8640%'
               OR parsed_fields_json LIKE '%EMT8640%'
               OR raw_email_preview LIKE '%EMT8640%'
               OR payload_json LIKE '%f9170acc-3bc4-43c5-9eed-65d9cadee490%'
               OR raw_email_preview LIKE '%f9170acc-3bc4-43c5-9eed-65d9cadee490%'
            ORDER BY id DESC
            LIMIT 100";
    $res = $db->query($sql);
    if (!$res) {
        return [];
    }
    $rows = [];
    while ($row = $res->fetch_assoc()) {
        $rows[] = $row;
    }
    return $rows;
}

/** @return array<int,array<string,mixed>> */
function emt8640_audit_events(mysqli $db): array
{
    $sql = "SELECT id, queue_id, dedupe_key, event_type, event_status, event_message, created_by, created_at
            FROM pre_ride_email_v3_queue_events
            WHERE event_type IN ('vehicle_exemption_blocked')
               OR event_message LIKE '%EMT8640%'
               OR event_context_json LIKE '%EMT8640%'
            ORDER BY id DESC
            LIMIT 50";
    $res = $db->query($sql);
    if (!$res) {
        return [];
    }
    $rows = [];
    while ($row = $res->fetch_assoc()) {
        $rows[] = $row;
    }
    return $rows;
}

$error = '';
$dbName = '';
$schema = ['queue' => false, 'events' => false];
$rows = [];
$events = [];
try {
    $db = emt8640_audit_db();
    $dbName = (string)(($db->query('SELECT DATABASE() AS db')->fetch_assoc()['db'] ?? ''));
    $schema = [
        'queue' => emt8640_table_exists($db, 'pre_ride_email_v3_queue'),
        'events' => emt8640_table_exists($db, 'pre_ride_email_v3_queue_events'),
    ];
    if ($schema['queue']) {
        $rows = emt8640_audit_rows($db);
    }
    if ($schema['events']) {
        $events = emt8640_audit_events($db);
    }
} catch (Throwable $e) {
    $error = $e->getMessage();
}

$activeRows = array_values(array_filter($rows, static fn(array $r): bool => !in_array((string)($r['queue_status'] ?? ''), ['blocked','cancelled','failed','failed_permanent','expired','submitted'], true)));
$blockedRows = array_values(array_filter($rows, static fn(array $r): bool => (string)($r['queue_status'] ?? '') === 'blocked'));
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>V3 EMT8640 Exemption Audit</title>
    <style>
        :root{--nav:#061226;--bg:#f4f7fb;--card:#fff;--line:#d7e3f3;--green:#d5f8df;--red:#ffe4e7;--blue:#e9f2ff;--text:#061226;--muted:#16315f;--purple:#6d28d9}
        *{box-sizing:border-box} body{margin:0;background:var(--bg);color:var(--text);font-family:Arial,Helvetica,sans-serif;font-size:14px}.top{background:var(--nav);color:#fff;padding:16px 22px;font-weight:700}.top a{color:#fff;text-decoration:none;margin-left:22px}.wrap{max-width:1325px;margin:20px auto;padding:0 18px}.hero,.card{background:var(--card);border:1px solid var(--line);border-radius:12px;padding:20px;margin-bottom:18px}.hero{border-left:6px solid var(--purple)}h1{margin:0 0 12px;font-size:30px}h2{margin:0 0 14px;font-size:22px}.badge{display:inline-block;border-radius:999px;background:var(--green);padding:6px 10px;margin:3px;font-weight:700;font-size:12px}.badge.red{background:var(--red)}.badge.blue{background:var(--blue)}.grid{display:grid;grid-template-columns:repeat(4,1fr);gap:10px}.metric{border:1px solid var(--line);border-radius:10px;background:#f8fbff;padding:14px}.metric strong{display:block;font-size:28px}.warn{background:#fff7ed;border:1px solid #fed7aa;border-radius:8px;padding:12px;margin:12px 0}table{width:100%;border-collapse:collapse;background:#fff}th,td{border:1px solid var(--line);padding:10px;text-align:left;vertical-align:top}th{background:#f8fbff}.mono{font-family:Consolas,monospace;font-size:12px}.muted{color:var(--muted)}.ok{background:var(--green);border-radius:999px;padding:4px 8px;font-weight:700}.bad{background:var(--red);border-radius:999px;padding:4px 8px;font-weight:700}@media(max-width:850px){.grid{grid-template-columns:1fr}.top a{display:inline-block;margin:8px 12px 0 0}}
    </style>
</head>
<body>
<div class="top">GC gov.cabnet.app <a href="/ops/pre-ride-email-v3-queue-watch.php">V3 Watch</a><a href="/ops/pre-ride-email-v3-queue.php">V3 Queue</a><a href="/ops/pre-ride-email-v3-live-submit.php">V3 Live Submit</a><a href="/ops/pre-ride-email-v3-emt8640-exemption-audit.php">EMT8640 Audit</a></div>
<div class="wrap">
    <section class="hero">
        <h1>V3 EMT8640 Exemption Audit</h1>
        <p class="muted">Read-only visibility for the permanent vehicle exemption. Version <span class="mono"><?= h(EMT8640_AUDIT_VERSION) ?></span>.</p>
        <span class="badge blue">V3 ISOLATED</span><span class="badge">READ ONLY</span><span class="badge">NO EDXEIX CALL</span><span class="badge">NO AADE CALL</span><span class="badge red">EMT8640 EXEMPT</span>
    </section>

    <?php if ($error !== ''): ?><section class="card"><div class="warn"><strong>Error:</strong> <?= h($error) ?></div></section><?php endif; ?>

    <section class="card">
        <h2>Status</h2>
        <p><strong>Database:</strong> <?= h($dbName) ?> <span class="badge <?= $schema['queue'] ? '' : 'red' ?>">queue <?= $schema['queue'] ? 'OK' : 'missing' ?></span> <span class="badge <?= $schema['events'] ? '' : 'red' ?>">events <?= $schema['events'] ? 'OK' : 'missing' ?></span></p>
        <div class="grid">
            <div class="metric"><strong><?= count($rows) ?></strong>Total EMT8640 V3 rows found</div>
            <div class="metric"><strong><?= count($activeRows) ?></strong>Active EMT8640 rows</div>
            <div class="metric"><strong><?= count($blockedRows) ?></strong>Blocked EMT8640 rows</div>
            <div class="metric"><strong><?= count($events) ?></strong>Recent exemption events</div>
        </div>
        <div class="warn"><strong>Rule:</strong> EMT8640 must not receive voucher, driver email, invoice/AADE receipt, or V3/EDXEIX processing.</div>
    </section>

    <section class="card">
        <h2>Vehicle identity</h2>
        <table><tbody>
            <tr><th>Plate</th><td class="mono"><?= h(EMT8640_AUDIT_PLATE) ?></td></tr>
            <tr><th>Bolt vehicle identifier</th><td class="mono"><?= h(EMT8640_AUDIT_UUID) ?></td></tr>
            <tr><th>Required status for active V3 rows</th><td><span class="bad">blocked</span></td></tr>
        </tbody></table>
    </section>

    <section class="card">
        <h2>Matching V3 queue rows</h2>
        <table>
            <thead><tr><th>ID</th><th>Status</th><th>Pickup</th><th>Transfer</th><th>Vehicle</th><th>Last error</th><th>Updated</th></tr></thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="7">No EMT8640 V3 queue rows found.</td></tr>
            <?php else: foreach ($rows as $row): ?>
                <tr>
                    <td class="mono"><?= h($row['id'] ?? '') ?></td>
                    <td><?= (string)($row['queue_status'] ?? '') === 'blocked' ? '<span class="bad">blocked</span>' : '<span class="ok">' . h($row['queue_status'] ?? '') . '</span>' ?></td>
                    <td><?= h($row['pickup_datetime'] ?? '') ?></td>
                    <td><?= h($row['customer_name'] ?? '') ?><br><span class="muted"><?= h($row['driver_name'] ?? '') ?></span></td>
                    <td class="mono"><?= h($row['vehicle_plate'] ?? '') ?><br><?= h($row['vehicle_id'] ?? '') ?></td>
                    <td><?= h($row['last_error'] ?? '') ?></td>
                    <td><?= h($row['updated_at'] ?? '') ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </section>

    <section class="card">
        <h2>Recent exemption events</h2>
        <table>
            <thead><tr><th>ID</th><th>Queue</th><th>Type</th><th>Status</th><th>Message</th><th>By</th><th>Created</th></tr></thead>
            <tbody>
            <?php if (!$events): ?>
                <tr><td colspan="7">No EMT8640 exemption events found yet.</td></tr>
            <?php else: foreach ($events as $event): ?>
                <tr>
                    <td class="mono"><?= h($event['id'] ?? '') ?></td>
                    <td class="mono"><?= h($event['queue_id'] ?? '') ?></td>
                    <td class="mono"><?= h($event['event_type'] ?? '') ?></td>
                    <td><?= h($event['event_status'] ?? '') ?></td>
                    <td><?= h($event['event_message'] ?? '') ?></td>
                    <td><?= h($event['created_by'] ?? '') ?></td>
                    <td><?= h($event['created_at'] ?? '') ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </section>
</div>
</body>
</html>
