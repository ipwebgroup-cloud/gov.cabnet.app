<?php
/**
 * gov.cabnet.app — Public Utility Reference Cleanup Phase 2 Preview.
 * Read-only ops page; no DB, no external calls, no writes.
 */

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Robots-Tag: noindex,nofollow', true);

require_once __DIR__ . '/_shell.php';

$homeRoot = dirname(__DIR__, 3);
$cliPath = $homeRoot . '/gov.cabnet.app_app/cli/public_utility_reference_cleanup_phase2_preview.php';
if (!is_file($cliPath)) {
    throw new RuntimeException('Phase 2 preview CLI is not installed.');
}
require_once $cliPath;

$report = gov_public_utility_reference_cleanup_phase2_preview_run();
$summary = is_array($report['summary'] ?? null) ? $report['summary'] : [];

opsui_shell_begin([
    'title' => 'Public Utility Reference Cleanup Phase 2 Preview',
    'page_title' => 'Public Utility Reference Cleanup Phase 2 Preview',
    'active_section' => 'Developer Archive',
    'breadcrumbs' => 'Αρχική / Διαχειριστικό / Public Utility Reference Cleanup Phase 2 Preview',
    'safe_notice' => 'READ-ONLY PREVIEW. No route moves, no route deletions, no DB, no Bolt, no EDXEIX, no AADE, and no filesystem writes. Filters inventory/planner references from actionable cleanup candidates.',
]);
?>
<section class="card hero neutral">
    <h1>Public Utility Reference Cleanup Phase 2 Preview</h1>
    <p>Identifies remaining actionable references to legacy guarded public-root utilities while ignoring inventory, audit, and planner references.</p>
    <div>
        <?= opsui_badge('READ ONLY', 'good') ?>
        <?= opsui_badge('NO DELETE', 'good') ?>
        <?= opsui_badge('NO DB', 'good') ?>
        <?= opsui_badge('NO EDXEIX CALL', 'good') ?>
        <?= opsui_badge('PHASE 2 PREVIEW', 'neutral') ?>
        <?= opsui_badge((string)($report['version'] ?? GOV_PURP2_VERSION), 'neutral') ?>
    </div>
    <div class="actions">
        <a class="btn" href="/ops/public-utility-reference-cleanup-phase2-preview.php">Refresh</a>
        <a class="btn dark" href="/ops/public-utility-relocation-plan.php">Relocation Plan</a>
        <a class="btn dark" href="/ops/public-route-exposure-audit.php">Public Route Exposure Audit</a>
    </div>
</section>

<section class="grid four">
    <div class="card metric"><strong><?= opsui_h((string)($summary['total_references'] ?? 0)) ?></strong><span>total refs found</span></div>
    <div class="card metric"><strong><?= opsui_h((string)($summary['inventory_or_planner_references_ignored'] ?? 0)) ?></strong><span>inventory/planner refs ignored</span></div>
    <div class="card metric"><strong><?= opsui_h((string)($summary['actionable_references'] ?? 0)) ?></strong><span>actionable refs</span></div>
    <div class="card metric"><strong><?= opsui_h((string)($summary['safe_phase2_candidates'] ?? 0)) ?></strong><span>safe phase 2 candidates</span></div>
</section>

<section class="two">
    <div class="card">
        <h2>Actionable by kind</h2>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Kind</th><th>Count</th></tr></thead>
                <tbody>
                <?php foreach ((array)($summary['actionable_by_kind'] ?? []) as $kind => $count): ?>
                    <tr><td><code><?= opsui_h((string)$kind) ?></code></td><td><?= opsui_h((string)$count) ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="card">
        <h2>Actionable by target</h2>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Target</th><th>Count</th></tr></thead>
                <tbody>
                <?php foreach ((array)($summary['actionable_by_target'] ?? []) as $target => $count): ?>
                    <tr><td><code><?= opsui_h((string)$target) ?></code></td><td><?= opsui_h((string)$count) ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<section class="card">
    <h2>Safe Phase 2 candidates</h2>
    <p class="small">These are docs or ops/admin references that can usually be cleaned before any route move. Do not edit private app or public-root compatibility code until separately reviewed.</p>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Target</th><th>Kind</th><th>Path</th><th>Line</th><th>Action hint</th></tr></thead>
            <tbody>
            <?php foreach ((array)($report['safe_phase2_candidates'] ?? []) as $ref): ?>
                <?php if (!is_array($ref)) { continue; } ?>
                <tr>
                    <td><code><?= opsui_h((string)($ref['target'] ?? '')) ?></code></td>
                    <td><?= opsui_badge(strtoupper((string)($ref['kind'] ?? '')), 'neutral') ?></td>
                    <td><code><?= opsui_h((string)($ref['path'] ?? '')) ?></code><br><span class="small"><?= opsui_h((string)($ref['preview'] ?? '')) ?></span></td>
                    <td><?= opsui_h((string)($ref['line'] ?? '')) ?></td>
                    <td><?= opsui_h((string)($ref['action_hint'] ?? '')) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="card">
    <h2>JSON report preview</h2>
    <textarea readonly style="width:100%;min-height:360px;border:1px solid #d8dde7;border-radius:4px;padding:12px;font-family:Consolas,Menlo,monospace;font-size:12px;line-height:1.4;"><?= opsui_h(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?></textarea>
</section>
<?php opsui_shell_end(); ?>
