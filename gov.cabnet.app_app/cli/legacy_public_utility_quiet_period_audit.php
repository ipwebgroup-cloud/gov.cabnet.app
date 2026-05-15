<?php
/**
 * gov.cabnet.app — Legacy Public Utility Quiet-Period Audit CLI.
 *
 * v3.0.95:
 * - Adds stable route-level JSON fields for CLI/operator summaries:
 *   quiet_period_classification, stub_review_candidate,
 *   usage_evidence_unknown_date, compatibility_stub_review_candidate.
 * - Remains read-only and non-mutating.
 *
 * SAFETY:
 * - No Bolt call.
 * - No EDXEIX call.
 * - No AADE call.
 * - No database connection.
 * - No filesystem writes.
 * - No route moves, deletions, redirects, or legacy utility execution.
 */

declare(strict_types=1);

const GOV_LEGACY_PUBLIC_UTILITY_QUIET_PERIOD_AUDIT_VERSION = 'v3.0.95-legacy-public-utility-quiet-period-stable-fields';
const GOV_LEGACY_PUBLIC_UTILITY_QUIET_PERIOD_AUDIT_SAFETY = 'No Bolt call. No EDXEIX call. No AADE call. No database connection. No filesystem writes. No route moves. No route deletion. No redirects. Read-only quiet-period classification only.';
const GOV_LEGACY_PUBLIC_UTILITY_QUIET_PERIOD_DAYS = 14;

function gov_lpuq_args(array $argv): array
{
    $out = [];
    foreach ($argv as $arg) {
        if (is_string($arg) && str_starts_with($arg, '--')) {
            $out[] = $arg;
        }
    }
    return $out;
}

function gov_lpuq_app_root(): string
{
    return dirname(__DIR__);
}

function gov_lpuq_usage_audit_path(): string
{
    return gov_lpuq_app_root() . '/cli/legacy_public_utility_usage_audit.php';
}

function gov_lpuq_parse_last_seen(mixed $value): ?int
{
    $raw = trim((string)$value);
    if ($raw === '' || strtolower($raw) === 'none' || $raw === '?') {
        return null;
    }

    $ts = strtotime($raw);
    return $ts === false ? null : $ts;
}

function gov_lpuq_normalized_last_seen(mixed $value): ?string
{
    $ts = gov_lpuq_parse_last_seen($value);
    return $ts === null ? null : gmdate('c', $ts);
}

function gov_lpuq_classify_route(int $mentions, mixed $lastSeen, int $quietDays): array
{
    $lastSeenTs = gov_lpuq_parse_last_seen($lastSeen);
    $lastSeenIso = $lastSeenTs === null ? null : gmdate('c', $lastSeenTs);
    $cutoffTs = time() - ($quietDays * 86400);

    if ($mentions <= 0) {
        return [
            'quiet_period_classification' => 'no_usage_seen_in_scanned_sources',
            'classification' => 'no_usage_seen_in_scanned_sources',
            'status' => 'quiet_period_candidate_review_only',
            'stub_review_candidate' => true,
            'compatibility_stub_review_candidate' => true,
            'usage_evidence_unknown_date' => false,
            'unknown_date' => false,
            'recent_usage_inside_quiet_window' => false,
            'historical_usage_outside_quiet_window' => false,
            'last_seen_normalized' => null,
            'recommended_action' => 'Candidate for compatibility-stub review only, after explicit approval and one more dependency scan. Do not delete now.',
            'safe_next_action' => 'Review as a compatibility-stub candidate only; do not move/delete without explicit approval.',
        ];
    }

    if ($lastSeenTs === null) {
        return [
            'quiet_period_classification' => 'usage_evidence_with_unknown_date',
            'classification' => 'usage_evidence_with_unknown_date',
            'status' => 'manual_usage_source_review_required',
            'stub_review_candidate' => false,
            'compatibility_stub_review_candidate' => false,
            'usage_evidence_unknown_date' => true,
            'unknown_date' => true,
            'recent_usage_inside_quiet_window' => false,
            'historical_usage_outside_quiet_window' => false,
            'last_seen_normalized' => null,
            'recommended_action' => 'Keep unchanged until the usage source is manually reviewed because mention dates could not be normalized.',
            'safe_next_action' => 'Manual source review required; do not move, redirect, stub, or delete.',
        ];
    }

    if ($lastSeenTs >= $cutoffTs) {
        return [
            'quiet_period_classification' => 'recent_usage_inside_quiet_window',
            'classification' => 'recent_usage_inside_quiet_window',
            'status' => 'keep_unchanged_recent_usage_seen',
            'stub_review_candidate' => false,
            'compatibility_stub_review_candidate' => false,
            'usage_evidence_unknown_date' => false,
            'unknown_date' => false,
            'recent_usage_inside_quiet_window' => true,
            'historical_usage_outside_quiet_window' => false,
            'last_seen_normalized' => $lastSeenIso,
            'recommended_action' => 'Keep unchanged. Recent usage appears inside the quiet window.',
            'safe_next_action' => 'Keep route unchanged and continue monitoring.',
        ];
    }

    return [
        'quiet_period_classification' => 'historical_usage_outside_quiet_window',
        'classification' => 'historical_usage_outside_quiet_window',
        'status' => 'quiet_period_candidate_review_only',
        'stub_review_candidate' => true,
        'compatibility_stub_review_candidate' => true,
        'usage_evidence_unknown_date' => false,
        'unknown_date' => false,
        'recent_usage_inside_quiet_window' => false,
        'historical_usage_outside_quiet_window' => true,
        'last_seen_normalized' => $lastSeenIso,
        'recommended_action' => 'Candidate for compatibility-stub review only. Historical usage is outside the quiet window, but do not move/delete without explicit approval.',
        'safe_next_action' => 'Review as a compatibility-stub candidate only; do not move/delete without explicit approval.',
    ];
}

function gov_lpuq_route_value(array $row, array $keys, mixed $default = null): mixed
{
    foreach ($keys as $key) {
        if (array_key_exists($key, $row) && $row[$key] !== null && $row[$key] !== '') {
            return $row[$key];
        }
    }
    return $default;
}

function gov_legacy_public_utility_quiet_period_audit_run(): array
{
    $usagePath = gov_lpuq_usage_audit_path();
    $usageReport = null;
    $finalBlocks = [];

    if (!is_file($usagePath) || !is_readable($usagePath)) {
        $finalBlocks[] = 'usage_audit: legacy_public_utility_usage_audit.php is not readable';
    } else {
        require_once $usagePath;
        if (!function_exists('gov_legacy_public_utility_usage_audit_run')) {
            $finalBlocks[] = 'usage_audit: gov_legacy_public_utility_usage_audit_run() is unavailable';
        } else {
            try {
                $usageReport = gov_legacy_public_utility_usage_audit_run();
            } catch (Throwable $e) {
                $finalBlocks[] = 'usage_audit: failed to run usage audit: ' . $e->getMessage();
            }
        }
    }

    $usageRoutes = [];
    if (is_array($usageReport)) {
        if (isset($usageReport['route_mention_summary']) && is_array($usageReport['route_mention_summary'])) {
            $usageRoutes = $usageReport['route_mention_summary'];
        } elseif (isset($usageReport['utilities']) && is_array($usageReport['utilities'])) {
            $usageRoutes = $usageReport['utilities'];
        } elseif (isset($usageReport['routes']) && is_array($usageReport['routes'])) {
            $usageRoutes = $usageReport['routes'];
        }
    }

    $routes = [];
    $summary = [
        'routes_reviewed' => 0,
        'usage_mentions_total' => 0,
        'quiet_period_stub_review_candidates' => 0,
        'no_usage_seen_candidates' => 0,
        'historical_usage_outside_quiet_window' => 0,
        'recent_usage_inside_quiet_window' => 0,
        'usage_evidence_with_unknown_date' => 0,
        'move_recommended_now' => 0,
        'delete_recommended_now' => 0,
        'redirect_recommended_now' => 0,
    ];

    foreach ($usageRoutes as $row) {
        if (!is_array($row)) {
            continue;
        }

        $route = (string)gov_lpuq_route_value($row, ['route', 'current_route', 'legacy_route'], '');
        $file = (string)gov_lpuq_route_value($row, ['file'], basename($route));
        if ($route === '' && $file !== '') {
            $route = '/' . ltrim($file, '/');
        }

        $mentions = (int)gov_lpuq_route_value($row, ['mentions', 'mention_count', 'usage_mentions', 'usage_mentions_total'], 0);
        $lastSeenRaw = gov_lpuq_route_value($row, ['last_seen', 'latest_seen'], null);
        $classification = gov_lpuq_classify_route($mentions, $lastSeenRaw, GOV_LEGACY_PUBLIC_UTILITY_QUIET_PERIOD_DAYS);

        $entry = array_merge($row, $classification, [
            'route' => $route,
            'current_route' => $route,
            'legacy_route' => $route,
            'file' => $file !== '' ? $file : basename($route),
            'mentions' => $mentions,
            'mention_count' => $mentions,
            'usage_mentions' => $mentions,
            'usage_mentions_total' => $mentions,
            'last_seen' => $classification['last_seen_normalized'] ?? null,
            'latest_seen' => $classification['last_seen_normalized'] ?? null,
            'last_seen_raw' => $lastSeenRaw,
            'quiet_period_days' => GOV_LEGACY_PUBLIC_UTILITY_QUIET_PERIOD_DAYS,
            'move_now' => false,
            'delete_now' => false,
            'redirect_now' => false,
        ]);
        $routes[] = $entry;

        $summary['routes_reviewed']++;
        $summary['usage_mentions_total'] += $mentions;
        if (!empty($entry['stub_review_candidate'])) {
            $summary['quiet_period_stub_review_candidates']++;
        }
        if (($entry['quiet_period_classification'] ?? '') === 'no_usage_seen_in_scanned_sources') {
            $summary['no_usage_seen_candidates']++;
        }
        if (!empty($entry['historical_usage_outside_quiet_window'])) {
            $summary['historical_usage_outside_quiet_window']++;
        }
        if (!empty($entry['recent_usage_inside_quiet_window'])) {
            $summary['recent_usage_inside_quiet_window']++;
        }
        if (!empty($entry['usage_evidence_unknown_date'])) {
            $summary['usage_evidence_with_unknown_date']++;
        }
    }

    return [
        'ok' => count($finalBlocks) === 0,
        'version' => GOV_LEGACY_PUBLIC_UTILITY_QUIET_PERIOD_AUDIT_VERSION,
        'mode' => 'read_only_legacy_public_utility_quiet_period_audit',
        'started_at' => gmdate('c'),
        'safety' => GOV_LEGACY_PUBLIC_UTILITY_QUIET_PERIOD_AUDIT_SAFETY,
        'quiet_period_days' => GOV_LEGACY_PUBLIC_UTILITY_QUIET_PERIOD_DAYS,
        'usage_audit_version' => is_array($usageReport) ? (string)($usageReport['version'] ?? '') : '',
        'summary' => $summary,
        'routes' => $routes,
        'utilities' => $routes,
        'route_classifications' => $routes,
        'quiet_period_routes' => $routes,
        'recommended_next_step' => 'Review candidate and unknown-date groups only. Do not move, delete, redirect, or stub legacy public-root utilities without explicit approval.',
        'final_blocks' => $finalBlocks,
        'finished_at' => gmdate('c'),
    ];
}

function gov_lpuq_print_text(array $report): void
{
    echo 'Legacy Public Utility Quiet-Period Audit ' . GOV_LEGACY_PUBLIC_UTILITY_QUIET_PERIOD_AUDIT_VERSION . PHP_EOL;
    echo 'Safety: ' . GOV_LEGACY_PUBLIC_UTILITY_QUIET_PERIOD_AUDIT_SAFETY . PHP_EOL;
    echo 'OK: ' . (!empty($report['ok']) ? 'yes' : 'no') . PHP_EOL;
    $summary = is_array($report['summary'] ?? null) ? $report['summary'] : [];
    echo 'Routes reviewed: ' . (string)($summary['routes_reviewed'] ?? 0) . PHP_EOL;
    echo 'Stub review candidates: ' . (string)($summary['quiet_period_stub_review_candidates'] ?? 0) . PHP_EOL;
    echo 'Unknown-date evidence: ' . (string)($summary['usage_evidence_with_unknown_date'] ?? 0) . PHP_EOL;
    echo 'Move recommended now: 0' . PHP_EOL;
    echo 'Delete recommended now: 0' . PHP_EOL;
}

function gov_lpuq_main(array $argv): int
{
    $args = gov_lpuq_args($argv);
    $report = gov_legacy_public_utility_quiet_period_audit_run();
    if (in_array('--json', $args, true)) {
        echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    } else {
        gov_lpuq_print_text($report);
    }
    return !empty($report['ok']) ? 0 : 1;
}

if (PHP_SAPI === 'cli' && realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === realpath(__FILE__)) {
    exit(gov_lpuq_main($argv));
}
