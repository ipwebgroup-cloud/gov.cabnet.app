<?php
/**
 * gov.cabnet.app — EDXEIX Submit Research v0.1
 *
 * Read-only research page for the future server-side EDXEIX submit connector.
 *
 * Safety contract:
 * - No Bolt API calls.
 * - No EDXEIX HTTP calls.
 * - No AADE calls.
 * - No workflow database writes.
 * - No queue staging.
 * - No live submission.
 * - Does not read or display cookies, sessions, tokens, credentials, or private config secrets.
 * - Production pre-ride tool is not modified by this file.
 */

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Robots-Tag: noindex,nofollow', true);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$shellFile = __DIR__ . '/_shell.php';
if (is_file($shellFile)) {
    require_once $shellFile;
}

function esr_h(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function esr_badge(string $text, string $type = 'neutral'): string
{
    $type = in_array($type, ['good', 'warn', 'bad', 'neutral'], true) ? $type : 'neutral';
    if (function_exists('opsui_badge')) {
        return opsui_badge($text, $type);
    }
    return '<span class="badge badge-' . esr_h($type) . '">' . esr_h($text) . '</span>';
}

function esr_shell_begin(): void
{
    if (function_exists('opsui_shell_begin')) {
        opsui_shell_begin([
            'title' => 'EDXEIX Submit Research',
            'page_title' => 'EDXEIX Submit Research',
            'active_section' => 'Mobile Submit',
            'breadcrumbs' => 'Αρχική / Mobile Submit / EDXEIX Submit Research',
            'safe_notice' => 'Read-only research page. It does not call EDXEIX and does not enable live submit. The production pre-ride tool remains unchanged.',
            'force_safe_notice' => true,
        ]);
        return;
    }

    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>EDXEIX Submit Research | gov.cabnet.app</title><style>body{font-family:Arial,Helvetica,sans-serif;background:#eef1f6;color:#20293a;margin:0;padding:18px}.card{background:#fff;border:1px solid #d8dde7;border-radius:8px;padding:16px;margin:0 0 16px}.badge{display:inline-block;padding:6px 10px;border-radius:12px;background:#e9edf7;margin:2px}.badge-good{background:#dbf0dc;color:#2d7b37}.badge-warn{background:#f8ead3;color:#9a5a00}.badge-bad{background:#f8dedd;color:#b13c35}.table-wrap{overflow:auto}table{width:100%;border-collapse:collapse;background:#fff}th,td{border-bottom:1px solid #e5e7eb;padding:10px;text-align:left;vertical-align:top}.small{font-size:13px;color:#667085}.grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}@media(max-width:900px){.grid{grid-template-columns:1fr}}</style></head><body>';
}

function esr_shell_end(): void
{
    if (function_exists('opsui_shell_end')) {
        opsui_shell_end();
        return;
    }
    echo '</body></html>';
}

function esr_short_hash(string $file): string
{
    if (!is_file($file) || !is_readable($file)) {
        return '';
    }
    $hash = hash_file('sha256', $file);
    return is_string($hash) ? substr($hash, 0, 16) : '';
}

function esr_file_info(string $file): array
{
    if (!is_file($file)) {
        return [
            'exists' => false,
            'readable' => false,
            'size' => 0,
            'mtime' => '',
            'sha16' => '',
        ];
    }

    return [
        'exists' => true,
        'readable' => is_readable($file),
        'size' => (int)filesize($file),
        'mtime' => date('Y-m-d H:i:s', (int)filemtime($file)),
        'sha16' => esr_short_hash($file),
    ];
}

function esr_format_bytes(int $bytes): string
{
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    }
    if ($bytes >= 1024) {
        return number_format($bytes / 1024, 1) . ' KB';
    }
    return (string)$bytes . ' B';
}

function esr_manifest_summary(string $dir): array
{
    $file = rtrim($dir, '/') . '/manifest.json';
    $info = esr_file_info($file);
    $summary = [
        'path' => $file,
        'dir' => $dir,
        'exists' => $info['exists'],
        'readable' => $info['readable'],
        'size' => $info['size'],
        'mtime' => $info['mtime'],
        'sha16' => $info['sha16'],
        'name' => '',
        'version' => '',
        'gecko_id' => '',
        'error' => '',
    ];

    if (!$info['readable']) {
        return $summary;
    }

    $raw = file_get_contents($file);
    if (!is_string($raw)) {
        $summary['error'] = 'Unable to read manifest.';
        return $summary;
    }

    $json = json_decode($raw, true);
    if (!is_array($json)) {
        $summary['error'] = 'Manifest is not valid JSON.';
        return $summary;
    }

    $summary['name'] = trim((string)($json['name'] ?? ''));
    $summary['version'] = trim((string)($json['version'] ?? ''));
    $gecko = $json['browser_specific_settings']['gecko']['id'] ?? ($json['applications']['gecko']['id'] ?? '');
    $summary['gecko_id'] = trim((string)$gecko);
    return $summary;
}

function esr_candidate_dirs(): array
{
    $dirs = [
        '/home/cabnet/tools/firefox-edxeix-autofill-helper',
        '/home/cabnet/tools/firefox-edxeix-session-payload-fill',
        '/home/cabnet/tools/cabnet-edxeix-session-payload-fill',
        '/home/cabnet/tools/cabnet-edxeix-payload-fill-helper',
        '/home/cabnet/tools/firefox-edxeix-helper',
    ];

    $toolsRoot = '/home/cabnet/tools';
    if (is_dir($toolsRoot) && is_readable($toolsRoot)) {
        $items = scandir($toolsRoot) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $toolsRoot . '/' . $item;
            if (is_dir($path) && is_file($path . '/manifest.json')) {
                $dirs[] = $path;
            }
        }
    }

    return array_values(array_unique($dirs));
}

function esr_js_signals(string $file): array
{
    $signals = [
        'post' => false,
        'fetch' => false,
        'xhr' => false,
        'submit_listener' => false,
        'prevent_default' => false,
        'csrf' => false,
        'coordinates' => false,
        'form_action' => false,
        'field_ids' => false,
    ];

    if (!is_file($file) || !is_readable($file) || filesize($file) > 2_000_000) {
        return $signals;
    }

    $raw = file_get_contents($file);
    if (!is_string($raw)) {
        return $signals;
    }

    $checks = [
        'post' => '/\bPOST\b/i',
        'fetch' => '/\bfetch\s*\(/i',
        'xhr' => '/XMLHttpRequest/i',
        'submit_listener' => '/addEventListener\s*\([^\)]*submit/i',
        'prevent_default' => '/preventDefault\s*\(/i',
        'csrf' => '/csrf|_token|authenticity/i',
        'coordinates' => '/latitude|longitude|\blat\b|\blng\b|\blon\b/i',
        'form_action' => '/\.action|action\s*=|querySelector\s*\([^\)]*form/i',
        'field_ids' => '/getElementById|querySelector|name\s*=|field/i',
    ];

    foreach ($checks as $key => $pattern) {
        $signals[$key] = preg_match($pattern, $raw) === 1;
    }

    return $signals;
}

function esr_dir_source_files(string $dir): array
{
    $files = [];
    if (!is_dir($dir) || !is_readable($dir)) {
        return $files;
    }

    $items = scandir($dir) ?: [];
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        if (!preg_match('/\.(json|js|css|html|md)$/i', $item)) {
            continue;
        }
        $path = rtrim($dir, '/') . '/' . $item;
        if (is_file($path)) {
            $files[] = $path;
        }
    }
    sort($files);
    return $files;
}

$root = dirname(__DIR__, 3);
$trackedRoutes = [
    '/ops/pre-ride-email-tool.php' => $root . '/public_html/gov.cabnet.app/ops/pre-ride-email-tool.php',
    '/ops/mobile-submit-dev.php' => $root . '/public_html/gov.cabnet.app/ops/mobile-submit-dev.php',
    '/ops/mobile-compatibility.php' => $root . '/public_html/gov.cabnet.app/ops/mobile-compatibility.php',
    '/ops/firefox-extension.php' => $root . '/public_html/gov.cabnet.app/ops/firefox-extension.php',
    '/ops/firefox-extensions-status.php' => $root . '/public_html/gov.cabnet.app/ops/firefox-extensions-status.php',
];

$manifests = [];
$jsSignals = [];
foreach (esr_candidate_dirs() as $dir) {
    $manifest = esr_manifest_summary($dir);
    if (!$manifest['exists']) {
        continue;
    }
    $manifests[] = $manifest;
    foreach (esr_dir_source_files($dir) as $file) {
        if (preg_match('/\.js$/i', $file)) {
            $jsSignals[$file] = esr_js_signals($file);
        }
    }
}

$researchRows = [
    ['Existing mobile review/submit-dev UI', is_file($trackedRoutes['/ops/mobile-submit-dev.php']), 'Mobile submit dev page exists and remains submit-disabled.'],
    ['Desktop helper source visible', count($manifests) > 0, 'At least one Firefox helper manifest was found under /home/cabnet/tools.'],
    ['Server-side EDXEIX connector', false, 'Not built yet. This is the next researched component.'],
    ['Live mobile submit', false, 'Must remain disabled until explicit approval and successful dry-run blueprint.'],
    ['Duplicate protection design', false, 'Needs a dedicated submit-attempt/dedupe plan before live submit.'],
    ['Map coordinate confirmation design', false, 'Needs verified EDXEIX field names and operator confirmation UX.'],
];

esr_shell_begin();
?>
<style>
.esr-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}.esr-two{display:grid;grid-template-columns:1fr 1fr;gap:18px}.esr-step{border:1px solid #d8dde7;background:#fff;border-radius:4px;padding:14px}.esr-step h3{margin-top:0}.esr-flow{counter-reset:step;list-style:none;padding:0;margin:0}.esr-flow li{counter-increment:step;border:1px solid #d8dde7;border-radius:4px;background:#fff;margin:8px 0;padding:12px 12px 12px 50px;position:relative}.esr-flow li:before{content:counter(step);position:absolute;left:12px;top:10px;width:28px;height:28px;border-radius:50%;background:#e7ecf9;color:#435189;border:1px solid #d1d8ec;display:flex;align-items:center;justify-content:center;font-weight:700}.esr-code{background:#0b1220;color:#dbeafe;border-radius:4px;padding:14px;overflow:auto;font-family:Consolas,Menlo,monospace;font-size:13px}.esr-scroll{overflow:auto}.esr-signal{display:inline-block;min-width:70px;margin:2px 3px 2px 0}.esr-warning{border-left:6px solid #d4922d}.esr-good{border-left:6px solid #5fa865}.esr-bad{border-left:6px solid #c44b44}@media(max-width:1100px){.esr-grid,.esr-two{grid-template-columns:1fr}}</style>

<section class="card hero warn">
    <h1>EDXEIX Server-Side Submit Research</h1>
    <p>This page defines the research blueprint for eventual mobile EDXEIX submission. It is intentionally read-only and does not make any HTTP request to EDXEIX.</p>
    <div>
        <?= esr_badge('RESEARCH ONLY', 'warn') ?>
        <?= esr_badge('NO EDXEIX CALL', 'good') ?>
        <?= esr_badge('NO LIVE SUBMIT', 'good') ?>
        <?= esr_badge('MOBILE SUBMIT TARGET', 'neutral') ?>
    </div>
</section>

<section class="esr-grid">
    <div class="card esr-good">
        <h2>Agreed target</h2>
        <p><strong>Mobile must eventually submit.</strong> The correct architecture is an authenticated mobile web app plus controlled server-side EDXEIX submission.</p>
        <div><?= esr_badge('SERVER-SIDE CONNECTOR', 'warn') ?> <?= esr_badge('EXTENSION NOT REQUIRED ON MOBILE', 'good') ?></div>
    </div>
    <div class="card esr-warning">
        <h2>Current boundary</h2>
        <p>Today, submit remains disabled. Desktop Firefox with the two helpers remains the production fill/save workflow.</p>
        <div><?= esr_badge('DESKTOP LIVE WORKFLOW', 'neutral') ?> <?= esr_badge('MOBILE DEV ONLY', 'warn') ?></div>
    </div>
    <div class="card esr-bad">
        <h2>Hard blocker</h2>
        <p>We still need to confirm EDXEIX form/session requirements before building a server-side POST connector.</p>
        <div><?= esr_badge('CSRF UNKNOWN', 'bad') ?> <?= esr_badge('SESSION UNKNOWN', 'bad') ?> <?= esr_badge('MAP FIELDS UNKNOWN', 'bad') ?></div>
    </div>
</section>

<section class="card">
    <h2>Phase 30 research status</h2>
    <div class="table-wrap esr-scroll">
        <table>
            <thead><tr><th>Item</th><th>Status</th><th>Notes</th></tr></thead>
            <tbody>
            <?php foreach ($researchRows as $row): ?>
                <tr>
                    <td><strong><?= esr_h($row[0]) ?></strong></td>
                    <td><?= !empty($row[1]) ? esr_badge('AVAILABLE', 'good') : esr_badge('NOT READY', 'warn') ?></td>
                    <td><?= esr_h($row[2]) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="esr-two">
    <div class="card">
        <h2>Server-side submit blueprint</h2>
        <ol class="esr-flow">
            <li>Mobile page parses the Bolt pre-ride email and resolves EDXEIX IDs.</li>
            <li>Operator reviews passenger, driver, vehicle, lessor, pickup, drop-off, time, and price.</li>
            <li>Operator confirms exact pickup map point and future ride status.</li>
            <li>Backend builds a dry-run EDXEIX payload and shows every field before submit.</li>
            <li>Backend submits only if explicit live-submit switch is later approved.</li>
            <li>Backend records audit, dedupe hash, operator ID, timestamps, and EDXEIX result.</li>
        </ol>
    </div>

    <div class="card">
        <h2>Required research facts</h2>
        <ul class="list">
            <li>EDXEIX create form URL and POST action URL.</li>
            <li>All required field names, not only visible labels.</li>
            <li>CSRF token name and renewal behavior.</li>
            <li>Authenticated session/cookie requirements.</li>
            <li>Map latitude/longitude or point fields.</li>
            <li>Duplicate behavior after successful save.</li>
            <li>Success and validation error response format.</li>
        </ul>
    </div>
</section>

<section class="card">
    <h2>Detected Firefox helper manifests</h2>
    <?php if ($manifests === []): ?>
        <p class="warnline"><strong>No helper manifests found under the expected /home/cabnet/tools folders.</strong> This does not break the page; it only means the server-side inventory cannot inspect helper source files.</p>
    <?php else: ?>
        <div class="table-wrap esr-scroll">
            <table>
                <thead><tr><th>Name</th><th>Version</th><th>Firefox ID</th><th>Path</th><th>Modified</th><th>SHA-256</th></tr></thead>
                <tbody>
                <?php foreach ($manifests as $manifest): ?>
                    <tr>
                        <td><strong><?= esr_h($manifest['name'] ?: '(unnamed)') ?></strong></td>
                        <td><?= esr_h($manifest['version'] ?: '-') ?></td>
                        <td><span class="mono"><?= esr_h($manifest['gecko_id'] ?: '-') ?></span></td>
                        <td><span class="mono"><?= esr_h($manifest['path']) ?></span></td>
                        <td><?= esr_h($manifest['mtime'] ?: '-') ?></td>
                        <td><span class="mono"><?= esr_h($manifest['sha16'] ?: '-') ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<section class="card">
    <h2>Helper source signals</h2>
    <p class="small">This reads only safe local helper source files and reports whether certain concepts appear. It does not display source code, cookies, tokens, or sessions.</p>
    <?php if ($jsSignals === []): ?>
        <p>No JavaScript helper signals available.</p>
    <?php else: ?>
        <div class="table-wrap esr-scroll">
            <table>
                <thead><tr><th>File</th><th>Signals</th></tr></thead>
                <tbody>
                <?php foreach ($jsSignals as $file => $signals): ?>
                    <tr>
                        <td><span class="mono"><?= esr_h($file) ?></span></td>
                        <td>
                            <?php foreach ($signals as $key => $value): ?>
                                <span class="esr-signal"><?= esr_badge($key, $value ? 'good' : 'neutral') ?></span>
                            <?php endforeach; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<section class="card">
    <h2>Route/file snapshot</h2>
    <div class="table-wrap esr-scroll">
        <table>
            <thead><tr><th>Route</th><th>Status</th><th>Modified</th><th>Size</th><th>SHA-256</th></tr></thead>
            <tbody>
            <?php foreach ($trackedRoutes as $route => $file): $info = esr_file_info($file); ?>
                <tr>
                    <td><a href="<?= esr_h($route) ?>"><?= esr_h($route) ?></a></td>
                    <td><?= $info['exists'] ? esr_badge('EXISTS', 'good') : esr_badge('MISSING', 'bad') ?></td>
                    <td><?= esr_h($info['mtime'] ?: '-') ?></td>
                    <td><?= esr_h($info['exists'] ? esr_format_bytes((int)$info['size']) : '-') ?></td>
                    <td><span class="mono"><?= esr_h($info['sha16'] ?: '-') ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="card">
    <h2>Next implementation phase</h2>
    <p>The next safe development step should be <strong>Phase 31 — EDXEIX Submit Capture Schema</strong>.</p>
    <p>That phase should create a sanitized capture format for the desktop helper to export only form schema and field names. It must exclude cookies, CSRF values, session tokens, and personal ride data unless explicitly sanitized.</p>
    <div class="esr-code">Phase 31 target:
- Add sanitized schema export/capture route
- Capture EDXEIX form action/method and field names only
- Capture map field names only, not real coordinates unless test/sanitized
- Keep live submit disabled
- Do not touch /ops/pre-ride-email-tool.php</div>
</section>

<?php esr_shell_end(); ?>
