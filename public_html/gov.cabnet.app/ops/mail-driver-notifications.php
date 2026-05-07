<?php
/**
 * gov.cabnet.app — Bolt Mail Driver Notifications
 *
 * Read-only dashboard for the driver email copy layer.
 * Resolves recipients from the Bolt driver directory stored in mapping_drivers.driver_email.
 * Does not import emails, create EDXEIX jobs, or submit anything live.
 */

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Robots-Tag: noindex, nofollow', true);

$bootstrap = dirname(__DIR__, 3) . '/gov.cabnet.app_app/src/bootstrap.php';

function mdn_h(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function mdn_authorized($config): bool
{
    $expected = (string)$config->get('app.internal_api_key', '');
    if ($expected === '' || str_starts_with($expected, 'REPLACE_WITH_')) {
        return false;
    }
    $provided = (string)($_GET['key'] ?? $_POST['key'] ?? ($_SERVER['HTTP_X_INTERNAL_API_KEY'] ?? ''));
    return $provided !== '' && hash_equals($expected, $provided);
}

function mdn_badge(string $text, string $type = 'neutral'): string
{
    return '<span class="badge badge-' . mdn_h($type) . '">' . mdn_h($text) . '</span>';
}

function mdn_status_type(string $status): string
{
    return match ($status) {
        'sent' => 'good',
        'failed' => 'bad',
        'skipped' => 'warn',
        default => 'neutral',
    };
}

function mdn_mask_email(?string $email): string
{
    $email = trim((string)$email);
    if ($email === '' || !str_contains($email, '@')) {
        return '';
    }
    [$local, $domain] = explode('@', $email, 2);
    if (strlen($local) <= 2) {
        $localMasked = substr($local, 0, 1) . '•';
    } else {
        $localMasked = substr($local, 0, 2) . str_repeat('•', max(2, strlen($local) - 3)) . substr($local, -1);
    }
    return $localMasked . '@' . $domain;
}

$error = null;
$app = null;
$config = null;
$db = null;
$authorized = false;
$rows = [];
$counts = ['sent' => 0, 'skipped' => 0, 'failed' => 0, 'total' => 0];
$enabled = false;
$driverDirectoryReady = false;
$driverDirectoryCounts = ['total' => 0, 'with_email' => 0];
$driverDirectoryRows = [];

try {
    if (!file_exists($bootstrap)) {
        throw new RuntimeException('Missing bootstrap: ' . $bootstrap);
    }
    $app = require $bootstrap;
    $config = $app['config'];
    $db = $app['db'];
    $authorized = mdn_authorized($config);
    $driverNotificationConfig = $config->get('mail.driver_notifications', []);
    $enabled = is_array($driverNotificationConfig) && filter_var($driverNotificationConfig['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);

    if ($authorized) {
        $countRows = $db->fetchAll(
            "SELECT notification_status, COUNT(*) AS c FROM bolt_mail_driver_notifications GROUP BY notification_status"
        );
        foreach ($countRows as $r) {
            $status = (string)($r['notification_status'] ?? '');
            $c = (int)($r['c'] ?? 0);
            if (array_key_exists($status, $counts)) {
                $counts[$status] = $c;
            }
            $counts['total'] += $c;
        }

        $rows = $db->fetchAll(
            "SELECT n.id, n.intake_id, n.driver_name, n.vehicle_plate, n.recipient_email, n.email_subject, n.notification_status,
                    n.skip_reason, n.error_message, n.sent_at, n.created_at,
                    i.customer_name, i.pickup_address, i.dropoff_address, i.parsed_pickup_at, i.safety_status
             FROM bolt_mail_driver_notifications n
             LEFT JOIN bolt_mail_intake i ON i.id = n.intake_id
             ORDER BY n.id DESC
             LIMIT 100"
        );


        $driverColumns = [];
        foreach ($db->fetchAll("SHOW COLUMNS FROM mapping_drivers") as $colRow) {
            $field = (string)($colRow['Field'] ?? '');
            if ($field !== '') {
                $driverColumns[$field] = true;
            }
        }
        $driverDirectoryReady = isset($driverColumns['driver_email']);
        if ($driverDirectoryReady) {
            $directoryTotal = $db->fetchOne("SELECT COUNT(*) AS c FROM mapping_drivers");
            $directoryWithEmail = $db->fetchOne("SELECT COUNT(*) AS c FROM mapping_drivers WHERE driver_email IS NOT NULL AND driver_email <> ''");
            $driverDirectoryCounts['total'] = (int)($directoryTotal['c'] ?? 0);
            $driverDirectoryCounts['with_email'] = (int)($directoryWithEmail['c'] ?? 0);
            $driverDirectoryRows = $db->fetchAll(
                "SELECT id, external_driver_name, driver_phone, driver_email, active_vehicle_plate, last_seen_at, updated_at
                 FROM mapping_drivers
                 WHERE driver_email IS NOT NULL AND driver_email <> ''
                 ORDER BY COALESCE(last_seen_at, updated_at, created_at) DESC, id DESC
                 LIMIT 50"
            );
        }
    }
} catch (Throwable $e) {
    $error = $e->getMessage();
}

$keyValue = mdn_h((string)($_GET['key'] ?? ''));
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Bolt Driver Email Copies | gov.cabnet.app</title>
    <style>
        :root{--bg:#f3f6fb;--panel:#fff;--ink:#07152f;--muted:#41577a;--line:#d7e1ef;--nav:#081225;--blue:#2563eb;--green:#07875a;--orange:#b85c00;--red:#b42318;--soft:#f8fbff}*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--ink);font-family:Arial,Helvetica,sans-serif}.nav{background:var(--nav);color:#fff;min-height:56px;display:flex;align-items:center;gap:18px;padding:0 26px;position:sticky;top:0;z-index:5;overflow:auto}.nav strong{white-space:nowrap}.nav a{color:#fff;text-decoration:none;font-size:15px;white-space:nowrap;opacity:.92}.wrap{width:min(1500px,calc(100% - 42px));margin:24px auto 60px}.card{background:var(--panel);border:1px solid var(--line);border-radius:14px;padding:18px;margin-bottom:18px;box-shadow:0 10px 26px rgba(8,18,37,.04)}h1{font-size:32px;margin:0 0 10px}h2{font-size:21px;margin:0 0 12px}p{color:var(--muted);line-height:1.45}.banner{border-left:7px solid var(--green)}.warn{border-left:7px solid var(--orange)}.bad{border-left:7px solid var(--red)}.actions{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-top:14px}.btn{border:0;border-radius:8px;background:var(--blue);color:#fff;font-weight:700;padding:10px 14px;cursor:pointer;text-decoration:none;font-size:14px}.btn.light{background:#334155}.badge{display:inline-block;padding:5px 9px;border-radius:999px;font-size:12px;font-weight:700;margin:1px 3px 1px 0;white-space:nowrap}.badge-good{background:#dcfce7;color:#166534}.badge-warn{background:#fff7ed;color:#b45309}.badge-bad{background:#fee2e2;color:#991b1b}.badge-neutral{background:#eaf1ff;color:#1e40af}.grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}.metric{border:1px solid var(--line);border-radius:10px;padding:14px;background:var(--soft);min-height:76px}.metric strong{display:block;font-size:28px;line-height:1.05}.metric span{color:var(--muted);font-size:14px}table{width:100%;border-collapse:collapse;background:#fff;border:1px solid var(--line);border-radius:12px;overflow:hidden}th,td{border-bottom:1px solid var(--line);padding:9px 8px;text-align:left;vertical-align:top;font-size:14px}th{background:#f8fbff;color:#1f3353}tr:last-child td{border-bottom:0}.small{font-size:12px;color:var(--muted)}code{background:#eef2ff;padding:2px 5px;border-radius:5px}.table-wrap{overflow:auto}.mono{font-family:Consolas,Menlo,monospace}@media(max-width:900px){.grid{grid-template-columns:1fr 1fr}.wrap{width:calc(100% - 24px)}}@media(max-width:640px){.grid{grid-template-columns:1fr}.nav{padding:0 14px}}
    </style>
</head>
<body>
<nav class="nav">
    <strong>GC gov.cabnet.app</strong>
    <a href="/ops/home.php">Ops Home</a>
    <a href="/ops/mail-status.php?key=<?= $keyValue ?>">Mail Status</a>
    <a href="/ops/mail-intake.php?key=<?= $keyValue ?>">Mail Intake</a>
    <a href="/ops/mail-auto-dry-run.php?key=<?= $keyValue ?>">Auto Dry-run</a>
    <a href="/ops/mail-dry-run-evidence.php?key=<?= $keyValue ?>">Dry-run Evidence</a>
</nav>
<main class="wrap">
    <section class="card banner">
        <h1>Bolt Driver Email Copies</h1>
        <p>Read-only monitor for the driver notification layer. When enabled, each newly imported real Bolt pre-ride email can generate one driver email copy by matching the parsed driver name or vehicle plate to the Bolt driver directory stored in <code>mapping_drivers.driver_email</code>.</p>
        <div>
            <?= mdn_badge($enabled ? 'DRIVER COPIES ENABLED' : 'DRIVER COPIES DISABLED', $enabled ? 'good' : 'warn') ?>
            <?= mdn_badge('IDEMPOTENT PER INTAKE ROW', 'good') ?>
            <?= mdn_badge($driverDirectoryReady ? 'BOLT DIRECTORY READY' : 'BOLT DIRECTORY EMAIL COLUMN MISSING', $driverDirectoryReady ? 'good' : 'warn') ?>
            <?= mdn_badge('NO EDXEIX SUBMIT', 'good') ?>
        </div>
    </section>

    <?php if (!$authorized): ?>
        <section class="card warn">
            <h2>Access key required</h2>
            <p>Open this screen with the server-only internal key:</p>
            <p><code>https://gov.cabnet.app/ops/mail-driver-notifications.php?key=YOUR_INTERNAL_API_KEY</code></p>
            <p class="small">Do not paste the real key into chat.</p>
        </section>
    <?php endif; ?>

    <?php if ($error !== null): ?>
        <section class="card bad">
            <h2>Error</h2>
            <p><?= mdn_h($error) ?></p>
            <?php if (str_contains($error, 'bolt_mail_driver_notifications')): ?>
                <p>Run the SQL migration first: <code>/home/cabnet/gov.cabnet.app_sql/2026_05_07_bolt_mail_driver_notifications.sql</code></p>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <?php if ($authorized): ?>
        <section class="card">
            <h2>Notification counts</h2>
            <div class="grid">
                <div class="metric"><strong><?= mdn_h($counts['total']) ?></strong><span>Total records</span></div>
                <div class="metric"><strong><?= mdn_h($counts['sent']) ?></strong><span>Sent</span></div>
                <div class="metric"><strong><?= mdn_h($counts['skipped']) ?></strong><span>Skipped</span></div>
                <div class="metric"><strong><?= mdn_h($counts['failed']) ?></strong><span>Failed</span></div>
            </div>
            <p class="small">Driver email addresses are masked in this UI. Full addresses remain server-side only.</p>
        </section>

        <section class="card">
            <h2>Bolt driver directory email coverage</h2>
            <div class="grid">
                <div class="metric"><strong><?= mdn_h($driverDirectoryCounts['total']) ?></strong><span>Driver mappings</span></div>
                <div class="metric"><strong><?= mdn_h($driverDirectoryCounts['with_email']) ?></strong><span>With API email</span></div>
                <div class="metric"><strong><?= mdn_h($driverDirectoryReady ? 'YES' : 'NO') ?></strong><span>driver_email column</span></div>
                <div class="metric"><strong><?= mdn_h($enabled ? 'ON' : 'OFF') ?></strong><span>Notifications</span></div>
            </div>
            <p class="small">Run <code>/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/sync_bolt_driver_directory.php --hours=720</code> after installing the v4.5.1 SQL migration. The notification worker can also refresh this directory automatically on a miss when <code>sync_reference_on_miss=true</code>.</p>
            <?php if ($driverDirectoryRows): ?>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>ID</th><th>Driver</th><th>Plate</th><th>Email</th><th>Last seen</th></tr></thead>
                        <tbody>
                        <?php foreach ($driverDirectoryRows as $drow): ?>
                            <tr>
                                <td class="mono">#<?= mdn_h($drow['id'] ?? '') ?></td>
                                <td><?= mdn_h($drow['external_driver_name'] ?? '') ?><br><span class="small"><?= mdn_h($drow['driver_phone'] ?? '') ?></span></td>
                                <td><?= mdn_h($drow['active_vehicle_plate'] ?? '') ?></td>
                                <td><?= mdn_h(mdn_mask_email($drow['driver_email'] ?? '')) ?></td>
                                <td><span class="small"><?= mdn_h($drow['last_seen_at'] ?? ($drow['updated_at'] ?? '')) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <section class="card">
            <h2>Latest notification records</h2>
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>Intake</th>
                        <th>Status</th>
                        <th>Driver / Vehicle</th>
                        <th>Recipient</th>
                        <th>Ride</th>
                        <th>Reason / Error</th>
                        <th>Created / Sent</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="8" class="small">No driver notification records yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($rows as $row): ?>
                            <?php $status = (string)($row['notification_status'] ?? ''); ?>
                            <tr>
                                <td class="mono">#<?= mdn_h($row['id']) ?></td>
                                <td class="mono">#<?= mdn_h($row['intake_id']) ?></td>
                                <td><?= mdn_badge(strtoupper($status), mdn_status_type($status)) ?></td>
                                <td>
                                    <strong><?= mdn_h($row['driver_name'] ?? '') ?></strong><br>
                                    <span class="small"><?= mdn_h($row['vehicle_plate'] ?? '') ?></span>
                                </td>
                                <td><?= mdn_h(mdn_mask_email($row['recipient_email'] ?? '')) ?></td>
                                <td>
                                    <strong><?= mdn_h($row['parsed_pickup_at'] ?? '') ?></strong><br>
                                    <span class="small"><?= mdn_h($row['pickup_address'] ?? '') ?> → <?= mdn_h($row['dropoff_address'] ?? '') ?></span><br>
                                    <?= mdn_badge((string)($row['safety_status'] ?? ''), 'neutral') ?>
                                </td>
                                <td>
                                    <?php if (!empty($row['skip_reason'])): ?>
                                        <span class="small"><?= mdn_h($row['skip_reason']) ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($row['error_message'])): ?>
                                        <span class="small"><?= mdn_h($row['error_message']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="small">Created: <?= mdn_h($row['created_at'] ?? '') ?></span><br>
                                    <span class="small">Sent: <?= mdn_h($row['sent_at'] ?? '') ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endif; ?>
</main>
</body>
</html>
