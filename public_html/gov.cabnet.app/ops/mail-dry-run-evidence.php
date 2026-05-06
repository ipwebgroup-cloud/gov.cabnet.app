<?php
/**
 * gov.cabnet.app — v4.2 Bolt mail dry-run evidence
 *
 * Protected Ops page. Records local dry-run evidence only.
 * - Does not call Bolt.
 * - Does not call EDXEIX.
 * - Does not create submission_jobs.
 * - Does not submit live.
 */

declare(strict_types=1);

use Bridge\Mail\BoltMailDryRunEvidenceService;

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Robots-Tag: noindex,nofollow', true);

$container = require '/home/cabnet/gov.cabnet.app_app/src/bootstrap.php';
$config = $container['config'];
$db = $container['db']->connection();
$configArray = $config->all();

$expectedKey = (string)$config->get('app.internal_api_key', '');
$key = (string)($_GET['key'] ?? $_POST['key'] ?? '');
if ($expectedKey === '' || !hash_equals($expectedKey, $key)) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

function mdre_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function mdre_badge(string $text, string $type = 'neutral'): string
{
    return '<span class="badge badge-' . mdre_h($type) . '">' . mdre_h($text) . '</span>';
}

function mdre_metric($value, string $label): string
{
    return '<div class="metric"><strong>' . mdre_h((string)$value) . '</strong><span>' . mdre_h($label) . '</span></div>';
}

function mdre_url(string $path, string $key, array $params = []): string
{
    $params = array_merge(['key' => $key], $params);
    return $path . '?' . http_build_query($params);
}

$service = new BoltMailDryRunEvidenceService($db, $configArray);
$message = null;
$error = null;
$selected = null;
$selectedId = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : (int)($_POST['booking_id'] ?? 0);

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'record' && $selectedId > 0) {
        $evidenceId = $service->recordEvidence($selectedId, 'ops-web');
        $message = 'Dry-run evidence recorded as ID #' . $evidenceId . '. No submission job was created and no EDXEIX POST was performed.';
    }

    if ($selectedId > 0) {
        $selected = $service->buildEvidencePreview($selectedId);
    }

    $bookings = $service->listMailBookings(30);
    $evidenceRows = $service->listEvidence(30);
    $jobCounts = $service->countSubmissionJobs();
} catch (Throwable $e) {
    $error = $e->getMessage();
    $bookings = $bookings ?? [];
    $evidenceRows = $evidenceRows ?? [];
    $jobCounts = $jobCounts ?? ['submission_jobs_total' => 0, 'open_submission_jobs' => 0, 'submission_attempts_total' => 0];
}

$liveEnabled = !empty($configArray['edxeix']['live_submit_enabled']);
$dryRun = !empty($configArray['app']['dry_run']);
$guard = (int)($configArray['edxeix']['future_start_guard_minutes'] ?? 2);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Bolt Mail Dry-run Evidence | gov.cabnet.app</title>
    <style>
        :root{--bg:#f3f6fb;--panel:#fff;--ink:#07152f;--muted:#29446d;--line:#cfe0f4;--nav:#081225;--green:#07875a;--red:#b42318;--orange:#b85c00;--blue:#2563eb;--purple:#6d28d9;--soft:#f8fbff}*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--ink);font-family:Arial,Helvetica,sans-serif}.nav{background:var(--nav);color:#fff;min-height:52px;display:flex;gap:20px;align-items:center;padding:0 22px;position:sticky;top:0;z-index:5}.nav a,.nav strong{color:#fff;text-decoration:none;white-space:nowrap}.wrap{width:min(1420px,calc(100% - 32px));margin:22px auto 60px}.card{background:var(--panel);border:1px solid var(--line);border-radius:14px;padding:18px;margin-bottom:16px;box-shadow:0 10px 24px rgba(8,18,37,.04)}.hero{border-left:7px solid var(--green)}.warnbox{border-left:7px solid var(--orange);background:#fff7ed}.grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin:14px 0}.metric{border:1px solid var(--line);border-radius:10px;padding:14px;background:var(--soft)}.metric strong{display:block;font-size:28px}.metric span{color:var(--muted);font-size:13px}.two{display:grid;grid-template-columns:1fr 1fr;gap:16px}.badge{display:inline-block;padding:5px 9px;border-radius:999px;font-size:12px;font-weight:800;margin:2px 3px}.badge-good{background:#dcfce7;color:#166534}.badge-bad{background:#fee2e2;color:#991b1b}.badge-warn{background:#fff7ed;color:#b45309}.badge-neutral{background:#eaf1ff;color:#1e40af}.badge-purple{background:#f3e8ff;color:#6d28d9}.btn{display:inline-block;border:0;border-radius:8px;padding:10px 14px;background:var(--blue);color:#fff;text-decoration:none;font-weight:800;cursor:pointer}.btn.green{background:var(--green)}.btn.orange{background:var(--orange)}.btn.dark{background:#22324d}.btn.purple{background:var(--purple)}table{width:100%;border-collapse:collapse;font-size:14px}th,td{border:1px solid var(--line);padding:9px;text-align:left;vertical-align:top}th{background:#f6f9fd}.actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:12px}pre{background:#081225;color:#e6edf7;border-radius:10px;padding:14px;overflow:auto;max-height:560px}.ok{color:#166534}.bad{color:#991b1b}.muted{color:var(--muted)}code{background:#eef4ff;padding:2px 5px;border-radius:5px}@media(max-width:900px){.grid,.two{grid-template-columns:1fr}.wrap{width:calc(100% - 18px)}.nav{overflow:auto}}
    </style>
</head>
<body>
<nav class="nav">
    <strong>GC gov.cabnet.app</strong>
    <a href="<?= mdre_h(mdre_url('/ops/home.php', $key)) ?>">Ops Home</a>
    <a href="<?= mdre_h(mdre_url('/ops/mail-status.php', $key)) ?>">Mail Status</a>
    <a href="<?= mdre_h(mdre_url('/ops/mail-preflight.php', $key)) ?>">Mail Preflight</a>
    <a href="<?= mdre_h(mdre_url('/ops/mail-synthetic-test.php', $key)) ?>">Synthetic Test</a>
    <a href="<?= mdre_h(mdre_url('/ops/mail-dry-run-evidence.php', $key)) ?>">Dry-run Evidence</a>
</nav>

<main class="wrap">
    <section class="card hero">
        <h1>Bolt Mail → Dry-run Evidence</h1>
        <p>Records a local dry-run evidence snapshot for an existing <code>source='bolt_mail'</code> normalized booking. This page does not create jobs and never submits to EDXEIX.</p>
        <div>
            <?= mdre_badge('READ ONLY UNTIL RECORD BUTTON', 'good') ?>
            <?= mdre_badge('NO BOLT API CALL', 'good') ?>
            <?= mdre_badge('NO EDXEIX POST', 'good') ?>
            <?= mdre_badge('NO SUBMISSION JOBS', 'good') ?>
            <?= mdre_badge('FUTURE GUARD ' . $guard . ' MIN', 'neutral') ?>
            <?= $liveEnabled ? mdre_badge('LIVE SUBMIT ENABLED', 'bad') : mdre_badge('LIVE SUBMIT OFF', 'good') ?>
            <?= $dryRun ? mdre_badge('DRY RUN ON', 'good') : mdre_badge('DRY RUN OFF', 'warn') ?>
        </div>
        <div class="grid">
            <?= mdre_metric(count($bookings), 'Mail-created bookings listed') ?>
            <?= mdre_metric(count($evidenceRows), 'Evidence rows listed') ?>
            <?= mdre_metric($jobCounts['submission_jobs_total'], 'Submission jobs total') ?>
            <?= mdre_metric($jobCounts['open_submission_jobs'], 'Open submission jobs') ?>
        </div>
    </section>

    <?php if ($message): ?><section class="card"><p class="ok"><strong><?= mdre_h($message) ?></strong></p></section><?php endif; ?>
    <?php if ($error): ?><section class="card"><p class="bad"><strong><?= mdre_h($error) ?></strong></p></section><?php endif; ?>

    <section class="card warnbox">
        <strong>Safety boundary:</strong> this evidence table is separate from <code>submission_jobs</code>. Recording evidence is only a local audit snapshot of what would be submitted later if a separate live-submit patch is explicitly approved.
    </section>

    <?php if ($selected): ?>
        <section class="card">
            <h2>Selected booking preview #<?= mdre_h($selected['booking']['id'] ?? '') ?></h2>
            <div>
                <?= $selected['can_record'] ? mdre_badge('CAN RECORD EVIDENCE', 'good') : mdre_badge('BLOCKED', 'bad') ?>
                <?= !empty($selected['mapping']['driver_ok']) ? mdre_badge('DRIVER MAPPED', 'good') : mdre_badge('DRIVER NOT MAPPED', 'bad') ?>
                <?= !empty($selected['mapping']['vehicle_ok']) ? mdre_badge('VEHICLE MAPPED', 'good') : mdre_badge('VEHICLE NOT MAPPED', 'bad') ?>
                <?= !empty($selected['mapping']['starting_point_ok']) ? mdre_badge('START POINT OK', 'good') : mdre_badge('START POINT MISSING', 'bad') ?>
                <?= mdre_badge('NO LIVE SUBMIT', 'good') ?>
            </div>
            <div class="two">
                <div>
                    <h3>Safety</h3>
                    <p><strong>Blockers:</strong> <?= $selected['safety']['blockers'] ? mdre_h(implode(', ', $selected['safety']['blockers'])) : 'None' ?></p>
                    <p><strong>Warnings:</strong> <?= $selected['safety']['warnings'] ? mdre_h(implode(', ', $selected['safety']['warnings'])) : 'None' ?></p>
                    <p><strong>Payload hash:</strong> <code><?= mdre_h($selected['payload_hash']) ?></code></p>
                    <?php if ($selected['can_record']): ?>
                        <form method="post">
                            <input type="hidden" name="key" value="<?= mdre_h($key) ?>">
                            <input type="hidden" name="booking_id" value="<?= mdre_h($selected['booking']['id']) ?>">
                            <input type="hidden" name="action" value="record">
                            <button class="btn green" type="submit">Record dry-run evidence</button>
                        </form>
                    <?php endif; ?>
                </div>
                <div>
                    <h3>Mapping result</h3>
                    <ul>
                        <li>Driver: <?= mdre_h($selected['booking']['driver_name'] ?? '') ?> → <?= mdre_h($selected['mapping']['driver']['edxeix_driver_id'] ?? '') ?></li>
                        <li>Vehicle: <?= mdre_h($selected['booking']['vehicle_plate'] ?? '') ?> → <?= mdre_h($selected['mapping']['vehicle']['edxeix_vehicle_id'] ?? '') ?></li>
                        <li>Starting point: <?= mdre_h($selected['mapping']['starting_point']['internal_key'] ?? '') ?> → <?= mdre_h($selected['mapping']['starting_point']['edxeix_starting_point_id'] ?? '') ?></li>
                    </ul>
                </div>
            </div>
            <h3>EDXEIX payload dry-run preview</h3>
            <pre><?= mdre_h(json_encode($selected['payload'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></pre>
            <h3>Safety snapshot</h3>
            <pre><?= mdre_h(json_encode($selected['safety'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></pre>
        </section>
    <?php endif; ?>

    <section class="card">
        <h2>Mail-created normalized bookings</h2>
        <table>
            <thead><tr><th>ID</th><th>Start</th><th>Customer</th><th>Driver / Vehicle</th><th>Route</th><th>Intake</th><th>Action</th></tr></thead>
            <tbody>
            <?php if (!$bookings): ?>
                <tr><td colspan="7">No source='bolt_mail' normalized bookings exist.</td></tr>
            <?php endif; ?>
            <?php foreach ($bookings as $row): ?>
                <tr>
                    <td>#<?= mdre_h($row['id']) ?></td>
                    <td><?= mdre_h($row['started_at']) ?><br><span class="muted"><?= mdre_h($row['created_at']) ?></span></td>
                    <td><?= mdre_h($row['customer_name']) ?></td>
                    <td><?= mdre_h($row['driver_name']) ?><br><strong><?= mdre_h($row['vehicle_plate']) ?></strong></td>
                    <td><strong>From:</strong> <?= mdre_h($row['boarding_point']) ?><br><strong>To:</strong> <?= mdre_h($row['disembark_point']) ?></td>
                    <td><?= $row['intake_id'] ? '#' . mdre_h($row['intake_id']) : '—' ?><br><?= mdre_h($row['intake_safety_status'] ?? '') ?></td>
                    <td><a class="btn dark" href="<?= mdre_h(mdre_url('/ops/mail-dry-run-evidence.php', $key, ['booking_id' => (int)$row['id']])) ?>">Preview evidence</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>

    <section class="card">
        <h2>Recent dry-run evidence records</h2>
        <table>
            <thead><tr><th>ID</th><th>Booking</th><th>Created</th><th>Status</th><th>Payload hash</th><th>Notes</th></tr></thead>
            <tbody>
            <?php if (!$evidenceRows): ?>
                <tr><td colspan="6">No dry-run evidence records exist yet.</td></tr>
            <?php endif; ?>
            <?php foreach ($evidenceRows as $row): ?>
                <tr>
                    <td>#<?= mdre_h($row['id']) ?></td>
                    <td><?= $row['normalized_booking_id'] ? '#' . mdre_h($row['normalized_booking_id']) : '—' ?><br><?= mdre_h($row['customer_name'] ?? '') ?></td>
                    <td><?= mdre_h($row['created_at']) ?><br><?= mdre_h($row['created_by']) ?></td>
                    <td><?= mdre_badge((string)$row['evidence_status'], $row['evidence_status'] === 'recorded' ? 'good' : 'bad') ?></td>
                    <td><code><?= mdre_h(substr((string)$row['payload_hash'], 0, 18)) ?>...</code></td>
                    <td><?= mdre_h($row['notes']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>
</main>
</body>
</html>
