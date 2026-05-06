<?php
/**
 * gov.cabnet.app — Bolt mail auto preflight/dry-run evidence worker v4.3
 *
 * Auto-creates local normalized_bookings rows for valid future_candidate mail rows
 * and records dry-run evidence snapshots. It never creates submission_jobs and
 * never POSTs to EDXEIX.
 */

declare(strict_types=1);

use Bridge\Mail\BoltMailAutoDryRunService;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "CLI only\n";
    exit(1);
}

$container = require __DIR__ . '/../src/bootstrap.php';
$config = $container['config'];
$db = $container['db'];

$options = getopt('', ['limit::', 'preview-only', 'json', 'help']);

if (isset($options['help'])) {
    echo "Bolt Mail Auto Dry-run Worker\n";
    echo "Usage: php auto_bolt_mail_dry_run.php [--limit=25] [--preview-only] [--json]\n";
    echo "Safety: creates local normalized bookings and dry-run evidence only; no submission_jobs; no EDXEIX POST.\n";
    exit(0);
}

$limit = isset($options['limit']) ? max(1, min(200, (int)$options['limit'])) : 25;
$previewOnly = array_key_exists('preview-only', $options);
$json = array_key_exists('json', $options);

$result = [
    'ok' => false,
    'started_at' => date('c'),
    'finished_at' => null,
    'limit' => $limit,
    'preview_only' => $previewOnly,
    'summary' => null,
    'items' => [],
    'error' => null,
];

try {
    $service = new BoltMailAutoDryRunService($db, $config);
    $run = $service->run($limit, $previewOnly, 'auto-cron');
    $result = array_replace($result, $run);
} catch (Throwable $e) {
    $result['error'] = $e->getMessage();
}

$result['finished_at'] = date('c');

if ($json) {
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;
} else {
    echo '[' . $result['finished_at'] . '] Bolt Mail Auto Dry-run Worker' . PHP_EOL;
    if ($result['ok']) {
        $s = $result['summary'];
        echo 'OK candidates=' . $s['candidate_rows']
            . ' created_bookings=' . $s['created_bookings']
            . ' linked_existing=' . $s['linked_existing_bookings']
            . ' evidence_recorded=' . $s['evidence_recorded']
            . ' evidence_existing=' . $s['evidence_existing']
            . ' blocked=' . $s['blocked']
            . ' errors=' . $s['errors'] . PHP_EOL;
        echo 'Safety: local preflight bookings + dry-run evidence only; no submission_jobs; no EDXEIX POST.' . PHP_EOL;
    } else {
        echo 'ERROR: ' . $result['error'] . PHP_EOL;
    }
}

exit($result['ok'] ? 0 : 1);
