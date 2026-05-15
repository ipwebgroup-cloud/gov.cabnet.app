<?php
/**
 * gov.cabnet.app — Public utility reference cleanup phase 2 preview.
 *
 * Read-only scanner for remaining actionable references to guarded public-root
 * Bolt/EDXEIX utility endpoints. It does not move, edit, delete, write, connect
 * to DB, call Bolt, call EDXEIX, or call AADE.
 *
 * v3.0.91:
 * - Filters intentional legacy wrapper / registry / navigation documentation refs
 *   from actionable cleanup counts after the v3.0.89 wrapper was added.
 */

declare(strict_types=1);

const GOV_PURP2_VERSION = 'v3.0.91-public-utility-reference-cleanup-preview-ignore-wrapper-noise';
const GOV_PURP2_SAFETY = 'No Bolt call. No EDXEIX call. No AADE call. No database connection. No filesystem writes. No route moves. No route deletion. Read-only scan only.';

/** @return array<int,string> */
function gov_purp2_args(array $argv): array
{
    $out = [];
    foreach ($argv as $arg) {
        if (is_string($arg) && str_starts_with($arg, '--')) {
            $out[] = $arg;
        }
    }
    return $out;
}

function gov_purp2_app_root(): string
{
    return dirname(__DIR__);
}

function gov_purp2_home_root(): string
{
    return dirname(gov_purp2_app_root());
}

function gov_purp2_public_root(): string
{
    return gov_purp2_home_root() . '/public_html/gov.cabnet.app';
}

function gov_purp2_docs_root(): string
{
    return gov_purp2_home_root() . '/docs';
}

/** @return array<int,string> */
function gov_purp2_targets(): array
{
    return [
        'bolt-api-smoke-test.php',
        'bolt-fleet-orders-watch.php',
        'bolt_stage_edxeix_jobs.php',
        'bolt_submission_worker.php',
        'bolt_sync_orders.php',
        'bolt_sync_reference.php',
    ];
}

function gov_purp2_should_skip_path(string $path, bool $isDir): bool
{
    $path = str_replace('\\', '/', $path);
    $lower = strtolower($path);
    $probe = $lower . ($isDir ? '/' : '');

    $skipSegments = [
        '/.git/', '/cache/', '/tmp/', '/temp/', '/sessions/', '/session/', '/logs/', '/log/',
        '/mail/', '/maildir/', '/storage/runtime/', '/storage/artifacts/', '/storage/logs/',
        '/storage/tmp/', '/storage/temp/', '/storage/patch_backups/', '/patch_backups/',
        '/handoff-packages/', '/vendor/', '/node_modules/', '/var/',
    ];
    foreach ($skipSegments as $segment) {
        if (str_contains($probe, $segment)) {
            return true;
        }
    }

    $base = strtolower(basename($path));
    if (preg_match('/\.(zip|tar|tgz|gz|bz2|7z|rar|bak|backup|old|tmp|swp|swo)$/i', $base)) {
        return true;
    }

    return false;
}

function gov_purp2_is_planner_or_inventory_ref(string $path): bool
{
    $path = str_replace('\\', '/', $path);
    $needles = [
        '/ops/route-index.php',
        '/ops/public-utility-relocation-plan.php',
        '/ops/public-route-exposure-audit.php',
        '/ops/public-utility-reference-cleanup-phase2-preview.php',
        '/ops/legacy-public-utility.php',
        '/ops/_shell.php',
        '/gov.cabnet.app_app/cli/public_utility_relocation_plan.php',
        '/gov.cabnet.app_app/cli/public_route_exposure_audit.php',
        '/gov.cabnet.app_app/cli/public_utility_reference_cleanup_phase2_preview.php',
        '/gov.cabnet.app_app/src/Support/LegacyPublicUtilityRegistry.php',
        '/docs/LIVE_PUBLIC_UTILITY_RELOCATION_PLAN_',
        '/docs/LIVE_PUBLIC_UTILITY_DEPENDENCY_EVIDENCE_',
        '/docs/LIVE_PUBLIC_UTILITY_REFERENCE_CLEANUP_PLAN_',
        '/docs/LIVE_PUBLIC_UTILITY_REFERENCE_CLEANUP_PHASE1_',
        '/docs/LIVE_PUBLIC_UTILITY_REFERENCE_CLEANUP_PHASE2_',
        '/docs/LIVE_PUBLIC_ROUTE_EXPOSURE_AUDIT_',
        '/docs/LIVE_LEGACY_PUBLIC_UTILITY_',
    ];
    foreach ($needles as $needle) {
        if (str_contains($path, $needle)) {
            return true;
        }
    }
    return false;
}

function gov_purp2_ref_kind(string $path): string
{
    $path = str_replace('\\', '/', $path);
    if (str_contains($path, '/public_html/gov.cabnet.app/ops/')) {
        return 'ops_route_or_page';
    }
    if (str_contains($path, '/public_html/gov.cabnet.app/')) {
        return 'public_root_code';
    }
    if (str_contains($path, '/gov.cabnet.app_app/')) {
        return 'private_app_code';
    }
    if (str_contains($path, '/docs/')) {
        return 'server_docs';
    }
    return 'other_project_file';
}

function gov_purp2_action_hint(string $kind, string $path): string
{
    $path = str_replace('\\', '/', $path);
    if ($kind === 'server_docs') {
        return 'Update text to mark legacy public-root utility as archived/compatibility-only, or point to /ops/legacy-public-utility.php.';
    }
    if ($kind === 'ops_route_or_page') {
        return 'Replace direct public-root button/link with /ops/legacy-public-utility.php or the relocation plan. Keep old public-root route working.';
    }
    if ($kind === 'private_app_code') {
        return 'Review before editing. If it only generates lab/dev links, point to the legacy wrapper or relocation plan.';
    }
    if ($kind === 'public_root_code') {
        return 'Likely compatibility/readiness self-reference. Do not edit unless replacing readiness expectations with explicit legacy compatibility labels.';
    }
    return 'Manual review required.';
}

/** @return array<int,array<string,mixed>> */
function gov_purp2_scan_references(array $targets, array $roots): array
{
    $refs = [];
    $targetPattern = '/(' . implode('|', array_map(static fn(string $s): string => preg_quote($s, '/'), $targets)) . ')/';
    $allowedFilePattern = '/\.(php|md|txt|json|sh|sql|htaccess|ini)$/i';

    $scanDir = static function (string $dir) use (&$scanDir, &$refs, $targetPattern, $allowedFilePattern): void {
        if (count($refs) >= 500) {
            return;
        }
        if (!is_dir($dir) || !is_readable($dir) || gov_purp2_should_skip_path($dir, true)) {
            return;
        }
        $items = @scandir($dir);
        if (!is_array($items)) {
            return;
        }

        foreach ($items as $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }
            if (count($refs) >= 500) {
                return;
            }
            $path = rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $name;
            if (is_link($path)) {
                continue;
            }
            if (is_dir($path)) {
                if (!is_readable($path) || gov_purp2_should_skip_path($path, true)) {
                    continue;
                }
                $scanDir($path);
                continue;
            }
            if (!is_file($path) || !is_readable($path) || gov_purp2_should_skip_path($path, false)) {
                continue;
            }
            if (!preg_match($allowedFilePattern, basename($path))) {
                continue;
            }

            $lines = @file($path, FILE_IGNORE_NEW_LINES);
            if (!is_array($lines)) {
                continue;
            }
            foreach ($lines as $lineNo => $line) {
                if (!preg_match($targetPattern, (string)$line, $m)) {
                    continue;
                }
                $matched = (string)$m[1];
                $kind = gov_purp2_ref_kind($path);
                $isInventory = gov_purp2_is_planner_or_inventory_ref($path);
                $refs[] = [
                    'target' => $matched,
                    'path' => $path,
                    'line' => $lineNo + 1,
                    'kind' => $kind,
                    'inventory_or_planner_reference' => $isInventory,
                    'actionable' => !$isInventory,
                    'action_hint' => $isInventory ? 'Ignore for cleanup counts; this is an inventory/planner/wrapper/audit reference.' : gov_purp2_action_hint($kind, $path),
                    'preview' => trim(substr((string)$line, 0, 240)),
                ];
            }
        }
    };

    foreach ($roots as $root) {
        $scanDir((string)$root);
    }

    return $refs;
}

/** @return array<string,mixed> */
function gov_public_utility_reference_cleanup_phase2_preview_run(): array
{
    $targets = gov_purp2_targets();
    $roots = [gov_purp2_public_root(), gov_purp2_app_root(), gov_purp2_docs_root()];
    $refs = gov_purp2_scan_references($targets, $roots);

    $actionable = [];
    $ignored = [];
    $byKind = [];
    $byTarget = [];
    foreach ($refs as $ref) {
        if (!empty($ref['actionable'])) {
            $actionable[] = $ref;
            $kind = (string)($ref['kind'] ?? 'unknown');
            $target = (string)($ref['target'] ?? 'unknown');
            $byKind[$kind] = ($byKind[$kind] ?? 0) + 1;
            $byTarget[$target] = ($byTarget[$target] ?? 0) + 1;
        } else {
            $ignored[] = $ref;
        }
    }
    ksort($byKind);
    ksort($byTarget);

    $safePhase2 = [];
    foreach ($actionable as $ref) {
        $kind = (string)($ref['kind'] ?? '');
        if ($kind === 'server_docs' || $kind === 'ops_route_or_page') {
            $safePhase2[] = $ref;
        }
    }

    return [
        'ok' => true,
        'version' => GOV_PURP2_VERSION,
        'mode' => 'read_only_public_utility_reference_cleanup_phase2_preview',
        'started_at' => gmdate('c'),
        'safety' => GOV_PURP2_SAFETY,
        'roots_scanned' => $roots,
        'targets' => $targets,
        'summary' => [
            'total_references' => count($refs),
            'inventory_or_planner_references_ignored' => count($ignored),
            'actionable_references' => count($actionable),
            'safe_phase2_candidates' => count($safePhase2),
            'actionable_by_kind' => $byKind,
            'actionable_by_target' => $byTarget,
        ],
        'safe_phase2_candidates' => array_slice($safePhase2, 0, 80),
        'actionable_references_sample' => array_slice($actionable, 0, 120),
        'ignored_inventory_or_planner_sample' => array_slice($ignored, 0, 40),
        'recommended_next_step' => count($safePhase2) > 0
            ? 'Patch only server docs and ops/admin links first. Do not move or delete public-root utilities.'
            : 'No docs/ops safe Phase 2 candidates found. Review private_app_code and public_root_code manually before any change.',
        'final_blocks' => [],
        'finished_at' => gmdate('c'),
    ];
}

function gov_purp2_print_text(array $report): void
{
    echo 'Public Utility Reference Cleanup Phase 2 Preview ' . GOV_PURP2_VERSION . PHP_EOL;
    echo 'Safety: ' . GOV_PURP2_SAFETY . PHP_EOL;
    echo 'OK: ' . (!empty($report['ok']) ? 'yes' : 'no') . PHP_EOL;
    $summary = is_array($report['summary'] ?? null) ? $report['summary'] : [];
    echo 'Total references: ' . (string)($summary['total_references'] ?? 0) . PHP_EOL;
    echo 'Ignored inventory/planner references: ' . (string)($summary['inventory_or_planner_references_ignored'] ?? 0) . PHP_EOL;
    echo 'Actionable references: ' . (string)($summary['actionable_references'] ?? 0) . PHP_EOL;
    echo 'Safe Phase 2 candidates: ' . (string)($summary['safe_phase2_candidates'] ?? 0) . PHP_EOL;
}

function gov_purp2_main(array $argv): int
{
    $args = gov_purp2_args($argv);
    $report = gov_public_utility_reference_cleanup_phase2_preview_run();
    if (in_array('--json', $args, true)) {
        echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    } else {
        gov_purp2_print_text($report);
    }
    return !empty($report['ok']) ? 0 : 1;
}

if (PHP_SAPI === 'cli' && realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === realpath(__FILE__)) {
    exit(gov_purp2_main($argv));
}
