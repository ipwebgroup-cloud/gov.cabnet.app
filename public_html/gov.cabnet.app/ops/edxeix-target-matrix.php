<?php
/**
 * gov.cabnet.app — EDXEIX Target URL Probe Matrix v2.9
 *
 * Purpose:
 * - Probe all known EDXEIX target URL candidates with GET-only requests.
 * - Classify each URL as login/dashboard/form/unconfirmed.
 * - Identify which URL, if any, reaches the authenticated lease-agreement form layer.
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

function etm_h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function etm_badge(string $text, string $type = 'neutral'): string { return '<span class="badge badge-' . etm_h($type) . '">' . etm_h($text) . '</span>'; }
function etm_yes(bool $v, string $yes = 'YES', string $no = 'NO'): string { return $v ? etm_badge($yes, 'good') : etm_badge($no, 'bad'); }
function etm_warn(bool $v, string $yes = 'YES', string $no = 'NO'): string { return $v ? etm_badge($yes, 'warn') : etm_badge($no, 'good'); }
function etm_metric($value, string $label): string { return '<div class="metric"><strong>' . etm_h((string)$value) . '</strong><span>' . etm_h($label) . '</span></div>'; }

function etm_safe_url_display(string $url): string
{
    $url = trim($url);
    if ($url === '') return '';
    $parts = parse_url($url);
    if (!is_array($parts)) return '[configured]';
    $scheme = $parts['scheme'] ?? 'https';
    $host = $parts['host'] ?? '';
    $path = $parts['path'] ?? '';
    if ($host === '') return '[configured]';
    return $scheme . '://' . $host . $path;
}

function etm_join_url(string $base, string $path): string
{
    $base = rtrim($base, '/');
    $path = trim($path);
    if ($path === '') return $base;
    if (preg_match('#^https?://#i', $path)) return $path;
    if ($path[0] !== '/') $path = '/' . $path;
    return $base . $path;
}

function etm_json_has_key_like($json, array $needles): bool
{
    if (!is_array($json)) return false;
    foreach ($json as $key => $value) {
        foreach ($needles as $needle) {
            if (stripos((string)$key, $needle) !== false && $value !== '') return true;
        }
        if (is_array($value) && etm_json_has_key_like($value, $needles)) return true;
    }
    return false;
}

function etm_extract_cookie_pairs($json): array
{
    $pairs = [];
    if (!is_array($json)) return $pairs;

    $add = static function ($name, $value) use (&$pairs): void {
        $name = trim((string)$name);
        $value = (string)$value;
        if ($name !== '' && $value !== '') $pairs[$name] = $value;
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
        if (!empty($json[$key])) $add($key, $json[$key]);
    }

    foreach ($json as $value) {
        if (is_array($value)) {
            foreach (etm_extract_cookie_pairs($value) as $k => $v) $pairs[$k] = $v;
        }
    }

    return $pairs;
}

function etm_candidate_dirs(array $config): array
{
    $paths = function_exists('gov_bridge_paths') ? gov_bridge_paths() : [];
    $dirs = [];
    foreach (['runtime','storage','artifacts'] as $key) {
        if (!empty($paths[$key]) && is_dir((string)$paths[$key])) $dirs[] = (string)$paths[$key];
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

function etm_session_candidates(array $config): array
{
    $files = [];
    foreach (etm_candidate_dirs($config) as $dir) {
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
                            $keys = array_slice(array_keys($tmp), 0, 25);
                        }
                    }
                }
                $files[$real] = [
                    'basename' => basename($file),
                    'path_hint' => dirname($file),
                    'readable' => is_readable($file),
                    'is_json' => is_array($json),
                    'json_keys' => $keys,
                    'has_cookie_like_data' => etm_json_has_key_like($json, ['cookie', 'session', 'xsrf']),
                    'has_token_like_data' => etm_json_has_key_like($json, ['token', 'csrf', 'xsrf']),
                    'size_bytes' => (int)@filesize($file),
                    'modified_at' => date('Y-m-d H:i:s', (int)@filemtime($file)),
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

function etm_select_session(array $sessions): array
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

function etm_target_candidates(array $config, array $session): array
{
    $edx = is_array($config['edxeix'] ?? null) ? $config['edxeix'] : [];
    $json = is_array($session['_json'] ?? null) ? $session['_json'] : [];
    $base = !empty($edx['base_url']) && is_string($edx['base_url']) ? rtrim((string)$edx['base_url'], '/') : 'https://edxeix.yme.gov.gr';
    $lessor = (string)($edx['lessor_id'] ?? '3814');

    $raw = [];

    foreach (['form_url','submit_url','dashboard_url','login_url','base_url'] as $key) {
        if (!empty($edx[$key]) && is_string($edx[$key])) {
            $raw[] = ['source' => 'config:' . $key, 'url' => (string)$edx[$key]];
        }
    }

    foreach (['source_url','detected_form_action','fixed_submit_url_used','form_action','action'] as $key) {
        if (!empty($json[$key]) && is_string($json[$key])) {
            $raw[] = ['source' => 'session:' . $key, 'url' => (string)$json[$key]];
        }
    }

    $common = [
        ['source' => 'common:base', 'url' => $base],
        ['source' => 'common:dashboard', 'url' => $base . '/dashboard'],
        ['source' => 'common:broker', 'url' => $base . '/dashboard/broker/' . rawurlencode($lessor)],
        ['source' => 'common:lease-index', 'url' => $base . '/dashboard/lease-agreement'],
        ['source' => 'common:lease-create', 'url' => $base . '/dashboard/lease-agreement/create'],
        ['source' => 'common:lease-store', 'url' => $base . '/dashboard/lease-agreement/store'],
        ['source' => 'common:lease-new', 'url' => $base . '/dashboard/lease-agreement/new'],
    ];

    $raw = array_merge($raw, $common);

    $out = [];
    foreach ($raw as $item) {
        $url = trim((string)$item['url']);
        if ($url === '') continue;
        if (strpos($url, '/') === 0) $url = etm_join_url($base, $url);
        if (!preg_match('#^https?://#i', $url)) continue;
        $key = etm_safe_url_display($url);
        if (!isset($out[$key])) {
            $out[$key] = [
                'source' => (string)$item['source'],
                'url' => $url,
                'url_display' => $key,
            ];
        } else {
            $out[$key]['source'] .= ', ' . (string)$item['source'];
        }
    }

    return array_values($out);
}

function etm_parse_redirect_chain(string $headers, string $initialUrl): array
{
    $blocks = preg_split("/\r\n\r\n|\n\n|\r\r/", trim($headers));
    $chain = [];
    $current = $initialUrl;

    foreach ($blocks as $block) {
        if (trim($block) === '') continue;
        $status = 0;
        $location = '';

        if (preg_match('/HTTP\/[0-9.]+\s+([0-9]+)/i', $block, $m)) $status = (int)$m[1];
        if (preg_match('/^Location:\s*(.+)$/im', $block, $m)) $location = trim($m[1]);

        if ($status > 0) {
            $chain[] = [
                'status' => $status,
                'from' => etm_safe_url_display($current),
                'location' => etm_safe_url_display($location),
            ];
        }

        if ($location !== '') $current = $location;
    }

    return $chain;
}

function etm_analyze_html(string $body): array
{
    $lower = strtolower($body);
    $out = [
        'body_bytes' => strlen($body),
        'title_hint' => '',
        'looks_like_login' => false,
        'looks_like_edxeix' => false,
        'looks_like_dashboard' => false,
        'looks_like_lease' => false,
        'looks_like_form' => false,
        'form_count' => 0,
        'input_names' => [],
        'csrf_candidates' => [],
        'required_field_names' => [],
        'classification' => 'PAGE_UNCONFIRMED',
        'raw_html_returned' => false,
    ];

    if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $body, $m)) {
        $out['title_hint'] = trim(strip_tags($m[1]));
    }

    $out['looks_like_login'] = (
        strpos($lower, 'login') !== false ||
        strpos($lower, 'password') !== false ||
        strpos($body, 'Σύνδεση') !== false ||
        strpos($body, 'σύνδεση') !== false
    );

    $out['looks_like_edxeix'] = (
        strpos($body, 'EDXEIX') !== false ||
        strpos($body, 'ΕΔΧ') !== false ||
        strpos($body, 'Ε.Δ.Χ') !== false ||
        strpos($body, 'ΕΛΛΗΝΙΚΗ ΔΗΜΟΚΡΑΤΙΑ') !== false ||
        strpos($body, 'Υπουργείο Υποδομών') !== false ||
        strpos($body, 'Φορέα Διαμεσολάβησης') !== false ||
        strpos($body, 'συμβάσεων Ε.Ι.Χ') !== false ||
        strpos($body, 'Συμβάσεις ενοικίασης') !== false
    );

    $out['looks_like_dashboard'] = (
        strpos($lower, 'dashboard') !== false ||
        strpos($body, 'Διαχειριστικό') !== false ||
        strpos($body, 'Αποσύνδεση') !== false ||
        strpos($body, 'Καρτέλα Φορέα') !== false ||
        strpos($body, 'Στοιχεία Φορέα') !== false
    );

    $out['looks_like_lease'] = (
        strpos($body, 'Σύμβαση') !== false ||
        strpos($body, 'Συμβάσεις') !== false ||
        strpos($lower, 'lease') !== false ||
        strpos($lower, 'agreement') !== false ||
        strpos($body, 'μίσθωσης') !== false ||
        strpos($body, 'ενοικίασης') !== false
    );

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
    $out['input_names'] = array_slice(array_keys($names), 0, 180);

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

    $requiredHints = ['broker','lessor','lessee','driver','vehicle','starting','boarding','disembark','started','ended','price','drafted','start','end'];
    foreach ($out['input_names'] as $name) {
        foreach ($requiredHints as $hint) {
            if (stripos($name, $hint) !== false) {
                $out['required_field_names'][] = $name;
                break;
            }
        }
    }
    $out['required_field_names'] = array_values(array_unique($out['required_field_names']));

    if ($out['looks_like_edxeix'] && $out['looks_like_lease'] && $out['looks_like_form'] && !empty($out['csrf_candidates'])) {
        $out['classification'] = 'LEASE_FORM_CANDIDATE';
    } elseif ($out['looks_like_edxeix'] && $out['looks_like_form'] && !empty($out['csrf_candidates'])) {
        $out['classification'] = 'EDXEIX_FORM_CANDIDATE';
    } elseif ($out['looks_like_login']) {
        $out['classification'] = 'LOGIN_OR_SESSION_PAGE';
    } elseif ($out['looks_like_edxeix'] && $out['looks_like_dashboard']) {
        $out['classification'] = 'EDXEIX_DASHBOARD';
    } elseif ($out['looks_like_edxeix']) {
        $out['classification'] = 'EDXEIX_PUBLIC_OR_SHELL';
    }

    return $out;
}

function etm_probe_one(array $target, array $session, bool $follow): array
{
    $url = (string)$target['url'];
    $out = [
        'source' => $target['source'],
        'url_display' => $target['url_display'],
        'requested' => true,
        'follow_redirects' => $follow,
        'ok' => false,
        'error' => null,
        'method' => 'GET',
        'http_status' => 0,
        'redirect_count' => 0,
        'effective_url_display' => '',
        'content_type' => '',
        'used_cookie_header' => false,
        'cookie_names_used' => [],
        'redirect_chain' => [],
        'analysis' => [],
        'raw_html_returned' => false,
    ];

    if (!function_exists('curl_init')) {
        $out['error'] = 'PHP cURL extension is not available.';
        return $out;
    }

    $headers = [
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        'User-Agent: gov.cabnet.app-readonly-edxeix-target-matrix/2.9',
    ];

    $json = is_array($session['_json'] ?? null) ? $session['_json'] : [];
    $pairs = etm_extract_cookie_pairs($json);
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
    $out['effective_url_display'] = etm_safe_url_display((string)($info['url'] ?? ''));
    $out['content_type'] = (string)($info['content_type'] ?? '');

    if ($response === false) {
        $out['error'] = 'GET failed: ' . $err;
        return $out;
    }

    $headerSize = (int)($info['header_size'] ?? 0);
    $headersRaw = substr((string)$response, 0, $headerSize);
    $body = substr((string)$response, $headerSize);

    $out['redirect_chain'] = etm_parse_redirect_chain($headersRaw, $url);
    $out['analysis'] = etm_analyze_html($body);
    $out['ok'] = $out['http_status'] >= 200 && $out['http_status'] < 400;

    return $out;
}

function etm_public_session(array $s): array
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
$followRedirects = in_array(strtolower(trim((string)($_GET['follow'] ?? '1'))), ['1','true','yes','on'], true);
$maxTargets = max(1, min(12, (int)($_GET['max'] ?? 10)));

$state = [
    'config_loaded' => false,
    'error' => null,
    'sessions' => [],
    'selected_session' => [],
    'targets' => [],
    'results' => [],
];

try {
    $config = gov_bridge_load_config();
    if (!empty($config['app']['timezone'])) date_default_timezone_set((string)$config['app']['timezone']);
    $state['config_loaded'] = true;
    $state['sessions'] = etm_session_candidates($config);
    $state['selected_session'] = etm_select_session($state['sessions']);
    $state['targets'] = array_slice(etm_target_candidates($config, $state['selected_session']), 0, $maxTargets);

    if ($probeRequested) {
        foreach ($state['targets'] as $target) {
            $state['results'][] = etm_probe_one($target, $state['selected_session'], $followRedirects);
        }
    }
} catch (Throwable $e) {
    $state['error'] = $e->getMessage();
}

$classificationCounts = [];
$best = null;
foreach ($state['results'] as $result) {
    $classification = (string)($result['analysis']['classification'] ?? 'NOT_PROBED');
    $classificationCounts[$classification] = ($classificationCounts[$classification] ?? 0) + 1;

    if ($best === null) {
        $best = $result;
        continue;
    }

    $rank = [
        'LEASE_FORM_CANDIDATE' => 100,
        'EDXEIX_FORM_CANDIDATE' => 90,
        'EDXEIX_DASHBOARD' => 70,
        'EDXEIX_PUBLIC_OR_SHELL' => 50,
        'LOGIN_OR_SESSION_PAGE' => 30,
        'PAGE_UNCONFIRMED' => 10,
    ];

    $currentScore = $rank[(string)($result['analysis']['classification'] ?? 'PAGE_UNCONFIRMED')] ?? 0;
    $bestScore = $rank[(string)($best['analysis']['classification'] ?? 'PAGE_UNCONFIRMED')] ?? 0;
    if ($currentScore > $bestScore) $best = $result;
}

$bestClass = $best ? (string)($best['analysis']['classification'] ?? 'PAGE_UNCONFIRMED') : '';
$leaseFormFound = $bestClass === 'LEASE_FORM_CANDIDATE';
$formFound = in_array($bestClass, ['LEASE_FORM_CANDIDATE', 'EDXEIX_FORM_CANDIDATE'], true);
$loginOnly = !$formFound && (($classificationCounts['LOGIN_OR_SESSION_PAGE'] ?? 0) > 0);
$dashboardFound = !$formFound && (($classificationCounts['EDXEIX_DASHBOARD'] ?? 0) > 0);

$decision = 'LOCAL_TARGET_MATRIX_READY';
$type = 'warn';
$text = 'Local EDXEIX target candidates are ready. Run the GET-only matrix probe.';
if ($probeRequested) {
    if ($leaseFormFound) {
        $decision = 'LEASE_FORM_TARGET_CONFIRMED_GET_ONLY';
        $type = 'good';
        $text = 'GET-only matrix found a likely authenticated lease-agreement form target. No POST was performed.';
    } elseif ($formFound) {
        $decision = 'EDXEIX_FORM_TARGET_CONFIRMED_GET_ONLY';
        $type = 'good';
        $text = 'GET-only matrix found an EDXEIX form target, but lease-specific fields should still be verified.';
    } elseif ($dashboardFound) {
        $decision = 'EDXEIX_DASHBOARD_REACHABLE_FORM_NOT_FOUND';
        $type = 'warn';
        $text = 'GET-only matrix reached an EDXEIX dashboard-like page but did not find the lease form.';
    } elseif ($loginOnly) {
        $decision = 'EDXEIX_TARGETS_RESOLVE_TO_LOGIN_OR_SESSION_SHELL';
        $type = 'warn';
        $text = 'GET-only matrix mainly reached login/session/public shell pages. Session or exact form URL likely needs refresh.';
    } else {
        $decision = 'EDXEIX_TARGET_MATRIX_NO_FORM_CONFIRMED';
        $type = 'bad';
        $text = 'GET-only matrix did not confirm dashboard or lease form access.';
    }
} elseif (empty($state['targets']) || empty($state['sessions'])) {
    $decision = 'LOCAL_TARGET_OR_SESSION_MISSING';
    $type = 'bad';
    $text = 'Saved session metadata or EDXEIX target candidates are missing.';
}

$payload = [
    'ok' => $state['error'] === null,
    'script' => 'ops/edxeix-target-matrix.php',
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
        'target_candidates_found' => count($state['targets']),
        'probe_requested' => $probeRequested,
        'follow_redirects' => $followRedirects,
        'targets_probed' => count($state['results']),
        'lease_form_found' => $leaseFormFound,
        'form_found' => $formFound,
        'dashboard_found' => $dashboardFound,
        'login_or_session_seen' => $loginOnly || (($classificationCounts['LOGIN_OR_SESSION_PAGE'] ?? 0) > 0),
    ],
    'classification_counts' => $classificationCounts,
    'selected_session' => etm_public_session($state['selected_session']),
    'targets' => $state['targets'],
    'best_candidate' => $best,
    'results' => $state['results'],
    'error' => $state['error'],
    'links' => [
        'html' => '/ops/edxeix-target-matrix.php',
        'json' => '/ops/edxeix-target-matrix.php?format=json',
        'run_matrix' => '/ops/edxeix-target-matrix.php?probe=1&follow=1',
        'run_matrix_json' => '/ops/edxeix-target-matrix.php?probe=1&follow=1&format=json',
        'redirect_probe' => '/ops/edxeix-redirect-probe.php',
        'session_probe' => '/ops/edxeix-session-probe.php',
        'submit_readiness' => '/ops/edxeix-submit-readiness.php',
        'route_index' => '/ops/route-index.php',
    ],
];

if ($format === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit;
}

function etm_class_badge(string $class): string
{
    if ($class === 'LEASE_FORM_CANDIDATE') return etm_badge($class, 'good');
    if ($class === 'EDXEIX_FORM_CANDIDATE') return etm_badge($class, 'good');
    if ($class === 'EDXEIX_DASHBOARD') return etm_badge($class, 'warn');
    if ($class === 'LOGIN_OR_SESSION_PAGE') return etm_badge($class, 'warn');
    if ($class === 'EDXEIX_PUBLIC_OR_SHELL') return etm_badge($class, 'neutral');
    return etm_badge($class ?: 'NOT_PROBED', 'bad');
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>EDXEIX Target Matrix | gov.cabnet.app</title>
    <link rel="stylesheet" href="/assets/css/gov-ops-edxeix.css?v=2.9">
</head>
<body>
<div class="gov-topbar">
    <div class="gov-brand"><div class="gov-brand-crest">ΕΔ</div><div class="gov-brand-text"><strong>gov.cabnet.app</strong><span>Bolt → EDXEIX operational console</span></div></div>
    <div class="gov-top-links"><a href="/ops/home.php">Αρχική</a><a href="/ops/edxeix-submit-readiness.php">Submit Readiness</a><a href="/ops/preflight-review.php">Preflight</a><a class="gov-logout" href="/ops/route-index.php">Route Index</a></div>
</div>
<div class="gov-shell">
    <aside class="gov-sidebar">
        <h3>Target Matrix</h3><p>GET-only EDXEIX URL classification</p>
        <div class="gov-side-group">
            <div class="gov-side-group-title">Preparation</div>
            <a class="gov-side-link active" href="/ops/edxeix-target-matrix.php">Target Matrix</a>
            <a class="gov-side-link" href="/ops/edxeix-redirect-probe.php">Redirect Probe</a>
            <a class="gov-side-link" href="/ops/edxeix-session-probe.php">Session/Form Probe</a>
            <a class="gov-side-link" href="/ops/edxeix-submit-readiness.php">Submit Readiness</a>
            <a class="gov-side-link" href="/ops/route-index.php">Route Index</a>
        </div>
        <div class="gov-side-note">GET-only matrix. No POST, no secrets, no writes, no live submit.</div>
    </aside>
    <div class="gov-content">
        <div class="gov-page-header">
            <div><h1 class="gov-page-title">Πίνακας ελέγχου URL EDXEIX</h1><div class="gov-breadcrumbs">Αρχική / Διαχειριστικό / Πίνακας ελέγχου URL EDXEIX</div></div>
            <div class="gov-tabs">
                <a class="gov-tab active" href="/ops/edxeix-target-matrix.php">Καρτέλα</a>
                <a class="gov-tab" href="/ops/edxeix-target-matrix.php?format=json">JSON</a>
                <a class="gov-tab" href="/ops/edxeix-target-matrix.php?probe=1&follow=1">Run Matrix</a>
                <a class="gov-tab" href="/ops/edxeix-target-matrix.php?probe=1&follow=1&format=json">Matrix JSON</a>
            </div>
        </div>
        <main class="wrap wrap-shell">
            <section class="safety">
                <strong>GET-ONLY TARGET MATRIX.</strong>
                Default load is local metadata only. The matrix probe uses GET only across known target candidates. No POST, no live submit, no job staging, no DB/file writes, and no raw HTML/cookie/token values are displayed.
            </section>

            <section class="card hero <?= etm_h($type) ?>">
                <h1>EDXEIX Target URL Probe Matrix</h1>
                <p><?= etm_h($text) ?></p>
                <div>
                    <?= etm_badge($decision, $type) ?>
                    <?= etm_badge('NO POST', 'good') ?>
                    <?= etm_badge('LIVE SUBMIT OFF', 'good') ?>
                    <?= $probeRequested ? etm_badge('MATRIX RAN', 'warn') : etm_badge('NOT RUN BY DEFAULT', 'good') ?>
                </div>
                <div class="grid" style="margin-top:14px">
                    <?= etm_metric(count($state['targets']), 'Target candidates') ?>
                    <?= etm_metric(count($state['results']), 'Targets probed') ?>
                    <?= etm_metric($leaseFormFound ? 'yes' : 'no', 'Lease form found') ?>
                    <?= etm_metric($formFound ? 'yes' : 'no', 'Any form found') ?>
                </div>
                <div class="actions">
                    <a class="btn warn" href="/ops/edxeix-target-matrix.php?probe=1&follow=1">Run GET Matrix</a>
                    <a class="btn dark" href="/ops/edxeix-target-matrix.php?probe=1&follow=1&format=json">Run Matrix JSON</a>
                    <a class="btn" href="/ops/edxeix-redirect-probe.php">Redirect Probe</a>
                    <a class="btn good" href="/ops/edxeix-submit-readiness.php">Submit Readiness</a>
                </div>
            </section>

            <section class="two">
                <div class="card">
                    <h2>Local readiness</h2>
                    <div class="kv">
                        <div class="k">Config loaded</div><div><?= etm_yes($state['config_loaded']) ?></div>
                        <div class="k">cURL loaded</div><div><?= etm_yes(extension_loaded('curl')) ?></div>
                        <div class="k">Session candidates</div><div><strong><?= etm_h(count($state['sessions'])) ?></strong></div>
                        <div class="k">Selected session</div><div><code><?= etm_h((string)($state['selected_session']['basename'] ?? '')) ?></code></div>
                        <div class="k">Cookie-like data</div><div><?= etm_yes(!empty($state['selected_session']['has_cookie_like_data'])) ?></div>
                        <div class="k">Token-like data</div><div><?= !empty($state['selected_session']['has_token_like_data']) ? etm_badge('YES / redacted','warn') : etm_badge('NO','neutral') ?></div>
                    </div>
                </div>
                <div class="card">
                    <h2>Classification counts</h2>
                    <?php if (!$probeRequested): ?>
                        <p>Matrix has not run yet.</p>
                    <?php else: ?>
                        <div class="actions">
                        <?php foreach ($classificationCounts as $class => $count): ?>
                            <?= etm_class_badge((string)$class) ?> <strong><?= etm_h((string)$count) ?></strong>
                        <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <section class="card">
                <h2>Target candidates</h2>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>#</th><th>Source</th><th>URL</th></tr></thead>
                        <tbody>
                        <?php foreach ($state['targets'] as $idx => $target): ?>
                            <tr>
                                <td><?= etm_h((string)($idx + 1)) ?></td>
                                <td><?= etm_h((string)$target['source']) ?></td>
                                <td><code><?= etm_h((string)$target['url_display']) ?></code></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="card">
                <h2>Matrix results</h2>
                <?php if (!$probeRequested): ?>
                    <p>Click <strong>Run GET Matrix</strong> to classify all target candidates safely.</p>
                <?php else: ?>
                    <p><strong>Raw HTML is not displayed.</strong> Cookie and token values are redacted.</p>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>URL</th>
                                    <th>Status</th>
                                    <th>Redirects</th>
                                    <th>Effective URL</th>
                                    <th>Classification</th>
                                    <th>Login</th>
                                    <th>Dashboard</th>
                                    <th>Lease</th>
                                    <th>Forms</th>
                                    <th>CSRF</th>
                                    <th>Title</th>
                                    <th>Field hints</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($state['results'] as $result): $a = is_array($result['analysis'] ?? null) ? $result['analysis'] : []; ?>
                                <tr>
                                    <td><code><?= etm_h((string)$result['url_display']) ?></code><br><span class="small"><?= etm_h((string)$result['source']) ?></span></td>
                                    <td><?= etm_h((string)$result['http_status']) ?></td>
                                    <td><?= etm_h((string)$result['redirect_count']) ?></td>
                                    <td><code><?= etm_h((string)$result['effective_url_display']) ?></code></td>
                                    <td><?= etm_class_badge((string)($a['classification'] ?? '')) ?></td>
                                    <td><?= !empty($a['looks_like_login']) ? etm_badge('YES','warn') : etm_badge('NO','good') ?></td>
                                    <td><?= !empty($a['looks_like_dashboard']) ? etm_badge('YES','good') : etm_badge('NO','neutral') ?></td>
                                    <td><?= !empty($a['looks_like_lease']) ? etm_badge('YES','good') : etm_badge('NO','neutral') ?></td>
                                    <td><?= !empty($a['form_count']) ? etm_badge((string)$a['form_count'],'good') : etm_badge('0','neutral') ?></td>
                                    <td><?= !empty($a['csrf_candidates']) ? etm_badge('YES','good') : etm_badge('NO','neutral') ?></td>
                                    <td><?= etm_h((string)($a['title_hint'] ?? '')) ?></td>
                                    <td><?= etm_h(implode(', ', array_slice((array)($a['required_field_names'] ?? []), 0, 20))) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>

            <section class="card">
                <h2>Best candidate</h2>
                <?php if (!$best): ?>
                    <p>No target has been probed yet.</p>
                <?php else: $ba = is_array($best['analysis'] ?? null) ? $best['analysis'] : []; ?>
                    <div class="kv">
                        <div class="k">Classification</div><div><?= etm_class_badge((string)($ba['classification'] ?? '')) ?></div>
                        <div class="k">URL</div><div><code><?= etm_h((string)($best['url_display'] ?? '')) ?></code></div>
                        <div class="k">Effective URL</div><div><code><?= etm_h((string)($best['effective_url_display'] ?? '')) ?></code></div>
                        <div class="k">HTTP status</div><div><?= etm_h((string)($best['http_status'] ?? '')) ?></div>
                        <div class="k">Title</div><div><?= etm_h((string)($ba['title_hint'] ?? '')) ?></div>
                        <div class="k">Forms</div><div><?= etm_h((string)($ba['form_count'] ?? '0')) ?></div>
                        <div class="k">CSRF names</div><div><?= etm_h(implode(', ', array_map(static fn($r) => (string)($r['name'] ?? ''), (array)($ba['csrf_candidates'] ?? [])))) ?></div>
                        <div class="k">Required-field hints</div><div><?= etm_h(implode(', ', (array)($ba['required_field_names'] ?? []))) ?></div>
                    </div>
                <?php endif; ?>
            </section>

            <section class="card">
                <h2>Next decision</h2>
                <ol class="timeline">
                    <li>If a <strong>LEASE_FORM_CANDIDATE</strong> appears, we can design the final submit handler, still blocked behind eligibility + explicit approval.</li>
                    <li>If only login/session pages appear, refresh the EDXEIX session with the browser extension.</li>
                    <li>If dashboard appears but no form, capture the exact lease form GET URL/action from the browser.</li>
                    <li>Do not POST or submit until a real future-safe Bolt candidate exists and you explicitly approve.</li>
                </ol>
            </section>
        </main>
    </div>
</div>
</body>
</html>
