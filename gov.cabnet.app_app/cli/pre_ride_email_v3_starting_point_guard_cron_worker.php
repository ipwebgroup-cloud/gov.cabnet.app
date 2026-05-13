<?php
/**
 * gov.cabnet.app — V3 starting-point guard cron worker.
 *
 * Runs the V3 starting-point guard in commit mode from cron.
 * Uses app-owned lock storage.
 */

declare(strict_types=1);

const SPGC_VERSION = 'v3.0.18-starting-point-guard-cron';
const SPGC_DEFAULT_LIMIT = 50;

function spgc_ts(): string { return date('Y-m-d H:i:s P'); }
function spgc_line(string $message): void { echo '[' . spgc_ts() . '] ' . $message . PHP_EOL; }
function spgc_arg_value(array $argv, string $name, string $default): string
{
    foreach ($argv as $arg) {
        if (str_starts_with($arg, $name . '=')) {
            return substr($arg, strlen($name) + 1);
        }
    }
    return $default;
}
function spgc_has_flag(array $argv, string $flag): bool { return in_array($flag, $argv, true); }

$limit = (int)spgc_arg_value($argv, '--limit', (string)SPGC_DEFAULT_LIMIT);
if ($limit < 1 || $limit > 500) { $limit = SPGC_DEFAULT_LIMIT; }
$status = trim(spgc_arg_value($argv, '--status', 'queued,submit_dry_run_ready,ready'));
$dryRun = spgc_has_flag($argv, '--dry-run');

$lockDir = dirname(__DIR__) . '/storage/locks';
if (!is_dir($lockDir) && !mkdir($lockDir, 0755, true) && !is_dir($lockDir)) {
    spgc_line('ERROR: could not create lock directory: ' . $lockDir);
    exit(3);
}
$lockFile = $lockDir . '/pre_ride_email_v3_starting_point_guard_cron_worker.lock';
$guardScript = __DIR__ . '/pre_ride_email_v3_starting_point_guard.php';

spgc_line('V3 starting-point guard cron start ' . SPGC_VERSION . ' mode=' . ($dryRun ? 'dry_run_select_only' : 'commit_v3_guard_only') . ' status=' . $status . ' limit=' . $limit);

if (!is_file($guardScript)) {
    spgc_line('ERROR: guard script not found: ' . $guardScript);
    exit(2);
}

$lockHandle = fopen($lockFile, 'c');
if (!$lockHandle) {
    spgc_line('ERROR: could not open lock file: ' . $lockFile);
    exit(3);
}
if (!flock($lockHandle, LOCK_EX | LOCK_NB)) {
    spgc_line('SKIP: another V3 starting-point guard cron worker is already running.');
    fclose($lockHandle);
    exit(0);
}
ftruncate($lockHandle, 0);
fwrite($lockHandle, (string)getmypid());

$php = PHP_BINARY;
if ($php === '' || !is_file($php)) { $php = '/usr/local/bin/php'; }
$cmd = [
    $php,
    $guardScript,
    '--limit=' . (string)$limit,
    '--status=' . $status,
    '--json',
];
if (!$dryRun) { $cmd[] = '--commit'; }
$command = implode(' ', array_map('escapeshellarg', $cmd));
$descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
$process = proc_open($command, $descriptors, $pipes);
if (!is_resource($process)) {
    spgc_line('ERROR: could not start guard process.');
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

if (trim((string)$stderr) !== '') { spgc_line('STDERR: ' . trim((string)$stderr)); }
$data = json_decode((string)$stdout, true);
if (is_array($data) && isset($data['summary']) && is_array($data['summary'])) {
    $s = $data['summary'];
    spgc_line(
        'SUMMARY ' .
        'ok=' . (!empty($s['ok']) ? 'yes' : 'no') .
        ' mode=' . (string)($s['mode'] ?? '') .
        ' db=' . (string)($s['database'] ?? '') .
        ' schema_ok=' . (!empty($s['schema_ok']) ? 'yes' : 'no') .
        ' rows=' . (string)($s['rows_checked'] ?? '0') .
        ' valid=' . (string)($s['valid_count'] ?? '0') .
        ' invalid=' . (string)($s['invalid_count'] ?? '0') .
        ' unknown=' . (string)($s['unknown_lessor_count'] ?? '0') .
        ' blocked=' . (string)($s['blocked_count'] ?? '0') .
        ' errors=' . (string)($s['error_count'] ?? '0')
    );
    if (!empty($data['rows']) && is_array($data['rows'])) {
        $shown = 0;
        foreach ($data['rows'] as $row) {
            if (!is_array($row)) { continue; }
            $isInvalid = empty($row['valid']) && !empty($row['known_lessor']);
            if (!$isInvalid) { continue; }
            spgc_line(
                'INVALID ' .
                'id=' . (string)($row['queue_id'] ?? '') .
                ' dedupe=' . (string)($row['dedupe_key'] ?? '') .
                ' lessor=' . (string)($row['lessor_id'] ?? '') .
                ' start=' . (string)($row['starting_point_id'] ?? '') .
                ' action=' . (string)($row['action'] ?? '') .
                ' reason=' . (string)($row['reason'] ?? '')
            );
            $shown++;
            if ($shown >= 5) { break; }
        }
    }
} else {
    spgc_line('WARNING: guard output was not valid JSON. Raw output follows.');
    foreach (explode("\n", trim((string)$stdout)) as $line) {
        if (trim($line) !== '') { spgc_line('RAW: ' . $line); }
    }
}

spgc_line('V3 starting-point guard cron finish exit_code=' . $exitCode);
flock($lockHandle, LOCK_UN);
fclose($lockHandle);
exit($exitCode === 0 ? 0 : 10);
