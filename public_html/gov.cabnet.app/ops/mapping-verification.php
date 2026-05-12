<?php
/**
 * gov.cabnet.app — Mapping Verification Register v1.0
 * Admin can record sanitized mapping verification decisions. No EDXEIX/Bolt calls.
 */
declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Robots-Tag: noindex,nofollow', true);

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

$shellFile = __DIR__ . '/_shell.php';
if (is_file($shellFile)) { require_once $shellFile; }
$mappingNavFile = __DIR__ . '/_mapping_nav.php';
if (is_file($mappingNavFile)) { require_once $mappingNavFile; }

function mvr_h(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function mvr_badge(string $text, string $type = 'neutral'): string
{
    if (function_exists('opsui_badge')) { return opsui_badge($text, $type); }
    return '<span class="badge badge-' . mvr_h($type) . '">' . mvr_h($text) . '</span>';
}
function mvr_user(): array
{
    if (function_exists('opsui_current_user')) { return opsui_current_user(); }
    return is_array($_SESSION['ops_user'] ?? null) ? $_SESSION['ops_user'] : [];
}
function mvr_is_admin(): bool
{
    if (function_exists('opsui_is_admin')) { return opsui_is_admin(); }
    $u = mvr_user(); return strtolower((string)($u['role'] ?? '')) === 'admin';
}
function mvr_csrf(): string
{
    if (empty($_SESSION['mapping_verification_csrf'])) { $_SESSION['mapping_verification_csrf'] = bin2hex(random_bytes(32)); }
    return (string)$_SESSION['mapping_verification_csrf'];
}
function mvr_check_csrf(string $token): bool
{
    return isset($_SESSION['mapping_verification_csrf']) && hash_equals((string)$_SESSION['mapping_verification_csrf'], $token);
}
function mvr_db(?string &$error = null): ?mysqli
{
    $bootstrap = dirname(__DIR__, 3) . '/gov.cabnet.app_app/src/bootstrap.php';
    if (!is_file($bootstrap)) { $error = 'Private app bootstrap not found.'; return null; }
    try {
        $ctx = require $bootstrap;
        if (!is_array($ctx) || !isset($ctx['db']) || !method_exists($ctx['db'], 'connection')) {
            throw new RuntimeException('Invalid private app context.');
        }
        $db = $ctx['db']->connection();
        if ($db instanceof mysqli) { $error = null; return $db; }
        throw new RuntimeException('Invalid DB connection.');
    } catch (Throwable $e) { $error = $e->getMessage(); return null; }
}
function mvr_table_exists(mysqli $db, string $table): bool
{
    $stmt = $db->prepare('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1');
    $stmt->bind_param('s', $table); $stmt->execute(); return (bool)$stmt->get_result()->fetch_assoc();
}
function mvr_fetch_all(mysqli $db, string $sql): array
{
    $res = $db->query($sql); if (!$res) { return []; }
    $rows = []; while ($row = $res->fetch_assoc()) { $rows[] = $row; } return $rows;
}
function mvr_fetch_one(mysqli $db, string $sql): ?array
{
    $rows = mvr_fetch_all($db, $sql); return $rows[0] ?? null;
}
function mvr_clean_text(string $value, int $max = 255): string
{
    $value = trim(preg_replace('/\s+/u', ' ', strip_tags($value)) ?? '');
    if (function_exists('mb_substr')) { return mb_substr($value, 0, $max, 'UTF-8'); }
    return substr($value, 0, $max);
}
function mvr_lessor_name(mysqli $db, int $lessorId): string
{
    if (mvr_table_exists($db, 'edxeix_export_lessors')) {
        $stmt = $db->prepare('SELECT * FROM edxeix_export_lessors WHERE id = ? LIMIT 1');
        $row = null;
        if ($stmt) {
            $stmt->bind_param('i', $lessorId);
            try { $stmt->execute(); $row = $stmt->get_result()->fetch_assoc(); } catch (Throwable) { $row = null; }
        }
        if (is_array($row)) {
            foreach (['name','label','lessor_name','title'] as $key) { if (!empty($row[$key])) { return (string)$row[$key]; } }
        }
    }
    $row = mvr_fetch_one($db, 'SELECT external_driver_name, edxeix_lessor_id FROM mapping_drivers WHERE edxeix_lessor_id=' . (int)$lessorId . ' LIMIT 1');
    return $row ? ('Lessor ' . $lessorId) : ('Lessor ' . $lessorId);
}
function mvr_lessor_ids(mysqli $db): array
{
    $ids = [];
    if (mvr_table_exists($db, 'edxeix_export_lessors')) {
        foreach (['id','edxeix_lessor_id'] as $col) {
            try {
                foreach (mvr_fetch_all($db, 'SELECT DISTINCT ' . $col . ' AS id FROM edxeix_export_lessors WHERE ' . $col . ' IS NOT NULL AND ' . $col . ' <> 0') as $r) { $ids[(int)$r['id']] = true; }
                break;
            } catch (Throwable) {}
        }
    }
    if (mvr_table_exists($db, 'mapping_drivers')) {
        foreach (mvr_fetch_all($db, 'SELECT DISTINCT edxeix_lessor_id AS id FROM mapping_drivers WHERE edxeix_lessor_id IS NOT NULL AND edxeix_lessor_id <> 0') as $r) { $ids[(int)$r['id']] = true; }
    }
    if (mvr_table_exists($db, 'mapping_vehicles')) {
        foreach (mvr_fetch_all($db, 'SELECT DISTINCT edxeix_lessor_id AS id FROM mapping_vehicles WHERE edxeix_lessor_id IS NOT NULL AND edxeix_lessor_id <> 0') as $r) { $ids[(int)$r['id']] = true; }
    }
    if (mvr_table_exists($db, 'mapping_lessor_starting_points')) {
        foreach (mvr_fetch_all($db, 'SELECT DISTINCT edxeix_lessor_id AS id FROM mapping_lessor_starting_points WHERE edxeix_lessor_id IS NOT NULL AND edxeix_lessor_id <> 0') as $r) { $ids[(int)$r['id']] = true; }
    }
    $out = array_keys($ids); sort($out); return $out;
}
function mvr_count(mysqli $db, string $table, int $lessorId, string $extra = ''): int
{
    if (!mvr_table_exists($db, $table)) { return 0; }
    $sql = 'SELECT COUNT(*) AS c FROM ' . $table . ' WHERE edxeix_lessor_id=' . (int)$lessorId . ' ' . $extra;
    return (int)(mvr_fetch_one($db, $sql)['c'] ?? 0);
}
function mvr_override(mysqli $db, int $lessorId): ?array
{
    if (!mvr_table_exists($db, 'mapping_lessor_starting_points')) { return null; }
    return mvr_fetch_one($db, 'SELECT * FROM mapping_lessor_starting_points WHERE edxeix_lessor_id=' . (int)$lessorId . ' AND is_active=1 ORDER BY updated_at DESC, id DESC LIMIT 1');
}
function mvr_verification(mysqli $db, int $lessorId): ?array
{
    if (!mvr_table_exists($db, 'mapping_verification_status')) { return null; }
    return mvr_fetch_one($db, 'SELECT * FROM mapping_verification_status WHERE edxeix_lessor_id=' . (int)$lessorId . ' LIMIT 1');
}
function mvr_audit(mysqli $db, string $event, string $details): void
{
    if (!mvr_table_exists($db, 'ops_audit_log')) { return; }
    try {
        $u = mvr_user();
        $uid = (int)($u['id'] ?? 0); $username = (string)($u['username'] ?? '');
        $ip = (string)($_SERVER['REMOTE_ADDR'] ?? ''); $ua = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500);
        $stmt = $db->prepare('INSERT INTO ops_audit_log (user_id, username, event_type, detail, ip_address, user_agent, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())');
        if ($stmt) { $stmt->bind_param('isssss', $uid, $username, $event, $details, $ip, $ua); $stmt->execute(); }
    } catch (Throwable) {}
}

$dbError = null;
$db = mvr_db($dbError);
$message = '';
$error = '';
$tableReady = false;
$isAdmin = mvr_is_admin();

if ($db) {
    $tableReady = mvr_table_exists($db, 'mapping_verification_status');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$db) { $error = 'DB unavailable: ' . $dbError; }
    elseif (!$tableReady) { $error = 'mapping_verification_status table is missing. Run the Phase 37 SQL migration first.'; }
    elseif (!$isAdmin) { $error = 'Admin role required to save verification rows.'; }
    elseif (!mvr_check_csrf((string)($_POST['csrf'] ?? ''))) { $error = 'Security token expired. Reload and try again.'; }
    else {
        $lessorId = (int)($_POST['edxeix_lessor_id'] ?? 0);
        $status = (string)($_POST['verification_status'] ?? 'pending');
        $allowed = ['verified','review_needed','pending'];
        if (!in_array($status, $allowed, true)) { $status = 'pending'; }
        $lessorName = mvr_clean_text((string)($_POST['lessor_name'] ?? ''), 255);
        if ($lessorName === '' && $lessorId > 0) { $lessorName = mvr_lessor_name($db, $lessorId); }
        $spId = mvr_clean_text((string)($_POST['starting_point_id'] ?? ''), 64);
        $spLabel = mvr_clean_text((string)($_POST['starting_point_label'] ?? ''), 255);
        $source = mvr_clean_text((string)($_POST['source'] ?? 'manual_edxeix_ui'), 64);
        $notes = trim(strip_tags((string)($_POST['notes'] ?? '')));
        $u = mvr_user();
        $verifiedById = (int)($u['id'] ?? 0);
        $verifiedByName = mvr_clean_text((string)(($u['display_name'] ?? '') ?: ($u['username'] ?? 'operator')), 190);
        if ($lessorId <= 0) {
            $error = 'Valid EDXEIX lessor ID is required.';
        } else {
            $stmt = $db->prepare("INSERT INTO mapping_verification_status
                (edxeix_lessor_id, lessor_name, verification_status, starting_point_id, starting_point_label, source, verified_by_user_id, verified_by_name, verified_at, notes, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, NOW(), NOW())
                ON DUPLICATE KEY UPDATE
                    lessor_name=VALUES(lessor_name), verification_status=VALUES(verification_status), starting_point_id=VALUES(starting_point_id),
                    starting_point_label=VALUES(starting_point_label), source=VALUES(source), verified_by_user_id=VALUES(verified_by_user_id),
                    verified_by_name=VALUES(verified_by_name), verified_at=NOW(), notes=VALUES(notes), updated_at=NOW()");
            if (!$stmt) { $error = $db->error; }
            else {
                $stmt->bind_param('isssssiss', $lessorId, $lessorName, $status, $spId, $spLabel, $source, $verifiedById, $verifiedByName, $notes);
                if ($stmt->execute()) {
                    $message = 'Verification saved for lessor ' . $lessorId . '.';
                    mvr_audit($db, 'mapping_verification_saved', 'lessor=' . $lessorId . ' status=' . $status . ' starting_point=' . $spId);
                } else { $error = $stmt->error; }
            }
        }
    }
}

$lessorFilter = isset($_GET['lessor']) ? max(0, (int)$_GET['lessor']) : 0;
$rows = [];
if ($db) {
    $ids = $lessorFilter > 0 ? [$lessorFilter] : mvr_lessor_ids($db);
    foreach ($ids as $id) {
        $override = mvr_override($db, (int)$id);
        $verification = mvr_verification($db, (int)$id);
        $name = (string)($verification['lessor_name'] ?? '');
        if ($name === '') { $name = mvr_lessor_name($db, (int)$id); }
        $rows[] = [
            'id' => (int)$id,
            'name' => $name,
            'drivers' => mvr_count($db, 'mapping_drivers', (int)$id, ' AND is_active=1'),
            'vehicles' => mvr_count($db, 'mapping_vehicles', (int)$id, ' AND is_active=1'),
            'override' => $override,
            'verification' => $verification,
        ];
    }
}

if (function_exists('opsui_shell_begin')) {
    opsui_shell_begin([
        'title' => 'Mapping Verification',
        'page_title' => 'Mapping Verification Register',
        'active_section' => 'Mapping Governance',
        'breadcrumbs' => 'Αρχική / Mapping / Verification',
        'safe_notice' => 'Mapping verification register. Admin saves sanitized verification notes only. No EDXEIX/Bolt calls and no live submission behavior.',
        'force_safe_notice' => true,
    ]);
} else { echo '<!doctype html><html><head><meta charset="utf-8"><title>Mapping Verification</title></head><body>'; }
?>
<style>
.mvr-grid{display:grid;grid-template-columns:minmax(0,1fr) minmax(330px,.55fr);gap:18px}.mvr-form{background:#fff;border:1px solid #d8dde7;border-radius:6px;padding:16px}.mvr-form label{display:block;font-weight:700;margin:10px 0 5px}.mvr-form input,.mvr-form select,.mvr-form textarea{width:100%;border:1px solid #d8dde7;border-radius:5px;padding:10px}.mvr-form textarea{min-height:110px}.mvr-actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:12px}.mvr-actions button,.mvr-actions a{background:#4f5ea7;color:#fff;border:0;border-radius:5px;padding:10px 12px;font-weight:700;text-decoration:none}.mvr-status-verified{color:#166534}.mvr-status-review_needed{color:#b45309}.mvr-status-pending{color:#6b7280}@media(max-width:1000px){.mvr-grid{grid-template-columns:1fr}}
</style>
<?php if (function_exists('gov_mapping_nav')) { gov_mapping_nav('verification'); } ?>

<section class="card hero">
    <h1>Mapping Verification Register</h1>
    <p>Record and review which EDXEIX lessor mappings have been manually verified. Use this after checking live EDXEIX dropdowns for company, driver, vehicle, and starting point.</p>
    <div>
        <?= mvr_badge($tableReady ? 'TABLE READY' : 'SQL REQUIRED', $tableReady ? 'good' : 'warn') ?>
        <?= mvr_badge($isAdmin ? 'ADMIN WRITE AVAILABLE' : 'READ ONLY USER', $isAdmin ? 'good' : 'neutral') ?>
        <?= mvr_badge('NO EDXEIX CALL', 'good') ?>
    </div>
</section>

<?php if ($message): ?><div class="gov-alert gov-alert-good"><?= mvr_h($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="gov-alert gov-alert-bad"><?= mvr_h($error) ?></div><?php endif; ?>
<?php if ($dbError): ?><div class="gov-alert gov-alert-bad">DB error: <?= mvr_h($dbError) ?></div><?php endif; ?>
<?php if (!$tableReady): ?>
<section class="card">
    <h2>SQL migration required</h2>
    <p>Upload and run:</p>
    <pre><code>mysql -u cabnet_gov -p cabnet_gov &lt; /home/cabnet/gov.cabnet.app_sql/2026_05_12_mapping_verification_register.sql</code></pre>
</section>
<?php endif; ?>

<section class="mvr-grid">
    <div class="card">
        <h2>Lessor verification overview</h2>
        <div class="table-wrap"><table>
            <thead><tr><th>Lessor</th><th>Mapped rows</th><th>Starting point override</th><th>Verification</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $row): $v=$row['verification']; $o=$row['override']; $status=(string)($v['verification_status'] ?? 'pending'); ?>
                <tr>
                    <td><strong><?= mvr_h($row['name']) ?></strong><br><code><?= (int)$row['id'] ?></code></td>
                    <td><?= (int)$row['drivers'] ?> drivers<br><?= (int)$row['vehicles'] ?> vehicles</td>
                    <td>
                        <?php if ($o): ?>
                            <?= mvr_badge((string)$o['edxeix_starting_point_id'], 'good') ?><br><?= mvr_h((string)$o['label']) ?>
                        <?php else: ?>
                            <?= mvr_badge('missing override', 'warn') ?><br><span class="small">Global fallback risk.</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?= mvr_badge($status, $status === 'verified' ? 'good' : ($status === 'review_needed' ? 'warn' : 'neutral')) ?>
                        <?php if ($v): ?><div class="small">By <?= mvr_h((string)$v['verified_by_name']) ?> at <?= mvr_h((string)$v['verified_at']) ?></div><?php endif; ?>
                    </td>
                    <td>
                        <a class="btn" href="/ops/mapping-verification.php?lessor=<?= (int)$row['id'] ?>">Focus</a>
                        <a class="btn dark" href="/ops/company-mapping-detail.php?lessor=<?= (int)$row['id'] ?>">Detail</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?><tr><td colspan="5">No lessor IDs found.</td></tr><?php endif; ?>
            </tbody>
        </table></div>
    </div>

    <aside class="mvr-form">
        <h2>Save verification</h2>
        <?php if (!$isAdmin): ?>
            <p class="warnline"><strong>Read-only:</strong> admin role is required to save verification rows.</p>
        <?php endif; ?>
        <form method="post" action="/ops/mapping-verification.php">
            <input type="hidden" name="csrf" value="<?= mvr_h(mvr_csrf()) ?>">
            <label for="edxeix_lessor_id">EDXEIX lessor ID</label>
            <input id="edxeix_lessor_id" name="edxeix_lessor_id" inputmode="numeric" value="<?= $lessorFilter > 0 ? (int)$lessorFilter : '' ?>" placeholder="1756">

            <label for="lessor_name">Lessor / company name</label>
            <input id="lessor_name" name="lessor_name" value="<?= $lessorFilter > 0 && $db ? mvr_h(mvr_lessor_name($db, $lessorFilter)) : '' ?>" placeholder="WHITEBLUE PREMIUM E E">

            <label for="verification_status">Status</label>
            <select id="verification_status" name="verification_status">
                <option value="verified">verified</option>
                <option value="review_needed">review_needed</option>
                <option value="pending" selected>pending</option>
            </select>

            <label for="starting_point_id">Verified starting point ID</label>
            <input id="starting_point_id" name="starting_point_id" value="<?= $lessorFilter === 1756 ? '612164' : '' ?>" placeholder="612164">

            <label for="starting_point_label">Verified starting point label</label>
            <input id="starting_point_label" name="starting_point_label" value="<?= $lessorFilter === 1756 ? mvr_h('Ομβροδέκτης, Κοινότητα Μυκόνου, Mykonos 84600') : '' ?>" placeholder="Live EDXEIX label">

            <label for="source">Verification source</label>
            <select id="source" name="source">
                <option value="manual_edxeix_ui">manual_edxeix_ui</option>
                <option value="edxeix_export_snapshot">edxeix_export_snapshot</option>
                <option value="operator_live_test">operator_live_test</option>
            </select>

            <label for="notes">Notes</label>
            <textarea id="notes" name="notes" placeholder="Example: Verified visually in live EDXEIX dropdown under lessor 1756. Driver 4382 and vehicle 4327 checked."></textarea>
            <div class="mvr-actions">
                <button type="submit" <?= (!$isAdmin || !$tableReady) ? 'disabled' : '' ?>>Save verification</button>
                <a href="/ops/starting-point-control.php<?= $lessorFilter ? '?lessor=' . (int)$lessorFilter : '' ?>">Starting Point Control</a>
            </div>
        </form>
    </aside>
</section>
<?php
if (function_exists('opsui_shell_end')) { opsui_shell_end(); } else { echo '</body></html>'; }
