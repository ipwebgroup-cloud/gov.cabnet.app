<?php
/**
 * gov.cabnet.app — public PHP authentication prepend.
 *
 * Activated by public_html/gov.cabnet.app/.user.ini.
 * Protects /ops and public PHP utility endpoints after IP restrictions are removed.
 */

declare(strict_types=1);

if (PHP_SAPI === 'cli') {
    return;
}

if (defined('GOV_CABNET_AUTH_PREPEND_LOADED')) {
    return;
}
define('GOV_CABNET_AUTH_PREPEND_LOADED', true);

$path = parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
$path = is_string($path) ? $path : '';
$base = basename($path);

// Block direct browser access to internal auth helper files.
if ($base === '_auth_prepend.php' || $base === '_auth.php') {
    http_response_code(404);
    echo 'Not found.';
    exit;
}

// Login/logout must remain reachable without an existing session.
if ($path === '/ops/login.php' || $path === '/ops/logout.php') {
    return;
}

$bootstrap = dirname(__DIR__, 2) . '/gov.cabnet.app_app/src/bootstrap.php';
if (!is_file($bootstrap)) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Authentication bootstrap failed: private app bootstrap not found.';
    exit;
}

try {
    $ctx = require $bootstrap;
    if (!is_array($ctx) || !isset($ctx['db'], $ctx['config']) || !method_exists($ctx['db'], 'connection')) {
        throw new RuntimeException('Invalid private app context.');
    }

    $auth = new Bridge\Auth\OpsAuth($ctx['db']->connection(), [
        'session_name' => (string)$ctx['config']->get('ops_auth.session_name', 'gov_cabnet_ops_session'),
        'login_path' => (string)$ctx['config']->get('ops_auth.login_path', '/ops/login.php'),
        'after_login_path' => (string)$ctx['config']->get('ops_auth.after_login_path', '/ops/pre-ride-email-tool.php'),
    ]);

    // Optional machine access for cron/cURL jobs. Prefer header over query string.
    $expectedKey = (string)$ctx['config']->get('app.internal_api_key', '');
    $providedKey = (string)($_SERVER['HTTP_X_GOV_CABNET_KEY'] ?? '');
    if ($providedKey === '') {
        $providedKey = (string)($_GET['key'] ?? '');
    }
    if ($auth->isInternalKeyAllowed($providedKey, $expectedKey)) {
        define('GOV_CABNET_AUTH_VIA_INTERNAL_KEY', true);
        return;
    }

    $wantsJson = str_contains(strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? '')), 'application/json')
        || !str_starts_with($path, '/ops/');

    $auth->requireLogin($wantsJson ? 'json' : 'redirect');
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Authentication bootstrap failed.';
    if (($_SERVER['SERVER_NAME'] ?? '') === 'localhost') {
        echo "\n" . $e->getMessage();
    }
    exit;
}
