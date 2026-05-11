<?php
/**
 * gov.cabnet.app — Firefox EDXEIX Helper Center v2.0
 *
 * Authenticated download/status page for the browser helper files.
 * This page does not install an extension, does not call Bolt, does not call EDXEIX,
 * and does not change trip/submission workflow state.
 */

declare(strict_types=1);

header('X-Robots-Tag: noindex,nofollow', true);
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

require_once __DIR__ . '/_shell.php';

$extensionDir = '/home/cabnet/tools/firefox-edxeix-autofill-helper';
$allowedFiles = ['manifest.json', 'edxeix-fill.js', 'gov-capture.js', 'README.md'];

function fxcenter_file_info(string $baseDir, array $files): array
{
    $rows = [];
    foreach ($files as $file) {
        $path = rtrim($baseDir, '/') . '/' . $file;
        $exists = is_file($path) && is_readable($path);
        $rows[] = [
            'name' => $file,
            'exists' => $exists,
            'size' => $exists ? (int)filesize($path) : 0,
            'mtime' => $exists ? date('Y-m-d H:i:s', (int)filemtime($path)) : '',
            'sha256' => $exists ? hash_file('sha256', $path) : '',
        ];
    }
    return $rows;
}

function fxcenter_manifest_version(string $baseDir): string
{
    $file = rtrim($baseDir, '/') . '/manifest.json';
    if (!is_file($file) || !is_readable($file)) {
        return '';
    }
    $json = json_decode((string)file_get_contents($file), true);
    return is_array($json) ? trim((string)($json['version'] ?? '')) : '';
}

function fxcenter_manifest_name(string $baseDir): string
{
    $file = rtrim($baseDir, '/') . '/manifest.json';
    if (!is_file($file) || !is_readable($file)) {
        return 'Firefox EDXEIX Helper';
    }
    $json = json_decode((string)file_get_contents($file), true);
    return is_array($json) && trim((string)($json['name'] ?? '')) !== ''
        ? trim((string)$json['name'])
        : 'Firefox EDXEIX Helper';
}

function fxcenter_zip_download(string $extensionDir, array $allowedFiles): void
{
    $missing = [];
    foreach ($allowedFiles as $file) {
        if (!is_file(rtrim($extensionDir, '/') . '/' . $file)) {
            $missing[] = $file;
        }
    }

    if ($missing !== []) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Extension files missing: ' . implode(', ', $missing);
        exit;
    }

    if (!class_exists(ZipArchive::class)) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'ZipArchive is not available on this PHP installation.';
        exit;
    }

    $tmp = tempnam(sys_get_temp_dir(), 'gov-edxeix-extension-');
    if ($tmp === false) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Unable to create temporary ZIP file.';
        exit;
    }

    $zip = new ZipArchive();
    if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
        @unlink($tmp);
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Unable to open temporary ZIP file.';
        exit;
    }

    foreach ($allowedFiles as $file) {
        $zip->addFile(rtrim($extensionDir, '/') . '/' . $file, $file);
    }
    $zip->close();

    $version = fxcenter_manifest_version($extensionDir);
    $versionPart = $version !== '' ? 'v' . preg_replace('/[^A-Za-z0-9._-]+/', '-', $version) . '-' : '';
    $name = 'firefox-edxeix-autofill-helper-' . $versionPart . date('Ymd-His') . '.zip';

    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $name . '"');
    header('Content-Length: ' . (string)filesize($tmp));
    readfile($tmp);
    @unlink($tmp);
    exit;
}

if (($_GET['download'] ?? '') === 'zip') {
    fxcenter_zip_download($extensionDir, $allowedFiles);
}

header('Content-Type: text/html; charset=utf-8');

$fileRows = fxcenter_file_info($extensionDir, $allowedFiles);
$missing = array_values(array_filter($fileRows, static fn(array $row): bool => !$row['exists']));
$ready = $missing === [];
$manifestName = fxcenter_manifest_name($extensionDir);
$manifestVersion = fxcenter_manifest_version($extensionDir);
$totalSize = array_sum(array_map(static fn(array $row): int => (int)$row['size'], $fileRows));
$latestMtime = '';
foreach ($fileRows as $row) {
    if ($row['mtime'] !== '' && ($latestMtime === '' || $row['mtime'] > $latestMtime)) {
        $latestMtime = $row['mtime'];
    }
}

opsui_shell_begin([
    'title' => 'Firefox Helper Center',
    'page_title' => 'Firefox helper center',
    'subtitle' => 'Authenticated browser helper package downloads',
    'breadcrumbs' => 'Αρχική / Administration / Firefox helper',
    'active_section' => 'Firefox Helper',
    'safe_notice' => 'Read-only helper package center. This page packages approved local helper files for download only. It does not call Bolt, EDXEIX, or AADE, and it does not submit any form.',
]);
?>
<section class="card hero <?= $ready ? 'good' : 'warn' ?>">
    <h1>Firefox EDXEIX Autofill Helper</h1>
    <p>Download the current helper package from the server. Staff still load the helper locally unless we later move to a signed XPI or Firefox Enterprise policy deployment.</p>
    <div>
        <?= opsui_badge($ready ? 'READY TO DOWNLOAD' : 'CHECK FILES', $ready ? 'good' : 'warn') ?>
        <?= opsui_badge('AUTHENTICATED', 'good') ?>
        <?= opsui_badge('NO COOKIES OR TOKENS INCLUDED', 'warn') ?>
        <?= opsui_badge('NO LIVE SUBMIT CHANGE', 'good') ?>
    </div>
    <div class="grid" style="margin-top:14px">
        <?= opsui_metric($manifestVersion !== '' ? $manifestVersion : 'unknown', 'Manifest version') ?>
        <?= opsui_metric((string)count($allowedFiles), 'Expected files') ?>
        <?= opsui_metric((string)count($missing), 'Missing files') ?>
        <?= opsui_metric($latestMtime !== '' ? $latestMtime : 'n/a', 'Latest file time') ?>
    </div>
    <div class="actions">
        <?php if ($ready): ?>
            <a class="btn good" href="/ops/firefox-extension.php?download=zip">Download current helper ZIP</a>
        <?php else: ?>
            <span class="btn dark" aria-disabled="true">Download blocked until files exist</span>
        <?php endif; ?>
        <a class="btn" href="/ops/profile-activity.php">My Activity</a>
        <a class="btn dark" href="/ops/route-index.php">Route Index</a>
    </div>
</section>

<section class="two">
    <div class="card">
        <h2>Package source</h2>
        <div class="gov-code-path"><?= opsui_h($extensionDir) ?></div>
        <div class="kv" style="margin-top:14px">
            <div class="k">Package name</div><div><?= opsui_h($manifestName) ?></div>
            <div class="k">Manifest version</div><div><?= opsui_h($manifestVersion !== '' ? $manifestVersion : 'unknown') ?></div>
            <div class="k">Total size</div><div><?= opsui_h(number_format($totalSize)) ?> bytes</div>
            <div class="k">Status</div><div><?= $ready ? opsui_badge('READY', 'good') : opsui_badge('CHECK', 'warn') ?></div>
        </div>
    </div>

    <div class="card">
        <h2>Loading method today</h2>
        <ol class="list">
            <li>Download the helper ZIP from this page.</li>
            <li>Extract it to a local folder on the staff computer.</li>
            <li>Open Firefox: <code>about:debugging#/runtime/this-firefox</code>.</li>
            <li>Click <strong>Load Temporary Add-on</strong>.</li>
            <li>Select <code>manifest.json</code> inside the extracted folder.</li>
        </ol>
    </div>
</section>

<section class="card">
    <h2>Helper files included in ZIP</h2>
    <div class="table-wrap">
        <table class="gov-log-table gov-helper-table">
            <thead>
            <tr>
                <th>File</th>
                <th>Status</th>
                <th>Size</th>
                <th>Modified</th>
                <th>SHA-256</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($fileRows as $row): ?>
                <tr>
                    <td class="gov-nowrap"><code><?= opsui_h($row['name']) ?></code></td>
                    <td><?= $row['exists'] ? opsui_badge('PRESENT', 'good') : opsui_badge('MISSING', 'bad') ?></td>
                    <td><?= $row['exists'] ? opsui_h(number_format((int)$row['size']) . ' bytes') : '—' ?></td>
                    <td><?= opsui_h($row['mtime'] !== '' ? $row['mtime'] : '—') ?></td>
                    <td class="gov-truncate-cell mono" title="<?= opsui_h($row['sha256']) ?>"><?= opsui_h($row['sha256'] !== '' ? $row['sha256'] : '—') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="card">
    <h2>Future central-update path</h2>
    <p>For true common-source updates, the helper should later become a signed Firefox XPI with a stable extension ID and update manifest. This page is the operational bridge until that packaging step is ready.</p>
    <div class="gov-admin-grid">
        <div class="gov-dashboard-card"><strong>Current mode</strong><span>Authenticated ZIP download. Each staff computer loads the extension locally.</span></div>
        <div class="gov-dashboard-card"><strong>Next mode</strong><span>Self-distributed signed XPI hosted on gov.cabnet.app with update manifest.</span></div>
        <div class="gov-dashboard-card"><strong>Office mode</strong><span>Firefox Enterprise policy can point staff machines to the hosted XPI install URL.</span></div>
    </div>
</section>
<?php
opsui_shell_end();
