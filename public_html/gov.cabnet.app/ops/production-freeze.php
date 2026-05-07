<?php
/**
 * gov.cabnet.app — v4.9 Final Dry-run Production Freeze
 *
 * Read-only freeze status panel for the Bolt mail → driver notification
 * → normalized booking → dry-run evidence workflow.
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
 * - Does not write files.
 */

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Robots-Tag: noindex,nofollow', true);

$bootstrap = dirname(__DIR__, 3) . '/gov.cabnet.app_app/src/bootstrap.php';

function pf_h(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function pf_bool(mixed $value): bool
{
    if (is_bool($value)) {
        return $value;
    }
    return in_array(strtolower(trim((string)$value)), ['1', 'true', 'yes', 'on'], true);
}

function pf_authorized($config): bool
{
    $expected = (string)$config->get('app.internal_api_key', '');
    if ($expected === '' || str_starts_with($expected, 'REPLACE_WITH_')) {
        return false;
    }

    $provided = (string)($_GET['key'] ?? $_POST['key'] ?? ($_SERVER['HTTP_X_INTERNAL_API_KEY'] ?? ''));
    return $provided !== '' && hash_equals($expected, $provided);
}

function pf_json_response(array $payload, int $statusCode = 200): void
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

function pf_badge(string $text, string $type = 'neutral'): string
{
    return '<span class="badge badge-' . pf_h($type) . '">' . pf_h($text) . '</span>';
}

function pf_metric(mixed $value, string $label): string
{
    return '<div class="metric"><strong>' . pf_h($value) . '</strong><span>' . pf_h($label) . '</span></div>';
}

function pf_current_key_query(array $extra = []): string
{
    $params = $extra;
    $key = (string)($_GET['key'] ?? '');
    if ($key !== '') {
        $params = array_merge(['key' => $key], $params);
    }
    return $params ? ('?' . http_build_query($params)) : '';
}

function pf_table_exists($db, string $table): bool
{
    $row = $db->fetchOne(
        'SELECT COUNT(*) AS c FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
        [$table]
    );
    return ((int)($row['c'] ?? 0)) > 0;
}

function pf_column_exists($db, string $table, string $column): bool
{
    $row = $db->fetchOne(
        'SELECT COUNT(*) AS c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
        [$table, $column]
    );
    return ((int)($row['c'] ?? 0)) > 0;
}

function pf_count($db, string $table, string $where = '1=1'): ?int
{
    if (!pf_table_exists($db, $table)) {
        return null;
    }
    $safeTable = str_replace('`', '``', $table);
    $row = $db->fetchOne('SELECT COUNT(*) AS c FROM `' . $safeTable . '` WHERE ' . $where);
    return (int)($row['c'] ?? 0);
}

function pf_fetch_counts_by_status($db, string $table, string $statusColumn): array
{
    if (!pf_table_exists($db, $table) || !pf_column_exists($db, $table, $statusColumn)) {
        return [];
    }

    $safeTable = str_replace('`', '``', $table);
    $safeColumn = str_replace('`', '``', $statusColumn);
    $rows = $db->fetchAll('SELECT `' . $safeColumn . '` AS s, COUNT(*) AS c FROM `' . $safeTable . '` GROUP BY `' . $safeColumn . '` ORDER BY c DESC, s ASC');

    $out = [];
    foreach ($rows as $row) {
        $out[(string)($row['s'] ?? '')] = (int)($row['c'] ?? 0);
    }
    return $out;
}

function pf_tail_lines(string $file, int $limit = 8): array
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

function pf_log_health(string $label, string $file, int $healthyWithinSeconds): array
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
        'tail' => pf_tail_lines($file, 8),
    ];
}

function pf_read_json_marker(string $file): array
{
    $result = [
        'marker_file' => $file,
        'exists' => false,
        'readable' => false,
        'valid' => false,
        'data' => null,
        'error' => null,
    ];

    if (!is_file($file)) {
        return $result;
    }

    $result['exists'] = true;
    $result['readable'] = is_readable($file);
    if (!$result['readable']) {
        $result['error'] = 'marker_not_readable';
        return $result;
    }

    $raw = file_get_contents($file);
    $data = is_string($raw) ? json_decode($raw, true) : null;
    if (!is_array($data)) {
        $result['error'] = 'invalid_marker_json';
        return $result;
    }

    $result['valid'] = true;
    $result['data'] = $data;
    return $result;
}

function pf_credential_rotation_complete(string $markerFile): array
{
    $marker = pf_read_json_marker($markerFile);
    $required = ['ops_key', 'bolt_credentials', 'edxeix_credentials', 'mailbox_credentials'];
    $items = [
        'ops_key' => false,
        'bolt_credentials' => false,
        'edxeix_credentials' => false,
        'mailbox_credentials' => false,
    ];
    $complete = false;

    if (!empty($marker['valid']) && is_array($marker['data'])) {
        $rawItems = is_array($marker['data']['items'] ?? null) ? $marker['data']['items'] : [];
        $complete = true;
        foreach ($required as $key) {
            $items[$key] = !empty($rawItems[$key]);
            if (!$items[$key]) {
                $complete = false;
            }
        }
    }

    return [
        'marker_file' => $markerFile,
        'exists' => $marker['exists'],
        'readable' => $marker['readable'],
        'valid' => $marker['valid'],
        'complete' => $complete,
        'items' => $items,
        'completed_at' => is_array($marker['data']) ? ($marker['data']['completed_at'] ?? null) : null,
        'completed_by' => is_array($marker['data']) ? ($marker['data']['completed_by'] ?? null) : null,
        'error' => $marker['error'],
    ];
}

function pf_mask_email(?string $email): string
{
    $email = trim((string)$email);
    if ($email === '' || !str_contains($email, '@')) {
        return '';
    }
    [$local, $domain] = explode('@', $email, 2);
    $prefix = substr($local, 0, min(2, strlen($local)));
    return $prefix . '•••@' . $domain;
}

$format = strtolower(trim((string)($_GET['format'] ?? 'html')));
$error = null;
$payload = [];
$latestIntake = [];
$latestNotifications = [];
$latestEvidence = [];
$freezeMarker = [];

try {
    if (!is_file($bootstrap) || !is_readable($bootstrap)) {
        throw new RuntimeException('Missing bootstrap file: ' . $bootstrap);
    }

    $app = require $bootstrap;
    $config = $app['config'];
    $db = $app['db'];

    if (!pf_authorized($config)) {
        if ($format === 'json') {
            pf_json_response(['ok' => false, 'error' => 'forbidden'], 403);
        }
        http_response_code(403);
        echo '<!doctype html><meta charset="utf-8"><title>Forbidden</title><h1>Forbidden</h1><p>Missing or invalid internal key.</p>';
        exit;
    }

    $dryRun = pf_bool($config->get('app.dry_run', false));
    $liveSubmitEnabled = pf_bool($config->get('edxeix.live_submit_enabled', false));
    $guardMinutes = (int)$config->get('edxeix.future_start_guard_minutes', 0);
    $timezone = (string)$config->get('app.timezone', date_default_timezone_get());
    $maildir = (string)$config->get('mail.bolt_bridge_maildir', '/home/cabnet/mail/gov.cabnet.app/bolt-bridge');
    $driverNotifications = $config->get('mail.driver_notifications', []);
    $driverNotificationsEnabled = is_array($driverNotifications) && pf_bool($driverNotifications['enabled'] ?? false);
    $resolveDriverDirectory = is_array($driverNotifications) && pf_bool($driverNotifications['resolve_from_bolt_driver_directory'] ?? false);
    $logsDir = (string)$config->get('paths.logs', '/home/cabnet/gov.cabnet.app_app/storage/logs');
    $securityDir = dirname(__DIR__, 3) . '/gov.cabnet.app_app/storage/security';
    $credentialMarkerFile = $securityDir . '/credential_rotation_ack.json';
    $freezeMarkerFile = $securityDir . '/production_dry_run_freeze.json';

    $tables = [
        'bolt_mail_intake' => pf_table_exists($db, 'bolt_mail_intake'),
        'normalized_bookings' => pf_table_exists($db, 'normalized_bookings'),
        'bolt_mail_dry_run_evidence' => pf_table_exists($db, 'bolt_mail_dry_run_evidence'),
        'bolt_mail_driver_notifications' => pf_table_exists($db, 'bolt_mail_driver_notifications'),
        'mapping_drivers' => pf_table_exists($db, 'mapping_drivers'),
        'submission_jobs' => pf_table_exists($db, 'submission_jobs'),
        'submission_attempts' => pf_table_exists($db, 'submission_attempts'),
    ];

    $columns = [
        'mapping_drivers.external_driver_name' => $tables['mapping_drivers'] && pf_column_exists($db, 'mapping_drivers', 'external_driver_name'),
        'mapping_drivers.driver_identifier' => $tables['mapping_drivers'] && pf_column_exists($db, 'mapping_drivers', 'driver_identifier'),
        'mapping_drivers.driver_email' => $tables['mapping_drivers'] && pf_column_exists($db, 'mapping_drivers', 'driver_email'),
        'bolt_mail_driver_notifications.recipient_email' => $tables['bolt_mail_driver_notifications'] && pf_column_exists($db, 'bolt_mail_driver_notifications', 'recipient_email'),
        'bolt_mail_driver_notifications.notification_status' => $tables['bolt_mail_driver_notifications'] && pf_column_exists($db, 'bolt_mail_driver_notifications', 'notification_status'),
    ];

    $counts = [
        'submission_jobs' => pf_count($db, 'submission_jobs'),
        'submission_attempts' => pf_count($db, 'submission_attempts'),
        'bolt_mail_bookings' => pf_count($db, 'normalized_bookings', "source='bolt_mail'"),
        'dry_run_evidence' => pf_count($db, 'bolt_mail_dry_run_evidence'),
        'driver_notifications' => pf_count($db, 'bolt_mail_driver_notifications'),
        'bolt_mail_intake' => pf_count($db, 'bolt_mail_intake'),
    ];

    $statuses = [
        'bolt_mail_intake' => pf_fetch_counts_by_status($db, 'bolt_mail_intake', 'safety_status'),
        'driver_notifications' => pf_fetch_counts_by_status($db, 'bolt_mail_driver_notifications', 'notification_status'),
        'dry_run_evidence' => pf_fetch_counts_by_status($db, 'bolt_mail_dry_run_evidence', 'evidence_status'),
    ];

    $driverDirectory = ['total' => 0, 'with_email' => 0, 'with_name_and_email' => 0, 'with_identifier_and_email' => 0, 'coverage_percent' => 0];
    if ($tables['mapping_drivers']) {
        $driverDirectory['total'] = (int)(pf_count($db, 'mapping_drivers') ?? 0);
        if ($columns['mapping_drivers.driver_email']) {
            $driverDirectory['with_email'] = (int)(pf_count($db, 'mapping_drivers', "driver_email IS NOT NULL AND driver_email <> ''") ?? 0);
        }
        if ($columns['mapping_drivers.external_driver_name'] && $columns['mapping_drivers.driver_email']) {
            $driverDirectory['with_name_and_email'] = (int)(pf_count($db, 'mapping_drivers', "external_driver_name IS NOT NULL AND external_driver_name <> '' AND driver_email IS NOT NULL AND driver_email <> ''") ?? 0);
        }
        if ($columns['mapping_drivers.driver_identifier'] && $columns['mapping_drivers.driver_email']) {
            $driverDirectory['with_identifier_and_email'] = (int)(pf_count($db, 'mapping_drivers', "driver_identifier IS NOT NULL AND driver_identifier <> '' AND driver_email IS NOT NULL AND driver_email <> ''") ?? 0);
        }
        $driverDirectory['coverage_percent'] = $driverDirectory['total'] > 0 ? round(($driverDirectory['with_name_and_email'] / $driverDirectory['total']) * 100, 1) : 0;
    }

    $logs = [
        'mail_intake' => pf_log_health('Mail intake cron', rtrim($logsDir, '/') . '/bolt_mail_intake.log', 180),
        'auto_dry_run' => pf_log_health('Auto dry-run cron', rtrim($logsDir, '/') . '/bolt_mail_auto_dry_run.log', 180),
        'driver_directory' => pf_log_health('Driver directory sync cron', rtrim($logsDir, '/') . '/bolt_driver_directory_sync.log', 1800),
    ];

    if ($tables['bolt_mail_intake']) {
        $latestIntake = $db->fetchAll(
            "SELECT id, customer_name, driver_name, vehicle_plate, parsed_pickup_at, parse_status, safety_status, linked_booking_id, created_at
             FROM bolt_mail_intake
             ORDER BY id DESC
             LIMIT 10"
        );
    }

    if ($tables['bolt_mail_driver_notifications']) {
        $latestNotifications = $db->fetchAll(
            "SELECT id, intake_id, driver_name, vehicle_plate, recipient_email, notification_status, skip_reason, error_message, sent_at, created_at
             FROM bolt_mail_driver_notifications
             ORDER BY id DESC
             LIMIT 10"
        );
    }

    if ($tables['bolt_mail_dry_run_evidence']) {
        $latestEvidence = $db->fetchAll(
            "SELECT id, normalized_booking_id, intake_id, source, evidence_status, payload_hash, created_by, created_at
             FROM bolt_mail_dry_run_evidence
             ORDER BY id DESC
             LIMIT 10"
        );
    }

    $credentialRotation = pf_credential_rotation_complete($credentialMarkerFile);
    $freezeMarker = pf_read_json_marker($freezeMarkerFile);

    $jobsZero = ($counts['submission_jobs'] ?? 0) === 0;
    $attemptsZero = ($counts['submission_attempts'] ?? 0) === 0;
    $logsHealthy = $logs['mail_intake']['healthy'] && $logs['auto_dry_run']['healthy'] && $logs['driver_directory']['healthy'];
    $maildirReady = is_dir($maildir) && is_readable($maildir);
    $driverDirectoryReady = $driverDirectory['total'] > 0 && $driverDirectory['with_name_and_email'] === $driverDirectory['total'] && $driverDirectory['with_identifier_and_email'] === $driverDirectory['total'];
    $notificationProof = ($counts['driver_notifications'] ?? 0) > 0 && (int)($statuses['driver_notifications']['sent'] ?? 0) > 0;
    $dryRunEvidenceProof = ($counts['bolt_mail_bookings'] ?? 0) > 0 && ($counts['dry_run_evidence'] ?? 0) > 0 && (int)($statuses['dry_run_evidence']['recorded'] ?? 0) > 0;

    $gateChecks = [
        ['label' => 'Dry-run mode is enabled', 'ok' => $dryRun, 'detail' => 'app.dry_run must remain true for v4.9 freeze.'],
        ['label' => 'Live EDXEIX submit is disabled', 'ok' => !$liveSubmitEnabled, 'detail' => 'edxeix.live_submit_enabled must remain false.'],
        ['label' => 'Submission jobs are clean', 'ok' => $jobsZero, 'detail' => 'submission_jobs count must be 0.'],
        ['label' => 'Submission attempts are clean', 'ok' => $attemptsZero, 'detail' => 'submission_attempts count must be 0.'],
        ['label' => 'Driver identity directory is complete', 'ok' => $driverDirectoryReady, 'detail' => 'Every synced driver should have name, identifier, and email.'],
        ['label' => 'Driver notification proof exists', 'ok' => $notificationProof, 'detail' => 'At least one real driver copy must be recorded as sent.'],
        ['label' => 'Dry-run evidence proof exists', 'ok' => $dryRunEvidenceProof, 'detail' => 'At least one bolt_mail booking and dry-run evidence row must exist.'],
        ['label' => 'Production crons are healthy', 'ok' => $logsHealthy, 'detail' => 'Mail intake, auto dry-run, and driver sync logs must be fresh.'],
        ['label' => 'Maildir is readable', 'ok' => $maildirReady, 'detail' => $maildir],
        ['label' => 'Credential rotation acknowledged', 'ok' => !empty($credentialRotation['complete']), 'manual' => true, 'detail' => 'Required before any live-submit phase; not required to freeze dry-run posture.'],
    ];

    $automaticPassed = true;
    foreach ($gateChecks as $check) {
        if (!empty($check['manual'])) {
            continue;
        }
        if (empty($check['ok'])) {
            $automaticPassed = false;
            break;
        }
    }

    $freezeExists = !empty($freezeMarker['valid']);
    $credentialComplete = !empty($credentialRotation['complete']);
    if (!$automaticPassed) {
        $verdict = 'FREEZE_NOT_READY';
    } elseif ($freezeExists && $credentialComplete) {
        $verdict = 'DRY_RUN_PRODUCTION_FROZEN_CREDENTIALS_ROTATED';
    } elseif ($freezeExists) {
        $verdict = 'DRY_RUN_PRODUCTION_FROZEN_CREDENTIAL_ROTATION_PENDING';
    } else {
        $verdict = 'DRY_RUN_FREEZE_READY';
    }

    $payload = [
        'ok' => true,
        'script' => 'ops/production-freeze.php',
        'generated_at' => date('c'),
        'verdict' => $verdict,
        'safety_contract' => [
            'read_only' => true,
            'displays_secrets' => false,
            'writes_files' => false,
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
        'statuses' => $statuses,
        'schema' => ['tables' => $tables, 'columns' => $columns],
        'driver_directory' => $driverDirectory,
        'logs' => $logs,
        'credential_rotation' => $credentialRotation,
        'production_freeze' => $freezeMarker,
        'gate_checks' => $gateChecks,
        'freeze_command' => '/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/freeze_dry_run_production.php --by=Andreas',
    ];
} catch (Throwable $e) {
    $error = $e->getMessage();
    $payload = [
        'ok' => false,
        'script' => 'ops/production-freeze.php',
        'generated_at' => date('c'),
        'error' => $error,
    ];
}

if ($format === 'json') {
    pf_json_response($payload, !empty($payload['ok']) ? 200 : 500);
}

$counts = $payload['counts'] ?? [];
$logs = $payload['logs'] ?? [];
$gateChecks = $payload['gate_checks'] ?? [];
$driverDirectory = $payload['driver_directory'] ?? [];
$statuses = $payload['statuses'] ?? [];
$verdict = (string)($payload['verdict'] ?? 'ERROR');
$credentialRotation = $payload['credential_rotation'] ?? [];
$freezeMarker = $payload['production_freeze'] ?? [];
$heroType = str_contains($verdict, 'FROZEN') ? 'good' : ($verdict === 'DRY_RUN_FREEZE_READY' ? 'warn' : 'bad');
$heroText = match ($verdict) {
    'DRY_RUN_PRODUCTION_FROZEN_CREDENTIALS_ROTATED' => 'Dry-run production posture is frozen and credential rotation has been acknowledged. Live submit is still off until a separate v5.0 approval.',
    'DRY_RUN_PRODUCTION_FROZEN_CREDENTIAL_ROTATION_PENDING' => 'Dry-run production posture is frozen. Credential rotation remains the manual gate before any future live-submit phase.',
    'DRY_RUN_FREEZE_READY' => 'Dry-run posture is ready to freeze. Run the CLI freeze command after reviewing the checks below.',
    default => 'Dry-run freeze is not ready. Review the failed checks below.',
};
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Production Freeze v4.9 | gov.cabnet.app</title>
    <style>
        :root { --bg:#f3f6fb; --panel:#fff; --ink:#07152f; --muted:#465f86; --line:#d7e1ef; --nav:#081225; --blue:#2563eb; --green:#087a4d; --orange:#b85c00; --red:#b42318; --soft:#f8fbff; --purple:#6046a8; }
        *{box-sizing:border-box} body{margin:0;background:var(--bg);color:var(--ink);font-family:Arial,Helvetica,sans-serif}.top{background:var(--nav);color:#fff;min-height:58px;display:flex;gap:18px;align-items:center;padding:0 24px;overflow:auto;position:sticky;top:0;z-index:10}.top strong{white-space:nowrap}.top a{color:#fff;text-decoration:none;white-space:nowrap;font-size:14px;opacity:.92}.top a:hover{opacity:1;text-decoration:underline}.wrap{width:min(1500px,calc(100% - 42px));margin:24px auto 60px}.card{background:var(--panel);border:1px solid var(--line);border-radius:14px;padding:18px;margin-bottom:18px;box-shadow:0 10px 28px rgba(8,18,37,.04)}.hero{border-left:8px solid var(--orange)}.hero.good{border-left-color:var(--green)}.hero.bad{border-left-color:var(--red)}h1{margin:0 0 10px;font-size:32px}h2{margin:0 0 14px;font-size:22px}h3{margin:0 0 10px;font-size:17px}p{color:var(--muted);line-height:1.45}.grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}.two{display:grid;grid-template-columns:1fr 1fr;gap:18px}.three{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:18px}.metric{border:1px solid var(--line);border-radius:11px;background:var(--soft);padding:14px;min-height:78px}.metric strong{display:block;font-size:29px;line-height:1.05;word-break:break-word}.metric span{display:block;color:var(--muted);font-size:13px;margin-top:6px}.badge{display:inline-block;padding:5px 9px;border-radius:999px;font-size:12px;font-weight:800;margin:1px 4px 1px 0;white-space:nowrap}.badge-good{background:#dcfce7;color:#166534}.badge-warn{background:#fff7ed;color:#b45309}.badge-bad{background:#fee2e2;color:#991b1b}.badge-neutral{background:#eaf1ff;color:#1e40af}.badge-purple{background:#ede9fe;color:#5b21b6}.checks{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}.check{border:1px solid var(--line);border-radius:12px;padding:13px;background:#fff}.check.good{border-left:6px solid var(--green)}.check.bad{border-left:6px solid var(--red)}.check.manual{border-left:6px solid var(--purple)}.check strong{display:block;margin-bottom:5px}.small{font-size:13px;color:var(--muted)}.actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:14px}.btn{display:inline-block;background:var(--blue);color:#fff;text-decoration:none;padding:10px 14px;border-radius:8px;font-weight:800;font-size:14px}.btn.dark{background:#334155}.btn.good{background:var(--green)}.btn.warn{background:var(--orange)}.btn.purple{background:var(--purple)}table{width:100%;border-collapse:collapse;font-size:13px}th,td{padding:9px;border-bottom:1px solid var(--line);text-align:left;vertical-align:top}th{background:#f8fbff;color:#29415f}.scroll{overflow:auto}.logbox{background:#081225;color:#dbeafe;border-radius:10px;padding:12px;min-height:110px;overflow:auto;font-family:Consolas,Menlo,monospace;font-size:12px;white-space:pre-wrap}.goodline{color:#166534}.warnline{color:#b45309}.badline{color:#991b1b}code{background:#eef2ff;padding:2px 5px;border-radius:5px}.safety{background:#ecfdf3;border:1px solid #bbf7d0;border-left:7px solid var(--green);border-radius:14px;padding:16px;margin-bottom:18px}.safety strong{color:#166534}@media(max-width:1100px){.grid,.three,.checks{grid-template-columns:repeat(2,minmax(0,1fr))}.two{grid-template-columns:1fr}}@media(max-width:720px){.wrap{width:calc(100% - 24px);margin-top:14px}.grid,.three,.checks{grid-template-columns:1fr}.top{padding:0 14px}}
    </style>
</head>
<body>
<nav class="top">
    <strong>gov.cabnet.app</strong>
    <a href="/ops/production-freeze.php<?= pf_h(pf_current_key_query()) ?>">Production Freeze</a>
    <a href="/ops/launch-readiness.php<?= pf_h(pf_current_key_query()) ?>">Launch Readiness</a>
    <a href="/ops/credential-rotation.php<?= pf_h(pf_current_key_query()) ?>">Credential Rotation</a>
    <a href="/ops/mail-status.php<?= pf_h(pf_current_key_query()) ?>">Mail Status</a>
    <a href="/ops/mail-driver-notifications.php<?= pf_h(pf_current_key_query()) ?>">Driver Notifications</a>
    <a href="/ops/mail-dry-run-evidence.php<?= pf_h(pf_current_key_query()) ?>">Dry-run Evidence</a>
    <a href="/ops/home.php">Ops Home</a>
</nav>

<main class="wrap">
    <section class="safety">
        <strong>LIVE EDXEIX SUBMISSION IS OFF.</strong>
        This page is read-only. It does not import mail, send driver email, create bookings/evidence/jobs/attempts, call Bolt, call EDXEIX, write files, or submit live.
    </section>

    <section class="card hero <?= pf_h($heroType) ?>">
        <h1>v4.9 Final Dry-run Production Freeze</h1>
        <p><?= pf_h($heroText) ?></p>
        <?php if ($error): ?><p class="badline"><strong>Error:</strong> <?= pf_h($error) ?></p><?php endif; ?>
        <div>
            <?= pf_badge($verdict, str_contains($verdict, 'FROZEN') ? 'good' : ($verdict === 'DRY_RUN_FREEZE_READY' ? 'warn' : 'bad')) ?>
            <?= pf_badge('LIVE SUBMIT OFF', 'good') ?>
            <?= pf_badge('DRY-RUN ONLY', 'good') ?>
            <?= pf_badge(!empty($freezeMarker['valid']) ? 'FREEZE MARKER EXISTS' : 'FREEZE MARKER PENDING', !empty($freezeMarker['valid']) ? 'good' : 'warn') ?>
            <?= pf_badge(!empty($credentialRotation['complete']) ? 'CREDENTIALS ROTATED' : 'CREDENTIAL ROTATION PENDING', !empty($credentialRotation['complete']) ? 'good' : 'purple') ?>
        </div>
        <div class="grid" style="margin-top:14px">
            <?= pf_metric($counts['submission_jobs'] ?? 'n/a', 'submission_jobs') ?>
            <?= pf_metric($counts['submission_attempts'] ?? 'n/a', 'submission_attempts') ?>
            <?= pf_metric($counts['bolt_mail_bookings'] ?? 'n/a', 'bolt_mail bookings') ?>
            <?= pf_metric($counts['dry_run_evidence'] ?? 'n/a', 'dry-run evidence') ?>
        </div>
        <div class="actions">
            <a class="btn" href="/ops/production-freeze.php<?= pf_h(pf_current_key_query(['format' => 'json'])) ?>">JSON</a>
            <a class="btn dark" href="/ops/launch-readiness.php<?= pf_h(pf_current_key_query()) ?>">Launch Readiness</a>
            <a class="btn purple" href="/ops/credential-rotation.php<?= pf_h(pf_current_key_query()) ?>">Credential Rotation</a>
        </div>
    </section>

    <section class="card">
        <h2>Freeze command</h2>
        <p>Run this only after reviewing the checks. It writes a no-secret dry-run freeze marker; it does not rotate credentials and does not enable live submit.</p>
        <pre class="logbox"><?= pf_h((string)($payload['freeze_command'] ?? '')) ?></pre>
    </section>

    <section class="card">
        <h2>Gate checks</h2>
        <div class="checks">
            <?php foreach ($gateChecks as $check): ?>
                <?php $ok = !empty($check['ok']); $manual = !empty($check['manual']); ?>
                <div class="check <?= $manual ? 'manual' : ($ok ? 'good' : 'bad') ?>">
                    <strong><?= pf_h((string)$check['label']) ?></strong>
                    <?= pf_badge($ok ? 'PASS' : ($manual ? 'MANUAL/PENDING' : 'FAIL'), $ok ? 'good' : ($manual ? 'purple' : 'bad')) ?>
                    <div class="small"><?= pf_h((string)($check['detail'] ?? '')) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="grid">
        <?= pf_metric($driverDirectory['total'] ?? 'n/a', 'drivers synced') ?>
        <?= pf_metric($driverDirectory['with_name_and_email'] ?? 'n/a', 'with name + email') ?>
        <?= pf_metric($driverDirectory['with_identifier_and_email'] ?? 'n/a', 'with identifier + email') ?>
        <?= pf_metric(($driverDirectory['coverage_percent'] ?? 'n/a') . '%', 'driver identity coverage') ?>
    </section>

    <section class="three">
        <?php foreach ($logs as $log): ?>
            <div class="card">
                <h3><?= pf_h((string)($log['label'] ?? 'Log')) ?> <?= pf_badge(!empty($log['healthy']) ? 'HEALTHY' : 'STALE', !empty($log['healthy']) ? 'good' : 'bad') ?></h3>
                <p class="small">mtime: <?= pf_h((string)($log['mtime'] ?? 'n/a')) ?> · age: <?= pf_h((string)($log['age_seconds'] ?? 'n/a')) ?> seconds</p>
                <pre class="logbox"><?= pf_h(implode("\n", $log['tail'] ?? [])) ?></pre>
            </div>
        <?php endforeach; ?>
    </section>

    <section class="two">
        <div class="card">
            <h2>Status counts</h2>
            <div class="scroll"><table><tbody>
                <?php foreach ($statuses as $group => $items): ?>
                    <?php foreach ((array)$items as $status => $count): ?>
                        <tr><th><?= pf_h((string)$group) ?></th><td><?= pf_h((string)$status) ?></td><td><?= pf_h((string)$count) ?></td></tr>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </tbody></table></div>
        </div>
        <div class="card">
            <h2>Markers</h2>
            <p><strong>Dry-run freeze:</strong> <?= pf_badge(!empty($freezeMarker['valid']) ? 'VALID' : 'PENDING', !empty($freezeMarker['valid']) ? 'good' : 'warn') ?></p>
            <p class="small"><?= pf_h((string)($freezeMarker['marker_file'] ?? '')) ?></p>
            <?php if (!empty($freezeMarker['data'])): ?>
                <pre class="logbox"><?= pf_h(json_encode($freezeMarker['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?></pre>
            <?php endif; ?>
            <p><strong>Credential rotation:</strong> <?= pf_badge(!empty($credentialRotation['complete']) ? 'COMPLETE' : 'PENDING', !empty($credentialRotation['complete']) ? 'good' : 'purple') ?></p>
            <p class="small"><?= pf_h((string)($credentialRotation['marker_file'] ?? '')) ?></p>
        </div>
    </section>

    <section class="card">
        <h2>Latest driver notifications</h2>
        <div class="scroll"><table>
            <thead><tr><th>ID</th><th>Intake</th><th>Driver</th><th>Plate</th><th>Masked recipient</th><th>Status</th><th>Skip/Error</th><th>Sent</th></tr></thead>
            <tbody>
            <?php foreach ($latestNotifications as $row): ?>
                <tr>
                    <td><?= pf_h($row['id'] ?? '') ?></td>
                    <td><?= pf_h($row['intake_id'] ?? '') ?></td>
                    <td><?= pf_h($row['driver_name'] ?? '') ?></td>
                    <td><?= pf_h($row['vehicle_plate'] ?? '') ?></td>
                    <td><?= pf_h(pf_mask_email($row['recipient_email'] ?? '')) ?></td>
                    <td><?= pf_h($row['notification_status'] ?? '') ?></td>
                    <td><?= pf_h(trim((string)($row['skip_reason'] ?? '') . ' ' . (string)($row['error_message'] ?? ''))) ?></td>
                    <td><?= pf_h($row['sent_at'] ?? '') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    </section>

    <section class="two">
        <div class="card">
            <h2>Latest intake rows</h2>
            <div class="scroll"><table>
                <thead><tr><th>ID</th><th>Driver</th><th>Plate</th><th>Pickup</th><th>Status</th><th>Linked</th></tr></thead>
                <tbody>
                <?php foreach ($latestIntake as $row): ?>
                    <tr><td><?= pf_h($row['id'] ?? '') ?></td><td><?= pf_h($row['driver_name'] ?? '') ?></td><td><?= pf_h($row['vehicle_plate'] ?? '') ?></td><td><?= pf_h($row['parsed_pickup_at'] ?? '') ?></td><td><?= pf_h($row['safety_status'] ?? '') ?></td><td><?= pf_h($row['linked_booking_id'] ?? '') ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
        </div>
        <div class="card">
            <h2>Latest dry-run evidence</h2>
            <div class="scroll"><table>
                <thead><tr><th>ID</th><th>Booking</th><th>Intake</th><th>Status</th><th>Payload hash</th><th>Created</th></tr></thead>
                <tbody>
                <?php foreach ($latestEvidence as $row): ?>
                    <tr><td><?= pf_h($row['id'] ?? '') ?></td><td><?= pf_h($row['normalized_booking_id'] ?? '') ?></td><td><?= pf_h($row['intake_id'] ?? '') ?></td><td><?= pf_h($row['evidence_status'] ?? '') ?></td><td><code><?= pf_h(substr((string)($row['payload_hash'] ?? ''), 0, 16)) ?></code></td><td><?= pf_h($row['created_at'] ?? '') ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
        </div>
    </section>
</main>
</body>
</html>
