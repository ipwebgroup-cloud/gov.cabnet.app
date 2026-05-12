<?php
/**
 * gov.cabnet.app — Company Mapping Control v0.1 / Phase 34
 *
 * Read-only company/lessor mapping governance page.
 * Purpose: detect missing/wrong lessor-specific starting point overrides and mapping health risks.
 *
 * Safety contract:
 * - Read-only DB SELECTs.
 * - No Bolt calls.
 * - No EDXEIX calls.
 * - No AADE calls.
 * - No workflow writes.
 * - No live submission behavior.
 * - Does not modify the production pre-ride tool.
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

function cmc_h(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function cmc_badge(string $text, string $type = 'neutral'): string
{
    $type = in_array($type, ['good', 'warn', 'bad', 'neutral'], true) ? $type : 'neutral';
    if (function_exists('opsui_badge')) {
        return opsui_badge($text, $type);
    }
    return '<span class="badge badge-' . cmc_h($type) . '">' . cmc_h($text) . '</span>';
}

function cmc_quote_identifier(string $name): string
{
    if (!preg_match('/^[A-Za-z0-9_]+$/', $name)) {
        throw new RuntimeException('Unsafe identifier requested.');
    }
    return '`' . $name . '`';
}

function cmc_bootstrap_path(): string
{
    return dirname(__DIR__, 3) . '/gov.cabnet.app_app/src/bootstrap.php';
}

function cmc_db(?string &$error = null): ?mysqli
{
    static $db = null;
    static $loaded = false;
    static $loadError = null;

    if ($loaded) {
        $error = $loadError;
        return $db instanceof mysqli ? $db : null;
    }

    $loaded = true;
    $bootstrap = cmc_bootstrap_path();
    if (!is_file($bootstrap)) {
        $loadError = 'Private app bootstrap not found.';
        $error = $loadError;
        return null;
    }

    try {
        $ctx = require $bootstrap;
        if (!is_array($ctx) || !isset($ctx['db']) || !method_exists($ctx['db'], 'connection')) {
            throw new RuntimeException('Private app bootstrap did not expose a DB connection.');
        }
        $db = $ctx['db']->connection();
        if (!$db instanceof mysqli) {
            throw new RuntimeException('DB connection is not mysqli.');
        }
        $error = null;
        return $db;
    } catch (Throwable $e) {
        $loadError = $e->getMessage();
        $error = $loadError;
        return null;
    }
}

function cmc_table_exists(mysqli $db, string $table): bool
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
function cmc_columns(mysqli $db, string $table): array
{
    $cols = [];
    if (!cmc_table_exists($db, $table)) {
        return $cols;
    }
    try {
        $res = $db->query('SHOW COLUMNS FROM ' . cmc_quote_identifier($table));
        while ($res && ($row = $res->fetch_assoc())) {
            $field = (string)($row['Field'] ?? '');
            if ($field !== '') {
                $cols[$field] = true;
            }
        }
    } catch (Throwable) {
        return [];
    }
    return $cols;
}

/** @return array<int,array<string,mixed>> */
function cmc_rows(mysqli $db, string $table, int $limit = 10000): array
{
    if (!cmc_table_exists($db, $table)) {
        return [];
    }
    $limit = max(1, min(20000, $limit));
    try {
        $res = $db->query('SELECT * FROM ' . cmc_quote_identifier($table) . ' LIMIT ' . $limit);
        $rows = [];
        while ($res && ($row = $res->fetch_assoc())) {
            $rows[] = $row;
        }
        return $rows;
    } catch (Throwable) {
        return [];
    }
}

/** @param array<string,mixed> $row */
function cmc_pick(array $row, array $keys, string $default = ''): string
{
    foreach ($keys as $key) {
        if (array_key_exists($key, $row) && $row[$key] !== null && trim((string)$row[$key]) !== '') {
            return trim((string)$row[$key]);
        }
    }
    return $default;
}

/** @param array<string,mixed> $row */
function cmc_active(array $row): bool
{
    if (!array_key_exists('is_active', $row)) {
        return true;
    }
    return trim((string)$row['is_active']) !== '0';
}

function cmc_has_id(mixed $value): bool
{
    $v = trim((string)$value);
    return $v !== '' && $v !== '0';
}

/** @return array<string,array{id:string,label:string,note:string}> */
function cmc_verified_starting_points(): array
{
    return [
        '1756' => [
            'id' => '612164',
            'label' => 'Ομβροδέκτης, Κοινότητα Μυκόνου, Mykonos 84600',
            'note' => 'Live EDXEIX verified for WHITEBLUE / lessor 1756 on 2026-05-12.',
        ],
    ];
}

/** @return array<string,string> */
function cmc_known_lessor_names(): array
{
    return [
        '1756' => 'WHITEBLUE PREMIUM E E',
        '2307' => 'QUALITATIVE TRANSFER MYKONOS ΙΚ Ε',
        '3814' => 'LUXLIMO Ι Κ Ε',
        '3474' => 'ΜΑΝΟΥΣΕΛΗΣ ΙΩΣΗΦ',
        '3894' => 'ΜΥΚΟΝΟΣ TOURIST AGENCY',
        '4635' => 'LUX MYKONOS Ο Ε',
        '2124' => 'NGK',
        '1487' => 'VIP ROAD',
    ];
}

function cmc_add_lessor(array &$lessors, string $id, string $name = '', string $source = ''): void
{
    $id = trim($id);
    if ($id === '' || $id === '0') {
        return;
    }
    if (!isset($lessors[$id])) {
        $lessors[$id] = [
            'id' => $id,
            'name' => '',
            'sources' => [],
            'driver_rows' => [],
            'vehicle_rows' => [],
            'starting_rows' => [],
        ];
    }
    if ($name !== '' && $lessors[$id]['name'] === '') {
        $lessors[$id]['name'] = $name;
    }
    if ($source !== '') {
        $lessors[$id]['sources'][$source] = true;
    }
}

function cmc_row_link(string $href, string $label, string $class = 'btn'): string
{
    return '<a class="' . cmc_h($class) . '" href="' . cmc_h($href) . '">' . cmc_h($label) . '</a>';
}

function cmc_shell_begin(): void
{
    if (function_exists('opsui_shell_begin')) {
        opsui_shell_begin([
            'title' => 'Company Mapping Control',
            'page_title' => 'Company Mapping Control',
            'active_section' => 'Mapping Governance',
            'breadcrumbs' => 'Αρχική / Mapping / Company Mapping Control',
            'safe_notice' => 'Read-only mapping governance. This page does not call Bolt, EDXEIX, or AADE, and does not write data.',
            'force_safe_notice' => true,
        ]);
        return;
    }

    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Company Mapping Control | gov.cabnet.app</title><style>body{font-family:Arial,Helvetica,sans-serif;background:#eef1f6;color:#0b1d3f;margin:0;padding:18px}.card{background:#fff;border:1px solid #d8dde7;border-radius:8px;padding:16px;margin:0 0 16px}.badge{display:inline-block;padding:5px 9px;border-radius:12px;background:#e9edf7;margin:2px;font-weight:700;font-size:12px}.badge-good{background:#dcfce7;color:#166534}.badge-warn{background:#fff7ed;color:#b45309}.badge-bad{background:#fee2e2;color:#991b1b}.badge-neutral{background:#eaf1ff;color:#1e40af}.table-wrap{overflow:auto}table{width:100%;border-collapse:collapse}th,td{border-bottom:1px solid #e5e7eb;padding:9px;text-align:left;vertical-align:top}.btn{display:inline-block;background:#4f5ea7;color:#fff;text-decoration:none;padding:8px 10px;border-radius:5px;font-weight:700}.small{font-size:13px;color:#667085}code{background:#f1f5f9;padding:2px 5px;border-radius:4px}</style></head><body>';
}

function cmc_shell_end(): void
{
    if (function_exists('opsui_shell_end')) {
        opsui_shell_end();
        return;
    }
    echo '</body></html>';
}

$dbError = null;
$db = cmc_db($dbError);
$filterLessor = isset($_GET['lessor']) ? preg_replace('/[^0-9]/', '', (string)$_GET['lessor']) : '';

$state = [
    'ok' => false,
    'error' => $dbError,
    'tables' => [],
    'lessors' => [],
    'global_starting_points' => [],
    'export_starting_points' => [],
    'summary' => [
        'lessors_total' => 0,
        'good' => 0,
        'warn' => 0,
        'bad' => 0,
        'missing_override' => 0,
        'known_mismatch' => 0,
    ],
];

if ($db instanceof mysqli) {
    try {
        $tables = [
            'edxeix_export_lessors',
            'edxeix_export_drivers',
            'edxeix_export_vehicles',
            'edxeix_export_starting_points',
            'mapping_drivers',
            'mapping_vehicles',
            'mapping_starting_points',
            'mapping_lessor_starting_points',
        ];
        foreach ($tables as $table) {
            $state['tables'][$table] = cmc_table_exists($db, $table);
        }

        $lessors = [];
        $knownNames = cmc_known_lessor_names();
        $verifiedStarts = cmc_verified_starting_points();

        $exportLessorRows = cmc_rows($db, 'edxeix_export_lessors');
        foreach ($exportLessorRows as $row) {
            $id = cmc_pick($row, ['id', 'edxeix_lessor_id', 'lessor_id']);
            $name = cmc_pick($row, ['name', 'label', 'company_name', 'title', 'lessor_name']);
            cmc_add_lessor($lessors, $id, $name, 'edxeix_export_lessors');
        }

        $exportStartingRows = cmc_rows($db, 'edxeix_export_starting_points');
        foreach ($exportStartingRows as $row) {
            $id = cmc_pick($row, ['id', 'edxeix_starting_point_id', 'starting_point_id', 'value']);
            if ($id === '') {
                continue;
            }
            $state['export_starting_points'][$id] = [
                'id' => $id,
                'label' => cmc_pick($row, ['label', 'name', 'address', 'text', 'title'], ''),
                'row' => $row,
            ];
        }

        $driverRows = cmc_rows($db, 'mapping_drivers');
        foreach ($driverRows as $row) {
            $lessorId = cmc_pick($row, ['edxeix_lessor_id']);
            cmc_add_lessor($lessors, $lessorId, $knownNames[$lessorId] ?? '', 'mapping_drivers');
            if ($lessorId !== '' && isset($lessors[$lessorId])) {
                $lessors[$lessorId]['driver_rows'][] = $row;
            }
        }

        $vehicleRows = cmc_rows($db, 'mapping_vehicles');
        foreach ($vehicleRows as $row) {
            $lessorId = cmc_pick($row, ['edxeix_lessor_id']);
            cmc_add_lessor($lessors, $lessorId, $knownNames[$lessorId] ?? '', 'mapping_vehicles');
            if ($lessorId !== '' && isset($lessors[$lessorId])) {
                $lessors[$lessorId]['vehicle_rows'][] = $row;
            }
        }

        $startingRows = cmc_rows($db, 'mapping_lessor_starting_points');
        foreach ($startingRows as $row) {
            $lessorId = cmc_pick($row, ['edxeix_lessor_id']);
            cmc_add_lessor($lessors, $lessorId, $knownNames[$lessorId] ?? '', 'mapping_lessor_starting_points');
            if ($lessorId !== '' && isset($lessors[$lessorId])) {
                $lessors[$lessorId]['starting_rows'][] = $row;
            }
        }

        foreach (cmc_rows($db, 'mapping_starting_points') as $row) {
            if (cmc_active($row)) {
                $state['global_starting_points'][] = $row;
            }
        }

        foreach ($knownNames as $id => $name) {
            if (isset($lessors[$id])) {
                if ($lessors[$id]['name'] === '') {
                    $lessors[$id]['name'] = $name;
                }
            }
        }

        foreach ($lessors as $id => &$lessor) {
            if ($lessor['name'] === '' && isset($knownNames[$id])) {
                $lessor['name'] = $knownNames[$id];
            }

            $activeDrivers = array_values(array_filter($lessor['driver_rows'], 'cmc_active'));
            $activeVehicles = array_values(array_filter($lessor['vehicle_rows'], 'cmc_active'));
            $activeStarts = array_values(array_filter($lessor['starting_rows'], function (array $row): bool {
                return cmc_active($row) && cmc_has_id($row['edxeix_starting_point_id'] ?? '');
            }));

            $mappedDrivers = array_values(array_filter($activeDrivers, function (array $row): bool {
                return cmc_has_id($row['edxeix_driver_id'] ?? '');
            }));
            $mappedVehicles = array_values(array_filter($activeVehicles, function (array $row): bool {
                return cmc_has_id($row['edxeix_vehicle_id'] ?? '');
            }));

            $currentStartId = '';
            $currentStartLabel = '';
            if (isset($activeStarts[0])) {
                $currentStartId = cmc_pick($activeStarts[0], ['edxeix_starting_point_id']);
                $currentStartLabel = cmc_pick($activeStarts[0], ['label', 'internal_key']);
            }

            $issues = [];
            $severity = 'good';
            $hasOperationalRows = count($activeDrivers) > 0 || count($activeVehicles) > 0;

            if ($hasOperationalRows && $currentStartId === '') {
                $issues[] = 'missing_lessor_specific_starting_point_override';
                $severity = 'warn';
                $state['summary']['missing_override']++;
            }

            if (count($activeDrivers) !== count($mappedDrivers)) {
                $issues[] = 'one_or_more_active_drivers_unmapped';
                $severity = $severity === 'bad' ? 'bad' : 'warn';
            }
            if (count($activeVehicles) !== count($mappedVehicles)) {
                $issues[] = 'one_or_more_active_vehicles_unmapped';
                $severity = $severity === 'bad' ? 'bad' : 'warn';
            }

            if ($currentStartId !== '' && count($state['export_starting_points']) > 0 && !isset($state['export_starting_points'][$currentStartId])) {
                $issues[] = 'starting_point_not_found_in_export_snapshot';
                $severity = $severity === 'bad' ? 'bad' : 'warn';
            }

            if (isset($verifiedStarts[$id])) {
                $expected = $verifiedStarts[$id]['id'];
                if ($currentStartId !== $expected) {
                    $issues[] = 'verified_starting_point_mismatch_expected_' . $expected;
                    $severity = 'bad';
                    $state['summary']['known_mismatch']++;
                }
            }

            $lessor['stats'] = [
                'active_drivers' => count($activeDrivers),
                'mapped_drivers' => count($mappedDrivers),
                'active_vehicles' => count($activeVehicles),
                'mapped_vehicles' => count($mappedVehicles),
                'active_starting_overrides' => count($activeStarts),
                'current_starting_point_id' => $currentStartId,
                'current_starting_point_label' => $currentStartLabel,
                'issues' => $issues,
                'severity' => $severity,
            ];

            $state['summary'][$severity]++;
        }
        unset($lessor);

        ksort($lessors, SORT_NATURAL);
        $state['lessors'] = $lessors;
        $state['summary']['lessors_total'] = count($lessors);
        $state['ok'] = true;
        $state['error'] = null;
    } catch (Throwable $e) {
        $state['error'] = $e->getMessage();
    }
}

$selected = ($filterLessor !== '' && isset($state['lessors'][$filterLessor])) ? $state['lessors'][$filterLessor] : null;

cmc_shell_begin();
?>
<style>
.company-map-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin:12px 0}.company-map-card{background:#fff;border:1px solid #d8dde7;border-radius:5px;padding:16px 18px;box-shadow:0 5px 16px rgba(26,33,52,.05);margin:0 0 16px}.company-map-card h2,.company-map-card h3{margin-top:0}.company-map-table th,.company-map-table td{vertical-align:top}.company-map-table .nowrap{white-space:nowrap}.company-map-status{font-weight:700}.company-map-status.good{color:#166534}.company-map-status.warn{color:#b45309}.company-map-status.bad{color:#991b1b}.company-map-issue{display:block;margin:4px 0;color:#991b1b;font-size:13px}.company-map-issue.warn{color:#b45309}.company-map-actions{display:flex;gap:8px;flex-wrap:wrap}.company-map-code{white-space:pre-wrap;background:#0f172a;color:#dbeafe;padding:14px;border-radius:6px;overflow:auto;font-size:13px}.company-map-kv{display:grid;grid-template-columns:220px minmax(0,1fr);gap:8px;border-bottom:1px solid #eef1f5;padding:8px 0}.company-map-kv:last-child{border-bottom:0}.company-map-kv .k{font-weight:700;color:#667085}.company-map-muted{color:#667085}.company-map-danger{border-left:6px solid #dc2626}.company-map-warn{border-left:6px solid #d97706}.company-map-good{border-left:6px solid #16a34a}.company-map-filter{display:flex;gap:10px;flex-wrap:wrap;align-items:end}.company-map-filter input{max-width:280px}@media(max-width:1100px){.company-map-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:760px){.company-map-grid{grid-template-columns:1fr}.company-map-kv{grid-template-columns:1fr}.company-map-filter input{max-width:100%}}</style>

<section class="card hero">
    <h1>Company / Lessor Mapping Control</h1>
    <p>Read-only governance for EDXEIX company mappings. This page is designed to catch the exact class of issue discovered today: a lessor using a wrong global starting point fallback instead of a verified lessor-specific starting point.</p>
    <div>
        <?= cmc_badge('READ ONLY', 'good') ?>
        <?= cmc_badge('NO EDXEIX CALL', 'good') ?>
        <?= cmc_badge('NO DB WRITE', 'good') ?>
        <?= cmc_badge('STARTING POINT OVERSIGHT', 'warn') ?>
    </div>
</section>

<?php if (!$state['ok']): ?>
    <section class="company-map-card company-map-danger">
        <h2>Unable to load mapping status</h2>
        <p class="badline"><strong><?= cmc_h($state['error'] ?? 'Unknown error') ?></strong></p>
    </section>
<?php else: ?>
    <section class="company-map-grid">
        <div class="metric"><strong><?= cmc_h((string)$state['summary']['lessors_total']) ?></strong><span>Lessors detected</span></div>
        <div class="metric"><strong><?= cmc_h((string)$state['summary']['good']) ?></strong><span>Green</span></div>
        <div class="metric"><strong><?= cmc_h((string)$state['summary']['warn']) ?></strong><span>Warnings</span></div>
        <div class="metric"><strong><?= cmc_h((string)$state['summary']['bad']) ?></strong><span>Critical</span></div>
    </section>

    <section class="company-map-card <?= $state['summary']['bad'] > 0 ? 'company-map-danger' : ($state['summary']['warn'] > 0 ? 'company-map-warn' : 'company-map-good') ?>">
        <h2>Mapping health rule</h2>
        <p>Every operational lessor should have an explicit lessor-specific starting point in <code>mapping_lessor_starting_points</code>. Global <code>mapping_starting_points</code> rows should be treated as fallback only.</p>
        <div class="company-map-actions">
            <?= cmc_row_link('/ops/mapping-control.php', 'Driver/Vehicle Mapping Review', 'btn dark') ?>
            <?= cmc_row_link('/ops/mappings.php', 'Original Mapping Editor', 'btn warn') ?>
            <?= cmc_row_link('/ops/pre-ride-email-tool.php', 'Production Pre-Ride Tool', 'btn') ?>
        </div>
    </section>

    <?php if (isset(cmc_verified_starting_points()['1756'])): $verified = cmc_verified_starting_points()['1756']; $whiteblue = $state['lessors']['1756'] ?? null; ?>
        <section class="company-map-card <?= $whiteblue && (($whiteblue['stats']['current_starting_point_id'] ?? '') === $verified['id']) ? 'company-map-good' : 'company-map-danger' ?>">
            <h2>Verified live EDXEIX expectation</h2>
            <div class="company-map-kv"><div class="k">Lessor</div><div>WHITEBLUE / 1756</div></div>
            <div class="company-map-kv"><div class="k">Expected starting point</div><div><strong><?= cmc_h($verified['id']) ?></strong> — <?= cmc_h($verified['label']) ?></div></div>
            <div class="company-map-kv"><div class="k">Current local override</div><div><strong><?= cmc_h((string)($whiteblue['stats']['current_starting_point_id'] ?? 'missing')) ?></strong> <?= cmc_h((string)($whiteblue['stats']['current_starting_point_label'] ?? '')) ?></div></div>
            <div class="company-map-kv"><div class="k">Note</div><div><?= cmc_h($verified['note']) ?></div></div>
        </section>
    <?php endif; ?>

    <section class="company-map-card">
        <h2>Lessor overview</h2>
        <p class="small">Red means critical mapping risk. Yellow means review needed. Green means active driver/vehicle mappings have an explicit lessor-specific starting point override.</p>
        <div class="table-wrap">
            <table class="company-map-table">
                <thead>
                    <tr>
                        <th>Lessor</th>
                        <th>Drivers</th>
                        <th>Vehicles</th>
                        <th>Starting point override</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($state['lessors'] as $lessor):
                    $stats = $lessor['stats'];
                    if ($filterLessor !== '' && $lessor['id'] !== $filterLessor) { continue; }
                    $severity = (string)$stats['severity'];
                    $startId = (string)$stats['current_starting_point_id'];
                    $startLabel = (string)$stats['current_starting_point_label'];
                ?>
                    <tr>
                        <td>
                            <strong><?= cmc_h((string)$lessor['name'] !== '' ? (string)$lessor['name'] : 'Lessor ' . (string)$lessor['id']) ?></strong><br>
                            <code><?= cmc_h((string)$lessor['id']) ?></code>
                            <div class="small">Sources: <?= cmc_h(implode(', ', array_keys((array)$lessor['sources']))) ?></div>
                        </td>
                        <td class="nowrap"><?= cmc_h((string)$stats['mapped_drivers']) ?>/<?= cmc_h((string)$stats['active_drivers']) ?> mapped</td>
                        <td class="nowrap"><?= cmc_h((string)$stats['mapped_vehicles']) ?>/<?= cmc_h((string)$stats['active_vehicles']) ?> mapped</td>
                        <td>
                            <?php if ($startId !== ''): ?>
                                <?= cmc_badge($startId, $severity === 'bad' ? 'bad' : 'good') ?><br>
                                <span class="small"><?= cmc_h($startLabel) ?></span>
                            <?php else: ?>
                                <?= cmc_badge('missing override', 'warn') ?>
                                <div class="small">Global fallback would be used.</div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?= cmc_badge(strtoupper($severity), $severity) ?>
                            <?php foreach ((array)$stats['issues'] as $issue): ?>
                                <span class="company-map-issue <?= $severity === 'bad' ? '' : 'warn' ?>"><?= cmc_h((string)$issue) ?></span>
                            <?php endforeach; ?>
                            <?php if (empty($stats['issues'])): ?><span class="small">No mapping blockers detected by this page.</span><?php endif; ?>
                        </td>
                        <td class="nowrap">
                            <?= cmc_row_link('/ops/company-mapping-control.php?lessor=' . rawurlencode((string)$lessor['id']), 'Details', 'btn') ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <?php if ($selected): $stats = $selected['stats']; ?>
        <section class="company-map-card">
            <h2>Details: <?= cmc_h((string)$selected['name'] !== '' ? (string)$selected['name'] : 'Lessor ' . (string)$selected['id']) ?> / <?= cmc_h((string)$selected['id']) ?></h2>
            <div class="company-map-kv"><div class="k">Current starting point override</div><div><?= cmc_h((string)($stats['current_starting_point_id'] ?: 'missing')) ?> <?= cmc_h((string)$stats['current_starting_point_label']) ?></div></div>
            <div class="company-map-kv"><div class="k">Active drivers</div><div><?= cmc_h((string)$stats['mapped_drivers']) ?>/<?= cmc_h((string)$stats['active_drivers']) ?> mapped</div></div>
            <div class="company-map-kv"><div class="k">Active vehicles</div><div><?= cmc_h((string)$stats['mapped_vehicles']) ?>/<?= cmc_h((string)$stats['active_vehicles']) ?> mapped</div></div>
            <div class="company-map-kv"><div class="k">Issues</div><div><?= empty($stats['issues']) ? cmc_badge('none detected', 'good') : cmc_h(implode(', ', (array)$stats['issues'])) ?></div></div>
        </section>

        <section class="company-map-card">
            <h3>Lessor-specific starting point rows</h3>
            <div class="table-wrap"><table class="company-map-table"><thead><tr><th>ID</th><th>Internal key</th><th>Label</th><th>Starting point ID</th><th>Active</th><th>Updated</th></tr></thead><tbody>
                <?php foreach ((array)$selected['starting_rows'] as $row): ?>
                    <tr><td><?= cmc_h(cmc_pick($row, ['id'])) ?></td><td><?= cmc_h(cmc_pick($row, ['internal_key'])) ?></td><td><?= cmc_h(cmc_pick($row, ['label'])) ?></td><td><?= cmc_h(cmc_pick($row, ['edxeix_starting_point_id'])) ?></td><td><?= cmc_active($row) ? cmc_badge('active', 'good') : cmc_badge('inactive', 'neutral') ?></td><td><?= cmc_h(cmc_pick($row, ['updated_at', 'created_at'])) ?></td></tr>
                <?php endforeach; ?>
                <?php if (empty($selected['starting_rows'])): ?><tr><td colspan="6">No lessor-specific starting point rows found.</td></tr><?php endif; ?>
            </tbody></table></div>
        </section>

        <section class="company-map-card">
            <h3>Drivers for this lessor</h3>
            <div class="table-wrap"><table class="company-map-table"><thead><tr><th>Name</th><th>EDXEIX driver ID</th><th>Active</th><th>Row ID</th></tr></thead><tbody>
                <?php foreach ((array)$selected['driver_rows'] as $row): ?>
                    <tr><td><?= cmc_h(cmc_pick($row, ['external_driver_name', 'driver_name', 'name'])) ?></td><td><?= cmc_h(cmc_pick($row, ['edxeix_driver_id', 'driver_id'])) ?></td><td><?= cmc_active($row) ? cmc_badge('active', 'good') : cmc_badge('inactive', 'neutral') ?></td><td><?= cmc_h(cmc_pick($row, ['id'])) ?></td></tr>
                <?php endforeach; ?>
                <?php if (empty($selected['driver_rows'])): ?><tr><td colspan="4">No driver rows found for this lessor.</td></tr><?php endif; ?>
            </tbody></table></div>
        </section>

        <section class="company-map-card">
            <h3>Vehicles for this lessor</h3>
            <div class="table-wrap"><table class="company-map-table"><thead><tr><th>Plate</th><th>EDXEIX vehicle ID</th><th>Active</th><th>Row ID</th></tr></thead><tbody>
                <?php foreach ((array)$selected['vehicle_rows'] as $row): ?>
                    <tr><td><strong><?= cmc_h(cmc_pick($row, ['plate', 'vehicle_plate'])) ?></strong></td><td><?= cmc_h(cmc_pick($row, ['edxeix_vehicle_id', 'vehicle_id'])) ?></td><td><?= cmc_active($row) ? cmc_badge('active', 'good') : cmc_badge('inactive', 'neutral') ?></td><td><?= cmc_h(cmc_pick($row, ['id'])) ?></td></tr>
                <?php endforeach; ?>
                <?php if (empty($selected['vehicle_rows'])): ?><tr><td colspan="4">No vehicle rows found for this lessor.</td></tr><?php endif; ?>
            </tbody></table></div>
        </section>
    <?php endif; ?>

    <section class="company-map-card">
        <h2>Global starting point fallback rows</h2>
        <p class="small">These are fallback rows only. Operational lessors should have explicit rows in <code>mapping_lessor_starting_points</code>.</p>
        <div class="table-wrap"><table class="company-map-table"><thead><tr><th>ID</th><th>Key</th><th>Label</th><th>EDXEIX starting point ID</th><th>Active</th></tr></thead><tbody>
            <?php foreach ($state['global_starting_points'] as $row): ?>
                <tr><td><?= cmc_h(cmc_pick($row, ['id'])) ?></td><td><?= cmc_h(cmc_pick($row, ['internal_key'])) ?></td><td><?= cmc_h(cmc_pick($row, ['label'])) ?></td><td><?= cmc_h(cmc_pick($row, ['edxeix_starting_point_id'])) ?></td><td><?= cmc_active($row) ? cmc_badge('active', 'neutral') : cmc_badge('inactive', 'neutral') ?></td></tr>
            <?php endforeach; ?>
            <?php if (empty($state['global_starting_points'])): ?><tr><td colspan="5">No active global starting point rows found.</td></tr><?php endif; ?>
        </tbody></table></div>
    </section>

    <section class="company-map-card">
        <h2>Database tables checked</h2>
        <div class="table-wrap"><table class="company-map-table"><thead><tr><th>Table</th><th>Status</th></tr></thead><tbody>
            <?php foreach ($state['tables'] as $table => $exists): ?>
                <tr><td><code><?= cmc_h((string)$table) ?></code></td><td><?= $exists ? cmc_badge('exists', 'good') : cmc_badge('missing', 'warn') ?></td></tr>
            <?php endforeach; ?>
        </tbody></table></div>
    </section>

    <section class="company-map-card">
        <h2>Safe verification SQL</h2>
        <p class="small">Copy/paste for read-only checks when investigating one lessor.</p>
        <pre class="company-map-code">SELECT *
FROM mapping_lessor_starting_points
WHERE edxeix_lessor_id = 1756;

SELECT id, external_driver_name, edxeix_driver_id, edxeix_lessor_id, is_active
FROM mapping_drivers
WHERE edxeix_lessor_id = 1756
ORDER BY external_driver_name;

SELECT id, plate, edxeix_vehicle_id, edxeix_lessor_id, is_active
FROM mapping_vehicles
WHERE edxeix_lessor_id = 1756
ORDER BY plate;</pre>
    </section>
<?php endif; ?>
<?php
cmc_shell_end();
