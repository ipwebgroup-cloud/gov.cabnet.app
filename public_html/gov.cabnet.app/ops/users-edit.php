<?php
/**
 * gov.cabnet.app — edit operator user v1.0
 *
 * Admin-only user metadata and password reset page.
 * No delete action is provided.
 */

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Robots-Tag: noindex,nofollow', true);

require_once __DIR__ . '/_shell.php';

$currentUser = opsui_current_user();
if (!opsui_is_admin($currentUser)) {
    http_response_code(403);
    opsui_shell_begin([
        'title' => 'Edit User',
        'page_title' => 'Επεξεργασία χρήστη',
        'active_section' => 'User Area',
        'subtitle' => 'Admin-only operator account management',
        'breadcrumbs' => 'Αρχική / Χρήστες / Επεξεργασία',
        'safe_notice' => 'Admin role required. This route does not affect Bolt, EDXEIX, AADE, or queue jobs.',
    ]);
    echo '<section class="card"><h2>Access denied</h2><p class="badline"><strong>Admin role required.</strong></p><div class="actions"><a class="btn" href="/ops/profile.php">Back to Profile</a></div></section>';
    opsui_shell_end();
    exit;
}

$bootstrap = dirname(__DIR__, 3) . '/gov.cabnet.app_app/src/bootstrap.php';
if (!is_file($bootstrap)) {
    http_response_code(500);
    echo 'Private app bootstrap not found.';
    exit;
}

try {
    $ctx = require $bootstrap;
    $db = $ctx['db']->connection();
} catch (Throwable $e) {
    http_response_code(500);
    echo 'User edit bootstrap failed.';
    exit;
}

function opue_csrf(): string
{
    if (empty($_SESSION['ops_user_admin_csrf']) || !is_string($_SESSION['ops_user_admin_csrf'])) {
        $_SESSION['ops_user_admin_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['ops_user_admin_csrf'];
}

function opue_check_csrf(string $token): bool
{
    return isset($_SESSION['ops_user_admin_csrf']) && is_string($_SESSION['ops_user_admin_csrf']) && hash_equals($_SESSION['ops_user_admin_csrf'], $token);
}

function opue_client_ip(): string
{
    foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'] as $key) {
        $value = trim((string)($_SERVER[$key] ?? ''));
        if ($value !== '') { return substr($value, 0, 45); }
    }
    return '';
}

function opue_audit(mysqli $db, int $adminId, string $event, array $meta = []): void
{
    try {
        $ip = opue_client_ip();
        $ua = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500);
        $json = json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $sql = 'INSERT INTO ops_audit_log (user_id, event_type, ip_address, user_agent, meta_json, created_at) VALUES (?, ?, ?, ?, ?, NOW())';
        $stmt = $db->prepare($sql);
        $stmt->bind_param('issss', $adminId, $event, $ip, $ua, $json);
        $stmt->execute();
    } catch (Throwable) {
    }
}

function opue_fetch_user(mysqli $db, int $id): ?array
{
    try {
        $stmt = $db->prepare('SELECT id, username, email, display_name, role, is_active, last_login_at, last_login_ip, created_at, updated_at FROM ops_users WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        return is_array($row) ? $row : null;
    } catch (Throwable) {
        return null;
    }
}

function opue_count_active_admins(mysqli $db): int
{
    try {
        $res = $db->query("SELECT COUNT(*) AS c FROM ops_users WHERE role = 'admin' AND is_active = 1");
        $row = $res ? $res->fetch_assoc() : null;
        return (int)($row['c'] ?? 0);
    } catch (Throwable) {
        return 0;
    }
}

function opue_duplicate_exists(mysqli $db, int $id, string $username, string $email): bool
{
    try {
        if ($email !== '') {
            $stmt = $db->prepare('SELECT id FROM ops_users WHERE id <> ? AND (username = ? OR email = ?) LIMIT 1');
            $stmt->bind_param('iss', $id, $username, $email);
        } else {
            $stmt = $db->prepare('SELECT id FROM ops_users WHERE id <> ? AND username = ? LIMIT 1');
            $stmt->bind_param('is', $id, $username);
        }
        $stmt->execute();
        return (bool)$stmt->get_result()->fetch_assoc();
    } catch (Throwable) {
        return true;
    }
}

$id = max(0, (int)($_GET['id'] ?? $_POST['id'] ?? 0));
$target = $id > 0 ? opue_fetch_user($db, $id) : null;
if (!$target) {
    http_response_code(404);
    opsui_shell_begin([
        'title' => 'Edit User',
        'page_title' => 'Επεξεργασία χρήστη',
        'active_section' => 'User Area',
        'subtitle' => 'Admin-only operator account management',
        'breadcrumbs' => 'Αρχική / Χρήστες / Επεξεργασία',
        'safe_notice' => 'User was not found.',
    ]);
    echo '<section class="card"><h2>User not found</h2><p>The requested user account could not be found.</p><div class="actions"><a class="btn" href="/ops/users-control.php">Back to Users</a></div></section>';
    opsui_shell_end();
    exit;
}

$errors = [];
$success = '';
$csrf = opue_csrf();
$activeAdminCount = opue_count_active_admins($db);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? 'update');
    if (!opue_check_csrf((string)($_POST['csrf'] ?? ''))) {
        $errors[] = 'Security token expired. Please reload and try again.';
    } elseif ($action === 'update') {
        $username = trim((string)($_POST['username'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $displayName = trim((string)($_POST['display_name'] ?? ''));
        $role = trim((string)($_POST['role'] ?? 'operator'));
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if (!preg_match('/^[A-Za-z0-9_.-]{3,80}$/', $username)) {
            $errors[] = 'Username must be 3–80 characters using letters, numbers, dot, underscore, or hyphen.';
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email address is not valid.';
        }
        if ($displayName === '' || (function_exists('mb_strlen') ? mb_strlen($displayName, 'UTF-8') : strlen($displayName)) > 190) {
            $errors[] = 'Display name is required and must be under 190 characters.';
        }
        if (!in_array($role, ['admin', 'operator', 'viewer'], true)) {
            $errors[] = 'Invalid role selected.';
        }
        if ((int)$target['id'] === (int)($currentUser['id'] ?? 0) && ($role !== 'admin' || $isActive !== 1)) {
            $errors[] = 'You cannot demote or deactivate your own current admin account from the web interface.';
        }
        if ((string)$target['role'] === 'admin' && (int)$target['is_active'] === 1 && ($role !== 'admin' || $isActive !== 1) && $activeAdminCount <= 1) {
            $errors[] = 'At least one active admin account must remain.';
        }
        if ($errors === [] && opue_duplicate_exists($db, (int)$target['id'], $username, $email)) {
            $errors[] = 'Username or email already exists on another account.';
        }

        if ($errors === []) {
            try {
                $emailDb = $email !== '' ? $email : null;
                $stmt = $db->prepare('UPDATE ops_users SET username = ?, email = ?, display_name = ?, role = ?, is_active = ?, updated_at = NOW() WHERE id = ? LIMIT 1');
                $stmt->bind_param('ssssii', $username, $emailDb, $displayName, $role, $isActive, $id);
                $stmt->execute();
                opue_audit($db, (int)($currentUser['id'] ?? 0), 'ops_user_updated', [
                    'target_user_id' => $id,
                    'username' => $username,
                    'role' => $role,
                    'is_active' => $isActive,
                ]);
                $success = 'User details updated.';
                $target = opue_fetch_user($db, $id) ?: $target;
            } catch (Throwable $e) {
                $errors[] = 'Unable to update user. Check for duplicate username/email or database errors.';
            }
        }
    } elseif ($action === 'reset_password') {
        $newPassword = (string)($_POST['new_password'] ?? '');
        $confirm = (string)($_POST['new_password_confirm'] ?? '');
        if (strlen($newPassword) < 12) {
            $errors[] = 'New password must be at least 12 characters.';
        }
        if ($newPassword !== $confirm) {
            $errors[] = 'Password confirmation does not match.';
        }
        if ($errors === []) {
            try {
                $hash = password_hash($newPassword, PASSWORD_DEFAULT);
                $stmt = $db->prepare('UPDATE ops_users SET password_hash = ?, updated_at = NOW() WHERE id = ? LIMIT 1');
                $stmt->bind_param('si', $hash, $id);
                $stmt->execute();
                opue_audit($db, (int)($currentUser['id'] ?? 0), 'ops_user_password_reset', [
                    'target_user_id' => $id,
                    'username' => (string)$target['username'],
                ]);
                $success = 'Password reset successfully.';
                $target = opue_fetch_user($db, $id) ?: $target;
            } catch (Throwable $e) {
                $errors[] = 'Unable to reset password.';
            }
        }
    }
}

opsui_shell_begin([
    'title' => 'Edit User',
    'page_title' => 'Επεξεργασία χρήστη',
    'active_section' => 'User Area',
    'subtitle' => 'Admin-only operator account management',
    'breadcrumbs' => 'Αρχική / Χρήστες / Επεξεργασία',
    'safe_notice' => 'USER ADMIN ONLY. This page updates local operator login accounts only. It does not affect Bolt, EDXEIX, AADE, bookings, or queue jobs.',
]);
?>
<section class="card hero neutral">
    <h1>Edit operator user</h1>
    <p>Update login account metadata or reset a password. Delete actions are intentionally not provided.</p>
    <div>
        <?= opsui_badge('ADMIN ONLY', 'warn') ?>
        <?= opsui_badge('NO DELETE ACTION', 'good') ?>
        <?= opsui_badge('AUDITED CHANGES', 'neutral') ?>
    </div>
</section>

<?php if ($success !== ''): ?><?= opsui_flash($success, 'good') ?><?php endif; ?>
<?php foreach ($errors as $err): ?><?= opsui_flash($err, 'bad') ?><?php endforeach; ?>

<section class="two">
    <article class="card">
        <h2>User details</h2>
        <form method="post" action="/ops/users-edit.php" autocomplete="off">
            <input type="hidden" name="csrf" value="<?= opsui_h($csrf) ?>">
            <input type="hidden" name="id" value="<?= opsui_h((string)$id) ?>">
            <input type="hidden" name="action" value="update">
            <div class="gov-form-grid">
                <div class="gov-form-field">
                    <label for="username">Username</label>
                    <input id="username" name="username" type="text" required value="<?= opsui_h($target['username'] ?? '') ?>" pattern="[A-Za-z0-9_.-]{3,80}">
                </div>
                <div class="gov-form-field">
                    <label for="email">Email</label>
                    <input id="email" name="email" type="email" value="<?= opsui_h($target['email'] ?? '') ?>">
                </div>
                <div class="gov-form-field">
                    <label for="display_name">Display name</label>
                    <input id="display_name" name="display_name" type="text" required value="<?= opsui_h($target['display_name'] ?? '') ?>">
                </div>
                <div class="gov-form-field">
                    <label for="role">Role</label>
                    <select id="role" name="role">
                        <?php foreach (['operator' => 'Operator', 'viewer' => 'Viewer', 'admin' => 'Admin'] as $value => $label): ?>
                            <option value="<?= opsui_h($value) ?>" <?= (string)($target['role'] ?? '') === $value ? 'selected' : '' ?>><?= opsui_h($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="gov-form-field full">
                    <label class="gov-form-checkline"><input type="checkbox" name="is_active" value="1" <?= (int)($target['is_active'] ?? 0) === 1 ? 'checked' : '' ?>> Active account</label>
                </div>
            </div>
            <div class="gov-panel-actions">
                <button class="btn good" type="submit">Save Details</button>
                <a class="btn dark" href="/ops/users-control.php">Back to Users</a>
            </div>
        </form>
    </article>

    <article class="card">
        <h2>Password reset</h2>
        <div class="gov-admin-warning">Use only when the operator cannot change their own password. Share the temporary password securely.</div>
        <form method="post" action="/ops/users-edit.php" autocomplete="off">
            <input type="hidden" name="csrf" value="<?= opsui_h($csrf) ?>">
            <input type="hidden" name="id" value="<?= opsui_h((string)$id) ?>">
            <input type="hidden" name="action" value="reset_password">
            <div class="gov-form-grid">
                <div class="gov-form-field">
                    <label for="new_password">New temporary password</label>
                    <input id="new_password" name="new_password" type="password" minlength="12" autocomplete="new-password">
                </div>
                <div class="gov-form-field">
                    <label for="new_password_confirm">Confirm password</label>
                    <input id="new_password_confirm" name="new_password_confirm" type="password" minlength="12" autocomplete="new-password">
                </div>
            </div>
            <div class="gov-panel-actions">
                <button class="btn warn" type="submit">Reset Password</button>
            </div>
        </form>
        <div class="gov-muted-box" style="margin-top:14px">
            Created: <code><?= opsui_h($target['created_at'] ?? '') ?></code><br>
            Updated: <code><?= opsui_h($target['updated_at'] ?? '') ?></code><br>
            Last login: <code><?= opsui_h($target['last_login_at'] ?: '—') ?></code>
        </div>
    </article>
</section>
<?php opsui_shell_end(); ?>
