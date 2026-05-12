<?php
/**
 * gov.cabnet.app — Mapping Exception Queue v1.1 schema-safe hotfix
 *
 * Read-only priority list for Bolt → EDXEIX mapping risks.
 * No Bolt calls. No EDXEIX calls. No AADE calls. No DB writes.
 *
 * v1.1:
 * - Avoids aggregate SQL and hard-coded optional columns.
 * - Uses information_schema and PHP-side counting to survive schema drift.
 * - Shows a safe runtime diagnostic card instead of throwing a 500 when possible.
 */

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Robots-Tag: noindex,nofollow', true);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Make this diagnostic page tolerant even if bootstrap enabled mysqli strict mode elsewhere.
if (function_exists('mysqli_report')) {
    mysqli_report(MYSQLI_REPORT_OFF);
}

$shellFile = __DIR__ . '/_shell.php';
if (is_file($shellFile)) {
    require_once $shellFile;
}
$mappingNavFile = __DIR__ . '/_mapping_nav.php';
if (is_file($mappingNavFile)) {
    require_once $mappingNavFile;
}

function meq_h(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function meq_badge(string $text, string $type = 'neutral'): string
{
    if (function_exists('opsui_badge')) {
        return opsui_badge($text, $type);
    }
    return '<span class="badge badge-' . meq_h($type) . '">' . meq_h($text) . '</span>';
}

function meq_metric(mixed $value, string $label): string
{
    if (function_exists('opsui_metric')) {
        return opsui_metric($value, $label);
    }
    return '<div class="metric"><strong>' . meq_h((string)$value) . '</strong><span>' . meq_h($label) . '</span></div>';
}

function meq_shell_begin(): void
{
    if (function_exists('opsui_shell_begin')) {
        opsui_shell_begin([
            'title' => 'Mapping Exception Queue',
            'page_title' => 'Mapping Exception Queue',
            'active_section' => 'Mapping governance',
            'breadcrumbs' => 'Αρχική / Mapping / Exception Queue',
            'safe_notice' => 'Read-only schema-safe mapping exception queue. This page does not call Bolt, EDXEIX, or AADE, and does not write data.',
            'force_safe_notice' => true,
        ]);
        return;
    }
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Mapping Exception Queue</title><link rel="stylesheet" href="/assets/css/gov-ops-edxeix.css?v=2.5"></head><body><main class="wrap">';
}

function meq_shell_end(): void
{
    if (function_exists('opsui_shell_end')) {
        opsui_shell_end();
        return;
    }
    echo '</main></body></html>';
}

function meq_bootstrap_path(): string
{
    return dirname(__DIR__, 3) . '/gov.cabnet.app_app/src/bootstrap.php';
}

function meq_db(?string &$error = null): ?mysqli
{
    $bootstrap = meq_bootstrap_path();
    if (!is_file($bootstrap)) {
        $error = 'Private app bootstrap not found.';
        return null;
    }
    try {
        $ctx = require $bootstrap;
        if (!is_array($ctx) || !isset($ctx['db']) || !method_exists($ctx['db'], 'connection')) {
            throw new RuntimeException('Invalid private app context.');
        }
        $db = $ctx['db']->connection();
        if (!$db instanceof mysqli) {
            throw new RuntimeException('Private app DB connection is not mysqli.');
        }
        $error = null;
        return $db;
    } catch (Throwable $e) {
        $error = $e->getMessage();
        return null;
    }
}

function meq_table_exists(mysqli $db, string $table): bool
{
    try {
        $stmt = $db->prepare('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1');
        if (!$stmt) { return false; }
        $stmt->bind_param('s', $table);
        if (!$stmt->execute()) { return false; }
        $result = $stmt->get_result();
        return $result && (bool)$result->fetch_assoc();
    } catch (Throwable) {
        return false;
    }
}

function meq_columns(mysqli $db, string $table): array
{
    $out = [];
    try {
        $stmt = $db->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
        if (!$stmt) { return $out; }
        $stmt->bind_param('s', $table);
        if (!$stmt->execute()) { return $out; }
        $res = $stmt->get_result();
        if (!$res) { return $out; }
        while ($row = $res->fetch_assoc()) {
            $name = (string)($row['COLUMN_NAME'] ?? '');
            if ($name !== '') { $out[$name] = true; }
        }
    } catch (Throwable) {}
    return $out;
}

function meq_first_col(array $cols, array $choices): ?string
{
    foreach ($choices as $col) {
        if (isset($cols[$col])) { return (string)$col; }
    }
    return null;
}

function meq_qi(string $identifier): string
{
    return '`' . str_replace('`', '``', $identifier) . '`';
}

function meq_fetch_all(mysqli $db, string $sql): array
{
    $out = [];
    try {
        $res = $db->query($sql);
        if (!$res) { return $out; }
        while ($row = $res->fetch_assoc()) { $out[] = $row; }
    } catch (Throwable) {}
    return $out;
}

function meq_select_rows(mysqli $db, string $table, array $aliases, int $limit = 5000): array
{
    if (!meq_table_exists($db, $table)) { return []; }
    $cols = meq_columns($db, $table);
    $select = [];
    foreach ($aliases as $alias => $choices) {
        $col = meq_first_col($cols, is_array($choices) ? $choices : [(string)$choices]);
        $select[] = $col ? meq_qi($col) . ' AS ' . meq_qi((string)$alias) : "'' AS " . meq_qi((string)$alias);
    }
    $orderCol = meq_first_col($cols, ['updated_at', 'created_at', 'id']);
    $sql = 'SELECT ' . implode(', ', $select) . ' FROM ' . meq_qi($table)
        . ($orderCol ? ' ORDER BY ' . meq_qi($orderCol) . ' DESC' : '')
        . ' LIMIT ' . max(1, min(10000, $limit));
    return meq_fetch_all($db, $sql);
}

function meq_is_active(array $row): bool
{
    $raw = trim((string)($row['is_active'] ?? '1'));
    return $raw === '' || $raw === '1' || strtolower($raw) === 'true' || strtolower($raw) === 'yes';
}

function meq_has_id(mixed $value): bool
{
    $v = trim((string)$value);
    return $v !== '' && $v !== '0';
}

function meq_lessor_names(mysqli $db): array
{
    $names = [];
    foreach (meq_select_rows($db, 'edxeix_export_lessors', [
        'lessor_id' => ['id', 'edxeix_lessor_id', 'lessor_id'],
        'lessor_name' => ['name', 'label', 'lessor_name', 'company_name', 'title'],
    ], 1000) as $row) {
        $id = trim((string)($row['lessor_id'] ?? ''));
        if ($id !== '') { $names[$id] = trim((string)($row['lessor_name'] ?? '')); }
    }
    return $names;
}

function meq_mapping_counts(mysqli $db, string $table, array $mapChoices): array
{
    $out = [];
    foreach (meq_select_rows($db, $table, [
        'lessor_id' => ['edxeix_lessor_id', 'lessor_id'],
        'map_id' => $mapChoices,
        'is_active' => ['is_active'],
    ], 10000) as $row) {
        if (!meq_is_active($row)) { continue; }
        $lessor = trim((string)($row['lessor_id'] ?? ''));
        if ($lessor === '') { continue; }
        if (!isset($out[$lessor])) { $out[$lessor] = ['total' => 0, 'mapped' => 0, 'unmapped' => 0]; }
        $out[$lessor]['total']++;
        if (meq_has_id($row['map_id'] ?? '')) { $out[$lessor]['mapped']++; } else { $out[$lessor]['unmapped']++; }
    }
    return $out;
}

function meq_overrides(mysqli $db): array
{
    $out = [];
    foreach (meq_select_rows($db, 'mapping_lessor_starting_points', [
        'id' => ['id'],
        'lessor_id' => ['edxeix_lessor_id', 'lessor_id'],
        'internal_key' => ['internal_key'],
        'label' => ['label', 'name'],
        'starting_point_id' => ['edxeix_starting_point_id', 'starting_point_id'],
        'is_active' => ['is_active'],
        'updated_at' => ['updated_at', 'created_at'],
    ], 10000) as $row) {
        $lessor = trim((string)($row['lessor_id'] ?? ''));
        if ($lessor === '') { continue; }
        $out[$lessor][] = $row;
    }
    return $out;
}

function meq_export_starting_ids(mysqli $db): array
{
    $ids = [];
    foreach (meq_select_rows($db, 'edxeix_export_starting_points', [
        'id' => ['id', 'edxeix_starting_point_id', 'starting_point_id'],
        'label' => ['label', 'name', 'title', 'address'],
    ], 10000) as $row) {
        $id = trim((string)($row['id'] ?? ''));
        if ($id !== '') { $ids[$id] = trim((string)($row['label'] ?? '')); }
    }
    return $ids;
}

function meq_verifications(mysqli $db): array
{
    $out = [];
    foreach (meq_select_rows($db, 'mapping_verification_status', [
        'lessor_id' => ['edxeix_lessor_id', 'lessor_id'],
        'status' => ['status', 'verification_status'],
        'starting_point_id' => ['verified_starting_point_id', 'edxeix_starting_point_id', 'starting_point_id'],
        'verified_at' => ['verified_at', 'updated_at', 'created_at'],
    ], 5000) as $row) {
        $lessor = trim((string)($row['lessor_id'] ?? ''));
        if ($lessor !== '' && !isset($out[$lessor])) { $out[$lessor] = $row; }
    }
    return $out;
}

function meq_add_exception(array &$items, string $lessorId, string $lessorName, string $severity, string $code, string $message, string $action): void
{
    $rankMap = ['critical' => 1, 'high' => 2, 'warn' => 3, 'info' => 4];
    $rank = $rankMap[$severity] ?? 5;
    $items[] = [
        'lessorId' => $lessorId,
        'lessorName' => $lessorName,
        'severity' => $severity,
        'code' => $code,
        'message' => $message,
        'action' => $action,
        'rank' => $rank,
    ];
}

$error = null;
$runtimeError = null;
$lessorNames = [];
$driverCounts = [];
$vehicleCounts = [];
$overrides = [];
$exportStartingIds = [];
$verifications = [];
$exceptions = [];
$allLessorIds = [];

try {
    $db = meq_db($error);
    if ($db) {
        $lessorNames = meq_lessor_names($db);
        $driverCounts = meq_mapping_counts($db, 'mapping_drivers', ['edxeix_driver_id', 'driver_id']);
        $vehicleCounts = meq_mapping_counts($db, 'mapping_vehicles', ['edxeix_vehicle_id', 'vehicle_id']);
        $overrides = meq_overrides($db);
        $exportStartingIds = meq_export_starting_ids($db);
        $verifications = meq_verifications($db);

        foreach ([$lessorNames, $driverCounts, $vehicleCounts, $overrides, $verifications] as $set) {
            foreach (array_keys($set) as $id) {
                $id = trim((string)$id);
                if ($id !== '') { $allLessorIds[$id] = true; }
            }
        }

        foreach (array_keys($allLessorIds) as $lessorId) {
            $name = $lessorNames[$lessorId] ?? '';
            $drivers = $driverCounts[$lessorId] ?? ['total' => 0, 'mapped' => 0, 'unmapped' => 0];
            $vehicles = $vehicleCounts[$lessorId] ?? ['total' => 0, 'mapped' => 0, 'unmapped' => 0];
            $activeOperational = ((int)$drivers['total'] + (int)$vehicles['total']) > 0;
            $rows = $overrides[$lessorId] ?? [];
            $activeOverrides = [];
            foreach ($rows as $row) {
                if (meq_is_active($row)) { $activeOverrides[] = $row; }
            }

            if ($activeOperational && count($activeOverrides) === 0) {
                meq_add_exception($exceptions, $lessorId, $name, 'critical', 'missing_lessor_specific_starting_point', 'Operational lessor has mapped active drivers/vehicles but no active lessor-specific starting point override. Resolver may use global fallback.', 'Open Starting Point Control and add a verified override for this lessor.');
            }
            if (count($activeOverrides) > 1) {
                meq_add_exception($exceptions, $lessorId, $name, 'warn', 'multiple_active_starting_point_overrides', 'More than one active starting point override exists for this lessor.', 'Keep only the verified active override and deactivate obsolete rows.');
            }
            foreach ($activeOverrides as $row) {
                $sp = trim((string)($row['starting_point_id'] ?? ''));
                if ($sp === '') {
                    meq_add_exception($exceptions, $lessorId, $name, 'critical', 'empty_starting_point_override', 'Active starting point override has an empty EDXEIX starting point ID.', 'Update the override with a live EDXEIX-confirmed starting point ID.');
                } elseif ($exportStartingIds && !isset($exportStartingIds[$sp])) {
                    meq_add_exception($exceptions, $lessorId, $name, 'high', 'starting_point_not_in_export_snapshot', 'Starting point override ' . $sp . ' was not found in the latest local EDXEIX starting point export snapshot.', 'Reconfirm in live EDXEIX or refresh the EDXEIX export snapshot.');
                }
            }
            if ((int)($drivers['unmapped'] ?? 0) > 0) {
                meq_add_exception($exceptions, $lessorId, $name, 'high', 'active_unmapped_drivers', (string)$drivers['unmapped'] . ' active driver row(s) under this lessor do not have an EDXEIX driver ID.', 'Open mappings editor and complete or deactivate unmapped drivers.');
            }
            if ((int)($vehicles['unmapped'] ?? 0) > 0) {
                meq_add_exception($exceptions, $lessorId, $name, 'high', 'active_unmapped_vehicles', (string)$vehicles['unmapped'] . ' active vehicle row(s) under this lessor do not have an EDXEIX vehicle ID.', 'Open mappings editor and complete or deactivate unmapped vehicles.');
            }
            if ($lessorId === '1756') {
                $hasWhiteblue = false;
                foreach ($activeOverrides as $row) {
                    if (trim((string)($row['starting_point_id'] ?? '')) === '612164') {
                        $hasWhiteblue = true;
                        break;
                    }
                }
                if (!$hasWhiteblue) {
                    meq_add_exception($exceptions, $lessorId, $name, 'critical', 'whiteblue_verified_starting_point_missing', 'WHITEBLUE / 1756 does not have the verified starting point 612164 active.', 'Restore verified override: WHITEBLUE / 1756 → 612164.');
                }
            }
            $verification = $verifications[$lessorId] ?? null;
            $status = strtolower(trim((string)($verification['status'] ?? '')));
            if ($activeOperational && (!$verification || !in_array($status, ['verified', 'ok', 'confirmed'], true))) {
                meq_add_exception($exceptions, $lessorId, $name, 'warn', 'verification_register_missing_or_not_verified', 'No verified mapping register entry was found for this operational lessor.', 'Record a verification decision after checking live EDXEIX.');
            }
        }

        usort($exceptions, static function (array $a, array $b): int {
            return ((int)$a['rank'] <=> (int)$b['rank']) ?: strcmp((string)$a['lessorId'], (string)$b['lessorId']) ?: strcmp((string)$a['code'], (string)$b['code']);
        });
    }
} catch (Throwable $e) {
    $runtimeError = $e->getMessage();
}

$counts = ['critical' => 0, 'high' => 0, 'warn' => 0, 'info' => 0];
foreach ($exceptions as $item) {
    $sev = (string)($item['severity'] ?? 'info');
    if (isset($counts[$sev])) { $counts[$sev]++; }
}

meq_shell_begin();
if (function_exists('gov_mapping_nav_render')) { gov_mapping_nav_render('/ops/mapping-exceptions.php'); }
?>
<style>
.mapping-exception-hero{border-left:6px solid #d4922d}.exception-table td{vertical-align:top}.severity-critical{background:#fee2e2;color:#991b1b;border:1px solid #fecaca}.severity-high{background:#fff7ed;color:#9a3412;border:1px solid #fed7aa}.severity-warn{background:#fef9c3;color:#854d0e;border:1px solid #fde68a}.severity-info{background:#e0f2fe;color:#075985;border:1px solid #bae6fd}.severity-pill{display:inline-flex;border-radius:999px;padding:5px 9px;font-size:12px;font-weight:700;text-transform:uppercase}.mapping-action-links{display:flex;gap:6px;flex-wrap:wrap}.mapping-action-links a{display:inline-flex;text-decoration:none;background:#4f5ea7;color:#fff;border-radius:5px;padding:6px 9px;font-size:12px;font-weight:700}.mapping-action-links a.secondary{background:#6b7280}.mapping-code{font-family:Consolas,monospace;background:#eef2ff;border-radius:4px;padding:2px 5px;font-size:12px}.exception-empty{background:#ecfdf3;border:1px solid #bbf7d0;border-radius:6px;padding:18px;color:#166534}.exception-meta{color:#667085;font-size:12px;margin-top:4px}.grid-4{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}@media(max-width:900px){.grid-4{grid-template-columns:1fr 1fr}}@media(max-width:640px){.grid-4{grid-template-columns:1fr}}
</style>

<section class="card hero mapping-exception-hero">
    <h1>Mapping Exception Queue</h1>
    <p>Prioritized list of mapping issues that can break the Bolt → EDXEIX workflow. Fix these before relying on a lessor/driver/vehicle combination in production.</p>
    <div>
        <?= meq_badge('READ ONLY', 'good') ?>
        <?= meq_badge('NO EDXEIX CALL', 'good') ?>
        <?= meq_badge('NO DB WRITE', 'good') ?>
        <?= meq_badge('PRODUCTION TOOL UNCHANGED', 'good') ?>
        <?= meq_badge('SCHEMA SAFE', 'good') ?>
    </div>
</section>

<?php if ($runtimeError): ?>
<section class="card">
    <h2>Runtime diagnostic</h2>
    <p class="badline"><strong><?= meq_h($runtimeError) ?></strong></p>
    <p class="small">The page caught this error instead of returning a blank 500. Please send this message back to Sophion if it appears.</p>
</section>
<?php elseif ($error): ?>
<section class="card">
    <h2>Database unavailable</h2>
    <p class="badline"><strong><?= meq_h($error) ?></strong></p>
</section>
<?php else: ?>
<section class="grid-4">
    <?= meq_metric((string)$counts['critical'], 'Critical exceptions') ?>
    <?= meq_metric((string)$counts['high'], 'High exceptions') ?>
    <?= meq_metric((string)$counts['warn'], 'Warnings') ?>
    <?= meq_metric((string)count($allLessorIds), 'Known lessors reviewed') ?>
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
                    <td><span class="severity-pill severity-<?= meq_h($sev) ?>"><?= meq_h($sev) ?></span></td>
                    <td><strong><?= meq_h($name !== '' ? $name : 'Lessor ' . $lessorId) ?></strong><div class="exception-meta">ID <?= meq_h($lessorId) ?></div></td>
                    <td><span class="mapping-code"><?= meq_h((string)$item['code']) ?></span><div style="margin-top:6px;"><?= meq_h((string)$item['message']) ?></div></td>
                    <td><?= meq_h((string)$item['action']) ?></td>
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
meq_shell_end();
