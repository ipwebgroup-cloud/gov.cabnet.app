<?php
/**
 * gov.cabnet.app — operator profile edit page v1.0
 *
 * Allows the logged-in operator to update their own display name and email.
 * Does not call Bolt, EDXEIX, or AADE. Does not touch trip/workflow data.
 */

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Robots-Tag: noindex,nofollow', true);

require_once __DIR__ . '/_shell.php';

function opedit_bootstrap_context(?string &$error = null): ?array
{
    $bootstrap = dirname(__DIR__, 3) . '/gov.cabnet.app_app/src/bootstrap.php';
    if (!is_file($bootstrap)) {
        $error = 'Private app bootstrap not found.';
        return null;
    }

    try {
        $ctx = require $bootstrap;
        if (!is_array($ctx) || !isset($ctx['db']) || !method_exists($ctx['db'], 'connection')) {
            $error = 'Private app bootstrap did not return a valid DB context.';
            return null;
        }
        $error = null;
        return $ctx;
    } catch (Throwable $e) {
        $error = $e->getMessage();
        return null;
    }
}

function opedit_csrf(): string
{
    if (empty($_SESSION['ops_profile_edit_csrf']) || !is_string($_SESSION['ops_profile_edit_csrf'])) {
        $_SESSION['ops_profile_edit_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['ops_profile_edit_csrf'];
}

function opedit_validate_csrf(string $token): bool
{
    return isset($_SESSION['ops_profile_edit_csrf'])
        && is_string($_SESSION['ops_profile_edit_csrf'])
        && hash_equals($_SESSION['ops_profile_edit_csrf'], $token);
}

function opedit_client_ip(): string
{
    foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'] as $key) {
        $value = trim((string)($_SERVER[$key] ?? ''));
        if ($value !== '') {
            return substr($value, 0, 45);
        }
    }
    return '';
}

function opedit_audit(mysqli $db, int $userId, string $eventType, array $meta = []): void
{
    try {
        $ip = opedit_client_ip();
        $ua = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500);
        $json = $meta === [] ? null : json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $stmt = $db->prepare('INSERT INTO ops_audit_log (user_id, event_type, ip_address, user_agent, meta_json, created_at) VALUES (?, ?, ?, ?, ?, NOW())');
        $stmt->bind_param('issss', $userId, $eventType, $ip, $ua, $json);
        $stmt->execute();
    } catch (Throwable) {
        // Profile updates should not fatal if audit logging is temporarily unavailable.
    }
}


function opedit_len(string $value): int
{
    if (function_exists('mb_strlen')) {
        return mb_strlen($value, 'UTF-8');
    }
    return strlen($value);
}

function opedit_fetch_user(mysqli $db, int $userId): ?array
{
    try {
        $stmt = $db->prepare('SELECT id, username, email, display_name, role, is_active, last_login_at, last_login_ip, created_at, updated_at FROM ops_users WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        return is_array($row) ? $row : null;
    } catch (Throwable) {
        return null;
    }
}

$user = opsui_current_user();
$userId = (int)($user['id'] ?? 0);
$name = opsui_user_display($user);
$isAdmin = opsui_is_admin($user);
$bootstrapError = null;
$ctx = opedit_bootstrap_context($bootstrapError);
$db = $ctx ? $ctx['db']->connection() : null;
$dbUser = $db instanceof mysqli && $userId > 0 ? opedit_fetch_user($db, $userId) : null;

$error = '';
$success = '';

if (!$db instanceof mysqli) {
    $error = 'Database connection is unavailable: ' . (string)$bootstrapError;
} elseif (!$dbUser) {
    $error = 'Your user record could not be loaded. Please sign out and sign in again.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $error === '' && $dbUser) {
    if (!opedit_validate_csrf((string)($_POST['csrf'] ?? ''))) {
        $error = 'Security token expired. Please try again.';
    } else {
        $displayName = trim((string)($_POST['display_name'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));

        if ($displayName === '' || opedit_len($displayName) > 190) {
            $error = 'Display name is required and must be 190 characters or less.';
        } elseif ($email !== '' && (opedit_len($email) > 190 || !filter_var($email, FILTER_VALIDATE_EMAIL))) {
            $error = 'Please enter a valid email address, or leave it blank.';
        } else {
            try {
                if ($email !== '') {
                    $stmt = $db->prepare('SELECT id FROM ops_users WHERE email = ? AND id <> ? LIMIT 1');
                    $stmt->bind_param('si', $email, $userId);
                    $stmt->execute();
                    $existing = $stmt->get_result()->fetch_assoc();
                    if (is_array($existing)) {
                        throw new RuntimeException('That email address is already assigned to another operator account.');
                    }
                }

                $emailDb = $email !== '' ? $email : null;
                $stmt = $db->prepare('UPDATE ops_users SET display_name = ?, email = ?, updated_at = NOW() WHERE id = ? LIMIT 1');
                $stmt->bind_param('ssi', $displayName, $emailDb, $userId);
                $stmt->execute();

                $_SESSION['ops_user']['display_name'] = $displayName;
                $_SESSION['ops_user']['email'] = $email;
                $user = opsui_current_user();
                $name = opsui_user_display($user);
                $dbUser = opedit_fetch_user($db, $userId) ?: $dbUser;

                opedit_audit($db, $userId, 'profile_updated', [
                    'updated_fields' => ['display_name', 'email'],
                    'source' => 'ops/profile-edit.php',
                ]);

                unset($_SESSION['ops_profile_edit_csrf']);
                $success = 'Profile updated successfully.';
            } catch (Throwable $e) {
                $error = $e->getMessage();
            }
        }
    }
}

$csrf = opedit_csrf();
$displayValue = (string)($dbUser['display_name'] ?? $user['display_name'] ?? $name);
$emailValue = (string)($dbUser['email'] ?? $user['email'] ?? '');
$username = (string)($dbUser['username'] ?? $user['username'] ?? '');
$role = (string)($dbUser['role'] ?? opsui_user_role($user));
$isActive = (int)($dbUser['is_active'] ?? 1) === 1;

opsui_shell_begin([
    'title' => 'Edit Profile',
    'page_title' => 'Edit profile',
    'active_section' => 'User area',
    'subtitle' => 'Safe Bolt → EDXEIX operator console',
    'breadcrumbs' => 'Αρχική / Profile / Edit profile',
    'safe_notice' => 'This page only updates your local operator display name and email. It does not call Bolt, EDXEIX, or AADE, and does not change trip data.',
]);
?>
<section class="card hero neutral">
    <h1>Edit my profile</h1>
    <p>Update the account details used inside the operator console.</p>
    <div>
        <?= opsui_badge('PROFILE AREA', 'neutral') ?>
        <?= opsui_badge('CSRF PROTECTED', 'good') ?>
        <?= opsui_badge('NO WORKFLOW ACTIONS', 'good') ?>
    </div>
</section>

<?php if ($success !== ''): ?>
    <?= opsui_flash($success, 'good') ?>
<?php endif; ?>
<?php if ($error !== ''): ?>
    <?= opsui_flash($error, 'bad') ?>
<?php endif; ?>

<section class="two">
    <article class="card">
        <h2>Editable details</h2>
        <form method="post" action="/ops/profile-edit.php" autocomplete="off">
            <input type="hidden" name="csrf" value="<?= opsui_h($csrf) ?>">
            <div class="gov-form-row">
                <label for="display_name">Display name</label>
                <input id="display_name" name="display_name" type="text" maxlength="190" required value="<?= opsui_h($displayValue) ?>">
                <span class="gov-help-text">This name appears in the sidebar, top profile chip, and user activity pages.</span>
            </div>
            <div class="gov-form-row">
                <label for="email">Email</label>
                <input id="email" name="email" type="email" maxlength="190" value="<?= opsui_h($emailValue) ?>">
                <span class="gov-help-text">Used for operator identity only. Leave blank if not needed.</span>
            </div>
            <div class="gov-form-actions">
                <button class="btn good" type="submit">Save profile</button>
                <a class="btn dark" href="/ops/profile.php">Cancel</a>
                <a class="btn" href="/ops/profile-password.php">Change password</a>
            </div>
        </form>
    </article>

    <article class="card">
        <h2>Read-only account identity</h2>
        <div class="gov-info-list">
            <div class="gov-info-row"><div class="label">Username</div><div class="value"><code><?= opsui_h($username) ?></code></div></div>
            <div class="gov-info-row"><div class="label">Role</div><div class="value"><?= opsui_badge(strtoupper($role), $isAdmin ? 'warn' : 'neutral') ?></div></div>
            <div class="gov-info-row"><div class="label">Status</div><div class="value"><?= opsui_badge($isActive ? 'ACTIVE' : 'INACTIVE', $isActive ? 'good' : 'bad') ?></div></div>
            <div class="gov-info-row"><div class="label">Last login</div><div class="value"><?= opsui_h((string)($dbUser['last_login_at'] ?? '')) ?></div></div>
        </div>
        <div class="gov-danger-note" style="margin-top:14px;">
            Username, role, and active status are controlled by admin user management and cannot be changed from this self-service page.
        </div>
    </article>
</section>

<section class="gov-admin-grid">
    <a class="gov-admin-link" href="/ops/profile.php"><strong>Profile</strong><span>Return to your account profile dashboard.</span></a>
    <a class="gov-admin-link" href="/ops/profile-activity.php"><strong>My Activity</strong><span>Review your recent login and audit activity.</span></a>
    <a class="gov-admin-link" href="/ops/pre-ride-email-tool.php"><strong>Production Pre-Ride Tool</strong><span>Open the current live operator tool. This patch does not modify it.</span></a>
</section>
<?php opsui_shell_end(); ?>
