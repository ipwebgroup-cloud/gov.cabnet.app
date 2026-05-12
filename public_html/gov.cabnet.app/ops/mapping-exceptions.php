<?php
/**
 * gov.cabnet.app — Mapping Exception Queue v1.0
 *
 * Read-only priority list for Bolt → EDXEIX mapping issues.
 * No Bolt calls. No EDXEIX calls. No AADE calls. No DB writes.
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

function mex_h(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function mex_badge(string $text, string $type = 'neutral'): string
{
    if (function_exists('opsui_badge')) {
        return opsui_badge($text, $type);
    }
    return '<span class="badge badge-' . mex_h($type) . '">' . mex_h($text) . '</span>';
}

function mex_metric(mixed $value, string $label): string
{
    if (function_exists('opsui_metric')) {
        return opsui_metric($value, $label);
    }
    return '<div class="metric"><strong>' . mex_h((string)$value) . '</strong><span>' . mex_h($label) . '</span></div>';
}

function mex_shell_begin(): void
{
    if (function_exists('opsui_shell_begin')) {
        opsui_shell_begin([
            'title' => 'Mapping Exception Queue',
            'page_title' => 'Mapping Exception Queue',
            'active_section' => 'Mapping governance',
            'breadcrumbs' => 'Αρχική / Mapping / Exception Queue',
            'safe_notice' => 'Read-only mapping exception queue. This page does not call Bolt, EDXEIX, or AADE, and does not write data.',
            'force_safe_notice' => true,
        ]);
        return;
    }
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Mapping Exception Queue</title><link rel="stylesheet" href="/assets/css/gov-ops-edxeix.css?v=2.5"></head><body><main class="wrap">';
}

function mex_shell_end(): void
{
    if (function_exists('opsui_shell_end')) {
        opsui_shell_end();
        return;
    }
    echo '</main></body></html>';
}

function mex_bootstrap_path(): string
{
    return dirname(__DIR__, 3) . '/gov.cabnet.app_app/src/bootstrap.php';
}

function mex_db(?string &$error = null): ?mysqli
{
    static $db = null;
    static $loaded = false;
    static $loadError = null;
    if ($loaded) {
        $error = $loadError;
        return $db;
    }
    $loaded = true;
    $bootstrap = mex_bootstrap_path();
    if (!is_file($bootstrap)) {
        $loadError = 'Private app bootstrap not found.';
        $error = $loadError;
        return null;
    }
    try {
        $ctx = require $bootstrap;
        if (!is_array($ctx) || !isset($ctx['db']) || !method_exists($ctx['db'], 'connection')) {
            throw new RuntimeException('Invalid private app context.');
        }
        $db = $ctx['db']->connection();
        $error = null;
        return $db;
    } catch (Throwable $e) {
        $loadError = $e->getMessage();
        $error = $loadError;
        return null;
    }
}

function mex_table_exists(mysqli $db, string $table): bool
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

function mex_columns(mysqli $db, string $table): array
{
    $out = [];
    try {
        $stmt = $db->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
        $stmt->bind_param('s', $table);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $out[(string)$row['COLUMN_NAME']] = true;
        }
    } catch (Throwable) {}
    return $out;
}

function mex_first_col(array $cols, array $choices): ?string
{
    foreach ($choices as $col) {
        if (isset($cols[$col])) {
            return $col;
        }
    }
    return null;
}

function mex_qi(string $identifier): string
{
    return '`' . str_replace('`', '``', $identifier) . '`';
}

function mex_fetch_all(mysqli $db, string $sql): array
{
    $out = [];
    try {
        $res = $db->query($sql);
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $out[] = $row;
            }
        }
    } catch (Throwable) {}
    return $out;
}

function mex_fetch_lessor_names(mysqli $db): array
{
    $names = [];
    if (!mex_table_exists($db, 'edxeix_export_lessors')) {
        return $names;
    }
    $cols = mex_columns($db, 'edxeix_export_lessors');
    $idCol = mex_first_col($cols, ['id', 'edxeix_lessor_id', 'lessor_id']);
    $nameCol = mex_first_col($cols, ['name', 'label', 'lessor_name', 'company_name', 'title']);
    if (!$idCol) {
        return $names;
    }
    $selectName = $nameCol ? mex_qi($nameCol) : "''";
    $rows = mex_fetch_all($db, 'SELECT ' . mex_qi($idCol) . ' AS lessor_id, ' . $selectName . ' AS lessor_name FROM edxeix_export_lessors LIMIT 1000');
    foreach ($rows as $row) {
        $id = trim((string)($row['lessor_id'] ?? ''));
        if ($id !== '') {
            $names[$id] = trim((string)($row['lessor_name'] ?? ''));
        }
    }
    return $names;
}

function mex_group_counts(mysqli $db, string $table, string $idCol, string $mapCol): array
{
    $out = [];
    if (!mex_table_exists($db, $table)) {
        return $out;
    }
    $cols = mex_columns($db, $table);
    if (!isset($cols[$idCol]) || !isset($cols[$mapCol])) {
        return $out;
    }
    $activeClause = isset($cols['is_active']) ? 'WHERE is_active = 1' : '';
    $sql = 'SELECT ' . mex_qi($idCol) . ' AS lessor_id, COUNT(*) AS total, '
        . 'SUM(CASE WHEN ' . mex_qi($mapCol) . ' IS NOT NULL AND ' . mex_qi($mapCol) . " <> '' AND " . mex_qi($mapCol) . ' <> 0 THEN 1 ELSE 0 END) AS mapped, '
        . 'SUM(CASE WHEN ' . mex_qi($mapCol) . ' IS NULL OR ' . mex_qi($mapCol) . " = '' OR " . mex_qi($mapCol) . ' = 0 THEN 1 ELSE 0 END) AS unmapped '
        . 'FROM ' . mex_qi($table) . ' ' . $activeClause . ' GROUP BY ' . mex_qi($idCol);
    foreach (mex_fetch_all($db, $sql) as $row) {
        $id = trim((string)($row['lessor_id'] ?? ''));
        if ($id === '') { continue; }
        $out[$id] = [
            'total' => (int)($row['total'] ?? 0),
            'mapped' => (int)($row['mapped'] ?? 0),
            'unmapped' => (int)($row['unmapped'] ?? 0),
        ];
    }
    return $out;
}

function mex_overrides(mysqli $db): array
{
    $out = [];
    if (!mex_table_exists($db, 'mapping_lessor_starting_points')) {
        return $out;
    }
    $rows = mex_fetch_all($db, "SELECT id, edxeix_lessor_id, internal_key, label, edxeix_starting_point_id, is_active FROM mapping_lessor_starting_points ORDER BY edxeix_lessor_id ASC, is_active DESC, id ASC");
    foreach ($rows as $row) {
        $id = trim((string)($row['edxeix_lessor_id'] ?? ''));
        if ($id === '') { continue; }
        $out[$id][] = $row;
    }
    return $out;
}

function mex_export_starting_ids(mysqli $db): array
{
    $ids = [];
    if (!mex_table_exists($db, 'edxeix_export_starting_points')) {
        return $ids;
    }
    $cols = mex_columns($db, 'edxeix_export_starting_points');
    $idCol = mex_first_col($cols, ['id', 'edxeix_starting_point_id', 'starting_point_id']);
    if (!$idCol) {
        return $ids;
    }
    foreach (mex_fetch_all($db, 'SELECT ' . mex_qi($idCol) . ' AS id FROM edxeix_export_starting_points LIMIT 5000') as $row) {
        $id = trim((string)($row['id'] ?? ''));
        if ($id !== '') { $ids[$id] = true; }
    }
    return $ids;
}

function mex_verifications(mysqli $db): array
{
    $out = [];
    if (!mex_table_exists($db, 'mapping_verification_status')) {
        return $out;
    }
    $cols = mex_columns($db, 'mapping_verification_status');
    $lessorCol = mex_first_col($cols, ['edxeix_lessor_id', 'lessor_id']);
    if (!$lessorCol) {
        return $out;
    }
    $statusCol = mex_first_col($cols, ['status', 'verification_status']);
    $spCol = mex_first_col($cols, ['verified_starting_point_id', 'edxeix_starting_point_id', 'starting_point_id']);
    $dateCol = mex_first_col($cols, ['verified_at', 'updated_at', 'created_at']);
    $sql = 'SELECT ' . mex_qi($lessorCol) . ' AS lessor_id'
        . ($statusCol ? ', ' . mex_qi($statusCol) . ' AS status' : ", '' AS status")
        . ($spCol ? ', ' . mex_qi($spCol) . ' AS starting_point_id' : ", '' AS starting_point_id")
        . ($dateCol ? ', ' . mex_qi($dateCol) . ' AS verified_at' : ", '' AS verified_at")
        . ' FROM mapping_verification_status ORDER BY ' . ($dateCol ? mex_qi($dateCol) : mex_qi($lessorCol)) . ' DESC LIMIT 2000';
    foreach (mex_fetch_all($db, $sql) as $row) {
        $id = trim((string)($row['lessor_id'] ?? ''));
        if ($id !== '' && !isset($out[$id])) {
            $out[$id] = $row;
        }
    }
    return $out;
}

function mex_add_exception(array &$items, string $lessorId, string $lessorName, string $severity, string $code, string $message, string $action): void
{
    $rank = ['critical' => 1, 'high' => 2, 'warn' => 3, 'info' => 4][$severity] ?? 5;
    $items[] = compact('lessorId', 'lessorName', 'severity', 'code', 'message', 'action', 'rank');
}

$error = null;
$db = mex_db($error);
$lessorNames = [];
$driverCounts = [];
$vehicleCounts = [];
$overrides = [];
$exportStartingIds = [];
$verifications = [];
$exceptions = [];
$allLessorIds = [];

if ($db) {
    $lessorNames = mex_fetch_lessor_names($db);
    $driverCounts = mex_group_counts($db, 'mapping_drivers', 'edxeix_lessor_id', 'edxeix_driver_id');
    $vehicleCounts = mex_group_counts($db, 'mapping_vehicles', 'edxeix_lessor_id', 'edxeix_vehicle_id');
    $overrides = mex_overrides($db);
    $exportStartingIds = mex_export_starting_ids($db);
    $verifications = mex_verifications($db);

    foreach ([$lessorNames, $driverCounts, $vehicleCounts, $overrides, $verifications] as $set) {
        foreach (array_keys($set) as $id) {
            if ((string)$id !== '') { $allLessorIds[(string)$id] = true; }
        }
    }

    foreach (array_keys($allLessorIds) as $lessorId) {
        $name = $lessorNames[$lessorId] ?? '';
        $drivers = $driverCounts[$lessorId] ?? ['total' => 0, 'mapped' => 0, 'unmapped' => 0];
        $vehicles = $vehicleCounts[$lessorId] ?? ['total' => 0, 'mapped' => 0, 'unmapped' => 0];
        $activeOperational = (($drivers['total'] ?? 0) + ($vehicles['total'] ?? 0)) > 0;
        $rows = $overrides[$lessorId] ?? [];
        $activeOverrides = array_values(array_filter($rows, static fn($row): bool => (string)($row['is_active'] ?? '1') !== '0'));

        if ($activeOperational && count($activeOverrides) === 0) {
            mex_add_exception($exceptions, $lessorId, $name, 'critical', 'missing_lessor_specific_starting_point', 'Operational lessor has mapped active drivers/vehicles but no active lessor-specific starting point override. Resolver may use global fallback.', 'Open Starting Point Control and add a verified override for this lessor.');
        }
        if (count($activeOverrides) > 1) {
            mex_add_exception($exceptions, $lessorId, $name, 'warn', 'multiple_active_starting_point_overrides', 'More than one active starting point override exists for this lessor.', 'Keep only the verified active override and deactivate obsolete rows.');
        }
        foreach ($activeOverrides as $row) {
            $sp = trim((string)($row['edxeix_starting_point_id'] ?? ''));
            if ($sp === '') {
                mex_add_exception($exceptions, $lessorId, $name, 'critical', 'empty_starting_point_override', 'Active starting point override has an empty EDXEIX starting point ID.', 'Update the override with a live EDXEIX-confirmed starting point ID.');
            } elseif ($exportStartingIds && !isset($exportStartingIds[$sp])) {
                mex_add_exception($exceptions, $lessorId, $name, 'high', 'starting_point_not_in_export_snapshot', 'Starting point override ' . $sp . ' was not found in the latest local EDXEIX starting point export snapshot.', 'Reconfirm in live EDXEIX or refresh the EDXEIX export snapshot.');
            }
        }
        if (($drivers['unmapped'] ?? 0) > 0) {
            mex_add_exception($exceptions, $lessorId, $name, 'high', 'active_unmapped_drivers', (string)$drivers['unmapped'] . ' active driver row(s) under this lessor do not have an EDXEIX driver ID.', 'Open mappings editor and complete or deactivate unmapped drivers.');
        }
        if (($vehicles['unmapped'] ?? 0) > 0) {
            mex_add_exception($exceptions, $lessorId, $name, 'high', 'active_unmapped_vehicles', (string)$vehicles['unmapped'] . ' active vehicle row(s) under this lessor do not have an EDXEIX vehicle ID.', 'Open mappings editor and complete or deactivate unmapped vehicles.');
        }
        if ($lessorId === '1756') {
            $hasWhiteblue = false;
            foreach ($activeOverrides as $row) {
                if (trim((string)($row['edxeix_starting_point_id'] ?? '')) === '612164') {
                    $hasWhiteblue = true;
                    break;
                }
            }
            if (!$hasWhiteblue) {
                mex_add_exception($exceptions, $lessorId, $name, 'critical', 'whiteblue_verified_starting_point_missing', 'WHITEBLUE / 1756 does not have the verified starting point 612164 active.', 'Restore verified override: WHITEBLUE / 1756 → 612164.');
            }
        }
        $verification = $verifications[$lessorId] ?? null;
        if ($activeOperational && (!$verification || !in_array(strtolower((string)($verification['status'] ?? '')), ['verified', 'ok', 'confirmed'], true))) {
            mex_add_exception($exceptions, $lessorId, $name, 'warn', 'verification_register_missing_or_not_verified', 'No verified mapping register entry was found for this operational lessor.', 'Record a verification decision after checking live EDXEIX.');
        }
    }

    usort($exceptions, static function (array $a, array $b): int {
        return ($a['rank'] <=> $b['rank']) ?: strcmp((string)$a['lessorId'], (string)$b['lessorId']) ?: strcmp((string)$a['code'], (string)$b['code']);
    });
}

$counts = ['critical' => 0, 'high' => 0, 'warn' => 0, 'info' => 0];
foreach ($exceptions as $item) {
    $sev = (string)($item['severity'] ?? 'info');
    if (isset($counts[$sev])) { $counts[$sev]++; }
}

mex_shell_begin();
if (function_exists('gov_mapping_nav_render')) { gov_mapping_nav_render('/ops/mapping-exceptions.php'); }
?>
<style>
.mapping-exception-hero{border-left:6px solid #d4922d}.exception-table td{vertical-align:top}.severity-critical{background:#fee2e2;color:#991b1b;border:1px solid #fecaca}.severity-high{background:#fff7ed;color:#9a3412;border:1px solid #fed7aa}.severity-warn{background:#fef9c3;color:#854d0e;border:1px solid #fde68a}.severity-info{background:#e0f2fe;color:#075985;border:1px solid #bae6fd}.severity-pill{display:inline-flex;border-radius:999px;padding:5px 9px;font-size:12px;font-weight:700;text-transform:uppercase}.mapping-action-links{display:flex;gap:6px;flex-wrap:wrap}.mapping-action-links a{display:inline-flex;text-decoration:none;background:#4f5ea7;color:#fff;border-radius:5px;padding:6px 9px;font-size:12px;font-weight:700}.mapping-action-links a.secondary{background:#6b7280}.mapping-code{font-family:Consolas,monospace;background:#eef2ff;border-radius:4px;padding:2px 5px;font-size:12px}.exception-empty{background:#ecfdf3;border:1px solid #bbf7d0;border-radius:6px;padding:18px;color:#166534}.exception-meta{color:#667085;font-size:12px;margin-top:4px}.grid-4{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}@media(max-width:900px){.grid-4{grid-template-columns:1fr 1fr}}@media(max-width:640px){.grid-4{grid-template-columns:1fr}}
</style>

<section class="card hero mapping-exception-hero">
    <h1>Mapping Exception Queue</h1>
    <p>Prioritized list of mapping issues that can break the Bolt → EDXEIX workflow. Fix these before relying on a lessor/driver/vehicle combination in production.</p>
    <div>
        <?= mex_badge('READ ONLY', 'good') ?>
        <?= mex_badge('NO EDXEIX CALL', 'good') ?>
        <?= mex_badge('NO DB WRITE', 'good') ?>
        <?= mex_badge('PRODUCTION TOOL UNCHANGED', 'good') ?>
    </div>
</section>

<?php if ($error): ?>
<section class="card">
    <h2>Database unavailable</h2>
    <p class="badline"><strong><?= mex_h($error) ?></strong></p>
</section>
<?php else: ?>
<section class="grid-4">
    <?= mex_metric((string)$counts['critical'], 'Critical exceptions') ?>
    <?= mex_metric((string)$counts['high'], 'High exceptions') ?>
    <?= mex_metric((string)$counts['warn'], 'Warnings') ?>
    <?= mex_metric((string)count($allLessorIds), 'Known lessors reviewed') ?>
</section>

<section class="card">
    <h2>Exception queue</h2>
    <p class="small">Critical items should be resolved before live use. This page intentionally does not edit mappings; use the linked control pages.</p>
    <?php if (!$exceptions): ?>
        <div class="exception-empty"><strong>No mapping exceptions detected by this audit.</strong><br>Continue to verify each lessor in the Mapping Verification Register before live use.</div>
    <?php else: ?>
        <div class="table-wrap"><table class="exception-table">
            <thead>
                <tr>
                    <th>Severity</th>
                    <th>Lessor</th>
                    <th>Issue</th>
                    <th>Recommended action</th>
                    <th>Links</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($exceptions as $item):
                $lessorId = (string)$item['lessorId'];
                $name = trim((string)$item['lessorName']);
                $sev = (string)$item['severity'];
            ?>
                <tr>
                    <td><span class="severity-pill severity-<?= mex_h($sev) ?>"><?= mex_h($sev) ?></span></td>
                    <td><strong><?= mex_h($name !== '' ? $name : 'Lessor ' . $lessorId) ?></strong><div class="exception-meta">ID <?= mex_h($lessorId) ?></div></td>
                    <td><span class="mapping-code"><?= mex_h((string)$item['code']) ?></span><div style="margin-top:6px;"><?= mex_h((string)$item['message']) ?></div></td>
                    <td><?= mex_h((string)$item['action']) ?></td>
                    <td>
                        <div class="mapping-action-links">
                            <a href="/ops/company-mapping-detail.php?lessor=<?= rawurlencode($lessorId) ?>">Details</a>
                            <a href="/ops/starting-point-control.php?lessor=<?= rawurlencode($lessorId) ?>">Starting point</a>
                            <a class="secondary" href="/ops/mapping-verification.php?lessor=<?= rawurlencode($lessorId) ?>">Verify</a>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    <?php endif; ?>
</section>

<section class="card">
    <h2>Operating rule</h2>
    <ul class="list">
        <li>Every operational lessor should have a lessor-specific starting point override in <code>mapping_lessor_starting_points</code>.</li>
        <li>Global starting points are fallback only; they should not be trusted for production without visible operator verification.</li>
        <li>Driver, vehicle, lessor, and starting point must all be reviewed before any EDXEIX POST.</li>
        <li>WHITEBLUE / 1756 has verified starting point <code>612164</code>; any other active value is a critical exception.</li>
    </ul>
</section>
<?php endif; ?>
<?php
mex_shell_end();
