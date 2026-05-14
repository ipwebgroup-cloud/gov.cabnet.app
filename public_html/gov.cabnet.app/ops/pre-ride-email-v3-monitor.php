<?php
/**
 * gov.cabnet.app — V3 Compact Monitor
 *
 * Fast read-only operator monitor for V3 pre-ride email queue and pulse health.
 * Does not call Bolt, EDXEIX, AADE, Gmail, or production submission tables.
 * Does not modify database rows or files.
 */

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Robots-Tag: noindex, nofollow', true);

require_once __DIR__ . '/_ops-nav.php';

function gov_v3_mon_db(): array
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

function gov_v3_mon_ident(string $name): string
{
    if (!preg_match('/^[A-Za-z0-9_]+$/', $name)) {
        throw new InvalidArgumentException('Invalid SQL identifier.');
    }
    return '`' . $name . '`';
}

function gov_v3_mon_table_exists(mysqli $mysqli, string $table): bool
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

function gov_v3_mon_column_exists(mysqli $mysqli, string $table, string $column): bool
{
    try {
        $sql = 'SELECT COUNT(*) AS c FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?';
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param('ss', $table, $column);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        return (int)($row['c'] ?? 0) > 0;
    } catch (Throwable $e) {
        return false;
    }
}

function gov_v3_mon_count(mysqli $mysqli, string $table, string $where): int
{
    try {
        $sql = 'SELECT COUNT(*) AS c FROM ' . gov_v3_mon_ident($table) . ' WHERE ' . $where;
        $result = $mysqli->query($sql);
        $row = $result ? $result->fetch_assoc() : null;
        return (int)($row['c'] ?? 0);
    } catch (Throwable $e) {
        return 0;
    }
}

function gov_v3_mon_owner_group(string $path): string
{
    $owner = @fileowner($path);
    $group = @filegroup($path);
    $ownerName = is_int($owner) ? (string)$owner : 'n/a';
    $groupName = is_int($group) ? (string)$group : 'n/a';
    if (function_exists('posix_getpwuid') && is_int($owner)) {
        $pw = @posix_getpwuid($owner);
        if (is_array($pw) && isset($pw['name'])) {
            $ownerName = (string)$pw['name'];
        }
    }
    if (function_exists('posix_getgrgid') && is_int($group)) {
        $gr = @posix_getgrgid($group);
        if (is_array($gr) && isset($gr['name'])) {
            $groupName = (string)$gr['name'];
        }
    }
    return $ownerName . ':' . $groupName;
}

function gov_v3_mon_perms(string $path): string
{
    $perms = @fileperms($path);
    return $perms === false ? 'n/a' : substr(sprintf('%o', $perms), -4);
}

function gov_v3_mon_minutes_until(?string $datetime): ?int
{
    if (!$datetime) {
        return null;
    }
    try {
        $ts = strtotime($datetime);
        if ($ts === false) {
            return null;
        }
        return (int)floor(($ts - time()) / 60);
    } catch (Throwable $e) {
        return null;
    }
}

function gov_v3_mon_status_badge_type(string $status): string
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
    return 'neutral';
}

function gov_v3_mon_live_config(): array
{
    $file = dirname(__DIR__, 3) . '/gov.cabnet.app_config/pre_ride_email_v3_live_submit.php';
    $state = ['exists' => is_file($file), 'loaded' => false, 'enabled' => false, 'mode' => 'missing', 'adapter' => 'missing', 'hard' => false, 'ok' => false, 'error' => null];
    if (!is_file($file)) {
        return $state;
    }
    try {
        $config = require $file;
        if (!is_array($config)) {
            $state['error'] = 'Config did not return an array.';
            return $state;
        }
        $state['loaded'] = true;
        $state['enabled'] = (bool)($config['enabled'] ?? false);
        $state['mode'] = (string)($config['mode'] ?? 'unknown');
        $state['adapter'] = (string)($config['adapter'] ?? 'unknown');
        $state['hard'] = (bool)($config['hard_enable_live_submit'] ?? false);
        $state['ok'] = $state['enabled'] && $state['hard'] && $state['mode'] !== 'disabled' && $state['adapter'] !== 'disabled';
    } catch (Throwable $e) {
        $state['error'] = $e->getMessage();
    }
    return $state;
}

function gov_v3_mon_log_summary(): array
{
    $logFile = dirname(__DIR__, 3) . '/gov.cabnet.app_app/logs/pre_ride_email_v3_fast_pipeline_pulse.log';
    $summary = [
        'file' => $logFile,
        'exists' => is_file($logFile),
        'readable' => is_readable($logFile),
        'last_start' => 'n/a',
        'last_summary' => 'n/a',
        'last_finish' => 'n/a',
        'last_error' => '',
        'freshness_seconds' => null,
        'ok' => false,
    ];
    if (!$summary['exists'] || !$summary['readable']) {
        return $summary;
    }

    $mtime = @filemtime($logFile);
    if ($mtime !== false) {
        $summary['freshness_seconds'] = time() - $mtime;
    }

    $lines = @file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!is_array($lines)) {
        return $summary;
    }

    $tail = array_slice($lines, -250);
    foreach ($tail as $line) {
        if (strpos($line, 'V3 fast pipeline pulse cron start') !== false) {
            $summary['last_start'] = $line;
        }
        if (strpos($line, 'Pulse summary:') !== false) {
            $summary['last_summary'] = $line;
        }
        if (strpos($line, 'finish exit_code=') !== false || strpos($line, 'Pulse finish exit_code=') !== false) {
            $summary['last_finish'] = $line;
        }
        if (strpos($line, 'ERROR:') !== false) {
            $summary['last_error'] = $line;
        }
    }

    $freshEnough = is_int($summary['freshness_seconds']) ? $summary['freshness_seconds'] <= 180 : false;
    $summary['ok'] = $freshEnough && strpos((string)$summary['last_summary'], 'failed=0') !== false && strpos((string)$summary['last_finish'], 'exit_code=0') !== false;
    return $summary;
}

function gov_v3_mon_storage_summary(): array
{
    $lockFile = dirname(__DIR__, 3) . '/gov.cabnet.app_app/storage/locks/pre_ride_email_v3_fast_pipeline_pulse.lock';
    $exists = is_file($lockFile);
    return [
        'lock_file' => $lockFile,
        'exists' => $exists,
        'readable' => $exists && is_readable($lockFile),
        'writable' => $exists && is_writable($lockFile),
        'perms' => $exists ? gov_v3_mon_perms($lockFile) : 'n/a',
        'owner_group' => $exists ? gov_v3_mon_owner_group($lockFile) : 'n/a',
        'ok' => $exists && is_readable($lockFile) && is_writable($lockFile) && gov_v3_mon_owner_group($lockFile) === 'cabnet:cabnet',
    ];
}

function gov_v3_mon_data(): array
{
    $data = [
        'generated_at' => date('Y-m-d H:i:s T'),
        'db' => ['ok' => false, 'error' => null],
        'queue_table_exists' => false,
        'metrics' => ['total' => 0, 'active' => 0, 'future_active' => 0, 'dry_ready' => 0, 'live_ready' => 0, 'blocked' => 0, 'submitted' => 0],
        'latest' => null,
        'warnings' => [],
        'log' => gov_v3_mon_log_summary(),
        'storage' => gov_v3_mon_storage_summary(),
        'gate' => gov_v3_mon_live_config(),
    ];

    $db = gov_v3_mon_db();
    $data['db']['ok'] = (bool)($db['ok'] ?? false);
    $data['db']['error'] = $db['error'] ?? null;
    if (!$data['db']['ok'] || empty($db['connection']) || !($db['connection'] instanceof mysqli)) {
        $data['warnings'][] = 'Database metrics unavailable: ' . (string)($data['db']['error'] ?? 'unknown error');
        return $data;
    }

    $mysqli = $db['connection'];
    $table = 'pre_ride_email_v3_queue';
    $data['queue_table_exists'] = gov_v3_mon_table_exists($mysqli, $table);
    if (!$data['queue_table_exists']) {
        $data['warnings'][] = 'V3 queue table not found.';
        return $data;
    }

    $columns = ['id','queue_status','customer_name','pickup_datetime','driver_name','vehicle_plate','lessor_id','driver_id','vehicle_id','starting_point_id','last_error','created_at','updated_at'];
    $available = [];
    foreach ($columns as $column) {
        if (gov_v3_mon_column_exists($mysqli, $table, $column)) {
            $available[] = gov_v3_mon_ident($column);
        }
    }

    $hasStatus = gov_v3_mon_column_exists($mysqli, $table, 'queue_status');
    $hasPickup = gov_v3_mon_column_exists($mysqli, $table, 'pickup_datetime');
    $data['metrics']['total'] = gov_v3_mon_count($mysqli, $table, '1=1');
    if ($hasStatus) {
        $terminal = "('blocked','submitted','cancelled','expired')";
        $data['metrics']['active'] = gov_v3_mon_count($mysqli, $table, 'queue_status NOT IN ' . $terminal);
        $data['metrics']['dry_ready'] = gov_v3_mon_count($mysqli, $table, "queue_status = 'submit_dry_run_ready'");
        $data['metrics']['live_ready'] = gov_v3_mon_count($mysqli, $table, "queue_status = 'live_submit_ready'");
        $data['metrics']['blocked'] = gov_v3_mon_count($mysqli, $table, "queue_status = 'blocked'");
        $data['metrics']['submitted'] = gov_v3_mon_count($mysqli, $table, "queue_status = 'submitted'");
        if ($hasPickup) {
            $data['metrics']['future_active'] = gov_v3_mon_count($mysqli, $table, 'queue_status NOT IN ' . $terminal . ' AND pickup_datetime > NOW()');
        }
    }

    if ($available !== []) {
        $orderBy = in_array('`id`', $available, true) ? '`id` DESC' : '1 DESC';
        try {
            $sql = 'SELECT ' . implode(', ', $available) . ' FROM ' . gov_v3_mon_ident($table) . ' ORDER BY ' . $orderBy . ' LIMIT 1';
            $result = $mysqli->query($sql);
            if ($result !== false) {
                $row = $result->fetch_assoc();
                if (is_array($row)) {
                    $row['minutes_until_pickup'] = gov_v3_mon_minutes_until($row['pickup_datetime'] ?? null);
                    $data['latest'] = $row;
                }
            }
        } catch (Throwable $e) {
            $data['warnings'][] = 'Latest row could not be loaded: ' . $e->getMessage();
        }
    }

    return $data;
}

$data = gov_v3_mon_data();
$m = $data['metrics'];
$latest = $data['latest'];
$latestStatus = is_array($latest) ? (string)($latest['queue_status'] ?? 'unknown') : 'none';
$latestBadgeType = gov_v3_mon_status_badge_type($latestStatus);
$pulseOk = !empty($data['log']['ok']);
$storageOk = !empty($data['storage']['ok']);
$gate = $data['gate'];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>V3 Compact Monitor | gov.cabnet.app Ops</title>
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
        .ops-main { padding:22px 24px 60px; max-width:1500px; width:100%; }
        .ops-page-title { display:flex; align-items:flex-start; justify-content:space-between; gap:18px; margin-bottom:8px; }
        h1 { margin:0; color:var(--ink-strong); font-size:30px; line-height:1.15; letter-spacing:-.02em; }
        .ops-kicker { color:#5662b1; text-transform:uppercase; letter-spacing:.08em; font-size:12px; font-weight:800; margin-bottom:5px; }
        .ops-page-subtitle { margin:6px 0 0; color:var(--muted); line-height:1.45; max-width:980px; }
        .ops-page-tabs { display:flex; flex-wrap:wrap; gap:12px; margin:18px 0; }
        .ops-page-tabs a { text-decoration:none; color:#4d5e7f; padding:12px 16px; border-radius:22px; background:#fff; border:1px solid var(--line); font-weight:800; font-size:13px; }
        .ops-page-tabs a:hover, .ops-page-tabs a.active { background:#5662b1; color:#fff; }
        .ops-badge { display:inline-block; padding:5px 9px; border-radius:999px; font-size:11px; font-weight:900; margin:2px; }
        .ops-badge-good { background:var(--green-soft); color:#087a33; } .ops-badge-warn { background:var(--amber-soft); color:#a55700; } .ops-badge-bad { background:var(--red-soft); color:#a11c15; } .ops-badge-neutral { background:#eef2f7; color:#40516d; } .ops-badge-info { background:var(--blue-soft); color:#214aab; } .ops-badge-dark { background:#29345a; color:#fff; }
        .ops-alert { border:1px solid var(--line); background:#fff; border-radius:8px; padding:13px 16px; margin:16px 0; box-shadow:var(--shadow); }
        .ops-alert.safe { border-left:4px solid var(--green); background:#f3fff5; } .ops-alert.warn { border-left:4px solid var(--amber); background:#fffaf1; } .ops-alert.danger { border-left:4px solid var(--red); background:#fff6f5; }
        .ops-grid { display:grid; gap:14px; } .ops-grid.metrics { grid-template-columns:repeat(5,minmax(0,1fr)); margin:16px 0; } .ops-grid.two { grid-template-columns:repeat(2,minmax(0,1fr)); margin:16px 0; } .ops-grid.three { grid-template-columns:repeat(3,minmax(0,1fr)); margin:16px 0; }
        .ops-card { background:#fff; border:1px solid var(--line); border-radius:8px; padding:16px; box-shadow:var(--shadow); }
        .ops-card h2, .ops-card h3 { color:var(--ink-strong); margin:0 0 10px; } .ops-card h2 { font-size:20px; } .ops-card h3 { font-size:17px; }
        .ops-card p { color:#31486d; line-height:1.45; margin:7px 0; }
        .metric-card { min-height:118px; border-left:4px solid var(--brand); } .metric-card.good { border-left-color:var(--green); } .metric-card.bad { border-left-color:var(--red); } .metric-card.warn { border-left-color:var(--amber); }
        .metric-card strong { display:block; font-size:32px; color:var(--ink-strong); line-height:1; } .metric-card span { display:block; color:#344a70; margin-top:8px; font-weight:700; } .metric-card small { display:block; color:#5c6b84; margin-top:7px; line-height:1.35; }
        .status-list { list-style:none; margin:0; padding:0; } .status-list li { display:flex; justify-content:space-between; gap:16px; align-items:flex-start; padding:11px 0; border-bottom:1px solid var(--line); }
        .status-list li:last-child { border-bottom:0; } .status-list span { color:#40577b; } .status-list strong { color:#071f4f; text-align:right; }
        .table-wrap { overflow:auto; border:1px solid var(--line); border-radius:8px; } table { width:100%; border-collapse:collapse; min-width:980px; } th,td { padding:10px 12px; border-bottom:1px solid var(--line); text-align:left; vertical-align:top; } th { color:#445078; text-transform:uppercase; letter-spacing:.05em; font-size:11px; background:#f6f8fc; }
        .mono { font-family:Consolas, Monaco, monospace; font-size:12px; overflow-wrap:anywhere; } pre { white-space:pre-wrap; word-break:break-word; margin:8px 0 0; background:#f6f8fc; border:1px solid var(--line); padding:10px; border-radius:6px; color:#15264f; }
        .btn { display:inline-flex; align-items:center; justify-content:center; text-decoration:none; border:0; border-radius:4px; padding:10px 13px; color:#fff; font-weight:800; font-size:13px; background:var(--brand); box-shadow:var(--shadow); }
        .btn.green { background:var(--green); } .btn.amber { background:var(--amber); } .btn.blue { background:var(--blue); } .btn.slate { background:#687386; }
        .ops-actions { display:flex; flex-wrap:wrap; gap:9px; margin-top:14px; }
        @media (max-width:1200px) { .ops-grid.metrics { grid-template-columns:repeat(2,1fr); } .ops-grid.two,.ops-grid.three { grid-template-columns:1fr; } .ops-shell-layout { grid-template-columns:1fr; } .ops-shell-sidebar { position:static; } }
        @media (max-width:760px) { .ops-shell-topbar { padding:10px 14px; flex-wrap:wrap; } .ops-shell-brand { min-width:0; } .ops-shell-user { display:none; } .ops-main { padding:16px 14px 44px; } .ops-page-title { display:block; } .ops-grid.metrics { grid-template-columns:1fr; } }
    </style>
</head>
<body>
<?php gov_ops_render_topbar('pre_ride'); ?>
<div class="ops-shell-layout">
    <?php gov_ops_render_sidebar('v3_monitor'); ?>
    <main class="ops-main">
        <div class="ops-page-title">
            <div>
                <div class="ops-kicker">V3 Operator Monitor</div>
                <h1>V3 Compact Monitor</h1>
                <p class="ops-page-subtitle">Fast read-only view for queue state, newest V3 row, pulse cron health, storage lock state, and closed live-submit gate. This page does not make operational decisions.</p>
            </div>
            <div>
                <?= gov_ops_badge('Production', 'dark') ?>
                <?= gov_ops_badge('V3 only', 'info') ?>
                <?= gov_ops_badge('V0 untouched', 'good') ?>
                <?= gov_ops_badge('Live submit disabled', 'bad') ?>
            </div>
        </div>

        <?php gov_ops_render_page_tabs([
            ['key' => 'monitor', 'label' => 'Compact Monitor', 'href' => '/ops/pre-ride-email-v3-monitor.php'],
            ['key' => 'dashboard', 'label' => 'V3 Dashboard', 'href' => '/ops/pre-ride-email-v3-dashboard.php'],
            ['key' => 'queue_watch', 'label' => 'Queue Watch', 'href' => '/ops/pre-ride-email-v3-queue-watch.php'],
            ['key' => 'pulse', 'label' => 'Pulse Monitor', 'href' => '/ops/pre-ride-email-v3-fast-pipeline-pulse.php'],
            ['key' => 'readiness', 'label' => 'Automation Readiness', 'href' => '/ops/pre-ride-email-v3-automation-readiness.php'],
            ['key' => 'storage', 'label' => 'Storage Check', 'href' => '/ops/pre-ride-email-v3-storage-check.php'],
        ], 'monitor'); ?>

        <section class="ops-alert safe">
            <strong>SAFE READ-ONLY V3 VIEW.</strong>
            No Bolt call, no EDXEIX call, no AADE call, no DB writes, no V0 production helper changes.
        </section>

        <?php foreach ($data['warnings'] as $warning): ?>
            <section class="ops-alert warn"><strong>Warning:</strong> <?= gov_ops_h((string)$warning) ?></section>
        <?php endforeach; ?>

        <section class="ops-grid metrics">
            <article class="ops-card metric-card <?= $pulseOk ? 'good' : 'bad' ?>">
                <strong><?= $pulseOk ? 'OK' : 'CHECK' ?></strong>
                <span>Pulse cron</span>
                <small><?= $pulseOk ? 'Recent pulse finished with exit_code=0.' : 'Inspect pulse log / cron health.' ?></small>
            </article>
            <article class="ops-card metric-card <?= $storageOk ? 'good' : 'bad' ?>">
                <strong><?= $storageOk ? 'OK' : 'CHECK' ?></strong>
                <span>Pulse lock</span>
                <small><?= gov_ops_h((string)$data['storage']['owner_group']) ?> / <?= gov_ops_h((string)$data['storage']['perms']) ?></small>
            </article>
            <article class="ops-card metric-card <?= ((int)$m['future_active'] > 0) ? 'good' : '' ?>">
                <strong><?= gov_ops_h((string)$m['future_active']) ?></strong>
                <span>Future active</span>
                <small>New real tests should appear here before pickup.</small>
            </article>
            <article class="ops-card metric-card <?= ((int)$m['dry_ready'] > 0) ? 'good' : '' ?>">
                <strong><?= gov_ops_h((string)$m['dry_ready']) ?></strong>
                <span>Dry-run ready</span>
                <small>Acceptable proof state before live readiness.</small>
            </article>
            <article class="ops-card metric-card <?= ((int)$m['blocked'] > 0) ? 'bad' : '' ?>">
                <strong><?= gov_ops_h((string)$m['blocked']) ?></strong>
                <span>Blocked</span>
                <small>Safe state. Use last_error for diagnosis.</small>
            </article>
        </section>

        <section class="ops-grid two">
            <article class="ops-card">
                <h2>Newest V3 Queue Row</h2>
                <?php if (!is_array($latest)): ?>
                    <p>No V3 queue rows found.</p>
                <?php else: ?>
                    <ul class="status-list">
                        <li><span>ID / status</span><strong>#<?= gov_ops_h((string)($latest['id'] ?? '')) ?> <?= gov_ops_badge($latestStatus, $latestBadgeType) ?></strong></li>
                        <li><span>Customer</span><strong><?= gov_ops_h((string)($latest['customer_name'] ?? '')) ?></strong></li>
                        <li><span>Pickup</span><strong><?= gov_ops_h((string)($latest['pickup_datetime'] ?? '')) ?></strong></li>
                        <li><span>Minutes until pickup</span><strong><?= $latest['minutes_until_pickup'] === null ? 'n/a' : gov_ops_h((string)$latest['minutes_until_pickup']) ?></strong></li>
                        <li><span>Driver / vehicle</span><strong><?= gov_ops_h((string)($latest['driver_name'] ?? '')) ?> / <?= gov_ops_h((string)($latest['vehicle_plate'] ?? '')) ?></strong></li>
                        <li><span>Lessor / driver / vehicle / start</span><strong><?= gov_ops_h((string)($latest['lessor_id'] ?? '')) ?> / <?= gov_ops_h((string)($latest['driver_id'] ?? '')) ?> / <?= gov_ops_h((string)($latest['vehicle_id'] ?? '')) ?> / <?= gov_ops_h((string)($latest['starting_point_id'] ?? '')) ?></strong></li>
                        <li><span>Created / updated</span><strong><?= gov_ops_h((string)($latest['created_at'] ?? '')) ?> / <?= gov_ops_h((string)($latest['updated_at'] ?? '')) ?></strong></li>
                    </ul>
                    <?php if (!empty($latest['last_error'])): ?>
                        <h3>Last error</h3>
                        <pre><?= gov_ops_h((string)$latest['last_error']) ?></pre>
                    <?php endif; ?>
                <?php endif; ?>
            </article>

            <article class="ops-card">
                <h2>Pulse / Gate Summary</h2>
                <ul class="status-list">
                    <li><span>Log freshness seconds</span><strong><?= $data['log']['freshness_seconds'] === null ? 'n/a' : gov_ops_h((string)$data['log']['freshness_seconds']) ?></strong></li>
                    <li><span>Last pulse summary</span><strong class="mono"><?= gov_ops_h((string)$data['log']['last_summary']) ?></strong></li>
                    <li><span>Last finish</span><strong class="mono"><?= gov_ops_h((string)$data['log']['last_finish']) ?></strong></li>
                    <li><span>Last error in tail</span><strong class="mono"><?= gov_ops_h((string)($data['log']['last_error'] ?: 'none')) ?></strong></li>
                    <li><span>Gate loaded</span><strong><?= !empty($gate['loaded']) ? gov_ops_badge('yes', 'good') : gov_ops_badge('no', 'bad') ?></strong></li>
                    <li><span>Gate enabled</span><strong><?= !empty($gate['enabled']) ? gov_ops_badge('yes', 'bad') : gov_ops_badge('no', 'good') ?></strong></li>
                    <li><span>Mode / adapter</span><strong><?= gov_ops_badge((string)$gate['mode'], ((string)$gate['mode'] === 'disabled') ? 'good' : 'warn') ?> <?= gov_ops_badge((string)$gate['adapter'], ((string)$gate['adapter'] === 'disabled') ? 'good' : 'warn') ?></strong></li>
                    <li><span>OK for future live submit</span><strong><?= !empty($gate['ok']) ? gov_ops_badge('yes', 'bad') : gov_ops_badge('no', 'good') ?></strong></li>
                </ul>
            </article>
        </section>

        <section class="ops-grid three">
            <article class="ops-card">
                <h2>Queue Metrics</h2>
                <ul class="status-list">
                    <li><span>Total rows</span><strong><?= gov_ops_h((string)$m['total']) ?></strong></li>
                    <li><span>Active rows</span><strong><?= gov_ops_h((string)$m['active']) ?></strong></li>
                    <li><span>Future active rows</span><strong><?= gov_ops_h((string)$m['future_active']) ?></strong></li>
                    <li><span>Dry-run ready</span><strong><?= gov_ops_h((string)$m['dry_ready']) ?></strong></li>
                    <li><span>Live-submit ready</span><strong><?= gov_ops_h((string)$m['live_ready']) ?></strong></li>
                    <li><span>Submitted</span><strong><?= gov_ops_h((string)$m['submitted']) ?></strong></li>
                </ul>
            </article>

            <article class="ops-card">
                <h2>Fast Links</h2>
                <div class="ops-actions">
                    <a class="btn green" href="/ops/pre-ride-email-v3-queue-watch.php">Queue Watch</a>
                    <a class="btn blue" href="/ops/pre-ride-email-v3-fast-pipeline-pulse.php">Pulse Monitor</a>
                    <a class="btn slate" href="/ops/pre-ride-email-v3-automation-readiness.php">Readiness</a>
                    <a class="btn amber" href="/ops/pre-ride-email-v3-live-submit-gate.php">Locked Gate</a>
                    <a class="btn" href="/ops/pre-ride-email-v3-storage-check.php">Storage Check</a>
                </div>
            </article>

            <article class="ops-card">
                <h2>CLI Check</h2>
                <p>For manual V3 verification, run as <strong>cabnet</strong>, not root.</p>
                <pre>su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_storage_check.php"</pre>
                <pre>tail -n 120 /home/cabnet/gov.cabnet.app_app/logs/pre_ride_email_v3_fast_pipeline_pulse.log | egrep "cron start|ERROR|Pulse summary|finish exit_code" || true</pre>
            </article>
        </section>
    </main>
</div>
</body>
</html>
