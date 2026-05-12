<?php
/**
 * gov.cabnet.app — CLI Safe Handoff Package Validator
 *
 * Validates a generated safe handoff ZIP without extracting it.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    echo "Not found.\n";
    exit(1);
}

$homeRoot = dirname(__DIR__, 2);
$appRoot = $homeRoot . '/gov.cabnet.app_app';
$validatorFile = $appRoot . '/src/Support/SafeHandoffPackageValidator.php';

if (!is_file($validatorFile)) {
    fwrite(STDERR, "ERROR: SafeHandoffPackageValidator.php not found: {$validatorFile}\n");
    exit(1);
}
require_once $validatorFile;

function vh_arg_value(array $argv, string $name, ?string $default = null): ?string
{
    $prefix = '--' . $name . '=';
    foreach ($argv as $arg) {
        if (str_starts_with($arg, $prefix)) {
            return substr($arg, strlen($prefix));
        }
    }
    return $default;
}

function vh_has_flag(array $argv, string $name): bool
{
    return in_array('--' . $name, $argv, true);
}

function vh_usage(): string
{
    return <<<TXT
gov.cabnet.app Safe Handoff Package Validator

Usage:
  php /home/cabnet/gov.cabnet.app_app/cli/validate_safe_handoff_package.php --file=/absolute/path/package.zip
  php /home/cabnet/gov.cabnet.app_app/cli/validate_safe_handoff_package.php --latest
  php /home/cabnet/gov.cabnet.app_app/cli/validate_safe_handoff_package.php --latest --json

Options:
  --file=PATH       Validate a specific ZIP.
  --latest          Validate newest package from /home/cabnet/gov.cabnet.app_app/var/handoff-packages.
  --json            Print JSON.
  --help            Show this help.

TXT;
}

function vh_latest_package(string $dir): string
{
    if (!is_dir($dir)) {
        throw new RuntimeException('Package directory not found: ' . $dir);
    }
    $matches = glob(rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'gov_cabnet_safe_handoff_*.zip') ?: [];
    if (!$matches) {
        throw new RuntimeException('No handoff packages found in: ' . $dir);
    }
    usort($matches, static fn(string $a, string $b): int => (filemtime($b) ?: 0) <=> (filemtime($a) ?: 0));
    return $matches[0];
}

if (vh_has_flag($argv, 'help')) {
    echo vh_usage();
    exit(0);
}

$json = vh_has_flag($argv, 'json');
$packageDir = $appRoot . '/var/handoff-packages';

try {
    $file = vh_arg_value($argv, 'file');
    if (!$file && vh_has_flag($argv, 'latest')) {
        $file = vh_latest_package($packageDir);
    }
    if (!$file) {
        throw new RuntimeException('Provide --file=/path/package.zip or --latest.');
    }

    $validator = new \Bridge\Support\SafeHandoffPackageValidator();
    $result = $validator->validate((string)$file);

    if ($json) {
        echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
    } else {
        echo "Safe handoff package validation\n";
        echo "Package: " . $result['zip_path'] . "\n";
        echo "Status: " . (!empty($result['ok']) ? 'OK' : 'REVIEW REQUIRED') . "\n";
        echo "Entries: " . $result['entry_count'] . "\n";
        echo "Size: " . number_format((float)$result['size_bytes']) . " bytes\n";
        echo "SHA256: " . $result['sha256'] . "\n";
        echo "Database export: " . (!empty($result['has_database_export']) ? 'YES' : 'NO') . "\n\n";
        if (!empty($result['warnings'])) {
            echo "Warnings:\n";
            foreach ($result['warnings'] as $warning) {
                echo "- {$warning}\n";
            }
            echo "\n";
        }
        if (!empty($result['dangerous_entries'])) {
            echo "Dangerous entries:\n";
            foreach ($result['dangerous_entries'] as $entry) {
                echo "- {$entry}\n";
            }
            echo "\n";
        }
        echo "Checks:\n";
        foreach (($result['checks'] ?? []) as $check) {
            echo "- {$check}\n";
        }
    }

    exit(!empty($result['ok']) ? 0 : 2);
} catch (Throwable $e) {
    $out = ['ok' => false, 'error' => $e->getMessage()];
    if ($json) {
        echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
    } else {
        fwrite(STDERR, "ERROR: " . $e->getMessage() . "\n");
    }
    exit(1);
}
