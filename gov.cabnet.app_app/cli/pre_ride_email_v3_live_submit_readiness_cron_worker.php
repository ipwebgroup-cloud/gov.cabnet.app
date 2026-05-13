<?php
/**
 * gov.cabnet.app — V3 live-submit readiness cron worker.
 *
 * Purpose:
 * - Automatically run the isolated V3 live-submit readiness worker from cron.
 * - Promote rows from submit_dry_run_ready to live_submit_ready when all gates pass.
 *
 * Safety:
 * - Calls only the V3 live-submit readiness worker.
 * - No EDXEIX calls.
 * - No AADE calls.
 * - No production submission_jobs writes.
 * - No production submission_attempts writes.
 * - Commit mode writes only V3 queue/status/events via the child worker.
 */

declare(strict_types=1);

const LSR_CRON_VERSION = 'v3.0.21-live-submit-readiness-cron';
const DEFAULT_LIMIT = 20;
const DEFAULT_STATUS = 'submit_dry_run_ready';
const DEFAULT_MIN_FUTURE_MINUTES = 0;

function lsrc_ts(): string
{
    return date('Y-m-d H:i:s P');
}

function lsrc_line(string $message): void
{
    echo '[' . lsrc_ts() . '] ' . $message . PHP_EOL;
}

function lsrc_arg_value(array $argv, string $name, string $default): string
{
    foreach ($argv as $arg) {
        if (str_starts_with($arg, $name . '=')) {
            return substr($arg, strlen($name) + 1);
        }
    }
    return $default;
}

function lsrc_has_flag(array $argv, string $flag): bool
{
    return in_array($flag, $argv, true);
}

$limit = (int)lsrc_arg_value($argv, '--limit', (string)DEFAULT_LIMIT);
if ($limit < 1 || $limit > 200) {
    $limit = DEFAULT_LIMIT;
}

$status = trim(lsrc_arg_value($argv, '--status', getenv('GOV_CABNET_V3_LIVE_READINESS_STATUS') ?: DEFAULT_STATUS));
if ($status === '') {
    $status = DEFAULT_STATUS;
}

$minFutureMinutes = (int)lsrc_arg_value(
    $argv,
    '--min-future-minutes',
    getenv('GOV_CABNET_V3_LIVE_READINESS_MIN_FUTURE_MINUTES') ?: (string)DEFAULT_MIN_FUTURE_MINUTES
);
if ($minFutureMinutes < 0 || $minFutureMinutes > 240) {
    $minFutureMinutes = DEFAULT_MIN_FUTURE_MINUTES;
}

$dryRun = lsrc_has_flag($argv, '--dry-run');
$lockDir = dirname(__DIR__) . '/storage/locks';
if (!is_dir($lockDir) && !mkdir($lockDir, 0755, true) && !is_dir($lockDir)) {
    lsrc_line('ERROR: could not create lock directory: ' . $lockDir);
    exit(3);
}
$lockFile = $lockDir . '/pre_ride_email_v3_live_submit_readiness_cron_worker.lock';
$workerScript = __DIR__ . '/pre_ride_email_v3_live_submit_readiness.php';

lsrc_line(
    'V3 live-submit readiness cron worker start ' . LSR_CRON_VERSION .
    ' mode=' . ($dryRun ? 'dry_run_select_only' : 'commit_v3_live_readiness_only') .
    ' status=' . $status .
    ' limit=' . $limit .
    ' min_future_minutes=' . $minFutureMinutes
);

if (!is_file($workerScript)) {
    lsrc_line('ERROR: live-submit readiness script not found: ' . $workerScript);
    exit(2);
}

$lockHandle = fopen($lockFile, 'c');
if (!$lockHandle) {
    lsrc_line('ERROR: could not open lock file: ' . $lockFile);
    exit(3);
}

if (!flock($lockHandle, LOCK_EX | LOCK_NB)) {
    lsrc_line('SKIP: another V3 live-submit readiness cron worker is already running.');
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
$descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
$process = proc_open($command, $descriptors, $pipes);
if (!is_resource($process)) {
    lsrc_line('ERROR: could not start V3 live-submit readiness process.');
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
    lsrc_line('STDERR: ' . trim((string)$stderr));
}

$data = json_decode((string)$stdout, true);
if (is_array($data) && isset($data['summary']) && is_array($data['summary'])) {
    $s = $data['summary'];
    lsrc_line(
        'SUMMARY ' .
        'ok=' . (!empty($s['ok']) ? 'yes' : 'no') .
        ' mode=' . (string)($s['mode'] ?? '') .
        ' db=' . (string)($s['database'] ?? '') .
        ' schema_ok=' . (!empty($s['schema_ok']) ? 'yes' : 'no') .
        ' start_options=' . (!empty($s['starting_point_options_ok']) ? 'yes' : 'no') .
        ' rows=' . (string)($s['rows_checked'] ?? '0') .
        ' live_ready=' . (string)($s['live_ready_count'] ?? '0') .
        ' blocked=' . (string)($s['blocked_count'] ?? '0') .
        ' start_guard_blocks=' . (string)($s['start_guard_blocks'] ?? '0') .
        ' warnings=' . (string)($s['warning_count'] ?? '0') .
        ' events=' . (string)($s['events_inserted'] ?? '0') .
        ' marked=' . (string)($s['rows_marked_live_ready'] ?? '0') .
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
            $firstReason = $reasonList[0] ?? '';
            lsrc_line(
                ($ready ? 'LIVE_READY ' : 'BLOCKED ') .
                'id=' . (string)($row['queue_id'] ?? '') .
                ' dedupe=' . (string)($row['dedupe_key'] ?? '') .
                ' pickup=' . (string)($row['pickup_datetime'] ?? '') .
                ' minutes=' . (string)($row['minutes_until'] ?? '') .
                ' transfer=' . (string)($row['customer_name'] ?? '') . ' / ' .
                    (string)($row['driver_name'] ?? '') . ' / ' .
                    (string)($row['vehicle_plate'] ?? '') .
                ($firstReason !== '' ? ' reason=' . (string)$firstReason : '')
            );
            $shown++;
            if ($shown >= 5) {
                break;
            }
        }
    }
} else {
    lsrc_line('WARNING: live-submit readiness worker output was not valid JSON. Raw output follows.');
    foreach (explode("\n", trim((string)$stdout)) as $line) {
        if (trim($line) !== '') {
            lsrc_line('RAW: ' . $line);
        }
    }
}

lsrc_line('V3 live-submit readiness cron worker finish exit_code=' . $exitCode);

flock($lockHandle, LOCK_UN);
fclose($lockHandle);

exit($exitCode === 0 ? 0 : 10);
