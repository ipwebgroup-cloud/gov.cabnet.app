<?php
/**
 * gov.cabnet.app — EDXEIX Session Readiness v0.1
 *
 * Read-only readiness page for the future server-side/mobile EDXEIX connector.
 * This page does not call EDXEIX, does not submit, and does not display secrets.
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

function esr_h(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function esr_badge(string $text, string $type = 'neutral'): string
{
    $type = in_array($type, ['good', 'warn', 'bad', 'neutral'], true) ? $type : 'neutral';
    if (function_exists('opsui_badge')) {
        return opsui_badge($text, $type);
    }
    return '<span class="badge badge-' . esr_h($type) . '">' . esr_h($text) . '</span>';
}

function esr_shell_begin(): void
{
    if (function_exists('opsui_shell_begin')) {
        opsui_shell_begin([
            'title' => 'EDXEIX Session Readiness',
            'page_title' => 'EDXEIX Session Readiness',
            'active_section' => 'Mobile Submit',
            'breadcrumbs' => 'Αρχική / Mobile Submit / EDXEIX Session Readiness',
            'safe_notice' => 'Read-only connector readiness page. It does not call EDXEIX, does not submit forms, and does not display cookies, tokens, passwords, or session values.',
            'force_safe_notice' => true,
        ]);
        return;
    }

    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>EDXEIX Session Readiness | gov.cabnet.app</title><style>body{font-family:Arial,Helvetica,sans-serif;background:#eef1f6;color:#20293a;margin:0;padding:18px}.card{background:#fff;border:1px solid #d8dde7;border-radius:8px;padding:16px;margin:0 0 16px}.badge{display:inline-block;padding:6px 10px;border-radius:12px;background:#e9edf7;margin:2px}.badge-good{background:#dbf0dc;color:#2d7b37}.badge-warn{background:#f8ead3;color:#9a5a00}.badge-bad{background:#f8dedd;color:#b13c35}.btn{display:inline-block;background:#4f5ea7;color:#fff;padding:10px 13px;border-radius:5px;text-decoration:none;font-weight:700}.small{font-size:13px;color:#667085}code{background:#eef2ff;padding:2px 5px;border-radius:5px}.table-wrap{overflow:auto}table{width:100%;border-collapse:collapse}th,td{border-bottom:1px solid #eef1f5;padding:10px;text-align:left}</style></head><body>';
}

function esr_shell_end(): void
{
    if (function_exists('opsui_shell_end')) {
        opsui_shell_end();
        return;
    }
    echo '</body></html>';
}

function esr_app_context(?string &$error = null): ?array
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
        $loadError = 'Private app bootstrap was not found.';
        $error = $loadError;
        return null;
    }

    try {
        $ctx = require $bootstrap;
        if (!is_array($ctx)) {
            throw new RuntimeException('Private app bootstrap did not return an array.');
        }
        $error = null;
        return $ctx;
    } catch (Throwable $e) {
        $loadError = $e->getMessage();
        $error = $loadError;
        return null;
    }
}

function esr_db(?string &$error = null): ?mysqli
{
    $ctx = esr_app_context($error);
    if (!$ctx || !isset($ctx['db']) || !method_exists($ctx['db'], 'connection')) {
        $error = $error ?: 'DB context is unavailable.';
        return null;
    }
    try {
        $db = $ctx['db']->connection();
        return $db instanceof mysqli ? $db : null;
    } catch (Throwable $e) {
        $error = $e->getMessage();
        return null;
    }
}

function esr_table_exists(mysqli $db, string $table): bool
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

/** @return array<string,bool> */
function esr_columns(mysqli $db, string $table): array
{
    $out = [];
    try {
        $stmt = $db->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
        $stmt->bind_param('s', $table);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $out[(string)$row['COLUMN_NAME']] = true;
        }
    } catch (Throwable) {
        return [];
    }
    return $out;
}

function esr_q(string $identifier): string
{
    return '`' . str_replace('`', '``', $identifier) . '`';
}

/** @return array<string,mixed>|null */
function esr_latest_capture(mysqli $db): ?array
{
    if (!esr_table_exists($db, 'ops_edxeix_submit_captures')) {
        return null;
    }
    $cols = esr_columns($db, 'ops_edxeix_submit_captures');
    $order = isset($cols['id']) ? 'id' : (isset($cols['created_at']) ? 'created_at' : '1');
    try {
        $res = $db->query('SELECT * FROM ops_edxeix_submit_captures ORDER BY ' . ($order === '1' ? '1' : esr_q($order)) . ' DESC LIMIT 1');
        $row = $res ? $res->fetch_assoc() : null;
        return is_array($row) ? $row : null;
    } catch (Throwable) {
        return null;
    }
}

function esr_value(?array $row, string $key): string
{
    return trim((string)($row[$key] ?? ''));
}

function esr_check(bool $ok, string $label, string $good = 'Ready', string $bad = 'Missing'): array
{
    return ['ok' => $ok, 'label' => $label, 'status' => $ok ? $good : $bad, 'type' => $ok ? 'good' : 'warn'];
}

function esr_route_status(string $route): array
{
    $path = dirname(__DIR__) . $route;
    $path = str_replace('/ops/../ops/', '/ops/', $path);
    if (is_file($path)) {
        return ['route' => $route, 'exists' => true, 'mtime' => date('Y-m-d H:i:s', (int)filemtime($path)), 'size' => filesize($path) ?: 0];
    }
    return ['route' => $route, 'exists' => false, 'mtime' => '', 'size' => 0];
}

$dbError = null;
$db = esr_db($dbError);
$capture = $db ? esr_latest_capture($db) : null;
$captureExists = $capture !== null;

$method = strtoupper(esr_value($capture, 'form_method'));
$actionHost = esr_value($capture, 'action_host');
$actionPath = esr_value($capture, 'action_path');
$csrfName = esr_value($capture, 'csrf_field_name');
$required = esr_value($capture, 'required_field_names');
$selects = esr_value($capture, 'select_field_names');
$coords = trim(implode(' ', array_filter([
    esr_value($capture, 'coordinate_field_names'),
    esr_value($capture, 'map_lat_field_name'),
    esr_value($capture, 'map_lng_field_name'),
    esr_value($capture, 'map_address_field_name'),
])));
$notes = esr_value($capture, 'notes');

$checks = [
    esr_check($db instanceof mysqli, 'Private DB context available', 'Available', 'Unavailable'),
    esr_check($captureExists, 'Latest sanitized submit capture exists', 'Available', 'Missing'),
    esr_check(in_array($method, ['POST', 'PUT', 'PATCH'], true), 'Form method recorded and valid', $method !== '' ? $method : 'Ready', 'Missing/invalid'),
    esr_check($actionHost !== '', 'Action host recorded', $actionHost !== '' ? $actionHost : 'Ready', 'Missing'),
    esr_check($actionPath !== '', 'Action path recorded', $actionPath !== '' ? $actionPath : 'Ready', 'Missing'),
    esr_check($required !== '', 'Required field names recorded', 'Recorded', 'Missing'),
    esr_check($selects !== '', 'Select/dropdown field names recorded', 'Recorded', 'Missing'),
    esr_check($csrfName !== '', 'CSRF field name recorded without token value', 'Name recorded', 'Missing'),
    esr_check($coords !== '', 'Coordinate/map field names recorded', 'Recorded', 'Missing'),
    esr_check($notes !== '', 'Submit behavior notes recorded', 'Recorded', 'Missing'),
];

$readyCount = 0;
foreach ($checks as $check) {
    if (!empty($check['ok'])) { $readyCount++; }
}
$totalChecks = count($checks);
$connectorResearchReady = $readyCount === $totalChecks;

$routes = [
    '/ops/mobile-submit-dev.php',
    '/ops/mobile-submit-readiness.php',
    '/ops/edxeix-submit-research.php',
    '/ops/edxeix-submit-capture.php',
    '/ops/edxeix-submit-dry-run.php',
    '/ops/edxeix-submit-preflight-gate.php',
    '/ops/mapping-resolver-test.php',
    '/ops/mapping-center.php',
];
$routeRows = array_map('esr_route_status', $routes);

esr_shell_begin();
?>
<style>
.esr-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}.esr-two{display:grid;grid-template-columns:minmax(0,1fr) minmax(320px,.65fr);gap:18px}.esr-card{background:#fff;border:1px solid #d8dde7;border-radius:4px;padding:18px 20px;box-shadow:0 6px 18px rgba(26,33,52,.06);margin-bottom:18px}.esr-card h2{margin-top:0}.esr-check{display:flex;gap:10px;justify-content:space-between;align-items:center;border:1px solid #d8dde7;background:#fff;border-radius:4px;padding:11px 12px;margin:8px 0}.esr-check strong{display:block}.esr-pre{white-space:pre-wrap;background:#0b1220;color:#dbeafe;border-radius:4px;padding:14px;overflow:auto;font-size:13px;line-height:1.45}.esr-actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:12px}.esr-warning{border-left:6px solid #b45309}.esr-good{border-left:6px solid #059669}.esr-bad{border-left:6px solid #b42318}@media(max-width:980px){.esr-grid,.esr-two{grid-template-columns:1fr}.esr-actions .btn{width:100%;text-align:center}.esr-check{display:block}.esr-check span{margin-top:7px}}
</style>

<section class="card hero warn">
    <h1>EDXEIX Session Readiness</h1>
    <p>This page tracks whether we have enough sanitized EDXEIX form/session knowledge to design the future server-side mobile submit connector. It is read-only and does not submit anything.</p>
    <div>
        <?= esr_badge('READ ONLY', 'good') ?>
        <?= esr_badge('NO EDXEIX CALL', 'good') ?>
        <?= esr_badge('NO LIVE SUBMIT', 'good') ?>
        <?= esr_badge('NO SECRET OUTPUT', 'good') ?>
    </div>
</section>

<section class="esr-grid">
    <div class="metric"><strong><?= esr_h((string)$readyCount) ?>/<?= esr_h((string)$totalChecks) ?></strong><span>Readiness checks passed</span></div>
    <div class="metric"><strong><?= $captureExists ? esr_h((string)($capture['id'] ?? 'latest')) : 'NONE' ?></strong><span>Latest sanitized capture</span></div>
    <div class="metric"><strong><?= esr_h($method !== '' ? $method : '-') ?></strong><span>Form method</span></div>
    <div class="metric"><strong><?= $connectorResearchReady ? 'READY' : 'NOT READY' ?></strong><span>Connector research state</span></div>
</section>

<section class="esr-two">
    <div>
        <section class="esr-card <?= $connectorResearchReady ? 'esr-good' : 'esr-warning' ?>">
            <h2>Connector readiness checklist</h2>
            <?php foreach ($checks as $check): ?>
                <div class="esr-check">
                    <strong><?= esr_h($check['label']) ?></strong>
                    <?= esr_badge((string)$check['status'], (string)$check['type']) ?>
                </div>
            <?php endforeach; ?>
        </section>

        <section class="esr-card">
            <h2>Latest sanitized capture metadata</h2>
            <?php if (!$captureExists): ?>
                <p class="warnline"><strong>No capture found.</strong> Use the sanitized capture page to record field names only. Never store cookie/session/CSRF token values.</p>
                <div class="esr-actions"><a class="btn" href="/ops/edxeix-submit-capture.php">Open Submit Capture</a></div>
            <?php else: ?>
                <div class="table-wrap">
                    <table>
                        <tbody>
                        <tr><th>Capture ID</th><td><?= esr_h((string)($capture['id'] ?? '')) ?></td></tr>
                        <tr><th>Created</th><td><?= esr_h((string)($capture['created_at'] ?? '')) ?></td></tr>
                        <tr><th>Method</th><td><?= esr_h($method) ?></td></tr>
                        <tr><th>Action host</th><td><?= esr_h($actionHost) ?></td></tr>
                        <tr><th>Action path</th><td><?= esr_h($actionPath) ?></td></tr>
                        <tr><th>CSRF field name</th><td><?= esr_h($csrfName !== '' ? $csrfName : '-') ?></td></tr>
                        <tr><th>Required fields</th><td><code><?= esr_h($required !== '' ? $required : '-') ?></code></td></tr>
                        <tr><th>Select fields</th><td><code><?= esr_h($selects !== '' ? $selects : '-') ?></code></td></tr>
                        <tr><th>Coordinate/map fields</th><td><code><?= esr_h($coords !== '' ? $coords : '-') ?></code></td></tr>
                        </tbody>
                    </table>
                </div>
                <p class="small">Only field names and sanitized notes are shown here. Values such as cookies, sessions, passwords, and CSRF token values must never be recorded.</p>
            <?php endif; ?>
        </section>

        <section class="esr-card">
            <h2>Related route status</h2>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Route</th><th>Status</th><th>Modified</th><th>Size</th></tr></thead>
                    <tbody>
                    <?php foreach ($routeRows as $row): ?>
                        <tr>
                            <td><a href="<?= esr_h($row['route']) ?>"><?= esr_h($row['route']) ?></a></td>
                            <td><?= esr_badge($row['exists'] ? 'PRESENT' : 'MISSING', $row['exists'] ? 'good' : 'warn') ?></td>
                            <td><?= esr_h($row['mtime']) ?></td>
                            <td><?= esr_h(number_format((float)$row['size'])) ?> bytes</td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <aside>
        <section class="esr-card esr-bad">
            <h2>Live submit status</h2>
            <p><strong>Server-side live EDXEIX submit remains disabled.</strong></p>
            <div><?= esr_badge('CONNECTOR DISABLED', 'bad') ?> <?= esr_badge('NO SUBMIT BUTTON', 'good') ?></div>
            <p class="small">A later phase may add a controlled connector, but only after Andreas explicitly approves live submit behavior.</p>
        </section>

        <section class="esr-card">
            <h2>Next required research</h2>
            <ul class="list">
                <li>Confirm exact EDXEIX success response behavior.</li>
                <li>Confirm validation error response behavior.</li>
                <li>Confirm if CSRF token must come from an authenticated browser/session bridge.</li>
                <li>Confirm coordinate/map fields are complete and non-default.</li>
                <li>Confirm duplicate prevention strategy before any submit.</li>
            </ul>
        </section>

        <section class="esr-card">
            <h2>Recommended next phase</h2>
            <p><strong>Phase 52 — Mobile Submit Readiness Integration v2</strong></p>
            <p class="small">Improve <code>/ops/mobile-submit-readiness.php</code> so it links directly to this readiness page and displays connector readiness state beside the dry-run/preflight results.</p>
        </section>

        <section class="esr-card">
            <h2>Quick links</h2>
            <div class="esr-actions">
                <a class="btn" href="/ops/mobile-submit-readiness.php">Mobile Readiness</a>
                <a class="btn dark" href="/ops/edxeix-submit-capture.php">Submit Capture</a>
                <a class="btn dark" href="/ops/edxeix-submit-dry-run.php">Dry-Run Builder</a>
                <a class="btn dark" href="/ops/edxeix-submit-preflight-gate.php">Preflight Gate</a>
                <a class="btn dark" href="/ops/mapping-center.php">Mapping Center</a>
            </div>
        </section>
    </aside>
</section>

<?php esr_shell_end(); ?>
