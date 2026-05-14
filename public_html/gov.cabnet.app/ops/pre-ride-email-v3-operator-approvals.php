<?php
/**
 * gov.cabnet.app — V3 Operator Approval Visibility
 *
 * Read-only V3 page for inspecting operator approval state around the closed
 * live-submit gate. This page does not write to the database, does not call
 * Bolt, does not call EDXEIX, does not call AADE, and does not touch V0.
 */
declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Robots-Tag: noindex, nofollow', true);

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function badge(string $text, string $type = 'neutral'): string
{
    return '<span class="badge badge-' . h($type) . '">' . h($text) . '</span>';
}

function bool_badge($value, string $yes = 'yes', string $no = 'no'): string
{
    return $value ? badge($yes, 'good') : badge($no, 'bad');
}

function table_name_safe(string $name): string
{
    return '`' . str_replace('`', '``', $name) . '`';
}

function fetch_all(mysqli $db, string $sql, array $params = []): array
{
    $stmt = $db->prepare($sql);
    if (!empty($params)) {
        $types = '';
        foreach ($params as $param) {
            $types .= is_int($param) ? 'i' : (is_float($param) ? 'd' : 's');
        }
        $bind = [$types];
        foreach ($params as $idx => $param) {
            $bind[] = &$params[$idx];
        }
        call_user_func_array([$stmt, 'bind_param'], $bind);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

function fetch_one(mysqli $db, string $sql, array $params = []): ?array
{
    $rows = fetch_all($db, $sql, $params);
    return $rows[0] ?? null;
}

function table_exists(mysqli $db, string $table): bool
{
    $row = fetch_one(
        $db,
        'SELECT COUNT(*) AS c FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
        [$table]
    );
    return (int)($row['c'] ?? 0) > 0;
}

function table_columns(mysqli $db, string $table): array
{
    $rows = fetch_all(
        $db,
        'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION',
        [$table]
    );
    $cols = [];
    foreach ($rows as $row) {
        $name = (string)($row['COLUMN_NAME'] ?? '');
        if ($name !== '') {
            $cols[$name] = true;
        }
    }
    return $cols;
}

function col_exists(array $cols, string $name): bool
{
    return isset($cols[$name]);
}

function pick_col(array $cols, array $names): ?string
{
    foreach ($names as $name) {
        if (isset($cols[$name])) {
            return $name;
        }
    }
    return null;
}

function select_expr(array $cols, array $names, string $alias, string $fallback = "''"): string
{
    $col = pick_col($cols, $names);
    if ($col !== null) {
        return table_name_safe($col) . ' AS `' . str_replace('`', '``', $alias) . '`';
    }
    return $fallback . ' AS `' . str_replace('`', '``', $alias) . '`';
}

function parse_dt(?string $value): ?DateTimeImmutable
{
    $value = trim((string)$value);
    if ($value === '' || $value === '0000-00-00 00:00:00') {
        return null;
    }
    try {
        return new DateTimeImmutable($value, new DateTimeZone('Europe/Athens'));
    } catch (Throwable $e) {
        return null;
    }
}

function minutes_until(?string $value): string
{
    $dt = parse_dt($value);
    if (!$dt) {
        return '';
    }
    $now = new DateTimeImmutable('now', new DateTimeZone('Europe/Athens'));
    return (string)((int)floor(($dt->getTimestamp() - $now->getTimestamp()) / 60));
}

function status_badge(string $status): string
{
    if ($status === 'live_submit_ready' || $status === 'submit_dry_run_ready') {
        return badge($status, 'good');
    }
    if ($status === 'queued' || $status === 'processing') {
        return badge($status, 'warn');
    }
    if ($status === 'blocked' || $status === 'failed') {
        return badge($status, 'bad');
    }
    return badge($status !== '' ? $status : 'unknown', 'neutral');
}

$bootError = null;
$db = null;
try {
    $bootstrap = dirname(__DIR__, 3) . '/gov.cabnet.app_app/src/bootstrap.php';
    if (!is_file($bootstrap)) {
        throw new RuntimeException('Missing bootstrap: ' . $bootstrap);
    }
    $app = require $bootstrap;
    $db = $app['db']->connection();
} catch (Throwable $e) {
    $bootError = $e->getMessage();
}

$queueTable = 'pre_ride_email_v3_queue';
$approvalTable = 'pre_ride_email_v3_live_submit_approvals';
$configFile = dirname(__DIR__, 3) . '/gov.cabnet.app_config/pre_ride_email_v3_live_submit.php';

$queueRows = [];
$approvalRows = [];
$approvalByQueue = [];
$metrics = [
    'queue_total' => 0,
    'live_ready' => 0,
    'submit_ready' => 0,
    'blocked' => 0,
    'approvals_total' => 0,
    'approvals_table' => false,
    'approval_valid_like' => 0,
];
$schema = [
    'queue_exists' => false,
    'approval_exists' => false,
    'approval_columns' => [],
];
$gate = [
    'config_exists' => is_file($configFile),
    'config_readable' => is_readable($configFile),
    'loaded' => false,
    'enabled' => false,
    'mode' => 'disabled',
    'adapter' => 'disabled',
    'hard' => false,
    'ack' => false,
    'ok' => false,
    'blocks' => [],
];
$errors = [];

if ($db instanceof mysqli) {
    try {
        $schema['queue_exists'] = table_exists($db, $queueTable);
        $schema['approval_exists'] = table_exists($db, $approvalTable);
        $metrics['approvals_table'] = $schema['approval_exists'];

        if ($schema['queue_exists']) {
            $m = fetch_one($db, "SELECT COUNT(*) AS total, SUM(queue_status='live_submit_ready') AS live_ready, SUM(queue_status='submit_dry_run_ready') AS submit_ready, SUM(queue_status='blocked') AS blocked FROM " . table_name_safe($queueTable));
            $metrics['queue_total'] = (int)($m['total'] ?? 0);
            $metrics['live_ready'] = (int)($m['live_ready'] ?? 0);
            $metrics['submit_ready'] = (int)($m['submit_ready'] ?? 0);
            $metrics['blocked'] = (int)($m['blocked'] ?? 0);

            $queueRows = fetch_all($db, "SELECT id, queue_status, customer_name, customer_phone, pickup_datetime, driver_name, vehicle_plate, lessor_id, driver_id, vehicle_id, starting_point_id, last_error, created_at, updated_at FROM " . table_name_safe($queueTable) . " ORDER BY id DESC LIMIT 25");
        }

        if ($schema['approval_exists']) {
            $approvalCols = table_columns($db, $approvalTable);
            $schema['approval_columns'] = array_keys($approvalCols);
            $metrics['approvals_total'] = (int)(fetch_one($db, "SELECT COUNT(*) AS c FROM " . table_name_safe($approvalTable))['c'] ?? 0);

            $queueCol = pick_col($approvalCols, ['queue_id', 'v3_queue_id', 'pre_ride_email_v3_queue_id', 'pre_ride_queue_id']);
            $statusCol = pick_col($approvalCols, ['approval_status', 'status', 'state']);
            $approvedAtCol = pick_col($approvalCols, ['approved_at', 'created_at']);
            $expiresAtCol = pick_col($approvalCols, ['expires_at', 'valid_until', 'expires_on']);
            $operatorCol = pick_col($approvalCols, ['approved_by', 'operator_user', 'operator_name', 'created_by']);
            $ackCol = pick_col($approvalCols, ['acknowledgement_phrase', 'ack_phrase', 'approval_phrase', 'acknowledgement']);
            $notesCol = pick_col($approvalCols, ['notes', 'operator_note', 'reason']);

            $select = [
                select_expr($approvalCols, ['id'], 'id', 'NULL'),
                select_expr($approvalCols, ['queue_id', 'v3_queue_id', 'pre_ride_email_v3_queue_id', 'pre_ride_queue_id'], 'queue_id', 'NULL'),
                select_expr($approvalCols, ['approval_status', 'status', 'state'], 'approval_status', "''"),
                select_expr($approvalCols, ['approved_by', 'operator_user', 'operator_name', 'created_by'], 'approved_by', "''"),
                select_expr($approvalCols, ['approved_at', 'created_at'], 'approved_at', 'NULL'),
                select_expr($approvalCols, ['expires_at', 'valid_until', 'expires_on'], 'expires_at', 'NULL'),
                select_expr($approvalCols, ['acknowledgement_phrase', 'ack_phrase', 'approval_phrase', 'acknowledgement'], 'acknowledgement_phrase', "''"),
                select_expr($approvalCols, ['notes', 'operator_note', 'reason'], 'notes', "''"),
                select_expr($approvalCols, ['created_at'], 'created_at', 'NULL'),
                select_expr($approvalCols, ['updated_at'], 'updated_at', 'NULL'),
            ];
            $order = $approvedAtCol ? table_name_safe($approvedAtCol) . ' DESC' : '1 DESC';
            $approvalRows = fetch_all($db, 'SELECT ' . implode(', ', $select) . ' FROM ' . table_name_safe($approvalTable) . ' ORDER BY ' . $order . ' LIMIT 50');
            foreach ($approvalRows as $approval) {
                $qid = (string)($approval['queue_id'] ?? '');
                if ($qid !== '' && !isset($approvalByQueue[$qid])) {
                    $approvalByQueue[$qid] = $approval;
                }
            }

            foreach ($approvalRows as $approval) {
                $status = strtolower((string)($approval['approval_status'] ?? ''));
                $ack = trim((string)($approval['acknowledgement_phrase'] ?? ''));
                $exp = parse_dt((string)($approval['expires_at'] ?? ''));
                $now = new DateTimeImmutable('now', new DateTimeZone('Europe/Athens'));
                $notExpired = !$exp || $exp->getTimestamp() > $now->getTimestamp();
                if (($status === 'approved' || $status === 'valid' || $status === 'active' || $status === '') && $ack !== '' && $notExpired) {
                    $metrics['approval_valid_like']++;
                }
            }
        }

        if ($gate['config_exists'] && $gate['config_readable']) {
            $cfg = require $configFile;
            if (is_array($cfg)) {
                $gate['loaded'] = true;
                $gate['enabled'] = !empty($cfg['enabled']);
                $gate['mode'] = (string)($cfg['mode'] ?? 'disabled');
                $gate['adapter'] = (string)($cfg['adapter'] ?? 'disabled');
                $gate['hard'] = !empty($cfg['hard_enable_live_submit']);
                $gate['ack'] = trim((string)($cfg['required_acknowledgement'] ?? $cfg['acknowledgement_phrase'] ?? '')) !== '';
            }
        }
        if (!$gate['config_exists']) { $gate['blocks'][] = 'server live-submit config missing'; }
        if ($gate['config_exists'] && !$gate['config_readable']) { $gate['blocks'][] = 'server live-submit config unreadable'; }
        if (!$gate['loaded']) { $gate['blocks'][] = 'server live-submit config not loaded'; }
        if (!$gate['enabled']) { $gate['blocks'][] = 'enabled is false'; }
        if ($gate['mode'] !== 'live') { $gate['blocks'][] = 'mode is not live'; }
        if ($gate['adapter'] === '' || $gate['adapter'] === 'disabled') { $gate['blocks'][] = 'adapter is disabled'; }
        if (!$gate['hard']) { $gate['blocks'][] = 'hard_enable_live_submit is false'; }
        if (!$gate['ack']) { $gate['blocks'][] = 'required acknowledgement phrase is not present'; }
        $gate['ok'] = empty($gate['blocks']);
    } catch (Throwable $e) {
        $errors[] = $e->getMessage();
    }
}

$generatedAt = (new DateTimeImmutable('now', new DateTimeZone('Europe/Athens')))->format('Y-m-d H:i:s T');
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>V3 Operator Approvals | gov.cabnet.app</title>
<style>
:root{--bg:#eef2f8;--panel:#fff;--ink:#071b46;--muted:#42537b;--line:#d5ddeb;--nav:#303a62;--blue:#5865bd;--green:#55aa62;--amber:#d7952b;--red:#ca4b4b;--dark:#162144;--soft:#f7f9fd}*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--ink);font-family:Arial,Helvetica,sans-serif}.top{height:62px;background:#fff;border-bottom:1px solid var(--line);display:flex;align-items:center;gap:22px;padding:0 22px;position:sticky;top:0;z-index:10}.brand{font-weight:800;font-size:18px}.top a{color:#0b2470;text-decoration:none;font-weight:700;font-size:14px}.layout{display:grid;grid-template-columns:260px 1fr;min-height:calc(100vh - 62px)}.side{background:var(--nav);color:#fff;padding:22px 16px}.side h2{font-size:18px;margin:0 0 8px}.side p{font-size:13px;line-height:1.45;color:#e6eaff}.side .group{margin-top:22px;color:#bfc8e8;text-transform:uppercase;letter-spacing:.06em;font-size:12px;font-weight:800}.side a{display:block;color:#fff;text-decoration:none;padding:10px 10px;border-radius:8px;margin:3px 0;font-size:14px}.side a.active,.side a:hover{background:rgba(255,255,255,.16)}.main{padding:26px 24px 70px}.hero,.card{background:var(--panel);border:1px solid var(--line);border-radius:14px;padding:20px 22px;margin-bottom:18px;box-shadow:0 8px 24px rgba(16,33,68,.04)}.hero{border-left:7px solid var(--green)}h1{font-size:34px;margin:0 0 10px}h2{font-size:22px;margin:0 0 14px}p{color:var(--muted);line-height:1.45}.badges{display:flex;flex-wrap:wrap;gap:8px;margin:14px 0}.badge{display:inline-block;border-radius:999px;padding:5px 10px;font-size:12px;font-weight:800}.badge-good{background:#dcfce7;color:#09662a}.badge-warn{background:#fff2cc;color:#8a5700}.badge-bad{background:#fee2e2;color:#9a1f1f}.badge-neutral{background:#eaf0ff;color:#1c3d8c}.actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:12px}.btn{display:inline-block;border-radius:8px;padding:10px 14px;background:var(--blue);color:#fff;text-decoration:none;font-weight:800;font-size:14px}.btn.green{background:var(--green)}.btn.amber{background:var(--amber)}.btn.dark{background:var(--dark)}.grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}.metric{background:var(--soft);border:1px solid var(--line);border-radius:12px;padding:16px}.metric strong{display:block;font-size:31px;line-height:1}.metric span{font-size:13px;color:var(--muted)}.two{display:grid;grid-template-columns:1fr 1fr;gap:16px}.table-wrap{overflow:auto;border:1px solid var(--line);border-radius:10px}table{width:100%;border-collapse:collapse;min-width:920px}th,td{padding:10px 12px;border-bottom:1px solid var(--line);text-align:left;vertical-align:top;font-size:14px}th{background:#f5f7fb;text-transform:uppercase;font-size:12px;letter-spacing:.04em;color:#435174}.kv{width:100%;border-collapse:collapse}.kv th{width:210px;text-transform:none;font-size:14px;background:#f7f9fd}.list{margin:0;padding-left:20px;color:var(--muted)}.list li{margin:7px 0}.mono{font-family:ui-monospace,SFMono-Regular,Consolas,monospace;font-size:12px;white-space:pre-wrap;word-break:break-word}.small{font-size:12px;color:var(--muted)}@media(max-width:1050px){.layout{grid-template-columns:1fr}.side{position:static}.grid,.two{grid-template-columns:1fr}.top{overflow:auto}.main{padding:16px 12px}.hero h1{font-size:28px}}
</style>
</head>
<body>
<header class="top">
    <div class="brand">EA / gov.cabnet.app</div>
    <a href="/ops/index.php">Ops Index</a>
    <a href="/ops/pre-ride-email-v3-dashboard.php">V3 Control Center</a>
    <a href="/ops/pre-ride-email-v3-proof.php">Proof</a>
    <a href="/ops/pre-ride-email-v3-live-package-export.php">Package Export</a>
</header>
<div class="layout">
    <aside class="side">
        <h2>V3 Automation</h2>
        <p>Read-only operator approval visibility. Live submit remains disabled.</p>
        <div class="group">Approval layer</div>
        <a class="active" href="/ops/pre-ride-email-v3-operator-approvals.php">Operator Approvals</a>
        <a href="/ops/pre-ride-email-v3-proof.php">Proof Dashboard</a>
        <a href="/ops/pre-ride-email-v3-live-package-export.php">Package Export</a>
        <div class="group">Monitoring</div>
        <a href="/ops/pre-ride-email-v3-monitor.php">Compact Monitor</a>
        <a href="/ops/pre-ride-email-v3-queue-focus.php">Queue Focus</a>
        <a href="/ops/pre-ride-email-v3-pulse-focus.php">Pulse Focus</a>
        <a href="/ops/pre-ride-email-v3-storage-check.php">Storage Check</a>
        <div class="group">Gate</div>
        <a href="/ops/pre-ride-email-v3-live-submit-gate.php">Locked Submit Gate</a>
        <a href="/ops/pre-ride-email-v3-live-payload-audit.php">Payload Audit</a>
    </aside>
    <main class="main">
        <section class="hero">
            <h1>V3 Operator Approval Visibility</h1>
            <p>Read-only inspection of V3 operator approvals, queue rows, and the closed live-submit gate. This page does not make operational decisions.</p>
            <div class="badges">
                <?= badge('V3 only', 'neutral') ?>
                <?= badge('read-only', 'good') ?>
                <?= badge('live submit disabled', 'bad') ?>
                <?= badge('no EDXEIX call', 'good') ?>
                <?= badge('V0 untouched', 'good') ?>
                <?= badge('generated ' . $generatedAt, 'neutral') ?>
            </div>
            <div class="actions">
                <a class="btn green" href="/ops/pre-ride-email-v3-proof.php">Open Proof</a>
                <a class="btn" href="/ops/pre-ride-email-v3-live-package-export.php">Open Package Export</a>
                <a class="btn amber" href="/ops/pre-ride-email-v3-live-submit-gate.php">Open Locked Gate</a>
                <a class="btn dark" href="/ops/pre-ride-email-v3-storage-check.php">Open Storage Check</a>
            </div>
        </section>

        <?php if ($bootError): ?>
            <section class="card"><?= badge('bootstrap error', 'bad') ?> <span class="mono"><?= h($bootError) ?></span></section>
        <?php endif; ?>
        <?php if (!empty($errors)): ?>
            <section class="card"><h2>Page warnings</h2><ul class="list"><?php foreach ($errors as $err): ?><li><?= h($err) ?></li><?php endforeach; ?></ul></section>
        <?php endif; ?>

        <section class="grid">
            <div class="metric"><strong><?= h($metrics['approvals_total']) ?></strong><span>Total approval records</span></div>
            <div class="metric"><strong><?= h($metrics['approval_valid_like']) ?></strong><span>Valid-like approval records</span></div>
            <div class="metric"><strong><?= h($metrics['live_ready']) ?></strong><span>Current live-submit-ready rows</span></div>
            <div class="metric"><strong><?= h($metrics['blocked']) ?></strong><span>Blocked V3 queue rows</span></div>
        </section>

        <section class="two" style="margin-top:18px;">
            <div class="card">
                <h2>Approval Table State</h2>
                <table class="kv">
                    <tr><th>Approval table</th><td><?= bool_badge($schema['approval_exists'], 'exists', 'missing') ?></td></tr>
                    <tr><th>Queue table</th><td><?= bool_badge($schema['queue_exists'], 'exists', 'missing') ?></td></tr>
                    <tr><th>Detected columns</th><td><span class="mono"><?= h(implode(', ', $schema['approval_columns'])) ?></span></td></tr>
                </table>
                <p class="small">The page adapts to the approval table columns that exist on the live server and does not require schema changes.</p>
            </div>
            <div class="card">
                <h2>Master Gate State</h2>
                <table class="kv">
                    <tr><th>Config exists/readable</th><td><?= bool_badge($gate['config_exists'], 'exists', 'missing') ?> <?= bool_badge($gate['config_readable'], 'readable', 'not readable') ?></td></tr>
                    <tr><th>Loaded</th><td><?= bool_badge($gate['loaded']) ?></td></tr>
                    <tr><th>Enabled</th><td><?= bool_badge($gate['enabled']) ?></td></tr>
                    <tr><th>Mode</th><td><?= h($gate['mode']) ?></td></tr>
                    <tr><th>Adapter</th><td><?= h($gate['adapter']) ?></td></tr>
                    <tr><th>Hard enable</th><td><?= bool_badge($gate['hard']) ?></td></tr>
                    <tr><th>Required ack phrase</th><td><?= bool_badge($gate['ack'], 'present', 'absent') ?></td></tr>
                    <tr><th>OK for live submit</th><td><?= bool_badge($gate['ok']) ?></td></tr>
                </table>
                <?php if (!empty($gate['blocks'])): ?>
                    <h3>Gate blocks</h3>
                    <ul class="list"><?php foreach ($gate['blocks'] as $block): ?><li><?= h($block) ?></li><?php endforeach; ?></ul>
                <?php endif; ?>
            </div>
        </section>

        <section class="card">
            <h2>Latest V3 Rows With Approval Visibility</h2>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Queue</th><th>Status</th><th>Pickup</th><th>Min</th><th>Transfer</th><th>IDs</th><th>Approval</th><th>Expires</th><th>Last Error</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($queueRows)): ?>
                        <tr><td colspan="9">No V3 queue rows found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($queueRows as $row): ?>
                            <?php
                            $qid = (string)($row['id'] ?? '');
                            $approval = $approvalByQueue[$qid] ?? null;
                            $approvalStatus = $approval ? (string)($approval['approval_status'] ?? '') : '';
                            $approvalAck = $approval ? trim((string)($approval['acknowledgement_phrase'] ?? '')) : '';
                            ?>
                            <tr>
                                <td><strong>#<?= h($qid) ?></strong></td>
                                <td><?= status_badge((string)($row['queue_status'] ?? '')) ?></td>
                                <td><?= h($row['pickup_datetime'] ?? '') ?></td>
                                <td><?= h(minutes_until((string)($row['pickup_datetime'] ?? ''))) ?></td>
                                <td><?= h($row['customer_name'] ?? '') ?><br><span class="small"><?= h(($row['driver_name'] ?? '') . ' / ' . ($row['vehicle_plate'] ?? '')) ?></span></td>
                                <td class="mono">lessor=<?= h($row['lessor_id'] ?? '') ?> driver=<?= h($row['driver_id'] ?? '') ?><br>vehicle=<?= h($row['vehicle_id'] ?? '') ?> start=<?= h($row['starting_point_id'] ?? '') ?></td>
                                <td>
                                    <?php if ($approval): ?>
                                        <?= badge($approvalStatus !== '' ? $approvalStatus : 'record found', 'warn') ?>
                                        <?= $approvalAck !== '' ? badge('ack present', 'good') : badge('ack absent', 'bad') ?><br>
                                        <span class="small"><?= h($approval['approved_by'] ?? '') ?> <?= h($approval['approved_at'] ?? '') ?></span>
                                    <?php else: ?>
                                        <?= badge('no approval record', 'bad') ?>
                                    <?php endif; ?>
                                </td>
                                <td><?= h($approval['expires_at'] ?? '') ?></td>
                                <td class="mono"><?= h($row['last_error'] ?? '') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="card">
            <h2>Latest Approval Records</h2>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>ID</th><th>Queue</th><th>Status</th><th>Approved By</th><th>Approved At</th><th>Expires</th><th>Acknowledgement</th><th>Notes</th></tr></thead>
                    <tbody>
                    <?php if (empty($approvalRows)): ?>
                        <tr><td colspan="8">No approval records found, or approval table is empty.</td></tr>
                    <?php else: ?>
                        <?php foreach ($approvalRows as $approval): ?>
                            <tr>
                                <td><?= h($approval['id'] ?? '') ?></td>
                                <td><?= h($approval['queue_id'] ?? '') ?></td>
                                <td><?= h($approval['approval_status'] ?? '') ?></td>
                                <td><?= h($approval['approved_by'] ?? '') ?></td>
                                <td><?= h($approval['approved_at'] ?? '') ?></td>
                                <td><?= h($approval['expires_at'] ?? '') ?></td>
                                <td class="mono"><?= h($approval['acknowledgement_phrase'] ?? '') ?></td>
                                <td class="mono"><?= h($approval['notes'] ?? '') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="card">
            <h2>Safety Notes</h2>
            <ul class="list">
                <li>This page is read-only and does not create or validate approvals.</li>
                <li>Operator judgment remains outside the software. This page only exposes approval state.</li>
                <li>Live submit remains blocked unless the master gate, adapter, hard-enable flag, acknowledgement phrase, and operator approval are all valid.</li>
                <li>V0 laptop/manual production helper remains untouched.</li>
            </ul>
        </section>
    </main>
</div>
</body>
</html>
