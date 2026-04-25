<?php
/**
 * gov.cabnet.app — Mapping Coverage + EDXEIX ID Editor
 *
 * Operations page for Bolt → EDXEIX driver/vehicle mapping coverage.
 * GET requests are read-only.
 * POST requests may update only edxeix_driver_id or edxeix_vehicle_id on existing mapping rows.
 * Does not call Bolt, does not call EDXEIX, and does not modify bookings/jobs.
 */

declare(strict_types=1);

require_once '/home/cabnet/gov.cabnet.app_app/lib/bolt_sync_lib.php';

function map_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function map_value(array $row, array $keys, $default = '')
{
    foreach ($keys as $key) {
        if (array_key_exists($key, $row) && $row[$key] !== null && $row[$key] !== '') {
            return $row[$key];
        }
    }
    return $default;
}

function map_has_col(array $columns, string $column): bool
{
    return isset($columns[$column]);
}

function map_first_col(array $columns, array $candidates): ?string
{
    foreach ($candidates as $candidate) {
        if (map_has_col($columns, $candidate)) {
            return $candidate;
        }
    }
    return null;
}

function map_bool_param(string $key, bool $default = false): bool
{
    $value = $_GET[$key] ?? $_POST[$key] ?? ($default ? '1' : '0');
    if (is_bool($value)) {
        return $value;
    }
    return in_array(strtolower((string)$value), ['1', 'true', 'yes', 'on'], true);
}

function map_request_param(string $key, string $default = ''): string
{
    $value = $_GET[$key] ?? $_POST[$key] ?? $default;
    $value = is_scalar($value) ? trim((string)$value) : $default;
    return mb_substr($value, 0, 120, 'UTF-8');
}

function map_post_param(string $key, string $default = ''): string
{
    $value = $_POST[$key] ?? $default;
    $value = is_scalar($value) ? trim((string)$value) : $default;
    return mb_substr($value, 0, 255, 'UTF-8');
}

function map_limit_param(): int
{
    $raw = $_GET['limit'] ?? $_POST['limit'] ?? '200';
    $value = filter_var($raw, FILTER_VALIDATE_INT, ['options' => ['default' => 200]]);
    return max(1, min(500, (int)$value));
}

function map_order_column(array $columns): string
{
    foreach (['last_seen_at', 'updated_at', 'created_at', 'id'] as $column) {
        if (map_has_col($columns, $column)) {
            return $column;
        }
    }
    return array_key_first($columns) ?: 'id';
}

function map_edxeix_column_for_table(string $table, array $columns): ?string
{
    if ($table === 'mapping_drivers') {
        return map_first_col($columns, ['edxeix_driver_id', 'driver_id']);
    }
    if ($table === 'mapping_vehicles') {
        return map_first_col($columns, ['edxeix_vehicle_id', 'vehicle_id']);
    }
    return null;
}

function map_table_rows(mysqli $db, string $table, string $view, string $query, int $limit): array
{
    if (!gov_bridge_table_exists($db, $table)) {
        return [];
    }

    $columns = gov_bridge_table_columns($db, $table);
    if (!$columns) {
        return [];
    }

    $edxeixCol = map_edxeix_column_for_table($table, $columns);
    $where = [];
    $params = [];

    if ($edxeixCol !== null) {
        $mappedExpr = '(' . gov_bridge_quote_identifier($edxeixCol) . ' IS NOT NULL AND ' . gov_bridge_quote_identifier($edxeixCol) . " <> '' AND " . gov_bridge_quote_identifier($edxeixCol) . ' <> 0)';
        if ($view === 'mapped') {
            $where[] = $mappedExpr;
        } elseif ($view === 'unmapped') {
            $where[] = 'NOT ' . $mappedExpr;
        }
    }

    if ($query !== '') {
        $searchCandidates = $table === 'mapping_drivers'
            ? ['external_driver_name', 'driver_name', 'external_driver_id', 'driver_external_id', 'driver_uuid', 'driver_phone', 'active_vehicle_uuid', 'active_vehicle_plate', 'source_system']
            : ['plate', 'vehicle_plate', 'external_vehicle_name', 'vehicle_name', 'vehicle_model', 'external_vehicle_id', 'vehicle_external_id', 'vehicle_uuid', 'source_system'];
        $parts = [];
        foreach ($searchCandidates as $candidate) {
            if (map_has_col($columns, $candidate)) {
                $parts[] = gov_bridge_quote_identifier($candidate) . ' LIKE ?';
                $params[] = '%' . $query . '%';
            }
        }
        if ($parts) {
            $where[] = '(' . implode(' OR ', $parts) . ')';
        }
    }

    $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
    $orderCol = map_order_column($columns);
    $sql = 'SELECT * FROM ' . gov_bridge_quote_identifier($table) . ' ' . $whereSql . ' ORDER BY ' . gov_bridge_quote_identifier($orderCol) . ' DESC LIMIT ' . (int)$limit;
    return gov_bridge_fetch_all($db, $sql, $params);
}

function map_table_stats(mysqli $db, string $table): array
{
    $out = [
        'exists' => false,
        'total' => 0,
        'mapped' => 0,
        'unmapped' => 0,
        'active' => 0,
        'inactive' => 0,
        'mapped_percent' => 0,
    ];

    if (!gov_bridge_table_exists($db, $table)) {
        return $out;
    }

    $columns = gov_bridge_table_columns($db, $table);
    $out['exists'] = true;
    $out['total'] = (int)(gov_bridge_fetch_one($db, 'SELECT COUNT(*) AS c FROM ' . gov_bridge_quote_identifier($table))['c'] ?? 0);

    $edxeixCol = map_edxeix_column_for_table($table, $columns);
    if ($edxeixCol !== null) {
        $out['mapped'] = (int)(gov_bridge_fetch_one(
            $db,
            'SELECT COUNT(*) AS c FROM ' . gov_bridge_quote_identifier($table) . ' WHERE ' . gov_bridge_quote_identifier($edxeixCol) . ' IS NOT NULL AND ' . gov_bridge_quote_identifier($edxeixCol) . " <> '' AND " . gov_bridge_quote_identifier($edxeixCol) . ' <> 0'
        )['c'] ?? 0);
    }

    if (map_has_col($columns, 'is_active')) {
        $out['active'] = (int)(gov_bridge_fetch_one($db, 'SELECT COUNT(*) AS c FROM ' . gov_bridge_quote_identifier($table) . ' WHERE is_active = 1')['c'] ?? 0);
        $out['inactive'] = max(0, $out['total'] - $out['active']);
    }

    $out['unmapped'] = max(0, $out['total'] - $out['mapped']);
    $out['mapped_percent'] = $out['total'] > 0 ? round(($out['mapped'] / $out['total']) * 100, 1) : 0;
    return $out;
}

function map_badge(string $text, string $type = 'neutral'): string
{
    return '<span class="badge badge-' . map_h($type) . '">' . map_h($text) . '</span>';
}

function map_yes_badge(bool $mapped): string
{
    return $mapped ? map_badge('mapped', 'good') : map_badge('unmapped', 'bad');
}

function map_is_mapped(array $row, array $keys): bool
{
    $value = map_value($row, $keys, '');
    return $value !== '' && $value !== '0' && $value !== 0;
}

function map_sanitize_driver(array $row): array
{
    $mapped = map_is_mapped($row, ['edxeix_driver_id', 'driver_id']);
    return [
        'id' => (int)map_value($row, ['id'], 0),
        'source_system' => (string)map_value($row, ['source_system', 'source_type', 'source'], ''),
        'external_driver_id' => (string)map_value($row, ['external_driver_id', 'driver_external_id', 'driver_uuid'], ''),
        'external_driver_name' => (string)map_value($row, ['external_driver_name', 'driver_name'], ''),
        'driver_phone' => (string)map_value($row, ['driver_phone', 'phone'], ''),
        'edxeix_driver_id' => (string)map_value($row, ['edxeix_driver_id', 'driver_id'], ''),
        'active_vehicle_uuid' => (string)map_value($row, ['active_vehicle_uuid'], ''),
        'active_vehicle_plate' => (string)map_value($row, ['active_vehicle_plate'], ''),
        'is_active' => map_value($row, ['is_active'], '1') !== '0',
        'is_mapped' => $mapped,
        'mapping_status' => $mapped ? 'mapped' : 'unmapped',
        'last_seen_at' => (string)map_value($row, ['last_seen_at', 'updated_at', 'created_at'], ''),
    ];
}

function map_sanitize_vehicle(array $row): array
{
    $mapped = map_is_mapped($row, ['edxeix_vehicle_id', 'vehicle_id']);
    return [
        'id' => (int)map_value($row, ['id'], 0),
        'source_system' => (string)map_value($row, ['source_system', 'source_type', 'source'], ''),
        'external_vehicle_id' => (string)map_value($row, ['external_vehicle_id', 'vehicle_external_id', 'vehicle_uuid'], ''),
        'plate' => (string)map_value($row, ['plate', 'vehicle_plate'], ''),
        'external_vehicle_name' => (string)map_value($row, ['external_vehicle_name', 'vehicle_name'], ''),
        'vehicle_model' => (string)map_value($row, ['vehicle_model', 'model'], ''),
        'edxeix_vehicle_id' => (string)map_value($row, ['edxeix_vehicle_id', 'vehicle_id'], ''),
        'is_active' => map_value($row, ['is_active'], '1') !== '0',
        'is_mapped' => $mapped,
        'mapping_status' => $mapped ? 'mapped' : 'unmapped',
        'last_seen_at' => (string)map_value($row, ['last_seen_at', 'updated_at', 'created_at'], ''),
    ];
}

function map_editor_enabled(mysqli $db): array
{
    $ok = gov_bridge_table_exists($db, 'mapping_update_audit');
    return [
        'enabled' => $ok,
        'reason' => $ok ? 'audit_table_present' : 'Run migration 2026_04_25_mapping_update_audit.sql before posting mapping edits.',
    ];
}

function map_validate_edxeix_id(string $value): string
{
    $value = trim($value);
    if (!preg_match('/^[1-9][0-9]{0,18}$/', $value)) {
        throw new RuntimeException('EDXEIX ID must be a positive numeric value.');
    }
    return $value;
}

function map_update_mapping(mysqli $db): array
{
    $result = [
        'ok' => false,
        'type' => 'bad',
        'message' => 'No update performed.',
    ];

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return $result;
    }

    $editor = map_editor_enabled($db);
    if (!$editor['enabled']) {
        throw new RuntimeException($editor['reason']);
    }

    $action = map_post_param('action');
    $confirm = map_post_param('confirm');
    $idRaw = map_post_param('row_id');
    $edxeixId = map_validate_edxeix_id(map_post_param('edxeix_id'));

    $rowId = filter_var($idRaw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if (!$rowId) {
        throw new RuntimeException('Invalid mapping row id.');
    }

    if ($action === 'update_driver') {
        $table = 'mapping_drivers';
        $field = 'edxeix_driver_id';
        $label = 'driver';
        $expectedConfirm = 'UPDATE DRIVER MAPPING';
    } elseif ($action === 'update_vehicle') {
        $table = 'mapping_vehicles';
        $field = 'edxeix_vehicle_id';
        $label = 'vehicle';
        $expectedConfirm = 'UPDATE VEHICLE MAPPING';
    } else {
        throw new RuntimeException('Invalid mapping update action.');
    }

    if ($confirm !== $expectedConfirm) {
        throw new RuntimeException('Confirmation phrase must be exactly: ' . $expectedConfirm);
    }

    if (!gov_bridge_table_exists($db, $table)) {
        throw new RuntimeException('Required table is missing: ' . $table);
    }

    $columns = gov_bridge_table_columns($db, $table);
    if (!map_has_col($columns, $field)) {
        throw new RuntimeException('Required mapping field is missing: ' . $field);
    }

    $before = gov_bridge_fetch_one($db, 'SELECT * FROM ' . gov_bridge_quote_identifier($table) . ' WHERE id = ? LIMIT 1', [(string)$rowId]);
    if (!$before) {
        throw new RuntimeException('Mapping row was not found.');
    }

    $oldValue = (string)($before[$field] ?? '');
    if ($oldValue === $edxeixId) {
        return [
            'ok' => true,
            'type' => 'warn',
            'message' => 'No change needed. Existing ' . $label . ' mapping already has EDXEIX ID ' . $edxeixId . '.',
        ];
    }

    $update = [$field => $edxeixId];
    if (map_has_col($columns, 'updated_at')) {
        $update['updated_at'] = date('Y-m-d H:i:s');
    }

    gov_bridge_update_row($db, $table, $update, 'id = ?', [(string)$rowId]);

    gov_bridge_insert_row($db, 'mapping_update_audit', [
        'table_name' => $table,
        'row_id' => (string)$rowId,
        'field_name' => $field,
        'old_value' => $oldValue,
        'new_value' => $edxeixId,
        'changed_by' => 'ops/mappings.php',
        'remote_ip' => (string)($_SERVER['REMOTE_ADDR'] ?? ''),
        'user_agent' => mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255, 'UTF-8'),
        'reason' => 'Manual EDXEIX ID mapping update from guarded operations dashboard.',
        'created_at' => date('Y-m-d H:i:s'),
    ]);

    return [
        'ok' => true,
        'type' => 'good',
        'message' => 'Updated ' . $label . ' mapping row #' . $rowId . ': ' . $field . ' ' . ($oldValue === '' ? '(blank)' : $oldValue) . ' → ' . $edxeixId . '.',
    ];
}

$state = [
    'ok' => false,
    'error' => null,
    'generated_at' => date('Y-m-d H:i:s'),
    'view' => 'all',
    'query' => '',
    'limit' => 200,
    'drivers' => [],
    'vehicles' => [],
    'driver_stats' => [],
    'vehicle_stats' => [],
    'editor' => ['enabled' => false, 'reason' => 'not_checked'],
    'update_result' => null,
];

try {
    $config = gov_bridge_load_config();
    if (!empty($config['app']['timezone'])) {
        date_default_timezone_set((string)$config['app']['timezone']);
        $state['generated_at'] = date('Y-m-d H:i:s');
    }

    $view = strtolower(map_request_param('view', 'all'));
    if (!in_array($view, ['all', 'mapped', 'unmapped'], true)) {
        $view = 'all';
    }
    $query = map_request_param('q', '');
    $limit = map_limit_param();

    $db = gov_bridge_db();
    $state['editor'] = map_editor_enabled($db);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $state['update_result'] = map_update_mapping($db);
    }

    $state['view'] = $view;
    $state['query'] = $query;
    $state['limit'] = $limit;
    $state['driver_stats'] = map_table_stats($db, 'mapping_drivers');
    $state['vehicle_stats'] = map_table_stats($db, 'mapping_vehicles');
    $state['drivers'] = map_table_rows($db, 'mapping_drivers', $view, $query, $limit);
    $state['vehicles'] = map_table_rows($db, 'mapping_vehicles', $view, $query, $limit);
    $state['ok'] = true;
} catch (Throwable $e) {
    $state['error'] = $e->getMessage();
}

if (map_request_param('format', '') === 'json') {
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('X-Robots-Tag: noindex, nofollow', true);
    }
    echo json_encode([
        'ok' => $state['ok'],
        'script' => 'ops/mappings.php',
        'generated_at' => $state['generated_at'],
        'read_only' => $_SERVER['REQUEST_METHOD'] !== 'POST',
        'editor_enabled' => $state['editor']['enabled'] ?? false,
        'json_sanitized' => true,
        'raw_payload_json_included' => false,
        'view' => $state['view'],
        'query' => $state['query'],
        'limit' => $state['limit'],
        'driver_stats' => $state['driver_stats'],
        'vehicle_stats' => $state['vehicle_stats'],
        'drivers' => array_map('map_sanitize_driver', $state['drivers']),
        'vehicles' => array_map('map_sanitize_vehicle', $state['vehicles']),
        'error' => $state['error'],
        'note' => 'Sanitized mapping coverage report. raw_payload_json is intentionally excluded. GET is read-only; POST editor only updates EDXEIX ID fields and writes local audit rows.',
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit;
}

$driverStats = $state['driver_stats'] ?: ['total' => 0, 'mapped' => 0, 'unmapped' => 0, 'mapped_percent' => 0];
$vehicleStats = $state['vehicle_stats'] ?: ['total' => 0, 'mapped' => 0, 'unmapped' => 0, 'mapped_percent' => 0];
$queryString = http_build_query(['view' => $state['view'], 'q' => $state['query'], 'limit' => $state['limit']]);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Mapping Coverage | gov.cabnet.app</title>
    <style>
        :root { --bg:#f3f6fb; --panel:#fff; --ink:#07152f; --muted:#41577a; --line:#d7e1ef; --nav:#081225; --blue:#2563eb; --green:#07875a; --orange:#b85c00; --red:#b42318; --slate:#334155; }
        * { box-sizing:border-box; }
        body { margin:0; background:var(--bg); color:var(--ink); font-family:Arial, Helvetica, sans-serif; }
        .nav { background:var(--nav); color:#fff; min-height:56px; display:flex; align-items:center; gap:18px; padding:0 26px; position:sticky; top:0; z-index:5; overflow:auto; }
        .nav strong { white-space:nowrap; }
        .nav a { color:#fff; text-decoration:none; font-size:15px; white-space:nowrap; opacity:.92; }
        .nav a:hover { opacity:1; text-decoration:underline; }
        .wrap { width:min(1540px, calc(100% - 48px)); margin:26px auto 60px; }
        .card { background:var(--panel); border:1px solid var(--line); border-radius:14px; padding:18px; margin-bottom:18px; box-shadow:0 10px 26px rgba(8,18,37,.04); }
        h1 { font-size:34px; margin:0 0 12px; } h2 { font-size:23px; margin:0 0 14px; } p { color:var(--muted); line-height:1.45; }
        .hero { border-left:7px solid var(--green); }
        .toolbar { display:flex; flex-wrap:wrap; gap:10px; align-items:flex-end; margin-top:14px; }
        .toolbar a, .btn { display:inline-block; padding:10px 14px; border-radius:8px; color:#fff; text-decoration:none; font-weight:700; background:var(--blue); border:0; cursor:pointer; font-size:14px; }
        .toolbar a.dark { background:var(--slate); } .toolbar a.green { background:var(--green); } .toolbar a.orange { background:var(--orange); }
        .filters { display:grid; grid-template-columns: 1fr 180px 120px auto; gap:10px; align-items:end; }
        label { display:block; font-size:13px; font-weight:700; color:var(--ink); margin-bottom:5px; }
        input, select { width:100%; padding:10px 12px; border:1px solid var(--line); border-radius:8px; font-size:14px; }
        .grid { display:grid; grid-template-columns:repeat(4, minmax(0,1fr)); gap:12px; margin-top:14px; }
        .metric { border:1px solid var(--line); border-radius:10px; padding:14px; background:#f8fbff; min-height:82px; }
        .metric strong { display:block; font-size:30px; line-height:1.05; word-break:break-word; }
        .metric span { color:var(--muted); font-size:14px; }
        .badge { display:inline-block; padding:5px 9px; border-radius:999px; font-size:12px; font-weight:700; margin:1px 3px 1px 0; white-space:nowrap; }
        .badge-good { background:#dcfce7; color:#166534; } .badge-warn { background:#fff7ed; color:#b45309; } .badge-bad { background:#fee2e2; color:#991b1b; } .badge-neutral { background:#eaf1ff; color:#1e40af; }
        .table-wrap { overflow:auto; border:1px solid var(--line); border-radius:10px; }
        table { width:100%; border-collapse:collapse; min-width:1260px; }
        th, td { text-align:left; padding:10px 12px; border-bottom:1px solid var(--line); vertical-align:top; font-size:14px; }
        th { background:#f8fafc; font-size:12px; text-transform:uppercase; letter-spacing:.02em; }
        tr.unmapped { background:#fffafa; }
        .small { font-size:13px; color:var(--muted); }
        code { background:#eef2ff; padding:2px 5px; border-radius:5px; }
        .error { color:var(--red); font-weight:700; }
        .alert { padding:12px 14px; border-radius:10px; margin:12px 0; border-left:5px solid var(--slate); background:#f8fafc; color:var(--ink); }
        .alert.good { border-left-color:var(--green); background:#ecfdf3; }
        .alert.warn { border-left-color:var(--orange); background:#fff7ed; }
        .alert.bad { border-left-color:var(--red); background:#fef3f2; }
        .inline-edit { display:grid; grid-template-columns:110px 210px 86px; gap:6px; min-width:420px; }
        .inline-edit input { padding:8px; font-size:13px; }
        .inline-edit .btn { padding:8px 10px; }
        @media (max-width:1100px) { .grid { grid-template-columns:repeat(2, minmax(0,1fr)); } .filters { grid-template-columns:1fr 1fr; } }
        @media (max-width:720px) { .grid, .filters { grid-template-columns:1fr; } .wrap { width:calc(100% - 24px); margin-top:14px; } .nav { padding:0 14px; } }
    </style>
</head>
<body>
<nav class="nav">
    <strong>GC gov.cabnet.app</strong>
    <a href="/ops/index.php">Operations Console</a>
    <a href="/ops/bolt-live.php">Bolt Live</a>
    <a href="/ops/jobs.php">Jobs Queue</a>
    <a href="/ops/readiness.php">Readiness</a>
    <a href="/ops/mappings.php">Mappings</a>
    <a href="/ops/test-booking.php">Local Test Booking</a>
    <a href="/ops/cleanup-lab.php">LAB Cleanup</a>
    <a href="/bolt_readiness_audit.php">Readiness JSON</a>
</nav>

<main class="wrap">
    <section class="card hero">
        <h1>Bolt → EDXEIX Mapping Coverage</h1>
        <p>Guarded mapping dashboard. GET is read-only. POST updates are limited to EDXEIX ID fields only and write local audit rows. This page does not call Bolt or EDXEIX.</p>
        <?php if (!$state['ok']): ?>
            <p class="error">Error: <?= map_h($state['error']) ?></p>
        <?php endif; ?>
        <?php if ($state['update_result']): ?>
            <div class="alert <?= map_h($state['update_result']['type'] ?? 'warn') ?>"><?= map_h($state['update_result']['message'] ?? '') ?></div>
        <?php endif; ?>
        <?php if (!$state['editor']['enabled']): ?>
            <div class="alert warn"><strong>Editor disabled:</strong> <?= map_h($state['editor']['reason'] ?? '') ?></div>
        <?php else: ?>
            <div class="alert good"><strong>Editor ready:</strong> EDXEIX ID updates are enabled and audit logging is available.</div>
        <?php endif; ?>
        <div class="grid">
            <div class="metric"><strong><?= map_h(($driverStats['mapped'] ?? 0) . '/' . ($driverStats['total'] ?? 0)) ?></strong><span>Drivers mapped</span></div>
            <div class="metric"><strong><?= map_h(($driverStats['mapped_percent'] ?? 0) . '%') ?></strong><span>Driver coverage</span></div>
            <div class="metric"><strong><?= map_h(($vehicleStats['mapped'] ?? 0) . '/' . ($vehicleStats['total'] ?? 0)) ?></strong><span>Vehicles mapped</span></div>
            <div class="metric"><strong><?= map_h(($vehicleStats['mapped_percent'] ?? 0) . '%') ?></strong><span>Vehicle coverage</span></div>
        </div>
        <div class="toolbar">
            <a href="/ops/mappings.php?view=unmapped" class="orange">Show Unmapped</a>
            <a href="/ops/mappings.php?view=mapped" class="green">Show Mapped</a>
            <a href="/ops/mappings.php" class="dark">Show All</a>
            <a href="/ops/mappings.php?<?= map_h($queryString) ?>&format=json" class="dark">Open Sanitized JSON</a>
        </div>
    </section>

    <section class="card">
        <h2>Filters</h2>
        <form method="get" class="filters">
            <div>
                <label for="q">Search</label>
                <input id="q" name="q" value="<?= map_h($state['query']) ?>" placeholder="driver, UUID, plate, model, phone">
            </div>
            <div>
                <label for="view">Coverage</label>
                <select id="view" name="view">
                    <?php foreach (['all' => 'All rows', 'unmapped' => 'Unmapped only', 'mapped' => 'Mapped only'] as $value => $label): ?>
                        <option value="<?= map_h($value) ?>" <?= $state['view'] === $value ? 'selected' : '' ?>><?= map_h($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="limit">Limit</label>
                <input id="limit" type="number" min="1" max="500" name="limit" value="<?= (int)$state['limit'] ?>">
            </div>
            <div>
                <button class="btn" type="submit">Apply Filters</button>
            </div>
        </form>
    </section>

    <section class="card">
        <h2>Driver mappings</h2>
        <p class="small">Unmapped drivers need a real EDXEIX driver ID before a matching future Bolt trip can be live-ready. To update, type <code>UPDATE DRIVER MAPPING</code> in that row's confirmation field.</p>
        <?php if (!$state['drivers']): ?>
            <p>No driver rows match the current filter.</p>
        <?php else: ?>
            <div class="table-wrap"><table>
                <thead><tr><th>Status</th><th>ID</th><th>Source</th><th>Bolt Driver UUID</th><th>Driver Name</th><th>Phone</th><th>EDXEIX Driver ID</th><th>Update EDXEIX Driver ID</th><th>Active Vehicle UUID</th><th>Active Plate</th><th>Active</th><th>Last Seen</th></tr></thead>
                <tbody>
                <?php foreach ($state['drivers'] as $row): $mapped = map_is_mapped($row, ['edxeix_driver_id', 'driver_id']); $current = map_value($row, ['edxeix_driver_id', 'driver_id'], ''); ?>
                    <tr class="<?= $mapped ? '' : 'unmapped' ?>">
                        <td><?= map_yes_badge($mapped) ?></td>
                        <td><?= map_h(map_value($row, ['id'], '')) ?></td>
                        <td><?= map_h(map_value($row, ['source_system', 'source_type', 'source'], '')) ?></td>
                        <td><code><?= map_h(map_value($row, ['external_driver_id', 'driver_external_id', 'driver_uuid'], '')) ?></code></td>
                        <td><strong><?= map_h(map_value($row, ['external_driver_name', 'driver_name'], '')) ?></strong></td>
                        <td><?= map_h(map_value($row, ['driver_phone', 'phone'], '')) ?></td>
                        <td><strong><?= map_h($current) ?></strong></td>
                        <td>
                            <form method="post" class="inline-edit">
                                <input type="hidden" name="action" value="update_driver">
                                <input type="hidden" name="row_id" value="<?= map_h(map_value($row, ['id'], '')) ?>">
                                <input type="number" min="1" name="edxeix_id" value="<?= map_h($current) ?>" placeholder="EDXEIX ID" required>
                                <input type="text" name="confirm" placeholder="UPDATE DRIVER MAPPING" required>
                                <button class="btn" type="submit" <?= $state['editor']['enabled'] ? '' : 'disabled' ?>>Update</button>
                            </form>
                        </td>
                        <td><code><?= map_h(map_value($row, ['active_vehicle_uuid'], '')) ?></code></td>
                        <td><?= map_h(map_value($row, ['active_vehicle_plate'], '')) ?></td>
                        <td><?= map_value($row, ['is_active'], '1') === '0' ? map_badge('inactive', 'warn') : map_badge('active', 'good') ?></td>
                        <td><?= map_h(map_value($row, ['last_seen_at', 'updated_at', 'created_at'], '')) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
        <?php endif; ?>
    </section>

    <section class="card">
        <h2>Vehicle mappings</h2>
        <p class="small">Unmapped vehicles need a real EDXEIX vehicle ID before a matching future Bolt trip can be live-ready. To update, type <code>UPDATE VEHICLE MAPPING</code> in that row's confirmation field.</p>
        <?php if (!$state['vehicles']): ?>
            <p>No vehicle rows match the current filter.</p>
        <?php else: ?>
            <div class="table-wrap"><table>
                <thead><tr><th>Status</th><th>ID</th><th>Source</th><th>Bolt Vehicle UUID</th><th>Plate</th><th>Name</th><th>Model</th><th>EDXEIX Vehicle ID</th><th>Update EDXEIX Vehicle ID</th><th>Active</th><th>Last Seen</th></tr></thead>
                <tbody>
                <?php foreach ($state['vehicles'] as $row): $mapped = map_is_mapped($row, ['edxeix_vehicle_id', 'vehicle_id']); $current = map_value($row, ['edxeix_vehicle_id', 'vehicle_id'], ''); ?>
                    <tr class="<?= $mapped ? '' : 'unmapped' ?>">
                        <td><?= map_yes_badge($mapped) ?></td>
                        <td><?= map_h(map_value($row, ['id'], '')) ?></td>
                        <td><?= map_h(map_value($row, ['source_system', 'source_type', 'source'], '')) ?></td>
                        <td><code><?= map_h(map_value($row, ['external_vehicle_id', 'vehicle_external_id', 'vehicle_uuid'], '')) ?></code></td>
                        <td><strong><?= map_h(map_value($row, ['plate', 'vehicle_plate'], '')) ?></strong></td>
                        <td><?= map_h(map_value($row, ['external_vehicle_name', 'vehicle_name'], '')) ?></td>
                        <td><?= map_h(map_value($row, ['vehicle_model', 'model'], '')) ?></td>
                        <td><strong><?= map_h($current) ?></strong></td>
                        <td>
                            <form method="post" class="inline-edit">
                                <input type="hidden" name="action" value="update_vehicle">
                                <input type="hidden" name="row_id" value="<?= map_h(map_value($row, ['id'], '')) ?>">
                                <input type="number" min="1" name="edxeix_id" value="<?= map_h($current) ?>" placeholder="EDXEIX ID" required>
                                <input type="text" name="confirm" placeholder="UPDATE VEHICLE MAPPING" required>
                                <button class="btn" type="submit" <?= $state['editor']['enabled'] ? '' : 'disabled' ?>>Update</button>
                            </form>
                        </td>
                        <td><?= map_value($row, ['is_active'], '1') === '0' ? map_badge('inactive', 'warn') : map_badge('active', 'good') ?></td>
                        <td><?= map_h(map_value($row, ['last_seen_at', 'updated_at', 'created_at'], '')) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
