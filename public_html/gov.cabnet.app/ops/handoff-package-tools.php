<?php
/**
 * gov.cabnet.app — Handoff Package Tools
 *
 * Admin-only read-only navigation hub for the Safe Handoff Package subsystem.
 * No ZIP build, no DB export, no secret output, no external calls.
 */

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Robots-Tag: noindex,nofollow', true);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/_shell.php';

function hpt_h(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function hpt_status(string $path): array
{
    if (is_file($path)) {
        return [
            'exists' => true,
            'readable' => is_readable($path),
            'size' => @filesize($path) ?: 0,
            'mtime' => @filemtime($path) ?: 0,
            'sha' => is_readable($path) ? substr((string)hash_file('sha256', $path), 0, 16) : '',
        ];
    }

    return [
        'exists' => false,
        'readable' => false,
        'size' => 0,
        'mtime' => 0,
        'sha' => '',
    ];
}

function hpt_fmt_size(int $bytes): string
{
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    }
    if ($bytes >= 1024) {
        return number_format($bytes / 1024, 1) . ' KB';
    }
    return number_format($bytes) . ' B';
}

function hpt_recent_packages(string $dir, int $limit = 5): array
{
    if (!is_dir($dir) || !is_readable($dir)) {
        return [];
    }

    $rows = [];
    $items = @scandir($dir);
    if (!is_array($items)) {
        return [];
    }

    foreach ($items as $item) {
        if (!preg_match('/^gov_cabnet_safe_handoff_\d{8}_\d{6}_(with_db|no_db)\.zip$/', $item)) {
            continue;
        }
        $path = rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $item;
        if (!is_file($path)) {
            continue;
        }
        $rows[] = [
            'name' => $item,
            'path' => $path,
            'size' => @filesize($path) ?: 0,
            'mtime' => @filemtime($path) ?: 0,
            'with_db' => str_contains($item, '_with_db.'),
            'sha' => is_readable($path) ? substr((string)hash_file('sha256', $path), 0, 16) : '',
        ];
    }

    usort($rows, static fn(array $a, array $b): int => ($b['mtime'] ?? 0) <=> ($a['mtime'] ?? 0));
    return array_slice($rows, 0, $limit);
}

if (!opsui_is_admin()) {
    opsui_shell_begin([
        'title' => 'Handoff Package Tools',
        'page_title' => 'Handoff Package Tools',
        'active_section' => 'Deployment',
        'breadcrumbs' => 'Αρχική / Διαχειριστικό / Handoff Package Tools',
        'safe_notice' => 'Admin role required. This page does not expose package contents or secrets.',
        'force_safe_notice' => true,
    ]);
    ?>
    <section class="card">
        <h1>Admin role required</h1>
        <p>This page is restricted to admin users because handoff packages may include a private database export.</p>
        <a class="btn dark" href="/ops/home.php">Back to Ops Home</a>
    </section>
    <?php
    opsui_shell_end();
    exit;
}

$homeRoot = dirname(__DIR__, 3);
$appRoot = $homeRoot . '/gov.cabnet.app_app';
$packageDir = $appRoot . '/var/handoff-packages';

$tools = [
    [
        'title' => 'Handoff Center',
        'route' => '/ops/handoff-center.php',
        'path' => __DIR__ . '/handoff-center.php',
        'purpose' => 'Copy/paste prompt and browser Safe Handoff ZIP builder.',
        'risk' => 'Can build and stream a ZIP if admin clicks the build button.',
    ],
    [
        'title' => 'Package Inspector',
        'route' => '/ops/handoff-package-inspector.php',
        'path' => __DIR__ . '/handoff-package-inspector.php',
        'purpose' => 'Read-only environment and packaging scope preview before building.',
        'risk' => 'No ZIP build and no database export.',
    ],
    [
        'title' => 'CLI Builder Guide',
        'route' => '/ops/handoff-package-cli.php',
        'path' => __DIR__ . '/handoff-package-cli.php',
        'purpose' => 'Shows SSH commands for private CLI package generation and cleanup.',
        'risk' => 'Read-only guide page only.',
    ],
    [
        'title' => 'Package Archive',
        'route' => '/ops/handoff-package-archive.php',
        'path' => __DIR__ . '/handoff-package-archive.php',
        'purpose' => 'List, download, and delete CLI-generated private handoff ZIPs.',
        'risk' => 'Downloads private ZIPs and can delete generated package files.',
    ],
    [
        'title' => 'Package Validator',
        'route' => '/ops/handoff-package-validator.php',
        'path' => __DIR__ . '/handoff-package-validator.php',
        'purpose' => 'Validate generated ZIP structure and suspicious entries without extracting.',
        'risk' => 'Read-only validation; does not print database contents.',
    ],
];

$cliFiles = [
    'SafeHandoffPackageBuilder' => $appRoot . '/src/Support/SafeHandoffPackageBuilder.php',
    'SafeHandoffPackageValidator' => $appRoot . '/src/Support/SafeHandoffPackageValidator.php',
    'CLI package builder' => $appRoot . '/cli/build_safe_handoff_package.php',
    'CLI package validator' => $appRoot . '/cli/validate_safe_handoff_package.php',
];

$recentPackages = hpt_recent_packages($packageDir, 6);

opsui_shell_begin([
    'title' => 'Handoff Package Tools',
    'page_title' => 'Handoff Package Tools',
    'active_section' => 'Deployment',
    'breadcrumbs' => 'Αρχική / Διαχειριστικό / Handoff Package Tools',
    'safe_notice' => 'Admin-only navigation hub for Safe Handoff Package tools. This page is read-only and does not build, validate, export, extract, or display package contents.',
    'force_safe_notice' => true,
]);
?>
<section class="card hero neutral">
    <h1>Safe Handoff Package Tools</h1>
    <p>Central hub for project continuity packages: inspect, build via CLI/browser, archive, download, and validate safe handoff ZIPs.</p>
    <div>
        <?= opsui_badge('ADMIN ONLY', 'good') ?>
        <?= opsui_badge('READ ONLY HUB', 'good') ?>
        <?= opsui_badge('NO SECRET OUTPUT', 'good') ?>
        <?= opsui_badge('NO DB EXPORT FROM THIS PAGE', 'good') ?>
    </div>
</section>

<section class="card">
    <h2>Tool shortcuts</h2>
    <div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:14px;">
        <?php foreach ($tools as $tool): $st = hpt_status($tool['path']); ?>
            <div class="metric" style="min-height:170px;">
                <strong style="font-size:20px;"><?= hpt_h($tool['title']) ?></strong>
                <span style="display:block;margin:8px 0 10px;"><?= hpt_h($tool['purpose']) ?></span>
                <div style="margin-bottom:10px;">
                    <?= opsui_badge($st['exists'] ? 'PRESENT' : 'MISSING', $st['exists'] ? 'good' : 'bad') ?>
                    <?= opsui_badge($st['readable'] ? 'READABLE' : 'NOT READABLE', $st['readable'] ? 'good' : 'warn') ?>
                </div>
                <p class="small" style="margin:0 0 12px;"><strong>Risk:</strong> <?= hpt_h($tool['risk']) ?></p>
                <a class="btn" href="<?= hpt_h($tool['route']) ?>">Open</a>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<section class="two">
    <div class="card">
        <h2>CLI command block</h2>
        <p class="small">Use SSH/cPanel Terminal for large packages. CLI packages are stored outside the public webroot.</p>
        <pre style="white-space:pre-wrap;background:#0b1220;color:#dbeafe;padding:14px;border-radius:6px;overflow:auto;"><code>php /home/cabnet/gov.cabnet.app_app/cli/build_safe_handoff_package.php --json
php /home/cabnet/gov.cabnet.app_app/cli/validate_safe_handoff_package.php --latest
php /home/cabnet/gov.cabnet.app_app/cli/build_safe_handoff_package.php --cleanup --keep-days=7</code></pre>
    </div>
    <div class="card">
        <h2>Private package directory</h2>
        <p><code><?= hpt_h($packageDir) ?></code></p>
        <p>
            <?= opsui_badge(is_dir($packageDir) ? 'DIRECTORY EXISTS' : 'DIRECTORY MISSING', is_dir($packageDir) ? 'good' : 'warn') ?>
            <?= opsui_badge(is_readable($packageDir) ? 'READABLE' : 'NOT READABLE', is_readable($packageDir) ? 'good' : 'warn') ?>
            <?= opsui_badge(is_writable($packageDir) ? 'WRITABLE' : 'NOT WRITABLE', is_writable($packageDir) ? 'good' : 'warn') ?>
        </p>
        <p class="small">Packages with <code>_with_db</code> include <code>DATABASE_EXPORT.sql</code> and must remain private operational material.</p>
        <div class="actions">
            <a class="btn dark" href="/ops/handoff-package-archive.php">Open Archive</a>
            <a class="btn dark" href="/ops/handoff-package-validator.php">Open Validator</a>
        </div>
    </div>
</section>

<section class="card">
    <h2>Core backend files</h2>
    <div class="table-wrap">
        <table>
            <thead><tr><th>File</th><th>Status</th><th>Size</th><th>Modified</th><th>SHA-256 short</th></tr></thead>
            <tbody>
                <?php foreach ($cliFiles as $label => $path): $st = hpt_status($path); ?>
                    <tr>
                        <td><strong><?= hpt_h($label) ?></strong><br><code><?= hpt_h($path) ?></code></td>
                        <td><?= opsui_badge($st['exists'] ? 'PRESENT' : 'MISSING', $st['exists'] ? 'good' : 'bad') ?></td>
                        <td><?= hpt_h(hpt_fmt_size((int)$st['size'])) ?></td>
                        <td><?= $st['mtime'] ? hpt_h(date('Y-m-d H:i:s', (int)$st['mtime'])) : '-' ?></td>
                        <td><code><?= hpt_h($st['sha'] ?: '-') ?></code></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="card">
    <h2>Recent private packages</h2>
    <?php if (!$recentPackages): ?>
        <p>No CLI-generated handoff packages found yet.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Package</th><th>Type</th><th>Size</th><th>Modified</th><th>SHA-256 short</th></tr></thead>
                <tbody>
                    <?php foreach ($recentPackages as $pkg): ?>
                        <tr>
                            <td><code><?= hpt_h($pkg['name']) ?></code></td>
                            <td><?= opsui_badge($pkg['with_db'] ? 'WITH DATABASE' : 'NO DATABASE', $pkg['with_db'] ? 'warn' : 'good') ?></td>
                            <td><?= hpt_h(hpt_fmt_size((int)$pkg['size'])) ?></td>
                            <td><?= $pkg['mtime'] ? hpt_h(date('Y-m-d H:i:s', (int)$pkg['mtime'])) : '-' ?></td>
                            <td><code><?= hpt_h($pkg['sha'] ?: '-') ?></code></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<section class="card" style="border-left:6px solid #b85c00;">
    <h2>Handling rules</h2>
    <ul class="list">
        <li>Do not commit generated ZIP files or <code>DATABASE_EXPORT.sql</code> to GitHub.</li>
        <li>Use the validator before trusting or sharing a generated handoff ZIP.</li>
        <li>Real config values must remain server-only under <code>/home/cabnet/gov.cabnet.app_config</code>.</li>
        <li>Generated config files inside the ZIP must remain placeholders under <code>gov.cabnet.app_config_examples/</code>.</li>
    </ul>
</section>
<?php opsui_shell_end(); ?>
