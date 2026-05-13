<?php
/**
 * gov.cabnet.app — V3 Live-Submit Readiness Dashboard
 *
 * Read-only visibility for V3 rows that are close to live-submit automation.
 * This page does not call EDXEIX and does not write to the database.
 */

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Robots-Tag: noindex, nofollow', true);

const V3_LIVE_PAGE_VERSION = 'v3.0.21-live-submit-readiness-page';

function v3lr_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function v3lr_badge(string $text, string $type = 'neutral'): string
{
    return '<span class="badge badge-' . v3lr_h($type) . '">' . v3lr_h($text) . '</span>';
}

function v3lr_private_file(string $relative): string
{
    $relative = ltrim($relative, '/');
    $candidates = [
        dirname(__DIR__, 3) . '/gov.cabnet.app_app/' . $relative,
        dirname(__DIR__, 2) . '/gov.cabnet.app_app/' . $relative,
    ];
    foreach ($candidates as $file) {
        if (is_file($file)) {
            return $file;
        }
    }
    return $candidates[0];
}

function v3lr_app_context(?string &$error = null): ?array
{
    $bootstrap = v3lr_private_file('src/bootstrap.php');
    if (!is_file($bootstrap)) {
        $error = 'Private app bootstrap not found.';
        return null;
    }
    try {
        $ctx = require $bootstrap;
        if (!is_array($ctx) || !isset($ctx['db']) || !method_exists($ctx['db'], 'connection')) {
            $error = 'Private app bootstrap did not return a usable DB context.';
            return null;
        }
        return $ctx;
    } catch (Throwable $e) {
        $error = $e->getMessage();
        return null;
    }
}

function v3lr_table_exists(mysqli $db, string $table): bool
{
    $stmt = $db->prepare('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1');
    if (!$stmt) { return false; }
    $stmt->bind_param('s', $table);
    $stmt->execute();
    return (bool)$stmt->get_result()->fetch_assoc();
}

/** @return array<int,array<string,mixed>> */
function v3lr_fetch_all(mysqli $db, string $sql): array
{
    $rows = [];
    $res = $db->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) { $rows[] = $row; }
    }
    return $rows;
}

function v3lr_log_file(string $relative): string
{
    return v3lr_private_file(ltrim($relative, '/'));
}

function v3lr_tail_file(string $file, int $maxBytes = 14000): string
{
    if (!is_file($file) || !is_readable($file)) { return ''; }
    $size = filesize($file);
    if ($size === false || $size <= 0) { return ''; }
    $handle = fopen($file, 'rb');
    if (!$handle) { return ''; }
    $seek = max(0, $size - $maxBytes);
    if ($seek > 0) { fseek($handle, $seek); }
    $data = stream_get_contents($handle);
    fclose($handle);
    return is_string($data) ? trim($data) : '';
}

/** @return array<string,mixed> */
function v3lr_cron_health(): array
{
    $file = v3lr_log_file('logs/pre_ride_email_v3_live_submit_readiness_cron.log');
    $exists = is_file($file);
    $readable = $exists && is_readable($file);
    $mtime = $exists ? (filemtime($file) ?: 0) : 0;
    $age = $mtime > 0 ? time() - $mtime : null;
    $tail = $readable ? v3lr_tail_file($file) : '';
    $lastSummary = '';
    $lastFinish = '';
    if ($tail !== '') {
        foreach ((preg_split('/\R/', $tail) ?: []) as $line) {
            $line = trim((string)$line);
            if (str_contains($line, 'SUMMARY')) { $lastSummary = $line; }
            if (str_contains($line, 'finish')) { $lastFinish = $line; }
        }
    }
    $status = 'missing';
    $type = 'bad';
    if ($readable && $age !== null && $age <= 180) { $status = 'fresh'; $type = 'good'; }
    elseif ($readable && $age !== null && $age <= 900) { $status = 'stale'; $type = 'warn'; }
    elseif ($readable) { $status = 'old'; $type = 'bad'; }
    elseif ($exists) { $status = 'unreadable'; $type = 'warn'; }
    return [
        'file' => 'gov.cabnet.app_app/logs/pre_ride_email_v3_live_submit_readiness_cron.log',
        'status' => $status,
        'status_type' => $type,
        'age_seconds' => $age,
        'last_summary' => $lastSummary,
        'last_finish' => $lastFinish,
        'tail' => $tail,
    ];
}

$ctxError = null;
$ctx = v3lr_app_context($ctxError);
$error = null;
$dbName = '';
$schema = ['queue' => false, 'events' => false, 'options' => false];
$statusRows = [];
$readyRows = [];
$eventRows = [];
$optionRows = [];
$cron = v3lr_cron_health();

if (!$ctx) {
    $error = $ctxError ?: 'DB context unavailable.';
} else {
    try {
        /** @var mysqli $db */
        $db = $ctx['db']->connection();
        $db->set_charset('utf8mb4');
        $res = $db->query('SELECT DATABASE() AS db_name');
        $tmp = $res ? $res->fetch_assoc() : null;
        $dbName = is_array($tmp) ? (string)($tmp['db_name'] ?? '') : '';
        $schema['queue'] = v3lr_table_exists($db, 'pre_ride_email_v3_queue');
        $schema['events'] = v3lr_table_exists($db, 'pre_ride_email_v3_queue_events');
        $schema['options'] = v3lr_table_exists($db, 'pre_ride_email_v3_starting_point_options');
        if ($schema['queue']) {
            $statusRows = v3lr_fetch_all($db, "
                SELECT queue_status, COUNT(*) AS total,
                       SUM(CASE WHEN pickup_datetime >= NOW() THEN 1 ELSE 0 END) AS future_total,
                       MIN(pickup_datetime) AS first_pickup,
                       MAX(pickup_datetime) AS last_pickup
                FROM pre_ride_email_v3_queue
                GROUP BY queue_status
                ORDER BY FIELD(queue_status, 'live_submit_ready', 'submit_dry_run_ready', 'queued', 'blocked', 'submitted'), queue_status
            ");
            $readyRows = v3lr_fetch_all($db, "
                SELECT id, dedupe_key, queue_status, customer_name, driver_name, vehicle_plate,
                       pickup_datetime, lessor_id, driver_id, vehicle_id, starting_point_id, price_amount, created_at, locked_at
                FROM pre_ride_email_v3_queue
                WHERE queue_status IN ('live_submit_ready', 'submit_dry_run_ready')
                  AND pickup_datetime >= NOW()
                ORDER BY pickup_datetime ASC, id ASC
                LIMIT 25
            ");
            $eventRows = v3lr_fetch_all($db, "
                SELECT id, queue_id, dedupe_key, event_type, event_status, event_message, created_by, created_at
                FROM pre_ride_email_v3_queue_events
                WHERE event_type LIKE 'live_submit_readiness%'
                ORDER BY id DESC
                LIMIT 20
            ");
        }
        if ($schema['options']) {
            $optionRows = v3lr_fetch_all($db, "
                SELECT lessor_id, starting_point_id, label, is_active, source, updated_at
                FROM pre_ride_email_v3_starting_point_options
                ORDER BY lessor_id ASC, label ASC
                LIMIT 100
            ");
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$totalLiveReady = 0;
$totalDryReady = 0;
$totalFutureLiveReady = 0;
foreach ($statusRows as $row) {
    if (($row['queue_status'] ?? '') === 'live_submit_ready') {
        $totalLiveReady = (int)($row['total'] ?? 0);
        $totalFutureLiveReady = (int)($row['future_total'] ?? 0);
    }
    if (($row['queue_status'] ?? '') === 'submit_dry_run_ready') {
        $totalDryReady = (int)($row['total'] ?? 0);
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>V3 Live-Submit Readiness | gov.cabnet.app</title>
    <style>
        :root{--bg:#f3f6fb;--panel:#fff;--ink:#061735;--muted:#41577a;--line:#d7e1ef;--nav:#081225;--blue:#2563eb;--green:#07875a;--orange:#b85c00;--red:#b42318;--purple:#6d28d9;--soft:#f8fbff}*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--ink);font-family:Arial,Helvetica,sans-serif}.nav{background:var(--nav);color:#fff;min-height:56px;display:flex;align-items:center;gap:18px;padding:0 26px;position:sticky;top:0;z-index:5}.nav a{color:#fff;text-decoration:none;font-size:14px;white-space:nowrap}.wrap{width:min(1480px,calc(100% - 48px));margin:22px auto 60px}.card{background:var(--panel);border:1px solid var(--line);border-radius:14px;padding:18px;margin-bottom:18px;box-shadow:0 10px 26px rgba(8,18,37,.04)}.hero{border-left:7px solid var(--purple)}h1{font-size:32px;margin:0 0 10px}h2{font-size:22px;margin:0 0 14px}p{color:var(--muted);line-height:1.45}.badge{display:inline-block;padding:5px 9px;border-radius:999px;font-size:12px;font-weight:700;margin:1px 3px 1px 0;white-space:nowrap}.badge-good{background:#dcfce7;color:#166534}.badge-warn{background:#fff7ed;color:#b45309}.badge-bad{background:#fee2e2;color:#991b1b}.badge-neutral{background:#eaf1ff;color:#1e40af}.badge-purple{background:#ede9fe;color:#5b21b6}.grid{display:grid;grid-template-columns:repeat(4,1fr);gap:10px}.metric{border:1px solid var(--line);border-radius:10px;padding:14px;background:var(--soft)}.metric strong{display:block;font-size:26px}.small{font-size:13px;color:var(--muted)}table{width:100%;border-collapse:collapse;border:1px solid var(--line);border-radius:10px;overflow:hidden;background:#fff}th,td{padding:10px;border-bottom:1px solid var(--line);text-align:left;vertical-align:top;font-size:13px}th{background:#f8fbff;font-size:12px}tr:last-child td{border-bottom:0}.mono{font-family:Consolas,Monaco,monospace}.pre{white-space:pre-wrap;background:#07101f;color:#e6f0ff;padding:12px;border-radius:8px;overflow:auto;font-family:Consolas,Monaco,monospace;font-size:12px}.alert{border-radius:10px;padding:12px;margin:12px 0}.alert-info{background:#eff6ff;border:1px solid #bfdbfe}.alert-warn{background:#fff7ed;border:1px solid #fed7aa}.btn{display:inline-block;border:0;border-radius:8px;padding:9px 13px;background:var(--blue);color:#fff;text-decoration:none;font-weight:700;font-size:13px}.btn-dark{background:#263449}@media(max-width:900px){.grid{grid-template-columns:1fr}.wrap{width:calc(100% - 24px)}}
    </style>
</head>
<body>
<nav class="nav">
    <strong>GC gov.cabnet.app</strong>
    <a href="/ops/pre-ride-email-v3-queue-watch.php">V3 Watch</a>
    <a href="/ops/pre-ride-email-v3-queue.php">V3 Queue</a>
    <a href="/ops/pre-ride-email-v3-cron-health.php">V3 Cron Health</a>
    <a href="/ops/pre-ride-email-v3-starting-point-guard.php">V3 Starting-Point Guard</a>
    <a href="/ops/pre-ride-email-v3-live-readiness.php">V3 Live Readiness</a>
</nav>
<div class="wrap">
    <section class="card hero">
        <h1>V3 Live-Submit Readiness</h1>
        <p>Read-only visibility for the final V3 pre-live gate. This page shows rows that are close to full automation, but it does not submit to EDXEIX.</p>
        <?= v3lr_badge('V3 ISOLATED', 'purple') ?>
        <?= v3lr_badge('READ ONLY', 'good') ?>
        <?= v3lr_badge('NO EDXEIX CALL', 'good') ?>
        <?= v3lr_badge('NO AADE CALL', 'good') ?>
        <?= v3lr_badge(V3_LIVE_PAGE_VERSION, 'neutral') ?>
        <div style="margin-top:14px">
            <a class="btn" href="/ops/pre-ride-email-v3-live-readiness.php">Refresh</a>
            <a class="btn btn-dark" href="/ops/pre-ride-email-v3-queue.php">Back to V3 queue</a>
        </div>
    </section>

    <?php if ($error): ?>
        <section class="card"><div class="alert alert-warn"><strong>Error:</strong> <?= v3lr_h($error) ?></div></section>
    <?php endif; ?>

    <section class="card">
        <h2>Status</h2>
        <p><strong>Database:</strong> <?= v3lr_h($dbName ?: '-') ?>
            <?= $schema['queue'] ? v3lr_badge('queue OK', 'good') : v3lr_badge('queue missing', 'bad') ?>
            <?= $schema['events'] ? v3lr_badge('events OK', 'good') : v3lr_badge('events missing', 'bad') ?>
            <?= $schema['options'] ? v3lr_badge('start options OK', 'good') : v3lr_badge('start options missing', 'bad') ?>
        </p>
        <div class="grid">
            <div class="metric"><strong><?= (int)$totalDryReady ?></strong><span>Submit dry-run ready</span></div>
            <div class="metric"><strong><?= (int)$totalLiveReady ?></strong><span>Total live-submit ready</span></div>
            <div class="metric"><strong><?= (int)$totalFutureLiveReady ?></strong><span>Future live-submit ready</span></div>
            <div class="metric"><strong><?= count($optionRows) ?></strong><span>Verified start options</span></div>
        </div>
        <div class="alert alert-info">
            <strong>Safety:</strong> live_submit_ready is still not an EDXEIX submit. It is only the last V3 queue status before a future live submit worker can be built and explicitly approved.
        </div>
    </section>

    <section class="card">
        <h2>Live-readiness cron</h2>
        <p>Status: <?= v3lr_badge((string)$cron['status'], (string)$cron['status_type']) ?> <?= $cron['age_seconds'] !== null ? 'Age: ' . (int)$cron['age_seconds'] . ' sec' : '' ?></p>
        <p class="small">File: <?= v3lr_h($cron['file']) ?></p>
        <h3>Latest summary</h3>
        <div class="pre"><?= v3lr_h($cron['last_summary'] ?: 'No summary yet.') ?></div>
        <details style="margin-top:12px"><summary>Show log tail</summary><div class="pre"><?= v3lr_h($cron['tail'] ?: 'No log tail yet.') ?></div></details>
    </section>

    <section class="card">
        <h2>V3 status counts</h2>
        <table>
            <thead><tr><th>Status</th><th>Total</th><th>Future</th><th>First pickup</th><th>Last pickup</th></tr></thead>
            <tbody>
            <?php if (!$statusRows): ?>
                <tr><td colspan="5">No V3 queue rows yet.</td></tr>
            <?php else: foreach ($statusRows as $row): ?>
                <tr>
                    <td><?= v3lr_badge((string)$row['queue_status'], ($row['queue_status'] ?? '') === 'live_submit_ready' ? 'good' : 'neutral') ?></td>
                    <td><?= (int)($row['total'] ?? 0) ?></td>
                    <td><?= (int)($row['future_total'] ?? 0) ?></td>
                    <td><?= v3lr_h($row['first_pickup'] ?? '') ?></td>
                    <td><?= v3lr_h($row['last_pickup'] ?? '') ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </section>

    <section class="card">
        <h2>Future rows near live automation</h2>
        <table>
            <thead><tr><th>ID</th><th>Status</th><th>Pickup</th><th>Transfer</th><th>IDs</th><th>Price</th><th>Locked</th></tr></thead>
            <tbody>
            <?php if (!$readyRows): ?>
                <tr><td colspan="7">No future live-readiness rows yet.</td></tr>
            <?php else: foreach ($readyRows as $row): ?>
                <tr>
                    <td><a href="/ops/pre-ride-email-v3-queue.php?id=<?= (int)$row['id'] ?>"><?= (int)$row['id'] ?></a></td>
                    <td><?= v3lr_badge((string)$row['queue_status'], ($row['queue_status'] ?? '') === 'live_submit_ready' ? 'good' : 'neutral') ?></td>
                    <td><?= v3lr_h($row['pickup_datetime'] ?? '') ?></td>
                    <td><?= v3lr_h($row['customer_name'] ?? '') ?><br><span class="small"><?= v3lr_h(($row['driver_name'] ?? '') . ' / ' . ($row['vehicle_plate'] ?? '')) ?></span></td>
                    <td class="mono">Lessor: <?= v3lr_h($row['lessor_id'] ?? '') ?><br>Driver: <?= v3lr_h($row['driver_id'] ?? '') ?><br>Vehicle: <?= v3lr_h($row['vehicle_id'] ?? '') ?><br>Start: <?= v3lr_h($row['starting_point_id'] ?? '') ?></td>
                    <td><?= v3lr_h($row['price_amount'] ?? '') ?></td>
                    <td><?= v3lr_h($row['locked_at'] ?? '') ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </section>

    <section class="card">
        <h2>Verified starting-point options</h2>
        <table>
            <thead><tr><th>Lessor</th><th>Starting point ID</th><th>Label</th><th>Active</th><th>Source</th><th>Updated</th></tr></thead>
            <tbody>
            <?php if (!$optionRows): ?>
                <tr><td colspan="6">No verified options found.</td></tr>
            <?php else: foreach ($optionRows as $row): ?>
                <tr>
                    <td><?= v3lr_h($row['lessor_id'] ?? '') ?></td>
                    <td class="mono"><?= v3lr_h($row['starting_point_id'] ?? '') ?></td>
                    <td><?= v3lr_h($row['label'] ?? '') ?></td>
                    <td><?= !empty($row['is_active']) ? v3lr_badge('active', 'good') : v3lr_badge('inactive', 'bad') ?></td>
                    <td><?= v3lr_h($row['source'] ?? '') ?></td>
                    <td><?= v3lr_h($row['updated_at'] ?? '') ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </section>

    <section class="card">
        <h2>Recent live-readiness events</h2>
        <table>
            <thead><tr><th>ID</th><th>Queue</th><th>Type</th><th>Status</th><th>Message</th><th>By</th><th>Created</th></tr></thead>
            <tbody>
            <?php if (!$eventRows): ?>
                <tr><td colspan="7">No live-readiness events yet.</td></tr>
            <?php else: foreach ($eventRows as $row): ?>
                <tr>
                    <td><?= (int)($row['id'] ?? 0) ?></td>
                    <td><?= (int)($row['queue_id'] ?? 0) ?></td>
                    <td><?= v3lr_h($row['event_type'] ?? '') ?></td>
                    <td><?= v3lr_badge((string)($row['event_status'] ?? ''), ($row['event_status'] ?? '') === 'live_submit_ready' ? 'good' : 'neutral') ?></td>
                    <td><?= v3lr_h($row['event_message'] ?? '') ?></td>
                    <td><?= v3lr_h($row['created_by'] ?? '') ?></td>
                    <td><?= v3lr_h($row['created_at'] ?? '') ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </section>
</div>
</body>
</html>
