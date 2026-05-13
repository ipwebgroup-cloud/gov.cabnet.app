<?php
/**
 * gov.cabnet.app — V3 Queue Watch Alert
 *
 * Independent read-only watch page for the isolated V3 pre-ride email queue.
 *
 * Safety:
 * - SELECT only.
 * - No queue mutations.
 * - No EDXEIX calls.
 * - No AADE calls.
 * - Does not modify /ops/pre-ride-email-tool.php.
 */

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Robots-Tag: noindex, nofollow', true);

const V3W_PAGE_VERSION = 'v3.0.16-queue-watch-alert';
const V3W_REFRESH_SECONDS_IDLE = 10;
const V3W_REFRESH_SECONDS_READY = 0;

function v3w_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function v3w_badge(string $text, string $type = 'neutral'): string
{
    return '<span class="badge badge-' . v3w_h($type) . '">' . v3w_h($text) . '</span>';
}

function v3w_private_file(string $relative): string
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

function v3w_app_context(?string &$error = null): ?array
{
    static $ctx = null;
    static $loaded = false;
    static $loadError = null;

    if ($loaded) {
        $error = $loadError;
        return is_array($ctx) ? $ctx : null;
    }

    $loaded = true;
    $bootstrap = v3w_private_file('src/bootstrap.php');
    if (!is_file($bootstrap)) {
        $loadError = 'Private app bootstrap not found.';
        $error = $loadError;
        return null;
    }

    try {
        $ctx = require $bootstrap;
        if (!is_array($ctx) || !isset($ctx['db']) || !method_exists($ctx['db'], 'connection')) {
            $loadError = 'Private app bootstrap did not return a usable DB context.';
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

function v3w_table_exists(mysqli $db, string $table): bool
{
    $stmt = $db->prepare('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1');
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('s', $table);
    $stmt->execute();
    return (bool)$stmt->get_result()->fetch_assoc();
}

/** @return array<int,array<string,mixed>> */
function v3w_fetch_all(mysqli $db, string $sql): array
{
    $rows = [];
    $res = $db->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
    }
    return $rows;
}

function v3w_fetch_one(mysqli $db, string $sql): array
{
    $res = $db->query($sql);
    if ($res) {
        $row = $res->fetch_assoc();
        if (is_array($row)) {
            return $row;
        }
    }
    return [];
}

function v3w_log_file(string $relative): string
{
    return v3w_private_file($relative);
}

function v3w_tail_file(string $file, int $maxBytes = 12000): string
{
    if (!is_file($file) || !is_readable($file)) {
        return '';
    }
    $size = filesize($file);
    if ($size === false || $size <= 0) {
        return '';
    }
    $handle = fopen($file, 'rb');
    if (!$handle) {
        return '';
    }
    $seek = max(0, $size - $maxBytes);
    if ($seek > 0) {
        fseek($handle, $seek);
    }
    $data = stream_get_contents($handle);
    fclose($handle);
    return is_string($data) ? trim($data) : '';
}

/** @return array<string,mixed> */
function v3w_log_health(string $relative, string $needle): array
{
    $file = v3w_log_file($relative);
    $exists = is_file($file);
    $readable = $exists && is_readable($file);
    $mtime = $exists ? (filemtime($file) ?: 0) : 0;
    $age = $mtime > 0 ? time() - $mtime : null;
    $tail = $readable ? v3w_tail_file($file) : '';
    $summary = '';
    $finish = '';
    $readyLines = [];
    $blockedLines = [];

    if ($tail !== '') {
        $lines = preg_split('/\R/', $tail) ?: [];
        foreach ($lines as $line) {
            $line = trim((string)$line);
            if ($line === '') { continue; }
            if (str_contains($line, 'SUMMARY')) { $summary = $line; }
            if (str_contains($line, 'finish exit_code=0')) { $finish = $line; }
            if (str_contains($line, $needle)) { $readyLines[] = $line; }
            if (str_contains($line, 'BLOCKED')) { $blockedLines[] = $line; }
        }
    }

    $status = 'missing';
    $type = 'bad';
    if ($exists && !$readable) {
        $status = 'unreadable';
        $type = 'warn';
    } elseif ($readable && $age !== null && $age <= 180) {
        $status = 'fresh';
        $type = 'good';
    } elseif ($readable && $age !== null && $age <= 900) {
        $status = 'stale';
        $type = 'warn';
    } elseif ($readable) {
        $status = 'old';
        $type = 'bad';
    }

    return [
        'file' => $file,
        'safe_file' => 'gov.cabnet.app_app/' . $relative,
        'exists' => $exists,
        'readable' => $readable,
        'mtime' => $mtime,
        'age_seconds' => $age,
        'status' => $status,
        'type' => $type,
        'summary' => $summary,
        'finish' => $finish,
        'ready_lines' => array_slice(array_reverse($readyLines), 0, 3),
        'blocked_lines' => array_slice(array_reverse($blockedLines), 0, 3),
        'tail' => $tail,
    ];
}

$ctxError = null;
$ctx = v3w_app_context($ctxError);
$db = null;
$dbName = '';
$error = null;
$tableQueue = false;
$tableEvents = false;
$schemaOk = false;
$counts = [
    'total_rows' => 0,
    'future_rows' => 0,
    'queued_rows' => 0,
    'submit_dry_run_ready_rows' => 0,
    'future_queued_rows' => 0,
    'future_submit_dry_run_ready_rows' => 0,
];
$latestRows = [];
$activeRows = [];

if (!$ctx) {
    $error = $ctxError ?: 'DB context unavailable.';
} else {
    try {
        $db = $ctx['db']->connection();
        $dbRow = $db->query('SELECT DATABASE() AS db_name');
        if ($dbRow) {
            $tmp = $dbRow->fetch_assoc();
            $dbName = (string)($tmp['db_name'] ?? '');
        }
        $tableQueue = v3w_table_exists($db, 'pre_ride_email_v3_queue');
        $tableEvents = v3w_table_exists($db, 'pre_ride_email_v3_queue_events');
        $schemaOk = $tableQueue && $tableEvents;

        if ($schemaOk) {
            $row = v3w_fetch_one($db, "
                SELECT
                    COUNT(*) AS total_rows,
                    SUM(CASE WHEN pickup_datetime IS NOT NULL AND pickup_datetime >= NOW() THEN 1 ELSE 0 END) AS future_rows,
                    SUM(CASE WHEN queue_status = 'queued' THEN 1 ELSE 0 END) AS queued_rows,
                    SUM(CASE WHEN queue_status = 'submit_dry_run_ready' THEN 1 ELSE 0 END) AS submit_dry_run_ready_rows,
                    SUM(CASE WHEN queue_status = 'queued' AND pickup_datetime IS NOT NULL AND pickup_datetime >= NOW() THEN 1 ELSE 0 END) AS future_queued_rows,
                    SUM(CASE WHEN queue_status = 'submit_dry_run_ready' AND pickup_datetime IS NOT NULL AND pickup_datetime >= NOW() THEN 1 ELSE 0 END) AS future_submit_dry_run_ready_rows
                FROM pre_ride_email_v3_queue
            ");
            foreach ($counts as $key => $_) {
                $counts[$key] = (int)($row[$key] ?? 0);
            }

            $activeRows = v3w_fetch_all($db, "
                SELECT id, dedupe_key, queue_status, pickup_datetime, customer_name, driver_name, vehicle_plate, lessor_id, driver_id, vehicle_id, starting_point_id, created_at
                FROM pre_ride_email_v3_queue
                WHERE pickup_datetime IS NOT NULL
                  AND pickup_datetime >= NOW()
                  AND queue_status IN ('queued','submit_dry_run_ready')
                ORDER BY pickup_datetime ASC, id ASC
                LIMIT 10
            ");

            $latestRows = v3w_fetch_all($db, "
                SELECT id, dedupe_key, queue_status, pickup_datetime, customer_name, driver_name, vehicle_plate, lessor_id, driver_id, vehicle_id, starting_point_id, created_at
                FROM pre_ride_email_v3_queue
                ORDER BY id DESC
                LIMIT 10
            ");
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$intakeHealth = v3w_log_health('logs/pre_ride_email_v3_cron.log', 'INSERT_RESULT');
$submitDryHealth = v3w_log_health('logs/pre_ride_email_v3_submit_dry_run_cron.log', 'DRY_READY');
$readyNow = count($activeRows) > 0;
$refreshSeconds = $readyNow ? V3W_REFRESH_SECONDS_READY : V3W_REFRESH_SECONDS_IDLE;
$titlePrefix = $readyNow ? 'READY - ' : 'Watching - ';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <?php if ($refreshSeconds > 0): ?><meta http-equiv="refresh" content="<?= (int)$refreshSeconds ?>"><?php endif; ?>
    <title><?= v3w_h($titlePrefix) ?>V3 Queue Watch | gov.cabnet.app</title>
    <style>
        :root{--bg:#f3f6fb;--panel:#fff;--ink:#061735;--muted:#41577a;--line:#d7e1ef;--nav:#081225;--blue:#2563eb;--green:#07875a;--orange:#b85c00;--red:#b42318;--purple:#6d28d9;--soft:#f8fbff}*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--ink);font-family:Arial,Helvetica,sans-serif}.nav{background:var(--nav);color:#fff;min-height:56px;display:flex;align-items:center;gap:18px;padding:0 26px;position:sticky;top:0;z-index:5;overflow:auto}.nav strong{white-space:nowrap}.nav a{color:#fff;text-decoration:none;font-size:14px;white-space:nowrap;opacity:.94}.nav a:hover{text-decoration:underline;opacity:1}.wrap{width:min(1480px,calc(100% - 48px));margin:22px auto 60px}.card{background:var(--panel);border:1px solid var(--line);border-radius:14px;padding:18px;margin-bottom:18px;box-shadow:0 10px 26px rgba(8,18,37,.04)}.hero{border-left:7px solid var(--purple)}.readyHero{border-left-color:var(--green);background:#f0fdf4}.watchHero{border-left-color:var(--purple)}h1{font-size:32px;margin:0 0 10px}h2{font-size:22px;margin:0 0 14px}h3{font-size:17px;margin:16px 0 10px}p{color:var(--muted);line-height:1.45}.small{font-size:13px;color:var(--muted)}.badge{display:inline-block;padding:5px 9px;border-radius:999px;font-size:12px;font-weight:700;margin:1px 3px 1px 0;white-space:nowrap}.badge-good{background:#dcfce7;color:#166534}.badge-warn{background:#fff7ed;color:#b45309}.badge-bad{background:#fee2e2;color:#991b1b}.badge-neutral{background:#eaf1ff;color:#1e40af}.badge-purple{background:#ede9fe;color:#5b21b6}.grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px}.two{display:grid;grid-template-columns:1fr 1fr;gap:18px}.metric{border:1px solid var(--line);border-radius:10px;padding:14px;background:var(--soft);min-height:88px}.metric strong{display:block;font-size:28px;line-height:1}.metric span{color:var(--muted);font-size:13px}.btn{display:inline-block;border:0;border-radius:8px;padding:10px 13px;background:var(--blue);color:#fff;font-weight:700;text-decoration:none;cursor:pointer}.btn.dark{background:#334155}.btn.green{background:var(--green)}.btn.light{background:#eaf1ff;color:#1e40af}.actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:12px}table{width:100%;border-collapse:collapse;border:1px solid var(--line);border-radius:10px;overflow:hidden;background:#fff}th,td{padding:10px;border-bottom:1px solid var(--line);vertical-align:top;text-align:left;font-size:13px}th{background:#f8fafc;color:#0f2758}tr:last-child td{border-bottom:0}.code{white-space:pre-wrap;font-family:Consolas,Menlo,Monaco,monospace;font-size:12px;background:#0b1220;color:#dbeafe;border-radius:8px;padding:12px;max-height:260px;overflow:auto}.good{color:#166534}.warn{color:#b45309}.bad{color:#991b1b}.readyPanel{border:2px solid #16a34a;background:#ecfdf3}.idlePanel{border:1px dashed #a78bfa;background:#faf5ff}@media(max-width:980px){.grid,.two{grid-template-columns:1fr}.wrap{width:calc(100% - 24px);margin-top:14px}.nav{padding:0 14px}}
    </style>
</head>
<body>
<nav class="nav">
    <strong>GC gov.cabnet.app</strong>
    <a href="/ops/index.php">Operations Console</a>
    <a href="/ops/pre-ride-email-toolv3.php">V3 Intake</a>
    <a href="/ops/pre-ride-email-v3-queue.php">V3 Queue</a>
    <a href="/ops/pre-ride-email-v3-cron-health.php">V3 Cron Health</a>
    <a href="/ops/pre-ride-email-v3-queue-watch.php">V3 Queue Watch</a>
</nav>

<main class="wrap">
    <section class="card hero <?= $readyNow ? 'readyHero' : 'watchHero' ?>">
        <h1><?= $readyNow ? 'V3 Queue Ready' : 'V3 Queue Watch' ?></h1>
        <p>This read-only page watches the isolated V3 queue and refreshes automatically while no future queued ride exists. When a future queued or dry-run-ready row appears, refresh stops and the page alerts the operator.</p>
        <div>
            <?= v3w_badge('V3 ISOLATED', 'purple') ?>
            <?= v3w_badge('READ ONLY', 'good') ?>
            <?= v3w_badge('NO EDXEIX CALL', 'good') ?>
            <?= v3w_badge('NO AADE CALL', 'good') ?>
            <?= v3w_badge(V3W_PAGE_VERSION, 'neutral') ?>
        </div>
        <div class="actions">
            <a class="btn" href="/ops/pre-ride-email-v3-queue-watch.php">Refresh now</a>
            <a class="btn dark" href="/ops/pre-ride-email-v3-queue.php">Open V3 queue</a>
            <a class="btn light" href="/ops/pre-ride-email-v3-cron-health.php">Cron health</a>
        </div>
    </section>

    <?php if ($error): ?>
        <section class="card"><p class="bad"><strong>Error:</strong> <?= v3w_h($error) ?></p></section>
    <?php endif; ?>

    <section class="card <?= $readyNow ? 'readyPanel' : 'idlePanel' ?>">
        <h2><?= $readyNow ? 'Action needed: future V3 queue row detected' : 'Waiting for future-safe V3 queue row' ?></h2>
        <?php if ($readyNow): ?>
            <p class="good"><strong>Ready:</strong> a future queued / submit-dry-run-ready V3 row exists. Open the V3 queue dashboard and inspect it.</p>
            <div class="actions"><a class="btn green" href="/ops/pre-ride-email-v3-queue.php">Open V3 queue now</a></div>
        <?php else: ?>
            <p class="small">No future queued rows yet. This page refreshes every <?= (int)V3W_REFRESH_SECONDS_IDLE ?> seconds. The cron workers continue scanning automatically.</p>
        <?php endif; ?>
    </section>

    <section class="card">
        <h2>V3 queue snapshot</h2>
        <p><strong>Database:</strong> <?= v3w_h($dbName ?: 'unknown') ?> <?= $schemaOk ? v3w_badge('schema OK', 'good') : v3w_badge('schema missing', 'bad') ?></p>
        <div class="grid">
            <div class="metric"><strong><?= (int)$counts['total_rows'] ?></strong><span>Total V3 queue rows</span></div>
            <div class="metric"><strong><?= (int)$counts['future_rows'] ?></strong><span>Future rows</span></div>
            <div class="metric"><strong><?= (int)$counts['future_queued_rows'] ?></strong><span>Future queued</span></div>
            <div class="metric"><strong><?= (int)$counts['future_submit_dry_run_ready_rows'] ?></strong><span>Future dry-run ready</span></div>
        </div>
    </section>

    <section class="card">
        <h2>Active future rows</h2>
        <table>
            <thead><tr><th>ID</th><th>Status</th><th>Pickup</th><th>Transfer</th><th>IDs</th><th>Created</th></tr></thead>
            <tbody>
            <?php if (!$activeRows): ?>
                <tr><td colspan="6">No active future V3 queue rows yet.</td></tr>
            <?php else: foreach ($activeRows as $row): ?>
                <tr>
                    <td><a href="/ops/pre-ride-email-v3-queue.php?id=<?= (int)$row['id'] ?>"><?= (int)$row['id'] ?></a></td>
                    <td><?= v3w_badge((string)$row['queue_status'], 'good') ?></td>
                    <td><?= v3w_h($row['pickup_datetime'] ?? '') ?></td>
                    <td><?= v3w_h($row['customer_name'] ?? '') ?><br><span class="small"><?= v3w_h($row['driver_name'] ?? '') ?> / <?= v3w_h($row['vehicle_plate'] ?? '') ?></span></td>
                    <td class="small">Lessor: <?= v3w_h($row['lessor_id'] ?? '') ?><br>Driver: <?= v3w_h($row['driver_id'] ?? '') ?><br>Vehicle: <?= v3w_h($row['vehicle_id'] ?? '') ?><br>Start: <?= v3w_h($row['starting_point_id'] ?? '') ?></td>
                    <td><?= v3w_h($row['created_at'] ?? '') ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </section>

    <section class="two">
        <div class="card">
            <h2>V3 intake cron</h2>
            <p>Status: <?= v3w_badge((string)$intakeHealth['status'], (string)$intakeHealth['type']) ?> <?= !empty($intakeHealth['readable']) ? v3w_badge('readable', 'good') : v3w_badge('not readable', 'bad') ?></p>
            <p class="small"><strong>File:</strong> <?= v3w_h($intakeHealth['safe_file']) ?><br><strong>Age:</strong> <?= v3w_h($intakeHealth['age_seconds'] ?? '-') ?> sec</p>
            <h3>Latest summary</h3>
            <div class="code"><?= v3w_h($intakeHealth['summary'] ?: 'No summary yet.') ?></div>
            <h3>Recent blocked examples</h3>
            <div class="code"><?= v3w_h(implode("\n", $intakeHealth['blocked_lines']) ?: 'No recent blocked examples.') ?></div>
        </div>
        <div class="card">
            <h2>V3 submit dry-run cron</h2>
            <p>Status: <?= v3w_badge((string)$submitDryHealth['status'], (string)$submitDryHealth['type']) ?> <?= !empty($submitDryHealth['readable']) ? v3w_badge('readable', 'good') : v3w_badge('not readable', 'bad') ?></p>
            <p class="small"><strong>File:</strong> <?= v3w_h($submitDryHealth['safe_file']) ?><br><strong>Age:</strong> <?= v3w_h($submitDryHealth['age_seconds'] ?? '-') ?> sec</p>
            <h3>Latest summary</h3>
            <div class="code"><?= v3w_h($submitDryHealth['summary'] ?: 'No summary yet.') ?></div>
            <h3>Recent dry-ready lines</h3>
            <div class="code"><?= v3w_h(implode("\n", $submitDryHealth['ready_lines']) ?: 'No recent dry-ready rows.') ?></div>
        </div>
    </section>

    <section class="card">
        <h2>Latest V3 queue rows</h2>
        <table>
            <thead><tr><th>ID</th><th>Status</th><th>Pickup</th><th>Transfer</th><th>IDs</th><th>Created</th></tr></thead>
            <tbody>
            <?php if (!$latestRows): ?>
                <tr><td colspan="6">No V3 queue rows yet.</td></tr>
            <?php else: foreach ($latestRows as $row): ?>
                <tr>
                    <td><a href="/ops/pre-ride-email-v3-queue.php?id=<?= (int)$row['id'] ?>"><?= (int)$row['id'] ?></a></td>
                    <td><?= v3w_h($row['queue_status'] ?? '') ?></td>
                    <td><?= v3w_h($row['pickup_datetime'] ?? '') ?></td>
                    <td><?= v3w_h($row['customer_name'] ?? '') ?><br><span class="small"><?= v3w_h($row['driver_name'] ?? '') ?> / <?= v3w_h($row['vehicle_plate'] ?? '') ?></span></td>
                    <td class="small">Lessor: <?= v3w_h($row['lessor_id'] ?? '') ?><br>Driver: <?= v3w_h($row['driver_id'] ?? '') ?><br>Vehicle: <?= v3w_h($row['vehicle_id'] ?? '') ?><br>Start: <?= v3w_h($row['starting_point_id'] ?? '') ?></td>
                    <td><?= v3w_h($row['created_at'] ?? '') ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </section>
</main>

<script>
(function () {
    'use strict';
    var ready = <?= $readyNow ? 'true' : 'false' ?>;
    var titleBase = document.title;

    function beep() {
        try {
            var AudioContext = window.AudioContext || window.webkitAudioContext;
            if (!AudioContext) { return; }
            var ctx = new AudioContext();
            var oscillator = ctx.createOscillator();
            var gain = ctx.createGain();
            oscillator.type = 'sine';
            oscillator.frequency.value = 880;
            gain.gain.value = 0.08;
            oscillator.connect(gain);
            gain.connect(ctx.destination);
            oscillator.start();
            setTimeout(function () { oscillator.stop(); ctx.close(); }, 550);
        } catch (e) {}
    }

    function notify() {
        if (!('Notification' in window)) { return; }
        if (Notification.permission === 'granted') {
            new Notification('V3 queue row ready', { body: 'A future pre-ride email is ready in the V3 queue.' });
            return;
        }
        if (Notification.permission !== 'denied') {
            Notification.requestPermission().then(function (permission) {
                if (permission === 'granted') {
                    new Notification('V3 queue watch enabled', { body: 'You will be notified when a V3 queue row is ready.' });
                }
            }).catch(function () {});
        }
    }

    if (ready) {
        document.title = 'READY - V3 Queue Watch';
        beep();
        notify();
    } else {
        document.title = 'Watching - ' + titleBase.replace(/^Watching - /, '');
    }
})();
</script>
</body>
</html>
