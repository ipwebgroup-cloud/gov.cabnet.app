<?php
/**
 * gov.cabnet.app — EDXEIX Session Capture Endpoint
 *
 * Receives EDXEIX submit URL, Cookie header, and CSRF token from the private
 * Firefox extension and saves them to server-only files.
 *
 * Safety:
 * - POST only for writes.
 * - Requires exact confirmation phrase.
 * - Validates EDXEIX host and placeholder values.
 * - Creates backups before overwriting server-only files.
 * - Forces live_submit_enabled and http_submit_enabled to false.
 * - Never prints cookie or CSRF values.
 * - Does not call Bolt or EDXEIX.
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Robots-Tag: noindex, nofollow', true);
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once '/home/cabnet/gov.cabnet.app_app/lib/edxeix_live_submit_gate.php';

function esc_json(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit;
}

function esc_input(string $key, int $max = 20000): string
{
    $value = $_POST[$key] ?? '';
    if (!is_scalar($value)) {
        return '';
    }
    $value = trim((string)$value);
    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $max, 'UTF-8');
    }
    return substr($value, 0, $max);
}

function esc_secret_looks_placeholder(string $value): bool
{
    $v = strtoupper(trim($value));
    if ($v === '') {
        return true;
    }

    $markers = [
        'PASTE', 'REPLACE', 'EXAMPLE', 'DUMMY', 'DEMO', 'TODO',
        'SERVER_ONLY', 'DO_NOT_COMMIT', 'COOKIE_HEADER', 'CSRF_TOKEN',
        'PLACEHOLDER', 'YYYY-MM-DD', 'HH:MM:SS', 'YOUR_', 'INSERT_',
    ];
    foreach ($markers as $marker) {
        if (strpos($v, $marker) !== false) {
            return true;
        }
    }

    if (function_exists('gov_live_secret_looks_placeholder')) {
        return gov_live_secret_looks_placeholder($value);
    }

    return false;
}

function esc_validate_submit_url(string $url): array
{
    $url = trim($url);
    if ($url === '') {
        return [false, 'edxeix_submit_url_missing'];
    }

    $parts = parse_url($url);
    $scheme = strtolower((string)($parts['scheme'] ?? ''));
    $host = strtolower((string)($parts['host'] ?? ''));
    $path = (string)($parts['path'] ?? '');

    if ($scheme !== 'https') {
        return [false, 'edxeix_submit_url_must_use_https'];
    }
    if ($host !== 'edxeix.yme.gov.gr') {
        return [false, 'edxeix_submit_url_host_not_allowed'];
    }
    if (strpos($path, '/dashboard/lease-agreement') !== 0) {
        return [false, 'edxeix_submit_url_path_not_allowed'];
    }

    return [true, ''];
}

function esc_backup_file(string $file): ?string
{
    if (!is_file($file)) {
        return null;
    }
    $backup = $file . '.bak.' . date('YmdHis');
    if (!copy($file, $backup)) {
        throw new RuntimeException('Could not create backup for ' . basename($file));
    }
    return $backup;
}

function esc_write_php_config(string $file, array $config): void
{
    $dir = dirname($file);
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }

    $content = "<?php\n";
    $content .= "/**\n";
    $content .= " * gov.cabnet.app — server-only live submit config.\n";
    $content .= " * Updated by guarded EDXEIX session capture endpoint.\n";
    $content .= " * Do not commit this file.\n";
    $content .= " */\n\n";
    $content .= "return " . var_export($config, true) . ";\n";

    if (file_put_contents($file, $content, LOCK_EX) === false) {
        throw new RuntimeException('Could not write live_submit.php.');
    }
    @chmod($file, 0640);
}

function esc_write_session_json(string $file, array $session): void
{
    $dir = dirname($file);
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }

    $json = json_encode($session, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($json) || $json === '') {
        throw new RuntimeException('Could not encode session JSON.');
    }
    if (file_put_contents($file, $json . PHP_EOL, LOCK_EX) === false) {
        throw new RuntimeException('Could not write EDXEIX session file.');
    }
    @chmod($file, 0600);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    esc_json([
        'ok' => true,
        'script' => 'ops/edxeix-session-capture.php',
        'method' => $_SERVER['REQUEST_METHOD'],
        'read_only' => true,
        'calls_bolt' => false,
        'calls_edxeix' => false,
        'writes_database' => false,
        'prints_secrets' => false,
        'message' => 'POST EDXEIX submit_url, cookie_header, csrf_token, and confirm to save server-side prerequisites.',
    ]);
}

$startedAt = date('Y-m-d H:i:s');
$confirm = esc_input('confirm', 255);
$expectedConfirm = 'SAVE EDXEIX SESSION SERVER SIDE';
$submitUrl = esc_input('submit_url', 2000);
$cookieHeader = esc_input('cookie_header', 20000);
$csrfToken = esc_input('csrf_token', 4000);
$sourceUrl = esc_input('source_url', 2000);
$extensionVersion = esc_input('extension_version', 50);
$formMethod = strtoupper(esc_input('form_method', 20) ?: 'POST');

$errors = [];
if ($confirm !== $expectedConfirm) {
    $errors[] = 'confirmation_phrase_mismatch';
}

[$urlOk, $urlError] = esc_validate_submit_url($submitUrl);
if (!$urlOk) {
    $errors[] = $urlError;
}

if ($cookieHeader === '') {
    $errors[] = 'cookie_header_missing';
} elseif (esc_secret_looks_placeholder($cookieHeader)) {
    $errors[] = 'cookie_header_placeholder_detected';
} elseif (strpos($cookieHeader, '=') === false) {
    $errors[] = 'cookie_header_invalid_format';
}

if ($csrfToken === '') {
    $errors[] = 'csrf_token_missing';
} elseif (esc_secret_looks_placeholder($csrfToken)) {
    $errors[] = 'csrf_token_placeholder_detected';
} elseif (strlen($csrfToken) < 16) {
    $errors[] = 'csrf_token_too_short';
}

if (!in_array($formMethod, ['POST'], true)) {
    $errors[] = 'form_method_not_allowed';
}

if ($errors) {
    esc_json([
        'ok' => false,
        'saved' => false,
        'script' => 'ops/edxeix-session-capture.php',
        'generated_at' => $startedAt,
        'errors' => array_values(array_unique($errors)),
        'secret_lengths' => [
            'cookie_header_length' => strlen($cookieHeader),
            'csrf_token_length' => strlen($csrfToken),
        ],
        'prints_secrets' => false,
        'calls_edxeix' => false,
        'live_flags_forced_disabled' => true,
    ], 422);
}

try {
    $configFile = gov_live_config_path();
    $currentConfig = gov_live_load_config();
    $sessionFile = (string)($currentConfig['edxeix_session_file'] ?? '/home/cabnet/gov.cabnet.app_app/storage/runtime/edxeix_session.json');

    $configBackup = esc_backup_file($configFile);
    $sessionBackup = esc_backup_file($sessionFile);

    $newConfig = $currentConfig;
    $newConfig['live_submit_enabled'] = false;
    $newConfig['http_submit_enabled'] = false;
    $newConfig['require_post'] = true;
    $newConfig['require_confirmation_phrase'] = true;
    $newConfig['confirmation_phrase'] = $newConfig['confirmation_phrase'] ?? 'I UNDERSTAND SUBMIT LIVE TO EDXEIX';
    $newConfig['edxeix_submit_url'] = $submitUrl;
    $newConfig['edxeix_form_method'] = 'POST';
    $newConfig['edxeix_session_file'] = $sessionFile;
    $newConfig['write_audit_rows'] = true;
    $newConfig['last_session_capture_at'] = $startedAt;
    $newConfig['last_session_capture_source'] = 'firefox_extension';

    esc_write_php_config($configFile, $newConfig);

    esc_write_session_json($sessionFile, [
        'cookie_header' => $cookieHeader,
        'csrf_token' => $csrfToken,
        'saved_at' => $startedAt,
        'updated_at' => $startedAt,
        'source' => 'firefox_extension_capture',
        'source_url' => $sourceUrl,
        'extension_version' => $extensionVersion,
        'note' => 'Server-only runtime session. Do not commit this file.',
    ]);

    esc_json([
        'ok' => true,
        'saved' => true,
        'script' => 'ops/edxeix-session-capture.php',
        'generated_at' => $startedAt,
        'calls_bolt' => false,
        'calls_edxeix' => false,
        'writes_database' => false,
        'prints_secrets' => false,
        'live_flags_forced_disabled' => true,
        'config_file' => $configFile,
        'session_file' => $sessionFile,
        'config_backup_created' => $configBackup !== null,
        'session_backup_created' => $sessionBackup !== null,
        'submit_url_configured' => true,
        'submit_url_host' => parse_url($submitUrl, PHP_URL_HOST) ?: '',
        'session_ready_indicators' => [
            'cookie_header_present' => true,
            'csrf_token_present' => true,
            'cookie_header_length' => strlen($cookieHeader),
            'csrf_token_length' => strlen($csrfToken),
        ],
        'next_checks' => [
            '/ops/edxeix-session.php',
            '/ops/live-submit.php',
        ],
    ]);
} catch (Throwable $e) {
    esc_json([
        'ok' => false,
        'saved' => false,
        'script' => 'ops/edxeix-session-capture.php',
        'generated_at' => $startedAt,
        'error' => $e->getMessage(),
        'prints_secrets' => false,
        'calls_edxeix' => false,
        'live_flags_forced_disabled' => true,
    ], 500);
}
