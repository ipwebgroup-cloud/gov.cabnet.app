<?php
/**
 * gov.cabnet.app — Ops Smoke Test Center
 *
 * Read-only post-deployment verification helper.
 * No Bolt calls, no EDXEIX calls, no AADE calls, no workflow writes.
 */

declare(strict_types=1);

require_once __DIR__ . '/_shell.php';

function stc_h(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function stc_badge(bool $ok, string $yes = 'OK', string $no = 'CHECK'): string
{
    return opsui_badge($ok ? $yes : $no, $ok ? 'good' : 'bad');
}

function stc_root(): string
{
    return dirname(__DIR__);
}

function stc_app_root(): string
{
    return dirname(__DIR__, 3) . '/gov.cabnet.app_app';
}

function stc_file_info(string $label, string $path, string $kind = 'file'): array
{
    $exists = ($kind === 'dir') ? is_dir($path) : is_file($path);
    $readable = $exists && is_readable($path);
    $size = ($exists && is_file($path)) ? (int)filesize($path) : 0;
    $mtime = $exists ? date('Y-m-d H:i:s', (int)filemtime($path)) : '';
    $hash = ($readable && is_file($path) && $size <= 2000000) ? substr(hash_file('sha256', $path) ?: '', 0, 16) : '';
    return [
        'label' => $label,
        'path' => $path,
        'exists' => $exists,
        'readable' => $readable,
        'size' => $size,
        'mtime' => $mtime,
        'hash' => $hash,
    ];
}

function stc_safe_relative(string $path): string
{
    $path = str_replace('\\', '/', $path);
    $replacements = [
        str_replace('\\', '/', dirname(__DIR__, 3)) => '~',
        '/home/cabnet' => '/home/cabnet',
    ];
    foreach ($replacements as $from => $to) {
        if ($from !== '' && str_starts_with($path, $from)) {
            return $to . substr($path, strlen($from));
        }
    }
    return $path;
}

function stc_load_db(): array
{
    $out = ['ok' => false, 'db' => null, 'error' => ''];
    $bootstrap = dirname(__DIR__, 3) . '/gov.cabnet.app_app/src/bootstrap.php';
    if (!is_file($bootstrap)) {
        $out['error'] = 'Private app bootstrap missing.';
        return $out;
    }
    try {
        $ctx = require $bootstrap;
        if (!is_array($ctx) || !isset($ctx['db']) || !method_exists($ctx['db'], 'connection')) {
            throw new RuntimeException('Invalid private app context.');
        }
        $db = $ctx['db']->connection();
        $db->ping();
        $out['ok'] = true;
        $out['db'] = $db;
    } catch (Throwable $e) {
        $out['error'] = $e->getMessage();
    }
    return $out;
}

function stc_table_exists(mysqli $db, string $table): bool
{
    try {
        $stmt = $db->prepare('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1');
        $stmt->bind_param('s', $table);
        $stmt->execute();
        return (bool)$stmt->get_result()->fetch_assoc();
    } catch (Throwable) {
        return false;
    }
}

function stc_table_count(mysqli $db, string $table): ?int
{
    if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) {
        return null;
    }
    try {
        $result = $db->query('SELECT COUNT(*) AS c FROM `' . $db->real_escape_string($table) . '`');
        $row = $result ? $result->fetch_assoc() : null;
        return is_array($row) ? (int)($row['c'] ?? 0) : null;
    } catch (Throwable) {
        return null;
    }
}

$root = stc_root();
$app = stc_app_root();
$files = [
    stc_file_info('Production Pre-Ride Tool', $root . '/ops/pre-ride-email-tool.php'),
    stc_file_info('Pre-Ride V2 Dev', $root . '/ops/pre-ride-email-toolv2.php'),
    stc_file_info('Shared Ops Shell', $root . '/ops/_shell.php'),
    stc_file_info('Login', $root . '/ops/login.php'),
    stc_file_info('Logout', $root . '/ops/logout.php'),
    stc_file_info('Auth Prepend', $root . '/_auth_prepend.php'),
    stc_file_info('Shell CSS', $root . '/assets/css/gov-ops-shell.css'),
    stc_file_info('EDXEIX CSS', $root . '/assets/css/gov-ops-edxeix.css'),
    stc_file_info('OpsAuth', $app . '/src/Auth/OpsAuth.php'),
    stc_file_info('Pre-Ride Parser', $app . '/src/BoltMail/BoltPreRideEmailParser.php'),
    stc_file_info('Mapping Lookup', $app . '/src/BoltMail/EdxeixMappingLookup.php'),
    stc_file_info('Maildir Loader', $app . '/src/BoltMail/MaildirPreRideEmailLoader.php'),
];

$dbState = stc_load_db();
$db = $dbState['db'] instanceof mysqli ? $dbState['db'] : null;
$tables = ['ops_users', 'ops_login_attempts', 'ops_audit_log', 'ops_user_preferences', 'mapping_drivers', 'mapping_vehicles', 'mapping_starting_points'];
$tableRows = [];
if ($db) {
    foreach ($tables as $table) {
        $exists = stc_table_exists($db, $table);
        $tableRows[] = ['table' => $table, 'exists' => $exists, 'count' => $exists ? stc_table_count($db, $table) : null];
    }
}

$criticalOk = true;
foreach ($files as $file) {
    if (!$file['exists']) {
        $criticalOk = false;
        break;
    }
}
if (!$dbState['ok']) {
    $criticalOk = false;
}

opsui_shell_begin([
    'title' => 'Smoke Test Center',
    'page_title' => 'Smoke Test Center',
    'breadcrumbs' => 'Αρχική / Maintenance / Smoke Test Center',
    'active_section' => 'Maintenance',
    'safe_notice' => 'Read-only post-deployment verification. This page does not call Bolt, EDXEIX, or AADE, and does not write data.',
]);
?>
<section class="card hero <?= $criticalOk ? 'good' : 'warn' ?>">
    <h1>Post-deployment smoke test</h1>
    <p>Use this page immediately after uploading a patch to confirm the protected operations area still has its core files, DB connection, and critical tables available.</p>
    <div>
        <?= opsui_badge('READ ONLY', 'good') ?>
        <?= opsui_badge('NO EXTERNAL CALLS', 'good') ?>
        <?= opsui_badge('NO WORKFLOW WRITES', 'good') ?>
        <?= opsui_badge($criticalOk ? 'BASELINE OK' : 'CHECK ITEMS', $criticalOk ? 'good' : 'warn') ?>
    </div>
</section>

<section class="grid" style="margin-bottom:18px">
    <?= opsui_metric($criticalOk ? 'OK' : 'CHECK', 'Overall smoke status') ?>
    <?= opsui_metric($dbState['ok'] ? 'OK' : 'CHECK', 'DB ping') ?>
    <?= opsui_metric((string)count(array_filter($files, static fn($f) => $f['exists'])), 'Files present') ?>
    <?= opsui_metric(date('Y-m-d H:i:s'), 'Server time') ?>
</section>

<?php if (!$dbState['ok']): ?>
    <section class="gov-alert gov-alert-bad">DB check failed: <?= stc_h($dbState['error']) ?></section>
<?php endif; ?>

<section class="card">
    <h2>Critical file snapshot</h2>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Item</th><th>Status</th><th>Path</th><th>Size</th><th>Modified</th><th>SHA-256</th></tr></thead>
            <tbody>
            <?php foreach ($files as $file): ?>
                <tr>
                    <td><strong><?= stc_h($file['label']) ?></strong></td>
                    <td><?= stc_badge((bool)$file['exists']) ?></td>
                    <td><code><?= stc_h(stc_safe_relative($file['path'])) ?></code></td>
                    <td><?= $file['size'] > 0 ? stc_h(number_format((int)$file['size']) . ' B') : '-' ?></td>
                    <td><?= stc_h($file['mtime'] ?: '-') ?></td>
                    <td><code><?= stc_h($file['hash'] ?: '-') ?></code></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="card">
    <h2>Database/table snapshot</h2>
    <?php if (!$db): ?>
        <p class="badline">Database connection was not available, so table checks could not run.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Table</th><th>Status</th><th>Rows</th></tr></thead>
                <tbody>
                <?php foreach ($tableRows as $row): ?>
                    <tr>
                        <td><code><?= stc_h($row['table']) ?></code></td>
                        <td><?= stc_badge((bool)$row['exists']) ?></td>
                        <td><?= $row['count'] === null ? '-' : stc_h((string)$row['count']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<section class="two">
    <div class="card">
        <h2>Copy/paste syntax checks</h2>
        <textarea class="code-box" readonly rows="10">php -l /home/cabnet/public_html/gov.cabnet.app/ops/_shell.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-tool.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/login.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/smoke-test-center.php
php -l /home/cabnet/gov.cabnet.app_app/src/Auth/OpsAuth.php
php -l /home/cabnet/gov.cabnet.app_app/src/BoltMail/BoltPreRideEmailParser.php
php -l /home/cabnet/gov.cabnet.app_app/src/BoltMail/EdxeixMappingLookup.php</textarea>
    </div>
    <div class="card">
        <h2>Copy/paste auth checks</h2>
        <textarea class="code-box" readonly rows="10">curl -sS -D - -o /dev/null https://gov.cabnet.app/ops/pre-ride-email-tool.php | egrep -i 'HTTP/|Location:|Set-Cookie:'
curl -sS -D - -o /dev/null https://gov.cabnet.app/ops/login.php | egrep -i 'HTTP/|Location:|Set-Cookie:'
curl -sS -D - -o /dev/null https://gov.cabnet.app/ops/smoke-test-center.php | egrep -i 'HTTP/|Location:|Set-Cookie:'</textarea>
    </div>
</section>

<section class="card">
    <h2>Safe route checks</h2>
    <div class="gov-admin-grid">
        <a class="gov-admin-link" href="/ops/pre-ride-email-tool.php"><strong>Production Pre-Ride Tool</strong><span>Live staff page. Confirm it still opens after login.</span></a>
        <a class="gov-admin-link" href="/ops/system-status.php"><strong>System Status</strong><span>Review DB and table visibility with no secret output.</span></a>
        <a class="gov-admin-link" href="/ops/tool-inventory.php"><strong>Tool Inventory</strong><span>Review route and file fingerprints.</span></a>
        <a class="gov-admin-link" href="/ops/maintenance-center.php"><strong>Maintenance Center</strong><span>Review deployment/rollback command blocks.</span></a>
        <a class="gov-admin-link" href="/ops/deployment-center.php"><strong>Deployment Center</strong><span>Manual cPanel/GitHub Desktop deployment checklist.</span></a>
        <a class="gov-admin-link" href="/ops/handoff-center.php"><strong>Handoff Center</strong><span>Copy continuity prompt for a new session.</span></a>
    </div>
</section>
<?php opsui_shell_end(); ?>
