<?php
/**
 * gov.cabnet.app — Ops Release Notes / Change Register
 *
 * Read-only shared-shell page for operator/admin visibility.
 * No Bolt calls, no EDXEIX calls, no AADE calls, no writes.
 */

declare(strict_types=1);

require_once __DIR__ . '/_shell.php';

function orl_file_status(string $path): array
{
    $root = dirname(__DIR__); // public_html/gov.cabnet.app
    $full = $root . $path;
    if (!is_file($full)) {
        return ['exists' => false, 'mtime' => '', 'size' => '', 'sha' => ''];
    }
    return [
        'exists' => true,
        'mtime' => date('Y-m-d H:i:s', (int)filemtime($full)),
        'size' => number_format((int)filesize($full)) . ' bytes',
        'sha' => substr(hash_file('sha256', $full) ?: '', 0, 16),
    ];
}

function orl_badge_for_status(bool $ok): string
{
    return opsui_badge($ok ? 'AVAILABLE' : 'MISSING', $ok ? 'good' : 'bad');
}

$routes = [
    ['/ops/home.php', 'Ops Home'],
    ['/ops/pre-ride-email-tool.php', 'Production Pre-Ride Tool'],
    ['/ops/pre-ride-email-toolv2.php', 'Pre-Ride Tool V2 Dev'],
    ['/ops/pre-ride-mobile-review.php', 'Mobile Pre-Ride Review'],
    ['/ops/firefox-extension.php', 'Firefox Helper Center'],
    ['/ops/firefox-extensions-status.php', 'Extension Pair Status'],
    ['/ops/mobile-compatibility.php', 'Mobile Compatibility'],
    ['/ops/workflow-guide.php', 'Workflow Guide'],
    ['/ops/safety-checklist.php', 'Safety Checklist'],
    ['/ops/quick-launch.php', 'Quick Launch'],
    ['/ops/documentation-center.php', 'Documentation Center'],
    ['/ops/deployment-center.php', 'Deployment Center'],
    ['/ops/handoff-center.php', 'Handoff Center'],
    ['/ops/system-status.php', 'System Status'],
    ['/ops/tool-inventory.php', 'Tool Inventory'],
    ['/ops/profile.php', 'Profile'],
    ['/ops/profile-edit.php', 'Edit Profile'],
    ['/ops/profile-preferences.php', 'Preferences'],
    ['/ops/profile-activity.php', 'My Activity'],
    ['/ops/users-control.php', 'Users Control'],
    ['/ops/activity-center.php', 'Activity Center'],
    ['/ops/audit-log.php', 'Audit Log'],
    ['/ops/login-attempts.php', 'Login Attempts'],
];

$phases = [
    ['phase' => 'Login foundation', 'title' => 'Ops login replaces IP-only access', 'status' => 'deployed', 'notes' => 'Session login protects /ops routes. Old IP guard can stay disabled after login verification.'],
    ['phase' => 'Phase 1–3', 'title' => 'Shared shell, profile, password, activity basics', 'status' => 'deployed', 'notes' => 'Introduced reusable EDXEIX-style GUI shell and user profile area.'],
    ['phase' => 'Phase 4–6', 'title' => 'User administration and activity visibility', 'status' => 'deployed', 'notes' => 'Added admin user management, audit log, login attempts, activity center, and My Activity.'],
    ['phase' => 'Phase 7–9', 'title' => 'Profile edit and preferences', 'status' => 'deployed', 'notes' => 'Added profile edit, preferences storage, and preference application on shared-shell pages.'],
    ['phase' => 'Phase 10–12', 'title' => 'Firefox helper and mobile guidance', 'status' => 'deployed', 'notes' => 'Added helper center, extension pair status, and mobile compatibility guidance.'],
    ['phase' => 'Phase 13–15', 'title' => 'Mobile review, SOPs, and inventory', 'status' => 'deployed', 'notes' => 'Added mobile pre-ride review, workflow guide, safety checklist, and tool inventory.'],
    ['phase' => 'Phase 16–18', 'title' => 'System, deployment, and handoff centers', 'status' => 'deployed', 'notes' => 'Added system status, deployment center, and handoff center.'],
    ['phase' => 'Phase 19–21', 'title' => 'Documentation, dropdown navigation, and quick launch', 'status' => 'deployed', 'notes' => 'Added documentation center, compact dropdown top navigation, and quick launch.'],
    ['phase' => 'Phase 22', 'title' => 'Release notes / change register', 'status' => 'current', 'notes' => 'This read-only page centralizes the GUI rollout record and route availability.'],
];

opsui_shell_begin([
    'title' => 'Release Notes',
    'page_title' => 'Ops release notes',
    'active_section' => 'Release Notes',
    'subtitle' => 'Read-only change register for the unified ops GUI.',
    'breadcrumbs' => 'Αρχική / Documentation / Release notes',
    'safe_notice' => 'This page is a read-only change register. It does not call Bolt, EDXEIX, AADE, write data, stage jobs, or change production behavior.',
]);
?>

<section class="card hero neutral">
    <h1>Ops GUI release notes</h1>
    <p>Central read-only register for the login, profile, helper, documentation, and deployment interface work. The production pre-ride tool remains the active staff workflow and is not modified by this page.</p>
    <div>
        <?= opsui_badge('READ ONLY', 'good') ?>
        <?= opsui_badge('NO WORKFLOW WRITES', 'good') ?>
        <?= opsui_badge('PRODUCTION TOOL UNCHANGED', 'good') ?>
    </div>
</section>

<section class="card">
    <h2>Current route rules</h2>
    <div class="gov-admin-grid">
        <div class="gov-admin-link"><strong>Production route</strong><span><code>/ops/pre-ride-email-tool.php</code><br>Live staff page. Do not disrupt.</span></div>
        <div class="gov-admin-link"><strong>Development route</strong><span><code>/ops/pre-ride-email-toolv2.php</code><br>Use for future improvements before promotion.</span></div>
        <div class="gov-admin-link"><strong>Firefox helpers</strong><span>Keep both temporary helpers loaded until the unified signed helper is built and tested.</span></div>
    </div>
</section>

<section class="card">
    <h2>Change register</h2>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Phase</th><th>Title</th><th>Status</th><th>Notes</th></tr></thead>
            <tbody>
            <?php foreach ($phases as $item): ?>
                <tr>
                    <td><strong><?= opsui_h($item['phase']) ?></strong></td>
                    <td><?= opsui_h($item['title']) ?></td>
                    <td><?= opsui_badge(strtoupper($item['status']), $item['status'] === 'current' ? 'warn' : 'good') ?></td>
                    <td><?= opsui_h($item['notes']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="card">
    <h2>Route availability snapshot</h2>
    <p class="small">Safe file presence check only. No config secrets are read.</p>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Route</th><th>Label</th><th>Status</th><th>Modified</th><th>Size</th><th>SHA-256</th></tr></thead>
            <tbody>
            <?php foreach ($routes as [$route, $label]): $st = orl_file_status($route); ?>
                <tr>
                    <td><a href="<?= opsui_h($route) ?>"><?= opsui_h($route) ?></a></td>
                    <td><?= opsui_h($label) ?></td>
                    <td><?= orl_badge_for_status((bool)$st['exists']) ?></td>
                    <td class="mono"><?= opsui_h($st['mtime']) ?></td>
                    <td><?= opsui_h($st['size']) ?></td>
                    <td class="mono"><?= opsui_h($st['sha']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="card">
    <h2>Next safe development candidates</h2>
    <ul class="list">
        <li>Create a unified Firefox helper development copy while keeping both current helpers loaded in production.</li>
        <li>Build a V2 pre-ride tool inside the shared shell without changing the production page.</li>
        <li>Add read-only operator dashboard widgets that summarize route status, helper status, and live safety posture.</li>
        <li>Prepare a controlled promotion checklist before any V2 page replaces the production route.</li>
    </ul>
</section>

<?php opsui_shell_end(); ?>
