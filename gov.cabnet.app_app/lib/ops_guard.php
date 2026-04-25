<?php
/**
 * gov.cabnet.app — lightweight operations access guard.
 *
 * Intended use:
 * - Loaded automatically by public_html/gov.cabnet.app/.user.ini via auto_prepend_file.
 * - Protects /ops/*.php and public bolt_*.php diagnostics/workers.
 * - Reads server-only config from /home/cabnet/gov.cabnet.app_config/ops.php.
 *
 * Safety:
 * - No Composer/framework/session dependency.
 * - No database dependency.
 * - If config file is absent or enabled=false, the guard does nothing.
 * - Real token/cookie secrets must stay server-only and must never be committed.
 */

declare(strict_types=1);

if (defined('GOV_OPS_GUARD_LOADED')) {
    return;
}
define('GOV_OPS_GUARD_LOADED', true);

if (!function_exists('gov_ops_guard_default_config')) {
    function gov_ops_guard_default_config(): array
    {
        return [
            'enabled' => false,
            'allow_cli' => true,
            'allowed_ips' => [],
            'protected_path_prefixes' => ['/ops/'],
            'protected_script_regex' => [
                '#^/bolt_.*\.php$#i',
            ],
            'exempt_paths' => [],
            'token_param' => 'ops_token',
            'token_hash' => '',
            'token_header' => 'HTTP_X_GOV_OPS_TOKEN',
            'cookie_enabled' => true,
            'cookie_name' => 'gov_ops_auth',
            'cookie_ttl_seconds' => 28800,
            'cookie_secret' => '',
            'bind_cookie_to_ip' => false,
            'bind_cookie_to_user_agent' => true,
            'show_denied_ip' => true,
        ];
    }
}

if (!function_exists('gov_ops_guard_config')) {
    function gov_ops_guard_config(): array
    {
        static $config = null;
        if (is_array($config)) {
            return $config;
        }

        $config = gov_ops_guard_default_config();
        $file = '/home/cabnet/gov.cabnet.app_config/ops.php';
        if (is_file($file) && is_readable($file)) {
            $loaded = require $file;
            if (is_array($loaded)) {
                $config = array_replace_recursive($config, $loaded);
            }
        }

        return $config;
    }
}

if (!function_exists('gov_ops_guard_request_path')) {
    function gov_ops_guard_request_path(): string
    {
        $path = (string)parse_url($_SERVER['REQUEST_URI'] ?? ($_SERVER['SCRIPT_NAME'] ?? ''), PHP_URL_PATH);
        if ($path === '') {
            $path = (string)($_SERVER['SCRIPT_NAME'] ?? '');
        }
        $path = '/' . ltrim(str_replace('\\', '/', $path), '/');
        return preg_replace('#/+#', '/', $path) ?: '/';
    }
}

if (!function_exists('gov_ops_guard_is_protected')) {
    function gov_ops_guard_is_protected(array $config): bool
    {
        $path = gov_ops_guard_request_path();

        foreach ((array)($config['exempt_paths'] ?? []) as $exempt) {
            $exempt = '/' . ltrim((string)$exempt, '/');
            if ($path === $exempt || rtrim($path, '/') === rtrim($exempt, '/')) {
                return false;
            }
        }

        foreach ((array)($config['protected_path_prefixes'] ?? []) as $prefix) {
            $prefix = '/' . trim((string)$prefix, '/') . '/';
            if (strpos($path, $prefix) === 0) {
                return true;
            }
        }

        foreach ((array)($config['protected_script_regex'] ?? []) as $regex) {
            $regex = (string)$regex;
            if ($regex !== '' && @preg_match($regex, $path)) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('gov_ops_guard_client_ip')) {
    function gov_ops_guard_client_ip(): string
    {
        return (string)($_SERVER['REMOTE_ADDR'] ?? '');
    }
}

if (!function_exists('gov_ops_guard_ip_matches')) {
    function gov_ops_guard_ip_matches(string $clientIp, string $rule): bool
    {
        $clientIp = trim($clientIp);
        $rule = trim($rule);
        if ($clientIp === '' || $rule === '') {
            return false;
        }

        if ($clientIp === $rule) {
            return true;
        }

        // Basic IPv4 CIDR support, e.g. 203.0.113.0/24.
        if (strpos($rule, '/') !== false && filter_var($clientIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            [$subnet, $bits] = array_pad(explode('/', $rule, 2), 2, '');
            $bits = (int)$bits;
            if ($bits < 0 || $bits > 32 || !filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                return false;
            }
            $ipLong = ip2long($clientIp);
            $subnetLong = ip2long($subnet);
            if ($ipLong === false || $subnetLong === false) {
                return false;
            }
            $mask = $bits === 0 ? 0 : (-1 << (32 - $bits));
            return (($ipLong & $mask) === ($subnetLong & $mask));
        }

        return false;
    }
}

if (!function_exists('gov_ops_guard_ip_allowed')) {
    function gov_ops_guard_ip_allowed(array $config): bool
    {
        $clientIp = gov_ops_guard_client_ip();
        foreach ((array)($config['allowed_ips'] ?? []) as $rule) {
            if (gov_ops_guard_ip_matches($clientIp, (string)$rule)) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('gov_ops_guard_token_from_request')) {
    function gov_ops_guard_token_from_request(array $config): string
    {
        $param = (string)($config['token_param'] ?? 'ops_token');
        if ($param !== '') {
            $value = $_GET[$param] ?? $_POST[$param] ?? null;
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        $header = (string)($config['token_header'] ?? 'HTTP_X_GOV_OPS_TOKEN');
        if ($header !== '' && isset($_SERVER[$header]) && is_string($_SERVER[$header])) {
            return (string)$_SERVER[$header];
        }

        return '';
    }
}

if (!function_exists('gov_ops_guard_token_valid')) {
    function gov_ops_guard_token_valid(array $config, string $token): bool
    {
        $hash = trim((string)($config['token_hash'] ?? ''));
        if ($hash === '' || $token === '') {
            return false;
        }

        if (function_exists('password_verify') && password_verify($token, $hash)) {
            return true;
        }

        // Optional fallback for SHA-256 hashes if password_hash() cannot be used.
        if (preg_match('/^[a-f0-9]{64}$/i', $hash)) {
            return hash_equals(strtolower($hash), hash('sha256', $token));
        }

        return false;
    }
}

if (!function_exists('gov_ops_guard_cookie_signature')) {
    function gov_ops_guard_cookie_signature(array $config): string
    {
        $secret = (string)($config['cookie_secret'] ?? '');
        $tokenHash = (string)($config['token_hash'] ?? '');
        if ($secret === '' || $tokenHash === '') {
            return '';
        }

        $parts = ['gov_ops_guard_v1', $tokenHash];
        if (!empty($config['bind_cookie_to_ip'])) {
            $parts[] = gov_ops_guard_client_ip();
        }
        if (!empty($config['bind_cookie_to_user_agent'])) {
            $parts[] = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
        }

        return hash_hmac('sha256', implode('|', $parts), $secret);
    }
}

if (!function_exists('gov_ops_guard_cookie_valid')) {
    function gov_ops_guard_cookie_valid(array $config): bool
    {
        if (empty($config['cookie_enabled'])) {
            return false;
        }
        $name = (string)($config['cookie_name'] ?? 'gov_ops_auth');
        $expected = gov_ops_guard_cookie_signature($config);
        $actual = is_string($_COOKIE[$name] ?? null) ? (string)$_COOKIE[$name] : '';
        return $expected !== '' && $actual !== '' && hash_equals($expected, $actual);
    }
}

if (!function_exists('gov_ops_guard_set_cookie')) {
    function gov_ops_guard_set_cookie(array $config): void
    {
        if (headers_sent() || empty($config['cookie_enabled'])) {
            return;
        }
        $name = (string)($config['cookie_name'] ?? 'gov_ops_auth');
        $value = gov_ops_guard_cookie_signature($config);
        if ($value === '') {
            return;
        }
        $ttl = max(300, (int)($config['cookie_ttl_seconds'] ?? 28800));
        setcookie($name, $value, [
            'expires' => time() + $ttl,
            'path' => '/',
            'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        $_COOKIE[$name] = $value;
    }
}

if (!function_exists('gov_ops_guard_accepts_json')) {
    function gov_ops_guard_accepts_json(): bool
    {
        $path = gov_ops_guard_request_path();
        if (strpos($path, '/ops/') === 0) {
            return false;
        }
        $accept = strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? ''));
        return strpos($accept, 'application/json') !== false || preg_match('#^/bolt_.*\.php$#i', $path) === 1;
    }
}

if (!function_exists('gov_ops_guard_deny')) {
    function gov_ops_guard_deny(array $config): void
    {
        if (!headers_sent()) {
            http_response_code(403);
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('X-Robots-Tag: noindex, nofollow', true);
        }

        $ip = gov_ops_guard_client_ip();
        $path = gov_ops_guard_request_path();
        $showIp = !empty($config['show_denied_ip']);

        if (gov_ops_guard_accepts_json()) {
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8');
            }
            echo json_encode([
                'ok' => false,
                'error' => 'ops_access_denied',
                'message' => 'Access denied by gov.cabnet.app operations guard.',
                'path' => $path,
                'client_ip' => $showIp ? $ip : null,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
            exit;
        }

        if (!headers_sent()) {
            header('Content-Type: text/html; charset=utf-8');
        }
        $safePath = htmlspecialchars($path, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeIp = htmlspecialchars($ip, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Access denied | gov.cabnet.app</title><style>body{font-family:Arial,Helvetica,sans-serif;background:#f3f6fb;color:#07152f;margin:0;padding:40px}.card{max-width:760px;background:#fff;border:1px solid #d7e1ef;border-radius:14px;padding:24px;box-shadow:0 10px 28px rgba(8,18,37,.06)}code{background:#eef2ff;padding:2px 5px;border-radius:5px}</style></head><body><div class="card"><h1>Access denied</h1><p>This operations endpoint is protected by the gov.cabnet.app access guard.</p><p><strong>Path:</strong> <code>' . $safePath . '</code></p>';
        if ($showIp) {
            echo '<p><strong>Your detected IP:</strong> <code>' . $safeIp . '</code></p>';
        }
        echo '<p>Use an allowed IP address or a valid temporary ops token configured server-side.</p></div></body></html>';
        exit;
    }
}

if (!function_exists('gov_ops_guard_run')) {
    function gov_ops_guard_run(): void
    {
        $config = gov_ops_guard_config();

        if (empty($config['enabled'])) {
            return;
        }
        if (PHP_SAPI === 'cli' && !empty($config['allow_cli'])) {
            return;
        }
        if (!gov_ops_guard_is_protected($config)) {
            return;
        }

        if (gov_ops_guard_ip_allowed($config)) {
            return;
        }

        if (gov_ops_guard_cookie_valid($config)) {
            return;
        }

        $token = gov_ops_guard_token_from_request($config);
        if (gov_ops_guard_token_valid($config, $token)) {
            gov_ops_guard_set_cookie($config);
            return;
        }

        gov_ops_guard_deny($config);
    }
}

gov_ops_guard_run();
