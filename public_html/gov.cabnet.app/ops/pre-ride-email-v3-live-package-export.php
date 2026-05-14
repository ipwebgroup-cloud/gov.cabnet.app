<?php
/**
 * V3 Live Package Export | read-only Ops page
 * v3.0.52-v3-live-package-export
 *
 * This page previews package-export eligibility and commands only. It does not
 * write artifacts, call EDXEIX, call AADE, change queue status, or touch V0.
 */

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Robots-Tag: noindex, nofollow', true);

const V3P_VERSION = 'v3.0.52-v3-live-package-export';
const V3P_QUEUE_TABLE = 'pre_ride_email_v3_queue';
const V3P_EVENTS_TABLE = 'pre_ride_email_v3_queue_events';
const V3P_OPTIONS_TABLE = 'pre_ride_email_v3_starting_point_options';

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function badge(string $text, string $type = 'neutral'): string
{
    return '<span class="badge badge-' . h($type) . '">' . h($text) . '</span>';
}

function app_mysqli(): mysqli
{
    $bootstrap = '/home/cabnet/gov.cabnet.app_app/src/bootstrap.php';
    if (!is_file($bootstrap)) {
        throw new RuntimeException('Bootstrap not found.');
    }
    $app = require $bootstrap;
    $db = is_array($app) ? ($app['db'] ?? null) : null;
    if (is_object($db) && method_exists($db, 'connection')) {
        $mysqli = $db->connection();
        if ($mysqli instanceof mysqli) {
            return $mysqli;
        }
    }
    if ($db instanceof mysqli) {
        return $db;
    }
    throw new RuntimeException('Could not resolve database connection.');
}

function table_exists(mysqli $db, string $table): bool
{
    $stmt = $db->prepare('SELECT COUNT(*) AS c FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
    $stmt->bind_param('s', $table);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return (int)($row['c'] ?? 0) > 0;
}

function fetch_one(mysqli $db, string $sql, array $params = [], string $types = ''): ?array
{
    $stmt = $db->prepare($sql);
    if ($params) {
        if ($types === '') {
            foreach ($params as $param) {
                $types .= is_int($param) ? 'i' : 's';
            }
        }
        $bind = [$types];
        foreach ($params as $i => $param) {
            $bind[] = &$params[$i];
        }
        call_user_func_array([$stmt, 'bind_param'], $bind);
    }
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return is_array($row) ? $row : null;
}

function fetch_all(mysqli $db, string $sql, array $params = [], string $types = ''): array
{
    $stmt = $db->prepare($sql);
    if ($params) {
        if ($types === '') {
            foreach ($params as $param) {
                $types .= is_int($param) ? 'i' : 's';
            }
        }
        $bind = [$types];
        foreach ($params as $i => $param) {
            $bind[] = &$params[$i];
        }
        call_user_func_array([$stmt, 'bind_param'], $bind);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

function decode_json_array($json): array
{
    $decoded = json_decode((string)$json, true);
    return is_array($decoded) ? $decoded : [];
}

$error = null;
$latestLive = null;
$latestProof = null;
$recent = [];
$startLabel = '';
$eventsCount = 0;

try {
    $db = app_mysqli();
    if (!table_exists($db, V3P_QUEUE_TABLE)) {
        throw new RuntimeException('V3 queue table not found.');
    }

    $latestLive = fetch_one($db, "SELECT * FROM " . V3P_QUEUE_TABLE . " WHERE queue_status = 'live_submit_ready' ORDER BY COALESCE(pickup_datetime, created_at) ASC, id ASC LIMIT 1");

    if ($latestLive !== null) {
        $latestProof = $latestLive;
    } elseif (table_exists($db, V3P_EVENTS_TABLE)) {
        $event = fetch_one($db, "SELECT queue_id, created_at FROM " . V3P_EVENTS_TABLE . " WHERE LOWER(CONCAT_WS(' ', event_type, event_message, note, details_json)) LIKE '%live%ready%' ORDER BY id DESC LIMIT 1");
        if ($event && isset($event['queue_id'])) {
            $latestProof = fetch_one($db, 'SELECT * FROM ' . V3P_QUEUE_TABLE . ' WHERE id = ? LIMIT 1', [(int)$event['queue_id']], 'i');
            $eventsCount = 1;
        }
    }

    if ($latestProof !== null && table_exists($db, V3P_OPTIONS_TABLE)) {
        $lessor = (string)($latestProof['lessor_id'] ?? '');
        $start = (string)($latestProof['starting_point_id'] ?? '');
        $labelRow = fetch_one($db, 'SELECT label FROM ' . V3P_OPTIONS_TABLE . ' WHERE edxeix_lessor_id = ? AND edxeix_starting_point_id = ? AND is_active = 1 LIMIT 1', [$lessor, $start], 'ss');
        $startLabel = (string)($labelRow['label'] ?? '');
    }

    $recent = fetch_all($db, 'SELECT id, queue_status, customer_name, pickup_datetime, driver_name, vehicle_plate, lessor_id, driver_id, vehicle_id, starting_point_id, last_error, created_at, updated_at FROM ' . V3P_QUEUE_TABLE . ' ORDER BY id DESC LIMIT 8');
} catch (Throwable $e) {
    $error = $e->getMessage();
}

$proofId = $latestProof ? (int)($latestProof['id'] ?? 0) : 0;
$proofStatus = $latestProof ? (string)($latestProof['queue_status'] ?? '') : '';
$isCurrentLive = $latestLive !== null;
$canExportCurrent = $isCurrentLive && $proofId > 0;
$canExportHistorical = !$isCurrentLive && $proofId > 0;
$payload = $latestProof ? decode_json_array((string)($latestProof['payload_json'] ?? '')) : [];
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>V3 Live Package Export | gov.cabnet.app</title>
<style>
:root{--bg:#f2f5fa;--panel:#fff;--ink:#12234b;--muted:#405174;--line:#d8deeb;--nav:#30395f;--blue:#5866b8;--green:#57ad67;--amber:#d99a2b;--red:#c84d4d;--deep:#151e42}*{box-sizing:border-box}body{margin:0;background:var(--bg);font-family:Arial,Helvetica,sans-serif;color:var(--ink)}.top{height:58px;background:#fff;border-bottom:1px solid var(--line);display:flex;align-items:center;gap:22px;padding:0 22px;position:sticky;top:0;z-index:3}.top strong{font-size:18px}.top a{font-size:14px;text-decoration:none;color:#23346a;font-weight:700}.layout{display:grid;grid-template-columns:260px 1fr;min-height:calc(100vh - 58px)}.side{background:var(--nav);color:#fff;padding:22px 16px}.side h2{font-size:18px;margin:0 0 8px}.side p{font-size:13px;color:#dbe3ff;line-height:1.4}.side .label{font-size:12px;color:#b9c3eb;text-transform:uppercase;letter-spacing:.08em;margin:22px 0 10px}.side a{display:block;color:#fff;text-decoration:none;padding:10px 10px;border-radius:9px;margin:4px 0;font-size:14px}.side a.active,.side a:hover{background:rgba(255,255,255,.15)}.main{padding:28px}.hero,.card{background:var(--panel);border:1px solid var(--line);border-radius:14px;padding:20px 22px;margin-bottom:16px;box-shadow:0 8px 22px rgba(25,35,70,.04)}.hero{border-left:7px solid var(--blue)}.hero.good{border-left-color:var(--green)}.hero.warn{border-left-color:var(--amber)}.hero.bad{border-left-color:var(--red)}h1{font-size:34px;margin:0 0 10px}h2{font-size:22px;margin:0 0 12px}p{color:var(--muted);line-height:1.45}.badge{display:inline-block;border-radius:999px;padding:5px 10px;font-size:12px;font-weight:800;margin:2px 4px 2px 0}.badge-good{background:#e1f7e7;color:#05611d}.badge-warn{background:#fff3da;color:#8a5300}.badge-bad{background:#ffe3e3;color:#9c1f1f}.badge-neutral{background:#e8edff;color:#263e8a}.actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:12px}.btn{display:inline-block;background:var(--blue);color:#fff;text-decoration:none;border-radius:9px;padding:10px 14px;font-weight:800}.btn.green{background:var(--green)}.btn.amber{background:var(--amber);color:#392600}.btn.dark{background:var(--deep)}.grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}.metric{border:1px solid var(--line);border-radius:12px;padding:15px;background:#fbfcff}.metric strong{display:block;font-size:32px}.two{display:grid;grid-template-columns:1fr 1fr;gap:14px}.table{width:100%;border-collapse:collapse}.table th,.table td{border-bottom:1px solid var(--line);padding:10px;text-align:left;vertical-align:top;font-size:14px}.table th{background:#f7f9fd;color:#405174;text-transform:uppercase;font-size:12px}.kv{width:100%;border-collapse:collapse}.kv th,.kv td{border-bottom:1px solid var(--line);padding:10px;text-align:left}.kv th{width:220px;background:#f7f9fd;color:#405174}.code{background:#07152f;color:#e9f0ff;border-radius:12px;padding:14px;overflow:auto;font-family:Consolas,monospace;font-size:13px;white-space:pre-wrap}.safe{border-left:6px solid var(--green)}.warnbox{border-left:6px solid var(--amber)}@media(max-width:1000px){.layout{grid-template-columns:1fr}.side{position:relative}.grid,.two{grid-template-columns:1fr}.main{padding:16px}}
</style>
</head>
<body>
<nav class="top">
    <strong>EA / gov.cabnet.app</strong>
    <a href="/ops/index.php">Ops Index</a>
    <a href="/ops/pre-ride-email-v3-dashboard.php">V3 Control Center</a>
    <a href="/ops/pre-ride-email-v3-proof.php">Proof</a>
    <a href="/ops/pre-ride-email-v3-monitor.php">Compact Monitor</a>
</nav>
<div class="layout">
<aside class="side">
    <h2>V3 Automation</h2>
    <p>Closed-gate package export. No live submit.</p>
    <div class="label">Proof</div>
    <a href="/ops/pre-ride-email-v3-proof.php">Proof Dashboard</a>
    <a class="active" href="/ops/pre-ride-email-v3-live-package-export.php">Package Export</a>
    <a href="/ops/pre-ride-email-v3-readiness-focus.php">Readiness Focus</a>
    <a href="/ops/pre-ride-email-v3-pulse-focus.php">Pulse Focus</a>
    <a href="/ops/pre-ride-email-v3-storage-check.php">Storage Check</a>
    <div class="label">Gate</div>
    <a href="/ops/pre-ride-email-v3-live-submit-gate.php">Locked Submit Gate</a>
    <a href="/ops/pre-ride-email-v3-live-payload-audit.php">Payload Audit</a>
</aside>
<main class="main">
    <section class="hero <?= $error ? 'bad' : ($canExportCurrent ? 'good' : ($canExportHistorical ? 'warn' : 'bad')) ?>">
        <h1>V3 Live Package Export</h1>
        <p>Read-only command guide for exporting local JSON/TXT artifacts from a V3 proof row. The web page does not write files, call EDXEIX, call AADE, or change the queue.</p>
        <?php if ($error): ?>
            <?= badge('error', 'bad') ?>
            <p><strong>Error:</strong> <?= h($error) ?></p>
        <?php else: ?>
            <?= badge($canExportCurrent ? 'current live-ready row available' : 'no current live-ready row', $canExportCurrent ? 'good' : 'warn') ?>
            <?= badge($canExportHistorical ? 'historical proof export available' : 'proof row checked', $canExportHistorical ? 'warn' : 'neutral') ?>
            <?= badge('no live edxeix call', 'good') ?>
            <?= badge('v0 untouched', 'good') ?>
        <?php endif; ?>
        <div class="actions">
            <a class="btn green" href="/ops/pre-ride-email-v3-proof.php">Open Proof Dashboard</a>
            <a class="btn" href="/ops/pre-ride-email-v3-queue-focus.php">Open Queue Focus</a>
            <a class="btn dark" href="/ops/pre-ride-email-v3-storage-check.php">Open Storage Check</a>
        </div>
    </section>

    <section class="grid">
        <div class="metric"><strong><?= h($proofId ?: '-') ?></strong><span>Package queue ID</span></div>
        <div class="metric"><strong><?= h($proofStatus ?: '-') ?></strong><span>Current row status</span></div>
        <div class="metric"><strong><?= $canExportCurrent ? 'YES' : 'NO' ?></strong><span>Currently live-submit-ready</span></div>
    </section>

    <?php if ($latestProof): ?>
    <section class="two">
        <div class="card">
            <h2>Proof row</h2>
            <table class="kv">
                <tr><th>Queue ID</th><td><?= h($proofId) ?></td></tr>
                <tr><th>Status</th><td><?= badge($proofStatus, $proofStatus === 'live_submit_ready' ? 'good' : 'warn') ?></td></tr>
                <tr><th>Customer</th><td><?= h($latestProof['customer_name'] ?? '') ?> / <?= h($latestProof['customer_phone'] ?? '') ?></td></tr>
                <tr><th>Pickup</th><td><?= h($latestProof['pickup_datetime'] ?? '') ?></td></tr>
                <tr><th>Driver / vehicle</th><td><?= h($latestProof['driver_name'] ?? '') ?> / <?= h($latestProof['vehicle_plate'] ?? '') ?></td></tr>
                <tr><th>EDXEIX IDs</th><td>lessor=<?= h($latestProof['lessor_id'] ?? '') ?> driver=<?= h($latestProof['driver_id'] ?? '') ?> vehicle=<?= h($latestProof['vehicle_id'] ?? '') ?> start=<?= h($latestProof['starting_point_id'] ?? '') ?></td></tr>
                <tr><th>Starting point</th><td><?= h($startLabel ?: ($payload['startingPointLabel'] ?? '')) ?></td></tr>
            </table>
        </div>
        <div class="card safe">
            <h2>Recommended command</h2>
            <?php if ($canExportCurrent): ?>
                <p>This row is currently live_submit_ready. Export local artifacts only:</p>
                <div class="code">su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_live_package_export.php --queue-id=<?= h($proofId) ?> --write"</div>
            <?php elseif ($canExportHistorical): ?>
                <p>This row is historical proof only because the pickup has passed or expiry guard blocked it. Export only as proof evidence:</p>
                <div class="code">su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_live_package_export.php --queue-id=<?= h($proofId) ?> --allow-historical-proof --write"</div>
            <?php else: ?>
                <p>No exportable row is available.</p>
            <?php endif; ?>
            <p><strong>Safety:</strong> the CLI writes local artifacts only and still does not call EDXEIX or AADE.</p>
        </div>
    </section>
    <?php endif; ?>

    <section class="card warnbox">
        <h2>Artifact output path</h2>
        <div class="code">/home/cabnet/gov.cabnet.app_app/storage/artifacts/v3_live_submit_packages/</div>
        <p>Expected files: payload JSON, EDXEIX fields JSON, safety report JSON, and safety report TXT.</p>
    </section>

    <section class="card">
        <h2>Recent V3 rows</h2>
        <table class="table">
            <thead><tr><th>ID</th><th>Status</th><th>Customer</th><th>Pickup</th><th>Driver / Vehicle</th><th>IDs</th><th>Error</th></tr></thead>
            <tbody>
            <?php foreach ($recent as $row): ?>
                <tr>
                    <td><?= h($row['id'] ?? '') ?></td>
                    <td><?= badge((string)($row['queue_status'] ?? ''), ($row['queue_status'] ?? '') === 'live_submit_ready' ? 'good' : (($row['queue_status'] ?? '') === 'blocked' ? 'bad' : 'neutral')) ?></td>
                    <td><?= h($row['customer_name'] ?? '') ?></td>
                    <td><?= h($row['pickup_datetime'] ?? '') ?></td>
                    <td><?= h($row['driver_name'] ?? '') ?> / <?= h($row['vehicle_plate'] ?? '') ?></td>
                    <td>lessor=<?= h($row['lessor_id'] ?? '') ?> start=<?= h($row['starting_point_id'] ?? '') ?></td>
                    <td><?= h($row['last_error'] ?? '') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>
</main>
</div>
</body>
</html>
