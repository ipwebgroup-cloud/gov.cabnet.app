<?php
declare(strict_types=1);

const PRV3_AUTOMATION_READINESS_CLI_VERSION = 'v3.0.32-automation-readiness-cli';

$appRoot = dirname(__DIR__);
$bootstrapFile = $appRoot . '/src/bootstrap.php';
$reportFile = $appRoot . '/src/BoltMailV3/AutomationReadinessReportV3.php';

foreach ([$bootstrapFile, $reportFile] as $file) {
    if (!is_file($file)) {
        fwrite(STDERR, "Missing required file: {$file}\n");
        exit(2);
    }
    require_once $file;
}

use Bridge\BoltMailV3\AutomationReadinessReportV3;

function prv3_arg_enabled(string $name): bool
{
    global $argv;
    return in_array($name, array_slice($argv, 1), true);
}

function prv3_help(): string
{
    return <<<TEXT
V3 automation readiness report

Usage:
  php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_automation_readiness.php [--json]

Safety:
  Read-only. No EDXEIX call. No AADE call. No DB writes. No production submission table writes.

TEXT;
}

if (prv3_arg_enabled('--help') || prv3_arg_enabled('-h')) {
    echo prv3_help();
    exit(0);
}

try {
    $ctx = require $bootstrapFile;
    if (!is_array($ctx) || !isset($ctx['db']) || !method_exists($ctx['db'], 'connection')) {
        throw new RuntimeException('Bootstrap did not return DB context.');
    }
    $db = $ctx['db']->connection();
    if (!$db instanceof mysqli) {
        throw new RuntimeException('DB connection is not mysqli.');
    }

    $report = (new AutomationReadinessReportV3($db, $appRoot))->build();
    $report['cli_version'] = PRV3_AUTOMATION_READINESS_CLI_VERSION;

    if (prv3_arg_enabled('--json')) {
        echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        exit(0);
    }

    $r = $report['readiness'];
    $q = $report['queue'];
    $g = $report['live_submit_config'];
    $c = $report['cron'];

    echo "V3 automation readiness " . PRV3_AUTOMATION_READINESS_CLI_VERSION . "\n";
    echo "Report: " . ($report['version'] ?? '') . "\n";
    echo "Database: " . ($report['database'] ?? '') . "\n";
    echo "Ready for V3 manual handoff: " . (!empty($r['ready_for_v3_manual_handoff']) ? 'yes' : 'no') . "\n";
    echo "Ready for future live submit: " . (!empty($r['ready_for_future_live_submit']) ? 'yes' : 'no') . "\n";
    echo "Next action: " . ($r['current_next_action'] ?? '') . "\n\n";

    echo "Queue: total=" . (int)($q['total'] ?? 0)
        . " active=" . (int)($q['active'] ?? 0)
        . " future_active=" . (int)($q['future_active'] ?? 0)
        . " submit_dry_run_ready=" . (int)($q['submit_dry_run_ready'] ?? 0)
        . " live_submit_ready=" . (int)($q['live_submit_ready'] ?? 0)
        . " submitted=" . (int)($q['submitted'] ?? 0)
        . " blocked=" . (int)($q['blocked'] ?? 0) . "\n";

    echo "Cron: present=" . (!empty($c['all_required_present']) ? 'yes' : 'no')
        . " fresh=" . (!empty($c['critical_fresh']) ? 'yes' : 'no') . "\n";

    echo "Gate: loaded=" . (!empty($g['config_loaded']) ? 'yes' : 'no')
        . " enabled=" . (!empty($g['enabled']) ? 'yes' : 'no')
        . " mode=" . (string)($g['mode'] ?? '')
        . " adapter=" . (string)($g['adapter'] ?? '')
        . " hard=" . (!empty($g['hard_enable_live_submit']) ? 'yes' : 'no')
        . " ok=" . (!empty($g['ok_for_future_live_submit']) ? 'yes' : 'no') . "\n";

    if (!empty($g['blocks']) && is_array($g['blocks'])) {
        foreach ($g['blocks'] as $block) {
            echo "Gate block: " . (string)$block . "\n";
        }
    }

    echo "\nSafety: No EDXEIX call. No AADE call. No DB writes. No production submission tables.\n";
    exit(0);
} catch (Throwable $e) {
    $error = [
        'ok' => false,
        'version' => PRV3_AUTOMATION_READINESS_CLI_VERSION,
        'error' => $e->getMessage(),
        'safety' => [
            'edxeix_call' => false,
            'aade_call' => false,
            'db_writes' => false,
        ],
    ];
    if (prv3_arg_enabled('--json')) {
        echo json_encode($error, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    } else {
        fwrite(STDERR, "ERROR: " . $e->getMessage() . "\n");
    }
    exit(1);
}
