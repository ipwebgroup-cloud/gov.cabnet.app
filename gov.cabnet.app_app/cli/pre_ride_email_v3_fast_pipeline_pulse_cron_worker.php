<?php
declare(strict_types=1);

/**
 * V3 Fast Pipeline Pulse Cron Worker
 *
 * Intended cPanel cron target. Runs the fast pipeline pulse in V3-only commit mode.
 */

const PRV3_FAST_PIPELINE_PULSE_CRON_VERSION = 'v3.0.37-fast-pipeline-pulse-cron-worker';

$appRoot = realpath(__DIR__ . '/..');
if ($appRoot === false) {
    echo "[" . date('Y-m-d H:i:s P') . "] ERROR: Could not resolve app root.\n";
    exit(2);
}

$pulse = $appRoot . '/cli/pre_ride_email_v3_fast_pipeline_pulse.php';
if (!is_file($pulse)) {
    echo "[" . date('Y-m-d H:i:s P') . "] ERROR: Missing pulse runner: {$pulse}\n";
    exit(2);
}

echo "[" . date('Y-m-d H:i:s P') . "] V3 fast pipeline pulse cron start " . PRV3_FAST_PIPELINE_PULSE_CRON_VERSION . "\n";

$cmd = escapeshellcmd(PHP_BINARY) . ' ' . escapeshellarg($pulse) . ' --commit --limit=50 --cycles=5 --sleep=10 --max-runtime=55';
passthru($cmd . ' 2>&1', $exitCode);

echo "[" . date('Y-m-d H:i:s P') . "] V3 fast pipeline pulse cron finish exit_code={$exitCode}\n";
exit((int)$exitCode);
