<?php
/**
 * gov.cabnet.app — v4.7 Production Hardening / Launch Control Panel
 *
 * Read-only operational launch gate for the Bolt mail → driver notification
 * → dry-run evidence workflow.
 *
 * Safety contract:
 * - Does not import mail.
 * - Does not send driver emails.
 * - Does not create normalized bookings.
 * - Does not create dry-run evidence.
 * - Does not create submission_jobs.
 * - Does not create submission_attempts.
 * - Does not call Bolt.
 * - Does not call EDXEIX.
 * - Does not submit anything live.
 */

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Robots-Tag: noindex,nofollow', true);

$bootstrap = dirname(__DIR__, 3) . '/gov.cabnet.app_app/src/bootstrap.php';

function lr_h(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function lr_bool(mixed $value): bool
{
    if (is_bool($value)) {
        return $value;
    }
    return in_array(strtolower(trim((string)$value)), ['1', 'true', 'yes', 'on'], true);
}

function lr_authorized($config): bool
{
    $expected = (string)$config->get('app.internal_api_key', '');
    if ($expected === '' || str_starts_with($expected, 'REPLACE_WITH_')) {
        return false;
    }

    $provided = (string)($_GET['key'] ?? $_POST['key'] ?? ($_SERVER['HTTP_X_INTERNAL_API_KEY'] ?? ''));
    return $provided !== '' && hash_equals($expected, $provided);
}

function lr_json_response(array $payload, int $statusCode = 200): void
{
    if (!headers_sent()) {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('X-Robots-Tag: noindex,nofollow', true);
    }
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit;
}

function lr_badge(string $text, string $type = 'neutral'): string
{
    return '<span class="badge badge-' . lr_h($type) . '">' . lr_h($text) . '</span>';
}

function lr_bool_badge(bool $ok, string $yes = 'PASS', string $no = 'FAIL'): string
{
    return lr_badge($ok ? $yes : $no, $ok ? 'good' : 'bad');
}

function lr_metric(mixed $value, string $label): string
{
    return '<div class="metric"><strong>' . lr_h($value) . '</strong><span>' . lr_h($label) . '</span></div>';
}

function lr_current_key_query(array $extra = []): string
{
    $params = $extra;
    $key = (string)($_GET['key'] ?? '');
    if ($key !== '') {
        $params = array_merge(['key' => $key], $params);
    }
    return $params ? ('?' . http_build_query($params)) : '';
}

function lr_table_exists($db, string $table): bool
{
    $row = $db->fetchOne(
        'SELECT COUNT(*) AS c FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
        [$table]
    );
    return ((int)($row['c'] ?? 0)) > 0;
}

function lr_column_exists($db, string $table, string $column): bool
{
    $row = $db->fetchOne(
        'SELECT COUNT(*) AS c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
        [$table, $column]
    );
    return ((int)($row['c'] ?? 0)) > 0;
}

function lr_count($db, string $table, string $where = '1=1'): ?int
{
    if (!lr_table_exists($db, $table)) {
        return null;
    }
    $row = $db->fetchOne('SELECT COUNT(*) AS c FROM `' . str_replace('`', '``', $table) . '` WHERE ' . $where);
    return (int)($row['c'] ?? 0);
}

function lr_fetch_counts_by_status($db, string $table, string $statusColumn): array
{
    if (!lr_table_exists($db, $table) || !lr_column_exists($db, $table, $statusColumn)) {
        return [];
    }

    $rows = $db->fetchAll(
        'SELECT `' . str_replace('`', '``', $statusColumn) . '` AS s, COUNT(*) AS c FROM `' . str_replace('`', '``', $table) . '` GROUP BY `' . str_replace('`', '``', $statusColumn) . '` ORDER BY c DESC, s ASC'
    );
    $out = [];
    foreach ($rows as $row) {
        $out[(string)($row['s'] ?? '')] = (int)($row['c'] ?? 0);
    }
    return $out;
}

function lr_tail_lines(string $file, int $limit = 8): array
{
    if (!is_file($file) || !is_readable($file)) {
        return [];
    }

    $lines = file($file, FILE_IGNORE_NEW_LINES);
    if (!is_array($lines)) {
        return [];
    }

    return array_slice($lines, -$limit);
}

function lr_log_health(string $label, string $file, int $healthyWithinSeconds): array
{
    $exists = is_file($file);
    $readable = $exists && is_readable($file);
    $mtime = $exists ? (int)filemtime($file) : null;
    $age = $mtime ? max(0, time() - $mtime) : null;
    $healthy = $readable && $age !== null && $age <= $healthyWithinSeconds;

    return [
        'label' => $label,
        'file' => $file,
        'exists' => $exists,
        'readable' => $readable,
        'mtime' => $mtime ? date('Y-m-d H:i:s T', $mtime) : null,
        'age_seconds' => $age,
        'healthy_within_seconds' => $healthyWithinSeconds,
        'healthy' => $healthy,
        'tail' => lr_tail_lines($file, 8),
    ];
}

function lr_mask_email(?string $email): string
{
    $email = trim((string)$email);
    if ($email === '' || !str_contains($email, '@')) {
        return '';
    }
    [$local, $domain] = explode('@', $email, 2);
    $prefix = substr($local, 0, min(2, strlen($local)));
    return $prefix . '•••@' . $domain;
}

function lr_render_kv(string $k, string $v): string
{
    return '<div class="k">' . lr_h($k) . '</div><div>' . $v . '</div>';
}

$format = strtolower(trim((string)($_GET['format'] ?? 'html')));
$error = null;
$app = null;
$config = null;
$db = null;
$authorized = false;
$payload = [];
$latestIntake = [];
$latestNotifications = [];
$latestEvidence = [];
$driverRows = [];

try {
    if (!is_file($bootstrap) || !is_readable($bootstrap)) {
        throw new RuntimeException('Missing bootstrap file: ' . $bootstrap);
    }

    $app = require $bootstrap;
    $config = $app['config'];
    $db = $app['db'];
    $authorized = lr_authorized($config);

    if (!$authorized) {
        if ($format === 'json') {
            lr_json_response(['ok' => false, 'error' => 'forbidden'], 403);
        }
        http_response_code(403);
        echo '<!doctype html><meta charset="utf-8"><title>Forbidden</title><h1>Forbidden</h1><p>Missing or invalid internal key.</p>';
        exit;
    }

    $dryRun = lr_bool($config->get('app.dry_run', false));
    $liveSubmitEnabled = lr_bool($config->get('edxeix.live_submit_enabled', false));
    $guardMinutes = (int)$config->get('edxeix.future_start_guard_minutes', 0);
    $timezone = (string)$config->get('app.timezone', date_default_timezone_get());
    $driverNotifications = $config->get('mail.driver_notifications', []);
    $driverNotificationsEnabled = is_array($driverNotifications) && lr_bool($driverNotifications['enabled'] ?? false);
    $resolveDriverDirectory = is_array($driverNotifications) && lr_bool($driverNotifications['resolve_from_bolt_driver_directory'] ?? false);
    $maildir = (string)$config->get('mail.bolt_bridge_maildir', '/home/cabnet/mail/gov.cabnet.app/bolt-bridge');

    $logsDir = (string)$config->get('paths.logs', '/home/cabnet/gov.cabnet.app_app/storage/logs');
    $logs = [
        'mail_intake' => lr_log_health('Mail intake cron', rtrim($logsDir, '/') . '/bolt_mail_intake.log', 180),
        'auto_dry_run' => lr_log_health('Auto dry-run cron', rtrim($logsDir, '/') . '/bolt_mail_auto_dry_run.log', 180),
        'driver_directory' => lr_log_health('Driver directory sync cron', rtrim($logsDir, '/') . '/bolt_driver_directory_sync.log', 1800),
    ];

    $tables = [
        'bolt_mail_intake' => lr_table_exists($db, 'bolt_mail_intake'),
        'normalized_bookings' => lr_table_exists($db, 'normalized_bookings'),
        'bolt_mail_dry_run_evidence' => lr_table_exists($db, 'bolt_mail_dry_run_evidence'),
        'bolt_mail_driver_notifications' => lr_table_exists($db, 'bolt_mail_driver_notifications'),
        'mapping_drivers' => lr_table_exists($db, 'mapping_drivers'),
        'submission_jobs' => lr_table_exists($db, 'submission_jobs'),
        'submission_attempts' => lr_table_exists($db, 'submission_attempts'),
    ];

    $columns = [
        'mapping_drivers.external_driver_name' => $tables['mapping_drivers'] && lr_column_exists($db, 'mapping_drivers', 'external_driver_name'),
        'mapping_drivers.driver_identifier' => $tables['mapping_drivers'] && lr_column_exists($db, 'mapping_drivers', 'driver_identifier'),
        'mapping_drivers.driver_email' => $tables['mapping_drivers'] && lr_column_exists($db, 'mapping_drivers', 'driver_email'),
        'bolt_mail_driver_notifications.recipient_email' => $tables['bolt_mail_driver_notifications'] && lr_column_exists($db, 'bolt_mail_driver_notifications', 'recipient_email'),
        'bolt_mail_driver_notifications.notification_status' => $tables['bolt_mail_driver_notifications'] && lr_column_exists($db, 'bolt_mail_driver_notifications', 'notification_status'),
    ];

    $counts = [
        'submission_jobs' => lr_count($db, 'submission_jobs'),
        'submission_attempts' => lr_count($db, 'submission_attempts'),
        'bolt_mail_bookings' => lr_count($db, 'normalized_bookings', "source='bolt_mail'"),
        'dry_run_evidence' => lr_count($db, 'bolt_mail_dry_run_evidence'),
        'driver_notifications' => lr_count($db, 'bolt_mail_driver_notifications'),
        'bolt_mail_intake' => lr_count($db, 'bolt_mail_intake'),
    ];

    $intakeStatusCounts = lr_fetch_counts_by_status($db, 'bolt_mail_intake', 'safety_status');
    $notificationStatusCounts = lr_fetch_counts_by_status($db, 'bolt_mail_driver_notifications', 'notification_status');
    $evidenceStatusCounts = lr_fetch_counts_by_status($db, 'bolt_mail_dry_run_evidence', 'evidence_status');

    $driverDirectory = ['total' => 0, 'with_email' => 0, 'with_name_and_email' => 0, 'with_identifier_and_email' => 0, 'coverage_percent' => 0];
    $driverRows = [];
    if ($tables['mapping_drivers']) {
        $driverDirectory['total'] = (int)(lr_count($db, 'mapping_drivers') ?? 0);
        if ($columns['mapping_drivers.driver_email']) {
            $driverDirectory['with_email'] = (int)(lr_count($db, 'mapping_drivers', "driver_email IS NOT NULL AND driver_email <> ''") ?? 0);
        }
        if ($columns['mapping_drivers.external_driver_name'] && $columns['mapping_drivers.driver_email']) {
            $driverDirectory['with_name_and_email'] = (int)(lr_count($db, 'mapping_drivers', "external_driver_name IS NOT NULL AND external_driver_name <> '' AND driver_email IS NOT NULL AND driver_email <> ''") ?? 0);
        }
        if ($columns['mapping_drivers.driver_identifier'] && $columns['mapping_drivers.driver_email']) {
            $driverDirectory['with_identifier_and_email'] = (int)(lr_count($db, 'mapping_drivers', "driver_identifier IS NOT NULL AND driver_identifier <> '' AND driver_email IS NOT NULL AND driver_email <> ''") ?? 0);
        }
        $driverDirectory['coverage_percent'] = $driverDirectory['total'] > 0 ? round(($driverDirectory['with_name_and_email'] / $driverDirectory['total']) * 100, 1) : 0;

        if ($columns['mapping_drivers.external_driver_name'] && $columns['mapping_drivers.driver_email']) {
            $driverRows = $db->fetchAll(
                "SELECT id, external_driver_name, driver_identifier, driver_email, last_seen_at
                 FROM mapping_drivers
                 WHERE driver_email IS NOT NULL AND driver_email <> ''
                 ORDER BY last_seen_at DESC, external_driver_name ASC
                 LIMIT 20"
            );
        }
    }

    $latestIntake = [];
    if ($tables['bolt_mail_intake']) {
        $latestIntake = $db->fetchAll(
            "SELECT id, customer_name, driver_name, vehicle_plate, pickup_address, dropoff_address, parsed_pickup_at, parse_status, safety_status, linked_booking_id, created_at
             FROM bolt_mail_intake
             ORDER BY id DESC
             LIMIT 10"
        );
    }

    $latestNotifications = [];
    if ($tables['bolt_mail_driver_notifications']) {
        $latestNotifications = $db->fetchAll(
            "SELECT id, intake_id, driver_name, vehicle_plate, recipient_email, notification_status, skip_reason, error_message, sent_at, created_at
             FROM bolt_mail_driver_notifications
             ORDER BY id DESC
             LIMIT 10"
        );
    }

    $latestEvidence = [];
    if ($tables['bolt_mail_dry_run_evidence']) {
        $latestEvidence = $db->fetchAll(
            "SELECT id, normalized_booking_id, intake_id, source, evidence_status, payload_hash, created_by, created_at
             FROM bolt_mail_dry_run_evidence
             ORDER BY id DESC
             LIMIT 10"
        );
    }

    $jobsZero = ($counts['submission_jobs'] ?? 0) === 0;
    $attemptsZero = ($counts['submission_attempts'] ?? 0) === 0;
    $logsHealthy = $logs['mail_intake']['healthy'] && $logs['auto_dry_run']['healthy'] && $logs['driver_directory']['healthy'];
    $driverDirectoryReady = $driverDirectory['with_name_and_email'] > 0 && $columns['mapping_drivers.driver_email'] && $columns['mapping_drivers.external_driver_name'];
    $maildirReady = is_dir($maildir) && is_readable($maildir);

    $gateChecks = [
        ['label' => 'Dry-run mode remains enabled', 'ok' => $dryRun, 'detail' => 'app.dry_run must stay true before live-submit design.'],
        ['label' => 'Live EDXEIX submit remains disabled', 'ok' => !$liveSubmitEnabled, 'detail' => 'edxeix.live_submit_enabled must remain false.'],
        ['label' => 'Submission jobs table is clean', 'ok' => $jobsZero, 'detail' => 'submission_jobs count must be 0.'],
        ['label' => 'Submission attempts table is clean', 'ok' => $attemptsZero, 'detail' => 'submission_attempts count must be 0.'],
        ['label' => 'Driver notifications are enabled', 'ok' => $driverNotificationsEnabled, 'detail' => 'Driver copy feature must be active.'],
        ['label' => 'Driver recipients resolve from driver identity directory', 'ok' => $resolveDriverDirectory && $driverDirectoryReady, 'detail' => 'mapping_drivers must contain driver names/identifiers and emails.'],
        ['label' => 'Maildir is readable', 'ok' => $maildirReady, 'detail' => $maildir],
        ['label' => 'Production cron logs are current', 'ok' => $logsHealthy, 'detail' => 'Mail intake, auto dry-run, and driver sync logs must be fresh.'],
        ['label' => 'Credential rotation is manually required before live submit', 'ok' => false, 'manual' => true, 'detail' => 'Rotate ops key, Bolt credentials, EDXEIX credentials/session, and mailbox-related credentials before any live-submit phase.'],
    ];

    $automaticChecks = array_filter($gateChecks, static fn(array $check): bool => empty($check['manual']));
    $automaticPassed = true;
    foreach ($automaticChecks as $check) {
        if (empty($check['ok'])) {
            $automaticPassed = false;
            break;
        }
    }

    $verdict = $automaticPassed ? 'HARDENING_READY_DRY_RUN' : 'NEEDS_ATTENTION';
    $heroType = $automaticPassed ? 'good' : 'warn';
    $heroText = $automaticPassed
        ? 'Production hardening checks pass for dry-run operations. Live EDXEIX submission remains blocked.'
        : 'One or more production hardening checks need attention. Live EDXEIX submission remains blocked.';

    $payload = [
        'ok' => true,
        'script' => 'ops/launch-readiness.php',
        'generated_at' => date('c'),
        'verdict' => $verdict,
        'safety_contract' => [
            'read_only' => true,
            'imports_mail' => false,
            'sends_driver_email' => false,
            'creates_normalized_bookings' => false,
            'creates_dry_run_evidence' => false,
            'creates_submission_jobs' => false,
            'creates_submission_attempts' => false,
            'calls_bolt' => false,
            'calls_edxeix' => false,
            'live_edxeix_submission' => false,
        ],
        'config' => [
            'timezone' => $timezone,
            'dry_run' => $dryRun,
            'live_submit_enabled' => $liveSubmitEnabled,
            'future_start_guard_minutes' => $guardMinutes,
            'driver_notifications_enabled' => $driverNotificationsEnabled,
            'resolve_from_bolt_driver_directory' => $resolveDriverDirectory,
            'maildir' => $maildir,
            'maildir_ready' => $maildirReady,
        ],
        'counts' => $counts,
        'statuses' => [
            'bolt_mail_intake' => $intakeStatusCounts,
            'driver_notifications' => $notificationStatusCounts,
            'dry_run_evidence' => $evidenceStatusCounts,
        ],
        'schema' => [
            'tables' => $tables,
            'columns' => $columns,
        ],
        'driver_directory' => $driverDirectory,
        'logs' => $logs,
        'gate_checks' => $gateChecks,
    ];
} catch (Throwable $e) {
    $error = $e->getMessage();
    $payload = [
        'ok' => false,
        'script' => 'ops/launch-readiness.php',
        'generated_at' => date('c'),
        'error' => $error,
    ];
}

if ($format === 'json') {
    lr_json_response($payload, !empty($payload['ok']) ? 200 : 500);
}

$counts = $payload['counts'] ?? [];
$logs = $payload['logs'] ?? [];
$gateChecks = $payload['gate_checks'] ?? [];
$driverDirectory = $payload['driver_directory'] ?? [];
$configPayload = $payload['config'] ?? [];
$statuses = $payload['statuses'] ?? [];
$schema = $payload['schema'] ?? ['tables' => [], 'columns' => []];
$verdict = (string)($payload['verdict'] ?? 'ERROR');
$heroType = $heroType ?? 'bad';
$heroText = $heroText ?? 'Launch control panel could not load.';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Launch Readiness | gov.cabnet.app</title>
    <style>
        :root { --bg:#f3f6fb; --panel:#fff; --ink:#07152f; --muted:#465f86; --line:#d7e1ef; --nav:#081225; --blue:#2563eb; --green:#087a4d; --orange:#b85c00; --red:#b42318; --soft:#f8fbff; --purple:#6046a8; }
        *{box-sizing:border-box} body{margin:0;background:var(--bg);color:var(--ink);font-family:Arial,Helvetica,sans-serif}.top{background:var(--nav);color:#fff;min-height:58px;display:flex;gap:18px;align-items:center;padding:0 24px;overflow:auto;position:sticky;top:0;z-index:10}.top strong{white-space:nowrap}.top a{color:#fff;text-decoration:none;white-space:nowrap;font-size:14px;opacity:.92}.top a:hover{opacity:1;text-decoration:underline}.wrap{width:min(1500px,calc(100% - 42px));margin:24px auto 60px}.card{background:var(--panel);border:1px solid var(--line);border-radius:14px;padding:18px;margin-bottom:18px;box-shadow:0 10px 28px rgba(8,18,37,.04)}.hero{border-left:8px solid var(--orange)}.hero.good{border-left-color:var(--green)}.hero.bad{border-left-color:var(--red)}h1{margin:0 0 10px;font-size:32px}h2{margin:0 0 14px;font-size:22px}h3{margin:0 0 10px;font-size:17px}p{color:var(--muted);line-height:1.45}.grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}.two{display:grid;grid-template-columns:1fr 1fr;gap:18px}.three{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:18px}.metric{border:1px solid var(--line);border-radius:11px;background:var(--soft);padding:14px;min-height:78px}.metric strong{display:block;font-size:29px;line-height:1.05;word-break:break-word}.metric span{display:block;color:var(--muted);font-size:13px;margin-top:6px}.badge{display:inline-block;padding:5px 9px;border-radius:999px;font-size:12px;font-weight:800;margin:1px 4px 1px 0;white-space:nowrap}.badge-good{background:#dcfce7;color:#166534}.badge-warn{background:#fff7ed;color:#b45309}.badge-bad{background:#fee2e2;color:#991b1b}.badge-neutral{background:#eaf1ff;color:#1e40af}.badge-purple{background:#ede9fe;color:#5b21b6}.kv{display:grid;grid-template-columns:minmax(180px, 36%) 1fr;border:1px solid var(--line);border-radius:10px;overflow:hidden}.kv div{padding:10px 12px;border-bottom:1px solid var(--line)}.kv div:nth-last-child(-n+2){border-bottom:none}.kv .k{background:#f8fbff;color:var(--muted);font-weight:700}.checks{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}.check{border:1px solid var(--line);border-radius:12px;padding:13px;background:#fff}.check.good{border-left:6px solid var(--green)}.check.bad{border-left:6px solid var(--red)}.check.manual{border-left:6px solid var(--purple)}.check strong{display:block;margin-bottom:5px}.small{font-size:13px;color:var(--muted)}.actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:14px}.btn{display:inline-block;background:var(--blue);color:#fff;text-decoration:none;padding:10px 14px;border-radius:8px;font-weight:800;font-size:14px}.btn.dark{background:#334155}.btn.good{background:var(--green)}.btn.warn{background:var(--orange)}.btn.purple{background:var(--purple)}table{width:100%;border-collapse:collapse;font-size:13px}th,td{padding:9px;border-bottom:1px solid var(--line);text-align:left;vertical-align:top}th{background:#f8fbff;color:#29415f}.scroll{overflow:auto}.logbox{background:#081225;color:#dbeafe;border-radius:10px;padding:12px;min-height:110px;overflow:auto;font-family:Consolas,Menlo,monospace;font-size:12px;white-space:pre-wrap}.goodline{color:#166534}.warnline{color:#b45309}.badline{color:#991b1b}code{background:#eef2ff;padding:2px 5px;border-radius:5px}.safety{background:#ecfdf3;border:1px solid #bbf7d0;border-left:7px solid var(--green);border-radius:14px;padding:16px;margin-bottom:18px}.safety strong{color:#166534}@media(max-width:1100px){.grid,.three,.checks{grid-template-columns:repeat(2,minmax(0,1fr))}.two{grid-template-columns:1fr}}@media(max-width:720px){.wrap{width:calc(100% - 24px);margin-top:14px}.grid,.three,.checks{grid-template-columns:1fr}.kv{grid-template-columns:1fr}.kv .k{border-bottom:0}.top{padding:0 14px}}
    </style>
</head>
<body>
<nav class="top">
    <strong>gov.cabnet.app</strong>
    <a href="/ops/launch-readiness.php<?= lr_h(lr_current_key_query()) ?>">Launch Readiness</a>
    <a href="/ops/mail-status.php<?= lr_h(lr_current_key_query()) ?>">Mail Status</a>
    <a href="/ops/mail-driver-notifications.php<?= lr_h(lr_current_key_query()) ?>">Driver Notifications</a>
    <a href="/ops/mail-dry-run-evidence.php<?= lr_h(lr_current_key_query()) ?>">Dry-run Evidence</a>
    <a href="/ops/mail-auto-dry-run.php<?= lr_h(lr_current_key_query()) ?>">Auto Dry-run</a>
    <a href="/ops/home.php">Ops Home</a>
</nav>

<main class="wrap">
    <section class="safety">
        <strong>LIVE EDXEIX SUBMISSION IS OFF.</strong>
        This page is read-only. It does not import mail, send driver email, create bookings/evidence/jobs/attempts, call Bolt, call EDXEIX, or submit live.
    </section>

    <section class="card hero <?= lr_h($heroType) ?>">
        <h1>v4.7 Production Hardening / Launch Control Panel</h1>
        <p><?= lr_h($heroText) ?></p>
        <?php if ($error): ?><p class="badline"><strong>Error:</strong> <?= lr_h($error) ?></p><?php endif; ?>
        <div>
            <?= lr_badge($verdict, $verdict === 'HARDENING_READY_DRY_RUN' ? 'good' : 'warn') ?>
            <?= lr_badge('LIVE SUBMIT OFF', 'good') ?>
            <?= lr_badge('DRY-RUN ONLY', 'good') ?>
            <?= lr_badge('READ ONLY', 'good') ?>
        </div>
        <div class="grid" style="margin-top:14px">
            <?= lr_metric($counts['submission_jobs'] ?? 'n/a', 'submission_jobs') ?>
            <?= lr_metric($counts['submission_attempts'] ?? 'n/a', 'submission_attempts') ?>
            <?= lr_metric($counts['driver_notifications'] ?? 'n/a', 'driver notifications') ?>
            <?= lr_metric(($driverDirectory['with_name_and_email'] ?? 0) . '/' . ($driverDirectory['total'] ?? 0), 'driver identity email coverage') ?>
        </div>
        <div class="actions">
            <a class="btn good" href="/ops/launch-readiness.php<?= lr_h(lr_current_key_query(['format' => 'json'])) ?>">Open JSON</a>
            <a class="btn" href="/ops/mail-status.php<?= lr_h(lr_current_key_query()) ?>">Mail Status</a>
            <a class="btn purple" href="/ops/mail-driver-notifications.php<?= lr_h(lr_current_key_query()) ?>">Driver Notifications</a>
            <a class="btn dark" href="/ops/mail-dry-run-evidence.php<?= lr_h(lr_current_key_query()) ?>">Dry-run Evidence</a>
        </div>
    </section>

    <section class="card">
        <h2>Launch gate checks</h2>
        <div class="checks">
            <?php foreach ($gateChecks as $check):
                $manual = !empty($check['manual']);
                $ok = !empty($check['ok']);
                $class = $manual ? 'manual' : ($ok ? 'good' : 'bad');
            ?>
                <div class="check <?= lr_h($class) ?>">
                    <strong><?= lr_h((string)$check['label']) ?></strong>
                    <?= $manual ? lr_badge('MANUAL REQUIRED', 'purple') : lr_bool_badge($ok) ?>
                    <div class="small"><?= lr_h((string)($check['detail'] ?? '')) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="two">
        <div class="card">
            <h2>Config posture</h2>
            <div class="kv">
                <?= lr_render_kv('Timezone', lr_h((string)($configPayload['timezone'] ?? ''))) ?>
                <?= lr_render_kv('app.dry_run', lr_bool_badge(!empty($configPayload['dry_run']), 'true', 'false')) ?>
                <?= lr_render_kv('edxeix.live_submit_enabled', lr_bool_badge(empty($configPayload['live_submit_enabled']), 'false', 'true')) ?>
                <?= lr_render_kv('future_start_guard_minutes', lr_h((string)($configPayload['future_start_guard_minutes'] ?? ''))) ?>
                <?= lr_render_kv('driver_notifications.enabled', lr_bool_badge(!empty($configPayload['driver_notifications_enabled']), 'true', 'false')) ?>
                <?= lr_render_kv('resolve_from_bolt_driver_directory', lr_bool_badge(!empty($configPayload['resolve_from_bolt_driver_directory']), 'true', 'false')) ?>
                <?= lr_render_kv('maildir', lr_h((string)($configPayload['maildir'] ?? ''))) ?>
                <?= lr_render_kv('maildir readable', lr_bool_badge(!empty($configPayload['maildir_ready']))) ?>
            </div>
        </div>

        <div class="card">
            <h2>Database safety counts</h2>
            <div class="grid" style="grid-template-columns:repeat(2,minmax(0,1fr))">
                <?= lr_metric($counts['bolt_mail_intake'] ?? 'n/a', 'bolt_mail_intake rows') ?>
                <?= lr_metric($counts['bolt_mail_bookings'] ?? 'n/a', "normalized_bookings source='bolt_mail'") ?>
                <?= lr_metric($counts['dry_run_evidence'] ?? 'n/a', 'dry-run evidence') ?>
                <?= lr_metric($counts['driver_notifications'] ?? 'n/a', 'driver notifications') ?>
                <?= lr_metric($counts['submission_jobs'] ?? 'n/a', 'submission_jobs') ?>
                <?= lr_metric($counts['submission_attempts'] ?? 'n/a', 'submission_attempts') ?>
            </div>
        </div>
    </section>

    <section class="three">
        <div class="card">
            <h2>Intake statuses</h2>
            <?php if ($statuses['bolt_mail_intake'] ?? []): ?>
                <div class="kv">
                    <?php foreach (($statuses['bolt_mail_intake'] ?? []) as $status => $count): ?>
                        <?= lr_render_kv((string)$status, lr_h((string)$count)) ?>
                    <?php endforeach; ?>
                </div>
            <?php else: ?><p>No intake status rows found.</p><?php endif; ?>
        </div>
        <div class="card">
            <h2>Driver notification statuses</h2>
            <?php if ($statuses['driver_notifications'] ?? []): ?>
                <div class="kv">
                    <?php foreach (($statuses['driver_notifications'] ?? []) as $status => $count): ?>
                        <?= lr_render_kv((string)$status, lr_h((string)$count)) ?>
                    <?php endforeach; ?>
                </div>
            <?php else: ?><p>No driver notification rows found yet.</p><?php endif; ?>
        </div>
        <div class="card">
            <h2>Dry-run evidence statuses</h2>
            <?php if ($statuses['dry_run_evidence'] ?? []): ?>
                <div class="kv">
                    <?php foreach (($statuses['dry_run_evidence'] ?? []) as $status => $count): ?>
                        <?= lr_render_kv((string)$status, lr_h((string)$count)) ?>
                    <?php endforeach; ?>
                </div>
            <?php else: ?><p>No dry-run evidence rows found yet.</p><?php endif; ?>
        </div>
    </section>

    <section class="card">
        <h2>Cron health</h2>
        <div class="three">
            <?php foreach ($logs as $log): ?>
                <div class="card" style="margin-bottom:0">
                    <h3><?= lr_h((string)$log['label']) ?></h3>
                    <?= lr_bool_badge(!empty($log['healthy']), 'HEALTHY', 'CHECK') ?>
                    <p class="small"><strong>File:</strong> <?= lr_h((string)$log['file']) ?><br>
                    <strong>Modified:</strong> <?= lr_h((string)($log['mtime'] ?? 'missing')) ?><br>
                    <strong>Age:</strong> <?= lr_h((string)($log['age_seconds'] ?? 'n/a')) ?> seconds<br>
                    <strong>Healthy window:</strong> <?= lr_h((string)$log['healthy_within_seconds']) ?> seconds</p>
                    <div class="logbox"><?php foreach (($log['tail'] ?? []) as $line): ?><?= lr_h((string)$line) . "\n" ?><?php endforeach; ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="two">
        <div class="card">
            <h2>Driver directory coverage</h2>
            <div class="grid" style="grid-template-columns:repeat(2,minmax(0,1fr))">
                <?= lr_metric($driverDirectory['total'] ?? 0, 'mapping_drivers total') ?>
                <?= lr_metric($driverDirectory['with_email'] ?? 0, 'with email') ?>
                <?= lr_metric($driverDirectory['with_name_and_email'] ?? 0, 'with name + email') ?>
                <?= lr_metric(($driverDirectory['coverage_percent'] ?? 0) . '%', 'name/email coverage') ?>
            </div>
            <div class="scroll" style="margin-top:14px">
                <table>
                    <thead><tr><th>ID</th><th>Driver name</th><th>Driver identifier</th><th>Masked email</th><th>Last seen</th></tr></thead>
                    <tbody>
                    <?php foreach ($driverRows as $r): ?>
                        <tr>
                            <td><?= lr_h($r['id'] ?? '') ?></td>
                            <td><?= lr_h($r['external_driver_name'] ?? '') ?></td>
                            <td><?= lr_h($r['driver_identifier'] ?? '') ?></td>
                            <td><?= lr_h(lr_mask_email($r['driver_email'] ?? '')) ?></td>
                            <td><?= lr_h($r['last_seen_at'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <h2>Schema readiness</h2>
            <h3>Tables</h3>
            <div>
                <?php foreach (($schema['tables'] ?? []) as $name => $ok): ?>
                    <?= lr_badge($name . ': ' . ($ok ? 'yes' : 'no'), $ok ? 'good' : 'bad') ?>
                <?php endforeach; ?>
            </div>
            <h3 style="margin-top:16px">Columns</h3>
            <div>
                <?php foreach (($schema['columns'] ?? []) as $name => $ok): ?>
                    <?= lr_badge($name . ': ' . ($ok ? 'yes' : 'no'), $ok ? 'good' : 'bad') ?>
                <?php endforeach; ?>
            </div>
            <p class="small">This panel verifies that driver identity email resolution can use driver name/identifier + email. Vehicle plate is context only, not the recipient resolver.</p>
        </div>
    </section>

    <section class="card">
        <h2>Latest imported Bolt mail rows</h2>
        <div class="scroll">
            <table>
                <thead><tr><th>ID</th><th>Customer</th><th>Driver</th><th>Plate</th><th>Pickup</th><th>Drop-off</th><th>Pickup time</th><th>Parse</th><th>Safety</th><th>Linked booking</th><th>Created</th></tr></thead>
                <tbody>
                <?php foreach ($latestIntake as $r): ?>
                    <tr>
                        <td><?= lr_h($r['id'] ?? '') ?></td>
                        <td><?= lr_h($r['customer_name'] ?? '') ?></td>
                        <td><?= lr_h($r['driver_name'] ?? '') ?></td>
                        <td><?= lr_h($r['vehicle_plate'] ?? '') ?></td>
                        <td><?= lr_h($r['pickup_address'] ?? '') ?></td>
                        <td><?= lr_h($r['dropoff_address'] ?? '') ?></td>
                        <td><?= lr_h($r['parsed_pickup_at'] ?? '') ?></td>
                        <td><?= lr_h($r['parse_status'] ?? '') ?></td>
                        <td><?= lr_h($r['safety_status'] ?? '') ?></td>
                        <td><?= lr_h($r['linked_booking_id'] ?? '') ?></td>
                        <td><?= lr_h($r['created_at'] ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="two">
        <div class="card">
            <h2>Latest driver notifications</h2>
            <div class="scroll">
                <table>
                    <thead><tr><th>ID</th><th>Intake</th><th>Driver</th><th>Plate</th><th>Recipient</th><th>Status</th><th>Skip/Error</th><th>Sent</th></tr></thead>
                    <tbody>
                    <?php foreach ($latestNotifications as $r): ?>
                        <tr>
                            <td><?= lr_h($r['id'] ?? '') ?></td>
                            <td><?= lr_h($r['intake_id'] ?? '') ?></td>
                            <td><?= lr_h($r['driver_name'] ?? '') ?></td>
                            <td><?= lr_h($r['vehicle_plate'] ?? '') ?></td>
                            <td><?= lr_h(lr_mask_email($r['recipient_email'] ?? '')) ?></td>
                            <td><?= lr_h($r['notification_status'] ?? '') ?></td>
                            <td><?= lr_h(($r['skip_reason'] ?? '') ?: ($r['error_message'] ?? '')) ?></td>
                            <td><?= lr_h($r['sent_at'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <h2>Latest dry-run evidence</h2>
            <div class="scroll">
                <table>
                    <thead><tr><th>ID</th><th>Booking</th><th>Intake</th><th>Source</th><th>Status</th><th>Hash</th><th>By</th><th>Created</th></tr></thead>
                    <tbody>
                    <?php foreach ($latestEvidence as $r): ?>
                        <tr>
                            <td><?= lr_h($r['id'] ?? '') ?></td>
                            <td><?= lr_h($r['normalized_booking_id'] ?? '') ?></td>
                            <td><?= lr_h($r['intake_id'] ?? '') ?></td>
                            <td><?= lr_h($r['source'] ?? '') ?></td>
                            <td><?= lr_h($r['evidence_status'] ?? '') ?></td>
                            <td><?= lr_h(substr((string)($r['payload_hash'] ?? ''), 0, 16)) ?>…</td>
                            <td><?= lr_h($r['created_by'] ?? '') ?></td>
                            <td><?= lr_h($r['created_at'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <section class="card">
        <h2>Production launch notes</h2>
        <p><strong>v4.6 is conditionally complete.</strong> A paid real future Bolt ride could not be used for the future-candidate evidence gate. Real mail import, past/too-late blocking, driver identity email copy, driver copy formatting, Bolt driver directory sync, and zero EDXEIX job/attempt behavior have been validated.</p>
        <p><strong>v4.7 does not enable live submit.</strong> Before any v5 live-submit design, rotate all exposed credentials and keep this panel green under normal cron operation.</p>
        <div class="actions">
            <a class="btn warn" href="/ops/mail-synthetic-test.php<?= lr_h(lr_current_key_query()) ?>">Synthetic Future Test</a>
            <a class="btn dark" href="/bolt_edxeix_preflight.php?limit=30">Raw Preflight JSON</a>
        </div>
    </section>
</main>
</body>
</html>
