<?php
/**
 * gov.cabnet.app — Mapping Resolver Test v1.0
 *
 * Read-only mapping sanity tester for Bolt driver/vehicle/operator inputs.
 * Safety contract:
 * - No Bolt calls.
 * - No EDXEIX calls.
 * - No AADE calls.
 * - No DB writes.
 * - No queue staging.
 * - No live submission.
 */

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Robots-Tag: noindex,nofollow', true);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$shellFile = __DIR__ . '/_shell.php';
if (is_file($shellFile)) {
    require_once $shellFile;
}

$mappingNavFile = __DIR__ . '/_mapping_nav.php';
if (is_file($mappingNavFile)) {
    require_once $mappingNavFile;
}

$root = dirname(__DIR__, 3);
$bootstrapFile = $root . '/gov.cabnet.app_app/src/bootstrap.php';
$lookupFile = $root . '/gov.cabnet.app_app/src/BoltMail/EdxeixMappingLookup.php';
if (is_file($lookupFile)) {
    require_once $lookupFile;
}

use Bridge\BoltMail\EdxeixMappingLookup;

function mrt_h(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function mrt_badge(string $text, string $type = 'neutral'): string
{
    if (function_exists('opsui_badge')) {
        return opsui_badge($text, $type);
    }
    $type = in_array($type, ['good', 'warn', 'bad', 'neutral'], true) ? $type : 'neutral';
    return '<span class="badge badge-' . mrt_h($type) . '">' . mrt_h($text) . '</span>';
}

function mrt_shell_begin(): void
{
    if (function_exists('opsui_shell_begin')) {
        opsui_shell_begin([
            'title' => 'Mapping Resolver Test',
            'page_title' => 'Mapping Resolver Test',
            'active_section' => 'Mapping Governance',
            'breadcrumbs' => 'Αρχική / Mapping / Resolver Test',
            'safe_notice' => 'Read-only mapping sanity tester. This page does not call Bolt, EDXEIX, or AADE, and it does not write data.',
            'force_safe_notice' => true,
        ]);
        return;
    }
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Mapping Resolver Test | gov.cabnet.app</title><style>body{font-family:Arial,Helvetica,sans-serif;background:#eef1f6;color:#07152f;margin:0;padding:18px}.card{background:#fff;border:1px solid #d8dde7;border-radius:8px;padding:16px;margin-bottom:16px}.badge{display:inline-block;padding:5px 9px;border-radius:999px;margin:2px;background:#eaf1ff}.badge-good{background:#dcfce7;color:#166534}.badge-warn{background:#fff7ed;color:#b45309}.badge-bad{background:#fee2e2;color:#991b1b}.btn{display:inline-block;background:#4f5ea7;color:#fff;padding:10px 14px;border-radius:6px;text-decoration:none;border:0;font-weight:700;cursor:pointer}input,textarea,select{width:100%;box-sizing:border-box;border:1px solid #d8dde7;border-radius:6px;padding:10px}.grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}.table-wrap{overflow:auto}table{width:100%;border-collapse:collapse}th,td{border-bottom:1px solid #e5e7eb;padding:8px;text-align:left}code,pre{background:#f3f4f6;padding:2px 5px;border-radius:4px}pre{padding:12px;overflow:auto}@media(max-width:900px){.grid{grid-template-columns:1fr}}</style></head><body>';
}

function mrt_shell_end(): void
{
    if (function_exists('opsui_shell_end')) {
        opsui_shell_end();
        return;
    }
    echo '</body></html>';
}

function mrt_db(?string &$error = null): ?mysqli
{
    global $bootstrapFile;
    if (!is_file($bootstrapFile)) {
        $error = 'Private app bootstrap not found.';
        return null;
    }
    try {
        $ctx = require $bootstrapFile;
        if (!is_array($ctx) || !isset($ctx['db']) || !method_exists($ctx['db'], 'connection')) {
            throw new RuntimeException('Private app bootstrap did not expose db connection.');
        }
        $db = $ctx['db']->connection();
        if (!$db instanceof mysqli) {
            throw new RuntimeException('DB connection is not mysqli.');
        }
        $error = null;
        return $db;
    } catch (Throwable $e) {
        $error = $e->getMessage();
        return null;
    }
}

function mrt_table_exists(mysqli $db, string $table): bool
{
    try {
        $stmt = $db->prepare('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1');
        $stmt->bind_param('s', $table);
        $stmt->execute();
        return (bool)$stmt->get_result()->fetch_assoc();
    } catch (Throwable) {
        return false;
    }
}

function mrt_columns(mysqli $db, string $table): array
{
    try {
        $stmt = $db->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
        $stmt->bind_param('s', $table);
        $stmt->execute();
        $out = [];
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $out[(string)$row['COLUMN_NAME']] = true;
        }
        return $out;
    } catch (Throwable) {
        return [];
    }
}

function mrt_pick_col(array $cols, array $choices): ?string
{
    foreach ($choices as $col) {
        if (isset($cols[$col])) {
            return $col;
        }
    }
    return null;
}

function mrt_fetch_all(mysqli $db, string $sql, string $types = '', array $params = []): array
{
    try {
        if ($types !== '') {
            $stmt = $db->prepare($sql);
            if (!$stmt) { return []; }
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $res = $stmt->get_result();
        } else {
            $res = $db->query($sql);
        }
        if (!$res) { return []; }
        $rows = [];
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
        return $rows;
    } catch (Throwable) {
        return [];
    }
}

function mrt_render_rows(string $title, array $rows): string
{
    $html = '<section class="card"><h2>' . mrt_h($title) . '</h2>';
    if (!$rows) {
        return $html . '<p>No rows found.</p></section>';
    }
    $cols = array_keys($rows[0]);
    $html .= '<div class="table-wrap"><table><thead><tr>';
    foreach ($cols as $col) {
        $html .= '<th>' . mrt_h($col) . '</th>';
    }
    $html .= '</tr></thead><tbody>';
    foreach ($rows as $row) {
        $html .= '<tr>';
        foreach ($cols as $col) {
            $value = $row[$col] ?? '';
            $html .= '<td>' . mrt_h((string)$value) . '</td>';
        }
        $html .= '</tr>';
    }
    $html .= '</tbody></table></div></section>';
    return $html;
}

function mrt_find_export_starting_point(mysqli $db, string $startingPointId): array
{
    if ($startingPointId === '' || !mrt_table_exists($db, 'edxeix_export_starting_points')) {
        return [];
    }
    $cols = mrt_columns($db, 'edxeix_export_starting_points');
    $idCol = mrt_pick_col($cols, ['id', 'edxeix_starting_point_id', 'starting_point_id', 'value']);
    if (!$idCol) { return []; }
    $sql = 'SELECT * FROM edxeix_export_starting_points WHERE `' . str_replace('`', '``', $idCol) . '` = ? LIMIT 5';
    return mrt_fetch_all($db, $sql, 's', [$startingPointId]);
}

function mrt_quick_link(string $driver, string $vehicle, string $operator): string
{
    return '/ops/mapping-resolver-test.php?driver=' . rawurlencode($driver) . '&vehicle=' . rawurlencode($vehicle) . '&operator=' . rawurlencode($operator);
}

$dbError = null;
$db = mrt_db($dbError);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$driverName = trim((string)(($method === 'POST' ? $_POST['driver_name'] ?? '' : $_GET['driver'] ?? '')));
$vehiclePlate = trim((string)(($method === 'POST' ? $_POST['vehicle_plate'] ?? '' : $_GET['vehicle'] ?? '')));
$operator = trim((string)(($method === 'POST' ? $_POST['operator'] ?? '' : $_GET['operator'] ?? '')));
$result = null;
$resultError = null;
$driverRows = [];
$vehicleRows = [];
$lessorStartRows = [];
$globalStartRows = [];
$exportStartRows = [];

if ($db && ($driverName !== '' || $vehiclePlate !== '' || $operator !== '')) {
    if (!class_exists(EdxeixMappingLookup::class)) {
        $resultError = 'EdxeixMappingLookup class is not installed.';
    } else {
        try {
            $lookup = new EdxeixMappingLookup($db);
            $result = $lookup->lookup([
                'operator' => $operator,
                'driver_name' => $driverName,
                'vehicle_plate' => $vehiclePlate,
            ]);
        } catch (Throwable $e) {
            $resultError = $e->getMessage();
        }
    }

    if ($driverName !== '' && mrt_table_exists($db, 'mapping_drivers')) {
        $needle = '%' . $driverName . '%';
        $driverRows = mrt_fetch_all($db, 'SELECT id, external_driver_name, edxeix_driver_id, edxeix_lessor_id, is_active FROM mapping_drivers WHERE external_driver_name LIKE ? ORDER BY id ASC LIMIT 20', 's', [$needle]);
    }
    if ($vehiclePlate !== '' && mrt_table_exists($db, 'mapping_vehicles')) {
        $vehicleRows = mrt_fetch_all($db, 'SELECT id, plate, edxeix_vehicle_id, edxeix_lessor_id, is_active FROM mapping_vehicles WHERE plate = ? ORDER BY id ASC LIMIT 20', 's', [$vehiclePlate]);
    }
    $resolvedLessor = trim((string)($result['lessor_id'] ?? ''));
    if ($resolvedLessor !== '' && mrt_table_exists($db, 'mapping_lessor_starting_points')) {
        $lessorStartRows = mrt_fetch_all($db, 'SELECT id, edxeix_lessor_id, internal_key, label, edxeix_starting_point_id, is_active, updated_at FROM mapping_lessor_starting_points WHERE edxeix_lessor_id = ? ORDER BY is_active DESC, id ASC', 's', [$resolvedLessor]);
    }
    if (mrt_table_exists($db, 'mapping_starting_points')) {
        $globalStartRows = mrt_fetch_all($db, 'SELECT id, internal_key, label, edxeix_starting_point_id, is_active FROM mapping_starting_points ORDER BY id ASC LIMIT 20');
    }
    $resolvedStartingPoint = trim((string)($result['starting_point_id'] ?? ''));
    if ($resolvedStartingPoint !== '') {
        $exportStartRows = mrt_find_export_starting_point($db, $resolvedStartingPoint);
    }
}

mrt_shell_begin();
?>
<style>
.mrt-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}.mrt-form-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}.mrt-actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:12px}.mrt-kv{display:grid;grid-template-columns:220px minmax(0,1fr);gap:10px;border-bottom:1px solid #eef1f5;padding:9px 0}.mrt-kv .k{font-weight:700;color:#667085}.mrt-risk{border-left:6px solid #d4922d}.mrt-ok{border-left:6px solid #5fa865}.mrt-bad{border-left:6px solid #b42318}.mrt-pre{white-space:pre-wrap;max-height:360px;overflow:auto;background:#0b1220;color:#dbeafe;border-radius:8px;padding:14px;font-size:13px}.mapping-local-nav{margin-bottom:14px;display:flex;gap:8px;flex-wrap:wrap}.mapping-local-nav a{display:inline-block;text-decoration:none;border:1px solid #d8dde7;background:#fff;border-radius:999px;padding:8px 11px;color:#27385f;font-weight:700;font-size:13px}.mapping-local-nav a:hover{background:#eef1f8}@media(max-width:900px){.mrt-grid,.mrt-form-grid{grid-template-columns:1fr}.mrt-kv{grid-template-columns:1fr}}
</style>

<?php
if (function_exists('mapping_nav_render')) {
    echo mapping_nav_render('/ops/mapping-resolver-test.php');
} else {
    echo '<nav class="mapping-local-nav"><a href="/ops/mapping-center.php">Mapping Center</a><a href="/ops/company-mapping-control.php">Company Control</a><a href="/ops/mapping-health.php">Mapping Health</a><a href="/ops/mapping-audit.php">Mapping Audit</a><a href="/ops/mapping-verification.php">Verification Register</a><a href="/ops/mappings.php">Original Editor</a></nav>';
}
?>

<section class="card hero mrt-risk">
    <h1>Mapping Resolver Test</h1>
    <p>Use this before a live future ride to confirm that a Bolt driver + vehicle resolves to the correct EDXEIX lessor, driver ID, vehicle ID, and lessor-specific starting point.</p>
    <div>
        <?= mrt_badge('READ ONLY', 'good') ?>
        <?= mrt_badge('NO EDXEIX CALL', 'good') ?>
        <?= mrt_badge('NO DB WRITE', 'good') ?>
        <?= mrt_badge('MAPPING SAFETY', 'warn') ?>
    </div>
</section>

<section class="card">
    <h2>Run resolver sanity test</h2>
    <form method="post" action="/ops/mapping-resolver-test.php" autocomplete="off">
        <div class="mrt-form-grid">
            <div><label for="operator">Bolt operator / company hint</label><input id="operator" name="operator" value="<?= mrt_h($operator) ?>" placeholder="WHITEBLUE PREMIUM E E"></div>
            <div><label for="driver_name">Driver name</label><input id="driver_name" name="driver_name" value="<?= mrt_h($driverName) ?>" placeholder="Georgios Tsatsas"></div>
            <div><label for="vehicle_plate">Vehicle plate</label><input id="vehicle_plate" name="vehicle_plate" value="<?= mrt_h($vehiclePlate) ?>" placeholder="XZO1837"></div>
        </div>
        <div class="mrt-actions">
            <button class="btn" type="submit">Run resolver test</button>
            <a class="btn dark" href="/ops/mapping-resolver-test.php">Clear</a>
            <a class="btn warn" href="<?= mrt_h(mrt_quick_link('Georgios Tsatsas', 'XZO1837', 'WHITEBLUE PREMIUM E E')) ?>">Quick test: WHITEBLUE / Tsatsas / XZO1837</a>
        </div>
    </form>
</section>

<?php if ($dbError): ?>
<section class="card mrt-bad"><h2>DB unavailable</h2><p class="badline"><?= mrt_h($dbError) ?></p></section>
<?php endif; ?>

<?php if ($resultError): ?>
<section class="card mrt-bad"><h2>Resolver error</h2><p class="badline"><?= mrt_h($resultError) ?></p></section>
<?php endif; ?>

<?php if (is_array($result)): ?>
<?php
    $ok = !empty($result['ok']);
    $sp = trim((string)($result['starting_point_id'] ?? ''));
    $lessor = trim((string)($result['lessor_id'] ?? ''));
    $hasSpecific = false;
    foreach ($lessorStartRows as $row) {
        if ((string)($row['is_active'] ?? '1') !== '0' && trim((string)($row['edxeix_starting_point_id'] ?? '')) === $sp && $sp !== '') {
            $hasSpecific = true;
        }
    }
    $severity = ($ok && $sp !== '' && $hasSpecific) ? 'mrt-ok' : (($ok && $sp !== '') ? 'mrt-risk' : 'mrt-bad');
?>
<section class="card <?= mrt_h($severity) ?>">
    <h2>Resolver result</h2>
    <div class="mrt-grid">
        <div class="metric"><strong><?= mrt_h($lessor !== '' ? $lessor : '-') ?></strong><span>Lessor ID</span></div>
        <div class="metric"><strong><?= mrt_h((string)($result['driver_id'] ?? '-')) ?></strong><span>Driver ID</span></div>
        <div class="metric"><strong><?= mrt_h((string)($result['vehicle_id'] ?? '-')) ?></strong><span>Vehicle ID</span></div>
        <div class="metric"><strong><?= mrt_h($sp !== '' ? $sp : '-') ?></strong><span>Starting point ID</span></div>
        <div class="metric"><strong><?= $ok ? 'READY' : 'CHECK' ?></strong><span>Mapping status</span></div>
        <div class="metric"><strong><?= $hasSpecific ? 'YES' : 'NO' ?></strong><span>Lessor-specific SP</span></div>
    </div>
    <div style="margin-top:14px">
        <?= mrt_badge($ok ? 'IDS READY' : 'IDS NEED REVIEW', $ok ? 'good' : 'warn') ?>
        <?= mrt_badge($hasSpecific ? 'LESSOR-SPECIFIC STARTING POINT' : 'GLOBAL FALLBACK RISK', $hasSpecific ? 'good' : 'warn') ?>
        <?= mrt_badge(!empty($result['company_trusted_from_edxeix_mapping']) ? 'COMPANY TRUSTED FROM MAPPING' : 'COMPANY NOT TRUSTED', !empty($result['company_trusted_from_edxeix_mapping']) ? 'good' : 'bad') ?>
    </div>
    <div style="margin-top:14px">
        <div class="mrt-kv"><div class="k">Lessor source</div><div><?= mrt_h((string)($result['lessor_source'] ?? '')) ?></div></div>
        <div class="mrt-kv"><div class="k">Driver label</div><div><?= mrt_h((string)($result['driver_label'] ?? '')) ?></div></div>
        <div class="mrt-kv"><div class="k">Vehicle label</div><div><?= mrt_h((string)($result['vehicle_label'] ?? '')) ?></div></div>
        <div class="mrt-kv"><div class="k">Starting point label</div><div><?= mrt_h((string)($result['starting_point_label'] ?? '')) ?></div></div>
    </div>
</section>

<section class="card">
    <h2>Resolver messages</h2>
    <?php foreach ((array)($result['messages'] ?? []) as $msg): ?><p class="goodline">✓ <?= mrt_h((string)$msg) ?></p><?php endforeach; ?>
    <?php foreach ((array)($result['warnings'] ?? []) as $msg): ?><p class="warnline">⚠ <?= mrt_h((string)$msg) ?></p><?php endforeach; ?>
    <?php if (!$hasSpecific): ?><p class="warnline"><strong>Warning:</strong> no matching active lessor-specific starting point row was found for this resolver result. This can cause the exact problem seen with WHITEBLUE before the hotfix.</p><?php endif; ?>
</section>

<?= mrt_render_rows('Driver mapping evidence', $driverRows) ?>
<?= mrt_render_rows('Vehicle mapping evidence', $vehicleRows) ?>
<?= mrt_render_rows('Lessor-specific starting point evidence', $lessorStartRows) ?>
<?= mrt_render_rows('Export snapshot row for resolved starting point', $exportStartRows) ?>
<?= mrt_render_rows('Global starting point fallback rows', $globalStartRows) ?>

<section class="card">
    <h2>Raw resolver JSON</h2>
    <pre class="mrt-pre"><?= mrt_h(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></pre>
</section>
<?php endif; ?>

<section class="card">
    <h2>Recommended use</h2>
    <ol class="list">
        <li>Before a live ride test, run the driver + vehicle pair here.</li>
        <li>Confirm the lessor, driver, vehicle, and starting point are all correct.</li>
        <li>If the result shows <strong>GLOBAL FALLBACK RISK</strong>, fix the lessor-specific starting point before using the production pre-ride tool.</li>
        <li>Only after mappings are clean, return to the production pre-ride workflow.</li>
    </ol>
</section>
<?php
mrt_shell_end();
