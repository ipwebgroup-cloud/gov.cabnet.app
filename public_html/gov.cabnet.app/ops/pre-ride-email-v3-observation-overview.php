<?php
/**
 * gov.cabnet.app — V3 Real-Mail Observation Overview.
 *
 * v3.1.9:
 * - Read-only combined board for queue health, expiry audit, and next candidate watch.
 * - No Bolt, EDXEIX, AADE, DB write, queue mutation, route move/delete, redirect, or live-submit action.
 */

declare(strict_types=1);

require_once __DIR__ . '/_shell.php';
require_once '/home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_observation_overview.php';

$report = gov_v3_observation_overview_run();
$summary = is_array($report['summary'] ?? null) ? $report['summary'] : [];
$components = is_array($report['components'] ?? null) ? $report['components'] : [];
$warnings = is_array($report['warnings'] ?? null) ? $report['warnings'] : [];
$finalBlocks = is_array($report['final_blocks'] ?? null) ? $report['final_blocks'] : [];

opsui_shell_begin([
    'title' => 'V3 Real-Mail Observation Overview',
    'page_title' => 'V3 Real-Mail Observation Overview',
    'subtitle' => 'Safe Bolt → EDXEIX closed-gate observation rollup',
    'breadcrumbs' => 'Αρχική / Διαχειριστικό / V3 Real-Mail Observation Overview',
    'active_section' => 'V3 proof & readiness',
    'force_safe_notice' => true,
    'safe_notice' => 'READ-ONLY V3 OVERVIEW. Composes existing read-only queue health, expiry audit, and candidate watch outputs. No live submit and no queue mutation.',
]);
?>
<style>
.v3ov-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:14px;margin:16px 0}.v3ov-card{background:#fff;border:1px solid #d8dde7;border-radius:8px;padding:16px}.v3ov-card h3{margin:0 0 8px;color:#17386f;font-size:28px}.v3ov-muted{color:#55637f}.v3ov-badges{display:flex;flex-wrap:wrap;gap:8px;margin:12px 0}.v3ov-badge{display:inline-flex;align-items:center;border-radius:999px;padding:5px 9px;font-size:12px;font-weight:800;background:#e8f6ea;color:#176b24;border:1px solid #c8e9cc}.v3ov-badge.warn{background:#fff4dd;color:#885b00;border-color:#f0d49a}.v3ov-badge.bad{background:#feeceb;color:#9d241d;border-color:#f3b8b4}.v3ov-table{width:100%;border-collapse:collapse;background:#fff;border:1px solid #d8dde7;border-radius:8px;overflow:hidden}.v3ov-table th,.v3ov-table td{padding:10px;border-bottom:1px solid #e5e9f1;text-align:left;vertical-align:top}.v3ov-table th{background:#f6f8fb;color:#1d3764}.v3ov-code{display:inline-block;background:#f4f6fa;border:1px solid #d8dde7;border-radius:5px;padding:2px 5px;font-family:ui-monospace,SFMono-Regular,Consolas,monospace}.v3ov-warning{background:#fff4dd;border:1px solid #f0d49a;color:#704a00;border-radius:8px;padding:12px;margin:10px 0}.v3ov-block{background:#feeceb;border:1px solid #f3b8b4;color:#7e211b;border-radius:8px;padding:12px;margin:10px 0}.v3ov-actions{display:flex;flex-wrap:wrap;gap:8px;margin-top:12px}.btn{display:inline-flex;align-items:center;justify-content:center;border:0;border-radius:4px;background:#586482;color:#fff;text-decoration:none;padding:10px 14px;font-weight:800}.btn.primary{background:#4f5fad}.btn:hover{text-decoration:none;filter:brightness(.97)}
</style>

<section class="panel">
    <h1>V3 Real-Mail Observation Overview</h1>
    <p>One read-only rollup for V3 real-mail queue health, expiry reasons, and next candidate watch. This page does not submit, approve, mutate, or write anything.</p>
    <div class="v3ov-badges">
        <span class="v3ov-badge">READ ONLY</span>
        <span class="v3ov-badge">NO DB WRITES</span>
        <span class="v3ov-badge">NO QUEUE MUTATION</span>
        <span class="v3ov-badge">NO BOLT CALL</span>
        <span class="v3ov-badge">NO EDXEIX CALL</span>
        <span class="v3ov-badge">NO AADE CALL</span>
        <span class="v3ov-badge warn"><?= opsui_h((string)($report['version'] ?? 'v3.1.9')) ?></span>
    </div>
    <div class="v3ov-actions">
        <a class="btn primary" href="/ops/pre-ride-email-v3-next-real-mail-candidate-watch.php">Next Candidate Watch</a>
        <a class="btn" href="/ops/pre-ride-email-v3-real-mail-queue-health.php">Real-Mail Queue Health</a>
        <a class="btn" href="/ops/pre-ride-email-v3-real-mail-expiry-reason-audit.php">Expiry Reason Audit</a>
        <a class="btn" href="/ops/pre-ride-email-v3-live-operator-console.php">Live Operator Console</a>
    </div>
</section>

<section class="v3ov-grid">
    <div class="v3ov-card"><h3><?= !empty($summary['queue_health_ok']) ? 'yes' : 'no' ?></h3><p class="v3ov-muted">queue health OK</p></div>
    <div class="v3ov-card"><h3><?= !empty($summary['expiry_audit_ok']) ? 'yes' : 'no' ?></h3><p class="v3ov-muted">expiry audit OK</p></div>
    <div class="v3ov-card"><h3><?= !empty($summary['candidate_watch_ok']) ? 'yes' : 'no' ?></h3><p class="v3ov-muted">candidate watch OK</p></div>
    <div class="v3ov-card"><h3><?= opsui_h((string)($summary['future_active_rows'] ?? 0)) ?></h3><p class="v3ov-muted">future active rows</p></div>
    <div class="v3ov-card"><h3><?= opsui_h((string)($summary['operator_review_candidates'] ?? 0)) ?></h3><p class="v3ov-muted">operator candidates</p></div>
    <div class="v3ov-card"><h3><?= !empty($summary['live_risk_detected']) ? 'yes' : 'no' ?></h3><p class="v3ov-muted">live risk detected</p></div>
</section>

<section class="v3ov-grid">
    <div class="v3ov-card"><h3><?= opsui_h((string)($summary['possible_real_rows_queue_health'] ?? 0)) ?></h3><p class="v3ov-muted">possible-real rows in queue health</p></div>
    <div class="v3ov-card"><h3><?= opsui_h((string)($summary['possible_real_expired_guard_rows'] ?? 0)) ?></h3><p class="v3ov-muted">expired by future-safety guard</p></div>
    <div class="v3ov-card"><h3><?= opsui_h((string)($summary['mapping_correction_rows'] ?? 0)) ?></h3><p class="v3ov-muted">historical mapping-correction rows</p></div>
    <div class="v3ov-card"><h3><?= !empty($summary['queue_health_vs_expiry_count_mismatch_explained']) ? 'yes' : 'no' ?></h3><p class="v3ov-muted">count mismatch explained</p></div>
</section>

<?php foreach ($warnings as $warning): ?>
    <div class="v3ov-warning"><?= opsui_h((string)$warning) ?></div>
<?php endforeach; ?>

<?php foreach ($finalBlocks as $block): ?>
    <div class="v3ov-block"><?= opsui_h((string)$block) ?></div>
<?php endforeach; ?>

<section class="panel">
    <h2>Component status</h2>
    <table class="v3ov-table">
        <thead>
            <tr>
                <th>Component</th>
                <th>OK</th>
                <th>Version</th>
                <th>Loaded</th>
                <th>Final blocks</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($components as $key => $component): ?>
            <?php if (!is_array($component)) { continue; } ?>
            <tr>
                <td><code class="v3ov-code"><?= opsui_h((string)$key) ?></code></td>
                <td><?= !empty($component['ok']) ? 'yes' : 'no' ?></td>
                <td><?= opsui_h((string)($component['version'] ?? '')) ?></td>
                <td><?= !empty($component['loaded']) ? 'yes' : 'no' ?></td>
                <td><small><?= opsui_h(implode('; ', array_map('strval', is_array($component['final_blocks'] ?? null) ? $component['final_blocks'] : []))) ?></small></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>

<section class="panel">
    <h2>Safe conclusion</h2>
    <ol>
        <li>Use this overview as the daily V3 real-mail observation board.</li>
        <li>If <code>operator_review_candidates</code> becomes greater than zero, inspect immediately with closed-gate tools before pickup expires.</li>
        <li>This overview does not enable or perform live EDXEIX submission.</li>
        <li>Live submission remains blocked until Andreas explicitly requests a live-submit update and all live gates pass.</li>
    </ol>
    <p><strong>Recommended next step:</strong> <?= opsui_h((string)($report['recommended_next_step'] ?? 'Continue observing.')) ?></p>
</section>

<?php opsui_shell_end(); ?>
