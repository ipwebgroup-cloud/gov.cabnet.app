<?php
/**
 * gov.cabnet.app — V3 Operator Approval Workflow
 * Read-only Ops page. Shows commands for the V3 approval CLI; it does not write approvals.
 */
declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Robots-Tag: noindex, nofollow', true);

function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function badge(string $text, string $type = 'neutral'): string { return '<span class="badge badge-' . h($type) . '">' . h($text) . '</span>'; }

$version = 'v3.0.58-v3-operator-approval-workflow';
$approvalPhrase = 'I APPROVE V3 ROW FOR CLOSED-GATE REHEARSAL ONLY';
$revokePhrase = 'I REVOKE V3 ROW APPROVAL';
$cliPath = '/home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_operator_approval.php';
$error = '';
$rows = [];
$approvals = [];
$approvalTableExists = false;
$gate = ['loaded' => false, 'enabled' => false, 'mode' => 'disabled', 'adapter' => 'disabled', 'hard' => false, 'ack' => false, 'ok' => false, 'blocks' => []];

try {
    $bootstrap = require '/home/cabnet/gov.cabnet.app_app/src/bootstrap.php';
    /** @var mysqli $db */
    $db = $bootstrap['db']->connection();

    $existsStmt = $db->prepare('SELECT COUNT(*) AS c FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
    $table = 'pre_ride_email_v3_live_submit_approvals';
    $existsStmt->bind_param('s', $table);
    $existsStmt->execute();
    $approvalTableExists = ((int)(($existsStmt->get_result()->fetch_assoc()['c'] ?? 0)) > 0);

    $result = $db->query("SELECT id, queue_status, customer_name, pickup_datetime, TIMESTAMPDIFF(MINUTE, NOW(), pickup_datetime) AS minutes_until_now, driver_name, vehicle_plate, lessor_id, driver_id, vehicle_id, starting_point_id, last_error, created_at, updated_at FROM pre_ride_email_v3_queue ORDER BY id DESC LIMIT 20");
    while ($row = $result->fetch_assoc()) { $rows[] = $row; }

    if ($approvalTableExists) {
        $result = $db->query("SELECT * FROM pre_ride_email_v3_live_submit_approvals ORDER BY id DESC LIMIT 20");
        while ($row = $result->fetch_assoc()) { $approvals[] = $row; }
    }

    $cfgPath = '/home/cabnet/gov.cabnet.app_config/pre_ride_email_v3_live_submit.php';
    if (is_readable($cfgPath)) {
        $cfg = require $cfgPath;
        if (is_array($cfg)) {
            $enabled = !empty($cfg['enabled']);
            $mode = trim((string)($cfg['mode'] ?? 'disabled'));
            $adapter = trim((string)($cfg['adapter'] ?? 'disabled'));
            $hard = !empty($cfg['hard_enable_live_submit']);
            $ack = trim((string)($cfg['required_acknowledgement_phrase'] ?? '')) !== '';
            $blocks = [];
            if (!$enabled) { $blocks[] = 'enabled is false'; }
            if ($mode !== 'live') { $blocks[] = 'mode is not live'; }
            if ($adapter === '' || strtolower($adapter) === 'disabled') { $blocks[] = 'adapter is disabled'; }
            if (!$hard) { $blocks[] = 'hard_enable_live_submit is false'; }
            if (!$ack) { $blocks[] = 'required acknowledgement phrase is not present'; }
            $gate = ['loaded' => true, 'enabled' => $enabled, 'mode' => $mode, 'adapter' => $adapter, 'hard' => $hard, 'ack' => $ack, 'ok' => $blocks === [], 'blocks' => $blocks];
        }
    }
} catch (Throwable $e) {
    $error = $e->getMessage();
}

function status_badge(string $status): string
{
    if ($status === 'live_submit_ready') { return badge($status, 'good'); }
    if ($status === 'submit_dry_run_ready' || $status === 'queued') { return badge($status, 'warn'); }
    if ($status === 'blocked') { return badge($status, 'bad'); }
    return badge($status, 'neutral');
}

function approval_valid_for_queue(array $approvals, int $queueId): bool
{
    $now = time();
    foreach ($approvals as $a) {
        if ((int)($a['queue_id'] ?? 0) !== $queueId) { continue; }
        if ((string)($a['approval_status'] ?? '') !== 'approved') { continue; }
        if (trim((string)($a['revoked_at'] ?? '')) !== '') { continue; }
        $expires = strtotime((string)($a['expires_at'] ?? ''));
        if ($expires !== false && $expires <= $now) { continue; }
        return true;
    }
    return false;
}

?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow">
<title>V3 Operator Approval Workflow | gov.cabnet.app</title>
<style>
:root{--bg:#f3f5fa;--panel:#fff;--ink:#1f2d4d;--muted:#5a6785;--line:#d9deea;--nav:#25304f;--blue:#5563b7;--green:#5fae63;--amber:#d39a31;--red:#c94b4b;--soft:#f8fbff}*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--ink);font-family:Arial,Helvetica,sans-serif}.top{background:#fff;border-bottom:1px solid var(--line);padding:14px 24px;display:flex;gap:16px;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:5}.brand{font-weight:800}.layout{display:grid;grid-template-columns:260px 1fr;min-height:calc(100vh - 58px)}.side{background:var(--nav);color:#fff;padding:22px 16px}.side a{display:block;color:#fff;text-decoration:none;padding:10px 12px;border-radius:10px;margin:3px 0;opacity:.92}.side a.active,.side a:hover{background:rgba(255,255,255,.13);opacity:1}.side .label{font-size:12px;text-transform:uppercase;letter-spacing:.08em;opacity:.65;margin:20px 12px 8px}.main{padding:24px}.card{background:var(--panel);border:1px solid var(--line);border-radius:14px;padding:18px;margin-bottom:18px;box-shadow:0 8px 20px rgba(31,45,77,.05)}h1{margin:0 0 8px;font-size:34px}h2{margin:0 0 14px;font-size:22px}p{color:var(--muted);line-height:1.45}.grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}.metric{background:var(--soft);border:1px solid var(--line);border-radius:12px;padding:14px}.metric strong{display:block;font-size:28px}.metric span{color:var(--muted);font-size:13px}.badge{display:inline-block;padding:5px 9px;border-radius:999px;font-weight:800;font-size:12px;margin:2px}.badge-good{background:#dcfce7;color:#166534}.badge-warn{background:#fff7ed;color:#b45309}.badge-bad{background:#fee2e2;color:#991b1b}.badge-neutral{background:#eaf1ff;color:#1e40af}.table-wrap{overflow:auto;border:1px solid var(--line);border-radius:12px}table{width:100%;border-collapse:collapse;min-width:1100px}th,td{padding:10px;border-bottom:1px solid var(--line);text-align:left;vertical-align:top;font-size:13px}th{background:#f8fafc;text-transform:uppercase;font-size:11px;letter-spacing:.04em}code,pre{background:#eef2ff;border-radius:8px}code{padding:2px 5px}pre{padding:12px;white-space:pre-wrap;overflow:auto}.safety{border-left:7px solid var(--green)}.danger{border-left:7px solid var(--red)}.warn{border-left:7px solid var(--amber)}.actions{display:flex;flex-wrap:wrap;gap:10px}.btn{display:inline-block;background:var(--blue);color:#fff;text-decoration:none;padding:10px 12px;border-radius:9px;font-weight:700}.btn.dark{background:#334155}@media(max-width:980px){.layout{grid-template-columns:1fr}.side{position:static}.grid{grid-template-columns:1fr 1fr}}@media(max-width:640px){.grid{grid-template-columns:1fr}.main{padding:14px}.top{padding:12px}}
</style>
</head>
<body>
<div class="top"><div class="brand">gov.cabnet.app Ops</div><div><?= badge('V3 ONLY','neutral') ?><?= badge('READ ONLY PAGE','good') ?><?= badge('LIVE SUBMIT DISABLED','bad') ?></div></div>
<div class="layout">
<aside class="side">
  <div class="label">V3 Automation</div>
  <a href="/ops/pre-ride-email-v3-dashboard.php">Control Center</a>
  <a href="/ops/pre-ride-email-v3-proof.php">Proof Dashboard</a>
  <a href="/ops/pre-ride-email-v3-live-package-export.php">Package Export</a>
  <a class="active" href="/ops/pre-ride-email-v3-operator-approval-workflow.php">Approval Workflow</a>
  <a href="/ops/pre-ride-email-v3-operator-approvals.php">Approval Visibility</a>
  <a href="/ops/pre-ride-email-v3-closed-gate-adapter-diagnostics.php">Adapter Diagnostics</a>
  <a href="/ops/pre-ride-email-v3-adapter-contract-probe.php">Adapter Probe</a>
  <div class="label">Monitoring</div>
  <a href="/ops/pre-ride-email-v3-monitor.php">Compact Monitor</a>
  <a href="/ops/pre-ride-email-v3-queue-focus.php">Queue Focus</a>
  <a href="/ops/pre-ride-email-v3-pulse-focus.php">Pulse Focus</a>
  <a href="/ops/pre-ride-email-v3-storage-check.php">Storage Check</a>
</aside>
<main class="main">
<section class="card safety">
<h1>V3 Operator Approval Workflow</h1>
<p>This page is read-only. It shows the approval command workflow for closed-gate rehearsal only. Approval does not open the master gate and does not submit to EDXEIX.</p>
<div><?= badge('No EDXEIX call','good') ?><?= badge('No AADE call','good') ?><?= badge('No queue mutation from page','good') ?><?= badge('V0 untouched','good') ?></div>
<?php if ($error !== ''): ?><p style="color:#991b1b"><strong>Error:</strong> <?= h($error) ?></p><?php endif; ?>
</section>
<section class="grid">
<div class="metric"><strong><?= $approvalTableExists ? 'yes' : 'no' ?></strong><span>Approval table exists</span></div>
<div class="metric"><strong><?= h((string)count($approvals)) ?></strong><span>Recent approval rows shown</span></div>
<div class="metric"><strong><?= !empty($gate['ok']) ? 'yes' : 'no' ?></strong><span>Master gate OK</span></div>
<div class="metric"><strong><?= h($gate['adapter']) ?></strong><span>Selected adapter</span></div>
</section>
<section class="card danger">
<h2>Master Gate</h2>
<p><?= badge('Loaded: ' . (!empty($gate['loaded']) ? 'yes' : 'no'), !empty($gate['loaded']) ? 'good' : 'bad') ?><?= badge('Enabled: ' . (!empty($gate['enabled']) ? 'yes' : 'no'), !empty($gate['enabled']) ? 'good' : 'bad') ?><?= badge('Mode: ' . $gate['mode'], $gate['mode'] === 'live' ? 'good' : 'bad') ?><?= badge('Adapter: ' . $gate['adapter'], $gate['adapter'] !== 'disabled' ? 'warn' : 'bad') ?><?= badge('Hard: ' . (!empty($gate['hard']) ? 'yes' : 'no'), !empty($gate['hard']) ? 'warn' : 'bad') ?></p>
<?php if (!empty($gate['blocks'])): ?><p><strong>Blocks:</strong> <?= h(implode(' | ', $gate['blocks'])) ?></p><?php endif; ?>
</section>
<section class="card warn">
<h2>Approval CLI Commands</h2>
<p>Run approvals only as <code>cabnet</code>. Approval is valid only for a currently <code>live_submit_ready</code> future-safe row.</p>
<pre>su -s /bin/bash cabnet -c "/usr/local/bin/php <?= h($cliPath) ?> --queue-id=QUEUE_ID --approve --phrase=&quot;<?= h($approvalPhrase) ?>&quot; --approved-by=&quot;Andreas&quot; --minutes=15"</pre>
<pre>su -s /bin/bash cabnet -c "/usr/local/bin/php <?= h($cliPath) ?> --queue-id=QUEUE_ID --revoke --phrase=&quot;<?= h($revokePhrase) ?>&quot; --approved-by=&quot;Andreas&quot;"</pre>
<pre>su -s /bin/bash cabnet -c "/usr/local/bin/php <?= h($cliPath) ?> --queue-id=QUEUE_ID --json"</pre>
</section>
<section class="card">
<h2>Latest V3 Queue Rows</h2>
<div class="table-wrap"><table><thead><tr><th>ID</th><th>Status</th><th>Customer</th><th>Pickup</th><th>Min</th><th>Driver</th><th>Vehicle</th><th>IDs</th><th>Valid Approval</th><th>Preview Command</th></tr></thead><tbody>
<?php foreach ($rows as $row): $qid=(int)($row['id'] ?? 0); ?>
<tr>
<td><?= h($qid) ?></td>
<td><?= status_badge((string)($row['queue_status'] ?? '')) ?></td>
<td><?= h($row['customer_name'] ?? '') ?></td>
<td><?= h($row['pickup_datetime'] ?? '') ?></td>
<td><?= h($row['minutes_until_now'] ?? '') ?></td>
<td><?= h($row['driver_name'] ?? '') ?></td>
<td><?= h($row['vehicle_plate'] ?? '') ?></td>
<td>lessor=<?= h($row['lessor_id'] ?? '') ?><br>driver=<?= h($row['driver_id'] ?? '') ?><br>vehicle=<?= h($row['vehicle_id'] ?? '') ?><br>start=<?= h($row['starting_point_id'] ?? '') ?></td>
<td><?= approval_valid_for_queue($approvals, $qid) ? badge('valid', 'good') : badge('none', 'neutral') ?></td>
<td><code>--queue-id=<?= h($qid) ?></code></td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
</section>
<section class="card">
<h2>Recent Approval Records</h2>
<?php if (!$approvalTableExists): ?><p>Approval table not found.</p><?php elseif (empty($approvals)): ?><p>No approval records yet.</p><?php else: ?>
<div class="table-wrap"><table><thead><tr><th>ID</th><th>Queue</th><th>Status</th><th>Scope</th><th>Approved By</th><th>Approved</th><th>Expires</th><th>Revoked</th><th>Note</th></tr></thead><tbody>
<?php foreach ($approvals as $a): ?>
<tr><td><?= h($a['id'] ?? '') ?></td><td><?= h($a['queue_id'] ?? '') ?></td><td><?= h($a['approval_status'] ?? '') ?></td><td><?= h($a['approval_scope'] ?? '') ?></td><td><?= h($a['approved_by'] ?? '') ?></td><td><?= h($a['approved_at'] ?? '') ?></td><td><?= h($a['expires_at'] ?? '') ?></td><td><?= h($a['revoked_at'] ?? '') ?></td><td><?= h($a['approval_note'] ?? '') ?></td></tr>
<?php endforeach; ?>
</tbody></table></div>
<?php endif; ?>
</section>
</main></div>
</body></html>
