<?php
/**
 * gov.cabnet.app — Ops Login Attempts
 *
 * Admin-only, read-only visibility into ops_login_attempts.
 * No Bolt calls, no EDXEIX calls, no database writes.
 */

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Robots-Tag: noindex,nofollow', true);

require_once __DIR__ . '/_shell.php';

function ola_bootstrap(): array
{
    $bootstrap = dirname(__DIR__, 3) . '/gov.cabnet.app_app/src/bootstrap.php';
    if (!is_file($bootstrap)) {
        throw new RuntimeException('Private app bootstrap not found.');
    }
    $ctx = require $bootstrap;
    if (!is_array($ctx) || !isset($ctx['db']) || !method_exists($ctx['db'], 'connection')) {
        throw new RuntimeException('Private app DB context is unavailable.');
    }
    return $ctx;
}

function ola_int_param(string $key, int $default, int $min, int $max): int
{
    $value = filter_input(INPUT_GET, $key, FILTER_VALIDATE_INT);
    if (!is_int($value)) {
        $value = $default;
    }
    return max($min, min($max, $value));
}

function ola_query(mysqli $db, string $sql, array $params = [], string $types = ''): array
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
    $result = $stmt->get_result();
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

function ola_one(mysqli $db, string $sql, array $params = [], string $types = ''): array
{
    $rows = ola_query($db, $sql, $params, $types);
    return $rows[0] ?? [];
}

function ola_has_table(mysqli $db, string $table): bool
{
    $row = ola_one($db, 'SHOW TABLES LIKE ?', [$table], 's');
    return $row !== [];
}

$user = opsui_current_user();
if (!opsui_is_admin($user)) {
    http_response_code(403);
    opsui_shell_begin([
        'title' => 'Access denied',
        'page_title' => 'Access denied',
        'active_section' => 'User area',
        'breadcrumbs' => 'Αρχική / Χρήστες / Access denied',
        'safe_notice' => 'This page is admin-only and does not modify workflow behavior.',
    ]);
    echo '<section class="card"><h2>Admin access required</h2><p class="badline">Only admin users can view login attempts.</p></section>';
    opsui_shell_end();
    exit;
}

$error = '';
$rows = [];
$summary = [
    'total' => 0,
    'success_24h' => 0,
    'failed_24h' => 0,
    'unique_ips_24h' => 0,
];
$limit = ola_int_param('limit', 100, 10, 500);
$q = trim((string)($_GET['q'] ?? ''));
$outcome = strtolower(trim((string)($_GET['outcome'] ?? 'all')));
if (!in_array($outcome, ['all', 'success', 'failed'], true)) {
    $outcome = 'all';
}

try {
    $ctx = ola_bootstrap();
    /** @var mysqli $db */
    $db = $ctx['db']->connection();

    if (!ola_has_table($db, 'ops_login_attempts')) {
        throw new RuntimeException('ops_login_attempts table does not exist. Run the ops login SQL migration first.');
    }

    $summary['total'] = (int)(ola_one($db, 'SELECT COUNT(*) AS c FROM ops_login_attempts')['c'] ?? 0);
    $summary['success_24h'] = (int)(ola_one($db, 'SELECT COUNT(*) AS c FROM ops_login_attempts WHERE success = 1 AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)')['c'] ?? 0);
    $summary['failed_24h'] = (int)(ola_one($db, 'SELECT COUNT(*) AS c FROM ops_login_attempts WHERE success = 0 AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)')['c'] ?? 0);
    $summary['unique_ips_24h'] = (int)(ola_one($db, 'SELECT COUNT(DISTINCT ip_address) AS c FROM ops_login_attempts WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)')['c'] ?? 0);

    $where = [];
    $params = [];
    $types = '';

    if ($q !== '') {
        $like = '%' . $q . '%';
        $where[] = '(l.login_name LIKE ? OR l.reason LIKE ? OR l.ip_address LIKE ? OR u.username LIKE ? OR u.email LIKE ? OR u.display_name LIKE ?)';
        array_push($params, $like, $like, $like, $like, $like, $like);
        $types .= 'ssssss';
    }

    if ($outcome === 'success') {
        $where[] = 'l.success = 1';
    } elseif ($outcome === 'failed') {
        $where[] = 'l.success = 0';
    }

    $sql = "
        SELECT
            l.id,
            l.user_id,
            l.login_name,
            l.success,
            l.reason,
            l.ip_address,
            l.user_agent,
            l.created_at,
            u.username,
            u.display_name,
            u.email,
            u.role
        FROM ops_login_attempts l
        LEFT JOIN ops_users u ON u.id = l.user_id
    ";
    if ($where !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY l.created_at DESC, l.id DESC LIMIT ' . (int)$limit;
    $rows = ola_query($db, $sql, $params, $types);
} catch (Throwable $e) {
    $error = $e->getMessage();
}

opsui_shell_begin([
    'title' => 'Login Attempts',
    'page_title' => 'Ops login attempts',
    'active_section' => 'User area',
    'breadcrumbs' => 'Αρχική / Χρήστες / Login attempts',
    'safe_notice' => 'Read-only admin visibility into authentication attempts. This page does not call Bolt, EDXEIX, or AADE, and does not write data.',
]);
?>

<section class="card hero neutral">
    <h1>Authentication visibility</h1>
    <p>Review recent successful and failed login attempts for the operator console.</p>
    <div>
        <?= opsui_badge('READ ONLY', 'good') ?>
        <?= opsui_badge('ADMIN ONLY', 'warn') ?>
        <?= opsui_badge('NO WORKFLOW ACTIONS', 'good') ?>
    </div>
</section>

<?php if ($error !== ''): ?>
    <?= opsui_flash($error, 'bad') ?>
<?php endif; ?>

<section class="gov-log-summary">
    <?= opsui_metric((string)$summary['total'], 'Total attempts') ?>
    <?= opsui_metric((string)$summary['success_24h'], 'Success in 24h') ?>
    <?= opsui_metric((string)$summary['failed_24h'], 'Failed in 24h') ?>
    <?= opsui_metric((string)$summary['unique_ips_24h'], 'Unique IPs in 24h') ?>
</section>

<section class="card">
    <h2>Filters</h2>
    <form class="gov-filter-bar" method="get" action="/ops/login-attempts.php">
        <div class="gov-form-field wide">
            <label for="q">Search</label>
            <input id="q" name="q" type="text" value="<?= opsui_h($q) ?>" placeholder="username, email, IP, reason">
        </div>
        <div class="gov-form-field">
            <label for="outcome">Outcome</label>
            <select id="outcome" name="outcome">
                <option value="all" <?= $outcome === 'all' ? 'selected' : '' ?>>All</option>
                <option value="success" <?= $outcome === 'success' ? 'selected' : '' ?>>Successful</option>
                <option value="failed" <?= $outcome === 'failed' ? 'selected' : '' ?>>Failed</option>
            </select>
        </div>
        <div class="gov-form-field">
            <label for="limit">Limit</label>
            <select id="limit" name="limit">
                <?php foreach ([50, 100, 200, 500] as $choice): ?>
                    <option value="<?= $choice ?>" <?= $limit === $choice ? 'selected' : '' ?>><?= $choice ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="gov-form-field">
            <label>&nbsp;</label>
            <button class="btn" type="submit">Apply filters</button>
        </div>
        <div class="gov-form-field">
            <label>&nbsp;</label>
            <a class="btn dark" href="/ops/login-attempts.php">Reset</a>
        </div>
    </form>
</section>

<section class="card">
    <h2>Recent login attempts</h2>
    <div class="table-wrap">
        <table class="gov-user-table">
            <thead>
            <tr>
                <th>ID</th>
                <th>Time</th>
                <th>Result</th>
                <th>Login name</th>
                <th>User</th>
                <th>Reason</th>
                <th>IP</th>
                <th>User agent</th>
            </tr>
            </thead>
            <tbody>
            <?php if ($rows === []): ?>
                <tr><td colspan="8">No login attempts found.</td></tr>
            <?php else: ?>
                <?php foreach ($rows as $row): ?>
                    <?php
                    $success = (int)($row['success'] ?? 0) === 1;
                    $actor = trim((string)($row['display_name'] ?? ''));
                    if ($actor === '') { $actor = trim((string)($row['username'] ?? '')); }
                    if ($actor === '') { $actor = 'No matched user'; }
                    ?>
                    <tr>
                        <td class="gov-nowrap">#<?= opsui_h((string)$row['id']) ?></td>
                        <td class="gov-nowrap"><?= opsui_h((string)$row['created_at']) ?></td>
                        <td><span class="gov-login-result <?= $success ? 'success' : 'failed' ?>"><?= $success ? 'SUCCESS' : 'FAILED' ?></span></td>
                        <td><?= opsui_h((string)$row['login_name']) ?></td>
                        <td><?= opsui_h($actor) ?><br><small><?= opsui_h((string)($row['email'] ?? '')) ?></small></td>
                        <td><span class="gov-event-type"><?= opsui_h((string)$row['reason']) ?></span></td>
                        <td class="gov-nowrap"><?= opsui_h((string)$row['ip_address']) ?></td>
                        <td class="gov-truncate-cell" title="<?= opsui_h((string)$row['user_agent']) ?>"><?= opsui_h((string)$row['user_agent']) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<?php opsui_shell_end(); ?>
