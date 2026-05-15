<?php
/**
 * gov.cabnet.app — Legacy public utility quiet-period audit CLI.
 *
 * Read-only classifier layered on top of the legacy public utility usage audit.
 * It does not execute legacy utilities, move routes, delete routes, redirect routes,
 * connect to DB, call Bolt, call EDXEIX, call AADE, or write files.
 */

declare(strict_types=1);

const GOV_LEGACY_PUBLIC_UTILITY_QUIET_AUDIT_VERSION = 'v3.0.94-legacy-public-utility-quiet-period-audit';
const GOV_LEGACY_PUBLIC_UTILITY_QUIET_AUDIT_SAFETY = 'No Bolt call. No EDXEIX call. No AADE call. No database connection. No filesystem writes. No route moves. No route deletion. No redirects. Read-only quiet-period classification only.';
const GOV_LEGACY_PUBLIC_UTILITY_QUIET_DAYS_DEFAULT = 14;

$govQuietUsageAuditPath = __DIR__ . '/legacy_public_utility_usage_audit.php';
if (is_file($govQuietUsageAuditPath)) {
    require_once $govQuietUsageAuditPath;
}

/** @return array<int,string> */
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

function gov_lpuq_arg_value(array $argv, string $name, ?string $default = null): ?string
{
    $prefix = '--' . $name . '=';
    foreach ($argv as $arg) {
        if (is_string($arg) && str_starts_with($arg, $prefix)) {
            return substr($arg, strlen($prefix));
        }
    }
    return $default;
}

function gov_lpuq_parse_quiet_days(array $argv): int
{
    $raw = gov_lpuq_arg_value($argv, 'quiet-days', (string)GOV_LEGACY_PUBLIC_UTILITY_QUIET_DAYS_DEFAULT);
    $days = filter_var($raw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 3650]]);
    return is_int($days) ? $days : GOV_LEGACY_PUBLIC_UTILITY_QUIET_DAYS_DEFAULT;
}

function gov_lpuq_normalize_last_seen(?string $raw): array
{
    $raw = trim((string)$raw);
    if ($raw === '' || strtolower($raw) === 'none' || $raw === '?') {
        return [
            'raw' => $raw,
            'parsed' => false,
            'iso' => null,
            'timestamp' => null,
            'reason' => 'no_last_seen_value',
        ];
    }

    $formats = [
        'd-M-Y H:i:s T',       // 24-Apr-2026 12:39:01 UTC
        'M/d/y g:i A',         // Apr/25/26  7:40 PM
        'M/d/y  g:i A',        // Apr/25/26  7:40 PM with double space
        'Y-m-d H:i:s',
        'Y-m-d H:i:s T',
        DateTimeInterface::ATOM,
    ];

    foreach ($formats as $format) {
        $dt = DateTimeImmutable::createFromFormat($format, $raw, new DateTimeZone('UTC'));
        if ($dt instanceof DateTimeImmutable) {
            $errors = DateTimeImmutable::getLastErrors();
            if ($errors === false || (($errors['warning_count'] ?? 0) === 0 && ($errors['error_count'] ?? 0) === 0)) {
                return [
                    'raw' => $raw,
                    'parsed' => true,
                    'iso' => $dt->setTimezone(new DateTimeZone('UTC'))->format(DateTimeInterface::ATOM),
                    'timestamp' => $dt->getTimestamp(),
                    'reason' => 'parsed_with_format_' . $format,
                ];
            }
        }
    }

    try {
        $dt = new DateTimeImmutable($raw, new DateTimeZone('UTC'));
        return [
            'raw' => $raw,
            'parsed' => true,
            'iso' => $dt->setTimezone(new DateTimeZone('UTC'))->format(DateTimeInterface::ATOM),
            'timestamp' => $dt->getTimestamp(),
            'reason' => 'parsed_by_datetime',
        ];
    } catch (Throwable) {
        return [
            'raw' => $raw,
            'parsed' => false,
            'iso' => null,
            'timestamp' => null,
            'reason' => 'unparseable_last_seen',
        ];
    }
}

/** @return array<int,array<string,mixed>> */
function gov_lpuq_usage_routes(array $usageReport): array
{
    $routes = $usageReport['route_mention_summary'] ?? $usageReport['utilities'] ?? $usageReport['routes'] ?? [];
    return is_array($routes) ? array_values($routes) : [];
}

/** @return array<string,mixed> */
function gov_legacy_public_utility_quiet_period_audit_run(int $quietDays = GOV_LEGACY_PUBLIC_UTILITY_QUIET_DAYS_DEFAULT): array
{
    $finalBlocks = [];
    $usageReport = [];

    if (!function_exists('gov_legacy_public_utility_usage_audit_run')) {
        $finalBlocks[] = 'usage_audit_function_missing';
    } else {
        try {
            $usageReport = gov_legacy_public_utility_usage_audit_run();
        } catch (Throwable $e) {
            $finalBlocks[] = 'usage_audit_failed: ' . $e->getMessage();
        }
    }

    $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    $cutoff = $now->modify('-' . $quietDays . ' days');
    $routes = [];
    $candidateCount = 0;
    $unknownDateCount = 0;
    $recentUsageCount = 0;
    $noUsageCount = 0;
    $historicalQuietCount = 0;

    foreach (gov_lpuq_usage_routes($usageReport) as $routeRow) {
        if (!is_array($routeRow)) {
            continue;
        }
        $route = (string)($routeRow['route'] ?? $routeRow['current_route'] ?? $routeRow['legacy_route'] ?? $routeRow['file'] ?? '');
        $mentionsRaw = $routeRow['mentions'] ?? $routeRow['mention_count'] ?? $routeRow['usage_mentions'] ?? $routeRow['usage_mentions_total'] ?? 0;
        $mentions = is_numeric($mentionsRaw) ? (int)$mentionsRaw : 0;
        $lastSeenRaw = (string)($routeRow['last_seen'] ?? $routeRow['latest_seen'] ?? '');
        $lastSeen = gov_lpuq_normalize_last_seen($lastSeenRaw);

        $daysSinceLastSeen = null;
        $quietPeriodOk = false;
        $reviewClass = 'keep_compatibility_route';
        $recommendedAction = 'Keep compatibility route in place.';

        if ($mentions <= 0) {
            $noUsageCount++;
            $quietPeriodOk = true;
            $reviewClass = 'no_usage_seen_in_scanned_sources';
            $recommendedAction = 'Candidate for compatibility-stub review only, after explicit approval and one more dependency scan. Do not delete now.';
        } elseif (!empty($lastSeen['parsed']) && is_int($lastSeen['timestamp'] ?? null)) {
            $seen = (new DateTimeImmutable('@' . (string)$lastSeen['timestamp']))->setTimezone(new DateTimeZone('UTC'));
            $daysSinceLastSeen = max(0, (int)$seen->diff($now)->format('%a'));
            if ($seen <= $cutoff) {
                $historicalQuietCount++;
                $quietPeriodOk = true;
                $reviewClass = 'historical_usage_outside_quiet_window';
                $recommendedAction = 'Candidate for compatibility-stub review only. Historical usage is outside the quiet window, but do not move/delete without explicit approval.';
            } else {
                $recentUsageCount++;
                $reviewClass = 'recent_usage_inside_quiet_window';
                $recommendedAction = 'Keep unchanged. Recent usage evidence is inside the quiet window.';
            }
        } else {
            $unknownDateCount++;
            $reviewClass = 'usage_evidence_with_unknown_date';
            $recommendedAction = 'Keep unchanged until the usage source is manually reviewed because mention dates could not be normalized.';
        }

        if ($quietPeriodOk) {
            $candidateCount++;
        }

        $routes[] = [
            'route' => $route,
            'current_route' => (string)($routeRow['current_route'] ?? $route),
            'file' => (string)($routeRow['file'] ?? basename($route)),
            'mentions' => $mentions,
            'mention_count' => $mentions,
            'last_seen_raw' => $lastSeenRaw !== '' ? $lastSeenRaw : null,
            'last_seen' => $lastSeen['iso'],
            'last_seen_parse' => $lastSeen,
            'days_since_last_seen' => $daysSinceLastSeen,
            'quiet_days_required' => $quietDays,
            'quiet_period_ok_for_stub_review' => $quietPeriodOk,
            'review_class' => $reviewClass,
            'source_kinds' => $routeRow['source_kinds'] ?? [],
            'sample_hits' => $routeRow['sample_hits'] ?? [],
            'recommended_action' => $recommendedAction,
            'delete_now' => false,
            'move_now' => false,
            'redirect_now' => false,
        ];
    }

    return [
        'ok' => empty($finalBlocks),
        'version' => GOV_LEGACY_PUBLIC_UTILITY_QUIET_AUDIT_VERSION,
        'mode' => 'read_only_legacy_public_utility_quiet_period_audit',
        'started_at' => gmdate('c'),
        'safety' => GOV_LEGACY_PUBLIC_UTILITY_QUIET_AUDIT_SAFETY,
        'quiet_days_required' => $quietDays,
        'now_utc' => $now->format(DateTimeInterface::ATOM),
        'quiet_cutoff_utc' => $cutoff->format(DateTimeInterface::ATOM),
        'usage_audit_version' => (string)($usageReport['version'] ?? ''),
        'summary' => [
            'routes_reviewed' => count($routes),
            'usage_mentions_total' => (int)($usageReport['summary']['usage_mentions_total'] ?? 0),
            'quiet_period_stub_review_candidates' => $candidateCount,
            'no_usage_seen_candidates' => $noUsageCount,
            'historical_usage_outside_quiet_window' => $historicalQuietCount,
            'recent_usage_inside_quiet_window' => $recentUsageCount,
            'usage_evidence_with_unknown_date' => $unknownDateCount,
            'move_recommended_now' => 0,
            'delete_recommended_now' => 0,
            'redirect_recommended_now' => 0,
        ],
        'routes' => $routes,
        'recommended_next_step' => 'No delete/move now. Review candidates for future authenticated compatibility stubs only after explicit approval and another dependency scan.',
        'final_blocks' => $finalBlocks,
        'finished_at' => gmdate('c'),
    ];
}

function gov_lpuq_print_text(array $report): void
{
    echo 'Legacy Public Utility Quiet-Period Audit ' . GOV_LEGACY_PUBLIC_UTILITY_QUIET_AUDIT_VERSION . PHP_EOL;
    echo 'Safety: ' . GOV_LEGACY_PUBLIC_UTILITY_QUIET_AUDIT_SAFETY . PHP_EOL;
    echo 'OK: ' . (!empty($report['ok']) ? 'yes' : 'no') . PHP_EOL;
    $summary = is_array($report['summary'] ?? null) ? $report['summary'] : [];
    echo 'Routes reviewed: ' . (string)($summary['routes_reviewed'] ?? 0) . PHP_EOL;
    echo 'Stub review candidates: ' . (string)($summary['quiet_period_stub_review_candidates'] ?? 0) . PHP_EOL;
    echo 'Delete recommended now: 0' . PHP_EOL;
    foreach ((array)($report['routes'] ?? []) as $route) {
        if (!is_array($route)) {
            continue;
        }
        echo '- ' . (string)($route['route'] ?? '') . ' | mentions=' . (string)($route['mentions'] ?? 0) . ' | class=' . (string)($route['review_class'] ?? '') . PHP_EOL;
    }
}

function gov_lpuq_main(array $argv): int
{
    $args = gov_lpuq_args($argv);
    $quietDays = gov_lpuq_parse_quiet_days($argv);
    $report = gov_legacy_public_utility_quiet_period_audit_run($quietDays);
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
