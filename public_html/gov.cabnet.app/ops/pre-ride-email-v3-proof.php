<?php
/**
 * gov.cabnet.app — V3 Proof Dashboard
 *
 * Read-only proof/status page for the V3 forwarded-email readiness milestone.
 * Safety: no Bolt calls, no EDXEIX calls, no AADE calls, no DB writes.
 */

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Robots-Tag: noindex, nofollow', true);

function v3p_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function v3p_badge(string $text, string $type = 'neutral'): string
{
    return '<span class="badge badge-' . v3p_h($type) . '">' . v3p_h($text) . '</span>';
}

function v3p_status_type(string $status): string
{
    $status = strtolower(trim($status));
    if (in_array($status, ['live_submit_ready', 'submit_dry_run_ready'], true)) {
        return 'good';
    }
    if (in_array($status, ['queued', 'pending', 'processing'], true)) {
        return 'warn';
    }
    if (in_array($status, ['blocked', 'failed', 'error'], true)) {
        return 'bad';
    }
    return 'neutral';
}

function v3p_table_exists(mysqli $db, string $table): bool
{
    $stmt = $db->prepare('SELECT COUNT(*) AS c FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
    $stmt->bind_param('s', $table);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return ((int)($row['c'] ?? 0)) > 0;
}

function v3p_column_exists(mysqli $db, string $table, string $column): bool
{
    $stmt = $db->prepare('SELECT COUNT(*) AS c FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return ((int)($row['c'] ?? 0)) > 0;
}

function v3p_fetch_all(mysqli $db, string $sql): array
{
    $result = $db->query($sql);
    if (!$result) {
        return [];
    }
    return $result->fetch_all(MYSQLI_ASSOC);
}

function v3p_fetch_one(mysqli $db, string $sql): ?array
{
    $rows = v3p_fetch_all($db, $sql);
    return $rows[0] ?? null;
}

function v3p_fetch_historical_live_ready_proof(mysqli $db): ?array
{
    if (!v3p_table_exists($db, 'pre_ride_email_v3_queue_events')) {
        return null;
    }
    if (!v3p_column_exists($db, 'pre_ride_email_v3_queue_events', 'queue_id')) {
        return null;
    }

    $hasId = v3p_column_exists($db, 'pre_ride_email_v3_queue_events', 'id');
    $hasType = v3p_column_exists($db, 'pre_ride_email_v3_queue_events', 'event_type');
    $hasMessage = v3p_column_exists($db, 'pre_ride_email_v3_queue_events', 'message');
    $hasCreated = v3p_column_exists($db, 'pre_ride_email_v3_queue_events', 'created_at');

    $where = [];
    if ($hasType) {
        $where[] = "LOWER(COALESCE(e.event_type,'')) LIKE '%live%'";
        $where[] = "LOWER(COALESCE(e.event_type,'')) LIKE '%readiness%'";
    }
    if ($hasMessage) {
        $where[] = "LOWER(COALESCE(e.message,'')) LIKE '%live-ready%'";
        $where[] = "LOWER(COALESCE(e.message,'')) LIKE '%live_submit_ready%'";
        $where[] = "LOWER(COALESCE(e.message,'')) LIKE '%live readiness%'";
        $where[] = "LOWER(COALESCE(e.message,'')) LIKE '%marked live%'";
    }
    if (!$where) {
        return null;
    }

    $eventIdExpr = $hasId ? 'e.id' : '0';
    $eventCreatedExpr = $hasCreated ? 'e.created_at' : 'NULL';
    $eventTypeExpr = $hasType ? 'e.event_type' : "''";
    $eventMessageExpr = $hasMessage ? 'e.message' : "''";
    $orderExpr = $hasId ? 'e.id DESC' : ($hasCreated ? 'e.created_at DESC' : 'q.updated_at DESC');

    return v3p_fetch_one($db, "
        SELECT q.id, q.dedupe_key, q.queue_status, q.customer_name, q.customer_phone, q.pickup_datetime,
               q.estimated_end_datetime, q.driver_name, q.vehicle_plate, q.lessor_id, q.driver_id,
               q.vehicle_id, q.starting_point_id, q.pickup_address, q.dropoff_address, q.price_text,
               q.price_amount, q.last_error, q.created_at, q.updated_at,
               " . $eventIdExpr . " AS proof_event_id,
               " . $eventCreatedExpr . " AS proof_event_at,
               " . $eventTypeExpr . " AS proof_event_type,
               " . $eventMessageExpr . " AS proof_event_message
        FROM pre_ride_email_v3_queue q
        INNER JOIN pre_ride_email_v3_queue_events e ON e.queue_id = q.id
        WHERE (" . implode(' OR ', $where) . ")
        ORDER BY " . $orderExpr . "
        LIMIT 1
    ");
}

function v3p_load_gate_config(string $path): array
{
    $state = [
        'exists' => file_exists($path),
        'readable' => is_readable($path),
        'loaded' => false,
        'enabled' => false,
        'mode' => 'missing',
        'adapter' => 'missing',
        'hard_enable_live_submit' => false,
        'ok_for_future_live_submit' => false,
        'blocks' => [],
    ];

    if (!$state['exists']) {
        $state['blocks'][] = 'server live-submit config is missing';
        return $state;
    }
    if (!$state['readable']) {
        $state['blocks'][] = 'server live-submit config is not readable by PHP';
        return $state;
    }

    try {
        $config = require $path;
        if (!is_array($config)) {
            $state['blocks'][] = 'server live-submit config did not return an array';
            return $state;
        }
        $state['loaded'] = true;
        $state['enabled'] = !empty($config['enabled']);
        $state['mode'] = (string)($config['mode'] ?? 'missing');
        $state['adapter'] = (string)($config['adapter'] ?? 'missing');
        $state['hard_enable_live_submit'] = !empty($config['hard_enable_live_submit']);

        if (!$state['enabled']) { $state['blocks'][] = 'enabled is false'; }
        if ($state['mode'] !== 'live') { $state['blocks'][] = 'mode is not live'; }
        if ($state['adapter'] === 'disabled' || $state['adapter'] === 'missing' || $state['adapter'] === '') { $state['blocks'][] = 'adapter is disabled'; }
        if (!$state['hard_enable_live_submit']) { $state['blocks'][] = 'hard_enable_live_submit is false'; }

        $state['ok_for_future_live_submit'] = empty($state['blocks']);
    } catch (Throwable $e) {
        $state['blocks'][] = 'config load error: ' . $e->getMessage();
    }

    return $state;
}

$errors = [];
$queueMetrics = [
    'total' => 0,
    'active' => 0,
    'live_submit_ready' => 0,
    'submit_dry_run_ready' => 0,
    'blocked' => 0,
];
$latestRows = [];
$proofRow = null;
$currentLiveProofRow = null;
$historicalLiveProof = false;
$proofSource = 'none';
$startOption = null;
$eventRows = [];
$dbOk = false;

$gatePath = dirname(__DIR__, 3) . '/gov.cabnet.app_config/pre_ride_email_v3_live_submit.php';
$gate = v3p_load_gate_config($gatePath);

try {
    $bootstrap = dirname(__DIR__, 3) . '/gov.cabnet.app_app/src/bootstrap.php';
    if (!file_exists($bootstrap)) {
        throw new RuntimeException('Missing bootstrap file: ' . $bootstrap);
    }
    $app = require $bootstrap;
    $dbObj = $app['db'] ?? null;
    if (!is_object($dbObj) || !method_exists($dbObj, 'connection')) {
        throw new RuntimeException('Bootstrap did not provide a database connection object.');
    }
    /** @var mysqli $db */
    $db = $dbObj->connection();
    $dbOk = true;

    if (!v3p_table_exists($db, 'pre_ride_email_v3_queue')) {
        throw new RuntimeException('Missing table: pre_ride_email_v3_queue');
    }

    $metricRow = v3p_fetch_one($db, "
        SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN queue_status NOT IN ('blocked','submitted','failed') THEN 1 ELSE 0 END) AS active,
            SUM(CASE WHEN queue_status = 'live_submit_ready' THEN 1 ELSE 0 END) AS live_submit_ready,
            SUM(CASE WHEN queue_status = 'submit_dry_run_ready' THEN 1 ELSE 0 END) AS submit_dry_run_ready,
            SUM(CASE WHEN queue_status = 'blocked' THEN 1 ELSE 0 END) AS blocked
        FROM pre_ride_email_v3_queue
    ");
    if ($metricRow) {
        foreach ($queueMetrics as $key => $_) {
            $queueMetrics[$key] = (int)($metricRow[$key] ?? 0);
        }
    }

    $currentLiveProofRow = v3p_fetch_one($db, "
        SELECT id, dedupe_key, queue_status, customer_name, customer_phone, pickup_datetime,
               estimated_end_datetime, driver_name, vehicle_plate, lessor_id, driver_id,
               vehicle_id, starting_point_id, pickup_address, dropoff_address, price_text,
               price_amount, last_error, created_at, updated_at,
               NULL AS proof_event_id, NULL AS proof_event_at, '' AS proof_event_type, '' AS proof_event_message
        FROM pre_ride_email_v3_queue
        WHERE queue_status = 'live_submit_ready'
        ORDER BY updated_at DESC, id DESC
        LIMIT 1
    ");

    if ($currentLiveProofRow) {
        $proofRow = $currentLiveProofRow;
        $proofSource = 'current_live_submit_ready';
    } else {
        $proofRow = v3p_fetch_historical_live_ready_proof($db);
        if ($proofRow) {
            $historicalLiveProof = true;
            $proofSource = 'historical_live_readiness_event';
        }
    }

    if (!$proofRow) {
        $proofRow = v3p_fetch_one($db, "
            SELECT id, dedupe_key, queue_status, customer_name, customer_phone, pickup_datetime,
                   estimated_end_datetime, driver_name, vehicle_plate, lessor_id, driver_id,
                   vehicle_id, starting_point_id, pickup_address, dropoff_address, price_text,
                   price_amount, last_error, created_at, updated_at,
                   NULL AS proof_event_id, NULL AS proof_event_at, '' AS proof_event_type, '' AS proof_event_message
            FROM pre_ride_email_v3_queue
            ORDER BY id DESC
            LIMIT 1
        ");
        if ($proofRow) {
            $proofSource = 'latest_queue_row_no_live_proof';
        }
    }

    $latestRows = v3p_fetch_all($db, "
        SELECT id, queue_status, customer_name, pickup_datetime, driver_name, vehicle_plate,
               lessor_id, driver_id, vehicle_id, starting_point_id, last_error, created_at, updated_at
        FROM pre_ride_email_v3_queue
        ORDER BY id DESC
        LIMIT 12
    ");

    if ($proofRow && v3p_table_exists($db, 'pre_ride_email_v3_starting_point_options')) {
        $lessorId = (string)($proofRow['lessor_id'] ?? '');
        $startId = (string)($proofRow['starting_point_id'] ?? '');
        $stmt = $db->prepare('SELECT edxeix_lessor_id, edxeix_starting_point_id, label, is_active, source, updated_at FROM pre_ride_email_v3_starting_point_options WHERE edxeix_lessor_id = ? AND edxeix_starting_point_id = ? AND is_active = 1 LIMIT 1');
        $stmt->bind_param('ss', $lessorId, $startId);
        $stmt->execute();
        $startOption = $stmt->get_result()->fetch_assoc() ?: null;
    }

    if (v3p_table_exists($db, 'pre_ride_email_v3_queue_events')) {
        $eventColumns = [
            'id' => v3p_column_exists($db, 'pre_ride_email_v3_queue_events', 'id'),
            'queue_id' => v3p_column_exists($db, 'pre_ride_email_v3_queue_events', 'queue_id'),
            'event_type' => v3p_column_exists($db, 'pre_ride_email_v3_queue_events', 'event_type'),
            'message' => v3p_column_exists($db, 'pre_ride_email_v3_queue_events', 'message'),
            'created_at' => v3p_column_exists($db, 'pre_ride_email_v3_queue_events', 'created_at'),
        ];
        if ($proofRow && $eventColumns['queue_id']) {
            $select = [];
            $select[] = $eventColumns['id'] ? 'id' : '0 AS id';
            $select[] = $eventColumns['event_type'] ? 'event_type' : "'' AS event_type";
            $select[] = $eventColumns['message'] ? 'message' : "'' AS message";
            $select[] = $eventColumns['created_at'] ? 'created_at' : "NULL AS created_at";
            $queueId = (int)($proofRow['id'] ?? 0);
            $eventRows = v3p_fetch_all($db, 'SELECT ' . implode(', ', $select) . ' FROM pre_ride_email_v3_queue_events WHERE queue_id = ' . $queueId . ' ORDER BY id DESC LIMIT 8');
        }
    }
} catch (Throwable $e) {
    $errors[] = $e->getMessage();
}

$proofIsLiveReady = $proofRow && (string)($proofRow['queue_status'] ?? '') === 'live_submit_ready';
$proofIsDryRunReady = $proofRow && (string)($proofRow['queue_status'] ?? '') === 'submit_dry_run_ready';
$proofHasLiveReadyEvidence = $proofIsLiveReady || $historicalLiveProof;
$proofReady = $proofIsLiveReady || $proofIsDryRunReady || $historicalLiveProof;
$proofExpiredAfterSuccess = $historicalLiveProof && $proofRow && (string)($proofRow['queue_status'] ?? '') === 'blocked';
$gateClosed = !$gate['ok_for_future_live_submit'];
$overallOk = $dbOk && $proofHasLiveReadyEvidence && $startOption && $gateClosed;
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>V3 Proof Dashboard | gov.cabnet.app</title>
    <style>
        :root{--bg:#f4f6fb;--panel:#fff;--ink:#1f2d4d;--muted:#5a6785;--line:#d9deea;--nav:#2f3659;--nav2:#25304f;--blue:#5563b7;--green:#5fae63;--amber:#d39a31;--red:#c94b4b;--soft:#f8faff}
        *{box-sizing:border-box} body{margin:0;background:var(--bg);color:var(--ink);font-family:Arial,Helvetica,sans-serif}.topbar{height:60px;background:#fff;border-bottom:1px solid var(--line);display:flex;align-items:center;gap:18px;padding:0 24px;position:sticky;top:0;z-index:5}.brand{font-weight:800;color:var(--nav)}.topbar a{color:var(--blue);text-decoration:none;font-weight:700;font-size:14px}.layout{display:flex;min-height:calc(100vh - 60px)}.side{width:282px;background:linear-gradient(180deg,var(--nav),var(--nav2));color:#fff;padding:22px 18px;flex:0 0 282px}.side h2{font-size:18px;margin:0 0 12px}.side .navgroup{margin:20px 0 8px;color:#cfd7ef;font-size:12px;text-transform:uppercase;letter-spacing:.08em}.side a{display:block;color:#eef2ff;text-decoration:none;padding:9px 10px;border-radius:10px;margin:3px 0;font-size:14px}.side a:hover,.side a.active{background:rgba(255,255,255,.13)}.main{padding:26px;width:100%;max-width:1520px}.hero,.card{background:var(--panel);border:1px solid var(--line);border-radius:16px;box-shadow:0 8px 24px rgba(31,45,77,.06);padding:20px;margin-bottom:18px}.hero{border-left:8px solid var(--amber)}.hero.ok{border-left-color:var(--green)}.hero.bad{border-left-color:var(--red)}h1{font-size:36px;margin:0 0 10px}h2{font-size:22px;margin:0 0 12px}h3{font-size:17px;margin:0 0 8px}p{color:var(--muted);line-height:1.45}.grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}.two{display:grid;grid-template-columns:1fr 1fr;gap:18px}.metric{background:var(--soft);border:1px solid var(--line);border-radius:14px;padding:16px;min-height:96px}.metric strong{font-size:34px;display:block}.metric span{font-size:13px;color:var(--muted)}.badge{display:inline-block;padding:5px 10px;border-radius:999px;font-size:12px;font-weight:800;margin:2px 4px 2px 0;white-space:nowrap}.badge-good{background:#e2f7e5;color:#236f31}.badge-warn{background:#fff5dd;color:#8a5a10}.badge-bad{background:#fde7e7;color:#932f2f}.badge-neutral{background:#eef2ff;color:#31418f}.actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:14px}.btn{display:inline-block;border-radius:10px;padding:10px 13px;background:var(--blue);color:#fff;text-decoration:none;font-weight:800;font-size:14px}.btn.green{background:var(--green)}.btn.warn{background:var(--amber)}.btn.dark{background:var(--nav)}.tablewrap{overflow:auto;border:1px solid var(--line);border-radius:14px}table{width:100%;border-collapse:collapse;min-width:860px}th,td{padding:10px 12px;border-bottom:1px solid var(--line);text-align:left;vertical-align:top;font-size:14px}th{background:#f8fafc;color:var(--muted);font-size:12px;text-transform:uppercase;letter-spacing:.04em}.kv{display:grid;grid-template-columns:220px 1fr;border-top:1px solid var(--line)}.kv div{padding:10px;border-bottom:1px solid var(--line)}.kv div:nth-child(odd){background:#f8fafc;color:var(--muted);font-weight:700}.small{font-size:13px;color:var(--muted)}.good{color:#236f31}.warn{color:#8a5a10}.bad{color:#932f2f}code{background:#eef2ff;border-radius:6px;padding:2px 5px}.list{margin:0;padding-left:18px;color:var(--muted)}.list li{margin:7px 0}.pre{white-space:pre-wrap;word-break:break-word;background:#0f172a;color:#e2e8f0;border-radius:12px;padding:14px;font-size:13px;line-height:1.45}@media(max-width:1100px){.layout{display:block}.side{width:auto}.grid,.two{grid-template-columns:1fr 1fr}}@media(max-width:760px){.grid,.two{grid-template-columns:1fr}.main{padding:14px}.topbar{overflow:auto}.kv{grid-template-columns:1fr}.kv div:nth-child(odd){border-bottom:0}.side{padding:16px}}
    </style>
</head>
<body>
<header class="topbar">
    <div class="brand">EA / gov.cabnet.app</div>
    <a href="/ops/index.php">Ops Index</a>
    <a href="/ops/pre-ride-email-v3-dashboard.php">V3 Control Center</a>
    <a href="/ops/pre-ride-email-v3-monitor.php">Compact Monitor</a>
    <a href="/ops/pre-ride-email-v3-queue-focus.php">Queue Focus</a>
</header>
<div class="layout">
    <aside class="side">
        <h2>V3 Automation</h2>
        <p class="small" style="color:#dbe4ff">Read-only proof dashboard. Live submit remains disabled.</p>
        <div class="navgroup">Proof</div>
        <a class="active" href="/ops/pre-ride-email-v3-proof.php">Proof Dashboard</a>
        <a href="/ops/pre-ride-email-v3-readiness-focus.php">Readiness Focus</a>
        <a href="/ops/pre-ride-email-v3-pulse-focus.php">Pulse Focus</a>
        <a href="/ops/pre-ride-email-v3-storage-check.php">Storage Check</a>
        <div class="navgroup">Gate</div>
        <a href="/ops/pre-ride-email-v3-live-submit-gate.php">Locked Submit Gate</a>
        <a href="/ops/pre-ride-email-v3-live-payload-audit.php">Payload Audit</a>
    </aside>
    <main class="main">
        <section class="hero <?= $overallOk ? 'ok' : ($proofReady ? '' : 'bad') ?>">
            <h1>V3 Forwarded-Email Readiness Proof</h1>
            <p>This page summarizes the proven V3 path, preserves historical proof after expiry, and confirms that the final live-submit path remains blocked by the master gate.</p>
            <div>
                <?php if ($proofIsLiveReady): ?>
                    <?= v3p_badge('CURRENT LIVE-SUBMIT-READY ROW FOUND', 'good') ?>
                <?php elseif ($historicalLiveProof): ?>
                    <?= v3p_badge('HISTORICAL LIVE-READY PROOF FOUND', 'good') ?>
                    <?= v3p_badge('NO CURRENT LIVE-READY ROW', 'warn') ?>
                <?php else: ?>
                    <?= v3p_badge('NO LIVE-READY PROOF FOUND', $proofIsDryRunReady ? 'warn' : 'bad') ?>
                <?php endif; ?>
                <?= $startOption ? v3p_badge('STARTING POINT VERIFIED', 'good') : v3p_badge('STARTING POINT CHECK NEEDED', 'warn') ?>
                <?= $gateClosed ? v3p_badge('MASTER GATE CLOSED', 'good') : v3p_badge('MASTER GATE OPEN', 'bad') ?>
                <?= v3p_badge('NO LIVE EDXEIX CALL', 'good') ?>
                <?= v3p_badge('V0 UNTOUCHED', 'good') ?>
            </div>
            <?php if ($errors): ?>
                <p class="bad"><strong>Load warnings:</strong> <?= v3p_h(implode(' | ', $errors)) ?></p>
            <?php endif; ?>
            <div class="actions">
                <a class="btn green" href="/ops/pre-ride-email-v3-monitor.php">Open Compact Monitor</a>
                <a class="btn" href="/ops/pre-ride-email-v3-queue-focus.php">Open Queue Focus</a>
                <a class="btn warn" href="/ops/pre-ride-email-v3-live-submit-gate.php">Open Locked Gate</a>
                <a class="btn dark" href="/ops/pre-ride-email-v3-storage-check.php">Open Storage Check</a>
            </div>
        </section>

        <?php if ($proofExpiredAfterSuccess): ?>
        <section class="card" style="border-left:8px solid var(--green)">
            <h2>Historical Proof Preserved</h2>
            <p class="good"><strong>This is expected:</strong> the proof row reached live-submit readiness earlier, then the expiry guard safely blocked it after the pickup time passed.</p>
            <p>The current queue may show zero live-submit-ready rows after time passes. This page now preserves the proof using the recorded V3 queue event history.</p>
        </section>
        <?php endif; ?>

        <section class="grid">
            <div class="metric"><strong><?= (int)$queueMetrics['total'] ?></strong><span>Total V3 queue rows</span></div>
            <div class="metric"><strong><?= (int)$queueMetrics['active'] ?></strong><span>Active/non-terminal rows</span></div>
            <div class="metric"><strong><?= (int)$queueMetrics['live_submit_ready'] ?></strong><span>Live-submit-ready rows</span></div>
            <div class="metric"><strong><?= (int)$queueMetrics['blocked'] ?></strong><span>Blocked rows</span></div>
        </section>

        <section class="two" style="margin-top:18px">
            <div class="card">
                <h2>Proof Row</h2>
                <?php if ($proofRow): ?>
                    <div class="kv">
                        <div>Queue ID</div><div><?= v3p_h($proofRow['id'] ?? '') ?></div>
                        <div>Status</div><div><?= v3p_badge((string)($proofRow['queue_status'] ?? ''), v3p_status_type((string)($proofRow['queue_status'] ?? ''))) ?></div>
                        <div>Proof source</div><div><?= v3p_badge($proofSource, $historicalLiveProof || $proofIsLiveReady ? 'good' : 'warn') ?></div>
                        <div>Live-ready proof</div><div><?= v3p_badge($proofHasLiveReadyEvidence ? 'yes' : 'no', $proofHasLiveReadyEvidence ? 'good' : 'bad') ?><?php if (!empty($proofRow['proof_event_at'])): ?> event at <?= v3p_h($proofRow['proof_event_at']) ?><?php endif; ?></div>
                        <div>Customer</div><div><?= v3p_h($proofRow['customer_name'] ?? '') ?> / <?= v3p_h($proofRow['customer_phone'] ?? '') ?></div>
                        <div>Pickup</div><div><?= v3p_h($proofRow['pickup_datetime'] ?? '') ?></div>
                        <div>Driver / Vehicle</div><div><?= v3p_h($proofRow['driver_name'] ?? '') ?> / <?= v3p_h($proofRow['vehicle_plate'] ?? '') ?></div>
                        <div>EDXEIX IDs</div><div>lessor=<?= v3p_h($proofRow['lessor_id'] ?? '') ?> driver=<?= v3p_h($proofRow['driver_id'] ?? '') ?> vehicle=<?= v3p_h($proofRow['vehicle_id'] ?? '') ?> start=<?= v3p_h($proofRow['starting_point_id'] ?? '') ?></div>
                        <div>Route</div><div><?= v3p_h($proofRow['pickup_address'] ?? '') ?> → <?= v3p_h($proofRow['dropoff_address'] ?? '') ?></div>
                        <div>Price</div><div><?= v3p_h($proofRow['price_text'] ?? '') ?></div>
                        <div>Last error</div><div><?= v3p_h($proofRow['last_error'] ?? 'NULL') ?></div>
                    </div>
                <?php else: ?>
                    <p class="warn">No V3 queue rows found.</p>
                <?php endif; ?>
            </div>
            <div class="card">
                <h2>Gate State</h2>
                <div class="kv">
                    <div>Config exists/readable</div><div><?= v3p_badge($gate['exists'] ? 'exists' : 'missing', $gate['exists'] ? 'good' : 'bad') ?> <?= v3p_badge($gate['readable'] ? 'readable' : 'not readable', $gate['readable'] ? 'good' : 'bad') ?></div>
                    <div>Loaded</div><div><?= v3p_badge($gate['loaded'] ? 'yes' : 'no', $gate['loaded'] ? 'good' : 'warn') ?></div>
                    <div>Enabled</div><div><?= v3p_badge($gate['enabled'] ? 'yes' : 'no', $gate['enabled'] ? 'bad' : 'good') ?></div>
                    <div>Mode</div><div><?= v3p_h($gate['mode']) ?></div>
                    <div>Adapter</div><div><?= v3p_h($gate['adapter']) ?></div>
                    <div>Hard enable</div><div><?= v3p_badge($gate['hard_enable_live_submit'] ? 'yes' : 'no', $gate['hard_enable_live_submit'] ? 'bad' : 'good') ?></div>
                    <div>OK for live submit</div><div><?= v3p_badge($gate['ok_for_future_live_submit'] ? 'yes' : 'no', $gate['ok_for_future_live_submit'] ? 'bad' : 'good') ?></div>
                </div>
                <?php if (!empty($gate['blocks'])): ?>
                    <h3 style="margin-top:14px">Gate blocks</h3>
                    <ul class="list">
                        <?php foreach ($gate['blocks'] as $block): ?><li><?= v3p_h($block) ?></li><?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </section>

        <section class="card">
            <h2>Verified Starting Point</h2>
            <?php if ($startOption): ?>
                <p class="good"><strong>Verified:</strong> lessor <?= v3p_h($startOption['edxeix_lessor_id'] ?? '') ?> / start <?= v3p_h($startOption['edxeix_starting_point_id'] ?? '') ?></p>
                <p><?= v3p_h($startOption['label'] ?? '') ?></p>
                <p class="small">Source: <?= v3p_h($startOption['source'] ?? '') ?> | Updated: <?= v3p_h($startOption['updated_at'] ?? '') ?></p>
            <?php else: ?>
                <p class="warn">No active verified starting-point option was found for the current proof row.</p>
            <?php endif; ?>
        </section>

        <?php if ($eventRows): ?>
        <section class="card">
            <h2>Recent Proof Row Events</h2>
            <div class="tablewrap">
                <table>
                    <thead><tr><th>ID</th><th>Type</th><th>Message</th><th>Created</th></tr></thead>
                    <tbody>
                    <?php foreach ($eventRows as $event): ?>
                        <tr>
                            <td><?= v3p_h($event['id'] ?? '') ?></td>
                            <td><?= v3p_h($event['event_type'] ?? '') ?></td>
                            <td><?= v3p_h($event['message'] ?? '') ?></td>
                            <td><?= v3p_h($event['created_at'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
        <?php endif; ?>

        <section class="card">
            <h2>Latest V3 Queue Rows</h2>
            <div class="tablewrap">
                <table>
                    <thead><tr><th>ID</th><th>Status</th><th>Customer</th><th>Pickup</th><th>Driver</th><th>Vehicle</th><th>IDs</th><th>Last error</th><th>Updated</th></tr></thead>
                    <tbody>
                    <?php foreach ($latestRows as $row): ?>
                        <tr>
                            <td><?= v3p_h($row['id'] ?? '') ?></td>
                            <td><?= v3p_badge((string)($row['queue_status'] ?? ''), v3p_status_type((string)($row['queue_status'] ?? ''))) ?></td>
                            <td><?= v3p_h($row['customer_name'] ?? '') ?></td>
                            <td><?= v3p_h($row['pickup_datetime'] ?? '') ?></td>
                            <td><?= v3p_h($row['driver_name'] ?? '') ?></td>
                            <td><?= v3p_h($row['vehicle_plate'] ?? '') ?></td>
                            <td>lessor=<?= v3p_h($row['lessor_id'] ?? '') ?><br>driver=<?= v3p_h($row['driver_id'] ?? '') ?><br>vehicle=<?= v3p_h($row['vehicle_id'] ?? '') ?><br>start=<?= v3p_h($row['starting_point_id'] ?? '') ?></td>
                            <td><?= v3p_h($row['last_error'] ?? '') ?></td>
                            <td><?= v3p_h($row['updated_at'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="card">
            <h2>Next Phase Boundary</h2>
            <ul class="list">
                <li>Next phase is closed-gate live adapter preparation only.</li>
                <li>Live submit must remain disabled until a real eligible future Bolt trip is approved explicitly.</li>
                <li>Forwarded/synthetic emails are useful for testing the V3 pipeline, but they are not final proof to open live submit.</li>
                <li>Live-ready proof rows may later become blocked by the expiry guard; this is expected and safe.</li>
                <li>V0 laptop/manual production helper remains untouched and available for business continuity.</li>
            </ul>
        </section>
    </main>
</div>
</body>
</html>
