<?php
/**
 * gov.cabnet.app — Ops Dashboard
 *
 * Read-only shared-shell dashboard for the operator/admin console.
 * No Bolt calls, no EDXEIX calls, no AADE calls, no workflow writes.
 */

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Robots-Tag: noindex, nofollow', true);

$shell = __DIR__ . '/_shell.php';
if (!is_file($shell)) {
    http_response_code(500);
    echo 'Shared ops shell not found.';
    exit;
}
require_once $shell;

function opd_file_status(string $label, string $relativePath, string $absolutePath): array
{
    $exists = is_file($absolutePath);
    return [
        'label' => $label,
        'relative' => $relativePath,
        'exists' => $exists,
        'mtime' => $exists ? date('Y-m-d H:i:s', (int)filemtime($absolutePath)) : '',
        'size' => $exists ? (int)filesize($absolutePath) : 0,
        'hash' => $exists ? substr(hash_file('sha256', $absolutePath) ?: '', 0, 16) : '',
    ];
}

function opd_table_exists(mysqli $db, string $table): bool
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

function opd_count(mysqli $db, string $table, string $where = '1=1'): ?int
{
    try {
        if (!opd_table_exists($db, $table)) {
            return null;
        }
        // $where is only used with static strings defined in this file.
        $res = $db->query('SELECT COUNT(*) AS c FROM `' . $db->real_escape_string($table) . '` WHERE ' . $where);
        $row = $res ? $res->fetch_assoc() : null;
        return is_array($row) ? (int)($row['c'] ?? 0) : null;
    } catch (Throwable) {
        return null;
    }
}

function opd_count_label(?int $value): string
{
    return $value === null ? 'n/a' : (string)$value;
}

function opd_safe_date(): string
{
    return date('Y-m-d H:i:s T');
}

$root = dirname(__DIR__);
$appRoot = dirname(__DIR__, 3) . '/gov.cabnet.app_app';
$bootstrap = $appRoot . '/src/bootstrap.php';

$files = [
    opd_file_status('Production pre-ride tool', '/ops/pre-ride-email-tool.php', __DIR__ . '/pre-ride-email-tool.php'),
    opd_file_status('Pre-ride V2 dev wrapper', '/ops/pre-ride-email-toolv2.php', __DIR__ . '/pre-ride-email-toolv2.php'),
    opd_file_status('Mobile pre-ride review', '/ops/pre-ride-mobile-review.php', __DIR__ . '/pre-ride-mobile-review.php'),
    opd_file_status('Firefox helper center', '/ops/firefox-extension.php', __DIR__ . '/firefox-extension.php'),
    opd_file_status('System status', '/ops/system-status.php', __DIR__ . '/system-status.php'),
    opd_file_status('Smoke test center', '/ops/smoke-test-center.php', __DIR__ . '/smoke-test-center.php'),
    opd_file_status('Shared ops shell', '/ops/_shell.php', __DIR__ . '/_shell.php'),
    opd_file_status('Ops auth class', 'gov.cabnet.app_app/src/Auth/OpsAuth.php', $appRoot . '/src/Auth/OpsAuth.php'),
    opd_file_status('Pre-ride parser', 'gov.cabnet.app_app/src/BoltMail/BoltPreRideEmailParser.php', $appRoot . '/src/BoltMail/BoltPreRideEmailParser.php'),
    opd_file_status('EDXEIX mapping lookup', 'gov.cabnet.app_app/src/BoltMail/EdxeixMappingLookup.php', $appRoot . '/src/BoltMail/EdxeixMappingLookup.php'),
];

$dbOk = false;
$dbError = '';
$counts = [
    'ops_users_total' => null,
    'ops_users_active' => null,
    'ops_admins_active' => null,
    'login_attempts_24h' => null,
    'login_failures_24h' => null,
    'audit_events_24h' => null,
    'preferences_rows' => null,
];

try {
    if (!is_file($bootstrap)) {
        throw new RuntimeException('Private bootstrap file not found.');
    }
    $ctx = require $bootstrap;
    if (!is_array($ctx) || !isset($ctx['db']) || !method_exists($ctx['db'], 'connection')) {
        throw new RuntimeException('Private bootstrap did not return a valid DB context.');
    }
    /** @var mysqli $db */
    $db = $ctx['db']->connection();
    $dbOk = (bool)$db->ping();
    $counts['ops_users_total'] = opd_count($db, 'ops_users');
    $counts['ops_users_active'] = opd_count($db, 'ops_users', 'is_active = 1');
    $counts['ops_admins_active'] = opd_count($db, 'ops_users', "is_active = 1 AND role = 'admin'");
    $counts['login_attempts_24h'] = opd_count($db, 'ops_login_attempts', 'created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)');
    $counts['login_failures_24h'] = opd_count($db, 'ops_login_attempts', 'success = 0 AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)');
    $counts['audit_events_24h'] = opd_count($db, 'ops_audit_log', 'created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)');
    $counts['preferences_rows'] = opd_count($db, 'ops_user_preferences');
} catch (Throwable $e) {
    $dbError = $e->getMessage();
}

$user = opsui_current_user();
$userName = opsui_user_display($user);
$userRole = opsui_user_role($user);

opsui_shell_begin([
    'title' => 'Ops Dashboard',
    'page_title' => 'Ops Dashboard',
    'active_section' => 'Dashboard',
    'breadcrumbs' => 'Αρχική / Διαχειριστικό / Ops Dashboard',
    'subtitle' => 'Read-only operational overview',
    'safe_notice' => 'This dashboard is read-only. It checks local files and safe DB counts only. It does not call Bolt, EDXEIX, or AADE.',
]);
?>
<section class="card hero neutral">
    <h1>Operations overview</h1>
    <p>A quick safe overview of the protected ops environment, user area, and production pre-ride tooling.</p>
    <div>
        <?= opsui_badge('READ ONLY', 'good') ?>
        <?= opsui_badge('NO BOLT CALL', 'good') ?>
        <?= opsui_badge('NO EDXEIX CALL', 'good') ?>
        <?= opsui_badge('NO AADE CALL', 'good') ?>
        <?= opsui_badge('PRODUCTION TOOL UNCHANGED', 'good') ?>
    </div>
    <div class="grid" style="margin-top:14px">
        <?= opsui_metric($dbOk ? 'OK' : 'CHECK', 'DB ping') ?>
        <?= opsui_metric(opd_count_label($counts['ops_users_active']), 'Active users') ?>
        <?= opsui_metric(opd_count_label($counts['ops_admins_active']), 'Active admins') ?>
        <?= opsui_metric(opd_safe_date(), 'Server time') ?>
    </div>
</section>

<section class="gov-admin-grid">
    <a class="gov-admin-link" href="/ops/pre-ride-email-tool.php"><strong>Production Pre-Ride Tool</strong><span>Live route currently used by staff. Do not disrupt unless explicitly approved.</span></a>
    <a class="gov-admin-link" href="/ops/pre-ride-email-toolv2.php"><strong>Pre-Ride V2 Dev</strong><span>Safe development route for future changes and UI experiments.</span></a>
    <a class="gov-admin-link" href="/ops/pre-ride-mobile-review.php"><strong>Mobile Review</strong><span>Mobile/tablet checking only. Desktop Firefox remains required for EDXEIX fill/save.</span></a>
    <a class="gov-admin-link" href="/ops/firefox-extension.php"><strong>Firefox Helper Center</strong><span>Download and verify the current helper package.</span></a>
    <a class="gov-admin-link" href="/ops/quick-launch.php"><strong>Quick Launch</strong><span>Searchable route launcher for the operations console.</span></a>
    <a class="gov-admin-link" href="/ops/smoke-test-center.php"><strong>Smoke Test Center</strong><span>Post-upload file, DB, and auth verification guidance.</span></a>
</section>

<section class="two">
    <div class="card">
        <h2>Current operator</h2>
        <div class="kv">
            <div class="k">Display name</div><div><?= opsui_h($userName) ?></div>
            <div class="k">Username</div><div><?= opsui_h((string)($user['username'] ?? '')) ?></div>
            <div class="k">Email</div><div><?= opsui_h((string)($user['email'] ?? '')) ?></div>
            <div class="k">Role</div><div><?= opsui_badge($userRole, $userRole === 'admin' ? 'good' : 'neutral') ?></div>
            <div class="k">Logged in at</div><div><?= opsui_h((string)($user['logged_in_at'] ?? '')) ?></div>
        </div>
        <div class="actions">
            <a class="btn" href="/ops/profile.php">Open Profile</a>
            <a class="btn dark" href="/ops/profile-activity.php">My Activity</a>
            <a class="btn warn" href="/ops/profile-preferences.php">Preferences</a>
        </div>
    </div>

    <div class="card">
        <h2>Auth and activity snapshot</h2>
        <?php if (!$dbOk): ?>
            <p class="badline"><strong>DB check failed:</strong> <?= opsui_h($dbError ?: 'Unknown DB error') ?></p>
        <?php endif; ?>
        <div class="kv">
            <div class="k">Total users</div><div><?= opsui_h(opd_count_label($counts['ops_users_total'])) ?></div>
            <div class="k">Active users</div><div><?= opsui_h(opd_count_label($counts['ops_users_active'])) ?></div>
            <div class="k">Active admins</div><div><?= opsui_h(opd_count_label($counts['ops_admins_active'])) ?></div>
            <div class="k">Login attempts 24h</div><div><?= opsui_h(opd_count_label($counts['login_attempts_24h'])) ?></div>
            <div class="k">Failed logins 24h</div><div><?= opsui_h(opd_count_label($counts['login_failures_24h'])) ?></div>
            <div class="k">Audit events 24h</div><div><?= opsui_h(opd_count_label($counts['audit_events_24h'])) ?></div>
            <div class="k">Preference rows</div><div><?= opsui_h(opd_count_label($counts['preferences_rows'])) ?></div>
        </div>
        <div class="actions">
            <a class="btn" href="/ops/activity-center.php">Activity Center</a>
            <a class="btn dark" href="/ops/login-attempts.php">Login Attempts</a>
            <a class="btn dark" href="/ops/audit-log.php">Audit Log</a>
        </div>
    </div>
</section>

<section class="card">
    <h2>Critical file snapshot</h2>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>File</th>
                    <th>Path</th>
                    <th>Status</th>
                    <th>Modified</th>
                    <th>Size</th>
                    <th>SHA-256</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($files as $file): ?>
                <tr>
                    <td><strong><?= opsui_h($file['label']) ?></strong></td>
                    <td><code><?= opsui_h($file['relative']) ?></code></td>
                    <td><?= opsui_bool_badge((bool)$file['exists'], 'FOUND', 'MISSING') ?></td>
                    <td><?= opsui_h($file['mtime']) ?></td>
                    <td><?= opsui_h($file['exists'] ? number_format((int)$file['size']) . ' bytes' : '') ?></td>
                    <td><code><?= opsui_h($file['hash']) ?></code></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="card">
    <h2>Next safe actions</h2>
    <ol class="timeline">
        <li>Keep <code>/ops/pre-ride-email-tool.php</code> stable for production staff.</li>
        <li>Use <code>/ops/pre-ride-email-toolv2.php</code> for future pre-ride UI or parser workflow experiments.</li>
        <li>Keep both Firefox helper extensions loaded until we explicitly test a merged helper.</li>
        <li>Use Smoke Test Center after every patch upload.</li>
        <li>Commit after production confirmation through GitHub Desktop.</li>
    </ol>
</section>
<?php
opsui_shell_end();
