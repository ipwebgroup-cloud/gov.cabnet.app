<?php
/**
 * gov.cabnet.app — v4.4 Bolt mail dry-run evidence monitor
 *
 * Protected Ops page. Records and inspects local dry-run evidence only.
 * - Does not call Bolt.
 * - Does not call EDXEIX.
 * - Does not create submission_jobs.
 * - Does not submit live.
 * - Synthetic cleanup is restricted to CABNET TEST / synthetic rows only.
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

function mdre_json_pretty($value): string
{
    if (is_string($value)) {
        $decoded = json_decode($value, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return (string)json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        return $value;
    }
    return (string)json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

$service = new BoltMailDryRunEvidenceService($db, $configArray);
$message = null;
$error = null;
$selected = null;
$evidenceDetail = null;
$cleanupPreview = null;
$cleanupResult = null;
$selectedId = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : (int)($_POST['booking_id'] ?? 0);
$evidenceId = isset($_GET['evidence_id']) ? (int)$_GET['evidence_id'] : (int)($_POST['evidence_id'] ?? 0);
$action = (string)($_POST['action'] ?? '');

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'record' && $selectedId > 0) {
        $evidenceId = $service->recordEvidence($selectedId, 'ops-web');
        $message = 'Dry-run evidence recorded as ID #' . $evidenceId . '. No submission job was created and no EDXEIX POST was performed.';
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'cleanup_synthetic') {
        $cleanupResult = $service->cleanupSyntheticEvidence((string)($_POST['confirm_text'] ?? ''));
        $message = 'Synthetic-only cleanup completed. Evidence deleted: ' . $cleanupResult['evidence_deleted'] . '; bookings deleted: ' . $cleanupResult['bookings_deleted'] . '; intake rows unlinked: ' . $cleanupResult['intake_rows_unlinked'] . '.';
    }

    if ($selectedId > 0) {
        $selected = $service->buildEvidencePreview($selectedId);
    }
    if ($evidenceId > 0) {
        $evidenceDetail = $service->findEvidenceById($evidenceId);
        if (!$evidenceDetail) {
            $error = 'Evidence row #' . $evidenceId . ' was not found.';
        }
    }

    $bookings = $service->listMailBookings(30);
    $evidenceRows = $service->listEvidence(30);
    $jobCounts = $service->countSubmissionJobs();
    $cleanupPreview = $service->syntheticCleanupPreview();
} catch (Throwable $e) {
    $error = $e->getMessage();
    $bookings = $bookings ?? [];
    $evidenceRows = $evidenceRows ?? [];
    $jobCounts = $jobCounts ?? ['submission_jobs_total' => 0, 'open_submission_jobs' => 0, 'submission_attempts_total' => 0];
    $cleanupPreview = $cleanupPreview ?? [
        'synthetic_evidence_rows' => 0,
        'synthetic_booking_rows' => 0,
        'linked_synthetic_intake_rows' => 0,
        'evidence_rows' => [],
        'booking_rows' => [],
        'linked_intake_rows' => [],
        'booking_ids' => [],
    ];
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
        :root{--bg:#f3f6fb;--panel:#fff;--ink:#07152f;--muted:#29446d;--line:#cfe0f4;--nav:#081225;--green:#07875a;--red:#b42318;--orange:#b85c00;--blue:#2563eb;--purple:#6d28d9;--soft:#f8fbff}*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--ink);font-family:Arial,Helvetica,sans-serif}.nav{background:var(--nav);color:#fff;min-height:52px;display:flex;gap:20px;align-items:center;padding:0 22px;position:sticky;top:0;z-index:5}.nav a,.nav strong{color:#fff;text-decoration:none;white-space:nowrap}.wrap{width:min(1420px,calc(100% - 32px));margin:22px auto 60px}.card{background:var(--panel);border:1px solid var(--line);border-radius:14px;padding:18px;margin-bottom:16px;box-shadow:0 10px 24px rgba(8,18,37,.04)}.hero{border-left:7px solid var(--green)}.warnbox{border-left:7px solid var(--orange);background:#fff7ed}.dangerbox{border-left:7px solid var(--red);background:#fff5f5}.grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin:14px 0}.metric{border:1px solid var(--line);border-radius:10px;padding:14px;background:var(--soft)}.metric strong{display:block;font-size:28px}.metric span{color:var(--muted);font-size:13px}.two{display:grid;grid-template-columns:1fr 1fr;gap:16px}.badge{display:inline-block;padding:5px 9px;border-radius:999px;font-size:12px;font-weight:800;margin:2px 3px}.badge-good{background:#dcfce7;color:#166534}.badge-bad{background:#fee2e2;color:#991b1b}.badge-warn{background:#fff7ed;color:#b45309}.badge-neutral{background:#eaf1ff;color:#1e40af}.badge-purple{background:#f3e8ff;color:#6d28d9}.btn{display:inline-block;border:0;border-radius:8px;padding:10px 14px;background:var(--blue);color:#fff;text-decoration:none;font-weight:800;cursor:pointer}.btn.green{background:var(--green)}.btn.orange{background:var(--orange)}.btn.dark{background:#22324d}.btn.purple{background:var(--purple)}.btn.red{background:var(--red)}input[type=text]{width:100%;padding:10px;border:1px solid var(--line);border-radius:8px}table{width:100%;border-collapse:collapse;font-size:14px}th,td{border:1px solid var(--line);padding:9px;text-align:left;vertical-align:top}th{background:#f6f9fd}.actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:12px}pre{background:#081225;color:#e6edf7;border-radius:10px;padding:14px;overflow:auto;max-height:560px}.ok{color:#166534}.bad{color:#991b1b}.muted{color:var(--muted)}code{background:#eef4ff;padding:2px 5px;border-radius:5px}@media(max-width:900px){.grid,.two{grid-template-columns:1fr}.wrap{width:calc(100% - 18px)}.nav{overflow:auto}}
    </style>
</head>
<body>
<nav class="nav">
    <strong>GC gov.cabnet.app</strong>
    <a href="<?= mdre_h(mdre_url('/ops/home.php', $key)) ?>">Ops Home</a>
    <a href="<?= mdre_h(mdre_url('/ops/mail-status.php', $key)) ?>">Mail Status</a>
    <a href="<?= mdre_h(mdre_url('/ops/mail-preflight.php', $key)) ?>">Mail Preflight</a>
    <a href="<?= mdre_h(mdre_url('/ops/mail-auto-dry-run.php', $key)) ?>">Auto Dry-run</a>
    <a href="<?= mdre_h(mdre_url('/ops/mail-synthetic-test.php', $key)) ?>">Synthetic Test</a>
    <a href="<?= mdre_h(mdre_url('/ops/mail-dry-run-evidence.php', $key)) ?>">Dry-run Evidence</a>
</nav>

<main class="wrap">
    <section class="card hero">
        <h1>Bolt Mail → Dry-run Evidence</h1>
        <p>Records, lists, and inspects local dry-run evidence snapshots for <code>source='bolt_mail'</code> normalized bookings. This page does not create jobs and never submits to EDXEIX.</p>
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

    <?php if ($evidenceDetail): ?>
        <section class="card">
            <h2>Evidence detail #<?= mdre_h($evidenceDetail['id']) ?></h2>
            <div>
                <?= mdre_badge((string)$evidenceDetail['evidence_status'], $evidenceDetail['evidence_status'] === 'recorded' ? 'good' : 'bad') ?>
                <?= mdre_badge('BOOKING #' . (string)($evidenceDetail['normalized_booking_id'] ?? '—'), 'neutral') ?>
                <?= mdre_badge('INTAKE #' . (string)($evidenceDetail['intake_id'] ?? '—'), 'neutral') ?>
                <?= mdre_badge('NO LIVE SUBMIT', 'good') ?>
            </div>
            <div class="two">
                <div>
                    <h3>Booking snapshot</h3>
                    <p><strong>Customer:</strong> <?= mdre_h($evidenceDetail['customer_name'] ?? '') ?></p>
                    <p><strong>Driver / Vehicle:</strong> <?= mdre_h($evidenceDetail['driver_name'] ?? '') ?> / <?= mdre_h($evidenceDetail['vehicle_plate'] ?? '') ?></p>
                    <p><strong>Start:</strong> <?= mdre_h($evidenceDetail['started_at'] ?? '') ?> → <?= mdre_h($evidenceDetail['ended_at'] ?? '') ?></p>
                    <p><strong>Route:</strong> <?= mdre_h($evidenceDetail['boarding_point'] ?? '') ?> → <?= mdre_h($evidenceDetail['disembark_point'] ?? '') ?></p>
                </div>
                <div>
                    <h3>Evidence metadata</h3>
                    <p><strong>Created:</strong> <?= mdre_h($evidenceDetail['created_at']) ?> by <?= mdre_h($evidenceDetail['created_by']) ?></p>
                    <p><strong>Payload hash:</strong> <code><?= mdre_h($evidenceDetail['payload_hash']) ?></code></p>
                    <p><strong>Notes:</strong> <?= mdre_h($evidenceDetail['notes']) ?></p>
                    <p><strong>Intake status:</strong> <?= mdre_h($evidenceDetail['intake_parse_status'] ?? '') ?> / <?= mdre_h($evidenceDetail['intake_safety_status'] ?? '') ?></p>
                </div>
            </div>
            <h3>Stored request payload</h3>
            <pre><?= mdre_h(mdre_json_pretty($evidenceDetail['request_payload_json'] ?? '')) ?></pre>
            <h3>Stored mapping snapshot</h3>
            <pre><?= mdre_h(mdre_json_pretty($evidenceDetail['mapping_snapshot_json'] ?? '')) ?></pre>
            <h3>Stored safety snapshot</h3>
            <pre><?= mdre_h(mdre_json_pretty($evidenceDetail['safety_snapshot_json'] ?? '')) ?></pre>
        </section>
    <?php endif; ?>

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
            <pre><?= mdre_h(mdre_json_pretty($selected['payload'])) ?></pre>
            <h3>Safety snapshot</h3>
            <pre><?= mdre_h(mdre_json_pretty($selected['safety'])) ?></pre>
        </section>
    <?php endif; ?>

    <section class="card">
        <h2>Mail-created normalized bookings</h2>
        <table>
            <thead><tr><th>ID</th><th>Start</th><th>Customer</th><th>Driver / Vehicle</th><th>Route</th><th>Intake</th><th>Action</th></tr></thead>
            <tbody>
            <?php if (!$bookings): ?>
                <tr><td colspan="7">No <code>source='bolt_mail'</code> normalized bookings exist.</td></tr>
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
            <thead><tr><th>ID</th><th>Booking</th><th>Created</th><th>Status</th><th>Payload hash</th><th>Notes</th><th>Action</th></tr></thead>
            <tbody>
            <?php if (!$evidenceRows): ?>
                <tr><td colspan="7">No dry-run evidence records exist yet.</td></tr>
            <?php endif; ?>
            <?php foreach ($evidenceRows as $row): ?>
                <tr>
                    <td>#<?= mdre_h($row['id']) ?></td>
                    <td><?= $row['normalized_booking_id'] ? '#' . mdre_h($row['normalized_booking_id']) : '—' ?><br><?= mdre_h($row['customer_name'] ?? '') ?></td>
                    <td><?= mdre_h($row['created_at']) ?><br><?= mdre_h($row['created_by']) ?></td>
                    <td><?= mdre_badge((string)$row['evidence_status'], $row['evidence_status'] === 'recorded' ? 'good' : 'bad') ?></td>
                    <td><code><?= mdre_h(substr((string)$row['payload_hash'], 0, 18)) ?>...</code></td>
                    <td><?= mdre_h($row['notes']) ?></td>
                    <td><a class="btn dark" href="<?= mdre_h(mdre_url('/ops/mail-dry-run-evidence.php', $key, ['evidence_id' => (int)$row['id']])) ?>">Open detail</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>

    <section class="card dangerbox">
        <h2>Synthetic cleanup only</h2>
        <p>This cleanup is restricted to <code>CABNET TEST%</code> / synthetic evidence and synthetic <code>source='bolt_mail'</code> bookings. It does not affect real Bolt mail rows, submission jobs, or EDXEIX.</p>
        <div class="grid">
            <?= mdre_metric($cleanupPreview['synthetic_evidence_rows'] ?? 0, 'Synthetic evidence rows') ?>
            <?= mdre_metric($cleanupPreview['synthetic_booking_rows'] ?? 0, 'Synthetic booking rows') ?>
            <?= mdre_metric($cleanupPreview['linked_synthetic_intake_rows'] ?? 0, 'Linked synthetic intake rows') ?>
            <?= mdre_metric($jobCounts['submission_attempts_total'], 'Submission attempts total') ?>
        </div>
        <?php if (($cleanupPreview['synthetic_evidence_rows'] ?? 0) > 0 || ($cleanupPreview['synthetic_booking_rows'] ?? 0) > 0): ?>
            <form method="post">
                <input type="hidden" name="key" value="<?= mdre_h($key) ?>">
                <input type="hidden" name="action" value="cleanup_synthetic">
                <p><strong>To apply synthetic-only cleanup, type:</strong> <code>DELETE_SYNTHETIC_ONLY</code></p>
                <input type="text" name="confirm_text" autocomplete="off" placeholder="DELETE_SYNTHETIC_ONLY">
                <div class="actions"><button class="btn red" type="submit">Delete synthetic evidence/bookings only</button></div>
            </form>
        <?php else: ?>
            <p class="muted">No synthetic evidence or synthetic local bookings are currently available for cleanup.</p>
        <?php endif; ?>
        <?php if ($cleanupResult): ?><pre><?= mdre_h(mdre_json_pretty($cleanupResult)) ?></pre><?php endif; ?>
    </section>
</main>
</body>
</html>
