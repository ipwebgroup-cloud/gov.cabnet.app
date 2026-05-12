<?php
/**
 * gov.cabnet.app — Mapping Audit v1.0
 *
 * Read-only mapping governance audit for Bolt → EDXEIX mapping risk.
 *
 * Safety contract:
 * - No Bolt calls.
 * - No EDXEIX calls.
 * - No AADE calls.
 * - No database writes.
 * - No live submission behavior.
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

function ma_h(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function ma_badge(string $text, string $type = 'neutral'): string
{
    if (function_exists('opsui_badge')) {
        return opsui_badge($text, $type);
    }
    $type = in_array($type, ['good', 'warn', 'bad', 'neutral'], true) ? $type : 'neutral';
    return '<span class="badge badge-' . ma_h($type) . '">' . ma_h($text) . '</span>';
}

function ma_quote(string $name): string
{
    return '`' . str_replace('`', '``', $name) . '`';
}

function ma_bootstrap_context(?string &$error = null): ?array
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
            throw new RuntimeException('Private bootstrap did not return expected DB context.');
        }
        $error = null;
        return $ctx;
    } catch (Throwable $e) {
        $loadError = $e->getMessage();
        $error = $loadError;
        return null;
    }
}

function ma_db(?string &$error = null): ?mysqli
{
    $ctx = ma_bootstrap_context($error);
    if (!$ctx) {
        return null;
    }
    try {
        $db = $ctx['db']->connection();
        if (!$db instanceof mysqli) {
            throw new RuntimeException('DB connection is not mysqli.');
        }
        return $db;
    } catch (Throwable $e) {
        $error = $e->getMessage();
        return null;
    }
}

function ma_table_exists(mysqli $db, string $table): bool
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

/** @return array<string,bool> */
function ma_columns(mysqli $db, string $table): array
{
    $out = [];
    if (!ma_table_exists($db, $table)) {
        return $out;
    }
    try {
        $stmt = $db->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
        $stmt->bind_param('s', $table);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $out[(string)$row['COLUMN_NAME']] = true;
        }
    } catch (Throwable) {
        return [];
    }
    return $out;
}

/** @param array<string,bool> $cols @param array<int,string> $choices */
function ma_pick(array $cols, array $choices): ?string
{
    foreach ($choices as $c) {
        if (isset($cols[$c])) {
            return $c;
        }
    }
    return null;
}

function ma_count(mysqli $db, string $sql, string $types = '', array $params = []): int
{
    try {
        if ($types !== '') {
            $stmt = $db->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
        } else {
            $row = $db->query($sql)?->fetch_assoc();
        }
        return (int)($row['c'] ?? 0);
    } catch (Throwable) {
        return 0;
    }
}

/** @return array<int,array<string,mixed>> */
function ma_fetch_all(mysqli $db, string $sql, string $types = '', array $params = []): array
{
    try {
        if ($types !== '') {
            $stmt = $db->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $res = $stmt->get_result();
        } else {
            $res = $db->query($sql);
        }
        if (!$res) {
            return [];
        }
        $rows = [];
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
        return $rows;
    } catch (Throwable) {
        return [];
    }
}

/** @return array<string,array{lessor_id:string,name:string,sources:array<int,string>}> */
function ma_collect_lessors(mysqli $db): array
{
    $lessors = [];

    $exportCols = ma_columns($db, 'edxeix_export_lessors');
    $exportIdCol = ma_pick($exportCols, ['id', 'edxeix_lessor_id', 'lessor_id']);
    $exportNameCol = ma_pick($exportCols, ['name', 'label', 'lessor_name', 'company_name', 'title']);
    if ($exportIdCol) {
        $select = ma_quote($exportIdCol) . ' AS lessor_id';
        $select .= $exportNameCol ? ', ' . ma_quote($exportNameCol) . ' AS company_name' : ', ' . ma_quote($exportIdCol) . ' AS company_name';
        foreach (ma_fetch_all($db, 'SELECT ' . $select . ' FROM edxeix_export_lessors ORDER BY ' . ma_quote($exportIdCol) . ' ASC') as $row) {
            $id = trim((string)($row['lessor_id'] ?? ''));
            if ($id === '') { continue; }
            $lessors[$id] = [
                'lessor_id' => $id,
                'name' => trim((string)($row['company_name'] ?? $id)),
                'sources' => ['edxeix_export_lessors'],
            ];
        }
    }

    foreach ([
        ['mapping_drivers', 'edxeix_lessor_id'],
        ['mapping_vehicles', 'edxeix_lessor_id'],
        ['mapping_lessor_starting_points', 'edxeix_lessor_id'],
    ] as $pair) {
        [$table, $col] = $pair;
        $cols = ma_columns($db, $table);
        if (!isset($cols[$col])) { continue; }
        foreach (ma_fetch_all($db, 'SELECT DISTINCT ' . ma_quote($col) . ' AS lessor_id FROM ' . ma_quote($table) . ' WHERE ' . ma_quote($col) . ' IS NOT NULL AND ' . ma_quote($col) . " <> '' AND " . ma_quote($col) . ' <> 0 ORDER BY ' . ma_quote($col) . ' ASC') as $row) {
            $id = trim((string)($row['lessor_id'] ?? ''));
            if ($id === '') { continue; }
            if (!isset($lessors[$id])) {
                $lessors[$id] = ['lessor_id' => $id, 'name' => 'Lessor ' . $id, 'sources' => []];
            }
            $lessors[$id]['sources'][] = $table;
            $lessors[$id]['sources'] = array_values(array_unique($lessors[$id]['sources']));
        }
    }

    uasort($lessors, static function (array $a, array $b): int {
        return strcasecmp($a['name'], $b['name']);
    });

    return $lessors;
}

/** @return array<int,array<string,mixed>> */
function ma_lessor_overrides(mysqli $db, string $lessorId): array
{
    $cols = ma_columns($db, 'mapping_lessor_starting_points');
    if (!isset($cols['edxeix_lessor_id'])) {
        return [];
    }
    return ma_fetch_all(
        $db,
        'SELECT * FROM mapping_lessor_starting_points WHERE edxeix_lessor_id = ? ORDER BY is_active DESC, updated_at DESC, id DESC',
        's',
        [$lessorId]
    );
}

function ma_export_starting_point_exists(mysqli $db, string $startingPointId): bool
{
    if ($startingPointId === '') {
        return false;
    }
    $cols = ma_columns($db, 'edxeix_export_starting_points');
    $idCol = ma_pick($cols, ['id', 'edxeix_starting_point_id', 'starting_point_id']);
    if (!$idCol) {
        return false;
    }
    return ma_count($db, 'SELECT COUNT(*) AS c FROM edxeix_export_starting_points WHERE ' . ma_quote($idCol) . ' = ?', 's', [$startingPointId]) > 0;
}

function ma_driver_counts(mysqli $db, string $lessorId): array
{
    $cols = ma_columns($db, 'mapping_drivers');
    if (!isset($cols['edxeix_lessor_id'])) {
        return ['total' => 0, 'mapped' => 0, 'unmapped' => 0];
    }
    $activeClause = isset($cols['is_active']) ? ' AND is_active = 1' : '';
    $total = ma_count($db, 'SELECT COUNT(*) AS c FROM mapping_drivers WHERE edxeix_lessor_id = ?' . $activeClause, 's', [$lessorId]);
    $mapped = ma_count($db, 'SELECT COUNT(*) AS c FROM mapping_drivers WHERE edxeix_lessor_id = ?' . $activeClause . " AND edxeix_driver_id IS NOT NULL AND edxeix_driver_id <> '' AND edxeix_driver_id <> 0", 's', [$lessorId]);
    return ['total' => $total, 'mapped' => $mapped, 'unmapped' => max(0, $total - $mapped)];
}

function ma_vehicle_counts(mysqli $db, string $lessorId): array
{
    $cols = ma_columns($db, 'mapping_vehicles');
    if (!isset($cols['edxeix_lessor_id'])) {
        return ['total' => 0, 'mapped' => 0, 'unmapped' => 0];
    }
    $activeClause = isset($cols['is_active']) ? ' AND is_active = 1' : '';
    $total = ma_count($db, 'SELECT COUNT(*) AS c FROM mapping_vehicles WHERE edxeix_lessor_id = ?' . $activeClause, 's', [$lessorId]);
    $mapped = ma_count($db, 'SELECT COUNT(*) AS c FROM mapping_vehicles WHERE edxeix_lessor_id = ?' . $activeClause . " AND edxeix_vehicle_id IS NOT NULL AND edxeix_vehicle_id <> '' AND edxeix_vehicle_id <> 0", 's', [$lessorId]);
    return ['total' => $total, 'mapped' => $mapped, 'unmapped' => max(0, $total - $mapped)];
}

function ma_global_fallbacks(mysqli $db): array
{
    if (!ma_table_exists($db, 'mapping_starting_points')) {
        return [];
    }
    return ma_fetch_all($db, 'SELECT id, internal_key, label, edxeix_starting_point_id, is_active FROM mapping_starting_points ORDER BY id ASC');
}

function ma_audit_lessor(mysqli $db, array $lessor): array
{
    $lessorId = (string)$lessor['lessor_id'];
    $driver = ma_driver_counts($db, $lessorId);
    $vehicle = ma_vehicle_counts($db, $lessorId);
    $overrides = ma_lessor_overrides($db, $lessorId);
    $activeOverrides = array_values(array_filter($overrides, static fn(array $row): bool => (string)($row['is_active'] ?? '1') !== '0'));
    $blockers = [];
    $warnings = [];
    $good = [];

    if ($driver['unmapped'] > 0) {
        $blockers[] = 'active_driver_missing_edxeix_id';
    }
    if ($vehicle['unmapped'] > 0) {
        $blockers[] = 'active_vehicle_missing_edxeix_id';
    }

    if (!$activeOverrides) {
        $blockers[] = 'missing_lessor_specific_starting_point_override';
    } else {
        foreach ($activeOverrides as $row) {
            $sp = trim((string)($row['edxeix_starting_point_id'] ?? ''));
            if ($sp === '') {
                $blockers[] = 'empty_starting_point_override';
                continue;
            }
            if (!ma_export_starting_point_exists($db, $sp)) {
                $warnings[] = 'starting_point_not_found_in_latest_export_snapshot:' . $sp;
            } else {
                $good[] = 'starting_point_found_in_export_snapshot:' . $sp;
            }
        }
    }

    $knownExpected = [
        '1756' => '612164',
    ];
    if (isset($knownExpected[$lessorId])) {
        $expected = $knownExpected[$lessorId];
        $ids = array_map(static fn(array $row): string => trim((string)($row['edxeix_starting_point_id'] ?? '')), $activeOverrides);
        if (!in_array($expected, $ids, true)) {
            $blockers[] = 'verified_starting_point_mismatch_expected_' . $expected;
        } else {
            $good[] = 'verified_expected_starting_point_present:' . $expected;
        }
    }

    $status = 'good';
    if ($blockers) {
        $status = 'bad';
    } elseif ($warnings) {
        $status = 'warn';
    }

    return [
        'lessor' => $lessor,
        'driver' => $driver,
        'vehicle' => $vehicle,
        'overrides' => $overrides,
        'active_overrides' => $activeOverrides,
        'status' => $status,
        'blockers' => array_values(array_unique($blockers)),
        'warnings' => array_values(array_unique($warnings)),
        'good' => array_values(array_unique($good)),
    ];
}

function ma_shell_begin(): void
{
    if (function_exists('opsui_shell_begin')) {
        opsui_shell_begin([
            'title' => 'Mapping Audit',
            'page_title' => 'Mapping Audit',
            'active_section' => 'Mapping Governance',
            'breadcrumbs' => 'Αρχική / Mapping / Audit',
            'safe_notice' => 'Read-only mapping failure-point audit. This page does not call Bolt, EDXEIX, or AADE, and does not write data.',
            'force_safe_notice' => true,
        ]);
        return;
    }
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Mapping Audit | gov.cabnet.app</title><style>body{font-family:Arial,Helvetica,sans-serif;background:#eef1f6;color:#07152f;margin:0;padding:18px}.card{background:#fff;border:1px solid #d8dde7;border-radius:8px;padding:16px;margin-bottom:16px}.badge{display:inline-block;padding:5px 9px;border-radius:999px;background:#eaf1ff;margin:2px}.badge-good{background:#dcfce7;color:#166534}.badge-warn{background:#fff7ed;color:#b45309}.badge-bad{background:#fee2e2;color:#991b1b}.table-wrap{overflow:auto}table{border-collapse:collapse;width:100%;background:#fff}th,td{border:1px solid #d8dde7;padding:8px;text-align:left;vertical-align:top}th{background:#f8fafc}.btn{display:inline-block;background:#4f5ea7;color:#fff;padding:9px 12px;border-radius:5px;text-decoration:none;font-weight:700}.small{font-size:13px;color:#667085}.grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}.metric{border:1px solid #d8dde7;border-radius:8px;padding:12px}.metric strong{display:block;font-size:24px}@media(max-width:900px){.grid{grid-template-columns:1fr}}</style></head><body>';
}

function ma_shell_end(): void
{
    if (function_exists('opsui_shell_end')) {
        opsui_shell_end();
        return;
    }
    echo '</body></html>';
}

$error = null;
$db = ma_db($error);
$audits = [];
$summary = ['good' => 0, 'warn' => 0, 'bad' => 0, 'lessors' => 0, 'blockers' => 0];
$globalFallbacks = [];

if ($db) {
    $lessors = ma_collect_lessors($db);
    foreach ($lessors as $lessor) {
        $audit = ma_audit_lessor($db, $lessor);
        $audits[] = $audit;
        $summary['lessors']++;
        $summary[$audit['status']]++;
        $summary['blockers'] += count($audit['blockers']);
    }
    usort($audits, static function (array $a, array $b): int {
        $rank = ['bad' => 0, 'warn' => 1, 'good' => 2];
        $ra = $rank[$a['status']] ?? 9;
        $rb = $rank[$b['status']] ?? 9;
        if ($ra !== $rb) { return $ra <=> $rb; }
        return strcasecmp((string)$a['lessor']['name'], (string)$b['lessor']['name']);
    });
    $globalFallbacks = ma_global_fallbacks($db);
}

ma_shell_begin();
?>
<style>
.mapping-audit-toolbar{display:flex;gap:10px;flex-wrap:wrap;margin:12px 0}.mapping-audit-blocker{color:#991b1b;font-size:13px;margin:2px 0}.mapping-audit-warning{color:#b45309;font-size:13px;margin:2px 0}.mapping-audit-good{color:#166534;font-size:13px;margin:2px 0}.mapping-audit-code{font-family:Consolas,Menlo,monospace;background:#0b1220;color:#dbeafe;border-radius:8px;padding:12px;white-space:pre-wrap;overflow:auto}.mapping-audit-table td{font-size:14px}.mapping-audit-muted{color:#667085;font-size:13px}</style>

<?php if (function_exists('ops_mapping_nav')) { echo ops_mapping_nav('audit'); } ?>

<section class="card hero">
    <h1>Mapping Audit</h1>
    <p>Read-only mapping failure-point audit for Bolt → EDXEIX. This page is designed to catch errors like wrong starting point fallback before a real ride is submitted.</p>
    <div>
        <?= ma_badge('READ ONLY', 'good') ?>
        <?= ma_badge('NO EDXEIX CALL', 'good') ?>
        <?= ma_badge('NO DB WRITE', 'good') ?>
        <?= ma_badge('MAPPING SAFETY', 'warn') ?>
    </div>
    <div class="mapping-audit-toolbar">
        <a class="btn" href="/ops/mapping-center.php">Mapping Center</a>
        <a class="btn dark" href="/ops/mapping-health.php">Mapping Health</a>
        <a class="btn dark" href="/ops/company-mapping-control.php">Company Mapping</a>
        <a class="btn dark" href="/ops/starting-point-control.php">Starting Points</a>
        <a class="btn dark" href="/ops/mapping-verification.php">Verification Register</a>
    </div>
</section>

<?php if (!$db): ?>
<section class="card">
    <h2>Audit unavailable</h2>
    <p class="badline"><strong><?= ma_h($error ?: 'Database unavailable.') ?></strong></p>
</section>
<?php else: ?>
<section class="card">
    <h2>Audit summary</h2>
    <div class="grid">
        <div class="metric"><strong><?= ma_h((string)$summary['lessors']) ?></strong><span>Lessors reviewed</span></div>
        <div class="metric"><strong><?= ma_h((string)$summary['bad']) ?></strong><span>Critical rows</span></div>
        <div class="metric"><strong><?= ma_h((string)$summary['warn']) ?></strong><span>Warning rows</span></div>
        <div class="metric"><strong><?= ma_h((string)$summary['blockers']) ?></strong><span>Total blockers</span></div>
    </div>
</section>

<section class="card">
    <h2>Lessor mapping failure points</h2>
    <div class="table-wrap">
        <table class="mapping-audit-table">
            <thead>
            <tr>
                <th>Status</th>
                <th>Lessor</th>
                <th>Drivers</th>
                <th>Vehicles</th>
                <th>Starting point overrides</th>
                <th>Findings</th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($audits as $audit):
                $lessor = $audit['lessor'];
                $lessorId = (string)$lessor['lessor_id'];
                $status = (string)$audit['status'];
                $statusLabel = $status === 'bad' ? 'BLOCK' : ($status === 'warn' ? 'WARN' : 'GOOD');
            ?>
                <tr>
                    <td><?= ma_badge($statusLabel, $status) ?></td>
                    <td>
                        <strong><?= ma_h((string)$lessor['name']) ?></strong><br>
                        <code><?= ma_h($lessorId) ?></code>
                        <div class="mapping-audit-muted">Sources: <?= ma_h(implode(', ', (array)$lessor['sources'])) ?></div>
                    </td>
                    <td><?= ma_h((string)$audit['driver']['mapped']) ?>/<?= ma_h((string)$audit['driver']['total']) ?> mapped</td>
                    <td><?= ma_h((string)$audit['vehicle']['mapped']) ?>/<?= ma_h((string)$audit['vehicle']['total']) ?> mapped</td>
                    <td>
                        <?php if (!$audit['active_overrides']): ?>
                            <?= ma_badge('missing override', 'bad') ?><br>
                            <span class="mapping-audit-warning">Global fallback may be used.</span>
                        <?php else: ?>
                            <?php foreach ($audit['active_overrides'] as $row): ?>
                                <?= ma_badge((string)($row['edxeix_starting_point_id'] ?? ''), 'good') ?><br>
                                <span class="mapping-audit-muted"><?= ma_h((string)($row['label'] ?? $row['internal_key'] ?? '')) ?></span><br>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php foreach ($audit['blockers'] as $msg): ?><div class="mapping-audit-blocker">● <?= ma_h((string)$msg) ?></div><?php endforeach; ?>
                        <?php foreach ($audit['warnings'] as $msg): ?><div class="mapping-audit-warning">▲ <?= ma_h((string)$msg) ?></div><?php endforeach; ?>
                        <?php if (!$audit['blockers'] && !$audit['warnings']): ?><div class="mapping-audit-good">✓ No blocker detected by this audit.</div><?php endif; ?>
                        <?php foreach ($audit['good'] as $msg): ?><div class="mapping-audit-good">✓ <?= ma_h((string)$msg) ?></div><?php endforeach; ?>
                    </td>
                    <td>
                        <a class="btn" href="/ops/company-mapping-detail.php?lessor=<?= ma_h(rawurlencode($lessorId)) ?>">Detail</a>
                        <a class="btn dark" href="/ops/starting-point-control.php?lessor=<?= ma_h(rawurlencode($lessorId)) ?>">Starting Point</a>
                        <a class="btn dark" href="/ops/mapping-verification.php?lessor=<?= ma_h(rawurlencode($lessorId)) ?>">Verify</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="card">
    <h2>Global starting point fallback rows</h2>
    <p>These rows are fallback only. Operational lessors should have explicit rows in <code>mapping_lessor_starting_points</code>.</p>
    <div class="table-wrap">
        <table>
            <thead><tr><th>ID</th><th>Internal key</th><th>Label</th><th>EDXEIX starting point ID</th><th>Active</th></tr></thead>
            <tbody>
            <?php foreach ($globalFallbacks as $row): ?>
                <tr>
                    <td><?= ma_h((string)($row['id'] ?? '')) ?></td>
                    <td><code><?= ma_h((string)($row['internal_key'] ?? '')) ?></code></td>
                    <td><?= ma_h((string)($row['label'] ?? '')) ?></td>
                    <td><?= ma_h((string)($row['edxeix_starting_point_id'] ?? '')) ?></td>
                    <td><?= ma_badge(((string)($row['is_active'] ?? '1') === '0') ? 'inactive' : 'active', ((string)($row['is_active'] ?? '1') === '0') ? 'neutral' : 'warn') ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$globalFallbacks): ?><tr><td colspan="5">No global fallback rows found.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="card">
    <h2>SQL diagnostics</h2>
    <p>Use these queries when checking a single lessor manually.</p>
    <pre class="mapping-audit-code">SELECT *
FROM mapping_lessor_starting_points
WHERE edxeix_lessor_id = 1756;

SELECT id, external_driver_name, edxeix_driver_id, edxeix_lessor_id, is_active
FROM mapping_drivers
WHERE edxeix_lessor_id = 1756;

SELECT id, plate, edxeix_vehicle_id, edxeix_lessor_id, is_active
FROM mapping_vehicles
WHERE edxeix_lessor_id = 1756;</pre>
</section>
<?php endif; ?>

<?php ma_shell_end(); ?>
