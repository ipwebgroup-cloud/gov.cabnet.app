<?php
/**
 * gov.cabnet.app — Ops tool inventory.
 * Read-only route and file status page. No Bolt/EDXEIX/AADE calls and no writes.
 */

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Robots-Tag: noindex, nofollow', true);

require_once __DIR__ . '/_shell.php';

function oti_webroot(): string
{
    return dirname(__DIR__);
}

function oti_private_app_root(): string
{
    return dirname(__DIR__, 3) . '/gov.cabnet.app_app';
}

function oti_status_for_path(string $absolutePath): array
{
    $exists = is_file($absolutePath);
    $readable = $exists && is_readable($absolutePath);
    $size = $readable ? (int)filesize($absolutePath) : 0;
    $mtime = $readable ? (int)filemtime($absolutePath) : 0;
    $sha = $readable ? hash_file('sha256', $absolutePath) : '';

    return [
        'exists' => $exists,
        'readable' => $readable,
        'size' => $size,
        'mtime' => $mtime,
        'sha256' => is_string($sha) ? $sha : '',
    ];
}

function oti_size_label(int $bytes): string
{
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    }
    if ($bytes >= 1024) {
        return number_format($bytes / 1024, 1) . ' KB';
    }
    return $bytes . ' B';
}

function oti_mtime_label(int $mtime): string
{
    return $mtime > 0 ? date('Y-m-d H:i:s', $mtime) : '-';
}

function oti_status_badge(array $status): string
{
    if (!$status['exists']) {
        return opsui_badge('MISSING', 'bad');
    }
    if (!$status['readable']) {
        return opsui_badge('NOT READABLE', 'warn');
    }
    return opsui_badge('OK', 'good');
}

$webroot = oti_webroot();
$appRoot = oti_private_app_root();

$routes = [
    ['group' => 'Production workflow', 'label' => 'Production Pre-Ride Tool', 'url' => '/ops/pre-ride-email-tool.php', 'file' => $webroot . '/ops/pre-ride-email-tool.php', 'note' => 'Production-critical. Do not modify during live use.'],
    ['group' => 'Production workflow', 'label' => 'Pre-Ride Tool V2 Dev', 'url' => '/ops/pre-ride-email-toolv2.php', 'file' => $webroot . '/ops/pre-ride-email-toolv2.php', 'note' => 'Safe development wrapper for future UI changes.'],
    ['group' => 'Production workflow', 'label' => 'Mobile Pre-Ride Review', 'url' => '/ops/pre-ride-mobile-review.php', 'file' => $webroot . '/ops/pre-ride-mobile-review.php', 'note' => 'Mobile/tablet review only. No EDXEIX save.'],
    ['group' => 'Operations shell', 'label' => 'Ops Home', 'url' => '/ops/home.php', 'file' => $webroot . '/ops/home.php', 'note' => 'Main shared-shell landing page.'],
    ['group' => 'Operations shell', 'label' => 'My Start', 'url' => '/ops/my-start.php', 'file' => $webroot . '/ops/my-start.php', 'note' => 'Redirects to user preferred landing page.'],
    ['group' => 'Operations shell', 'label' => 'Shared Shell', 'url' => '', 'file' => $webroot . '/ops/_shell.php', 'note' => 'Include-only shared GUI shell.'],
    ['group' => 'User area', 'label' => 'Profile', 'url' => '/ops/profile.php', 'file' => $webroot . '/ops/profile.php', 'note' => 'Read-only profile overview.'],
    ['group' => 'User area', 'label' => 'Edit Profile', 'url' => '/ops/profile-edit.php', 'file' => $webroot . '/ops/profile-edit.php', 'note' => 'Self-service display name/email update.'],
    ['group' => 'User area', 'label' => 'Preferences', 'url' => '/ops/profile-preferences.php', 'file' => $webroot . '/ops/profile-preferences.php', 'note' => 'Self-service UI preferences.'],
    ['group' => 'User area', 'label' => 'Change Password', 'url' => '/ops/profile-password.php', 'file' => $webroot . '/ops/profile-password.php', 'note' => 'Self-service password change.'],
    ['group' => 'User area', 'label' => 'My Activity', 'url' => '/ops/profile-activity.php', 'file' => $webroot . '/ops/profile-activity.php', 'note' => 'Read-only personal activity history.'],
    ['group' => 'Administration', 'label' => 'Users Control', 'url' => '/ops/users-control.php', 'file' => $webroot . '/ops/users-control.php', 'note' => 'Admin-only local user overview.'],
    ['group' => 'Administration', 'label' => 'Create User', 'url' => '/ops/users-new.php', 'file' => $webroot . '/ops/users-new.php', 'note' => 'Admin-only user creation.'],
    ['group' => 'Administration', 'label' => 'Audit Log', 'url' => '/ops/audit-log.php', 'file' => $webroot . '/ops/audit-log.php', 'note' => 'Admin-only read-only audit log.'],
    ['group' => 'Administration', 'label' => 'Login Attempts', 'url' => '/ops/login-attempts.php', 'file' => $webroot . '/ops/login-attempts.php', 'note' => 'Admin-only login attempt visibility.'],
    ['group' => 'Helper workflow', 'label' => 'Firefox Helper Center', 'url' => '/ops/firefox-extension.php', 'file' => $webroot . '/ops/firefox-extension.php', 'note' => 'Authenticated helper download/status page.'],
    ['group' => 'Helper workflow', 'label' => 'Extension Pair Status', 'url' => '/ops/firefox-extensions-status.php', 'file' => $webroot . '/ops/firefox-extensions-status.php', 'note' => 'Read-only status for current two-extension workflow.'],
    ['group' => 'Help', 'label' => 'Workflow Guide', 'url' => '/ops/workflow-guide.php', 'file' => $webroot . '/ops/workflow-guide.php', 'note' => 'Read-only staff SOP.'],
    ['group' => 'Help', 'label' => 'Safety Checklist', 'url' => '/ops/safety-checklist.php', 'file' => $webroot . '/ops/safety-checklist.php', 'note' => 'Read-only pre-submit checklist.'],
    ['group' => 'Help', 'label' => 'Mobile Compatibility', 'url' => '/ops/mobile-compatibility.php', 'file' => $webroot . '/ops/mobile-compatibility.php', 'note' => 'Read-only mobile usage guidance.'],
    ['group' => 'Help', 'label' => 'Tool Inventory', 'url' => '/ops/tool-inventory.php', 'file' => $webroot . '/ops/tool-inventory.php', 'note' => 'This read-only inventory page.'],
];

$coreFiles = [
    ['label' => 'Auth prepend', 'path' => $webroot . '/_auth_prepend.php', 'note' => 'Global login guard loaded by .user.ini.'],
    ['label' => 'Login page', 'path' => $webroot . '/ops/login.php', 'note' => 'Public unauthenticated login route.'],
    ['label' => 'Logout page', 'path' => $webroot . '/ops/logout.php', 'note' => 'Session logout route.'],
    ['label' => 'OpsAuth class', 'path' => $appRoot . '/src/Auth/OpsAuth.php', 'note' => 'Session authentication class.'],
    ['label' => 'Pre-Ride Parser', 'path' => $appRoot . '/src/BoltMail/BoltPreRideEmailParser.php', 'note' => 'No DB/network parser for Bolt email text.'],
    ['label' => 'EDXEIX Mapping Lookup', 'path' => $appRoot . '/src/BoltMail/EdxeixMappingLookup.php', 'note' => 'Read-only DB ID lookup for driver/vehicle/lessor.'],
    ['label' => 'Maildir Loader', 'path' => $appRoot . '/src/BoltMail/MaildirPreRideEmailLoader.php', 'note' => 'Latest server email loader.'],
];

$routeRows = [];
$missingRoutes = 0;
foreach ($routes as $route) {
    $status = oti_status_for_path($route['file']);
    if (!$status['exists']) { $missingRoutes++; }
    $routeRows[] = $route + ['status' => $status];
}

$coreRows = [];
$missingCore = 0;
foreach ($coreFiles as $file) {
    $status = oti_status_for_path($file['path']);
    if (!$status['exists']) { $missingCore++; }
    $coreRows[] = $file + ['status' => $status];
}

opsui_shell_begin([
    'title' => 'Tool Inventory',
    'page_title' => 'Ops tool inventory',
    'subtitle' => 'Read-only route and file status for the unified /ops GUI',
    'breadcrumbs' => 'Αρχική / Help / Tool inventory',
    'active_section' => 'Help',
    'safe_notice' => 'Read-only status page. It checks selected file existence, timestamps, and hashes only. It does not call Bolt, EDXEIX, or AADE, and it does not write data.',
]);
?>
<section class="card hero neutral">
    <h1>Route and file inventory</h1>
    <p>This page helps confirm which operator routes are present after patch uploads. It intentionally avoids reading real server-only config files or secrets.</p>
    <div>
        <?= opsui_badge('READ ONLY', 'good') ?>
        <?= opsui_badge('NO SECRET READS', 'good') ?>
        <?= opsui_badge('NO WORKFLOW ACTIONS', 'good') ?>
    </div>
    <div class="grid" style="margin-top:14px">
        <?= opsui_metric((string)count($routes), 'Tracked routes') ?>
        <?= opsui_metric((string)$missingRoutes, 'Missing routes') ?>
        <?= opsui_metric((string)count($coreFiles), 'Tracked core files') ?>
        <?= opsui_metric((string)$missingCore, 'Missing core files') ?>
    </div>
</section>

<section class="card">
    <h2>Operator routes</h2>
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>Group</th><th>Route</th><th>Status</th><th>Modified</th><th>Size</th><th>SHA-256</th><th>Note</th></tr>
            </thead>
            <tbody>
                <?php foreach ($routeRows as $row): $s = $row['status']; ?>
                <tr>
                    <td><?= opsui_h($row['group']) ?></td>
                    <td><?php if ($row['url'] !== ''): ?><a href="<?= opsui_h($row['url']) ?>"><?= opsui_h($row['label']) ?></a><br><code><?= opsui_h($row['url']) ?></code><?php else: ?><?= opsui_h($row['label']) ?><?php endif; ?></td>
                    <td><?= oti_status_badge($s) ?></td>
                    <td><?= opsui_h(oti_mtime_label((int)$s['mtime'])) ?></td>
                    <td><?= opsui_h(oti_size_label((int)$s['size'])) ?></td>
                    <td><code><?= opsui_h($s['sha256'] !== '' ? substr($s['sha256'], 0, 16) . '…' : '-') ?></code></td>
                    <td><?= opsui_h($row['note']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="card">
    <h2>Core support files</h2>
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>File</th><th>Status</th><th>Modified</th><th>Size</th><th>SHA-256</th><th>Note</th></tr>
            </thead>
            <tbody>
                <?php foreach ($coreRows as $row): $s = $row['status']; ?>
                <tr>
                    <td><strong><?= opsui_h($row['label']) ?></strong><br><code><?= opsui_h($row['path']) ?></code></td>
                    <td><?= oti_status_badge($s) ?></td>
                    <td><?= opsui_h(oti_mtime_label((int)$s['mtime'])) ?></td>
                    <td><?= opsui_h(oti_size_label((int)$s['size'])) ?></td>
                    <td><code><?= opsui_h($s['sha256'] !== '' ? substr($s['sha256'], 0, 16) . '…' : '-') ?></code></td>
                    <td><?= opsui_h($row['note']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="two">
    <div class="card">
        <h2>Production rule</h2>
        <ul class="list">
            <li><code>/ops/pre-ride-email-tool.php</code> remains the live production staff tool.</li>
            <li>Use <code>/ops/pre-ride-email-toolv2.php</code> for ongoing GUI development.</li>
            <li>Use this inventory after uploads to verify that expected pages exist.</li>
        </ul>
    </div>
    <div class="card">
        <h2>What this page does not check</h2>
        <ul class="list">
            <li>It does not validate real API credentials.</li>
            <li>It does not read server-only config files.</li>
            <li>It does not call Bolt or EDXEIX.</li>
            <li>It does not perform PHP syntax checks; use <code>php -l</code> from SSH for that.</li>
        </ul>
    </div>
</section>
<?php opsui_shell_end(); ?>
