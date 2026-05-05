<?php
/**
 * gov.cabnet.app — Bolt Mail Intake → Preflight Candidate Bridge
 *
 * Guarded Ops page.
 * - Reads bolt_mail_intake rows.
 * - Allows manually creating a local normalized booking only for future_candidate rows.
 * - Does not create submission jobs.
 * - Does not submit to EDXEIX.
 */

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Robots-Tag: noindex, nofollow', true);

$container = require '/home/cabnet/gov.cabnet.app_app/src/bootstrap.php';

use Bridge\Mail\BoltMailIntakeBookingBridge;

$config = $container['config'];
$db = $container['db'];
$key = (string)($_GET['key'] ?? $_POST['key'] ?? '');
$expectedKey = (string)$config->get('app.internal_api_key', '');

if ($expectedKey === '' || !hash_equals($expectedKey, $key)) {
    http_response_code(403);
    echo '<!doctype html><meta charset="utf-8"><title>Forbidden</title><h1>Forbidden</h1><p>Missing or invalid internal key.</p>';
    exit;
}

function mp_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function mp_badge(string $text, string $type = 'neutral'): string
{
    return '<span class="badge badge-' . mp_h($type) . '">' . mp_h($text) . '</span>';
}

function mp_mask_phone(?string $phone): string
{
    $phone = (string)$phone;
    if ($phone === '') {
        return '';
    }
    $digits = preg_replace('/\D+/', '', $phone) ?: '';
    if (strlen($digits) <= 4) {
        return '****';
    }
    return '+***' . substr($digits, -4);
}

function mp_short_hash(?string $hash): string
{
    $hash = (string)$hash;
    return $hash === '' ? '' : substr($hash, 0, 12) . '…';
}

function mp_status_badge(string $status): string
{
    return match ($status) {
        'future_candidate', 'parsed', 'ready', 'linked' => mp_badge($status, 'good'),
        'blocked_past', 'blocked_too_soon', 'rejected', 'error' => mp_badge($status, 'bad'),
        'needs_review' => mp_badge($status, 'warn'),
        default => mp_badge($status, 'neutral'),
    };
}

function mp_bool_badge(bool $ok, string $yes = 'OK', string $no = 'BLOCKED'): string
{
    return $ok ? mp_badge($yes, 'good') : mp_badge($no, 'bad');
}

function mp_error_list(array $items): string
{
    if (!$items) {
        return '<span class="muted">None</span>';
    }
    $html = '<ul class="mini-list">';
    foreach ($items as $item) {
        $html .= '<li>' . mp_h($item) . '</li>';
    }
    $html .= '</ul>';
    return $html;
}

$bridge = new BoltMailIntakeBookingBridge($db, $config);
$notice = null;
$noticeType = 'neutral';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['action'] ?? '') === 'create_booking') {
    $intakeId = filter_var($_POST['intake_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['default' => 0]]);
    if ($intakeId > 0) {
        $result = $bridge->createLocalPreflightBooking((int)$intakeId);
        $notice = $result['message'] ?? 'Action completed.';
        $noticeType = !empty($result['ok']) ? 'good' : 'bad';
    } else {
        $notice = 'Invalid intake row id.';
        $noticeType = 'bad';
    }
}

$selectedId = filter_var($_GET['intake_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['default' => 0]]);
$selectedPreview = $selectedId > 0 ? $bridge->previewById((int)$selectedId) : null;
$futureRows = $bridge->listFutureCandidates(50);
$recentRows = $bridge->listRecentIntakeRows(30);
$keyParam = rawurlencode($key);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Bolt Mail Preflight Bridge | gov.cabnet.app</title>
    <style>
        :root{--bg:#f4f7fb;--panel:#fff;--ink:#07152f;--muted:#45617f;--line:#d7e2f2;--nav:#071225;--blue:#2563eb;--green:#07875a;--red:#b42318;--orange:#b85c00;--soft:#f8fbff;--slate:#334155}
        *{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--ink);font-family:Arial,Helvetica,sans-serif}.nav{background:var(--nav);color:#fff;min-height:54px;display:flex;align-items:center;gap:18px;padding:0 24px;position:sticky;top:0;z-index:2;overflow:auto}.nav strong{white-space:nowrap}.nav a{color:#fff;text-decoration:none;white-space:nowrap}.nav a:hover{text-decoration:underline}.wrap{width:min(1480px,calc(100% - 42px));margin:22px auto 60px}.card{background:var(--panel);border:1px solid var(--line);border-radius:14px;padding:18px;margin-bottom:18px;box-shadow:0 10px 28px rgba(7,18,37,.05)}.hero{border-left:7px solid var(--green)}h1{font-size:30px;margin:0 0 10px}h2{font-size:22px;margin:0 0 14px}h3{font-size:18px;margin:0 0 8px}p{color:var(--muted);line-height:1.45}.muted{color:var(--muted)}code{background:#eef3ff;padding:2px 5px;border-radius:5px}.actions{display:flex;gap:10px;flex-wrap:wrap;align-items:center}.btn,button.btn{display:inline-block;border:0;padding:10px 14px;border-radius:8px;background:var(--blue);color:#fff;text-decoration:none;font-weight:700;cursor:pointer;font-size:14px}.btn.green{background:var(--green)}.btn.red{background:var(--red)}.btn.slate{background:var(--slate)}.btn.light{background:#e9f0fb;color:#1f3b64}.btn:disabled{opacity:.5;cursor:not-allowed}.badge{display:inline-block;padding:5px 9px;border-radius:999px;font-size:12px;font-weight:800;margin:2px 4px 2px 0;white-space:nowrap}.badge-good{background:#dcfce7;color:#166534}.badge-warn{background:#fff7ed;color:#b45309}.badge-bad{background:#fee2e2;color:#991b1b}.badge-neutral{background:#eaf1ff;color:#1e40af}.alert{border-radius:12px;padding:14px 16px;margin-bottom:18px;border:1px solid var(--line)}.alert.good{background:#ecfdf3;border-color:#bbf7d0;color:#166534}.alert.bad{background:#fef2f2;border-color:#fecaca;color:#991b1b}.grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}.metric{border:1px solid var(--line);border-radius:10px;padding:13px;background:var(--soft)}.metric strong{display:block;font-size:28px}.metric span{color:var(--muted);font-size:13px}.table-wrap{overflow:auto;border:1px solid var(--line);border-radius:10px}table{border-collapse:collapse;width:100%;background:#fff}th,td{border-bottom:1px solid var(--line);padding:10px;text-align:left;vertical-align:top;font-size:14px}th{background:#f8fbff;color:#0f284a;white-space:nowrap}tr:last-child td{border-bottom:0}.two{display:grid;grid-template-columns:1fr 1fr;gap:16px}.mini-list{margin:0;padding-left:18px}.mini-list li{margin:4px 0}.payload{white-space:pre-wrap;background:#071225;color:#e5eefc;border-radius:10px;padding:12px;overflow:auto;font-size:13px;line-height:1.4}.nowrap{white-space:nowrap}.small{font-size:13px}.danger-note{background:#fff7ed;border:1px solid #fed7aa;border-left:7px solid var(--orange);border-radius:12px;padding:14px;margin-bottom:18px}.idlink{font-weight:800;text-decoration:none;color:#1d4ed8}@media(max-width:1000px){.grid,.two{grid-template-columns:1fr}.wrap{width:calc(100% - 22px);margin-top:14px}.nav{padding:0 12px}}
    </style>
</head>
<body>
<nav class="nav">
    <strong>GC gov.cabnet.app</strong>
    <a href="/ops/home.php">Ops Home</a>
    <a href="/ops/mail-intake.php?key=<?= mp_h($keyParam) ?>">Mail Intake</a>
    <a href="/ops/mail-preflight.php?key=<?= mp_h($keyParam) ?>">Mail Preflight</a>
    <a href="/ops/readiness.php">Readiness</a>
    <a href="/bolt_edxeix_preflight.php?limit=30">Preflight JSON</a>
</nav>

<main class="wrap">
    <section class="card hero">
        <h1>Bolt Mail Intake → Preflight Candidate Bridge</h1>
        <p>This page converts only manually approved <code>future_candidate</code> mail intake rows into local <code>normalized_bookings</code> rows for EDXEIX preflight review. It does not create submission jobs and does not submit to EDXEIX.</p>
        <div>
            <?= mp_badge('MAIL INTAKE READY', 'good') ?>
            <?= mp_badge('MANUAL APPROVAL REQUIRED', 'warn') ?>
            <?= mp_badge('NO LIVE SUBMIT', 'good') ?>
        </div>
    </section>

    <section class="danger-note">
        <strong>Safety boundary:</strong> creating a local preflight booking is a database write to <code>normalized_bookings</code>, but it is not a live submission. Blocked/past/too-soon/rejected rows cannot be created from this screen.
    </section>

    <?php if ($notice !== null): ?>
        <div class="alert <?= mp_h($noticeType) ?>"><?= mp_h($notice) ?></div>
    <?php endif; ?>

    <section class="card">
        <h2>Future candidates waiting for approval</h2>
        <div class="grid">
            <div class="metric"><strong><?= count($futureRows) ?></strong><span>Future candidate rows</span></div>
            <div class="metric"><strong><?= count(array_filter($futureRows, static fn($r) => empty($r['linked_booking_id']))) ?></strong><span>Unlinked candidates</span></div>
            <div class="metric"><strong><?= count(array_filter($futureRows, static fn($r) => !empty($r['linked_booking_id']))) ?></strong><span>Already linked</span></div>
            <div class="metric"><strong>OFF</strong><span>Live submit status</span></div>
        </div>
        <p class="small muted">A candidate can disappear from this list after time passes because the bridge re-checks the future guard before creation.</p>
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>ID</th>
                    <th>Pickup</th>
                    <th>Customer</th>
                    <th>Driver / Vehicle</th>
                    <th>Route</th>
                    <th>Status</th>
                    <th>Linked booking</th>
                    <th>Action</th>
                </tr>
                </thead>
                <tbody>
                <?php if (!$futureRows): ?>
                    <tr><td colspan="8" class="muted">No future candidate rows are currently waiting. The system is waiting for the next real Bolt Ride details email.</td></tr>
                <?php endif; ?>
                <?php foreach ($futureRows as $row): ?>
                    <tr>
                        <td><a class="idlink" href="/ops/mail-preflight.php?key=<?= mp_h($keyParam) ?>&amp;intake_id=<?= (int)$row['id'] ?>">#<?= (int)$row['id'] ?></a></td>
                        <td class="nowrap"><?= mp_h($row['parsed_pickup_at'] ?? '') ?><br><span class="muted small"><?= mp_h($row['timezone_label'] ?? '') ?></span></td>
                        <td><?= mp_h($row['customer_name'] ?? '') ?><br><span class="muted small"><?= mp_h(mp_mask_phone($row['customer_mobile'] ?? '')) ?></span></td>
                        <td><?= mp_h($row['driver_name'] ?? '') ?><br><strong><?= mp_h($row['vehicle_plate'] ?? '') ?></strong></td>
                        <td><strong>From:</strong> <?= mp_h($row['pickup_address'] ?? '') ?><br><strong>To:</strong> <?= mp_h($row['dropoff_address'] ?? '') ?></td>
                        <td><?= mp_status_badge((string)($row['parse_status'] ?? '')) ?><?= mp_status_badge((string)($row['safety_status'] ?? '')) ?></td>
                        <td><?= !empty($row['linked_booking_id']) ? ('#' . (int)$row['linked_booking_id']) : '<span class="muted">Not linked</span>' ?></td>
                        <td><a class="btn light" href="/ops/mail-preflight.php?key=<?= mp_h($keyParam) ?>&amp;intake_id=<?= (int)$row['id'] ?>">Preview</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <?php if ($selectedPreview !== null): ?>
        <section class="card">
            <h2>Selected preview: intake row #<?= (int)$selectedPreview['intake_id'] ?></h2>
            <p><?= mp_h($selectedPreview['message'] ?? '') ?></p>
            <div class="actions">
                <?= mp_bool_badge(!empty($selectedPreview['ok']), 'READY', 'BLOCKED') ?>
                <?= mp_bool_badge(!empty($selectedPreview['time_check']['ok']), 'TIME OK', 'TIME BLOCK') ?>
                <?= mp_bool_badge(!empty($selectedPreview['mapping']['driver']['ok']), 'DRIVER MAPPED', 'DRIVER MISSING') ?>
                <?= mp_bool_badge(!empty($selectedPreview['mapping']['vehicle']['ok']), 'VEHICLE MAPPED', 'VEHICLE MISSING') ?>
                <?= mp_bool_badge(!empty($selectedPreview['mapping']['starting_point']['ok']), 'START POINT OK', 'START POINT MISSING') ?>
                <?= mp_badge('NO LIVE SUBMIT', 'good') ?>
            </div>

            <div class="two" style="margin-top:16px">
                <div>
                    <h3>Blockers</h3>
                    <?= mp_error_list($selectedPreview['errors'] ?? []) ?>
                    <h3 style="margin-top:14px">Warnings</h3>
                    <?= mp_error_list($selectedPreview['warnings'] ?? []) ?>
                </div>
                <div>
                    <h3>Mapping result</h3>
                    <ul class="mini-list">
                        <li>Driver lookup: <strong><?= mp_h($selectedPreview['mapping']['driver']['lookup'] ?? '') ?></strong> → <?= mp_h($selectedPreview['mapping']['driver']['edxeix_driver_id'] ?? '') ?></li>
                        <li>Vehicle lookup: <strong><?= mp_h($selectedPreview['mapping']['vehicle']['lookup'] ?? '') ?></strong> → <?= mp_h($selectedPreview['mapping']['vehicle']['edxeix_vehicle_id'] ?? '') ?></li>
                        <li>Starting point: <strong><?= mp_h($selectedPreview['mapping']['starting_point']['lookup'] ?? '') ?></strong> → <?= mp_h($selectedPreview['mapping']['starting_point']['edxeix_starting_point_id'] ?? '') ?></li>
                    </ul>
                </div>
            </div>

            <div class="actions" style="margin-top:18px">
                <?php if (!empty($selectedPreview['existing_booking_id'])): ?>
                    <a class="btn green" href="/bolt_edxeix_preflight.php?limit=30">Open existing preflight JSON</a>
                <?php elseif (!empty($selectedPreview['create_allowed'])): ?>
                    <form method="post" style="display:inline" onsubmit="return confirm('Create a local normalized booking for preflight only? This will not submit to EDXEIX.');">
                        <input type="hidden" name="key" value="<?= mp_h($key) ?>">
                        <input type="hidden" name="action" value="create_booking">
                        <input type="hidden" name="intake_id" value="<?= (int)$selectedPreview['intake_id'] ?>">
                        <button class="btn green" type="submit">Create local preflight booking</button>
                    </form>
                <?php else: ?>
                    <button class="btn" disabled>Create blocked until errors are fixed</button>
                <?php endif; ?>
                <a class="btn slate" href="/ops/mail-preflight.php?key=<?= mp_h($keyParam) ?>">Clear selection</a>
            </div>

            <h3 style="margin-top:18px">Normalized booking candidate</h3>
            <pre class="payload"><?= mp_h(json_encode($selectedPreview['normalized_candidate'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></pre>

            <h3>EDXEIX preflight payload preview</h3>
            <pre class="payload"><?= mp_h(json_encode($selectedPreview['edxeix_preview_payload'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></pre>
        </section>
    <?php endif; ?>

    <section class="card">
        <h2>Recent mail intake rows</h2>
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>ID</th>
                    <th>Pickup</th>
                    <th>Customer</th>
                    <th>Driver / Vehicle</th>
                    <th>Route</th>
                    <th>Status</th>
                    <th>Linked</th>
                    <th>Hash</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($recentRows as $row): ?>
                    <tr>
                        <td>#<?= (int)$row['id'] ?></td>
                        <td class="nowrap"><?= mp_h($row['parsed_pickup_at'] ?? '') ?></td>
                        <td><?= mp_h($row['customer_name'] ?? '') ?><br><span class="muted small"><?= mp_h(mp_mask_phone($row['customer_mobile'] ?? '')) ?></span></td>
                        <td><?= mp_h($row['driver_name'] ?? '') ?><br><strong><?= mp_h($row['vehicle_plate'] ?? '') ?></strong></td>
                        <td><strong>From:</strong> <?= mp_h($row['pickup_address'] ?? '') ?><br><strong>To:</strong> <?= mp_h($row['dropoff_address'] ?? '') ?></td>
                        <td><?= mp_status_badge((string)($row['parse_status'] ?? '')) ?><?= mp_status_badge((string)($row['safety_status'] ?? '')) ?></td>
                        <td><?= !empty($row['linked_booking_id']) ? ('#' . (int)$row['linked_booking_id']) : '<span class="muted">—</span>' ?></td>
                        <td><code><?= mp_h(mp_short_hash($row['message_hash'] ?? '')) ?></code></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>
</body>
</html>
