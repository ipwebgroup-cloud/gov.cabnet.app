<?php
/**
 * gov.cabnet.app — Mapping Center v1.0
 *
 * Read-only route hub for Bolt → EDXEIX mappings.
 * Safety: no external calls, no writes, no production submit behavior.
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

function mc_h(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function mc_badge(string $text, string $type = 'neutral'): string
{
    if (function_exists('opsui_badge')) {
        return opsui_badge($text, $type);
    }
    $type = in_array($type, ['good', 'warn', 'bad', 'neutral'], true) ? $type : 'neutral';
    return '<span class="badge badge-' . mc_h($type) . '">' . mc_h($text) . '</span>';
}

function mc_root(): string
{
    return dirname(__DIR__, 3);
}

function mc_context(?string &$error = null): ?array
{
    static $ctx = null;
    static $loaded = false;
    static $loadError = null;

    if ($loaded) {
        $error = $loadError;
        return is_array($ctx) ? $ctx : null;
    }

    $loaded = true;
    $bootstrap = mc_root() . '/gov.cabnet.app_app/src/bootstrap.php';
    if (!is_file($bootstrap)) {
        $loadError = 'Private app bootstrap was not found.';
        $error = $loadError;
        return null;
    }

    try {
        $ctx = require $bootstrap;
        if (!is_array($ctx) || !isset($ctx['db']) || !method_exists($ctx['db'], 'connection')) {
            throw new RuntimeException('Private app bootstrap did not return a usable DB context.');
        }
        $error = null;
        return $ctx;
    } catch (Throwable $e) {
        $loadError = $e->getMessage();
        $error = $loadError;
        return null;
    }
}

function mc_db(?string &$error = null): ?mysqli
{
    $ctx = mc_context($error);
    if (!$ctx) {
        return null;
    }
    try {
        $db = $ctx['db']->connection();
        return $db instanceof mysqli ? $db : null;
    } catch (Throwable $e) {
        $error = $e->getMessage();
        return null;
    }
}

function mc_table_exists(mysqli $db, string $table): bool
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

function mc_one(mysqli $db, string $sql): int
{
    try {
        $res = $db->query($sql);
        if (!$res) { return 0; }
        $row = $res->fetch_assoc();
        return (int)($row['c'] ?? 0);
    } catch (Throwable) {
        return 0;
    }
}

function mc_file_status(string $path): array
{
    $full = mc_root() . '/public_html/gov.cabnet.app' . $path;
    return [
        'path' => $path,
        'exists' => is_file($full),
        'mtime' => is_file($full) ? date('Y-m-d H:i:s', (int)filemtime($full)) : '',
        'size' => is_file($full) ? (string)filesize($full) : '',
    ];
}

function mc_link_card(string $title, string $href, string $description, string $type = 'neutral'): string
{
    $status = mc_file_status(parse_url($href, PHP_URL_PATH) ?: $href);
    $badge = $status['exists'] ? mc_badge('available', 'good') : mc_badge('missing file', 'warn');
    return '<a class="mc-tool-card" href="' . mc_h($href) . '">'
        . '<strong>' . mc_h($title) . '</strong>'
        . '<span>' . mc_h($description) . '</span>'
        . '<em>' . $badge . ' ' . mc_badge($type, $type === 'admin' ? 'warn' : 'neutral') . '</em>'
        . '</a>';
}

function mc_shell_begin(): void
{
    if (function_exists('opsui_shell_begin')) {
        opsui_shell_begin([
            'title' => 'Mapping Center',
            'page_title' => 'Mapping Center',
            'active_section' => 'Mappings',
            'breadcrumbs' => 'Αρχική / Mappings / Mapping Center',
            'safe_notice' => 'Read-only mapping hub. This page does not call Bolt, EDXEIX, or AADE and does not write data.',
            'force_safe_notice' => true,
        ]);
        return;
    }
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Mapping Center</title></head><body>';
}

function mc_shell_end(): void
{
    if (function_exists('opsui_shell_end')) {
        opsui_shell_end();
        return;
    }
    echo '</body></html>';
}

$dbError = null;
$db = mc_db($dbError);
$tables = ['mapping_drivers','mapping_vehicles','mapping_starting_points','mapping_lessor_starting_points','edxeix_export_lessors','edxeix_export_starting_points'];
$tableStatus = [];
foreach ($tables as $table) {
    $tableStatus[$table] = $db ? mc_table_exists($db, $table) : false;
}

$driverTotal = $driverMapped = $vehicleTotal = $vehicleMapped = $overrideActive = $lessorExportCount = $atRiskLessors = 0;
if ($db) {
    if ($tableStatus['mapping_drivers']) {
        $driverTotal = mc_one($db, "SELECT COUNT(*) AS c FROM mapping_drivers WHERE COALESCE(is_active,1) = 1");
        $driverMapped = mc_one($db, "SELECT COUNT(*) AS c FROM mapping_drivers WHERE COALESCE(is_active,1) = 1 AND edxeix_driver_id IS NOT NULL AND edxeix_driver_id <> 0");
    }
    if ($tableStatus['mapping_vehicles']) {
        $vehicleTotal = mc_one($db, "SELECT COUNT(*) AS c FROM mapping_vehicles WHERE COALESCE(is_active,1) = 1");
        $vehicleMapped = mc_one($db, "SELECT COUNT(*) AS c FROM mapping_vehicles WHERE COALESCE(is_active,1) = 1 AND edxeix_vehicle_id IS NOT NULL AND edxeix_vehicle_id <> 0");
    }
    if ($tableStatus['mapping_lessor_starting_points']) {
        $overrideActive = mc_one($db, "SELECT COUNT(*) AS c FROM mapping_lessor_starting_points WHERE COALESCE(is_active,1) = 1 AND edxeix_starting_point_id IS NOT NULL AND edxeix_starting_point_id <> ''");
    }
    if ($tableStatus['edxeix_export_lessors']) {
        $lessorExportCount = mc_one($db, "SELECT COUNT(*) AS c FROM edxeix_export_lessors");
    }
    if ($tableStatus['mapping_drivers'] && $tableStatus['mapping_vehicles'] && $tableStatus['mapping_lessor_starting_points']) {
        $atRiskLessors = mc_one($db, "
            SELECT COUNT(*) AS c FROM (
                SELECT DISTINCT CAST(edxeix_lessor_id AS CHAR) AS lessor_id
                FROM mapping_drivers
                WHERE COALESCE(is_active,1)=1 AND edxeix_lessor_id IS NOT NULL AND edxeix_lessor_id <> 0
                UNION
                SELECT DISTINCT CAST(edxeix_lessor_id AS CHAR) AS lessor_id
                FROM mapping_vehicles
                WHERE COALESCE(is_active,1)=1 AND edxeix_lessor_id IS NOT NULL AND edxeix_lessor_id <> 0
            ) x
            LEFT JOIN mapping_lessor_starting_points sp
              ON CAST(sp.edxeix_lessor_id AS CHAR)=x.lessor_id
             AND COALESCE(sp.is_active,1)=1
             AND sp.edxeix_starting_point_id IS NOT NULL
             AND sp.edxeix_starting_point_id <> ''
            WHERE sp.id IS NULL
        ");
    }
}

$tools = [
    ['Company Mapping Control', '/ops/company-mapping-control.php', 'Lessor/company overview and starting-point override status.', 'read-only'],
    ['Mapping Health', '/ops/mapping-health.php', 'Red/yellow/green health dashboard for all mapping failure points.', 'read-only'],
    ['Company Detail: WHITEBLUE', '/ops/company-mapping-detail.php?lessor=1756', 'Detailed WHITEBLUE / 1756 drill-down.', 'read-only'],
    ['Starting Point Control', '/ops/starting-point-control.php', 'Admin-controlled lessor-specific starting-point overrides.', 'admin'],
    ['Driver/Vehicle Mapping Review', '/ops/mapping-control.php', 'Existing read-only driver and vehicle mapping overview.', 'read-only'],
    ['Original Mapping Editor', '/ops/mappings.php', 'Existing guarded editor for driver/vehicle mapping records.', 'admin'],
    ['Mapping JSON', '/ops/mappings.php?format=json', 'Existing JSON view for mapping diagnostics.', 'read-only'],
    ['Readiness Control', '/ops/readiness-control.php', 'Existing readiness checks that depend on mappings.', 'read-only'],
];

mc_shell_begin();
?>
<style>
.mc-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}.mc-card{background:#fff;border:1px solid #d8dde7;border-radius:6px;padding:16px}.mc-card strong{display:block;font-size:28px;color:#132a5e}.mc-card span{color:#44516f}.mc-tools{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}.mc-tool-card{display:block;background:#fff;border:1px solid #d8dde7;border-radius:6px;padding:14px;text-decoration:none;color:#132a5e;min-height:118px}.mc-tool-card:hover{border-color:#4f5ea7;box-shadow:0 6px 18px rgba(26,33,52,.08)}.mc-tool-card strong{display:block;font-size:18px;margin-bottom:6px}.mc-tool-card span{display:block;color:#44516f;line-height:1.35;margin-bottom:10px}.mc-tool-card em{font-style:normal}.mc-dropdown{position:relative;display:inline-block}.mc-dropdown summary{list-style:none;cursor:pointer;background:#4f5ea7;color:#fff;border-radius:6px;padding:11px 14px;font-weight:700}.mc-dropdown summary::-webkit-details-marker{display:none}.mc-dropdown[open] .mc-dropdown-panel{display:block}.mc-dropdown-panel{display:none;position:absolute;z-index:20;top:44px;left:0;background:#fff;border:1px solid #d8dde7;border-radius:8px;min-width:320px;box-shadow:0 18px 44px rgba(26,33,52,.18);padding:8px}.mc-dropdown-panel a{display:block;padding:10px 12px;border-radius:6px;color:#27385f;text-decoration:none}.mc-dropdown-panel a:hover{background:#eef1f8}.mc-status-table{width:100%;border-collapse:collapse}.mc-status-table th,.mc-status-table td{border-bottom:1px solid #e5e7eb;padding:10px;text-align:left;vertical-align:top}.mc-status-table th{background:#f8fafc}.mc-warn{border-left:6px solid #d4922d}.mc-good{border-left:6px solid #16a34a}.mc-bad{border-left:6px solid #dc2626}@media(max-width:1100px){.mc-grid,.mc-tools{grid-template-columns:1fr 1fr}}@media(max-width:760px){.mc-grid,.mc-tools{grid-template-columns:1fr}.mc-dropdown-panel{position:static;box-shadow:none;margin-top:8px}.mc-card strong{font-size:24px}}
</style>

<section class="card hero">
    <h1>Mapping Center</h1>
    <p>Central oversight for all Bolt → EDXEIX mappings. Use this before production if a new company, driver, vehicle, or starting point appears.</p>
    <div>
        <?= mc_badge('READ ONLY', 'good') ?>
        <?= mc_badge('NO EDXEIX CALL', 'good') ?>
        <?= mc_badge('MAPPING GOVERNANCE', 'neutral') ?>
    </div>
    <div class="actions" style="margin-top:14px;">
        <details class="mc-dropdown">
            <summary>Mapping Tools ▾</summary>
            <div class="mc-dropdown-panel">
                <?php foreach ($tools as $tool): ?>
                    <a href="<?= mc_h($tool[1]) ?>"><?= mc_h($tool[0]) ?></a>
                <?php endforeach; ?>
            </div>
        </details>
        <a class="btn" href="/ops/company-mapping-control.php">Company Mapping Control</a>
        <a class="btn warn" href="/ops/mapping-health.php">Mapping Health</a>
        <a class="btn dark" href="/ops/mapping-control.php">Driver/Vehicle Review</a>
    </div>
</section>

<?php if ($dbError): ?>
<section class="card mc-bad"><h2>Database unavailable</h2><p class="badline"><?= mc_h($dbError) ?></p></section>
<?php endif; ?>

<section class="mc-grid">
    <div class="mc-card"><strong><?= mc_h($driverMapped . '/' . $driverTotal) ?></strong><span>Active drivers mapped</span></div>
    <div class="mc-card"><strong><?= mc_h($vehicleMapped . '/' . $vehicleTotal) ?></strong><span>Active vehicles mapped</span></div>
    <div class="mc-card"><strong><?= mc_h((string)$overrideActive) ?></strong><span>Active lessor starting-point overrides</span></div>
    <div class="mc-card"><strong><?= mc_h((string)$atRiskLessors) ?></strong><span>Lessors at fallback risk</span></div>
</section>

<section class="card <?= $atRiskLessors > 0 ? 'mc-warn' : 'mc-good' ?>">
    <h2>Mapping risk summary</h2>
    <?php if ($atRiskLessors > 0): ?>
        <p class="warnline"><strong><?= mc_h((string)$atRiskLessors) ?> lessor(s)</strong> have active driver/vehicle mappings but no lessor-specific starting-point override. These can fall back to a global starting point.</p>
    <?php else: ?>
        <p class="goodline"><strong>No lessor fallback risk detected</strong> from the current driver/vehicle mappings.</p>
    <?php endif; ?>
    <p>The WHITEBLUE issue proved that driver/vehicle correctness is not enough. Every operational lessor should have an explicit starting-point override.</p>
</section>

<section class="card">
    <h2>Mapping tool menu</h2>
    <div class="mc-tools">
        <?php foreach ($tools as $tool): ?>
            <?= mc_link_card($tool[0], $tool[1], $tool[2], $tool[3]) ?>
        <?php endforeach; ?>
    </div>
</section>

<section class="card">
    <h2>Required source tables</h2>
    <div class="table-wrap"><table class="mc-status-table">
        <thead><tr><th>Table</th><th>Status</th><th>Purpose</th></tr></thead>
        <tbody>
        <?php foreach ($tableStatus as $table => $exists): ?>
            <tr>
                <td><code><?= mc_h($table) ?></code></td>
                <td><?= $exists ? mc_badge('available','good') : mc_badge('missing','warn') ?></td>
                <td><?= mc_h(match ($table) {
                    'mapping_drivers' => 'Local Bolt driver → EDXEIX driver/lessor mapping.',
                    'mapping_vehicles' => 'Local Bolt vehicle → EDXEIX vehicle/lessor mapping.',
                    'mapping_starting_points' => 'Global fallback starting points only.',
                    'mapping_lessor_starting_points' => 'Preferred lessor-specific starting point overrides.',
                    'edxeix_export_lessors' => 'Read-only EDXEIX lessor source snapshot.',
                    'edxeix_export_starting_points' => 'Read-only EDXEIX starting point source snapshot.',
                    default => 'Mapping support table.',
                }) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
</section>

<section class="card mc-warn">
    <h2>Operational rule</h2>
    <p>For production, each active EDXEIX lessor used by mapped drivers or vehicles should have a row in <code>mapping_lessor_starting_points</code>. Global rows in <code>mapping_starting_points</code> are fallback only.</p>
    <ul class="list">
        <li>Company/lessor must come from driver/vehicle EDXEIX mapping.</li>
        <li>Starting point must prefer the lessor-specific override.</li>
        <li>If fallback is used, the operator must verify the field before any EDXEIX save.</li>
    </ul>
</section>
<?php
mc_shell_end();
