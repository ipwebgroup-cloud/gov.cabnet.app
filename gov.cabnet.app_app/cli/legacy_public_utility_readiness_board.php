<?php
/**
 * gov.cabnet.app — Legacy Public Utility Readiness Board CLI.
 *
 * v3.0.98:
 * - Read-only aggregate board for legacy guarded public-root utility audits.
 * - Consumes existing read-only usage, quiet-period, stats-source, and reference-preview audits.
 * - Does not execute legacy utilities or perform external/API/DB/write actions.
 */

declare(strict_types=1);

const GOV_LPUB_VERSION = 'v3.0.98-legacy-public-utility-readiness-board';
const GOV_LPUB_SAFETY = 'No Bolt call. No EDXEIX call. No AADE call. No database connection. No filesystem writes. No route moves. No route deletion. No redirects. No legacy utility execution. Read-only aggregate audit only.';

/** @return array<int,string> */
function gov_lpub_args(array $argv): array
{
    $out = [];
    foreach ($argv as $arg) {
        if (is_string($arg) && str_starts_with($arg, '--')) {
            $out[] = $arg;
        }
    }
    return $out;
}

function gov_lpub_app_root(): string
{
    return dirname(__DIR__);
}

/** @return array<string,string> */
function gov_lpub_dependency_paths(): array
{
    $cliRoot = __DIR__;
    return [
        'usage_audit' => $cliRoot . '/legacy_public_utility_usage_audit.php',
        'quiet_period_audit' => $cliRoot . '/legacy_public_utility_quiet_period_audit.php',
        'stats_source_audit' => $cliRoot . '/legacy_public_utility_stats_source_audit.php',
        'phase2_preview' => $cliRoot . '/public_utility_reference_cleanup_phase2_preview.php',
    ];
}

/** @return array<string,mixed> */
function gov_lpub_load_dependencies(): array
{
    $loaded = [];
    foreach (gov_lpub_dependency_paths() as $key => $path) {
        $row = [
            'key' => $key,
            'path' => $path,
            'exists' => is_file($path),
            'readable' => is_readable($path),
            'loaded' => false,
            'error' => '',
        ];
        if ($row['readable']) {
            try {
                require_once $path;
                $row['loaded'] = true;
            } catch (Throwable $e) {
                $row['error'] = $e->getMessage();
            }
        }
        $loaded[$key] = $row;
    }
    return $loaded;
}

/** @return array<string,mixed> */
function gov_lpub_call_report(string $label, string $functionName): array
{
    if (!function_exists($functionName)) {
        return [
            'ok' => false,
            'label' => $label,
            'error' => 'missing_function:' . $functionName,
            'report' => [],
        ];
    }

    try {
        $report = $functionName();
        if (!is_array($report)) {
            return [
                'ok' => false,
                'label' => $label,
                'error' => 'function_returned_non_array:' . $functionName,
                'report' => [],
            ];
        }
        return [
            'ok' => !empty($report['ok']),
            'label' => $label,
            'error' => '',
            'report' => $report,
        ];
    } catch (Throwable $e) {
        return [
            'ok' => false,
            'label' => $label,
            'error' => $e->getMessage(),
            'report' => [],
        ];
    }
}

/** @param array<string,mixed> $array */
function gov_lpub_int(array $array, string $key, int $default = 0): int
{
    $value = $array[$key] ?? $default;
    if (is_numeric($value)) {
        return (int)$value;
    }
    return $default;
}

/** @param array<string,mixed> $report */
function gov_lpub_summary(array $report): array
{
    $summary = $report['summary'] ?? [];
    return is_array($summary) ? $summary : [];
}

/** @param array<string,mixed> $report @return array<int,array<string,mixed>> */
function gov_lpub_routes(array $report): array
{
    foreach (['routes', 'utilities', 'route_classifications', 'quiet_period_routes', 'route_mention_summary'] as $key) {
        if (isset($report[$key]) && is_array($report[$key])) {
            return array_values(array_filter($report[$key], 'is_array'));
        }
    }
    return [];
}

/** @param array<int,array<string,mixed>> $rows @return array<string,array<string,mixed>> */
function gov_lpub_index_by_route(array $rows): array
{
    $out = [];
    foreach ($rows as $row) {
        $route = (string)($row['route'] ?? $row['current_route'] ?? $row['legacy_route'] ?? '');
        if ($route === '' && !empty($row['file'])) {
            $file = (string)$row['file'];
            $route = str_starts_with($file, '/') ? $file : '/' . $file;
        }
        if ($route !== '') {
            $out[$route] = $row;
        }
    }
    return $out;
}

/** @return array<int,string> */
function gov_lpub_target_routes(): array
{
    return [
        '/bolt-api-smoke-test.php',
        '/bolt-fleet-orders-watch.php',
        '/bolt_stage_edxeix_jobs.php',
        '/bolt_submission_worker.php',
        '/bolt_sync_orders.php',
        '/bolt_sync_reference.php',
    ];
}

/** @param array<string,mixed> $statsRow */
function gov_lpub_stats_classification(array $statsRow): string
{
    foreach (['source_classification', 'stats_source_classification', 'classification', 'status'] as $key) {
        $value = trim((string)($statsRow[$key] ?? ''));
        if ($value !== '') {
            return $value;
        }
    }

    $sourceKinds = $statsRow['source_kinds'] ?? $statsRow['usage_source_kinds'] ?? [];
    if (is_array($sourceKinds)) {
        $keys = array_keys($sourceKinds);
        if (count($keys) === 1 && $keys[0] === 'cpanel_stats_cache') {
            return 'cpanel_stats_cache_only';
        }
        if (in_array('raw_access_log', $keys, true) || in_array('live_access_log', $keys, true)) {
            return 'raw_or_live_access_log_evidence';
        }
    }

    return 'not_reported';
}

/** @return array<string,mixed> */
function gov_legacy_public_utility_readiness_board_run(): array
{
    $dependencies = gov_lpub_load_dependencies();

    $usage = gov_lpub_call_report('Legacy Utility Usage Audit', 'gov_legacy_public_utility_usage_audit_run');
    $quiet = gov_lpub_call_report('Legacy Utility Quiet-Period Audit', 'gov_legacy_public_utility_quiet_period_audit_run');
    $stats = gov_lpub_call_report('Legacy Utility Stats Source Audit', 'gov_legacy_public_utility_stats_source_audit_run');
    $phase2 = gov_lpub_call_report('Public Utility Phase 2 Reference Preview', 'gov_public_utility_reference_cleanup_phase2_preview_run');

    $usageReport = is_array($usage['report'] ?? null) ? $usage['report'] : [];
    $quietReport = is_array($quiet['report'] ?? null) ? $quiet['report'] : [];
    $statsReport = is_array($stats['report'] ?? null) ? $stats['report'] : [];
    $phase2Report = is_array($phase2['report'] ?? null) ? $phase2['report'] : [];

    $usageSummary = gov_lpub_summary($usageReport);
    $quietSummary = gov_lpub_summary($quietReport);
    $statsSummary = gov_lpub_summary($statsReport);
    $phase2Summary = gov_lpub_summary($phase2Report);

    $usageByRoute = gov_lpub_index_by_route(gov_lpub_routes($usageReport));
    $quietByRoute = gov_lpub_index_by_route(gov_lpub_routes($quietReport));
    $statsByRoute = gov_lpub_index_by_route(gov_lpub_routes($statsReport));

    $routes = [];
    foreach (gov_lpub_target_routes() as $route) {
        $u = $usageByRoute[$route] ?? [];
        $q = $quietByRoute[$route] ?? [];
        $s = $statsByRoute[$route] ?? [];

        $mentions = gov_lpub_int($u, 'mentions', gov_lpub_int($u, 'mention_count', gov_lpub_int($u, 'usage_mentions_total', 0)));
        $quietClass = (string)($q['quiet_period_classification'] ?? $q['classification'] ?? 'not_reported');
        $statsClass = gov_lpub_stats_classification($s ?: $u);
        $stubCandidate = !empty($q['stub_review_candidate']) || !empty($q['compatibility_stub_review_candidate']);
        $unknownDate = !empty($q['usage_evidence_unknown_date']) || !empty($q['unknown_date']);
        $recentUsage = !empty($q['recent_usage_inside_quiet_window']);
        $rawLiveEvidence = str_contains($statsClass, 'raw') || str_contains($statsClass, 'live_access_log') || !empty($s['live_access_log_evidence']);

        $readiness = 'keep_unchanged';
        if ($rawLiveEvidence || $recentUsage) {
            $readiness = 'blocked_by_recent_or_live_evidence';
        } elseif ($unknownDate) {
            $readiness = 'manual_review_required_before_stub';
        } elseif ($stubCandidate) {
            $readiness = 'candidate_for_future_compatibility_stub_review_only';
        }

        $routes[] = [
            'route' => $route,
            'mentions' => $mentions,
            'last_seen' => (string)($q['last_seen_normalized'] ?? $q['last_seen'] ?? $u['last_seen'] ?? ''),
            'quiet_period_classification' => $quietClass,
            'stats_source_classification' => $statsClass,
            'source_kinds' => $u['source_kinds'] ?? $s['source_kinds'] ?? [],
            'stub_review_candidate' => $stubCandidate,
            'usage_evidence_unknown_date' => $unknownDate,
            'recent_usage_inside_quiet_window' => $recentUsage,
            'raw_or_live_access_log_evidence' => $rawLiveEvidence,
            'readiness' => $readiness,
            'recommended_action' => $rawLiveEvidence || $recentUsage
                ? 'Keep unchanged. Recent/live evidence blocks any stub review.'
                : ($unknownDate
                    ? 'Keep unchanged. Manual evidence review is required before any compatibility-stub discussion.'
                    : ($stubCandidate
                        ? 'Future compatibility-stub review candidate only. Do not move/delete/redirect without explicit approval.'
                        : 'Keep unchanged; no action recommended now.')),
        ];
    }

    $reports = [
        'usage_audit' => $usage,
        'quiet_period_audit' => $quiet,
        'stats_source_audit' => $stats,
        'phase2_preview' => $phase2,
    ];

    $finalBlocks = [];
    foreach ($reports as $key => $report) {
        if (empty($report['ok'])) {
            $finalBlocks[] = $key . ': ' . (string)($report['error'] ?? 'not ok');
        }
    }

    $routeCounts = [
        'future_stub_review_candidates_only' => 0,
        'manual_review_required_before_stub' => 0,
        'blocked_by_recent_or_live_evidence' => 0,
        'keep_unchanged' => 0,
    ];
    foreach ($routes as $row) {
        $key = (string)$row['readiness'];
        if (!isset($routeCounts[$key])) {
            $routeCounts[$key] = 0;
        }
        $routeCounts[$key]++;
    }

    return [
        'ok' => count($finalBlocks) === 0,
        'version' => GOV_LPUB_VERSION,
        'mode' => 'read_only_legacy_public_utility_readiness_board',
        'started_at' => gmdate('c'),
        'safety' => GOV_LPUB_SAFETY,
        'app_root' => gov_lpub_app_root(),
        'dependencies' => $dependencies,
        'report_status' => array_map(static function (array $r): array {
            return [
                'ok' => !empty($r['ok']),
                'label' => (string)($r['label'] ?? ''),
                'error' => (string)($r['error'] ?? ''),
            ];
        }, $reports),
        'summary' => [
            'routes_reviewed' => count($routes),
            'usage_mentions_total' => gov_lpub_int($usageSummary, 'usage_mentions_total'),
            'files_scanned' => gov_lpub_int($usageSummary, 'files_scanned'),
            'cpanel_stats_cache_only_routes' => gov_lpub_int($statsSummary, 'cpanel_stats_cache_only_routes'),
            'live_access_log_evidence_routes' => gov_lpub_int($statsSummary, 'live_access_log_evidence_routes'),
            'quiet_period_stub_review_candidates' => gov_lpub_int($quietSummary, 'quiet_period_stub_review_candidates'),
            'usage_evidence_with_unknown_date' => gov_lpub_int($quietSummary, 'usage_evidence_with_unknown_date'),
            'phase2_actionable_references' => gov_lpub_int($phase2Summary, 'actionable_references'),
            'phase2_safe_candidates' => gov_lpub_int($phase2Summary, 'safe_phase2_candidates'),
            'move_recommended_now' => 0,
            'delete_recommended_now' => 0,
            'redirect_recommended_now' => 0,
            'route_readiness_counts' => $routeCounts,
        ],
        'routes' => $routes,
        'recommended_next_step' => 'Stop cleanup at read-only audit posture. Do not move, delete, redirect, or stub legacy public-root utilities without explicit approval and one final dependency scan.',
        'final_blocks' => $finalBlocks,
        'finished_at' => gmdate('c'),
    ];
}

function gov_lpub_print_text(array $report): void
{
    echo 'Legacy Public Utility Readiness Board ' . GOV_LPUB_VERSION . PHP_EOL;
    echo 'Safety: ' . GOV_LPUB_SAFETY . PHP_EOL;
    echo 'OK: ' . (!empty($report['ok']) ? 'yes' : 'no') . PHP_EOL;
    $summary = is_array($report['summary'] ?? null) ? $report['summary'] : [];
    echo 'Routes reviewed: ' . (string)($summary['routes_reviewed'] ?? 0) . PHP_EOL;
    echo 'Move recommended now: ' . (string)($summary['move_recommended_now'] ?? 0) . PHP_EOL;
    echo 'Delete recommended now: ' . (string)($summary['delete_recommended_now'] ?? 0) . PHP_EOL;
    echo 'Redirect recommended now: ' . (string)($summary['redirect_recommended_now'] ?? 0) . PHP_EOL;
    foreach ((array)($report['routes'] ?? []) as $route) {
        if (!is_array($route)) { continue; }
        echo '- ' . (string)($route['route'] ?? '') . ' | ' . (string)($route['readiness'] ?? '') . PHP_EOL;
    }
}

function gov_lpub_main(array $argv): int
{
    $args = gov_lpub_args($argv);
    $report = gov_legacy_public_utility_readiness_board_run();
    if (in_array('--json', $args, true)) {
        echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    } else {
        gov_lpub_print_text($report);
    }
    return !empty($report['ok']) ? 0 : 1;
}

if (PHP_SAPI === 'cli' && realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === realpath(__FILE__)) {
    exit(gov_lpub_main($argv));
}
