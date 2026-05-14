<?php
/**
 * gov.cabnet.app — V3 Readiness Focus
 *
 * Read-only readiness overview for V3 pre-ride automation.
 * Does not call Bolt, EDXEIX, AADE, Gmail, or production submission tables.
 * Does not modify database rows or files.
 * V0 laptop/manual production helper remains untouched.
 */

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Robots-Tag: noindex, nofollow', true);

require_once __DIR__ . '/_ops-nav.php';

function gov_v3_rf_db(): array
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

function gov_v3_rf_ident(string $name): string
{
    if (!preg_match('/^[A-Za-z0-9_]+$/', $name)) {
        throw new InvalidArgumentException('Invalid SQL identifier.');
    }
    return '`' . $name . '`';
}

function gov_v3_rf_table_exists(mysqli $mysqli, string $table): bool
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

function gov_v3_rf_column_exists(mysqli $mysqli, string $table, string $column): bool
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

function gov_v3_rf_count(mysqli $mysqli, string $table, string $where = '1=1'): int
{
    try {
        $sql = 'SELECT COUNT(*) AS c FROM ' . gov_v3_rf_ident($table) . ' WHERE ' . $where;
        $result = $mysqli->query($sql);
        $row = $result ? $result->fetch_assoc() : null;
        return (int)($row['c'] ?? 0);
    } catch (Throwable $e) {
        return 0;
    }
}

function gov_v3_rf_scalar_prepared(mysqli $mysqli, string $sql, array $params = [], string $types = ''): int
{
    try {
        $stmt = $mysqli->prepare($sql);
        if ($params) {
            if ($types === '') {
                foreach ($params as $param) {
                    $types .= is_int($param) ? 'i' : (is_float($param) ? 'd' : 's');
                }
            }
            $bind = [$types];
            foreach ($params as $idx => $param) {
                $bind[] = &$params[$idx];
            }
            call_user_func_array([$stmt, 'bind_param'], $bind);
        }
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        return (int)($row['c'] ?? 0);
    } catch (Throwable $e) {
        return 0;
    }
}

function gov_v3_rf_fetch_all(mysqli $mysqli, string $sql, int $limit = 10): array
{
    try {
        $limit = max(1, min(50, $limit));
        $sql = str_replace('__LIMIT__', (string)$limit, $sql);
        $result = $mysqli->query($sql);
        return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    } catch (Throwable $e) {
        return [];
    }
}

function gov_v3_rf_minutes_until(?string $datetime): ?int
{
    if (!$datetime) {
        return null;
    }
    $ts = strtotime($datetime);
    if ($ts === false) {
        return null;
    }
    return (int)floor(($ts - time()) / 60);
}

function gov_v3_rf_owner_group(string $path): string
{
    if (!file_exists($path)) {
        return 'missing';
    }
    $owner = @fileowner($path);
    $group = @filegroup($path);
    $ownerName = is_int($owner) ? (string)$owner : 'unknown';
    $groupName = is_int($group) ? (string)$group : 'unknown';
    if (function_exists('posix_getpwuid') && is_int($owner)) {
        $info = @posix_getpwuid($owner);
        if (is_array($info) && isset($info['name'])) {
            $ownerName = (string)$info['name'];
        }
    }
    if (function_exists('posix_getgrgid') && is_int($group)) {
        $info = @posix_getgrgid($group);
        if (is_array($info) && isset($info['name'])) {
            $groupName = (string)$info['name'];
        }
    }
    return $ownerName . ':' . $groupName;
}

function gov_v3_rf_perms(string $path): string
{
    if (!file_exists($path)) {
        return 'missing';
    }
    $perms = @fileperms($path);
    return is_int($perms) ? substr(sprintf('%o', $perms), -4) : 'unknown';
}

function gov_v3_rf_tail_lines(string $path, int $maxLines = 220): array
{
    if (!is_file($path) || !is_readable($path)) {
        return [];
    }
    try {
        $file = new SplFileObject($path, 'r');
        $file->seek(PHP_INT_MAX);
        $lastLine = $file->key();
        $start = max(0, $lastLine - $maxLines + 1);
        $lines = [];
        for ($i = $start; $i <= $lastLine; $i++) {
            $file->seek($i);
            $line = rtrim((string)$file->current(), "\r\n");
            if ($line !== '') {
                $lines[] = $line;
            }
        }
        return $lines;
    } catch (Throwable $e) {
        $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        return is_array($lines) ? array_slice($lines, -$maxLines) : [];
    }
}

function gov_v3_rf_pulse_state(): array
{
    $appRoot = dirname(__DIR__, 3) . '/gov.cabnet.app_app';
    $logPath = $appRoot . '/logs/pre_ride_email_v3_fast_pipeline_pulse.log';
    $lockPath = $appRoot . '/storage/locks/pre_ride_email_v3_fast_pipeline_pulse.lock';
    $lines = gov_v3_rf_tail_lines($logPath, 260);
    $lastStart = '';
    $lastSummary = '';
    $lastFinish = '';
    $lastError = '';
    $lastExitCode = null;
    foreach ($lines as $line) {
        if (strpos($line, 'V3 fast pipeline pulse cron start') !== false) {
            $lastStart = $line;
        }
        if (strpos($line, 'Pulse summary:') !== false) {
            $lastSummary = $line;
        }
        if (strpos($line, 'finish exit_code=') !== false || strpos($line, 'Pulse finish exit_code=') !== false) {
            $lastFinish = $line;
            if (preg_match('/exit_code=(\d+)/', $line, $m)) {
                $lastExitCode = (int)$m[1];
            }
        }
        if (strpos($line, 'ERROR:') !== false) {
            $lastError = $line;
        }
    }
    $mtime = is_file($logPath) ? @filemtime($logPath) : false;
    $freshness = is_int($mtime) ? (time() - $mtime) : null;
    $lockOwner = gov_v3_rf_owner_group($lockPath);
    $lockPerms = gov_v3_rf_perms($lockPath);
    $lockOk = is_file($lockPath) && is_readable($lockPath) && is_writable($lockPath) && $lockOwner === 'cabnet:cabnet';
    $ok = $lockOk && is_file($logPath) && is_readable($logPath) && $lastExitCode === 0 && is_int($freshness) && $freshness <= 180 && strpos($lastSummary, 'failed=0') !== false;
    return [
        'ok' => $ok,
        'log_path' => $logPath,
        'lock_path' => $lockPath,
        'freshness_seconds' => $freshness,
        'last_start' => $lastStart ?: 'n/a',
        'last_summary' => $lastSummary ?: 'n/a',
        'last_finish' => $lastFinish ?: 'n/a',
        'last_error' => $lastError ?: 'none in recent tail',
        'last_exit_code' => $lastExitCode,
        'lock_ok' => $lockOk,
        'lock_owner' => $lockOwner,
        'lock_perms' => $lockPerms,
    ];
}

function gov_v3_rf_live_config(): array
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

function gov_v3_rf_status_badge_type(string $status): string
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

$dbState = gov_v3_rf_db();
$mysqli = $dbState['connection'];
$pulse = gov_v3_rf_pulse_state();
$gate = gov_v3_rf_live_config();

$queueTable = false;
$queueMetrics = ['total' => 0, 'active' => 0, 'future_active' => 0, 'dry_run_ready' => 0, 'live_ready' => 0, 'blocked' => 0, 'submitted' => 0];
$newestRow = null;
$statusRows = [];
$errorRows = [];
$mappingChecks = [];

if ($mysqli instanceof mysqli) {
    $queueTable = gov_v3_rf_table_exists($mysqli, 'pre_ride_email_v3_queue');
    if ($queueTable) {
        $queueMetrics['total'] = gov_v3_rf_count($mysqli, 'pre_ride_email_v3_queue');
        $queueMetrics['active'] = gov_v3_rf_count($mysqli, 'pre_ride_email_v3_queue', "queue_status NOT IN ('blocked','submitted','cancelled','expired')");
        $queueMetrics['future_active'] = gov_v3_rf_count($mysqli, 'pre_ride_email_v3_queue', "queue_status NOT IN ('blocked','submitted','cancelled','expired') AND pickup_datetime > NOW()");
        $queueMetrics['dry_run_ready'] = gov_v3_rf_count($mysqli, 'pre_ride_email_v3_queue', "queue_status='submit_dry_run_ready'");
        $queueMetrics['live_ready'] = gov_v3_rf_count($mysqli, 'pre_ride_email_v3_queue', "queue_status='live_submit_ready'");
        $queueMetrics['blocked'] = gov_v3_rf_count($mysqli, 'pre_ride_email_v3_queue', "queue_status='blocked'");
        $queueMetrics['submitted'] = gov_v3_rf_count($mysqli, 'pre_ride_email_v3_queue', "queue_status='submitted'");

        $rows = gov_v3_rf_fetch_all($mysqli, "SELECT id, queue_status, customer_name, pickup_datetime, driver_name, vehicle_plate, lessor_id, driver_id, vehicle_id, starting_point_id, last_error, created_at, updated_at FROM pre_ride_email_v3_queue ORDER BY id DESC LIMIT __LIMIT__", 1);
        $newestRow = $rows[0] ?? null;

        $statusRows = gov_v3_rf_fetch_all($mysqli, "SELECT queue_status, COUNT(*) AS c FROM pre_ride_email_v3_queue GROUP BY queue_status ORDER BY c DESC, queue_status ASC LIMIT __LIMIT__", 20);
        $errorRows = gov_v3_rf_fetch_all($mysqli, "SELECT queue_status, LEFT(COALESCE(last_error,''), 220) AS last_error, COUNT(*) AS c, MAX(updated_at) AS latest_update FROM pre_ride_email_v3_queue WHERE COALESCE(last_error,'') <> '' GROUP BY queue_status, LEFT(COALESCE(last_error,''), 220) ORDER BY latest_update DESC LIMIT __LIMIT__", 8);
    }

    $startOptionsTable = gov_v3_rf_table_exists($mysqli, 'pre_ride_email_v3_starting_point_options');
    $mappingTable = gov_v3_rf_table_exists($mysqli, 'mapping_lessor_starting_points');

    $mappingChecks[] = [
        'label' => 'V3 verified starting-point options table',
        'status' => $startOptionsTable ? 'ok' : 'missing',
        'detail' => $startOptionsTable ? 'present' : 'table not found',
    ];
    if ($startOptionsTable) {
        $mappingChecks[] = [
            'label' => 'Verified options for lessor 2307',
            'status' => gov_v3_rf_scalar_prepared($mysqli, 'SELECT COUNT(*) AS c FROM pre_ride_email_v3_starting_point_options WHERE edxeix_lessor_id = ?', [2307], 'i') > 0 ? 'ok' : 'warn',
            'detail' => (string)gov_v3_rf_scalar_prepared($mysqli, 'SELECT COUNT(*) AS c FROM pre_ride_email_v3_starting_point_options WHERE edxeix_lessor_id = ?', [2307], 'i') . ' option(s)',
        ];
        $mappingChecks[] = [
            'label' => 'ΧΩΡΑ ΜΥΚΟΝΟΥ option 1455969',
            'status' => gov_v3_rf_scalar_prepared($mysqli, 'SELECT COUNT(*) AS c FROM pre_ride_email_v3_starting_point_options WHERE edxeix_lessor_id = ? AND edxeix_starting_point_id = ?', [2307, 1455969], 'ii') > 0 ? 'ok' : 'bad',
            'detail' => 'expected lessor 2307 / starting point 1455969',
        ];
    }
    $mappingChecks[] = [
        'label' => 'Lessor starting-point mapping table',
        'status' => $mappingTable ? 'ok' : 'missing',
        'detail' => $mappingTable ? 'present' : 'table not found',
    ];
    if ($mappingTable) {
        $mappingChecks[] = [
            'label' => 'Active chora_mykonou mapping',
            'status' => gov_v3_rf_scalar_prepared($mysqli, "SELECT COUNT(*) AS c FROM mapping_lessor_starting_points WHERE edxeix_lessor_id = ? AND edxeix_starting_point_id = ? AND is_active = 1", [2307, 1455969], 'ii') > 0 ? 'ok' : 'bad',
            'detail' => 'expected lessor 2307 → 1455969 active',
        ];
    }
}

$minutesNewest = is_array($newestRow) ? gov_v3_rf_minutes_until((string)($newestRow['pickup_datetime'] ?? '')) : null;
$manualHandoffReady = $dbState['ok'] && $queueTable && $pulse['ok'];
$futureLiveReady = false; // Intentionally remains false while gate/config are closed.
$overallBadge = $manualHandoffReady ? 'ready for V3 monitoring' : 'check V3 prerequisites';
$overallType = $manualHandoffReady ? 'good' : 'warn';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>V3 Readiness Focus | gov.cabnet.app</title>
    <style>
        :root { --bg:#eef2f7; --panel:#fff; --ink:#14234a; --ink-strong:#092159; --muted:#31466c; --line:#d5ddec; --sidebar:#30385f; --brand:#5662b1; --brand-dark:#405096; --green:#58b267; --green-soft:#e1f6e6; --amber:#d99529; --amber-soft:#fff0d6; --red:#cc392f; --red-soft:#ffe5e3; --blue:#386fd4; --blue-soft:#e8efff; --shadow:0 7px 18px rgba(31,45,77,.07); }
        * { box-sizing:border-box; } body { margin:0; background:var(--bg); color:var(--ink); font-family:Arial, Helvetica, sans-serif; font-size:14px; }
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
        .ops-shell-user strong { display:block; font-size:12px; line-height:1; letter-spacing:.03em; } .ops-shell-user em { display:block; font-style:normal; font-size:10px; color:#64748b; margin-top:2px; }
        .ops-shell-layout { display:grid; grid-template-columns:300px 1fr; min-height:calc(100vh - 68px); }
        .ops-shell-sidebar { background:var(--sidebar); color:#fff; padding:22px 16px 44px; box-shadow:inset -1px 0 0 rgba(0,0,0,.08); }
        .ops-operator-card { border:1px solid rgba(255,255,255,.18); background:rgba(255,255,255,.08); border-radius:8px; padding:13px; margin-bottom:22px; }
        .ops-operator-top { display:flex; align-items:center; gap:10px; margin-bottom:12px; } .ops-operator-avatar { width:42px; height:42px; background:#fff; color:#5964ad; border-radius:50%; display:grid; place-items:center; font-weight:900; }
        .ops-operator-card strong { display:block; } .ops-operator-card em { display:block; font-style:normal; color:#d9e1f2; font-size:12px; }
        .ops-operator-actions { display:flex; gap:7px; flex-wrap:wrap; } .ops-operator-actions a { color:#fff; text-decoration:none; font-size:11px; font-weight:700; padding:6px 8px; border:1px solid rgba(255,255,255,.22); border-radius:4px; background:rgba(0,0,0,.08); }
        .ops-side-section { margin-bottom:22px; } .ops-side-section h3 { color:#cdd7ef; text-transform:uppercase; letter-spacing:.06em; font-size:11px; font-weight:500; margin:0 0 8px 7px; }
        .ops-side-link { display:block; color:#fff; text-decoration:none; padding:11px 12px; border-radius:4px; margin:2px 0; font-weight:700; font-size:14px; } .ops-side-link:hover, .ops-side-link.active { background:#5662b1; }
        .ops-side-hint { margin:6px 7px 13px; color:#edf3ff; line-height:1.45; }
        .ops-main { padding:22px 24px 60px; max-width:1600px; width:100%; } .ops-page-title { display:flex; align-items:flex-start; justify-content:space-between; gap:18px; margin-bottom:8px; }
        h1 { margin:0; color:var(--ink-strong); font-size:30px; line-height:1.15; letter-spacing:-.02em; } .ops-kicker { color:#5662b1; text-transform:uppercase; letter-spacing:.08em; font-size:12px; font-weight:800; margin-bottom:5px; }
        .ops-page-subtitle { margin:6px 0 0; color:var(--muted); line-height:1.45; max-width:980px; }
        .ops-page-tabs { display:flex; flex-wrap:wrap; gap:12px; margin:18px 0; } .ops-page-tabs a { text-decoration:none; color:#4d5e7f; padding:12px 16px; border-radius:22px; background:#fff; border:1px solid var(--line); font-weight:800; font-size:13px; } .ops-page-tabs a:hover, .ops-page-tabs a.active { background:#5662b1; color:#fff; }
        .ops-badge { display:inline-block; padding:5px 9px; border-radius:999px; font-size:11px; font-weight:900; margin:2px; white-space:nowrap; } .ops-badge-good { background:var(--green-soft); color:#087a33; } .ops-badge-warn { background:var(--amber-soft); color:#a55700; } .ops-badge-bad { background:var(--red-soft); color:#a11c15; } .ops-badge-neutral { background:#eef2f7; color:#40516d; } .ops-badge-info { background:var(--blue-soft); color:#214aab; } .ops-badge-dark { background:#29345a; color:#fff; }
        .ops-alert { border:1px solid var(--line); background:#fff; border-radius:8px; padding:13px 16px; margin:16px 0; box-shadow:var(--shadow); } .ops-alert.safe { border-left:4px solid var(--green); background:#f3fff5; } .ops-alert.warn { border-left:4px solid var(--amber); background:#fffaf1; } .ops-alert.danger { border-left:4px solid var(--red); background:#fff6f5; }
        .ops-grid { display:grid; gap:14px; } .ops-grid.metrics { grid-template-columns:repeat(5,minmax(0,1fr)); margin:16px 0; } .ops-grid.two { grid-template-columns:1fr 1fr; margin:16px 0; }
        .ops-card { background:#fff; border:1px solid var(--line); border-radius:8px; padding:16px; box-shadow:var(--shadow); } .ops-card h2, .ops-card h3 { color:var(--ink-strong); margin:0 0 10px; } .ops-card h2 { font-size:20px; } .ops-card h3 { font-size:17px; } .ops-card p { color:#31486d; line-height:1.45; margin:7px 0; }
        .metric-card { min-height:108px; border-left:4px solid var(--brand); } .metric-card.good { border-left-color:var(--green); } .metric-card.bad { border-left-color:var(--red); } .metric-card.warn { border-left-color:var(--amber); }
        .metric-card strong { display:block; font-size:30px; color:var(--ink-strong); line-height:1; } .metric-card span { display:block; color:#344a70; margin-top:8px; font-weight:700; } .metric-card small { display:block; color:#5c6b84; margin-top:7px; line-height:1.35; }
        .status-list { list-style:none; margin:0; padding:0; } .status-list li { display:flex; justify-content:space-between; gap:16px; align-items:flex-start; padding:11px 0; border-bottom:1px solid var(--line); } .status-list li:last-child { border-bottom:0; } .status-list span { color:#40577b; } .status-list strong { color:#071f4f; text-align:right; }
        .table-wrap { overflow:auto; border:1px solid var(--line); border-radius:8px; } table { width:100%; border-collapse:collapse; min-width:900px; } th,td { padding:10px 12px; border-bottom:1px solid var(--line); text-align:left; vertical-align:top; } th { color:#445078; text-transform:uppercase; letter-spacing:.05em; font-size:11px; background:#f6f8fc; }
        .mono { font-family:Consolas, Monaco, monospace; font-size:12px; overflow-wrap:anywhere; } .btn { display:inline-flex; align-items:center; justify-content:center; text-decoration:none; border:0; border-radius:4px; padding:10px 13px; color:#fff; font-weight:800; font-size:13px; background:var(--brand); box-shadow:var(--shadow); } .btn.green { background:var(--green); } .btn.amber { background:var(--amber); } .btn.blue { background:var(--blue); } .btn.slate { background:#687386; }
        .ops-actions { display:flex; flex-wrap:wrap; gap:9px; margin-top:14px; }
        @media (max-width:1300px) { .ops-grid.metrics { grid-template-columns:repeat(3,1fr); } .ops-grid.two { grid-template-columns:1fr; } }
        @media (max-width:1200px) { .ops-shell-layout { grid-template-columns:1fr; } .ops-shell-sidebar { position:static; } }
        @media (max-width:760px) { .ops-shell-topbar { padding:10px 14px; flex-wrap:wrap; } .ops-shell-brand { min-width:0; } .ops-shell-user { display:none; } .ops-main { padding:16px 14px 44px; } .ops-page-title { display:block; } .ops-grid.metrics { grid-template-columns:1fr; } }
    </style>
</head>
<body>
<?php gov_ops_render_topbar('pre_ride'); ?>
<div class="ops-shell-layout">
    <?php gov_ops_render_sidebar('v3_readiness_focus'); ?>
    <main class="ops-main">
        <div class="ops-page-title">
            <div>
                <div class="ops-kicker">V3 Readiness Overview</div>
                <h1>V3 Readiness Focus</h1>
                <p class="ops-page-subtitle">Read-only readiness view for V3 queue, pulse, starting-point mapping facts, and locked live-submit gate state. This page does not run the pipeline and does not make operational decisions.</p>
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
            ['key' => 'queue_focus', 'label' => 'Queue Focus', 'href' => '/ops/pre-ride-email-v3-queue-focus.php'],
            ['key' => 'pulse_focus', 'label' => 'Pulse Focus', 'href' => '/ops/pre-ride-email-v3-pulse-focus.php'],
            ['key' => 'readiness_focus', 'label' => 'Readiness Focus', 'href' => '/ops/pre-ride-email-v3-readiness-focus.php'],
            ['key' => 'dashboard', 'label' => 'V3 Dashboard', 'href' => '/ops/pre-ride-email-v3-dashboard.php'],
            ['key' => 'queue_watch', 'label' => 'Queue Watch', 'href' => '/ops/pre-ride-email-v3-queue-watch.php'],
            ['key' => 'pulse', 'label' => 'Pulse Monitor', 'href' => '/ops/pre-ride-email-v3-fast-pipeline-pulse.php'],
            ['key' => 'readiness', 'label' => 'Automation Readiness', 'href' => '/ops/pre-ride-email-v3-automation-readiness.php'],
            ['key' => 'storage', 'label' => 'Storage Check', 'href' => '/ops/pre-ride-email-v3-storage-check.php'],
        ], 'readiness_focus'); ?>

        <section class="ops-alert safe">
            <strong>SAFE READ-ONLY V3 VIEW.</strong>
            No Bolt call, no EDXEIX call, no AADE call, no DB writes, no V0 production helper changes.
        </section>

        <?php if (!$manualHandoffReady): ?>
            <section class="ops-alert warn">
                <strong>V3 readiness needs attention:</strong>
                Review the cards below. This page only reads state; it does not repair files, write queue rows, or run cron workers.
            </section>
        <?php endif; ?>

        <section class="ops-grid metrics">
            <article class="ops-card metric-card <?= gov_ops_h($overallType) ?>">
                <strong><?= $manualHandoffReady ? 'YES' : 'CHECK' ?></strong>
                <span>Manual handoff visibility</span>
                <small><?= gov_ops_h($overallBadge) ?></small>
            </article>
            <article class="ops-card metric-card <?= $pulse['ok'] ? 'good' : 'warn' ?>">
                <strong><?= $pulse['ok'] ? 'OK' : 'CHECK' ?></strong>
                <span>Pulse cron</span>
                <small><?= gov_ops_h((string)($pulse['freshness_seconds'] ?? 'n/a')) ?> seconds since log update</small>
            </article>
            <article class="ops-card metric-card <?= $queueTable ? 'good' : 'bad' ?>">
                <strong><?= $queueTable ? 'OK' : 'BAD' ?></strong>
                <span>V3 queue table</span>
                <small><?= $queueTable ? 'present and readable' : 'missing or unreadable' ?></small>
            </article>
            <article class="ops-card metric-card <?= ((int)$queueMetrics['future_active'] > 0 || (int)$queueMetrics['dry_run_ready'] > 0 || (int)$queueMetrics['live_ready'] > 0) ? 'good' : 'warn' ?>">
                <strong><?= gov_ops_h((string)((int)$queueMetrics['future_active'] + (int)$queueMetrics['dry_run_ready'] + (int)$queueMetrics['live_ready'])) ?></strong>
                <span>Useful current rows</span>
                <small>future active + dry-run-ready + live-ready</small>
            </article>
            <article class="ops-card metric-card bad">
                <strong>NO</strong>
                <span>Future live submit</span>
                <small>Gate remains closed by design.</small>
            </article>
        </section>

        <section class="ops-grid two">
            <article class="ops-card">
                <h2>Queue Readiness</h2>
                <ul class="status-list">
                    <li><span>DB connection</span><strong><?= gov_ops_badge($dbState['ok'] ? 'ok' : 'bad', $dbState['ok'] ? 'good' : 'bad') ?></strong></li>
                    <li><span>Total V3 rows</span><strong><?= gov_ops_h((string)$queueMetrics['total']) ?></strong></li>
                    <li><span>Active rows</span><strong><?= gov_ops_h((string)$queueMetrics['active']) ?></strong></li>
                    <li><span>Future active rows</span><strong><?= gov_ops_h((string)$queueMetrics['future_active']) ?></strong></li>
                    <li><span>Submit dry-run ready</span><strong><?= gov_ops_h((string)$queueMetrics['dry_run_ready']) ?></strong></li>
                    <li><span>Live-submit ready</span><strong><?= gov_ops_h((string)$queueMetrics['live_ready']) ?></strong></li>
                    <li><span>Blocked rows</span><strong><?= gov_ops_h((string)$queueMetrics['blocked']) ?></strong></li>
                    <li><span>Submitted rows</span><strong><?= gov_ops_h((string)$queueMetrics['submitted']) ?></strong></li>
                </ul>
                <div class="ops-actions">
                    <a class="btn blue" href="/ops/pre-ride-email-v3-queue-focus.php">Queue Focus</a>
                    <a class="btn green" href="/ops/pre-ride-email-v3-monitor.php">Compact Monitor</a>
                    <a class="btn slate" href="/ops/pre-ride-email-v3-queue-watch.php">Queue Watch</a>
                </div>
            </article>

            <article class="ops-card">
                <h2>Pulse / Gate Readiness</h2>
                <ul class="status-list">
                    <li><span>Pulse status</span><strong><?= gov_ops_badge($pulse['ok'] ? 'ok' : 'check', $pulse['ok'] ? 'good' : 'warn') ?></strong></li>
                    <li><span>Pulse lock</span><strong><?= gov_ops_badge($pulse['lock_ok'] ? 'ok' : 'bad', $pulse['lock_ok'] ? 'good' : 'bad') ?></strong></li>
                    <li><span>Pulse lock owner/perms</span><strong><?= gov_ops_h((string)$pulse['lock_owner']) ?> / <?= gov_ops_h((string)$pulse['lock_perms']) ?></strong></li>
                    <li><span>Last pulse summary</span><strong class="mono"><?= gov_ops_h((string)$pulse['last_summary']) ?></strong></li>
                    <li><span>Last pulse finish</span><strong class="mono"><?= gov_ops_h((string)$pulse['last_finish']) ?></strong></li>
                    <li><span>Gate config loaded</span><strong><?= gov_ops_badge($gate['loaded'] ? 'yes' : 'no', $gate['loaded'] ? 'good' : 'bad') ?></strong></li>
                    <li><span>Gate enabled</span><strong><?= gov_ops_badge($gate['enabled'] ? 'yes' : 'no', $gate['enabled'] ? 'bad' : 'good') ?></strong></li>
                    <li><span>Mode / adapter</span><strong><?= gov_ops_badge((string)$gate['mode'], ((string)$gate['mode'] === 'disabled') ? 'good' : 'warn') ?> <?= gov_ops_badge((string)$gate['adapter'], ((string)$gate['adapter'] === 'disabled') ? 'good' : 'warn') ?></strong></li>
                    <li><span>OK for future live submit</span><strong><?= gov_ops_badge($futureLiveReady ? 'yes' : 'no', $futureLiveReady ? 'warn' : 'good') ?></strong></li>
                </ul>
                <div class="ops-actions">
                    <a class="btn blue" href="/ops/pre-ride-email-v3-pulse-focus.php">Pulse Focus</a>
                    <a class="btn amber" href="/ops/pre-ride-email-v3-live-submit-gate.php">Locked Submit Gate</a>
                    <a class="btn slate" href="/ops/pre-ride-email-v3-storage-check.php">Storage Check</a>
                </div>
            </article>
        </section>

        <section class="ops-grid two">
            <article class="ops-card">
                <h2>Newest Queue Row</h2>
                <?php if (!$newestRow): ?>
                    <p>No queue rows found.</p>
                <?php else: ?>
                    <ul class="status-list">
                        <li><span>ID / status</span><strong>#<?= gov_ops_h((string)($newestRow['id'] ?? '')) ?> <?= gov_ops_badge((string)($newestRow['queue_status'] ?? ''), gov_v3_rf_status_badge_type((string)($newestRow['queue_status'] ?? ''))) ?></strong></li>
                        <li><span>Customer</span><strong><?= gov_ops_h((string)($newestRow['customer_name'] ?? '')) ?></strong></li>
                        <li><span>Pickup</span><strong><?= gov_ops_h((string)($newestRow['pickup_datetime'] ?? '')) ?></strong></li>
                        <li><span>Minutes until pickup</span><strong><?= $minutesNewest === null ? 'n/a' : gov_ops_h((string)$minutesNewest) ?></strong></li>
                        <li><span>Driver / vehicle</span><strong><?= gov_ops_h((string)($newestRow['driver_name'] ?? '')) ?> / <?= gov_ops_h((string)($newestRow['vehicle_plate'] ?? '')) ?></strong></li>
                        <li><span>Lessor / driver / vehicle / start</span><strong><?= gov_ops_h((string)($newestRow['lessor_id'] ?? '')) ?> / <?= gov_ops_h((string)($newestRow['driver_id'] ?? '')) ?> / <?= gov_ops_h((string)($newestRow['vehicle_id'] ?? '')) ?> / <?= gov_ops_h((string)($newestRow['starting_point_id'] ?? '')) ?></strong></li>
                        <li><span>Created / updated</span><strong><?= gov_ops_h((string)($newestRow['created_at'] ?? '')) ?> / <?= gov_ops_h((string)($newestRow['updated_at'] ?? '')) ?></strong></li>
                    </ul>
                    <h3>Last error</h3>
                    <p class="mono"><?= gov_ops_h((string)($newestRow['last_error'] ?? '')) ?></p>
                <?php endif; ?>
            </article>

            <article class="ops-card">
                <h2>Mapping Facts</h2>
                <ul class="status-list">
                    <?php foreach ($mappingChecks as $check): ?>
                        <?php $status = (string)($check['status'] ?? 'warn'); ?>
                        <li>
                            <span><?= gov_ops_h((string)($check['label'] ?? '')) ?></span>
                            <strong><?= gov_ops_badge($status, $status === 'ok' ? 'good' : ($status === 'missing' ? 'warn' : 'bad')) ?> <span class="mono"><?= gov_ops_h((string)($check['detail'] ?? '')) ?></span></strong>
                        </li>
                    <?php endforeach; ?>
                    <li><span>Known valid lessor 2307 option</span><strong class="mono">1455969 = ΧΩΡΑ ΜΥΚΟΝΟΥ</strong></li>
                    <li><span>Known valid lessor 2307 option</span><strong class="mono">9700559 = ΕΠΑΝΩ ΔΙΑΚΟΦΤΗΣ</strong></li>
                    <li><span>Old invalid start id</span><strong><?= gov_ops_badge('6467495 invalid for 2307', 'bad') ?></strong></li>
                </ul>
            </article>
        </section>

        <section class="ops-card">
            <h2>Status Distribution</h2>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Status</th><th>Rows</th></tr></thead>
                    <tbody>
                    <?php if (!$statusRows): ?>
                        <tr><td colspan="2">No status rows found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($statusRows as $row): ?>
                            <tr>
                                <td><?= gov_ops_badge((string)($row['queue_status'] ?? ''), gov_v3_rf_status_badge_type((string)($row['queue_status'] ?? ''))) ?></td>
                                <td><strong><?= gov_ops_h((string)($row['c'] ?? '0')) ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="ops-card" style="margin-top:16px;">
            <h2>Recent Error Reasons</h2>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Status</th><th>Count</th><th>Latest Update</th><th>Error Preview</th></tr></thead>
                    <tbody>
                    <?php if (!$errorRows): ?>
                        <tr><td colspan="4">No recent error rows found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($errorRows as $row): ?>
                            <tr>
                                <td><?= gov_ops_badge((string)($row['queue_status'] ?? ''), gov_v3_rf_status_badge_type((string)($row['queue_status'] ?? ''))) ?></td>
                                <td><?= gov_ops_h((string)($row['c'] ?? '0')) ?></td>
                                <td><?= gov_ops_h((string)($row['latest_update'] ?? '')) ?></td>
                                <td class="mono"><?= gov_ops_h((string)($row['last_error'] ?? '')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</div>
</body>
</html>
