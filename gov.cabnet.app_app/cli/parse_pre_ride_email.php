<?php
/**
 * gov.cabnet.app — CLI parser for pasted/file-based Bolt pre-ride email text.
 *
 * Usage:
 *   php gov.cabnet.app_app/cli/parse_pre_ride_email.php --file=/path/to/email.txt --json
 *   cat /path/to/email.txt | php gov.cabnet.app_app/cli/parse_pre_ride_email.php --json
 *
 * Safety: no DB, no network, no EDXEIX, no AADE, no storage.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/BoltMail/BoltPreRideEmailParser.php';

use Bridge\BoltMail\BoltPreRideEmailParser;

function arg_value(array $argv, string $name): ?string
{
    $prefix = '--' . $name . '=';
    foreach ($argv as $arg) {
        if (str_starts_with($arg, $prefix)) {
            return substr($arg, strlen($prefix));
        }
    }
    return null;
}

function has_flag(array $argv, string $name): bool
{
    return in_array('--' . $name, $argv, true);
}

if (has_flag($argv, 'help')) {
    echo "Usage:\n";
    echo "  php parse_pre_ride_email.php --file=/path/to/email.txt --json\n";
    echo "  cat email.txt | php parse_pre_ride_email.php --json\n";
    echo "\nSafety: read-only parser only; no DB/network/EDXEIX/AADE calls.\n";
    exit(0);
}

$file = arg_value($argv, 'file');
$raw = '';

if ($file !== null && $file !== '') {
    if (!is_file($file) || !is_readable($file)) {
        fwrite(STDERR, "Email file not found or not readable: {$file}\n");
        exit(2);
    }
    $raw = (string)file_get_contents($file);
} else {
    $raw = (string)stream_get_contents(STDIN);
}

if (trim($raw) === '') {
    fwrite(STDERR, "No email text provided. Use --file=/path/to/email.txt or pipe text into STDIN.\n");
    exit(2);
}

$parser = new BoltPreRideEmailParser();
$result = $parser->parse($raw);

if (has_flag($argv, 'json')) {
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(empty($result['missing_required']) ? 0 : 1);
}

$fields = $result['fields'];
$generated = $result['generated'];

echo "Bolt pre-ride email parse result\n";
echo "================================\n";
echo "OK: " . (!empty($result['ok']) ? 'yes' : 'no') . "\n";
echo "Confidence: " . ($result['confidence'] ?? '') . "\n";
if (!empty($result['missing_required'])) {
    echo "Missing: " . implode(', ', $result['missing_required']) . "\n";
}
if (!empty($result['warnings'])) {
    echo "Warnings: " . implode(' | ', $result['warnings']) . "\n";
}
echo "\nFields\n";
echo "------\n";
foreach ($fields as $key => $value) {
    echo str_pad((string)$key, 28) . ': ' . (string)$value . "\n";
}
echo "\nDispatch summary\n";
echo "----------------\n";
echo (string)($generated['dispatch_summary'] ?? '') . "\n";
