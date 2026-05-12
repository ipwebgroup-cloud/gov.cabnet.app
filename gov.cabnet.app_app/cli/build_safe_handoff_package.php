<?php
/**
 * gov.cabnet.app — CLI Safe Handoff Package Builder
 *
 * Builds the same safe handoff ZIP from SSH/terminal, outside the public webroot.
 *
 * Safety contract:
 * - Does not call Bolt.
 * - Does not call EDXEIX.
 * - Does not call AADE.
 * - Does not expose real config values.
 * - Stores generated ZIPs under the private app folder by default.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    echo "Not found.\n";
    exit(1);
}

@set_time_limit(0);

$homeRoot = dirname(__DIR__, 2);
$appRoot = $homeRoot . '/gov.cabnet.app_app';
$builderFile = $appRoot . '/src/Support/SafeHandoffPackageBuilder.php';

if (!is_file($builderFile)) {
    fwrite(STDERR, "ERROR: SafeHandoffPackageBuilder.php not found: {$builderFile}\n");
    exit(1);
}

require_once $builderFile;

function gov_cli_arg_value(array $argv, string $name, ?string $default = null): ?string
{
    $prefix = '--' . $name . '=';
    foreach ($argv as $arg) {
        if (str_starts_with($arg, $prefix)) {
            return substr($arg, strlen($prefix));
        }
    }
    return $default;
}

function gov_cli_has_flag(array $argv, string $name): bool
{
    return in_array('--' . $name, $argv, true);
}

function gov_cli_usage(): string
{
    return <<<TXT
gov.cabnet.app Safe Handoff Package CLI

Usage:
  php /home/cabnet/gov.cabnet.app_app/cli/build_safe_handoff_package.php [options]

Options:
  --no-db                 Build package without DATABASE_EXPORT.sql.
  --output-dir=PATH       Private output directory. Default: /home/cabnet/gov.cabnet.app_app/var/handoff-packages
  --keep-days=N           Remove generated packages older than N days. Default: 7.
  --cleanup               Only cleanup old packages, then exit.
  --json                  Print machine-readable JSON result.
  --help                  Show this help.

Examples:
  php /home/cabnet/gov.cabnet.app_app/cli/build_safe_handoff_package.php

  php /home/cabnet/gov.cabnet.app_app/cli/build_safe_handoff_package.php --no-db

  php /home/cabnet/gov.cabnet.app_app/cli/build_safe_handoff_package.php --json

Safety:
  The ZIP includes sanitized config placeholders only. Real files from
  /home/cabnet/gov.cabnet.app_config are not copied.
  If DATABASE_EXPORT.sql is included, treat the ZIP as private operational data.

TXT;
}

/**
 * @return array{removed:int,errors:array<int,string>}
 */
function gov_cli_cleanup_packages(string $dir, int $keepDays): array
{
    $out = ['removed' => 0, 'errors' => []];
    if (!is_dir($dir)) {
        return $out;
    }

    $cutoff = time() - max(1, $keepDays) * 86400;
    $items = @scandir($dir);
    if (!is_array($items)) {
        $out['errors'][] = "Unable to scan output directory: {$dir}";
        return $out;
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        if (!preg_match('/^gov_cabnet_safe_handoff_.*\.zip$/', $item)) {
            continue;
        }
        $path = rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $item;
        if (!is_file($path)) {
            continue;
        }
        $mtime = @filemtime($path);
        if ($mtime === false || $mtime >= $cutoff) {
            continue;
        }
        if (@unlink($path)) {
            $out['removed']++;
        } else {
            $out['errors'][] = "Unable to remove old package: {$path}";
        }
    }

    return $out;
}

if (gov_cli_has_flag($argv, 'help')) {
    echo gov_cli_usage();
    exit(0);
}

$json = gov_cli_has_flag($argv, 'json');
$includeDb = !gov_cli_has_flag($argv, 'no-db');
$outputDir = gov_cli_arg_value($argv, 'output-dir', $appRoot . '/var/handoff-packages');
$outputDir = rtrim((string)$outputDir, DIRECTORY_SEPARATOR);
$keepDaysRaw = gov_cli_arg_value($argv, 'keep-days', '7');
$keepDays = is_numeric($keepDaysRaw) ? max(1, (int)$keepDaysRaw) : 7;

$result = [
    'ok' => false,
    'mode' => gov_cli_has_flag($argv, 'cleanup') ? 'cleanup' : 'build',
    'include_database' => $includeDb,
    'output_dir' => $outputDir,
    'keep_days' => $keepDays,
    'cleanup' => null,
    'zip_path' => '',
    'zip_size_bytes' => 0,
    'zip_sha256' => '',
    'error' => '',
];

try {
    if (!is_dir($outputDir) && !mkdir($outputDir, 0700, true) && !is_dir($outputDir)) {
        throw new RuntimeException('Unable to create private output directory: ' . $outputDir);
    }
    @chmod($outputDir, 0700);

    $cleanup = gov_cli_cleanup_packages($outputDir, $keepDays);
    $result['cleanup'] = $cleanup;

    if (gov_cli_has_flag($argv, 'cleanup')) {
        $result['ok'] = count($cleanup['errors']) === 0;
        if ($json) {
            echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
        } else {
            echo "Cleanup complete. Removed old packages: " . $cleanup['removed'] . "\n";
            foreach ($cleanup['errors'] as $err) {
                echo "WARN: {$err}\n";
            }
        }
        exit($result['ok'] ? 0 : 1);
    }

    $builder = new \Bridge\Support\SafeHandoffPackageBuilder([
        'homeRoot' => $homeRoot,
        'publicRoot' => $homeRoot . '/public_html/gov.cabnet.app',
        'appRoot' => $homeRoot . '/gov.cabnet.app_app',
        'configRoot' => $homeRoot . '/gov.cabnet.app_config',
        'sqlRoot' => $homeRoot . '/gov.cabnet.app_sql',
        'docsRoot' => $homeRoot . '/docs',
        'toolsRoot' => $homeRoot . '/tools',
        'bootstrap' => $homeRoot . '/gov.cabnet.app_app/src/bootstrap.php',
    ]);

    $tmpZip = $builder->build(['include_database' => $includeDb]);

    $stamp = date('Ymd_His');
    $finalName = 'gov_cabnet_safe_handoff_' . $stamp . ($includeDb ? '_with_db' : '_no_db') . '.zip';
    $finalPath = $outputDir . DIRECTORY_SEPARATOR . $finalName;

    if (!@rename($tmpZip, $finalPath)) {
        if (!@copy($tmpZip, $finalPath)) {
            @unlink($tmpZip);
            throw new RuntimeException('Unable to move generated package into private output directory.');
        }
        @unlink($tmpZip);
    }

    @chmod($finalPath, 0600);

    $size = filesize($finalPath);
    $hash = hash_file('sha256', $finalPath);

    $result['ok'] = true;
    $result['zip_path'] = $finalPath;
    $result['zip_size_bytes'] = $size !== false ? (int)$size : 0;
    $result['zip_sha256'] = is_string($hash) ? $hash : '';

    if ($json) {
        echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
    } else {
        echo "Safe handoff package created.\n";
        echo "Path: {$finalPath}\n";
        echo "Size: " . number_format((float)$result['zip_size_bytes']) . " bytes\n";
        echo "SHA256: {$result['zip_sha256']}\n";
        echo "Includes database export: " . ($includeDb ? 'YES' : 'NO') . "\n";
        echo "\nTreat this ZIP as private operational material.\n";
    }

    exit(0);
} catch (Throwable $e) {
    $result['error'] = $e->getMessage();
    if ($json) {
        echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
    } else {
        fwrite(STDERR, "ERROR: " . $e->getMessage() . "\n");
    }
    exit(1);
}
