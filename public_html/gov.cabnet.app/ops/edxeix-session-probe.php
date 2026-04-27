<?php
/**
 * gov.cabnet.app — EDXEIX Session / Form GET Probe v2.7
 *
 * Purpose:
 * - Check local EDXEIX session metadata.
 * - Optionally perform a read-only GET request to the configured EDXEIX form/submit/dashboard URL.
 * - Detect whether the response looks like an authenticated EDXEIX page and whether a CSRF/request token is discoverable.
 *
 * Safety contract:
 * - Default page load does not call EDXEIX.
 * - probe=1 performs GET only.
 * - Never POSTs to EDXEIX.
 * - Does not call Bolt.
 * - Does not stage jobs.
 * - Does not update mappings.
 * - Does not write database rows or files.
 * - Does not print cookies, session secrets, CSRF values, or raw HTML.
 */

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Robots-Tag: noindex,nofollow', true);

require_once '/home/cabnet/gov.cabnet.app_app/lib/bolt_sync_lib.php';

function esf_h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function esf_badge(string $text, string $type = 'neutral'): string { return '<span class="badge badge-' . esf_h($type) . '">' . esf_h($text) . '</span>'; }
function esf_yes(bool $v, string $yes = 'YES', string $no = 'NO'): string { return $v ? esf_badge($yes, 'good') : esf_badge($no, 'bad'); }
function esf_warn(bool $v, string $yes = 'YES', string $no = 'NO'): string { return $v ? esf_badge($yes, 'warn') : esf_badge($no, 'good'); }
function esf_metric($value, string $label): string { return '<div class="metric"><strong>' . esf_h((string)$value) . '</strong><span>' . esf_h($label) . '</span></div>'; }

function esf_safe_url_display(string $url): string
{
    if ($url === '') return '';
    $parts = parse_url($url);
    if (!is_array($parts)) return '[configured]';
    $scheme = $parts['scheme'] ?? 'https';
    $host = $parts['host'] ?? '';
    $path = $parts['path'] ?? '';
    if ($host === '') return '[configured]';
    return $scheme . '://' . $host . $path;
}

function esf_config_presence(array $config): array
{
    $edx = is_array($config['edxeix'] ?? null) ? $config['edxeix'] : [];
    $keys = ['base_url','login_url','dashboard_url','form_url','submit_url','lessor_id','default_starting_point_id','future_start_guard_minutes','session_cookie_file','cookie_file','session_file'];
    $out = [];
    foreach ($keys as $key) {
        $exists = array_key_exists($key, $edx) && $edx[$key] !== null && $edx[$key] !== '';
        $display = '';
        if ($exists && in_array($key, ['lessor_id','default_starting_point_id','future_start_guard_minutes'], true)) {
            $display = (string)$edx[$key];
        } elseif ($exists && in_array($key, ['base_url','login_url','dashboard_url','form_url','submit_url'], true)) {
            $display = esf_safe_url_display((string)$edx[$key]);
        } elseif ($exists) {
            $display = '[configured]';
        }
        $out[$key] = ['present' => $exists, 'display' => $display];
    }
    return $out;
}

function esf_candidate_dirs(array $config): array
{
    $paths = function_exists('gov_bridge_paths') ? gov_bridge_paths() : [];
    $dirs = [];
    foreach (['runtime','storage','artifacts'] as $key) {
        if (!empty($paths[$key]) && is_dir((string)$paths[$key])) {
            $dirs[] = (string)$paths[$key];
        }
    }
    $edx = is_array($config['edxeix'] ?? null) ? $config['edxeix'] : [];
    foreach (['session_cookie_file','cookie_file','session_file'] as $key) {
        if (!empty($edx[$key]) && is_string($edx[$key])) {
            $dir = dirname((string)$edx[$key]);
            if (is_dir($dir)) $dirs[] = $dir;
        }
    }
    return array_values(array_unique($dirs));
}

function esf_session_candidates(array $config): array
{
    $files = [];
    foreach (esf_candidate_dirs($config) as $dir) {
        foreach (['edxeix_session.json','*edxeix*','*EDXEIX*','*cookie*','*session*'] as $pattern) {
            foreach (glob(rtrim($dir, '/') . '/' . $pattern) ?: [] as $file) {
                if (!is_file($file)) continue;
                $real = realpath($file) ?: $file;
                $decoded = null;
                $jsonKeys = [];
                if (is_readable($file)) {
                    $raw = @file_get_contents($file);
                    if (is_string($raw) && trim($raw) !== '') {
                        $tmp = json_decode($raw, true);
                        if (is_array($tmp)) {
                            $decoded = $tmp;
                            $jsonKeys = array_slice(array_keys($tmp), 0, 20);
                        }
                    }
                }
                $files[$real] = [
                    'basename' => basename($file),
                    'path_hint' => dirname($file),
                    'readable' => is_readable($file),
                    'is_json' => is_array($decoded),
                    'json_keys' => $jsonKeys,
                    'has_cookie_like_data' => esf_json_has_cookie_like_data($decoded),
                    'has_token_like_data' => esf_json_has_token_like_data($decoded),
                    'size_bytes' => (int)@filesize($file),
                    'modified_at' => date('Y-m-d H:i:s', (int)@filemtime($file)),
                    '_path' => $real,
                    '_json' => $decoded,
                ];
            }
        }
    }

    $out = array_values($files);
    usort($out, static function (array $a, array $b): int {
        $ap = ($a['basename'] === 'edxeix_session.json' ? 0 : 1) . '|' . (9999999999 - strtotime((string)$a['modified_at']));
        $bp = ($b['basename'] === 'edxeix_session.json' ? 0 : 1) . '|' . (9999999999 - strtotime((string)$b['modified_at']));
        return strcmp($ap, $bp);
    });

    return $out;
}

function esf_json_has_cookie_like_data($json): bool
{
    if (!is_array($json)) return false;
    $keys = ['cookie','cookies','cookie_header','cookieHeader','session_cookie','sessionCookie','laravel_session','XSRF-TOKEN'];
    foreach ($keys as $key) {
        if (array_key_exists($key, $json) && $json[$key] !== '') return true;
    }
    foreach ($json as $value) {
        if (is_array($value) && esf_json_has_cookie_like_data($value)) return true;
    }
    return false;
}

function esf_json_has_token_like_data($json): bool
{
    if (!is_array($json)) return false;
    $keys = ['_token','csrf','csrf_token','csrfToken','token','request_token','authenticity_token','xsrf'];
    foreach ($keys as $key) {
        if (array_key_exists($key, $json) && $json[$key] !== '') return true;
    }
    foreach ($json as $value) {
        if (is_array($value) && esf_json_has_token_like_data($value)) return true;
    }
    return false;
}

function esf_extract_cookie_pairs($json): array
{
    $pairs = [];
    if (!is_array($json)) return $pairs;

    $addPair = static function ($name, $value) use (&$pairs): void {
        $name = trim((string)$name);
        $value = (string)$value;
        if ($name !== '' && $value !== '') {
            $pairs[$name] = $value;
        }
    };

    foreach (['cookie_header','cookieHeader','cookie','session_cookie','sessionCookie'] as $key) {
        if (!empty($json[$key]) && is_string($json[$key])) {
            foreach (explode(';', $json[$key]) as $piece) {
                if (strpos($piece, '=') === false) continue;
                [$name, $value] = explode('=', $piece, 2);
                $addPair($name, $value);
            }
        }
    }

    if (!empty($json['cookies'])) {
        if (is_string($json['cookies'])) {
            foreach (explode(';', $json['cookies']) as $piece) {
                if (strpos($piece, '=') === false) continue;
                [$name, $value] = explode('=', $piece, 2);
                $addPair($name, $value);
            }
        } elseif (is_array($json['cookies'])) {
            foreach ($json['cookies'] as $key => $cookie) {
                if (is_array($cookie)) {
                    $name = $cookie['name'] ?? $cookie['Name'] ?? $key;
                    $value = $cookie['value'] ?? $cookie['Value'] ?? '';
                    $addPair($name, $value);
                } elseif (is_string($cookie)) {
                    if (strpos($cookie, '=') !== false) {
                        [$name, $value] = explode('=', $cookie, 2);
                        $addPair($name, $value);
                    } else {
                        $addPair($key, $cookie);
                    }
                }
            }
        }
    }

    foreach (['laravel_session','XSRF-TOKEN','xsrf-token','sessionid','PHPSESSID'] as $key) {
        if (!empty($json[$key])) {
            $addPair($key, $json[$key]);
        }
    }

    foreach ($json as $value) {
        if (is_array($value)) {
            foreach (esf_extract_cookie_pairs($value) as $k => $v) {
                $pairs[$k] = $v;
            }
        }
    }

    return $pairs;
}

function esf_select_session(array $sessions): array
{
    foreach ($sessions as $session) {
        if (($session['basename'] ?? '') === 'edxeix_session.json' && !empty($session['is_json'])) return $session;
    }
    foreach ($sessions as $session) {
        if (!empty($session['has_cookie_like_data']) && !empty($session['is_json'])) return $session;
    }
    foreach ($sessions as $session) {
        if (!empty($session['is_json'])) return $session;
    }
    return $sessions[0] ?? [];
}

function esf_target_url(array $config): string
{
    $edx = is_array($config['edxeix'] ?? null) ? $config['edxeix'] : [];
    foreach (['form_url','submit_url','dashboard_url','base_url'] as $key) {
        if (!empty($edx[$key]) && is_string($edx[$key]) && preg_match('#^https?://#i', (string)$edx[$key])) {
            return (string)$edx[$key];
        }
    }
    return '';
}

function esf_probe_get(string $url, array $selectedSession): array
{
    $out = [
        'requested' => true,
        'ok' => false,
        'error' => null,
        'method' => 'GET',
        'url_display' => esf_safe_url_display($url),
        'used_cookie_header' => false,
        'used_cookie_file' => false,
        'http_status' => 0,
        'content_type' => '',
        'location_display' => '',
        'body_bytes' => 0,
        'looks_like_login' => false,
        'looks_like_edxeix' => false,
        'form_count' => 0,
        'input_names' => [],
        'csrf_candidates' => [],
        'required_field_names' => [],
        'raw_html_returned' => false,
    ];

    if (!function_exists('curl_init')) {
        $out['error'] = 'PHP cURL extension is not available.';
        return $out;
    }
    if ($url === '') {
        $out['error'] = 'No configured EDXEIX form/submit/dashboard/base URL was found.';
        return $out;
    }

    $headers = [
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        'User-Agent: gov.cabnet.app-readonly-edxeix-get-probe/2.7',
    ];

    $json = is_array($selectedSession['_json'] ?? null) ? $selectedSession['_json'] : [];
    $pairs = esf_extract_cookie_pairs($json);
    if ($pairs) {
        $cookiePieces = [];
        foreach ($pairs as $name => $value) {
            $cookiePieces[] = $name . '=' . $value;
        }
        $headers[] = 'Cookie: ' . implode('; ', $cookiePieces);
        $out['used_cookie_header'] = true;
        $out['cookie_names_used'] = array_values(array_map(static fn($n) => '[redacted:' . $n . ']', array_keys($pairs)));
    }

    $edxCookieFile = '';
    foreach (['cookie_file','session_cookie_file'] as $key) {
        if (!empty($json[$key]) && is_string($json[$key]) && is_readable((string)$json[$key])) {
            $edxCookieFile = (string)$json[$key];
            break;
        }
    }

    $ch = curl_init($url);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_NOBODY => false,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_CUSTOMREQUEST => 'GET',
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ];
    if ($edxCookieFile !== '') {
        $opts[CURLOPT_COOKIEFILE] = $edxCookieFile;
        $out['used_cookie_file'] = true;
        $out['cookie_file_hint'] = dirname($edxCookieFile) . '/' . basename($edxCookieFile);
    }
    curl_setopt_array($ch, $opts);
    $response = curl_exec($ch);
    $error = curl_error($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $contentType = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);

    $out['http_status'] = $status;
    $out['content_type'] = $contentType;

    if ($response === false) {
        $out['error'] = 'GET request failed: ' . $error;
        return $out;
    }

    $rawHeaders = substr((string)$response, 0, $headerSize);
    $body = substr((string)$response, $headerSize);
    $out['body_bytes'] = strlen($body);

    if (preg_match('/^Location:\s*(.+)$/im', $rawHeaders, $m)) {
        $out['location_display'] = esf_safe_url_display(trim($m[1]));
    }

    $lower = strtolower($body);
    $out['looks_like_login'] = (strpos($lower, 'login') !== false || strpos($lower, 'password') !== false || strpos($lower, 'σύνδεση') !== false || strpos($lower, 'αποσύνδεση') !== false);
    $out['looks_like_edxeix'] = (strpos($body, 'EDXEIX') !== false || strpos($body, 'ΕΔΧ') !== false || strpos($body, 'Ε.Δ.Χ') !== false || strpos($body, 'ΕΛΛΗΝΙΚΗ ΔΗΜΟΚΡΑΤΙΑ') !== false || strpos($body, 'Καρτέλα') !== false || strpos($body, 'Υπουργείο Υποδομών') !== false);
    $out['form_count'] = preg_match_all('/<form\b/i', $body, $tmp);

    $inputNames = [];
    if (preg_match_all('/<input\b[^>]*\bname=["\']([^"\']+)["\']/i', $body, $matches)) {
        foreach ($matches[1] as $name) {
            $name = trim((string)$name);
            if ($name !== '') $inputNames[$name] = true;
        }
    }
    if (preg_match_all('/<select\b[^>]*\bname=["\']([^"\']+)["\']/i', $body, $matches)) {
        foreach ($matches[1] as $name) {
            $name = trim((string)$name);
            if ($name !== '') $inputNames[$name] = true;
        }
    }
    if (preg_match_all('/<textarea\b[^>]*\bname=["\']([^"\']+)["\']/i', $body, $matches)) {
        foreach ($matches[1] as $name) {
            $name = trim((string)$name);
            if ($name !== '') $inputNames[$name] = true;
        }
    }
    $out['input_names'] = array_slice(array_keys($inputNames), 0, 120);

    $csrfNames = ['_token','csrf_token','csrf','authenticity_token','__RequestVerificationToken','request_token'];
    foreach ($out['input_names'] as $name) {
        foreach ($csrfNames as $csrfName) {
            if (stripos($name, $csrfName) !== false) {
                $out['csrf_candidates'][] = [
                    'name' => $name,
                    'value' => '[redacted]',
                    'detected' => true,
                ];
            }
        }
    }
    if (preg_match_all('/<meta\b[^>]*(?:name|property)=["\']([^"\']*csrf[^"\']*)["\'][^>]*content=["\']([^"\']*)["\']/i', $body, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $m) {
            $out['csrf_candidates'][] = [
                'name' => $m[1],
                'value' => '[redacted]',
                'detected' => true,
            ];
        }
    }

    $requiredHints = ['broker','lessor','lessee','driver','vehicle','starting','boarding','disembark','started','ended','price'];
    foreach ($out['input_names'] as $name) {
        foreach ($requiredHints as $hint) {
            if (stripos($name, $hint) !== false) {
                $out['required_field_names'][] = $name;
                break;
            }
        }
    }
    $out['required_field_names'] = array_values(array_unique($out['required_field_names']));

    $out['ok'] = $status >= 200 && $status < 400;
    return $out;
}

$format = strtolower(trim((string)($_GET['format'] ?? 'html')));
$probeRequested = in_array(strtolower(trim((string)($_GET['probe'] ?? '0'))), ['1','true','yes','on'], true);

$state = [
    'config_loaded' => false,
    'error' => null,
    'config_presence' => [],
    'sessions' => [],
    'selected_session' => [],
    'target_url_display' => '',
    'get_probe' => ['requested' => false],
];

try {
    $config = gov_bridge_load_config();
    if (!empty($config['app']['timezone'])) {
        date_default_timezone_set((string)$config['app']['timezone']);
    }
    $state['config_loaded'] = true;
    $state['config_presence'] = esf_config_presence($config);
    $state['sessions'] = esf_session_candidates($config);
    $state['selected_session'] = esf_select_session($state['sessions']);
    $targetUrl = esf_target_url($config);
    $state['target_url_display'] = esf_safe_url_display($targetUrl);

    if ($probeRequested) {
        $state['get_probe'] = esf_probe_get($targetUrl, $state['selected_session']);
    }
} catch (Throwable $e) {
    $state['error'] = $e->getMessage();
}

$sessionCount = count($state['sessions']);
$selected = $state['selected_session'];
$selectedHasCookie = !empty($selected['has_cookie_like_data']);
$selectedHasToken = !empty($selected['has_token_like_data']);
$targetConfigured = $state['target_url_display'] !== '';
$get = $state['get_probe'];
$getOk = !empty($get['ok']);
$csrfDetected = !empty($get['csrf_candidates']);
$formsDetected = (int)($get['form_count'] ?? 0) > 0;
$looksLikeEdxeix = !empty($get['looks_like_edxeix']);
$looksLikeLogin = !empty($get['looks_like_login']);

$decisionCode = 'LOCAL_SESSION_METADATA_READY';
$decisionType = 'warn';
$decisionText = 'Local session/config metadata exists. Run the GET probe to verify the EDXEIX page/session layer.';
if ($probeRequested) {
    if ($getOk && $looksLikeEdxeix && ($csrfDetected || $formsDetected)) {
        $decisionCode = 'EDXEIX_GET_PROBE_READY_FOR_FINAL_SUBMIT_DESIGN';
        $decisionType = 'good';
        $decisionText = 'GET probe reached an EDXEIX-like page and detected form/token signals. No POST was performed.';
    } elseif ($getOk && $looksLikeEdxeix) {
        $decisionCode = 'EDXEIX_GET_REACHABLE_BUT_FORM_TOKEN_UNCONFIRMED';
        $decisionType = 'warn';
        $decisionText = 'GET probe reached an EDXEIX-like page, but form/token fields were not clearly detected.';
    } elseif ($getOk && $looksLikeLogin) {
        $decisionCode = 'EDXEIX_GET_REACHED_LOGIN_OR_SESSION_EXPIRED';
        $decisionType = 'warn';
        $decisionText = 'GET probe reached a page that looks like login/session state may require refresh.';
    } elseif ($getOk) {
        $decisionCode = 'EDXEIX_GET_REACHABLE_BUT_PAGE_UNCONFIRMED';
        $decisionType = 'warn';
        $decisionText = 'GET probe reached a page, but it did not clearly match expected EDXEIX form signals.';
    } else {
        $decisionCode = 'EDXEIX_GET_PROBE_NOT_READY';
        $decisionType = 'bad';
        $decisionText = 'GET probe did not confirm EDXEIX form/session readiness.';
    }
} elseif (!$targetConfigured || $sessionCount === 0) {
    $decisionCode = 'LOCAL_SESSION_OR_TARGET_MISSING';
    $decisionType = 'bad';
    $decisionText = 'Local session metadata or target URL configuration is missing.';
}

function esf_public_session(array $session): array
{
    if (!$session) return [];
    return [
        'basename' => $session['basename'] ?? '',
        'path_hint' => $session['path_hint'] ?? '',
        'readable' => !empty($session['readable']),
        'is_json' => !empty($session['is_json']),
        'json_keys' => $session['json_keys'] ?? [],
        'has_cookie_like_data' => !empty($session['has_cookie_like_data']),
        'has_token_like_data' => !empty($session['has_token_like_data']),
        'size_bytes' => $session['size_bytes'] ?? 0,
        'modified_at' => $session['modified_at'] ?? '',
    ];
}

$payload = [
    'ok' => $state['error'] === null,
    'script' => 'ops/edxeix-session-probe.php',
    'generated_at' => date('c'),
    'safety_contract' => [
        'calls_bolt' => false,
        'calls_edxeix_get' => $probeRequested,
        'posts_to_edxeix' => false,
        'stages_jobs' => false,
        'updates_mappings' => false,
        'writes_database' => false,
        'writes_files' => false,
        'prints_secrets' => false,
        'live_edxeix_submission' => 'disabled_not_used',
    ],
    'decision' => [
        'code' => $decisionCode,
        'type' => $decisionType,
        'text' => $decisionText,
    ],
    'checks' => [
        'config_loaded' => $state['config_loaded'],
        'curl_extension_loaded' => extension_loaded('curl'),
        'target_url_configured' => $targetConfigured,
        'session_candidates_found' => $sessionCount,
        'selected_session_readable' => !empty($selected['readable']),
        'selected_session_json' => !empty($selected['is_json']),
        'selected_session_cookie_like_data' => $selectedHasCookie,
        'selected_session_token_like_data' => $selectedHasToken,
        'probe_requested' => $probeRequested,
        'probe_http_ok' => $getOk,
        'probe_looks_like_edxeix' => $looksLikeEdxeix,
        'probe_looks_like_login' => $looksLikeLogin,
        'probe_forms_detected' => $formsDetected,
        'probe_csrf_detected' => $csrfDetected,
    ],
    'target_url_display' => $state['target_url_display'],
    'config_presence' => $state['config_presence'],
    'selected_session' => esf_public_session($state['selected_session']),
    'session_candidates' => array_map('esf_public_session', $state['sessions']),
    'get_probe' => $state['get_probe'],
    'error' => $state['error'],
    'links' => [
        'html' => '/ops/edxeix-session-probe.php',
        'json' => '/ops/edxeix-session-probe.php?format=json',
        'run_get_probe' => '/ops/edxeix-session-probe.php?probe=1',
        'run_get_probe_json' => '/ops/edxeix-session-probe.php?probe=1&format=json',
        'submit_readiness' => '/ops/edxeix-submit-readiness.php',
        'preflight_review' => '/ops/preflight-review.php',
        'route_index' => '/ops/route-index.php',
    ],
];

if ($format === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>EDXEIX Session Probe | gov.cabnet.app</title>
    <link rel="stylesheet" href="/assets/css/gov-ops-edxeix.css?v=2.7">
</head>
<body>
<div class="gov-topbar">
    <div class="gov-brand"><div class="gov-brand-crest">ΕΔ</div><div class="gov-brand-text"><strong>gov.cabnet.app</strong><span>Bolt → EDXEIX operational console</span></div></div>
    <div class="gov-top-links"><a href="/ops/home.php">Αρχική</a><a href="/ops/edxeix-submit-readiness.php">Submit Readiness</a><a href="/ops/preflight-review.php">Preflight</a><a class="gov-logout" href="/ops/route-index.php">Route Index</a></div>
</div>
<div class="gov-shell">
    <aside class="gov-sidebar">
        <h3>EDXEIX Session Probe</h3><p>Read-only session/form GET preparation</p>
        <div class="gov-side-group">
            <div class="gov-side-group-title">Preparation</div>
            <a class="gov-side-link active" href="/ops/edxeix-session-probe.php">Session/Form Probe</a>
            <a class="gov-side-link" href="/ops/edxeix-submit-readiness.php">Submit Readiness</a>
            <a class="gov-side-link" href="/ops/preflight-review.php">Preflight Review</a>
            <a class="gov-side-link" href="/ops/completed-visibility.php">Completed Visibility</a>
            <a class="gov-side-link" href="/ops/route-index.php">Route Index</a>
        </div>
        <div class="gov-side-note">GET only when you click the probe button. No POST. No live submit. No secrets printed.</div>
    </aside>
    <div class="gov-content">
        <div class="gov-page-header">
            <div><h1 class="gov-page-title">Έλεγχος συνεδρίας / φόρμας EDXEIX</h1><div class="gov-breadcrumbs">Αρχική / Διαχειριστικό / Έλεγχος συνεδρίας / φόρμας EDXEIX</div></div>
            <div class="gov-tabs">
                <a class="gov-tab active" href="/ops/edxeix-session-probe.php">Καρτέλα</a>
                <a class="gov-tab" href="/ops/edxeix-session-probe.php?format=json">JSON</a>
                <a class="gov-tab" href="/ops/edxeix-session-probe.php?probe=1">Run GET</a>
                <a class="gov-tab" href="/ops/edxeix-submit-readiness.php">Readiness</a>
            </div>
        </div>
        <main class="wrap wrap-shell">
            <section class="safety">
                <strong>READ-ONLY SESSION / FORM PROBE.</strong>
                Default load is local metadata only. The GET probe uses GET only and never POSTs to EDXEIX, never stages jobs, and never writes data.
            </section>

            <section class="card hero <?= esf_h($decisionType) ?>">
                <h1>EDXEIX Session / Form GET Probe</h1>
                <p><?= esf_h($decisionText) ?></p>
                <div>
                    <?= esf_badge($decisionCode, $decisionType) ?>
                    <?= esf_badge('NO POST', 'good') ?>
                    <?= esf_badge('LIVE SUBMIT OFF', 'good') ?>
                    <?= $probeRequested ? esf_badge('GET PROBE RAN', 'warn') : esf_badge('GET NOT RUN BY DEFAULT', 'good') ?>
                </div>
                <div class="grid" style="margin-top:14px">
                    <?= esf_metric($sessionCount, 'Session candidates') ?>
                    <?= esf_metric($targetConfigured ? 'yes' : 'no', 'Target URL configured') ?>
                    <?= esf_metric($probeRequested ? (string)($get['http_status'] ?? 0) : 'not run', 'GET HTTP status') ?>
                    <?= esf_metric($csrfDetected ? 'yes' : 'no', 'CSRF/token signal') ?>
                </div>
                <div class="actions">
                    <a class="btn warn" href="/ops/edxeix-session-probe.php?probe=1">Run Safe GET Probe</a>
                    <a class="btn" href="/ops/edxeix-session-probe.php?format=json">Open JSON</a>
                    <a class="btn dark" href="/ops/edxeix-submit-readiness.php">Submit Readiness</a>
                    <a class="btn good" href="/ops/preflight-review.php">Preflight Review</a>
                </div>
            </section>

            <section class="two">
                <div class="card">
                    <h2>Local session/config checks</h2>
                    <div class="kv">
                        <div class="k">Config loaded</div><div><?= esf_yes($state['config_loaded']) ?></div>
                        <div class="k">cURL loaded</div><div><?= esf_yes(extension_loaded('curl')) ?></div>
                        <div class="k">Target URL configured</div><div><?= esf_yes($targetConfigured) ?> <code><?= esf_h($state['target_url_display']) ?></code></div>
                        <div class="k">Session candidates</div><div><strong><?= esf_h($sessionCount) ?></strong></div>
                        <div class="k">Selected session</div><div><code><?= esf_h((string)($selected['basename'] ?? '')) ?></code></div>
                        <div class="k">Selected JSON</div><div><?= esf_yes(!empty($selected['is_json'])) ?></div>
                        <div class="k">Cookie-like data</div><div><?= esf_yes($selectedHasCookie) ?></div>
                        <div class="k">Token-like data</div><div><?= $selectedHasToken ? esf_badge('YES / redacted', 'warn') : esf_badge('NO', 'neutral') ?></div>
                    </div>
                </div>
                <div class="card">
                    <h2>GET probe checks</h2>
                    <div class="kv">
                        <div class="k">Probe requested</div><div><?= $probeRequested ? esf_badge('YES','warn') : esf_badge('NO','good') ?></div>
                        <div class="k">HTTP OK</div><div><?= esf_yes($getOk) ?></div>
                        <div class="k">HTTP status</div><div><strong><?= esf_h((string)($get['http_status'] ?? '')) ?></strong></div>
                        <div class="k">Content type</div><div><?= esf_h((string)($get['content_type'] ?? '')) ?></div>
                        <div class="k">Looks like EDXEIX</div><div><?= esf_yes($looksLikeEdxeix) ?></div>
                        <div class="k">Looks like login/session</div><div><?= esf_warn($looksLikeLogin) ?></div>
                        <div class="k">Forms detected</div><div><?= esf_yes($formsDetected) ?></div>
                        <div class="k">CSRF/token detected</div><div><?= esf_yes($csrfDetected) ?></div>
                    </div>
                    <?php if (!empty($get['error'])): ?><p class="badline"><strong><?= esf_h($get['error']) ?></strong></p><?php endif; ?>
                </div>
            </section>

            <section class="card">
                <h2>Discovered form signals</h2>
                <?php if (!$probeRequested): ?>
                    <p>GET probe has not been run yet. Click <strong>Run Safe GET Probe</strong> when you want to check the session/form layer.</p>
                <?php else: ?>
                    <p><strong>Raw HTML is not displayed.</strong> Cookies and token values are redacted.</p>
                    <div class="table-wrap">
                        <table>
                            <thead><tr><th>Signal</th><th>Value</th></tr></thead>
                            <tbody>
                                <tr><td>Forms detected</td><td><?= esf_h((string)($get['form_count'] ?? 0)) ?></td></tr>
                                <tr><td>Input/select/textarea names</td><td><?= esf_h(implode(', ', array_slice((array)($get['input_names'] ?? []), 0, 80))) ?></td></tr>
                                <tr><td>CSRF candidates</td><td><?= esf_h(implode(', ', array_map(static fn($r) => (string)($r['name'] ?? ''), (array)($get['csrf_candidates'] ?? [])))) ?></td></tr>
                                <tr><td>Required-field hints</td><td><?= esf_h(implode(', ', (array)($get['required_field_names'] ?? []))) ?></td></tr>
                                <tr><td>Redirect location</td><td><?= esf_h((string)($get['location_display'] ?? '')) ?></td></tr>
                                <tr><td>Body bytes</td><td><?= esf_h((string)($get['body_bytes'] ?? 0)) ?></td></tr>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>

            <section class="card">
                <h2>Session candidates</h2>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>File</th><th>Readable</th><th>JSON</th><th>Cookie-like</th><th>Token-like</th><th>Size</th><th>Modified</th><th>Keys</th></tr></thead>
                        <tbody>
                        <?php foreach ($state['sessions'] as $session): ?>
                            <tr>
                                <td><code><?= esf_h((string)$session['basename']) ?></code></td>
                                <td><?= esf_yes(!empty($session['readable'])) ?></td>
                                <td><?= esf_yes(!empty($session['is_json'])) ?></td>
                                <td><?= !empty($session['has_cookie_like_data']) ? esf_badge('YES','good') : esf_badge('NO','neutral') ?></td>
                                <td><?= !empty($session['has_token_like_data']) ? esf_badge('YES / redacted','warn') : esf_badge('NO','neutral') ?></td>
                                <td><?= esf_h((string)$session['size_bytes']) ?></td>
                                <td><?= esf_h((string)$session['modified_at']) ?></td>
                                <td><?= esf_h(implode(', ', (array)$session['json_keys'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="card">
                <h2>Next decision</h2>
                <ol class="timeline">
                    <li>If GET probe confirms EDXEIX form/token signals, the app is ready for final submit-handler design.</li>
                    <li>Do not POST or live submit historical/completed/cancelled rows.</li>
                    <li>Live submit test still requires a real mapped future-safe Bolt candidate and explicit approval.</li>
                    <li>If the GET probe shows login/session expiry, refresh the saved EDXEIX session before any future live-submit test.</li>
                </ol>
            </section>
        </main>
    </div>
</div>
</body>
</html>
