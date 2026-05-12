<?php
/**
 * gov.cabnet.app — Mapping Center v2.0
 * Main mapping governance hub. Read-only.
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
if (is_file($shellFile)) { require_once $shellFile; }
$mappingNavFile = __DIR__ . '/_mapping_nav.php';
if (is_file($mappingNavFile)) { require_once $mappingNavFile; }

function mc2_h(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function mc2_badge(string $text, string $type = 'neutral'): string
{
    if (function_exists('opsui_badge')) { return opsui_badge($text, $type); }
    return '<span class="badge badge-' . mc2_h($type) . '">' . mc2_h($text) . '</span>';
}
function mc2_file_status(string $path): array
{
    $full = __DIR__ . '/' . basename($path);
    return [
        'exists' => is_file($full),
        'mtime' => is_file($full) ? date('Y-m-d H:i:s', (int)filemtime($full)) : '',
        'size' => is_file($full) ? number_format((int)filesize($full)) . ' bytes' : '',
        'sha' => is_file($full) ? substr(hash_file('sha256', $full) ?: '', 0, 12) : '',
    ];
}

$tools = [
    ['Core', '/ops/mapping-center.php', 'Mapping Center', 'Main mapping menu and route hub.', 'center'],
    ['Core', '/ops/mapping-health.php', 'Mapping Health', 'Read-only failure-point dashboard.', 'health'],
    ['Company', '/ops/company-mapping-control.php', 'Company Mapping Control', 'Lessor/company overview and starting-point warning table.', 'companies'],
    ['Company', '/ops/company-mapping-detail.php?lessor=1756', 'Company Mapping Detail', 'One-company view: drivers, vehicles, starting point, export comparison.', 'whiteblue'],
    ['Starting Point', '/ops/starting-point-control.php', 'Starting Point Control', 'Admin tool for lessor-specific starting point overrides.', 'starting'],
    ['Verification', '/ops/mapping-verification.php', 'Mapping Verification Register', 'Record verified mapping decisions and notes.', 'verification'],
    ['Legacy', '/ops/mapping-control.php', 'Existing Mapping Review', 'Existing read-only driver/vehicle mapping overview.', 'review'],
    ['Legacy', '/ops/mappings.php', 'Original Mapping Editor', 'Existing guarded mapping editor.', 'editor'],
    ['Legacy', '/ops/mappings.php?format=json', 'Mapping JSON', 'Existing JSON mapping output.', 'json'],
    ['Readiness', '/ops/readiness-control.php', 'Readiness Control', 'Existing readiness overview.', 'readiness'],
];

if (function_exists('opsui_shell_begin')) {
    opsui_shell_begin([
        'title' => 'Mapping Center',
        'page_title' => 'Mapping Center',
        'active_section' => 'Mapping Governance',
        'breadcrumbs' => 'Αρχική / Mapping / Center',
        'safe_notice' => 'Read-only mapping hub. This page links the mapping subsystem and does not modify mappings or workflow data.',
        'force_safe_notice' => true,
    ]);
} else {
    echo '<!doctype html><html><head><meta charset="utf-8"><title>Mapping Center</title></head><body>';
}
?>
<style>
.map-card-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}.map-tool-card{background:#fff;border:1px solid #d8dde7;border-radius:6px;padding:16px;min-height:150px;box-shadow:0 6px 18px rgba(26,33,52,.05)}.map-tool-card h3{margin:0 0 8px}.map-tool-card p{margin:0 0 12px;color:#52617f}.map-tool-meta{font-size:12px;color:#667085;margin:8px 0}.map-route-table td,.map-route-table th{vertical-align:top}.map-mini-actions{display:flex;gap:8px;flex-wrap:wrap}.map-mini-actions a{display:inline-block;background:#4f5ea7;color:#fff;border-radius:5px;padding:8px 10px;text-decoration:none;font-weight:700;font-size:13px}.map-mini-actions a.secondary{background:#6b7280}.map-risk{border-left:5px solid #d4922d}.map-ok{border-left:5px solid #2f9e44}@media(max-width:1000px){.map-card-grid{grid-template-columns:1fr}}
</style>
<?php if (function_exists('gov_mapping_nav')) { gov_mapping_nav('center'); } ?>

<section class="card hero">
    <h1>Mapping Center</h1>
    <p>This is the main hub for mapping governance. The goal is to prevent failures where the company, driver, and vehicle are correct but another mapped field, such as the starting point, falls back to an unsafe default.</p>
    <div>
        <?= mc2_badge('READ ONLY', 'good') ?>
        <?= mc2_badge('NO EDXEIX CALL', 'good') ?>
        <?= mc2_badge('NO DB WRITE', 'good') ?>
        <?= mc2_badge('MAPPING GOVERNANCE', 'neutral') ?>
    </div>
</section>

<section class="map-card-grid">
    <article class="map-tool-card map-risk">
        <h3>1. Mapping Health</h3>
        <p>Start here after every mapping change. It highlights missing lessor-specific starting points, unmapped drivers, unmapped vehicles, and known mismatch risks.</p>
        <div class="map-mini-actions"><a href="/ops/mapping-health.php">Open Health</a></div>
    </article>
    <article class="map-tool-card map-risk">
        <h3>2. Company Mapping Control</h3>
        <p>Review all companies/lessors and confirm each operational lessor has a valid starting-point override.</p>
        <div class="map-mini-actions"><a href="/ops/company-mapping-control.php">Open Companies</a></div>
    </article>
    <article class="map-tool-card map-ok">
        <h3>3. Verification Register</h3>
        <p>Record what has been visually confirmed in live EDXEIX, who confirmed it, and when. This gives us an operational audit trail.</p>
        <div class="map-mini-actions"><a href="/ops/mapping-verification.php">Open Register</a></div>
    </article>
</section>

<section class="card">
    <h2>All mapping routes</h2>
    <div class="table-wrap"><table class="map-route-table">
        <thead><tr><th>Group</th><th>Tool</th><th>Purpose</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($tools as $tool): $status = mc2_file_status($tool[1]); ?>
            <tr>
                <td><?= mc2_h($tool[0]) ?></td>
                <td><strong><?= mc2_h($tool[2]) ?></strong><br><code><?= mc2_h($tool[1]) ?></code></td>
                <td><?= mc2_h($tool[3]) ?></td>
                <td>
                    <?= mc2_badge($status['exists'] ? 'exists' : 'route maybe dynamic/missing', $status['exists'] ? 'good' : 'warn') ?>
                    <?php if ($status['exists']): ?><div class="small">mtime <?= mc2_h($status['mtime']) ?> · sha <?= mc2_h($status['sha']) ?></div><?php endif; ?>
                </td>
                <td><a class="btn" href="<?= mc2_h($tool[1]) ?>">Open</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
</section>

<section class="card">
    <h2>Mapping safety policy</h2>
    <ul class="list">
        <li>Every operational lessor should have an explicit row in <code>mapping_lessor_starting_points</code>.</li>
        <li>Global rows in <code>mapping_starting_points</code> are fallback only; they should not silently control production lessor behavior.</li>
        <li>Driver and vehicle mappings must resolve to the same EDXEIX lessor unless manually investigated.</li>
        <li>Live EDXEIX visual confirmation is the strongest proof for driver/vehicle/starting point IDs.</li>
        <li>Production pre-ride workflow remains operator-confirmed; mapping tools do not enable automatic EDXEIX submission.</li>
    </ul>
</section>
<?php
if (function_exists('opsui_shell_end')) { opsui_shell_end(); } else { echo '</body></html>'; }
