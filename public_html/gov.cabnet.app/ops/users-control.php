<?php
/**
 * gov.cabnet.app — users control v1.0
 *
 * Admin-only, read-only operator account overview.
 * No user create/edit/delete in this phase.
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
        'title' => 'Users Control',
        'page_title' => 'Διαχείριση χρηστών',
        'active_section' => 'User Area',
        'subtitle' => 'Admin-only operator user overview',
        'breadcrumbs' => 'Αρχική / Χρήστες / Users Control',
        'safe_notice' => 'This admin route is read-only and does not change users or workflow data.',
    ]);
    echo '<section class="card"><h2>Access denied</h2><p class="badline"><strong>Admin role required.</strong></p><p>This page is available only to operator accounts with the admin role.</p><div class="actions"><a class="btn" href="/ops/profile.php">Back to Profile</a><a class="btn dark" href="/ops/home.php">Ops Home</a></div></section>';
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
    echo 'Users page bootstrap failed.';
    exit;
}

function opu_fetch_all(mysqli $db, string $sql): array
{
    try {
        $res = $db->query($sql);
        return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    } catch (Throwable) {
        return [];
    }
}

$users = opu_fetch_all($db, "
    SELECT id, username, email, display_name, role, is_active, last_login_at, last_login_ip, created_at, updated_at
    FROM ops_users
    ORDER BY is_active DESC, role ASC, username ASC
");

$recentAttempts = opu_fetch_all($db, "
    SELECT id, user_id, login_name, success, reason, ip_address, created_at
    FROM ops_login_attempts
    ORDER BY created_at DESC
    LIMIT 12
");

$totalUsers = count($users);
$activeUsers = 0;
$adminUsers = 0;
foreach ($users as $row) {
    if ((int)($row['is_active'] ?? 0) === 1) { $activeUsers++; }
    if ((string)($row['role'] ?? '') === 'admin') { $adminUsers++; }
}

opsui_shell_begin([
    'title' => 'Users Control',
    'page_title' => 'Διαχείριση χρηστών',
    'active_section' => 'User Area',
    'subtitle' => 'Admin-only operator user overview',
    'breadcrumbs' => 'Αρχική / Χρήστες / Users Control',
    'safe_notice' => 'READ-ONLY USERS CONTROL. This page lists operator accounts and recent login attempts only. It does not create, edit, disable, or delete users.',
]);
?>
<section class="card hero neutral">
    <h1>Users Control</h1>
    <p>Read-only account visibility for the operations login system.</p>
    <div>
        <?= opsui_badge('ADMIN ONLY', 'warn') ?>
        <?= opsui_badge('READ ONLY', 'good') ?>
        <?= opsui_badge('NO USER WRITES', 'good') ?>
    </div>
    <div class="grid" style="margin-top:14px">
        <?= opsui_metric((string)$totalUsers, 'Total users') ?>
        <?= opsui_metric((string)$activeUsers, 'Active users') ?>
        <?= opsui_metric((string)$adminUsers, 'Admin users') ?>
        <?= opsui_metric((string)count($recentAttempts), 'Recent attempts shown') ?>
    </div>
</section>

<section class="card">
    <h2>Operator accounts</h2>
    <div class="table-wrap">
        <table class="gov-user-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>User</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Last login</th>
                    <th>Last IP</th>
                    <th>Created</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($users === []): ?>
                    <tr><td colspan="8">No users found.</td></tr>
                <?php endif; ?>
                <?php foreach ($users as $row): ?>
                    <?php $active = (int)($row['is_active'] ?? 0) === 1; ?>
                    <tr>
                        <td><?= opsui_h($row['id'] ?? '') ?></td>
                        <td><strong><?= opsui_h($row['display_name'] ?: $row['username']) ?></strong><br><span class="small"><?= opsui_h($row['username'] ?? '') ?></span></td>
                        <td><?= opsui_h($row['email'] ?: '—') ?></td>
                        <td><?= opsui_badge((string)($row['role'] ?? 'operator'), (string)($row['role'] ?? '') === 'admin' ? 'warn' : 'neutral') ?></td>
                        <td><span class="gov-user-status <?= $active ? 'active' : 'inactive' ?>"><?= $active ? 'ACTIVE' : 'INACTIVE' ?></span></td>
                        <td><?= opsui_h($row['last_login_at'] ?: '—') ?></td>
                        <td><code><?= opsui_h($row['last_login_ip'] ?: '—') ?></code></td>
                        <td><?= opsui_h($row['created_at'] ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="two">
    <article class="card">
        <h2>Recent login attempts</h2>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th>Time</th><th>Login</th><th>Result</th><th>Reason</th><th>IP</th></tr>
                </thead>
                <tbody>
                    <?php if ($recentAttempts === []): ?>
                        <tr><td colspan="5">No recent attempts found.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($recentAttempts as $attempt): ?>
                        <?php $ok = (int)($attempt['success'] ?? 0) === 1; ?>
                        <tr>
                            <td><?= opsui_h($attempt['created_at'] ?? '') ?></td>
                            <td><?= opsui_h($attempt['login_name'] ?? '') ?></td>
                            <td><?= opsui_badge($ok ? 'OK' : 'FAILED', $ok ? 'good' : 'bad') ?></td>
                            <td><?= opsui_h($attempt['reason'] ?? '') ?></td>
                            <td><code><?= opsui_h($attempt['ip_address'] ?? '') ?></code></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </article>

    <article class="card">
        <h2>Current user policy</h2>
        <div class="gov-alert-note">
            This page is intentionally read-only. For now, create new operators with the CLI helper so production access changes remain deliberate and easy to audit.
        </div>
        <h3>CLI user creation</h3>
        <p>Use this server command when a new staff account is needed:</p>
        <p><code>php /home/cabnet/gov.cabnet.app_app/cli/create_ops_user.php --username=name --email=email@example.com --display-name="Name" --role=operator</code></p>
        <div class="actions">
            <a class="btn" href="/ops/profile.php">Back to Profile</a>
            <a class="btn dark" href="/ops/home.php">Ops Home</a>
        </div>
    </article>
</section>
<?php opsui_shell_end(); ?>
