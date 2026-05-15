<?php
/**
 * gov.cabnet.app — Public Utility Relocation Plan CLI.
 *
 * Read-only planner for guarded public-root Bolt/EDXEIX utility endpoints.
 * It does not move, delete, disable, call external services, connect to DB, or write files.
 */

declare(strict_types=1);

const GOV_PUBLIC_UTILITY_RELOCATION_PLAN_VERSION = 'v3.0.83-public-utility-relocation-plan';
const GOV_PUBLIC_UTILITY_RELOCATION_PLAN_SAFETY = 'No Bolt call. No EDXEIX call. No AADE call. No database connection. No filesystem writes. No route moves. No route deletion. Read-only source scan.';

/** @return array<int,string> */
function gov_purp_args(array $argv): array
{
    $out = [];
    foreach ($argv as $arg) {
        if (is_string($arg) && str_starts_with($arg, '--')) {
            $out[] = $arg;
        }
    }
    return $out;
}

function gov_purp_app_root(): string
{
    return dirname(__DIR__);
}

function gov_purp_home_root(): string
{
    return dirname(gov_purp_app_root());
}

function gov_purp_public_root(): string
{
    return gov_purp_home_root() . '/public_html/gov.cabnet.app';
}

/** @return array<string,array<string,string>> */
function gov_purp_targets(): array
{
    return [
        'bolt-api-smoke-test.php' => [
            'current_route' => '/bolt-api-smoke-test.php',
            'role' => 'Bolt API smoke/readiness probe',
            'current_mode' => 'Browser and CLI wrapper; calls Bolt read APIs through private lib; no secret output by design.',
            'recommended_target' => 'private_cli_plus_ops_wrapper',
            'target_path' => '/home/cabnet/gov.cabnet.app_app/cli/bolt_api_smoke_test.php with optional /ops/bolt-api-smoke-test.php wrapper',
            'compatibility_strategy' => 'Keep current public-root URL until bookmarks/cron/monitor usage is checked; later replace with an authenticated redirect or 410 notice.',
            'why' => 'It is operationally useful but should not live at public root long-term because it reads live Bolt API metadata.',
        ],
        'bolt-fleet-orders-watch.php' => [
            'current_route' => '/bolt-fleet-orders-watch.php',
            'role' => 'Bolt fleet order watcher/state diff utility',
            'current_mode' => 'Browser and CLI wrapper; calls Bolt read API and writes watcher state to /tmp.',
            'recommended_target' => 'private_cli_only',
            'target_path' => '/home/cabnet/gov.cabnet.app_app/cli/bolt_fleet_orders_watch.php',
            'compatibility_strategy' => 'Do not move until cron/monitor usage is confirmed. If used, update cron to CLI first, then leave a short-lived authenticated compatibility stub.',
            'why' => 'It has local file-write behavior and is a better fit for CLI/cron than public-root web access.',
        ],
        'bolt_stage_edxeix_jobs.php' => [
            'current_route' => '/bolt_stage_edxeix_jobs.php',
            'role' => 'Legacy guarded local EDXEIX job staging dry-run/staging utility',
            'current_mode' => 'Browser and CLI wrapper; create=1 can stage local submission_jobs records; no EDXEIX call.',
            'recommended_target' => 'locked_cli_or_ops_admin_only',
            'target_path' => '/home/cabnet/gov.cabnet.app_app/cli/bolt_stage_edxeix_jobs.php or /ops/legacy-bolt-stage-edxeix-jobs.php',
            'compatibility_strategy' => 'Keep unchanged until V3 fully replaces legacy staging. Later move behind Developer Archive / CLI and leave compatibility stub if required.',
            'why' => 'It is submit/stage-adjacent and should be kept away from public-root URLs even when auth-prepend is active.',
        ],
        'bolt_submission_worker.php' => [
            'current_route' => '/bolt_submission_worker.php',
            'role' => 'Legacy dry-run submission worker / local audit recorder',
            'current_mode' => 'Browser and CLI wrapper; record=1 writes local submission_attempts audit rows only; no EDXEIX call.',
            'recommended_target' => 'locked_cli_or_ops_admin_only',
            'target_path' => '/home/cabnet/gov.cabnet.app_app/cli/bolt_submission_worker.php or /ops/legacy-bolt-submission-worker.php',
            'compatibility_strategy' => 'Keep unchanged until cron/bookmark usage is known and V3 operator flow is fully replacing it. Then move or archive.',
            'why' => 'It is submit-adjacent and belongs behind a clearer CLI/ops-only boundary.',
        ],
        'bolt_sync_orders.php' => [
            'current_route' => '/bolt_sync_orders.php',
            'role' => 'Bolt fleet orders sync into raw payloads / normalized bookings',
            'current_mode' => 'Browser and CLI wrapper; dry_run parameter available; calls private sync lib.',
            'recommended_target' => 'private_cli_first',
            'target_path' => '/home/cabnet/gov.cabnet.app_app/cli/bolt_sync_orders.php with optional /ops/bolt-sync-orders.php audit wrapper',
            'compatibility_strategy' => 'Check cron first. If active, migrate cron to private CLI. Keep web URL as authenticated compatibility stub during transition.',
            'why' => 'This is a job/sync operation and should be managed as CLI/cron or supervised ops action, not public-root utility.',
        ],
        'bolt_sync_reference.php' => [
            'current_route' => '/bolt_sync_reference.php',
            'role' => 'Bolt drivers/vehicles reference sync into mapping tables',
            'current_mode' => 'Browser and CLI wrapper; dry_run parameter available; calls private sync lib.',
            'recommended_target' => 'private_cli_first',
            'target_path' => '/home/cabnet/gov.cabnet.app_app/cli/bolt_sync_reference.php with optional /ops/bolt-sync-reference.php audit wrapper',
            'compatibility_strategy' => 'Check cron/operator usage first. If active, migrate to private CLI. Keep web URL as authenticated compatibility stub during transition.',
            'why' => 'Reference sync is operational maintenance and belongs in CLI/ops, not as public-root utility.',
        ],
    ];
}

function gov_purp_file_meta(string $path): array
{
    return [
        'path' => $path,
        'exists' => is_file($path),
        'readable' => is_readable($path),
        'size' => is_file($path) ? (int)filesize($path) : 0,
        'modified_at' => is_file($path) ? date('Y-m-d H:i:s', (int)filemtime($path)) : null,
    ];
}

/** @return array<string,mixed> */
function gov_purp_token_scan(string $raw): array
{
    $patterns = [
        'bolt_api_read' => '/gov_bolt_get_|gov_bolt_sync_|bolt/i',
        'edxeix_terms' => '/edxeix/i',
        'db_write_tokens' => '/\b(INSERT\s+INTO|UPDATE\s+|DELETE\s+FROM|submission_jobs|submission_attempts|normalized_bookings|->query\s*\(|->prepare\s*\()/i',
        'file_write_tokens' => '/\b(file_put_contents|fwrite|LOCK_EX|stateFile)\b/i',
        'external_http_tokens' => '/\b(curl_|stream_context_create|file_get_contents\s*\([^)]*https?:)/i',
        'dangerous_shell_tokens' => '/\b(shell_exec|exec\s*\(|passthru|proc_open|popen)\b/i',
        'explicit_write_params' => '/\b(create=1|record=1|dry_run|allow_lab)\b/i',
        'live_submit_blocks' => '/does NOT call EDXEIX|does not submit|dry-run|dry run|blocked from live submission|No EDXEIX/i',
    ];

    $out = [];
    foreach ($patterns as $key => $pattern) {
        $out[$key] = (bool)preg_match($pattern, $raw);
    }
    return $out;
}

/** @return array<int,array<string,mixed>> */
function gov_purp_scan_project_references(array $targetNames, array $roots): array
{
    $refs = [];
    $targetPattern = '/(' . implode('|', array_map(static fn(string $s): string => preg_quote($s, '/'), $targetNames)) . ')/';
    foreach ($roots as $root) {
        if (!is_dir($root) || !is_readable($root)) {
            continue;
        }
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iterator as $item) {
            if (!$item->isFile() || !$item->isReadable() || $item->isLink()) {
                continue;
            }
            $path = $item->getPathname();
            $lower = strtolower(str_replace('\\', '/', $path));
            if (str_contains($lower, '/storage/runtime/') || str_contains($lower, '/storage/artifacts/') || str_contains($lower, '/storage/logs/') || str_contains($lower, '/cache/') || str_contains($lower, '/tmp/')) {
                continue;
            }
            if (!preg_match('/\.(php|md|txt|json|sh|sql|htaccess|ini)$/i', basename($path))) {
                continue;
            }
            $raw = @file($path, FILE_IGNORE_NEW_LINES);
            if (!is_array($raw)) {
                continue;
            }
            foreach ($raw as $lineNo => $line) {
                if (!preg_match($targetPattern, $line, $m)) {
                    continue;
                }
                $matched = (string)$m[1];
                $refs[] = [
                    'target' => $matched,
                    'path' => $path,
                    'line' => $lineNo + 1,
                    'preview' => trim(substr((string)$line, 0, 220)),
                ];
                if (count($refs) >= 200) {
                    return $refs;
                }
            }
        }
    }
    return $refs;
}

/** @return array<string,mixed> */
function gov_public_utility_relocation_plan_run(): array
{
    $publicRoot = gov_purp_public_root();
    $appRoot = gov_purp_app_root();
    $targets = gov_purp_targets();
    $targetNames = array_keys($targets);
    $references = gov_purp_scan_project_references($targetNames, [$publicRoot, $appRoot]);

    $byTarget = [];
    foreach ($references as $ref) {
        $target = (string)($ref['target'] ?? '');
        if ($target === '') {
            continue;
        }
        $byTarget[$target][] = $ref;
    }

    $routes = [];
    $moveReadyNow = 0;
    $requiresCronCheck = 0;
    foreach ($targets as $file => $info) {
        $path = $publicRoot . '/' . $file;
        $meta = gov_purp_file_meta($path);
        $raw = ($meta['readable'] ?? false) ? (string)@file_get_contents($path) : '';
        $tokens = gov_purp_token_scan($raw);
        $refs = $byTarget[$file] ?? [];
        $selfRefs = array_values(array_filter($refs, static function (array $ref) use ($path): bool {
            return str_replace('\\', '/', (string)($ref['path'] ?? '')) === str_replace('\\', '/', $path);
        }));
        $externalRefs = array_values(array_filter($refs, static function (array $ref) use ($path): bool {
            return str_replace('\\', '/', (string)($ref['path'] ?? '')) !== str_replace('\\', '/', $path);
        }));

        $needsCronCheck = in_array($file, ['bolt-fleet-orders-watch.php', 'bolt_sync_orders.php', 'bolt_sync_reference.php', 'bolt_stage_edxeix_jobs.php', 'bolt_submission_worker.php'], true);
        if ($needsCronCheck) {
            $requiresCronCheck++;
        } else {
            $moveReadyNow++;
        }

        $routes[] = [
            'file' => $file,
            'current_route' => $info['current_route'],
            'role' => $info['role'],
            'current_mode' => $info['current_mode'],
            'recommended_target' => $info['recommended_target'],
            'target_path' => $info['target_path'],
            'compatibility_strategy' => $info['compatibility_strategy'],
            'why' => $info['why'],
            'file_meta' => $meta,
            'tokens' => $tokens,
            'project_reference_count' => count($refs),
            'external_project_reference_count' => count($externalRefs),
            'external_project_references_sample' => array_slice($externalRefs, 0, 8),
            'requires_cron_or_bookmark_check_before_move' => $needsCronCheck,
            'delete_now' => false,
            'move_now' => false,
            'safe_next_action' => $needsCronCheck
                ? 'Check cron/monitor/bookmark usage before any relocation. No code move now.'
                : 'Can be moved later through a compatibility-stub patch after Andreas approval. No code move now.',
        ];
    }

    return [
        'ok' => true,
        'version' => GOV_PUBLIC_UTILITY_RELOCATION_PLAN_VERSION,
        'mode' => 'read_only_public_utility_relocation_plan',
        'started_at' => gmdate('c'),
        'safety' => GOV_PUBLIC_UTILITY_RELOCATION_PLAN_SAFETY,
        'public_root' => $publicRoot,
        'app_root' => $appRoot,
        'summary' => [
            'target_public_utilities' => count($targets),
            'delete_recommended_now' => 0,
            'move_recommended_now' => 0,
            'requires_cron_or_bookmark_check' => $requiresCronCheck,
            'planned_as_cli_or_ops' => count($targets),
        ],
        'routes' => $routes,
        'operator_dependency_check_commands' => [
            'server_cron_search_root' => 'grep -RIn "bolt-api-smoke-test.php\\|bolt-fleet-orders-watch.php\\|bolt_stage_edxeix_jobs.php\\|bolt_submission_worker.php\\|bolt_sync_orders.php\\|bolt_sync_reference.php" /var/spool/cron /etc/cron* /home/cabnet 2>/dev/null | head -200',
            'project_reference_search' => 'grep -RIn "bolt-api-smoke-test.php\\|bolt-fleet-orders-watch.php\\|bolt_stage_edxeix_jobs.php\\|bolt_submission_worker.php\\|bolt_sync_orders.php\\|bolt_sync_reference.php" /home/cabnet/public_html/gov.cabnet.app /home/cabnet/gov.cabnet.app_app 2>/dev/null | head -200',
        ],
        'recommended_next_step' => 'No-break dependency check only. Do not move or delete public-root utilities until cron/monitor/bookmark references are known.',
        'final_blocks' => [],
        'finished_at' => gmdate('c'),
    ];
}

function gov_purp_print_text(array $report): void
{
    echo 'Public Utility Relocation Plan ' . GOV_PUBLIC_UTILITY_RELOCATION_PLAN_VERSION . PHP_EOL;
    echo 'Safety: ' . GOV_PUBLIC_UTILITY_RELOCATION_PLAN_SAFETY . PHP_EOL;
    echo 'OK: ' . (!empty($report['ok']) ? 'yes' : 'no') . PHP_EOL;
    $summary = is_array($report['summary'] ?? null) ? $report['summary'] : [];
    echo 'Target public utilities: ' . (string)($summary['target_public_utilities'] ?? 0) . PHP_EOL;
    echo 'Delete recommended now: ' . (string)($summary['delete_recommended_now'] ?? 0) . PHP_EOL;
    echo 'Move recommended now: ' . (string)($summary['move_recommended_now'] ?? 0) . PHP_EOL;
    echo 'Requires cron/bookmark check: ' . (string)($summary['requires_cron_or_bookmark_check'] ?? 0) . PHP_EOL;
    foreach ((array)($report['routes'] ?? []) as $route) {
        if (!is_array($route)) {
            continue;
        }
        echo '- ' . (string)($route['file'] ?? '') . ' → ' . (string)($route['recommended_target'] ?? '') . ' | move_now=no delete_now=no' . PHP_EOL;
    }
}

function gov_purp_main(array $argv): int
{
    $args = gov_purp_args($argv);
    $report = gov_public_utility_relocation_plan_run();
    if (in_array('--json', $args, true)) {
        echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    } else {
        gov_purp_print_text($report);
    }
    return !empty($report['ok']) ? 0 : 1;
}

if (PHP_SAPI === 'cli' && realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === realpath(__FILE__)) {
    exit(gov_purp_main($argv));
}
