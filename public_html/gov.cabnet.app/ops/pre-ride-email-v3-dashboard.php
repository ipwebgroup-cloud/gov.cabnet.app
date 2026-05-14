<?php
/**
 * gov.cabnet.app — V3 Pre-Ride Automation Control Center
 *
 * Read-only Ops dashboard for Bolt pre-ride email V3 monitoring.
 * This page does not call Bolt, does not call EDXEIX, does not submit forms,
 * does not enqueue live submissions, and does not modify database rows.
 *
 * v3.0.45 notes:
 * - Keeps the defensive metric handling and Ops Home shell/palette.
 * - Integrates verified V3 focus pages into the control center: Compact Monitor, Queue Focus, Pulse Focus, Readiness Focus, and Storage Check.
 * - UI-only change: no V0 changes, no queue mutation, no cron changes, no live-submit change.
 */

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Robots-Tag: noindex, nofollow', true);

require_once __DIR__ . '/_ops-nav.php';

/** @return array<string,mixed> */
function gov_v3_dashboard_db(): array
{
    $bootstrapFile = dirname(__DIR__, 3) . '/gov.cabnet.app_app/src/bootstrap.php';

    if (!is_file($bootstrapFile)) {
        return [
            'ok' => false,
            'error' => 'Missing bootstrap file: ' . $bootstrapFile,
            'connection' => null,
        ];
    }

    try {
        $app = require $bootstrapFile;
        $db = $app['db'] ?? null;
        if (!is_object($db) || !method_exists($db, 'connection')) {
            return [
                'ok' => false,
                'error' => 'Bootstrap loaded, but database service is unavailable.',
                'connection' => null,
            ];
        }

        $mysqli = $db->connection();
        if (!$mysqli instanceof mysqli) {
            return [
                'ok' => false,
                'error' => 'Database service did not return a mysqli connection.',
                'connection' => null,
            ];
        }

        return ['ok' => true, 'error' => null, 'connection' => $mysqli];
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => $e->getMessage(), 'connection' => null];
    }
}

function gov_v3_identifier(string $name): string
{
    if (!preg_match('/^[A-Za-z0-9_]+$/', $name)) {
        throw new InvalidArgumentException('Invalid SQL identifier.');
    }
    return '`' . $name . '`';
}

function gov_v3_table_exists(mysqli $mysqli, string $table): bool
{
    try {
        $sql = 'SELECT COUNT(*) AS c FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?';
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param('s', $table);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        return (int)($row['c'] ?? 0) > 0;
    } catch (Throwable $e) {
        return false;
    }
}

function gov_v3_column_exists(mysqli $mysqli, string $table, string $column): bool
{
    try {
        $sql = 'SELECT COUNT(*) AS c FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?';
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param('ss', $table, $column);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        return (int)($row['c'] ?? 0) > 0;
    } catch (Throwable $e) {
        return false;
    }
}

/** @return array<string,bool> */
function gov_v3_columns(mysqli $mysqli, string $table, array $columns): array
{
    $found = [];
    foreach ($columns as $column) {
        $found[(string)$column] = gov_v3_column_exists($mysqli, $table, (string)$column);
    }
    return $found;
}

function gov_v3_count(mysqli $mysqli, string $table, string $where = '1=1'): int
{
    try {
        $sql = 'SELECT COUNT(*) AS c FROM ' . gov_v3_identifier($table) . ' WHERE ' . $where;
        $result = $mysqli->query($sql);
        $row = $result ? $result->fetch_assoc() : null;
        return (int)($row['c'] ?? 0);
    } catch (Throwable $e) {
        return 0;
    }
}

/** @return array<string,mixed> */
function gov_v3_live_submit_config_state(): array
{
    $file = dirname(__DIR__, 3) . '/gov.cabnet.app_config/pre_ride_email_v3_live_submit.php';
    $state = [
        'file' => $file,
        'exists' => is_file($file),
        'loaded' => false,
        'enabled' => false,
        'mode' => 'missing',
        'adapter' => 'missing',
        'hard_enable_live_submit' => false,
        'ok_for_future_live_submit' => false,
        'error' => null,
    ];

    if (!is_file($file)) {
        return $state;
    }

    try {
        $config = require $file;
        if (!is_array($config)) {
            $state['error'] = 'Config file did not return an array.';
            return $state;
        }

        $enabled = (bool)($config['enabled'] ?? false);
        $hardEnable = (bool)($config['hard_enable_live_submit'] ?? false);
        $mode = (string)($config['mode'] ?? 'unknown');
        $adapter = (string)($config['adapter'] ?? 'unknown');

        $state['loaded'] = true;
        $state['enabled'] = $enabled;
        $state['mode'] = $mode;
        $state['adapter'] = $adapter;
        $state['hard_enable_live_submit'] = $hardEnable;
        $state['ok_for_future_live_submit'] = ($enabled === true && $hardEnable === true && $mode !== 'disabled' && $adapter !== 'disabled');
    } catch (Throwable $e) {
        $state['error'] = $e->getMessage();
    }

    return $state;
}

/** @return array<string,mixed> */
function gov_v3_dashboard_default_data(): array
{
    return [
        'generated_at' => date('Y-m-d H:i:s T'),
        'db' => ['ok' => false, 'error' => null],
        'queue_table_exists' => false,
        'options_table_exists' => false,
        'metrics' => [
            'total' => 0,
            'active' => 0,
            'future_active' => 0,
            'submit_dry_run_ready' => 0,
            'live_submit_ready' => 0,
            'submitted' => 0,
            'blocked' => 0,
            'expired_blocked' => 0,
            'verified_starting_options' => 0,
        ],
        'latest_rows' => [],
        'warnings' => [],
        'next_action' => 'Waiting for the next future-safe Bolt pre-ride email.',
        'live_config' => gov_v3_live_submit_config_state(),
    ];
}

/** @return array<string,mixed> */
function gov_v3_dashboard_data(): array
{
    $data = gov_v3_dashboard_default_data();

    try {
        $db = gov_v3_dashboard_db();
        $data['db']['ok'] = (bool)($db['ok'] ?? false);
        $data['db']['error'] = $db['error'] ?? null;

        if (!$data['db']['ok'] || empty($db['connection']) || !($db['connection'] instanceof mysqli)) {
            $data['warnings'][] = 'Database unavailable; dashboard links still work, but live metrics cannot be shown.';
            return $data;
        }

        $mysqli = $db['connection'];
        $queueTable = 'pre_ride_email_v3_queue';
        $optionsTable = 'pre_ride_email_v3_starting_point_options';

        $data['queue_table_exists'] = gov_v3_table_exists($mysqli, $queueTable);
        $data['options_table_exists'] = gov_v3_table_exists($mysqli, $optionsTable);

        if (!$data['queue_table_exists']) {
            $data['warnings'][] = 'V3 queue table was not found.';
            return $data;
        }

        $queueColumns = gov_v3_columns($mysqli, $queueTable, [
            'id', 'queue_status', 'customer_name', 'pickup_datetime', 'driver_name',
            'vehicle_plate', 'lessor_id', 'driver_id', 'vehicle_id', 'starting_point_id',
            'last_error', 'created_at', 'updated_at',
        ]);

        $hasStatus = !empty($queueColumns['queue_status']);
        $hasPickup = !empty($queueColumns['pickup_datetime']);
        $hasLastError = !empty($queueColumns['last_error']);

        $data['metrics']['total'] = gov_v3_count($mysqli, $queueTable);

        if ($hasStatus) {
            $terminalStatuses = "('blocked', 'submitted', 'cancelled', 'expired')";
            $data['metrics']['blocked'] = gov_v3_count($mysqli, $queueTable, "queue_status = 'blocked'");
            $data['metrics']['submitted'] = gov_v3_count($mysqli, $queueTable, "queue_status = 'submitted'");
            $data['metrics']['submit_dry_run_ready'] = gov_v3_count($mysqli, $queueTable, "queue_status = 'submit_dry_run_ready'");
            $data['metrics']['live_submit_ready'] = gov_v3_count($mysqli, $queueTable, "queue_status = 'live_submit_ready'");
            $data['metrics']['active'] = gov_v3_count($mysqli, $queueTable, 'queue_status NOT IN ' . $terminalStatuses);

            if ($hasPickup) {
                $data['metrics']['future_active'] = gov_v3_count($mysqli, $queueTable, 'queue_status NOT IN ' . $terminalStatuses . ' AND pickup_datetime > NOW()');
            }
        } else {
            $data['warnings'][] = 'queue_status column was not found; status metrics are limited.';
        }

        if ($hasStatus && $hasLastError) {
            $data['metrics']['expired_blocked'] = gov_v3_count($mysqli, $queueTable, "queue_status = 'blocked' AND last_error LIKE '%expired%'");
        }

        if ($data['options_table_exists']) {
            $optionColumns = gov_v3_columns($mysqli, $optionsTable, ['is_active']);
            $data['metrics']['verified_starting_options'] = !empty($optionColumns['is_active'])
                ? gov_v3_count($mysqli, $optionsTable, 'is_active = 1')
                : gov_v3_count($mysqli, $optionsTable);
        }

        $selectColumns = [];
        foreach ($queueColumns as $column => $exists) {
            if ($exists) {
                $selectColumns[] = gov_v3_identifier($column);
            }
        }

        if ($selectColumns !== []) {
            $orderBy = !empty($queueColumns['id']) ? '`id` DESC' : (!empty($queueColumns['created_at']) ? '`created_at` DESC' : '1 DESC');
            $sql = 'SELECT ' . implode(', ', $selectColumns) . ' FROM ' . gov_v3_identifier($queueTable) . ' ORDER BY ' . $orderBy . ' LIMIT 10';
            try {
                $result = $mysqli->query($sql);
                if ($result !== false) {
                    $data['latest_rows'] = $result->fetch_all(MYSQLI_ASSOC);
                }
            } catch (Throwable $e) {
                $data['warnings'][] = 'Latest queue rows could not be loaded: ' . $e->getMessage();
            }
        }

        if ((int)$data['metrics']['live_submit_ready'] > 0) {
            $data['next_action'] = 'Proof candidate exists: inspect queue/watch and payload audit. Live submit remains disabled.';
        } elseif ((int)$data['metrics']['submit_dry_run_ready'] > 0) {
            $data['next_action'] = 'Dry-run candidate exists: inspect live readiness and payload audit.';
        } elseif ((int)$data['metrics']['future_active'] > 0) {
            $data['next_action'] = 'Future active row exists: keep Queue Watch and Pulse Runner open.';
        } else {
            $data['next_action'] = 'Waiting for the next future-safe Bolt pre-ride email.';
        }
    } catch (Throwable $e) {
        $data['warnings'][] = 'Dashboard metric builder recovered from an error: ' . $e->getMessage();
    }

    return $data;
}

function gov_v3_metric_class(int $value, string $kind = 'neutral'): string
{
    if ($kind === 'bad_when_positive' && $value > 0) {
        return 'danger';
    }
    if ($kind === 'good_when_positive' && $value > 0) {
        return 'success';
    }
    if ($kind === 'warn_when_zero' && $value === 0) {
        return 'warning';
    }
    return 'normal';
}

function gov_v3_status_badge(bool $value, string $trueText = 'yes', string $falseText = 'no'): string
{
    return $value ? gov_ops_badge($trueText, 'good') : gov_ops_badge($falseText, 'bad');
}

function gov_v3_queue_status_type(string $status): string
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

$data = gov_v3_dashboard_data();
$metrics = $data['metrics'];
$liveConfig = $data['live_config'];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>V3 Pre-Ride Automation | gov.cabnet.app Ops</title>
    <style>
        :root {
            --page-bg:#eef2f7;
            --panel:#ffffff;
            --ink:#09245a;
            --ink-strong:#071f4f;
            --muted:#536683;
            --line:#d3dbe8;
            --sidebar:#2d3557;
            --sidebar-dark:#26304f;
            --sidebar-line:rgba(255,255,255,.14);
            --brand:#5967b1;
            --brand-dark:#43509a;
            --green:#36a15a;
            --green-soft:#e7f7eb;
            --amber:#d99022;
            --amber-soft:#fff6e6;
            --red:#c7352c;
            --red-soft:#feeceb;
            --blue:#3468d8;
            --blue-soft:#e9f0ff;
            --slate:#64748b;
            --shadow:0 1px 2px rgba(15,23,42,.06);
        }
        * { box-sizing:border-box; }
        body { margin:0; background:var(--page-bg); color:var(--ink); font-family:Arial, Helvetica, sans-serif; font-size:14px; }
        a { color:var(--brand-dark); }
        .ops-shell-topbar { min-height:68px; background:#fff; border-bottom:1px solid var(--line); display:flex; align-items:center; gap:22px; padding:0 26px; position:sticky; top:0; z-index:30; }
        .ops-shell-brand { display:flex; align-items:center; gap:10px; min-width:290px; color:var(--ink); text-decoration:none; }
        .ops-shell-logo { width:46px; height:46px; border:2px solid #7d8ccc; border-radius:50%; display:grid; place-items:center; font-weight:800; color:#5361a9; letter-spacing:.03em; font-size:19px; }
        .ops-shell-brand-text strong { display:block; font-size:20px; line-height:1; color:#1e3a78; }
        .ops-shell-brand-text em { display:block; font-style:normal; color:#31466c; font-size:12px; margin-top:3px; }
        .ops-shell-topnav { display:flex; align-items:center; gap:5px; flex:1; min-width:0; overflow:auto; padding:8px 0; }
        .ops-shell-topnav a { color:#34436b; text-decoration:none; padding:9px 13px; border-radius:14px; white-space:nowrap; font-size:13px; }
        .ops-shell-topnav a:hover, .ops-shell-topnav a.active { background:#e9eefb; color:#2f4193; box-shadow:inset 0 0 0 1px #d4dcf4; }
        .ops-shell-user { display:flex; align-items:center; gap:9px; color:#2d3a62; min-width:150px; justify-content:flex-end; }
        .ops-shell-user-mark { width:36px; height:36px; border-radius:50%; display:grid; place-items:center; background:#5864b0; color:#fff; font-weight:800; }
        .ops-shell-user strong { display:block; font-size:12px; line-height:1; letter-spacing:.03em; }
        .ops-shell-user em { display:block; font-style:normal; font-size:10px; color:#64748b; margin-top:2px; }
        .ops-shell-layout { display:grid; grid-template-columns:300px 1fr; min-height:calc(100vh - 68px); }
        .ops-shell-sidebar { background:var(--sidebar); color:#fff; padding:22px 16px 44px; box-shadow:inset -1px 0 0 rgba(0,0,0,.08); }
        .ops-operator-card { border:1px solid var(--sidebar-line); border-radius:8px; background:rgba(255,255,255,.06); padding:12px; margin-bottom:22px; }
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
        .ops-breadcrumb { color:#61708b; font-size:12px; margin:7px 0 18px; }
        .ops-page-title { display:flex; align-items:flex-start; justify-content:space-between; gap:18px; margin-bottom:8px; }
        h1 { margin:0; color:var(--ink-strong); font-size:28px; line-height:1.15; letter-spacing:-.02em; }
        .ops-page-subtitle { margin:6px 0 0; color:var(--muted); line-height:1.45; max-width:980px; }
        .ops-page-tabs { display:flex; flex-wrap:wrap; gap:12px; margin:18px 0; }
        .ops-page-tabs a { text-decoration:none; color:#4d5e7f; padding:12px 16px; border-radius:4px; }
        .ops-page-tabs a:hover, .ops-page-tabs a.active { background:#5662b1; color:#fff; }
        .ops-alert { border:1px solid var(--line); background:#fff; border-radius:4px; padding:13px 16px; margin:16px 0; box-shadow:var(--shadow); }
        .ops-alert.safe { border-left:4px solid var(--green); background:#f3fff5; }
        .ops-alert.warn { border-left:4px solid var(--amber); background:#fffaf1; }
        .ops-alert.danger { border-left:4px solid var(--red); background:#fff6f5; }
        .ops-hero { background:#fff; border:1px solid var(--line); border-top:3px solid var(--amber); border-radius:3px; padding:22px 20px; margin-bottom:16px; box-shadow:var(--shadow); }
        .ops-hero h2 { margin:0 0 10px; color:var(--ink-strong); font-size:27px; letter-spacing:-.02em; }
        .ops-hero p { color:#254062; margin:8px 0; line-height:1.45; }
        .ops-actions { display:flex; flex-wrap:wrap; gap:9px; margin-top:14px; }
        .btn { display:inline-flex; align-items:center; justify-content:center; gap:8px; text-decoration:none; border:0; border-radius:4px; padding:11px 15px; color:#fff; font-weight:800; font-size:14px; background:var(--brand); box-shadow:var(--shadow); }
        .btn.green { background:var(--green); } .btn.amber { background:var(--amber); } .btn.slate { background:#687386; } .btn.blue { background:var(--blue); }
        .ops-grid { display:grid; gap:14px; }
        .ops-grid.metrics { grid-template-columns:repeat(4, minmax(0,1fr)); margin:16px 0; }
        .ops-grid.two { grid-template-columns:repeat(2, minmax(0,1fr)); margin:16px 0; }
        .ops-grid.three { grid-template-columns:repeat(3, minmax(0,1fr)); margin:16px 0; }
        .ops-card { background:#fff; border:1px solid var(--line); border-radius:4px; padding:16px; box-shadow:var(--shadow); }
        .ops-card h2, .ops-card h3 { color:var(--ink-strong); margin:0 0 10px; }
        .ops-card h2 { font-size:20px; } .ops-card h3 { font-size:17px; }
        .ops-card p { color:#31486d; line-height:1.45; margin:7px 0; }
        .metric-card { min-height:130px; border-left:4px solid var(--brand); }
        .metric-card.good { border-left-color:var(--green); }
        .metric-card.warn { border-left-color:var(--amber); }
        .metric-card.bad { border-left-color:var(--red); }
        .metric-card strong { display:block; font-size:32px; color:#06245c; line-height:1; letter-spacing:-.03em; margin-bottom:8px; }
        .metric-card span { display:block; color:#405278; font-size:13px; }
        .metric-card small { display:block; margin-top:10px; color:#61708b; line-height:1.4; }
        .ops-badge { display:inline-flex; align-items:center; white-space:nowrap; padding:5px 9px; border-radius:999px; font-size:11px; line-height:1; font-weight:800; margin:2px; }
        .ops-badge-good { background:var(--green-soft); color:#17743b; border:1px solid #bce8c8; }
        .ops-badge-warn { background:var(--amber-soft); color:#9c5b00; border:1px solid #f0d19b; }
        .ops-badge-bad { background:var(--red-soft); color:#a3221b; border:1px solid #f5c2bf; }
        .ops-badge-info { background:var(--blue-soft); color:#244ea9; border:1px solid #cbdafa; }
        .ops-badge-neutral { background:#edf1f7; color:#40536f; border:1px solid #d9e1ef; }
        .ops-badge-dark { background:#30384e; color:#fff; border:1px solid #30384e; }
        .ops-badge-soft { background:#eef1fb; color:#425099; border:1px solid #d9def4; }
        .status-list { margin:0; padding:0; list-style:none; }
        .status-list li { display:flex; align-items:center; justify-content:space-between; gap:12px; padding:10px 0; border-bottom:1px solid var(--line); color:#3d4d6d; }
        .status-list li:last-child { border-bottom:0; }
        .status-list strong { color:#061f54; text-align:right; }
        .link-grid { display:grid; grid-template-columns:repeat(2, minmax(0,1fr)); gap:10px; }
        .link-card { display:block; text-decoration:none; color:#14346c; background:#fbfcff; border:1px solid var(--line); border-radius:4px; padding:13px; min-height:82px; }
        .link-card:hover { background:#f3f6ff; border-color:#aeb9df; }
        .link-card strong { display:block; margin-bottom:5px; color:#09245a; }
        .link-card span { display:block; color:#536683; font-size:13px; line-height:1.38; }
        .table-wrap { border:1px solid var(--line); border-radius:4px; overflow:auto; background:#fff; }
        table { width:100%; border-collapse:collapse; min-width:1080px; }
        th, td { padding:10px 11px; border-bottom:1px solid var(--line); text-align:left; vertical-align:top; font-size:13px; }
        th { color:#405278; background:#f6f8fc; text-transform:uppercase; letter-spacing:.04em; font-size:11px; }
        tr:last-child td { border-bottom:0; }
        code, pre { background:#eef2ff; border:1px solid #d7def5; border-radius:4px; color:#132e65; }
        code { padding:2px 5px; }
        pre { padding:12px; overflow:auto; white-space:pre-wrap; }
        .nowrap { white-space:nowrap; }
        .muted { color:var(--muted); }
        .section-kicker { font-size:12px; text-transform:uppercase; letter-spacing:.06em; color:#697893; margin-bottom:6px; }
        @media (max-width:1220px) {
            .ops-shell-layout { grid-template-columns:1fr; }
            .ops-shell-sidebar { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:12px; padding-bottom:18px; }
            .ops-operator-card, .ops-side-section { margin-bottom:0; }
            .ops-grid.metrics { grid-template-columns:repeat(2,minmax(0,1fr)); }
            .ops-grid.two, .ops-grid.three { grid-template-columns:1fr; }
        }
        @media (max-width:820px) {
            .ops-shell-topbar { position:relative; flex-direction:column; align-items:flex-start; padding:13px; gap:10px; }
            .ops-shell-brand { min-width:0; }
            .ops-shell-user { justify-content:flex-start; min-width:0; }
            .ops-shell-sidebar { grid-template-columns:1fr; }
            .ops-main { padding:16px 12px 50px; }
            .ops-page-title { flex-direction:column; }
            .ops-grid.metrics { grid-template-columns:1fr; }
            .link-grid { grid-template-columns:1fr; }
            .ops-hero h2 { font-size:23px; }
        }
    </style>
</head>
<body>
<?php gov_ops_render_topbar('pre_ride'); ?>
<div class="ops-shell-layout">
    <?php gov_ops_render_sidebar('v3_dashboard'); ?>
    <main class="ops-main">
        <div class="ops-page-title">
            <div>
                <h1>V3 Pre-Ride Automation</h1>
                <div class="ops-breadcrumb">Αρχική / Pre-Ride / V3 Control Center</div>
                <p class="ops-page-subtitle">Read-only monitoring for Bolt pre-ride email intake, queue state, safety guards, dry-run readiness, payload audit, and locked live-submit preparation.</p>
            </div>
            <div>
                <?= gov_ops_badge('Production', 'dark') ?>
                <?= gov_ops_badge('Read-only UI', 'info') ?>
                <?= gov_ops_badge('Live submit disabled', 'bad') ?>
            </div>
        </div>

        <?php
        gov_ops_render_page_tabs([
            ['key' => 'card', 'label' => 'Καρτέλα', 'href' => '/ops/pre-ride-email-v3-dashboard.php'],
            ['key' => 'compact', 'label' => 'Compact Monitor', 'href' => '/ops/pre-ride-email-v3-monitor.php'],
            ['key' => 'queue_focus', 'label' => 'Queue Focus', 'href' => '/ops/pre-ride-email-v3-queue-focus.php'],
            ['key' => 'pulse_focus', 'label' => 'Pulse Focus', 'href' => '/ops/pre-ride-email-v3-pulse-focus.php'],
            ['key' => 'readiness_focus', 'label' => 'Readiness Focus', 'href' => '/ops/pre-ride-email-v3-readiness-focus.php'],
            ['key' => 'storage', 'label' => 'Storage Check', 'href' => '/ops/pre-ride-email-v3-storage-check.php'],
            ['key' => 'gate', 'label' => 'Locked Gate', 'href' => '/ops/pre-ride-email-v3-live-submit-gate.php'],
        ], 'card');
        ?>

        <section class="ops-alert safe">
            <strong>SAFE OPS SHELL.</strong>
            READ-ONLY V3 MONITOR. This page reads readiness state only. It does not call Bolt, does not call EDXEIX, does not stage jobs, and does not write data.
        </section>

        <section class="ops-hero">
            <h2>V3 Pre-Ride Automation Control Center</h2>
            <p><?= gov_ops_h((string)$data['next_action']) ?></p>
            <p>
                <?= gov_ops_badge('LIVE SUBMIT OFF', 'good') ?>
                <?= gov_ops_badge('NO BOLT CALL HERE', 'good') ?>
                <?= gov_ops_badge('NO EDXEIX CALL HERE', 'good') ?>
                <?= gov_ops_badge('READ ONLY', 'good') ?>
                <?= gov_ops_badge('Generated ' . (string)$data['generated_at'], 'neutral') ?>
            </p>
            <div class="ops-actions">
                <a class="btn green" href="/ops/pre-ride-email-v3-monitor.php">Open Compact Monitor</a>
                <a class="btn blue" href="/ops/pre-ride-email-v3-queue-focus.php">Open Queue Focus</a>
                <a class="btn blue" href="/ops/pre-ride-email-v3-pulse-focus.php">Open Pulse Focus</a>
                <a class="btn slate" href="/ops/pre-ride-email-v3-readiness-focus.php">Readiness Focus</a>
                <a class="btn amber" href="/ops/pre-ride-email-v3-live-submit-gate.php">Locked Submit Gate</a>
            </div>
        </section>

        <?php if (!$data['db']['ok']): ?>
            <section class="ops-alert danger">
                <strong>Dashboard metrics unavailable.</strong>
                <?= gov_ops_h((string)($data['db']['error'] ?? 'Unknown database/bootstrap error.')) ?>
            </section>
        <?php endif; ?>

        <section class="ops-grid metrics">
            <article class="ops-card metric-card <?= ((int)$metrics['future_active'] > 0) ? 'good' : '' ?>">
                <strong><?= gov_ops_h((string)$metrics['future_active']) ?></strong>
                <span>Future active rows</span>
                <small>Best signal that a new real pre-ride test is moving through V3.</small>
            </article>
            <article class="ops-card metric-card <?= ((int)$metrics['submit_dry_run_ready'] > 0) ? 'good' : '' ?>">
                <strong><?= gov_ops_h((string)$metrics['submit_dry_run_ready']) ?></strong>
                <span>Submit dry-run ready</span>
                <small>Acceptable proof state before live-readiness confirmation.</small>
            </article>
            <article class="ops-card metric-card <?= ((int)$metrics['live_submit_ready'] > 0) ? 'good' : '' ?>">
                <strong><?= gov_ops_h((string)$metrics['live_submit_ready']) ?></strong>
                <span>Live-submit ready</span>
                <small>Best proof state. The live-submit gate still remains closed.</small>
            </article>
            <article class="ops-card metric-card <?= ((int)$metrics['blocked'] > 0) ? 'bad' : '' ?>">
                <strong><?= gov_ops_h((string)$metrics['blocked']) ?></strong>
                <span>Blocked rows</span>
                <small>Safe state. Inspect last_error only if a new current test blocks.</small>
            </article>
        </section>

        <section class="ops-grid two">
            <article class="ops-card">
                <h2>V3 Queue Snapshot</h2>
                <ul class="status-list">
                    <li><span>Queue table</span><strong><?= gov_v3_status_badge((bool)$data['queue_table_exists'], 'present', 'missing') ?></strong></li>
                    <li><span>Total V3 rows</span><strong><?= gov_ops_h((string)$metrics['total']) ?></strong></li>
                    <li><span>Active rows</span><strong><?= gov_ops_h((string)$metrics['active']) ?></strong></li>
                    <li><span>Future active rows</span><strong><?= gov_ops_h((string)$metrics['future_active']) ?></strong></li>
                    <li><span>Submitted rows</span><strong><?= gov_ops_h((string)$metrics['submitted']) ?></strong></li>
                    <li><span>Expired blocked rows</span><strong><?= gov_ops_h((string)$metrics['expired_blocked']) ?></strong></li>
                    <li><span>Verified starting options</span><strong><?= gov_ops_h((string)$metrics['verified_starting_options']) ?></strong></li>
                </ul>
            </article>

            <article class="ops-card">
                <h2>Live Submit Gate</h2>
                <ul class="status-list">
                    <li><span>Config exists</span><strong><?= gov_v3_status_badge((bool)$liveConfig['exists']) ?></strong></li>
                    <li><span>Config loaded</span><strong><?= gov_v3_status_badge((bool)$liveConfig['loaded']) ?></strong></li>
                    <li><span>Enabled</span><strong><?= (bool)$liveConfig['enabled'] ? gov_ops_badge('yes', 'bad') : gov_ops_badge('no', 'good') ?></strong></li>
                    <li><span>Mode</span><strong><?= gov_ops_badge((string)$liveConfig['mode'], ((string)$liveConfig['mode'] === 'disabled') ? 'good' : 'warn') ?></strong></li>
                    <li><span>Adapter</span><strong><?= gov_ops_badge((string)$liveConfig['adapter'], ((string)$liveConfig['adapter'] === 'disabled') ? 'good' : 'warn') ?></strong></li>
                    <li><span>Hard enable live submit</span><strong><?= (bool)$liveConfig['hard_enable_live_submit'] ? gov_ops_badge('yes', 'bad') : gov_ops_badge('no', 'good') ?></strong></li>
                    <li><span>OK for future live submit</span><strong><?= (bool)$liveConfig['ok_for_future_live_submit'] ? gov_ops_badge('yes', 'bad') : gov_ops_badge('no', 'good') ?></strong></li>
                </ul>
                <?php if (!empty($liveConfig['error'])): ?>
                    <p><strong>Config note:</strong> <?= gov_ops_h((string)$liveConfig['error']) ?></p>
                <?php endif; ?>
            </article>
        </section>

        <section class="ops-grid three">
            <article class="ops-card">
                <div class="section-kicker">Current test</div>
                <h2>Pending Future-Safe Email</h2>
                <p>Keep the watch and pulse pages open while waiting for the next real Bolt pre-ride email.</p>
                <div class="link-grid">
                    <a class="link-card" href="/ops/pre-ride-email-v3-monitor.php"><strong>Compact Monitor</strong><span>Fast top-level V3 status view.</span></a>
                    <a class="link-card" href="/ops/pre-ride-email-v3-queue-focus.php"><strong>Queue Focus</strong><span>Newest rows, status, pickup timing, and last_error.</span></a>
                    <a class="link-card" href="/ops/pre-ride-email-v3-pulse-focus.php"><strong>Pulse Focus</strong><span>Pulse cron health, lock owner, and recent log events.</span></a>
                    <a class="link-card" href="/ops/pre-ride-email-v3-readiness-focus.php"><strong>Readiness Focus</strong><span>Pulse, queue, mappings, errors, and locked gate overview.</span></a>
                </div>
                <h3>Manual proof command</h3>
                <pre>php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_fast_pipeline_pulse.php --limit=50 --cycles=3 --sleep=2 --commit</pre>
            </article>

            <article class="ops-card">
                <div class="section-kicker">Safety guards</div>
                <h2>Preflight Visibility</h2>
                <div class="link-grid">
                    <a class="link-card" href="/ops/pre-ride-email-v3-starting-point-guard.php"><strong>Starting-Point Guard</strong><span>Validates lessor and EDXEIX starting point.</span></a>
                    <a class="link-card" href="/ops/pre-ride-email-v3-expiry-guard.php"><strong>Expiry Guard</strong><span>Blocks old, past, and expired queue rows.</span></a>
                    <a class="link-card" href="/ops/pre-ride-email-v3-live-readiness.php"><strong>Live Readiness</strong><span>Shows future live-submit readiness only.</span></a>
                    <a class="link-card" href="/ops/pre-ride-email-v3-live-payload-audit.php"><strong>Payload Audit</strong><span>Audit payload before future adapter work.</span></a>
                </div>
            </article>

            <article class="ops-card">
                <div class="section-kicker">Known safe facts</div>
                <h2>Mappings & Exemptions</h2>
                <ul class="status-list">
                    <li><span>Lessor 2307 / ΧΩΡΑ ΜΥΚΟΝΟΥ</span><strong>1455969</strong></li>
                    <li><span>Lessor 2307 / ΕΠΑΝΩ ΔΙΑΚΟΦΤΗΣ</span><strong>9700559</strong></li>
                    <li><span>Invalid old starting point</span><strong>6467495</strong></li>
                    <li><span>EMT8640</span><strong><?= gov_ops_badge('permanently exempt', 'warn') ?></strong></li>
                    <li><span>Live adapter</span><strong><?= gov_ops_badge('disabled', 'good') ?></strong></li>
                </ul>
            </article>
        </section>

        <section class="ops-card">
            <h2>Verified V3 Operator Views</h2>
            <p>These pages are now the primary V3 visibility set. They are read-only and do not touch the V0 laptop/manual production helper.</p>
            <div class="link-grid">
                <a class="link-card" href="/ops/pre-ride-email-v3-monitor.php"><strong>Compact Monitor</strong><span>One-screen pulse, queue, newest row, and locked gate summary.</span></a>
                <a class="link-card" href="/ops/pre-ride-email-v3-queue-focus.php"><strong>Queue Focus</strong><span>Queue totals, newest row, status distribution, and last_error previews.</span></a>
                <a class="link-card" href="/ops/pre-ride-email-v3-pulse-focus.php"><strong>Pulse Focus</strong><span>Pulse cron start/finish, summary, errors, log tail, and lock ownership.</span></a>
                <a class="link-card" href="/ops/pre-ride-email-v3-readiness-focus.php"><strong>Readiness Focus</strong><span>Pulse readiness, queue state, mappings, errors, and closed gate state.</span></a>
                <a class="link-card" href="/ops/pre-ride-email-v3-storage-check.php"><strong>Storage Check</strong><span>Runtime folders, logs, pulse files, and lock file owner/perms.</span></a>
                <a class="link-card" href="/ops/pre-ride-email-v3-dashboard.php"><strong>V3 Control Center</strong><span>This integrated hub for the V3 development/monitoring path.</span></a>
            </div>
        </section>

        <?php if (!empty($data['warnings'])): ?>
            <section class="ops-alert warn">
                <strong>Dashboard warnings</strong>
                <ul>
                    <?php foreach ($data['warnings'] as $warning): ?>
                        <li><?= gov_ops_h((string)$warning) ?></li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endif; ?>

        <section class="ops-card">
            <h2>Latest V3 Queue Rows</h2>
            <?php if (empty($data['latest_rows'])): ?>
                <p>No queue rows are available to display.</p>
            <?php else: ?>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th><th>Status</th><th>Customer</th><th>Pickup</th><th>Driver</th><th>Vehicle</th>
                                <th>Lessor</th><th>Driver ID</th><th>Vehicle ID</th><th>Starting Point</th><th>Last Error</th><th>Created</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($data['latest_rows'] as $row): ?>
                            <?php $status = (string)($row['queue_status'] ?? 'unknown'); ?>
                            <tr>
                                <td class="nowrap"><?= gov_ops_h((string)($row['id'] ?? '')) ?></td>
                                <td><?= gov_ops_badge($status, gov_v3_queue_status_type($status)) ?></td>
                                <td><?= gov_ops_h((string)($row['customer_name'] ?? '')) ?></td>
                                <td class="nowrap"><?= gov_ops_h((string)($row['pickup_datetime'] ?? '')) ?></td>
                                <td><?= gov_ops_h((string)($row['driver_name'] ?? '')) ?></td>
                                <td><?= gov_ops_h((string)($row['vehicle_plate'] ?? '')) ?></td>
                                <td><?= gov_ops_h((string)($row['lessor_id'] ?? '')) ?></td>
                                <td><?= gov_ops_h((string)($row['driver_id'] ?? '')) ?></td>
                                <td><?= gov_ops_h((string)($row['vehicle_id'] ?? '')) ?></td>
                                <td><?= gov_ops_h((string)($row['starting_point_id'] ?? '')) ?></td>
                                <td><?= gov_ops_h((string)($row['last_error'] ?? '')) ?></td>
                                <td class="nowrap"><?= gov_ops_h((string)($row['created_at'] ?? '')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <section class="ops-grid two">
            <article class="ops-card">
                <h2>Ops Structure Direction</h2>
                <p>The first screenshot is now treated as the canonical shell: white top nav, deep-blue sidebar, light content canvas, clean cards, and simple operator tabs.</p>
                <div class="link-grid">
                    <a class="link-card" href="/ops/home.php"><strong>Ops Home</strong><span>Master operations landing page.</span></a>
                    <a class="link-card" href="/ops/pre-ride-email-v3-dashboard.php"><strong>Pre-Ride V3</strong><span>Current automation monitor.</span></a>
                    <a class="link-card" href="/ops/pre-ride-email-v3-live-submit-gate.php"><strong>Live Submit Locked</strong><span>Gate visibility only.</span></a>
                    <a class="link-card" href="/ops/pre-ride-email-v3-storage-check.php"><strong>Storage Check</strong><span>Pulse lock and runtime prerequisites.</span></a>
                    <a class="link-card" href="/ops/readiness.php"><strong>Bolt Bridge</strong><span>Legacy/current bridge visibility.</span></a>
                </div>
            </article>

            <article class="ops-card">
                <h2>Next Safe UI Steps</h2>
                <ol>
                    <li>Use the compact/focus pages as the main V3 visibility set.</li>
                    <li>Leave V0 on the laptop/manual production flow untouched.</li>
                    <li>Polish older V3 worker pages only when needed.</li>
                    <li>Keep live submit disabled until separately approved.</li>
                </ol>
                <p><strong>No live-submit, cron, queue mutation, mapping, or SQL change is included.</strong></p>
            </article>
        </section>
    </main>
</div>
</body>
</html>
