<?php
/**
 * gov.cabnet.app — Company Mapping Detail v1.0
 *
 * Read-only lessor/company mapping detail page.
 * No Bolt calls, no EDXEIX calls, no AADE calls, no workflow writes.
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

function cmd_h(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function cmd_badge(string $text, string $type = 'neutral'): string
{
    if (function_exists('opsui_badge')) {
        return opsui_badge($text, $type);
    }
    $type = in_array($type, ['good', 'warn', 'bad', 'neutral'], true) ? $type : 'neutral';
    return '<span class="badge badge-' . cmd_h($type) . '">' . cmd_h($text) . '</span>';
}

function cmd_shell_begin(string $title, string $lessorLabel = ''): void
{
    if (function_exists('opsui_shell_begin')) {
        opsui_shell_begin([
            'title' => $title,
            'page_title' => $title,
            'active_section' => 'Company Mapping',
            'breadcrumbs' => 'Αρχική / Διαχειριστικό / Company mapping detail' . ($lessorLabel !== '' ? ' / ' . $lessorLabel : ''),
            'safe_notice' => 'Read-only company mapping detail. This page checks lessor, driver, vehicle, and starting-point mapping state without calling EDXEIX or writing workflow data.',
            'force_safe_notice' => true,
        ]);
        return;
    }
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>' . cmd_h($title) . '</title><style>body{font-family:Arial,Helvetica,sans-serif;background:#eef1f6;color:#07152f;margin:0;padding:18px}.card{background:#fff;border:1px solid #d8dde7;border-radius:8px;padding:16px;margin:0 0 16px}.badge{display:inline-block;padding:5px 9px;border-radius:12px;background:#eef2ff;margin:2px}.badge-good{background:#dcfce7;color:#166534}.badge-warn{background:#fff7ed;color:#b45309}.badge-bad{background:#fee2e2;color:#991b1b}.table-wrap{overflow:auto}table{border-collapse:collapse;width:100%;background:#fff}th,td{border:1px solid #d8dde7;padding:8px;text-align:left;vertical-align:top}.btn{display:inline-block;background:#4f5ea7;color:#fff;text-decoration:none;padding:9px 12px;border-radius:5px;font-weight:700}</style></head><body>';
}

function cmd_shell_end(): void
{
    if (function_exists('opsui_shell_end')) {
        opsui_shell_end();
        return;
    }
    echo '</body></html>';
}

function cmd_bootstrap(?string &$error = null): ?array
{
    static $ctx = null;
    static $loaded = false;
    static $loadError = null;
    if ($loaded) {
        $error = $loadError;
        return is_array($ctx) ? $ctx : null;
    }
    $loaded = true;
    $bootstrap = dirname(__DIR__, 3) . '/gov.cabnet.app_app/src/bootstrap.php';
    if (!is_file($bootstrap)) {
        $loadError = 'Private app bootstrap not found.';
        $error = $loadError;
        return null;
    }
    try {
        $ctx = require $bootstrap;
        if (!is_array($ctx) || !isset($ctx['db']) || !method_exists($ctx['db'], 'connection')) {
            throw new RuntimeException('Private app bootstrap did not return a DB context.');
        }
        $error = null;
        return $ctx;
    } catch (Throwable $e) {
        $loadError = $e->getMessage();
        $error = $loadError;
        return null;
    }
}

function cmd_db(?string &$error = null): ?mysqli
{
    $ctx = cmd_bootstrap($error);
    if (!$ctx || !isset($ctx['db']) || !method_exists($ctx['db'], 'connection')) {
        return null;
    }
    try {
        return $ctx['db']->connection();
    } catch (Throwable $e) {
        $error = $e->getMessage();
        return null;
    }
}

function cmd_table_exists(mysqli $db, string $table): bool
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

function cmd_columns(mysqli $db, string $table): array
{
    if (!cmd_table_exists($db, $table)) {
        return [];
    }
    $cols = [];
    $res = $db->query('SHOW COLUMNS FROM `' . str_replace('`', '``', $table) . '`');
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $name = (string)($row['Field'] ?? '');
            if ($name !== '') {
                $cols[$name] = true;
            }
        }
    }
    return $cols;
}

function cmd_pick_col(array $cols, array $choices): ?string
{
    foreach ($choices as $choice) {
        if (isset($cols[$choice])) {
            return $choice;
        }
    }
    return null;
}

function cmd_fetch_all(mysqli $db, string $sql, string $types = '', array $params = []): array
{
    if ($types === '') {
        $res = $db->query($sql);
        $out = [];
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $out[] = $row;
            }
        }
        return $out;
    }
    $stmt = $db->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException($db->error);
    }
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    $out = [];
    while ($row = $res->fetch_assoc()) {
        $out[] = $row;
    }
    return $out;
}

function cmd_fetch_one(mysqli $db, string $sql, string $types = '', array $params = []): ?array
{
    $rows = cmd_fetch_all($db, $sql, $types, $params);
    return $rows[0] ?? null;
}

function cmd_lessor_name(mysqli $db, int $lessorId): string
{
    if (cmd_table_exists($db, 'edxeix_export_lessors')) {
        $cols = cmd_columns($db, 'edxeix_export_lessors');
        $idCol = cmd_pick_col($cols, ['edxeix_lessor_id', 'lessor_id', 'id']);
        $nameCol = cmd_pick_col($cols, ['name', 'lessor_name', 'title', 'label']);
        if ($idCol && $nameCol) {
            $row = cmd_fetch_one($db, "SELECT `$nameCol` AS name FROM edxeix_export_lessors WHERE `$idCol` = ? LIMIT 1", 'i', [$lessorId]);
            if ($row && trim((string)($row['name'] ?? '')) !== '') {
                return trim((string)$row['name']);
            }
        }
    }

    $names = [];
    foreach (['mapping_drivers', 'mapping_vehicles'] as $table) {
        if (!cmd_table_exists($db, $table)) { continue; }
        $cols = cmd_columns($db, $table);
        if (!isset($cols['edxeix_lessor_id'])) { continue; }
        $labelCol = cmd_pick_col($cols, ['edxeix_lessor_name', 'lessor_name', 'company_name', 'operator']);
        if (!$labelCol) { continue; }
        $row = cmd_fetch_one($db, "SELECT `$labelCol` AS name FROM `$table` WHERE edxeix_lessor_id = ? AND `$labelCol` <> '' LIMIT 1", 'i', [$lessorId]);
        if ($row && trim((string)($row['name'] ?? '')) !== '') {
            $names[] = trim((string)$row['name']);
        }
    }
    return $names[0] ?? ('Lessor ' . $lessorId);
}

function cmd_mapping_rows(mysqli $db, string $table, int $lessorId): array
{
    if (!cmd_table_exists($db, $table)) {
        return [];
    }
    $cols = cmd_columns($db, $table);
    if (!isset($cols['edxeix_lessor_id'])) {
        return [];
    }
    $order = isset($cols['external_driver_name']) ? 'external_driver_name' : (isset($cols['plate']) ? 'plate' : 'id');
    return cmd_fetch_all($db, "SELECT * FROM `$table` WHERE edxeix_lessor_id = ? ORDER BY `$order` ASC", 'i', [$lessorId]);
}

function cmd_export_rows(mysqli $db, string $table, int $lessorId): array
{
    if (!cmd_table_exists($db, $table)) {
        return [];
    }
    $cols = cmd_columns($db, $table);
    $lessorCol = cmd_pick_col($cols, ['edxeix_lessor_id', 'lessor_id']);
    if (!$lessorCol) {
        return [];
    }
    return cmd_fetch_all($db, "SELECT * FROM `$table` WHERE `$lessorCol` = ? LIMIT 500", 'i', [$lessorId]);
}

function cmd_overrides(mysqli $db, int $lessorId): array
{
    if (!cmd_table_exists($db, 'mapping_lessor_starting_points')) {
        return [];
    }
    return cmd_fetch_all($db, 'SELECT * FROM mapping_lessor_starting_points WHERE edxeix_lessor_id = ? ORDER BY is_active DESC, id ASC', 'i', [$lessorId]);
}

function cmd_global_starting_points(mysqli $db): array
{
    if (!cmd_table_exists($db, 'mapping_starting_points')) {
        return [];
    }
    return cmd_fetch_all($db, 'SELECT * FROM mapping_starting_points ORDER BY is_active DESC, id ASC LIMIT 100');
}

function cmd_export_starting_point(mysqli $db, string $startingPointId): ?array
{
    if ($startingPointId === '' || !cmd_table_exists($db, 'edxeix_export_starting_points')) {
        return null;
    }
    $cols = cmd_columns($db, 'edxeix_export_starting_points');
    $idCol = cmd_pick_col($cols, ['edxeix_starting_point_id', 'starting_point_id', 'id', 'value']);
    if (!$idCol) {
        return null;
    }
    return cmd_fetch_one($db, "SELECT * FROM edxeix_export_starting_points WHERE `$idCol` = ? LIMIT 1", 's', [$startingPointId]);
}

function cmd_row_value(array $row, array $keys): string
{
    foreach ($keys as $key) {
        if (array_key_exists($key, $row) && trim((string)$row[$key]) !== '') {
            return trim((string)$row[$key]);
        }
    }
    return '';
}

function cmd_short(array $row, int $limit = 110): string
{
    $parts = [];
    foreach ($row as $key => $value) {
        if (in_array((string)$key, ['created_at', 'updated_at'], true)) { continue; }
        $v = trim((string)$value);
        if ($v !== '') {
            $parts[] = $key . '=' . $v;
        }
    }
    $text = implode(' | ', $parts);
    return mb_strlen($text, 'UTF-8') > $limit ? mb_substr($text, 0, $limit, 'UTF-8') . '…' : $text;
}

$lessorId = filter_var($_GET['lessor'] ?? $_GET['id'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['default' => 0]]);
$lessorId = max(0, (int)$lessorId);
$error = null;
$db = cmd_db($error);

$lessorName = $lessorId > 0 && $db ? cmd_lessor_name($db, $lessorId) : '';
cmd_shell_begin('Company Mapping Detail', $lessorName ?: (string)$lessorId);
?>
<style>
.cmd-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}.cmd-card{background:#fff;border:1px solid #d8dde7;border-radius:6px;padding:16px;margin:0 0 16px}.cmd-card h2,.cmd-card h3{margin-top:0}.cmd-actions{display:flex;gap:10px;flex-wrap:wrap;margin:12px 0}.cmd-table-wrap{overflow:auto;border:1px solid #d8dde7;border-radius:6px}.cmd-table{border-collapse:collapse;width:100%;background:#fff}.cmd-table th,.cmd-table td{border-bottom:1px solid #e7ebf2;padding:9px 10px;text-align:left;vertical-align:top;font-size:13px}.cmd-table th{background:#f5f7fb;color:#27385f}.cmd-kv{display:grid;grid-template-columns:200px 1fr;gap:8px;padding:8px 0;border-bottom:1px solid #eef1f5}.cmd-k{font-weight:700;color:#55637f}.cmd-warning{border-left:5px solid #d4922d}.cmd-danger{border-left:5px solid #b42318}.cmd-ok{border-left:5px solid #07875a}.cmd-code{font-family:Consolas,monospace;background:#eef2ff;border-radius:4px;padding:2px 5px}.cmd-small{font-size:13px;color:#52627c}@media(max-width:980px){.cmd-grid{grid-template-columns:1fr}.cmd-kv{grid-template-columns:1fr}}
</style>

<section class="cmd-card">
    <h1>Company Mapping Detail</h1>
    <p class="cmd-small">Read-only focused view for one EDXEIX lessor. Use this page before trusting a company mapping for production pre-ride work.</p>
    <div class="cmd-actions">
        <a class="btn" href="/ops/company-mapping-control.php">Company Mapping Control</a>
        <a class="btn dark" href="/ops/starting-point-control.php<?= $lessorId > 0 ? '?lessor=' . cmd_h((string)$lessorId) : '' ?>">Starting Point Control</a>
        <a class="btn dark" href="/ops/mapping-control.php">Driver/Vehicle Mapping Review</a>
    </div>
</section>

<?php if ($error || !$db): ?>
<section class="cmd-card cmd-danger"><h2>Database unavailable</h2><p><?= cmd_h($error ?: 'Unknown DB error.') ?></p></section>
<?php elseif ($lessorId <= 0): ?>
<section class="cmd-card cmd-warning"><h2>Select a company</h2><p>Open this page with a lessor ID, for example:</p><p><span class="cmd-code">/ops/company-mapping-detail.php?lessor=1756</span></p></section>
<?php else: ?>
<?php
$drivers = cmd_mapping_rows($db, 'mapping_drivers', $lessorId);
$vehicles = cmd_mapping_rows($db, 'mapping_vehicles', $lessorId);
$exportDrivers = cmd_export_rows($db, 'edxeix_export_drivers', $lessorId);
$exportVehicles = cmd_export_rows($db, 'edxeix_export_vehicles', $lessorId);
$overrides = cmd_overrides($db, $lessorId);
$globals = cmd_global_starting_points($db);
$activeOverrides = array_values(array_filter($overrides, static fn(array $r): bool => (string)($r['is_active'] ?? '1') !== '0'));
$primaryOverride = $activeOverrides[0] ?? null;
$spId = $primaryOverride ? trim((string)($primaryOverride['edxeix_starting_point_id'] ?? '')) : '';
$spExport = $spId !== '' ? cmd_export_starting_point($db, $spId) : null;
$driverMapped = count(array_filter($drivers, static fn(array $r): bool => trim((string)($r['edxeix_driver_id'] ?? '')) !== '' && (string)($r['is_active'] ?? '1') !== '0'));
$vehicleMapped = count(array_filter($vehicles, static fn(array $r): bool => trim((string)($r['edxeix_vehicle_id'] ?? '')) !== '' && (string)($r['is_active'] ?? '1') !== '0'));
$warnings = [];
if (!$primaryOverride) { $warnings[] = 'missing_lessor_specific_starting_point_override'; }
if ($primaryOverride && !$spExport) { $warnings[] = 'starting_point_not_found_in_export_snapshot'; }
if ($lessorId === 1756 && $spId !== '612164') { $warnings[] = 'WHITEBLUE_expected_starting_point_612164'; }
?>
<section class="cmd-grid">
    <div class="cmd-card"><strong><?= cmd_h($lessorName) ?></strong><br><span class="cmd-small">Lessor ID <?= cmd_h((string)$lessorId) ?></span></div>
    <div class="cmd-card"><strong><?= cmd_h((string)$driverMapped) ?>/<?= cmd_h((string)count($drivers)) ?></strong><br><span class="cmd-small">Local drivers mapped</span></div>
    <div class="cmd-card"><strong><?= cmd_h((string)$vehicleMapped) ?>/<?= cmd_h((string)count($vehicles)) ?></strong><br><span class="cmd-small">Local vehicles mapped</span></div>
    <div class="cmd-card"><strong><?= $warnings ? cmd_badge('WARN', 'warn') : cmd_badge('GOOD', 'good') ?></strong><br><span class="cmd-small"><?= $warnings ? cmd_h(implode(', ', $warnings)) : 'No blockers detected here.' ?></span></div>
</section>

<section class="cmd-card <?= $warnings ? 'cmd-warning' : 'cmd-ok' ?>">
    <h2>Starting point override</h2>
    <?php if ($primaryOverride): ?>
        <div class="cmd-kv"><div class="cmd-k">Override ID</div><div><?= cmd_h((string)($primaryOverride['id'] ?? '')) ?></div></div>
        <div class="cmd-kv"><div class="cmd-k">Internal key</div><div><?= cmd_h((string)($primaryOverride['internal_key'] ?? '')) ?></div></div>
        <div class="cmd-kv"><div class="cmd-k">Label</div><div><?= cmd_h((string)($primaryOverride['label'] ?? '')) ?></div></div>
        <div class="cmd-kv"><div class="cmd-k">EDXEIX starting_point_id</div><div><strong><?= cmd_h($spId) ?></strong> <?= $spExport ? cmd_badge('found in export snapshot', 'good') : cmd_badge('not found in export snapshot', 'warn') ?></div></div>
        <?php if ($spExport): ?><div class="cmd-kv"><div class="cmd-k">Export snapshot</div><div><?= cmd_h(cmd_short($spExport, 220)) ?></div></div><?php endif; ?>
    <?php else: ?>
        <p class="warnline"><strong>No active lessor-specific starting point override exists.</strong></p>
        <p>The resolver may use a global fallback row, which can select the wrong EDXEIX starting point for this company.</p>
    <?php endif; ?>
    <div class="cmd-actions"><a class="btn" href="/ops/starting-point-control.php?lessor=<?= cmd_h((string)$lessorId) ?>">Review / manage starting point override</a></div>
</section>

<section class="cmd-card">
    <h2>All lessor-specific starting point rows</h2>
    <div class="cmd-table-wrap"><table class="cmd-table"><thead><tr><th>ID</th><th>Internal key</th><th>Label</th><th>Starting point ID</th><th>Status</th><th>Updated</th></tr></thead><tbody>
    <?php if (!$overrides): ?><tr><td colspan="6">No lessor-specific rows.</td></tr><?php endif; ?>
    <?php foreach ($overrides as $row): ?>
        <tr><td><?= cmd_h((string)($row['id'] ?? '')) ?></td><td><?= cmd_h((string)($row['internal_key'] ?? '')) ?></td><td><?= cmd_h((string)($row['label'] ?? '')) ?></td><td><strong><?= cmd_h((string)($row['edxeix_starting_point_id'] ?? '')) ?></strong></td><td><?= (string)($row['is_active'] ?? '1') === '0' ? cmd_badge('inactive', 'neutral') : cmd_badge('active', 'good') ?></td><td><?= cmd_h((string)($row['updated_at'] ?? '')) ?></td></tr>
    <?php endforeach; ?></tbody></table></div>
</section>

<section class="cmd-card">
    <h2>Local mapped drivers</h2>
    <div class="cmd-table-wrap"><table class="cmd-table"><thead><tr><th>Name</th><th>Bolt UUID</th><th>EDXEIX driver ID</th><th>Status</th></tr></thead><tbody>
    <?php if (!$drivers): ?><tr><td colspan="4">No local driver mappings for this lessor.</td></tr><?php endif; ?>
    <?php foreach ($drivers as $row): ?>
        <tr><td><?= cmd_h((string)($row['external_driver_name'] ?? '')) ?></td><td><span class="cmd-code"><?= cmd_h((string)($row['external_driver_id'] ?? '')) ?></span></td><td><?= trim((string)($row['edxeix_driver_id'] ?? '')) !== '' ? cmd_badge((string)$row['edxeix_driver_id'], 'good') : cmd_badge('missing', 'warn') ?></td><td><?= (string)($row['is_active'] ?? '1') === '0' ? cmd_badge('inactive', 'neutral') : cmd_badge('active', 'good') ?></td></tr>
    <?php endforeach; ?></tbody></table></div>
</section>

<section class="cmd-card">
    <h2>Local mapped vehicles</h2>
    <div class="cmd-table-wrap"><table class="cmd-table"><thead><tr><th>Plate</th><th>EDXEIX vehicle ID</th><th>Status</th></tr></thead><tbody>
    <?php if (!$vehicles): ?><tr><td colspan="3">No local vehicle mappings for this lessor.</td></tr><?php endif; ?>
    <?php foreach ($vehicles as $row): ?>
        <tr><td><strong><?= cmd_h((string)($row['plate'] ?? '')) ?></strong></td><td><?= trim((string)($row['edxeix_vehicle_id'] ?? '')) !== '' ? cmd_badge((string)$row['edxeix_vehicle_id'], 'good') : cmd_badge('missing', 'warn') ?></td><td><?= (string)($row['is_active'] ?? '1') === '0' ? cmd_badge('inactive', 'neutral') : cmd_badge('active', 'good') ?></td></tr>
    <?php endforeach; ?></tbody></table></div>
</section>

<section class="cmd-card">
    <h2>Export snapshot comparison</h2>
    <div class="cmd-grid">
        <div><strong><?= cmd_h((string)count($exportDrivers)) ?></strong><br><span class="cmd-small">EDXEIX export drivers for lessor</span></div>
        <div><strong><?= cmd_h((string)count($exportVehicles)) ?></strong><br><span class="cmd-small">EDXEIX export vehicles for lessor</span></div>
        <div><strong><?= cmd_h((string)count($globals)) ?></strong><br><span class="cmd-small">Global fallback rows available</span></div>
        <div><strong><?= $primaryOverride ? cmd_badge('override present', 'good') : cmd_badge('fallback risk', 'warn') ?></strong></div>
    </div>
</section>
<?php endif; ?>
<?php cmd_shell_end(); ?>
