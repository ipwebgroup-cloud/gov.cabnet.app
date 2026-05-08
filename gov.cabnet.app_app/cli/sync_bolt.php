<?php
/**
 * gov.cabnet.app — Bolt Fleet Orders Sync
 *
 * Uses the canonical library sync path:
 * - OAuth token
 * - POST /fleetintegration/v1/getFleetOrders
 * - raw payload storage
 * - normalized_bookings upsert
 *
 * Safety:
 * - does not create EDXEIX submission jobs
 * - does not call EDXEIX
 * - does not issue AADE receipts
 */

declare(strict_types=1);

require_once __DIR__ . '/../lib/bolt_sync_lib.php';

$hours = 48;
$dryRun = false;

foreach ($argv ?? [] as $arg) {
    if ($arg === '--dry-run') {
        $dryRun = true;
        continue;
    }

    if (preg_match('/^--hours=(\d+)$/', $arg, $m)) {
        $hours = max(1, min(720, (int)$m[1]));
    }
}

try {
    $beforeJobs = 0;
    $beforeAttempts = 0;

    $db = gov_bridge_db();

    $row = gov_bridge_fetch_one($db, 'SELECT COUNT(*) AS c FROM submission_jobs');
    $beforeJobs = (int)($row['c'] ?? 0);

    $row = gov_bridge_fetch_one($db, 'SELECT COUNT(*) AS c FROM submission_attempts');
    $beforeAttempts = (int)($row['c'] ?? 0);

    $result = gov_bolt_sync_orders($hours, $dryRun);

    $row = gov_bridge_fetch_one($db, 'SELECT COUNT(*) AS c FROM submission_jobs');
    $afterJobs = (int)($row['c'] ?? 0);

    $row = gov_bridge_fetch_one($db, 'SELECT COUNT(*) AS c FROM submission_attempts');
    $afterAttempts = (int)($row['c'] ?? 0);

    echo json_encode([
        'ok' => true,
        'script' => 'cli/sync_bolt.php',
        'hours' => $hours,
        'dry_run' => $dryRun,
        'result' => $result,
        'safety' => [
            'does_not_call_edxeix' => true,
            'does_not_create_submission_jobs' => true,
            'does_not_create_submission_attempts' => true,
            'submission_jobs_before' => $beforeJobs,
            'submission_jobs_after' => $afterJobs,
            'submission_attempts_before' => $beforeAttempts,
            'submission_attempts_after' => $afterAttempts,
        ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;

    exit(0);
} catch (Throwable $e) {
    echo json_encode([
        'ok' => false,
        'script' => 'cli/sync_bolt.php',
        'error' => $e->getMessage(),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;

    exit(1);
}
