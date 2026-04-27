<?php
/**
 * gov.cabnet.app — EDXEIX Redirect-Follow GET Probe v2.8
 *
 * Purpose:
 * - Continue the v2.7 EDXEIX session/form check.
 * - Perform a GET-only probe with optional redirect following.
 * - Report redirect chain, final URL/status, login/dashboard/form/token signals.
 *
 * Safety contract:
 * - Default load does not call EDXEIX.
 * - probe=1 performs GET only.
 * - follow=1 follows redirects, still GET only.
 * - Never POSTs to EDXEIX.
 * - Does not call Bolt.
 * - Does not stage jobs.
 * - Does not update mappings.
 * - Does not write database rows or files.
 * - Does not print cookies, token values, session secrets, or raw HTML.
 */

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Robots-Tag: noindex,nofollow', true);

require_once '/home/cabnet/gov.cabnet.app_app/lib/bolt_sync_lib.php';

function erg_h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function erg_badge(string $text, string $type = 'neutral'): string { return '<span class="badge badge-' . erg_h($type) . '">' . erg_h($text) . '</span>'; }
function erg_yes(bool $v, string $yes = 'YES', string $no = 'NO'): string { return $v ? erg_badge($yes, 'good') : erg_badge($no, 'bad'); }
function erg_warn(bool $v, string $yes = 'YES', string $no = 'NO'): string { return $v ? erg_badge($yes, 'warn') : erg_badge($no, 'good'); }
function erg_metric($value, string $label): string { return '<div class="metric"><strong>' . erg_h((string)$value) . '</strong><span>' . erg_h($label) . '</span></div>'; }

function erg_safe_url_display(string $url): string
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

function erg_json_has_key_like($json, array $needles): bool
{
    if (!is_array($json)) return false;
    foreach ($json as $key => $value) {
        foreach ($needles as $needle) {
            if (stripos((string)$key, $needle) !== false && $value !== '') {
                return true;
            }
        }
        if (is_array($value) && erg_json_has_key_like($value, $needles)) {
            return true;
        }
    }
    return false;
}

function erg_extract_cookie_pairs($json): array
{
    $pairs = [];
    if (!is_array($json)) return $pairs;

    $add = static function ($name, $value) use (&$pairs): void {
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
                $add($name, $value);
            }
        }
    }

    if (!empty($json['cookies'])) {
        if (is_string($json['cookies'])) {
            foreach (explode(';', $json['cookies']) as $piece) {
                if (strpos($piece, '=') === false) continue;
                [$name, $value] = explode('=', $piece, 2);
                $add($name, $value);
            }
        } elseif (is_array($json['cookies'])) {
            foreach ($json['cookies'] as $key => $cookie) {
                if (is_array($cookie)) {
                    $add($cookie['name'] ?? $cookie['Name'] ?? $key, $cookie['value'] ?? $cookie['Value'] ?? '');
                } elseif (is_string($cookie)) {
                    if (strpos($cookie, '=') !== false) {
                        [$name, $value] = explode('=', $cookie, 2);
                        $add($name, $value);
                    } else {
                        $add($key, $cookie);
                    }
                }
            }
        }
    }

    foreach (['laravel_session','XSRF-TOKEN','xsrf-token','sessionid','PHPSESSID'] as $key) {
        if (!empty($json[$key])) {
            $add($key, $json[$key]);
        }
    }

    foreach ($json as $value) {
        if (is_array($value)) {
            foreach (erg_extract_cookie_pairs($value) as $k => $v) {
                $pairs[$k] = $v;
            }
        }
    }

    return $pairs;
}

function erg_candidate_dirs(array $config): array
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

function erg_session_candidates(array $config): array
{
    $files = [];
    foreach (erg_candidate_dirs($config) as $dir) {
        foreach (['edxeix_session.json','*edxeix*','*EDXEIX*','*cookie*','*session*'] as $pattern) {
            foreach (glob(rtrim($dir, '/') . '/' . $pattern) ?: [] as $file) {
                if (!is_file($file)) continue;
                $real = realpath($file) ?: $file;
                $json = null;
                $keys = [];
                if (is_readable($file)) {
                    $raw = @file_get_contents($file);
                    if (is_string($raw) && trim($raw) !== '') {
                        $tmp = json_decode($raw, true);
                        if (is_array($tmp)) {
                            $json = $tmp;
                            $keys = array_slice(array_keys($tmp), 0, 20);
                        }
                    }
                }
                $files[$real] = [
                    'basename' => basename($file),
                    'path_hint' => dirname($file),
                    'readable' => is_readable($file),
                    'is_json' => is_array($json),
                    'json_keys' => $keys,
                    'has_cookie_like_data' => erg_json_has_key_like($json, ['cookie', 'session', 'xsrf']),
                    'has_token_like_data' => erg_json_has_key_like($json, ['token', 'csrf', 'xsrf']),
                    'size_bytes' => (int)@filesize($file),
                    'modified_at' => date('Y-m-d H:i:s', (int)@filemtime($file)),
                    '_path' => $real,
                    '_json' => $json,
                ];
            }
        }
    }

    $out = array_values($files);
    usort($out, static function (array $a, array $b): int {
        if (($a['basename'] ?? '') === 'edxeix_session.json') return -1;
        if (($b['basename'] ?? '') === 'edxeix_session.json') return 1;
        return strcmp((string)($b['modified_at'] ?? ''), (string)($a['modified_at'] ?? ''));
    });
    return $out;
}

function erg_select_session(array $sessions): array
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

function erg_target_candidates(array $config, array $session): array
{
    $edx = is_array($config['edxeix'] ?? null) ? $config['edxeix'] : [];
    $json = is_array($session['_json'] ?? null) ? $session['_json'] : [];
    $raw = [];

    foreach (['form_url','submit_url','dashboard_url','base_url'] as $key) {
        if (!empty($edx[$key]) && is_string($edx[$key])) $raw[] = (string)$edx[$key];
    }
    foreach (['detected_form_action','fixed_submit_url_used','source_url'] as $key) {
        if (!empty($json[$key]) && is_string($json[$key])) $raw[] = (string)$json[$key];
    }

    $base = !empty($edx['base_url']) && is_string($edx['base_url']) ? rtrim((string)$edx['base_url'], '/') : 'https://edxeix.yme.gov.gr';
    $common = [
        $base,
        $base . '/dashboard',
        $base . '/dashboard/broker/' . rawurlencode((string)($edx['lessor_id'] ?? '')),
        $base . '/dashboard/lease-agreement',
    ];
    $raw = array_merge($raw, $common);

    $out = [];
    foreach ($raw as $url) {
        $url = trim($url);
        if ($url === '') continue;
        if (strpos($url, '/') === 0) $url = $base . $url;
        if (!preg_match('#^https?://#i', $url)) continue;
        $key = erg_safe_url_display($url);
        $out[$key] = $url;
    }
    return array_values($out);
}

function erg_parse_redirect_chain(string $headers, string $initialUrl): array
{
    $blocks = preg_split("/\r\n\r\n|\n\n|\r\r/", trim($headers));
    $chain = [];
    $current = $initialUrl;
    foreach ($blocks as $block) {
        if (trim($block) === '') continue;
        $status = 0;
        $location = '';
        if (preg_match('/HTTP\/[0-9.]+\s+([0-9]+)/i', $block, $m)) {
            $status = (int)$m[1];
        }
        if (preg_match('/^Location:\s*(.+)$/im', $block, $m)) {
            $location = trim($m[1]);
        }
        if ($status > 0) {
            $chain[] = [
                'status' => $status,
                'from' => erg_safe_url_display($current),
                'location' => erg_safe_url_display($location),
            ];
        }
        if ($location !== '') {
            $current = $location;
        }
    }
    return $chain;
}

function erg_analyze_html(string $body): array
{
    $lower = strtolower($body);

    $out = [
        'body_bytes' => strlen($body),
        'looks_like_login' => false,
        'looks_like_dashboard' => false,
        'looks_like_edxeix' => false,
        'looks_like_form' => false,
        'form_count' => 0,
        'input_names' => [],
        'csrf_candidates' => [],
        'required_field_names' => [],
        'title_hint' => '',
        'raw_html_returned' => false,
    ];

    $out['looks_like_login'] = (
        strpos($lower, 'login') !== false ||
        strpos($lower, 'password') !== false ||
        strpos($body, 'Σύνδεση') !== false ||
        strpos($body, 'σύνδεση') !== false ||
        strpos($body, 'ΑΦΜ') !== false && strpos($lower, 'password') !== false
    );

    $out['looks_like_edxeix'] = (
        strpos($body, 'EDXEIX') !== false ||
        strpos($body, 'ΕΔΧ') !== false ||
        strpos($body, 'Ε.Δ.Χ') !== false ||
        strpos($body, 'ΕΛΛΗΝΙΚΗ ΔΗΜΟΚΡΑΤΙΑ') !== false ||
        strpos($body, 'Υπουργείο Υποδομών') !== false ||
        strpos($body, 'Καρτέλα') !== false ||
        strpos($body, 'Φορέα Διαμεσολάβησης') !== false ||
        strpos($body, 'Συμβάσεις ενοικίασης') !== false
    );

    $out['looks_like_dashboard'] = (
        strpos($lower, 'dashboard') !== false ||
        strpos($body, 'Διαχειριστικό') !== false ||
        strpos($body, 'Αποσύνδεση') !== false ||
        strpos($body, 'Καρτέλα Φορέα') !== false
    );

    if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $body, $m)) {
        $out['title_hint'] = trim(strip_tags($m[1]));
    }

    $out['form_count'] = preg_match_all('/<form\b/i', $body, $tmp);
    $out['looks_like_form'] = $out['form_count'] > 0;

    $names = [];
    foreach (['input','select','textarea'] as $tag) {
        if (preg_match_all('/<' . $tag . '\b[^>]*\bname=["\']([^"\']+)["\']/i', $body, $matches)) {
            foreach ($matches[1] as $name) {
                $name = trim((string)$name);
                if ($name !== '') $names[$name] = true;
            }
        }
    }
    $out['input_names'] = array_slice(array_keys($names), 0, 150);

    $csrfNeedles = ['_token','csrf','xsrf','requestverificationtoken','authenticity'];
    foreach ($out['input_names'] as $name) {
        foreach ($csrfNeedles as $needle) {
            if (stripos($name, $needle) !== false) {
                $out['csrf_candidates'][] = ['name' => $name, 'value' => '[redacted]'];
                break;
            }
        }
    }
    if (preg_match_all('/<meta\b[^>]*(?:name|property)=["\']([^"\']*(?:csrf|xsrf)[^"\']*)["\'][^>]*content=["\']([^"\']*)["\']/i', $body, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $m) {
            $out['csrf_candidates'][] = ['name' => $m[1], 'value' => '[redacted]'];
        }
    }

    $requiredHints = ['broker','lessor','lessee','driver','vehicle','starting','boarding','disembark','started','ended','price','drafted'];
    foreach ($out['input_names'] as $name) {
        foreach ($requiredHints as $hint) {
            if (stripos($name, $hint) !== false) {
                $out['required_field_names'][] = $name;
                break;
            }
        }
    }
    $out['required_field_names'] = array_values(array_unique($out['required_field_names']));

    return $out;
}

function erg_get_probe(string $url, array $selectedSession, bool $follow): array
{
    $out = [
        'requested' => true,
        'follow_redirects' => $follow,
        'ok' => false,
        'error' => null,
        'method' => 'GET',
        'initial_url_display' => erg_safe_url_display($url),
        'effective_url_display' => '',
        'used_cookie_header' => false,
        'http_status' => 0,
        'redirect_count' => 0,
        'redirect_chain' => [],
        'content_type' => '',
        'cookie_names_used' => [],
        'analysis' => [],
        'raw_html_returned' => false,
    ];

    if (!function_exists('curl_init')) {
        $out['error'] = 'PHP cURL extension is not available.';
        return $out;
    }
    if ($url === '') {
        $out['error'] = 'No target URL was available.';
        return $out;
    }

    $headers = [
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        'User-Agent: gov.cabnet.app-readonly-edxeix-redirect-get-probe/2.8',
    ];

    $json = is_array($selectedSession['_json'] ?? null) ? $selectedSession['_json'] : [];
    $pairs = erg_extract_cookie_pairs($json);
    if ($pairs) {
        $cookiePieces = [];
        foreach ($pairs as $name => $value) {
            $cookiePieces[] = $name . '=' . $value;
            $out['cookie_names_used'][] = '[redacted:' . $name . ']';
        }
        $headers[] = 'Cookie: ' . implode('; ', $cookiePieces);
        $out['used_cookie_header'] = true;
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_NOBODY => false,
        CURLOPT_FOLLOWLOCATION => $follow,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CUSTOMREQUEST => 'GET',
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    $response = curl_exec($ch);
    $err = curl_error($ch);
    $info = curl_getinfo($ch);
    curl_close($ch);

    $out['http_status'] = (int)($info['http_code'] ?? 0);
    $out['redirect_count'] = (int)($info['redirect_count'] ?? 0);
    $out['content_type'] = (string)($info['content_type'] ?? '');
    $out['effective_url_display'] = erg_safe_url_display((string)($info['url'] ?? ''));

    if ($response === false) {
        $out['error'] = 'GET failed: ' . $err;
        return $out;
    }

    $headerSize = (int)($info['header_size'] ?? 0);
    $headerText = substr((string)$response, 0, $headerSize);
    $body = substr((string)$response, $headerSize);

    $out['redirect_chain'] = erg_parse_redirect_chain($headerText, $url);
    $out['analysis'] = erg_analyze_html($body);
    $out['ok'] = $out['http_status'] >= 200 && $out['http_status'] < 400;
    return $out;
}

function erg_public_session(array $s): array
{
    if (!$s) return [];
    return [
        'basename' => $s['basename'] ?? '',
        'path_hint' => $s['path_hint'] ?? '',
        'readable' => !empty($s['readable']),
        'is_json' => !empty($s['is_json']),
        'json_keys' => $s['json_keys'] ?? [],
        'has_cookie_like_data' => !empty($s['has_cookie_like_data']),
        'has_token_like_data' => !empty($s['has_token_like_data']),
        'size_bytes' => $s['size_bytes'] ?? 0,
        'modified_at' => $s['modified_at'] ?? '',
    ];
}

$format = strtolower(trim((string)($_GET['format'] ?? 'html')));
$probeRequested = in_array(strtolower(trim((string)($_GET['probe'] ?? '0'))), ['1','true','yes','on'], true);
$followRedirects = in_array(strtolower(trim((string)($_GET['follow'] ?? '0'))), ['1','true','yes','on'], true);

$state = [
    'config_loaded' => false,
    'error' => null,
    'sessions' => [],
    'selected_session' => [],
    'target_candidates' => [],
    'selected_target_url_display' => '',
    'get_probe' => ['requested' => false],
];

try {
    $config = gov_bridge_load_config();
    if (!empty($config['app']['timezone'])) date_default_timezone_set((string)$config['app']['timezone']);
    $state['config_loaded'] = true;
    $state['sessions'] = erg_session_candidates($config);
    $state['selected_session'] = erg_select_session($state['sessions']);
    $state['target_candidates'] = erg_target_candidates($config, $state['selected_session']);
    $targetUrl = $state['target_candidates'][0] ?? '';
    $state['selected_target_url_display'] = erg_safe_url_display($targetUrl);

    if ($probeRequested) {
        $state['get_probe'] = erg_get_probe($targetUrl, $state['selected_session'], $followRedirects);
    }
} catch (Throwable $e) {
    $state['error'] = $e->getMessage();
}

$get = $state['get_probe'];
$analysis = is_array($get['analysis'] ?? null) ? $get['analysis'] : [];
$httpOk = !empty($get['ok']);
$looksEdxeix = !empty($analysis['looks_like_edxeix']);
$looksDashboard = !empty($analysis['looks_like_dashboard']);
$looksLogin = !empty($analysis['looks_like_login']);
$forms = (int)($analysis['form_count'] ?? 0) > 0;
$csrf = !empty($analysis['csrf_candidates']);

$decision = 'LOCAL_REDIRECT_PROBE_READY';
$type = 'warn';
$text = 'Local session and target metadata are ready. Run GET or GET+follow to inspect the EDXEIX route.';
if ($probeRequested) {
    if ($httpOk && $looksEdxeix && ($forms || $csrf)) {
        $decision = 'EDXEIX_FORM_SIGNALS_CONFIRMED_GET_ONLY';
        $type = 'good';
        $text = 'GET-only probe confirmed EDXEIX page/form/token signals. No POST was performed.';
    } elseif ($httpOk && ($looksEdxeix || $looksDashboard)) {
        $decision = 'EDXEIX_DASHBOARD_REACHABLE_FORM_NOT_CONFIRMED';
        $type = 'warn';
        $text = 'GET-only probe reached an EDXEIX-like/dashboard page, but the lease form/token was not confirmed.';
    } elseif ($httpOk && $looksLogin) {
        $decision = 'EDXEIX_SESSION_LIKELY_EXPIRED_OR_LOGIN_REQUIRED';
        $type = 'warn';
        $text = 'GET-only probe reached a login/session page. The saved EDXEIX session likely needs refresh.';
    } elseif ($httpOk && (int)($get['redirect_count'] ?? 0) > 0) {
        $decision = 'EDXEIX_REDIRECT_REACHED_FINAL_PAGE_UNCONFIRMED';
        $type = 'warn';
        $text = 'GET-only probe followed redirects and reached a final page, but EDXEIX/form signals were not confirmed.';
    } elseif ($httpOk) {
        $decision = 'EDXEIX_GET_REACHABLE_PAGE_UNCONFIRMED';
        $type = 'warn';
        $text = 'GET-only probe reached a page, but it did not clearly match EDXEIX/form/login signals.';
    } else {
        $decision = 'EDXEIX_GET_REDIRECT_PROBE_NOT_READY';
        $type = 'bad';
        $text = 'GET-only probe did not confirm EDXEIX route/session readiness.';
    }
} elseif (empty($state['target_candidates']) || empty($state['sessions'])) {
    $decision = 'LOCAL_TARGET_OR_SESSION_MISSING';
    $type = 'bad';
    $text = 'Target URL or saved session metadata is missing.';
}

$payload = [
    'ok' => $state['error'] === null,
    'script' => 'ops/edxeix-redirect-probe.php',
    'generated_at' => date('c'),
    'safety_contract' => [
        'calls_bolt' => false,
        'calls_edxeix_get' => $probeRequested,
        'follows_redirects' => $probeRequested && $followRedirects,
        'posts_to_edxeix' => false,
        'stages_jobs' => false,
        'updates_mappings' => false,
        'writes_database' => false,
        'writes_files' => false,
        'prints_secrets' => false,
        'raw_html_returned' => false,
        'live_edxeix_submission' => 'disabled_not_used',
    ],
    'decision' => ['code' => $decision, 'type' => $type, 'text' => $text],
    'checks' => [
        'config_loaded' => $state['config_loaded'],
        'curl_extension_loaded' => extension_loaded('curl'),
        'session_candidates_found' => count($state['sessions']),
        'selected_session_readable' => !empty($state['selected_session']['readable']),
        'selected_session_json' => !empty($state['selected_session']['is_json']),
        'selected_session_cookie_like_data' => !empty($state['selected_session']['has_cookie_like_data']),
        'target_candidates_found' => count($state['target_candidates']),
        'probe_requested' => $probeRequested,
        'follow_redirects' => $followRedirects,
        'probe_http_ok' => $httpOk,
        'probe_http_status' => $get['http_status'] ?? 0,
        'redirect_count' => $get['redirect_count'] ?? 0,
        'looks_like_edxeix' => $looksEdxeix,
        'looks_like_dashboard' => $looksDashboard,
        'looks_like_login' => $looksLogin,
        'forms_detected' => $forms,
        'csrf_detected' => $csrf,
    ],
    'selected_target_url_display' => $state['selected_target_url_display'],
    'target_candidates_display' => array_map('erg_safe_url_display', $state['target_candidates']),
    'selected_session' => erg_public_session($state['selected_session']),
    'session_candidates' => array_map('erg_public_session', $state['sessions']),
    'get_probe' => $get,
    'error' => $state['error'],
    'links' => [
        'html' => '/ops/edxeix-redirect-probe.php',
        'json' => '/ops/edxeix-redirect-probe.php?format=json',
        'run_get_no_follow' => '/ops/edxeix-redirect-probe.php?probe=1',
        'run_get_follow' => '/ops/edxeix-redirect-probe.php?probe=1&follow=1',
        'run_get_follow_json' => '/ops/edxeix-redirect-probe.php?probe=1&follow=1&format=json',
        'session_probe' => '/ops/edxeix-session-probe.php',
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
    <title>EDXEIX Redirect GET Probe | gov.cabnet.app</title>
    <link rel="stylesheet" href="/assets/css/gov-ops-edxeix.css?v=2.8">
</head>
<body>
<div class="gov-topbar">
    <div class="gov-brand"><div class="gov-brand-crest">ΕΔ</div><div class="gov-brand-text"><strong>gov.cabnet.app</strong><span>Bolt → EDXEIX operational console</span></div></div>
    <div class="gov-top-links"><a href="/ops/home.php">Αρχική</a><a href="/ops/edxeix-submit-readiness.php">Submit Readiness</a><a href="/ops/preflight-review.php">Preflight</a><a class="gov-logout" href="/ops/route-index.php">Route Index</a></div>
</div>
<div class="gov-shell">
    <aside class="gov-sidebar">
        <h3>Redirect Probe</h3><p>GET-only EDXEIX redirect/session check</p>
        <div class="gov-side-group">
            <div class="gov-side-group-title">Preparation</div>
            <a class="gov-side-link active" href="/ops/edxeix-redirect-probe.php">Redirect GET Probe</a>
            <a class="gov-side-link" href="/ops/edxeix-session-probe.php">Session/Form Probe</a>
            <a class="gov-side-link" href="/ops/edxeix-submit-readiness.php">Submit Readiness</a>
            <a class="gov-side-link" href="/ops/preflight-review.php">Preflight Review</a>
            <a class="gov-side-link" href="/ops/route-index.php">Route Index</a>
        </div>
        <div class="gov-side-note">GET only. Optional redirect following. No POST, no secrets, no writes.</div>
    </aside>
    <div class="gov-content">
        <div class="gov-page-header">
            <div><h1 class="gov-page-title">Έλεγχος ανακατευθύνσεων EDXEIX</h1><div class="gov-breadcrumbs">Αρχική / Διαχειριστικό / Έλεγχος ανακατευθύνσεων EDXEIX</div></div>
            <div class="gov-tabs">
                <a class="gov-tab active" href="/ops/edxeix-redirect-probe.php">Καρτέλα</a>
                <a class="gov-tab" href="/ops/edxeix-redirect-probe.php?format=json">JSON</a>
                <a class="gov-tab" href="/ops/edxeix-redirect-probe.php?probe=1">GET</a>
                <a class="gov-tab" href="/ops/edxeix-redirect-probe.php?probe=1&follow=1">GET + Follow</a>
            </div>
        </div>
        <main class="wrap wrap-shell">
            <section class="safety">
                <strong>GET-ONLY REDIRECT PROBE.</strong>
                Default load is local metadata only. Probe links use GET only. No POST, no live submission, no job staging, no database writes, and no raw HTML/cookies/tokens are displayed.
            </section>

            <section class="card hero <?= erg_h($type) ?>">
                <h1>EDXEIX Redirect-Follow GET Probe</h1>
                <p><?= erg_h($text) ?></p>
                <div>
                    <?= erg_badge($decision, $type) ?>
                    <?= erg_badge('NO POST', 'good') ?>
                    <?= erg_badge('LIVE SUBMIT OFF', 'good') ?>
                    <?= $probeRequested ? erg_badge($followRedirects ? 'GET + FOLLOW RAN' : 'GET RAN', 'warn') : erg_badge('GET NOT RUN BY DEFAULT', 'good') ?>
                </div>
                <div class="grid" style="margin-top:14px">
                    <?= erg_metric(count($state['sessions']), 'Session candidates') ?>
                    <?= erg_metric(count($state['target_candidates']), 'Target candidates') ?>
                    <?= erg_metric($probeRequested ? (string)($get['http_status'] ?? 0) : 'not run', 'HTTP status') ?>
                    <?= erg_metric($probeRequested ? (string)($get['redirect_count'] ?? 0) : 'not run', 'Redirect count') ?>
                </div>
                <div class="actions">
                    <a class="btn" href="/ops/edxeix-redirect-probe.php?probe=1">Run GET Only</a>
                    <a class="btn warn" href="/ops/edxeix-redirect-probe.php?probe=1&follow=1">Run GET + Follow Redirects</a>
                    <a class="btn dark" href="/ops/edxeix-redirect-probe.php?probe=1&follow=1&format=json">GET+Follow JSON</a>
                    <a class="btn good" href="/ops/edxeix-submit-readiness.php">Submit Readiness</a>
                </div>
            </section>

            <section class="two">
                <div class="card">
                    <h2>Local readiness</h2>
                    <div class="kv">
                        <div class="k">Config loaded</div><div><?= erg_yes($state['config_loaded']) ?></div>
                        <div class="k">cURL loaded</div><div><?= erg_yes(extension_loaded('curl')) ?></div>
                        <div class="k">Selected target</div><div><code><?= erg_h($state['selected_target_url_display']) ?></code></div>
                        <div class="k">Session candidates</div><div><strong><?= erg_h(count($state['sessions'])) ?></strong></div>
                        <div class="k">Selected session</div><div><code><?= erg_h((string)($state['selected_session']['basename'] ?? '')) ?></code></div>
                        <div class="k">Cookie-like data</div><div><?= erg_yes(!empty($state['selected_session']['has_cookie_like_data'])) ?></div>
                        <div class="k">Token-like data</div><div><?= !empty($state['selected_session']['has_token_like_data']) ? erg_badge('YES / redacted','warn') : erg_badge('NO','neutral') ?></div>
                    </div>
                </div>
                <div class="card">
                    <h2>Probe result</h2>
                    <div class="kv">
                        <div class="k">Probe requested</div><div><?= $probeRequested ? erg_badge('YES','warn') : erg_badge('NO','good') ?></div>
                        <div class="k">Follow redirects</div><div><?= $followRedirects ? erg_badge('YES','warn') : erg_badge('NO','good') ?></div>
                        <div class="k">HTTP OK</div><div><?= erg_yes($httpOk) ?></div>
                        <div class="k">HTTP status</div><div><strong><?= erg_h((string)($get['http_status'] ?? '')) ?></strong></div>
                        <div class="k">Effective URL</div><div><code><?= erg_h((string)($get['effective_url_display'] ?? '')) ?></code></div>
                        <div class="k">Looks EDXEIX</div><div><?= erg_yes($looksEdxeix) ?></div>
                        <div class="k">Looks dashboard</div><div><?= erg_yes($looksDashboard) ?></div>
                        <div class="k">Looks login</div><div><?= erg_warn($looksLogin) ?></div>
                        <div class="k">Forms</div><div><?= erg_yes($forms) ?></div>
                        <div class="k">CSRF/token names</div><div><?= erg_yes($csrf) ?></div>
                    </div>
                    <?php if (!empty($get['error'])): ?><p class="badline"><strong><?= erg_h($get['error']) ?></strong></p><?php endif; ?>
                </div>
            </section>

            <section class="card">
                <h2>Redirect chain and final page signals</h2>
                <?php if (!$probeRequested): ?>
                    <p>GET probe has not been run yet. Use <strong>Run GET + Follow Redirects</strong> to inspect the redirect chain safely.</p>
                <?php else: ?>
                    <p><strong>Raw HTML is not displayed.</strong> Cookie and token values are redacted.</p>
                    <div class="table-wrap">
                        <table>
                            <thead><tr><th>Item</th><th>Value</th></tr></thead>
                            <tbody>
                                <tr><td>Initial URL</td><td><code><?= erg_h((string)($get['initial_url_display'] ?? '')) ?></code></td></tr>
                                <tr><td>Effective URL</td><td><code><?= erg_h((string)($get['effective_url_display'] ?? '')) ?></code></td></tr>
                                <tr><td>Content type</td><td><?= erg_h((string)($get['content_type'] ?? '')) ?></td></tr>
                                <tr><td>Title hint</td><td><?= erg_h((string)($analysis['title_hint'] ?? '')) ?></td></tr>
                                <tr><td>Body bytes</td><td><?= erg_h((string)($analysis['body_bytes'] ?? 0)) ?></td></tr>
                                <tr><td>Form count</td><td><?= erg_h((string)($analysis['form_count'] ?? 0)) ?></td></tr>
                                <tr><td>Input names</td><td><?= erg_h(implode(', ', array_slice((array)($analysis['input_names'] ?? []), 0, 120))) ?></td></tr>
                                <tr><td>CSRF candidates</td><td><?= erg_h(implode(', ', array_map(static fn($r) => (string)($r['name'] ?? ''), (array)($analysis['csrf_candidates'] ?? [])))) ?></td></tr>
                                <tr><td>Required-field hints</td><td><?= erg_h(implode(', ', (array)($analysis['required_field_names'] ?? []))) ?></td></tr>
                            </tbody>
                        </table>
                    </div>

                    <h3>Redirect chain</h3>
                    <div class="table-wrap">
                        <table>
                            <thead><tr><th>#</th><th>Status</th><th>From</th><th>Location</th></tr></thead>
                            <tbody>
                            <?php foreach ((array)($get['redirect_chain'] ?? []) as $idx => $hop): ?>
                                <tr>
                                    <td><?= erg_h((string)($idx + 1)) ?></td>
                                    <td><?= erg_h((string)($hop['status'] ?? '')) ?></td>
                                    <td><code><?= erg_h((string)($hop['from'] ?? '')) ?></code></td>
                                    <td><code><?= erg_h((string)($hop['location'] ?? '')) ?></code></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($get['redirect_chain'])): ?>
                                <tr><td colspan="4">No redirect chain captured.</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>

            <section class="card">
                <h2>Next decision</h2>
                <ol class="timeline">
                    <li>If GET+follow confirms dashboard but not form, we need the exact EDXEIX form GET URL from the browser/session capture.</li>
                    <li>If GET+follow lands at login, refresh the saved EDXEIX session using the browser extension before any live-submit test.</li>
                    <li>If form and CSRF names are confirmed, the next patch can design the final submit handler, still blocked behind explicit approval and candidate eligibility.</li>
                    <li>Do not submit completed/historical/cancelled rows.</li>
                </ol>
            </section>
        </main>
    </div>
</div>
</body>
</html>
