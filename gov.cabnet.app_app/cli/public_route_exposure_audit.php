<?php
/**
 * gov.cabnet.app — public route exposure audit CLI.
 *
 * Read-only scanner for public-root PHP endpoints and global auth-prepend posture.
 * Safety: no Bolt call, no EDXEIX call, no AADE call, no DB connection, no writes.
 */

declare(strict_types=1);

const GOV_PUBLIC_ROUTE_EXPOSURE_AUDIT_VERSION = 'v3.0.81-public-route-exposure-audit';
const GOV_PUBLIC_ROUTE_EXPOSURE_AUDIT_SAFETY = 'No Bolt call. No EDXEIX call. No AADE call. No database connection. No filesystem writes. Read-only source scan.';

/** @return array<string,string> */
function gov_pra_args(array $argv): array
{
    $out = [];
    foreach ($argv as $arg) {
        $arg = (string)$arg;
        if (!str_starts_with($arg, '--')) {
            continue;
        }
        $arg = substr($arg, 2);
        if (str_contains($arg, '=')) {
            [$k, $v] = explode('=', $arg, 2);
            $out[$k] = $v;
        } else {
            $out[$arg] = '1';
        }
    }
    return $out;
}

function gov_pra_app_root(): string
{
    return dirname(__DIR__);
}

function gov_pra_home_root(): string
{
    return dirname(gov_pra_app_root());
}

function gov_pra_public_root(): string
{
    $env = getenv('GOVCABNET_PUBLIC_ROOT');
    if (is_string($env) && trim($env) !== '') {
        return rtrim($env, '/');
    }
    return gov_pra_home_root() . '/public_html/gov.cabnet.app';
}

/** @return array<string,mixed> */
function gov_pra_file_meta(string $path): array
{
    return [
        'path' => $path,
        'exists' => is_file($path),
        'readable' => is_readable($path),
        'size' => is_file($path) ? (int)filesize($path) : 0,
        'modified_at' => is_file($path) ? date('Y-m-d H:i:s', (int)filemtime($path)) : null,
    ];
}

function gov_pra_read(string $path): string
{
    if (!is_readable($path)) {
        return '';
    }
    $raw = file_get_contents($path);
    return is_string($raw) ? $raw : '';
}

/** @return array<int,string> */
function gov_pra_token_hits(string $raw, array $patterns): array
{
    $hits = [];
    foreach ($patterns as $label => $regex) {
        if (preg_match($regex, $raw)) {
            $hits[] = is_string($label) ? $label : (string)$regex;
        }
    }
    return $hits;
}

/** @return array<string,mixed> */
function gov_pra_auth_posture(string $publicRoot): array
{
    $userIni = $publicRoot . '/.user.ini';
    $authPrepend = $publicRoot . '/_auth_prepend.php';
    $htaccess = $publicRoot . '/.htaccess';

    $iniRaw = gov_pra_read($userIni);
    $prependRaw = gov_pra_read($authPrepend);
    $htRaw = gov_pra_read($htaccess);

    $autoPrependPath = '';
    if (preg_match('/^\s*auto_prepend_file\s*=\s*["\']?([^"\'\r\n]+)["\']?/mi', $iniRaw, $m)) {
        $autoPrependPath = trim((string)$m[1]);
    }

    $autoPrependMatches = $autoPrependPath !== ''
        && (realpath($autoPrependPath) !== false)
        && realpath($autoPrependPath) === realpath($authPrepend);

    $checks = [
        'user_ini_present' => is_file($userIni) && is_readable($userIni),
        'auto_prepend_declared' => $autoPrependPath !== '',
        'auto_prepend_target_exists' => $autoPrependPath !== '' && is_file($autoPrependPath) && is_readable($autoPrependPath),
        'auto_prepend_targets_auth_prepend' => $autoPrependMatches,
        'auth_prepend_present' => is_file($authPrepend) && is_readable($authPrepend),
        'auth_prepend_uses_opsauth' => str_contains($prependRaw, 'Bridge\\Auth\\OpsAuth') || str_contains($prependRaw, 'OpsAuth'),
        'auth_prepend_requires_login' => str_contains($prependRaw, 'requireLogin'),
        'auth_prepend_allows_internal_key' => str_contains($prependRaw, 'isInternalKeyAllowed'),
        'auth_prepend_blocks_helper_direct_access' => str_contains($prependRaw, '_auth_prepend.php') && str_contains($prependRaw, 'http_response_code(404)'),
        'htaccess_present' => is_file($htaccess) && is_readable($htaccess),
        'htaccess_denies_auth_prepend' => preg_match('/_auth_prepend\\?\.php|_auth_prepend\\\\\.php|_auth_prepend\.php/i', $htRaw) === 1,
        'htaccess_denies_user_ini' => preg_match('/\\?\.user\\?\.ini|\.user\.ini/i', $htRaw) === 1,
    ];

    $criticalBlocks = [];
    if (!$checks['user_ini_present']) {
        $criticalBlocks[] = '.user.ini missing or unreadable; global auth prepend may not load';
    }
    if (!$checks['auto_prepend_declared']) {
        $criticalBlocks[] = '.user.ini does not declare auto_prepend_file';
    }
    if (!$checks['auto_prepend_target_exists']) {
        $criticalBlocks[] = 'auto_prepend_file target missing or unreadable';
    }
    if (!$checks['auth_prepend_requires_login']) {
        $criticalBlocks[] = '_auth_prepend.php does not contain requireLogin marker';
    }

    $warnings = [];
    if (!$checks['auto_prepend_targets_auth_prepend']) {
        $warnings[] = 'auto_prepend_file path does not resolve exactly to public _auth_prepend.php';
    }
    if (!$checks['htaccess_denies_auth_prepend']) {
        $warnings[] = '.htaccess direct-file deny for _auth_prepend.php was not detected';
    }
    if (!$checks['htaccess_denies_user_ini']) {
        $warnings[] = '.htaccess direct-file deny for .user.ini was not detected';
    }

    return [
        'ok' => $criticalBlocks === [],
        'mode' => 'global_auto_prepend_auth_guard',
        'files' => [
            'user_ini' => gov_pra_file_meta($userIni),
            'auth_prepend' => gov_pra_file_meta($authPrepend),
            'htaccess' => gov_pra_file_meta($htaccess),
        ],
        'auto_prepend_path' => $autoPrependPath,
        'checks' => $checks,
        'critical_blocks' => $criticalBlocks,
        'warnings' => $warnings,
    ];
}

/** @return string */
function gov_pra_classify_public_route(string $name, string $raw): string
{
    if ($name === 'index.php') {
        return 'keep_public_landing';
    }
    if ($name === '_auth_prepend.php') {
        return 'internal_auth_helper_not_route';
    }
    if (preg_match('/submission_worker|stage_edxeix|sync_orders|sync_reference|api-smoke-test|fleet-orders-watch/i', $name)) {
        return 'locked_review_internal_utility';
    }
    if (preg_match('/readiness|preflight|jobs_queue/i', $name)) {
        return 'keep_admin_audit_json';
    }
    if (preg_match('/extension-payload/i', $name)) {
        return 'keep_guarded_helper';
    }
    if (preg_match('/mysqli|INSERT\s+INTO|UPDATE\s+`?|DELETE\s+FROM|CREATE\s+TABLE|DROP\s+TABLE/i', $raw)) {
        return 'locked_review_db_touching_public_endpoint';
    }
    return 'review_public_root_endpoint';
}

/** @return array<string,mixed> */
function gov_pra_scan_php_file(string $path, string $route): array
{
    $name = basename($path);
    $raw = gov_pra_read($path);
    $writeTokens = gov_pra_token_hits($raw, [
        'insert' => '/\bINSERT\s+INTO\b/i',
        'update' => '/\bUPDATE\s+`?[A-Za-z0-9_]+/i',
        'delete' => '/\bDELETE\s+FROM\b/i',
        'replace' => '/\bREPLACE\s+INTO\b/i',
        'alter_or_drop' => '/\b(ALTER|DROP|TRUNCATE)\s+(TABLE|DATABASE)\b/i',
        'file_write' => '/\b(file_put_contents|fopen\s*\([^,]+,\s*["\'](?:w|a|x|c))/i',
    ]);
    $networkTokens = gov_pra_token_hits($raw, [
        'curl' => '/\bcurl_[a-z_]+\s*\(/i',
        'http_file_get_contents' => '/file_get_contents\s*\([^)]*https?:\/\//i',
        'stream_context' => '/stream_context_create\s*\(/i',
    ]);
    $submitTokens = gov_pra_token_hits($raw, [
        'submit_word' => '/submit/i',
        'stage_word' => '/stage/i',
        'sync_word' => '/sync/i',
        'worker_word' => '/worker/i',
        'create_flag' => '/\$_GET\s*\[\s*["\']create["\']\s*\]/i',
    ]);
    $authMarkers = gov_pra_token_hits($raw, [
        'require_login' => '/requireLogin\s*\(/',
        'ops_auth' => '/OpsAuth|_auth\.php|ops-auth\.php|_ops-auth\.php/i',
        'auth_prepend_constant' => '/GOV_CABNET_AUTH_PREPEND_LOADED/',
    ]);

    $classification = gov_pra_classify_public_route($name, $raw);
    $risk = 'low_when_auth_prepend_active';
    if ($classification === 'internal_auth_helper_not_route') {
        $risk = 'direct_access_should_404';
    } elseif ($writeTokens !== [] || $networkTokens !== [] || preg_match('/submit|stage|sync|worker/i', $name)) {
        $risk = 'guarded_requires_auth_or_internal_key';
    }

    return [
        'name' => $name,
        'route' => $route,
        'path' => $path,
        'size' => is_file($path) ? (int)filesize($path) : 0,
        'modified_at' => is_file($path) ? date('Y-m-d H:i:s', (int)filemtime($path)) : null,
        'classification' => $classification,
        'risk' => $risk,
        'has_direct_auth_markers' => $authMarkers !== [],
        'auth_markers' => $authMarkers,
        'write_tokens' => $writeTokens,
        'network_tokens' => $networkTokens,
        'submit_stage_sync_tokens' => $submitTokens,
        'recommended_action' => gov_pra_recommend_action($classification),
    ];
}

function gov_pra_recommend_action(string $classification): string
{
    return match ($classification) {
        'keep_public_landing' => 'Keep public. Verify it remains informational only.',
        'internal_auth_helper_not_route' => 'Keep protected by .htaccess and helper self-404.',
        'locked_review_internal_utility' => 'Keep behind auth/internal key only. Consider moving under /ops or CLI in a later no-break cleanup.',
        'keep_admin_audit_json' => 'Keep as authenticated JSON/admin audit endpoint. Consider adding links only from Developer Archive.',
        'keep_guarded_helper' => 'Keep guarded; verify it does not expose sensitive payloads without auth.',
        'locked_review_db_touching_public_endpoint' => 'Review before any public/root cleanup. Do not delete until replacement path exists.',
        default => 'Review and classify before cleanup.',
    };
}

/** @return array<int,array<string,mixed>> */
function gov_pra_public_root_routes(string $publicRoot): array
{
    $files = glob(rtrim($publicRoot, '/') . '/*.php') ?: [];
    sort($files, SORT_STRING);
    $routes = [];
    foreach ($files as $path) {
        $name = basename($path);
        $route = $name === 'index.php' ? '/' : '/' . $name;
        $routes[] = gov_pra_scan_php_file($path, $route);
    }
    return $routes;
}

/** @return array<string,mixed> */
function gov_pra_ops_summary(string $publicRoot): array
{
    $opsRoot = rtrim($publicRoot, '/') . '/ops';
    $files = glob($opsRoot . '/*.php') ?: [];
    sort($files, SORT_STRING);
    $devLike = [];
    $submitLike = [];
    foreach ($files as $file) {
        $name = basename($file);
        if (preg_match('/dev|test|probe|capture|simulation|contract|harness|visibility|archive/i', $name)) {
            $devLike[] = '/ops/' . $name;
        }
        if (preg_match('/submit|submission|worker|stage|live/i', $name)) {
            $submitLike[] = '/ops/' . $name;
        }
    }
    return [
        'ops_root' => $opsRoot,
        'exists' => is_dir($opsRoot),
        'php_route_count' => count($files),
        'developer_like_count' => count($devLike),
        'submit_like_count' => count($submitLike),
        'developer_like_examples' => array_slice($devLike, 0, 25),
        'submit_like_examples' => array_slice($submitLike, 0, 25),
    ];
}

/** @return array<string,mixed> */
function gov_pra_run(): array
{
    $publicRoot = gov_pra_public_root();
    $auth = gov_pra_auth_posture($publicRoot);
    $publicRoutes = gov_pra_public_root_routes($publicRoot);
    $opsSummary = gov_pra_ops_summary($publicRoot);

    $guardedCount = 0;
    $writeCount = 0;
    $networkCount = 0;
    foreach ($publicRoutes as $route) {
        if (($route['risk'] ?? '') === 'guarded_requires_auth_or_internal_key') {
            $guardedCount++;
        }
        if (!empty($route['write_tokens'])) {
            $writeCount++;
        }
        if (!empty($route['network_tokens'])) {
            $networkCount++;
        }
    }

    $finalBlocks = [];
    foreach (($auth['critical_blocks'] ?? []) as $block) {
        $finalBlocks[] = 'auth: ' . (string)$block;
    }

    $warnings = [];
    foreach (($auth['warnings'] ?? []) as $warning) {
        $warnings[] = 'auth: ' . (string)$warning;
    }
    if ($guardedCount > 0) {
        $warnings[] = 'public root has ' . $guardedCount . ' guarded utility endpoints; keep global auth prepend active and consider later relocation under /ops or CLI';
    }

    return [
        'ok' => $finalBlocks === [],
        'version' => GOV_PUBLIC_ROUTE_EXPOSURE_AUDIT_VERSION,
        'mode' => 'read_only_public_route_exposure_audit',
        'started_at' => date('c'),
        'safety' => GOV_PUBLIC_ROUTE_EXPOSURE_AUDIT_SAFETY,
        'public_root' => $publicRoot,
        'auth_posture' => $auth,
        'summary' => [
            'public_root_php_count' => count($publicRoutes),
            'ops_php_count' => (int)($opsSummary['php_route_count'] ?? 0),
            'guarded_public_utility_count' => $guardedCount,
            'public_routes_with_write_tokens' => $writeCount,
            'public_routes_with_network_tokens' => $networkCount,
            'delete_recommended_now' => 0,
            'live_edxeix_submit_enabled' => false,
        ],
        'public_root_routes' => $publicRoutes,
        'ops_summary' => $opsSummary,
        'warnings' => $warnings,
        'final_blocks' => $finalBlocks,
        'next_safe_action' => 'No-delete cleanup only: keep global auth prepend active, move public utility links to Developer Archive, and consider later CLI/ops relocation for guarded public-root endpoints.',
        'finished_at' => date('c'),
    ];
}

function gov_pra_print_text(array $report): void
{
    $summary = is_array($report['summary'] ?? null) ? $report['summary'] : [];
    $auth = is_array($report['auth_posture'] ?? null) ? $report['auth_posture'] : [];
    echo 'Public route exposure audit ' . GOV_PUBLIC_ROUTE_EXPOSURE_AUDIT_VERSION . PHP_EOL;
    echo 'Safety: ' . GOV_PUBLIC_ROUTE_EXPOSURE_AUDIT_SAFETY . PHP_EOL;
    echo 'OK: ' . (!empty($report['ok']) ? 'yes' : 'no') . PHP_EOL;
    echo 'Public root: ' . (string)($report['public_root'] ?? '') . PHP_EOL;
    echo 'Auth posture: ' . (!empty($auth['ok']) ? 'ok' : 'blocked') . PHP_EOL;
    echo 'Public PHP routes: ' . (string)($summary['public_root_php_count'] ?? 0) . PHP_EOL;
    echo 'Ops PHP routes: ' . (string)($summary['ops_php_count'] ?? 0) . PHP_EOL;
    echo 'Guarded public utilities: ' . (string)($summary['guarded_public_utility_count'] ?? 0) . PHP_EOL;
    echo 'Routes with write tokens: ' . (string)($summary['public_routes_with_write_tokens'] ?? 0) . PHP_EOL;
    echo 'Routes with network tokens: ' . (string)($summary['public_routes_with_network_tokens'] ?? 0) . PHP_EOL;

    $routes = is_array($report['public_root_routes'] ?? null) ? $report['public_root_routes'] : [];
    if ($routes) {
        echo PHP_EOL . 'Public root routes:' . PHP_EOL;
        foreach ($routes as $route) {
            echo '  - ' . (string)($route['route'] ?? '') . ' | ' . (string)($route['classification'] ?? '') . ' | ' . (string)($route['risk'] ?? '') . PHP_EOL;
        }
    }

    $warnings = is_array($report['warnings'] ?? null) ? $report['warnings'] : [];
    if ($warnings) {
        echo PHP_EOL . 'Warnings:' . PHP_EOL;
        foreach ($warnings as $warning) {
            echo '  - ' . (string)$warning . PHP_EOL;
        }
    }

    $blocks = is_array($report['final_blocks'] ?? null) ? $report['final_blocks'] : [];
    if ($blocks) {
        echo PHP_EOL . 'Final blocks:' . PHP_EOL;
        foreach ($blocks as $block) {
            echo '  - ' . (string)$block . PHP_EOL;
        }
    }
    echo PHP_EOL . 'Next safe action: ' . (string)($report['next_safe_action'] ?? '') . PHP_EOL;
}

function gov_pra_main(array $argv): int
{
    $args = gov_pra_args($argv);
    $report = gov_pra_run();
    if (isset($args['json'])) {
        echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    } else {
        gov_pra_print_text($report);
    }
    return !empty($report['ok']) ? 0 : 1;
}

if (PHP_SAPI === 'cli' && realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === realpath(__FILE__)) {
    exit(gov_pra_main($argv));
}
