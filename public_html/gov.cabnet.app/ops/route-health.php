<?php
/**
 * gov.cabnet.app — Ops Route Health Center
 *
 * Read-only local file availability dashboard for /ops routes.
 * No Bolt calls, no EDXEIX calls, no AADE calls, no DB writes.
 */

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Robots-Tag: noindex, nofollow', true);

$shell = __DIR__ . '/_shell.php';
if (is_file($shell)) {
    require_once $shell;
}

if (!function_exists('opsui_h')) {
    function opsui_h(mixed $value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('opsui_shell_begin')) {
    function opsui_shell_begin(array $options = []): void
    {
        $title = opsui_h((string)($options['title'] ?? 'Route Health'));
        echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>' . $title . ' | gov.cabnet.app</title><style>body{font-family:Arial,sans-serif;background:#f3f6fb;margin:0;color:#20293a}.wrap{max-width:1280px;margin:24px auto;padding:0 16px}.card{background:#fff;border:1px solid #d8dde7;border-radius:8px;padding:18px;margin:0 0 16px}table{width:100%;border-collapse:collapse;background:#fff}th,td{border-bottom:1px solid #e5e7eb;padding:9px 10px;text-align:left;vertical-align:top;font-size:14px}th{background:#f6f7f9}.badge{display:inline-block;padding:4px 8px;border-radius:999px;font-weight:700;font-size:12px}.badge-good{background:#dcfce7;color:#166534}.badge-warn{background:#fff7ed;color:#b45309}.badge-bad{background:#fee2e2;color:#991b1b}.mono{font-family:Consolas,monospace}.table-wrap{overflow:auto}.btn{display:inline-block;background:#4f5ea7;color:#fff;text-decoration:none;padding:9px 12px;border-radius:5px;margin:3px}</style></head><body><main class="wrap">';
    }
    function opsui_shell_end(): void
    {
        echo '</main></body></html>';
    }
}

function rh_badge(string $text, string $type = 'neutral'): string
{
    return '<span class="badge badge-' . opsui_h($type) . '">' . opsui_h($text) . '</span>';
}

function rh_short_hash(string $path): string
{
    if (!is_file($path) || !is_readable($path)) {
        return '';
    }
    $hash = hash_file('sha256', $path);
    return is_string($hash) ? substr($hash, 0, 16) : '';
}

function rh_file_meta(string $path): array
{
    if (!is_file($path)) {
        return [
            'exists' => false,
            'size' => '',
            'mtime' => '',
            'hash' => '',
        ];
    }
    $size = filesize($path);
    $mtime = filemtime($path);
    return [
        'exists' => true,
        'size' => is_int($size) ? number_format($size) . ' bytes' : '',
        'mtime' => is_int($mtime) ? date('Y-m-d H:i:s', $mtime) : '',
        'hash' => rh_short_hash($path),
    ];
}

$routes = [
    ['group' => 'Production', 'route' => '/ops/pre-ride-email-tool.php', 'label' => 'Production Pre-Ride Email Tool', 'required' => true, 'note' => 'Production-critical. Do not modify directly for development.'],
    ['group' => 'Development', 'route' => '/ops/pre-ride-email-toolv2.php', 'label' => 'Pre-Ride Tool V2 Dev', 'required' => false, 'note' => 'Safe development wrapper/route.'],
    ['group' => 'Core', 'route' => '/ops/home.php', 'label' => 'Ops Home', 'required' => true, 'note' => 'Operator landing page.'],
    ['group' => 'Core', 'route' => '/ops/ops-dashboard.php', 'label' => 'Ops Dashboard', 'required' => false, 'note' => 'Read-only overview.'],
    ['group' => 'Core', 'route' => '/ops/quick-launch.php', 'label' => 'Quick Launch', 'required' => false, 'note' => 'Searchable route launcher.'],
    ['group' => 'Core', 'route' => '/ops/documentation-center.php', 'label' => 'Documentation Center', 'required' => false, 'note' => 'Central documentation index.'],
    ['group' => 'Core', 'route' => '/ops/release-notes.php', 'label' => 'Release Notes', 'required' => false, 'note' => 'Change register.'],
    ['group' => 'Core', 'route' => '/ops/route-health.php', 'label' => 'Route Health Center', 'required' => false, 'note' => 'This page.'],
    ['group' => 'User', 'route' => '/ops/profile.php', 'label' => 'Profile', 'required' => true, 'note' => 'User profile page.'],
    ['group' => 'User', 'route' => '/ops/profile-edit.php', 'label' => 'Edit Profile', 'required' => false, 'note' => 'Self-service profile edit.'],
    ['group' => 'User', 'route' => '/ops/profile-password.php', 'label' => 'Change Password', 'required' => false, 'note' => 'Self-service password change.'],
    ['group' => 'User', 'route' => '/ops/profile-preferences.php', 'label' => 'Preferences', 'required' => false, 'note' => 'User UI preferences.'],
    ['group' => 'User', 'route' => '/ops/profile-activity.php', 'label' => 'My Activity', 'required' => false, 'note' => 'Read-only personal activity.'],
    ['group' => 'Admin', 'route' => '/ops/users-control.php', 'label' => 'Users Control', 'required' => false, 'note' => 'Admin-only user overview.'],
    ['group' => 'Admin', 'route' => '/ops/users-new.php', 'label' => 'Create User', 'required' => false, 'note' => 'Admin-only user creation.'],
    ['group' => 'Admin', 'route' => '/ops/users-edit.php', 'label' => 'Edit User', 'required' => false, 'note' => 'Admin-only user edit/reset. Requires id.'],
    ['group' => 'Admin', 'route' => '/ops/activity-center.php', 'label' => 'Activity Center', 'required' => false, 'note' => 'Admin-only activity overview.'],
    ['group' => 'Admin', 'route' => '/ops/audit-log.php', 'label' => 'Audit Log', 'required' => false, 'note' => 'Admin-only audit visibility.'],
    ['group' => 'Admin', 'route' => '/ops/login-attempts.php', 'label' => 'Login Attempts', 'required' => false, 'note' => 'Admin-only login visibility.'],
    ['group' => 'Helper', 'route' => '/ops/firefox-extension.php', 'label' => 'Firefox Helper Center', 'required' => false, 'note' => 'Authenticated helper ZIP/download info.'],
    ['group' => 'Helper', 'route' => '/ops/firefox-extensions-status.php', 'label' => 'Firefox Extension Pair Status', 'required' => false, 'note' => 'Two-helper workflow visibility.'],
    ['group' => 'Mobile', 'route' => '/ops/mobile-compatibility.php', 'label' => 'Mobile Compatibility', 'required' => false, 'note' => 'Mobile/desktop rules.'],
    ['group' => 'Mobile', 'route' => '/ops/pre-ride-mobile-review.php', 'label' => 'Mobile Pre-Ride Review', 'required' => false, 'note' => 'Read-only mobile review.'],
    ['group' => 'Guide', 'route' => '/ops/workflow-guide.php', 'label' => 'Workflow Guide', 'required' => false, 'note' => 'Staff SOP.'],
    ['group' => 'Guide', 'route' => '/ops/safety-checklist.php', 'label' => 'Safety Checklist', 'required' => false, 'note' => 'Pre-submit checklist.'],
    ['group' => 'Guide', 'route' => '/ops/print-guide.php', 'label' => 'Printable Guide', 'required' => false, 'note' => 'Print/save-to-PDF guide.'],
    ['group' => 'Maintenance', 'route' => '/ops/tool-inventory.php', 'label' => 'Tool Inventory', 'required' => false, 'note' => 'File inventory.'],
    ['group' => 'Maintenance', 'route' => '/ops/system-status.php', 'label' => 'System Status', 'required' => false, 'note' => 'Read-only system overview.'],
    ['group' => 'Maintenance', 'route' => '/ops/deployment-center.php', 'label' => 'Deployment Center', 'required' => false, 'note' => 'Manual deployment guidance.'],
    ['group' => 'Maintenance', 'route' => '/ops/maintenance-center.php', 'label' => 'Maintenance Center', 'required' => false, 'note' => 'Maintenance commands/checklists.'],
    ['group' => 'Maintenance', 'route' => '/ops/smoke-test-center.php', 'label' => 'Smoke Test Center', 'required' => false, 'note' => 'Post-upload checks.'],
    ['group' => 'Continuity', 'route' => '/ops/handoff-center.php', 'label' => 'Handoff Center', 'required' => false, 'note' => 'New-session handoff prompt.'],
];

$summary = ['total' => 0, 'present' => 0, 'missing_required' => 0, 'missing_optional' => 0];
foreach ($routes as $route) {
    $summary['total']++;
    $file = __DIR__ . '/' . basename((string)$route['route']);
    if (is_file($file)) {
        $summary['present']++;
    } elseif (!empty($route['required'])) {
        $summary['missing_required']++;
    } else {
        $summary['missing_optional']++;
    }
}

opsui_shell_begin([
    'title' => 'Route Health Center',
    'page_title' => 'Route Health Center',
    'breadcrumbs' => 'Αρχική / Διαχειριστικό / Route Health',
    'active_section' => 'Maintenance',
    'safe_notice' => 'Read-only local route/file availability dashboard. It does not call external services, does not read secrets, and does not write data.',
]);
?>
<section class="card hero neutral">
    <h1>Ops Route Health</h1>
    <p>Local availability check for important <code>/ops</code> pages. This helps confirm that uploaded patch files landed correctly without touching the production pre-ride workflow.</p>
    <div class="grid" style="margin-top:14px">
        <?= function_exists('opsui_metric') ? opsui_metric((string)$summary['total'], 'Routes tracked') : '' ?>
        <?= function_exists('opsui_metric') ? opsui_metric((string)$summary['present'], 'Files present') : '' ?>
        <?= function_exists('opsui_metric') ? opsui_metric((string)$summary['missing_required'], 'Required missing') : '' ?>
        <?= function_exists('opsui_metric') ? opsui_metric((string)$summary['missing_optional'], 'Optional missing') : '' ?>
    </div>
    <div class="actions">
        <a class="btn good" href="/ops/pre-ride-email-tool.php">Production Pre-Ride Tool</a>
        <a class="btn" href="/ops/quick-launch.php">Quick Launch</a>
        <a class="btn dark" href="/ops/smoke-test-center.php">Smoke Test Center</a>
        <a class="btn warn" href="/ops/deployment-center.php">Deployment Center</a>
    </div>
</section>

<section class="card">
    <h2>Route file status</h2>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Group</th>
                    <th>Route</th>
                    <th>Status</th>
                    <th>Required</th>
                    <th>Modified</th>
                    <th>Size</th>
                    <th>SHA-256</th>
                    <th>Note</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($routes as $route): ?>
                <?php
                $file = __DIR__ . '/' . basename((string)$route['route']);
                $meta = rh_file_meta($file);
                $statusType = $meta['exists'] ? 'good' : (!empty($route['required']) ? 'bad' : 'warn');
                $statusText = $meta['exists'] ? 'PRESENT' : 'MISSING';
                ?>
                <tr>
                    <td><?= opsui_h((string)$route['group']) ?></td>
                    <td><a href="<?= opsui_h((string)$route['route']) ?>"><?= opsui_h((string)$route['label']) ?></a><br><span class="mono small"><?= opsui_h((string)$route['route']) ?></span></td>
                    <td><?= rh_badge($statusText, $statusType) ?></td>
                    <td><?= !empty($route['required']) ? rh_badge('YES', 'good') : rh_badge('NO', 'neutral') ?></td>
                    <td><?= opsui_h($meta['mtime']) ?></td>
                    <td><?= opsui_h($meta['size']) ?></td>
                    <td class="mono"><?= opsui_h($meta['hash']) ?></td>
                    <td><?= opsui_h((string)$route['note']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="card">
    <h2>Copy/paste route syntax checks</h2>
    <p class="small">Run this after uploading a route patch. It checks PHP syntax only.</p>
    <textarea class="code-box" readonly rows="12"><?php foreach ($routes as $route): ?><?php $file = '/home/cabnet/public_html/gov.cabnet.app/ops/' . basename((string)$route['route']); ?>php -l <?= opsui_h($file) . "\n" ?><?php endforeach; ?></textarea>
</section>

<section class="card">
    <h2>Production route rule</h2>
    <p><strong>Do not modify the production pre-ride tool directly during GUI development:</strong></p>
    <ul class="list">
        <li><code>/ops/pre-ride-email-tool.php</code> = production and in active use.</li>
        <li><code>/ops/pre-ride-email-toolv2.php</code> = safe development/testing route.</li>
        <li>Actual EDXEIX fill/save remains desktop/laptop Firefox with both helpers loaded.</li>
    </ul>
</section>
<?php
opsui_shell_end();
