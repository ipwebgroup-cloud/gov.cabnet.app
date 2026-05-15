<?php
/**
 * gov.cabnet.app — Legacy Public Utility Stats Source Audit CLI.
 *
 * v3.0.96:
 * - Read-only classifier for usage-audit evidence source kinds.
 * - Focuses on cPanel stats/cache-only evidence versus actionable live usage evidence.
 * - Does not execute legacy utilities, connect to DB, write files, call Bolt, EDXEIX, or AADE.
 */

declare(strict_types=1);

const GOV_LEGACY_PUBLIC_UTILITY_STATS_SOURCE_AUDIT_VERSION = 'v3.0.96-legacy-public-utility-stats-source-audit';
const GOV_LEGACY_PUBLIC_UTILITY_STATS_SOURCE_AUDIT_SAFETY = 'No Bolt call. No EDXEIX call. No AADE call. No database connection. No filesystem writes. No route moves. No route deletion. No redirects. Read-only usage evidence source classification only.';

require_once '/home/cabnet/gov.cabnet.app_app/cli/legacy_public_utility_usage_audit.php';

/** @return array<int,string> */
function gov_lpu_stats_args(array $argv): array
{
    $out = [];
    foreach ($argv as $arg) {
        if (is_string($arg) && str_starts_with($arg, '--')) {
            $out[] = $arg;
        }
    }
    return $out;
}

/** @return array<int,string> */
function gov_lpu_stats_source_kind_names(array $sourceKinds): array
{
    $names = [];
    foreach ($sourceKinds as $kind => $count) {
        if ((int)$count > 0) {
            $names[] = (string)$kind;
        }
    }
    sort($names);
    return $names;
}

function gov_lpu_stats_is_cpanel_stats_cache_only(array $sourceKinds): bool
{
    $names = gov_lpu_stats_source_kind_names($sourceKinds);
    return count($names) === 1 && $names[0] === 'cpanel_stats_cache';
}

function gov_lpu_stats_has_live_log_evidence(array $sourceKinds): bool
{
    $names = gov_lpu_stats_source_kind_names($sourceKinds);
    foreach ($names as $name) {
        if (in_array($name, ['apache_access_log', 'access_log', 'current_access_log', 'live_access_log', 'raw_access_log'], true)) {
            return true;
        }
    }
    return false;
}

/** @return array<int,string> */
function gov_lpu_stats_extract_source_month_hints(array $sampleHits): array
{
    $hints = [];
    foreach ($sampleHits as $hit) {
        if (!is_array($hit)) {
            continue;
        }
        $source = str_replace('\\', '/', (string)($hit['source'] ?? $hit['path'] ?? ''));
        if ($source === '') {
            continue;
        }
        if (preg_match('/awstats(\d{2})(\d{4})/i', $source, $m)) {
            $hints[] = $m[2] . '-' . $m[1];
            continue;
        }
        if (preg_match('/usage_(\d{4})(\d{2})\.html/i', $source, $m)) {
            $hints[] = $m[1] . '-' . $m[2];
            continue;
        }
        if (preg_match('#/tmp/analog/ssl/[^/]+/(\d+)\.html#i', $source, $m)) {
            $month = str_pad((string)((int)$m[1]), 2, '0', STR_PAD_LEFT);
            $hints[] = 'analog-month-' . $month;
            continue;
        }
        if (str_contains($source, '/tmp/analog/') && str_ends_with($source, '/cache')) {
            $hints[] = 'analog-cache-undated';
            continue;
        }
    }
    $hints = array_values(array_unique($hints));
    sort($hints);
    return $hints;
}

function gov_lpu_stats_classify_route(array $route): array
{
    $mentions = (int)($route['mentions'] ?? $route['mention_count'] ?? $route['usage_mentions_total'] ?? $route['usage_mentions'] ?? 0);
    $sourceKinds = is_array($route['source_kinds'] ?? null) ? $route['source_kinds'] : [];
    $sampleHits = is_array($route['sample_hits'] ?? null) ? $route['sample_hits'] : [];
    $lastSeen = trim((string)($route['last_seen'] ?? $route['latest_seen'] ?? ''));

    $cpanelOnly = $mentions > 0 && gov_lpu_stats_is_cpanel_stats_cache_only($sourceKinds);
    $hasLiveLog = gov_lpu_stats_has_live_log_evidence($sourceKinds);
    $monthHints = gov_lpu_stats_extract_source_month_hints($sampleHits);

    if ($mentions <= 0) {
        $classification = 'no_usage_seen_in_scanned_sources';
        $review = 'Potential future compatibility-stub review candidate, but only after one more dependency scan and explicit approval.';
        $unknownDate = false;
    } elseif ($hasLiveLog) {
        $classification = 'live_or_raw_access_log_evidence_present';
        $review = 'Keep unchanged. Raw/live access-log evidence exists and must be reviewed before any stub or relocation discussion.';
        $unknownDate = ($lastSeen === '');
    } elseif ($cpanelOnly && $lastSeen !== '') {
        $classification = 'cpanel_stats_cache_only_with_last_seen_hint';
        $review = 'Stats-cache evidence only. Treat as historical evidence candidate for manual review, not as approval to move/delete.';
        $unknownDate = false;
    } elseif ($cpanelOnly) {
        $classification = 'cpanel_stats_cache_only_with_unknown_date';
        $review = 'Stats-cache evidence only but no normalized last_seen. Keep unchanged until one raw access-log check or quiet-period review confirms no recent use.';
        $unknownDate = true;
    } else {
        $classification = 'mixed_or_unclassified_usage_evidence';
        $review = 'Keep unchanged until mixed evidence is manually reviewed.';
        $unknownDate = ($lastSeen === '');
    }

    return [
        'route' => (string)($route['route'] ?? $route['current_route'] ?? $route['legacy_route'] ?? $route['file'] ?? ''),
        'file' => (string)($route['file'] ?? basename((string)($route['route'] ?? ''))),
        'mentions' => $mentions,
        'last_seen' => $lastSeen !== '' ? $lastSeen : null,
        'source_kinds' => $sourceKinds,
        'source_kind_names' => gov_lpu_stats_source_kind_names($sourceKinds),
        'cpanel_stats_cache_only' => $cpanelOnly,
        'live_access_log_evidence_present' => $hasLiveLog,
        'source_month_hints' => $monthHints,
        'classification' => $classification,
        'usage_evidence_unknown_date' => $unknownDate,
        'manual_review_required_before_stub' => $mentions > 0,
        'move_recommended_now' => false,
        'delete_recommended_now' => false,
        'redirect_recommended_now' => false,
        'recommended_action' => $review,
        'sample_hits' => array_slice($sampleHits, 0, 8),
    ];
}

/** @return array<string,mixed> */
function gov_legacy_public_utility_stats_source_audit_run(): array
{
    $usage = gov_legacy_public_utility_usage_audit_run();
    $usageSummary = is_array($usage['summary'] ?? null) ? $usage['summary'] : [];
    $routes = is_array($usage['route_mention_summary'] ?? null)
        ? $usage['route_mention_summary']
        : (is_array($usage['routes'] ?? null) ? $usage['routes'] : []);

    $outRoutes = [];
    $cpanelOnly = 0;
    $unknownDateStatsOnly = 0;
    $liveLogEvidence = 0;
    $noUsage = 0;
    $mixedOrUnclassified = 0;

    foreach ($routes as $route) {
        if (!is_array($route)) {
            continue;
        }
        $classified = gov_lpu_stats_classify_route($route);
        $outRoutes[] = $classified;
        if (!empty($classified['cpanel_stats_cache_only'])) {
            $cpanelOnly++;
        }
        if (($classified['classification'] ?? '') === 'cpanel_stats_cache_only_with_unknown_date') {
            $unknownDateStatsOnly++;
        }
        if (!empty($classified['live_access_log_evidence_present'])) {
            $liveLogEvidence++;
        }
        if (($classified['classification'] ?? '') === 'no_usage_seen_in_scanned_sources') {
            $noUsage++;
        }
        if (($classified['classification'] ?? '') === 'mixed_or_unclassified_usage_evidence') {
            $mixedOrUnclassified++;
        }
    }

    return [
        'ok' => true,
        'version' => GOV_LEGACY_PUBLIC_UTILITY_STATS_SOURCE_AUDIT_VERSION,
        'mode' => 'read_only_legacy_public_utility_stats_source_audit',
        'started_at' => gmdate('c'),
        'safety' => GOV_LEGACY_PUBLIC_UTILITY_STATS_SOURCE_AUDIT_SAFETY,
        'upstream_usage_audit_version' => (string)($usage['version'] ?? ''),
        'summary' => [
            'routes_reviewed' => count($outRoutes),
            'usage_mentions_total' => (int)($usageSummary['usage_mentions_total'] ?? 0),
            'cpanel_stats_cache_only_routes' => $cpanelOnly,
            'cpanel_stats_cache_only_with_unknown_date' => $unknownDateStatsOnly,
            'live_access_log_evidence_routes' => $liveLogEvidence,
            'no_usage_seen_routes' => $noUsage,
            'mixed_or_unclassified_routes' => $mixedOrUnclassified,
            'move_recommended_now' => 0,
            'delete_recommended_now' => 0,
            'redirect_recommended_now' => 0,
        ],
        'routes' => $outRoutes,
        'recommended_next_step' => 'No route moves/deletes. Use this evidence to decide whether a future compatibility-stub review is safe after explicit approval and one final dependency/access-log check.',
        'final_blocks' => [],
        'finished_at' => gmdate('c'),
    ];
}

function gov_lpu_stats_print_text(array $report): void
{
    echo 'Legacy Public Utility Stats Source Audit ' . GOV_LEGACY_PUBLIC_UTILITY_STATS_SOURCE_AUDIT_VERSION . PHP_EOL;
    echo 'Safety: ' . GOV_LEGACY_PUBLIC_UTILITY_STATS_SOURCE_AUDIT_SAFETY . PHP_EOL;
    echo 'OK: ' . (!empty($report['ok']) ? 'yes' : 'no') . PHP_EOL;
    $summary = is_array($report['summary'] ?? null) ? $report['summary'] : [];
    foreach ($summary as $key => $value) {
        echo $key . ': ' . (string)$value . PHP_EOL;
    }
    foreach ((array)($report['routes'] ?? []) as $route) {
        if (!is_array($route)) {
            continue;
        }
        echo '- ' . (string)($route['route'] ?? '') . ' | ' . (string)($route['classification'] ?? '') . ' | move_now=no delete_now=no' . PHP_EOL;
    }
}

function gov_lpu_stats_main(array $argv): int
{
    $args = gov_lpu_stats_args($argv);
    $report = gov_legacy_public_utility_stats_source_audit_run();
    if (in_array('--json', $args, true)) {
        echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    } else {
        gov_lpu_stats_print_text($report);
    }
    return !empty($report['ok']) ? 0 : 1;
}

if (PHP_SAPI === 'cli' && realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === realpath(__FILE__)) {
    exit(gov_lpu_stats_main($argv));
}
