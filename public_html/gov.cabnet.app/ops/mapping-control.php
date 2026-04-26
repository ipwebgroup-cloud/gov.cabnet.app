<?php
declare(strict_types=1);
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Robots-Tag: noindex,nofollow', true);

require_once '/home/cabnet/gov.cabnet.app_app/lib/bolt_sync_lib.php';

function mpc_h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function mpc_badge(string $text, string $type='neutral'): string { return '<span class="badge badge-' . mpc_h($type) . '">' . mpc_h($text) . '</span>'; }
function mpc_val(array $row, array $keys, $default='') { foreach ($keys as $k) { if (array_key_exists($k,$row) && $row[$k] !== null && $row[$k] !== '') return $row[$k]; } return $default; }
function mpc_col(array $cols, string $c): bool { return isset($cols[$c]); }
function mpc_first(array $cols, array $choices): ?string { foreach ($choices as $c) if (mpc_col($cols,$c)) return $c; return null; }
function mpc_limit(): int { $raw = $_GET['limit'] ?? '120'; $v = filter_var($raw, FILTER_VALIDATE_INT, ['options'=>['default'=>120]]); return max(1, min(300, (int)$v)); }
function mpc_table_rows(mysqli $db, string $table, int $limit): array {
    if (!gov_bridge_table_exists($db, $table)) return [];
    $cols = gov_bridge_table_columns($db, $table);
    $order = mpc_first($cols, ['last_seen_at','updated_at','created_at','id']) ?? 'id';
    return gov_bridge_fetch_all($db, 'SELECT * FROM ' . gov_bridge_quote_identifier($table) . ' ORDER BY ' . gov_bridge_quote_identifier($order) . ' DESC LIMIT ' . (int)$limit);
}
function mpc_stats(mysqli $db, string $table, array $edxCols): array {
    $out = ['total'=>0,'mapped'=>0,'unmapped'=>0,'exists'=>false];
    if (!gov_bridge_table_exists($db, $table)) return $out;
    $cols = gov_bridge_table_columns($db, $table);
    $edx = mpc_first($cols, $edxCols);
    $out['exists'] = true;
    $out['total'] = (int)(gov_bridge_fetch_one($db, 'SELECT COUNT(*) AS c FROM ' . gov_bridge_quote_identifier($table))['c'] ?? 0);
    if ($edx) {
        $out['mapped'] = (int)(gov_bridge_fetch_one($db, 'SELECT COUNT(*) AS c FROM ' . gov_bridge_quote_identifier($table) . ' WHERE ' . gov_bridge_quote_identifier($edx) . ' IS NOT NULL AND ' . gov_bridge_quote_identifier($edx) . " <> '' AND " . gov_bridge_quote_identifier($edx) . ' <> 0')['c'] ?? 0);
    }
    $out['unmapped'] = max(0, $out['total'] - $out['mapped']);
    return $out;
}

$state = ['ok'=>false,'error'=>null,'drivers'=>[],'vehicles'=>[],'driver_stats'=>[],'vehicle_stats'=>[]];
try {
    $config = gov_bridge_load_config();
    if (!empty($config['app']['timezone'])) date_default_timezone_set((string)$config['app']['timezone']);
    $db = gov_bridge_db();
    $limit = mpc_limit();
    $state['drivers'] = mpc_table_rows($db, 'mapping_drivers', $limit);
    $state['vehicles'] = mpc_table_rows($db, 'mapping_vehicles', $limit);
    $state['driver_stats'] = mpc_stats($db, 'mapping_drivers', ['edxeix_driver_id','driver_id']);
    $state['vehicle_stats'] = mpc_stats($db, 'mapping_vehicles', ['edxeix_vehicle_id','vehicle_id']);
    $state['ok'] = true;
} catch (Throwable $e) { $state['error'] = $e->getMessage(); }
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Mapping Review | gov.cabnet.app</title>
    <link rel="stylesheet" href="/assets/css/gov-ops-edxeix.css?v=2.0">
</head>
<body>
<div class="gov-topbar">
    <div class="gov-brand">
        <div class="gov-brand-crest">ΕΔ</div>
        <div class="gov-brand-text">
            <strong>gov.cabnet.app</strong>
            <span>Bolt → EDXEIX operational console</span>
        </div>
    </div>
    <div class="gov-top-links">
        <a href="/ops/index.php">Αρχική</a>
        <a href="/ops/admin-control.php">Administration</a>
        <a href="/ops/test-session.php">Test Session</a>
        <a href="/ops/preflight-review.php">Preflight Review</a>
        <a class="gov-logout" href="/ops/index.php">Safe Ops</a>
    </div>
</div>
<div class="gov-shell">
    <aside class="gov-sidebar">
        <h3>Mapping Review</h3>
        <p>Read-only driver and vehicle mapping overview.</p>
        <div class="gov-side-group"><div class="gov-side-group-title">Administration</div><a class="gov-side-link" href="/ops/admin-control.php">Admin Control</a><a class="gov-side-link" href="/ops/readiness-control.php">Readiness Control</a><a class="gov-side-link active" href="/ops/mapping-control.php">Mapping Review</a><a class="gov-side-link" href="/ops/jobs-control.php">Jobs Review</a><div class="gov-side-group-title">Workflow</div><a class="gov-side-link" href="/ops/test-session.php">Test Session</a><a class="gov-side-link" href="/ops/dev-accelerator.php">Dev Accelerator</a><a class="gov-side-link" href="/ops/preflight-review.php">Preflight Review</a><a class="gov-side-link" href="/ops/evidence-bundle.php">Evidence Bundle</a><a class="gov-side-link" href="/ops/evidence-report.php">Evidence Report</a></div>
        <div class="gov-side-note">Read-only admin companion pages. Original operational pages remain available and unchanged.</div>
    </aside>
    <div class="gov-content">
        <div class="gov-page-header">
            <div>
                <h1 class="gov-page-title">Επισκόπηση αντιστοιχίσεων</h1>
                <div class="gov-breadcrumbs">Αρχική / Διαχειριστικό / Επισκόπηση αντιστοιχίσεων</div>
            </div>
            <div class="gov-tabs"><a class="gov-tab active" href="/ops/mapping-control.php">Overview</a><a class="gov-tab" href="/ops/mappings.php">Original Editor</a><a class="gov-tab" href="/ops/mappings.php?format=json">JSON</a></div>
        </div>
        <main class="wrap wrap-shell">

<section class="safety">
    <strong>READ-ONLY MAPPING REVIEW.</strong>
    This page does not edit mappings. Use the original guarded mapping editor only when an EDXEIX ID is independently confirmed.
</section>

<section class="card hero">
    <h1>Bolt → EDXEIX Mapping Review</h1>
    <?php if (!$state['ok']): ?>
        <p class="badline"><strong>Error:</strong> <?= mpc_h($state['error']) ?></p>
    <?php else: ?>
        <p>Read-only coverage overview for driver and vehicle mappings.</p>
        <div class="grid">
            <div class="metric"><strong><?= mpc_h(($state['driver_stats']['mapped'] ?? 0) . '/' . ($state['driver_stats']['total'] ?? 0)) ?></strong><span>Drivers mapped</span></div>
            <div class="metric"><strong><?= mpc_h($state['driver_stats']['unmapped'] ?? 0) ?></strong><span>Drivers unmapped</span></div>
            <div class="metric"><strong><?= mpc_h(($state['vehicle_stats']['mapped'] ?? 0) . '/' . ($state['vehicle_stats']['total'] ?? 0)) ?></strong><span>Vehicles mapped</span></div>
            <div class="metric"><strong><?= mpc_h($state['vehicle_stats']['unmapped'] ?? 0) ?></strong><span>Vehicles unmapped</span></div>
        </div>
        <div class="actions">
            <a class="btn" href="/ops/mappings.php">Open Original Mapping Editor</a>
            <a class="btn dark" href="/ops/mappings.php?format=json">Open Mapping JSON</a>
            <a class="btn warn" href="/ops/readiness-control.php">Readiness Control</a>
        </div>
    <?php endif; ?>
</section>

<?php if ($state['ok']): ?>
<section class="card">
    <h2>Driver Mappings</h2>
    <div class="table-wrap"><table>
        <thead><tr><th>ID</th><th>Name</th><th>Bolt UUID</th><th>EDXEIX Driver ID</th><th>Vehicle Plate</th><th>Status</th><th>Last Seen</th></tr></thead>
        <tbody>
        <?php foreach ($state['drivers'] as $row): $edx=(string)mpc_val($row,['edxeix_driver_id','driver_id'],''); ?>
            <tr>
                <td><?= mpc_h(mpc_val($row,['id'],'')) ?></td>
                <td><?= mpc_h(mpc_val($row,['external_driver_name','driver_name'],'')) ?></td>
                <td><code><?= mpc_h(mpc_val($row,['external_driver_id','driver_external_id','driver_uuid'],'')) ?></code></td>
                <td><?= $edx !== '' && $edx !== '0' ? mpc_badge($edx,'good') : mpc_badge('unmapped','warn') ?></td>
                <td><?= mpc_h(mpc_val($row,['active_vehicle_plate','vehicle_plate','plate'],'')) ?></td>
                <td><?= mpc_val($row,['is_active'],'1') === '0' ? mpc_badge('inactive','neutral') : mpc_badge('active','good') ?></td>
                <td><?= mpc_h(mpc_val($row,['last_seen_at','updated_at','created_at'],'')) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
</section>

<section class="card">
    <h2>Vehicle Mappings</h2>
    <div class="table-wrap"><table>
        <thead><tr><th>ID</th><th>Plate</th><th>Name / Model</th><th>Bolt UUID</th><th>EDXEIX Vehicle ID</th><th>Status</th><th>Last Seen</th></tr></thead>
        <tbody>
        <?php foreach ($state['vehicles'] as $row): $edx=(string)mpc_val($row,['edxeix_vehicle_id','vehicle_id'],''); ?>
            <tr>
                <td><?= mpc_h(mpc_val($row,['id'],'')) ?></td>
                <td><strong><?= mpc_h(mpc_val($row,['plate','vehicle_plate'],'')) ?></strong></td>
                <td><?= mpc_h(trim((string)mpc_val($row,['external_vehicle_name','vehicle_name'],'') . ' ' . (string)mpc_val($row,['vehicle_model','model'],''))) ?></td>
                <td><code><?= mpc_h(mpc_val($row,['external_vehicle_id','vehicle_external_id','vehicle_uuid'],'')) ?></code></td>
                <td><?= $edx !== '' && $edx !== '0' ? mpc_badge($edx,'good') : mpc_badge('unmapped','warn') ?></td>
                <td><?= mpc_val($row,['is_active'],'1') === '0' ? mpc_badge('inactive','neutral') : mpc_badge('active','good') ?></td>
                <td><?= mpc_h(mpc_val($row,['last_seen_at','updated_at','created_at'],'')) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
</section>
<?php endif; ?>
        </main>
    </div>
</div>
</body>
</html>
