<?php
/**
 * gov.cabnet.app — operator password change v1.0
 *
 * Allows a logged-in operator to change their own password.
 * Does not call Bolt, EDXEIX, AADE, or any trip workflow.
 */

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Robots-Tag: noindex,nofollow', true);

require_once __DIR__ . '/_shell.php';

$bootstrap = dirname(__DIR__, 3) . '/gov.cabnet.app_app/src/bootstrap.php';
if (!is_file($bootstrap)) {
    http_response_code(500);
    echo 'Private app bootstrap not found.';
    exit;
}

try {
    $ctx = require $bootstrap;
    $db = $ctx['db']->connection();
    $auth = new Bridge\Auth\OpsAuth($db, [
        'session_name' => (string)$ctx['config']->get('ops_auth.session_name', 'gov_cabnet_ops_session'),
        'login_path' => (string)$ctx['config']->get('ops_auth.login_path', '/ops/login.php'),
        'after_login_path' => (string)$ctx['config']->get('ops_auth.after_login_path', '/ops/home.php'),
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Password page bootstrap failed.';
    exit;
}

$user = opsui_current_user();
$userId = (int)($user['id'] ?? 0);
$message = '';
$messageType = 'neutral';

function opp_client_ip(): string
{
    foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'] as $key) {
        $value = trim((string)($_SERVER[$key] ?? ''));
        if ($value !== '') { return substr($value, 0, 45); }
    }
    return '';
}

function opp_audit(mysqli $db, int $userId, string $event, array $meta = []): void
{
    try {
        $ip = opp_client_ip();
        $ua = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500);
        $metaJson = $meta === [] ? null : json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $stmt = $db->prepare('INSERT INTO ops_audit_log (user_id, event_type, ip_address, user_agent, meta_json, created_at) VALUES (?, ?, ?, ?, ?, NOW())');
        $stmt->bind_param('issss', $userId, $event, $ip, $ua, $metaJson);
        $stmt->execute();
    } catch (Throwable) {
        // Audit failure must not break password changes during staged rollout.
    }
}

function opp_fetch_user(mysqli $db, int $userId): ?array
{
    try {
        $stmt = $db->prepare('SELECT id, username, email, display_name, role, password_hash, is_active FROM ops_users WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        return is_array($row) ? $row : null;
    } catch (Throwable) {
        return null;
    }
}

$currentUserRow = $userId > 0 ? opp_fetch_user($db, $userId) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentPassword = (string)($_POST['current_password'] ?? '');
    $newPassword = (string)($_POST['new_password'] ?? '');
    $confirmPassword = (string)($_POST['confirm_password'] ?? '');

    if (!$auth->validateCsrf((string)($_POST['csrf'] ?? ''))) {
        $message = 'Security token expired. Please try again.';
        $messageType = 'bad';
    } elseif (!$currentUserRow || (int)($currentUserRow['is_active'] ?? 0) !== 1) {
        $message = 'Your user account could not be loaded or is inactive.';
        $messageType = 'bad';
    } elseif (!password_verify($currentPassword, (string)$currentUserRow['password_hash'])) {
        opp_audit($db, $userId, 'password_change_failed', ['reason' => 'current_password_invalid']);
        $message = 'Current password is incorrect.';
        $messageType = 'bad';
    } elseif (strlen($newPassword) < 10) {
        $message = 'New password must be at least 10 characters.';
        $messageType = 'bad';
    } elseif ($newPassword !== $confirmPassword) {
        $message = 'New password and confirmation do not match.';
        $messageType = 'bad';
    } elseif ($newPassword === $currentPassword) {
        $message = 'New password must be different from the current password.';
        $messageType = 'bad';
    } else {
        try {
            $hash = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $db->prepare('UPDATE ops_users SET password_hash = ?, updated_at = NOW() WHERE id = ? LIMIT 1');
            $stmt->bind_param('si', $hash, $userId);
            $stmt->execute();
            opp_audit($db, $userId, 'password_changed');
            $message = 'Password updated successfully.';
            $messageType = 'good';
            $currentUserRow = opp_fetch_user($db, $userId);
        } catch (Throwable $e) {
            opp_audit($db, $userId, 'password_change_failed', ['reason' => 'db_update_failed']);
            $message = 'Password could not be updated. Please try again.';
            $messageType = 'bad';
        }
    }
}

$csrf = $auth->csrfToken();

opsui_shell_begin([
    'title' => 'Change Password',
    'page_title' => 'Αλλαγή κωδικού',
    'active_section' => 'User Profile',
    'subtitle' => 'Update your own operator password',
    'breadcrumbs' => 'Αρχική / Χρήστες / Αλλαγή κωδικού',
    'safe_notice' => 'This page only changes the current logged-in operator password. It does not call Bolt, EDXEIX, AADE, or any trip workflow.',
]);
?>
<?php if ($message !== ''): ?>
    <?= opsui_flash($message, $messageType) ?>
<?php endif; ?>

<section class="two">
    <article class="card">
        <h2>Change password</h2>
        <form method="post" action="/ops/profile-password.php" autocomplete="off">
            <input type="hidden" name="csrf" value="<?= opsui_h($csrf) ?>">
            <div class="gov-form-grid">
                <div class="gov-form-field full">
                    <label for="current_password">Current password</label>
                    <input id="current_password" name="current_password" type="password" required>
                </div>
                <div class="gov-form-field full">
                    <label for="new_password">New password</label>
                    <input id="new_password" name="new_password" type="password" required minlength="10">
                    <div class="gov-form-help">Use at least 10 characters. Do not reuse shared or emailed passwords.</div>
                </div>
                <div class="gov-form-field full">
                    <label for="confirm_password">Confirm new password</label>
                    <input id="confirm_password" name="confirm_password" type="password" required minlength="10">
                </div>
            </div>
            <div class="gov-panel-actions">
                <button class="btn good" type="submit">Update password</button>
                <a class="btn dark" href="/ops/profile.php">Back to profile</a>
            </div>
        </form>
    </article>

    <article class="card">
        <h2>Password safety</h2>
        <ul class="list">
            <li>The current password is required before any change.</li>
            <li>Successful and failed password-change attempts are audit logged.</li>
            <li>Changing this password does not affect EDXEIX, AADE, Bolt, or browser helper credentials.</li>
        </ul>
        <div class="gov-danger-note" style="margin-top:14px;">
            Never paste real passwords into ChatGPT, GitHub, email, screenshots, or support messages.
        </div>
    </article>
</section>
<?php opsui_shell_end(); ?>
