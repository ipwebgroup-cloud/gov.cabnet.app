<?php
/**
 * gov.cabnet.app — V3 fast pipeline cron worker.
 *
 * Safety: wrapper only. Runs the V3 fast pipeline in commit mode. No EDXEIX or AADE call.
 */

declare(strict_types=1);

const PRV3_FAST_PIPELINE_CRON_VERSION = 'v3.0.35-fast-pipeline-cron-worker';

date_default_timezone_set('Europe/Athens');

$appRoot = dirname(__DIR__);
$lockDir = $appRoot . '/storage/locks';
if (!is_dir($lockDir)) {
    @mkdir($lockDir, 0755, true);
}
$lockPath = $lockDir . '/pre_ride_email_v3_fast_pipeline.lock';
$lock = @fopen($lockPath, 'c');

function fpcron_log(string $message): void
{
    echo '[' . gmdate('Y-m-d H:i:s') . ' +00:00] ' . $message . PHP_EOL;
}

fpcron_log('V3 fast pipeline cron start ' . PRV3_FAST_PIPELINE_CRON_VERSION);

if (!$lock) {
    fpcron_log('ERROR: could not open lock file: ' . $lockPath);
    exit(1);
}

if (!flock($lock, LOCK_EX | LOCK_NB)) {
    fpcron_log('SKIP: previous V3 fast pipeline run is still active.');
    fclose($lock);
    exit(0);
}

$php = defined('PHP_BINARY') && PHP_BINARY ? PHP_BINARY : '/usr/local/bin/php';
$script = $appRoot . '/cli/pre_ride_email_v3_fast_pipeline.php';
if (!is_file($script)) {
    fpcron_log('ERROR: pipeline runner missing: ' . $script);
    flock($lock, LOCK_UN);
    fclose($lock);
    exit(1);
}

$cmd = [
    $php,
    $script,
    '--limit=50',
    '--commit',
    '--no-readiness-report',
];

$descriptorSpec = [
    0 => ['pipe', 'r'],
    1 => ['pipe', 'w'],
    2 => ['pipe', 'w'],
];
$process = proc_open($cmd, $descriptorSpec, $pipes, null, null, ['bypass_shell' => true]);
if (!is_resource($process)) {
    fpcron_log('ERROR: failed to start pipeline runner.');
    flock($lock, LOCK_UN);
    fclose($lock);
    exit(1);
}

fclose($pipes[0]);
$stdout = stream_get_contents($pipes[1]);
$stderr = stream_get_contents($pipes[2]);
fclose($pipes[1]);
fclose($pipes[2]);
$exit = proc_close($process);

$summaryLine = '';
foreach (explode("\n", trim((string)$stdout)) as $line) {
    if (preg_match('/^(Steps:|\[[0-9]+\/[0-9]+\])/', $line)) {
        fpcron_log($line);
    }
    if (str_starts_with($line, 'Steps:')) {
        $summaryLine = $line;
    }
}
if (trim((string)$stderr) !== '') {
    fpcron_log('STDERR ' . trim((string)$stderr));
}
fpcron_log('SUMMARY ok=' . ($exit === 0 ? 'yes' : 'no') . ' exit_code=' . $exit . ($summaryLine !== '' ? ' ' . $summaryLine : ''));
fpcron_log('V3 fast pipeline cron finish exit_code=' . $exit);

flock($lock, LOCK_UN);
fclose($lock);
exit($exit === 0 ? 0 : 1);
