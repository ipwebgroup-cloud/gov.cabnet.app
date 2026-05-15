<?php
/**
 * gov.cabnet.app — V3 Real-Mail Expiry Reason Audit.
 *
 * v3.1.2:
 * - Read-only explanation board for blocked/expired V3 real-mail queue rows.
 * - Does not call Bolt, EDXEIX, AADE, or mutate DB/queue/files.
 */

declare(strict_types=1);

require_once __DIR__ . '/_shell.php';
require_once '/home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_real_mail_expiry_reason_audit.php';

$report = gov_v3_real_mail_expiry_reason_audit_run();
$summary = is_array($report['summary'] ?? null) ? $report['summary'] : [];
$queue = is_array($report['queue'] ?? null) ? $report['queue'] : [];
$rows = is_array($queue['rows'] ?? null) ? $queue['rows'] : [];
$warnings = is_array($report['warnings'] ?? null) ? $report['warnings'] : [];
$finalBlocks = is_array($report['final_blocks'] ?? null) ? $report['final_blocks'] : [];

opsui_shell_begin([
    'title' => 'V3 Real-Mail Expiry Reason Audit',
    'page_title' => 'V3 Real-Mail Expiry Reason Audit',
    'subtitle' => 'Safe Bolt → EDXEIX closed-gate monitoring',
    'breadcrumbs' => 'Αρχική / Διαχειριστικό / V3 Real-Mail Expiry Reason Audit',
    'active_section' => 'V3 proof & readiness',
    'force_safe_notice' => true,
    'safe_notice' => 'READ-ONLY V3 EXPIRY AUDIT. No Bolt, EDXEIX, AADE, DB write, queue mutation, route move, route delete, redirect, or live-submit action.',
]);
?>
<style>
.expiry-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:14px;margin:16px 0}.expiry-card{background:#fff;border:1px solid #d8dde7;border-radius:8px;padding:16px}.expiry-card h3{margin:0 0 8px;color:#17386f;font-size:28px}.expiry-muted{color:#55637f}.expiry-badges{display:flex;flex-wrap:wrap;gap:8px;margin:12px 0}.expiry-badge{display:inline-flex;align-items:center;border-radius:999px;padding:5px 9px;font-size:12px;font-weight:800;background:#e8f6ea;color:#176b24;border:1px solid #c8e9cc}.expiry-badge.warn{background:#fff4dd;color:#885b00;border-color:#f0d49a}.expiry-badge.bad{background:#feeceb;color:#9d241d;border-color:#f3b8b4}.expiry-table{width:100%;border-collapse:collapse;background:#fff;border:1px solid #d8dde7;border-radius:8px;overflow:hidden}.expiry-table th,.expiry-table td{padding:10px;border-bottom:1px solid #e5e9f1;text-align:left;vertical-align:top}.expiry-table th{background:#f6f8fb;color:#1d3764}.expiry-code{display:inline-block;background:#f4f6fa;border:1px solid #d8dde7;border-radius:5px;padding:2px 5px;font-family:ui-monospace,SFMono-Regular,Consolas,monospace}.expiry-warning{background:#fff4dd;border:1px solid #f0d49a;color:#704a00;border-radius:8px;padding:12px;margin:10px 0}.expiry-block{background:#feeceb;border:1px solid #f3b8b4;color:#7e211b;border-radius:8px;padding:12px;margin:10px 0}.expiry-actions{display:flex;flex-wrap:wrap;gap:8px;margin-top:12px}.btn{display:inline-flex;align-items:center;justify-content:center;border:0;border-radius:4px;background:#586482;color:#fff;text-decoration:none;padding:10px 14px;font-weight:800}.btn.primary{background:#4f5fad}.btn:hover{text-decoration:none;filter:brightness(.97)}
</style>

<section class="panel">
    <h1>V3 Real-Mail Expiry Reason Audit</h1>
    <p>Explains why current V3 queue rows are blocked/expired after the closed-gate real-mail observation. This page is read-only and does not submit anything.</p>
    <div class="expiry-badges">
        <span class="expiry-badge">READ ONLY</span>
        <span class="expiry-badge">NO DB WRITES</span>
        <span class="expiry-badge">NO QUEUE MUTATION</span>
        <span class="expiry-badge">NO EDXEIX CALL</span>
        <span class="expiry-badge">NO AADE CALL</span>
        <span class="expiry-badge warn"><?= opsui_h((string)($report['version'] ?? 'v3.1.2')) ?></span>
    </div>
    <div class="expiry-actions">
        <a class="btn primary" href="/ops/pre-ride-email-v3-real-mail-queue-health.php">Real-Mail Queue Health</a>
        <a class="btn" href="/ops/pre-ride-email-v3-live-operator-console.php">Live Operator Console</a>
        <a class="btn" href="/ops/pre-ride-email-v3-live-gate-drift-guard.php">Live Gate Drift Guard</a>
    </div>
</section>

<section class="expiry-grid">
    <div class="expiry-card"><h3><?= opsui_h((string)($summary['rows_reviewed'] ?? 0)) ?></h3><p class="expiry-muted">rows reviewed</p></div>
    <div class="expiry-card"><h3><?= opsui_h((string)($summary['expired_by_future_safety_guard'] ?? 0)) ?></h3><p class="expiry-muted">expired by V3 future-safety guard</p></div>
    <div class="expiry-card"><h3><?= opsui_h((string)($summary['possible_real_mail_expired_guard_rows'] ?? 0)) ?></h3><p class="expiry-muted">possible real-mail rows expired safely</p></div>
    <div class="expiry-card"><h3><?= !empty($summary['live_risk_detected']) ? 'yes' : 'no' ?></h3><p class="expiry-muted">live risk detected</p></div>
    <div class="expiry-card"><h3><?= opsui_h((string)($summary['live_submit_recommended_now'] ?? 0)) ?></h3><p class="expiry-muted">live submit recommended now</p></div>
</section>

<?php foreach ($warnings as $warning): ?>
    <div class="expiry-warning"><?= opsui_h((string)$warning) ?></div>
<?php endforeach; ?>

<?php foreach ($finalBlocks as $block): ?>
    <div class="expiry-block"><?= opsui_h((string)$block) ?></div>
<?php endforeach; ?>

<section class="panel">
    <h2>Blocked / expired queue rows</h2>
    <table class="expiry-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Status</th>
                <th>Pickup</th>
                <th>Customer</th>
                <th>Driver / Vehicle</th>
                <th>Classification</th>
                <th>Safe interpretation</th>
            </tr>
        </thead>
        <tbody>
        <?php if (!$rows): ?>
            <tr><td colspan="7">No blocked/error rows found in the read-only scan.</td></tr>
        <?php endif; ?>
        <?php foreach ($rows as $row): ?>
            <?php if (!is_array($row)) { continue; } ?>
            <tr>
                <td><code class="expiry-code"><?= opsui_h((string)($row['id'] ?? '')) ?></code></td>
                <td><?= opsui_h((string)($row['queue_status'] ?? '')) ?></td>
                <td><?= opsui_h((string)($row['pickup_datetime'] ?? '')) ?><br><small><?= opsui_h((string)($row['minutes_past_pickup_now'] ?? '')) ?> min past now</small></td>
                <td><?= opsui_h((string)($row['customer_name'] ?? '')) ?></td>
                <td><?= opsui_h((string)($row['driver_name'] ?? '')) ?><br><code class="expiry-code"><?= opsui_h((string)($row['vehicle_plate'] ?? '')) ?></code></td>
                <td><code class="expiry-code"><?= opsui_h((string)($row['classification'] ?? '')) ?></code></td>
                <td><?= opsui_h((string)($row['safe_interpretation'] ?? 'Review only.')) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>

<section class="panel">
    <h2>Safe conclusion</h2>
    <ol>
        <li>No live submission is recommended by this audit.</li>
        <li>Rows blocked by <code>v3_queue_row_expired_pickup_not_future_safe</code> are expected closed-gate behavior after pickup time passes.</li>
        <li>Future real-mail readiness must be observed before pickup expires; this tool does not change queue status.</li>
        <li>Keep V3 closed-gate until a real future trip is observed and all preflight gates pass.</li>
    </ol>
</section>

<?php opsui_shell_end(); ?>
