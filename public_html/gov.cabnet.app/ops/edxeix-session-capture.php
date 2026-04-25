<?php
/**
 * gov.cabnet.app — EDXEIX Session Capture Endpoint
 *
 * Receives EDXEIX Cookie header and CSRF token from the private Firefox
 * extension and saves them to server-only files.
 *
 * Safety:
 * - POST only for writes.
 * - Intended to be protected by the existing /ops guard.
 * - Uses the fixed, known EDXEIX submit URL; operators do not type it.
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

const ESC_FIXED_EDXEIX_SUBMIT_URL = 'https://edxeix.yme.gov.gr/dashboard/lease-agreement';
const ESC_FIXED_FORM_METHOD = 'POST';

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

function esc_validate_fixed_submit_url(): array
{
    $parts = parse_url(ESC_FIXED_EDXEIX_SUBMIT_URL);
    $scheme = strtolower((string)($parts['scheme'] ?? ''));
    $host = strtolower((string)($parts['host'] ?? ''));
    $path = (string)($parts['path'] ?? '');

    if ($scheme !== 'https') {
        return [false, 'fixed_submit_url_must_use_https'];
    }
    if ($host !== 'edxeix.yme.gov.gr') {
        return [false, 'fixed_submit_url_host_not_allowed'];
    }
    if ($path !== '/dashboard/lease-agreement') {
        return [false, 'fixed_submit_url_path_not_allowed'];
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
    $content .= " * Updated by guarded EDXEIX Firefox session capture endpoint.\n";
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
        'confirmation_phrase_required' => false,
        'fixed_submit_url' => ESC_FIXED_EDXEIX_SUBMIT_URL,
        'message' => 'POST cookie_header and csrf_token from the private Firefox extension to save server-side EDXEIX prerequisites.',
    ]);
}

$startedAt = date('Y-m-d H:i:s');
$submitUrl = ESC_FIXED_EDXEIX_SUBMIT_URL;
$formMethod = ESC_FIXED_FORM_METHOD;
$cookieHeader = esc_input('cookie_header', 20000);
$csrfToken = esc_input('csrf_token', 4000);
$sourceUrl = esc_input('source_url', 2000);
$extensionVersion = esc_input('extension_version', 50);
$detectedFormAction = esc_input('detected_form_action', 2000);

$errors = [];
[$urlOk, $urlError] = esc_validate_fixed_submit_url();
if (!$urlOk) {
    $errors[] = $urlError;
}

if ($sourceUrl !== '') {
    $sourceParts = parse_url($sourceUrl);
    $sourceHost = strtolower((string)($sourceParts['host'] ?? ''));
    $sourcePath = (string)($sourceParts['path'] ?? '');
    if ($sourceHost !== 'edxeix.yme.gov.gr') {
        $errors[] = 'source_url_host_not_allowed';
    }
    if (strpos($sourcePath, '/dashboard/lease-agreement') !== 0) {
        $errors[] = 'source_url_path_not_allowed';
    }
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

if ($errors) {
    esc_json([
        'ok' => false,
        'saved' => false,
        'script' => 'ops/edxeix-session-capture.php',
        'generated_at' => $startedAt,
        'errors' => array_values(array_unique($errors)),
        'fixed_submit_url_used' => $submitUrl,
        'confirmation_phrase_required' => false,
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
    $newConfig['edxeix_form_method'] = $formMethod;
    $newConfig['edxeix_session_file'] = $sessionFile;
    $newConfig['write_audit_rows'] = true;
    $newConfig['last_session_capture_at'] = $startedAt;
    $newConfig['last_session_capture_source'] = 'firefox_extension_fixed_url_no_phrase';

    esc_write_php_config($configFile, $newConfig);

    esc_write_session_json($sessionFile, [
        'cookie_header' => $cookieHeader,
        'csrf_token' => $csrfToken,
        'saved_at' => $startedAt,
        'updated_at' => $startedAt,
        'source' => 'firefox_extension_capture_fixed_url_no_phrase',
        'source_url' => $sourceUrl,
        'detected_form_action' => $detectedFormAction,
        'fixed_submit_url_used' => $submitUrl,
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
        'confirmation_phrase_required' => false,
        'live_flags_forced_disabled' => true,
        'config_file' => $configFile,
        'session_file' => $sessionFile,
        'config_backup_created' => $configBackup !== null,
        'session_backup_created' => $sessionBackup !== null,
        'submit_url_configured' => true,
        'submit_url' => $submitUrl,
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
        'confirmation_phrase_required' => false,
        'live_flags_forced_disabled' => true,
    ], 500);
}
