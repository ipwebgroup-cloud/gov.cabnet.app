<?php
/**
 * gov.cabnet.app — Bolt synthetic mail test harness
 *
 * Purpose:
 * - Generate a synthetic Bolt Ride details email directly in the bolt-bridge Maildir.
 * - Optionally import it immediately into bolt_mail_intake.
 * - Close unlinked synthetic rows after testing.
 *
 * Safety:
 * - Does not call Bolt.
 * - Does not call EDXEIX.
 * - Does not create submission jobs.
 * - Does not submit live.
 */

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Robots-Tag: noindex,nofollow', true);

$container = require '/home/cabnet/gov.cabnet.app_app/src/bootstrap.php';

use Bridge\Mail\BoltMaildirScanner;
use Bridge\Mail\BoltPreRideImporter;
use Bridge\Mail\BoltSyntheticMailFactory;

$config = $container['config'];
$db = $container['db'];

function st_h(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function st_badge(string $text, string $type = 'neutral'): string
{
    return '<span class="badge badge-' . st_h($type) . '">' . st_h($text) . '</span>';
}

function st_mask_mobile(?string $mobile): string
{
    $mobile = (string)$mobile;
    if ($mobile === '') {
        return '';
    }
    return substr($mobile, 0, 4) . '••••' . substr($mobile, -4);
}

function st_int(mixed $value, int $default, int $min, int $max): int
{
    $int = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['default' => $default]]);
    return max($min, min($max, (int)$int));
}

$expectedKey = (string)$config->get('app.internal_api_key', '');
$key = (string)($_GET['key'] ?? $_POST['key'] ?? '');
if ($expectedKey === '' || !hash_equals($expectedKey, $key)) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

$maildir = (string)$config->get('mail.bolt_bridge_maildir', '/home/cabnet/mail/gov.cabnet.app/bolt-bridge');
$timezone = new DateTimeZone((string)$config->get('app.timezone', 'Europe/Athens'));
$futureGuard = (int)$config->get('edxeix.future_start_guard_minutes', 2);
$liveSubmitEnabled = !empty($config->get('edxeix.live_submit_enabled', false));

$messages = [];
$errors = [];
$created = null;
$importResult = null;

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = (string)($_POST['action'] ?? '');

        if ($action === 'generate') {
            $factory = new BoltSyntheticMailFactory($maildir, $timezone);
            $created = $factory->create([
                'lead_minutes' => st_int($_POST['lead_minutes'] ?? 15, 15, 3, 1440),
                'duration_minutes' => st_int($_POST['duration_minutes'] ?? 30, 30, 5, 240),
                'customer_name' => 'CABNET TEST DO NOT SUBMIT',
                'customer_mobile' => '+300000000000',
                'driver_name' => (string)($_POST['driver_name'] ?? 'Filippos Giannakopoulos'),
                'vehicle_plate' => (string)($_POST['vehicle_plate'] ?? 'EHA2545'),
                'pickup_address' => (string)($_POST['pickup_address'] ?? 'Mikonos 846 00, Greece'),
                'dropoff_address' => (string)($_POST['dropoff_address'] ?? 'Chora TEST, Mykonos Chora'),
                'estimated_price' => (string)($_POST['estimated_price'] ?? '0.00 eur'),
            ]);
            $messages[] = 'Synthetic Bolt Ride details email created in Maildir/new.';

            if (!empty($_POST['import_now'])) {
                $scanner = new BoltMaildirScanner($maildir);
                $importer = new BoltPreRideImporter($db, null, $timezone, $futureGuard);
                $importResult = $importer->importFromScanner($scanner, 250, 30);
                $messages[] = 'Importer ran immediately after synthetic email creation.';
            }
        } elseif ($action === 'close_synthetic') {
            $affected = $db->execute(
                "UPDATE bolt_mail_intake
                 SET safety_status='blocked_past',
                     rejection_reason='Synthetic CABNET test email closed manually; not a real Bolt ride.'
                 WHERE customer_name LIKE 'CABNET TEST%'
                   AND linked_booking_id IS NULL",
                []
            );
            $messages[] = 'Closed unlinked synthetic test rows: ' . $affected;
        }
    }
} catch (Throwable $e) {
    $errors[] = $e->getMessage();
}

$recentSynthetic = [];
try {
    $recentSynthetic = $db->fetchAll(
        "SELECT id, customer_name, customer_mobile, driver_name, vehicle_plate, pickup_address, dropoff_address,
                parsed_pickup_at, parse_status, safety_status, linked_booking_id, created_at
         FROM bolt_mail_intake
         WHERE customer_name LIKE 'CABNET TEST%'
         ORDER BY id DESC
         LIMIT 20"
    );
} catch (Throwable $e) {
    $errors[] = 'Could not load synthetic rows: ' . $e->getMessage();
}

$keyParam = rawurlencode($key);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Bolt Synthetic Mail Test | gov.cabnet.app</title>
    <style>
        :root{--bg:#f3f6fb;--panel:#fff;--ink:#061328;--muted:#334e78;--line:#cfe0f5;--nav:#071225;--green:#07875a;--red:#b42318;--orange:#b85c00;--blue:#2563eb;--soft:#f8fbff}*{box-sizing:border-box}body{margin:0;background:var(--bg);font-family:Arial,Helvetica,sans-serif;color:var(--ink)}.nav{background:var(--nav);color:#fff;padding:0 20px;height:52px;display:flex;align-items:center;gap:18px;position:sticky;top:0;z-index:2}.nav a{color:#fff;text-decoration:none;font-size:14px}.wrap{width:min(1440px,calc(100% - 32px));margin:24px auto 70px}.card{background:var(--panel);border:1px solid var(--line);border-radius:14px;padding:18px;margin-bottom:18px;box-shadow:0 10px 26px rgba(8,18,37,.04)}.hero{border-left:7px solid var(--green)}h1{margin:0 0 10px;font-size:30px}h2{margin:0 0 14px;font-size:22px}p{color:var(--muted);line-height:1.45}.badge{display:inline-block;border-radius:999px;padding:5px 9px;font-size:12px;font-weight:800;margin:2px 4px 2px 0}.badge-good{background:#dcfce7;color:#166534}.badge-warn{background:#fff7ed;color:#b45309}.badge-bad{background:#fee2e2;color:#991b1b}.badge-neutral{background:#eaf1ff;color:#1e40af}.grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.form-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}label{display:block;font-weight:800;font-size:13px;margin-bottom:6px}input,select{width:100%;padding:10px;border:1px solid var(--line);border-radius:8px;background:white}.actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:14px}.btn,button{border:0;border-radius:8px;padding:10px 14px;font-weight:800;text-decoration:none;color:#fff;background:var(--blue);cursor:pointer}.btn.good,button.good{background:var(--green)}.btn.warn,button.warn{background:var(--orange)}.btn.dark,button.dark{background:#26364f}.btn.bad,button.bad{background:var(--red)}table{width:100%;border-collapse:collapse;font-size:14px}th,td{border:1px solid var(--line);padding:8px;text-align:left;vertical-align:top}th{background:#f4f8ff}code,pre{background:#eef4ff;border-radius:6px;padding:2px 5px}pre{padding:12px;overflow:auto;background:#071225;color:#eaf1ff}.notice{border-left:5px solid var(--green);background:#f0fdf4}.error{border-left:5px solid var(--red);background:#fff1f2}.warnbox{border-left:5px solid var(--orange);background:#fff7ed}@media(max-width:900px){.grid,.form-grid{grid-template-columns:1fr}.wrap{width:calc(100% - 20px)}}
    </style>
</head>
<body>
<nav class="nav">
    <strong>GC gov.cabnet.app</strong>
    <a href="/ops/home.php">Ops Home</a>
    <a href="/ops/mail-status.php?key=<?= st_h($keyParam) ?>">Mail Status</a>
    <a href="/ops/mail-intake.php?key=<?= st_h($keyParam) ?>">Mail Intake</a>
    <a href="/ops/mail-preflight.php?key=<?= st_h($keyParam) ?>">Mail Preflight</a>
    <a href="/ops/mail-synthetic-test.php?key=<?= st_h($keyParam) ?>">Synthetic Test</a>
</nav>
<main class="wrap">
    <section class="card hero">
        <h1>Bolt Synthetic Mail Test</h1>
        <p>Generate a synthetic Bolt <strong>Ride details</strong> email directly in <code>bolt-bridge</code> Maildir. This avoids rider-app credit card transactions while testing parser, cron, future-candidate logic, and Mail Preflight.</p>
        <div>
            <?= st_badge('NO BOLT API CALL', 'good') ?>
            <?= st_badge('NO EDXEIX CALL', 'good') ?>
            <?= st_badge('NO SUBMISSION JOBS', 'good') ?>
            <?= st_badge('LIVE SUBMIT ' . ($liveSubmitEnabled ? 'ON' : 'OFF'), $liveSubmitEnabled ? 'bad' : 'good') ?>
            <?= st_badge('FUTURE GUARD ' . $futureGuard . ' MIN', 'neutral') ?>
        </div>
    </section>

    <?php foreach ($messages as $message): ?>
        <section class="card notice"><strong><?= st_h($message) ?></strong></section>
    <?php endforeach; ?>
    <?php foreach ($errors as $error): ?>
        <section class="card error"><strong><?= st_h($error) ?></strong></section>
    <?php endforeach; ?>

    <section class="card warnbox">
        <strong>Safety boundary:</strong> This tool creates synthetic Maildir files and may import them into <code>bolt_mail_intake</code>. It does not create <code>normalized_bookings</code> unless you later use Mail Preflight manually, and it never creates EDXEIX jobs or live submissions.
    </section>

    <section class="grid">
        <div class="card">
            <h2>Create synthetic Ride details email</h2>
            <form method="post">
                <input type="hidden" name="key" value="<?= st_h($key) ?>">
                <input type="hidden" name="action" value="generate">
                <div class="form-grid">
                    <div><label>Lead minutes</label><input type="number" name="lead_minutes" min="3" max="1440" value="15"></div>
                    <div><label>Duration minutes</label><input type="number" name="duration_minutes" min="5" max="240" value="30"></div>
                    <div><label>Estimated price</label><input name="estimated_price" value="0.00 eur"></div>
                    <div><label>Driver</label><input name="driver_name" value="Filippos Giannakopoulos"></div>
                    <div><label>Vehicle plate</label><input name="vehicle_plate" value="EHA2545"></div>
                    <div><label>Pickup</label><input name="pickup_address" value="Mikonos 846 00, Greece"></div>
                    <div style="grid-column:1 / -1"><label>Drop-off</label><input name="dropoff_address" value="Chora TEST, Mykonos Chora"></div>
                </div>
                <p><label><input type="checkbox" name="import_now" value="1" checked style="width:auto"> Import immediately after creating the synthetic email</label></p>
                <div class="actions"><button class="good" type="submit">Create synthetic email</button><a class="btn dark" href="/ops/mail-preflight.php?key=<?= st_h($keyParam) ?>">Open Mail Preflight</a></div>
            </form>
        </div>

        <div class="card">
            <h2>Close synthetic test rows</h2>
            <p>Use this after testing to make unlinked synthetic rows non-actionable.</p>
            <form method="post" onsubmit="return confirm('Close all unlinked CABNET TEST rows as blocked_past?');">
                <input type="hidden" name="key" value="<?= st_h($key) ?>">
                <input type="hidden" name="action" value="close_synthetic">
                <button class="warn" type="submit">Close unlinked synthetic rows</button>
            </form>
            <div class="actions">
                <a class="btn" href="/ops/mail-status.php?key=<?= st_h($keyParam) ?>">Mail Status</a>
                <a class="btn dark" href="/ops/mail-intake.php?key=<?= st_h($keyParam) ?>">Mail Intake</a>
            </div>
        </div>
    </section>

    <?php if (is_array($created)): ?>
        <section class="card">
            <h2>Created synthetic email</h2>
            <pre><?= st_h(json_encode($created, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)) ?></pre>
        </section>
    <?php endif; ?>

    <?php if (is_array($importResult)): ?>
        <section class="card">
            <h2>Immediate import result</h2>
            <pre><?= st_h(json_encode($importResult, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)) ?></pre>
        </section>
    <?php endif; ?>

    <section class="card">
        <h2>Recent synthetic intake rows</h2>
        <table>
            <thead><tr><th>ID</th><th>Pickup</th><th>Customer</th><th>Driver / Vehicle</th><th>Route</th><th>Status</th><th>Linked booking</th></tr></thead>
            <tbody>
            <?php if (!$recentSynthetic): ?>
                <tr><td colspan="7">No synthetic rows found.</td></tr>
            <?php endif; ?>
            <?php foreach ($recentSynthetic as $row): ?>
                <tr>
                    <td>#<?= st_h($row['id'] ?? '') ?><br><small><?= st_h($row['created_at'] ?? '') ?></small></td>
                    <td><?= st_h($row['parsed_pickup_at'] ?? '') ?></td>
                    <td><?= st_h($row['customer_name'] ?? '') ?><br><small><?= st_h(st_mask_mobile($row['customer_mobile'] ?? '')) ?></small></td>
                    <td><?= st_h($row['driver_name'] ?? '') ?><br><strong><?= st_h($row['vehicle_plate'] ?? '') ?></strong></td>
                    <td><strong>From:</strong> <?= st_h($row['pickup_address'] ?? '') ?><br><strong>To:</strong> <?= st_h($row['dropoff_address'] ?? '') ?></td>
                    <td><?= st_badge((string)($row['parse_status'] ?? ''), 'good') ?> <?= st_badge((string)($row['safety_status'] ?? ''), ($row['safety_status'] ?? '') === 'future_candidate' ? 'warn' : 'bad') ?></td>
                    <td><?= $row['linked_booking_id'] ? ('#' . st_h($row['linked_booking_id'])) : '—' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>
</main>
</body>
</html>
