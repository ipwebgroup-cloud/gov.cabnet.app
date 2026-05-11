<?php
/**
 * gov.cabnet.app — My profile activity v1.0
 *
 * Read-only page for the logged-in operator's own login/audit history.
 * Does not call Bolt, EDXEIX, or AADE. Does not write data.
 */

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Robots-Tag: noindex, nofollow', true);

require_once __DIR__ . '/_shell.php';

function pa_bootstrap(): array
{
    $bootstrap = dirname(__DIR__, 3) . '/gov.cabnet.app_app/src/bootstrap.php';
    if (!is_file($bootstrap)) {
        throw new RuntimeException('Private app bootstrap not found.');
    }
    $ctx = require $bootstrap;
    if (!is_array($ctx) || !isset($ctx['db']) || !method_exists($ctx['db'], 'connection')) {
        throw new RuntimeException('Invalid private app context.');
    }
    return $ctx;
}

function pa_table_exists(mysqli $db, string $table): bool
{
    $sql = 'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1';
    $stmt = $db->prepare($sql);
    $stmt->bind_param('s', $table);
    $stmt->execute();
    return (bool)$stmt->get_result()->fetch_assoc();
}

function pa_rows(mysqli $db, string $sql, array $params = [], string $types = ''): array
{
    $stmt = $db->prepare($sql);
    if ($params !== []) {
        if ($types === '') {
            foreach ($params as $param) {
                $types .= is_int($param) ? 'i' : 's';
            }
        }
        $bind = [$types];
        foreach ($params as $i => $param) {
            $bind[] = &$params[$i];
        }
        call_user_func_array([$stmt, 'bind_param'], $bind);
    }
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

$user = opsui_current_user();
$userId = (int)($user['id'] ?? 0);
$username = (string)($user['username'] ?? '');
$email = (string)($user['email'] ?? '');
$error = '';
$tables = ['ops_login_attempts' => false, 'ops_audit_log' => false];
$attempts = [];
$audit = [];

try {
    if ($userId <= 0) {
        throw new RuntimeException('Current user session is missing a valid user ID.');
    }
    $ctx = pa_bootstrap();
    $db = $ctx['db']->connection();
    foreach (array_keys($tables) as $table) {
        $tables[$table] = pa_table_exists($db, $table);
    }

    if ($tables['ops_login_attempts']) {
        $attempts = pa_rows($db, "
            SELECT id, user_id, login_name, success, reason, ip_address, user_agent, created_at
            FROM ops_login_attempts
            WHERE user_id = ? OR login_name = ? OR login_name = ?
            ORDER BY created_at DESC, id DESC
            LIMIT 50
        ", [$userId, $username, $email], 'iss');
    }

    if ($tables['ops_audit_log']) {
        $audit = pa_rows($db, "
            SELECT id, user_id, event_type, ip_address, user_agent, created_at
            FROM ops_audit_log
            WHERE user_id = ?
            ORDER BY created_at DESC, id DESC
            LIMIT 50
        ", [$userId], 'i');
    }
} catch (Throwable $e) {
    $error = $e->getMessage();
}

opsui_shell_begin([
    'title' => 'My Activity',
    'page_title' => 'My activity',
    'active_section' => 'User area',
    'breadcrumbs' => 'Αρχική / Profile / My activity',
    'safe_notice' => 'Read-only profile activity for the logged-in operator. This page does not call Bolt, EDXEIX, or AADE, and does not write data.',
]);
?>
<section class="card hero neutral">
    <h1>My account activity</h1>
    <p>Review your recent login attempts and account audit events.</p>
    <div><?= opsui_badge('READ ONLY', 'good') ?> <?= opsui_badge('PROFILE AREA', 'neutral') ?> <?= opsui_badge('NO WORKFLOW ACTIONS', 'good') ?></div>
</section>

<?php if ($error !== ''): ?>
    <?= opsui_flash($error, 'bad') ?>
<?php endif; ?>

<section class="gov-mini-grid two-col" style="margin-bottom:18px">
    <a class="gov-dashboard-card" href="/ops/profile.php"><strong>Profile</strong><span>View current account and session details.</span></a>
    <a class="gov-dashboard-card" href="/ops/profile-password.php"><strong>Change password</strong><span>Update your own operator password securely.</span></a>
</section>

<section class="two">
    <div class="card">
        <h2>My login attempts</h2>
        <?php if (!$tables['ops_login_attempts']): ?>
            <div class="gov-empty-state">ops_login_attempts table is not available.</div>
        <?php elseif ($attempts === []): ?>
            <div class="gov-empty-state">No login attempts found for your account.</div>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>When</th><th>Result</th><th>Login</th><th>IP</th><th>Reason</th></tr></thead>
                    <tbody>
                    <?php foreach ($attempts as $row): ?>
                        <tr>
                            <td class="gov-nowrap"><?= opsui_h((string)$row['created_at']) ?></td>
                            <td><?= ((int)$row['success'] === 1) ? '<span class="gov-login-result success">SUCCESS</span>' : '<span class="gov-login-result failed">FAILED</span>' ?></td>
                            <td><?= opsui_h((string)$row['login_name']) ?></td>
                            <td><?= opsui_h((string)$row['ip_address']) ?></td>
                            <td><?= opsui_h((string)$row['reason']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2>My audit events</h2>
        <?php if (!$tables['ops_audit_log']): ?>
            <div class="gov-empty-state">ops_audit_log table is not available.</div>
        <?php elseif ($audit === []): ?>
            <div class="gov-empty-state">No audit events found for your account.</div>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>When</th><th>Event</th><th>IP</th><th>User agent</th></tr></thead>
                    <tbody>
                    <?php foreach ($audit as $row): ?>
                        <tr>
                            <td class="gov-nowrap"><?= opsui_h((string)$row['created_at']) ?></td>
                            <td><span class="gov-event-type"><?= opsui_h((string)$row['event_type']) ?></span></td>
                            <td><?= opsui_h((string)$row['ip_address']) ?></td>
                            <td class="gov-truncate-cell"><?= opsui_h((string)$row['user_agent']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php opsui_shell_end(); ?>
