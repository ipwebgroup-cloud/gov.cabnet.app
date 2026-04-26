<?php
declare(strict_types=1);
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Robots-Tag: noindex,nofollow', true);

require_once '/home/cabnet/gov.cabnet.app_app/lib/bolt_sync_lib.php';

function jbc_h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function jbc_badge(string $text, string $type='neutral'): string { return '<span class="badge badge-' . jbc_h($type) . '">' . jbc_h($text) . '</span>'; }
function jbc_val(array $row, array $keys, $default='') { foreach ($keys as $k) { if (array_key_exists($k,$row) && $row[$k] !== null && $row[$k] !== '') return $row[$k]; } return $default; }
function jbc_order_col(array $cols): string { foreach (['updated_at','created_at','queued_at','id'] as $c) if (isset($cols[$c])) return $c; return array_key_first($cols) ?: 'id'; }
function jbc_recent(mysqli $db, string $table, int $limit): array {
    if (!gov_bridge_table_exists($db, $table)) return [];
    $cols = gov_bridge_table_columns($db, $table);
    if (!$cols) return [];
    $order = jbc_order_col($cols);
    return gov_bridge_fetch_all($db, 'SELECT * FROM ' . gov_bridge_quote_identifier($table) . ' ORDER BY ' . gov_bridge_quote_identifier($order) . ' DESC LIMIT ' . (int)$limit);
}
function jbc_type(string $s): string {
    $x = strtolower($s);
    if (strpos($x,'success') !== false || strpos($x,'done') !== false || strpos($x,'sent') !== false) return 'good';
    if (strpos($x,'fail') !== false || strpos($x,'error') !== false || strpos($x,'blocked') !== false) return 'bad';
    if (strpos($x,'queued') !== false || strpos($x,'pending') !== false || strpos($x,'staged') !== false) return 'warn';
    return 'neutral';
}
$state = ['ok'=>false,'error'=>null,'jobs'=>[],'attempts'=>[],'status_counts'=>[],'jobs_table'=>false,'attempts_table'=>false];
try {
    $config = gov_bridge_load_config();
    if (!empty($config['app']['timezone'])) date_default_timezone_set((string)$config['app']['timezone']);
    $limit = max(1, min(200, (int)($_GET['limit'] ?? 50)));
    $db = gov_bridge_db();
    $state['jobs_table'] = gov_bridge_table_exists($db, 'submission_jobs');
    $state['attempts_table'] = gov_bridge_table_exists($db, 'submission_attempts');
    $state['jobs'] = jbc_recent($db, 'submission_jobs', $limit);
    $state['attempts'] = jbc_recent($db, 'submission_attempts', $limit);
    foreach ($state['jobs'] as $job) { $s=(string)jbc_val($job,['status','state','job_status'],'unknown'); $state['status_counts'][$s] = ($state['status_counts'][$s] ?? 0) + 1; }
    $state['ok'] = true;
} catch (Throwable $e) { $state['error'] = $e->getMessage(); }
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Jobs Review | gov.cabnet.app</title>
    <link rel="stylesheet" href="/assets/css/gov-ops-edxeix.css?v=2.0">
</head>
<body>
<div class="gov-topbar">
    <div class="gov-brand">
        <div class="gov-brand-crest">ΕΔ</div>
        <div class="gov-brand-text">
            <strong>gov.cabnet.app</strong>
            <span>Bolt → EDXEIX operational console</span>
        </div>
    </div>
    <div class="gov-top-links">
        <a href="/ops/index.php">Αρχική</a>
        <a href="/ops/admin-control.php">Administration</a>
        <a href="/ops/test-session.php">Test Session</a>
        <a href="/ops/preflight-review.php">Preflight Review</a>
        <a class="gov-logout" href="/ops/index.php">Safe Ops</a>
    </div>
</div>
<div class="gov-shell">
    <aside class="gov-sidebar">
        <h3>Jobs Review</h3>
        <p>Read-only local jobs and attempts visibility.</p>
        <div class="gov-side-group"><div class="gov-side-group-title">Administration</div><a class="gov-side-link" href="/ops/admin-control.php">Admin Control</a><a class="gov-side-link" href="/ops/readiness-control.php">Readiness Control</a><a class="gov-side-link" href="/ops/mapping-control.php">Mapping Review</a><a class="gov-side-link active" href="/ops/jobs-control.php">Jobs Review</a><div class="gov-side-group-title">Workflow</div><a class="gov-side-link" href="/ops/test-session.php">Test Session</a><a class="gov-side-link" href="/ops/dev-accelerator.php">Dev Accelerator</a><a class="gov-side-link" href="/ops/preflight-review.php">Preflight Review</a><a class="gov-side-link" href="/ops/evidence-bundle.php">Evidence Bundle</a><a class="gov-side-link" href="/ops/evidence-report.php">Evidence Report</a></div>
        <div class="gov-side-note">Read-only admin companion pages. Original operational pages remain available and unchanged.</div>
    </aside>
    <div class="gov-content">
        <div class="gov-page-header">
            <div>
                <h1 class="gov-page-title">Επισκόπηση ουράς εργασιών</h1>
                <div class="gov-breadcrumbs">Αρχική / Διαχειριστικό / Επισκόπηση ουράς εργασιών</div>
            </div>
            <div class="gov-tabs"><a class="gov-tab active" href="/ops/jobs-control.php">Jobs Review</a><a class="gov-tab" href="/ops/jobs.php">Original Jobs</a><a class="gov-tab" href="/bolt_jobs_queue.php?limit=50">Jobs JSON</a></div>
        </div>
        <main class="wrap wrap-shell">

<section class="safety">
    <strong>READ-ONLY JOBS REVIEW.</strong>
    This companion page does not create jobs, does not stage jobs, and does not call EDXEIX.
</section>

<section class="card hero">
    <h1>EDXEIX Jobs Review</h1>
    <?php if (!$state['ok']): ?>
        <p class="badline"><strong>Error:</strong> <?= jbc_h($state['error']) ?></p>
    <?php else: ?>
        <p>Read-only visibility for local submission jobs and attempts.</p>
        <div class="grid">
            <div class="metric"><strong><?= count($state['jobs']) ?></strong><span>Jobs shown</span></div>
            <div class="metric"><strong><?= count($state['attempts']) ?></strong><span>Attempts shown</span></div>
            <div class="metric"><strong><?= $state['jobs_table'] ? 'yes' : 'no' ?></strong><span>submission_jobs table</span></div>
            <div class="metric"><strong><?= $state['attempts_table'] ? 'yes' : 'no' ?></strong><span>submission_attempts table</span></div>
        </div>
        <div class="actions">
            <a class="btn" href="/ops/jobs.php">Open Original Jobs Page</a>
            <a class="btn dark" href="/bolt_jobs_queue.php?limit=50">Open Jobs JSON</a>
            <a class="btn warn" href="/ops/preflight-review.php">Preflight Review</a>
        </div>
    <?php endif; ?>
</section>

<?php if ($state['ok']): ?>
<section class="card">
    <h2>Queue Status Counts</h2>
    <?php if (!$state['status_counts']): ?>
        <p>No local submission jobs are currently staged.</p>
    <?php else: ?>
        <div class="actions">
            <?php foreach ($state['status_counts'] as $status => $count): ?>
                <?= jbc_badge((string)$status . ': ' . (string)$count, jbc_type((string)$status)) ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section class="card">
    <h2>Local Submission Jobs</h2>
    <?php if (!$state['jobs']): ?>
        <p>No local submission jobs are currently staged.</p>
    <?php else: ?>
        <div class="table-wrap"><table>
            <thead><tr><th>ID</th><th>Status</th><th>Source</th><th>Order Ref</th><th>Booking ID</th><th>Driver</th><th>Vehicle</th><th>Queued</th><th>Updated</th><th>Hash</th></tr></thead>
            <tbody>
            <?php foreach ($state['jobs'] as $job): $status=(string)jbc_val($job,['status','state','job_status'],'unknown'); ?>
                <tr>
                    <td><?= jbc_h($job['id'] ?? '') ?></td>
                    <td><?= jbc_badge($status, jbc_type($status)) ?></td>
                    <td><?= jbc_h(jbc_val($job,['source_system','source_type'],'')) ?></td>
                    <td><code><?= jbc_h(jbc_val($job,['order_reference','external_order_id'],'')) ?></code></td>
                    <td><?= jbc_h(jbc_val($job,['normalized_booking_id','booking_id'],'')) ?></td>
                    <td><?= jbc_h(jbc_val($job,['edxeix_driver_id','driver_id'],'')) ?></td>
                    <td><?= jbc_h(jbc_val($job,['edxeix_vehicle_id','vehicle_id'],'')) ?></td>
                    <td><?= jbc_h(jbc_val($job,['queued_at','created_at'],'')) ?></td>
                    <td><?= jbc_h(jbc_val($job,['updated_at'],'')) ?></td>
                    <td><?= jbc_h(substr((string)jbc_val($job,['payload_hash','dedupe_hash'],''),0,16)) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    <?php endif; ?>
</section>

<section class="card">
    <h2>Recent Attempts</h2>
    <?php if (!$state['attempts']): ?>
        <p>No submission attempts recorded. This remains expected while live EDXEIX submit is disabled.</p>
    <?php else: ?>
        <div class="table-wrap"><table>
            <thead><tr><th>ID</th><th>Job ID</th><th>Status</th><th>HTTP</th><th>Created</th><th>Message</th></tr></thead>
            <tbody>
            <?php foreach ($state['attempts'] as $attempt): ?>
                <tr>
                    <td><?= jbc_h($attempt['id'] ?? '') ?></td>
                    <td><?= jbc_h(jbc_val($attempt,['submission_job_id','job_id'],'')) ?></td>
                    <td><?= jbc_h(jbc_val($attempt,['status','state','result'],'')) ?></td>
                    <td><?= jbc_h(jbc_val($attempt,['http_status','status_code'],'')) ?></td>
                    <td><?= jbc_h(jbc_val($attempt,['created_at','attempted_at'],'')) ?></td>
                    <td><?= jbc_h(jbc_val($attempt,['message','error','response_summary'],'')) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    <?php endif; ?>
</section>
<?php endif; ?>
        </main>
    </div>
</div>
</body>
</html>
