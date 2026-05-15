<?php
/**
 * gov.cabnet.app — Legacy Public Utility Stats Source Audit.
 *
 * v3.0.96:
 * - Read-only classifier for usage evidence source kinds.
 * - Does not execute legacy utilities or perform external/API/DB/write actions.
 */

declare(strict_types=1);

require_once __DIR__ . '/_shell.php';
require_once '/home/cabnet/gov.cabnet.app_app/cli/legacy_public_utility_stats_source_audit.php';

$report = gov_legacy_public_utility_stats_source_audit_run();
$summary = is_array($report['summary'] ?? null) ? $report['summary'] : [];
$routes = is_array($report['routes'] ?? null) ? $report['routes'] : [];

opsui_shell_begin([
    'title' => 'Legacy Utility Stats Source Audit',
    'page_title' => 'Legacy Utility Stats Source Audit',
    'subtitle' => 'Safe Bolt → EDXEIX operator console',
    'breadcrumbs' => 'Αρχική / Διαχειριστικό / Legacy Utility Stats Source Audit',
    'active_section' => 'Developer Archive',
    'force_safe_notice' => true,
    'safe_notice' => 'READ-ONLY STATS SOURCE AUDIT. No Bolt, EDXEIX, AADE, DB, filesystem write, route move, route delete, redirect, include, or legacy utility execution. Classifies usage evidence already found by the usage audit.',
]);
?>
<style>
.stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px;margin:16px 0}.stats-card{background:#fff;border:1px solid #d8dde7;border-radius:8px;padding:16px}.stats-card h3{margin:0 0 8px;color:#17386f;font-size:28px}.stats-badges{display:flex;flex-wrap:wrap;gap:8px;margin:12px 0}.stats-badge{display:inline-flex;align-items:center;border-radius:999px;padding:5px 9px;font-size:12px;font-weight:800;background:#e8f6ea;color:#176b24;border:1px solid #c8e9cc}.stats-badge.warn{background:#fff4dd;color:#885b00;border-color:#f0d49a}.stats-table{width:100%;border-collapse:collapse;background:#fff;border:1px solid #d8dde7;border-radius:8px;overflow:hidden}.stats-table th,.stats-table td{padding:10px;border-bottom:1px solid #e5e9f1;text-align:left;vertical-align:top}.stats-table th{background:#f6f8fb;color:#1d3764}.stats-code{display:inline-block;background:#f4f6fa;border:1px solid #d8dde7;border-radius:5px;padding:2px 5px;font-family:ui-monospace,SFMono-Regular,Consolas,monospace}.stats-muted{color:#55637f}.stats-actions{display:flex;flex-wrap:wrap;gap:8px;margin-top:12px}.btn{display:inline-flex;align-items:center;justify-content:center;border:0;border-radius:4px;background:#586482;color:#fff;text-decoration:none;padding:10px 14px;font-weight:800}.btn.primary{background:#4f5fad}.btn:hover{text-decoration:none;filter:brightness(.97)}.stats-note{background:#eef6ff;border:1px solid #bfd9ff;color:#17386f;border-radius:8px;padding:12px;margin:10px 0}.nowrap{white-space:nowrap}
</style>

<section class="panel">
    <h1>Legacy Utility Stats Source Audit</h1>
    <p>Classifies the historical usage evidence source for the six guarded public-root utilities. This clarifies whether unknown-date evidence is only cPanel stats/cache evidence or something more active.</p>
    <div class="stats-badges">
        <span class="stats-badge">READ ONLY</span>
        <span class="stats-badge">NO DELETE</span>
        <span class="stats-badge">NO ROUTE MOVE</span>
        <span class="stats-badge">NO REDIRECT</span>
        <span class="stats-badge">NO DB</span>
        <span class="stats-badge">NO EDXEIX CALL</span>
        <span class="stats-badge warn"><?= opsui_h((string)($report['version'] ?? 'v3.0.96')) ?></span>
    </div>
    <div class="stats-actions">
        <a class="btn primary" href="/ops/legacy-public-utility-usage-audit.php">Usage Audit</a>
        <a class="btn" href="/ops/legacy-public-utility-quiet-period-audit.php">Quiet-Period Audit</a>
        <a class="btn" href="/ops/legacy-public-utility.php">Legacy Utility Wrapper</a>
        <a class="btn" href="/ops/public-utility-reference-cleanup-phase2-preview.php">Phase 2 Preview</a>
    </div>
</section>

<section class="stats-grid">
    <div class="stats-card"><h3><?= opsui_h((string)($summary['routes_reviewed'] ?? 0)) ?></h3><p class="stats-muted">routes reviewed</p></div>
    <div class="stats-card"><h3><?= opsui_h((string)($summary['usage_mentions_total'] ?? 0)) ?></h3><p class="stats-muted">usage mentions</p></div>
    <div class="stats-card"><h3><?= opsui_h((string)($summary['cpanel_stats_cache_only_routes'] ?? 0)) ?></h3><p class="stats-muted">cPanel stats/cache only</p></div>
    <div class="stats-card"><h3><?= opsui_h((string)($summary['live_access_log_evidence_routes'] ?? 0)) ?></h3><p class="stats-muted">live/raw access log evidence</p></div>
    <div class="stats-card"><h3><?= opsui_h((string)($summary['move_recommended_now'] ?? 0)) ?></h3><p class="stats-muted">move recommended now</p></div>
    <div class="stats-card"><h3><?= opsui_h((string)($summary['delete_recommended_now'] ?? 0)) ?></h3><p class="stats-muted">delete recommended now</p></div>
</section>

<section class="stats-note">
    <strong>Interpretation:</strong> cPanel stats/cache-only evidence is historical evidence, not proof of active cron use. It still does not approve route deletion. Use it only to decide whether a future compatibility-stub review is safe after explicit approval and one final access-log/dependency check.
</section>

<section class="panel">
    <h2>Route source classification</h2>
    <table class="stats-table">
        <thead><tr><th>Route</th><th>Mentions</th><th>Last seen</th><th>Source kind</th><th>Classification</th><th>Action</th></tr></thead>
        <tbody>
        <?php foreach ($routes as $route): ?>
            <?php if (!is_array($route)) { continue; } ?>
            <tr>
                <td><code class="stats-code nowrap"><?= opsui_h((string)($route['route'] ?? '')) ?></code></td>
                <td><?= opsui_h((string)($route['mentions'] ?? 0)) ?></td>
                <td><?= opsui_h((string)(($route['last_seen'] ?? '') ?: 'none')) ?></td>
                <td>
                    <?php foreach ((array)($route['source_kind_names'] ?? []) as $kind): ?>
                        <code class="stats-code"><?= opsui_h((string)$kind) ?></code><br>
                    <?php endforeach; ?>
                </td>
                <td><code class="stats-code"><?= opsui_h((string)($route['classification'] ?? '')) ?></code></td>
                <td><?= opsui_h((string)($route['recommended_action'] ?? 'Keep unchanged.')) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>

<section class="panel">
    <h2>Safety result</h2>
    <ul>
        <li>No public-root route is moved or deleted.</li>
        <li>No redirect or compatibility stub is added by this audit.</li>
        <li>Legacy utilities are not included or executed.</li>
        <li>Live EDXEIX submission remains disabled.</li>
    </ul>
</section>

<?php opsui_shell_end(); ?>
