<?php
/**
 * gov.cabnet.app — Starting Point Control v1.0
 *
 * Lessor-specific starting-point governance page.
 * Admins may add/update/deactivate mapping_lessor_starting_points rows.
 * No Bolt calls, no EDXEIX calls, no AADE calls, no workflow writes.
 */

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Robots-Tag: noindex,nofollow', true);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$shellFile = __DIR__ . '/_shell.php';
if (is_file($shellFile)) {
    require_once $shellFile;
}

function spc_h(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function spc_badge(string $text, string $type = 'neutral'): string
{
    if (function_exists('opsui_badge')) {
        return opsui_badge($text, $type);
    }
    $type = in_array($type, ['good', 'warn', 'bad', 'neutral'], true) ? $type : 'neutral';
    return '<span class="badge badge-' . spc_h($type) . '">' . spc_h($text) . '</span>';
}

function spc_is_admin(): bool
{
    if (function_exists('opsui_is_admin')) {
        return opsui_is_admin();
    }
    $user = $_SESSION['ops_user'] ?? [];
    return is_array($user) && strtolower((string)($user['role'] ?? '')) === 'admin';
}

function spc_user_id(): int
{
    $user = $_SESSION['ops_user'] ?? [];
    return is_array($user) ? (int)($user['id'] ?? 0) : 0;
}

function spc_csrf(): string
{
    if (empty($_SESSION['starting_point_control_csrf']) || !is_string($_SESSION['starting_point_control_csrf'])) {
        $_SESSION['starting_point_control_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['starting_point_control_csrf'];
}

function spc_check_csrf(string $token): bool
{
    return isset($_SESSION['starting_point_control_csrf'])
        && is_string($_SESSION['starting_point_control_csrf'])
        && hash_equals($_SESSION['starting_point_control_csrf'], $token);
}

function spc_shell_begin(string $title): void
{
    if (function_exists('opsui_shell_begin')) {
        opsui_shell_begin([
            'title' => $title,
            'page_title' => $title,
            'active_section' => 'Starting Points',
            'breadcrumbs' => 'Αρχική / Διαχειριστικό / Starting point control',
            'safe_notice' => 'Starting point mapping governance. Admin actions only affect local mapping_lessor_starting_points rows; this page does not call EDXEIX or submit rides.',
            'force_safe_notice' => true,
        ]);
        return;
    }
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>' . spc_h($title) . '</title><style>body{font-family:Arial,Helvetica,sans-serif;background:#eef1f6;color:#07152f;margin:0;padding:18px}.card{background:#fff;border:1px solid #d8dde7;border-radius:8px;padding:16px;margin:0 0 16px}.badge{display:inline-block;padding:5px 9px;border-radius:12px;background:#eef2ff;margin:2px}.badge-good{background:#dcfce7;color:#166534}.badge-warn{background:#fff7ed;color:#b45309}.badge-bad{background:#fee2e2;color:#991b1b}.table-wrap{overflow:auto}table{border-collapse:collapse;width:100%;background:#fff}th,td{border:1px solid #d8dde7;padding:8px;text-align:left;vertical-align:top}.btn{display:inline-block;background:#4f5ea7;color:#fff;text-decoration:none;padding:9px 12px;border-radius:5px;font-weight:700;border:0}.btn.warn{background:#b45309}.btn.dark{background:#6b7280}input,select,textarea{width:100%;box-sizing:border-box;border:1px solid #d8dde7;border-radius:5px;padding:9px}</style></head><body>';
}

function spc_shell_end(): void
{
    if (function_exists('opsui_shell_end')) {
        opsui_shell_end();
        return;
    }
    echo '</body></html>';
}

function spc_bootstrap(?string &$error = null): ?array
{
    static $ctx = null;
    static $loaded = false;
    static $loadError = null;
    if ($loaded) {
        $error = $loadError;
        return is_array($ctx) ? $ctx : null;
    }
    $loaded = true;
    $bootstrap = dirname(__DIR__, 3) . '/gov.cabnet.app_app/src/bootstrap.php';
    if (!is_file($bootstrap)) {
        $loadError = 'Private app bootstrap not found.';
        $error = $loadError;
        return null;
    }
    try {
        $ctx = require $bootstrap;
        if (!is_array($ctx) || !isset($ctx['db']) || !method_exists($ctx['db'], 'connection')) {
            throw new RuntimeException('Private app bootstrap did not return a DB context.');
        }
        $error = null;
        return $ctx;
    } catch (Throwable $e) {
        $loadError = $e->getMessage();
        $error = $loadError;
        return null;
    }
}

function spc_db(?string &$error = null): ?mysqli
{
    $ctx = spc_bootstrap($error);
    if (!$ctx || !isset($ctx['db']) || !method_exists($ctx['db'], 'connection')) {
        return null;
    }
    try {
        return $ctx['db']->connection();
    } catch (Throwable $e) {
        $error = $e->getMessage();
        return null;
    }
}

function spc_table_exists(mysqli $db, string $table): bool
{
    try {
        $stmt = $db->prepare('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1');
        $stmt->bind_param('s', $table);
        $stmt->execute();
        return (bool)$stmt->get_result()->fetch_assoc();
    } catch (Throwable) {
        return false;
    }
}

function spc_columns(mysqli $db, string $table): array
{
    if (!spc_table_exists($db, $table)) { return []; }
    $cols = [];
    $res = $db->query('SHOW COLUMNS FROM `' . str_replace('`', '``', $table) . '`');
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $field = (string)($row['Field'] ?? '');
            if ($field !== '') { $cols[$field] = true; }
        }
    }
    return $cols;
}

function spc_pick_col(array $cols, array $choices): ?string
{
    foreach ($choices as $choice) {
        if (isset($cols[$choice])) { return $choice; }
    }
    return null;
}

function spc_fetch_all(mysqli $db, string $sql, string $types = '', array $params = []): array
{
    if ($types === '') {
        $res = $db->query($sql);
        $out = [];
        if ($res) { while ($row = $res->fetch_assoc()) { $out[] = $row; } }
        return $out;
    }
    $stmt = $db->prepare($sql);
    if (!$stmt) { throw new RuntimeException($db->error); }
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    $out = [];
    while ($row = $res->fetch_assoc()) { $out[] = $row; }
    return $out;
}

function spc_fetch_one(mysqli $db, string $sql, string $types = '', array $params = []): ?array
{
    $rows = spc_fetch_all($db, $sql, $types, $params);
    return $rows[0] ?? null;
}

function spc_lessor_name(mysqli $db, int $lessorId): string
{
    if (spc_table_exists($db, 'edxeix_export_lessors')) {
        $cols = spc_columns($db, 'edxeix_export_lessors');
        $idCol = spc_pick_col($cols, ['edxeix_lessor_id', 'lessor_id', 'id']);
        $nameCol = spc_pick_col($cols, ['name', 'lessor_name', 'title', 'label']);
        if ($idCol && $nameCol) {
            $row = spc_fetch_one($db, "SELECT `$nameCol` AS name FROM edxeix_export_lessors WHERE `$idCol` = ? LIMIT 1", 'i', [$lessorId]);
            if ($row && trim((string)($row['name'] ?? '')) !== '') { return trim((string)$row['name']); }
        }
    }
    return 'Lessor ' . $lessorId;
}

function spc_lessors(mysqli $db): array
{
    $lessors = [];
    if (spc_table_exists($db, 'edxeix_export_lessors')) {
        $cols = spc_columns($db, 'edxeix_export_lessors');
        $idCol = spc_pick_col($cols, ['edxeix_lessor_id', 'lessor_id', 'id']);
        $nameCol = spc_pick_col($cols, ['name', 'lessor_name', 'title', 'label']);
        if ($idCol) {
            $sql = "SELECT `$idCol` AS id" . ($nameCol ? ", `$nameCol` AS name" : ", '' AS name") . " FROM edxeix_export_lessors ORDER BY " . ($nameCol ? '`' . $nameCol . '`' : '`' . $idCol . '`') . " ASC";
            foreach (spc_fetch_all($db, $sql) as $row) {
                $id = (int)($row['id'] ?? 0);
                if ($id > 0) { $lessors[$id] = trim((string)($row['name'] ?? '')) ?: ('Lessor ' . $id); }
            }
        }
    }
    foreach (['mapping_drivers', 'mapping_vehicles', 'mapping_lessor_starting_points'] as $table) {
        if (!spc_table_exists($db, $table)) { continue; }
        $cols = spc_columns($db, $table);
        if (!isset($cols['edxeix_lessor_id'])) { continue; }
        foreach (spc_fetch_all($db, "SELECT DISTINCT edxeix_lessor_id FROM `$table` WHERE edxeix_lessor_id IS NOT NULL AND edxeix_lessor_id <> 0") as $row) {
            $id = (int)($row['edxeix_lessor_id'] ?? 0);
            if ($id > 0 && !isset($lessors[$id])) { $lessors[$id] = spc_lessor_name($db, $id); }
        }
    }
    ksort($lessors);
    return $lessors;
}

function spc_starting_point_export_rows(mysqli $db): array
{
    if (!spc_table_exists($db, 'edxeix_export_starting_points')) { return []; }
    $cols = spc_columns($db, 'edxeix_export_starting_points');
    $idCol = spc_pick_col($cols, ['edxeix_starting_point_id', 'starting_point_id', 'id', 'value']);
    $labelCol = spc_pick_col($cols, ['label', 'name', 'title', 'address', 'text']);
    if (!$idCol) { return []; }
    $sql = "SELECT `$idCol` AS id" . ($labelCol ? ", `$labelCol` AS label" : ", '' AS label") . " FROM edxeix_export_starting_points ORDER BY " . ($labelCol ? '`' . $labelCol . '`' : '`' . $idCol . '`') . " ASC LIMIT 500";
    return spc_fetch_all($db, $sql);
}

function spc_export_starting_point_exists(mysqli $db, string $id): bool
{
    if ($id === '' || !spc_table_exists($db, 'edxeix_export_starting_points')) { return false; }
    $cols = spc_columns($db, 'edxeix_export_starting_points');
    $idCol = spc_pick_col($cols, ['edxeix_starting_point_id', 'starting_point_id', 'id', 'value']);
    if (!$idCol) { return false; }
    $row = spc_fetch_one($db, "SELECT `$idCol` FROM edxeix_export_starting_points WHERE `$idCol` = ? LIMIT 1", 's', [$id]);
    return (bool)$row;
}

function spc_overrides(mysqli $db, ?int $lessorId = null): array
{
    if (!spc_table_exists($db, 'mapping_lessor_starting_points')) { return []; }
    if ($lessorId && $lessorId > 0) {
        return spc_fetch_all($db, 'SELECT * FROM mapping_lessor_starting_points WHERE edxeix_lessor_id = ? ORDER BY is_active DESC, id ASC', 'i', [$lessorId]);
    }
    return spc_fetch_all($db, 'SELECT * FROM mapping_lessor_starting_points ORDER BY edxeix_lessor_id ASC, is_active DESC, id ASC LIMIT 1000');
}

function spc_global_rows(mysqli $db): array
{
    if (!spc_table_exists($db, 'mapping_starting_points')) { return []; }
    return spc_fetch_all($db, 'SELECT * FROM mapping_starting_points ORDER BY is_active DESC, id ASC LIMIT 200');
}

function spc_audit(mysqli $db, string $event, string $details): void
{
    if (!spc_table_exists($db, 'ops_audit_log')) { return; }
    $cols = spc_columns($db, 'ops_audit_log');
    try {
        if (isset($cols['user_id'], $cols['event'], $cols['details'])) {
            $uid = spc_user_id();
            $stmt = $db->prepare('INSERT INTO ops_audit_log (user_id, event, details, created_at) VALUES (?, ?, ?, NOW())');
            if ($stmt) { $stmt->bind_param('iss', $uid, $event, $details); $stmt->execute(); }
        } elseif (isset($cols['event_type'], $cols['message'])) {
            $stmt = $db->prepare('INSERT INTO ops_audit_log (event_type, message, created_at) VALUES (?, ?, NOW())');
            if ($stmt) { $stmt->bind_param('ss', $event, $details); $stmt->execute(); }
        }
    } catch (Throwable) {
        // Audit failure must not break mapping correction.
    }
}

function spc_upsert_override(mysqli $db, int $lessorId, string $internalKey, string $label, string $startingPointId, int $active): string
{
    if (!spc_table_exists($db, 'mapping_lessor_starting_points')) {
        throw new RuntimeException('mapping_lessor_starting_points table does not exist.');
    }

    $existing = spc_fetch_one($db, 'SELECT id FROM mapping_lessor_starting_points WHERE edxeix_lessor_id = ? AND internal_key = ? LIMIT 1', 'is', [$lessorId, $internalKey]);

    if ($existing) {
        $id = (int)$existing['id'];
        $stmt = $db->prepare('UPDATE mapping_lessor_starting_points SET label = ?, edxeix_starting_point_id = ?, is_active = ?, updated_at = NOW() WHERE id = ? LIMIT 1');
        if (!$stmt) { throw new RuntimeException($db->error); }
        $stmt->bind_param('ssii', $label, $startingPointId, $active, $id);
        $stmt->execute();
        spc_audit($db, 'starting_point_override_updated', 'lessor=' . $lessorId . ' key=' . $internalKey . ' starting_point=' . $startingPointId . ' active=' . $active);
        return 'Updated existing override row #' . $id . '.';
    }

    $stmt = $db->prepare('INSERT INTO mapping_lessor_starting_points (edxeix_lessor_id, internal_key, label, edxeix_starting_point_id, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())');
    if (!$stmt) { throw new RuntimeException($db->error); }
    $stmt->bind_param('isssi', $lessorId, $internalKey, $label, $startingPointId, $active);
    $stmt->execute();
    spc_audit($db, 'starting_point_override_created', 'lessor=' . $lessorId . ' key=' . $internalKey . ' starting_point=' . $startingPointId . ' active=' . $active);
    return 'Created new override row #' . (int)$db->insert_id . '.';
}

function spc_deactivate_override(mysqli $db, int $id): string
{
    if (!spc_table_exists($db, 'mapping_lessor_starting_points')) {
        throw new RuntimeException('mapping_lessor_starting_points table does not exist.');
    }
    $row = spc_fetch_one($db, 'SELECT id, edxeix_lessor_id, internal_key, edxeix_starting_point_id FROM mapping_lessor_starting_points WHERE id = ? LIMIT 1', 'i', [$id]);
    if (!$row) { throw new RuntimeException('Override row not found.'); }
    $stmt = $db->prepare('UPDATE mapping_lessor_starting_points SET is_active = 0, updated_at = NOW() WHERE id = ? LIMIT 1');
    if (!$stmt) { throw new RuntimeException($db->error); }
    $stmt->bind_param('i', $id);
    $stmt->execute();
    spc_audit($db, 'starting_point_override_deactivated', 'id=' . $id . ' lessor=' . ($row['edxeix_lessor_id'] ?? '') . ' key=' . ($row['internal_key'] ?? '') . ' starting_point=' . ($row['edxeix_starting_point_id'] ?? ''));
    return 'Deactivated override row #' . $id . '.';
}

$error = null;
$notice = '';
$warn = '';
$db = spc_db($error);
$selectedLessor = filter_var($_GET['lessor'] ?? $_POST['edxeix_lessor_id'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['default' => 0]]);
$selectedLessor = max(0, (int)$selectedLessor);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $db) {
    try {
        if (!spc_is_admin()) {
            throw new RuntimeException('Only admin users can change starting point mappings.');
        }
        if (!spc_check_csrf((string)($_POST['csrf'] ?? ''))) {
            throw new RuntimeException('Security token expired. Refresh and try again.');
        }
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'upsert_override') {
            $lessorId = filter_var($_POST['edxeix_lessor_id'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['default' => 0]]);
            $lessorId = max(0, (int)$lessorId);
            $internalKey = trim((string)($_POST['internal_key'] ?? 'lessor_default'));
            $label = trim((string)($_POST['label'] ?? ''));
            $startingPointId = trim((string)($_POST['edxeix_starting_point_id'] ?? ''));
            $active = (int)(($_POST['is_active'] ?? '1') === '1');
            $confirmed = (string)($_POST['confirmed_live']) === '1';

            if ($lessorId <= 0) { throw new RuntimeException('Missing/invalid lessor ID.'); }
            if ($internalKey === '' || !preg_match('/^[a-z0-9_\-]{2,100}$/i', $internalKey)) { throw new RuntimeException('Internal key must be 2-100 letters/numbers/underscore/dash.'); }
            if ($label === '' || mb_strlen($label, 'UTF-8') > 255) { throw new RuntimeException('Label is required and must fit in 255 characters.'); }
            if ($startingPointId === '' || !preg_match('/^[0-9]{1,30}$/', $startingPointId)) { throw new RuntimeException('Starting point ID must be numeric.'); }
            $existsInExport = spc_export_starting_point_exists($db, $startingPointId);
            if (!$existsInExport && !$confirmed) {
                throw new RuntimeException('Starting point ID was not found in the local EDXEIX export snapshot. Tick live verification only if you confirmed it in the live EDXEIX dropdown.');
            }
            $selectedLessor = $lessorId;
            $notice = spc_upsert_override($db, $lessorId, $internalKey, $label, $startingPointId, $active);
        } elseif ($action === 'deactivate_override') {
            $id = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['default' => 0]]);
            $id = max(0, (int)$id);
            if ($id <= 0) { throw new RuntimeException('Invalid override row ID.'); }
            $notice = spc_deactivate_override($db, $id);
        }
    } catch (Throwable $e) {
        $warn = $e->getMessage();
    }
}

spc_shell_begin('Starting Point Control');
?>
<style>
.spc-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}.spc-card{background:#fff;border:1px solid #d8dde7;border-radius:6px;padding:16px;margin:0 0 16px}.spc-card h2,.spc-card h3{margin-top:0}.spc-table-wrap{overflow:auto;border:1px solid #d8dde7;border-radius:6px}.spc-table{border-collapse:collapse;width:100%;background:#fff}.spc-table th,.spc-table td{border-bottom:1px solid #e7ebf2;padding:9px 10px;text-align:left;vertical-align:top;font-size:13px}.spc-table th{background:#f5f7fb;color:#27385f}.spc-actions{display:flex;gap:10px;flex-wrap:wrap}.spc-form-row{margin-bottom:12px}.spc-form-row label{display:block;font-weight:700;margin-bottom:5px}.spc-small{font-size:13px;color:#52627c}.spc-alert{border-radius:6px;padding:12px 14px;margin:0 0 16px}.spc-alert-good{background:#ecfdf3;border:1px solid #bbf7d0;color:#166534}.spc-alert-bad{background:#fee2e2;border:1px solid #fecaca;color:#991b1b}.spc-code{font-family:Consolas,monospace;background:#eef2ff;border-radius:4px;padding:2px 5px}@media(max-width:980px){.spc-grid{grid-template-columns:1fr}}
</style>

<section class="spc-card">
    <h1>Starting Point Control</h1>
    <p class="spc-small">Use this page to prevent wrong starting point fallbacks. Lessor-specific rows in <span class="spc-code">mapping_lessor_starting_points</span> should exist for every operational EDXEIX company.</p>
    <div class="spc-actions">
        <a class="btn" href="/ops/company-mapping-control.php">Company Mapping Control</a>
        <?php if ($selectedLessor > 0): ?><a class="btn dark" href="/ops/company-mapping-detail.php?lessor=<?= spc_h((string)$selectedLessor) ?>">Company Detail</a><?php endif; ?>
        <a class="btn dark" href="/ops/mapping-control.php">Driver/Vehicle Mapping Review</a>
    </div>
</section>

<?php if ($notice !== ''): ?><div class="spc-alert spc-alert-good"><strong><?= spc_h($notice) ?></strong></div><?php endif; ?>
<?php if ($warn !== ''): ?><div class="spc-alert spc-alert-bad"><strong><?= spc_h($warn) ?></strong></div><?php endif; ?>
<?php if ($error || !$db): ?>
<section class="spc-card"><h2>Database unavailable</h2><p><?= spc_h($error ?: 'Unknown DB error.') ?></p></section>
<?php else: ?>
<?php
$lessors = spc_lessors($db);
$exports = spc_starting_point_export_rows($db);
$selectedRows = $selectedLessor > 0 ? spc_overrides($db, $selectedLessor) : [];
$allRows = spc_overrides($db, null);
$globalRows = spc_global_rows($db);
$selectedName = $selectedLessor > 0 ? spc_lessor_name($db, $selectedLessor) : '';
$defaultInternalKey = $selectedLessor === 1756 ? 'whiteblue_default' : 'lessor_default';
$defaultSp = $selectedLessor === 1756 ? '612164' : '';
$defaultLabel = $selectedLessor === 1756 ? 'WHITEBLUE / Ομβροδέκτης, Κοινότητα Μυκόνου, Mykonos 84600' : ($selectedName ? $selectedName . ' / starting point' : '');
?>
<section class="spc-grid">
    <div class="spc-card">
        <h2>Select lessor/company</h2>
        <form method="get" action="/ops/starting-point-control.php">
            <div class="spc-form-row">
                <label for="lessor">EDXEIX lessor</label>
                <select name="lessor" id="lessor" onchange="this.form.submit()">
                    <option value="">Select company…</option>
                    <?php foreach ($lessors as $id => $name): ?>
                        <option value="<?= spc_h((string)$id) ?>" <?= $selectedLessor === (int)$id ? 'selected' : '' ?>><?= spc_h($name . ' / ' . $id) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button class="btn" type="submit">Open</button>
        </form>
    </div>

    <div class="spc-card">
        <h2>Risk rule</h2>
        <p>If a lessor has no active lessor-specific starting point row, the resolver may fall back to global starting points. That can select the wrong EDXEIX option.</p>
        <p><?= spc_badge('Goal', 'good') ?> Every operational lessor must have an explicit active override.</p>
    </div>
</section>

<?php if ($selectedLessor > 0): ?>
<section class="spc-grid">
    <div class="spc-card">
        <h2><?= spc_h($selectedName) ?> / <?= spc_h((string)$selectedLessor) ?></h2>
        <p class="spc-small">Current lessor-specific rows:</p>
        <div class="spc-table-wrap"><table class="spc-table"><thead><tr><th>ID</th><th>Key</th><th>Label</th><th>Starting point ID</th><th>Status</th><th>Action</th></tr></thead><tbody>
        <?php if (!$selectedRows): ?><tr><td colspan="6"><?= spc_badge('missing override', 'warn') ?> No override rows for this lessor.</td></tr><?php endif; ?>
        <?php foreach ($selectedRows as $row): ?>
            <tr>
                <td><?= spc_h((string)($row['id'] ?? '')) ?></td>
                <td><?= spc_h((string)($row['internal_key'] ?? '')) ?></td>
                <td><?= spc_h((string)($row['label'] ?? '')) ?></td>
                <td><strong><?= spc_h((string)($row['edxeix_starting_point_id'] ?? '')) ?></strong></td>
                <td><?= (string)($row['is_active'] ?? '1') === '0' ? spc_badge('inactive', 'neutral') : spc_badge('active', 'good') ?></td>
                <td>
                    <?php if (spc_is_admin() && (string)($row['is_active'] ?? '1') !== '0'): ?>
                    <form method="post" action="/ops/starting-point-control.php?lessor=<?= spc_h((string)$selectedLessor) ?>" onsubmit="return confirm('Deactivate this starting point override?');">
                        <input type="hidden" name="csrf" value="<?= spc_h(spc_csrf()) ?>">
                        <input type="hidden" name="action" value="deactivate_override">
                        <input type="hidden" name="id" value="<?= spc_h((string)($row['id'] ?? '')) ?>">
                        <button class="btn warn" type="submit">Deactivate</button>
                    </form>
                    <?php else: ?>—<?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?></tbody></table></div>
    </div>

    <div class="spc-card">
        <h2><?= spc_is_admin() ? 'Add / update override' : 'Override form' ?></h2>
        <?php if (!spc_is_admin()): ?>
            <p><?= spc_badge('admin only', 'warn') ?> You can review rows here, but only admin users can change mapping overrides.</p>
        <?php endif; ?>
        <form method="post" action="/ops/starting-point-control.php?lessor=<?= spc_h((string)$selectedLessor) ?>">
            <input type="hidden" name="csrf" value="<?= spc_h(spc_csrf()) ?>">
            <input type="hidden" name="action" value="upsert_override">
            <input type="hidden" name="edxeix_lessor_id" value="<?= spc_h((string)$selectedLessor) ?>">
            <div class="spc-form-row"><label>Internal key</label><input name="internal_key" value="<?= spc_h($defaultInternalKey) ?>" required></div>
            <div class="spc-form-row"><label>Label</label><input name="label" value="<?= spc_h($defaultLabel) ?>" maxlength="255" required></div>
            <div class="spc-form-row">
                <label>EDXEIX starting point ID</label>
                <input name="edxeix_starting_point_id" value="<?= spc_h($defaultSp) ?>" pattern="[0-9]+" required list="spc-starting-point-list">
                <datalist id="spc-starting-point-list">
                    <?php foreach ($exports as $row): ?><option value="<?= spc_h((string)($row['id'] ?? '')) ?>"><?= spc_h((string)($row['label'] ?? '')) ?></option><?php endforeach; ?>
                </datalist>
            </div>
            <div class="spc-form-row"><label>Status</label><select name="is_active"><option value="1">Active</option><option value="0">Inactive</option></select></div>
            <div class="spc-form-row"><label><input type="checkbox" name="confirmed_live" value="1" style="width:auto"> I verified this starting point ID in live EDXEIX for this lessor.</label><p class="spc-small">Required if the ID is not present in the local export snapshot.</p></div>
            <button class="btn" type="submit" <?= spc_is_admin() ? '' : 'disabled' ?>>Save override</button>
        </form>
    </div>
</section>
<?php endif; ?>

<section class="spc-card">
    <h2>All lessor-specific overrides</h2>
    <div class="spc-table-wrap"><table class="spc-table"><thead><tr><th>Lessor</th><th>Key</th><th>Label</th><th>Starting point ID</th><th>Status</th><th>Updated</th></tr></thead><tbody>
    <?php if (!$allRows): ?><tr><td colspan="6">No rows in mapping_lessor_starting_points.</td></tr><?php endif; ?>
    <?php foreach ($allRows as $row): $lid = (int)($row['edxeix_lessor_id'] ?? 0); ?>
        <tr><td><a href="/ops/starting-point-control.php?lessor=<?= spc_h((string)$lid) ?>"><?= spc_h(spc_lessor_name($db, $lid) . ' / ' . $lid) ?></a></td><td><?= spc_h((string)($row['internal_key'] ?? '')) ?></td><td><?= spc_h((string)($row['label'] ?? '')) ?></td><td><strong><?= spc_h((string)($row['edxeix_starting_point_id'] ?? '')) ?></strong></td><td><?= (string)($row['is_active'] ?? '1') === '0' ? spc_badge('inactive', 'neutral') : spc_badge('active', 'good') ?></td><td><?= spc_h((string)($row['updated_at'] ?? '')) ?></td></tr>
    <?php endforeach; ?></tbody></table></div>
</section>

<section class="spc-card">
    <h2>Global fallback rows</h2>
    <p class="spc-small">These remain fallback only. Operational lessors should not rely on them for production submissions.</p>
    <div class="spc-table-wrap"><table class="spc-table"><thead><tr><th>ID</th><th>Internal key</th><th>Label</th><th>Starting point ID</th><th>Status</th></tr></thead><tbody>
    <?php foreach ($globalRows as $row): ?><tr><td><?= spc_h((string)($row['id'] ?? '')) ?></td><td><?= spc_h((string)($row['internal_key'] ?? '')) ?></td><td><?= spc_h((string)($row['label'] ?? '')) ?></td><td><strong><?= spc_h((string)($row['edxeix_starting_point_id'] ?? '')) ?></strong></td><td><?= (string)($row['is_active'] ?? '1') === '0' ? spc_badge('inactive', 'neutral') : spc_badge('active', 'warn') ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
</section>
<?php endif; ?>
<?php spc_shell_end(); ?>
