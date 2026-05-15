<?php
/**
 * gov.cabnet.app — Ops Jobs Queue
 *
 * Read-only GUI for local submission_jobs/submission_attempts.
 * Does not call EDXEIX and does not create jobs.
 */

declare(strict_types=1);

require_once '/home/cabnet/gov.cabnet.app_app/lib/bolt_sync_lib.php';

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function job_value(array $row, array $keys, $default = '')
{
    foreach ($keys as $key) {
        if (array_key_exists($key, $row) && $row[$key] !== null && $row[$key] !== '') {
            return $row[$key];
        }
    }
    return $default;
}

function job_order_column(array $columns): string
{
    foreach (['updated_at', 'created_at', 'queued_at', 'id'] as $column) {
        if (isset($columns[$column])) {
            return $column;
        }
    }
    return array_key_first($columns) ?: 'id';
}

function job_recent_table(mysqli $db, string $table, int $limit): array
{
    if (!gov_bridge_table_exists($db, $table)) {
        return [];
    }
    $columns = gov_bridge_table_columns($db, $table);
    if (!$columns) {
        return [];
    }
    $orderColumn = job_order_column($columns);
    $sql = 'SELECT * FROM ' . gov_bridge_quote_identifier($table) . ' ORDER BY ' . gov_bridge_quote_identifier($orderColumn) . ' DESC LIMIT ' . (int)$limit;
    return gov_bridge_fetch_all($db, $sql);
}

function job_payload(array $row): string
{
    $raw = job_value($row, ['edxeix_payload_json', 'payload_json', 'request_payload_json', 'payload', 'body'], '');
    if ($raw === '') {
        return '';
    }
    $decoded = is_string($raw) ? json_decode($raw, true) : null;
    if (is_array($decoded)) {
        return json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: (string)$raw;
    }
    return (string)$raw;
}

function status_badge_class(string $status): string
{
    $s = strtolower($status);
    if (strpos($s, 'staged') !== false || strpos($s, 'queued') !== false || strpos($s, 'pending') !== false) {
        return 'warn';
    }
    if (strpos($s, 'success') !== false || strpos($s, 'done') !== false || strpos($s, 'sent') !== false) {
        return 'ok';
    }
    if (strpos($s, 'fail') !== false || strpos($s, 'error') !== false || strpos($s, 'blocked') !== false) {
        return 'danger';
    }
    return 'neutral';
}

$state = [
    'ok' => false,
    'error' => null,
    'jobs' => [],
    'attempts' => [],
    'status_counts' => [],
    'jobs_table_exists' => false,
    'attempts_table_exists' => false,
];

try {
    $config = gov_bridge_load_config();
    if (!empty($config['app']['timezone'])) {
        date_default_timezone_set((string)$config['app']['timezone']);
    }
    $limit = gov_bridge_int_param('limit', 50, 1, 200);
    $db = gov_bridge_db();
    $state['jobs_table_exists'] = gov_bridge_table_exists($db, 'submission_jobs');
    $state['attempts_table_exists'] = gov_bridge_table_exists($db, 'submission_attempts');
    $state['jobs'] = job_recent_table($db, 'submission_jobs', $limit);
    $state['attempts'] = job_recent_table($db, 'submission_attempts', $limit);

    foreach ($state['jobs'] as $job) {
        $status = (string)job_value($job, ['status', 'state', 'job_status'], 'unknown');
        $state['status_counts'][$status] = ($state['status_counts'][$status] ?? 0) + 1;
    }
    $state['ok'] = true;
} catch (Throwable $e) {
    $state['error'] = $e->getMessage();
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>EDXEIX Jobs Queue | gov.cabnet.app</title>
    <style>
        :root { --bg:#f3f6fb; --card:#fff; --text:#0f172a; --muted:#475467; --line:#d8e2ef; --brand:#0f172a; --blue:#2563eb; --ok:#067647; --warn:#b54708; --danger:#b42318; }
        *{box-sizing:border-box} body{margin:0;background:var(--bg);color:var(--text);font-family:Arial,Helvetica,sans-serif;}
        header{background:#0b1220;color:#fff;padding:16px 28px;display:flex;gap:20px;align-items:center;flex-wrap:wrap;position:sticky;top:0;z-index:5;}
        header a{color:#fff;text-decoration:none;} header a:hover{text-decoration:underline;}
        main{max-width:1500px;margin:0 auto;padding:26px;}
        .card{background:var(--card);border:1px solid var(--line);border-radius:14px;padding:18px;margin-bottom:18px;box-shadow:0 8px 24px rgba(15,23,42,.04);} h1,h2{margin:0 0 12px;} p{color:var(--muted);}
        .toolbar{display:flex;gap:10px;flex-wrap:wrap;margin:16px 0 4px;} .btn{display:inline-block;border-radius:9px;background:var(--blue);color:#fff;text-decoration:none;padding:10px 14px;font-weight:700;} .btn.secondary{background:#344054}.btn.warn{background:var(--warn)}.btn.safe{background:var(--ok)}
        .stats{display:grid;grid-template-columns:repeat(4,minmax(150px,1fr));gap:12px;margin-top:14px}.stat{border:1px solid var(--line);border-radius:12px;padding:14px;background:#f8fafc}.stat strong{font-size:28px;display:block}.stat span{color:var(--muted);font-size:14px}
        .table-wrap{overflow:auto;border:1px solid var(--line);border-radius:12px;} table{width:100%;border-collapse:collapse;background:#fff;min-width:1120px;} th,td{text-align:left;padding:10px 12px;border-bottom:1px solid var(--line);vertical-align:top;font-size:14px;} th{background:#f8fafc;color:#475467;font-size:12px;text-transform:uppercase;letter-spacing:.03em;}
        .badge{display:inline-flex;align-items:center;border-radius:999px;padding:4px 8px;font-size:12px;font-weight:700;background:#eef2f6;color:#344054;white-space:nowrap}.badge.ok{background:#ecfdf3;color:var(--ok)}.badge.warn{background:#fffaeb;color:var(--warn)}.badge.danger{background:#fef3f2;color:var(--danger)}
        pre{overflow:auto;background:#0b1020;color:#d7e3ff;padding:14px;border-radius:12px;max-height:360px}.alert{border-left:5px solid var(--warn);background:#fff7ed;padding:12px 14px;border-radius:10px;margin-bottom:16px}.alert.ok{border-left-color:var(--ok);background:#ecfdf3}.alert.danger{border-left-color:var(--danger);background:#fef3f2}.muted{color:var(--muted)}
        @media (max-width:800px){.stats{grid-template-columns:1fr 1fr;}main{padding:14px;}header{padding:12px 16px;}}
    </style>
</head>
<body>
<header>
    <strong>GC gov.cabnet.app</strong>
    <a href="/ops/index.php">Operations Console</a>
    <a href="/ops/bolt-live.php">Bolt Live</a>
    <a href="/ops/jobs.php">Jobs Queue</a>
    <a href="/ops/readiness.php">Readiness</a>
    <a href="/bolt_edxeix_preflight.php?limit=30">EDXEIX Preflight JSON</a>
    <a href="/bolt_jobs_queue.php?limit=50">Jobs JSON</a>
</header>
<main>
    <section class="card">
        <h1>EDXEIX Jobs Queue</h1>
        <p>Read-only local queue viewer. This page does not call EDXEIX, does not post forms, and does not create jobs.</p>
        <div class="toolbar">
            <a class="btn" href="/ops/public-utility-relocation-plan.php">Legacy Stage Plan</a>
            <a class="btn secondary" href="/ops/public-route-exposure-audit.php">Public Route Audit</a>
            <a class="btn secondary" href="/ops/readiness.php">Open Readiness</a>
            <a class="btn warn" href="/bolt_edxeix_preflight.php?limit=30">Open Preflight JSON</a>
            <a class="btn safe" href="/bolt_jobs_queue.php?limit=50">Open Jobs JSON</a>
        </div>
        <?php if (!$state['ok']): ?>
            <div class="alert danger"><strong>Error:</strong> <?= h($state['error']) ?></div>
        <?php else: ?>
            <div class="stats">
                <div class="stat"><strong><?= count($state['jobs']) ?></strong><span>Local submission jobs shown</span></div>
                <div class="stat"><strong><?= count($state['attempts']) ?></strong><span>Recent attempts shown</span></div>
                <div class="stat"><strong><?= $state['jobs_table_exists'] ? 'yes' : 'no' ?></strong><span>submission_jobs table</span></div>
                <div class="stat"><strong><?= $state['attempts_table_exists'] ? 'yes' : 'no' ?></strong><span>submission_attempts table</span></div>
            </div>
        <?php endif; ?>
    </section>

    <section class="card">
        <h2>Queue Status Counts</h2>
        <?php if (!$state['status_counts']): ?>
            <p>No local submission jobs are currently staged.</p>
        <?php else: ?>
            <div class="toolbar">
                <?php foreach ($state['status_counts'] as $status => $count): ?>
                    <span class="badge <?= h(status_badge_class((string)$status)) ?>"><?= h($status) ?>: <?= (int)$count ?></span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="card">
        <h2>Local Submission Jobs</h2>
        <?php if (!$state['jobs']): ?>
            <p>No jobs yet. Use <strong>Stage Dry Run</strong> to preview eligible rows. Use <strong>Create Local Jobs</strong> only when preflight has a real submission-safe row.</p>
        <?php else: ?>
            <div class="table-wrap"><table>
                <thead><tr><th>ID</th><th>Status</th><th>Source</th><th>Order Ref</th><th>Booking ID</th><th>Driver</th><th>Vehicle</th><th>Queued</th><th>Updated</th><th>Hash</th><th>Payload</th></tr></thead>
                <tbody>
                <?php foreach ($state['jobs'] as $job): $status=(string)job_value($job,['status','state','job_status'],'unknown'); $payload=job_payload($job); ?>
                    <tr>
                        <td><?= h($job['id'] ?? '') ?></td>
                        <td><span class="badge <?= h(status_badge_class($status)) ?>"><?= h($status) ?></span></td>
                        <td><?= h(job_value($job, ['source_system','source_type'], '')) ?></td>
                        <td><?= h(job_value($job, ['order_reference','external_order_id'], '')) ?></td>
                        <td><?= h(job_value($job, ['normalized_booking_id','booking_id'], '')) ?></td>
                        <td><?= h(job_value($job, ['edxeix_driver_id','driver_id'], '')) ?></td>
                        <td><?= h(job_value($job, ['edxeix_vehicle_id','vehicle_id'], '')) ?></td>
                        <td><?= h(job_value($job, ['queued_at','created_at'], '')) ?></td>
                        <td><?= h(job_value($job, ['updated_at'], '')) ?></td>
                        <td><?= h(substr((string)job_value($job, ['payload_hash','dedupe_hash'], ''), 0, 16)) ?></td>
                        <td><?php if ($payload !== ''): ?><details><summary>view</summary><pre><?= h($payload) ?></pre></details><?php else: ?><span class="muted">none</span><?php endif; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
        <?php endif; ?>
    </section>

    <section class="card">
        <h2>Recent Attempts</h2>
        <?php if (!$state['attempts']): ?>
            <p>No submission attempts recorded. This is expected because the project has not performed a live EDXEIX submit.</p>
        <?php else: ?>
            <div class="table-wrap"><table>
                <thead><tr><th>ID</th><th>Job ID</th><th>Status</th><th>HTTP</th><th>Created</th><th>Message</th></tr></thead>
                <tbody>
                <?php foreach ($state['attempts'] as $attempt): ?>
                    <tr>
                        <td><?= h($attempt['id'] ?? '') ?></td>
                        <td><?= h(job_value($attempt, ['submission_job_id','job_id'], '')) ?></td>
                        <td><?= h(job_value($attempt, ['status','state','result'], '')) ?></td>
                        <td><?= h(job_value($attempt, ['http_status','status_code'], '')) ?></td>
                        <td><?= h(job_value($attempt, ['created_at','attempted_at'], '')) ?></td>
                        <td><?= h(job_value($attempt, ['message','error','response_summary'], '')) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
