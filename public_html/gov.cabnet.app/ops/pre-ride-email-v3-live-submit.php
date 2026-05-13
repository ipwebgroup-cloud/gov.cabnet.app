<?php
/**
 * gov.cabnet.app — V3 Live Submit Scaffold Dashboard.
 * Read-only visibility for the disabled final V3 submit worker scaffold.
 * Shows master gate and per-row approval enforcement state.
 */

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Robots-Tag: noindex, nofollow', true);

const V3_LIVE_SUBMIT_PAGE_VERSION = 'v3.0.27-live-submit-gate-approval-page';

function v3ls_h($value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function v3ls_badge(string $text, string $type = 'neutral'): string { return '<span class="badge badge-' . v3ls_h($type) . '">' . v3ls_h($text) . '</span>'; }
function v3ls_app_root(): string
{
    $fixed = '/home/cabnet/gov.cabnet.app_app';
    if (is_dir($fixed)) { return $fixed; }
    return dirname(__DIR__, 3) . '/gov.cabnet.app_app';
}
function v3ls_private_file(string $relative): string { return rtrim(v3ls_app_root(), '/') . '/' . ltrim($relative, '/'); }

/** @return array<string,mixed>|null */
function v3ls_bootstrap(?string &$error = null): ?array
{
    $error = null;
    $bootstrap = v3ls_private_file('src/bootstrap.php');
    $gate = v3ls_private_file('src/BoltMailV3/LiveSubmitGateV3.php');
    if (!is_file($bootstrap)) { $error = 'Private bootstrap not found.'; return null; }
    if (!is_file($gate)) { $error = 'LiveSubmitGateV3.php not found.'; return null; }
    try {
        require_once $gate;
        $ctx = require $bootstrap;
        if (!is_array($ctx) || !isset($ctx['db']) || !method_exists($ctx['db'], 'connection')) {
            $error = 'Private bootstrap did not return DB context.';
            return null;
        }
        return $ctx;
    } catch (Throwable $e) { $error = $e->getMessage(); return null; }
}

function v3ls_table_exists(mysqli $db, string $table): bool
{
    $stmt = $db->prepare('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1');
    if (!$stmt) { return false; }
    $stmt->bind_param('s', $table); $stmt->execute();
    return (bool)$stmt->get_result()->fetch_assoc();
}

/** @return array<int,array<string,mixed>> */
function v3ls_fetch_all(mysqli $db, string $sql): array
{
    $res = $db->query($sql);
    if (!$res) { throw new RuntimeException($db->error); }
    $rows = [];
    while ($row = $res->fetch_assoc()) { $rows[] = $row; }
    return $rows;
}

/** @return array<string,mixed> */
function v3ls_cron_health(): array
{
    $file = v3ls_private_file('logs/pre_ride_email_v3_live_submit_cron.log');
    $out = ['file' => $file, 'exists' => is_file($file), 'readable' => is_readable($file), 'age_seconds' => null, 'status' => 'missing', 'status_type' => 'bad', 'last_summary' => '', 'tail' => ''];
    if (!$out['exists']) { return $out; }
    $mtime = @filemtime($file);
    if ($mtime !== false) { $out['age_seconds'] = time() - $mtime; }
    if ($out['age_seconds'] !== null && $out['age_seconds'] <= 180) { $out['status'] = 'fresh'; $out['status_type'] = 'good'; }
    elseif ($out['age_seconds'] !== null && $out['age_seconds'] <= 900) { $out['status'] = 'stale'; $out['status_type'] = 'warn'; }
    else { $out['status'] = 'old'; $out['status_type'] = 'bad'; }
    if (!$out['readable']) { $out['status'] = 'unreadable'; $out['status_type'] = 'bad'; return $out; }
    $lines = @file($file, FILE_IGNORE_NEW_LINES) ?: [];
    $tail = array_slice($lines, -80);
    $out['tail'] = implode("\n", $tail);
    for ($i = count($lines) - 1; $i >= 0; $i--) { if (str_contains($lines[$i], 'SUMMARY')) { $out['last_summary'] = $lines[$i]; break; } }
    return $out;
}

/** @return array<string,mixed> */
function v3ls_gate_status(): array
{
    if (!class_exists('Bridge\\BoltMailV3\\LiveSubmitGateV3')) {
        return ['ok_for_future_live_submit' => false, 'version' => 'missing', 'config_loaded' => false, 'mode' => 'disabled', 'adapter' => 'disabled', 'operator_approval_required' => true, 'blocks' => ['LiveSubmitGateV3 class not loaded.'], 'warnings' => []];
    }
    return \Bridge\BoltMailV3\LiveSubmitGateV3::evaluate();
}

$error = '';
$ctx = v3ls_bootstrap($error);
$dbName = '';
$schema = ['queue' => false, 'events' => false, 'options' => false, 'approvals' => false];
$statusRows = [];
$liveRows = [];
$eventRows = [];
$gate = v3ls_gate_status();
$cron = v3ls_cron_health();
$counts = ['total_live' => 0, 'future_live' => 0, 'submitted' => 0, 'valid_approvals' => 0, 'expired_approvals' => 0];

if ($ctx) {
    try {
        /** @var mysqli $db */ $db = $ctx['db']->connection(); $db->set_charset('utf8mb4');
        $res = $db->query('SELECT DATABASE() AS db_name'); $tmp = $res ? $res->fetch_assoc() : null; $dbName = is_array($tmp) ? (string)($tmp['db_name'] ?? '') : '';
        $schema['queue'] = v3ls_table_exists($db, 'pre_ride_email_v3_queue');
        $schema['events'] = v3ls_table_exists($db, 'pre_ride_email_v3_queue_events');
        $schema['options'] = v3ls_table_exists($db, 'pre_ride_email_v3_starting_point_options');
        $schema['approvals'] = v3ls_table_exists($db, 'pre_ride_email_v3_live_submit_approvals');
        if ($schema['queue']) {
            $statusRows = v3ls_fetch_all($db, "SELECT queue_status, COUNT(*) AS total, SUM(CASE WHEN pickup_datetime >= NOW() THEN 1 ELSE 0 END) AS future_total, MIN(pickup_datetime) AS first_pickup, MAX(pickup_datetime) AS last_pickup FROM pre_ride_email_v3_queue GROUP BY queue_status ORDER BY FIELD(queue_status, 'live_submit_ready', 'submit_dry_run_ready', 'queued', 'blocked', 'submitted'), queue_status");
            foreach ($statusRows as $row) {
                if (($row['queue_status'] ?? '') === 'live_submit_ready') { $counts['total_live'] = (int)($row['total'] ?? 0); $counts['future_live'] = (int)($row['future_total'] ?? 0); }
                if (($row['queue_status'] ?? '') === 'submitted') { $counts['submitted'] = (int)($row['total'] ?? 0); }
            }
            if ($schema['approvals']) {
                $liveRows = v3ls_fetch_all($db, "SELECT q.id, q.dedupe_key, q.queue_status, q.customer_name, q.driver_name, q.vehicle_plate, q.pickup_datetime, q.lessor_id, q.driver_id, q.vehicle_id, q.starting_point_id, q.price_amount, q.created_at, a.approval_status, a.approved_by, a.approved_at, a.expires_at, a.revoked_at FROM pre_ride_email_v3_queue q LEFT JOIN pre_ride_email_v3_live_submit_approvals a ON a.queue_id=q.id WHERE q.queue_status='live_submit_ready' AND q.pickup_datetime >= NOW() ORDER BY q.pickup_datetime ASC, q.id ASC LIMIT 25");
                $approvalCounts = v3ls_fetch_all($db, "SELECT SUM(CASE WHEN approval_status='approved' AND revoked_at IS NULL AND expires_at > NOW() THEN 1 ELSE 0 END) AS valid_count, SUM(CASE WHEN approval_status='approved' AND (revoked_at IS NOT NULL OR expires_at <= NOW() OR expires_at IS NULL) THEN 1 ELSE 0 END) AS expired_count FROM pre_ride_email_v3_live_submit_approvals");
                if (isset($approvalCounts[0])) { $counts['valid_approvals'] = (int)($approvalCounts[0]['valid_count'] ?? 0); $counts['expired_approvals'] = (int)($approvalCounts[0]['expired_count'] ?? 0); }
            } else {
                $liveRows = v3ls_fetch_all($db, "SELECT id, dedupe_key, queue_status, customer_name, driver_name, vehicle_plate, pickup_datetime, lessor_id, driver_id, vehicle_id, starting_point_id, price_amount, created_at FROM pre_ride_email_v3_queue WHERE queue_status='live_submit_ready' AND pickup_datetime >= NOW() ORDER BY pickup_datetime ASC, id ASC LIMIT 25");
            }
        }
        if ($schema['events']) {
            $eventRows = v3ls_fetch_all($db, "SELECT id, queue_id, dedupe_key, event_type, event_status, event_message, created_by, created_at FROM pre_ride_email_v3_queue_events WHERE event_type LIKE 'live_submit%' OR event_type LIKE 'operator_live_submit%' ORDER BY id DESC LIMIT 20");
        }
    } catch (Throwable $e) { $error = $e->getMessage(); }
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>V3 Live Submit Scaffold | gov.cabnet.app</title><style>
:root{--bg:#f3f6fb;--panel:#fff;--ink:#061735;--muted:#41577a;--line:#d7e1ef;--nav:#081225;--blue:#2563eb;--green:#07875a;--orange:#b85c00;--red:#b42318;--purple:#6d28d9;--soft:#f8fbff}*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--ink);font-family:Arial,Helvetica,sans-serif}.nav{background:var(--nav);color:#fff;min-height:56px;display:flex;align-items:center;gap:18px;padding:0 26px;position:sticky;top:0;z-index:5}.nav a{color:#fff;text-decoration:none;font-size:14px;white-space:nowrap}.wrap{width:min(1480px,calc(100% - 48px));margin:22px auto 60px}.card{background:var(--panel);border:1px solid var(--line);border-radius:14px;padding:18px;margin-bottom:18px;box-shadow:0 10px 26px rgba(8,18,37,.04)}.hero{border-left:7px solid var(--purple)}h1{font-size:32px;margin:0 0 10px}h2{font-size:22px;margin:0 0 14px}p{color:var(--muted);line-height:1.45}.badge{display:inline-block;padding:5px 9px;border-radius:999px;font-size:12px;font-weight:700;margin:1px 3px 1px 0;white-space:nowrap}.badge-good{background:#dcfce7;color:#166534}.badge-warn{background:#fff7ed;color:#b45309}.badge-bad{background:#fee2e2;color:#991b1b}.badge-neutral{background:#eaf1ff;color:#1e40af}.badge-purple{background:#ede9fe;color:#5b21b6}.grid{display:grid;grid-template-columns:repeat(4,1fr);gap:10px}.metric{border:1px solid var(--line);border-radius:10px;padding:14px;background:var(--soft)}.metric strong{display:block;font-size:26px}.small{font-size:13px;color:var(--muted)}table{width:100%;border-collapse:collapse;border:1px solid var(--line);border-radius:10px;overflow:hidden;background:#fff}th,td{padding:10px;border-bottom:1px solid var(--line);text-align:left;vertical-align:top;font-size:13px}th{background:#f8fbff;font-size:12px}tr:last-child td{border-bottom:0}.mono{font-family:Consolas,Monaco,monospace}.pre{white-space:pre-wrap;background:#07101f;color:#e6f0ff;padding:12px;border-radius:8px;overflow:auto;font-family:Consolas,Monaco,monospace;font-size:12px}.alert{border-radius:10px;padding:12px;margin:12px 0}.alert-info{background:#eff6ff;border:1px solid #bfdbfe}.alert-warn{background:#fff7ed;border:1px solid #fed7aa}.btn{display:inline-block;border:0;border-radius:8px;padding:9px 13px;background:var(--blue);color:#fff;text-decoration:none;font-weight:700;font-size:13px}.btn-dark{background:#263449}@media(max-width:900px){.grid{grid-template-columns:1fr}.wrap{width:calc(100% - 24px)}}
</style></head><body><nav class="nav"><strong>GC gov.cabnet.app</strong><a href="/ops/pre-ride-email-v3-queue-watch.php">V3 Watch</a><a href="/ops/pre-ride-email-v3-queue.php">V3 Queue</a><a href="/ops/pre-ride-email-v3-live-readiness.php">V3 Live Readiness</a><a href="/ops/pre-ride-email-v3-live-approval.php">V3 Approval</a><a href="/ops/pre-ride-email-v3-live-submit.php">V3 Live Submit</a><a href="/ops/pre-ride-email-v3-live-submit-gate.php">V3 Submit Gate</a></nav><div class="wrap">
<section class="card hero"><h1>V3 Live Submit Scaffold</h1><p>Read-only visibility for the disabled final worker scaffold. This layer now enforces the master gate and the per-row operator approval ledger before any future live submit adapter can be considered.</p><?= v3ls_badge('V3 ISOLATED','purple') ?><?= v3ls_badge('READ ONLY','good') ?><?= v3ls_badge('LIVE SUBMIT HARD-DISABLED','bad') ?><?= v3ls_badge('MASTER GATE REQUIRED','warn') ?><?= v3ls_badge('OPERATOR APPROVAL REQUIRED','warn') ?><?= v3ls_badge('NO EDXEIX CALL','good') ?><?= v3ls_badge('NO AADE CALL','good') ?><?= v3ls_badge(V3_LIVE_SUBMIT_PAGE_VERSION,'neutral') ?><div style="margin-top:14px"><a class="btn" href="/ops/pre-ride-email-v3-live-submit.php">Refresh</a><a class="btn btn-dark" href="/ops/pre-ride-email-v3-live-readiness.php">Back to Live Readiness</a></div></section>
<?php if ($error): ?><section class="card"><div class="alert alert-warn"><strong>Error:</strong> <?= v3ls_h($error) ?></div></section><?php endif; ?>
<section class="card"><h2>Master gate</h2><div class="grid"><div class="metric"><strong><?= !empty($gate['ok_for_future_live_submit']) ? 'yes' : 'no' ?></strong><span>Gate OK</span></div><div class="metric"><strong><?= !empty($gate['config_loaded']) ? 'yes' : 'no' ?></strong><span>Config loaded</span></div><div class="metric"><strong><?= v3ls_h($gate['mode'] ?? 'disabled') ?></strong><span>Mode</span></div><div class="metric"><strong><?= v3ls_h($gate['adapter'] ?? 'disabled') ?></strong><span>Adapter</span></div></div><div class="alert alert-warn"><strong>Current gate:</strong> <?= !empty($gate['ok_for_future_live_submit']) ? 'open for future worker checks' : 'closed / hard-disabled' ?>. This still does not submit to EDXEIX.</div><?php foreach ((array)($gate['blocks'] ?? []) as $block): ?><p><?= v3ls_badge('block','bad') ?> <?= v3ls_h($block) ?></p><?php endforeach; ?><?php foreach ((array)($gate['warnings'] ?? []) as $warning): ?><p><?= v3ls_badge('warning','warn') ?> <?= v3ls_h($warning) ?></p><?php endforeach; ?></section>
<section class="card"><h2>Status</h2><p><strong>Database:</strong> <?= v3ls_h($dbName ?: '-') ?> <?= $schema['queue'] ? v3ls_badge('queue OK','good') : v3ls_badge('queue missing','bad') ?> <?= $schema['events'] ? v3ls_badge('events OK','good') : v3ls_badge('events missing','bad') ?> <?= $schema['options'] ? v3ls_badge('start options OK','good') : v3ls_badge('start options missing','bad') ?> <?= $schema['approvals'] ? v3ls_badge('approval table OK','good') : v3ls_badge('approval table missing','bad') ?></p><div class="grid"><div class="metric"><strong><?= (int)$counts['future_live'] ?></strong><span>Future live-submit ready</span></div><div class="metric"><strong><?= (int)$counts['valid_approvals'] ?></strong><span>Valid approvals</span></div><div class="metric"><strong><?= (int)$counts['submitted'] ?></strong><span>V3 manually reported submitted</span></div><div class="metric"><strong>0</strong><span>Automatic EDXEIX submits</span></div></div><div class="alert alert-warn"><strong>Safety:</strong> automatic EDXEIX submit is not enabled. The scaffold now refuses rows unless the master gate and per-row approval are valid, and even then submit remains hard-disabled.</div></section>
<section class="card"><h2>Live-submit scaffold cron</h2><p>Status: <?= v3ls_badge((string)$cron['status'], (string)$cron['status_type']) ?> <?= $cron['age_seconds'] !== null ? 'Age: ' . (int)$cron['age_seconds'] . ' sec' : '' ?></p><p class="small">File: <?= v3ls_h($cron['file']) ?></p><h3>Latest summary</h3><div class="pre"><?= v3ls_h($cron['last_summary'] ?: 'No summary yet.') ?></div><details style="margin-top:12px"><summary>Show log tail</summary><div class="pre"><?= v3ls_h($cron['tail'] ?: 'No log tail yet.') ?></div></details></section>
<section class="card"><h2>Future live-submit-ready rows</h2><table><thead><tr><th>ID</th><th>Pickup</th><th>Transfer</th><th>IDs</th><th>Approval</th><th>Price</th></tr></thead><tbody><?php if (!$liveRows): ?><tr><td colspan="6">No future live-submit-ready rows yet.</td></tr><?php else: foreach ($liveRows as $row): $approvalValid = (($row['approval_status'] ?? '') === 'approved' && empty($row['revoked_at']) && !empty($row['expires_at']) && strtotime((string)$row['expires_at']) > time()); ?><tr><td><a href="/ops/pre-ride-email-v3-queue.php?id=<?= (int)$row['id'] ?>"><?= (int)$row['id'] ?></a></td><td><?= v3ls_h($row['pickup_datetime'] ?? '') ?></td><td><?= v3ls_h($row['customer_name'] ?? '') ?><br><span class="small"><?= v3ls_h(($row['driver_name'] ?? '') . ' / ' . ($row['vehicle_plate'] ?? '')) ?></span></td><td class="mono">Lessor: <?= v3ls_h($row['lessor_id'] ?? '') ?><br>Driver: <?= v3ls_h($row['driver_id'] ?? '') ?><br>Vehicle: <?= v3ls_h($row['vehicle_id'] ?? '') ?><br>Start: <?= v3ls_h($row['starting_point_id'] ?? '') ?></td><td><?= $approvalValid ? v3ls_badge('valid','good') : v3ls_badge('missing/invalid','bad') ?><br><span class="small"><?= v3ls_h($row['approved_by'] ?? '') ?> <?= v3ls_h($row['expires_at'] ?? '') ?></span></td><td><?= v3ls_h($row['price_amount'] ?? '') ?></td></tr><?php endforeach; endif; ?></tbody></table></section>
<section class="card"><h2>V3 status counts</h2><table><thead><tr><th>Status</th><th>Total</th><th>Future</th><th>First pickup</th><th>Last pickup</th></tr></thead><tbody><?php if (!$statusRows): ?><tr><td colspan="5">No V3 queue rows yet.</td></tr><?php else: foreach ($statusRows as $row): ?><tr><td><?= v3ls_badge((string)$row['queue_status'], ($row['queue_status'] ?? '') === 'live_submit_ready' ? 'good' : 'neutral') ?></td><td><?= (int)($row['total'] ?? 0) ?></td><td><?= (int)($row['future_total'] ?? 0) ?></td><td><?= v3ls_h($row['first_pickup'] ?? '') ?></td><td><?= v3ls_h($row['last_pickup'] ?? '') ?></td></tr><?php endforeach; endif; ?></tbody></table></section>
<section class="card"><h2>Recent live-submit scaffold events</h2><table><thead><tr><th>ID</th><th>Queue</th><th>Type</th><th>Status</th><th>Message</th><th>By</th><th>Created</th></tr></thead><tbody><?php if (!$eventRows): ?><tr><td colspan="7">No live-submit scaffold events yet.</td></tr><?php else: foreach ($eventRows as $row): ?><tr><td><?= (int)($row['id'] ?? 0) ?></td><td><?= (int)($row['queue_id'] ?? 0) ?></td><td><?= v3ls_h($row['event_type'] ?? '') ?></td><td><?= v3ls_badge((string)($row['event_status'] ?? ''), 'neutral') ?></td><td><?= v3ls_h($row['event_message'] ?? '') ?></td><td><?= v3ls_h($row['created_by'] ?? '') ?></td><td><?= v3ls_h($row['created_at'] ?? '') ?></td></tr><?php endforeach; endif; ?></tbody></table></section>
</div></body></html>
