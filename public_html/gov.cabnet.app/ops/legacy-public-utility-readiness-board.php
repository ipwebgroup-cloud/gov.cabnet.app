<?php
/**
 * gov.cabnet.app — Legacy Public Utility Readiness Board.
 *
 * v3.0.98:
 * - Read-only aggregate board for usage, quiet-period, stats-source, and Phase 2 reference audits.
 * - Does not execute legacy utilities or perform external/API/DB/write actions.
 */

declare(strict_types=1);

require_once __DIR__ . '/_shell.php';
require_once '/home/cabnet/gov.cabnet.app_app/cli/legacy_public_utility_readiness_board.php';

$report = gov_legacy_public_utility_readiness_board_run();
$summary = is_array($report['summary'] ?? null) ? $report['summary'] : [];
$routes = is_array($report['routes'] ?? null) ? $report['routes'] : [];
$finalBlocks = is_array($report['final_blocks'] ?? null) ? $report['final_blocks'] : [];
$reportStatus = is_array($report['report_status'] ?? null) ? $report['report_status'] : [];

opsui_shell_begin([
    'title' => 'Legacy Public Utility Readiness Board',
    'page_title' => 'Legacy Public Utility Readiness Board',
    'subtitle' => 'Safe Bolt → EDXEIX operator console',
    'breadcrumbs' => 'Αρχική / Διαχειριστικό / Legacy Public Utility Readiness Board',
    'active_section' => 'Developer Archive',
    'force_safe_notice' => true,
    'safe_notice' => 'READ-ONLY AGGREGATE BOARD. No Bolt, EDXEIX, AADE, DB, filesystem write, route move, route delete, redirect, include, or legacy utility execution. This page summarizes existing read-only audits only.',
]);
?>
<style>
.lpub-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:14px;margin:16px 0}.lpub-card{background:#fff;border:1px solid #d8dde7;border-radius:8px;padding:16px}.lpub-card h3{margin:0 0 8px;color:#17386f;font-size:28px}.lpub-card p{margin:0;color:#55637f}.lpub-badges{display:flex;flex-wrap:wrap;gap:8px;margin:12px 0}.lpub-badge{display:inline-flex;align-items:center;border-radius:999px;padding:5px 9px;font-size:12px;font-weight:800;background:#e8f6ea;color:#176b24;border:1px solid #c8e9cc}.lpub-badge.warn{background:#fff4dd;color:#885b00;border-color:#f0d49a}.lpub-badge.dark{background:#eef1f8;color:#27385f;border-color:#d8dde7}.lpub-table{width:100%;border-collapse:collapse;background:#fff;border:1px solid #d8dde7;border-radius:8px;overflow:hidden}.lpub-table th,.lpub-table td{padding:10px;border-bottom:1px solid #e5e9f1;text-align:left;vertical-align:top}.lpub-table th{background:#f6f8fb;color:#1d3764}.lpub-code{display:inline-block;background:#f4f6fa;border:1px solid #d8dde7;border-radius:5px;padding:2px 5px;font-family:ui-monospace,SFMono-Regular,Consolas,monospace}.lpub-muted{color:#55637f}.lpub-actions{display:flex;flex-wrap:wrap;gap:8px;margin-top:12px}.btn{display:inline-flex;align-items:center;justify-content:center;border:0;border-radius:4px;background:#586482;color:#fff;text-decoration:none;padding:10px 14px;font-weight:800}.btn.primary{background:#4f5fad}.btn:hover{text-decoration:none;filter:brightness(.97)}.lpub-warning{background:#fff4dd;border:1px solid #f0d49a;color:#704a00;border-radius:8px;padding:12px;margin:10px 0}.lpub-ok{background:#e8f6ea;border:1px solid #c8e9cc;color:#176b24;border-radius:8px;padding:12px;margin:10px 0}.lpub-readiness{font-weight:800}.lpub-readiness.candidate_for_future_compatibility_stub_review_only{color:#885b00}.lpub-readiness.manual_review_required_before_stub{color:#704a00}.lpub-readiness.blocked_by_recent_or_live_evidence{color:#a52222}.lpub-readiness.keep_unchanged{color:#27385f}
</style>

<section class="panel">
    <h1>Legacy Public Utility Readiness Board</h1>
    <p>Aggregates the read-only usage, quiet-period, stats-source, and Phase 2 reference audits for the six guarded legacy public-root utilities. This board does not run those utilities and does not approve route removal.</p>
    <div class="lpub-badges">
        <span class="lpub-badge">READ ONLY</span>
        <span class="lpub-badge">NO DELETE</span>
        <span class="lpub-badge">NO REDIRECT</span>
        <span class="lpub-badge">NO DB</span>
        <span class="lpub-badge">NO EDXEIX CALL</span>
        <span class="lpub-badge warn"><?= opsui_h((string)($report['version'] ?? 'v3.0.98')) ?></span>
    </div>
    <div class="lpub-actions">
        <a class="btn primary" href="/ops/legacy-public-utility-usage-audit.php">Usage Audit</a>
        <a class="btn" href="/ops/legacy-public-utility-quiet-period-audit.php">Quiet-Period Audit</a>
        <a class="btn" href="/ops/legacy-public-utility-stats-source-audit.php">Stats Source Audit</a>
        <a class="btn" href="/ops/public-utility-reference-cleanup-phase2-preview.php">Phase 2 Preview</a>
        <a class="btn" href="/ops/legacy-public-utility.php">Legacy Wrapper</a>
    </div>
</section>

<?php if ($finalBlocks): ?>
    <?php foreach ($finalBlocks as $block): ?>
        <div class="lpub-warning"><strong>Final block:</strong> <?= opsui_h((string)$block) ?></div>
    <?php endforeach; ?>
<?php else: ?>
    <div class="lpub-ok"><strong>Audit aggregate OK.</strong> No final blocks. This still does not approve route moves, deletes, redirects, or compatibility stubs.</div>
<?php endif; ?>

<section class="lpub-grid">
    <div class="lpub-card"><h3><?= opsui_h((string)($summary['routes_reviewed'] ?? 0)) ?></h3><p>routes reviewed</p></div>
    <div class="lpub-card"><h3><?= opsui_h((string)($summary['usage_mentions_total'] ?? 0)) ?></h3><p>usage mentions</p></div>
    <div class="lpub-card"><h3><?= opsui_h((string)($summary['cpanel_stats_cache_only_routes'] ?? 0)) ?></h3><p>cPanel stats/cache-only routes</p></div>
    <div class="lpub-card"><h3><?= opsui_h((string)($summary['live_access_log_evidence_routes'] ?? 0)) ?></h3><p>live/raw access-log routes</p></div>
    <div class="lpub-card"><h3><?= opsui_h((string)($summary['move_recommended_now'] ?? 0)) ?></h3><p>move recommended now</p></div>
    <div class="lpub-card"><h3><?= opsui_h((string)($summary['delete_recommended_now'] ?? 0)) ?></h3><p>delete recommended now</p></div>
</section>

<section class="panel">
    <h2>Route readiness summary</h2>
    <table class="lpub-table">
        <thead>
            <tr>
                <th>Route</th>
                <th>Mentions</th>
                <th>Quiet-period classification</th>
                <th>Stats/source classification</th>
                <th>Readiness</th>
                <th>Recommendation</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($routes as $route): ?>
            <?php if (!is_array($route)) { continue; } ?>
            <?php $readiness = (string)($route['readiness'] ?? ''); ?>
            <tr>
                <td><code class="lpub-code"><?= opsui_h((string)($route['route'] ?? '')) ?></code></td>
                <td><?= opsui_h((string)($route['mentions'] ?? 0)) ?></td>
                <td><?= opsui_h((string)($route['quiet_period_classification'] ?? '')) ?></td>
                <td><?= opsui_h((string)($route['stats_source_classification'] ?? '')) ?></td>
                <td><span class="lpub-readiness <?= opsui_h($readiness) ?>"><?= opsui_h($readiness) ?></span></td>
                <td><?= opsui_h((string)($route['recommended_action'] ?? 'Keep unchanged.')) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>

<section class="panel">
    <h2>Audit dependency status</h2>
    <table class="lpub-table">
        <thead><tr><th>Audit</th><th>OK</th><th>Error</th></tr></thead>
        <tbody>
        <?php foreach ($reportStatus as $key => $row): ?>
            <?php if (!is_array($row)) { continue; } ?>
            <tr>
                <td><code class="lpub-code"><?= opsui_h((string)$key) ?></code> <?= opsui_h((string)($row['label'] ?? '')) ?></td>
                <td><?= !empty($row['ok']) ? 'yes' : 'no' ?></td>
                <td><?= opsui_h((string)($row['error'] ?? '')) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>

<section class="panel">
    <h2>Decision guardrail</h2>
    <ol>
        <li>Do not move, delete, redirect, or stub any legacy public-root utility from this board alone.</li>
        <li>Before any future compatibility-stub patch, run one final dependency scan and require explicit approval.</li>
        <li>Keep the production pre-ride tool and V0 workflows untouched.</li>
        <li>Keep live EDXEIX submission disabled unless Andreas explicitly requests a live-submit update.</li>
    </ol>
</section>

<?php opsui_shell_end(); ?>
