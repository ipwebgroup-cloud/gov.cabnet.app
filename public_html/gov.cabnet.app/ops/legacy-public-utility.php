<?php
/**
 * gov.cabnet.app — Legacy Public Utility Ops Wrapper.
 *
 * v3.0.89:
 * - Adds a safe /ops landing wrapper for guarded legacy public-root utilities.
 * - Does not execute, include, redirect to, move, delete, or disable the legacy utilities.
 * - Gives a stable future target for reference cleanup before public-root relocation.
 */

declare(strict_types=1);

require_once __DIR__ . '/_shell.php';
require_once '/home/cabnet/gov.cabnet.app_app/src/Support/LegacyPublicUtilityRegistry.php';

$report = gov_legacy_public_utility_report();
$items = gov_legacy_public_utility_items();
$selectedKey = preg_replace('/[^a-z0-9\-]/i', '', (string)($_GET['utility'] ?? '')) ?? '';
$selected = $selectedKey !== '' ? gov_legacy_public_utility_find($selectedKey) : null;

opsui_shell_begin([
    'title' => 'Legacy Public Utility Wrapper',
    'page_title' => 'Legacy Public Utility Wrapper',
    'subtitle' => 'Safe Bolt → EDXEIX operator console',
    'breadcrumbs' => 'Αρχική / Διαχειριστικό / Legacy Public Utility Wrapper',
    'active_section' => 'Developer Archive',
    'force_safe_notice' => true,
    'safe_notice' => 'READ-ONLY WRAPPER. No Bolt, EDXEIX, AADE, DB, route move, route delete, redirect, include, or legacy utility execution. This creates a stable /ops destination for future cleanup only.',
]);
?>
<style>
.legacy-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:14px;margin:16px 0}.legacy-card{background:#fff;border:1px solid #d8dde7;border-radius:8px;padding:16px}.legacy-card h3{margin:0 0 8px;color:#17386f}.legacy-badges{display:flex;flex-wrap:wrap;gap:8px;margin:12px 0}.legacy-badge{display:inline-flex;align-items:center;border-radius:999px;padding:5px 9px;font-size:12px;font-weight:800;background:#e8f6ea;color:#176b24;border:1px solid #c8e9cc}.legacy-badge.warn{background:#fff4dd;color:#885b00;border-color:#f0d49a}.legacy-badge.danger{background:#ffe7e7;color:#9b1c1c;border-color:#f3b7b7}.legacy-table{width:100%;border-collapse:collapse;background:#fff;border:1px solid #d8dde7;border-radius:8px;overflow:hidden}.legacy-table th,.legacy-table td{padding:10px;border-bottom:1px solid #e5e9f1;text-align:left;vertical-align:top}.legacy-table th{background:#f6f8fb;color:#1d3764}.legacy-code{display:inline-block;background:#f4f6fa;border:1px solid #d8dde7;border-radius:5px;padding:2px 5px;font-family:ui-monospace,SFMono-Regular,Consolas,monospace}.legacy-muted{color:#55637f}.legacy-actions{display:flex;flex-wrap:wrap;gap:8px;margin-top:12px}.btn{display:inline-flex;align-items:center;justify-content:center;border:0;border-radius:4px;background:#586482;color:#fff;text-decoration:none;padding:10px 14px;font-weight:800}.btn.primary{background:#4f5fad}.btn:hover{text-decoration:none;filter:brightness(.97)}
</style>

<section class="panel">
    <h1>Legacy Public Utility Wrapper</h1>
    <p>This page is a safe /ops landing wrapper for legacy guarded public-root utilities. It does not run them. It gives us a stable destination for future reference cleanup before any route relocation.</p>
    <div class="legacy-badges">
        <span class="legacy-badge">READ ONLY</span>
        <span class="legacy-badge">NO DELETE</span>
        <span class="legacy-badge">NO REDIRECT</span>
        <span class="legacy-badge">NO DB</span>
        <span class="legacy-badge">NO EDXEIX CALL</span>
        <span class="legacy-badge warn"><?= opsui_h((string)($report['version'] ?? 'v3.0.89')) ?></span>
    </div>
    <div class="legacy-actions">
        <a class="btn primary" href="/ops/public-utility-reference-cleanup-phase2-preview.php">Phase 2 Reference Preview</a>
        <a class="btn" href="/ops/public-utility-relocation-plan.php">Relocation Plan</a>
        <a class="btn" href="/ops/public-route-exposure-audit.php">Public Route Exposure Audit</a>
    </div>
</section>

<section class="legacy-grid">
    <div class="legacy-card">
        <h3><?= opsui_h((string)($report['summary']['utilities_registered'] ?? 0)) ?></h3>
        <p class="legacy-muted">registered legacy utilities</p>
    </div>
    <div class="legacy-card">
        <h3><?= opsui_h((string)($report['summary']['legacy_files_existing'] ?? 0)) ?></h3>
        <p class="legacy-muted">legacy files still present</p>
    </div>
    <div class="legacy-card">
        <h3><?= opsui_h((string)($report['summary']['move_recommended_now'] ?? 0)) ?></h3>
        <p class="legacy-muted">move recommended now</p>
    </div>
    <div class="legacy-card">
        <h3><?= opsui_h((string)($report['summary']['delete_recommended_now'] ?? 0)) ?></h3>
        <p class="legacy-muted">delete recommended now</p>
    </div>
</section>

<?php if (is_array($selected)): ?>
<?php $meta = gov_legacy_public_utility_file_meta((string)$selected['legacy_file']); ?>
<section class="panel">
    <h2><?= opsui_h((string)$selected['label']) ?></h2>
    <p><?= opsui_h((string)$selected['role']) ?></p>
    <table class="legacy-table">
        <tbody>
            <tr><th>Legacy route</th><td><code class="legacy-code"><?= opsui_h((string)$selected['legacy_route']) ?></code></td></tr>
            <tr><th>Current posture</th><td><?= opsui_h((string)$selected['current_posture']) ?></td></tr>
            <tr><th>Future target</th><td><?= opsui_h((string)$selected['future_target']) ?></td></tr>
            <tr><th>Operator use</th><td><?= opsui_h((string)$selected['operator_use']) ?></td></tr>
            <tr><th>File exists</th><td><?= !empty($meta['exists']) ? 'yes' : 'no' ?></td></tr>
            <tr><th>Move now</th><td><?= !empty($selected['move_now']) ? 'yes' : 'no' ?></td></tr>
            <tr><th>Delete now</th><td><?= !empty($selected['delete_now']) ? 'yes' : 'no' ?></td></tr>
            <tr><th>Wrapper executes legacy utility</th><td><?= !empty($selected['direct_execution_from_wrapper']) ? 'yes' : 'no' ?></td></tr>
        </tbody>
    </table>
</section>
<?php endif; ?>

<section class="panel">
    <h2>Registered legacy utilities</h2>
    <table class="legacy-table">
        <thead>
            <tr>
                <th>Wrapper target</th>
                <th>Legacy route</th>
                <th>Risk</th>
                <th>Future target</th>
                <th>File</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($items as $key => $item): ?>
            <?php $meta = gov_legacy_public_utility_file_meta((string)$item['legacy_file']); ?>
            <tr>
                <td><a href="/ops/legacy-public-utility.php?utility=<?= opsui_h((string)$key) ?>"><?= opsui_h((string)$item['label']) ?></a></td>
                <td><code class="legacy-code"><?= opsui_h((string)$item['legacy_route']) ?></code></td>
                <td><span class="legacy-badge <?= ((string)$item['risk_level'] === 'high') ? 'danger' : 'warn' ?>"><?= opsui_h((string)$item['risk_level']) ?></span></td>
                <td><?= opsui_h((string)$item['future_target']) ?></td>
                <td><?= !empty($meta['exists']) ? 'present' : 'missing' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>

<section class="panel">
    <h2>Next safe sequence</h2>
    <ol>
        <li>Keep all legacy public-root routes in place for compatibility.</li>
        <li>Use this page as the safe future /ops target for references.</li>
        <li>Update low-risk generated/test links first in a later patch.</li>
        <li>Only after references and cron/bookmark usage are clean, create private CLI equivalents or compatibility stubs.</li>
        <li>Do not delete or move any legacy route without explicit approval.</li>
    </ol>
</section>

<?php opsui_shell_end(); ?>
