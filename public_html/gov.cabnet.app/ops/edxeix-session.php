<?php
/**
 * gov.cabnet.app — EDXEIX Session / Submit URL Readiness + Guarded Server-Side Save Form
 *
 * Guarded helper for final live-submit preparation.
 * GET is read-only. POST can save EDXEIX session/config values server-side only.
 * This page never prints cookies, CSRF tokens, or secrets and never calls EDXEIX.
 */

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Robots-Tag: noindex, nofollow', true);

require_once '/home/cabnet/gov.cabnet.app_app/lib/edxeix_live_submit_gate.php';

function es_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function es_badge(string $text, string $type = 'neutral'): string
{
    return '<span class="badge badge-' . es_h($type) . '">' . es_h($text) . '</span>';
}

function es_bool_badge(bool $value, string $yes = 'ready', string $no = 'missing'): string
{
    return $value ? es_badge($yes, 'good') : es_badge($no, 'bad');
}

function es_warn_badge(bool $value, string $yes = 'ready', string $no = 'attention'): string
{
    return $value ? es_badge($yes, 'good') : es_badge($no, 'warn');
}

function es_json_response(array $payload): void
{
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('X-Robots-Tag: noindex, nofollow', true);
    }
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit;
}

function es_request_value(string $key, string $default = '', int $maxLength = 100000): string
{
    $value = $_POST[$key] ?? $default;
    $value = is_scalar($value) ? trim((string)$value) : $default;
    if ($maxLength > 0 && strlen($value) > $maxLength) {
        $value = substr($value, 0, $maxLength);
    }
    return $value;
}

function es_secret_looks_placeholder(string $value): bool
{
    if (function_exists('gov_live_secret_looks_placeholder')) {
        return gov_live_secret_looks_placeholder($value);
    }

    $v = strtoupper(trim($value));
    if ($v === '') {
        return false;
    }
    $markers = [
        'PASTE', 'REPLACE', 'EXAMPLE', 'DUMMY', 'DEMO', 'TODO', 'SERVER_ONLY',
        'SERVER-SIDE', 'SERVER_SIDE', 'DO_NOT_COMMIT', 'COOKIE_HEADER',
        'CSRF_TOKEN', 'PLACEHOLDER', 'YYYY-MM-DD', 'HH:MM:SS', 'YOUR_',
        'CHANGE_ME', 'SAMPLE', 'TEMPLATE', 'NOT_A_REAL', 'FAKE',
    ];
    foreach ($markers as $marker) {
        if (strpos($v, $marker) !== false) {
            return true;
        }
    }
    return false;
}

function es_read_session_details(string $file): array
{
    $details = [
        'file_configured' => $file !== '',
        'file_exists' => false,
        'file_readable' => false,
        'json_valid' => false,
        'cookie_raw_present' => false,
        'csrf_raw_present' => false,
        'cookie_present' => false,
        'csrf_present' => false,
        'cookie_placeholder_detected' => false,
        'csrf_placeholder_detected' => false,
        'timestamp_placeholder_detected' => false,
        'placeholder_detected' => false,
        'cookie_length' => 0,
        'csrf_length' => 0,
        'updated_at' => null,
        'saved_at' => null,
        'age_minutes' => null,
        'ready' => false,
        'error' => null,
    ];

    if ($file === '') {
        $details['error'] = 'session_file_not_configured';
        return $details;
    }

    $details['file_exists'] = is_file($file);
    $details['file_readable'] = $details['file_exists'] && is_readable($file);

    if (!$details['file_readable']) {
        $details['error'] = $details['file_exists'] ? 'session_file_not_readable' : 'session_file_missing';
        return $details;
    }

    $raw = file_get_contents($file);
    $decoded = json_decode((string)$raw, true);
    if (!is_array($decoded)) {
        $details['error'] = 'session_json_invalid';
        return $details;
    }

    $cookie = trim((string)($decoded['cookie_header'] ?? ''));
    $csrf = trim((string)($decoded['csrf_token'] ?? ''));
    $updatedAt = $decoded['updated_at'] ?? $decoded['saved_at'] ?? null;

    $cookiePlaceholder = es_secret_looks_placeholder($cookie);
    $csrfPlaceholder = es_secret_looks_placeholder($csrf);
    $timestampPlaceholder = is_string($updatedAt) && es_secret_looks_placeholder($updatedAt);

    $details['json_valid'] = true;
    $details['cookie_raw_present'] = $cookie !== '';
    $details['csrf_raw_present'] = $csrf !== '';
    $details['cookie_placeholder_detected'] = $cookiePlaceholder;
    $details['csrf_placeholder_detected'] = $csrfPlaceholder;
    $details['timestamp_placeholder_detected'] = $timestampPlaceholder;
    $details['placeholder_detected'] = $cookiePlaceholder || $csrfPlaceholder || $timestampPlaceholder;
    $details['cookie_present'] = $cookie !== '' && !$cookiePlaceholder;
    $details['csrf_present'] = $csrf !== '' && !$csrfPlaceholder;
    $details['cookie_length'] = strlen($cookie);
    $details['csrf_length'] = strlen($csrf);
    $details['updated_at'] = $decoded['updated_at'] ?? null;
    $details['saved_at'] = $decoded['saved_at'] ?? null;

    if (is_string($updatedAt) && $updatedAt !== '') {
        $ts = strtotime($updatedAt);
        if ($ts !== false) {
            $details['age_minutes'] = max(0, (int)floor((time() - $ts) / 60));
        }
    }

    $details['ready'] = $details['json_valid'] && $details['cookie_present'] && $details['csrf_present'] && !$details['placeholder_detected'];
    if ($details['placeholder_detected']) {
        $details['error'] = 'placeholder_session_values_detected';
    }
    return $details;
}

function es_public_config_state(array $config): array
{
    $sessionFile = (string)($config['edxeix_session_file'] ?? '/home/cabnet/gov.cabnet.app_app/storage/runtime/edxeix_session.json');
    $submitUrl = trim((string)($config['edxeix_submit_url'] ?? ''));
    return [
        'config_file' => gov_live_config_path(),
        'config_file_exists' => is_file(gov_live_config_path()),
        'config_file_readable' => is_readable(gov_live_config_path()),
        'config_file_writable' => is_file(gov_live_config_path()) ? is_writable(gov_live_config_path()) : is_writable(dirname(gov_live_config_path())),
        'live_submit_enabled' => !empty($config['live_submit_enabled']),
        'http_submit_enabled' => !empty($config['http_submit_enabled']),
        'edxeix_submit_url_configured' => $submitUrl !== '',
        'edxeix_submit_url_host' => $submitUrl !== '' ? (parse_url($submitUrl, PHP_URL_HOST) ?: '') : '',
        'edxeix_form_method' => (string)($config['edxeix_form_method'] ?? 'POST'),
        'edxeix_session_file' => $sessionFile,
        'confirmation_phrase_required' => !empty($config['require_confirmation_phrase']),
        'allowed_booking_id' => $config['allowed_booking_id'] ?? null,
        'allowed_order_reference' => $config['allowed_order_reference'] ?? null,
    ];
}

function es_overall_ready(array $state, array $session): bool
{
    return !empty($state['config_file_exists'])
        && !empty($state['config_file_readable'])
        && !empty($state['edxeix_submit_url_configured'])
        && !empty($session['ready']);
}

function es_backup_file_if_exists(string $file): ?string
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

function es_atomic_write(string $file, string $content, int $mode): void
{
    $dir = dirname($file);
    if (!is_dir($dir) && !mkdir($dir, 0750, true)) {
        throw new RuntimeException('Could not create directory for server-side file.');
    }
    if (!is_writable($dir)) {
        throw new RuntimeException('Target directory is not writable by PHP.');
    }
    $tmp = $file . '.tmp.' . bin2hex(random_bytes(6));
    if (file_put_contents($tmp, $content, LOCK_EX) === false) {
        throw new RuntimeException('Could not write temporary server-side file.');
    }
    chmod($tmp, $mode);
    if (!rename($tmp, $file)) {
        @unlink($tmp);
        throw new RuntimeException('Could not move temporary file into place.');
    }
    chmod($file, $mode);
}

function es_validate_edxeix_submit_url(string $url): string
{
    $url = trim($url);
    if ($url === '') {
        return '';
    }
    if (strlen($url) > 1000) {
        throw new RuntimeException('EDXEIX submit URL is too long.');
    }
    $parts = parse_url($url);
    if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
        throw new RuntimeException('EDXEIX submit URL is not a valid absolute URL.');
    }
    if (strtolower((string)$parts['scheme']) !== 'https') {
        throw new RuntimeException('EDXEIX submit URL must use HTTPS.');
    }
    $host = strtolower((string)$parts['host']);
    if ($host !== 'edxeix.yme.gov.gr') {
        throw new RuntimeException('EDXEIX submit URL host must be edxeix.yme.gov.gr.');
    }
    return $url;
}

function es_save_live_config(array $currentConfig, string $submitUrl, string $method): array
{
    $file = gov_live_config_path();
    $config = array_replace_recursive(function_exists('gov_live_default_config') ? gov_live_default_config() : [], $currentConfig);
    if ($submitUrl !== '') {
        $config['edxeix_submit_url'] = $submitUrl;
    }
    $config['edxeix_form_method'] = strtoupper($method) === 'GET' ? 'GET' : 'POST';

    // Hard safety: this form prepares prerequisites only. It must never enable live flags.
    $config['live_submit_enabled'] = false;
    $config['http_submit_enabled'] = false;
    $config['require_post'] = true;
    $config['require_confirmation_phrase'] = true;
    $config['require_real_bolt_source'] = true;
    $config['require_future_guard'] = true;
    $config['require_no_lab_or_test_flags'] = true;
    $config['require_no_duplicate_success'] = true;
    $config['write_audit_rows'] = true;
    $config['note'] = 'Real config is server-only. Saved by guarded ops form. Do not commit this file.';

    es_backup_file_if_exists($file);
    $content = "<?php\n/**\n * gov.cabnet.app — server-only live submit config.\n * DO NOT COMMIT. DO NOT SHARE SECRETS.\n * Saved by guarded /ops/edxeix-session.php form.\n */\n\nreturn " . var_export($config, true) . ";\n";
    es_atomic_write($file, $content, 0640);

    return $config;
}

function es_save_session_file(string $file, string $cookie, string $csrf): void
{
    $cookie = trim($cookie);
    $csrf = trim($csrf);
    if ($cookie === '' && $csrf === '') {
        return;
    }
    if ($cookie === '' || $csrf === '') {
        throw new RuntimeException('Both cookie_header and csrf_token are required when updating the EDXEIX session.');
    }
    if (strlen($cookie) < 20) {
        throw new RuntimeException('Cookie header looks too short.');
    }
    if (strlen($csrf) < 8) {
        throw new RuntimeException('CSRF token looks too short.');
    }
    if (es_secret_looks_placeholder($cookie) || es_secret_looks_placeholder($csrf)) {
        throw new RuntimeException('Placeholder/example cookie or CSRF value detected. Real EDXEIX values were not saved.');
    }

    es_backup_file_if_exists($file);
    $payload = [
        'cookie_header' => $cookie,
        'csrf_token' => $csrf,
        'saved_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s'),
        'source' => 'guarded_ops_web_form',
        'note' => 'Server-only runtime session. Do not commit. Do not share.',
    ];
    es_atomic_write($file, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL, 0600);
}

function es_handle_post(array $config, array $configState): array
{
    $result = [
        'ok' => false,
        'saved_config' => false,
        'saved_session' => false,
        'message' => '',
        'errors' => [],
    ];

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        return $result;
    }

    $phrase = es_request_value('confirm_phrase', '', 200);
    $expected = 'SAVE EDXEIX SESSION SERVER SIDE';
    if ($phrase !== $expected) {
        throw new RuntimeException('Confirmation phrase mismatch. Nothing was saved.');
    }

    $submitUrlRaw = es_request_value('edxeix_submit_url', '', 1000);
    $method = es_request_value('edxeix_form_method', 'POST', 10);
    $cookie = es_request_value('cookie_header', '', 20000);
    $csrf = es_request_value('csrf_token', '', 4000);

    $submitUrl = es_validate_edxeix_submit_url($submitUrlRaw);
    if ($submitUrl !== '') {
        $config = es_save_live_config($config, $submitUrl, $method);
        $result['saved_config'] = true;
    }

    if ($cookie !== '' || $csrf !== '') {
        es_save_session_file((string)$configState['edxeix_session_file'], $cookie, $csrf);
        $result['saved_session'] = true;
    }

    if (!$result['saved_config'] && !$result['saved_session']) {
        throw new RuntimeException('Nothing was provided to save. Paste a submit URL and/or both session values.');
    }

    $result['ok'] = true;
    $result['message'] = 'Server-side EDXEIX preparation values were saved. Live flags remain disabled.';
    return $result;
}

$error = null;
$postResult = null;
$config = [];
$configState = [];
$sessionDetails = [];
$overallReady = false;

try {
    $bridgeConfig = gov_bridge_load_config();
    if (!empty($bridgeConfig['app']['timezone'])) {
        date_default_timezone_set((string)$bridgeConfig['app']['timezone']);
    }
    $config = gov_live_load_config();
    $configState = es_public_config_state($config);

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $postResult = es_handle_post($config, $configState);
        $config = gov_live_load_config();
        $configState = es_public_config_state($config);
    }

    $sessionDetails = es_read_session_details((string)$configState['edxeix_session_file']);
    $overallReady = es_overall_ready($configState, $sessionDetails);
} catch (Throwable $e) {
    $error = $e->getMessage();
    if (!$config) {
        $config = gov_live_load_config();
        $configState = es_public_config_state($config);
        $sessionDetails = es_read_session_details((string)$configState['edxeix_session_file']);
        $overallReady = es_overall_ready($configState, $sessionDetails);
    }
}

if (($_GET['format'] ?? '') === 'json') {
    es_json_response([
        'ok' => $error === null,
        'script' => 'ops/edxeix-session.php',
        'generated_at' => date('Y-m-d H:i:s'),
        'read_only_when_get' => $_SERVER['REQUEST_METHOD'] !== 'POST',
        'writes_server_only_files_on_post' => $_SERVER['REQUEST_METHOD'] === 'POST',
        'calls_bolt' => false,
        'calls_edxeix' => false,
        'writes_database' => false,
        'prints_secrets' => false,
        'overall_ready_for_final_live_patch_prerequisites' => $overallReady,
        'config_state' => $configState,
        'session_state' => $sessionDetails,
        'post_result' => $postResult,
        'remaining_notes' => [
            'This page never displays cookie or CSRF values.',
            'POST can save submit URL/cookie/CSRF to server-only files for authorized operators.',
            'Live EDXEIX HTTP transport is still blocked in the current live-submit gate.',
            'A real future Bolt candidate is still required for the first actual live submission test.',
        ],
        'error' => $error,
    ]);
}

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>EDXEIX Session Readiness | gov.cabnet.app</title>
    <style>
        :root { --bg:#f3f6fb; --panel:#fff; --ink:#07152f; --muted:#41577a; --line:#d7e1ef; --nav:#081225; --blue:#2563eb; --green:#07875a; --orange:#b85c00; --red:#b42318; --slate:#334155; --soft:#f8fbff; }
        *{box-sizing:border-box} body{margin:0;background:var(--bg);color:var(--ink);font-family:Arial,Helvetica,sans-serif}.nav{background:var(--nav);color:#fff;min-height:56px;display:flex;align-items:center;gap:18px;padding:0 26px;position:sticky;top:0;z-index:5;overflow:auto}.nav strong{white-space:nowrap}.nav a{color:#fff;text-decoration:none;font-size:15px;white-space:nowrap;opacity:.92}.nav a:hover{opacity:1;text-decoration:underline}.wrap{width:min(1480px,calc(100% - 48px));margin:26px auto 60px}.card{background:var(--panel);border:1px solid var(--line);border-radius:14px;padding:18px;margin-bottom:18px;box-shadow:0 10px 26px rgba(8,18,37,.04)}h1{font-size:34px;margin:0 0 12px}h2{font-size:23px;margin:0 0 14px}h3{margin:0 0 8px}p{color:var(--muted);line-height:1.45}.hero{border-left:7px solid var(--orange)}.hero.good{border-left-color:var(--green)}.hero.bad{border-left-color:var(--red)}.safe{border-left:7px solid var(--green)}.warn{border-left:7px solid var(--orange)}.danger{border-left:7px solid var(--red)}.grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-top:14px}.metric{border:1px solid var(--line);border-radius:10px;padding:14px;background:var(--soft);min-height:82px}.metric strong{display:block;font-size:28px;line-height:1.05;word-break:break-word}.metric span{color:var(--muted);font-size:14px}.badge{display:inline-block;padding:5px 9px;border-radius:999px;font-size:12px;font-weight:700;margin:1px 3px 1px 0;white-space:nowrap}.badge-good{background:#dcfce7;color:#166534}.badge-warn{background:#fff7ed;color:#b45309}.badge-bad{background:#fee2e2;color:#991b1b}.badge-neutral{background:#eaf1ff;color:#1e40af}.actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:12px}.btn,button{display:inline-block;padding:10px 14px;border-radius:8px;color:#fff;text-decoration:none;font-weight:700;background:var(--blue);font-size:14px;border:0;cursor:pointer}.btn.dark{background:var(--slate)}.btn.orange{background:var(--orange)}button.green{background:var(--green)}.table-wrap{overflow:auto;border:1px solid var(--line);border-radius:10px}table{width:100%;border-collapse:collapse;min-width:850px}th,td{text-align:left;padding:10px 12px;border-bottom:1px solid var(--line);vertical-align:top;font-size:14px}th{background:#f8fafc;font-size:12px;text-transform:uppercase;letter-spacing:.02em}.two{display:grid;grid-template-columns:1fr 1fr;gap:18px}.list{margin:0;padding-left:18px;color:var(--muted)}.list li{margin:7px 0}.small{font-size:13px;color:var(--muted)}.badline{color:#991b1b}.goodline{color:#166534}.warnline{color:#b45309}code{background:#eef2ff;padding:2px 5px;border-radius:5px}pre{background:#0b1020;color:#d7e3ff;padding:14px;border-radius:12px;overflow:auto}label{display:block;font-weight:700;margin:12px 0 5px}input,textarea,select{width:100%;padding:10px 12px;border:1px solid var(--line);border-radius:8px;font-size:14px;font-family:Arial,Helvetica,sans-serif}textarea{min-height:110px;resize:vertical}.field-note{font-size:12px;color:var(--muted);margin-top:4px}.callout{border-radius:12px;padding:12px 14px;margin:12px 0}.callout.good{background:#ecfdf3;border:1px solid #bbf7d0}.callout.bad{background:#fef3f2;border:1px solid #fecaca}.callout.warn{background:#fff7ed;border:1px solid #fed7aa}@media(max-width:1100px){.grid{grid-template-columns:repeat(2,minmax(0,1fr))}.two{grid-template-columns:1fr}}@media(max-width:720px){.grid{grid-template-columns:1fr}.wrap{width:calc(100% - 24px);margin-top:14px}.nav{padding:0 14px}}
    </style>
</head>
<body>
<nav class="nav">
    <strong>GC gov.cabnet.app</strong>
    <a href="/ops/index.php">Operations Console</a>
    <a href="/ops/readiness.php">Readiness</a>
    <a href="/ops/future-test.php">Future Test</a>
    <a href="/ops/mappings.php">Mappings</a>
    <a href="/ops/live-submit.php">Live Submit Gate</a>
    <a href="/ops/edxeix-session.php">EDXEIX Session</a>
    <a href="/ops/help.php">Help</a>
</nav>

<main class="wrap">
    <section class="card hero <?= $error ? 'bad' : ($overallReady ? 'good' : '') ?>">
        <h1>EDXEIX Session / Submit URL Readiness</h1>
        <p>Helper for production preparation. GET is diagnostic only. The guarded form can save EDXEIX session/config values server-side for authorized operators. It never prints secrets and never calls EDXEIX.</p>
        <div>
            <?= es_badge('NO SECRET OUTPUT', 'good') ?>
            <?= es_badge('NO EDXEIX CALL', 'good') ?>
            <?= es_badge('POST SAVES SERVER-ONLY FILES', 'warn') ?>
            <?= $overallReady ? es_badge('SESSION PREREQS READY', 'good') : es_badge('SESSION PREREQS NEED ATTENTION', 'warn') ?>
        </div>
        <?php if ($error): ?><p class="badline"><strong>Error:</strong> <?= es_h($error) ?></p><?php endif; ?>
        <?php if ($postResult && !empty($postResult['ok'])): ?>
            <div class="callout good"><strong>Saved.</strong> <?= es_h($postResult['message']) ?> No secret values are displayed back.</div>
        <?php endif; ?>
        <div class="actions">
            <a class="btn" href="/ops/edxeix-session.php?format=json">Open Session JSON</a>
            <a class="btn dark" href="/ops/live-submit.php">Open Live Submit Gate</a>
            <a class="btn orange" href="/ops/future-test.php">Open Future Test</a>
        </div>
        <?php if (!empty($sessionDetails['placeholder_detected'])): ?>
            <p class="badline"><strong>Placeholder values detected:</strong> the session file was likely copied from the example template. This is safe, but it is not a real EDXEIX browser session yet.</p>
        <?php endif; ?>
        <div class="grid">
            <div class="metric"><strong><?= !empty($configState['config_file_exists']) ? 'yes' : 'no' ?></strong><span>Live config file exists</span></div>
            <div class="metric"><strong><?= !empty($sessionDetails['ready']) ? 'yes' : 'no' ?></strong><span>Session cookie/CSRF ready</span></div>
            <div class="metric"><strong><?= !empty($configState['edxeix_submit_url_configured']) ? 'yes' : 'no' ?></strong><span>Submit URL configured</span></div>
            <div class="metric"><strong><?= isset($sessionDetails['age_minutes']) && $sessionDetails['age_minutes'] !== null ? es_h($sessionDetails['age_minutes']) : 'n/a' ?></strong><span>Session age, minutes</span></div>
        </div>
    </section>

    <section class="card warn">
        <h2>Guarded Server-Side Save Form</h2>
        <p>This form is available because only authorized operators use the app. It saves values directly into server-only files and never displays them back. It does not enable live submission.</p>
        <form method="post" autocomplete="off" spellcheck="false">
            <label for="edxeix_submit_url">EDXEIX submit URL</label>
            <input type="url" id="edxeix_submit_url" name="edxeix_submit_url" placeholder="https://edxeix.yme.gov.gr/dashboard/lease-agreement" autocomplete="off">
            <div class="field-note">Optional if already configured. Must be HTTPS and host must be edxeix.yme.gov.gr.</div>

            <label for="edxeix_form_method">Form method</label>
            <select id="edxeix_form_method" name="edxeix_form_method">
                <option value="POST" selected>POST</option>
                <option value="GET">GET</option>
            </select>

            <label for="cookie_header">Cookie header</label>
            <textarea id="cookie_header" name="cookie_header" placeholder="Paste the full EDXEIX Cookie request header here. It will be saved server-side and never printed back." autocomplete="off"></textarea>
            <div class="field-note">Optional only if updating URL alone. If updating session, paste both cookie and CSRF token.</div>

            <label for="csrf_token">CSRF token</label>
            <input type="password" id="csrf_token" name="csrf_token" placeholder="Paste EDXEIX CSRF token here" autocomplete="new-password">

            <label for="confirm_phrase">Confirmation phrase</label>
            <input type="text" id="confirm_phrase" name="confirm_phrase" placeholder="SAVE EDXEIX SESSION SERVER SIDE" autocomplete="off" required>
            <div class="field-note">Type exactly: <code>SAVE EDXEIX SESSION SERVER SIDE</code></div>

            <div class="actions">
                <button class="green" type="submit">Save Server-Side Values</button>
            </div>
            <p class="small"><strong>Safety:</strong> saving these values does not enable live submission. The live and HTTP flags remain forced disabled by this form.</p>
        </form>
    </section>

    <section class="two">
        <div class="card safe">
            <h2>Server-only Config State</h2>
            <div class="table-wrap"><table>
                <thead><tr><th>Check</th><th>Status</th><th>Detail</th></tr></thead>
                <tbody>
                    <tr><td><strong>live_submit.php exists</strong></td><td><?= es_bool_badge(!empty($configState['config_file_exists']), 'yes', 'no') ?></td><td><code><?= es_h($configState['config_file'] ?? '') ?></code></td></tr>
                    <tr><td><strong>live_submit.php readable</strong></td><td><?= es_bool_badge(!empty($configState['config_file_readable']), 'yes', 'no') ?></td><td>Must be readable by the cabnet PHP runtime.</td></tr>
                    <tr><td><strong>live_submit.php writable</strong></td><td><?= es_bool_badge(!empty($configState['config_file_writable']), 'yes', 'no') ?></td><td>Required only for the web save form.</td></tr>
                    <tr><td><strong>EDXEIX submit URL</strong></td><td><?= es_bool_badge(!empty($configState['edxeix_submit_url_configured']), 'configured', 'missing') ?></td><td>Host: <?= es_h($configState['edxeix_submit_url_host'] ?: 'not configured') ?></td></tr>
                    <tr><td><strong>Form method</strong></td><td><?= es_badge((string)($configState['edxeix_form_method'] ?? 'POST'), 'neutral') ?></td><td>Expected method is normally POST.</td></tr>
                    <tr><td><strong>Live flag</strong></td><td><?= !empty($configState['live_submit_enabled']) ? es_badge('enabled', 'warn') : es_badge('disabled', 'good') ?></td><td>Forced disabled by this form.</td></tr>
                    <tr><td><strong>HTTP flag</strong></td><td><?= !empty($configState['http_submit_enabled']) ? es_badge('enabled', 'warn') : es_badge('disabled', 'good') ?></td><td>Forced disabled by this form.</td></tr>
                </tbody>
            </table></div>
        </div>

        <div class="card safe">
            <h2>EDXEIX Session File State</h2>
            <div class="table-wrap"><table>
                <thead><tr><th>Check</th><th>Status</th><th>Detail</th></tr></thead>
                <tbody>
                    <tr><td><strong>Session path configured</strong></td><td><?= es_bool_badge(!empty($sessionDetails['file_configured']), 'yes', 'no') ?></td><td><code><?= es_h($configState['edxeix_session_file'] ?? '') ?></code></td></tr>
                    <tr><td><strong>Session file exists</strong></td><td><?= es_bool_badge(!empty($sessionDetails['file_exists']), 'yes', 'no') ?></td><td>Runtime-only file, not committed.</td></tr>
                    <tr><td><strong>Session file readable</strong></td><td><?= es_bool_badge(!empty($sessionDetails['file_readable']), 'yes', 'no') ?></td><td>Must be readable by PHP.</td></tr>
                    <tr><td><strong>JSON valid</strong></td><td><?= es_bool_badge(!empty($sessionDetails['json_valid']), 'yes', 'no') ?></td><td>Expected JSON object with cookie_header and csrf_token keys.</td></tr>
                    <tr><td><strong>Cookie header present</strong></td><td><?= es_bool_badge(!empty($sessionDetails['cookie_raw_present']), 'yes', 'no') ?></td><td>Length only: <?= es_h($sessionDetails['cookie_length'] ?? 0) ?> chars. Placeholder values do not count as ready.</td></tr>
                    <tr><td><strong>CSRF token present</strong></td><td><?= es_bool_badge(!empty($sessionDetails['csrf_raw_present']), 'yes', 'no') ?></td><td>Length only: <?= es_h($sessionDetails['csrf_length'] ?? 0) ?> chars. Placeholder values do not count as ready.</td></tr>
                    <tr><td><strong>Placeholder/example values</strong></td><td><?= !empty($sessionDetails['placeholder_detected']) ? es_badge('detected', 'bad') : es_badge('not detected', 'good') ?></td><td><?= !empty($sessionDetails['placeholder_detected']) ? 'Replace template values with real server-side EDXEIX session values before the final live phase.' : 'No known placeholder markers detected.' ?></td></tr>
                    <tr><td><strong>Updated at</strong></td><td><?= es_warn_badge(!empty($sessionDetails['updated_at']) || !empty($sessionDetails['saved_at']), 'recorded', 'unknown') ?></td><td><?= es_h($sessionDetails['updated_at'] ?? $sessionDetails['saved_at'] ?? 'not recorded') ?></td></tr>
                </tbody>
            </table></div>
        </div>
    </section>

    <section class="card warn">
        <h2>How to collect these values</h2>
        <ol class="list">
            <li>Log in to EDXEIX in the browser and open the lease agreement creation form.</li>
            <li>Use browser developer tools to inspect the form action URL and CSRF token field.</li>
            <li>Use the Network tab to copy the relevant Cookie request header for the authenticated EDXEIX session.</li>
            <li>Paste values into the guarded form above. Do not send them in chat, screenshots, GitHub, or email.</li>
            <li>Reload this page and confirm submit URL + cookie + CSRF are ready.</li>
        </ol>
        <p class="small">Backups are created automatically before overwriting server-only config/session files.</p>
    </section>
</main>
</body>
</html>
