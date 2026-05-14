<?php
/**
 * gov.cabnet.app — V3 Queue Focus
 *
 * Fast read-only queue view for V3 pre-ride email rows.
 * Does not call Bolt, EDXEIX, AADE, Gmail, or production submission tables.
 * Does not modify database rows or files.
 */

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Robots-Tag: noindex, nofollow', true);

require_once __DIR__ . '/_ops-nav.php';

function gov_v3_qf_db(): array
{
    $bootstrapFile = dirname(__DIR__, 3) . '/gov.cabnet.app_app/src/bootstrap.php';
    if (!is_file($bootstrapFile)) {
        return ['ok' => false, 'error' => 'Missing bootstrap file: ' . $bootstrapFile, 'connection' => null];
    }

    try {
        $app = require $bootstrapFile;
        $db = $app['db'] ?? null;
        if (!is_object($db) || !method_exists($db, 'connection')) {
            return ['ok' => false, 'error' => 'Bootstrap loaded, but database service is unavailable.', 'connection' => null];
        }
        $mysqli = $db->connection();
        if (!$mysqli instanceof mysqli) {
            return ['ok' => false, 'error' => 'Database service did not return mysqli.', 'connection' => null];
        }
        return ['ok' => true, 'error' => null, 'connection' => $mysqli];
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => $e->getMessage(), 'connection' => null];
    }
}

function gov_v3_qf_ident(string $name): string
{
    if (!preg_match('/^[A-Za-z0-9_]+$/', $name)) {
        throw new InvalidArgumentException('Invalid SQL identifier.');
    }
    return '`' . $name . '`';
}

function gov_v3_qf_table_exists(mysqli $mysqli, string $table): bool
{
    try {
        $sql = 'SELECT COUNT(*) AS c FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?';
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param('s', $table);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        return (int)($row['c'] ?? 0) > 0;
    } catch (Throwable $e) {
        return false;
    }
}

function gov_v3_qf_columns(mysqli $mysqli, string $table): array
{
    $cols = [];
    try {
        $sql = 'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?';
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param('s', $table);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $cols[(string)$row['COLUMN_NAME']] = true;
        }
    } catch (Throwable $e) {
        return [];
    }
    return $cols;
}

function gov_v3_qf_count(mysqli $mysqli, string $table, string $where): int
{
    try {
        $sql = 'SELECT COUNT(*) AS c FROM ' . gov_v3_qf_ident($table) . ' WHERE ' . $where;
        $result = $mysqli->query($sql);
        $row = $result ? $result->fetch_assoc() : null;
        return (int)($row['c'] ?? 0);
    } catch (Throwable $e) {
        return 0;
    }
}

function gov_v3_qf_status_badge_type(string $status): string
{
    if ($status === 'live_submit_ready' || $status === 'submit_dry_run_ready') {
        return 'good';
    }
    if ($status === 'blocked') {
        return 'bad';
    }
    if ($status === 'submitted') {
        return 'info';
    }
    if ($status === 'new' || $status === 'intake_ready' || $status === 'starting_point_verified') {
        return 'warn';
    }
    return 'neutral';
}

function gov_v3_qf_minutes_badge_type(?int $minutes): string
{
    if ($minutes === null) {
        return 'neutral';
    }
    if ($minutes < 0) {
        return 'bad';
    }
    if ($minutes <= 10) {
        return 'warn';
    }
    return 'good';
}

function gov_v3_qf_text_preview(?string $text, int $max = 150): string
{
    $text = trim((string)$text);
    if ($text === '') {
        return '';
    }
    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        return mb_strlen($text, 'UTF-8') > $max ? mb_substr($text, 0, $max, 'UTF-8') . '…' : $text;
    }
    return strlen($text) > $max ? substr($text, 0, $max) . '…' : $text;
}

function gov_v3_qf_selected_columns(array $cols): array
{
    $wanted = [
        'id',
        'queue_status',
        'customer_name',
        'pickup_datetime',
        'driver_name',
        'vehicle_plate',
        'lessor_id',
        'driver_id',
        'vehicle_id',
        'starting_point_id',
        'last_error',
        'created_at',
        'updated_at',
    ];
    $selected = [];
    foreach ($wanted as $col) {
        if (isset($cols[$col])) {
            $selected[] = gov_v3_qf_ident($col);
        }
    }
    if (isset($cols['pickup_datetime'])) {
        $selected[] = 'TIMESTAMPDIFF(MINUTE, NOW(), `pickup_datetime`) AS `minutes_until_pickup`';
    } else {
        $selected[] = 'NULL AS `minutes_until_pickup`';
    }
    return $selected;
}

function gov_v3_qf_data(): array
{
    $data = [
        'ok' => false,
        'warnings' => [],
        'metrics' => [
            'total' => 0,
            'active' => 0,
            'future_active' => 0,
            'dry_ready' => 0,
            'live_ready' => 0,
            'blocked' => 0,
            'submitted' => 0,
            'expired_blocked' => 0,
        ],
        'rows' => [],
        'status_counts' => [],
        'table_exists' => false,
        'columns_ok' => false,
        'db_error' => null,
        'generated_at' => date('Y-m-d H:i:s T'),
    ];

    $db = gov_v3_qf_db();
    if (!$db['ok']) {
        $data['db_error'] = $db['error'];
        return $data;
    }

    /** @var mysqli $mysqli */
    $mysqli = $db['connection'];
    $table = 'pre_ride_email_v3_queue';
    if (!gov_v3_qf_table_exists($mysqli, $table)) {
        $data['db_error'] = 'V3 queue table is not present.';
        return $data;
    }

    $data['table_exists'] = true;
    $cols = gov_v3_qf_columns($mysqli, $table);
    $required = ['id', 'queue_status', 'pickup_datetime'];
    $missing = [];
    foreach ($required as $col) {
        if (!isset($cols[$col])) {
            $missing[] = $col;
        }
    }
    if ($missing !== []) {
        $data['db_error'] = 'Missing required V3 queue columns: ' . implode(', ', $missing);
        return $data;
    }
    $data['columns_ok'] = true;

    $statusCol = '`queue_status`';
    $pickupCol = '`pickup_datetime`';
    $data['metrics']['total'] = gov_v3_qf_count($mysqli, $table, '1=1');
    $data['metrics']['blocked'] = gov_v3_qf_count($mysqli, $table, $statusCol . " = 'blocked'");
    $data['metrics']['submitted'] = gov_v3_qf_count($mysqli, $table, $statusCol . " = 'submitted'");
    $data['metrics']['dry_ready'] = gov_v3_qf_count($mysqli, $table, $statusCol . " = 'submit_dry_run_ready'");
    $data['metrics']['live_ready'] = gov_v3_qf_count($mysqli, $table, $statusCol . " = 'live_submit_ready'");
    $data['metrics']['active'] = gov_v3_qf_count($mysqli, $table, $statusCol . " NOT IN ('blocked','submitted')");
    $data['metrics']['future_active'] = gov_v3_qf_count($mysqli, $table, $statusCol . " NOT IN ('blocked','submitted') AND " . $pickupCol . ' > NOW()');
    if (isset($cols['last_error'])) {
        $data['metrics']['expired_blocked'] = gov_v3_qf_count($mysqli, $table, $statusCol . " = 'blocked' AND `last_error` LIKE '%expired%'");
    }

    try {
        $result = $mysqli->query('SELECT `queue_status`, COUNT(*) AS c FROM ' . gov_v3_qf_ident($table) . ' GROUP BY `queue_status` ORDER BY c DESC, `queue_status` ASC');
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $data['status_counts'][] = ['status' => (string)($row['queue_status'] ?? 'unknown'), 'count' => (int)($row['c'] ?? 0)];
            }
        }
    } catch (Throwable $e) {
        $data['warnings'][] = 'Status counts could not be loaded: ' . $e->getMessage();
    }

    try {
        $selected = gov_v3_qf_selected_columns($cols);
        $sql = 'SELECT ' . implode(', ', $selected) . ' FROM ' . gov_v3_qf_ident($table) . ' ORDER BY `id` DESC LIMIT 25';
        $result = $mysqli->query($sql);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                if (isset($row['minutes_until_pickup']) && $row['minutes_until_pickup'] !== null) {
                    $row['minutes_until_pickup'] = (int)$row['minutes_until_pickup'];
                }
                $data['rows'][] = $row;
            }
        }
    } catch (Throwable $e) {
        $data['warnings'][] = 'Latest rows could not be loaded: ' . $e->getMessage();
    }

    $data['ok'] = true;
    return $data;
}

$data = gov_v3_qf_data();
$metrics = $data['metrics'];
$newest = $data['rows'][0] ?? null;
$newestStatus = is_array($newest) ? (string)($newest['queue_status'] ?? 'unknown') : 'none';
$newestMinutes = is_array($newest) && array_key_exists('minutes_until_pickup', $newest) ? $newest['minutes_until_pickup'] : null;
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>V3 Queue Focus | gov.cabnet.app Ops</title>
    <style>
        :root { --page-bg:#eef2f7; --panel:#fff; --ink:#09245a; --ink-strong:#071f4f; --muted:#536683; --line:#d3dbe8; --sidebar:#2d3557; --brand:#5967b1; --brand-dark:#43509a; --green:#36a15a; --green-soft:#e7f7eb; --amber:#d99022; --amber-soft:#fff6e6; --red:#c7352c; --red-soft:#feeceb; --blue:#3468d8; --blue-soft:#e9f0ff; --shadow:0 1px 2px rgba(15,23,42,.06); }
        * { box-sizing:border-box; }
        body { margin:0; background:var(--page-bg); color:var(--ink); font-family:Arial, Helvetica, sans-serif; font-size:14px; }
        a { color:var(--brand-dark); }
        .ops-shell-topbar { min-height:68px; background:#fff; border-bottom:1px solid var(--line); display:flex; align-items:center; gap:22px; padding:0 26px; position:sticky; top:0; z-index:30; }
        .ops-shell-brand { display:flex; align-items:center; gap:10px; min-width:290px; color:var(--ink); text-decoration:none; }
        .ops-shell-logo { width:46px; height:46px; border:2px solid #7d8ccc; border-radius:50%; display:grid; place-items:center; font-weight:800; color:#5361a9; letter-spacing:.03em; font-size:19px; }
        .ops-shell-brand-text strong { display:block; font-size:20px; line-height:1; color:#1e3a78; }
        .ops-shell-brand-text em { display:block; font-style:normal; color:#31466c; font-size:12px; margin-top:3px; }
        .ops-shell-topnav { display:flex; align-items:center; gap:5px; flex:1; min-width:0; overflow:auto; padding:8px 0; }
        .ops-shell-topnav a { color:#34436b; text-decoration:none; padding:9px 13px; border-radius:14px; white-space:nowrap; font-size:13px; font-weight:700; }
        .ops-shell-topnav a:hover, .ops-shell-topnav a.active { background:#e9eefb; color:#2f4193; box-shadow:inset 0 0 0 1px #d4dcf4; }
        .ops-shell-user { display:flex; align-items:center; gap:9px; color:#2d3a62; min-width:150px; justify-content:flex-end; }
        .ops-shell-user-mark { width:36px; height:36px; border-radius:50%; display:grid; place-items:center; background:#5864b0; color:#fff; font-weight:800; }
        .ops-shell-user strong { display:block; font-size:12px; line-height:1; letter-spacing:.03em; }
        .ops-shell-user em { display:block; font-style:normal; font-size:10px; color:#64748b; margin-top:2px; }
        .ops-shell-layout { display:grid; grid-template-columns:300px 1fr; min-height:calc(100vh - 68px); }
        .ops-shell-sidebar { background:var(--sidebar); color:#fff; padding:22px 16px 44px; box-shadow:inset -1px 0 0 rgba(0,0,0,.08); }
        .ops-operator-card { border:1px solid rgba(255,255,255,.14); border-radius:8px; background:rgba(255,255,255,.06); padding:12px; margin-bottom:22px; }
        .ops-operator-top { display:flex; align-items:center; gap:10px; margin-bottom:11px; }
        .ops-operator-avatar { width:30px; height:30px; border-radius:50%; display:grid; place-items:center; background:#68739a; color:#fff; font-weight:800; border:1px solid rgba(255,255,255,.22); }
        .ops-operator-card strong { display:block; color:#fff; }
        .ops-operator-card em { display:block; font-style:normal; color:#d9e1f2; font-size:12px; }
        .ops-operator-actions { display:flex; gap:7px; flex-wrap:wrap; }
        .ops-operator-actions a { color:#fff; text-decoration:none; font-size:11px; font-weight:700; padding:6px 8px; border:1px solid rgba(255,255,255,.22); border-radius:4px; background:rgba(0,0,0,.08); }
        .ops-side-section { margin-bottom:22px; }
        .ops-side-section h3 { color:#cdd7ef; text-transform:uppercase; letter-spacing:.06em; font-size:11px; font-weight:500; margin:0 0 8px 7px; }
        .ops-side-link { display:block; color:#fff; text-decoration:none; padding:11px 12px; border-radius:4px; margin:2px 0; font-weight:700; font-size:14px; }
        .ops-side-link:hover, .ops-side-link.active { background:#5662b1; }
        .ops-side-hint { margin:6px 7px 13px; color:#edf3ff; line-height:1.45; }
        .ops-main { padding:22px 24px 60px; max-width:1600px; width:100%; }
        .ops-page-title { display:flex; align-items:flex-start; justify-content:space-between; gap:18px; margin-bottom:8px; }
        h1 { margin:0; color:var(--ink-strong); font-size:30px; line-height:1.15; letter-spacing:-.02em; }
        .ops-kicker { color:#5662b1; text-transform:uppercase; letter-spacing:.08em; font-size:12px; font-weight:800; margin-bottom:5px; }
        .ops-page-subtitle { margin:6px 0 0; color:var(--muted); line-height:1.45; max-width:980px; }
        .ops-page-tabs { display:flex; flex-wrap:wrap; gap:12px; margin:18px 0; }
        .ops-page-tabs a { text-decoration:none; color:#4d5e7f; padding:12px 16px; border-radius:22px; background:#fff; border:1px solid var(--line); font-weight:800; font-size:13px; }
        .ops-page-tabs a:hover, .ops-page-tabs a.active { background:#5662b1; color:#fff; }
        .ops-badge { display:inline-block; padding:5px 9px; border-radius:999px; font-size:11px; font-weight:900; margin:2px; white-space:nowrap; }
        .ops-badge-good { background:var(--green-soft); color:#087a33; } .ops-badge-warn { background:var(--amber-soft); color:#a55700; } .ops-badge-bad { background:var(--red-soft); color:#a11c15; } .ops-badge-neutral { background:#eef2f7; color:#40516d; } .ops-badge-info { background:var(--blue-soft); color:#214aab; } .ops-badge-dark { background:#29345a; color:#fff; }
        .ops-alert { border:1px solid var(--line); background:#fff; border-radius:8px; padding:13px 16px; margin:16px 0; box-shadow:var(--shadow); }
        .ops-alert.safe { border-left:4px solid var(--green); background:#f3fff5; } .ops-alert.warn { border-left:4px solid var(--amber); background:#fffaf1; } .ops-alert.danger { border-left:4px solid var(--red); background:#fff6f5; }
        .ops-grid { display:grid; gap:14px; } .ops-grid.metrics { grid-template-columns:repeat(6,minmax(0,1fr)); margin:16px 0; } .ops-grid.two { grid-template-columns:1.2fr .8fr; margin:16px 0; }
        .ops-card { background:#fff; border:1px solid var(--line); border-radius:8px; padding:16px; box-shadow:var(--shadow); }
        .ops-card h2, .ops-card h3 { color:var(--ink-strong); margin:0 0 10px; } .ops-card h2 { font-size:20px; } .ops-card h3 { font-size:17px; }
        .ops-card p { color:#31486d; line-height:1.45; margin:7px 0; }
        .metric-card { min-height:108px; border-left:4px solid var(--brand); } .metric-card.good { border-left-color:var(--green); } .metric-card.bad { border-left-color:var(--red); } .metric-card.warn { border-left-color:var(--amber); }
        .metric-card strong { display:block; font-size:30px; color:var(--ink-strong); line-height:1; } .metric-card span { display:block; color:#344a70; margin-top:8px; font-weight:700; } .metric-card small { display:block; color:#5c6b84; margin-top:7px; line-height:1.35; }
        .status-list { list-style:none; margin:0; padding:0; } .status-list li { display:flex; justify-content:space-between; gap:16px; align-items:flex-start; padding:11px 0; border-bottom:1px solid var(--line); }
        .status-list li:last-child { border-bottom:0; } .status-list span { color:#40577b; } .status-list strong { color:#071f4f; text-align:right; }
        .table-wrap { overflow:auto; border:1px solid var(--line); border-radius:8px; } table { width:100%; border-collapse:collapse; min-width:1120px; } th,td { padding:10px 12px; border-bottom:1px solid var(--line); text-align:left; vertical-align:top; } th { color:#445078; text-transform:uppercase; letter-spacing:.05em; font-size:11px; background:#f6f8fc; }
        tr.status-blocked { background:#fffafa; } tr.status-submit_dry_run_ready, tr.status-live_submit_ready { background:#f7fff9; } tr.status-submitted { background:#f7fbff; }
        .mono { font-family:Consolas, Monaco, monospace; font-size:12px; overflow-wrap:anywhere; } pre { white-space:pre-wrap; word-break:break-word; margin:8px 0 0; background:#f6f8fc; border:1px solid var(--line); padding:10px; border-radius:6px; color:#15264f; }
        .btn { display:inline-flex; align-items:center; justify-content:center; text-decoration:none; border:0; border-radius:4px; padding:10px 13px; color:#fff; font-weight:800; font-size:13px; background:var(--brand); box-shadow:var(--shadow); }
        .btn.green { background:var(--green); } .btn.amber { background:var(--amber); } .btn.blue { background:var(--blue); } .btn.slate { background:#687386; }
        .ops-actions { display:flex; flex-wrap:wrap; gap:9px; margin-top:14px; }
        .error-preview { max-width:460px; }
        @media (max-width:1300px) { .ops-grid.metrics { grid-template-columns:repeat(3,1fr); } .ops-grid.two { grid-template-columns:1fr; } }
        @media (max-width:1200px) { .ops-shell-layout { grid-template-columns:1fr; } .ops-shell-sidebar { position:static; } }
        @media (max-width:760px) { .ops-shell-topbar { padding:10px 14px; flex-wrap:wrap; } .ops-shell-brand { min-width:0; } .ops-shell-user { display:none; } .ops-main { padding:16px 14px 44px; } .ops-page-title { display:block; } .ops-grid.metrics { grid-template-columns:1fr; } }
    </style>
</head>
<body>
<?php gov_ops_render_topbar('pre_ride'); ?>
<div class="ops-shell-layout">
    <?php gov_ops_render_sidebar('v3_queue_focus'); ?>
    <main class="ops-main">
        <div class="ops-page-title">
            <div>
                <div class="ops-kicker">V3 Queue Visibility</div>
                <h1>V3 Queue Focus</h1>
                <p class="ops-page-subtitle">Fast read-only view for the latest V3 pre-ride queue rows, status counts, pickup timing, and block reasons. This page does not modify data or make operational decisions.</p>
            </div>
            <div>
                <?= gov_ops_badge('Production', 'dark') ?>
                <?= gov_ops_badge('V3 only', 'info') ?>
                <?= gov_ops_badge('V0 untouched', 'good') ?>
                <?= gov_ops_badge('Read-only', 'good') ?>
            </div>
        </div>

        <?php gov_ops_render_page_tabs([
            ['key' => 'monitor', 'label' => 'Compact Monitor', 'href' => '/ops/pre-ride-email-v3-monitor.php'],
            ['key' => 'queue_focus', 'label' => 'Queue Focus', 'href' => '/ops/pre-ride-email-v3-queue-focus.php'],
            ['key' => 'dashboard', 'label' => 'V3 Dashboard', 'href' => '/ops/pre-ride-email-v3-dashboard.php'],
            ['key' => 'queue_watch', 'label' => 'Queue Watch', 'href' => '/ops/pre-ride-email-v3-queue-watch.php'],
            ['key' => 'pulse', 'label' => 'Pulse Monitor', 'href' => '/ops/pre-ride-email-v3-fast-pipeline-pulse.php'],
            ['key' => 'readiness', 'label' => 'Automation Readiness', 'href' => '/ops/pre-ride-email-v3-automation-readiness.php'],
            ['key' => 'storage', 'label' => 'Storage Check', 'href' => '/ops/pre-ride-email-v3-storage-check.php'],
        ], 'queue_focus'); ?>

        <section class="ops-alert safe">
            <strong>SAFE READ-ONLY V3 VIEW.</strong>
            No Bolt call, no EDXEIX call, no AADE call, no DB writes, no V0 production helper changes. Generated <?= gov_ops_h($data['generated_at']) ?>.
        </section>

        <?php if (!$data['ok']): ?>
            <section class="ops-alert danger"><strong>Queue focus unavailable:</strong> <?= gov_ops_h((string)$data['db_error']) ?></section>
        <?php endif; ?>

        <?php foreach ($data['warnings'] as $warning): ?>
            <section class="ops-alert warn"><strong>Warning:</strong> <?= gov_ops_h((string)$warning) ?></section>
        <?php endforeach; ?>

        <section class="ops-grid metrics">
            <article class="ops-card metric-card">
                <strong><?= gov_ops_h((string)$metrics['total']) ?></strong>
                <span>Total</span>
                <small>All V3 queue rows.</small>
            </article>
            <article class="ops-card metric-card <?= ((int)$metrics['active'] > 0) ? 'warn' : '' ?>">
                <strong><?= gov_ops_h((string)$metrics['active']) ?></strong>
                <span>Active</span>
                <small>Not blocked or submitted.</small>
            </article>
            <article class="ops-card metric-card <?= ((int)$metrics['future_active'] > 0) ? 'good' : '' ?>">
                <strong><?= gov_ops_h((string)$metrics['future_active']) ?></strong>
                <span>Future active</span>
                <small>Future pickup rows still moving.</small>
            </article>
            <article class="ops-card metric-card <?= ((int)$metrics['dry_ready'] > 0) ? 'good' : '' ?>">
                <strong><?= gov_ops_h((string)$metrics['dry_ready']) ?></strong>
                <span>Dry-run ready</span>
                <small>Pre-live proof status.</small>
            </article>
            <article class="ops-card metric-card <?= ((int)$metrics['live_ready'] > 0) ? 'good' : '' ?>">
                <strong><?= gov_ops_h((string)$metrics['live_ready']) ?></strong>
                <span>Live-ready</span>
                <small>Gate remains closed.</small>
            </article>
            <article class="ops-card metric-card <?= ((int)$metrics['blocked'] > 0) ? 'bad' : '' ?>">
                <strong><?= gov_ops_h((string)$metrics['blocked']) ?></strong>
                <span>Blocked</span>
                <small><?= gov_ops_h((string)$metrics['expired_blocked']) ?> expired/past block(s).</small>
            </article>
        </section>

        <section class="ops-grid two">
            <article class="ops-card">
                <h2>Newest Row Summary</h2>
                <?php if (!is_array($newest)): ?>
                    <p>No V3 queue rows found.</p>
                <?php else: ?>
                    <ul class="status-list">
                        <li><span>ID / status</span><strong>#<?= gov_ops_h((string)($newest['id'] ?? '')) ?> <?= gov_ops_badge($newestStatus, gov_v3_qf_status_badge_type($newestStatus)) ?></strong></li>
                        <li><span>Customer</span><strong><?= gov_ops_h((string)($newest['customer_name'] ?? '')) ?></strong></li>
                        <li><span>Pickup / minutes</span><strong><?= gov_ops_h((string)($newest['pickup_datetime'] ?? '')) ?> <?= gov_ops_badge($newestMinutes === null ? 'n/a' : (string)$newestMinutes . ' min', gov_v3_qf_minutes_badge_type(is_int($newestMinutes) ? $newestMinutes : null)) ?></strong></li>
                        <li><span>Driver / vehicle</span><strong><?= gov_ops_h((string)($newest['driver_name'] ?? '')) ?> / <?= gov_ops_h((string)($newest['vehicle_plate'] ?? '')) ?></strong></li>
                        <li><span>Lessor / driver / vehicle / start</span><strong><?= gov_ops_h((string)($newest['lessor_id'] ?? '')) ?> / <?= gov_ops_h((string)($newest['driver_id'] ?? '')) ?> / <?= gov_ops_h((string)($newest['vehicle_id'] ?? '')) ?> / <?= gov_ops_h((string)($newest['starting_point_id'] ?? '')) ?></strong></li>
                        <li><span>Created / updated</span><strong><?= gov_ops_h((string)($newest['created_at'] ?? '')) ?> / <?= gov_ops_h((string)($newest['updated_at'] ?? '')) ?></strong></li>
                    </ul>
                    <?php if (!empty($newest['last_error'])): ?>
                        <h3>Newest last_error</h3>
                        <pre><?= gov_ops_h((string)$newest['last_error']) ?></pre>
                    <?php endif; ?>
                <?php endif; ?>
            </article>

            <article class="ops-card">
                <h2>Status Distribution</h2>
                <?php if (empty($data['status_counts'])): ?>
                    <p>No status counts available.</p>
                <?php else: ?>
                    <ul class="status-list">
                        <?php foreach ($data['status_counts'] as $item): ?>
                            <?php $status = (string)($item['status'] ?? 'unknown'); ?>
                            <li><span><?= gov_ops_badge($status, gov_v3_qf_status_badge_type($status)) ?></span><strong><?= gov_ops_h((string)($item['count'] ?? 0)) ?></strong></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <div class="ops-actions">
                    <a class="btn green" href="/ops/pre-ride-email-v3-monitor.php">Compact Monitor</a>
                    <a class="btn blue" href="/ops/pre-ride-email-v3-fast-pipeline-pulse.php">Pulse Monitor</a>
                    <a class="btn slate" href="/ops/pre-ride-email-v3-storage-check.php">Storage Check</a>
                </div>
            </article>
        </section>

        <section class="ops-card">
            <h2>Latest V3 Queue Rows</h2>
            <?php if (empty($data['rows'])): ?>
                <p>No rows to display.</p>
            <?php else: ?>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Status</th>
                                <th>Pickup</th>
                                <th>Min</th>
                                <th>Customer</th>
                                <th>Driver</th>
                                <th>Vehicle</th>
                                <th>Lessor</th>
                                <th>Driver ID</th>
                                <th>Vehicle ID</th>
                                <th>Start Point</th>
                                <th>Created / Updated</th>
                                <th>Last Error</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($data['rows'] as $row): ?>
                                <?php
                                $status = (string)($row['queue_status'] ?? 'unknown');
                                $mins = array_key_exists('minutes_until_pickup', $row) ? $row['minutes_until_pickup'] : null;
                                $minsInt = is_int($mins) ? $mins : null;
                                ?>
                                <tr class="status-<?= gov_ops_h($status) ?>">
                                    <td><strong>#<?= gov_ops_h((string)($row['id'] ?? '')) ?></strong></td>
                                    <td><?= gov_ops_badge($status, gov_v3_qf_status_badge_type($status)) ?></td>
                                    <td><?= gov_ops_h((string)($row['pickup_datetime'] ?? '')) ?></td>
                                    <td><?= gov_ops_badge($minsInt === null ? 'n/a' : (string)$minsInt, gov_v3_qf_minutes_badge_type($minsInt)) ?></td>
                                    <td><?= gov_ops_h((string)($row['customer_name'] ?? '')) ?></td>
                                    <td><?= gov_ops_h((string)($row['driver_name'] ?? '')) ?></td>
                                    <td><?= gov_ops_h((string)($row['vehicle_plate'] ?? '')) ?></td>
                                    <td><?= gov_ops_h((string)($row['lessor_id'] ?? '')) ?></td>
                                    <td><?= gov_ops_h((string)($row['driver_id'] ?? '')) ?></td>
                                    <td><?= gov_ops_h((string)($row['vehicle_id'] ?? '')) ?></td>
                                    <td><?= gov_ops_h((string)($row['starting_point_id'] ?? '')) ?></td>
                                    <td class="mono"><?= gov_ops_h((string)($row['created_at'] ?? '')) ?><br><?= gov_ops_h((string)($row['updated_at'] ?? '')) ?></td>
                                    <td class="mono error-preview"><?= gov_ops_h(gov_v3_qf_text_preview((string)($row['last_error'] ?? ''), 190)) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </main>
</div>
</body>
</html>
