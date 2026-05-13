<?php
/**
 * gov.cabnet.app — V3 live-submit cron worker scaffold with gate + approval enforcement.
 *
 * Safety:
 * - Calls the disabled live-submit scaffold worker only.
 * - No EDXEIX calls.
 * - No AADE calls.
 * - No production submission tables.
 */

declare(strict_types=1);

const PRV3_LIVE_SUBMIT_CRON_VERSION = 'v3.0.27-live-submit-gate-approval-cron-scaffold';

date_default_timezone_set('Europe/Athens');

function prv3lsc_app_root(): string
{
    return dirname(__DIR__);
}

function prv3lsc_log(string $message): void
{
    echo '[' . (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s P') . '] ' . $message . "\n";
}

function prv3lsc_lock_file(): string
{
    $lockDir = prv3lsc_app_root() . '/storage/locks';
    if (!is_dir($lockDir)) {
        @mkdir($lockDir, 0755, true);
    }
    return $lockDir . '/pre_ride_email_v3_live_submit_cron_worker.lock';
}

function prv3lsc_parse_summary_json(string $output): array
{
    $trim = trim($output);
    if ($trim === '') {
        return [];
    }
    $decoded = json_decode($trim, true);
    if (is_array($decoded) && isset($decoded['summary']) && is_array($decoded['summary'])) {
        return $decoded['summary'];
    }
    return [];
}

$lockPath = prv3lsc_lock_file();
$lock = @fopen($lockPath, 'c');
if (!$lock) {
    prv3lsc_log('ERROR: could not open lock file: ' . $lockPath);
    exit(1);
}
if (!flock($lock, LOCK_EX | LOCK_NB)) {
    prv3lsc_log('SKIP: previous V3 live-submit scaffold worker is still running.');
    exit(0);
}

$exitCode = 0;
try {
    $worker = __DIR__ . '/pre_ride_email_v3_live_submit_worker.php';
    if (!is_file($worker)) {
        throw new RuntimeException('Missing worker: ' . $worker);
    }

    prv3lsc_log('V3 live-submit cron scaffold start ' . PRV3_LIVE_SUBMIT_CRON_VERSION . ' mode=dry_run_select_only status=live_submit_ready limit=20 gate=required approval=required-if-configured');

    $cmd = escapeshellcmd(PHP_BINARY) . ' ' . escapeshellarg($worker) . ' --limit=20 --status=live_submit_ready --json';
    $lines = [];
    $childExit = 0;
    exec($cmd . ' 2>&1', $lines, $childExit);
    $output = implode("\n", $lines);
    $summary = prv3lsc_parse_summary_json($output);

    if ($summary) {
        prv3lsc_log('SUMMARY ok=' . (!empty($summary['ok']) ? 'yes' : 'no')
            . ' mode=' . ($summary['mode'] ?? '-')
            . ' db=' . ($summary['database'] ?? '-')
            . ' schema_ok=' . (!empty($summary['schema_ok']) ? 'yes' : 'no')
            . ' start_options=' . (!empty($summary['start_options_ok']) ? 'yes' : 'no')
            . ' approvals=' . (!empty($summary['approval_table_ok']) ? 'yes' : 'no')
            . ' gate_ok=' . (!empty($summary['gate_ok']) ? 'yes' : 'no')
            . ' gate_loaded=' . (!empty($summary['gate_config_loaded']) ? 'yes' : 'no')
            . ' rows=' . (int)($summary['rows_checked'] ?? 0)
            . ' pre_live_passed=' . (int)($summary['pre_live_passed_count'] ?? 0)
            . ' hard_enabled_eligible=' . (int)($summary['eligible_count'] ?? 0)
            . ' disabled_eligible=' . (int)($summary['eligible_but_hard_disabled_count'] ?? 0)
            . ' blocked=' . (int)($summary['blocked_count'] ?? 0)
            . ' valid_approvals=' . (int)($summary['valid_approval_count'] ?? 0)
            . ' warnings=' . (int)($summary['warning_count'] ?? 0)
            . ' live_hard_enabled=' . (!empty($summary['live_submit_hard_enabled']) ? 'yes' : 'no')
        );
        if (!empty($summary['error'])) {
            prv3lsc_log('ERROR: ' . (string)$summary['error']);
        }
    } else {
        prv3lsc_log('WARN: could not parse worker JSON output. Raw follows.');
        foreach (array_slice($lines, 0, 20) as $line) {
            prv3lsc_log('RAW: ' . $line);
        }
    }

    $exitCode = (int)$childExit;
} catch (Throwable $e) {
    $exitCode = 1;
    prv3lsc_log('ERROR: ' . $e->getMessage());
} finally {
    prv3lsc_log('V3 live-submit cron scaffold finish exit_code=' . $exitCode);
    flock($lock, LOCK_UN);
    fclose($lock);
}

exit($exitCode);
