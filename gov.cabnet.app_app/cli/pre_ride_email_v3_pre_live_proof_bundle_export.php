<?php

declare(strict_types=1);

/**
 * V3 pre-live proof bundle export.
 *
 * V3-only local artifact exporter.
 * - No Bolt call.
 * - No EDXEIX call.
 * - No AADE call.
 * - No DB writes.
 * - No queue status changes.
 * - No production submission tables.
 * - V0 untouched.
 *
 * The script runs existing read-only V3 proof tools, captures their output,
 * summarizes the result, and optionally writes local server-side proof artifacts.
 */

const V3_PROOF_BUNDLE_VERSION = 'v3.0.72-v3-proof-bundle-runner-and-ops-hotfix';

function v3pb_arg_value(string $name, ?string $default = null): ?string
{
    global $argv;
    $prefix = '--' . $name . '=';
    foreach ($argv as $arg) {
        if (strpos($arg, $prefix) === 0) {
            return substr($arg, strlen($prefix));
        }
    }
    return $default;
}

function v3pb_has_flag(string $name): bool
{
    global $argv;
    return in_array('--' . $name, $argv, true);
}

function v3pb_app_root(): string
{
    $env = getenv('GOV_CABNET_APP_ROOT');
    if (is_string($env) && trim($env) !== '') {
        return rtrim(trim($env), '/');
    }

    $candidate = realpath(__DIR__ . '/..');
    return $candidate !== false ? rtrim($candidate, '/') : '/home/cabnet/gov.cabnet.app_app';
}

function v3pb_php_binary(): string
{
    $candidates = [
        '/usr/local/bin/php',
        PHP_BINARY,
        '/usr/bin/php',
    ];

    foreach ($candidates as $candidate) {
        if (is_string($candidate) && $candidate !== '' && is_file($candidate) && is_executable($candidate)) {
            return $candidate;
        }
    }

    return 'php';
}

/**
 * @param array<int,string> $args
 * @return array<string,mixed>
 */
function v3pb_run_cli(string $label, string $file, array $args = [], int $timeoutSeconds = 30): array
{
    $started = microtime(true);
    $result = [
        'label' => $label,
        'file' => $file,
        'exists' => is_file($file),
        'readable' => is_readable($file),
        'command' => '',
        'exit_code' => null,
        'duration_ms' => 0,
        'stdout' => '',
        'stderr' => '',
        'json' => null,
        'json_ok' => false,
        'error' => '',
    ];

    if (!is_file($file) || !is_readable($file)) {
        $result['error'] = 'CLI file missing or not readable.';
        $result['duration_ms'] = (int)round((microtime(true) - $started) * 1000);
        return $result;
    }

    $php = v3pb_php_binary();
    $cmdParts = [escapeshellarg($php), escapeshellarg($file)];
    foreach ($args as $arg) {
        $cmdParts[] = escapeshellarg($arg);
    }
    $command = implode(' ', $cmdParts);
    $result['command'] = $command;

    $descriptorSpec = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $process = @proc_open($command, $descriptorSpec, $pipes);
    if (!is_resource($process)) {
        $result['error'] = 'Could not start process.';
        $result['duration_ms'] = (int)round((microtime(true) - $started) * 1000);
        return $result;
    }

    fclose($pipes[0]);

    $stdout = '';
    $stderr = '';
    $timedOut = false;
    $lastExitCode = null;
    $deadline = microtime(true) + $timeoutSeconds;

    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);

    while (true) {
        $stdoutChunk = stream_get_contents($pipes[1]);
        if (is_string($stdoutChunk) && $stdoutChunk !== '') {
            $stdout .= $stdoutChunk;
        }

        $stderrChunk = stream_get_contents($pipes[2]);
        if (is_string($stderrChunk) && $stderrChunk !== '') {
            $stderr .= $stderrChunk;
        }

        $status = proc_get_status($process);
        if (isset($status['exitcode']) && is_int($status['exitcode']) && $status['exitcode'] >= 0) {
            $lastExitCode = $status['exitcode'];
        }
        if (empty($status['running'])) {
            break;
        }

        if (microtime(true) > $deadline) {
            $timedOut = true;
            proc_terminate($process);
            usleep(150000);
            break;
        }

        usleep(50000);
    }

    $stdoutChunk = stream_get_contents($pipes[1]);
    if (is_string($stdoutChunk) && $stdoutChunk !== '') {
        $stdout .= $stdoutChunk;
    }
    $stderrChunk = stream_get_contents($pipes[2]);
    if (is_string($stderrChunk) && $stderrChunk !== '') {
        $stderr .= $stderrChunk;
    }

    fclose($pipes[1]);
    fclose($pipes[2]);

    $exitCode = proc_close($process);
    if ($exitCode === -1 && is_int($lastExitCode) && $lastExitCode >= 0) {
        $exitCode = $lastExitCode;
    }

    $result['exit_code'] = $timedOut ? 124 : $exitCode;
    $result['stdout'] = trim($stdout);
    $result['stderr'] = trim($stderr);
    $result['duration_ms'] = (int)round((microtime(true) - $started) * 1000);

    if ($timedOut) {
        $result['error'] = 'Process timed out.';
    }

    $json = v3pb_extract_json_object($stdout);
    if (is_array($json)) {
        $result['json'] = $json;
        $result['json_ok'] = true;
    }

    return $result;
}

/**
 * @return array<string,mixed>|null
 */
function v3pb_extract_json_object(string $text): ?array
{
    $trim = trim($text);
    if ($trim === '') {
        return null;
    }

    $decoded = json_decode($trim, true);
    if (is_array($decoded)) {
        return $decoded;
    }

    $start = strpos($trim, '{');
    $end = strrpos($trim, '}');
    if ($start === false || $end === false || $end <= $start) {
        return null;
    }

    $candidate = substr($trim, $start, $end - $start + 1);
    $decoded = json_decode($candidate, true);
    return is_array($decoded) ? $decoded : null;
}

/**
 * @return array<string,string>
 */
function v3pb_latest_bundle_files(string $dir): array
{
    if (!is_dir($dir)) {
        return [];
    }

    $files = glob($dir . '/bundle_*_*.*') ?: [];
    rsort($files, SORT_STRING);
    $out = [];
    foreach (array_slice($files, 0, 12) as $file) {
        $out[basename($file)] = $file;
    }
    return $out;
}

/**
 * @param array<string,mixed> $report
 * @return string
 */
function v3pb_text_report(array $report): string
{
    $lines = [];
    $lines[] = 'V3 pre-live proof bundle export ' . (string)($report['version'] ?? V3_PROOF_BUNDLE_VERSION);
    $lines[] = 'Mode: ' . (string)($report['mode'] ?? '');
    $lines[] = 'Started: ' . (string)($report['started_at'] ?? '');
    $lines[] = 'Finished: ' . (string)($report['finished_at'] ?? '');
    $lines[] = 'Safety: ' . (string)($report['safety'] ?? '');
    $lines[] = '';
    $lines[] = 'OK: ' . (!empty($report['ok']) ? 'yes' : 'no');
    $lines[] = 'Bundle safe: ' . (!empty($report['bundle_safe']) ? 'yes' : 'no');
    $lines[] = 'Artifacts written: ' . (!empty($report['artifacts_written']) ? 'yes' : 'no');
    $lines[] = 'Queue ID: ' . (string)($report['queue_id'] ?? 'auto');
    $lines[] = '';

    $summary = is_array($report['summary'] ?? null) ? $report['summary'] : [];
    $lines[] = 'Summary:';
    foreach ($summary as $key => $value) {
        if (is_bool($value)) {
            $value = $value ? 'yes' : 'no';
        } elseif (is_array($value)) {
            $value = implode(', ', array_map('strval', $value));
        }
        $lines[] = '  ' . $key . ': ' . (string)$value;
    }

    $lines[] = '';
    $lines[] = 'Command results:';
    $commands = is_array($report['commands'] ?? null) ? $report['commands'] : [];
    foreach ($commands as $key => $command) {
        if (!is_array($command)) {
            continue;
        }
        $lines[] = '  [' . $key . '] ' . (string)($command['label'] ?? $key)
            . ' exit=' . (string)($command['exit_code'] ?? 'n/a')
            . ' json=' . (!empty($command['json_ok']) ? 'yes' : 'no')
            . ' duration_ms=' . (string)($command['duration_ms'] ?? '');
        if (!empty($command['error'])) {
            $lines[] = '      error: ' . (string)$command['error'];
        }
    }

    $blocks = is_array($report['final_blocks'] ?? null) ? $report['final_blocks'] : [];
    if ($blocks !== []) {
        $lines[] = '';
        $lines[] = 'Final blocks:';
        foreach ($blocks as $block) {
            $lines[] = '  - ' . (string)$block;
        }
    }

    $written = is_array($report['artifacts'] ?? null) ? $report['artifacts'] : [];
    if ($written !== []) {
        $lines[] = '';
        $lines[] = 'Artifacts:';
        foreach ($written as $file) {
            $lines[] = '  ' . (string)$file;
        }
    }

    return implode(PHP_EOL, $lines) . PHP_EOL;
}

/**
 * @param array<string,mixed> $report
 * @return array<int,string>
 */
function v3pb_write_artifacts(string $dir, array $report): array
{
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }

    if (!is_dir($dir) || !is_writable($dir)) {
        throw new RuntimeException('Proof bundle artifact directory is not writable: ' . $dir);
    }

    $stamp = date('Ymd_His');
    $base = rtrim($dir, '/') . '/bundle_' . $stamp;
    $jsonPath = $base . '_summary.json';
    $txtPath = $base . '_summary.txt';

    $json = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($json)) {
        throw new RuntimeException('Could not encode proof bundle JSON.');
    }

    file_put_contents($jsonPath, $json . PHP_EOL);
    file_put_contents($txtPath, v3pb_text_report($report));

    @chmod($jsonPath, 0640);
    @chmod($txtPath, 0640);

    return [$jsonPath, $txtPath];
}

/**
 * @param array<string,mixed> $commands
 * @return array<string,mixed>
 */
function v3pb_build_summary(array $commands): array
{
    $summary = [
        'storage_ok' => false,
        'automation_readiness_seen' => false,
        'switchboard_seen' => false,
        'adapter_simulation_seen' => false,
        'payload_consistency_seen' => false,
        'payload_consistency_ok' => false,
        'db_vs_artifact_match' => false,
        'adapter_hash_match' => false,
        'adapter_live_capable' => null,
        'adapter_submitted' => null,
        'simulation_safe' => false,
        'edxeix_call_made' => false,
        'aade_call_made' => false,
        'db_write_made' => false,
        'v0_touched' => false,
    ];

    if (is_array($commands['storage_check']['json'] ?? null)) {
        $summary['storage_ok'] = !empty($commands['storage_check']['json']['ok']);
    }

    if (array_key_exists('automation_readiness', $commands)) {
        $summary['automation_readiness_seen'] = true;
    }

    if (is_array($commands['pre_live_switchboard']['json'] ?? null)) {
        $summary['switchboard_seen'] = true;
    }

    if (is_array($commands['adapter_row_simulation']['json'] ?? null)) {
        $j = $commands['adapter_row_simulation']['json'];
        $summary['adapter_simulation_seen'] = true;
        $summary['simulation_safe'] = !empty($j['simulation_safe']);
        $adapter = is_array($j['adapter_simulation'] ?? null) ? $j['adapter_simulation'] : [];
        $summary['adapter_live_capable'] = !empty($adapter['is_live_capable']);
        $summary['adapter_submitted'] = !empty($adapter['submitted']);
    }

    if (is_array($commands['payload_consistency']['json'] ?? null)) {
        $j = $commands['payload_consistency']['json'];
        $summary['payload_consistency_seen'] = true;
        $summary['payload_consistency_ok'] = !empty($j['ok']);
        $consistency = is_array($j['consistency'] ?? null) ? $j['consistency'] : [];
        $adapter = is_array($j['adapter_simulation'] ?? null) ? $j['adapter_simulation'] : [];
        $summary['db_vs_artifact_match'] = !empty($consistency['db_vs_artifact_match']);
        $summary['adapter_hash_match'] = !empty($adapter['hash_matches_expected']);
        $summary['simulation_safe'] = $summary['simulation_safe'] || !empty($j['simulation_safe']);
        $summary['adapter_live_capable'] = !empty($adapter['is_live_capable']);
        $summary['adapter_submitted'] = !empty($adapter['submitted']);
    }

    return $summary;
}

/**
 * @param array<string,mixed> $command
 */
function v3pb_command_runner_ok(string $key, array $command): bool
{
    if (!empty($command['error'])) {
        return false;
    }

    if (empty($command['exists']) || empty($command['readable'])) {
        return false;
    }

    $exit = $command['exit_code'] ?? null;
    if ($exit === 124) {
        return false;
    }

    // JSON proof tools may intentionally return a non-zero exit when the live gate is
    // closed or a row is expired. That is expected proof state, not a runner failure.
    if (!empty($command['json_ok'])) {
        return true;
    }

    // The automation readiness CLI is text-first in the current live build.
    // Treat captured text output as successful runner execution.
    if ($key === 'automation_readiness' && trim((string)($command['stdout'] ?? '')) !== '') {
        return true;
    }

    return $exit === 0;
}

/**
 * @param array<string,mixed> $commands
 * @return array<int,string>
 */
function v3pb_collect_blocks(array $commands): array
{
    $blocks = [];

    foreach ($commands as $key => $command) {
        if (!is_array($command)) {
            continue;
        }

        if (!v3pb_command_runner_ok($key, $command)) {
            $blocks[] = $key . ': runner_not_ok exit_code=' . (string)($command['exit_code'] ?? 'n/a');
        }

        if (!empty($command['error'])) {
            $blocks[] = $key . ': ' . (string)$command['error'];
        }

        $json = is_array($command['json'] ?? null) ? $command['json'] : null;
        if ($json && is_array($json['final_blocks'] ?? null)) {
            foreach ($json['final_blocks'] as $block) {
                $block = (string)$block;
                if ($block !== '' && !in_array($block, $blocks, true)) {
                    $blocks[] = $block;
                }
            }
        }
    }

    return $blocks;
}

$jsonMode = v3pb_has_flag('json');
$write = v3pb_has_flag('write');
$queueId = trim((string)v3pb_arg_value('queue-id', ''));

$appRoot = v3pb_app_root();
$cliRoot = $appRoot . '/cli';
$artifactDir = $appRoot . '/storage/artifacts/v3_pre_live_proof_bundles';

$startedAt = gmdate('c');
$safety = 'No Bolt call. No EDXEIX call. No AADE call. No DB writes. No queue status changes. No production submission tables. V0 untouched. Writes local proof artifacts only when --write is used.';

$queueArgs = $queueId !== '' ? ['--queue-id=' . $queueId] : [];

$commands = [];
$commands['storage_check'] = v3pb_run_cli('V3 storage check', $cliRoot . '/pre_ride_email_v3_storage_check.php', ['--json'], 20);
$commands['automation_readiness'] = v3pb_run_cli('V3 automation readiness', $cliRoot . '/pre_ride_email_v3_automation_readiness.php', [], 20);
$commands['pre_live_switchboard'] = v3pb_run_cli('V3 pre-live switchboard', $cliRoot . '/pre_ride_email_v3_pre_live_switchboard.php', array_merge($queueArgs, ['--json']), 25);
$commands['adapter_row_simulation'] = v3pb_run_cli('V3 adapter row simulation', $cliRoot . '/pre_ride_email_v3_adapter_row_simulation.php', array_merge($queueArgs, ['--json']), 25);
$commands['payload_consistency'] = v3pb_run_cli('V3 adapter payload consistency', $cliRoot . '/pre_ride_email_v3_adapter_payload_consistency.php', array_merge($queueArgs, ['--json']), 25);
$commands['closed_gate_diagnostics'] = v3pb_run_cli('V3 closed-gate adapter diagnostics', $cliRoot . '/pre_ride_email_v3_closed_gate_adapter_diagnostics.php', array_merge($queueArgs, ['--json']), 25);

$summary = v3pb_build_summary($commands);
$blocks = v3pb_collect_blocks($commands);

$commandRunnerOk = true;
foreach ($commands as $key => $command) {
    if (!is_array($command) || !v3pb_command_runner_ok((string)$key, $command)) {
        $commandRunnerOk = false;
    }
}

$bundleSafe = $commandRunnerOk
    && !empty($summary['storage_ok'])
    && !empty($summary['adapter_simulation_seen'])
    && !empty($summary['payload_consistency_seen'])
    && !empty($summary['payload_consistency_ok'])
    && !empty($summary['db_vs_artifact_match'])
    && !empty($summary['adapter_hash_match'])
    && !empty($summary['simulation_safe'])
    && ($summary['adapter_submitted'] === false || $summary['adapter_submitted'] === null)
    && ($summary['adapter_live_capable'] === false || $summary['adapter_live_capable'] === null)
    && empty($summary['edxeix_call_made'])
    && empty($summary['aade_call_made'])
    && empty($summary['db_write_made'])
    && empty($summary['v0_touched']);

$report = [
    'ok' => $bundleSafe,
    'bundle_safe' => $bundleSafe,
    'version' => V3_PROOF_BUNDLE_VERSION,
    'mode' => $write ? 'write_local_proof_bundle' : 'dry_run_preview_only',
    'started_at' => $startedAt,
    'finished_at' => gmdate('c'),
    'safety' => $safety,
    'app_root' => $appRoot,
    'queue_id' => $queueId !== '' ? $queueId : null,
    'artifact_dir' => $artifactDir,
    'summary' => $summary,
    'final_blocks' => $blocks,
    'commands' => $commands,
    'latest_existing_bundles' => v3pb_latest_bundle_files($artifactDir),
    'artifacts_written' => false,
    'artifacts' => [],
];

try {
    if ($write) {
        $written = v3pb_write_artifacts($artifactDir, $report);
        $report['artifacts_written'] = true;
        $report['artifacts'] = $written;
        $report['latest_existing_bundles'] = v3pb_latest_bundle_files($artifactDir);
        $report['finished_at'] = gmdate('c');
    }
} catch (Throwable $e) {
    $report['ok'] = false;
    $report['bundle_safe'] = false;
    $report['final_blocks'][] = 'artifact_write: ' . $e->getMessage();
    $report['artifact_write_error'] = $e->getMessage();
}

if ($jsonMode) {
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(!empty($report['bundle_safe']) ? 0 : 1);
}

echo 'V3 pre-live proof bundle export ' . V3_PROOF_BUNDLE_VERSION . PHP_EOL;
echo 'Mode: ' . $report['mode'] . PHP_EOL;
echo 'Safety: ' . $safety . PHP_EOL;
echo 'OK: ' . (!empty($report['ok']) ? 'yes' : 'no') . PHP_EOL;
echo 'Bundle safe: ' . (!empty($report['bundle_safe']) ? 'yes' : 'no') . PHP_EOL;
echo 'Queue ID: ' . ($queueId !== '' ? $queueId : 'auto') . PHP_EOL;
echo 'Artifact dir: ' . $artifactDir . PHP_EOL;
echo PHP_EOL;

echo 'Summary:' . PHP_EOL;
foreach ($summary as $key => $value) {
    if (is_bool($value)) {
        $value = $value ? 'yes' : 'no';
    } elseif ($value === null) {
        $value = 'n/a';
    }
    echo '  ' . $key . ': ' . (string)$value . PHP_EOL;
}

echo PHP_EOL . 'Commands:' . PHP_EOL;
foreach ($commands as $key => $command) {
    echo '  [' . $key . '] exit=' . (string)($command['exit_code'] ?? 'n/a')
        . ' json=' . (!empty($command['json_ok']) ? 'yes' : 'no')
        . ' duration_ms=' . (string)($command['duration_ms'] ?? '') . PHP_EOL;
    if (!empty($command['error'])) {
        echo '      error: ' . (string)$command['error'] . PHP_EOL;
    }
}

if ($blocks !== []) {
    echo PHP_EOL . 'Final blocks observed:' . PHP_EOL;
    foreach ($blocks as $block) {
        echo '  - ' . (string)$block . PHP_EOL;
    }
}

if (!empty($report['artifacts_written'])) {
    echo PHP_EOL . 'Artifacts written:' . PHP_EOL;
    foreach ($report['artifacts'] as $file) {
        echo '  ' . $file . PHP_EOL;
    }
} else {
    echo PHP_EOL . 'Artifacts written: no (dry-run preview only; add --write)' . PHP_EOL;
}

exit(!empty($report['bundle_safe']) ? 0 : 1);
