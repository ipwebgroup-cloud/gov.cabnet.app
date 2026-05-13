<?php
/**
 * gov.cabnet.app — V3 Live-Submit Payload Audit Dashboard
 *
 * Read-only page that previews the exact EDXEIX form payload a future live-submit
 * worker would use. It does not call EDXEIX and does not write to the DB.
 */

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Robots-Tag: noindex, nofollow', true);

const V3_PAYLOAD_PAGE_VERSION = 'v3.0.24-live-submit-payload-audit-page';

function v3pa_h($value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function v3pa_badge(string $text, string $type = 'neutral'): string { return '<span class="badge badge-' . v3pa_h($type) . '">' . v3pa_h($text) . '</span>'; }
function v3pa_private_file(string $relative): string
{
    $relative = ltrim($relative, '/');
    $candidates = [dirname(__DIR__, 3) . '/gov.cabnet.app_app/' . $relative, dirname(__DIR__, 2) . '/gov.cabnet.app_app/' . $relative];
    foreach ($candidates as $file) { if (is_file($file)) { return $file; } }
    return $candidates[0];
}
function v3pa_app_context(?string &$error = null): ?array
{
    $bootstrap = v3pa_private_file('src/bootstrap.php');
    if (!is_file($bootstrap)) { $error = 'Private app bootstrap not found.'; return null; }
    try {
        $ctx = require $bootstrap;
        if (!is_array($ctx) || !isset($ctx['db']) || !method_exists($ctx['db'], 'connection')) { $error = 'Private app bootstrap did not return a usable DB context.'; return null; }
        return $ctx;
    } catch (Throwable $e) { $error = $e->getMessage(); return null; }
}
function v3pa_table_exists(mysqli $db, string $table): bool
{
    $stmt = $db->prepare('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1');
    if (!$stmt) { return false; }
    $stmt->bind_param('s', $table); $stmt->execute(); return (bool)$stmt->get_result()->fetch_assoc();
}
/** @return array<int,array<string,mixed>> */
function v3pa_fetch_all(mysqli $db, string $sql): array
{
    $rows = []; $res = $db->query($sql); if ($res) { while ($row = $res->fetch_assoc()) { $rows[] = $row; } } return $rows;
}
function v3pa_fmt_dt(?string $raw): string
{
    $raw = trim((string)$raw); if ($raw === '' || $raw === '0000-00-00 00:00:00') { return ''; }
    try { return (new DateTimeImmutable($raw, new DateTimeZone('Europe/Athens')))->format('d/m/Y H:i'); } catch (Throwable) { $ts = strtotime($raw); return $ts ? date('d/m/Y H:i', $ts) : ''; }
}
function v3pa_price($value): string
{
    $n = (float)str_replace(',', '.', trim((string)$value)); if ($n <= 0) { return ''; }
    return rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');
}
function v3pa_start_label(mysqli $db, string $lessorId, string $startId): array
{
    if (!v3pa_table_exists($db, 'pre_ride_email_v3_starting_point_options')) { return [false, 'options table missing', '']; }
    $stmt = $db->prepare('SELECT label FROM pre_ride_email_v3_starting_point_options WHERE edxeix_lessor_id = ? AND edxeix_starting_point_id = ? AND is_active = 1 LIMIT 1');
    if (!$stmt) { return [false, 'lookup prepare failed', '']; }
    $stmt->bind_param('ss', $lessorId, $startId); $stmt->execute(); $row = $stmt->get_result()->fetch_assoc();
    return is_array($row) ? [true, 'verified', (string)($row['label'] ?? '')] : [false, 'not verified for lessor', ''];
}

$ctxError = null; $ctx = v3pa_app_context($ctxError); $error = null; $dbName = ''; $schema = ['queue'=>false,'events'=>false,'options'=>false]; $rows = []; $eventRows = [];
if (!$ctx) { $error = $ctxError ?: 'DB context unavailable.'; }
else {
    try {
        /** @var mysqli $db */ $db = $ctx['db']->connection(); $db->set_charset('utf8mb4');
        $res = $db->query('SELECT DATABASE() AS db_name'); $tmp = $res ? $res->fetch_assoc() : null; $dbName = is_array($tmp) ? (string)($tmp['db_name'] ?? '') : '';
        $schema['queue'] = v3pa_table_exists($db, 'pre_ride_email_v3_queue');
        $schema['events'] = v3pa_table_exists($db, 'pre_ride_email_v3_queue_events');
        $schema['options'] = v3pa_table_exists($db, 'pre_ride_email_v3_starting_point_options');
        if ($schema['queue']) {
            $rows = v3pa_fetch_all($db, "
                SELECT id, dedupe_key, queue_status, customer_name, customer_phone, driver_name, vehicle_plate,
                       pickup_address, dropoff_address, pickup_datetime, estimated_end_datetime,
                       lessor_id, driver_id, vehicle_id, starting_point_id, price_amount, created_at, locked_at
                FROM pre_ride_email_v3_queue
                WHERE queue_status IN ('live_submit_ready', 'submit_dry_run_ready')
                ORDER BY pickup_datetime ASC, id ASC
                LIMIT 30
            ");
        }
        if ($schema['events']) {
            $eventRows = v3pa_fetch_all($db, "
                SELECT id, queue_id, dedupe_key, event_status, event_message, created_by, created_at
                FROM pre_ride_email_v3_queue_events
                WHERE event_type = 'live_submit_payload_audited'
                ORDER BY id DESC
                LIMIT 20
            ");
        }
    } catch (Throwable $e) { $error = $e->getMessage(); }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>V3 Live Payload Audit | gov.cabnet.app</title>
<style>
:root{--bg:#f3f6fb;--panel:#fff;--ink:#061735;--muted:#41577a;--line:#d7e1ef;--nav:#081225;--blue:#2563eb;--green:#07875a;--orange:#b85c00;--red:#b42318;--purple:#6d28d9;--soft:#f8fbff}*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--ink);font-family:Arial,Helvetica,sans-serif}.nav{background:var(--nav);color:#fff;min-height:56px;display:flex;align-items:center;gap:18px;padding:0 26px;position:sticky;top:0;z-index:5}.nav a{color:#fff;text-decoration:none;font-size:14px;white-space:nowrap}.wrap{width:min(1480px,calc(100% - 48px));margin:22px auto 60px}.card{background:var(--panel);border:1px solid var(--line);border-radius:14px;padding:18px;margin-bottom:18px;box-shadow:0 10px 26px rgba(8,18,37,.04)}.hero{border-left:7px solid var(--purple)}h1{font-size:32px;margin:0 0 10px}h2{font-size:22px;margin:0 0 14px}p{color:var(--muted);line-height:1.45}.badge{display:inline-block;padding:5px 9px;border-radius:999px;font-size:12px;font-weight:700;margin:1px 3px 1px 0;white-space:nowrap}.badge-good{background:#dcfce7;color:#166534}.badge-warn{background:#fff7ed;color:#b45309}.badge-bad{background:#fee2e2;color:#991b1b}.badge-neutral{background:#eaf1ff;color:#1e40af}.badge-purple{background:#ede9fe;color:#5b21b6}.grid{display:grid;grid-template-columns:repeat(4,1fr);gap:10px}.metric{border:1px solid var(--line);border-radius:10px;padding:14px;background:var(--soft)}.metric strong{display:block;font-size:26px}.small{font-size:13px;color:var(--muted)}table{width:100%;border-collapse:collapse;border:1px solid var(--line);border-radius:10px;overflow:hidden;background:#fff}th,td{padding:10px;border-bottom:1px solid var(--line);text-align:left;vertical-align:top;font-size:13px}th{background:#f8fbff;font-size:12px}tr:last-child td{border-bottom:0}.mono{font-family:Consolas,Monaco,monospace}.pre{white-space:pre-wrap;background:#07101f;color:#e6f0ff;padding:12px;border-radius:8px;overflow:auto;font-family:Consolas,Monaco,monospace;font-size:12px}.alert{border-radius:10px;padding:12px;margin:12px 0}.alert-info{background:#eff6ff;border:1px solid #bfdbfe}.alert-warn{background:#fff7ed;border:1px solid #fed7aa}.btn{display:inline-block;border:0;border-radius:8px;padding:9px 13px;background:var(--blue);color:#fff;text-decoration:none;font-weight:700;font-size:13px}.btn-dark{background:#263449}@media(max-width:900px){.grid{grid-template-columns:1fr}.wrap{width:calc(100% - 24px)}}
</style>
</head>
<body>
<nav class="nav"><strong>GC gov.cabnet.app</strong><a href="/ops/pre-ride-email-v3-queue-watch.php">V3 Watch</a><a href="/ops/pre-ride-email-v3-queue.php">V3 Queue</a><a href="/ops/pre-ride-email-v3-live-readiness.php">V3 Live Readiness</a><a href="/ops/pre-ride-email-v3-live-submit.php">V3 Live Submit</a><a href="/ops/pre-ride-email-v3-live-payload-audit.php">V3 Payload Audit</a></nav>
<div class="wrap">
<section class="card hero"><h1>V3 Live-Submit Payload Audit</h1><p>Read-only preview of the exact EDXEIX form-field package a future V3 live-submit adapter would use. This page does not submit to EDXEIX.</p><?= v3pa_badge('V3 ISOLATED','purple') ?><?= v3pa_badge('READ ONLY','good') ?><?= v3pa_badge('NO EDXEIX CALL','good') ?><?= v3pa_badge('NO AADE CALL','good') ?><?= v3pa_badge(V3_PAYLOAD_PAGE_VERSION,'neutral') ?><div style="margin-top:14px"><a class="btn" href="/ops/pre-ride-email-v3-live-payload-audit.php">Refresh</a><a class="btn btn-dark" href="/ops/pre-ride-email-v3-live-readiness.php">Back to Live Readiness</a></div></section>
<?php if ($error): ?><section class="card"><div class="alert alert-warn"><strong>Error:</strong> <?= v3pa_h($error) ?></div></section><?php endif; ?>
<section class="card"><h2>Status</h2><p><strong>Database:</strong> <?= v3pa_h($dbName ?: '-') ?> <?= $schema['queue'] ? v3pa_badge('queue OK','good') : v3pa_badge('queue missing','bad') ?> <?= $schema['events'] ? v3pa_badge('events OK','good') : v3pa_badge('events missing','bad') ?> <?= $schema['options'] ? v3pa_badge('start options OK','good') : v3pa_badge('start options missing','bad') ?></p><div class="grid"><div class="metric"><strong><?= count($rows) ?></strong><span>Rows near payload audit</span></div><div class="metric"><strong><?= count(array_filter($rows, static fn($r) => ($r['queue_status'] ?? '') === 'live_submit_ready')) ?></strong><span>Live-submit ready rows</span></div><div class="metric"><strong><?= count($eventRows) ?></strong><span>Recent payload audit events</span></div><div class="metric"><strong><?= $schema['options'] ? 'yes' : 'no' ?></strong><span>Start guard table</span></div></div><div class="alert alert-info"><strong>Safety:</strong> this page only builds/displays a payload. It does not press save, submit, or call EDXEIX.</div></section>
<section class="card"><h2>Future submit payload previews</h2><table><thead><tr><th>ID</th><th>Status</th><th>Transfer</th><th>EDXEIX field package</th><th>Guard</th></tr></thead><tbody><?php if (!$rows): ?><tr><td colspan="5">No rows are ready for payload audit yet.</td></tr><?php else: foreach ($rows as $row): [$startOk, $startMsg, $startLabel] = isset($db) ? v3pa_start_label($db, (string)($row['lessor_id'] ?? ''), (string)($row['starting_point_id'] ?? '')) : [false,'db unavailable','']; $payload = ['lessor'=>(string)($row['lessor_id'] ?? ''),'lessee[type]'=>'natural','lessee[name]'=>(string)($row['customer_name'] ?? ''),'driver'=>(string)($row['driver_id'] ?? ''),'vehicle'=>(string)($row['vehicle_id'] ?? ''),'starting_point_id'=>(string)($row['starting_point_id'] ?? ''),'boarding_point'=>(string)($row['pickup_address'] ?? ''),'disembark_point'=>(string)($row['dropoff_address'] ?? ''),'drafted_at'=>v3pa_fmt_dt($row['pickup_datetime'] ?? ''),'started_at'=>v3pa_fmt_dt($row['pickup_datetime'] ?? ''),'ended_at'=>v3pa_fmt_dt($row['estimated_end_datetime'] ?? ''),'price'=>v3pa_price($row['price_amount'] ?? '')]; ?><tr><td><a href="/ops/pre-ride-email-v3-queue.php?id=<?= (int)$row['id'] ?>"><?= (int)$row['id'] ?></a></td><td><?= v3pa_badge((string)$row['queue_status'], ($row['queue_status'] ?? '') === 'live_submit_ready' ? 'good' : 'neutral') ?></td><td><?= v3pa_h($row['customer_name'] ?? '') ?><br><span class="small"><?= v3pa_h(($row['driver_name'] ?? '') . ' / ' . ($row['vehicle_plate'] ?? '')) ?></span><br><span class="small"><?= v3pa_h($row['pickup_datetime'] ?? '') ?></span></td><td><div class="pre"><?= v3pa_h(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?: '{}') ?></div></td><td><?= $startOk ? v3pa_badge('start verified','good') : v3pa_badge('start blocked','bad') ?><br><span class="small"><?= v3pa_h($startMsg) ?></span><br><span class="small"><?= v3pa_h($startLabel) ?></span></td></tr><?php endforeach; endif; ?></tbody></table></section>
<section class="card"><h2>Recent payload audit events</h2><table><thead><tr><th>ID</th><th>Queue</th><th>Status</th><th>Message</th><th>By</th><th>Created</th></tr></thead><tbody><?php if (!$eventRows): ?><tr><td colspan="6">No payload audit events yet.</td></tr><?php else: foreach ($eventRows as $row): ?><tr><td><?= (int)($row['id'] ?? 0) ?></td><td><?= (int)($row['queue_id'] ?? 0) ?></td><td><?= v3pa_badge((string)($row['event_status'] ?? ''), ($row['event_status'] ?? '') === 'ready' ? 'good' : 'neutral') ?></td><td><?= v3pa_h($row['event_message'] ?? '') ?></td><td><?= v3pa_h($row['created_by'] ?? '') ?></td><td><?= v3pa_h($row['created_at'] ?? '') ?></td></tr><?php endforeach; endif; ?></tbody></table></section>
</div></body></html>
