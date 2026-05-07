#!/usr/bin/env php
<?php
/**
 * gov.cabnet.app — v4.8 Credential Rotation Acknowledgement Marker
 *
 * Writes a no-secret JSON marker after manual credential rotation.
 * This script does not rotate credentials and must never be given secrets.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only.\n");
    exit(1);
}

function usage(): void
{
    $script = basename(__FILE__);
    echo "Usage:\n";
    echo "  /usr/local/bin/php {$script} --ops-key --bolt --edxeix --mailbox [--by=Andreas] [--notes='short note']\n\n";
    echo "This writes only an acknowledgement marker. It does not store passwords, API keys, tokens, cookies, or sessions.\n";
}

$options = getopt('', [
    'ops-key',
    'bolt',
    'edxeix',
    'mailbox',
    'by::',
    'notes::',
    'help',
]);

if (isset($options['help'])) {
    usage();
    exit(0);
}

$items = [
    'ops_key' => array_key_exists('ops-key', $options),
    'bolt_credentials' => array_key_exists('bolt', $options),
    'edxeix_credentials' => array_key_exists('edxeix', $options),
    'mailbox_credentials' => array_key_exists('mailbox', $options),
];

$missing = [];
foreach ($items as $key => $done) {
    if (!$done) {
        $missing[] = $key;
    }
}

if ($missing) {
    fwrite(STDERR, "Missing acknowledgement flags: " . implode(', ', $missing) . "\n\n");
    usage();
    exit(1);
}

$completedBy = trim((string)($options['by'] ?? 'ops'));
$notes = trim((string)($options['notes'] ?? ''));

if ($completedBy === '') {
    $completedBy = 'ops';
}

$appRoot = dirname(__DIR__);
$markerDir = $appRoot . '/storage/security';
$markerFile = $markerDir . '/credential_rotation_ack.json';

if (!is_dir($markerDir) && !mkdir($markerDir, 0750, true) && !is_dir($markerDir)) {
    fwrite(STDERR, "Could not create marker directory: {$markerDir}\n");
    exit(1);
}

$payload = [
    'ok' => true,
    'completed_at' => date('c'),
    'completed_by' => $completedBy,
    'items' => $items,
    'notes' => $notes,
    'safety' => 'Acknowledgement only. No secrets, passwords, API keys, tokens, cookies, or session contents are stored in this file.',
    'generated_by' => 'gov.cabnet.app_app/cli/mark_credential_rotation.php',
];

$json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
if (!is_string($json)) {
    fwrite(STDERR, "Could not encode marker JSON.\n");
    exit(1);
}

$tmp = $markerFile . '.tmp.' . getmypid();
if (file_put_contents($tmp, $json . PHP_EOL, LOCK_EX) === false) {
    fwrite(STDERR, "Could not write temporary marker file: {$tmp}\n");
    exit(1);
}

chmod($tmp, 0640);
if (!rename($tmp, $markerFile)) {
    @unlink($tmp);
    fwrite(STDERR, "Could not move marker into place: {$markerFile}\n");
    exit(1);
}

chmod($markerFile, 0640);

echo '[' . date('c') . "] Credential rotation acknowledgement recorded.\n";
echo "Marker: {$markerFile}\n";
echo "Safety: no secrets stored. Live EDXEIX submit remains controlled by config and is not changed by this script.\n";
