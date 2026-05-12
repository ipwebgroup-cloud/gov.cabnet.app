<?php
/**
 * gov.cabnet.app — Safe Handoff Package Validator GUI
 *
 * Admin-only read-only validation page for generated safe handoff ZIP packages.
 * It does not build packages, export databases, or extract ZIPs.
 */

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Robots-Tag: noindex,nofollow', true);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/_shell.php';

$homeRoot = dirname(__DIR__, 3);
$appRoot = $homeRoot . '/gov.cabnet.app_app';
$validatorFile = $appRoot . '/src/Support/SafeHandoffPackageValidator.php';
if (is_file($validatorFile)) {
    require_once $validatorFile;
}

function hpv_h(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function hpv_is_admin(): bool
{
    return function_exists('opsui_is_admin') && opsui_is_admin();
}

function hpv_package_dir(): string
{
    return dirname(__DIR__, 3) . '/gov.cabnet.app_app/var/handoff-packages';
}

function hpv_safe_filename(string $name): bool
{
    return (bool)preg_match('/^gov_cabnet_safe_handoff_[A-Za-z0-9_\-]+\.zip$/', $name);
}

/** @return list<array<string,mixed>> */
function hpv_packages(): array
{
    $dir = hpv_package_dir();
    if (!is_dir($dir)) {
        return [];
    }
    $items = scandir($dir);
    if (!is_array($items)) {
        return [];
    }
    $rows = [];
    foreach ($items as $item) {
        if (!hpv_safe_filename($item)) {
            continue;
        }
        $path = rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $item;
        if (!is_file($path) || !is_readable($path)) {
            continue;
        }
        $rows[] = [
            'name' => $item,
            'path' => $path,
            'size' => filesize($path) ?: 0,
            'mtime' => filemtime($path) ?: 0,
            'sha256' => hash_file('sha256', $path) ?: '',
            'with_db' => str_contains($item, '_with_db'),
        ];
    }
    usort($rows, static fn(array $a, array $b): int => ((int)$b['mtime']) <=> ((int)$a['mtime']));
    return $rows;
}

$selected = trim((string)($_GET['file'] ?? ''));
$packages = hpv_packages();
if ($selected === '' && $packages) {
    $selected = (string)$packages[0]['name'];
}

$result = null;
$error = '';
if ($selected !== '') {
    if (!hpv_safe_filename($selected)) {
        $error = 'Invalid package filename.';
    } else {
        $path = rtrim(hpv_package_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $selected;
        if (!is_file($path) || !is_readable($path)) {
            $error = 'Selected package was not found or is not readable.';
        } elseif (!class_exists(\Bridge\Support\SafeHandoffPackageValidator::class)) {
            $error = 'SafeHandoffPackageValidator class is not installed.';
        } elseif (!hpv_is_admin()) {
            $error = 'Admin role required.';
        } else {
            try {
                $validator = new \Bridge\Support\SafeHandoffPackageValidator();
                $result = $validator->validate($path);
            } catch (Throwable $e) {
                $error = $e->getMessage();
            }
        }
    }
}

opsui_shell_begin([
    'title' => 'Handoff Package Validator',
    'page_title' => 'Handoff Package Validator',
    'active_section' => 'Deployment',
    'breadcrumbs' => 'Αρχική / Handoff / Package Validator',
    'safe_notice' => 'Admin-only read-only validator. It opens generated ZIP metadata without extracting files and does not display database export contents.',
    'force_safe_notice' => true,
]);
?>
<section class="card hero neutral">
    <h1>Safe handoff package validator</h1>
    <p>Validate generated Safe Handoff ZIP packages before trusting, sharing, or storing them. This page checks for required entries, sanitized config examples, dangerous file paths, and accidental real config inclusion.</p>
    <div>
        <?= opsui_badge('READ ONLY', 'good') ?>
        <?= opsui_badge('ADMIN ONLY', hpv_is_admin() ? 'good' : 'warn') ?>
        <?= opsui_badge('NO ZIP BUILD', 'good') ?>
        <?= opsui_badge('NO DB EXPORT', 'good') ?>
        <?= opsui_badge('NO SECRET OUTPUT', 'good') ?>
    </div>
    <div class="actions">
        <a class="btn dark" href="/ops/handoff-center.php">Handoff Center</a>
        <a class="btn dark" href="/ops/handoff-package-cli.php">CLI Builder</a>
        <a class="btn dark" href="/ops/handoff-package-archive.php">Package Archive</a>
        <a class="btn dark" href="/ops/handoff-package-inspector.php">Inspector</a>
    </div>
</section>

<?php if (!hpv_is_admin()): ?>
<section class="card" style="border-left:6px solid #b45309;">
    <h2>Admin required</h2>
    <p class="warnline"><strong>This validator is admin-only because package names and database-export presence are private operational information.</strong></p>
</section>
<?php else: ?>

<section class="card">
    <h2>Select package</h2>
    <?php if (!$packages): ?>
        <p class="warnline"><strong>No generated handoff ZIPs found.</strong></p>
        <p class="small">Build one using the CLI first:</p>
        <pre><code>php /home/cabnet/gov.cabnet.app_app/cli/build_safe_handoff_package.php --json</code></pre>
    <?php else: ?>
        <form method="get" action="/ops/handoff-package-validator.php" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
            <select name="file" style="min-width:min(100%,520px);padding:10px;border:1px solid #d8dde7;border-radius:4px;">
                <?php foreach ($packages as $pkg): ?>
                    <option value="<?= hpv_h($pkg['name']) ?>" <?= $selected === $pkg['name'] ? 'selected' : '' ?>>
                        <?= hpv_h($pkg['name']) ?> — <?= hpv_h(number_format((float)$pkg['size'])) ?> bytes — <?= hpv_h(date('Y-m-d H:i:s', (int)$pkg['mtime'])) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button class="btn" type="submit">Validate</button>
        </form>
    <?php endif; ?>
</section>

<?php if ($error !== ''): ?>
<section class="card" style="border-left:6px solid #b42318;">
    <h2>Validation error</h2>
    <p class="badline"><strong><?= hpv_h($error) ?></strong></p>
</section>
<?php endif; ?>

<?php if (is_array($result)): ?>
<section class="card">
    <h2>Validation result</h2>
    <div class="grid">
        <div class="metric"><strong><?= !empty($result['ok']) ? 'OK' : 'REVIEW' ?></strong><span>Status</span></div>
        <div class="metric"><strong><?= hpv_h((string)$result['entry_count']) ?></strong><span>ZIP entries</span></div>
        <div class="metric"><strong><?= hpv_h(number_format((float)$result['size_bytes'])) ?></strong><span>Bytes</span></div>
        <div class="metric"><strong><?= !empty($result['has_database_export']) ? 'YES' : 'NO' ?></strong><span>Database export</span></div>
    </div>
    <p style="margin-top:14px;">
        <?= opsui_badge(!empty($result['ok']) ? 'VALIDATION OK' : 'REVIEW REQUIRED', !empty($result['ok']) ? 'good' : 'warn') ?>
        <?= !empty($result['has_sanitized_config_examples']) ? opsui_badge('SANITIZED CONFIG PRESENT', 'good') : opsui_badge('SANITIZED CONFIG MISSING', 'bad') ?>
        <?= !empty($result['has_real_config_directory']) ? opsui_badge('REAL CONFIG FOUND', 'bad') : opsui_badge('NO REAL CONFIG DIRECTORY', 'good') ?>
    </p>
    <div class="table-wrap">
        <table>
            <tbody>
                <tr><th>Package</th><td><code><?= hpv_h((string)$result['zip_name']) ?></code></td></tr>
                <tr><th>Path</th><td><code><?= hpv_h((string)$result['zip_path']) ?></code></td></tr>
                <tr><th>SHA-256</th><td><code><?= hpv_h((string)$result['sha256']) ?></code></td></tr>
            </tbody>
        </table>
    </div>
</section>

<?php if (!empty($result['warnings'])): ?>
<section class="card" style="border-left:6px solid #b45309;">
    <h2>Warnings</h2>
    <ul class="list">
        <?php foreach ((array)$result['warnings'] as $warning): ?>
            <li class="warnline"><?= hpv_h((string)$warning) ?></li>
        <?php endforeach; ?>
    </ul>
</section>
<?php endif; ?>

<?php if (!empty($result['dangerous_entries'])): ?>
<section class="card" style="border-left:6px solid #b42318;">
    <h2>Dangerous or suspicious entries</h2>
    <ul class="list">
        <?php foreach ((array)$result['dangerous_entries'] as $entry): ?>
            <li class="badline"><code><?= hpv_h((string)$entry) ?></code></li>
        <?php endforeach; ?>
    </ul>
</section>
<?php endif; ?>

<section class="two">
    <div class="card">
        <h2>Checks</h2>
        <ul class="list">
            <?php foreach ((array)($result['checks'] ?? []) as $check): ?>
                <li><?= hpv_h((string)$check) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <div class="card">
        <h2>CLI validation</h2>
        <p class="small">Run this from SSH/cPanel Terminal for the latest package:</p>
        <pre><code>php /home/cabnet/gov.cabnet.app_app/cli/validate_safe_handoff_package.php --latest</code></pre>
        <p class="small">JSON mode:</p>
        <pre><code>php /home/cabnet/gov.cabnet.app_app/cli/validate_safe_handoff_package.php --latest --json</code></pre>
    </div>
</section>

<section class="card">
    <h2>Sample entries</h2>
    <div class="table-wrap">
        <table>
            <tbody>
            <?php foreach ((array)($result['sample_entries'] ?? []) as $entry): ?>
                <tr><td><code><?= hpv_h((string)$entry) ?></code></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php endif; ?>
<?php endif; ?>
<?php opsui_shell_end(); ?>
