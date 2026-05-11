<?php
/**
 * gov.cabnet.app — operator profile page v1.2
 *
 * Read-only profile dashboard with links to edit profile, password change, and activity.
 * Does not call Bolt, does not call EDXEIX.
 */

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Robots-Tag: noindex,nofollow', true);

require_once __DIR__ . '/_shell.php';

$user = opsui_current_user();
$name = opsui_user_display($user);
$role = opsui_user_role($user);
$username = (string)($user['username'] ?? '');
$email = (string)($user['email'] ?? '');
$loggedInAt = (string)($user['logged_in_at'] ?? '');
$sessionName = session_name();
$authViaInternalKey = defined('GOV_CABNET_AUTH_VIA_INTERNAL_KEY') && GOV_CABNET_AUTH_VIA_INTERNAL_KEY;
$isAdmin = opsui_is_admin($user);

opsui_shell_begin([
    'title' => 'Operator Profile',
    'page_title' => 'Προφίλ χρήστη',
    'active_section' => 'User area',
    'subtitle' => 'Current signed-in operator account',
    'breadcrumbs' => 'Αρχική / Χρήστες / Προφίλ',
    'safe_notice' => 'This profile page reads the current login session only. It does not call Bolt, does not call EDXEIX, and does not change trip data.',
]);
?>
<section class="gov-profile-grid">
    <article class="card gov-profile-hero">
        <div class="gov-profile-avatar-large"><?= opsui_h(opsui_initials($name)) ?></div>
        <div class="gov-profile-name"><?= opsui_h($name) ?></div>
        <div class="gov-profile-role"><?= opsui_h($role) ?></div>
        <div style="margin-top:16px;">
            <?= opsui_badge('SIGNED IN', 'good') ?>
            <?= opsui_badge(strtoupper($role), $isAdmin ? 'warn' : 'neutral') ?>
            <?= $authViaInternalKey ? opsui_badge('INTERNAL KEY', 'warn') : opsui_badge('SESSION LOGIN', 'good') ?>
        </div>
        <div class="gov-compact-actions" style="justify-content:center;">
            <a class="btn" href="/ops/profile-edit.php">Edit Profile</a>
            <a class="btn good" href="/ops/profile-password.php">Change Password</a>
            <a class="btn dark" href="/ops/logout.php">Logout</a>
        </div>
    </article>

    <article class="card">
        <h2>Account details</h2>
        <div class="gov-info-list">
            <div class="gov-info-row"><div class="label">Display name</div><div class="value"><?= opsui_h($name) ?></div></div>
            <div class="gov-info-row"><div class="label">Username</div><div class="value"><?= opsui_h($username) ?></div></div>
            <div class="gov-info-row"><div class="label">Email</div><div class="value"><?= opsui_h($email !== '' ? $email : 'Not set') ?></div></div>
            <div class="gov-info-row"><div class="label">Role</div><div class="value"><?= opsui_h($role) ?></div></div>
            <div class="gov-info-row"><div class="label">Logged in at</div><div class="value"><?= opsui_h($loggedInAt !== '' ? $loggedInAt : 'Current session') ?></div></div>
            <div class="gov-info-row"><div class="label">Session cookie</div><div class="value"><code><?= opsui_h($sessionName) ?></code></div></div>
        </div>
    </article>
</section>

<section class="gov-admin-grid">
    <a class="gov-admin-link gov-profile-action-card" href="/ops/profile-edit.php"><strong>Edit Profile</strong><span>Update your display name and email address.</span><small>Self-service</small></a>
    <a class="gov-admin-link gov-profile-action-card" href="/ops/profile-password.php"><strong>Change Password</strong><span>Update your own operator password using current-password confirmation.</span><small>CSRF protected</small></a>
    <a class="gov-admin-link gov-profile-action-card" href="/ops/profile-activity.php"><strong>My Activity</strong><span>Review your recent login attempts and account audit events.</span><small>Read only</small></a>
    <a class="gov-admin-link gov-profile-action-card" href="/ops/pre-ride-email-tool.php"><strong>Production Pre-Ride Tool</strong><span>Open the current live production operator workflow.</span><small>Do not disrupt</small></a>
    <a class="gov-admin-link gov-profile-action-card" href="/ops/firefox-extension.php"><strong>Firefox Helper</strong><span>Download the current operator browser helper package.</span><small>Authenticated access</small></a>
    <?php if ($isAdmin): ?>
        <a class="gov-admin-link gov-profile-action-card" href="/ops/users-control.php"><strong>Users Control</strong><span>Manage local operator accounts.</span><small>Admin only</small></a>
    <?php endif; ?>
</section>

<section class="two">
    <article class="card">
        <h2>User area status</h2>
        <ul class="list">
            <li>Profile display, profile editing, password change, and activity visibility are available.</li>
            <li>Role and active status remain controlled by admin user management.</li>
            <li>User actions do not affect the Bolt → EDXEIX workflow state.</li>
        </ul>
    </article>

    <article class="card">
        <h2>Safety boundary</h2>
        <div class="gov-alert-note">
            This user area does not affect Bolt ingestion, EDXEIX payload generation, AADE receipt issuing, or live submission state.
        </div>
        <div class="actions">
            <a class="btn good" href="/ops/home.php">Ops Home</a>
            <a class="btn" href="/ops/pre-ride-email-toolv2.php">Pre-Ride V2 Dev</a>
            <a class="btn dark" href="/ops/logout.php">Sign out</a>
        </div>
    </article>
</section>
<?php opsui_shell_end(); ?>
