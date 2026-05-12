<?php
/**
 * gov.cabnet.app — Safe Handoff Package Inspector
 *
 * Admin-only read-only inspector for the Safe Handoff ZIP builder.
 * Does not build a ZIP, does not dump DB data, and does not read/display secrets.
 */

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Robots-Tag: noindex,nofollow', true);

require_once __DIR__ . '/_shell.php';

function hpi_h(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function hpi_badge(string $text, string $type = 'neutral'): string
{
    if (function_exists('opsui_badge')) {
        return opsui_badge($text, $type);
    }
    $safeType = in_array($type, ['good', 'warn', 'bad', 'neutral'], true) ? $type : 'neutral';
    return '<span class="badge badge-' . hpi_h($safeType) . '">' . hpi_h($text) . '</span>';
}

function hpi_root(): string
{
    return dirname(__DIR__, 3);
}

function hpi_status_for_path(string $path, bool $directory = false): array
{
    $exists = $directory ? is_dir($path) : is_file($path);
    if (!$exists) {
        return ['status' => 'missing', 'type' => 'bad', 'detail' => 'Not found'];
    }
    if (!is_readable($path)) {
        return ['status' => 'not_readable', 'type' => 'warn', 'detail' => 'Exists but is not readable'];
    }
    return ['status' => 'ready', 'type' => 'good', 'detail' => 'Readable'];
}

function hpi_is_excluded(string $path): bool
{
    $normalized = str_replace('\\', '/', $path);
    $base = basename($normalized);
    $lower = strtolower($normalized);
    $baseLower = strtolower($base);

    $blockedDirs = [
        '/.git/', '/node_modules/', '/vendor/', '/cache/', '/tmp/', '/temp/', '/logs/', '/log/',
        '/sessions/', '/session/', '/mail/', '/.trash/', '/backups/', '/backup/', '/wordpress-backups/',
        '/storage/framework/cache/', '/storage/logs/', '/access-logs/', '/public_ftp/', '/ssl/', '/.cpanel/',
    ];
    foreach ($blockedDirs as $needle) {
        if (str_contains($lower, $needle)) {
            return true;
        }
    }

    $blockedExtensions = [
        '.zip', '.tar', '.gz', '.tgz', '.bz2', '.xz', '.7z', '.rar', '.bak', '.backup', '.old', '.orig',
        '.log', '.tmp', '.temp', '.cache', '.sess', '.session', '.pem', '.key', '.p12', '.pfx', '.crt', '.csr',
        '.sqlite', '.sqlite3', '.db', '.dump'
    ];
    foreach ($blockedExtensions as $ext) {
        if (str_ends_with($baseLower, $ext)) {
            return true;
        }
    }

    $blockedNameParts = [
        'password', 'passwd', 'secret', 'token', 'cookie', 'session', 'credential', 'private_key', 'id_rsa',
        'aade', 'edxeix_cookie', 'csrf', 'raw_payload', 'mailbox', 'imap', 'smtp_password'
    ];
    foreach ($blockedNameParts as $part) {
        if (str_contains($baseLower, $part)) {
            return true;
        }
    }

    return false;
}

function hpi_scan_dir(string $root, int $maxFiles = 1200): array
{
    $out = [
        'exists' => is_dir($root),
        'readable' => is_readable($root),
        'included_files' => 0,
        'excluded_files' => 0,
        'included_bytes' => 0,
        'sample_included' => [],
        'sample_excluded' => [],
        'truncated' => false,
    ];

    if (!$out['exists'] || !$out['readable']) {
        return $out;
    }

    $root = rtrim($root, '/');
    try {
        $flags = FilesystemIterator::SKIP_DOTS | FilesystemIterator::CURRENT_AS_FILEINFO | FilesystemIterator::KEY_AS_PATHNAME;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveCallbackFilterIterator(
                new RecursiveDirectoryIterator($root, $flags),
                static function (SplFileInfo $current, string $key): bool {
                    if ($current->isDir()) {
                        return !hpi_is_excluded($key . '/');
                    }
                    return true;
                }
            ),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $path => $info) {
            if (!$info instanceof SplFileInfo || !$info->isFile()) {
                continue;
            }
            $relative = ltrim(substr((string)$path, strlen($root)), '/');
            if ($relative === '') {
                continue;
            }
            if (hpi_is_excluded((string)$path)) {
                $out['excluded_files']++;
                if (count($out['sample_excluded']) < 12) {
                    $out['sample_excluded'][] = $relative;
                }
                continue;
            }
            $out['included_files']++;
            $out['included_bytes'] += max(0, (int)$info->getSize());
            if (count($out['sample_included']) < 12) {
                $out['sample_included'][] = $relative;
            }
            if ($out['included_files'] >= $maxFiles) {
                $out['truncated'] = true;
                break;
            }
        }
    } catch (Throwable $e) {
        $out['error'] = $e->getMessage();
    }

    return $out;
}

function hpi_format_bytes(int $bytes): string
{
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    }
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    }
    if ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    }
    return (string)$bytes . ' B';
}

function hpi_find_command(string $command): string
{
    $command = preg_replace('/[^a-zA-Z0-9_\-]/', '', $command) ?? '';
    if ($command === '') {
        return '';
    }
    $output = [];
    $code = 1;
    @exec('command -v ' . escapeshellarg($command) . ' 2>/dev/null', $output, $code);
    return $code === 0 && isset($output[0]) ? (string)$output[0] : '';
}

function hpi_table_row(string $label, string $status, string $type, string $detail, string $path = ''): string
{
    return '<tr><td><strong>' . hpi_h($label) . '</strong></td><td>' . hpi_badge(strtoupper($status), $type) . '</td><td>' . hpi_h($detail) . '</td><td>' . ($path !== '' ? '<code>' . hpi_h($path) . '</code>' : '') . '</td></tr>';
}

$user = function_exists('opsui_current_user') ? opsui_current_user() : [];
$isAdmin = function_exists('opsui_is_admin') ? opsui_is_admin($user) : false;

opsui_shell_begin([
    'title' => 'Handoff Package Inspector',
    'page_title' => 'Handoff Package Inspector',
    'active_section' => 'Deployment',
    'breadcrumbs' => 'Αρχική / Διαχειριστικό / Handoff Package Inspector',
    'safe_notice' => 'Admin-only read-only inspector. This page does not build a ZIP, does not dump the database, and does not read or display secrets.',
    'force_safe_notice' => true,
]);

if (!$isAdmin): ?>
<section class="card hero warn">
    <h1>Admin access required</h1>
    <p>This inspector shows server file layout and packaging readiness details, so it is restricted to admin users.</p>
    <div><?= hpi_badge('NO DATA WRITTEN', 'good') ?> <?= hpi_badge('NO ZIP BUILT', 'good') ?></div>
</section>
<?php opsui_shell_end(); exit; endif;

$root = hpi_root();
$paths = [
    'Public gov webroot' => [$root . '/public_html/gov.cabnet.app', true],
    'Private app folder' => [$root . '/gov.cabnet.app_app', true],
    'SQL folder' => [$root . '/gov.cabnet.app_sql', true],
    'Tools folder' => [$root . '/tools', true],
    'Server-only config folder' => [$root . '/gov.cabnet.app_config', true],
    'Safe handoff builder class' => [$root . '/gov.cabnet.app_app/src/Support/SafeHandoffPackageBuilder.php', false],
    'Handoff Center route' => [$root . '/public_html/gov.cabnet.app/ops/handoff-center.php', false],
];

$scans = [
    'public_html/gov.cabnet.app' => hpi_scan_dir($root . '/public_html/gov.cabnet.app'),
    'gov.cabnet.app_app' => hpi_scan_dir($root . '/gov.cabnet.app_app'),
    'gov.cabnet.app_sql' => hpi_scan_dir($root . '/gov.cabnet.app_sql'),
    'tools' => hpi_scan_dir($root . '/tools'),
];

$zipAvailable = class_exists(ZipArchive::class);
$mysqldump = hpi_find_command('mysqldump');
$mysql = hpi_find_command('mysql');
$tempWritable = is_writable(sys_get_temp_dir());
?>
<section class="card hero neutral">
    <h1>Safe Handoff Package Inspector</h1>
    <p>Use this page before building a Safe Handoff ZIP. It previews packaging readiness, expected inclusion areas, exclusion behavior, and environment requirements without generating the ZIP or exporting database data.</p>
    <div>
        <?= hpi_badge('READ ONLY', 'good') ?>
        <?= hpi_badge('NO ZIP BUILD', 'good') ?>
        <?= hpi_badge('NO DB EXPORT', 'good') ?>
        <?= hpi_badge('NO SECRET OUTPUT', 'good') ?>
    </div>
    <div class="actions">
        <a class="btn" href="/ops/handoff-center.php">Handoff Center</a>
        <a class="btn dark" href="/ops/deployment-center.php">Deployment Center</a>
        <a class="btn dark" href="/ops/system-status.php">System Status</a>
    </div>
</section>

<section class="card">
    <h2>Builder readiness</h2>
    <div class="table-wrap"><table>
        <thead><tr><th>Check</th><th>Status</th><th>Detail</th><th>Path</th></tr></thead>
        <tbody>
            <?= hpi_table_row('PHP ZipArchive extension', $zipAvailable ? 'ready' : 'missing', $zipAvailable ? 'good' : 'bad', $zipAvailable ? 'ZIP creation supported' : 'ZipArchive is not available') ?>
            <?= hpi_table_row('mysqldump command', $mysqldump !== '' ? 'ready' : 'missing', $mysqldump !== '' ? 'good' : 'warn', $mysqldump !== '' ? 'Database export command found' : 'Database export may fail unless builder has fallback', $mysqldump) ?>
            <?= hpi_table_row('mysql command', $mysql !== '' ? 'ready' : 'missing', $mysql !== '' ? 'good' : 'warn', $mysql !== '' ? 'MySQL CLI found' : 'MySQL CLI not found', $mysql) ?>
            <?= hpi_table_row('Temporary directory writable', $tempWritable ? 'ready' : 'blocked', $tempWritable ? 'good' : 'bad', sys_get_temp_dir(), sys_get_temp_dir()) ?>
            <?php foreach ($paths as $label => $item): ?>
                <?php [$path, $isDir] = $item; $status = hpi_status_for_path($path, $isDir); ?>
                <?= hpi_table_row($label, $status['status'], $status['type'], $status['detail'], $path) ?>
            <?php endforeach; ?>
        </tbody>
    </table></div>
</section>

<section class="card">
    <h2>Packaging preview</h2>
    <p class="small">Counts are approximate and use the same broad safety principles as the builder: include project code and SQL files, but exclude logs, sessions, mailboxes, caches, backups, archives, and secret-looking files.</p>
    <div class="table-wrap"><table>
        <thead><tr><th>Area</th><th>Included files</th><th>Excluded files</th><th>Approx size</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($scans as $area => $scan): ?>
            <?php
            $statusText = !$scan['exists'] ? 'missing' : (!$scan['readable'] ? 'not readable' : (!empty($scan['truncated']) ? 'truncated preview' : 'preview ok'));
            $statusType = !$scan['exists'] || !$scan['readable'] ? 'warn' : (!empty($scan['truncated']) ? 'warn' : 'good');
            ?>
            <tr>
                <td><strong><?= hpi_h($area) ?></strong></td>
                <td><?= hpi_h((string)$scan['included_files']) ?></td>
                <td><?= hpi_h((string)$scan['excluded_files']) ?></td>
                <td><?= hpi_h(hpi_format_bytes((int)$scan['included_bytes'])) ?></td>
                <td><?= hpi_badge(strtoupper($statusText), $statusType) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
</section>

<section class="two">
    <div class="card">
        <h2>Sample included files</h2>
        <?php foreach ($scans as $area => $scan): ?>
            <h3><?= hpi_h($area) ?></h3>
            <?php if (empty($scan['sample_included'])): ?>
                <p class="small">No included sample files found.</p>
            <?php else: ?>
                <ul class="list">
                    <?php foreach ($scan['sample_included'] as $file): ?><li><code><?= hpi_h($file) ?></code></li><?php endforeach; ?>
                </ul>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
    <div class="card">
        <h2>Sample excluded files</h2>
        <?php foreach ($scans as $area => $scan): ?>
            <h3><?= hpi_h($area) ?></h3>
            <?php if (empty($scan['sample_excluded'])): ?>
                <p class="small">No excluded sample files found in preview.</p>
            <?php else: ?>
                <ul class="list">
                    <?php foreach ($scan['sample_excluded'] as $file): ?><li><code><?= hpi_h($file) ?></code></li><?php endforeach; ?>
                </ul>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
</section>

<section class="card">
    <h2>Safety policy</h2>
    <ul class="list">
        <li>The real server-only config folder is never copied directly. The handoff package should include generated placeholder examples only.</li>
        <li>The database export is private operational material and should not be committed to GitHub unless intentionally sanitized first.</li>
        <li>Downloaded packages should be stored securely and deleted when no longer needed.</li>
        <li>The production pre-ride tool remains untouched by the handoff package builder and inspector.</li>
    </ul>
</section>
<?php opsui_shell_end(); ?>
