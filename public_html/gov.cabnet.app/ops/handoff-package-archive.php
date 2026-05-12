<?php
/**
 * gov.cabnet.app — Handoff Package Archive + GUI Builder
 *
 * Admin-only private archive for Safe Handoff ZIP packages.
 * Lets an admin build, list, download, and delete generated packages stored outside the public webroot.
 *
 * Safety contract:
 * - Does not call Bolt.
 * - Does not call EDXEIX.
 * - Does not call AADE.
 * - Does not modify the production pre-ride tool.
 * - Does not copy real server-only config values; builder generates sanitized config placeholders only.
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

$homeRoot = dirname(__DIR__, 3);
$appRoot = $homeRoot . '/gov.cabnet.app_app';
$packageDir = $appRoot . '/var/handoff-packages';
$builderFile = $appRoot . '/src/Support/SafeHandoffPackageBuilder.php';
$validatorFile = $appRoot . '/src/Support/SafeHandoffPackageValidator.php';

if (is_file($builderFile)) {
    require_once $builderFile;
}
if (is_file($validatorFile)) {
    require_once $validatorFile;
}

function hpa_h(mixed $value): string
{
    if (function_exists('opsui_h')) {
        return opsui_h($value);
    }
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function hpa_admin_required(): void
{
    if (!function_exists('opsui_is_admin') || !opsui_is_admin()) {
        http_response_code(403);
        echo 'Forbidden. Admin role required.';
        exit;
    }
}

function hpa_csrf_token(): string
{
    if (empty($_SESSION['handoff_package_archive_csrf']) || !is_string($_SESSION['handoff_package_archive_csrf'])) {
        $_SESSION['handoff_package_archive_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['handoff_package_archive_csrf'];
}

function hpa_validate_csrf(string $token): bool
{
    return isset($_SESSION['handoff_package_archive_csrf'])
        && is_string($_SESSION['handoff_package_archive_csrf'])
        && hash_equals($_SESSION['handoff_package_archive_csrf'], $token);
}

function hpa_is_safe_package_name(string $name): bool
{
    return (bool)preg_match('/^gov_cabnet_safe_handoff_\d{8}_\d{6}_(with_db|no_db)\.zip$/', $name);
}

function hpa_package_path(string $dir, string $name): string
{
    return rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . basename($name);
}

function hpa_format_bytes(int $bytes): string
{
    if ($bytes >= 1073741824) { return number_format($bytes / 1073741824, 2) . ' GB'; }
    if ($bytes >= 1048576) { return number_format($bytes / 1048576, 2) . ' MB'; }
    if ($bytes >= 1024) { return number_format($bytes / 1024, 2) . ' KB'; }
    return $bytes . ' B';
}

/**
 * @return list<array{name:string,path:string,size:int,mtime:int,sha256:string,with_db:bool,readable:bool,writable:bool}>
 */
function hpa_list_packages(string $dir): array
{
    if (!is_dir($dir) || !is_readable($dir)) {
        return [];
    }

    $out = [];
    $items = @scandir($dir);
    if (!is_array($items)) {
        return [];
    }

    foreach ($items as $item) {
        if (!hpa_is_safe_package_name($item)) {
            continue;
        }
        $path = hpa_package_path($dir, $item);
        if (!is_file($path)) {
            continue;
        }
        $size = filesize($path);
        $mtime = filemtime($path);
        $hash = is_readable($path) ? hash_file('sha256', $path) : '';
        $out[] = [
            'name' => $item,
            'path' => $path,
            'size' => $size !== false ? (int)$size : 0,
            'mtime' => $mtime !== false ? (int)$mtime : 0,
            'sha256' => is_string($hash) ? $hash : '',
            'with_db' => str_contains($item, '_with_db.zip'),
            'readable' => is_readable($path),
            'writable' => is_writable($path),
        ];
    }

    usort($out, static function (array $a, array $b): int {
        return ($b['mtime'] <=> $a['mtime']) ?: strcmp($b['name'], $a['name']);
    });

    return $out;
}

function hpa_ensure_package_dir(string $dir): void
{
    if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
        throw new RuntimeException('Unable to create package directory: ' . $dir);
    }
    @chmod(dirname($dir), 0750);
    @chmod($dir, 0750);
}

function hpa_build_package(string $homeRoot, string $packageDir, bool $includeDb): array
{
    if (!class_exists(\Bridge\Support\SafeHandoffPackageBuilder::class)) {
        throw new RuntimeException('SafeHandoffPackageBuilder is not installed.');
    }

    @set_time_limit(600);
    hpa_ensure_package_dir($packageDir);

    $builder = new \Bridge\Support\SafeHandoffPackageBuilder([
        'homeRoot' => $homeRoot,
        'publicRoot' => $homeRoot . '/public_html/gov.cabnet.app',
        'appRoot' => $homeRoot . '/gov.cabnet.app_app',
        'configRoot' => $homeRoot . '/gov.cabnet.app_config',
        'sqlRoot' => $homeRoot . '/gov.cabnet.app_sql',
        'docsRoot' => $homeRoot . '/docs',
        'toolsRoot' => $homeRoot . '/tools',
        'bootstrap' => $homeRoot . '/gov.cabnet.app_app/src/bootstrap.php',
    ]);

    $tmpZip = $builder->build(['include_database' => $includeDb]);
    $finalName = 'gov_cabnet_safe_handoff_' . date('Ymd_His') . ($includeDb ? '_with_db' : '_no_db') . '.zip';
    $finalPath = hpa_package_path($packageDir, $finalName);

    if (!@rename($tmpZip, $finalPath)) {
        if (!@copy($tmpZip, $finalPath)) {
            @unlink($tmpZip);
            throw new RuntimeException('Unable to move generated package into archive directory.');
        }
        @unlink($tmpZip);
    }

    @chmod($finalPath, 0640);
    clearstatcache(true, $finalPath);

    $size = filesize($finalPath);
    $hash = hash_file('sha256', $finalPath);

    return [
        'name' => $finalName,
        'path' => $finalPath,
        'size' => $size !== false ? (int)$size : 0,
        'sha256' => is_string($hash) ? $hash : '',
        'include_database' => $includeDb,
    ];
}

function hpa_validate_package_if_available(string $path): array
{
    if (!class_exists(\Bridge\Support\SafeHandoffPackageValidator::class)) {
        return ['available' => false, 'ok' => false, 'status' => 'VALIDATOR NOT INSTALLED', 'warnings' => [], 'errors' => []];
    }
    try {
        $validator = new \Bridge\Support\SafeHandoffPackageValidator();
        $result = $validator->validate($path);
        return [
            'available' => true,
            'ok' => !empty($result['ok']),
            'status' => !empty($result['ok']) ? 'OK' : 'CHECK',
            'warnings' => is_array($result['warnings'] ?? null) ? $result['warnings'] : [],
            'errors' => is_array($result['errors'] ?? null) ? $result['errors'] : [],
        ];
    } catch (Throwable $e) {
        return ['available' => true, 'ok' => false, 'status' => 'VALIDATION ERROR', 'warnings' => [], 'errors' => [$e->getMessage()]];
    }
}

$notice = '';
$error = '';
$built = null;
$validated = null;
$csrf = hpa_csrf_token();

if ((string)($_GET['action'] ?? '') === 'download') {
    hpa_admin_required();
    $file = basename((string)($_GET['file'] ?? ''));
    if (!hpa_is_safe_package_name($file)) {
        http_response_code(400);
        echo 'Invalid package name.';
        exit;
    }
    $path = hpa_package_path($packageDir, $file);
    if (!is_file($path) || !is_readable($path)) {
        http_response_code(404);
        echo 'Package not found or not readable.';
        exit;
    }

    header_remove('Content-Type');
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $file . '"');
    header('Content-Length: ' . (string)filesize($path));
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    readfile($path);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        hpa_admin_required();
        if (!hpa_validate_csrf((string)($_POST['csrf'] ?? ''))) {
            throw new RuntimeException('Security token expired. Please reload and try again.');
        }

        $action = (string)($_POST['action'] ?? '');
        if ($action === 'build_archive_package') {
            $includeDb = (string)($_POST['include_database'] ?? '1') === '1';
            $built = hpa_build_package($homeRoot, $packageDir, $includeDb);
            $validated = hpa_validate_package_if_available((string)$built['path']);
            $notice = 'Archive package created: ' . (string)$built['name'];
        } elseif ($action === 'delete_package') {
            $file = basename((string)($_POST['file'] ?? ''));
            if (!hpa_is_safe_package_name($file)) {
                throw new RuntimeException('Invalid package name.');
            }
            $path = hpa_package_path($packageDir, $file);
            if (!is_file($path)) {
                throw new RuntimeException('Package was not found.');
            }
            if (!@unlink($path)) {
                throw new RuntimeException('Unable to delete package. Check permissions.');
            }
            $notice = 'Deleted package: ' . $file;
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$packages = hpa_list_packages($packageDir);
$latest = $packages[0] ?? null;
$dirExists = is_dir($packageDir);
$dirReadable = is_readable($packageDir);
$dirWritable = is_writable($packageDir);
$builderInstalled = class_exists(\Bridge\Support\SafeHandoffPackageBuilder::class);
$validatorInstalled = class_exists(\Bridge\Support\SafeHandoffPackageValidator::class);

opsui_shell_begin([
    'title' => 'Handoff Package Archive',
    'page_title' => 'Handoff Package Archive',
    'active_section' => 'Deployment',
    'breadcrumbs' => 'Αρχική / Handoff / Package Archive',
    'safe_notice' => 'Admin-only private archive for generated Safe Handoff ZIPs. Packages with DATABASE_EXPORT.sql are private operational material.',
    'force_safe_notice' => true,
]);
?>
<section class="card hero neutral">
    <h1>Handoff Package Archive</h1>
    <p>Build, list, download, and delete private Safe Handoff ZIP packages stored outside the public webroot.</p>
    <div>
        <?= opsui_badge('ADMIN ONLY', opsui_is_admin() ? 'good' : 'warn') ?>
        <?= opsui_badge('BUILDER ' . ($builderInstalled ? 'READY' : 'MISSING'), $builderInstalled ? 'good' : 'bad') ?>
        <?= opsui_badge('VALIDATOR ' . ($validatorInstalled ? 'READY' : 'MISSING'), $validatorInstalled ? 'good' : 'warn') ?>
        <?= opsui_badge('NO EDXEIX CALL', 'good') ?>
    </div>
    <div class="actions">
        <a class="btn dark" href="/ops/handoff-package-tools.php">Package Tools</a>
        <a class="btn dark" href="/ops/handoff-package-validator.php">Validator</a>
        <a class="btn dark" href="/ops/handoff-package-cli.php">CLI Guide</a>
        <a class="btn dark" href="/ops/handoff-center.php">Handoff Center</a>
    </div>
</section>

<?php if ($notice !== ''): ?>
<section class="card" style="border-left:6px solid #07875a;">
    <h2>Done</h2>
    <p class="goodline"><strong><?= hpa_h($notice) ?></strong></p>
    <?php if (is_array($built)): ?>
        <div class="grid">
            <div class="metric"><strong><?= hpa_h(hpa_format_bytes((int)$built['size'])) ?></strong><span>Package size</span></div>
            <div class="metric"><strong><?= hpa_h(!empty($built['include_database']) ? 'YES' : 'NO') ?></strong><span>Database export</span></div>
            <div class="metric"><strong><?= hpa_h(substr((string)$built['sha256'], 0, 16)) ?></strong><span>SHA-256 prefix</span></div>
        </div>
    <?php endif; ?>
    <?php if (is_array($validated) && !empty($validated['available'])): ?>
        <p>Validation: <?= opsui_badge((string)$validated['status'], !empty($validated['ok']) ? 'good' : 'warn') ?></p>
        <?php foreach ((array)$validated['errors'] as $err): ?><p class="badline">ERROR: <?= hpa_h($err) ?></p><?php endforeach; ?>
        <?php foreach ((array)$validated['warnings'] as $warn): ?><p class="warnline">WARN: <?= hpa_h($warn) ?></p><?php endforeach; ?>
    <?php endif; ?>
</section>
<?php endif; ?>

<?php if ($error !== ''): ?>
<section class="card" style="border-left:6px solid #b42318;">
    <h2>Archive action failed</h2>
    <p class="badline"><strong><?= hpa_h($error) ?></strong></p>
</section>
<?php endif; ?>

<section class="card">
    <h2>Build new archive package</h2>
    <p>This creates a persistent private package in <code><?= hpa_h($packageDir) ?></code>. Use this instead of SSH when the package is small enough for a normal web request.</p>
    <div class="safety" style="margin:12px 0;">
        <strong>PRIVATE PACKAGE WARNING.</strong>
        If the database export is included, the ZIP may contain operational/customer data. Do not commit it to GitHub unless intentionally sanitized.
    </div>
    <form method="post" action="/ops/handoff-package-archive.php" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
        <input type="hidden" name="csrf" value="<?= hpa_h($csrf) ?>">
        <input type="hidden" name="action" value="build_archive_package">
        <button class="btn green" type="submit" name="include_database" value="1" <?= (!$builderInstalled || !opsui_is_admin() || !$dirWritable) ? 'disabled' : '' ?>>Build archived ZIP with database</button>
        <button class="btn dark" type="submit" name="include_database" value="0" <?= (!$builderInstalled || !opsui_is_admin() || !$dirWritable) ? 'disabled' : '' ?>>Build archived ZIP without database</button>
    </form>
    <ul class="list" style="margin-top:14px;">
        <li>The builder generates sanitized config placeholders only; it does not copy real config values.</li>
        <li>The package is saved outside the public webroot and then appears in the archive list below.</li>
        <li>If a browser timeout occurs, use the CLI builder as fallback.</li>
    </ul>
</section>

<section class="two">
    <div class="card">
        <h2>Archive directory</h2>
        <div class="metric"><strong><?= hpa_h($dirExists ? 'YES' : 'NO') ?></strong><span>Directory exists</span></div>
        <p><?= opsui_badge($dirReadable ? 'READABLE' : 'NOT READABLE', $dirReadable ? 'good' : 'bad') ?> <?= opsui_badge($dirWritable ? 'WRITABLE' : 'NOT WRITABLE', $dirWritable ? 'good' : 'bad') ?></p>
        <p><code><?= hpa_h($packageDir) ?></code></p>
    </div>
    <div class="card">
        <h2>Latest package</h2>
        <?php if (is_array($latest)): ?>
            <p><strong><?= hpa_h($latest['name']) ?></strong></p>
            <p><?= opsui_badge($latest['with_db'] ? 'WITH DB' : 'NO DB', $latest['with_db'] ? 'warn' : 'good') ?> <?= opsui_badge(hpa_format_bytes((int)$latest['size']), 'neutral') ?></p>
            <p class="small">Modified: <?= hpa_h(date('Y-m-d H:i:s T', (int)$latest['mtime'])) ?></p>
            <p><code><?= hpa_h(substr((string)$latest['sha256'], 0, 32)) ?>...</code></p>
        <?php else: ?>
            <p>No archived packages found.</p>
        <?php endif; ?>
    </div>
</section>

<section class="card">
    <h2>Archived packages</h2>
    <?php if (!$packages): ?>
        <p>No packages found yet. Build one above or use the CLI builder.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Package</th>
                        <th>Type</th>
                        <th>Size</th>
                        <th>Modified</th>
                        <th>SHA-256</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($packages as $pkg): ?>
                    <tr>
                        <td><strong><?= hpa_h($pkg['name']) ?></strong></td>
                        <td><?= opsui_badge($pkg['with_db'] ? 'WITH DB' : 'NO DB', $pkg['with_db'] ? 'warn' : 'good') ?></td>
                        <td><?= hpa_h(hpa_format_bytes((int)$pkg['size'])) ?></td>
                        <td><?= hpa_h(date('Y-m-d H:i:s T', (int)$pkg['mtime'])) ?></td>
                        <td><code><?= hpa_h(substr((string)$pkg['sha256'], 0, 24)) ?>...</code></td>
                        <td>
                            <div class="actions" style="margin:0;">
                                <a class="btn" href="/ops/handoff-package-archive.php?action=download&amp;file=<?= rawurlencode((string)$pkg['name']) ?>">Download</a>
                                <form method="post" action="/ops/handoff-package-archive.php" onsubmit="return confirm('Delete this package from the private archive?');" style="display:inline;">
                                    <input type="hidden" name="csrf" value="<?= hpa_h($csrf) ?>">
                                    <input type="hidden" name="action" value="delete_package">
                                    <input type="hidden" name="file" value="<?= hpa_h($pkg['name']) ?>">
                                    <button class="btn orange" type="submit">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<section class="card">
    <h2>CLI fallback</h2>
    <p>If the browser build ever times out, run this over SSH:</p>
    <textarea readonly style="width:100%;min-height:110px;border:1px solid #d8dde7;border-radius:4px;padding:14px;font-family:Consolas,Menlo,monospace;font-size:13px;line-height:1.45;">su -s /bin/bash cabnet -c 'php /home/cabnet/gov.cabnet.app_app/cli/build_safe_handoff_package.php --json'
su -s /bin/bash cabnet -c 'php /home/cabnet/gov.cabnet.app_app/cli/validate_safe_handoff_package.php --latest'</textarea>
</section>
<?php opsui_shell_end(); ?>
