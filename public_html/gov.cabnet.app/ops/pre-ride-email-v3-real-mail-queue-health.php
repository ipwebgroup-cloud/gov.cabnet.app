<?php
/**
 * gov.cabnet.app — V3 Real-Mail Intake + Queue Health.
 *
 * v3.1.0:
 * - Read-only ops page for V3 real-mail intake observation and queue health.
 * - Does not submit, enqueue, call APIs, or mutate database/filesystem state.
 */

declare(strict_types=1);

require_once __DIR__ . '/_shell.php';
require_once '/home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_real_mail_queue_health.php';

$report = gov_pre_ride_email_v3_real_mail_queue_health_run();
$summary = is_array($report['summary'] ?? null) ? $report['summary'] : [];
$queue = is_array($report['queue'] ?? null) ? $report['queue'] : [];
$health = is_array($queue['health'] ?? null) ? $queue['health'] : [];
$statusCounts = is_array($health['status_counts'] ?? null) ? $health['status_counts'] : [];
$latest = is_array($queue['latest_row_classification'] ?? null) ? $queue['latest_row_classification'] : [];
$warnings = is_array($report['warnings'] ?? null) ? $report['warnings'] : [];
$finalBlocks = is_array($report['final_blocks'] ?? null) ? $report['final_blocks'] : [];
$gate = is_array($report['live_gate'] ?? null) ? $report['live_gate'] : [];

opsui_shell_begin([
    'title' => 'V3 Real-Mail Queue Health',
    'page_title' => 'V3 Real-Mail Intake + Queue Health',
    'subtitle' => 'Closed-gate observation for real Bolt pre-ride mail intake',
    'breadcrumbs' => 'Αρχική / Διαχειριστικό / V3 Real-Mail Queue Health',
    'active_section' => 'V3 proof & readiness',
    'force_safe_notice' => true,
    'safe_notice' => 'READ-ONLY V3 QUEUE HEALTH. No Bolt, EDXEIX, AADE, DB writes, queue mutations, filesystem writes, route moves, route deletes, redirects, or live-submit enablement.',
]);
?>
<style>
.v3rm-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:14px;margin:16px 0}.v3rm-card{background:#fff;border:1px solid #d8dde7;border-radius:10px;padding:16px;box-shadow:0 8px 18px rgba(26,33,52,.04)}.v3rm-card h3{margin:0 0 6px;color:#17386f;font-size:28px}.v3rm-card p{margin:0;color:#55637f}.v3rm-badges{display:flex;flex-wrap:wrap;gap:8px;margin:12px 0}.v3rm-badge{display:inline-flex;align-items:center;border-radius:999px;padding:5px 9px;font-size:12px;font-weight:800;background:#e8f6ea;color:#176b24;border:1px solid #c8e9cc}.v3rm-badge.warn{background:#fff4dd;color:#885b00;border-color:#f0d49a}.v3rm-badge.bad{background:#feeceb;color:#9a1b16;border-color:#f0b8b2}.v3rm-table{width:100%;border-collapse:collapse;background:#fff;border:1px solid #d8dde7;border-radius:8px;overflow:hidden;margin-top:12px}.v3rm-table th,.v3rm-table td{padding:10px;border-bottom:1px solid #e5e9f1;text-align:left;vertical-align:top}.v3rm-table th{background:#f6f8fb;color:#1d3764}.v3rm-code{display:inline-block;background:#f4f6fa;border:1px solid #d8dde7;border-radius:5px;padding:2px 5px;font-family:ui-monospace,SFMono-Regular,Consolas,monospace}.v3rm-muted{color:#55637f}.v3rm-panel{background:#fff;border:1px solid #d8dde7;border-radius:10px;padding:16px;margin:16px 0}.v3rm-alert{border-radius:8px;padding:12px;margin:10px 0;background:#fff4dd;border:1px solid #f0d49a;color:#704a00}.v3rm-alert.bad{background:#feeceb;border-color:#f0b8b2;color:#9a1b16}.v3rm-actions{display:flex;flex-wrap:wrap;gap:8px;margin-top:12px}.btn{display:inline-flex;align-items:center;justify-content:center;border:0;border-radius:4px;background:#586482;color:#fff;text-decoration:none;padding:10px 14px;font-weight:800}.btn.primary{background:#4f5fad}.btn:hover{text-decoration:none;filter:brightness(.97)}
</style>

<section class="v3rm-panel">
    <h1>V3 Real-Mail Intake + Queue Health</h1>
    <p class="v3rm-muted">Closed-gate observation board for the next real Bolt pre-ride email. It reads the V3 queue and live gate config only.</p>
    <div class="v3rm-badges">
        <span class="v3rm-badge">READ ONLY</span>
        <span class="v3rm-badge">NO DB WRITES</span>
        <span class="v3rm-badge">NO EDXEIX CALL</span>
        <span class="v3rm-badge">NO AADE CALL</span>
        <span class="v3rm-badge <?= !empty($summary['live_risk_detected']) ? 'bad' : '' ?>">Live risk: <?= !empty($summary['live_risk_detected']) ? 'yes' : 'no' ?></span>
        <span class="v3rm-badge warn"><?= opsui_h((string)($report['version'] ?? 'v3.1.0')) ?></span>
    </div>
    <div class="v3rm-actions">
        <a class="btn primary" href="/ops/pre-ride-email-v3-live-operator-console.php">Live Operator Console</a>
        <a class="btn" href="/ops/pre-ride-email-v3-pre-live-switchboard.php">Pre-Live Switchboard</a>
        <a class="btn" href="/ops/pre-ride-email-v3-live-gate-drift-guard.php">Live Gate Drift Guard</a>
        <a class="btn" href="/ops/pre-ride-email-v3-dashboard.php">V3 Control Center</a>
    </div>
</section>

<section class="v3rm-grid">
    <div class="v3rm-card"><h3><?= opsui_h((string)($summary['total_rows'] ?? 0)) ?></h3><p>Total queue rows</p></div>
    <div class="v3rm-card"><h3><?= opsui_h((string)($summary['possible_real_mail_recent_count'] ?? 0)) ?></h3><p>Possible real-mail rows in latest 25</p></div>
    <div class="v3rm-card"><h3><?= opsui_h((string)($summary['canary_recent_count'] ?? 0)) ?></h3><p>Canary rows in latest 25</p></div>
    <div class="v3rm-card"><h3><?= opsui_h((string)($summary['future_active_rows'] ?? 0)) ?></h3><p>Future active rows</p></div>
    <div class="v3rm-card"><h3><?= opsui_h((string)($summary['live_submit_ready'] ?? 0)) ?></h3><p>Live-submit-ready rows</p></div>
    <div class="v3rm-card"><h3><?= opsui_h((string)($summary['past_ready_rows'] ?? 0)) ?></h3><p>Past ready rows</p></div>
    <div class="v3rm-card"><h3><?= opsui_h((string)($summary['stale_locked_rows'] ?? 0)) ?></h3><p>Stale locked rows</p></div>
    <div class="v3rm-card"><h3><?= opsui_h((string)($summary['missing_required_field_rows'] ?? 0)) ?></h3><p>Missing-field rows</p></div>
</section>

<?php foreach ($finalBlocks as $block): ?>
    <div class="v3rm-alert bad"><strong>Final block:</strong> <?= opsui_h((string)$block) ?></div>
<?php endforeach; ?>
<?php foreach ($warnings as $warning): ?>
    <div class="v3rm-alert"><strong>Warning:</strong> <?= opsui_h((string)$warning) ?></div>
<?php endforeach; ?>

<section class="v3rm-panel">
    <h2>Closed live gate posture</h2>
    <table class="v3rm-table">
        <tbody>
            <tr><th>Config loaded</th><td><?= !empty($gate['loaded']) ? 'yes' : 'no' ?></td></tr>
            <tr><th>Enabled</th><td><?= !empty($gate['enabled']) ? 'true' : 'false' ?></td></tr>
            <tr><th>Mode</th><td><code class="v3rm-code"><?= opsui_h((string)($gate['mode'] ?? '')) ?></code></td></tr>
            <tr><th>Adapter</th><td><code class="v3rm-code"><?= opsui_h((string)($gate['adapter'] ?? '')) ?></code></td></tr>
            <tr><th>Hard enable</th><td><?= !empty($gate['hard_enable_live_submit']) ? 'true' : 'false' ?></td></tr>
            <tr><th>Expected closed pre-live posture</th><td><?= !empty($gate['expected_closed_pre_live_posture']) ? 'yes' : 'no' ?></td></tr>
            <tr><th>Live risk detected</th><td><?= !empty($gate['live_risk_detected']) ? 'yes' : 'no' ?></td></tr>
        </tbody>
    </table>
</section>

<section class="v3rm-panel">
    <h2>Status counts</h2>
    <table class="v3rm-table">
        <thead><tr><th>Status</th><th>Rows</th></tr></thead>
        <tbody>
        <?php foreach ($statusCounts as $status => $count): ?>
            <tr><td><code class="v3rm-code"><?= opsui_h((string)$status) ?></code></td><td><?= opsui_h((string)$count) ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$statusCounts): ?><tr><td colspan="2">No status counts available.</td></tr><?php endif; ?>
        </tbody>
    </table>
</section>

<section class="v3rm-panel">
    <h2>Latest row classification</h2>
    <table class="v3rm-table">
        <thead><tr><th>ID</th><th>Status</th><th>Customer</th><th>Pickup</th><th>Source mtime</th><th>Canary</th><th>Possible real mail</th><th>Error</th></tr></thead>
        <tbody>
        <?php foreach (array_slice($latest, 0, 15) as $row): ?>
            <?php if (!is_array($row)) { continue; } ?>
            <tr>
                <td><?= opsui_h((string)($row['id'] ?? '')) ?></td>
                <td><code class="v3rm-code"><?= opsui_h((string)($row['queue_status'] ?? '')) ?></code></td>
                <td><?= opsui_h((string)($row['customer_name'] ?? '')) ?></td>
                <td><?= opsui_h((string)($row['pickup_datetime'] ?? '')) ?></td>
                <td><?= opsui_h((string)($row['source_mtime'] ?? '')) ?></td>
                <td><?= !empty($row['is_canary']) ? 'yes' : 'no' ?></td>
                <td><?= !empty($row['possible_real_mail']) ? 'yes' : 'no' ?></td>
                <td><?= opsui_h((string)($row['last_error'] ?? '')) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$latest): ?><tr><td colspan="8">No recent queue rows available.</td></tr><?php endif; ?>
        </tbody>
    </table>
</section>

<section class="v3rm-panel">
    <h2>Next safe action</h2>
    <p><?= opsui_h((string)($report['recommended_next_step'] ?? 'Observe next real Bolt pre-ride email.')) ?></p>
    <p class="v3rm-muted">This page intentionally does not approve live EDXEIX submission.</p>
</section>

<?php opsui_shell_end(); ?>
