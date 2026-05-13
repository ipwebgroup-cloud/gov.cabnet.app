<?php
/**
 * gov.cabnet.app — V3 submit dry-run cron worker.
 *
 * Purpose:
 * - Automatically run the isolated V3 submit dry-run worker from cron.
 * - Move queued V3 rows to submit_dry_run_ready when strict dry-run checks pass.
 *
 * Safety:
 * - Calls only the V3 submit dry-run worker.
 * - No EDXEIX calls.
 * - No AADE calls.
 * - No writes to production submission_jobs.
 * - No writes to production submission_attempts.
 * - No production pre-ride-email-tool.php changes.
 * - Commit mode writes only to V3 queue/status/events via the child worker.
 */

declare(strict_types=1);

const V3_SUBMIT_DRY_CRON_VERSION = 'v3.0.15-app-owned-locks';
const DEFAULT_LIMIT = 20;
const DEFAULT_STATUS = 'queued';
const DEFAULT_MIN_FUTURE_MINUTES = 0;

function sd3_ts(): string
{
    return date('Y-m-d H:i:s P');
}

function sd3_line(string $message): void
{
    echo '[' . sd3_ts() . '] ' . $message . PHP_EOL;
}

function sd3_arg_value(array $argv, string $name, string $default): string
{
    foreach ($argv as $arg) {
        if (str_starts_with($arg, $name . '=')) {
            return substr($arg, strlen($name) + 1);
        }
    }
    return $default;
}

function sd3_has_flag(array $argv, string $flag): bool
{
    return in_array($flag, $argv, true);
}

$limitText = sd3_arg_value($argv, '--limit', (string)DEFAULT_LIMIT);
$limit = (int)$limitText;
if ($limit < 1 || $limit > 200) {
    $limit = DEFAULT_LIMIT;
}

$status = trim(sd3_arg_value($argv, '--status', getenv('GOV_CABNET_V3_SUBMIT_DRY_STATUS') ?: DEFAULT_STATUS));
if ($status === '') {
    $status = DEFAULT_STATUS;
}

$minFutureText = sd3_arg_value(
    $argv,
    '--min-future-minutes',
    getenv('GOV_CABNET_V3_SUBMIT_DRY_MIN_FUTURE_MINUTES') ?: (string)DEFAULT_MIN_FUTURE_MINUTES
);
$minFutureMinutes = (int)$minFutureText;
if ($minFutureMinutes < 0 || $minFutureMinutes > 1440) {
    $minFutureMinutes = DEFAULT_MIN_FUTURE_MINUTES;
}

$dryRun = sd3_has_flag($argv, '--dry-run');
$lockDir = dirname(__DIR__) . '/storage/locks';
if (!is_dir($lockDir) && !mkdir($lockDir, 0755, true) && !is_dir($lockDir)) {
    sd3_line('ERROR: could not create lock directory: ' . $lockDir);
    exit(3);
}
$lockFile = $lockDir . '/pre_ride_email_v3_submit_dry_run_cron_worker.lock';
$workerScript = __DIR__ . '/pre_ride_email_v3_submit_dry_run_worker.php';

sd3_line(
    'V3 submit dry-run cron worker start ' . V3_SUBMIT_DRY_CRON_VERSION .
    ' mode=' . ($dryRun ? 'dry_run_select_only' : 'commit_v3_status_only') .
    ' status=' . $status .
    ' limit=' . $limit .
    ' min_future_minutes=' . $minFutureMinutes
);

if (!is_file($workerScript)) {
    sd3_line('ERROR: submit dry-run worker script not found: ' . $workerScript);
    exit(2);
}

$lockHandle = fopen($lockFile, 'c');
if (!$lockHandle) {
    sd3_line('ERROR: could not open lock file: ' . $lockFile);
    exit(3);
}

if (!flock($lockHandle, LOCK_EX | LOCK_NB)) {
    sd3_line('SKIP: another V3 submit dry-run cron worker is already running.');
    fclose($lockHandle);
    exit(0);
}

ftruncate($lockHandle, 0);
fwrite($lockHandle, (string)getmypid());

$php = PHP_BINARY;
if ($php === '' || !is_file($php)) {
    $php = '/usr/local/bin/php';
}

$cmd = [
    $php,
    $workerScript,
    '--status=' . $status,
    '--limit=' . (string)$limit,
    '--min-future-minutes=' . (string)$minFutureMinutes,
    '--json',
];

if (!$dryRun) {
    $cmd[] = '--commit';
}

$command = implode(' ', array_map('escapeshellarg', $cmd));
$descriptors = [
    0 => ['pipe', 'r'],
    1 => ['pipe', 'w'],
    2 => ['pipe', 'w'],
];

$process = proc_open($command, $descriptors, $pipes);
if (!is_resource($process)) {
    sd3_line('ERROR: could not start V3 submit dry-run worker process.');
    flock($lockHandle, LOCK_UN);
    fclose($lockHandle);
    exit(4);
}

fclose($pipes[0]);
$stdout = stream_get_contents($pipes[1]);
$stderr = stream_get_contents($pipes[2]);
fclose($pipes[1]);
fclose($pipes[2]);
$exitCode = proc_close($process);

if (trim((string)$stderr) !== '') {
    sd3_line('STDERR: ' . trim((string)$stderr));
}

$data = json_decode((string)$stdout, true);
if (is_array($data) && isset($data['summary']) && is_array($data['summary'])) {
    $s = $data['summary'];
    sd3_line(
        'SUMMARY ' .
        'ok=' . (!empty($s['ok']) ? 'yes' : 'no') .
        ' mode=' . (string)($s['mode'] ?? '') .
        ' db=' . (string)($s['database'] ?? '') .
        ' schema_ok=' . (!empty($s['schema_ok']) ? 'yes' : 'no') .
        ' rows=' . (string)($s['rows_checked'] ?? '0') .
        ' dry_ready=' . (string)($s['submit_dry_run_ready_count'] ?? '0') .
        ' blocked=' . (string)($s['blocked_count'] ?? '0') .
        ' warnings=' . (string)($s['warning_count'] ?? '0') .
        ' events=' . (string)($s['events_inserted'] ?? '0') .
        ' marked=' . (string)($s['rows_marked_ready'] ?? '0') .
        ((string)($s['error'] ?? '') !== '' ? ' error=' . (string)$s['error'] : '')
    );

    if (!empty($data['rows']) && is_array($data['rows'])) {
        $shown = 0;
        foreach ($data['rows'] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $ready = !empty($row['ready']);
            $reasonList = is_array($row['block_reasons'] ?? null) ? $row['block_reasons'] : [];
            $warningList = is_array($row['warnings'] ?? null) ? $row['warnings'] : [];
            $firstReason = $reasonList[0] ?? '';
            $firstWarning = $warningList[0] ?? '';

            sd3_line(
                ($ready ? 'DRY_READY ' : 'BLOCKED ') .
                'id=' . (string)($row['queue_id'] ?? '') .
                ' dedupe=' . (string)($row['dedupe_key'] ?? '') .
                ' pickup=' . (string)($row['pickup_datetime'] ?? '') .
                ' minutes=' . (string)($row['minutes_until'] ?? '') .
                ' transfer=' . (string)($row['customer_name'] ?? '') . ' / ' .
                    (string)($row['driver_name'] ?? '') . ' / ' .
                    (string)($row['vehicle_plate'] ?? '') .
                ($firstReason !== '' ? ' reason=' . (string)$firstReason : '') .
                ($firstWarning !== '' ? ' warning=' . (string)$firstWarning : '')
            );

            $shown++;
            if ($shown >= 5) {
                break;
            }
        }
    }
} else {
    sd3_line('WARNING: submit dry-run worker output was not valid JSON. Raw output follows.');
    foreach (explode("\n", trim((string)$stdout)) as $line) {
        if (trim($line) !== '') {
            sd3_line('RAW: ' . $line);
        }
    }
}

sd3_line('V3 submit dry-run cron worker finish exit_code=' . $exitCode);

flock($lockHandle, LOCK_UN);
fclose($lockHandle);

exit($exitCode === 0 ? 0 : 10);
