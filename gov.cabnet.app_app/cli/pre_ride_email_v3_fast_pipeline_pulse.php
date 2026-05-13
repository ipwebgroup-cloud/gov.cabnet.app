<?php
declare(strict_types=1);

/**
 * V3 Fast Pipeline Pulse Runner
 *
 * Runs the existing V3 fast pipeline repeatedly inside one cron minute to reduce
 * waiting time between Maildir delivery and V3 queue/readiness movement.
 *
 * Safety:
 * - Delegates only to pre_ride_email_v3_fast_pipeline.php.
 * - No EDXEIX call.
 * - No AADE call.
 * - No production submission tables.
 * - Commit mode is still V3-only because the child pipeline is V3-only.
 */

const PRV3_FAST_PIPELINE_PULSE_VERSION = 'v3.0.37-fast-pipeline-pulse-runner';

$appRoot = realpath(__DIR__ . '/..');
if ($appRoot === false) {
    fwrite(STDERR, "ERROR: Could not resolve app root.\n");
    exit(2);
}

$pipeline = $appRoot . '/cli/pre_ride_email_v3_fast_pipeline.php';
$lockDir = $appRoot . '/storage/locks';
$lockFile = $lockDir . '/pre_ride_email_v3_fast_pipeline_pulse.lock';

$args = $argv ?? [];
$help = in_array('--help', $args, true) || in_array('-h', $args, true);
$commit = in_array('--commit', $args, true);

function prv3_pulse_arg_int(array $args, string $name, int $default, int $min, int $max): int
{
    foreach ($args as $arg) {
        if (strpos($arg, $name . '=') === 0) {
            $value = (int)substr($arg, strlen($name) + 1);
            if ($value < $min) {
                return $min;
            }
            if ($value > $max) {
                return $max;
            }
            return $value;
        }
    }
    return $default;
}

$limit = prv3_pulse_arg_int($args, '--limit', 50, 1, 250);
$cycles = prv3_pulse_arg_int($args, '--cycles', 5, 1, 10);
$sleepSeconds = prv3_pulse_arg_int($args, '--sleep', 10, 1, 20);
$maxRuntime = prv3_pulse_arg_int($args, '--max-runtime', 55, 10, 58);

if ($help) {
    echo "V3 Fast Pipeline Pulse Runner " . PRV3_FAST_PIPELINE_PULSE_VERSION . "\n";
    echo "Usage:\n";
    echo "  php pre_ride_email_v3_fast_pipeline_pulse.php [--commit] [--limit=50] [--cycles=5] [--sleep=10] [--max-runtime=55]\n\n";
    echo "Default mode is dry-run preview. Add --commit for V3-only pipeline commit stages.\n";
    exit(0);
}

if (!is_file($pipeline)) {
    fwrite(STDERR, "ERROR: Missing child pipeline: {$pipeline}\n");
    exit(2);
}

if (!is_dir($lockDir) && !mkdir($lockDir, 0755, true) && !is_dir($lockDir)) {
    fwrite(STDERR, "ERROR: Could not create lock dir: {$lockDir}\n");
    exit(2);
}

$lockHandle = fopen($lockFile, 'c');
if ($lockHandle === false) {
    fwrite(STDERR, "ERROR: Could not open lock file: {$lockFile}\n");
    exit(2);
}

if (!flock($lockHandle, LOCK_EX | LOCK_NB)) {
    echo "[" . date('Y-m-d H:i:s P') . "] V3 fast pipeline pulse already running; exiting safely.\n";
    exit(0);
}

ftruncate($lockHandle, 0);
fwrite($lockHandle, json_encode([
    'version' => PRV3_FAST_PIPELINE_PULSE_VERSION,
    'pid' => getmypid(),
    'started_at' => date('c'),
], JSON_UNESCAPED_SLASHES) . "\n");
fflush($lockHandle);

$started = time();
$okCount = 0;
$failCount = 0;
$cycleResults = [];

$modeLabel = $commit ? 'commit_v3_pipeline_pulse_only' : 'dry_run_pulse_preview';
echo "V3 fast pipeline pulse " . PRV3_FAST_PIPELINE_PULSE_VERSION . "\n";
echo "Mode: {$modeLabel}\n";
echo "Cycles: {$cycles} | Sleep: {$sleepSeconds}s | Limit: {$limit} | Max runtime: {$maxRuntime}s\n";
echo "Safety: No EDXEIX call. No AADE call. No production submission tables.\n";

for ($i = 1; $i <= $cycles; $i++) {
    $elapsed = time() - $started;
    if ($elapsed >= $maxRuntime) {
        echo "\n[PULSE] Max runtime reached before cycle {$i}; stopping safely.\n";
        break;
    }

    $cmd = escapeshellcmd(PHP_BINARY) . ' ' . escapeshellarg($pipeline) . ' --limit=' . (int)$limit;
    if ($commit) {
        $cmd .= ' --commit';
    }

    $cycleStart = microtime(true);
    $output = [];
    $exitCode = 0;
    exec($cmd . ' 2>&1', $output, $exitCode);
    $durationMs = (int)round((microtime(true) - $cycleStart) * 1000);

    $status = ($exitCode === 0) ? 'OK' : 'FAIL';
    if ($exitCode === 0) {
        $okCount++;
    } else {
        $failCount++;
    }

    $summaryLine = '';
    foreach ($output as $line) {
        if (strpos($line, 'Steps:') !== false || strpos($line, 'Queue:') === 0 || strpos($line, 'Next action:') === 0) {
            $summaryLine = $line;
            break;
        }
    }

    $cycleResults[] = [
        'cycle' => $i,
        'status' => $status,
        'exit_code' => $exitCode,
        'duration_ms' => $durationMs,
        'summary' => $summaryLine,
    ];

    echo "\n[PULSE {$i}/{$cycles}] {$status} exit={$exitCode} duration={$durationMs}ms\n";
    if ($summaryLine !== '') {
        echo "  {$summaryLine}\n";
    }

    $printed = 0;
    foreach ($output as $line) {
        if ($printed >= 14) {
            $remaining = count($output) - $printed;
            if ($remaining > 0) {
                echo "  ... {$remaining} more line(s)\n";
            }
            break;
        }
        echo "  {$line}\n";
        $printed++;
    }

    if ($i < $cycles) {
        $remainingRuntime = $maxRuntime - (time() - $started);
        if ($remainingRuntime <= $sleepSeconds) {
            echo "\n[PULSE] Not enough runtime left for another sleep; stopping safely.\n";
            break;
        }
        sleep($sleepSeconds);
    }
}

$totalElapsed = time() - $started;
echo "\nPulse summary: cycles_run=" . count($cycleResults) . " ok={$okCount} failed={$failCount} elapsed={$totalElapsed}s\n";
echo "Pulse finish exit_code=" . ($failCount > 0 ? 1 : 0) . "\n";

flock($lockHandle, LOCK_UN);
fclose($lockHandle);

exit($failCount > 0 ? 1 : 0);
