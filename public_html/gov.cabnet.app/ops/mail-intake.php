<?php
/**
 * gov.cabnet.app — Bolt Mail Intake
 *
 * Production-safe mailbox scanner for bolt-bridge@gov.cabnet.app.
 * This page imports normalized fields into bolt_mail_intake only.
 * It does not create EDXEIX jobs and does not submit anything live.
 */

declare(strict_types=1);

use Bridge\Mail\BoltMaildirScanner;
use Bridge\Mail\BoltMailDriverNotificationService;
use Bridge\Mail\BoltPreRideEmailParser;
use Bridge\Mail\BoltPreRideImporter;

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Robots-Tag: noindex, nofollow', true);

$bootstrap = dirname(__DIR__, 3) . '/gov.cabnet.app_app/src/bootstrap.php';

function bmi_h(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function bmi_badge(string $text, string $type = 'neutral'): string
{
    return '<span class="badge badge-' . bmi_h($type) . '">' . bmi_h($text) . '</span>';
}

function bmi_mask_mobile(?string $mobile): string
{
    $mobile = trim((string)$mobile);
    if ($mobile === '') {
        return '';
    }
    $len = strlen($mobile);
    if ($len <= 5) {
        return str_repeat('•', $len);
    }
    return substr($mobile, 0, 4) . str_repeat('•', max(3, $len - 8)) . substr($mobile, -4);
}

function bmi_authorized($config): bool
{
    $expected = (string)$config->get('app.internal_api_key', '');
    if ($expected === '' || str_starts_with($expected, 'REPLACE_WITH_')) {
        return false;
    }

    $provided = (string)($_GET['key'] ?? $_POST['key'] ?? ($_SERVER['HTTP_X_INTERNAL_API_KEY'] ?? ''));
    return $provided !== '' && hash_equals($expected, $provided);
}

$error = null;
$app = null;
$db = null;
$config = null;
$authorized = false;
$summary = null;
$rows = [];
$maildir = '/home/cabnet/mail/gov.cabnet.app/bolt-bridge';

try {
    if (!file_exists($bootstrap)) {
        throw new RuntimeException('Missing bootstrap: ' . $bootstrap);
    }
    $app = require $bootstrap;
    $db = $app['db'];
    $config = $app['config'];
    $authorized = bmi_authorized($config);

    $configuredMaildir = (string)$config->get('mail.bolt_bridge_maildir', '');
    if ($configuredMaildir !== '') {
        $maildir = rtrim($configuredMaildir, '/');
    }

    $futureGuard = (int)$config->get('edxeix.future_start_guard_minutes', 30);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!$authorized) {
            throw new RuntimeException('Unauthorized. Add ?key=YOUR_INTERNAL_API_KEY from the server-only config.php. Do not paste the key into chat.');
        }
        $timezone = new DateTimeZone((string)$config->get('app.timezone', 'Europe/Athens'));
        $scanner = new BoltMaildirScanner($maildir);
        $parser = new BoltPreRideEmailParser($timezone);
        $driverNotificationConfig = $config->get('mail.driver_notifications', []);
        $driverNotifier = null;
        if (is_array($driverNotificationConfig) && filter_var($driverNotificationConfig['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $driverNotifier = new BoltMailDriverNotificationService($db, $driverNotificationConfig, $timezone);
        }
        $importer = new BoltPreRideImporter($db, $parser, $timezone, $futureGuard, $driverNotifier);
        $summary = $importer->importFromScanner($scanner, 250, 30);
    }

    if ($authorized) {
        $rows = $db->fetchAll(
            'SELECT id, source_basename, subject, sender_email, received_at, operator_raw, customer_name, customer_mobile, driver_name, vehicle_plate, pickup_address, dropoff_address, parsed_pickup_at, parsed_end_at, timezone_label, parse_status, safety_status, rejection_reason, created_at FROM bolt_mail_intake ORDER BY id DESC LIMIT 80'
        );
    }
} catch (Throwable $e) {
    $error = $e->getMessage();
}

$keyValue = bmi_h((string)($_GET['key'] ?? ''));
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Bolt Mail Intake | gov.cabnet.app</title>
    <style>
        :root{--bg:#f3f6fb;--panel:#fff;--ink:#07152f;--muted:#41577a;--line:#d7e1ef;--nav:#081225;--blue:#2563eb;--green:#07875a;--orange:#b85c00;--red:#b42318;--soft:#f8fbff}*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--ink);font-family:Arial,Helvetica,sans-serif}.nav{background:var(--nav);color:#fff;min-height:56px;display:flex;align-items:center;gap:18px;padding:0 26px;position:sticky;top:0;z-index:5;overflow:auto}.nav strong{white-space:nowrap}.nav a{color:#fff;text-decoration:none;font-size:15px;white-space:nowrap;opacity:.92}.wrap{width:min(1500px,calc(100% - 42px));margin:24px auto 60px}.card{background:var(--panel);border:1px solid var(--line);border-radius:14px;padding:18px;margin-bottom:18px;box-shadow:0 10px 26px rgba(8,18,37,.04)}h1{font-size:32px;margin:0 0 10px}h2{font-size:21px;margin:0 0 12px}p{color:var(--muted);line-height:1.45}.banner{border-left:7px solid var(--green)}.warn{border-left:7px solid var(--orange)}.bad{border-left:7px solid var(--red)}.actions{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-top:14px}.btn{border:0;border-radius:8px;background:var(--blue);color:#fff;font-weight:700;padding:10px 14px;cursor:pointer;text-decoration:none;font-size:14px}.btn.light{background:#334155}.btn.green{background:var(--green)}.badge{display:inline-block;padding:5px 9px;border-radius:999px;font-size:12px;font-weight:700;margin:1px 3px 1px 0;white-space:nowrap}.badge-good{background:#dcfce7;color:#166534}.badge-warn{background:#fff7ed;color:#b45309}.badge-bad{background:#fee2e2;color:#991b1b}.badge-neutral{background:#eaf1ff;color:#1e40af}.grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}.metric{border:1px solid var(--line);border-radius:10px;padding:14px;background:var(--soft);min-height:76px}.metric strong{display:block;font-size:28px;line-height:1.05}.metric span{color:var(--muted);font-size:14px}table{width:100%;border-collapse:collapse;background:#fff;border:1px solid var(--line);border-radius:12px;overflow:hidden}th,td{border-bottom:1px solid var(--line);padding:9px 8px;text-align:left;vertical-align:top;font-size:14px}th{background:#f8fbff;color:#1f3353}tr:last-child td{border-bottom:0}.small{font-size:12px;color:var(--muted)}code{background:#eef2ff;padding:2px 5px;border-radius:5px}.table-wrap{overflow:auto}.mono{font-family:Consolas,Menlo,monospace}@media(max-width:900px){.grid{grid-template-columns:1fr 1fr}.wrap{width:calc(100% - 24px)}}@media(max-width:640px){.grid{grid-template-columns:1fr}.nav{padding:0 14px}}
    </style>
</head>
<body>
<nav class="nav">
    <strong>GC gov.cabnet.app</strong>
    <a href="/ops/home.php">Ops Home</a>
    <a href="/ops/index.php">Guided Console</a>
    <a href="/ops/readiness.php">Readiness</a>
    <a href="/ops/jobs.php">Jobs</a>
    <a href="/bolt_edxeix_preflight.php">Preflight</a>
</nav>
<main class="wrap">
    <section class="card banner">
        <h1>Bolt Mail Intake</h1>
        <p>This page scans <code>bolt-bridge@gov.cabnet.app</code>, parses Bolt <strong>Ride details</strong> emails, and stores normalized intake rows only. It does not stage jobs and does not submit to EDXEIX.</p>
        <div>
            <?= bmi_badge('MAIL INTAKE ONLY', 'good') ?>
            <?= bmi_badge('LIVE SUBMIT OFF', 'good') ?>
            <?= bmi_badge('PII MASKED IN UI', 'good') ?>
        </div>
    </section>

    <?php if (!$authorized): ?>
        <section class="card warn">
            <h2>Access key required</h2>
            <p>For passenger-data safety, this screen requires the server-only <code>app.internal_api_key</code> as a URL parameter.</p>
            <p>Open it like this, replacing the value locally on your machine only:</p>
            <p><code>https://gov.cabnet.app/ops/mail-intake.php?key=YOUR_INTERNAL_API_KEY</code></p>
            <p class="small">Do not paste the real key into chat. The key should already exist in <code>/home/cabnet/gov.cabnet.app_config/config.php</code>.</p>
        </section>
    <?php endif; ?>

    <?php if ($error !== null): ?>
        <section class="card bad">
            <h2>Error</h2>
            <p><?= bmi_h($error) ?></p>
            <?php if (str_contains($error, 'bolt_mail_intake')): ?>
                <p>Run the SQL migration first: <code>/home/cabnet/gov.cabnet.app_sql/2026_05_05_bolt_mail_intake.sql</code></p>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <?php if ($authorized): ?>
        <section class="card">
            <h2>Mailbox scan</h2>
            <p>Mailbox path: <code><?= bmi_h($maildir) ?></code></p>
            <form method="post" class="actions">
                <input type="hidden" name="key" value="<?= $keyValue ?>">
                <button class="btn green" type="submit">Scan mailbox now</button>
                <a class="btn light" href="/ops/mail-intake.php?key=<?= $keyValue ?>">Refresh</a>
            </form>
        </section>

        <?php if (is_array($summary)): ?>
            <section class="card">
                <h2>Scan result</h2>
                <div class="grid">
                    <div class="metric"><strong><?= bmi_h($summary['files']) ?></strong><span>Candidate files</span></div>
                    <div class="metric"><strong><?= bmi_h($summary['inserted']) ?></strong><span>Inserted</span></div>
                    <div class="metric"><strong><?= bmi_h($summary['duplicates']) ?></strong><span>Duplicates</span></div>
                    <div class="metric"><strong><?= bmi_h($summary['errors']) ?></strong><span>Errors</span></div>
                    <div class="metric"><strong><?= bmi_h($summary['driver_notifications_sent'] ?? 0) ?></strong><span>Driver emails sent</span></div>
                    <div class="metric"><strong><?= bmi_h($summary['driver_notifications_skipped'] ?? 0) ?></strong><span>Driver emails skipped</span></div>
                    <div class="metric"><strong><?= bmi_h($summary['driver_notifications_failed'] ?? 0) ?></strong><span>Driver email failures</span></div>
                </div>
                <?php if (!empty($summary['items'])): ?>
                    <div class="table-wrap" style="margin-top:14px">
                        <table>
                            <thead><tr><th>File</th><th>Status</th><th>ID</th><th>Message</th></tr></thead>
                            <tbody>
                            <?php foreach ($summary['items'] as $item): ?>
                                <tr>
                                    <td class="mono small"><?= bmi_h($item['file'] ?? '') ?></td>
                                    <td><?= bmi_badge((string)($item['status'] ?? ''), ($item['status'] ?? '') === 'error' ? 'bad' : (($item['status'] ?? '') === 'duplicate' ? 'warn' : 'good')) ?></td>
                                    <td><?= bmi_h($item['id'] ?? '') ?></td>
                                    <td><?= bmi_h($item['message'] ?? '') ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <section class="card">
            <h2>Recent intake rows</h2>
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
                        <th>Reason</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="7">No intake rows yet.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($rows as $row): ?>
                        <?php
                        $safeType = match ((string)$row['safety_status']) {
                            'future_candidate' => 'good',
                            'blocked_past', 'blocked_too_soon' => 'bad',
                            default => 'warn',
                        };
                        $parseType = ((string)$row['parse_status']) === 'parsed' ? 'good' : (((string)$row['parse_status']) === 'rejected' ? 'bad' : 'warn');
                        ?>
                        <tr>
                            <td><?= bmi_h($row['id']) ?><br><span class="small"><?= bmi_h($row['created_at']) ?></span></td>
                            <td><?= bmi_h($row['parsed_pickup_at']) ?><br><span class="small"><?= bmi_h($row['timezone_label']) ?></span></td>
                            <td><?= bmi_h($row['customer_name']) ?><br><span class="small"><?= bmi_h(bmi_mask_mobile($row['customer_mobile'] ?? '')) ?></span></td>
                            <td><?= bmi_h($row['driver_name']) ?><br><strong><?= bmi_h($row['vehicle_plate']) ?></strong></td>
                            <td><strong>From:</strong> <?= bmi_h($row['pickup_address']) ?><br><strong>To:</strong> <?= bmi_h($row['dropoff_address']) ?></td>
                            <td><?= bmi_badge((string)$row['parse_status'], $parseType) ?><br><?= bmi_badge((string)$row['safety_status'], $safeType) ?></td>
                            <td><?= bmi_h($row['rejection_reason']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endif; ?>
</main>
</body>
</html>
