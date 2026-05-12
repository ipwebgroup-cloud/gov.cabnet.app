<?php
/**
 * gov.cabnet.app — Mobile Submit Gates
 *
 * Read-only gate matrix for the future mobile/server-side EDXEIX submit workflow.
 * No live submit, no external calls, no workflow writes.
 */

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Robots-Tag: noindex,nofollow', true);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$shell = __DIR__ . '/_shell.php';
if (is_file($shell)) {
    require_once $shell;
}

function msg_h(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function msg_badge(string $text, string $type = 'neutral'): string
{
    $type = in_array($type, ['good', 'warn', 'bad', 'neutral'], true) ? $type : 'neutral';
    if (function_exists('opsui_badge')) {
        return opsui_badge($text, $type);
    }
    return '<span class="badge badge-' . msg_h($type) . '">' . msg_h($text) . '</span>';
}

function msg_shell_begin(): void
{
    if (function_exists('opsui_shell_begin')) {
        opsui_shell_begin([
            'title' => 'Mobile Submit Gates',
            'page_title' => 'Mobile Submit Gates',
            'active_section' => 'Mobile Submit',
            'breadcrumbs' => 'Αρχική / Mobile / Submit Gates',
            'safe_notice' => 'Read-only gate matrix. This page does not submit to EDXEIX, call external services, or write workflow data.',
            'force_safe_notice' => true,
        ]);
        return;
    }
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Mobile Submit Gates</title><style>body{font-family:Arial,Helvetica,sans-serif;background:#f3f6fb;color:#07152f;margin:0;padding:20px}.card{background:#fff;border:1px solid #d8dde7;border-radius:8px;padding:16px;margin:0 0 16px}.badge{display:inline-block;padding:5px 9px;border-radius:999px;background:#eaf1ff;color:#1e40af;font-size:12px;font-weight:700}.badge-good{background:#dcfce7;color:#166534}.badge-warn{background:#fff7ed;color:#b45309}.badge-bad{background:#fee2e2;color:#991b1b}.table-wrap{overflow:auto}table{width:100%;border-collapse:collapse}th,td{padding:9px;border-bottom:1px solid #e5e7eb;text-align:left}code{background:#eef2ff;padding:2px 5px;border-radius:4px}.btn{display:inline-block;background:#2563eb;color:#fff;padding:9px 12px;border-radius:6px;text-decoration:none;font-weight:700}.grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}@media(max-width:900px){.grid{grid-template-columns:1fr}}</style></head><body>';
}

function msg_shell_end(): void
{
    if (function_exists('opsui_shell_end')) {
        opsui_shell_end();
        return;
    }
    echo '</body></html>';
}

function msg_path_status(string $path): array
{
    return [
        'exists' => is_file($path),
        'readable' => is_file($path) && is_readable($path),
        'mtime' => is_file($path) ? date('Y-m-d H:i:s', (int)filemtime($path)) : '',
        'size' => is_file($path) ? (int)filesize($path) : 0,
    ];
}

function msg_dir_root(): string
{
    return dirname(__DIR__, 3);
}

function msg_app_context(?string &$error = null): ?array
{
    $bootstrap = msg_dir_root() . '/gov.cabnet.app_app/src/bootstrap.php';
    if (!is_file($bootstrap)) {
        $error = 'Private bootstrap not found.';
        return null;
    }
    try {
        $ctx = require $bootstrap;
        if (!is_array($ctx)) {
            throw new RuntimeException('Bootstrap did not return context array.');
        }
        $error = null;
        return $ctx;
    } catch (Throwable $e) {
        $error = $e->getMessage();
        return null;
    }
}

function msg_table_exists(mysqli $db, string $table): bool
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

function msg_scalar(mysqli $db, string $sql): ?string
{
    try {
        $res = $db->query($sql);
        if (!$res) { return null; }
        $row = $res->fetch_array(MYSQLI_NUM);
        return isset($row[0]) ? (string)$row[0] : null;
    } catch (Throwable) {
        return null;
    }
}

function msg_latest_capture(mysqli $db): array
{
    if (!msg_table_exists($db, 'ops_edxeix_submit_captures')) {
        return ['exists' => false, 'row' => null, 'complete' => false, 'missing' => ['table_missing']];
    }
    try {
        $res = $db->query('SELECT * FROM ops_edxeix_submit_captures ORDER BY id DESC LIMIT 1');
        $row = $res ? $res->fetch_assoc() : null;
        if (!is_array($row)) {
            return ['exists' => true, 'row' => null, 'complete' => false, 'missing' => ['no_capture_rows']];
        }
        $missing = [];
        foreach (['form_method', 'action_host', 'action_path', 'required_field_names'] as $key) {
            if (trim((string)($row[$key] ?? '')) === '') {
                $missing[] = $key;
            }
        }
        return ['exists' => true, 'row' => $row, 'complete' => $missing === [], 'missing' => $missing];
    } catch (Throwable $e) {
        return ['exists' => true, 'row' => null, 'complete' => false, 'missing' => ['query_error: ' . $e->getMessage()]];
    }
}

function msg_gate_row(string $gate, bool $ok, string $statusText, string $details, string $route = ''): string
{
    $badge = $ok ? msg_badge('PASS', 'good') : msg_badge('NEEDS WORK', 'warn');
    $routeHtml = $route !== '' ? '<br><a href="' . msg_h($route) . '">' . msg_h($route) . '</a>' : '';
    return '<tr><td><strong>' . msg_h($gate) . '</strong>' . $routeHtml . '</td><td>' . $badge . '</td><td>' . msg_h($statusText) . '</td><td>' . msg_h($details) . '</td></tr>';
}

$root = msg_dir_root();
$publicRoot = $root . '/public_html/gov.cabnet.app';
$appRoot = $root . '/gov.cabnet.app_app';

$classes = [
    'BoltPreRideEmailParser' => $appRoot . '/src/BoltMail/BoltPreRideEmailParser.php',
    'EdxeixMappingLookup' => $appRoot . '/src/BoltMail/EdxeixMappingLookup.php',
    'MaildirPreRideEmailLoader' => $appRoot . '/src/BoltMail/MaildirPreRideEmailLoader.php',
    'EdxeixSubmitPreflightGate' => $appRoot . '/src/Edxeix/EdxeixSubmitPreflightGate.php',
    'EdxeixSubmitConnector' => $appRoot . '/src/Edxeix/EdxeixSubmitConnector.php',
    'EdxeixSubmitPayloadValidator' => $appRoot . '/src/Edxeix/EdxeixSubmitPayloadValidator.php',
];

$routes = [
    '/ops/mobile-submit-center.php' => $publicRoot . '/ops/mobile-submit-center.php',
    '/ops/mobile-submit-dev.php' => $publicRoot . '/ops/mobile-submit-dev.php',
    '/ops/mobile-submit-readiness.php' => $publicRoot . '/ops/mobile-submit-readiness.php',
    '/ops/edxeix-submit-capture.php' => $publicRoot . '/ops/edxeix-submit-capture.php',
    '/ops/edxeix-submit-dry-run.php' => $publicRoot . '/ops/edxeix-submit-dry-run.php',
    '/ops/edxeix-submit-preflight-gate.php' => $publicRoot . '/ops/edxeix-submit-preflight-gate.php',
    '/ops/edxeix-session-readiness.php' => $publicRoot . '/ops/edxeix-session-readiness.php',
    '/ops/edxeix-submit-connector-dev.php' => $publicRoot . '/ops/edxeix-submit-connector-dev.php',
    '/ops/edxeix-submit-payload-validator.php' => $publicRoot . '/ops/edxeix-submit-payload-validator.php',
    '/ops/mapping-resolver-test.php' => $publicRoot . '/ops/mapping-resolver-test.php',
    '/ops/mapping-exceptions.php' => $publicRoot . '/ops/mapping-exceptions.php',
];

$dbError = null;
$ctx = msg_app_context($dbError);
$db = null;
if (is_array($ctx) && isset($ctx['db']) && method_exists($ctx['db'], 'connection')) {
    try { $db = $ctx['db']->connection(); } catch (Throwable $e) { $dbError = $e->getMessage(); }
}

$tables = [];
$capture = ['exists' => false, 'row' => null, 'complete' => false, 'missing' => ['db_unavailable']];
if ($db instanceof mysqli) {
    foreach (['ops_edxeix_submit_captures', 'mapping_lessor_starting_points', 'mapping_drivers', 'mapping_vehicles', 'mapping_verification_status'] as $table) {
        $tables[$table] = msg_table_exists($db, $table);
    }
    $capture = msg_latest_capture($db);
}

$requiredClassOk = true;
foreach ($classes as $path) { if (!is_file($path) || !is_readable($path)) { $requiredClassOk = false; } }
$routeOk = true;
foreach ($routes as $path) { if (!is_file($path) || !is_readable($path)) { $routeOk = false; } }
$tableOk = ($db instanceof mysqli) && !empty($tables['ops_edxeix_submit_captures']) && !empty($tables['mapping_lessor_starting_points']);
$captureOk = !empty($capture['complete']);

msg_shell_begin();
?>
<style>
.msg-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}.msg-card{background:#fff;border:1px solid #d8dde7;border-radius:4px;padding:16px}.msg-card h2,.msg-card h3{margin-top:0}.msg-route-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.msg-good{border-left:5px solid #16a34a}.msg-warn{border-left:5px solid #d97706}.msg-bad{border-left:5px solid #dc2626}.msg-code{white-space:pre-wrap;background:#0f172a;color:#e2e8f0;border-radius:6px;padding:12px;font-family:Consolas,Menlo,monospace;font-size:12px;overflow:auto}.msg-actions{display:flex;gap:10px;flex-wrap:wrap}.msg-actions a{display:inline-block}@media(max-width:1100px){.msg-grid,.msg-route-grid{grid-template-columns:1fr}.msg-card{padding:14px}}
</style>

<section class="card hero neutral">
    <h1>Mobile Submit Gates</h1>
    <p>This page defines the required gates before the future mobile/server-side EDXEIX submitter can move beyond dry-run. It is intentionally read-only and does not submit anything.</p>
    <div>
        <?= msg_badge('READ ONLY', 'good') ?>
        <?= msg_badge('NO LIVE SUBMIT', 'good') ?>
        <?= msg_badge('NO EDXEIX CALL', 'good') ?>
        <?= msg_badge('NO DB WRITE', 'good') ?>
    </div>
</section>

<section class="msg-grid">
    <div class="msg-card <?= $requiredClassOk ? 'msg-good' : 'msg-warn' ?>">
        <h3>Support classes</h3>
        <p><?= $requiredClassOk ? msg_badge('READY', 'good') : msg_badge('INCOMPLETE', 'warn') ?></p>
        <p class="small">Parser, mapping lookup, preflight, connector, and validator files.</p>
    </div>
    <div class="msg-card <?= $routeOk ? 'msg-good' : 'msg-warn' ?>">
        <h3>Dev routes</h3>
        <p><?= $routeOk ? msg_badge('READY', 'good') : msg_badge('INCOMPLETE', 'warn') ?></p>
        <p class="small">Mobile submit and EDXEIX research pages.</p>
    </div>
    <div class="msg-card <?= $tableOk ? 'msg-good' : 'msg-warn' ?>">
        <h3>Required tables</h3>
        <p><?= $tableOk ? msg_badge('READY', 'good') : msg_badge('NEEDS CHECK', 'warn') ?></p>
        <p class="small">Submit capture and lessor-specific starting point tables.</p>
    </div>
    <div class="msg-card <?= $captureOk ? 'msg-good' : 'msg-warn' ?>">
        <h3>Submit capture</h3>
        <p><?= $captureOk ? msg_badge('COMPLETE', 'good') : msg_badge('INCOMPLETE', 'warn') ?></p>
        <p class="small">Latest sanitized EDXEIX form capture metadata.</p>
    </div>
</section>

<section class="card">
    <h2>Gate matrix</h2>
    <div class="table-wrap"><table>
        <thead><tr><th>Gate</th><th>Status</th><th>Current state</th><th>Why it matters</th></tr></thead>
        <tbody>
            <?= msg_gate_row('Mobile dev route exists', is_file($routes['/ops/mobile-submit-dev.php']), is_file($routes['/ops/mobile-submit-dev.php']) ? 'Route present' : 'Route missing', 'Base mobile-first review page.', '/ops/mobile-submit-dev.php') ?>
            <?= msg_gate_row('Integrated readiness route exists', is_file($routes['/ops/mobile-submit-readiness.php']), is_file($routes['/ops/mobile-submit-readiness.php']) ? 'Route present' : 'Route missing', 'One screen for parse → mapping → capture → preflight → dry-run.', '/ops/mobile-submit-readiness.php') ?>
            <?= msg_gate_row('Mapping governance available', is_file($routes['/ops/mapping-resolver-test.php']) && is_file($routes['/ops/mapping-exceptions.php']), 'Resolver/exceptions route check', 'Prevents wrong lessor/driver/vehicle/starting-point combinations.', '/ops/mapping-resolver-test.php') ?>
            <?= msg_gate_row('Lessor-specific starting point table available', !empty($tables['mapping_lessor_starting_points']), !empty($tables['mapping_lessor_starting_points']) ? 'Table present' : 'Table missing', 'Prevents fallback to unsafe global starting points.') ?>
            <?= msg_gate_row('Sanitized submit capture table available', !empty($tables['ops_edxeix_submit_captures']), !empty($tables['ops_edxeix_submit_captures']) ? 'Table present' : 'Table missing', 'Stores safe form metadata only, never cookies/tokens.') ?>
            <?= msg_gate_row('Latest submit capture complete', $captureOk, $captureOk ? 'Latest capture has method/action/required fields' : 'Missing: ' . implode(', ', (array)$capture['missing']), 'Needed before any server-side connector can be designed safely.', '/ops/edxeix-submit-capture.php') ?>
            <?= msg_gate_row('Preflight gate class available', is_file($classes['EdxeixSubmitPreflightGate']), is_file($classes['EdxeixSubmitPreflightGate']) ? 'Class present' : 'Class missing', 'Central safety evaluator for future submit.', '/ops/edxeix-submit-preflight-gate.php') ?>
            <?= msg_gate_row('Connector remains dry-run only', is_file($classes['EdxeixSubmitConnector']), 'Live connector intentionally disabled', 'No mobile live submit until explicitly approved.', '/ops/edxeix-submit-connector-dev.php') ?>
            <?= msg_gate_row('Payload validator available', is_file($classes['EdxeixSubmitPayloadValidator']), is_file($classes['EdxeixSubmitPayloadValidator']) ? 'Class present' : 'Class missing', 'Checks request shape before any live connector work.', '/ops/edxeix-submit-payload-validator.php') ?>
            <?= msg_gate_row('Live submit blocked', true, 'Blocked by design', 'Final operator confirmation, map point, active session bridge, and explicit approval are still required.') ?>
        </tbody>
    </table></div>
</section>

<section class="msg-route-grid">
    <div class="msg-card">
        <h2>Route status</h2>
        <div class="table-wrap"><table>
            <thead><tr><th>Route</th><th>Status</th><th>Modified</th></tr></thead>
            <tbody>
            <?php foreach ($routes as $route => $path): $s = msg_path_status($path); ?>
                <tr>
                    <td><a href="<?= msg_h($route) ?>"><?= msg_h($route) ?></a></td>
                    <td><?= $s['readable'] ? msg_badge('OK', 'good') : msg_badge('MISSING', 'warn') ?></td>
                    <td><?= msg_h($s['mtime']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    </div>
    <div class="msg-card">
        <h2>Private support files</h2>
        <div class="table-wrap"><table>
            <thead><tr><th>File</th><th>Status</th><th>Modified</th></tr></thead>
            <tbody>
            <?php foreach ($classes as $label => $path): $s = msg_path_status($path); ?>
                <tr>
                    <td><code><?= msg_h($label) ?></code></td>
                    <td><?= $s['readable'] ? msg_badge('OK', 'good') : msg_badge('MISSING', 'warn') ?></td>
                    <td><?= msg_h($s['mtime']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    </div>
</section>

<section class="msg-route-grid">
    <div class="msg-card">
        <h2>Latest sanitized submit capture</h2>
        <?php if (!($db instanceof mysqli)): ?>
            <p class="badline"><strong>DB unavailable:</strong> <?= msg_h((string)$dbError) ?></p>
        <?php elseif (!$capture['exists']): ?>
            <p><?= msg_badge('TABLE MISSING', 'warn') ?> <code>ops_edxeix_submit_captures</code> is not available.</p>
        <?php elseif (!is_array($capture['row'])): ?>
            <p><?= msg_badge('NO ROWS', 'warn') ?> No sanitized capture row exists yet.</p>
        <?php else: $row = $capture['row']; ?>
            <p><?= $captureOk ? msg_badge('COMPLETE', 'good') : msg_badge('INCOMPLETE', 'warn') ?></p>
            <ul class="list">
                <li>ID: <strong><?= msg_h((string)($row['id'] ?? '')) ?></strong></li>
                <li>Method: <strong><?= msg_h((string)($row['form_method'] ?? '')) ?></strong></li>
                <li>Action: <strong><?= msg_h((string)($row['action_host'] ?? '')) ?><?= msg_h((string)($row['action_path'] ?? '')) ?></strong></li>
                <li>CSRF field name: <strong><?= msg_h((string)($row['csrf_field_name'] ?? '')) ?></strong> <span class="small">value is never stored/displayed</span></li>
                <li>Missing: <strong><?= msg_h(implode(', ', (array)$capture['missing']) ?: 'none') ?></strong></li>
            </ul>
        <?php endif; ?>
    </div>
    <div class="msg-card">
        <h2>Next safe development step</h2>
        <ol class="list">
            <li>Use <a href="/ops/edxeix-submit-capture.php">Submit Capture</a> to keep sanitized form metadata current.</li>
            <li>Use <a href="/ops/mobile-submit-readiness.php">Mobile Submit Readiness</a> with a real future email preview.</li>
            <li>Use <a href="/ops/edxeix-submit-payload-validator.php">Payload Validator</a> to confirm request shape.</li>
            <li>Keep live submit disabled until explicit approval and active session bridge design are reviewed.</li>
        </ol>
        <div class="msg-actions" style="margin-top:12px;">
            <a class="btn" href="/ops/mobile-submit-center.php">Mobile Submit Center</a>
            <a class="btn dark" href="/ops/edxeix-session-readiness.php">Session Readiness</a>
            <a class="btn dark" href="/ops/mapping-exceptions.php">Mapping Exceptions</a>
        </div>
    </div>
</section>
<?php msg_shell_end(); ?>
