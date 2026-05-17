<?php
/**
 * gov.cabnet.app — Pre-ride readiness watch CLI.
 * v3.2.28
 *
 * Safe by default: no EDXEIX transport, no AADE call, no queue job.
 */

declare(strict_types=1);

require_once '/home/cabnet/gov.cabnet.app_app/lib/edxeix_pre_ride_readiness_watch_lib.php';

function prw_cli_bool($value): bool
{
    if (is_bool($value)) {
        return $value;
    }
    return in_array(strtolower((string)$value), ['1', 'true', 'yes', 'on'], true);
}

function prw_cli_options(array $argv): array
{
    $options = [
        'json' => false,
        'capture_ready' => false,
        'debug_source' => false,
        'include_latest_ready' => true,
        'debug_lines' => 24,
    ];

    foreach ($argv as $arg) {
        if ($arg === '--json') { $options['json'] = true; continue; }
        if ($arg === '--capture-ready') { $options['capture_ready'] = true; continue; }
        if ($arg === '--debug-source') { $options['debug_source'] = true; continue; }
        if ($arg === '--no-latest-ready') { $options['include_latest_ready'] = false; continue; }
        if (str_starts_with($arg, '--debug-lines=')) {
            $options['debug_lines'] = max(5, min(60, (int)substr($arg, 14)));
            continue;
        }
        if (str_starts_with($arg, '--capture-ready=')) {
            $options['capture_ready'] = prw_cli_bool(substr($arg, 16));
            continue;
        }
        if (str_starts_with($arg, '--debug-source=')) {
            $options['debug_source'] = prw_cli_bool(substr($arg, 15));
            continue;
        }
    }

    return $options;
}

$options = prw_cli_options($argv ?? []);

try {
    $result = gov_prw_run($options);
    if (!empty($options['json'])) {
        echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        exit(0);
    }

    echo 'Pre-ride readiness watch: ' . ($result['classification']['code'] ?? 'UNKNOWN') . PHP_EOL;
    echo ($result['classification']['message'] ?? '') . PHP_EOL;
    echo 'Transport performed: NO' . PHP_EOL;
    $captured = $result['captured_candidate_id'] ?? null;
    if ($captured) {
        echo 'Captured candidate ID: ' . $captured . PHP_EOL;
    }
    echo ($result['next_action'] ?? '') . PHP_EOL;
    exit(0);
} catch (Throwable $e) {
    $payload = [
        'ok' => false,
        'classification' => [
            'code' => 'WATCH_ERROR',
            'message' => $e->getMessage(),
        ],
        'transport_performed' => false,
    ];
    if (!empty($options['json'])) {
        echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    } else {
        fwrite(STDERR, 'Pre-ride readiness watch error: ' . $e->getMessage() . PHP_EOL);
    }
    exit(1);
}
