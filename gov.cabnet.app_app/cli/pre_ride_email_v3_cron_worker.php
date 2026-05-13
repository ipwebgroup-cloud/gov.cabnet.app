<?php
/**
 * gov.cabnet.app — Bolt pre-ride email V3 cron worker
 *
 * Purpose:
 * - Run the isolated V3 pre-ride email queue intake automatically from cron.
 * - Insert only eligible future-ready rides into V3-only queue tables.
 *
 * Safety:
 * - Calls only the V3 CLI intake script.
 * - The V3 intake script is responsible for parser/mapping/future gates.
 * - No EDXEIX server call.
 * - No AADE call.
 * - No writes to production submission_jobs or submission_attempts.
 * - No email delete/move/mark-read.
 */

declare(strict_types=1);

const WORKER_VERSION = 'v3.0.9-fast-intake-cron-worker';
const DEFAULT_LIMIT = 20;
const DEFAULT_MIN_FUTURE_MINUTES = 1;

function cw_ts(): string
{
    return date('Y-m-d H:i:s P');
}

function cw_line(string $message): void
{
    echo '[' . cw_ts() . '] ' . $message . PHP_EOL;
}

function cw_arg_value(array $argv, string $name, string $default): string
{
    foreach ($argv as $arg) {
        if (str_starts_with($arg, $name . '=')) {
            return substr($arg, strlen($name) + 1);
        }
    }
    return $default;
}

function cw_has_flag(array $argv, string $flag): bool
{
    return in_array($flag, $argv, true);
}

$limitText = cw_arg_value($argv, '--limit', (string)DEFAULT_LIMIT);
$limit = (int)$limitText;
if ($limit < 1 || $limit > 200) {
    $limit = DEFAULT_LIMIT;
}

$minFutureText = cw_arg_value($argv, '--min-future-minutes', getenv('GOV_CABNET_V3_MIN_FUTURE_MINUTES') ?: (string)DEFAULT_MIN_FUTURE_MINUTES);
$minFutureMinutes = (int)$minFutureText;
if ($minFutureMinutes < 1 || $minFutureMinutes > 1440) {
    $minFutureMinutes = DEFAULT_MIN_FUTURE_MINUTES;
}

$dryRun = cw_has_flag($argv, '--dry-run');
$lockFile = sys_get_temp_dir() . '/gov_cabnet_pre_ride_email_v3_cron_worker.lock';
$intakeScript = __DIR__ . '/pre_ride_email_v3_intake.php';

cw_line('V3 cron worker start ' . WORKER_VERSION . ' mode=' . ($dryRun ? 'dry_run' : 'commit') . ' limit=' . $limit . ' min_future_minutes=' . $minFutureMinutes);

if (!is_file($intakeScript)) {
    cw_line('ERROR: intake script not found: ' . $intakeScript);
    exit(2);
}

$lockHandle = fopen($lockFile, 'c');
if (!$lockHandle) {
    cw_line('ERROR: could not open lock file: ' . $lockFile);
    exit(3);
}

if (!flock($lockHandle, LOCK_EX | LOCK_NB)) {
    cw_line('SKIP: another V3 cron worker is already running.');
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
    $intakeScript,
    '--limit=' . (string)$limit,
    '--min-future-minutes=' . (string)$minFutureMinutes,
    '--json',
];

if (!$dryRun) {
    $cmd[] = '--commit';
}

$escaped = array_map('escapeshellarg', $cmd);
$command = implode(' ', $escaped);

$descriptors = [
    0 => ['pipe', 'r'],
    1 => ['pipe', 'w'],
    2 => ['pipe', 'w'],
];

$process = proc_open($command, $descriptors, $pipes);
if (!is_resource($process)) {
    cw_line('ERROR: could not start intake process.');
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
    cw_line('STDERR: ' . trim((string)$stderr));
}

$data = json_decode((string)$stdout, true);
if (is_array($data) && isset($data['summary']) && is_array($data['summary'])) {
    $s = $data['summary'];

    cw_line(
        'SUMMARY ' .
        'ok=' . (!empty($s['ok']) ? 'yes' : 'no') .
        ' mode=' . (string)($s['mode'] ?? '') .
        ' schema_ok=' . (!empty($s['schema_ok']) ? 'yes' : 'no') .
        ' candidates=' . (string)($s['candidate_count'] ?? '0') .
        ' ready=' . (string)($s['ready_count'] ?? '0') .
        ' blocked=' . (string)($s['blocked_count'] ?? '0') .
        ' inserted=' . (string)($s['inserted_count'] ?? '0') .
        ' duplicates=' . (string)($s['duplicate_count'] ?? '0') .
        ' errors=' . (string)($s['error_count'] ?? '0')
    );

    if (!empty($data['insert_results']) && is_array($data['insert_results'])) {
        foreach ($data['insert_results'] as $row) {
            if (!is_array($row)) {
                continue;
            }
            cw_line(
                'INSERT_RESULT ' .
                'dedupe=' . (string)($row['dedupe_key'] ?? '') .
                ' status=' . (string)($row['status'] ?? '') .
                ' id=' . (string)($row['id'] ?? '')
            );
        }
    }

    if (!empty($data['rows']) && is_array($data['rows'])) {
        $shown = 0;
        foreach ($data['rows'] as $row) {
            if (!is_array($row) || !empty($row['ready'])) {
                continue;
            }
            $summary = is_array($row['summary'] ?? null) ? $row['summary'] : [];
            $reasonList = is_array($row['block_reasons'] ?? null) ? $row['block_reasons'] : [];
            $reason = $reasonList[0] ?? '';
            cw_line(
                'BLOCKED ' .
                'dedupe=' . (string)($row['dedupe_key'] ?? '') .
                ' pickup=' . (string)($summary['pickup_datetime'] ?? '') .
                ' minutes=' . (string)($summary['minutes_until'] ?? '') .
                ' transfer=' . (string)($summary['customer'] ?? '') . ' / ' .
                    (string)($summary['driver'] ?? '') . ' / ' .
                    (string)($summary['vehicle'] ?? '') .
                ($reason !== '' ? ' reason=' . (string)$reason : '')
            );
            $shown++;
            if ($shown >= 3) {
                break;
            }
        }
    }
} else {
    cw_line('WARNING: intake output was not valid JSON. Raw output follows.');
    foreach (explode("\n", trim((string)$stdout)) as $line) {
        if (trim($line) !== '') {
            cw_line('RAW: ' . $line);
        }
    }
}

cw_line('V3 cron worker finish exit_code=' . $exitCode);

flock($lockHandle, LOCK_UN);
fclose($lockHandle);

exit($exitCode === 0 ? 0 : 10);
