<?php
/**
 * gov.cabnet.app — V3 Real Future Candidate Capture Readiness.
 *
 * v3.2.0:
 * - Read-only board for real future possible-real pre-ride candidate capture readiness.
 * - Shows minutes until pickup, completeness, missing fields, closed-gate review qualification,
 *   urgency/expiry posture, and operator-alert suitability.
 * - Does not call Bolt, EDXEIX, AADE, or mutate DB/queue/files.
 *
 * v3.2.1:
 * - Adds compact operator watch snapshot and fixes Latest rows table column alignment.
 */

declare(strict_types=1);

require_once __DIR__ . '/_shell.php';
require_once '/home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_real_future_candidate_capture_readiness.php';

$report = gov_v3_real_future_candidate_capture_readiness_run();
$watchSnapshot = gov_v3rfccr_watch_snapshot($report);
$watchCounts = is_array($watchSnapshot['counts'] ?? null) ? $watchSnapshot['counts'] : [];
$watchNext = is_array($watchSnapshot['next_candidate'] ?? null) ? $watchSnapshot['next_candidate'] : null;
$summary = is_array($report['summary'] ?? null) ? $report['summary'] : [];
$queue = is_array($report['queue'] ?? null) ? $report['queue'] : [];
$nextCandidate = is_array($queue['next_future_possible_real_row'] ?? null) ? $queue['next_future_possible_real_row'] : null;
$reviewRows = is_array($queue['closed_gate_operator_review_rows'] ?? null) ? $queue['closed_gate_operator_review_rows'] : [];
$alertRows = is_array($queue['operator_alert_rows'] ?? null) ? $queue['operator_alert_rows'] : [];
$futureRows = is_array($queue['future_possible_real_rows'] ?? null) ? $queue['future_possible_real_rows'] : [];
$latestRows = is_array($queue['latest_rows'] ?? null) ? $queue['latest_rows'] : [];
$warnings = is_array($report['warnings'] ?? null) ? $report['warnings'] : [];
$finalBlocks = is_array($report['final_blocks'] ?? null) ? $report['final_blocks'] : [];

function v3rfccr_bool_label(bool $value): string
{
    return $value ? 'yes' : 'no';
}

function v3rfccr_missing_label(array $row): string
{
    $missing = is_array($row['missing_required_fields'] ?? null) ? $row['missing_required_fields'] : [];
    return $missing ? implode(', ', array_map('strval', $missing)) : 'none';
}

opsui_shell_begin([
    'title' => 'V3 Real Future Candidate Capture Readiness',
    'page_title' => 'V3 Real Future Candidate Capture Readiness',
    'subtitle' => 'Safe real-mail capture readiness before pickup expiry',
    'breadcrumbs' => 'Αρχική / Διαχειριστικό / V3 Real Future Candidate Capture Readiness',
    'active_section' => 'V3 proof & readiness',
    'force_safe_notice' => true,
    'safe_notice' => 'READ-ONLY V3 CAPTURE READINESS. No Bolt, EDXEIX, AADE, DB write, queue mutation, route move, route delete, redirect, or live-submit action.',
]);
?>
<style>
.v3cap-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:14px;margin:16px 0}.v3cap-card{background:#fff;border:1px solid #d8dde7;border-radius:8px;padding:16px}.v3cap-card h3{margin:0 0 8px;color:#17386f;font-size:28px}.v3cap-muted{color:#55637f}.v3cap-badges{display:flex;flex-wrap:wrap;gap:8px;margin:12px 0}.v3cap-badge{display:inline-flex;align-items:center;border-radius:999px;padding:5px 9px;font-size:12px;font-weight:800;background:#e8f6ea;color:#176b24;border:1px solid #c8e9cc}.v3cap-badge.warn{background:#fff4dd;color:#885b00;border-color:#f0d49a}.v3cap-badge.bad{background:#feeceb;color:#9d241d;border-color:#f3b8b4}.v3cap-table{width:100%;border-collapse:collapse;background:#fff;border:1px solid #d8dde7;border-radius:8px;overflow:hidden}.v3cap-table th,.v3cap-table td{padding:10px;border-bottom:1px solid #e5e9f1;text-align:left;vertical-align:top}.v3cap-table th{background:#f6f8fb;color:#1d3764}.v3cap-code{display:inline-block;background:#f4f6fa;border:1px solid #d8dde7;border-radius:5px;padding:2px 5px;font-family:ui-monospace,SFMono-Regular,Consolas,monospace;max-width:100%;overflow-wrap:anywhere}.v3cap-warning{background:#fff4dd;border:1px solid #f0d49a;color:#704a00;border-radius:8px;padding:12px;margin:10px 0}.v3cap-block{background:#feeceb;border:1px solid #f3b8b4;color:#7e211b;border-radius:8px;padding:12px;margin:10px 0}.v3cap-actions{display:flex;flex-wrap:wrap;gap:8px;margin-top:12px}.btn{display:inline-flex;align-items:center;justify-content:center;border:0;border-radius:4px;background:#586482;color:#fff;text-decoration:none;padding:10px 14px;font-weight:800}.btn.primary{background:#4f5fad}.btn:hover{text-decoration:none;filter:brightness(.97)}.v3cap-next{background:#f7fbff;border:1px solid #cfe1ff;border-radius:10px;padding:16px;margin:16px 0}.v3cap-next strong{color:#17386f}.v3cap-small{font-size:12px;color:#55637f}.v3cap-scroll{overflow:auto}.v3cap-watch{background:#fbfcff;border:1px solid #cfd8ea;border-radius:10px;padding:16px;margin:16px 0}.v3cap-watch h2{margin-top:0}.v3cap-watch .status{font-size:18px;font-weight:900;color:#17386f}.v3cap-watch .status.urgent{color:#9d241d}.v3cap-watch .status.warning{color:#885b00}.v3cap-watch pre{white-space:pre-wrap;overflow-wrap:anywhere;background:#f4f6fa;border:1px solid #d8dde7;border-radius:8px;padding:10px}.v3cap-scroll{overflow:auto}
</style>

<section class="panel">
    <h1>V3 Real Future Candidate Capture Readiness</h1>
    <p>Detects whether a new possible-real future row exists and whether it is complete enough for closed-gate operator review before pickup expiry. This page is read-only and does not submit anything.</p>
    <div class="v3cap-badges">
        <span class="v3cap-badge">READ ONLY</span>
        <span class="v3cap-badge">NO DB WRITES</span>
        <span class="v3cap-badge">NO QUEUE MUTATION</span>
        <span class="v3cap-badge">NO BOLT CALL</span>
        <span class="v3cap-badge">NO EDXEIX CALL</span>
        <span class="v3cap-badge">NO AADE CALL</span>
        <span class="v3cap-badge warn"><?= opsui_h((string)($report['version'] ?? 'v3.2.1')) ?></span>
    </div>
    <div class="v3cap-actions">
        <a class="btn primary" href="/ops/pre-ride-email-v3-next-real-mail-candidate-watch.php">Next Candidate Watch</a>
        <a class="btn" href="/ops/pre-ride-email-v3-observation-overview.php">Observation Overview</a>
        <a class="btn" href="/ops/pre-ride-email-v3-live-operator-console.php">Live Operator Console</a>
        <a class="btn" href="/ops/pre-ride-email-v3-live-gate-drift-guard.php">Live Gate Drift Guard</a>
    </div>
</section>

<section class="v3cap-grid">
    <div class="v3cap-card"><h3><?= opsui_h((string)($summary['future_possible_real_rows'] ?? 0)) ?></h3><p class="v3cap-muted">future possible-real rows</p></div>
    <div class="v3cap-card"><h3><?= opsui_h((string)($summary['complete_future_possible_real_rows'] ?? 0)) ?></h3><p class="v3cap-muted">complete future rows</p></div>
    <div class="v3cap-card"><h3><?= opsui_h((string)($summary['closed_gate_operator_review_candidates'] ?? 0)) ?></h3><p class="v3cap-muted">closed-gate review candidates</p></div>
    <div class="v3cap-card"><h3><?= opsui_h((string)($summary['operator_alerts_appropriate'] ?? 0)) ?></h3><p class="v3cap-muted">operator alerts appropriate</p></div>
    <div class="v3cap-card"><h3><?= opsui_h((string)($summary['urgent_or_about_to_expire_rows'] ?? 0)) ?></h3><p class="v3cap-muted">urgent/about-to-expire rows</p></div>
    <div class="v3cap-card"><h3><?= !empty($summary['live_risk_detected']) ? 'yes' : 'no' ?></h3><p class="v3cap-muted">live risk detected</p></div>
</section>

<section class="v3cap-watch">
    <h2>Operator Watch Snapshot</h2>
    <p class="status <?= opsui_h((string)($watchSnapshot['severity'] ?? 'clear')) ?>"><?= opsui_h((string)($watchSnapshot['action_code'] ?? 'UNKNOWN')) ?></p>
    <p><?= opsui_h((string)($watchSnapshot['action_label'] ?? 'No watch status available.')) ?></p>
    <div class="v3cap-grid">
        <div class="v3cap-card"><h3><?= opsui_h((string)($watchCounts['future_possible_real_rows'] ?? 0)) ?></h3><p class="v3cap-muted">watch future rows</p></div>
        <div class="v3cap-card"><h3><?= opsui_h((string)($watchCounts['closed_gate_operator_review_candidates'] ?? 0)) ?></h3><p class="v3cap-muted">watch review candidates</p></div>
        <div class="v3cap-card"><h3><?= opsui_h((string)($watchCounts['operator_alerts_appropriate'] ?? 0)) ?></h3><p class="v3cap-muted">watch alerts</p></div>
        <div class="v3cap-card"><h3><?= opsui_h((string)($watchCounts['urgent_or_about_to_expire_rows'] ?? 0)) ?></h3><p class="v3cap-muted">watch urgent rows</p></div>
    </div>
    <?php if ($watchNext): ?>
        <p><strong>Next candidate:</strong> queue <code class="v3cap-code"><?= opsui_h((string)($watchNext['id'] ?? '')) ?></code>, pickup <?= opsui_h((string)($watchNext['pickup_datetime'] ?? '')) ?>, <?= opsui_h((string)($watchNext['minutes_until_pickup_now'] ?? '')) ?> minutes, complete <?= !empty($watchNext['complete']) ? 'yes' : 'no' ?>.</p>
    <?php else: ?>
        <p>No next candidate is currently visible in the watch snapshot.</p>
    <?php endif; ?>
    <p><strong>Manual terminal watch:</strong></p>
    <pre>watch -n 30 '/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_real_future_candidate_capture_readiness.php --status-line'</pre>
    <p class="v3cap-small">This is manual terminal polling only. It does not create cron jobs, notifications, logs, DB writes, queue changes, or live submissions.</p>
</section>

<?php foreach ($warnings as $warning): ?>
    <div class="v3cap-warning"><?= opsui_h((string)$warning) ?></div>
<?php endforeach; ?>

<?php foreach ($finalBlocks as $block): ?>
    <div class="v3cap-block"><?= opsui_h((string)$block) ?></div>
<?php endforeach; ?>

<section class="panel">
    <h2>Next future possible-real row</h2>
    <?php if (!$nextCandidate): ?>
        <p>No real future possible-real row is visible right now. Continue observing until a real pre-ride email arrives before pickup expiry.</p>
    <?php else: ?>
        <div class="v3cap-next">
            <p><strong>Queue ID:</strong> <code class="v3cap-code"><?= opsui_h((string)($nextCandidate['id'] ?? '')) ?></code></p>
            <p><strong>Pickup:</strong> <?= opsui_h((string)($nextCandidate['pickup_datetime'] ?? '')) ?> — <strong><?= opsui_h((string)($nextCandidate['minutes_until_pickup_now'] ?? '')) ?> minutes</strong> until pickup now.</p>
            <p><strong>Completeness:</strong> <?= v3rfccr_bool_label(!empty($nextCandidate['complete'])) ?> · <strong>Missing fields:</strong> <?= opsui_h(v3rfccr_missing_label($nextCandidate)) ?></p>
            <p><strong>Closed-gate operator review:</strong> <?= v3rfccr_bool_label(!empty($nextCandidate['qualifies_for_closed_gate_operator_review'])) ?> · <strong>Operator alert:</strong> <?= v3rfccr_bool_label(!empty($nextCandidate['operator_alert_appropriate'])) ?> / <?= opsui_h((string)($nextCandidate['operator_alert_priority'] ?? 'none')) ?></p>
            <p><strong>Readiness:</strong> <code class="v3cap-code"><?= opsui_h((string)($nextCandidate['capture_readiness'] ?? '')) ?></code> · <strong>Reason:</strong> <code class="v3cap-code"><?= opsui_h((string)($nextCandidate['reason'] ?? '')) ?></code></p>
            <p class="v3cap-small"><?= opsui_h((string)($nextCandidate['safe_interpretation'] ?? 'Review only.')) ?></p>
        </div>
    <?php endif; ?>
</section>

<section class="panel">
    <h2>Closed-gate operator review candidates</h2>
    <div class="v3cap-scroll">
    <table class="v3cap-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Pickup / minutes</th>
                <th>Customer</th>
                <th>Driver / vehicle</th>
                <th>Mapping IDs</th>
                <th>Missing</th>
                <th>Alert</th>
            </tr>
        </thead>
        <tbody>
        <?php if (!$reviewRows): ?>
            <tr><td colspan="7">No complete future row currently qualifies for closed-gate operator review.</td></tr>
        <?php endif; ?>
        <?php foreach ($reviewRows as $row): ?>
            <?php if (!is_array($row)) { continue; } ?>
            <tr>
                <td><code class="v3cap-code"><?= opsui_h((string)($row['id'] ?? '')) ?></code></td>
                <td><?= opsui_h((string)($row['pickup_datetime'] ?? '')) ?><br><small><?= opsui_h((string)($row['minutes_until_pickup_now'] ?? '')) ?> min</small></td>
                <td><?= opsui_h((string)($row['customer_name'] ?? '')) ?><br><small><?= opsui_h((string)($row['customer_phone'] ?? '')) ?></small></td>
                <td><?= opsui_h((string)($row['driver_name'] ?? '')) ?><br><code class="v3cap-code"><?= opsui_h((string)($row['vehicle_plate'] ?? '')) ?></code></td>
                <td><small>lessor <?= opsui_h((string)($row['lessor_id'] ?? '')) ?><br>driver <?= opsui_h((string)($row['driver_id'] ?? '')) ?><br>vehicle <?= opsui_h((string)($row['vehicle_id'] ?? '')) ?><br>start <?= opsui_h((string)($row['starting_point_id'] ?? '')) ?></small></td>
                <td><small><?= opsui_h(v3rfccr_missing_label($row)) ?></small></td>
                <td><?= v3rfccr_bool_label(!empty($row['operator_alert_appropriate'])) ?><br><code class="v3cap-code"><?= opsui_h((string)($row['operator_alert_priority'] ?? 'none')) ?></code></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</section>

<section class="panel">
    <h2>Operator alert rows</h2>
    <div class="v3cap-scroll">
    <table class="v3cap-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Readiness</th>
                <th>Pickup</th>
                <th>Complete</th>
                <th>Missing fields</th>
                <th>Reason</th>
                <th>Priority</th>
            </tr>
        </thead>
        <tbody>
        <?php if (!$alertRows): ?>
            <tr><td colspan="7">No operator alert is appropriate right now.</td></tr>
        <?php endif; ?>
        <?php foreach ($alertRows as $row): ?>
            <?php if (!is_array($row)) { continue; } ?>
            <tr>
                <td><code class="v3cap-code"><?= opsui_h((string)($row['id'] ?? '')) ?></code></td>
                <td><code class="v3cap-code"><?= opsui_h((string)($row['capture_readiness'] ?? '')) ?></code></td>
                <td><?= opsui_h((string)($row['pickup_datetime'] ?? '')) ?><br><small><?= opsui_h((string)($row['minutes_until_pickup_now'] ?? '')) ?> min</small></td>
                <td><?= v3rfccr_bool_label(!empty($row['complete'])) ?></td>
                <td><small><?= opsui_h(v3rfccr_missing_label($row)) ?></small></td>
                <td><code class="v3cap-code"><?= opsui_h((string)($row['reason'] ?? '')) ?></code></td>
                <td><code class="v3cap-code"><?= opsui_h((string)($row['operator_alert_priority'] ?? 'none')) ?></code></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</section>

<section class="panel">
    <h2>Future possible-real rows</h2>
    <div class="v3cap-scroll">
    <table class="v3cap-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Status</th>
                <th>Pickup / minutes</th>
                <th>Customer</th>
                <th>Complete</th>
                <th>Missing fields</th>
                <th>Closed-gate review</th>
                <th>Reason</th>
            </tr>
        </thead>
        <tbody>
        <?php if (!$futureRows): ?>
            <tr><td colspan="8">No future possible-real rows found.</td></tr>
        <?php endif; ?>
        <?php foreach ($futureRows as $row): ?>
            <?php if (!is_array($row)) { continue; } ?>
            <tr>
                <td><code class="v3cap-code"><?= opsui_h((string)($row['id'] ?? '')) ?></code></td>
                <td><?= opsui_h((string)($row['queue_status'] ?? '')) ?></td>
                <td><?= opsui_h((string)($row['pickup_datetime'] ?? '')) ?><br><small><?= opsui_h((string)($row['minutes_until_pickup_now'] ?? '')) ?> min</small></td>
                <td><?= opsui_h((string)($row['customer_name'] ?? '')) ?></td>
                <td><?= v3rfccr_bool_label(!empty($row['complete'])) ?></td>
                <td><small><?= opsui_h(v3rfccr_missing_label($row)) ?></small></td>
                <td><?= v3rfccr_bool_label(!empty($row['qualifies_for_closed_gate_operator_review'])) ?></td>
                <td><code class="v3cap-code"><?= opsui_h((string)($row['reason'] ?? '')) ?></code></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</section>

<section class="panel">
    <h2>Latest rows scanned</h2>
    <div class="v3cap-scroll">
    <table class="v3cap-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Possible real</th>
                <th>Pickup</th>
                <th>Readiness</th>
                <th>Missing</th>
                <th>Reason</th>
                <th>Last error</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach (array_slice($latestRows, 0, 15) as $row): ?>
            <?php if (!is_array($row)) { continue; } ?>
            <tr>
                <td><code class="v3cap-code"><?= opsui_h((string)($row['id'] ?? '')) ?></code></td>
                <td><?= v3rfccr_bool_label(!empty($row['possible_real_mail'])) ?></td>
                <td><?= opsui_h((string)($row['pickup_datetime'] ?? '')) ?><br><small><?= opsui_h((string)($row['minutes_until_pickup_now'] ?? '')) ?> min</small></td>
                <td><code class="v3cap-code"><?= opsui_h((string)($row['capture_readiness'] ?? '')) ?></code></td>
                <td><small><?= opsui_h(v3rfccr_missing_label($row)) ?></small></td>
                <td><code class="v3cap-code"><?= opsui_h((string)($row['reason'] ?? '')) ?></code></td>
                <td><small><?= opsui_h((string)($row['last_error'] ?? '')) ?></small></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</section>

<section class="panel">
    <h2>Safe conclusion</h2>
    <ol>
        <li>This tool is only a capture-readiness layer; it does not submit, approve, mutate, or write.</li>
        <li>If a row qualifies for closed-gate operator review, inspect it before pickup expiry while the live gate remains closed.</li>
        <li>If missing fields appear, correct the source/mapping path first; do not submit incomplete data.</li>
        <li>Live EDXEIX submission remains blocked until Andreas explicitly requests a live-submit update and all live gates pass.</li>
    </ol>
    <p><strong>Recommended next step:</strong> <?= opsui_h((string)($report['recommended_next_step'] ?? 'Continue observing.')) ?></p>
</section>

<?php opsui_shell_end(); ?>
