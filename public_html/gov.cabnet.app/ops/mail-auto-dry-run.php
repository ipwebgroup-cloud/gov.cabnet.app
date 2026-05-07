<?php
/**
 * gov.cabnet.app — v4.4 Bolt mail auto dry-run controller
 * Protected Ops page. Runs/monitors the auto preflight + dry-run evidence worker.
 * No Bolt call, no EDXEIX call, no submission_jobs, no live submit.
 */

declare(strict_types=1);

use Bridge\Mail\BoltMailAutoDryRunService;

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Robots-Tag: noindex,nofollow', true);

$container = require '/home/cabnet/gov.cabnet.app_app/src/bootstrap.php';
$config = $container['config'];
$db = $container['db'];

$key = (string)($_GET['key'] ?? $_POST['key'] ?? '');
$expectedKey = (string)$config->get('app.internal_api_key', '');
if ($expectedKey === '' || !hash_equals($expectedKey, $key)) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

function madr_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function madr_badge(string $text, string $type = 'neutral'): string
{
    return '<span class="badge badge-' . madr_h($type) . '">' . madr_h($text) . '</span>';
}

function madr_metric($value, string $label): string
{
    return '<div class="metric"><strong>' . madr_h((string)$value) . '</strong><span>' . madr_h($label) . '</span></div>';
}

function madr_url(string $path, string $key, array $params = []): string
{
    return $path . '?' . http_build_query(array_merge(['key' => $key], $params));
}

$service = new BoltMailAutoDryRunService($db, $config);
$result = null;
$error = null;

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'preview') {
            $result = $service->run(50, true, 'ops-web-preview');
        } elseif ($action === 'process') {
            $result = $service->run(50, false, 'ops-web-auto');
        }
    }

    $status = $service->status();
    $evidence = $service->recentEvidence(20);
} catch (Throwable $e) {
    $error = $e->getMessage();
    $status = $status ?? [];
    $evidence = $evidence ?? [];
}

$liveEnabled = (bool)$config->get('edxeix.live_submit_enabled', false);
$dryRun = (bool)$config->get('app.dry_run', true);
$guard = (int)$config->get('edxeix.future_start_guard_minutes', 2);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Bolt Mail Auto Dry-run | gov.cabnet.app</title>
    <style>
        :root{--bg:#f3f6fb;--panel:#fff;--ink:#07152f;--muted:#29446d;--line:#cfe0f4;--nav:#081225;--green:#07875a;--red:#b42318;--orange:#b85c00;--blue:#2563eb;--purple:#6d28d9;--soft:#f8fbff}*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--ink);font-family:Arial,Helvetica,sans-serif}.nav{background:var(--nav);color:#fff;min-height:52px;display:flex;gap:18px;align-items:center;padding:0 22px;position:sticky;top:0;z-index:5;overflow:auto}.nav a,.nav strong{color:#fff;text-decoration:none;white-space:nowrap}.wrap{width:min(1420px,calc(100% - 32px));margin:22px auto 60px}.card{background:var(--panel);border:1px solid var(--line);border-radius:14px;padding:18px;margin-bottom:16px;box-shadow:0 10px 24px rgba(8,18,37,.04)}.hero{border-left:7px solid var(--green)}.warnbox{border-left:7px solid var(--orange);background:#fff7ed}.grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin:14px 0}.metric{border:1px solid var(--line);border-radius:10px;padding:14px;background:var(--soft)}.metric strong{display:block;font-size:28px}.metric span{color:var(--muted);font-size:13px}.badge{display:inline-block;padding:5px 9px;border-radius:999px;font-size:12px;font-weight:800;margin:2px 3px}.badge-good{background:#dcfce7;color:#166534}.badge-bad{background:#fee2e2;color:#991b1b}.badge-warn{background:#fff7ed;color:#b45309}.badge-neutral{background:#eaf1ff;color:#1e40af}.badge-purple{background:#f3e8ff;color:#6d28d9}.btn{display:inline-block;border:0;border-radius:8px;padding:10px 14px;background:var(--blue);color:#fff;text-decoration:none;font-weight:800;cursor:pointer}.btn.green{background:var(--green)}.btn.orange{background:var(--orange)}.btn.dark{background:#22324d}.btn.purple{background:var(--purple)}table{width:100%;border-collapse:collapse;font-size:14px}th,td{border:1px solid var(--line);padding:9px;text-align:left;vertical-align:top}th{background:#f6f9fd}.actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:12px}pre{background:#081225;color:#e6edf7;border-radius:10px;padding:14px;overflow:auto;max-height:560px}.ok{color:#166534}.bad{color:#991b1b}.muted{color:var(--muted)}code{background:#eef4ff;padding:2px 5px;border-radius:5px}@media(max-width:900px){.grid{grid-template-columns:1fr}.wrap{width:calc(100% - 18px)}}
    </style>
</head>
<body>
<nav class="nav">
    <strong>GC gov.cabnet.app</strong>
    <a href="<?= madr_h(madr_url('/ops/home.php', $key)) ?>">Ops Home</a>
    <a href="<?= madr_h(madr_url('/ops/mail-status.php', $key)) ?>">Mail Status</a>
    <a href="<?= madr_h(madr_url('/ops/mail-preflight.php', $key)) ?>">Mail Preflight</a>
    <a href="<?= madr_h(madr_url('/ops/mail-dry-run-evidence.php', $key)) ?>">Dry-run Evidence</a>
    <a href="<?= madr_h(madr_url('/ops/mail-auto-dry-run.php', $key)) ?>">Auto Dry-run</a>
</nav>

<main class="wrap">
    <section class="card hero">
        <h1>Bolt Mail Auto Preflight + Dry-run Evidence</h1>
        <p>Automatically converts valid active <code>future_candidate</code> mail rows into local <code>normalized_bookings</code> rows and records dry-run evidence. It does not create <code>submission_jobs</code> and never submits to EDXEIX.</p>
        <div>
            <?= madr_badge('LOCAL BOOKING ONLY', 'good') ?>
            <?= madr_badge('DRY-RUN EVIDENCE ONLY', 'good') ?>
            <?= madr_badge('NO SUBMISSION JOBS', 'good') ?>
            <?= madr_badge('NO EDXEIX POST', 'good') ?>
            <?= madr_badge('FUTURE GUARD ' . $guard . ' MIN', 'neutral') ?>
            <?= $liveEnabled ? madr_badge('LIVE SUBMIT ENABLED - BLOCKED', 'bad') : madr_badge('LIVE SUBMIT OFF', 'good') ?>
            <?= $dryRun ? madr_badge('DRY RUN ON', 'good') : madr_badge('DRY RUN OFF - BLOCKED', 'bad') ?>
        </div>
        <div class="grid">
            <?= madr_metric($status['active_unlinked_candidates'] ?? 0, 'Active unlinked candidates') ?>
            <?= madr_metric($status['mail_created_bookings'] ?? 0, 'Mail-created bookings') ?>
            <?= madr_metric($status['dry_run_evidence_rows'] ?? 0, 'Dry-run evidence rows') ?>
            <?= madr_metric($status['open_submission_jobs'] ?? 0, 'Open submission jobs') ?>
        </div>
        <form method="post" class="actions">
            <input type="hidden" name="key" value="<?= madr_h($key) ?>">
            <button class="btn dark" name="action" value="preview" type="submit">Preview auto-run</button>
            <button class="btn green" name="action" value="process" type="submit">Run auto dry-run now</button>
            <a class="btn purple" href="<?= madr_h(madr_url('/ops/mail-synthetic-test.php', $key)) ?>">Synthetic Test</a>
        </form>
    </section>

    <section class="card warnbox">
        <strong>Safety boundary:</strong> this page may write to <code>normalized_bookings</code> and <code>bolt_mail_dry_run_evidence</code>. It never writes to <code>submission_jobs</code>, never writes to <code>submission_attempts</code>, and never POSTs to EDXEIX.
    </section>

    <?php if ($error): ?><section class="card"><p class="bad"><strong><?= madr_h($error) ?></strong></p></section><?php endif; ?>

    <?php if ($result): ?>
        <section class="card">
            <h2>Run result</h2>
            <div class="grid">
                <?= madr_metric($result['summary']['candidate_rows'] ?? 0, 'Candidates checked') ?>
                <?= madr_metric($result['summary']['created_bookings'] ?? 0, 'Bookings created') ?>
                <?= madr_metric($result['summary']['evidence_recorded'] ?? 0, 'Evidence recorded') ?>
                <?= madr_metric($result['summary']['blocked'] ?? 0, 'Blocked') ?>
            </div>
            <table>
                <thead><tr><th>Intake</th><th>Pickup</th><th>Customer</th><th>Driver / Vehicle</th><th>Status</th><th>Booking</th><th>Evidence</th><th>Message</th></tr></thead>
                <tbody>
                <?php if (empty($result['items'])): ?>
                    <tr><td colspan="8" class="muted">No active future candidates were available.</td></tr>
                <?php endif; ?>
                <?php foreach (($result['items'] ?? []) as $item): ?>
                    <tr>
                        <td>#<?= madr_h($item['intake_id']) ?></td>
                        <td><?= madr_h($item['parsed_pickup_at']) ?></td>
                        <td><?= madr_h($item['customer_name']) ?></td>
                        <td><?= madr_h($item['driver_name']) ?><br><strong><?= madr_h($item['vehicle_plate']) ?></strong></td>
                        <td><?= madr_badge((string)$item['status'], str_contains((string)$item['status'], 'recorded') || str_contains((string)$item['status'], 'ready') || str_contains((string)$item['status'], 'exists') ? 'good' : 'warn') ?></td>
                        <td><?= $item['booking_id'] ? ('#' . madr_h($item['booking_id']) . '<br><a href="' . madr_h(madr_url('/ops/mail-dry-run-evidence.php', $key, ['booking_id' => (int)$item['booking_id']])) . '">preview</a>') : '—' ?></td>
                        <td><?= $item['evidence_id'] ? ('#' . madr_h($item['evidence_id']) . '<br><a href="' . madr_h(madr_url('/ops/mail-dry-run-evidence.php', $key, ['evidence_id' => (int)$item['evidence_id']])) . '">detail</a>') : '—' ?></td>
                        <td><?= madr_h($item['message']) ?><?php if (!empty($item['blockers'])): ?><br><code><?= madr_h(implode(', ', $item['blockers'])) ?></code><?php endif; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <pre><?= madr_h(json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)) ?></pre>
        </section>
    <?php endif; ?>

    <section class="card">
        <h2>Safety checks</h2>
        <div class="grid">
            <?= madr_metric($status['submission_jobs_total'] ?? 0, 'Submission jobs total') ?>
            <?= madr_metric($status['open_submission_jobs'] ?? 0, 'Open submission jobs') ?>
            <?= madr_metric($status['submission_attempts_total'] ?? 0, 'Submission attempts total') ?>
            <?= madr_metric($status['synthetic_rows'] ?? 0, 'Synthetic intake rows') ?>
        </div>
    </section>

    <section class="card">
        <h2>Recent dry-run evidence</h2>
        <table>
            <thead><tr><th>ID</th><th>Booking</th><th>Customer</th><th>Driver / Vehicle</th><th>Started at</th><th>Status</th><th>Created</th></tr></thead>
            <tbody>
            <?php if (!$evidence): ?><tr><td colspan="7" class="muted">No dry-run evidence rows yet.</td></tr><?php endif; ?>
            <?php foreach ($evidence as $row): ?>
                <tr>
                    <td>#<?= madr_h($row['id']) ?><br><a href="<?= madr_h(madr_url('/ops/mail-dry-run-evidence.php', $key, ['evidence_id' => (int)$row['id']])) ?>">detail</a></td>
                    <td>#<?= madr_h($row['normalized_booking_id']) ?><br><a href="<?= madr_h(madr_url('/ops/mail-dry-run-evidence.php', $key, ['booking_id' => (int)$row['normalized_booking_id']])) ?>">preview</a></td>
                    <td><?= madr_h($row['customer_name'] ?? '') ?></td>
                    <td><?= madr_h($row['driver_name'] ?? '') ?><br><strong><?= madr_h($row['vehicle_plate'] ?? '') ?></strong></td>
                    <td><?= madr_h($row['started_at'] ?? '') ?></td>
                    <td><?= madr_badge((string)$row['evidence_status'], 'good') ?></td>
                    <td><?= madr_h($row['created_at']) ?><br><?= madr_h($row['created_by']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>
</main>
</body>
</html>
