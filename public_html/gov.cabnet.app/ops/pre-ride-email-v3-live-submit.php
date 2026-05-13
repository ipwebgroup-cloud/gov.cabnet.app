<?php
/**
 * gov.cabnet.app — V3 Live Submit Scaffold Dashboard
 * Read-only visibility for the disabled final V3 submit worker scaffold.
 */

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Robots-Tag: noindex, nofollow', true);

const V3_LIVE_SUBMIT_PAGE_VERSION = 'v3.0.23-live-submit-disabled-scaffold-page';

function v3ls_h($value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function v3ls_badge(string $text, string $type = 'neutral'): string { return '<span class="badge badge-' . v3ls_h($type) . '">' . v3ls_h($text) . '</span>'; }
function v3ls_private_file(string $relative): string
{
    $relative = ltrim($relative, '/');
    $candidates = [dirname(__DIR__, 3) . '/gov.cabnet.app_app/' . $relative, dirname(__DIR__, 2) . '/gov.cabnet.app_app/' . $relative];
    foreach ($candidates as $file) { if (is_file($file)) { return $file; } }
    return $candidates[0];
}
function v3ls_app_context(?string &$error = null): ?array
{
    $bootstrap = v3ls_private_file('src/bootstrap.php');
    if (!is_file($bootstrap)) { $error = 'Private app bootstrap not found.'; return null; }
    try {
        $ctx = require $bootstrap;
        if (!is_array($ctx) || !isset($ctx['db']) || !method_exists($ctx['db'], 'connection')) { $error = 'Private app bootstrap did not return a usable DB context.'; return null; }
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
function v3ls_fetch_all(mysqli $db, string $sql): array
{
    $rows = []; $res = $db->query($sql);
    if ($res) { while ($row = $res->fetch_assoc()) { $rows[] = $row; } }
    return $rows;
}
function v3ls_tail_file(string $file, int $maxBytes = 14000): string
{
    if (!is_file($file) || !is_readable($file)) { return ''; }
    $size = filesize($file); if ($size === false || $size <= 0) { return ''; }
    $handle = fopen($file, 'rb'); if (!$handle) { return ''; }
    $seek = max(0, $size - $maxBytes); if ($seek > 0) { fseek($handle, $seek); }
    $data = stream_get_contents($handle); fclose($handle);
    return is_string($data) ? trim($data) : '';
}
function v3ls_cron_health(): array
{
    $file = v3ls_private_file('logs/pre_ride_email_v3_live_submit_cron.log');
    $exists = is_file($file); $readable = $exists && is_readable($file); $mtime = $exists ? (filemtime($file) ?: 0) : 0;
    $age = $mtime > 0 ? time() - $mtime : null; $tail = $readable ? v3ls_tail_file($file) : ''; $lastSummary = ''; $lastFinish = '';
    if ($tail !== '') { foreach ((preg_split('/\R/', $tail) ?: []) as $line) { $line = trim((string)$line); if (str_contains($line, 'SUMMARY')) { $lastSummary = $line; } if (str_contains($line, 'finish')) { $lastFinish = $line; } } }
    $status = 'missing'; $type = 'bad';
    if ($readable && $age !== null && $age <= 180) { $status = 'fresh'; $type = 'good'; }
    elseif ($readable && $age !== null && $age <= 900) { $status = 'stale'; $type = 'warn'; }
    elseif ($readable) { $status = 'old'; $type = 'bad'; }
    elseif ($exists) { $status = 'unreadable'; $type = 'warn'; }
    return ['file' => 'gov.cabnet.app_app/logs/pre_ride_email_v3_live_submit_cron.log', 'status' => $status, 'status_type' => $type, 'age_seconds' => $age, 'last_summary' => $lastSummary, 'last_finish' => $lastFinish, 'tail' => $tail];
}

$ctxError = null; $ctx = v3ls_app_context($ctxError); $error = null; $dbName = '';
$schema = ['queue' => false, 'events' => false, 'options' => false]; $statusRows = []; $liveRows = []; $eventRows = []; $cron = v3ls_cron_health();
if (!$ctx) { $error = $ctxError ?: 'DB context unavailable.'; }
else {
    try {
        /** @var mysqli $db */ $db = $ctx['db']->connection(); $db->set_charset('utf8mb4');
        $res = $db->query('SELECT DATABASE() AS db_name'); $tmp = $res ? $res->fetch_assoc() : null; $dbName = is_array($tmp) ? (string)($tmp['db_name'] ?? '') : '';
        $schema['queue'] = v3ls_table_exists($db, 'pre_ride_email_v3_queue'); $schema['events'] = v3ls_table_exists($db, 'pre_ride_email_v3_queue_events'); $schema['options'] = v3ls_table_exists($db, 'pre_ride_email_v3_starting_point_options');
        if ($schema['queue']) {
            $statusRows = v3ls_fetch_all($db, "SELECT queue_status, COUNT(*) AS total, SUM(CASE WHEN pickup_datetime >= NOW() THEN 1 ELSE 0 END) AS future_total, MIN(pickup_datetime) AS first_pickup, MAX(pickup_datetime) AS last_pickup FROM pre_ride_email_v3_queue GROUP BY queue_status ORDER BY FIELD(queue_status, 'live_submit_ready', 'submit_dry_run_ready', 'queued', 'blocked', 'submitted'), queue_status");
            $liveRows = v3ls_fetch_all($db, "SELECT id, dedupe_key, queue_status, customer_name, driver_name, vehicle_plate, pickup_datetime, lessor_id, driver_id, vehicle_id, starting_point_id, price_amount, created_at, locked_at FROM pre_ride_email_v3_queue WHERE queue_status = 'live_submit_ready' AND pickup_datetime >= NOW() ORDER BY pickup_datetime ASC, id ASC LIMIT 25");
        }
        if ($schema['events']) {
            $eventRows = v3ls_fetch_all($db, "SELECT id, queue_id, dedupe_key, event_type, event_status, event_message, created_by, created_at FROM pre_ride_email_v3_queue_events WHERE event_type LIKE 'live_submit%' ORDER BY id DESC LIMIT 20");
        }
    } catch (Throwable $e) { $error = $e->getMessage(); }
}
$totalLive = 0; $futureLive = 0; $submitted = 0;
foreach ($statusRows as $row) { if (($row['queue_status'] ?? '') === 'live_submit_ready') { $totalLive = (int)($row['total'] ?? 0); $futureLive = (int)($row['future_total'] ?? 0); } if (($row['queue_status'] ?? '') === 'submitted') { $submitted = (int)($row['total'] ?? 0); } }
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>V3 Live Submit Scaffold | gov.cabnet.app</title><style>
:root{--bg:#f3f6fb;--panel:#fff;--ink:#061735;--muted:#41577a;--line:#d7e1ef;--nav:#081225;--blue:#2563eb;--green:#07875a;--orange:#b85c00;--red:#b42318;--purple:#6d28d9;--soft:#f8fbff}*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--ink);font-family:Arial,Helvetica,sans-serif}.nav{background:var(--nav);color:#fff;min-height:56px;display:flex;align-items:center;gap:18px;padding:0 26px;position:sticky;top:0;z-index:5}.nav a{color:#fff;text-decoration:none;font-size:14px;white-space:nowrap}.wrap{width:min(1480px,calc(100% - 48px));margin:22px auto 60px}.card{background:var(--panel);border:1px solid var(--line);border-radius:14px;padding:18px;margin-bottom:18px;box-shadow:0 10px 26px rgba(8,18,37,.04)}.hero{border-left:7px solid var(--purple)}h1{font-size:32px;margin:0 0 10px}h2{font-size:22px;margin:0 0 14px}p{color:var(--muted);line-height:1.45}.badge{display:inline-block;padding:5px 9px;border-radius:999px;font-size:12px;font-weight:700;margin:1px 3px 1px 0;white-space:nowrap}.badge-good{background:#dcfce7;color:#166534}.badge-warn{background:#fff7ed;color:#b45309}.badge-bad{background:#fee2e2;color:#991b1b}.badge-neutral{background:#eaf1ff;color:#1e40af}.badge-purple{background:#ede9fe;color:#5b21b6}.grid{display:grid;grid-template-columns:repeat(4,1fr);gap:10px}.metric{border:1px solid var(--line);border-radius:10px;padding:14px;background:var(--soft)}.metric strong{display:block;font-size:26px}.small{font-size:13px;color:var(--muted)}table{width:100%;border-collapse:collapse;border:1px solid var(--line);border-radius:10px;overflow:hidden;background:#fff}th,td{padding:10px;border-bottom:1px solid var(--line);text-align:left;vertical-align:top;font-size:13px}th{background:#f8fbff;font-size:12px}tr:last-child td{border-bottom:0}.mono{font-family:Consolas,Monaco,monospace}.pre{white-space:pre-wrap;background:#07101f;color:#e6f0ff;padding:12px;border-radius:8px;overflow:auto;font-family:Consolas,Monaco,monospace;font-size:12px}.alert{border-radius:10px;padding:12px;margin:12px 0}.alert-info{background:#eff6ff;border:1px solid #bfdbfe}.alert-warn{background:#fff7ed;border:1px solid #fed7aa}.btn{display:inline-block;border:0;border-radius:8px;padding:9px 13px;background:var(--blue);color:#fff;text-decoration:none;font-weight:700;font-size:13px}.btn-dark{background:#263449}@media(max-width:900px){.grid{grid-template-columns:1fr}.wrap{width:calc(100% - 24px)}}
</style></head><body><nav class="nav"><strong>GC gov.cabnet.app</strong><a href="/ops/pre-ride-email-v3-queue-watch.php">V3 Watch</a><a href="/ops/pre-ride-email-v3-queue.php">V3 Queue</a><a href="/ops/pre-ride-email-v3-live-readiness.php">V3 Live Readiness</a><a href="/ops/pre-ride-email-v3-live-submit.php">V3 Live Submit</a></nav><div class="wrap">
<section class="card hero"><h1>V3 Live Submit Scaffold</h1><p>Read-only visibility for the disabled final worker scaffold. This proves the last automation path but does not submit to EDXEIX.</p><?= v3ls_badge('V3 ISOLATED','purple') ?><?= v3ls_badge('READ ONLY','good') ?><?= v3ls_badge('LIVE SUBMIT HARD-DISABLED','bad') ?><?= v3ls_badge('NO EDXEIX CALL','good') ?><?= v3ls_badge('NO AADE CALL','good') ?><?= v3ls_badge(V3_LIVE_SUBMIT_PAGE_VERSION,'neutral') ?><div style="margin-top:14px"><a class="btn" href="/ops/pre-ride-email-v3-live-submit.php">Refresh</a><a class="btn btn-dark" href="/ops/pre-ride-email-v3-live-readiness.php">Back to Live Readiness</a></div></section>
<?php if ($error): ?><section class="card"><div class="alert alert-warn"><strong>Error:</strong> <?= v3ls_h($error) ?></div></section><?php endif; ?>
<section class="card"><h2>Status</h2><p><strong>Database:</strong> <?= v3ls_h($dbName ?: '-') ?> <?= $schema['queue'] ? v3ls_badge('queue OK','good') : v3ls_badge('queue missing','bad') ?> <?= $schema['events'] ? v3ls_badge('events OK','good') : v3ls_badge('events missing','bad') ?> <?= $schema['options'] ? v3ls_badge('start options OK','good') : v3ls_badge('start options missing','bad') ?></p><div class="grid"><div class="metric"><strong><?= (int)$totalLive ?></strong><span>Total live-submit ready</span></div><div class="metric"><strong><?= (int)$futureLive ?></strong><span>Future live-submit ready</span></div><div class="metric"><strong><?= (int)$submitted ?></strong><span>V3 manually reported submitted</span></div><div class="metric"><strong>0</strong><span>Automatic EDXEIX submits</span></div></div><div class="alert alert-warn"><strong>Safety:</strong> automatic EDXEIX submit is not enabled. This page exists so we can verify the final worker path before explicit live approval.</div></section>
<section class="card"><h2>Live-submit scaffold cron</h2><p>Status: <?= v3ls_badge((string)$cron['status'], (string)$cron['status_type']) ?> <?= $cron['age_seconds'] !== null ? 'Age: ' . (int)$cron['age_seconds'] . ' sec' : '' ?></p><p class="small">File: <?= v3ls_h($cron['file']) ?></p><h3>Latest summary</h3><div class="pre"><?= v3ls_h($cron['last_summary'] ?: 'No summary yet.') ?></div><details style="margin-top:12px"><summary>Show log tail</summary><div class="pre"><?= v3ls_h($cron['tail'] ?: 'No log tail yet.') ?></div></details></section>
<section class="card"><h2>Future live-submit-ready rows</h2><table><thead><tr><th>ID</th><th>Pickup</th><th>Transfer</th><th>IDs</th><th>Price</th><th>Locked</th></tr></thead><tbody><?php if (!$liveRows): ?><tr><td colspan="6">No future live-submit-ready rows yet.</td></tr><?php else: foreach ($liveRows as $row): ?><tr><td><a href="/ops/pre-ride-email-v3-queue.php?id=<?= (int)$row['id'] ?>"><?= (int)$row['id'] ?></a></td><td><?= v3ls_h($row['pickup_datetime'] ?? '') ?></td><td><?= v3ls_h($row['customer_name'] ?? '') ?><br><span class="small"><?= v3ls_h(($row['driver_name'] ?? '') . ' / ' . ($row['vehicle_plate'] ?? '')) ?></span></td><td class="mono">Lessor: <?= v3ls_h($row['lessor_id'] ?? '') ?><br>Driver: <?= v3ls_h($row['driver_id'] ?? '') ?><br>Vehicle: <?= v3ls_h($row['vehicle_id'] ?? '') ?><br>Start: <?= v3ls_h($row['starting_point_id'] ?? '') ?></td><td><?= v3ls_h($row['price_amount'] ?? '') ?></td><td><?= v3ls_h($row['locked_at'] ?? '') ?></td></tr><?php endforeach; endif; ?></tbody></table></section>
<section class="card"><h2>V3 status counts</h2><table><thead><tr><th>Status</th><th>Total</th><th>Future</th><th>First pickup</th><th>Last pickup</th></tr></thead><tbody><?php if (!$statusRows): ?><tr><td colspan="5">No V3 queue rows yet.</td></tr><?php else: foreach ($statusRows as $row): ?><tr><td><?= v3ls_badge((string)$row['queue_status'], ($row['queue_status'] ?? '') === 'live_submit_ready' ? 'good' : 'neutral') ?></td><td><?= (int)($row['total'] ?? 0) ?></td><td><?= (int)($row['future_total'] ?? 0) ?></td><td><?= v3ls_h($row['first_pickup'] ?? '') ?></td><td><?= v3ls_h($row['last_pickup'] ?? '') ?></td></tr><?php endforeach; endif; ?></tbody></table></section>
<section class="card"><h2>Recent live-submit scaffold events</h2><table><thead><tr><th>ID</th><th>Queue</th><th>Type</th><th>Status</th><th>Message</th><th>By</th><th>Created</th></tr></thead><tbody><?php if (!$eventRows): ?><tr><td colspan="7">No live-submit scaffold events yet.</td></tr><?php else: foreach ($eventRows as $row): ?><tr><td><?= (int)($row['id'] ?? 0) ?></td><td><?= (int)($row['queue_id'] ?? 0) ?></td><td><?= v3ls_h($row['event_type'] ?? '') ?></td><td><?= v3ls_badge((string)($row['event_status'] ?? ''), 'neutral') ?></td><td><?= v3ls_h($row['event_message'] ?? '') ?></td><td><?= v3ls_h($row['created_by'] ?? '') ?></td><td><?= v3ls_h($row['created_at'] ?? '') ?></td></tr><?php endforeach; endif; ?></tbody></table></section>
</div></body></html>
