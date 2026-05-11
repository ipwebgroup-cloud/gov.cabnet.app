<?php
/**
 * gov.cabnet.app — Ops system status.
 * Read-only health snapshot for auth, DB, core tables, and safe configuration booleans.
 * No Bolt calls, no EDXEIX calls, no AADE calls, no writes.
 */

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Robots-Tag: noindex, nofollow', true);

require_once __DIR__ . '/_shell.php';

function oss_badge_for(bool $ok, string $yes = 'OK', string $no = 'CHECK'): string
{
    return opsui_badge($ok ? $yes : $no, $ok ? 'good' : 'warn');
}

function oss_bootstrap_path(): string
{
    return dirname(__DIR__, 3) . '/gov.cabnet.app_app/src/bootstrap.php';
}

function oss_safe_identifier(string $name): string
{
    return preg_match('/^[A-Za-z0-9_]+$/', $name) === 1 ? $name : '';
}

function oss_count_rows(mysqli $db, string $table): ?int
{
    $safe = oss_safe_identifier($table);
    if ($safe === '' || !opsui_table_exists($db, $safe)) {
        return null;
    }
    try {
        $result = $db->query('SELECT COUNT(*) AS c FROM `' . $safe . '`');
        $row = $result ? $result->fetch_assoc() : null;
        return is_array($row) ? (int)($row['c'] ?? 0) : null;
    } catch (Throwable) {
        return null;
    }
}

function oss_fetch_one_value(mysqli $db, string $sql, string $types = '', array $params = []): mixed
{
    try {
        $stmt = $db->prepare($sql);
        if ($params !== []) {
            $bind = [$types];
            foreach ($params as $i => $param) {
                $bind[] = &$params[$i];
            }
            call_user_func_array([$stmt, 'bind_param'], $bind);
        }
        $stmt->execute();
        $row = $stmt->get_result()->fetch_row();
        return is_array($row) ? ($row[0] ?? null) : null;
    } catch (Throwable) {
        return null;
    }
}

function oss_file_status(string $path): array
{
    $exists = is_file($path);
    return [
        'exists' => $exists,
        'readable' => $exists && is_readable($path),
        'mtime' => ($exists && is_readable($path)) ? (int)filemtime($path) : 0,
        'size' => ($exists && is_readable($path)) ? (int)filesize($path) : 0,
    ];
}

function oss_size_label(int $bytes): string
{
    if ($bytes >= 1048576) { return number_format($bytes / 1048576, 2) . ' MB'; }
    if ($bytes >= 1024) { return number_format($bytes / 1024, 1) . ' KB'; }
    return $bytes . ' B';
}

function oss_mtime_label(int $mtime): string
{
    return $mtime > 0 ? date('Y-m-d H:i:s', $mtime) : '-';
}

$bootstrap = oss_bootstrap_path();
$bootstrapStatus = oss_file_status($bootstrap);
$ctx = null;
$config = null;
$db = null;
$bootstrapError = '';
$dbOk = false;
$dbError = '';

try {
    if (!$bootstrapStatus['readable']) {
        throw new RuntimeException('Private app bootstrap is missing or not readable.');
    }
    $ctx = require $bootstrap;
    if (!is_array($ctx) || !isset($ctx['config'], $ctx['db']) || !method_exists($ctx['db'], 'connection')) {
        throw new RuntimeException('Private app bootstrap returned an invalid context.');
    }
    $config = $ctx['config'];
    $db = $ctx['db']->connection();
    $dbOk = $db instanceof mysqli && $db->ping();
} catch (Throwable $e) {
    $bootstrapError = $e->getMessage();
    $dbError = $e->getMessage();
}

$tables = [
    'ops_users' => 'Operator login users',
    'ops_login_attempts' => 'Login attempt history',
    'ops_audit_log' => 'Operator audit log',
    'ops_user_preferences' => 'User UI preferences',
    'mapping_drivers' => 'Bolt → EDXEIX driver mappings',
    'mapping_vehicles' => 'Bolt → EDXEIX vehicle mappings',
    'mapping_starting_points' => 'EDXEIX starting point mappings',
    'edxeix_export_lessors' => 'EDXEIX exported lessors snapshot',
    'edxeix_export_drivers' => 'EDXEIX exported drivers snapshot',
    'edxeix_export_vehicles' => 'EDXEIX exported vehicles snapshot',
    'edxeix_export_starting_points' => 'EDXEIX exported starting points snapshot',
];

$tableRows = [];
$presentTables = 0;
if ($dbOk && $db instanceof mysqli) {
    foreach ($tables as $table => $label) {
        $exists = opsui_table_exists($db, $table);
        if ($exists) { $presentTables++; }
        $tableRows[] = [
            'table' => $table,
            'label' => $label,
            'exists' => $exists,
            'count' => $exists ? oss_count_rows($db, $table) : null,
        ];
    }
} else {
    foreach ($tables as $table => $label) {
        $tableRows[] = ['table' => $table, 'label' => $label, 'exists' => false, 'count' => null];
    }
}

$authStats = [
    'active_users' => null,
    'active_admins' => null,
    'login_attempts_24h' => null,
    'failed_attempts_24h' => null,
    'latest_login_at' => null,
];
if ($dbOk && $db instanceof mysqli && opsui_table_exists($db, 'ops_users')) {
    $authStats['active_users'] = oss_fetch_one_value($db, 'SELECT COUNT(*) FROM ops_users WHERE is_active = 1');
    $authStats['active_admins'] = oss_fetch_one_value($db, "SELECT COUNT(*) FROM ops_users WHERE is_active = 1 AND role = 'admin'");
    $authStats['latest_login_at'] = oss_fetch_one_value($db, 'SELECT MAX(last_login_at) FROM ops_users');
}
if ($dbOk && $db instanceof mysqli && opsui_table_exists($db, 'ops_login_attempts')) {
    $authStats['login_attempts_24h'] = oss_fetch_one_value($db, 'SELECT COUNT(*) FROM ops_login_attempts WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)');
    $authStats['failed_attempts_24h'] = oss_fetch_one_value($db, 'SELECT COUNT(*) FROM ops_login_attempts WHERE success = 0 AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)');
}

$configChecks = [];
if ($config && method_exists($config, 'get')) {
    $baseUrl = (string)$config->get('app.base_url', '');
    $internalKey = (string)$config->get('app.internal_api_key', '');
    $dryRun = (bool)$config->get('app.dry_run', true);
    $edxeixCreate = (string)$config->get('edxeix.create_url', '');
    $edxeixSubmit = (string)$config->get('edxeix.submit_url', '');
    $maildir = (string)$config->get('mail.pre_ride_maildir', $config->get('mail.bolt_bridge_maildir', ''));
    $configChecks = [
        ['label' => 'Base URL configured', 'ok' => $baseUrl !== '', 'note' => $baseUrl !== '' ? 'Configured' : 'Missing'],
        ['label' => 'Internal API key configured', 'ok' => $internalKey !== '' && !str_contains($internalKey, 'REPLACE_WITH'), 'note' => $internalKey !== '' ? 'Present, not displayed' : 'Missing'],
        ['label' => 'Dry-run default', 'ok' => $dryRun === true, 'note' => $dryRun ? 'dry_run=true' : 'dry_run=false'],
        ['label' => 'EDXEIX create URL configured', 'ok' => $edxeixCreate !== '', 'note' => $edxeixCreate !== '' ? 'Present, not called' : 'Missing'],
        ['label' => 'EDXEIX submit URL configured', 'ok' => $edxeixSubmit !== '', 'note' => $edxeixSubmit !== '' ? 'Present, not called' : 'Missing'],
        ['label' => 'Pre-ride maildir configured', 'ok' => $maildir !== '', 'note' => $maildir !== '' ? (is_dir($maildir) ? 'Configured and directory exists' : 'Configured, directory not confirmed') : 'Missing'],
    ];
} else {
    $configChecks = [
        ['label' => 'Private config loaded', 'ok' => false, 'note' => 'Config unavailable'],
    ];
}

$webroot = dirname(__DIR__);
$appRoot = dirname(__DIR__, 3) . '/gov.cabnet.app_app';
$coreFiles = [
    ['label' => 'Production Pre-Ride Tool', 'path' => $webroot . '/ops/pre-ride-email-tool.php', 'must' => true],
    ['label' => 'Shared Ops Shell', 'path' => $webroot . '/ops/_shell.php', 'must' => true],
    ['label' => 'Auth prepend', 'path' => $webroot . '/_auth_prepend.php', 'must' => true],
    ['label' => 'OpsAuth class', 'path' => $appRoot . '/src/Auth/OpsAuth.php', 'must' => true],
    ['label' => 'Pre-Ride Parser', 'path' => $appRoot . '/src/BoltMail/BoltPreRideEmailParser.php', 'must' => true],
    ['label' => 'EDXEIX Mapping Lookup', 'path' => $appRoot . '/src/BoltMail/EdxeixMappingLookup.php', 'must' => true],
    ['label' => 'Maildir Loader', 'path' => $appRoot . '/src/BoltMail/MaildirPreRideEmailLoader.php', 'must' => false],
];
$fileRows = [];
$missingMustFiles = 0;
foreach ($coreFiles as $file) {
    $status = oss_file_status($file['path']);
    if ($file['must'] && !$status['exists']) { $missingMustFiles++; }
    $fileRows[] = $file + ['status' => $status];
}

$overallOk = $bootstrapStatus['readable'] && $dbOk && $missingMustFiles === 0 && $presentTables >= 3;

opsui_shell_begin([
    'title' => 'System Status',
    'page_title' => 'Ops system status',
    'subtitle' => 'Read-only health snapshot for login, database, tables, and core files',
    'breadcrumbs' => 'Αρχική / Help / System status',
    'active_section' => 'Help',
    'force_safe_notice' => true,
    'safe_notice' => 'Read-only system health snapshot. This page does not call Bolt, EDXEIX, or AADE, does not read/display secrets, and does not write data.',
]);
?>
<section class="card hero <?= $overallOk ? 'good' : 'warn' ?>">
    <h1>System status snapshot</h1>
    <p>This page checks the local application shell, DB connectivity, auth tables, selected mapping tables, and core files. It is safe to use after patch uploads.</p>
    <div>
        <?= opsui_badge('READ ONLY', 'good') ?>
        <?= opsui_badge('NO SECRET OUTPUT', 'good') ?>
        <?= opsui_badge('NO EXTERNAL CALLS', 'good') ?>
    </div>
    <div class="grid" style="margin-top:14px">
        <?= opsui_metric($overallOk ? 'OK' : 'CHECK', 'Overall snapshot') ?>
        <?= opsui_metric($dbOk ? 'OK' : 'FAIL', 'DB connection') ?>
        <?= opsui_metric((string)$presentTables . '/' . (string)count($tables), 'Tracked tables present') ?>
        <?= opsui_metric((string)$missingMustFiles, 'Missing required files') ?>
    </div>
    <?php if ($bootstrapError !== ''): ?><p class="badline"><strong>Bootstrap:</strong> <?= opsui_h($bootstrapError) ?></p><?php endif; ?>
</section>

<section class="two">
    <div class="card">
        <h2>Runtime</h2>
        <div class="kv">
            <div class="k">Server time</div><div><?= opsui_h(date('Y-m-d H:i:s T')) ?></div>
            <div class="k">PHP version</div><div><?= opsui_h(PHP_VERSION) ?></div>
            <div class="k">Timezone</div><div><?= opsui_h(date_default_timezone_get()) ?></div>
            <div class="k">Logged-in user</div><div><?= opsui_h(opsui_user_display(opsui_current_user())) ?> <?= opsui_badge(opsui_user_role(opsui_current_user()), 'neutral') ?></div>
            <div class="k">Bootstrap readable</div><div><?= oss_badge_for($bootstrapStatus['readable']) ?></div>
            <div class="k">DB ping</div><div><?= oss_badge_for($dbOk, 'OK', 'FAILED') ?></div>
        </div>
    </div>

    <div class="card">
        <h2>Auth summary</h2>
        <div class="kv">
            <div class="k">Active users</div><div><?= opsui_h($authStats['active_users'] === null ? '-' : (string)$authStats['active_users']) ?></div>
            <div class="k">Active admins</div><div><?= opsui_h($authStats['active_admins'] === null ? '-' : (string)$authStats['active_admins']) ?></div>
            <div class="k">Login attempts / 24h</div><div><?= opsui_h($authStats['login_attempts_24h'] === null ? '-' : (string)$authStats['login_attempts_24h']) ?></div>
            <div class="k">Failed attempts / 24h</div><div><?= opsui_h($authStats['failed_attempts_24h'] === null ? '-' : (string)$authStats['failed_attempts_24h']) ?></div>
            <div class="k">Latest login</div><div><?= opsui_h((string)($authStats['latest_login_at'] ?: '-')) ?></div>
        </div>
    </div>
</section>

<section class="card">
    <h2>Safe configuration booleans</h2>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Check</th><th>Status</th><th>Note</th></tr></thead>
            <tbody>
                <?php foreach ($configChecks as $check): ?>
                <tr>
                    <td><?= opsui_h($check['label']) ?></td>
                    <td><?= oss_badge_for((bool)$check['ok']) ?></td>
                    <td><?= opsui_h($check['note']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="card">
    <h2>Database tables</h2>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Table</th><th>Status</th><th>Rows</th><th>Purpose</th></tr></thead>
            <tbody>
                <?php foreach ($tableRows as $row): ?>
                <tr>
                    <td><code><?= opsui_h($row['table']) ?></code></td>
                    <td><?= $row['exists'] ? opsui_badge('PRESENT', 'good') : opsui_badge('MISSING', 'warn') ?></td>
                    <td><?= opsui_h($row['count'] === null ? '-' : (string)$row['count']) ?></td>
                    <td><?= opsui_h($row['label']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="card">
    <h2>Core files</h2>
    <div class="table-wrap">
        <table>
            <thead><tr><th>File</th><th>Status</th><th>Modified</th><th>Size</th><th>Required</th></tr></thead>
            <tbody>
                <?php foreach ($fileRows as $row): $s = $row['status']; ?>
                <tr>
                    <td><strong><?= opsui_h($row['label']) ?></strong><br><code><?= opsui_h($row['path']) ?></code></td>
                    <td><?= $s['exists'] ? opsui_badge('OK', 'good') : opsui_badge('MISSING', $row['must'] ? 'bad' : 'warn') ?></td>
                    <td><?= opsui_h(oss_mtime_label((int)$s['mtime'])) ?></td>
                    <td><?= opsui_h(oss_size_label((int)$s['size'])) ?></td>
                    <td><?= $row['must'] ? opsui_badge('YES', 'neutral') : opsui_badge('OPTIONAL', 'warn') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="two">
    <div class="card">
        <h2>Safe use</h2>
        <ul class="list">
            <li>Use this page after uploading patches to confirm the app shell and DB tables are visible.</li>
            <li>Use <code>php -l</code> from SSH for syntax checks; this page does not execute syntax linting.</li>
            <li>The production pre-ride tool is checked as a file only and is not modified.</li>
        </ul>
    </div>
    <div class="card">
        <h2>What this page does not do</h2>
        <ul class="list">
            <li>It does not print DB passwords, API keys, cookies, tokens, or session secrets.</li>
            <li>It does not call Bolt, EDXEIX, AADE, or external websites.</li>
            <li>It does not create, update, or delete database rows.</li>
        </ul>
    </div>
</section>
<?php opsui_shell_end(); ?>
