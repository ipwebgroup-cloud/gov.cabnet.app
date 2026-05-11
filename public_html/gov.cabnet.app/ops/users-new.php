<?php
/**
 * gov.cabnet.app — create operator user v1.0
 *
 * Admin-only user creation. Plain PHP + mysqli.
 * Does not call Bolt, EDXEIX, AADE, or queue jobs.
 */

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Robots-Tag: noindex,nofollow', true);

require_once __DIR__ . '/_shell.php';

$user = opsui_current_user();
if (!opsui_is_admin($user)) {
    http_response_code(403);
    opsui_shell_begin([
        'title' => 'Create User',
        'page_title' => 'Νέος χρήστης',
        'active_section' => 'User Area',
        'subtitle' => 'Admin-only operator user creation',
        'breadcrumbs' => 'Αρχική / Χρήστες / Νέος χρήστης',
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
    echo 'User creation bootstrap failed.';
    exit;
}

function opun_csrf(): string
{
    if (empty($_SESSION['ops_user_admin_csrf']) || !is_string($_SESSION['ops_user_admin_csrf'])) {
        $_SESSION['ops_user_admin_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['ops_user_admin_csrf'];
}

function opun_check_csrf(string $token): bool
{
    return isset($_SESSION['ops_user_admin_csrf']) && is_string($_SESSION['ops_user_admin_csrf']) && hash_equals($_SESSION['ops_user_admin_csrf'], $token);
}

function opun_client_ip(): string
{
    foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'] as $key) {
        $value = trim((string)($_SERVER[$key] ?? ''));
        if ($value !== '') { return substr($value, 0, 45); }
    }
    return '';
}

function opun_audit(mysqli $db, int $adminId, string $event, array $meta = []): void
{
    try {
        $ip = opun_client_ip();
        $ua = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500);
        $json = json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $sql = 'INSERT INTO ops_audit_log (user_id, event_type, ip_address, user_agent, meta_json, created_at) VALUES (?, ?, ?, ?, ?, NOW())';
        $stmt = $db->prepare($sql);
        $stmt->bind_param('issss', $adminId, $event, $ip, $ua, $json);
        $stmt->execute();
    } catch (Throwable) {
    }
}

function opun_user_exists(mysqli $db, string $username, string $email): bool
{
    try {
        if ($email !== '') {
            $stmt = $db->prepare('SELECT id FROM ops_users WHERE username = ? OR email = ? LIMIT 1');
            $stmt->bind_param('ss', $username, $email);
        } else {
            $stmt = $db->prepare('SELECT id FROM ops_users WHERE username = ? LIMIT 1');
            $stmt->bind_param('s', $username);
        }
        $stmt->execute();
        return (bool)$stmt->get_result()->fetch_assoc();
    } catch (Throwable) {
        return true;
    }
}

$values = [
    'username' => '',
    'email' => '',
    'display_name' => '',
    'role' => 'operator',
    'is_active' => '1',
];
$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $values['username'] = trim((string)($_POST['username'] ?? ''));
    $values['email'] = trim((string)($_POST['email'] ?? ''));
    $values['display_name'] = trim((string)($_POST['display_name'] ?? ''));
    $values['role'] = trim((string)($_POST['role'] ?? 'operator'));
    $values['is_active'] = isset($_POST['is_active']) ? '1' : '0';
    $password = (string)($_POST['password'] ?? '');
    $confirm = (string)($_POST['password_confirm'] ?? '');

    if (!opun_check_csrf((string)($_POST['csrf'] ?? ''))) {
        $errors[] = 'Security token expired. Please reload and try again.';
    }
    if (!preg_match('/^[A-Za-z0-9_.-]{3,80}$/', $values['username'])) {
        $errors[] = 'Username must be 3–80 characters using letters, numbers, dot, underscore, or hyphen.';
    }
    if ($values['email'] !== '' && !filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Email address is not valid.';
    }
    if ($values['display_name'] === '' || (function_exists('mb_strlen') ? mb_strlen($values['display_name'], 'UTF-8') : strlen($values['display_name'])) > 190) {
        $errors[] = 'Display name is required and must be under 190 characters.';
    }
    if (!in_array($values['role'], ['admin', 'operator', 'viewer'], true)) {
        $errors[] = 'Invalid role selected.';
    }
    if (strlen($password) < 12) {
        $errors[] = 'Password must be at least 12 characters.';
    }
    if ($password !== $confirm) {
        $errors[] = 'Password confirmation does not match.';
    }
    if ($errors === [] && opun_user_exists($db, $values['username'], $values['email'])) {
        $errors[] = 'Username or email already exists.';
    }

    if ($errors === []) {
        try {
            $emailDb = $values['email'] !== '' ? $values['email'] : null;
            $active = (int)$values['is_active'];
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $db->prepare('INSERT INTO ops_users (username, email, display_name, role, password_hash, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())');
            $stmt->bind_param('sssssi', $values['username'], $emailDb, $values['display_name'], $values['role'], $hash, $active);
            $stmt->execute();
            $newId = (int)$db->insert_id;
            opun_audit($db, (int)($user['id'] ?? 0), 'ops_user_created', [
                'target_user_id' => $newId,
                'username' => $values['username'],
                'role' => $values['role'],
                'is_active' => $active,
            ]);
            header('Location: /ops/users-control.php?created=1', true, 302);
            exit;
        } catch (Throwable $e) {
            $errors[] = 'Unable to create user. Check for duplicate username/email or database errors.';
        }
    }
}

$csrf = opun_csrf();

opsui_shell_begin([
    'title' => 'Create User',
    'page_title' => 'Νέος χρήστης',
    'active_section' => 'User Area',
    'subtitle' => 'Admin-only operator user creation',
    'breadcrumbs' => 'Αρχική / Χρήστες / Νέος χρήστης',
    'safe_notice' => 'USER ADMIN ONLY. This page creates local operator login accounts only. It does not affect Bolt, EDXEIX, AADE, bookings, or queue jobs.',
]);
?>
<section class="card hero neutral">
    <h1>Create operator user</h1>
    <p>Create a local login account for the protected operations area.</p>
    <div>
        <?= opsui_badge('ADMIN ONLY', 'warn') ?>
        <?= opsui_badge('LOCAL OPS ACCOUNT', 'neutral') ?>
        <?= opsui_badge('NO WORKFLOW CHANGES', 'good') ?>
    </div>
</section>

<?php foreach ($errors as $err): ?><?= opsui_flash($err, 'bad') ?><?php endforeach; ?>

<section class="card">
    <h2>New user details</h2>
    <form method="post" action="/ops/users-new.php" autocomplete="off">
        <input type="hidden" name="csrf" value="<?= opsui_h($csrf) ?>">
        <div class="gov-form-grid">
            <div class="gov-form-field">
                <label for="username">Username</label>
                <input id="username" name="username" type="text" required value="<?= opsui_h($values['username']) ?>" pattern="[A-Za-z0-9_.-]{3,80}">
                <div class="gov-form-help">Letters, numbers, dot, underscore, or hyphen. 3–80 characters.</div>
            </div>
            <div class="gov-form-field">
                <label for="email">Email</label>
                <input id="email" name="email" type="email" value="<?= opsui_h($values['email']) ?>">
            </div>
            <div class="gov-form-field">
                <label for="display_name">Display name</label>
                <input id="display_name" name="display_name" type="text" required value="<?= opsui_h($values['display_name']) ?>">
            </div>
            <div class="gov-form-field">
                <label for="role">Role</label>
                <select id="role" name="role">
                    <?php foreach (['operator' => 'Operator', 'viewer' => 'Viewer', 'admin' => 'Admin'] as $value => $label): ?>
                        <option value="<?= opsui_h($value) ?>" <?= $values['role'] === $value ? 'selected' : '' ?>><?= opsui_h($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="gov-form-field">
                <label for="password">Temporary password</label>
                <input id="password" name="password" type="password" required minlength="12" autocomplete="new-password">
                <div class="gov-form-help">Minimum 12 characters. Share securely, never by public chat or Git.</div>
            </div>
            <div class="gov-form-field">
                <label for="password_confirm">Confirm password</label>
                <input id="password_confirm" name="password_confirm" type="password" required minlength="12" autocomplete="new-password">
            </div>
            <div class="gov-form-field full">
                <label class="gov-form-checkline"><input type="checkbox" name="is_active" value="1" <?= $values['is_active'] === '1' ? 'checked' : '' ?>> Active account</label>
            </div>
        </div>
        <div class="gov-panel-actions">
            <button class="btn good" type="submit">Create User</button>
            <a class="btn dark" href="/ops/users-control.php">Cancel</a>
        </div>
    </form>
</section>
<?php opsui_shell_end(); ?>
