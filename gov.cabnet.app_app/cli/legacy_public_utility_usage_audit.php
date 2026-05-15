<?php
/**
 * gov.cabnet.app — Legacy Public Utility Usage Audit CLI.
 *
 * Read-only usage evidence scanner for legacy guarded public-root utilities.
 * It does not move, delete, redirect, write files, connect to DB, call Bolt,
 * call EDXEIX, or call AADE.
 */

declare(strict_types=1);

const GOV_LEGACY_PUBLIC_UTILITY_USAGE_AUDIT_VERSION = 'v3.0.93-legacy-public-utility-usage-audit-route-summary';
const GOV_LEGACY_PUBLIC_UTILITY_USAGE_AUDIT_SAFETY = 'No Bolt call. No EDXEIX call. No AADE call. No database connection. No filesystem writes. No route moves. No route deletion. Read-only log/stat evidence scan only.';

/** @return array<int,string> */
function gov_lpuua_args(array $argv): array
{
    $out = [];
    foreach ($argv as $arg) {
        if (is_string($arg) && str_starts_with($arg, '--')) {
            $out[] = $arg;
        }
    }
    return $out;
}

function gov_lpuua_home_root(): string
{
    return '/home/cabnet';
}

function gov_lpuua_public_root(): string
{
    return gov_lpuua_home_root() . '/public_html/gov.cabnet.app';
}

/** @return array<string,array<string,mixed>> */
function gov_lpuua_targets(): array
{
    $registry = gov_lpuua_home_root() . '/gov.cabnet.app_app/src/Support/LegacyPublicUtilityRegistry.php';
    if (is_file($registry) && is_readable($registry)) {
        require_once $registry;
        if (function_exists('gov_legacy_public_utility_items')) {
            /** @var array<string,array<string,mixed>> $items */
            $items = gov_legacy_public_utility_items();
            return $items;
        }
    }

    return [
        'bolt-api-smoke-test' => ['label' => 'Bolt API Smoke Test', 'legacy_file' => 'bolt-api-smoke-test.php', 'legacy_route' => '/bolt-api-smoke-test.php'],
        'bolt-fleet-orders-watch' => ['label' => 'Bolt Fleet Orders Watch', 'legacy_file' => 'bolt-fleet-orders-watch.php', 'legacy_route' => '/bolt-fleet-orders-watch.php'],
        'bolt-stage-edxeix-jobs' => ['label' => 'Legacy Bolt Stage EDXEIX Jobs', 'legacy_file' => 'bolt_stage_edxeix_jobs.php', 'legacy_route' => '/bolt_stage_edxeix_jobs.php'],
        'bolt-submission-worker' => ['label' => 'Legacy Bolt Submission Worker', 'legacy_file' => 'bolt_submission_worker.php', 'legacy_route' => '/bolt_submission_worker.php'],
        'bolt-sync-orders' => ['label' => 'Bolt Sync Orders', 'legacy_file' => 'bolt_sync_orders.php', 'legacy_route' => '/bolt_sync_orders.php'],
        'bolt-sync-reference' => ['label' => 'Bolt Sync Reference', 'legacy_file' => 'bolt_sync_reference.php', 'legacy_route' => '/bolt_sync_reference.php'],
    ];
}

/** @return array<string,mixed> */
function gov_lpuua_file_meta(string $path): array
{
    return [
        'path' => $path,
        'exists' => is_file($path),
        'readable' => is_readable($path),
        'size' => is_file($path) ? (int)filesize($path) : 0,
        'modified_at' => is_file($path) ? date('Y-m-d H:i:s', (int)filemtime($path)) : null,
    ];
}

/** @return array<int,string> */
function gov_lpuua_candidate_log_paths(): array
{
    $home = gov_lpuua_home_root();
    $paths = [
        $home . '/logs/gov_cabnet_app.php.error.log',
        $home . '/logs/gov.cabnet.app.php.error.log',
        $home . '/logs/gov.cabnet.app',
        $home . '/logs/gov.cabnet.app-ssl_log',
        $home . '/access-logs/gov.cabnet.app',
        $home . '/access-logs/gov.cabnet.app-ssl_log',
        $home . '/tmp/analog/ssl/gov.cabnet.app/cache',
    ];

    $globs = [
        $home . '/tmp/analog/ssl/gov.cabnet.app/*.html',
        $home . '/tmp/awstats/ssl/awstats*.gov.cabnet.app.txt',
        $home . '/tmp/webalizer/ssl/gov.cabnet.app/usage_*.html',
    ];
    foreach ($globs as $pattern) {
        $matches = glob($pattern);
        if (is_array($matches)) {
            foreach ($matches as $match) {
                if (is_string($match)) {
                    $paths[] = $match;
                }
            }
        }
    }

    $paths = array_values(array_unique($paths));
    sort($paths, SORT_STRING);
    return $paths;
}

function gov_lpuua_source_kind(string $path): string
{
    $lower = strtolower(str_replace('\\', '/', $path));
    if (str_contains($lower, '.php.error.log') || str_contains($lower, '/logs/')) {
        return 'php_or_domain_log';
    }
    if (str_contains($lower, '/access-logs/') || str_ends_with($lower, '_log')) {
        return 'access_log';
    }
    if (str_contains($lower, '/tmp/analog/') || str_contains($lower, '/tmp/awstats/') || str_contains($lower, '/tmp/webalizer/')) {
        return 'cpanel_stats_cache';
    }
    return 'other_readable_log';
}

function gov_lpuua_detect_timestamp(string $line): ?string
{
    if (preg_match('/\[([0-9]{1,2}-[A-Z][a-z]{2}-[0-9]{4}\s+[0-9:]{8}\s+[A-Z]{2,5})\]/', $line, $m)) {
        return $m[1];
    }
    if (preg_match('/\[([0-9]{1,2}\/[A-Z][a-z]{2}\/[0-9]{4}:[0-9:]{8}\s+[+\-][0-9]{4})\]/', $line, $m)) {
        return $m[1];
    }
    if (preg_match('/\b([A-Z][a-z]{2}\/[0-9]{2}\/[0-9]{2}\s+[0-9]{1,2}:[0-9]{2}\s+[AP]M)\b/', $line, $m)) {
        return $m[1];
    }
    if (preg_match('/\b([0-9]{4}-[0-9]{2}-[0-9]{2}\s+[0-9:]{8})\b/', $line, $m)) {
        return $m[1];
    }
    return null;
}

/** @return array<string,mixed> */
function gov_lpuua_scan_one_file(string $path, array $targets): array
{
    $meta = gov_lpuua_file_meta($path);
    $result = [
        'path' => $path,
        'kind' => gov_lpuua_source_kind($path),
        'exists' => $meta['exists'],
        'readable' => $meta['readable'],
        'size' => $meta['size'],
        'modified_at' => $meta['modified_at'],
        'scanned' => false,
        'skipped_reason' => '',
        'mentions_by_target' => [],
        'sample_hits' => [],
    ];

    if (empty($meta['exists'])) {
        $result['skipped_reason'] = 'missing';
        return $result;
    }
    if (empty($meta['readable'])) {
        $result['skipped_reason'] = 'not_readable';
        return $result;
    }
    if ((int)$meta['size'] > 15 * 1024 * 1024) {
        $result['skipped_reason'] = 'too_large_for_safe_inline_scan';
        return $result;
    }

    $files = [];
    foreach ($targets as $item) {
        $file = (string)($item['legacy_file'] ?? '');
        if ($file !== '') {
            $files[] = $file;
        }
    }
    $pattern = '/(' . implode('|', array_map(static fn(string $s): string => preg_quote($s, '/'), $files)) . ')/';

    $handle = @fopen($path, 'rb');
    if (!is_resource($handle)) {
        $result['skipped_reason'] = 'open_failed';
        return $result;
    }

    $result['scanned'] = true;
    $lineNo = 0;
    $hitCap = 250;
    while (!feof($handle) && $lineNo < 150000) {
        $line = fgets($handle);
        if (!is_string($line)) {
            break;
        }
        $lineNo++;
        if (!preg_match($pattern, $line, $m)) {
            continue;
        }
        $matched = (string)$m[1];
        $result['mentions_by_target'][$matched] = ((int)($result['mentions_by_target'][$matched] ?? 0)) + 1;
        if (count($result['sample_hits']) < $hitCap) {
            $result['sample_hits'][] = [
                'target' => $matched,
                'line' => $lineNo,
                'timestamp_hint' => gov_lpuua_detect_timestamp($line),
            ];
        }
    }
    fclose($handle);

    return $result;
}

/**
 * @param array<int,array<string,mixed>> $scannedSources
 * @return array<string,array<string,mixed>>
 */
function gov_lpuua_evidence_by_file(array $scannedSources, array $targets): array
{
    $out = [];
    foreach ($targets as $item) {
        $file = (string)($item['legacy_file'] ?? '');
        if ($file !== '') {
            $out[$file] = [
                'mention_count' => 0,
                'last_seen' => null,
                'last_seen_source' => '',
                'source_kinds' => [],
                'sample_hits' => [],
            ];
        }
    }

    foreach ($scannedSources as $source) {
        if (!is_array($source)) {
            continue;
        }
        $sourcePath = (string)($source['path'] ?? '');
        $sourceKind = (string)($source['kind'] ?? 'unknown');
        foreach ((array)($source['mentions_by_target'] ?? []) as $targetFile => $count) {
            $targetFile = (string)$targetFile;
            if (!isset($out[$targetFile])) {
                $out[$targetFile] = [
                    'mention_count' => 0,
                    'last_seen' => null,
                    'last_seen_source' => '',
                    'source_kinds' => [],
                    'sample_hits' => [],
                ];
            }
            $out[$targetFile]['mention_count'] = (int)$out[$targetFile]['mention_count'] + (int)$count;
            $out[$targetFile]['source_kinds'][$sourceKind] = ((int)($out[$targetFile]['source_kinds'][$sourceKind] ?? 0)) + (int)$count;
        }

        foreach ((array)($source['sample_hits'] ?? []) as $hit) {
            if (!is_array($hit)) {
                continue;
            }
            $targetFile = (string)($hit['target'] ?? '');
            if ($targetFile === '') {
                continue;
            }
            if (!isset($out[$targetFile])) {
                continue;
            }
            if (count($out[$targetFile]['sample_hits']) < 8) {
                $out[$targetFile]['sample_hits'][] = [
                    'source' => $sourcePath,
                    'source_kind' => $sourceKind,
                    'line' => (int)($hit['line'] ?? 0),
                    'timestamp_hint' => $hit['timestamp_hint'] ?? null,
                ];
            }
            $timestamp = $hit['timestamp_hint'] ?? null;
            if (is_string($timestamp) && $timestamp !== '') {
                // Best-effort string comparison only. Formats vary between PHP logs, Apache logs, and cPanel stats caches.
                $current = $out[$targetFile]['last_seen'];
                if (!is_string($current) || $timestamp > $current) {
                    $out[$targetFile]['last_seen'] = $timestamp;
                    $out[$targetFile]['last_seen_source'] = $sourcePath;
                }
            }
        }
    }

    foreach ($out as $file => $row) {
        if (is_array($row['source_kinds'] ?? null)) {
            ksort($out[$file]['source_kinds']);
        }
    }

    return $out;
}

/** @return array<string,mixed> */
function gov_legacy_public_utility_usage_audit_run(): array
{
    $targets = gov_lpuua_targets();
    $paths = gov_lpuua_candidate_log_paths();
    $scanned = [];
    $totalsByTarget = [];
    $totalsByKind = [];
    $filesScanned = 0;
    $skipped = 0;

    foreach ($paths as $path) {
        $row = gov_lpuua_scan_one_file($path, $targets);
        $scanned[] = $row;
        if (!empty($row['scanned'])) {
            $filesScanned++;
        } elseif (($row['skipped_reason'] ?? '') !== 'missing') {
            $skipped++;
        }
        $kind = (string)($row['kind'] ?? 'unknown');
        foreach ((array)($row['mentions_by_target'] ?? []) as $target => $count) {
            $totalsByTarget[(string)$target] = ((int)($totalsByTarget[(string)$target] ?? 0)) + (int)$count;
            $totalsByKind[$kind] = ((int)($totalsByKind[$kind] ?? 0)) + (int)$count;
        }
    }

    $evidenceByFile = gov_lpuua_evidence_by_file($scanned, $targets);

    $routes = [];
    $routeMentionSummary = [];
    $legacyFilesPresent = 0;
    foreach ($targets as $key => $item) {
        $file = (string)($item['legacy_file'] ?? '');
        $route = (string)($item['legacy_route'] ?? ('/' . $file));
        $meta = gov_lpuua_file_meta(gov_lpuua_public_root() . '/' . ltrim($file, '/'));
        if (!empty($meta['exists'])) {
            $legacyFilesPresent++;
        }
        $evidence = is_array($evidenceByFile[$file] ?? null) ? $evidenceByFile[$file] : [];
        $mentionCount = (int)($totalsByTarget[$file] ?? ($evidence['mention_count'] ?? 0));
        $lastSeen = $evidence['last_seen'] ?? null;
        $sourceKinds = is_array($evidence['source_kinds'] ?? null) ? $evidence['source_kinds'] : [];
        $sampleHits = is_array($evidence['sample_hits'] ?? null) ? $evidence['sample_hits'] : [];

        $summaryRow = [
            'key' => (string)$key,
            'label' => (string)($item['label'] ?? $file),
            'file' => $file,
            'legacy_file' => $file,
            'route' => $route,
            'current_route' => $route,
            'legacy_route' => $route,
            'mentions' => $mentionCount,
            'mention_count' => $mentionCount,
            'usage_mentions' => $mentionCount,
            'usage_mentions_total' => $mentionCount,
            'last_seen' => $lastSeen,
            'latest_seen' => $lastSeen,
            'last_seen_source' => (string)($evidence['last_seen_source'] ?? ''),
            'source_kinds' => $sourceKinds,
            'sample_hits' => $sampleHits,
            'move_now' => false,
            'delete_now' => false,
            'recommended_action' => 'Keep compatibility route in place. Review log evidence and quiet-period history before any future wrapper/stub or retirement action.',
        ];
        $routeMentionSummary[] = $summaryRow;

        $routes[] = array_merge($summaryRow, [
            'file_meta' => $meta,
        ]);
    }

    ksort($totalsByTarget);
    ksort($totalsByKind);

    $warnings = [];
    if ($filesScanned === 0) {
        $warnings[] = 'No readable log/stat files were scanned; usage evidence is incomplete.';
    }
    if (empty($totalsByTarget)) {
        $warnings[] = 'No legacy utility mentions found in readable logs/stat caches. This is not removal approval; keep compatibility routes until a quiet-period policy is approved.';
    }

    $totalMentions = array_sum(array_map('intval', $totalsByTarget));

    return [
        'ok' => true,
        'version' => GOV_LEGACY_PUBLIC_UTILITY_USAGE_AUDIT_VERSION,
        'mode' => 'read_only_legacy_public_utility_usage_audit',
        'started_at' => gmdate('c'),
        'safety' => GOV_LEGACY_PUBLIC_UTILITY_USAGE_AUDIT_SAFETY,
        'public_root' => gov_lpuua_public_root(),
        'summary' => [
            'targets' => count($targets),
            'legacy_files_present' => $legacyFilesPresent,
            'candidate_log_files' => count($paths),
            'files_scanned' => $filesScanned,
            'files_skipped_non_missing' => $skipped,
            'usage_mentions_total' => (int)$totalMentions,
            'move_recommended_now' => 0,
            'delete_recommended_now' => 0,
        ],
        'usage_by_target' => $totalsByTarget,
        'usage_by_source_kind' => $totalsByKind,
        'route_mention_summary' => $routeMentionSummary,
        'utilities' => $routeMentionSummary,
        'routes' => $routes,
        'scanned_sources' => $scanned,
        'warnings' => $warnings,
        'recommended_next_step' => 'Use this audit to decide whether compatibility wrappers/stubs need log quiet-period tracking. Do not move or delete routes from this evidence alone.',
        'final_blocks' => [],
        'finished_at' => gmdate('c'),
    ];
}

function gov_lpuua_print_text(array $report): void
{
    echo 'Legacy Public Utility Usage Audit ' . GOV_LEGACY_PUBLIC_UTILITY_USAGE_AUDIT_VERSION . PHP_EOL;
    echo 'Safety: ' . GOV_LEGACY_PUBLIC_UTILITY_USAGE_AUDIT_SAFETY . PHP_EOL;
    echo 'OK: ' . (!empty($report['ok']) ? 'yes' : 'no') . PHP_EOL;
    $summary = is_array($report['summary'] ?? null) ? $report['summary'] : [];
    echo 'Targets: ' . (string)($summary['targets'] ?? 0) . PHP_EOL;
    echo 'Files scanned: ' . (string)($summary['files_scanned'] ?? 0) . PHP_EOL;
    echo 'Usage mentions total: ' . (string)($summary['usage_mentions_total'] ?? 0) . PHP_EOL;
    echo 'Move recommended now: 0' . PHP_EOL;
    echo 'Delete recommended now: 0' . PHP_EOL;
}

function gov_lpuua_main(array $argv): int
{
    $args = gov_lpuua_args($argv);
    $report = gov_legacy_public_utility_usage_audit_run();
    if (in_array('--json', $args, true)) {
        echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    } else {
        gov_lpuua_print_text($report);
    }
    return !empty($report['ok']) ? 0 : 1;
}

if (PHP_SAPI === 'cli' && realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === realpath(__FILE__)) {
    exit(gov_lpuua_main($argv));
}
