<?php
/**
 * gov.cabnet.app — Handoff Package CLI Guide
 *
 * Admin-only read-only page with SSH/cPanel terminal commands for building the
 * Safe Handoff ZIP outside the browser request path.
 */

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Robots-Tag: noindex,nofollow', true);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/_shell.php';

function hpcli_h(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function hpcli_file_status(string $path): array
{
    $status = [
        'exists' => is_file($path),
        'readable' => is_file($path) && is_readable($path),
        'mtime' => '',
        'size' => '',
    ];

    if ($status['exists']) {
        $mtime = @filemtime($path);
        $size = @filesize($path);
        $status['mtime'] = $mtime ? date('Y-m-d H:i:s', $mtime) : '';
        $status['size'] = $size !== false ? number_format((float)$size) . ' bytes' : '';
    }

    return $status;
}

/**
 * @return array<int,array{name:string,path:string,size:string,mtime:string,sha256:string}>
 */
function hpcli_recent_packages(string $dir): array
{
    $rows = [];
    if (!is_dir($dir)) {
        return $rows;
    }

    $items = @scandir($dir);
    if (!is_array($items)) {
        return $rows;
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        if (!preg_match('/^gov_cabnet_safe_handoff_.*\.zip$/', $item)) {
            continue;
        }
        $path = rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $item;
        if (!is_file($path)) {
            continue;
        }

        $size = @filesize($path);
        $mtime = @filemtime($path);
        $rows[] = [
            'name' => $item,
            'path' => $path,
            'size' => $size !== false ? number_format((float)$size) . ' bytes' : '',
            'mtime' => $mtime ? date('Y-m-d H:i:s', $mtime) : '',
            'sha256' => is_readable($path) ? (string)hash_file('sha256', $path) : '',
        ];
    }

    usort($rows, static function (array $a, array $b): int {
        return strcmp((string)$b['mtime'], (string)$a['mtime']);
    });

    return array_slice($rows, 0, 10);
}

if (!opsui_is_admin()) {
    http_response_code(403);
    opsui_shell_begin([
        'title' => 'Handoff Package CLI',
        'page_title' => 'Handoff Package CLI',
        'active_section' => 'Deployment',
        'breadcrumbs' => 'Αρχική / Deployment / Handoff Package CLI',
        'safe_notice' => 'Admin role required.',
        'force_safe_notice' => true,
    ]);
    echo '<section class="card"><h1>Forbidden</h1><p>Admin role is required to view this page.</p></section>';
    opsui_shell_end();
    exit;
}

$homeRoot = dirname(__DIR__, 3);
$appRoot = $homeRoot . '/gov.cabnet.app_app';
$cliPath = $appRoot . '/cli/build_safe_handoff_package.php';
$builderPath = $appRoot . '/src/Support/SafeHandoffPackageBuilder.php';
$outputDir = $appRoot . '/var/handoff-packages';

$cliStatus = hpcli_file_status($cliPath);
$builderStatus = hpcli_file_status($builderPath);
$recentPackages = hpcli_recent_packages($outputDir);

$cmdBuild = 'php ' . $cliPath;
$cmdBuildJson = 'php ' . $cliPath . ' --json';
$cmdWithDb = 'php ' . $cliPath . ' --include-db';
$cmdWithDbJson = 'php ' . $cliPath . ' --include-db --json';
$cmdCleanup = 'php ' . $cliPath . ' --cleanup --keep-days=7';

opsui_shell_begin([
    'title' => 'Handoff Package CLI',
    'page_title' => 'Handoff Package CLI',
    'active_section' => 'Deployment',
    'breadcrumbs' => 'Αρχική / Deployment / Handoff Package CLI',
    'safe_notice' => 'Admin-only read-only guide for building safe handoff packages through SSH/cPanel Terminal. This page does not build a ZIP and does not export the database.',
    'force_safe_notice' => true,
]);
?>
<section class="card hero neutral">
    <h1>Safe Handoff Package CLI</h1>
    <p>Use this when the browser download builder is too slow or too large. The CLI builds the same safe handoff ZIP in a private server directory outside the public webroot.</p>
    <div>
        <?= opsui_badge('ADMIN ONLY', 'good') ?>
        <?= opsui_badge('READ ONLY PAGE', 'good') ?>
        <?= opsui_badge('NO ZIP BUILT HERE', 'neutral') ?>
        <?= opsui_badge('NO SECRET OUTPUT', 'good') ?>
    </div>
    <div class="actions">
        <a class="btn" href="/ops/handoff-center.php">Handoff Center</a>
        <a class="btn dark" href="/ops/handoff-package-inspector.php">Package Inspector</a>
        <a class="btn dark" href="/ops/deployment-center.php">Deployment Center</a>
    </div>
</section>

<section class="card">
    <h2>CLI readiness</h2>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Item</th><th>Status</th><th>Path</th><th>Modified</th><th>Size</th></tr></thead>
            <tbody>
                <tr>
                    <td><strong>CLI runner</strong></td>
                    <td><?= opsui_bool_badge((bool)$cliStatus['readable'], 'READY', 'MISSING') ?></td>
                    <td><code><?= hpcli_h($cliPath) ?></code></td>
                    <td><?= hpcli_h($cliStatus['mtime']) ?></td>
                    <td><?= hpcli_h($cliStatus['size']) ?></td>
                </tr>
                <tr>
                    <td><strong>Builder class</strong></td>
                    <td><?= opsui_bool_badge((bool)$builderStatus['readable'], 'READY', 'MISSING') ?></td>
                    <td><code><?= hpcli_h($builderPath) ?></code></td>
                    <td><?= hpcli_h($builderStatus['mtime']) ?></td>
                    <td><?= hpcli_h($builderStatus['size']) ?></td>
                </tr>
                <tr>
                    <td><strong>Private output directory</strong></td>
                    <td><?= opsui_badge(is_dir($outputDir) ? 'PRESENT' : 'CREATED BY CLI', is_dir($outputDir) ? 'good' : 'neutral') ?></td>
                    <td><code><?= hpcli_h($outputDir) ?></code></td>
                    <td colspan="2">Permission target: <code>0700</code>; ZIP target: <code>0600</code></td>
                </tr>
            </tbody>
        </table>
    </div>
</section>

<section class="card">
    <h2>Copy/paste commands</h2>
    <p class="small">Run these from SSH or cPanel Terminal. The generated ZIP remains private under <code><?= hpcli_h($outputDir) ?></code>.</p>

    <h3>Build DB-free package</h3>
    <textarea readonly style="width:100%;min-height:54px;font-family:Consolas,Menlo,monospace;"><?= hpcli_h($cmdBuild) ?></textarea>

    <h3>Build DB-free package and print JSON</h3>
    <textarea readonly style="width:100%;min-height:54px;font-family:Consolas,Menlo,monospace;"><?= hpcli_h($cmdBuildJson) ?></textarea>

    <h3>Build private DB audit package</h3>
    <textarea readonly style="width:100%;min-height:54px;font-family:Consolas,Menlo,monospace;"><?= hpcli_h($cmdWithDb) ?></textarea>

    <h3>Build private DB audit package and print JSON</h3>
    <textarea readonly style="width:100%;min-height:54px;font-family:Consolas,Menlo,monospace;"><?= hpcli_h($cmdWithDbJson) ?></textarea>

    <h3>Cleanup old generated packages</h3>
    <textarea readonly style="width:100%;min-height:54px;font-family:Consolas,Menlo,monospace;"><?= hpcli_h($cmdCleanup) ?></textarea>
</section>

<section class="card">
    <h2>Recent private packages</h2>
    <?php if (!$recentPackages): ?>
        <p>No generated handoff packages found yet in the private output directory.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Package</th><th>Size</th><th>Modified</th><th>SHA-256</th><th>Private path</th></tr></thead>
                <tbody>
                    <?php foreach ($recentPackages as $pkg): ?>
                        <tr>
                            <td><strong><?= hpcli_h($pkg['name']) ?></strong></td>
                            <td><?= hpcli_h($pkg['size']) ?></td>
                            <td><?= hpcli_h($pkg['mtime']) ?></td>
                            <td><code><?= hpcli_h(substr($pkg['sha256'], 0, 16)) ?>…</code></td>
                            <td><code><?= hpcli_h($pkg['path']) ?></code></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="small">Download via cPanel File Manager from the private path, or move it manually to a temporary protected location only when needed. Delete it after use.</p>
    <?php endif; ?>
</section>

<section class="two">
    <div class="card">
        <h2>Safety rules</h2>
        <ul class="list">
            <li>The CLI does not copy real config files from <code>gov.cabnet.app_config</code>.</li>
            <li>Sanitized config placeholders are generated under <code>gov.cabnet.app_config_examples/</code>.</li>
            <li>CLI package generation is DB-free by default.</li>
            <li>Database export requires <code>--include-db</code>; that ZIP is private operational material.</li>
            <li>Do not commit <code>DATABASE_EXPORT.sql</code> unless you intentionally sanitize it first.</li>
        </ul>
    </div>
    <div class="card">
        <h2>Recommended use</h2>
        <ol class="list">
            <li>Open Package Inspector first.</li>
            <li>Run the CLI from SSH/cPanel Terminal.</li>
            <li>Download the ZIP from the private output path.</li>
            <li>Delete old packages after confirming transfer.</li>
        </ol>
    </div>
</section>
<?php opsui_shell_end(); ?>
