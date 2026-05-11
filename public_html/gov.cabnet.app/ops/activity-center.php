<?php
/**
 * gov.cabnet.app — Ops Activity Center v1.0
 *
 * Admin-only read-only dashboard for the login/user activity layer.
 * Does not call Bolt, EDXEIX, or AADE. Does not write data.
 */

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Robots-Tag: noindex, nofollow', true);

require_once __DIR__ . '/_shell.php';

if (!opsui_is_admin()) {
    http_response_code(403);
    opsui_shell_begin([
        'title' => 'Access denied',
        'page_title' => 'Access denied',
        'active_section' => 'User area',
        'breadcrumbs' => 'Αρχική / Χρήστες / Activity center',
        'safe_notice' => 'This admin page is protected and requires administrator role.',
    ]);
    echo '<section class="card hero bad"><h1>Administrator access required</h1><p>This page is available only to admin users.</p></section>';
    opsui_shell_end();
    exit;
}

function ac_bootstrap(): array
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

function ac_table_exists(mysqli $db, string $table): bool
{
    $sql = 'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1';
    $stmt = $db->prepare($sql);
    $stmt->bind_param('s', $table);
    $stmt->execute();
    return (bool)$stmt->get_result()->fetch_assoc();
}

function ac_scalar(mysqli $db, string $sql, array $params = [], string $types = ''): int
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
    $row = $stmt->get_result()->fetch_assoc();
    return (int)array_values($row ?: [0])[0];
}

function ac_rows(mysqli $db, string $sql, array $params = [], string $types = ''): array
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

$db = null;
$error = '';
$tables = ['ops_users' => false, 'ops_login_attempts' => false, 'ops_audit_log' => false];
$metrics = [
    'users_total' => 0,
    'users_active' => 0,
    'admins_active' => 0,
    'failed_24h' => 0,
    'success_24h' => 0,
    'audit_24h' => 0,
];
$recentAttempts = [];
$recentAudit = [];

try {
    $ctx = ac_bootstrap();
    $db = $ctx['db']->connection();
    foreach (array_keys($tables) as $table) {
        $tables[$table] = ac_table_exists($db, $table);
    }

    if ($tables['ops_users']) {
        $metrics['users_total'] = ac_scalar($db, 'SELECT COUNT(*) FROM ops_users');
        $metrics['users_active'] = ac_scalar($db, 'SELECT COUNT(*) FROM ops_users WHERE is_active = 1');
        $metrics['admins_active'] = ac_scalar($db, "SELECT COUNT(*) FROM ops_users WHERE is_active = 1 AND role = 'admin'");
    }

    if ($tables['ops_login_attempts']) {
        $metrics['failed_24h'] = ac_scalar($db, 'SELECT COUNT(*) FROM ops_login_attempts WHERE success = 0 AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)');
        $metrics['success_24h'] = ac_scalar($db, 'SELECT COUNT(*) FROM ops_login_attempts WHERE success = 1 AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)');
        $recentAttempts = ac_rows($db, "
            SELECT la.id, la.user_id, la.login_name, la.success, la.reason, la.ip_address, la.user_agent, la.created_at,
                   u.username, u.email, u.display_name, u.role
            FROM ops_login_attempts la
            LEFT JOIN ops_users u ON u.id = la.user_id
            ORDER BY la.created_at DESC, la.id DESC
            LIMIT 12
        ");
    }

    if ($tables['ops_audit_log']) {
        $metrics['audit_24h'] = ac_scalar($db, 'SELECT COUNT(*) FROM ops_audit_log WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)');
        $recentAudit = ac_rows($db, "
            SELECT a.id, a.user_id, a.event_type, a.ip_address, a.user_agent, a.created_at,
                   u.username, u.email, u.display_name, u.role
            FROM ops_audit_log a
            LEFT JOIN ops_users u ON u.id = a.user_id
            ORDER BY a.created_at DESC, a.id DESC
            LIMIT 12
        ");
    }
} catch (Throwable $e) {
    $error = $e->getMessage();
}

opsui_shell_begin([
    'title' => 'Activity Center',
    'page_title' => 'Ops activity center',
    'active_section' => 'User area',
    'breadcrumbs' => 'Αρχική / Χρήστες / Activity center',
    'safe_notice' => 'Read-only admin dashboard for users, authentication activity, and operator audit events. This page does not call Bolt, EDXEIX, or AADE, and does not write data.',
]);
?>
<section class="card hero neutral">
    <h1>Activity center</h1>
    <p>Single read-only overview for users, login attempts, and operator audit events.</p>
    <div><?= opsui_badge('READ ONLY', 'good') ?> <?= opsui_badge('ADMIN ONLY', 'warn') ?> <?= opsui_badge('NO WORKFLOW ACTIONS', 'good') ?></div>
</section>

<?php if ($error !== ''): ?>
    <?= opsui_flash($error, 'bad') ?>
<?php endif; ?>

<section class="grid" style="margin-bottom:18px">
    <?= opsui_metric($metrics['users_active'] . '/' . $metrics['users_total'], 'Active / total users') ?>
    <?= opsui_metric((string)$metrics['admins_active'], 'Active admins') ?>
    <?= opsui_metric((string)$metrics['success_24h'], 'Successful logins in 24h') ?>
    <?= opsui_metric((string)$metrics['failed_24h'], 'Failed logins in 24h') ?>
</section>

<section class="gov-mini-grid" style="margin-bottom:18px">
    <a class="gov-dashboard-card" href="/ops/users-control.php"><strong>User management</strong><span>Create, edit, activate/deactivate users. No delete actions are available.</span></a>
    <a class="gov-dashboard-card" href="/ops/audit-log.php"><strong>Audit log</strong><span>Review login/logout and user administration events.</span></a>
    <a class="gov-dashboard-card" href="/ops/login-attempts.php"><strong>Login attempts</strong><span>Review recent authentication success/failure visibility.</span></a>
</section>

<section class="two">
    <div class="card">
        <h2>Recent audit events</h2>
        <?php if (!$tables['ops_audit_log']): ?>
            <div class="gov-empty-state">ops_audit_log table is not available.</div>
        <?php elseif ($recentAudit === []): ?>
            <div class="gov-empty-state">No audit events found.</div>
        <?php else: ?>
            <ul class="gov-timeline-list">
                <?php foreach ($recentAudit as $row): ?>
                    <li>
                        <strong><?= opsui_h((string)$row['event_type']) ?></strong>
                        <div class="meta">
                            <?= opsui_h((string)($row['display_name'] ?: $row['username'] ?: 'Unknown user')) ?> ·
                            <?= opsui_h((string)$row['ip_address']) ?> ·
                            <?= opsui_h((string)$row['created_at']) ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2>Recent login attempts</h2>
        <?php if (!$tables['ops_login_attempts']): ?>
            <div class="gov-empty-state">ops_login_attempts table is not available.</div>
        <?php elseif ($recentAttempts === []): ?>
            <div class="gov-empty-state">No login attempts found.</div>
        <?php else: ?>
            <ul class="gov-timeline-list">
                <?php foreach ($recentAttempts as $row): ?>
                    <li>
                        <strong><?= ((int)$row['success'] === 1) ? '<span class="gov-login-result success">SUCCESS</span>' : '<span class="gov-login-result failed">FAILED</span>' ?></strong>
                        <?= opsui_h((string)$row['login_name']) ?>
                        <div class="meta">
                            <?= opsui_h((string)$row['reason']) ?> ·
                            <?= opsui_h((string)$row['ip_address']) ?> ·
                            <?= opsui_h((string)$row['created_at']) ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</section>
<?php opsui_shell_end(); ?>
