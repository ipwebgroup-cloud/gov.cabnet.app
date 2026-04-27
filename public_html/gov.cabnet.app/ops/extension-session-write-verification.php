<?php
/**
 * gov.cabnet.app — Extension Session Write Verification v3.1
 *
 * Purpose:
 * - Verify whether the Firefox extension has actually updated edxeix_session.json recently.
 * - Show safe session metadata only.
 *
 * Safety contract:
 * - Does not call Bolt.
 * - Does not call EDXEIX.
 * - Does not POST.
 * - Does not read/write database.
 * - Does not stage jobs.
 * - Does not update mappings.
 * - Does not write files.
 * - Does not print cookies, token values, session secrets, or raw JSON contents.
 * - Live EDXEIX submission remains disabled.
 */

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Robots-Tag: noindex,nofollow', true);

require_once '/home/cabnet/gov.cabnet.app_app/lib/bolt_sync_lib.php';

function ev_h($v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function ev_badge(string $text, string $type = 'neutral'): string
{
    return '<span class="badge badge-' . ev_h($type) . '">' . ev_h($text) . '</span>';
}

function ev_yes(bool $v, string $yes = 'YES', string $no = 'NO'): string
{
    return $v ? ev_badge($yes, 'good') : ev_badge($no, 'bad');
}

function ev_metric($value, string $label): string
{
    return '<div class="metric"><strong>' . ev_h((string)$value) . '</strong><span>' . ev_h($label) . '</span></div>';
}

function ev_safe_url_display($url): string
{
    $url = trim((string)$url);
    if ($url === '') {
        return '';
    }
    $parts = parse_url($url);
    if (!is_array($parts)) {
        return '[configured]';
    }
    $scheme = $parts['scheme'] ?? 'https';
    $host = $parts['host'] ?? '';
    $path = $parts['path'] ?? '';
    if ($host === '') {
        return '[configured]';
    }
    return $scheme . '://' . $host . $path;
}

function ev_load_session_file(string $path): array
{
    $out = [
        'exists' => is_file($path),
        'readable' => is_readable($path),
        'basename' => basename($path),
        'path_hint' => dirname($path),
        'size_bytes' => is_file($path) ? (int)@filesize($path) : 0,
        'modified_at' => is_file($path) ? date('Y-m-d H:i:s', (int)@filemtime($path)) : '',
        'modified_ts' => is_file($path) ? (int)@filemtime($path) : 0,
        'json_valid' => false,
        'json_keys' => [],
        'safe_metadata' => [],
        'cookie_like_present' => false,
        'token_like_present' => false,
        'error' => null,
    ];

    if (!$out['exists']) {
        $out['error'] = 'Session file does not exist.';
        return $out;
    }
    if (!$out['readable']) {
        $out['error'] = 'Session file is not readable.';
        return $out;
    }

    $raw = @file_get_contents($path);
    if (!is_string($raw) || trim($raw) === '') {
        $out['error'] = 'Session file is empty or unreadable.';
        return $out;
    }

    $json = json_decode($raw, true);
    if (!is_array($json)) {
        $out['error'] = 'Session file is not valid JSON.';
        return $out;
    }

    $out['json_valid'] = true;
    $out['json_keys'] = array_slice(array_keys($json), 0, 50);
    $out['cookie_like_present'] = ev_has_key_like($json, ['cookie', 'session', 'xsrf']);
    $out['token_like_present'] = ev_has_key_like($json, ['token', 'csrf', 'xsrf']);

    $safeKeys = [
        'saved_at',
        'updated_at',
        'last_refreshed_at',
        'source',
        'source_url',
        'detected_form_action',
        'fixed_submit_url_used',
        'extension_version',
        'note',
        'notes',
    ];

    foreach ($safeKeys as $key) {
        if (!array_key_exists($key, $json)) {
            continue;
        }

        if (in_array($key, ['source_url', 'detected_form_action', 'fixed_submit_url_used'], true)) {
            $out['safe_metadata'][$key] = ev_safe_url_display((string)$json[$key]);
        } else {
            $out['safe_metadata'][$key] = is_scalar($json[$key]) ? (string)$json[$key] : '[non-scalar metadata]';
        }
    }

    return $out;
}

function ev_has_key_like($value, array $needles): bool
{
    if (!is_array($value)) {
        return false;
    }
    foreach ($value as $key => $item) {
        foreach ($needles as $needle) {
            if (stripos((string)$key, $needle) !== false && $item !== null && $item !== '') {
                return true;
            }
        }
        if (is_array($item) && ev_has_key_like($item, $needles)) {
            return true;
        }
    }
    return false;
}

function ev_find_session_path(array $config): string
{
    $edx = is_array($config['edxeix'] ?? null) ? $config['edxeix'] : [];

    foreach (['session_file', 'session_cookie_file', 'cookie_file'] as $key) {
        if (!empty($edx[$key]) && is_string($edx[$key]) && basename((string)$edx[$key]) === 'edxeix_session.json') {
            return (string)$edx[$key];
        }
    }

    $paths = function_exists('gov_bridge_paths') ? gov_bridge_paths() : [];
    if (!empty($paths['runtime'])) {
        return rtrim((string)$paths['runtime'], '/') . '/edxeix_session.json';
    }

    return '/home/cabnet/gov.cabnet.app_app/storage/runtime/edxeix_session.json';
}

function ev_age_label(int $seconds): string
{
    if ($seconds < 60) {
        return $seconds . ' sec';
    }
    if ($seconds < 3600) {
        return floor($seconds / 60) . ' min';
    }
    if ($seconds < 86400) {
        return floor($seconds / 3600) . ' hr';
    }
    return floor($seconds / 86400) . ' days';
}

function ev_json_response(array $payload): void
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit;
}

$format = strtolower(trim((string)($_GET['format'] ?? 'html')));
$freshMinutes = max(1, min(1440, (int)($_GET['fresh_minutes'] ?? 30)));

$state = [
    'ok' => false,
    'error' => null,
    'config_loaded' => false,
    'server_time' => date('Y-m-d H:i:s T'),
    'server_timestamp' => time(),
    'fresh_minutes' => $freshMinutes,
    'session_path' => '',
    'session' => [],
    'age_seconds' => null,
    'age_label' => '',
    'is_fresh' => false,
    'is_recent_today' => false,
    'appears_updated_by_extension' => false,
];

try {
    $config = gov_bridge_load_config();
    if (!empty($config['app']['timezone'])) {
        date_default_timezone_set((string)$config['app']['timezone']);
        $state['server_time'] = date('Y-m-d H:i:s T');
        $state['server_timestamp'] = time();
    }

    $state['config_loaded'] = true;
    $path = ev_find_session_path($config);
    $state['session_path'] = $path;
    $session = ev_load_session_file($path);
    $state['session'] = $session;

    if (!empty($session['modified_ts'])) {
        $age = max(0, time() - (int)$session['modified_ts']);
        $state['age_seconds'] = $age;
        $state['age_label'] = ev_age_label($age);
        $state['is_fresh'] = $age <= ($freshMinutes * 60);
        $state['is_recent_today'] = date('Y-m-d', (int)$session['modified_ts']) === date('Y-m-d');
    }

    $safe = is_array($session['safe_metadata'] ?? null) ? $session['safe_metadata'] : [];
    $state['appears_updated_by_extension'] =
        !empty($session['json_valid']) &&
        !empty($session['cookie_like_present']) &&
        !empty($session['token_like_present']) &&
        (!empty($safe['extension_version']) || !empty($safe['source']) || !empty($safe['source_url']));

    $state['ok'] = $state['config_loaded'] && !empty($session['exists']) && !empty($session['readable']) && !empty($session['json_valid']);
} catch (Throwable $e) {
    $state['error'] = $e->getMessage();
}

$decisionCode = 'SESSION_WRITE_NOT_VERIFIED';
$decisionType = 'bad';
$decisionText = 'The saved EDXEIX session file was not verified as fresh. Refresh with the Firefox extension.';

if ($state['ok'] && $state['is_fresh'] && $state['appears_updated_by_extension']) {
    $decisionCode = 'EXTENSION_SESSION_WRITE_FRESH';
    $decisionType = 'good';
    $decisionText = 'edxeix_session.json appears freshly updated by the extension. Run the GET-only target matrix next.';
} elseif ($state['ok'] && $state['is_recent_today'] && $state['appears_updated_by_extension']) {
    $decisionCode = 'EXTENSION_SESSION_WRITE_TODAY_BUT_NOT_FRESH';
    $decisionType = 'warn';
    $decisionText = 'edxeix_session.json appears to be from today, but it is older than the selected freshness window.';
} elseif ($state['ok'] && $state['appears_updated_by_extension']) {
    $decisionCode = 'EXTENSION_SESSION_WRITE_OLD';
    $decisionType = 'warn';
    $decisionText = 'edxeix_session.json has extension-like metadata but is not fresh.';
} elseif ($state['ok']) {
    $decisionCode = 'SESSION_FILE_VALID_BUT_EXTENSION_METADATA_UNCLEAR';
    $decisionType = 'warn';
    $decisionText = 'edxeix_session.json is valid JSON, but extension/source metadata is unclear.';
}

$payload = [
    'ok' => $state['ok'],
    'script' => 'ops/extension-session-write-verification.php',
    'generated_at' => date('c'),
    'safety_contract' => [
        'calls_bolt' => false,
        'calls_edxeix' => false,
        'posts_to_edxeix' => false,
        'reads_database' => false,
        'writes_database' => false,
        'writes_files' => false,
        'stages_jobs' => false,
        'updates_mappings' => false,
        'prints_secrets' => false,
        'raw_session_json_returned' => false,
        'live_edxeix_submission' => 'disabled_not_used',
        'purpose' => 'verify extension session-file write metadata only',
    ],
    'decision' => [
        'code' => $decisionCode,
        'type' => $decisionType,
        'text' => $decisionText,
    ],
    'checks' => [
        'config_loaded' => $state['config_loaded'],
        'session_file_exists' => !empty($state['session']['exists']),
        'session_file_readable' => !empty($state['session']['readable']),
        'session_json_valid' => !empty($state['session']['json_valid']),
        'cookie_like_data_present' => !empty($state['session']['cookie_like_present']),
        'token_like_data_present' => !empty($state['session']['token_like_present']),
        'appears_updated_by_extension' => $state['appears_updated_by_extension'],
        'is_recent_today' => $state['is_recent_today'],
        'is_fresh' => $state['is_fresh'],
    ],
    'server_time' => $state['server_time'],
    'fresh_minutes' => $freshMinutes,
    'session_file' => [
        'basename' => $state['session']['basename'] ?? '',
        'path_hint' => $state['session']['path_hint'] ?? '',
        'readable' => !empty($state['session']['readable']),
        'json_valid' => !empty($state['session']['json_valid']),
        'size_bytes' => $state['session']['size_bytes'] ?? 0,
        'modified_at' => $state['session']['modified_at'] ?? '',
        'age_seconds' => $state['age_seconds'],
        'age_label' => $state['age_label'],
        'json_keys' => $state['session']['json_keys'] ?? [],
        'safe_metadata' => $state['session']['safe_metadata'] ?? [],
    ],
    'next_links' => [
        'this_json' => '/ops/extension-session-write-verification.php?format=json',
        'target_matrix_json' => '/ops/edxeix-target-matrix.php?probe=1&follow=1&format=json',
        'session_probe_json' => '/ops/edxeix-session-probe.php?format=json',
        'refresh_checklist' => '/ops/edxeix-session-refresh-checklist.php',
        'submit_readiness_json' => '/ops/edxeix-submit-readiness.php?format=json',
    ],
    'error' => $state['error'] ?: ($state['session']['error'] ?? null),
];

if ($format === 'json') {
    ev_json_response($payload);
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Extension Session Write Verification | gov.cabnet.app</title>
    <link rel="stylesheet" href="/assets/css/gov-ops-edxeix.css?v=3.1">
</head>
<body>
<div class="gov-topbar">
    <div class="gov-brand">
        <div class="gov-brand-crest">ΕΔ</div>
        <div class="gov-brand-text">
            <strong>gov.cabnet.app</strong>
            <span>Bolt → EDXEIX operational console</span>
        </div>
    </div>
    <div class="gov-top-links">
        <a href="/ops/home.php">Αρχική</a>
        <a href="/ops/edxeix-session-refresh-checklist.php">Refresh Checklist</a>
        <a href="/ops/edxeix-target-matrix.php">Target Matrix</a>
        <a class="gov-logout" href="/ops/route-index.php">Route Index</a>
    </div>
</div>

<div class="gov-shell">
    <aside class="gov-sidebar">
        <h3>Extension Verify</h3>
        <p>Check whether edxeix_session.json was refreshed</p>
        <div class="gov-side-group">
            <div class="gov-side-group-title">EDXEIX preparation</div>
            <a class="gov-side-link active" href="/ops/extension-session-write-verification.php">Extension Write Verify</a>
            <a class="gov-side-link" href="/ops/edxeix-session-refresh-checklist.php">Session Refresh Checklist</a>
            <a class="gov-side-link" href="/ops/edxeix-target-matrix.php">Target Matrix</a>
            <a class="gov-side-link" href="/ops/edxeix-session-probe.php">Session/Form Probe</a>
            <a class="gov-side-link" href="/ops/edxeix-submit-readiness.php">Submit Readiness</a>
            <div class="gov-side-group-title">Safety</div>
            <a class="gov-side-link" href="/ops/preflight-review.php">Preflight Review</a>
            <a class="gov-side-link" href="/ops/route-index.php">Route Index</a>
        </div>
        <div class="gov-side-note">Reads local file metadata only. No EDXEIX call, no POST, no raw session JSON.</div>
    </aside>

    <div class="gov-content">
        <div class="gov-page-header">
            <div>
                <h1 class="gov-page-title">Έλεγχος ενημέρωσης συνεδρίας από extension</h1>
                <div class="gov-breadcrumbs">Αρχική / Διαχειριστικό / Έλεγχος ενημέρωσης συνεδρίας</div>
            </div>
            <div class="gov-tabs">
                <a class="gov-tab active" href="/ops/extension-session-write-verification.php">Καρτέλα</a>
                <a class="gov-tab" href="/ops/extension-session-write-verification.php?format=json">JSON</a>
                <a class="gov-tab" href="/ops/edxeix-target-matrix.php?probe=1&follow=1&format=json">Matrix JSON</a>
                <a class="gov-tab" href="/ops/edxeix-session-refresh-checklist.php">Checklist</a>
            </div>
        </div>

        <main class="wrap wrap-shell">
            <section class="safety">
                <strong>LOCAL SESSION FILE CHECK ONLY.</strong>
                This page does not call Bolt, does not call EDXEIX, does not POST, does not write files, and does not display cookies/tokens/raw session JSON.
            </section>

            <section class="card hero <?= ev_h($decisionType) ?>">
                <h1>Extension Session Write Verification</h1>
                <p><?= ev_h($decisionText) ?></p>
                <div>
                    <?= ev_badge($decisionCode, $decisionType) ?>
                    <?= ev_badge('NO EDXEIX CALL', 'good') ?>
                    <?= ev_badge('NO POST', 'good') ?>
                    <?= ev_badge('NO SECRETS PRINTED', 'good') ?>
                </div>
                <div class="grid" style="margin-top:14px">
                    <?= ev_metric($state['session']['modified_at'] ?? '', 'Session modified at') ?>
                    <?= ev_metric($state['age_label'] ?: 'n/a', 'Session age') ?>
                    <?= ev_metric($state['is_fresh'] ? 'yes' : 'no', 'Fresh within ' . $freshMinutes . ' min') ?>
                    <?= ev_metric($state['appears_updated_by_extension'] ? 'yes' : 'no', 'Extension metadata') ?>
                </div>
                <div class="actions">
                    <a class="btn" href="/ops/extension-session-write-verification.php?format=json">Open JSON</a>
                    <a class="btn warn" href="/ops/edxeix-target-matrix.php?probe=1&follow=1&format=json">Run Target Matrix JSON</a>
                    <a class="btn dark" href="/ops/edxeix-session-refresh-checklist.php">Open Refresh Checklist</a>
                </div>
            </section>

            <section class="two">
                <div class="card">
                    <h2>Verification checks</h2>
                    <div class="kv">
                        <div class="k">Server time</div><div><strong><?= ev_h($state['server_time']) ?></strong></div>
                        <div class="k">Config loaded</div><div><?= ev_yes($state['config_loaded']) ?></div>
                        <div class="k">Session file exists</div><div><?= ev_yes(!empty($state['session']['exists'])) ?></div>
                        <div class="k">Session file readable</div><div><?= ev_yes(!empty($state['session']['readable'])) ?></div>
                        <div class="k">Valid JSON</div><div><?= ev_yes(!empty($state['session']['json_valid'])) ?></div>
                        <div class="k">Cookie-like data present</div><div><?= ev_yes(!empty($state['session']['cookie_like_present'])) ?></div>
                        <div class="k">Token-like data present</div><div><?= ev_yes(!empty($state['session']['token_like_present'])) ?></div>
                        <div class="k">Fresh today</div><div><?= ev_yes($state['is_recent_today']) ?></div>
                    </div>
                    <?php if (!empty($payload['error'])): ?>
                        <p class="badline"><strong><?= ev_h($payload['error']) ?></strong></p>
                    <?php endif; ?>
                </div>

                <div class="card">
                    <h2>Safe session metadata</h2>
                    <div class="kv">
                        <div class="k">File</div><div><code><?= ev_h($state['session']['basename'] ?? '') ?></code></div>
                        <div class="k">Path hint</div><div><code><?= ev_h($state['session']['path_hint'] ?? '') ?></code></div>
                        <div class="k">Size</div><div><?= ev_h((string)($state['session']['size_bytes'] ?? 0)) ?> bytes</div>
                        <div class="k">Modified at</div><div><strong><?= ev_h($state['session']['modified_at'] ?? '') ?></strong></div>
                        <div class="k">Age</div><div><strong><?= ev_h($state['age_label']) ?></strong></div>
                        <?php foreach (($state['session']['safe_metadata'] ?? []) as $key => $value): ?>
                            <div class="k"><?= ev_h($key) ?></div><div><?= ev_h($value) ?></div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>

            <section class="card">
                <h2>Safe JSON key list</h2>
                <p>The keys below prove structure only. Raw values for cookies/tokens are never displayed.</p>
                <div class="actions">
                    <?php foreach (($state['session']['json_keys'] ?? []) as $key): ?>
                        <?= ev_badge((string)$key, (stripos((string)$key, 'cookie') !== false || stripos((string)$key, 'token') !== false || stripos((string)$key, 'csrf') !== false) ? 'warn' : 'neutral') ?>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="card">
                <h2>What to do next</h2>
                <?php if ($decisionCode === 'EXTENSION_SESSION_WRITE_FRESH'): ?>
                    <ol class="timeline">
                        <li>Run the GET-only target matrix now.</li>
                        <li>Success is <code>EDXEIX_DASHBOARD</code>, <code>EDXEIX_FORM_CANDIDATE</code>, or ideally <code>LEASE_FORM_CANDIDATE</code>.</li>
                        <li>Do not POST or submit yet. We still need a valid future-safe Bolt candidate and explicit approval.</li>
                    </ol>
                <?php else: ?>
                    <ol class="timeline">
                        <li>Open Firefox with the EDXEIX extension installed.</li>
                        <li>Log into EDXEIX and navigate to the lease-agreement creation form.</li>
                        <li>Run the extension session refresh action.</li>
                        <li>Return to this page and confirm the modified timestamp is current.</li>
                        <li>Then run the GET-only target matrix.</li>
                    </ol>
                <?php endif; ?>
                <div class="actions">
                    <a class="btn" href="/ops/edxeix-session-refresh-checklist.php">Refresh Checklist</a>
                    <a class="btn warn" href="/ops/edxeix-target-matrix.php?probe=1&follow=1&format=json">Run Target Matrix JSON</a>
                    <a class="btn dark" href="/ops/edxeix-submit-readiness.php?format=json">Submit Readiness JSON</a>
                </div>
            </section>

            <section class="card">
                <h2>Hard stop rules</h2>
                <ul class="timeline">
                    <li>Do not paste raw session JSON, cookies, CSRF values, or credentials into chat.</li>
                    <li>Do not create jobs from completed/historical/cancelled/terminal rows.</li>
                    <li>Do not POST to EDXEIX until authenticated form access and a future-safe Bolt candidate are both confirmed.</li>
                    <li>Live submit remains disabled until Andreas explicitly approves a live-submit test.</li>
                </ul>
            </section>
        </main>
    </div>
</div>
</body>
</html>
