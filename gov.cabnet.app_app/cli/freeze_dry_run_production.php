<?php
/**
 * gov.cabnet.app — v4.9 dry-run production freeze marker CLI.
 *
 * Writes a no-secret freeze marker only when the current dry-run production
 * posture is safe. Does not call Bolt, call EDXEIX, import mail, send email,
 * create bookings/evidence/jobs/attempts, or enable live submit.
 */

declare(strict_types=1);

function freeze_bool(mixed $value): bool
{
    if (is_bool($value)) {
        return $value;
    }
    return in_array(strtolower(trim((string)$value)), ['1', 'true', 'yes', 'on'], true);
}

function freeze_parse_args(array $argv): array
{
    $args = ['by' => 'ops', 'notes' => ''];
    foreach ($argv as $arg) {
        if (str_starts_with($arg, '--by=')) {
            $args['by'] = trim(substr($arg, 5));
        } elseif (str_starts_with($arg, '--notes=')) {
            $args['notes'] = trim(substr($arg, 8));
        } elseif ($arg === '--help' || $arg === '-h') {
            $args['help'] = true;
        }
    }
    return $args;
}

function freeze_table_exists($db, string $table): bool
{
    $row = $db->fetchOne(
        'SELECT COUNT(*) AS c FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
        [$table]
    );
    return ((int)($row['c'] ?? 0)) > 0;
}

function freeze_column_exists($db, string $table, string $column): bool
{
    $row = $db->fetchOne(
        'SELECT COUNT(*) AS c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
        [$table, $column]
    );
    return ((int)($row['c'] ?? 0)) > 0;
}

function freeze_count($db, string $table, string $where = '1=1'): int
{
    if (!freeze_table_exists($db, $table)) {
        return 0;
    }
    $safeTable = str_replace('`', '``', $table);
    $row = $db->fetchOne('SELECT COUNT(*) AS c FROM `' . $safeTable . '` WHERE ' . $where);
    return (int)($row['c'] ?? 0);
}

function freeze_log_health(string $file, int $healthyWithinSeconds): array
{
    $exists = is_file($file);
    $readable = $exists && is_readable($file);
    $mtime = $exists ? (int)filemtime($file) : null;
    $age = $mtime ? max(0, time() - $mtime) : null;
    $healthy = $readable && $age !== null && $age <= $healthyWithinSeconds;

    return [
        'file' => $file,
        'exists' => $exists,
        'readable' => $readable,
        'age_seconds' => $age,
        'healthy_within_seconds' => $healthyWithinSeconds,
        'healthy' => $healthy,
    ];
}

function freeze_credential_complete(string $markerFile): bool
{
    if (!is_file($markerFile) || !is_readable($markerFile)) {
        return false;
    }
    $raw = file_get_contents($markerFile);
    $data = is_string($raw) ? json_decode($raw, true) : null;
    if (!is_array($data)) {
        return false;
    }
    $items = is_array($data['items'] ?? null) ? $data['items'] : [];
    foreach (['ops_key', 'bolt_credentials', 'edxeix_credentials', 'mailbox_credentials'] as $key) {
        if (empty($items[$key])) {
            return false;
        }
    }
    return true;
}

$args = freeze_parse_args($argv ?? []);
if (!empty($args['help'])) {
    echo "Usage: php freeze_dry_run_production.php --by=Andreas [--notes='short note']\n";
    echo "Safety: writes a no-secret dry-run freeze marker only. Does not enable live submit.\n";
    exit(0);
}

$root = dirname(__DIR__);
$bootstrap = $root . '/src/bootstrap.php';
if (!is_file($bootstrap) || !is_readable($bootstrap)) {
    fwrite(STDERR, "ERROR missing bootstrap: {$bootstrap}\n");
    exit(1);
}

$app = require $bootstrap;
$config = $app['config'];
$db = $app['db'];

$dryRun = freeze_bool($config->get('app.dry_run', false));
$liveSubmitEnabled = freeze_bool($config->get('edxeix.live_submit_enabled', false));
$driverNotifications = $config->get('mail.driver_notifications', []);
$driverNotificationsEnabled = is_array($driverNotifications) && freeze_bool($driverNotifications['enabled'] ?? false);
$resolveDriverDirectory = is_array($driverNotifications) && freeze_bool($driverNotifications['resolve_from_bolt_driver_directory'] ?? false);
$logsDir = (string)$config->get('paths.logs', $root . '/storage/logs');
$securityDir = $root . '/storage/security';
$freezeFile = $securityDir . '/production_dry_run_freeze.json';
$credentialMarkerFile = $securityDir . '/credential_rotation_ack.json';

$tables = [
    'bolt_mail_intake' => freeze_table_exists($db, 'bolt_mail_intake'),
    'normalized_bookings' => freeze_table_exists($db, 'normalized_bookings'),
    'bolt_mail_dry_run_evidence' => freeze_table_exists($db, 'bolt_mail_dry_run_evidence'),
    'bolt_mail_driver_notifications' => freeze_table_exists($db, 'bolt_mail_driver_notifications'),
    'mapping_drivers' => freeze_table_exists($db, 'mapping_drivers'),
    'submission_jobs' => freeze_table_exists($db, 'submission_jobs'),
    'submission_attempts' => freeze_table_exists($db, 'submission_attempts'),
];

$driverNameColumn = $tables['mapping_drivers'] && freeze_column_exists($db, 'mapping_drivers', 'external_driver_name');
$driverIdColumn = $tables['mapping_drivers'] && freeze_column_exists($db, 'mapping_drivers', 'driver_identifier');
$driverEmailColumn = $tables['mapping_drivers'] && freeze_column_exists($db, 'mapping_drivers', 'driver_email');

$counts = [
    'submission_jobs' => freeze_count($db, 'submission_jobs'),
    'submission_attempts' => freeze_count($db, 'submission_attempts'),
    'bolt_mail_bookings' => freeze_count($db, 'normalized_bookings', "source='bolt_mail'"),
    'dry_run_evidence' => freeze_count($db, 'bolt_mail_dry_run_evidence'),
    'driver_notifications' => freeze_count($db, 'bolt_mail_driver_notifications'),
    'driver_notifications_sent' => freeze_count($db, 'bolt_mail_driver_notifications', "notification_status='sent'"),
    'mapping_drivers_total' => freeze_count($db, 'mapping_drivers'),
    'mapping_drivers_with_name_identifier_email' => ($driverNameColumn && $driverIdColumn && $driverEmailColumn)
        ? freeze_count($db, 'mapping_drivers', "external_driver_name IS NOT NULL AND external_driver_name <> '' AND driver_identifier IS NOT NULL AND driver_identifier <> '' AND driver_email IS NOT NULL AND driver_email <> ''")
        : 0,
];

$logs = [
    'mail_intake' => freeze_log_health(rtrim($logsDir, '/') . '/bolt_mail_intake.log', 180),
    'auto_dry_run' => freeze_log_health(rtrim($logsDir, '/') . '/bolt_mail_auto_dry_run.log', 180),
    'driver_directory' => freeze_log_health(rtrim($logsDir, '/') . '/bolt_driver_directory_sync.log', 1800),
];

$checks = [
    'dry_run_enabled' => $dryRun,
    'live_submit_disabled' => !$liveSubmitEnabled,
    'submission_jobs_zero' => $counts['submission_jobs'] === 0,
    'submission_attempts_zero' => $counts['submission_attempts'] === 0,
    'driver_notifications_enabled' => $driverNotificationsEnabled,
    'resolve_from_driver_directory' => $resolveDriverDirectory,
    'driver_directory_complete' => $counts['mapping_drivers_total'] > 0 && $counts['mapping_drivers_with_name_identifier_email'] === $counts['mapping_drivers_total'],
    'driver_notification_proof' => $counts['driver_notifications_sent'] > 0,
    'dry_run_evidence_proof' => $counts['bolt_mail_bookings'] > 0 && $counts['dry_run_evidence'] > 0,
    'mail_intake_cron_healthy' => !empty($logs['mail_intake']['healthy']),
    'auto_dry_run_cron_healthy' => !empty($logs['auto_dry_run']['healthy']),
    'driver_directory_cron_healthy' => !empty($logs['driver_directory']['healthy']),
];

$failed = [];
foreach ($checks as $name => $ok) {
    if (!$ok) {
        $failed[] = $name;
    }
}

if ($failed) {
    echo '[' . date('c') . "] Dry-run production freeze refused. Failed checks:\n";
    foreach ($failed as $name) {
        echo '- ' . $name . "\n";
    }
    echo "Safety: no file written; no live submit enabled; no external calls.\n";
    exit(2);
}

if (!is_dir($securityDir)) {
    if (!mkdir($securityDir, 0750, true) && !is_dir($securityDir)) {
        fwrite(STDERR, "ERROR could not create security directory: {$securityDir}\n");
        exit(1);
    }
}

$payload = [
    'ok' => true,
    'marker_type' => 'production_dry_run_freeze',
    'version' => 'v4.9',
    'created_at' => date('c'),
    'created_by' => $args['by'] !== '' ? $args['by'] : 'ops',
    'notes' => (string)$args['notes'],
    'verdict' => freeze_credential_complete($credentialMarkerFile)
        ? 'DRY_RUN_PRODUCTION_FROZEN_CREDENTIALS_ROTATED'
        : 'DRY_RUN_PRODUCTION_FROZEN_CREDENTIAL_ROTATION_PENDING',
    'safety_contract' => [
        'contains_secrets' => false,
        'dry_run' => true,
        'live_submit_enabled' => false,
        'creates_submission_jobs' => false,
        'creates_submission_attempts' => false,
        'calls_bolt' => false,
        'calls_edxeix' => false,
        'live_edxeix_submission' => false,
    ],
    'checks' => $checks,
    'counts' => $counts,
    'logs' => $logs,
    'credential_rotation_acknowledged' => freeze_credential_complete($credentialMarkerFile),
];

$json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
if (!is_string($json)) {
    fwrite(STDERR, "ERROR could not encode freeze marker JSON\n");
    exit(1);
}

$tmp = $freezeFile . '.tmp';
if (file_put_contents($tmp, $json . PHP_EOL, LOCK_EX) === false) {
    fwrite(STDERR, "ERROR could not write temporary marker: {$tmp}\n");
    exit(1);
}
chmod($tmp, 0640);
if (!rename($tmp, $freezeFile)) {
    @unlink($tmp);
    fwrite(STDERR, "ERROR could not move marker into place: {$freezeFile}\n");
    exit(1);
}

printf("[%s] Dry-run production freeze marker written.\n", date('c'));
printf("Marker: %s\n", $freezeFile);
printf("Verdict: %s\n", $payload['verdict']);
echo "Safety: no secrets stored; no live submit enabled; no Bolt/EDXEIX calls; no jobs/attempts created.\n";
