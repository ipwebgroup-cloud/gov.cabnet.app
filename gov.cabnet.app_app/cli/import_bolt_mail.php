<?php
/**
 * gov.cabnet.app — Bolt Mail Intake CLI Scanner
 *
 * Production-safe cron entry for bolt-bridge@gov.cabnet.app.
 * Imports Bolt Ride details emails into bolt_mail_intake only.
 * Expires stale open intake rows after each run so old future candidates cannot remain actionable.
 * Does not create EDXEIX jobs and does not submit anything live.
 *
 * Usage:
 *   php import_bolt_mail.php
 *   php import_bolt_mail.php --limit=250 --days=30
 *   php import_bolt_mail.php --json
 */

declare(strict_types=1);

use Bridge\Mail\BoltMaildirScanner;
use Bridge\Mail\BoltMailIntakeMaintenance;
use Bridge\Mail\BoltPreRideEmailParser;
use Bridge\Mail\BoltPreRideImporter;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "CLI only\n";
    exit(1);
}

$startedAt = date('c');
$container = require __DIR__ . '/../src/bootstrap.php';
$config = $container['config'];
$db = $container['db'];

$options = getopt('', ['limit::', 'days::', 'json', 'help']);

if (isset($options['help'])) {
    echo "Bolt Mail Intake CLI\n";
    echo "Usage: php import_bolt_mail.php [--limit=250] [--days=30] [--json]\n";
    echo "Safety: imports mail intake rows only; expires stale candidates; no EDXEIX jobs; no live submit.\n";
    exit(0);
}

$limit = isset($options['limit']) ? max(1, min(1000, (int)$options['limit'])) : 250;
$days = isset($options['days']) ? max(1, min(90, (int)$options['days'])) : 30;
$json = array_key_exists('json', $options);

$maildir = (string)$config->get('mail.bolt_bridge_maildir', '');
if ($maildir === '') {
    $maildir = '/home/cabnet/mail/gov.cabnet.app/bolt-bridge';
}
$maildir = rtrim($maildir, '/');

$timezoneName = (string)$config->get('app.timezone', 'Europe/Athens');
$timezone = new DateTimeZone($timezoneName);
$futureGuard = (int)$config->get('edxeix.future_start_guard_minutes', 30);

$result = [
    'ok' => false,
    'started_at' => $startedAt,
    'finished_at' => null,
    'maildir' => $maildir,
    'limit' => $limit,
    'days' => $days,
    'future_guard_minutes' => $futureGuard,
    'summary' => null,
    'error' => null,
];

try {
    if (!is_dir($maildir)) {
        throw new RuntimeException('Maildir not found: ' . $maildir);
    }

    $scanner = new BoltMaildirScanner($maildir);
    $parser = new BoltPreRideEmailParser($timezone);
    $importer = new BoltPreRideImporter($db, $parser, $timezone, $futureGuard);
    $maintenance = new BoltMailIntakeMaintenance($db, $timezone);

    $summary = $importer->importFromScanner($scanner, $limit, $days);
    $expired = $maintenance->expirePastOpenRows();

    $result['ok'] = true;
    $result['summary'] = [
        'files' => (int)($summary['files'] ?? 0),
        'inserted' => (int)($summary['inserted'] ?? 0),
        'duplicates' => (int)($summary['duplicates'] ?? 0),
        'rejected' => (int)($summary['rejected'] ?? 0),
        'errors' => (int)($summary['errors'] ?? 0),
        'expired_open_rows' => $expired,
    ];
} catch (Throwable $e) {
    $result['error'] = $e->getMessage();
}

$result['finished_at'] = date('c');

if ($json) {
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;
} else {
    echo '[' . $result['finished_at'] . '] Bolt Mail Intake CLI' . PHP_EOL;
    echo 'Maildir: ' . $result['maildir'] . PHP_EOL;
    if ($result['ok']) {
        $s = $result['summary'];
        echo 'OK files=' . $s['files']
            . ' inserted=' . $s['inserted']
            . ' duplicates=' . $s['duplicates']
            . ' rejected=' . $s['rejected']
            . ' expired=' . $s['expired_open_rows']
            . ' errors=' . $s['errors'] . PHP_EOL;
        echo 'Safety: mail intake only; stale candidates expired; no EDXEIX jobs; no live submit.' . PHP_EOL;
    } else {
        echo 'ERROR: ' . $result['error'] . PHP_EOL;
    }
}

exit($result['ok'] ? 0 : 1);
