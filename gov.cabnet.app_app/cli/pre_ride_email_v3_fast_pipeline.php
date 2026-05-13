<?php
/**
 * gov.cabnet.app — V3 pre-ride email fast pipeline runner.
 *
 * Purpose:
 * - Run the isolated V3 automation chain in a single ordered pass.
 * - Reduce cron ordering/race delays between intake, expiry, starting-point guard,
 *   submit dry-run, and live-readiness stages.
 *
 * Safety:
 * - No EDXEIX calls.
 * - No AADE calls.
 * - No production submission_jobs writes.
 * - No production submission_attempts writes.
 * - No production pre-ride-email-tool.php changes.
 * - Child scripts write only to V3 queue/status/events when their commit flags are used.
 */

declare(strict_types=1);

const PRV3_FAST_PIPELINE_VERSION = 'v3.0.35-fast-pipeline-runner';

date_default_timezone_set('Europe/Athens');

/** @return array<string,mixed> */
function prv3fp_parse_args(array $argv): array
{
    $opts = [
        'help' => false,
        'json' => false,
        'commit' => false,
        'limit' => 50,
        'skip_live_readiness' => false,
        'include_readiness_report' => true,
    ];

    foreach (array_slice($argv, 1) as $arg) {
        if ($arg === '--help' || $arg === '-h') {
            $opts['help'] = true;
        } elseif ($arg === '--json') {
            $opts['json'] = true;
        } elseif ($arg === '--commit') {
            $opts['commit'] = true;
        } elseif ($arg === '--skip-live-readiness') {
            $opts['skip_live_readiness'] = true;
        } elseif ($arg === '--no-readiness-report') {
            $opts['include_readiness_report'] = false;
        } elseif (str_starts_with($arg, '--limit=')) {
            $opts['limit'] = max(1, min(500, (int)substr($arg, 8)));
        }
    }

    return $opts;
}

function prv3fp_usage(): void
{
    echo 'V3 fast pipeline runner ' . PRV3_FAST_PIPELINE_VERSION . "\n\n";
    echo "Usage:\n";
    echo "  php pre_ride_email_v3_fast_pipeline.php [--limit=50] [--commit] [--json]\n\n";
    echo "Default mode is rehearsal only where possible. --commit runs V3-only commit stages.\n";
    echo "Safety: no EDXEIX call, no AADE call, no production submission table writes.\n";
}

function prv3fp_app_root(): string
{
    return dirname(__DIR__);
}

function prv3fp_php_binary(): string
{
    if (defined('PHP_BINARY') && is_string(PHP_BINARY) && PHP_BINARY !== '') {
        return PHP_BINARY;
    }
    return '/usr/local/bin/php';
}

/** @return array{command:string,exit_code:int,ok:bool,stdout:string,stderr:string,duration_ms:int} */
function prv3fp_run_command(array $args, int $timeoutSeconds = 120): array
{
    $start = microtime(true);
    $cmd = array_map('strval', $args);
    $display = implode(' ', array_map('escapeshellarg', $cmd));

    $descriptorSpec = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $process = proc_open($cmd, $descriptorSpec, $pipes, null, null, ['bypass_shell' => true]);
    if (!is_resource($process)) {
        return [
            'command' => $display,
            'exit_code' => 127,
            'ok' => false,
            'stdout' => '',
            'stderr' => 'Failed to start process.',
            'duration_ms' => (int)round((microtime(true) - $start) * 1000),
        ];
    }

    fclose($pipes[0]);
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);

    $stdout = '';
    $stderr = '';
    $timedOut = false;

    while (true) {
        $status = proc_get_status($process);
        $stdout .= stream_get_contents($pipes[1]);
        $stderr .= stream_get_contents($pipes[2]);

        if (!$status['running']) {
            break;
        }
        if ((microtime(true) - $start) > $timeoutSeconds) {
            $timedOut = true;
            proc_terminate($process, 15);
            usleep(200000);
            $status = proc_get_status($process);
            if ($status['running']) {
                proc_terminate($process, 9);
            }
            break;
        }
        usleep(50000);
    }

    $stdout .= stream_get_contents($pipes[1]);
    $stderr .= stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);

    if ($timedOut) {
        $exitCode = 124;
        $stderr .= ($stderr !== '' ? "\n" : '') . 'Process timed out after ' . $timeoutSeconds . ' seconds.';
    }

    return [
        'command' => $display,
        'exit_code' => $exitCode,
        'ok' => $exitCode === 0,
        'stdout' => trim($stdout),
        'stderr' => trim($stderr),
        'duration_ms' => (int)round((microtime(true) - $start) * 1000),
    ];
}

/** @return array<int,array<string,mixed>> */
function prv3fp_steps(bool $commit, int $limit, bool $skipLiveReadiness, bool $includeReadinessReport): array
{
    $php = prv3fp_php_binary();
    $cli = prv3fp_app_root() . '/cli';
    $steps = [];

    $expiryArgs = [$php, $cli . '/pre_ride_email_v3_expiry_guard.php', '--limit=' . $limit];
    if ($commit) { $expiryArgs[] = '--commit'; }
    $steps[] = ['key' => 'expiry_before', 'label' => 'Expiry guard before intake', 'args' => $expiryArgs, 'required' => true];

    $intakeArgs = [$php, $cli . '/pre_ride_email_v3_intake.php', '--limit=' . $limit];
    if ($commit) { $intakeArgs[] = '--commit'; }
    $steps[] = ['key' => 'intake', 'label' => 'Maildir intake', 'args' => $intakeArgs, 'required' => true];

    $expiryAfterIntake = [$php, $cli . '/pre_ride_email_v3_expiry_guard.php', '--limit=' . $limit];
    if ($commit) { $expiryAfterIntake[] = '--commit'; }
    $steps[] = ['key' => 'expiry_after_intake', 'label' => 'Expiry guard after intake', 'args' => $expiryAfterIntake, 'required' => true];

    $startGuardArgs = [$php, $cli . '/pre_ride_email_v3_starting_point_guard.php', '--limit=' . $limit];
    if ($commit) { $startGuardArgs[] = '--commit'; }
    $steps[] = ['key' => 'starting_point_guard', 'label' => 'Starting-point guard', 'args' => $startGuardArgs, 'required' => true];

    $submitArgs = [$php, $cli . '/pre_ride_email_v3_submit_dry_run_worker.php', '--status=queued', '--limit=' . $limit, '--min-future-minutes=0'];
    if ($commit) { $submitArgs[] = '--commit'; }
    $steps[] = ['key' => 'submit_dry_run', 'label' => 'Submit dry-run readiness', 'args' => $submitArgs, 'required' => true];

    $expiryAfterSubmit = [$php, $cli . '/pre_ride_email_v3_expiry_guard.php', '--limit=' . $limit];
    if ($commit) { $expiryAfterSubmit[] = '--commit'; }
    $steps[] = ['key' => 'expiry_after_submit', 'label' => 'Expiry guard after submit dry-run', 'args' => $expiryAfterSubmit, 'required' => true];

    if (!$skipLiveReadiness) {
        $liveArgs = [$php, $cli . '/pre_ride_email_v3_live_submit_readiness.php', '--limit=' . $limit];
        if ($commit) { $liveArgs[] = '--commit'; }
        $steps[] = ['key' => 'live_readiness', 'label' => 'Live-submit readiness gate', 'args' => $liveArgs, 'required' => true];
    }

    if ($includeReadinessReport) {
        $steps[] = [
            'key' => 'automation_readiness',
            'label' => 'Automation readiness report',
            'args' => [$php, $cli . '/pre_ride_email_v3_automation_readiness.php'],
            'required' => false,
        ];
    }

    return $steps;
}

$opts = prv3fp_parse_args($argv);
if ($opts['help']) {
    prv3fp_usage();
    exit(0);
}

$started = new DateTimeImmutable('now', new DateTimeZone('Europe/Athens'));
$summary = [
    'ok' => false,
    'version' => PRV3_FAST_PIPELINE_VERSION,
    'mode' => $opts['commit'] ? 'commit_v3_pipeline_only' : 'dry_run_pipeline_preview',
    'started_at' => $started->format(DATE_ATOM),
    'finished_at' => '',
    'limit' => (int)$opts['limit'],
    'steps_total' => 0,
    'steps_ok' => 0,
    'steps_failed' => 0,
    'safety' => [
        'edxeix_call' => false,
        'aade_call' => false,
        'production_submission_jobs' => false,
        'production_submission_attempts' => false,
        'production_pre_ride_tool_change' => false,
        'v3_status_events_only_when_commit' => true,
    ],
];

$results = [];
foreach (prv3fp_steps((bool)$opts['commit'], (int)$opts['limit'], (bool)$opts['skip_live_readiness'], (bool)$opts['include_readiness_report']) as $step) {
    $summary['steps_total']++;
    $result = prv3fp_run_command($step['args']);
    $result['key'] = $step['key'];
    $result['label'] = $step['label'];
    $result['required'] = (bool)$step['required'];
    $results[] = $result;

    if ($result['ok']) {
        $summary['steps_ok']++;
    } else {
        $summary['steps_failed']++;
        if ($step['required']) {
            // Continue to produce complete diagnostics, but final ok remains false.
        }
    }
}

$summary['ok'] = $summary['steps_failed'] === 0;
$summary['finished_at'] = (new DateTimeImmutable('now', new DateTimeZone('Europe/Athens')))->format(DATE_ATOM);

if ($opts['json']) {
    echo json_encode(['summary' => $summary, 'steps' => $results], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "\n";
    exit($summary['ok'] ? 0 : 1);
}

echo 'V3 fast pipeline runner ' . PRV3_FAST_PIPELINE_VERSION . "\n";
echo 'Mode: ' . $summary['mode'] . "\n";
echo 'Limit: ' . $summary['limit'] . "\n";
echo 'Steps: ' . $summary['steps_total'] . ' | OK: ' . $summary['steps_ok'] . ' | Failed: ' . $summary['steps_failed'] . "\n";
echo "Safety: No EDXEIX call. No AADE call. No production submission tables.\n";
if (!$opts['commit']) {
    echo "Dry-run pipeline preview. Add --commit to run V3-only status/event commit stages.\n";
} else {
    echo "Commit pipeline wrote only through existing V3-only stage workers.\n";
}
echo "\n";

foreach ($results as $idx => $step) {
    echo '[' . ($idx + 1) . '/' . $summary['steps_total'] . '] ' . ($step['ok'] ? 'OK' : 'FAIL') . ' — ' . $step['label'] . ' (' . $step['duration_ms'] . " ms)\n";
    if ($step['stdout'] !== '') {
        $lines = explode("\n", $step['stdout']);
        foreach (array_slice($lines, 0, 12) as $line) {
            echo '  ' . $line . "\n";
        }
        if (count($lines) > 12) {
            echo '  ... ' . (count($lines) - 12) . " more line(s)\n";
        }
    }
    if ($step['stderr'] !== '') {
        echo '  STDERR: ' . str_replace("\n", "\n  STDERR: ", $step['stderr']) . "\n";
    }
    echo "\n";
}

exit($summary['ok'] ? 0 : 1);
