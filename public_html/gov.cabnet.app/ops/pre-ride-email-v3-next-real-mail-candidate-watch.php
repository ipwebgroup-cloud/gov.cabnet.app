<?php
/**
 * gov.cabnet.app — V3 Next Real-Mail Candidate Watch.
 *
 * v3.1.5:
 * - Read-only observation board for the next future possible-real V3 pre-ride queue row.
 * - Does not call Bolt, EDXEIX, AADE, or mutate DB/queue/files.
 */

declare(strict_types=1);

require_once __DIR__ . '/_shell.php';
require_once '/home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_next_real_mail_candidate_watch.php';

$report = gov_v3_next_real_mail_candidate_watch_run();
$summary = is_array($report['summary'] ?? null) ? $report['summary'] : [];
$queue = is_array($report['queue'] ?? null) ? $report['queue'] : [];
$candidates = is_array($queue['candidate_rows'] ?? null) ? $queue['candidate_rows'] : [];
$latestRows = is_array($queue['latest_rows'] ?? null) ? $queue['latest_rows'] : [];
$warnings = is_array($report['warnings'] ?? null) ? $report['warnings'] : [];
$finalBlocks = is_array($report['final_blocks'] ?? null) ? $report['final_blocks'] : [];

opsui_shell_begin([
    'title' => 'V3 Next Real-Mail Candidate Watch',
    'page_title' => 'V3 Next Real-Mail Candidate Watch',
    'subtitle' => 'Safe Bolt → EDXEIX closed-gate monitoring',
    'breadcrumbs' => 'Αρχική / Διαχειριστικό / V3 Next Real-Mail Candidate Watch',
    'active_section' => 'V3 proof & readiness',
    'force_safe_notice' => true,
    'safe_notice' => 'READ-ONLY V3 WATCH. No Bolt, EDXEIX, AADE, DB write, queue mutation, route move, route delete, redirect, or live-submit action.',
]);
?>
<style>
.nrm-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:14px;margin:16px 0}.nrm-card{background:#fff;border:1px solid #d8dde7;border-radius:8px;padding:16px}.nrm-card h3{margin:0 0 8px;color:#17386f;font-size:28px}.nrm-muted{color:#55637f}.nrm-badges{display:flex;flex-wrap:wrap;gap:8px;margin:12px 0}.nrm-badge{display:inline-flex;align-items:center;border-radius:999px;padding:5px 9px;font-size:12px;font-weight:800;background:#e8f6ea;color:#176b24;border:1px solid #c8e9cc}.nrm-badge.warn{background:#fff4dd;color:#885b00;border-color:#f0d49a}.nrm-badge.bad{background:#feeceb;color:#9d241d;border-color:#f3b8b4}.nrm-table{width:100%;border-collapse:collapse;background:#fff;border:1px solid #d8dde7;border-radius:8px;overflow:hidden}.nrm-table th,.nrm-table td{padding:10px;border-bottom:1px solid #e5e9f1;text-align:left;vertical-align:top}.nrm-table th{background:#f6f8fb;color:#1d3764}.nrm-code{display:inline-block;background:#f4f6fa;border:1px solid #d8dde7;border-radius:5px;padding:2px 5px;font-family:ui-monospace,SFMono-Regular,Consolas,monospace}.nrm-warning{background:#fff4dd;border:1px solid #f0d49a;color:#704a00;border-radius:8px;padding:12px;margin:10px 0}.nrm-block{background:#feeceb;border:1px solid #f3b8b4;color:#7e211b;border-radius:8px;padding:12px;margin:10px 0}.nrm-actions{display:flex;flex-wrap:wrap;gap:8px;margin-top:12px}.btn{display:inline-flex;align-items:center;justify-content:center;border:0;border-radius:4px;background:#586482;color:#fff;text-decoration:none;padding:10px 14px;font-weight:800}.btn.primary{background:#4f5fad}.btn:hover{text-decoration:none;filter:brightness(.97)}
</style>

<section class="panel">
    <h1>V3 Next Real-Mail Candidate Watch</h1>
    <p>Watches for the next future possible-real V3 pre-ride row before it expires. This page is read-only and does not submit anything.</p>
    <div class="nrm-badges">
        <span class="nrm-badge">READ ONLY</span>
        <span class="nrm-badge">NO DB WRITES</span>
        <span class="nrm-badge">NO QUEUE MUTATION</span>
        <span class="nrm-badge">NO EDXEIX CALL</span>
        <span class="nrm-badge">NO AADE CALL</span>
        <span class="nrm-badge warn"><?= opsui_h((string)($report['version'] ?? 'v3.1.5')) ?></span>
    </div>
    <div class="nrm-actions">
        <a class="btn primary" href="/ops/pre-ride-email-v3-real-mail-queue-health.php">Real-Mail Queue Health</a>
        <a class="btn" href="/ops/pre-ride-email-v3-real-mail-expiry-reason-audit.php">Expiry Reason Audit</a>
        <a class="btn" href="/ops/pre-ride-email-v3-live-operator-console.php">Live Operator Console</a>
        <a class="btn" href="/ops/pre-ride-email-v3-live-gate-drift-guard.php">Live Gate Drift Guard</a>
    </div>
</section>

<section class="nrm-grid">
    <div class="nrm-card"><h3><?= opsui_h((string)($summary['possible_real_rows_scanned'] ?? 0)) ?></h3><p class="nrm-muted">possible-real rows scanned</p></div>
    <div class="nrm-card"><h3><?= opsui_h((string)($summary['future_possible_real_rows'] ?? 0)) ?></h3><p class="nrm-muted">future possible-real rows</p></div>
    <div class="nrm-card"><h3><?= opsui_h((string)($summary['operator_review_candidates'] ?? 0)) ?></h3><p class="nrm-muted">operator review candidates</p></div>
    <div class="nrm-card"><h3><?= opsui_h((string)($summary['urgent_operator_review_candidates'] ?? 0)) ?></h3><p class="nrm-muted">urgent candidates</p></div>
    <div class="nrm-card"><h3><?= !empty($summary['live_risk_detected']) ? 'yes' : 'no' ?></h3><p class="nrm-muted">live risk detected</p></div>
</section>

<?php foreach ($warnings as $warning): ?>
    <div class="nrm-warning"><?= opsui_h((string)$warning) ?></div>
<?php endforeach; ?>

<?php foreach ($finalBlocks as $block): ?>
    <div class="nrm-block"><?= opsui_h((string)$block) ?></div>
<?php endforeach; ?>

<section class="panel">
    <h2>Operator review candidates</h2>
    <table class="nrm-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Status</th>
                <th>Pickup</th>
                <th>Customer</th>
                <th>Driver / Vehicle</th>
                <th>Reason</th>
                <th>Safe interpretation</th>
            </tr>
        </thead>
        <tbody>
        <?php if (!$candidates): ?>
            <tr><td colspan="7">No future possible-real row currently qualifies for operator review.</td></tr>
        <?php endif; ?>
        <?php foreach ($candidates as $row): ?>
            <?php if (!is_array($row)) { continue; } ?>
            <tr>
                <td><code class="nrm-code"><?= opsui_h((string)($row['id'] ?? '')) ?></code></td>
                <td><?= opsui_h((string)($row['queue_status'] ?? '')) ?></td>
                <td><?= opsui_h((string)($row['pickup_datetime'] ?? '')) ?><br><small><?= opsui_h((string)($row['minutes_until_pickup_now'] ?? '')) ?> min until pickup</small></td>
                <td><?= opsui_h((string)($row['customer_name'] ?? '')) ?><br><small><?= opsui_h((string)($row['customer_phone'] ?? '')) ?></small></td>
                <td><?= opsui_h((string)($row['driver_name'] ?? '')) ?><br><code class="nrm-code"><?= opsui_h((string)($row['vehicle_plate'] ?? '')) ?></code></td>
                <td><code class="nrm-code"><?= opsui_h((string)($row['reason'] ?? '')) ?></code></td>
                <td><?= opsui_h((string)($row['safe_interpretation'] ?? 'Review only.')) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>

<section class="panel">
    <h2>Latest queue rows scanned</h2>
    <table class="nrm-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Class</th>
                <th>Pickup</th>
                <th>Customer</th>
                <th>Vehicle</th>
                <th>Reason</th>
                <th>Last error</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach (array_slice($latestRows, 0, 15) as $row): ?>
            <?php if (!is_array($row)) { continue; } ?>
            <tr>
                <td><code class="nrm-code"><?= opsui_h((string)($row['id'] ?? '')) ?></code></td>
                <td><code class="nrm-code"><?= opsui_h((string)($row['watch_classification'] ?? '')) ?></code></td>
                <td><?= opsui_h((string)($row['pickup_datetime'] ?? '')) ?><br><small><?= opsui_h((string)($row['minutes_until_pickup_now'] ?? '')) ?> min</small></td>
                <td><?= opsui_h((string)($row['customer_name'] ?? '')) ?></td>
                <td><code class="nrm-code"><?= opsui_h((string)($row['vehicle_plate'] ?? '')) ?></code></td>
                <td><code class="nrm-code"><?= opsui_h((string)($row['reason'] ?? '')) ?></code></td>
                <td><small><?= opsui_h((string)($row['last_error'] ?? '')) ?></small></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>

<section class="panel">
    <h2>Safe conclusion</h2>
    <ol>
        <li>This page only watches for a future possible-real row; it does not change queue status.</li>
        <li>If an operator review candidate appears, inspect the row with the existing closed-gate V3 tools before pickup expires.</li>
        <li>Live EDXEIX submission remains blocked by the master gate.</li>
        <li>Do not enable live submit until Andreas explicitly requests a live-submit update and all live gates pass.</li>
    </ol>
</section>

<?php opsui_shell_end(); ?>
