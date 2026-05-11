<?php
/**
 * gov.cabnet.app — operator profile page v1.0
 *
 * Read-only profile view. No password change yet, no DB writes.
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

opsui_shell_begin([
    'title' => 'Operator Profile',
    'page_title' => 'Προφίλ χρήστη',
    'active_section' => 'User Profile',
    'subtitle' => 'Current signed-in operator account',
    'breadcrumbs' => 'Αρχική / Χρήστες / Προφίλ',
    'safe_notice' => 'This profile page is read-only. It does not call Bolt, does not call EDXEIX, and does not write database rows.',
]);
?>
<section class="gov-profile-grid">
    <article class="card gov-profile-hero">
        <div class="gov-profile-avatar-large"><?= opsui_h(opsui_initials($name)) ?></div>
        <div class="gov-profile-name"><?= opsui_h($name) ?></div>
        <div class="gov-profile-role"><?= opsui_h($role) ?></div>
        <div style="margin-top:16px;">
            <?= opsui_badge('SIGNED IN', 'good') ?>
            <?= opsui_badge(strtoupper($role), $role === 'admin' ? 'warn' : 'neutral') ?>
            <?= $authViaInternalKey ? opsui_badge('INTERNAL KEY', 'warn') : opsui_badge('SESSION LOGIN', 'good') ?>
        </div>
        <div class="gov-compact-actions" style="justify-content:center;">
            <a class="btn" href="/ops/home.php">Ops Home</a>
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

<section class="two">
    <article class="card">
        <h2>Profile actions</h2>
        <p>This first version is intentionally read-only to avoid disrupting production operations.</p>
        <div class="actions">
            <a class="btn good" href="/ops/pre-ride-email-tool.php">Open Production Pre-Ride Tool</a>
            <a class="btn" href="/ops/firefox-extension.php">Firefox Helper</a>
            <a class="btn dark" href="/ops/logout.php">Sign out</a>
        </div>
    </article>

    <article class="card">
        <h2>Next profile features</h2>
        <div class="gov-alert-note">
            Password changes are deliberately not enabled on this first patch. User creation/password reset remains CLI/admin-controlled until we add a reviewed form with CSRF, password strength checks, and audit logging.
        </div>
        <ul class="list">
            <li>Change password form with current password confirmation.</li>
            <li>User management screen for admin role only.</li>
            <li>Last login and audit history panel.</li>
            <li>Optional staff preferences for UI language and default landing page.</li>
        </ul>
    </article>
</section>
<?php opsui_shell_end(); ?>
