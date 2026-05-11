<?php
/**
 * gov.cabnet.app — Ops Audit Log
 *
 * Admin-only, read-only visibility into ops_audit_log.
 * No Bolt calls, no EDXEIX calls, no database writes.
 */

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Robots-Tag: noindex,nofollow', true);

require_once __DIR__ . '/_shell.php';

function oal_bootstrap(): array
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

function oal_int_param(string $key, int $default, int $min, int $max): int
{
    $value = filter_input(INPUT_GET, $key, FILTER_VALIDATE_INT);
    if (!is_int($value)) {
        $value = $default;
    }
    return max($min, min($max, $value));
}

function oal_query(mysqli $db, string $sql, array $params = [], string $types = ''): array
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

function oal_one(mysqli $db, string $sql, array $params = [], string $types = ''): array
{
    $rows = oal_query($db, $sql, $params, $types);
    return $rows[0] ?? [];
}

function oal_has_table(mysqli $db, string $table): bool
{
    $row = oal_one($db, 'SHOW TABLES LIKE ?', [$table], 's');
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
    echo '<section class="card"><h2>Admin access required</h2><p class="badline">Only admin users can view the audit log.</p></section>';
    opsui_shell_end();
    exit;
}

$error = '';
$rows = [];
$summary = [
    'total' => 0,
    'last_24h' => 0,
    'logins' => 0,
    'user_admin' => 0,
];
$limit = oal_int_param('limit', 100, 10, 500);
$q = trim((string)($_GET['q'] ?? ''));
$event = trim((string)($_GET['event'] ?? ''));

try {
    $ctx = oal_bootstrap();
    /** @var mysqli $db */
    $db = $ctx['db']->connection();

    if (!oal_has_table($db, 'ops_audit_log')) {
        throw new RuntimeException('ops_audit_log table does not exist. Run the ops login SQL migration first.');
    }

    $summary['total'] = (int)(oal_one($db, 'SELECT COUNT(*) AS c FROM ops_audit_log')['c'] ?? 0);
    $summary['last_24h'] = (int)(oal_one($db, 'SELECT COUNT(*) AS c FROM ops_audit_log WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)')['c'] ?? 0);
    $summary['logins'] = (int)(oal_one($db, "SELECT COUNT(*) AS c FROM ops_audit_log WHERE event_type IN ('login','logout')")['c'] ?? 0);
    $summary['user_admin'] = (int)(oal_one($db, "SELECT COUNT(*) AS c FROM ops_audit_log WHERE event_type LIKE 'user_%'")['c'] ?? 0);

    $where = [];
    $params = [];
    $types = '';

    if ($q !== '') {
        $like = '%' . $q . '%';
        $where[] = '(a.event_type LIKE ? OR a.ip_address LIKE ? OR u.username LIKE ? OR u.email LIKE ? OR u.display_name LIKE ?)';
        array_push($params, $like, $like, $like, $like, $like);
        $types .= 'sssss';
    }

    if ($event !== '') {
        $where[] = 'a.event_type = ?';
        $params[] = $event;
        $types .= 's';
    }

    $sql = "
        SELECT
            a.id,
            a.user_id,
            a.event_type,
            a.ip_address,
            a.user_agent,
            a.meta_json,
            a.created_at,
            u.username,
            u.display_name,
            u.email,
            u.role
        FROM ops_audit_log a
        LEFT JOIN ops_users u ON u.id = a.user_id
    ";
    if ($where !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY a.created_at DESC, a.id DESC LIMIT ' . (int)$limit;
    $rows = oal_query($db, $sql, $params, $types);
} catch (Throwable $e) {
    $error = $e->getMessage();
}

opsui_shell_begin([
    'title' => 'Audit Log',
    'page_title' => 'Ops audit log',
    'active_section' => 'User area',
    'breadcrumbs' => 'Αρχική / Χρήστες / Audit log',
    'safe_notice' => 'Read-only admin visibility into operator activity. This page does not call Bolt, EDXEIX, or AADE, and does not write data.',
]);
?>

<section class="card hero neutral">
    <h1>Operator activity audit</h1>
    <p>Read-only visibility into login/logout and user administration events.</p>
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
    <?= opsui_metric((string)$summary['total'], 'Audit events') ?>
    <?= opsui_metric((string)$summary['last_24h'], 'Events in 24h') ?>
    <?= opsui_metric((string)$summary['logins'], 'Login/logout events') ?>
    <?= opsui_metric((string)$summary['user_admin'], 'User admin events') ?>
</section>

<section class="card">
    <h2>Filters</h2>
    <form class="gov-filter-bar" method="get" action="/ops/audit-log.php">
        <div class="gov-form-field wide">
            <label for="q">Search</label>
            <input id="q" name="q" type="text" value="<?= opsui_h($q) ?>" placeholder="username, email, event, IP">
        </div>
        <div class="gov-form-field">
            <label for="event">Event type</label>
            <input id="event" name="event" type="text" value="<?= opsui_h($event) ?>" placeholder="login, user_created">
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
            <a class="btn dark" href="/ops/audit-log.php">Reset</a>
        </div>
    </form>
</section>

<section class="card">
    <h2>Recent audit events</h2>
    <div class="table-wrap">
        <table class="gov-user-table">
            <thead>
            <tr>
                <th>ID</th>
                <th>Time</th>
                <th>Event</th>
                <th>User</th>
                <th>IP</th>
                <th>Meta</th>
                <th>User agent</th>
            </tr>
            </thead>
            <tbody>
            <?php if ($rows === []): ?>
                <tr><td colspan="7">No audit events found.</td></tr>
            <?php else: ?>
                <?php foreach ($rows as $row): ?>
                    <?php
                    $actor = trim((string)($row['display_name'] ?? ''));
                    if ($actor === '') { $actor = trim((string)($row['username'] ?? '')); }
                    if ($actor === '') { $actor = 'Unknown / system'; }
                    $meta = trim((string)($row['meta_json'] ?? ''));
                    ?>
                    <tr>
                        <td class="gov-nowrap">#<?= opsui_h((string)$row['id']) ?></td>
                        <td class="gov-nowrap"><?= opsui_h((string)$row['created_at']) ?></td>
                        <td><span class="gov-event-type"><?= opsui_h((string)$row['event_type']) ?></span></td>
                        <td><?= opsui_h($actor) ?><br><small><?= opsui_h((string)($row['email'] ?? '')) ?></small></td>
                        <td class="gov-nowrap"><?= opsui_h((string)$row['ip_address']) ?></td>
                        <td class="gov-truncate-cell" title="<?= opsui_h($meta) ?>"><?= opsui_h($meta) ?></td>
                        <td class="gov-truncate-cell" title="<?= opsui_h((string)$row['user_agent']) ?>"><?= opsui_h((string)$row['user_agent']) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<?php opsui_shell_end(); ?>
