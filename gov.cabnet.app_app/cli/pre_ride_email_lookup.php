<?php
/**
 * Read-only CLI helper: parse a Bolt pre-ride email and resolve EDXEIX IDs from DB.
 * Usage:
 *   php pre_ride_email_lookup.php < email.txt
 *   php pre_ride_email_lookup.php --latest-maildir
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/BoltMail/BoltPreRideEmailParser.php';
require_once dirname(__DIR__) . '/src/BoltMail/EdxeixMappingLookup.php';
require_once dirname(__DIR__) . '/src/BoltMail/MaildirPreRideEmailLoader.php';

use Bridge\BoltMail\BoltPreRideEmailParser;
use Bridge\BoltMail\EdxeixMappingLookup;
use Bridge\BoltMail\MaildirPreRideEmailLoader;

function out(array $payload): void
{
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
}

$latest = in_array('--latest-maildir', $argv, true);
$raw = '';
$mailInfo = null;

if ($latest) {
    $loader = new MaildirPreRideEmailLoader();
    $mailInfo = $loader->loadLatest();
    if (empty($mailInfo['ok'])) {
        out(['ok' => false, 'error' => $mailInfo['error'] ?? 'No matching email found.', 'mail' => $mailInfo]);
        exit(1);
    }
    $raw = (string)$mailInfo['email_text'];
} else {
    $raw = stream_get_contents(STDIN);
}

if (trim($raw) === '') {
    out(['ok' => false, 'error' => 'No email text supplied.']);
    exit(1);
}

try {
    $parser = new BoltPreRideEmailParser();
    $parsed = $parser->parse($raw);

    $ctx = require dirname(__DIR__) . '/src/bootstrap.php';
    $lookup = new EdxeixMappingLookup($ctx['db']->connection());
    $mapping = $lookup->lookup($parsed['fields'] ?? []);

    out([
        'ok' => true,
        'mail' => $mailInfo,
        'parsed' => [
            'ok' => $parsed['ok'] ?? false,
            'confidence' => $parsed['confidence'] ?? '',
            'missing_required' => $parsed['missing_required'] ?? [],
            'warnings' => $parsed['warnings'] ?? [],
            'fields' => $parsed['fields'] ?? [],
        ],
        'mapping' => $mapping,
    ]);
} catch (Throwable $e) {
    out(['ok' => false, 'error' => $e->getMessage()]);
    exit(1);
}
