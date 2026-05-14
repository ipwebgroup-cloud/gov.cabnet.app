<?php
/**
 * gov.cabnet.app — V3 Pre-Ride Automation Control Center
 *
 * Read-only Ops dashboard for Bolt pre-ride email V3 monitoring.
 * This page does not call Bolt, does not call EDXEIX, does not submit forms,
 * does not enqueue live submissions, and does not modify database rows.
 */

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Robots-Tag: noindex, nofollow', true);

require_once __DIR__ . '/_ops-nav.php';

/** @return array{ok:bool,error:?string,connection:?mysqli} */
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
    $stmt = $mysqli->prepare('SHOW TABLES LIKE ?');
    $stmt->bind_param('s', $table);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result !== false && $result->num_rows > 0;
}

function gov_v3_column_exists(mysqli $mysqli, string $table, string $column): bool
{
    $safeTable = gov_v3_identifier($table);
    $stmt = $mysqli->prepare('SHOW COLUMNS FROM ' . $safeTable . ' LIKE ?');
    $stmt->bind_param('s', $column);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result !== false && $result->num_rows > 0;
}

/** @return array<string,bool> */
function gov_v3_columns(mysqli $mysqli, string $table, array $columns): array
{
    $found = [];
    foreach ($columns as $column) {
        $found[$column] = gov_v3_column_exists($mysqli, $table, (string) $column);
    }
    return $found;
}

function gov_v3_count(mysqli $mysqli, string $table, string $where = '1=1'): int
{
    $safeTable = gov_v3_identifier($table);
    $sql = 'SELECT COUNT(*) AS c FROM ' . $safeTable . ' WHERE ' . $where;
    $result = $mysqli->query($sql);
    if ($result === false) {
        return 0;
    }
    $row = $result->fetch_assoc();
    return (int) ($row['c'] ?? 0);
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

        $enabled = (bool) ($config['enabled'] ?? false);
        $hardEnable = (bool) ($config['hard_enable_live_submit'] ?? false);
        $mode = (string) ($config['mode'] ?? 'unknown');
        $adapter = (string) ($config['adapter'] ?? 'unknown');

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
function gov_v3_dashboard_data(): array
{
    $data = [
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

    $db = gov_v3_dashboard_db();
    $data['db']['ok'] = $db['ok'];
    $data['db']['error'] = $db['error'];

    if (!$db['ok'] || !$db['connection']) {
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
    ]);

    $hasStatus = !empty($queueColumns['queue_status']);
    $hasPickup = !empty($queueColumns['pickup_datetime']);
    $hasLastError = !empty($queueColumns['last_error']);

    $data['metrics']['total'] = gov_v3_count($mysqli, $queueTable);

    if ($hasStatus) {
        $data['metrics']['blocked'] = gov_v3_count($mysqli, $queueTable, "queue_status = 'blocked'");
        $data['metrics']['submitted'] = gov_v3_count($mysqli, $queueTable, "queue_status = 'submitted'");
        $data['metrics']['submit_dry_run_ready'] = gov_v3_count($mysqli, $queueTable, "queue_status = 'submit_dry_run_ready'");
        $data['metrics']['live_submit_ready'] = gov_v3_count($mysqli, $queueTable, "queue_status = 'live_submit_ready'");
        $data['metrics']['active'] = gov_v3_count($mysqli, $queueTable, "queue_status NOT IN ('blocked', 'submitted', 'cancelled', 'expired')");

        if ($hasPickup) {
            $data['metrics']['future_active'] = gov_v3_count($mysqli, $queueTable, "queue_status NOT IN ('blocked', 'submitted', 'cancelled', 'expired') AND pickup_datetime > NOW()");
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
        $result = $mysqli->query($sql);
        if ($result !== false) {
            $data['latest_rows'] = $result->fetch_all(MYSQLI_ASSOC);
        }
    }

    if ((int) $data['metrics']['live_submit_ready'] > 0) {
        $data['next_action'] = 'Proof candidate exists: inspect queue/watch and payload audit. Live submit remains disabled.';
    } elseif ((int) $data['metrics']['submit_dry_run_ready'] > 0) {
        $data['next_action'] = 'Dry-run candidate exists: inspect live readiness and payload audit.';
    } elseif ((int) $data['metrics']['future_active'] > 0) {
        $data['next_action'] = 'Future active row exists: keep Queue Watch and Pulse Runner open.';
    } else {
        $data['next_action'] = 'Waiting for the next future-safe Bolt pre-ride email.';
    }

    return $data;
}

$data = gov_v3_dashboard_data();
$metrics = $data['metrics'];
$liveConfig = $data['live_config'];

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
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>V3 Pre-Ride Automation Control Center | gov.cabnet.app</title>
    <style>
        :root {
            --bg: #f4f7fb;
            --panel: #ffffff;
            --ink: #081225;
            --muted: #52627a;
            --line: #d9e3f0;
            --nav: #071225;
            --blue: #2563eb;
            --green: #07875a;
            --amber: #b85c00;
            --red: #b42318;
            --slate: #334155;
            --soft-blue: #eaf1ff;
            --soft-green: #dcfce7;
            --soft-amber: #fff7ed;
            --soft-red: #fee2e2;
        }
        * { box-sizing: border-box; }
        body { margin: 0; background: var(--bg); color: var(--ink); font-family: Arial, Helvetica, sans-serif; }
        a { color: var(--blue); }
        .ops-topbar { background: var(--nav); color: #fff; padding: 14px 24px; display: flex; align-items: center; justify-content: space-between; gap: 16px; position: sticky; top: 0; z-index: 20; box-shadow: 0 8px 24px rgba(7,18,37,.16); }
        .ops-brand { display: flex; align-items: center; gap: 12px; min-width: 230px; }
        .ops-brand-mark { width: 42px; height: 42px; border-radius: 13px; display: grid; place-items: center; background: #fff; color: var(--nav); font-weight: 800; letter-spacing: -.04em; }
        .ops-brand strong { display: block; font-size: 17px; }
        .ops-brand span { display: block; font-size: 12px; color: #bfdbfe; margin-top: 2px; }
        .ops-top-status { display: flex; flex-wrap: wrap; gap: 7px; justify-content: flex-end; }
        .ops-layout { display: grid; grid-template-columns: 292px 1fr; min-height: calc(100vh - 70px); }
        .ops-nav { background: #0b1730; color: #dbeafe; padding: 18px 14px 44px; border-right: 1px solid rgba(255,255,255,.08); }
        .ops-nav-section { margin-bottom: 12px; border: 1px solid rgba(255,255,255,.08); border-radius: 13px; background: rgba(255,255,255,.035); overflow: hidden; }
        .ops-nav-section summary { cursor: pointer; padding: 12px 13px; font-weight: 800; font-size: 13px; color: #fff; list-style: none; }
        .ops-nav-section summary::-webkit-details-marker { display: none; }
        .ops-nav-links { display: grid; gap: 3px; padding: 0 8px 10px; }
        .ops-nav-links a { display: flex; align-items: center; justify-content: space-between; gap: 9px; color: #dbeafe; text-decoration: none; border-radius: 9px; padding: 9px 10px; font-size: 13px; }
        .ops-nav-links a:hover, .ops-nav-links a.active { background: rgba(255,255,255,.12); color: #fff; }
        .ops-main { width: min(1480px, 100%); padding: 24px; }
        .hero { background: linear-gradient(135deg, #ffffff 0%, #f8fbff 64%, #eef6ff 100%); border: 1px solid var(--line); border-left: 8px solid var(--blue); border-radius: 18px; padding: 22px; box-shadow: 0 12px 30px rgba(8,18,37,.05); margin-bottom: 18px; }
        .hero h1 { font-size: clamp(27px, 3vw, 42px); margin: 0 0 8px; letter-spacing: -.03em; }
        .hero p { max-width: 1040px; color: var(--muted); line-height: 1.5; margin: 8px 0; }
        .hero-actions { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 16px; }
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; border: 0; border-radius: 10px; padding: 11px 14px; background: var(--blue); color: #fff; text-decoration: none; font-weight: 800; font-size: 14px; box-shadow: 0 8px 20px rgba(37,99,235,.16); }
        .btn:hover { filter: brightness(.96); }
        .btn.green { background: var(--green); box-shadow: 0 8px 20px rgba(7,135,90,.14); }
        .btn.amber { background: var(--amber); box-shadow: 0 8px 20px rgba(184,92,0,.14); }
        .btn.dark { background: var(--slate); box-shadow: 0 8px 20px rgba(51,65,85,.14); }
        .grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 14px; margin-bottom: 18px; }
        .grid.two { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .grid.three { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        .card { background: var(--panel); border: 1px solid var(--line); border-radius: 16px; padding: 18px; box-shadow: 0 10px 28px rgba(8,18,37,.04); }
        .card h2 { margin: 0 0 12px; font-size: 22px; letter-spacing: -.02em; }
        .card h3 { margin: 16px 0 8px; font-size: 16px; }
        .card p { color: var(--muted); line-height: 1.48; margin: 7px 0; }
        .metric { min-height: 118px; display: flex; flex-direction: column; justify-content: space-between; }
        .metric strong { display: block; font-size: 38px; line-height: 1; letter-spacing: -.04em; }
        .metric span { color: var(--muted); font-size: 13px; margin-top: 8px; }
        .metric.success { border-left: 7px solid var(--green); }
        .metric.warning { border-left: 7px solid var(--amber); }
        .metric.danger { border-left: 7px solid var(--red); }
        .metric.normal { border-left: 7px solid var(--blue); }
        .ops-badge { display: inline-flex; align-items: center; white-space: nowrap; padding: 5px 9px; border-radius: 999px; font-size: 12px; line-height: 1; font-weight: 800; }
        .ops-badge-good { background: var(--soft-green); color: #166534; }
        .ops-badge-warn { background: var(--soft-amber); color: #9a4b00; }
        .ops-badge-bad { background: var(--soft-red); color: #991b1b; }
        .ops-badge-neutral { background: #edf2f7; color: #334155; }
        .ops-badge-info { background: var(--soft-blue); color: #1d4ed8; }
        .ops-badge-dark { background: #1f2937; color: #fff; }
        .status-list { display: grid; gap: 9px; margin: 0; padding: 0; list-style: none; }
        .status-list li { display: flex; justify-content: space-between; align-items: center; gap: 12px; border-bottom: 1px solid var(--line); padding: 8px 0; color: var(--muted); }
        .status-list li:last-child { border-bottom: 0; }
        .status-list strong { color: var(--ink); }
        .link-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
        .link-card { display: block; text-decoration: none; border: 1px solid var(--line); border-radius: 13px; padding: 14px; background: #fbfdff; color: var(--ink); min-height: 86px; }
        .link-card:hover { border-color: #9bbcf8; box-shadow: 0 8px 20px rgba(37,99,235,.08); }
        .link-card strong { display: block; margin-bottom: 5px; }
        .link-card span { color: var(--muted); font-size: 13px; line-height: 1.38; }
        .table-wrap { border: 1px solid var(--line); border-radius: 13px; overflow: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 1040px; }
        th, td { padding: 10px 12px; border-bottom: 1px solid var(--line); text-align: left; vertical-align: top; font-size: 13px; }
        th { background: #f8fafc; color: #475569; font-size: 11px; text-transform: uppercase; letter-spacing: .04em; }
        tr:last-child td { border-bottom: 0; }
        code, pre { background: #eef2ff; border-radius: 8px; }
        code { padding: 2px 5px; }
        pre { padding: 12px; overflow: auto; color: #1e293b; }
        .notice { border-radius: 14px; padding: 14px 16px; border: 1px solid var(--line); background: #fbfdff; margin-bottom: 18px; }
        .notice.warning { border-left: 7px solid var(--amber); background: #fffaf2; }
        .notice.danger { border-left: 7px solid var(--red); background: #fff7f7; }
        .notice.success { border-left: 7px solid var(--green); background: #f3fff9; }
        .muted { color: var(--muted); }
        .nowrap { white-space: nowrap; }
        @media (max-width: 1180px) {
            .ops-layout { grid-template-columns: 1fr; }
            .ops-nav { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; padding-bottom: 18px; }
            .ops-nav-section { margin-bottom: 0; }
            .grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .grid.three { grid-template-columns: 1fr; }
        }
        @media (max-width: 740px) {
            .ops-topbar { align-items: flex-start; flex-direction: column; padding: 13px; position: relative; }
            .ops-main { padding: 14px; }
            .ops-nav { grid-template-columns: 1fr; }
            .grid, .grid.two { grid-template-columns: 1fr; }
            .link-grid { grid-template-columns: 1fr; }
            .hero { padding: 17px; }
        }
    </style>
</head>
<body>
<?php gov_ops_render_nav('v3_dashboard'); ?>
<div class="ops-layout">
    <aside></aside>
    <main class="ops-main">
        <section class="hero">
            <h1>V3 Pre-Ride Automation Control Center</h1>
            <p>Read-only monitoring for Bolt pre-ride email intake, queue state, starting-point guards, dry-run readiness, payload audit, and locked live-submit preparation.</p>
            <p>
                <?= gov_ops_badge('Live EDXEIX submission disabled', 'bad') ?>
                <?= gov_ops_badge('Dry-run / monitor mode', 'info') ?>
                <?= gov_ops_badge('Generated ' . (string) $data['generated_at'], 'neutral') ?>
            </p>
            <div class="hero-actions">
                <a class="btn green" href="/ops/pre-ride-email-v3-queue-watch.php">Open Queue Watch</a>
                <a class="btn" href="/ops/pre-ride-email-v3-fast-pipeline-pulse.php">Open Pulse Monitor</a>
                <a class="btn dark" href="/ops/pre-ride-email-v3-automation-readiness.php">Open Automation Readiness</a>
                <a class="btn amber" href="/ops/pre-ride-email-v3-live-submit-gate.php">Open Locked Submit Gate</a>
            </div>
        </section>

        <?php if (!$data['db']['ok']): ?>
            <section class="notice danger">
                <strong>Dashboard metrics unavailable.</strong>
                <p><?= gov_ops_h((string) ($data['db']['error'] ?? 'Unknown database/bootstrap error.')) ?></p>
            </section>
        <?php endif; ?>

        <section class="notice warning">
            <strong>Current test objective:</strong>
            <span><?= gov_ops_h((string) $data['next_action']) ?></span>
        </section>

        <section class="grid">
            <article class="card metric <?= gov_v3_metric_class((int) $metrics['future_active'], 'good_when_positive') ?>">
                <div><strong><?= gov_ops_h((string) $metrics['future_active']) ?></strong><span>Future active rows</span></div>
                <p class="muted">Best signal that a new real pre-ride test is moving.</p>
            </article>
            <article class="card metric <?= gov_v3_metric_class((int) $metrics['submit_dry_run_ready'], 'good_when_positive') ?>">
                <div><strong><?= gov_ops_h((string) $metrics['submit_dry_run_ready']) ?></strong><span>Submit dry-run ready</span></div>
                <p class="muted">Acceptable proof state before live readiness.</p>
            </article>
            <article class="card metric <?= gov_v3_metric_class((int) $metrics['live_submit_ready'], 'good_when_positive') ?>">
                <div><strong><?= gov_ops_h((string) $metrics['live_submit_ready']) ?></strong><span>Live-submit ready</span></div>
                <p class="muted">Best proof state. Gate remains closed.</p>
            </article>
            <article class="card metric <?= gov_v3_metric_class((int) $metrics['blocked'], 'bad_when_positive') ?>">
                <div><strong><?= gov_ops_h((string) $metrics['blocked']) ?></strong><span>Blocked rows</span></div>
                <p class="muted">Safe state; inspect last_error if a new test blocks.</p>
            </article>
        </section>

        <section class="grid two">
            <article class="card">
                <h2>V3 Queue Snapshot</h2>
                <ul class="status-list">
                    <li><span>Queue table</span><strong><?= gov_v3_status_badge((bool) $data['queue_table_exists'], 'present', 'missing') ?></strong></li>
                    <li><span>Total V3 rows</span><strong><?= gov_ops_h((string) $metrics['total']) ?></strong></li>
                    <li><span>Active rows</span><strong><?= gov_ops_h((string) $metrics['active']) ?></strong></li>
                    <li><span>Submitted rows</span><strong><?= gov_ops_h((string) $metrics['submitted']) ?></strong></li>
                    <li><span>Expired blocked rows</span><strong><?= gov_ops_h((string) $metrics['expired_blocked']) ?></strong></li>
                    <li><span>Verified starting options</span><strong><?= gov_ops_h((string) $metrics['verified_starting_options']) ?></strong></li>
                </ul>
            </article>

            <article class="card">
                <h2>Live Submit Gate</h2>
                <ul class="status-list">
                    <li><span>Config exists</span><strong><?= gov_v3_status_badge((bool) $liveConfig['exists']) ?></strong></li>
                    <li><span>Config loaded</span><strong><?= gov_v3_status_badge((bool) $liveConfig['loaded']) ?></strong></li>
                    <li><span>Enabled</span><strong><?= (bool) $liveConfig['enabled'] ? gov_ops_badge('yes', 'bad') : gov_ops_badge('no', 'good') ?></strong></li>
                    <li><span>Mode</span><strong><?= gov_ops_badge((string) $liveConfig['mode'], ((string) $liveConfig['mode'] === 'disabled') ? 'good' : 'warn') ?></strong></li>
                    <li><span>Adapter</span><strong><?= gov_ops_badge((string) $liveConfig['adapter'], ((string) $liveConfig['adapter'] === 'disabled') ? 'good' : 'warn') ?></strong></li>
                    <li><span>Hard enable live submit</span><strong><?= (bool) $liveConfig['hard_enable_live_submit'] ? gov_ops_badge('yes', 'bad') : gov_ops_badge('no', 'good') ?></strong></li>
                    <li><span>OK for future live submit</span><strong><?= (bool) $liveConfig['ok_for_future_live_submit'] ? gov_ops_badge('yes', 'bad') : gov_ops_badge('no', 'good') ?></strong></li>
                </ul>
                <?php if (!empty($liveConfig['error'])): ?>
                    <p class="muted"><strong>Config note:</strong> <?= gov_ops_h((string) $liveConfig['error']) ?></p>
                <?php endif; ?>
            </article>
        </section>

        <section class="grid three">
            <article class="card">
                <h2>Current Test</h2>
                <p>Keep these two pages open while waiting for the next future-safe Bolt pre-ride email.</p>
                <div class="link-grid">
                    <a class="link-card" href="/ops/pre-ride-email-v3-queue-watch.php"><strong>Queue Watch</strong><span>Main live view for the pending test.</span></a>
                    <a class="link-card" href="/ops/pre-ride-email-v3-fast-pipeline-pulse.php"><strong>Pulse Runner</strong><span>Confirms pulse cycles and pipeline status.</span></a>
                </div>
                <h3>Manual proof command</h3>
                <pre>php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_fast_pipeline_pulse.php --limit=50 --cycles=3 --sleep=2 --commit</pre>
            </article>

            <article class="card">
                <h2>Safety Guards</h2>
                <div class="link-grid">
                    <a class="link-card" href="/ops/pre-ride-email-v3-starting-point-guard.php"><strong>Starting-Point Guard</strong><span>Validates lessor and EDXEIX starting point.</span></a>
                    <a class="link-card" href="/ops/pre-ride-email-v3-expiry-guard.php"><strong>Expiry Guard</strong><span>Blocks old/past/expired queue rows.</span></a>
                    <a class="link-card" href="/ops/pre-ride-email-v3-live-readiness.php"><strong>Live Readiness</strong><span>Shows future live-submit readiness only.</span></a>
                    <a class="link-card" href="/ops/pre-ride-email-v3-live-payload-audit.php"><strong>Payload Audit</strong><span>Audit payload before any future adapter work.</span></a>
                </div>
            </article>

            <article class="card">
                <h2>Known Safety Facts</h2>
                <ul class="status-list">
                    <li><span>Lessor 2307 / ΧΩΡΑ ΜΥΚΟΝΟΥ</span><strong>1455969</strong></li>
                    <li><span>Lessor 2307 / ΕΠΑΝΩ ΔΙΑΚΟΦΤΗΣ</span><strong>9700559</strong></li>
                    <li><span>Invalid old starting point</span><strong>6467495</strong></li>
                    <li><span>EMT8640</span><strong><?= gov_ops_badge('permanently exempt', 'warn') ?></strong></li>
                    <li><span>Live adapter</span><strong><?= gov_ops_badge('disabled', 'good') ?></strong></li>
                </ul>
            </article>
        </section>

        <?php if (!empty($data['warnings'])): ?>
            <section class="notice warning">
                <strong>Dashboard warnings</strong>
                <ul>
                    <?php foreach ($data['warnings'] as $warning): ?>
                        <li><?= gov_ops_h((string) $warning) ?></li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endif; ?>

        <section class="card">
            <h2>Latest V3 Queue Rows</h2>
            <?php if (empty($data['latest_rows'])): ?>
                <p>No queue rows are available to display.</p>
            <?php else: ?>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Status</th>
                                <th>Customer</th>
                                <th>Pickup</th>
                                <th>Driver</th>
                                <th>Vehicle</th>
                                <th>Lessor</th>
                                <th>Driver ID</th>
                                <th>Vehicle ID</th>
                                <th>Starting Point</th>
                                <th>Last Error</th>
                                <th>Created</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($data['latest_rows'] as $row): ?>
                            <?php
                            $status = (string) ($row['queue_status'] ?? 'unknown');
                            $statusType = match ($status) {
                                'live_submit_ready', 'submit_dry_run_ready' => 'good',
                                'blocked' => 'bad',
                                'submitted' => 'info',
                                default => 'neutral',
                            };
                            ?>
                            <tr>
                                <td class="nowrap"><?= gov_ops_h((string) ($row['id'] ?? '')) ?></td>
                                <td><?= gov_ops_badge($status, $statusType) ?></td>
                                <td><?= gov_ops_h((string) ($row['customer_name'] ?? '')) ?></td>
                                <td class="nowrap"><?= gov_ops_h((string) ($row['pickup_datetime'] ?? '')) ?></td>
                                <td><?= gov_ops_h((string) ($row['driver_name'] ?? '')) ?></td>
                                <td><?= gov_ops_h((string) ($row['vehicle_plate'] ?? '')) ?></td>
                                <td><?= gov_ops_h((string) ($row['lessor_id'] ?? '')) ?></td>
                                <td><?= gov_ops_h((string) ($row['driver_id'] ?? '')) ?></td>
                                <td><?= gov_ops_h((string) ($row['vehicle_id'] ?? '')) ?></td>
                                <td><?= gov_ops_h((string) ($row['starting_point_id'] ?? '')) ?></td>
                                <td><?= gov_ops_h((string) ($row['last_error'] ?? '')) ?></td>
                                <td class="nowrap"><?= gov_ops_h((string) ($row['created_at'] ?? '')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <section class="grid two">
            <article class="card">
                <h2>Proposed Ops Sitemap</h2>
                <p>This dashboard is the first additive page in the new structure. The full proposed sitemap is included in <code>docs/OPS_SITEMAP_V3.md</code>.</p>
                <div class="link-grid">
                    <a class="link-card" href="/ops/index.php"><strong>Operations Home</strong><span>Future master console.</span></a>
                    <a class="link-card" href="/ops/pre-ride-email-v3-dashboard.php"><strong>V3 Automation</strong><span>Current control center.</span></a>
                    <a class="link-card" href="/ops/pre-ride-email-v3-live-submit-gate.php"><strong>Live Submit Locked</strong><span>Gate visibility only.</span></a>
                    <a class="link-card" href="/ops/readiness.php"><strong>Bolt Bridge</strong><span>Legacy/current bridge visibility.</span></a>
                </div>
            </article>

            <article class="card">
                <h2>Next Safe UI Steps</h2>
                <ol>
                    <li>Confirm this dashboard loads correctly.</li>
                    <li>Make <code>/ops/index.php</code> point clearly to this V3 control center.</li>
                    <li>Gradually include <code>_ops-nav.php</code> in existing V3 pages.</li>
                    <li>Add a mappings/reference-data section after the future-safe test proof.</li>
                </ol>
                <p><strong>No live-submit change is included in this patch.</strong></p>
            </article>
        </section>
    </main>
</div>
</body>
</html>
