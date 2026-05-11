<?php
/**
 * gov.cabnet.app — Firefox Extension Pair Status v1.0
 *
 * Read-only helper inventory page for the current two-extension operator workflow.
 * Does not install extensions, does not call Bolt/EDXEIX/AADE, and does not write data.
 */

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
header('X-Robots-Tag: noindex,nofollow', true);
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

require_once __DIR__ . '/_shell.php';

/**
 * @return array<string,mixed>
 */
function fxpair_manifest(string $dir): array
{
    $file = rtrim($dir, '/') . '/manifest.json';
    if (!is_file($file) || !is_readable($file)) {
        return [];
    }
    $decoded = json_decode((string)file_get_contents($file), true);
    return is_array($decoded) ? $decoded : [];
}

function fxpair_first_existing_dir(array $dirs): string
{
    foreach ($dirs as $dir) {
        if (is_dir($dir) && is_file(rtrim($dir, '/') . '/manifest.json')) {
            return $dir;
        }
    }
    return (string)($dirs[0] ?? '');
}

/**
 * @return array<int,array{name:string,size:int,mtime:string,sha256:string}>
 */
function fxpair_safe_files(string $dir): array
{
    if (!is_dir($dir) || !is_readable($dir)) {
        return [];
    }

    $allowedExt = ['json', 'js', 'css', 'html', 'htm', 'md', 'txt', 'svg', 'png', 'jpg', 'jpeg', 'webp'];
    $blockedExt = ['zip', 'xpi', 'bak', 'backup', 'old', 'orig', 'tmp', 'log', 'sqlite', 'db', 'sql', 'pem', 'key', 'crt', 'p12'];
    $rows = [];

    $items = scandir($dir);
    if (!is_array($items)) {
        return [];
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..' || str_starts_with($item, '.')) {
            continue;
        }
        $path = rtrim($dir, '/') . '/' . $item;
        if (!is_file($path) || !is_readable($path)) {
            continue;
        }
        $ext = strtolower(pathinfo($item, PATHINFO_EXTENSION));
        if (in_array($ext, $blockedExt, true) || !in_array($ext, $allowedExt, true)) {
            continue;
        }
        $rows[] = [
            'name' => $item,
            'size' => (int)filesize($path),
            'mtime' => date('Y-m-d H:i:s', (int)filemtime($path)),
            'sha256' => hash_file('sha256', $path) ?: '',
        ];
    }

    usort($rows, static fn(array $a, array $b): int => strcmp($a['name'], $b['name']));
    return $rows;
}

/**
 * @param array<string,mixed> $manifest
 */
function fxpair_manifest_gecko_id(array $manifest): string
{
    $settings = $manifest['browser_specific_settings']['gecko']['id'] ?? null;
    if (is_string($settings) && trim($settings) !== '') {
        return trim($settings);
    }
    $legacy = $manifest['applications']['gecko']['id'] ?? null;
    return is_string($legacy) ? trim($legacy) : '';
}

/**
 * @return array<string,mixed>
 */
function fxpair_package(array $config): array
{
    $dir = fxpair_first_existing_dir($config['candidate_dirs']);
    $manifest = fxpair_manifest($dir);
    $files = fxpair_safe_files($dir);
    $latest = '';
    foreach ($files as $file) {
        if ($file['mtime'] !== '' && ($latest === '' || $file['mtime'] > $latest)) {
            $latest = $file['mtime'];
        }
    }

    return [
        'key' => $config['key'],
        'expected_name' => $config['expected_name'],
        'purpose' => $config['purpose'],
        'dir' => $dir,
        'exists' => is_dir($dir) && is_file(rtrim($dir, '/') . '/manifest.json'),
        'manifest' => $manifest,
        'manifest_name' => trim((string)($manifest['name'] ?? '')),
        'manifest_version' => trim((string)($manifest['version'] ?? '')),
        'gecko_id' => fxpair_manifest_gecko_id($manifest),
        'file_count' => count($files),
        'total_size' => array_sum(array_map(static fn(array $row): int => (int)$row['size'], $files)),
        'latest_mtime' => $latest,
        'files' => $files,
        'candidate_dirs' => $config['candidate_dirs'],
    ];
}

$packages = [
    fxpair_package([
        'key' => 'session_payload',
        'expected_name' => 'Cabnet EDXEIX Session + Payload Fill',
        'purpose' => 'Current helper used for gov.cabnet.app payload/session bridge behavior before the EDXEIX fill step.',
        'candidate_dirs' => [
            '/home/cabnet/tools/firefox-edxeix-session-payload-fill',
            '/home/cabnet/tools/firefox-edxeix-session-capture',
            '/home/cabnet/tools/cabnet-edxeix-session-payload-fill',
            '/home/cabnet/tools/firefox-edxeix-autofill-helper-session',
        ],
    ]),
    fxpair_package([
        'key' => 'autofill',
        'expected_name' => 'Gov Cabnet EDXEIX Autofill Helper',
        'purpose' => 'Current helper used for exact-ID autofill and guarded reviewed save behavior on EDXEIX pages.',
        'candidate_dirs' => [
            '/home/cabnet/tools/firefox-edxeix-autofill-helper',
        ],
    ]),
];

$found = 0;
$totalFiles = 0;
$latestMtime = '';
foreach ($packages as $pkg) {
    if (!empty($pkg['exists'])) {
        $found++;
    }
    $totalFiles += (int)$pkg['file_count'];
    if (($pkg['latest_mtime'] ?? '') !== '' && ($latestMtime === '' || $pkg['latest_mtime'] > $latestMtime)) {
        $latestMtime = (string)$pkg['latest_mtime'];
    }
}

$allFound = $found === count($packages);

opsui_shell_begin([
    'title' => 'Firefox Extension Pair Status',
    'page_title' => 'Firefox extension pair status',
    'subtitle' => 'Read-only status for the two-helper production workflow',
    'breadcrumbs' => 'Αρχική / Administration / Firefox extension pair status',
    'active_section' => 'Extension Status',
    'safe_notice' => 'Read-only extension inventory page. Keep both current helpers loaded until a single merged helper has been built and tested. This page does not call Bolt, EDXEIX, or AADE, and does not write data.',
]);
?>
<section class="card hero <?= $allFound ? 'good' : 'warn' ?>">
    <h1>Current Firefox helper workflow</h1>
    <p>For the current production workflow, both temporary Firefox helpers should stay loaded. This page tracks the server-side helper source folders and gives us a safe merge checklist.</p>
    <div>
        <?= opsui_badge('KEEP BOTH LOADED TODAY', 'warn') ?>
        <?= opsui_badge('READ ONLY', 'good') ?>
        <?= opsui_badge('NO EDXEIX CALL', 'good') ?>
        <?= opsui_badge('MERGE NOT ACTIVE YET', 'neutral') ?>
    </div>
    <div class="grid" style="margin-top:14px">
        <?= opsui_metric($found . '/' . count($packages), 'Helper folders detected') ?>
        <?= opsui_metric((string)$totalFiles, 'Safe source files visible') ?>
        <?= opsui_metric($latestMtime !== '' ? $latestMtime : 'n/a', 'Latest helper file time') ?>
        <?= opsui_metric($allFound ? 'YES' : 'CHECK', 'Pair inventory ready') ?>
    </div>
    <div class="actions">
        <a class="btn good" href="/ops/firefox-extension.php">Open helper download center</a>
        <a class="btn" href="/ops/pre-ride-email-toolv2.php">Open Pre-Ride V2 Dev</a>
        <a class="btn dark" href="/ops/route-index.php">Route Index</a>
    </div>
</section>

<section class="two">
    <?php foreach ($packages as $pkg): ?>
        <div class="card gov-extension-card">
            <h2><?= opsui_h((string)$pkg['expected_name']) ?></h2>
            <p><?= opsui_h((string)$pkg['purpose']) ?></p>
            <div>
                <?= !empty($pkg['exists']) ? opsui_badge('SERVER SOURCE FOUND', 'good') : opsui_badge('SERVER SOURCE NOT FOUND', 'warn') ?>
                <?= $pkg['manifest_version'] !== '' ? opsui_badge('v' . (string)$pkg['manifest_version'], 'neutral') : opsui_badge('version unknown', 'warn') ?>
            </div>
            <div class="kv" style="margin-top:14px">
                <div class="k">Detected path</div><div><code><?= opsui_h((string)$pkg['dir']) ?></code></div>
                <div class="k">Manifest name</div><div><?= opsui_h((string)($pkg['manifest_name'] ?: 'not detected')) ?></div>
                <div class="k">Extension ID</div><div><code><?= opsui_h((string)($pkg['gecko_id'] ?: 'not detected')) ?></code></div>
                <div class="k">Safe files</div><div><?= opsui_h((string)$pkg['file_count']) ?></div>
                <div class="k">Total size</div><div><?= opsui_h(number_format((int)$pkg['total_size'])) ?> bytes</div>
                <div class="k">Latest modified</div><div><?= opsui_h((string)($pkg['latest_mtime'] ?: 'n/a')) ?></div>
            </div>
            <?php if (empty($pkg['exists'])): ?>
                <div class="gov-alert gov-alert-warn" style="margin-top:14px">The local browser screenshot shows this helper loaded, but the expected server source folder was not detected from the configured candidate paths. Confirm the exact folder under <code>/home/cabnet/tools/</code> before we package or merge it.</div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</section>

<section class="card">
    <h2>Safe merge checklist</h2>
    <div class="gov-checklist-grid">
        <div class="gov-check-card"><strong>1. Inventory both helpers</strong><span>Confirm exact server folders, manifest IDs, content scripts, and page matches.</span></div>
        <div class="gov-check-card"><strong>2. Build one dev helper</strong><span>Create one new temporary extension for V2 only, without removing today’s two helpers.</span></div>
        <div class="gov-check-card"><strong>3. Test on V2 route</strong><span>Use <code>/ops/pre-ride-email-toolv2.php</code> and an EDXEIX test/review flow before production use.</span></div>
        <div class="gov-check-card"><strong>4. Live future ride proof</strong><span>Only after a real future ride passes end-to-end should we replace the two-helper workflow.</span></div>
    </div>
</section>

<section class="card">
    <h2>Detected safe source files</h2>
    <?php foreach ($packages as $pkg): ?>
        <h3><?= opsui_h((string)$pkg['expected_name']) ?></h3>
        <?php if (empty($pkg['files'])): ?>
            <p class="warnline">No safe source files detected for this helper source path.</p>
        <?php else: ?>
            <div class="table-wrap" style="margin-bottom:18px">
                <table class="gov-log-table gov-helper-table">
                    <thead>
                    <tr>
                        <th>File</th>
                        <th>Size</th>
                        <th>Modified</th>
                        <th>SHA-256</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($pkg['files'] as $file): ?>
                        <tr>
                            <td><code><?= opsui_h((string)$file['name']) ?></code></td>
                            <td><?= opsui_h(number_format((int)$file['size'])) ?> bytes</td>
                            <td><?= opsui_h((string)$file['mtime']) ?></td>
                            <td class="mono gov-truncate-cell" title="<?= opsui_h((string)$file['sha256']) ?>"><?= opsui_h((string)$file['sha256']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    <?php endforeach; ?>
</section>

<section class="card">
    <h2>Current operator instruction</h2>
    <ol class="list">
        <li>Keep both temporary add-ons loaded in Firefox.</li>
        <li>After any helper file update, click <strong>Reload</strong> on both helpers inside <code>about:debugging#/runtime/this-firefox</code>.</li>
        <li>Do not remove either helper until a single merged helper is explicitly deployed and tested.</li>
        <li>Continue using the production pre-ride tool normally. Development work belongs in <code>/ops/pre-ride-email-toolv2.php</code>.</li>
    </ol>
</section>
<?php opsui_shell_end(); ?>
