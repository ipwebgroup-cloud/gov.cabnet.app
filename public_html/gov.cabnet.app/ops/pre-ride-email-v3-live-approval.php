<?php
/**
 * gov.cabnet.app — V3 live-submit operator approval page.
 *
 * V3-only per-row approval ledger for future live submit automation.
 * This page never submits to EDXEIX.
 */

declare(strict_types=1);

session_start();
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Robots-Tag: noindex, nofollow', true);

const V3_APPROVAL_PAGE_VERSION = 'v3.0.26-live-submit-operator-approval';
const V3_APPROVAL_CONFIRM_PHRASE = 'APPROVE V3 LIVE SUBMIT HANDOFF';

function v3ap_h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function v3ap_badge(string $text, string $type = 'neutral'): string { return '<span class="badge badge-' . v3ap_h($type) . '">' . v3ap_h($text) . '</span>'; }
function v3ap_token(): string { if (empty($_SESSION['v3ap_csrf'])) { $_SESSION['v3ap_csrf'] = bin2hex(random_bytes(16)); } return (string)$_SESSION['v3ap_csrf']; }
function v3ap_private_file(string $relative): string {
    $relative = ltrim($relative, '/');
    $candidates = [dirname(__DIR__, 3) . '/gov.cabnet.app_app/' . $relative, dirname(__DIR__, 2) . '/gov.cabnet.app_app/' . $relative];
    foreach ($candidates as $file) { if (is_file($file)) { return $file; } }
    return $candidates[0];
}
function v3ap_context(?string &$error = null): ?array {
    $bootstrap = v3ap_private_file('src/bootstrap.php');
    if (!is_file($bootstrap)) { $error = 'Private app bootstrap not found.'; return null; }
    try {
        $ctx = require $bootstrap;
        if (!is_array($ctx) || !isset($ctx['db']) || !method_exists($ctx['db'], 'connection')) { $error = 'Private app bootstrap did not return a usable DB context.'; return null; }
        return $ctx;
    } catch (Throwable $e) { $error = $e->getMessage(); return null; }
}
function v3ap_table_exists(mysqli $db, string $table): bool {
    $stmt = $db->prepare('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1');
    if (!$stmt) { return false; }
    $stmt->bind_param('s', $table); $stmt->execute(); return (bool)$stmt->get_result()->fetch_assoc();
}
function v3ap_fetch_all(mysqli $db, string $sql): array {
    $rows = []; $res = $db->query($sql); if ($res) { while ($row = $res->fetch_assoc()) { $rows[] = $row; } } return $rows;
}
function v3ap_start_verified(mysqli $db, string $lessorId, string $startId): bool {
    if (!v3ap_table_exists($db, 'pre_ride_email_v3_starting_point_options')) { return false; }
    $stmt = $db->prepare('SELECT id FROM pre_ride_email_v3_starting_point_options WHERE edxeix_lessor_id = ? AND edxeix_starting_point_id = ? AND is_active = 1 LIMIT 1');
    if (!$stmt) { return false; }
    $stmt->bind_param('ss', $lessorId, $startId); $stmt->execute(); return (bool)$stmt->get_result()->fetch_assoc();
}
function v3ap_future_minutes(?string $pickup): ?int {
    $pickup = trim((string)$pickup); if ($pickup === '') { return null; }
    $ts = strtotime($pickup); if ($ts === false) { return null; }
    return (int)floor(($ts - time()) / 60);
}
function v3ap_get_queue_row(mysqli $db, int $id, string $dedupe): ?array {
    $stmt = $db->prepare('SELECT * FROM pre_ride_email_v3_queue WHERE id = ? AND dedupe_key = ? LIMIT 1');
    if (!$stmt) { return null; }
    $stmt->bind_param('is', $id, $dedupe); $stmt->execute(); $row = $stmt->get_result()->fetch_assoc(); return is_array($row) ? $row : null;
}
function v3ap_row_approvable(mysqli $db, array $row, array &$blocks): bool {
    $blocks = [];
    if ((string)($row['queue_status'] ?? '') !== 'live_submit_ready') { $blocks[] = 'Row status is not live_submit_ready.'; }
    foreach (['parser_ok', 'mapping_ok', 'future_ok'] as $gate) { if ((int)($row[$gate] ?? 0) !== 1) { $blocks[] = $gate . ' is not 1.'; } }
    foreach (['lessor_id', 'driver_id', 'vehicle_id', 'starting_point_id', 'customer_name', 'customer_phone', 'pickup_datetime', 'estimated_end_datetime', 'payload_json'] as $key) { if (trim((string)($row[$key] ?? '')) === '') { $blocks[] = $key . ' is missing.'; } }
    $minutes = v3ap_future_minutes($row['pickup_datetime'] ?? ''); if ($minutes === null || $minutes < 1) { $blocks[] = 'Pickup is not at least 1 minute in the future.'; }
    if (!v3ap_start_verified($db, (string)($row['lessor_id'] ?? ''), (string)($row['starting_point_id'] ?? ''))) { $blocks[] = 'Starting point is not verified for this lessor.'; }
    return count($blocks) === 0;
}
function v3ap_log_event(mysqli $db, int $queueId, string $dedupe, string $type, string $status, string $message, array $context = []): void {
    if (!v3ap_table_exists($db, 'pre_ride_email_v3_queue_events')) { return; }
    $json = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); if (!is_string($json)) { $json = '{}'; }
    $by = 'v3_live_approval_page';
    $stmt = $db->prepare('INSERT INTO pre_ride_email_v3_queue_events (queue_id, dedupe_key, event_type, event_status, event_message, event_context_json, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)');
    if ($stmt) { $stmt->bind_param('issssss', $queueId, $dedupe, $type, $status, $message, $json, $by); $stmt->execute(); }
}

$ctxError = null; $ctx = v3ap_context($ctxError); $error = $ctxError; $notice = ''; $dbName = '';
$schema = ['queue' => false, 'events' => false, 'approvals' => false, 'options' => false];
$rows = []; $approvalRows = [];

if ($ctx) {
    try {
        /** @var mysqli $db */
        $db = $ctx['db']->connection(); $db->set_charset('utf8mb4');
        $res = $db->query('SELECT DATABASE() AS db_name'); $tmp = $res ? $res->fetch_assoc() : null; $dbName = is_array($tmp) ? (string)($tmp['db_name'] ?? '') : '';
        $schema['queue'] = v3ap_table_exists($db, 'pre_ride_email_v3_queue');
        $schema['events'] = v3ap_table_exists($db, 'pre_ride_email_v3_queue_events');
        $schema['approvals'] = v3ap_table_exists($db, 'pre_ride_email_v3_live_submit_approvals');
        $schema['options'] = v3ap_table_exists($db, 'pre_ride_email_v3_starting_point_options');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!hash_equals(v3ap_token(), (string)($_POST['csrf'] ?? ''))) { throw new RuntimeException('Invalid CSRF token.'); }
            if (!$schema['approvals'] || !$schema['queue']) { throw new RuntimeException('Approval schema is not installed.'); }
            $action = (string)($_POST['action'] ?? ''); $queueId = (int)($_POST['queue_id'] ?? 0); $dedupe = trim((string)($_POST['dedupe_key'] ?? ''));
            $row = v3ap_get_queue_row($db, $queueId, $dedupe); if (!$row) { throw new RuntimeException('Queue row not found or dedupe mismatch.'); }
            if ($action === 'approve') {
                $phrase = trim((string)($_POST['confirm_phrase'] ?? ''));
                if ($phrase !== V3_APPROVAL_CONFIRM_PHRASE) { throw new RuntimeException('Confirmation phrase did not match.'); }
                $blocks = []; if (!v3ap_row_approvable($db, $row, $blocks)) { throw new RuntimeException('Row is not approvable: ' . implode(' | ', $blocks)); }
                $note = trim((string)($_POST['approval_note'] ?? '')); $note = mb_substr($note, 0, 1000, 'UTF-8');
                $snapshot = json_encode(['row' => $row, 'approved_from' => 'v3_live_approval_page', 'approved_at' => date(DATE_ATOM)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); if (!is_string($snapshot)) { $snapshot = '{}'; }
                $expiresAt = (string)($row['pickup_datetime'] ?? '');
                $stmt = $db->prepare('INSERT INTO pre_ride_email_v3_live_submit_approvals (queue_id, dedupe_key, approval_status, approval_scope, approved_by, approved_at, expires_at, revoked_at, approval_note, row_snapshot_json) VALUES (?, ?, ?, ?, ?, NOW(), ?, NULL, ?, ?) ON DUPLICATE KEY UPDATE dedupe_key = VALUES(dedupe_key), approval_status = VALUES(approval_status), approval_scope = VALUES(approval_scope), approved_by = VALUES(approved_by), approved_at = NOW(), expires_at = VALUES(expires_at), revoked_at = NULL, approval_note = VALUES(approval_note), row_snapshot_json = VALUES(row_snapshot_json)');
                if (!$stmt) { throw new RuntimeException('Could not prepare approval upsert: ' . $db->error); }
                $status = 'approved'; $scope = 'single_row_live_submit_handoff'; $by = 'operator_web';
                $stmt->bind_param('isssssss', $queueId, $dedupe, $status, $scope, $by, $expiresAt, $note, $snapshot); $stmt->execute();
                v3ap_log_event($db, $queueId, $dedupe, 'live_submit_operator_approved', 'approved', 'Operator approved V3 live-submit handoff for this row. No EDXEIX call was made.', ['expires_at' => $expiresAt, 'note' => $note]);
                $notice = 'Approval saved for queue row #' . $queueId . '. No EDXEIX call was made.';
            } elseif ($action === 'revoke') {
                $stmt = $db->prepare("UPDATE pre_ride_email_v3_live_submit_approvals SET approval_status = 'revoked', revoked_at = NOW() WHERE queue_id = ? AND dedupe_key = ? LIMIT 1");
                if (!$stmt) { throw new RuntimeException('Could not prepare revoke update: ' . $db->error); }
                $stmt->bind_param('is', $queueId, $dedupe); $stmt->execute();
                v3ap_log_event($db, $queueId, $dedupe, 'live_submit_operator_approval_revoked', 'revoked', 'Operator revoked V3 live-submit handoff approval. No EDXEIX call was made.', []);
                $notice = 'Approval revoked for queue row #' . $queueId . '.';
            }
        }

        if ($schema['queue'] && $schema['approvals']) {
            $rows = v3ap_fetch_all($db, "
                SELECT q.id, q.dedupe_key, q.queue_status, q.customer_name, q.driver_name, q.vehicle_plate, q.pickup_datetime,
                       q.lessor_id, q.driver_id, q.vehicle_id, q.starting_point_id, q.price_amount, q.created_at,
                       a.approval_status, a.approved_at, a.expires_at, a.revoked_at, a.approval_note
                FROM pre_ride_email_v3_queue q
                LEFT JOIN pre_ride_email_v3_live_submit_approvals a ON a.queue_id = q.id
                WHERE q.queue_status = 'live_submit_ready'
                ORDER BY q.pickup_datetime ASC, q.id ASC
                LIMIT 50
            ");
            $approvalRows = v3ap_fetch_all($db, "
                SELECT a.*, q.queue_status, q.customer_name, q.pickup_datetime
                FROM pre_ride_email_v3_live_submit_approvals a
                LEFT JOIN pre_ride_email_v3_queue q ON q.id = a.queue_id
                ORDER BY a.id DESC
                LIMIT 30
            ");
        }
    } catch (Throwable $e) { $error = $e->getMessage(); }
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>V3 Live Approval | gov.cabnet.app</title><style>
:root{--bg:#f3f6fb;--panel:#fff;--ink:#061735;--muted:#41577a;--line:#d7e1ef;--nav:#081225;--blue:#2563eb;--green:#07875a;--orange:#b85c00;--red:#b42318;--purple:#6d28d9;--soft:#f8fbff}*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--ink);font-family:Arial,Helvetica,sans-serif}.nav{background:var(--nav);color:#fff;min-height:56px;display:flex;align-items:center;gap:18px;padding:0 26px;position:sticky;top:0;z-index:5}.nav a{color:#fff;text-decoration:none;font-size:14px;white-space:nowrap}.wrap{width:min(1480px,calc(100% - 48px));margin:22px auto 60px}.card{background:var(--panel);border:1px solid var(--line);border-radius:14px;padding:18px;margin-bottom:18px;box-shadow:0 10px 26px rgba(8,18,37,.04)}.hero{border-left:7px solid var(--purple)}h1{font-size:32px;margin:0 0 10px}h2{font-size:22px;margin:0 0 14px}p{color:var(--muted);line-height:1.45}.badge{display:inline-block;padding:5px 9px;border-radius:999px;font-size:12px;font-weight:700;margin:1px 3px 1px 0;white-space:nowrap}.badge-good{background:#dcfce7;color:#166534}.badge-warn{background:#fff7ed;color:#b45309}.badge-bad{background:#fee2e2;color:#991b1b}.badge-neutral{background:#eaf1ff;color:#1e40af}.badge-purple{background:#ede9fe;color:#5b21b6}.grid{display:grid;grid-template-columns:repeat(4,1fr);gap:10px}.metric{border:1px solid var(--line);border-radius:10px;padding:14px;background:var(--soft)}.metric strong{display:block;font-size:26px}table{width:100%;border-collapse:collapse;border:1px solid var(--line);border-radius:10px;overflow:hidden;background:#fff}th,td{padding:10px;border-bottom:1px solid var(--line);text-align:left;vertical-align:top;font-size:13px}th{background:#f8fbff;font-size:12px}tr:last-child td{border-bottom:0}.mono{font-family:Consolas,Monaco,monospace}.alert{border-radius:10px;padding:12px;margin:12px 0}.alert-info{background:#eff6ff;border:1px solid #bfdbfe}.alert-warn{background:#fff7ed;border:1px solid #fed7aa}.alert-good{background:#ecfdf5;border:1px solid #bbf7d0}.btn{display:inline-block;border:0;border-radius:8px;padding:9px 13px;background:var(--blue);color:#fff;text-decoration:none;font-weight:700;font-size:13px;cursor:pointer}.btn-dark{background:#263449}.btn-red{background:#b42318}.input{width:100%;border:1px solid var(--line);border-radius:8px;padding:8px;margin:4px 0 8px}@media(max-width:900px){.grid{grid-template-columns:1fr}.wrap{width:calc(100% - 24px)}}
</style></head><body><nav class="nav"><strong>GC gov.cabnet.app</strong><a href="/ops/pre-ride-email-v3-queue-watch.php">V3 Watch</a><a href="/ops/pre-ride-email-v3-queue.php">V3 Queue</a><a href="/ops/pre-ride-email-v3-live-readiness.php">V3 Live Readiness</a><a href="/ops/pre-ride-email-v3-live-submit.php">V3 Live Submit</a><a href="/ops/pre-ride-email-v3-live-payload-audit.php">V3 Payload Audit</a><a href="/ops/pre-ride-email-v3-live-approval.php">V3 Approval</a></nav><div class="wrap">
<section class="card hero"><h1>V3 Live-Submit Operator Approval</h1><p>Per-row approval ledger for future V3 live-submit automation. This page does not submit to EDXEIX.</p><?= v3ap_badge('V3 ISOLATED','purple') ?><?= v3ap_badge('V3 DB WRITES ONLY','warn') ?><?= v3ap_badge('NO EDXEIX CALL','good') ?><?= v3ap_badge('NO AADE CALL','good') ?><?= v3ap_badge(V3_APPROVAL_PAGE_VERSION,'neutral') ?><div style="margin-top:14px"><a class="btn" href="/ops/pre-ride-email-v3-live-approval.php">Refresh</a><a class="btn btn-dark" href="/ops/pre-ride-email-v3-live-submit-gate.php">Back to Submit Gate</a></div></section>
<?php if ($notice): ?><section class="card"><div class="alert alert-good"><strong>OK:</strong> <?= v3ap_h($notice) ?></div></section><?php endif; ?>
<?php if ($error): ?><section class="card"><div class="alert alert-warn"><strong>Error:</strong> <?= v3ap_h($error) ?></div></section><?php endif; ?>
<section class="card"><h2>Status</h2><p><strong>Database:</strong> <?= v3ap_h($dbName ?: '-') ?> <?= $schema['queue'] ? v3ap_badge('queue OK','good') : v3ap_badge('queue missing','bad') ?> <?= $schema['approvals'] ? v3ap_badge('approval table OK','good') : v3ap_badge('approval table missing','bad') ?> <?= $schema['options'] ? v3ap_badge('start options OK','good') : v3ap_badge('start options missing','bad') ?></p><div class="grid"><div class="metric"><strong><?= count($rows) ?></strong><span>live_submit_ready rows</span></div><div class="metric"><strong><?= count($approvalRows) ?></strong><span>recent approval records</span></div><div class="metric"><strong><?= v3ap_h(V3_APPROVAL_CONFIRM_PHRASE) ?></strong><span>required phrase</span></div><div class="metric"><strong>no</strong><span>EDXEIX calls</span></div></div><div class="alert alert-info"><strong>Safety:</strong> approval is not a submit. It only records operator authorization for a future worker that is still gated separately.</div></section>
<section class="card"><h2>Rows awaiting approval</h2><table><thead><tr><th>ID</th><th>Pickup</th><th>Transfer</th><th>IDs</th><th>Approval</th><th>Action</th></tr></thead><tbody><?php if (!$rows): ?><tr><td colspan="6">No live_submit_ready rows currently need approval.</td></tr><?php else: foreach ($rows as $row): $minutes = v3ap_future_minutes($row['pickup_datetime'] ?? ''); $approvalValid = (($row['approval_status'] ?? '') === 'approved' && empty($row['revoked_at']) && strtotime((string)($row['expires_at'] ?? '')) > time()); ?><tr><td><a href="/ops/pre-ride-email-v3-queue.php?id=<?= (int)$row['id'] ?>"><?= (int)$row['id'] ?></a></td><td><?= v3ap_h($row['pickup_datetime'] ?? '') ?><br><span class="mono"><?= $minutes === null ? '-' : (int)$minutes . ' min' ?></span></td><td><?= v3ap_h($row['customer_name'] ?? '') ?><br><?= v3ap_h(($row['driver_name'] ?? '') . ' / ' . ($row['vehicle_plate'] ?? '')) ?></td><td class="mono">Lessor: <?= v3ap_h($row['lessor_id'] ?? '') ?><br>Driver: <?= v3ap_h($row['driver_id'] ?? '') ?><br>Vehicle: <?= v3ap_h($row['vehicle_id'] ?? '') ?><br>Start: <?= v3ap_h($row['starting_point_id'] ?? '') ?></td><td><?= $approvalValid ? v3ap_badge('approved valid','good') : v3ap_badge('not approved','warn') ?><br><span class="mono"><?= v3ap_h($row['approved_at'] ?? '') ?></span></td><td><form method="post"><input type="hidden" name="csrf" value="<?= v3ap_h(v3ap_token()) ?>"><input type="hidden" name="queue_id" value="<?= (int)$row['id'] ?>"><input type="hidden" name="dedupe_key" value="<?= v3ap_h($row['dedupe_key'] ?? '') ?>"><input type="hidden" name="action" value="approve"><label>Type phrase exactly:</label><input class="input mono" name="confirm_phrase" placeholder="<?= v3ap_h(V3_APPROVAL_CONFIRM_PHRASE) ?>"><label>Note</label><input class="input" name="approval_note" placeholder="optional"><button class="btn" type="submit">Approve V3 live-submit handoff</button></form><?php if (($row['approval_status'] ?? '') === 'approved'): ?><form method="post" style="margin-top:8px"><input type="hidden" name="csrf" value="<?= v3ap_h(v3ap_token()) ?>"><input type="hidden" name="queue_id" value="<?= (int)$row['id'] ?>"><input type="hidden" name="dedupe_key" value="<?= v3ap_h($row['dedupe_key'] ?? '') ?>"><input type="hidden" name="action" value="revoke"><button class="btn btn-red" type="submit">Revoke approval</button></form><?php endif; ?></td></tr><?php endforeach; endif; ?></tbody></table></section>
<section class="card"><h2>Recent approval records</h2><table><thead><tr><th>ID</th><th>Queue</th><th>Status</th><th>Pickup</th><th>Approved</th><th>Expires</th><th>Revoked</th><th>Note</th></tr></thead><tbody><?php if (!$approvalRows): ?><tr><td colspan="8">No approval records yet.</td></tr><?php else: foreach ($approvalRows as $row): ?><tr><td><?= (int)$row['id'] ?></td><td><?= (int)$row['queue_id'] ?><br><?= v3ap_h($row['customer_name'] ?? '') ?></td><td><?= v3ap_badge((string)$row['approval_status'], ($row['approval_status'] ?? '') === 'approved' ? 'good' : 'bad') ?></td><td><?= v3ap_h($row['pickup_datetime'] ?? '') ?></td><td><?= v3ap_h($row['approved_at'] ?? '') ?><br><?= v3ap_h($row['approved_by'] ?? '') ?></td><td><?= v3ap_h($row['expires_at'] ?? '') ?></td><td><?= v3ap_h($row['revoked_at'] ?? '') ?></td><td><?= v3ap_h($row['approval_note'] ?? '') ?></td></tr><?php endforeach; endif; ?></tbody></table></section>
</div></body></html>
