<?php
/**
 * gov.cabnet.app — Ops Documentation Center
 *
 * Read-only shared-shell index for staff/operator guidance pages.
 * No Bolt calls, no EDXEIX calls, no AADE calls, no DB writes.
 */

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Robots-Tag: noindex,nofollow', true);

$shell = __DIR__ . '/_shell.php';
if (!is_file($shell)) {
    http_response_code(500);
    echo 'Shared ops shell not found.';
    exit;
}
require_once $shell;

function docc_public_root(): string
{
    return dirname(__DIR__);
}

function docc_file_meta(string $href): array
{
    $path = parse_url($href, PHP_URL_PATH);
    $path = is_string($path) ? $path : '';
    $full = docc_public_root() . $path;
    $ok = is_file($full) && is_readable($full);
    return [
        'exists' => $ok,
        'mtime' => $ok ? date('Y-m-d H:i:s', (int)filemtime($full)) : '',
        'size' => $ok ? number_format((int)filesize($full)) . ' bytes' : '',
        'hash' => $ok ? substr(hash_file('sha256', $full) ?: '', 0, 16) : '',
    ];
}

function docc_card(string $href, string $title, string $summary, string $tag = 'Guide'): string
{
    $meta = docc_file_meta($href);
    $status = $meta['exists'] ? opsui_badge('AVAILABLE', 'good') : opsui_badge('MISSING', 'bad');
    $details = $meta['exists']
        ? '<div class="small">Updated: ' . opsui_h($meta['mtime']) . ' · ' . opsui_h($meta['size']) . ' · SHA: <code>' . opsui_h($meta['hash']) . '</code></div>'
        : '<div class="small badline">Expected file was not found on this server.</div>';

    return '<article class="gov-doc-card">'
        . '<div class="gov-doc-card-head"><span class="gov-doc-tag">' . opsui_h($tag) . '</span>' . $status . '</div>'
        . '<h3>' . opsui_h($title) . '</h3>'
        . '<p>' . opsui_h($summary) . '</p>'
        . $details
        . '<div class="actions"><a class="btn" href="' . opsui_h($href) . '">Open</a></div>'
        . '</article>';
}

$groups = [
    'Live workflow' => [
        ['/ops/pre-ride-email-tool.php', 'Production Pre-Ride Tool', 'Current live operator tool. Do not modify directly unless absolutely necessary.', 'Production'],
        ['/ops/pre-ride-email-toolv2.php', 'Pre-Ride Tool V2', 'Safe development/staging wrapper for future pre-ride workflow improvements.', 'Development'],
        ['/ops/pre-ride-mobile-review.php', 'Mobile Pre-Ride Review', 'Mobile-friendly read-only review page. Checking only; desktop remains required for EDXEIX save.', 'Mobile'],
        ['/ops/workflow-guide.php', 'Workflow Guide', 'Step-by-step operator SOP for the Bolt pre-ride email to EDXEIX workflow.', 'SOP'],
        ['/ops/safety-checklist.php', 'Safety Checklist', 'Pre-submit checklist and never-submit rules for operators.', 'Safety'],
    ],
    'Firefox helper guidance' => [
        ['/ops/firefox-extension.php', 'Firefox Helper Center', 'Authenticated download/status page for the helper files.', 'Helper'],
        ['/ops/firefox-extensions-status.php', 'Extension Pair Status', 'Read-only status page for the current two-extension workflow.', 'Helper'],
        ['/ops/mobile-compatibility.php', 'Mobile Compatibility', 'Explains what can be done from mobile and why the helper workflow remains desktop-only.', 'Mobile'],
    ],
    'User area' => [
        ['/ops/profile.php', 'Operator Profile', 'Logged-in operator account summary and profile actions.', 'Profile'],
        ['/ops/profile-edit.php', 'Edit Profile', 'Self-service display name and email update page.', 'Profile'],
        ['/ops/profile-password.php', 'Change Password', 'Self-service password change page.', 'Security'],
        ['/ops/profile-preferences.php', 'Preferences', 'Personal UI preferences for supported shared-shell pages.', 'Profile'],
        ['/ops/profile-activity.php', 'My Activity', 'Logged-in operator activity and recent login visibility.', 'Activity'],
    ],
    'Admin visibility' => [
        ['/ops/users-control.php', 'Users Control', 'Admin-only user account overview.', 'Admin'],
        ['/ops/users-new.php', 'Create User', 'Admin-only local operator account creation.', 'Admin'],
        ['/ops/activity-center.php', 'Activity Center', 'Admin-only activity summary.', 'Audit'],
        ['/ops/audit-log.php', 'Audit Log', 'Admin-only audit event viewer.', 'Audit'],
        ['/ops/login-attempts.php', 'Login Attempts', 'Admin-only successful/failed login attempt viewer.', 'Security'],
    ],
    'Operations and continuity' => [
        ['/ops/home.php', 'Ops Home', 'Main shared-shell operations landing page.', 'Home'],
        ['/ops/system-status.php', 'System Status', 'Read-only server/app/DB status without secret output.', 'Status'],
        ['/ops/tool-inventory.php', 'Tool Inventory', 'Read-only route and core file presence/fingerprint inventory.', 'Inventory'],
        ['/ops/deployment-center.php', 'Deployment Center', 'Manual upload, verification, commit, and rollback guidance.', 'Deployment'],
        ['/ops/handoff-center.php', 'Handoff Center', 'Copy/paste continuity prompt for starting a new Sophion session.', 'Continuity'],
    ],
];

opsui_shell_begin([
    'title' => 'Documentation Center',
    'page_title' => 'Documentation Center',
    'breadcrumbs' => 'Αρχική / Διαχειριστικό / Documentation Center',
    'active_section' => 'Documentation',
    'subtitle' => 'Central read-only index for operator SOPs, user help, helper guidance, deployment notes, and continuity tools.',
    'safe_notice' => 'This page is a read-only index. It does not call Bolt, EDXEIX, AADE, write data, stage jobs, or change production behavior.',
]);
?>

<section class="card hero neutral">
    <h1>Ops Documentation Center</h1>
    <p>Use this page as the staff-facing index for the new shared GUI. The production pre-ride tool remains the live working route; development work stays on V2 or separate support pages.</p>
    <div>
        <?= opsui_badge('READ ONLY', 'good') ?>
        <?= opsui_badge('NO SECRET OUTPUT', 'good') ?>
        <?= opsui_badge('NO WORKFLOW WRITES', 'good') ?>
        <?= opsui_badge('PRODUCTION TOOL UNCHANGED', 'good') ?>
    </div>
</section>

<section class="card">
    <h2>Current operating rules</h2>
    <div class="gov-admin-grid">
        <div class="gov-admin-link"><strong>Production route</strong><span><code>/ops/pre-ride-email-tool.php</code><br>Live staff page. Do not disrupt.</span></div>
        <div class="gov-admin-link"><strong>Development route</strong><span><code>/ops/pre-ride-email-toolv2.php</code><br>Use for future improvements before promotion.</span></div>
        <div class="gov-admin-link"><strong>Firefox helpers</strong><span>Keep both helper extensions loaded until we merge and test one unified signed helper.</span></div>
    </div>
</section>

<?php foreach ($groups as $groupTitle => $items): ?>
<section class="card">
    <h2><?= opsui_h($groupTitle) ?></h2>
    <div class="gov-doc-grid">
        <?php foreach ($items as $item): ?>
            <?= docc_card($item[0], $item[1], $item[2], $item[3]) ?>
        <?php endforeach; ?>
    </div>
</section>
<?php endforeach; ?>

<section class="card">
    <h2>Next safe GUI direction</h2>
    <ol class="timeline">
        <li>Keep adding new features as separate shared-shell pages.</li>
        <li>Do not replace the production pre-ride tool until V2 is tested with the full operator flow.</li>
        <li>Merge Firefox helpers only after both source folders are inventoried and a signed-XPI strategy is chosen.</li>
        <li>Use Deployment Center and Handoff Center after each significant batch of work.</li>
    </ol>
</section>

<?php opsui_shell_end(); ?>
