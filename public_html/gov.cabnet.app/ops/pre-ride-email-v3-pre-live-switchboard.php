<?php

declare(strict_types=1);

/**
 * V3 Pre-Live Switchboard — direct DB renderer
 * Version: v3.0.65-v3-pre-live-switchboard-web-direct-db-fix
 *
 * Read-only Ops page. No shell_exec/exec, no Bolt, no EDXEIX, no AADE,
 * no DB writes, no queue mutation, no production submission tables, no V0 changes.
 */

const PRV3_SWITCHBOARD_VERSION = 'v3.0.65-v3-pre-live-switchboard-web-direct-db-fix';
const PRV3_APPROVAL_SCOPE = 'closed_gate_rehearsal_only';
const PRV3_APP_ROOT = '/home/cabnet/gov.cabnet.app_app';
const PRV3_CONFIG_ROOT = '/home/cabnet/gov.cabnet.app_config';

if (!headers_sent()) {
    header('Content-Type: text/html; charset=UTF-8');
}

date_default_timezone_set('Europe/Athens');

/** @return string */
function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** @return string */
function yn($value): string
{
    return !empty($value) ? 'yes' : 'no';
}

/** @return string */
function badge(string $text, string $type = 'neutral'): string
{
    $classes = [
        'good' => 'badge good',
        'bad' => 'badge bad',
        'warn' => 'badge warn',
        'neutral' => 'badge neutral',
        'dark' => 'badge dark',
    ];
    return '<span class="' . h($classes[$type] ?? $classes['neutral']) . '">' . h($text) . '</span>';
}

/** @return array<string,mixed> */
function result_base(): array
{
    return [
        'ok' => false,
        'version' => PRV3_SWITCHBOARD_VERSION,
        'mode' => 'read_only_web_direct_db_switchboard',
        'started_at' => date('c'),
        'safety' => 'No Bolt call. No EDXEIX call. No AADE call. No DB writes. No queue status changes. No production submission tables. V0 untouched.',
        'events' => [],
        'final_blocks' => [],
    ];
}

/** @param array<string,mixed> $report */
function add_event(array &$report, string $level, string $message): void
{
    $report['events'][] = ['level' => $level, 'message' => $message];
}

function db_connect(array &$report): ?mysqli
{
    $bootstrap = PRV3_APP_ROOT . '/src/bootstrap.php';
    if (!is_file($bootstrap) || !is_readable($bootstrap)) {
        add_event($report, 'error', 'Bootstrap not readable: ' . $bootstrap);
        return null;
    }

    try {
        $ctx = require $bootstrap;
        if (!is_array($ctx) || !isset($ctx['db']) || !is_object($ctx['db']) || !method_exists($ctx['db'], 'connection')) {
            add_event($report, 'error', 'Bootstrap did not return a usable database object.');
            return null;
        }
        $conn = $ctx['db']->connection();
        if (!$conn instanceof mysqli) {
            add_event($report, 'error', 'Database connection is not mysqli.');
            return null;
        }
        $conn->set_charset('utf8mb4');
        return $conn;
    } catch (Throwable $e) {
        add_event($report, 'error', 'Database bootstrap failed: ' . $e->getMessage());
        return null;
    }
}

function fetch_one(mysqli $db, string $sql, array $params = [], string $types = ''): ?array
{
    $rows = fetch_all($db, $sql, $params, $types);
    return $rows[0] ?? null;
}

function fetch_all(mysqli $db, string $sql, array $params = [], string $types = ''): array
{
    $stmt = $db->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException('prepare failed: ' . $db->error);
    }
    if ($params !== []) {
        if ($types === '') {
            foreach ($params as $p) {
                $types .= is_int($p) ? 'i' : (is_float($p) ? 'd' : 's');
            }
        }
        $bind = [$types];
        foreach ($params as $i => $p) {
            $bind[] = &$params[$i];
        }
        call_user_func_array([$stmt, 'bind_param'], $bind);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    if (!$res) {
        return [];
    }
    return $res->fetch_all(MYSQLI_ASSOC);
}

function table_exists(mysqli $db, string $table): bool
{
    $row = fetch_one(
        $db,
        'SELECT COUNT(*) AS c FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1',
        [$table]
    );
    return (int)($row['c'] ?? 0) > 0;
}

/** @return array<string,bool> */
function table_columns(mysqli $db, string $table): array
{
    $rows = fetch_all(
        $db,
        'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
        [$table]
    );
    $out = [];
    foreach ($rows as $row) {
        $name = (string)($row['COLUMN_NAME'] ?? '');
        if ($name !== '') {
            $out[$name] = true;
        }
    }
    return $out;
}

/** @return array<string,mixed> */
function load_gate(): array
{
    $path = PRV3_CONFIG_ROOT . '/pre_ride_email_v3_live_submit.php';
    $out = [
        'loaded' => false,
        'exists' => is_file($path),
        'readable' => is_readable($path),
        'config_path' => $path,
        'enabled' => false,
        'mode' => '',
        'adapter' => '',
        'hard_enable_live_submit' => false,
        'acknowledgement_phrase_present' => false,
        'blocks' => [],
    ];

    if (!$out['exists'] || !$out['readable']) {
        $out['blocks'][] = 'master_gate: config missing or unreadable';
        return $out;
    }

    try {
        $config = require $path;
        if (!is_array($config)) {
            $out['blocks'][] = 'master_gate: config did not return an array';
            return $out;
        }
        $out['loaded'] = true;
        $out['enabled'] = !empty($config['enabled']);
        $out['mode'] = trim((string)($config['mode'] ?? ''));
        $out['adapter'] = trim((string)($config['adapter'] ?? ''));
        $out['hard_enable_live_submit'] = !empty($config['hard_enable_live_submit']);
        $ack = '';
        foreach (['acknowledgement_phrase', 'required_acknowledgement_phrase', 'operator_acknowledgement_phrase', 'acknowledgement'] as $key) {
            if (isset($config[$key]) && trim((string)$config[$key]) !== '') {
                $ack = trim((string)$config[$key]);
                break;
            }
        }
        $out['acknowledgement_phrase_present'] = $ack !== '';
    } catch (Throwable $e) {
        $out['blocks'][] = 'master_gate: config load failed: ' . $e->getMessage();
        return $out;
    }

    if (!$out['enabled']) {
        $out['blocks'][] = 'master_gate: enabled is false';
    }
    if ($out['mode'] !== 'live') {
        $out['blocks'][] = 'master_gate: mode is not live';
    }
    if ($out['adapter'] !== 'edxeix_live') {
        $out['blocks'][] = 'master_gate: adapter is not edxeix_live';
    }
    if (!$out['hard_enable_live_submit']) {
        $out['blocks'][] = 'master_gate: hard_enable_live_submit is false';
    }
    if (!$out['acknowledgement_phrase_present']) {
        $out['blocks'][] = 'master_gate: required acknowledgement phrase is not present';
    }

    return $out;
}

/** @return array<string,mixed>|null */
function selected_row(mysqli $db, ?int $queueId): ?array
{
    if (!table_exists($db, 'pre_ride_email_v3_queue')) {
        return null;
    }
    if ($queueId !== null && $queueId > 0) {
        return fetch_one($db, 'SELECT * FROM pre_ride_email_v3_queue WHERE id = ? LIMIT 1', [$queueId], 'i');
    }
    $row = fetch_one(
        $db,
        "SELECT * FROM pre_ride_email_v3_queue WHERE queue_status = 'live_submit_ready' ORDER BY pickup_datetime ASC, id ASC LIMIT 1"
    );
    if ($row) {
        return $row;
    }
    return fetch_one($db, 'SELECT * FROM pre_ride_email_v3_queue ORDER BY id DESC LIMIT 1');
}

function minutes_until(?string $dt): ?int
{
    $dt = trim((string)$dt);
    if ($dt === '') {
        return null;
    }
    try {
        $tz = new DateTimeZone('Europe/Athens');
        $target = new DateTimeImmutable($dt, $tz);
        $now = new DateTimeImmutable('now', $tz);
        return (int)floor(($target->getTimestamp() - $now->getTimestamp()) / 60);
    } catch (Throwable $e) {
        return null;
    }
}

/** @return array{complete:bool,missing:array<int,string>,values:array<string,string>} */
function payload_check(array $row): array
{
    $fields = [
        'lessor_id', 'driver_id', 'vehicle_id', 'starting_point_id', 'customer_name', 'customer_phone',
        'pickup_datetime', 'estimated_end_datetime', 'pickup_address', 'dropoff_address', 'price_amount', 'payload_json',
    ];
    $values = [];
    $missing = [];
    foreach ($fields as $key) {
        $v = trim((string)($row[$key] ?? ''));
        $values[$key] = $v;
        if ($v === '') {
            $missing[] = $key;
        }
    }
    return ['complete' => $missing === [], 'missing' => $missing, 'values' => $values];
}

/** @return array<string,mixed> */
function starting_point_check(mysqli $db, array $row): array
{
    $out = ['ok' => false, 'label' => '', 'reason' => 'not checked'];
    if (!table_exists($db, 'pre_ride_email_v3_starting_point_options')) {
        $out['reason'] = 'starting point options table missing';
        return $out;
    }
    $lessor = trim((string)($row['lessor_id'] ?? ''));
    $start = trim((string)($row['starting_point_id'] ?? ''));
    if ($lessor === '' || $start === '') {
        $out['reason'] = 'lessor or starting point missing';
        return $out;
    }
    $opt = fetch_one(
        $db,
        'SELECT label FROM pre_ride_email_v3_starting_point_options WHERE edxeix_lessor_id = ? AND edxeix_starting_point_id = ? AND is_active = 1 LIMIT 1',
        [$lessor, $start]
    );
    if ($opt) {
        $out['ok'] = true;
        $out['label'] = (string)($opt['label'] ?? '');
        $out['reason'] = 'operator_verified';
    } else {
        $out['reason'] = 'no active operator-verified starting point option found';
    }
    return $out;
}

/** @return array<string,mixed> */
function approval_check(mysqli $db, array $row): array
{
    $out = ['table_exists' => false, 'valid' => false, 'count' => 0, 'latest' => null, 'reason' => 'approval table missing'];
    if (!table_exists($db, 'pre_ride_email_v3_live_submit_approvals')) {
        return $out;
    }
    $out['table_exists'] = true;
    $cols = table_columns($db, 'pre_ride_email_v3_live_submit_approvals');
    $queueId = (string)($row['id'] ?? '');
    $dedupeKey = (string)($row['dedupe_key'] ?? '');

    $where = [];
    $params = [];
    $types = '';
    if (isset($cols['queue_id']) && $queueId !== '') {
        $where[] = 'queue_id = ?';
        $params[] = (int)$queueId;
        $types .= 'i';
    }
    if (isset($cols['dedupe_key']) && $dedupeKey !== '') {
        $where[] = 'dedupe_key = ?';
        $params[] = $dedupeKey;
        $types .= 's';
    }
    if ($where === []) {
        $out['reason'] = 'approval table has no usable queue_id/dedupe_key column';
        return $out;
    }

    $filters = [];
    if (isset($cols['approval_status'])) {
        $filters[] = "approval_status IN ('approved','valid','active')";
    }
    if (isset($cols['approval_scope'])) {
        $filters[] = 'approval_scope = ?';
        $params[] = PRV3_APPROVAL_SCOPE;
        $types .= 's';
    }
    if (isset($cols['revoked_at'])) {
        $filters[] = '(revoked_at IS NULL OR revoked_at = \'\')';
    }
    if (isset($cols['expires_at'])) {
        $filters[] = '(expires_at IS NULL OR expires_at >= NOW())';
    }

    $sql = 'SELECT * FROM pre_ride_email_v3_live_submit_approvals WHERE (' . implode(' OR ', $where) . ')';
    if ($filters !== []) {
        $sql .= ' AND ' . implode(' AND ', $filters);
    }
    $sql .= ' ORDER BY id DESC LIMIT 1';

    $latest = fetch_one($db, $sql, $params, $types);

    $countRow = fetch_one(
        $db,
        'SELECT COUNT(*) AS c FROM pre_ride_email_v3_live_submit_approvals WHERE (' . implode(' OR ', $where) . ')',
        array_slice($params, 0, count($where)),
        substr($types, 0, count($where))
    );
    $out['count'] = (int)($countRow['c'] ?? 0);

    if (!$latest) {
        $out['reason'] = 'no valid approval found';
        return $out;
    }
    $out['valid'] = true;
    $out['latest'] = $latest;
    $out['reason'] = 'valid closed-gate rehearsal approval found';
    return $out;
}

/** @return array<string,mixed> */
function adapter_check(array $gate): array
{
    $selected = trim((string)($gate['adapter'] ?? ''));
    $files = [
        'interface' => PRV3_APP_ROOT . '/src/BoltMailV3/LiveSubmitAdapterV3.php',
        'disabled' => PRV3_APP_ROOT . '/src/BoltMailV3/DisabledLiveSubmitAdapterV3.php',
        'dry_run' => PRV3_APP_ROOT . '/src/BoltMailV3/DryRunLiveSubmitAdapterV3.php',
        'edxeix_live' => PRV3_APP_ROOT . '/src/BoltMailV3/EdxeixLiveSubmitAdapterV3.php',
    ];
    $out = [
        'selected_adapter' => $selected,
        'expected_adapter' => 'edxeix_live',
        'files' => [],
        'class_exists' => false,
        'instantiated' => false,
        'name' => '',
        'is_live_capable' => false,
        'reason' => '',
    ];
    foreach ($files as $key => $path) {
        $out['files'][$key] = ['path' => $path, 'exists' => is_file($path), 'readable' => is_readable($path)];
    }
    if ($selected !== 'edxeix_live') {
        $out['reason'] = 'selected adapter is not edxeix_live';
        return $out;
    }
    try {
        require_once $files['interface'];
        require_once $files['edxeix_live'];
        $class = 'Bridge\\BoltMailV3\\EdxeixLiveSubmitAdapterV3';
        $out['class_exists'] = class_exists($class);
        if (!$out['class_exists']) {
            $out['reason'] = 'edxeix_live adapter class missing';
            return $out;
        }
        $adapter = new $class();
        $out['instantiated'] = true;
        $out['name'] = method_exists($adapter, 'name') ? (string)$adapter->name() : '';
        $out['is_live_capable'] = method_exists($adapter, 'isLiveCapable') && $adapter->isLiveCapable();
        if (!$out['is_live_capable']) {
            $out['reason'] = 'adapter is not live-capable';
        }
    } catch (Throwable $e) {
        $out['reason'] = 'adapter check failed: ' . $e->getMessage();
    }
    return $out;
}

/** @return array<string,mixed> */
function package_artifacts(?array $row): array
{
    $dir = PRV3_APP_ROOT . '/storage/artifacts/v3_live_submit_packages';
    $out = ['artifact_dir' => $dir, 'artifact_dir_exists' => is_dir($dir), 'artifact_dir_writable' => is_writable($dir), 'queue_artifact_count' => 0, 'latest_queue_artifacts' => []];
    if (!$row || !$out['artifact_dir_exists']) {
        return $out;
    }
    $id = (string)($row['id'] ?? '');
    if ($id === '') {
        return $out;
    }
    $files = [];
    foreach (scandir($dir) ?: [] as $f) {
        if (strpos($f, 'queue_' . $id . '_') === 0) {
            $files[] = $f;
        }
    }
    rsort($files, SORT_NATURAL);
    $out['queue_artifact_count'] = count($files);
    $out['latest_queue_artifacts'] = array_slice($files, 0, 8);
    return $out;
}

/** @return array<string,mixed> */
function build_report(?int $requestedQueueId = null): array
{
    $report = result_base();
    $db = db_connect($report);
    if (!$db) {
        $report['final_blocks'][] = 'system: database unavailable';
        return $report;
    }
    $report['database'] = ['connected' => true];

    try {
        $gate = load_gate();
        $report['gate'] = $gate;
        foreach ((array)($gate['blocks'] ?? []) as $b) {
            $report['final_blocks'][] = (string)$b;
        }

        $row = selected_row($db, $requestedQueueId);
        $report['selected_queue_row'] = $row;
        if ($row) {
            $row['minutes_until_now'] = minutes_until((string)($row['pickup_datetime'] ?? ''));
            $report['selected_queue_row'] = $row;
        }

        $payload = $row ? payload_check($row) : ['complete' => false, 'missing' => ['no_selected_row'], 'values' => []];
        $report['payload'] = $payload;
        if (!$payload['complete']) {
            $report['final_blocks'][] = 'payload: missing ' . implode(', ', $payload['missing']);
        }

        $start = $row ? starting_point_check($db, $row) : ['ok' => false, 'label' => '', 'reason' => 'no selected row'];
        $report['starting_point'] = $start;
        if (empty($start['ok'])) {
            $report['final_blocks'][] = 'starting_point: not verified';
        }

        $approval = $row ? approval_check($db, $row) : ['valid' => false, 'reason' => 'no selected row', 'count' => 0, 'latest' => null, 'table_exists' => false];
        $report['approval'] = $approval;
        if (empty($approval['valid'])) {
            $report['final_blocks'][] = 'approval: no valid closed-gate rehearsal approval found';
        }

        $adapter = adapter_check($gate);
        $report['adapter'] = $adapter;
        if (($gate['adapter'] ?? '') !== 'edxeix_live') {
            $report['final_blocks'][] = 'adapter: selected adapter is not edxeix_live';
        } elseif (empty($adapter['is_live_capable'])) {
            $report['final_blocks'][] = 'adapter: adapter is not live-capable';
        }

        $report['package_export'] = package_artifacts($row);

        if (!$row) {
            $report['final_blocks'][] = 'queue: no selected row';
        } else {
            if ((string)($row['queue_status'] ?? '') !== 'live_submit_ready') {
                $report['final_blocks'][] = 'queue: row is not live_submit_ready';
            }
            $m = $row['minutes_until_now'] ?? null;
            if (!is_int($m) || $m < 0) {
                $report['final_blocks'][] = 'queue: pickup is not future-safe';
            }
        }

        $report['final_blocks'] = array_values(array_unique($report['final_blocks']));
        $report['eligible_for_live_submit_now'] = $report['final_blocks'] === [];
        $report['ok'] = false;
        $report['finished_at'] = date('c');
        return $report;
    } catch (Throwable $e) {
        add_event($report, 'error', $e->getMessage());
        $report['final_blocks'][] = 'system: ' . $e->getMessage();
        $report['finished_at'] = date('c');
        return $report;
    }
}

$queueId = null;
if (isset($_GET['queue_id']) && preg_match('/^\d+$/', (string)$_GET['queue_id'])) {
    $queueId = (int)$_GET['queue_id'];
}
$report = build_report($queueId);
$row = is_array($report['selected_queue_row'] ?? null) ? $report['selected_queue_row'] : [];
$gate = is_array($report['gate'] ?? null) ? $report['gate'] : [];
$approval = is_array($report['approval'] ?? null) ? $report['approval'] : [];
$payload = is_array($report['payload'] ?? null) ? $report['payload'] : [];
$start = is_array($report['starting_point'] ?? null) ? $report['starting_point'] : [];
$adapter = is_array($report['adapter'] ?? null) ? $report['adapter'] : [];
$pkg = is_array($report['package_export'] ?? null) ? $report['package_export'] : [];
$events = is_array($report['events'] ?? null) ? $report['events'] : [];
$blocks = is_array($report['final_blocks'] ?? null) ? $report['final_blocks'] : [];

$statusBadges = [];
$statusBadges[] = badge('LIVE BLOCKED', 'bad');
$statusBadges[] = !empty($approval['valid']) ? badge('APPROVAL VALID', 'good') : badge('NO VALID APPROVAL', 'bad');
$statusBadges[] = !empty($start['ok']) ? badge('START VERIFIED', 'good') : badge('START NOT VERIFIED', 'bad');
$statusBadges[] = !empty($adapter['is_live_capable']) ? badge('ADAPTER LIVE-CAPABLE', 'warn') : badge('ADAPTER NOT LIVE', 'bad');

?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>V3 Pre-Live Switchboard</title>
<style>
:root{--bg:#eef2f7;--card:#fff;--nav:#303a63;--ink:#071d49;--muted:#496086;--line:#d8e0ee;--green:#2faa61;--red:#b42318;--orange:#d97706;--blue:#5361b8;--dark:#15204a}
*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--ink);font-family:Arial,Helvetica,sans-serif;font-size:14px}.layout{display:grid;grid-template-columns:260px 1fr;min-height:100vh}.side{background:var(--nav);color:#fff;padding:22px 18px}.side h2{font-size:18px;margin:0 0 14px}.side p{line-height:1.4;margin:0 0 20px;color:#e5eaff}.side a{display:block;color:#fff;text-decoration:none;padding:10px;border-radius:8px;margin:4px 0}.side a.active,.side a:hover{background:rgba(255,255,255,.16)}.main{padding:24px}.card{background:var(--card);border:1px solid var(--line);border-radius:14px;padding:18px;margin-bottom:16px;box-shadow:0 4px 14px rgba(0,0,0,.04)}.hero{border-left:5px solid var(--orange)}h1{font-size:30px;margin:8px 0 8px}.sub{color:var(--muted);line-height:1.45}.badge{display:inline-block;font-weight:700;border-radius:999px;padding:6px 10px;font-size:12px;margin:2px 4px 2px 0}.good{background:#dff6e6;color:#086b25}.bad{background:#ffe3e1;color:#a4161a}.warn{background:#fff1cc;color:#8a5100}.neutral{background:#edf2ff;color:#293b8f}.dark{background:#17244d;color:#fff}.btn{display:inline-block;border-radius:9px;padding:11px 14px;background:var(--blue);color:#fff;text-decoration:none;font-weight:700;margin:8px 8px 0 0}.btn.green{background:var(--green)}.btn.dark{background:var(--dark)}.grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}.grid2{display:grid;grid-template-columns:1fr 1fr;gap:12px}.metric .num{font-size:28px;font-weight:800}.metric .label{color:var(--muted);font-size:12px}.table{width:100%;border-collapse:collapse}.table th,.table td{border-bottom:1px solid var(--line);text-align:left;padding:10px;vertical-align:top}.table th{background:#f5f7fb;color:#43547d;width:34%}.blocks{border-color:#ffc879;background:#fff8ed}.blocks h3{margin-top:0;color:#7a3f00}.events{background:#fff}.small{font-size:12px;color:var(--muted)}code{white-space:pre-wrap;word-break:break-word}.mono{font-family:ui-monospace,SFMono-Regular,Consolas,monospace}.list{margin:0;padding-left:18px}.nowrap{white-space:nowrap}@media(max-width:900px){.layout{grid-template-columns:1fr}.side{position:relative}.grid,.grid2{grid-template-columns:1fr}.main{padding:14px}}
</style>
</head>
<body>
<div class="layout">
  <aside class="side">
    <h2>V3 Automation</h2>
    <p>Read-only pre-live switchboard. Live submit remains disabled.</p>
    <a class="active" href="/ops/pre-ride-email-v3-pre-live-switchboard.php">Pre-Live Switchboard</a>
    <a href="/ops/pre-ride-email-v3-proof.php">Proof Dashboard</a>
    <a href="/ops/pre-ride-email-v3-live-package-export.php">Package Export</a>
    <a href="/ops/pre-ride-email-v3-operator-approval-workflow.php">Operator Approval</a>
    <a href="/ops/pre-ride-email-v3-live-adapter-kill-switch-check.php">Kill-Switch Check</a>
    <a href="/ops/pre-ride-email-v3-monitor.php">Compact Monitor</a>
  </aside>
  <main class="main">
    <section class="card hero">
      <div><?= implode('', $statusBadges) ?></div>
      <h1>V3 Pre-Live Switchboard</h1>
      <div class="sub"><?= h($report['safety'] ?? '') ?></div>
      <a class="btn green" href="?queue_id=<?= h($row['id'] ?? '') ?>">Refresh selected row</a>
      <a class="btn" href="/ops/pre-ride-email-v3-closed-gate-adapter-diagnostics.php">Adapter Diagnostics</a>
      <a class="btn dark" href="/ops/pre-ride-email-v3-storage-check.php">Storage Check</a>
    </section>

    <?php if ($events !== []): ?>
    <section class="card events">
      <h3>Events</h3>
      <ul>
        <?php foreach ($events as $event): ?><li><?= h(($event['level'] ?? 'event') . ': ' . ($event['message'] ?? '')) ?></li><?php endforeach; ?>
      </ul>
    </section>
    <?php endif; ?>

    <section class="grid">
      <div class="card metric"><div class="num"><?= h(($gate['mode'] ?? '-') . ' / ' . ($gate['adapter'] ?? '-')) ?></div><div class="label">Gate mode / adapter</div><div class="small">loaded=<?= h(yn($gate['loaded'] ?? false)) ?> enabled=<?= h(yn($gate['enabled'] ?? false)) ?> hard=<?= h(yn($gate['hard_enable_live_submit'] ?? false)) ?> ack=<?= h(yn($gate['acknowledgement_phrase_present'] ?? false)) ?></div></div>
      <div class="card metric"><div class="num">#<?= h($row['id'] ?? '-') ?></div><div class="label">Selected row</div><div class="small"><?= h($row['queue_status'] ?? '-') ?> · pickup <?= h($row['pickup_datetime'] ?? '-') ?> · minutes <?= h($row['minutes_until_now'] ?? '-') ?></div></div>
      <div class="card metric"><div class="num"><?= !empty($approval['valid']) ? 'valid' : 'not valid' ?></div><div class="label">Approval</div><div class="small"><?= h($approval['reason'] ?? '-') ?></div></div>
      <div class="card metric"><div class="num"><?= h($pkg['queue_artifact_count'] ?? 0) ?></div><div class="label">Package artifacts</div><div class="small">local package exports for selected row</div></div>
    </section>

    <section class="card blocks">
      <h3>Final blocks</h3>
      <?php if ($blocks === []): ?>
        <p><?= badge('NO BLOCKS REPORTED', 'warn') ?> This page is read-only and still does not submit.</p>
      <?php else: ?>
        <ul class="list"><?php foreach ($blocks as $b): ?><li><?= h($b) ?></li><?php endforeach; ?></ul>
      <?php endif; ?>
    </section>

    <section class="grid2">
      <div class="card">
        <h3>Queue Row</h3>
        <table class="table">
          <tr><th>Customer</th><td><?= h($row['customer_name'] ?? '') ?> / <?= h($row['customer_phone'] ?? '') ?></td></tr>
          <tr><th>Driver / Vehicle</th><td><?= h($row['driver_name'] ?? '') ?> / <?= h($row['vehicle_plate'] ?? '') ?></td></tr>
          <tr><th>EDXEIX IDs</th><td>lessor=<?= h($row['lessor_id'] ?? '') ?> driver=<?= h($row['driver_id'] ?? '') ?> vehicle=<?= h($row['vehicle_id'] ?? '') ?> start=<?= h($row['starting_point_id'] ?? '') ?></td></tr>
          <tr><th>Route</th><td><?= h($row['pickup_address'] ?? '') ?> → <?= h($row['dropoff_address'] ?? '') ?></td></tr>
          <tr><th>Last error</th><td><?= h($row['last_error'] ?? '') ?></td></tr>
        </table>
      </div>
      <div class="card">
        <h3>Checks</h3>
        <table class="table">
          <tr><th>Payload</th><td><?= !empty($payload['complete']) ? badge('complete', 'good') : badge('missing', 'bad') ?> <?= h(implode(', ', (array)($payload['missing'] ?? [])) ?: 'none missing') ?></td></tr>
          <tr><th>Starting point</th><td><?= !empty($start['ok']) ? badge('verified', 'good') : badge('not verified', 'bad') ?> <?= h($start['label'] ?? '') ?></td></tr>
          <tr><th>Adapter</th><td><?= h($adapter['selected_adapter'] ?? '') ?> · expected <?= h($adapter['expected_adapter'] ?? '') ?> · live-capable=<?= h(yn($adapter['is_live_capable'] ?? false)) ?> · <?= h($adapter['reason'] ?? '') ?></td></tr>
          <tr><th>Approval</th><td><?= !empty($approval['valid']) ? badge('valid', 'good') : badge('not valid', 'bad') ?> count=<?= h($approval['count'] ?? 0) ?> <?= h($approval['reason'] ?? '') ?></td></tr>
          <tr><th>Version</th><td><?= h($report['version'] ?? '') ?></td></tr>
        </table>
      </div>
    </section>

    <section class="card">
      <h3>Latest package artifacts</h3>
      <?php $artifacts = (array)($pkg['latest_queue_artifacts'] ?? []); ?>
      <?php if ($artifacts === []): ?><p class="small">No package artifacts found for selected row.</p><?php else: ?>
        <ul class="list mono"><?php foreach ($artifacts as $f): ?><li><?= h($f) ?></li><?php endforeach; ?></ul>
      <?php endif; ?>
    </section>
  </main>
</div>
</body>
</html>
