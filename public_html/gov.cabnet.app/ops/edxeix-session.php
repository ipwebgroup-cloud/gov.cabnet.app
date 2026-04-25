<?php
/**
 * gov.cabnet.app — EDXEIX Session / Submit URL Readiness
 *
 * Guarded, read-only helper for the final live-submit preparation path.
 * This page never displays cookies, CSRF tokens, or secrets and never calls EDXEIX.
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

    $cookiePlaceholder = function_exists('gov_live_secret_looks_placeholder') ? gov_live_secret_looks_placeholder($cookie) : false;
    $csrfPlaceholder = function_exists('gov_live_secret_looks_placeholder') ? gov_live_secret_looks_placeholder($csrf) : false;
    $timestampPlaceholder = is_string($updatedAt) && function_exists('gov_live_secret_looks_placeholder') ? gov_live_secret_looks_placeholder($updatedAt) : false;

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

$error = null;
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
    $sessionDetails = es_read_session_details((string)$configState['edxeix_session_file']);
    $overallReady = es_overall_ready($configState, $sessionDetails);
} catch (Throwable $e) {
    $error = $e->getMessage();
}

if (($_GET['format'] ?? '') === 'json') {
    es_json_response([
        'ok' => $error === null,
        'script' => 'ops/edxeix-session.php',
        'generated_at' => date('Y-m-d H:i:s'),
        'read_only' => true,
        'calls_bolt' => false,
        'calls_edxeix' => false,
        'writes_database' => false,
        'prints_secrets' => false,
        'overall_ready_for_final_live_patch_prerequisites' => $overallReady,
        'config_state' => $configState,
        'session_state' => $sessionDetails,
        'remaining_notes' => [
            'This page only checks server-side readiness and never displays cookie or CSRF values.',
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
        *{box-sizing:border-box} body{margin:0;background:var(--bg);color:var(--ink);font-family:Arial,Helvetica,sans-serif}.nav{background:var(--nav);color:#fff;min-height:56px;display:flex;align-items:center;gap:18px;padding:0 26px;position:sticky;top:0;z-index:5;overflow:auto}.nav strong{white-space:nowrap}.nav a{color:#fff;text-decoration:none;font-size:15px;white-space:nowrap;opacity:.92}.nav a:hover{opacity:1;text-decoration:underline}.wrap{width:min(1480px,calc(100% - 48px));margin:26px auto 60px}.card{background:var(--panel);border:1px solid var(--line);border-radius:14px;padding:18px;margin-bottom:18px;box-shadow:0 10px 26px rgba(8,18,37,.04)}h1{font-size:34px;margin:0 0 12px}h2{font-size:23px;margin:0 0 14px}h3{margin:0 0 8px}p{color:var(--muted);line-height:1.45}.hero{border-left:7px solid var(--orange)}.hero.good{border-left-color:var(--green)}.hero.bad{border-left-color:var(--red)}.safe{border-left:7px solid var(--green)}.warn{border-left:7px solid var(--orange)}.danger{border-left:7px solid var(--red)}.grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-top:14px}.metric{border:1px solid var(--line);border-radius:10px;padding:14px;background:var(--soft);min-height:82px}.metric strong{display:block;font-size:28px;line-height:1.05;word-break:break-word}.metric span{color:var(--muted);font-size:14px}.badge{display:inline-block;padding:5px 9px;border-radius:999px;font-size:12px;font-weight:700;margin:1px 3px 1px 0;white-space:nowrap}.badge-good{background:#dcfce7;color:#166534}.badge-warn{background:#fff7ed;color:#b45309}.badge-bad{background:#fee2e2;color:#991b1b}.badge-neutral{background:#eaf1ff;color:#1e40af}.actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:12px}.btn{display:inline-block;padding:10px 14px;border-radius:8px;color:#fff;text-decoration:none;font-weight:700;background:var(--blue);font-size:14px}.btn.dark{background:var(--slate)}.btn.orange{background:var(--orange)}.table-wrap{overflow:auto;border:1px solid var(--line);border-radius:10px}table{width:100%;border-collapse:collapse;min-width:850px}th,td{text-align:left;padding:10px 12px;border-bottom:1px solid var(--line);vertical-align:top;font-size:14px}th{background:#f8fafc;font-size:12px;text-transform:uppercase;letter-spacing:.02em}.two{display:grid;grid-template-columns:1fr 1fr;gap:18px}.list{margin:0;padding-left:18px;color:var(--muted)}.list li{margin:7px 0}.small{font-size:13px;color:var(--muted)}.badline{color:#991b1b}.goodline{color:#166534}.warnline{color:#b45309}code{background:#eef2ff;padding:2px 5px;border-radius:5px}pre{background:#0b1020;color:#d7e3ff;padding:14px;border-radius:12px;overflow:auto}@media(max-width:1100px){.grid{grid-template-columns:repeat(2,minmax(0,1fr))}.two{grid-template-columns:1fr}}@media(max-width:720px){.grid{grid-template-columns:1fr}.wrap{width:calc(100% - 24px);margin-top:14px}.nav{padding:0 14px}}
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
        <p>Read-only helper for production preparation. It checks whether the server-side EDXEIX cookie/CSRF session and submit URL configuration are present. It never prints secrets and never calls EDXEIX.</p>
        <div>
            <?= es_badge('READ ONLY', 'good') ?>
            <?= es_badge('NO SECRET OUTPUT', 'good') ?>
            <?= es_badge('NO EDXEIX CALL', 'good') ?>
            <?= $overallReady ? es_badge('SESSION PREREQS READY', 'good') : es_badge('SESSION PREREQS NEED ATTENTION', 'warn') ?>
        </div>
        <?php if ($error): ?><p class="badline"><strong>Error:</strong> <?= es_h($error) ?></p><?php endif; ?>
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

    <section class="two">
        <div class="card safe">
            <h2>Server-only Config State</h2>
            <div class="table-wrap"><table>
                <thead><tr><th>Check</th><th>Status</th><th>Detail</th></tr></thead>
                <tbody>
                    <tr><td><strong>live_submit.php exists</strong></td><td><?= es_bool_badge(!empty($configState['config_file_exists']), 'yes', 'no') ?></td><td><code><?= es_h($configState['config_file'] ?? '') ?></code></td></tr>
                    <tr><td><strong>live_submit.php readable</strong></td><td><?= es_bool_badge(!empty($configState['config_file_readable']), 'yes', 'no') ?></td><td>Must be readable by the cabnet PHP runtime.</td></tr>
                    <tr><td><strong>EDXEIX submit URL</strong></td><td><?= es_bool_badge(!empty($configState['edxeix_submit_url_configured']), 'configured', 'missing') ?></td><td>Host: <?= es_h($configState['edxeix_submit_url_host'] ?: 'not configured') ?></td></tr>
                    <tr><td><strong>Form method</strong></td><td><?= es_badge((string)($configState['edxeix_form_method'] ?? 'POST'), 'neutral') ?></td><td>Expected method is normally POST.</td></tr>
                    <tr><td><strong>Live flag</strong></td><td><?= !empty($configState['live_submit_enabled']) ? es_badge('enabled', 'warn') : es_badge('disabled', 'good') ?></td><td>Should stay disabled until the approved one-shot live test.</td></tr>
                    <tr><td><strong>HTTP flag</strong></td><td><?= !empty($configState['http_submit_enabled']) ? es_badge('enabled', 'warn') : es_badge('disabled', 'good') ?></td><td>Should stay disabled until the final HTTP transport patch.</td></tr>
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
        <h2>How to prepare these values safely</h2>
        <p>Do not paste cookies, CSRF tokens, API keys, or passwords into ChatGPT, GitHub, screenshots, or public files. These values belong only on the server.</p>
        <ol class="list">
            <li>Log in to EDXEIX in the browser and open the lease agreement creation form.</li>
            <li>Use browser developer tools to confirm the final form action URL and CSRF token field.</li>
            <li>Update only the server-side file: <code>/home/cabnet/gov.cabnet.app_config/live_submit.php</code>.</li>
            <li>Update/create only the runtime session file: <code><?= es_h($configState['edxeix_session_file'] ?? '/home/cabnet/gov.cabnet.app_app/storage/runtime/edxeix_session.json') ?></code>.</li>
            <li>Reload this page and confirm submit URL + cookie + CSRF are ready.</li>
        </ol>
        <p class="small">This page intentionally does not provide a web form for secrets. Use cPanel File Manager or SSH so secrets stay server-side.</p>
    </section>

    <section class="card">
        <h2>Safe session file template</h2>
        <p class="small">Example structure only. Replace values directly on the server. Do not commit this file.</p>
<pre>{
  "cookie_header": "PASTE_EDXEIX_COOKIE_HEADER_SERVER_SIDE_ONLY",
  "csrf_token": "PASTE_EDXEIX_CSRF_TOKEN_SERVER_SIDE_ONLY",
  "saved_at": "<?= es_h(date('Y-m-d H:i:s')) ?>",
  "source": "manual_server_update"
}</pre>
        <h3>Suggested permissions</h3>
<pre>mkdir -p /home/cabnet/gov.cabnet.app_app/storage/runtime
chown cabnet:cabnet /home/cabnet/gov.cabnet.app_app/storage/runtime/edxeix_session.json
chmod 600 /home/cabnet/gov.cabnet.app_app/storage/runtime/edxeix_session.json
chown cabnet:cabnet /home/cabnet/gov.cabnet.app_config/live_submit.php
chmod 640 /home/cabnet/gov.cabnet.app_config/live_submit.php</pre>
    </section>
</main>
</body>
</html>
