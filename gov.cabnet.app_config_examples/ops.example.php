<?php
/**
 * gov.cabnet.app operations guard example config.
 *
 * Copy this file to:
 *   /home/cabnet/gov.cabnet.app_config/ops.php
 *
 * Then edit values server-side only. Do not commit the real ops.php file.
 */

return [
    'enabled' => true,

    // Allow CLI scripts so root/cPanel verification commands are not blocked.
    'allow_cli' => true,

    // Add your trusted public IP address(es). CIDR works for IPv4, e.g. 203.0.113.0/24.
    // Leave empty if you want token-only access.
    'allowed_ips' => [
        // 'REPLACE_WITH_YOUR_PUBLIC_IP',
    ],

    // Protect all /ops/*.php pages and public bolt_*.php diagnostic/worker endpoints.
    'protected_path_prefixes' => ['/ops/'],
    'protected_script_regex' => [
        '#^/bolt_.*\.php$#i',
    ],
    'exempt_paths' => [],

    // Optional token access. Generate a hash on the server:
    // php -r "echo password_hash('CHANGE_THIS_LONG_RANDOM_TOKEN', PASSWORD_DEFAULT), PHP_EOL;"
    // Then open /ops/readiness.php?ops_token=CHANGE_THIS_LONG_RANDOM_TOKEN once.
    'token_param' => 'ops_token',
    'token_header' => 'HTTP_X_GOV_OPS_TOKEN',
    'token_hash' => '',

    // Optional signed cookie after a valid token is supplied.
    // Generate a cookie secret with:
    // php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
    'cookie_enabled' => true,
    'cookie_name' => 'gov_ops_auth',
    'cookie_ttl_seconds' => 28800,
    'cookie_secret' => '',
    'bind_cookie_to_ip' => false,
    'bind_cookie_to_user_agent' => true,

    // Helpful while configuring; set false later if preferred.
    'show_denied_ip' => true,
];
