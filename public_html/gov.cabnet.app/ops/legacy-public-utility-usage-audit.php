<?php
/**
 * gov.cabnet.app — Legacy Public Utility Usage Audit.
 *
 * v3.0.92:
 * - Read-only log/stat usage audit for legacy guarded public-root utilities.
 * - Does not execute legacy utilities or perform external/API/DB/write actions.
 */

declare(strict_types=1);

require_once __DIR__ . '/_shell.php';
require_once '/home/cabnet/gov.cabnet.app_app/cli/legacy_public_utility_usage_audit.php';

$report = gov_legacy_public_utility_usage_audit_run();
$summary = is_array($report['summary'] ?? null) ? $report['summary'] : [];
$routes = is_array($report['routes'] ?? null) ? $report['routes'] : [];
$usageByKind = is_array($report['usage_by_source_kind'] ?? null) ? $report['usage_by_source_kind'] : [];
$warnings = is_array($report['warnings'] ?? null) ? $report['warnings'] : [];

opsui_shell_begin([
    'title' => 'Legacy Public Utility Usage Audit',
    'page_title' => 'Legacy Public Utility Usage Audit',
    'subtitle' => 'Safe Bolt → EDXEIX operator console',
    'breadcrumbs' => 'Αρχική / Διαχειριστικό / Legacy Public Utility Usage Audit',
    'active_section' => 'Developer Archive',
    'force_safe_notice' => true,
    'safe_notice' => 'READ-ONLY USAGE AUDIT. No Bolt, EDXEIX, AADE, DB, filesystem write, route move, route delete, redirect, include, or legacy utility execution. Scans readable local logs/stat caches only.',
]);
?>
<style>
.usage-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px;margin:16px 0}.usage-card{background:#fff;border:1px solid #d8dde7;border-radius:8px;padding:16px}.usage-card h3{margin:0 0 8px;color:#17386f;font-size:28px}.usage-badges{display:flex;flex-wrap:wrap;gap:8px;margin:12px 0}.usage-badge{display:inline-flex;align-items:center;border-radius:999px;padding:5px 9px;font-size:12px;font-weight:800;background:#e8f6ea;color:#176b24;border:1px solid #c8e9cc}.usage-badge.warn{background:#fff4dd;color:#885b00;border-color:#f0d49a}.usage-table{width:100%;border-collapse:collapse;background:#fff;border:1px solid #d8dde7;border-radius:8px;overflow:hidden}.usage-table th,.usage-table td{padding:10px;border-bottom:1px solid #e5e9f1;text-align:left;vertical-align:top}.usage-table th{background:#f6f8fb;color:#1d3764}.usage-code{display:inline-block;background:#f4f6fa;border:1px solid #d8dde7;border-radius:5px;padding:2px 5px;font-family:ui-monospace,SFMono-Regular,Consolas,monospace}.usage-muted{color:#55637f}.usage-actions{display:flex;flex-wrap:wrap;gap:8px;margin-top:12px}.btn{display:inline-flex;align-items:center;justify-content:center;border:0;border-radius:4px;background:#586482;color:#fff;text-decoration:none;padding:10px 14px;font-weight:800}.btn.primary{background:#4f5fad}.btn:hover{text-decoration:none;filter:brightness(.97)}.usage-warning{background:#fff4dd;border:1px solid #f0d49a;color:#704a00;border-radius:8px;padding:12px;margin:10px 0}
</style>

<section class="panel">
    <h1>Legacy Public Utility Usage Audit</h1>
    <p>Checks readable local logs and cPanel stat caches for historical mentions of the six legacy guarded public-root utilities. This page does not run those utilities and does not approve removal.</p>
    <div class="usage-badges">
        <span class="usage-badge">READ ONLY</span>
        <span class="usage-badge">NO DELETE</span>
        <span class="usage-badge">NO ROUTE MOVE</span>
        <span class="usage-badge">NO DB</span>
        <span class="usage-badge">NO EDXEIX CALL</span>
        <span class="usage-badge warn"><?= opsui_h((string)($report['version'] ?? 'v3.0.92')) ?></span>
    </div>
    <div class="usage-actions">
        <a class="btn primary" href="/ops/legacy-public-utility.php">Legacy Utility Wrapper</a>
        <a class="btn" href="/ops/public-utility-reference-cleanup-phase2-preview.php">Phase 2 Reference Preview</a>
        <a class="btn" href="/ops/public-utility-relocation-plan.php">Relocation Plan</a>
    </div>
</section>

<section class="usage-grid">
    <div class="usage-card"><h3><?= opsui_h((string)($summary['targets'] ?? 0)) ?></h3><p class="usage-muted">legacy utility targets</p></div>
    <div class="usage-card"><h3><?= opsui_h((string)($summary['files_scanned'] ?? 0)) ?></h3><p class="usage-muted">log/stat files scanned</p></div>
    <div class="usage-card"><h3><?= opsui_h((string)($summary['usage_mentions_total'] ?? 0)) ?></h3><p class="usage-muted">usage mentions found</p></div>
    <div class="usage-card"><h3><?= opsui_h((string)($summary['delete_recommended_now'] ?? 0)) ?></h3><p class="usage-muted">delete recommended now</p></div>
</section>

<?php foreach ($warnings as $warning): ?>
    <div class="usage-warning"><?= opsui_h((string)$warning) ?></div>
<?php endforeach; ?>

<section class="panel">
    <h2>Usage by legacy utility</h2>
    <table class="usage-table">
        <thead><tr><th>Legacy utility</th><th>Route</th><th>File</th><th>Mentions</th><th>Action</th></tr></thead>
        <tbody>
        <?php foreach ($routes as $route): ?>
            <?php if (!is_array($route)) { continue; } ?>
            <tr>
                <td><?= opsui_h((string)($route['label'] ?? '')) ?></td>
                <td><code class="usage-code"><?= opsui_h((string)($route['legacy_route'] ?? '')) ?></code></td>
                <td><?= !empty($route['file_meta']['exists']) ? 'present' : 'missing' ?></td>
                <td><?= opsui_h((string)($route['usage_mentions_total'] ?? 0)) ?></td>
                <td><?= opsui_h((string)($route['recommended_action'] ?? 'Keep compatibility route in place.')) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>

<section class="panel">
    <h2>Usage by source kind</h2>
    <table class="usage-table">
        <thead><tr><th>Source kind</th><th>Mentions</th></tr></thead>
        <tbody>
        <?php if (!$usageByKind): ?>
            <tr><td colspan="2">No mentions found in readable scanned sources.</td></tr>
        <?php endif; ?>
        <?php foreach ($usageByKind as $kind => $count): ?>
            <tr><td><code class="usage-code"><?= opsui_h((string)$kind) ?></code></td><td><?= opsui_h((string)$count) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>

<section class="panel">
    <h2>Next safe sequence</h2>
    <ol>
        <li>Keep all six legacy public-root utilities in place.</li>
        <li>Use this audit to understand historical/manual usage only.</li>
        <li>Do not delete based on this audit alone.</li>
        <li>Next safe engineering step is quiet-period tracking or explicit compatibility wrappers, after approval.</li>
    </ol>
</section>

<?php opsui_shell_end(); ?>
