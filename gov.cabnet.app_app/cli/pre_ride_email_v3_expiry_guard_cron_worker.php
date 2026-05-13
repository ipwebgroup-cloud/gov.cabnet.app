<?php
/**
 * gov.cabnet.app — V3 queue expiry guard cron worker.
 *
 * Runs the V3 expiry guard in commit mode with an app-owned lock.
 * No EDXEIX calls. No AADE calls. V3 queue/status/events only.
 */

declare(strict_types=1);

const PRV3_EXPIRY_CRON_VERSION = 'v3.0.34-v3-queue-expiry-guard-cron';

date_default_timezone_set('Europe/Athens');

$appRoot = dirname(__DIR__);
$lockDir = $appRoot . '/storage/locks';
if (!is_dir($lockDir)) {
    @mkdir($lockDir, 0755, true);
}
$lockFile = $lockDir . '/pre_ride_email_v3_expiry_guard_cron.lock';
$lock = @fopen($lockFile, 'c');
if (!$lock) {
    echo '[' . gmdate('Y-m-d H:i:s') . ' +00:00] ERROR: could not open lock file: ' . $lockFile . PHP_EOL;
    exit(1);
}
if (!flock($lock, LOCK_EX | LOCK_NB)) {
    echo '[' . gmdate('Y-m-d H:i:s') . ' +00:00] V3 expiry guard cron already running; exiting' . PHP_EOL;
    exit(0);
}

$script = __DIR__ . '/pre_ride_email_v3_expiry_guard.php';
if (!is_file($script)) {
    echo '[' . gmdate('Y-m-d H:i:s') . ' +00:00] ERROR: expiry guard script missing: ' . $script . PHP_EOL;
    exit(2);
}

$php = PHP_BINARY ?: '/usr/local/bin/php';
$cmd = escapeshellarg($php) . ' ' . escapeshellarg($script) . ' --limit=500 --min-future-minutes=0 --commit --json';

echo '[' . gmdate('Y-m-d H:i:s') . ' +00:00] V3 expiry guard cron start ' . PRV3_EXPIRY_CRON_VERSION . PHP_EOL;
$output = [];
$exitCode = 0;
exec($cmd . ' 2>&1', $output, $exitCode);
$text = implode("\n", $output);
$data = json_decode($text, true);

if (is_array($data) && isset($data['summary']) && is_array($data['summary'])) {
    $s = $data['summary'];
    echo '[' . gmdate('Y-m-d H:i:s') . ' +00:00] SUMMARY ok=' . (!empty($s['ok']) ? 'yes' : 'no')
        . ' mode=' . (string)($s['mode'] ?? '-')
        . ' db=' . (string)($s['database'] ?? '-')
        . ' schema_ok=' . (!empty($s['schema_ok']) ? 'yes' : 'no')
        . ' rows=' . (int)($s['rows_checked'] ?? 0)
        . ' expired=' . (int)($s['expired_count'] ?? 0)
        . ' future_safe=' . (int)($s['future_safe_count'] ?? 0)
        . ' blocked=' . (int)($s['blocked_count'] ?? 0)
        . ' events=' . (int)($s['events_inserted'] ?? 0)
        . ((string)($s['error'] ?? '') !== '' ? ' error=' . (string)$s['error'] : '')
        . PHP_EOL;
    if (!empty($data['rows']) && is_array($data['rows'])) {
        $printed = 0;
        foreach ($data['rows'] as $row) {
            if (empty($row['expired'])) {
                continue;
            }
            echo '[' . gmdate('Y-m-d H:i:s') . ' +00:00] EXPIRED queue_id=' . (int)($row['queue_id'] ?? 0)
                . ' old_status=' . (string)($row['old_status'] ?? '')
                . ' pickup=' . (string)($row['pickup_datetime'] ?? '')
                . ' minutes=' . (($row['minutes_until'] ?? null) === null ? '-' : (string)$row['minutes_until'])
                . ' transfer=' . (string)($row['customer_name'] ?? '') . ' / ' . (string)($row['driver_name'] ?? '') . ' / ' . (string)($row['vehicle_plate'] ?? '')
                . ' blocked=' . (!empty($row['blocked']) ? 'yes' : 'no')
                . PHP_EOL;
            $printed++;
            if ($printed >= 5) {
                break;
            }
        }
    }
} else {
    echo '[' . gmdate('Y-m-d H:i:s') . ' +00:00] RAW_OUTPUT_BEGIN' . PHP_EOL;
    echo $text . PHP_EOL;
    echo '[' . gmdate('Y-m-d H:i:s') . ' +00:00] RAW_OUTPUT_END' . PHP_EOL;
}

echo '[' . gmdate('Y-m-d H:i:s') . ' +00:00] V3 expiry guard cron finish exit_code=' . $exitCode . PHP_EOL;

flock($lock, LOCK_UN);
fclose($lock);
exit($exitCode === 0 ? 0 : 1);
