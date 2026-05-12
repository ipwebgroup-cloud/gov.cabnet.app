<?php
/**
 * gov.cabnet.app — Mobile Submit QA Dashboard v0.1 / Phase 62
 *
 * Read-only status dashboard for the future mobile/server-side EDXEIX submit workflow.
 * It does not submit to EDXEIX, call Bolt, call AADE, write workflow rows, or modify
 * the production pre-ride tool.
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

function msq_h(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function msq_badge(string $text, string $type = 'neutral'): string
{
    $type = in_array($type, ['good', 'warn', 'bad', 'neutral'], true) ? $type : 'neutral';
    if (function_exists('opsui_badge')) {
        return opsui_badge($text, $type);
    }
    return '<span class="badge badge-' . msq_h($type) . '">' . msq_h($text) . '</span>';
}

function msq_bool_badge(bool $ok, string $yes = 'PASS', string $no = 'NEEDS WORK'): string
{
    return msq_badge($ok ? $yes : $no, $ok ? 'good' : 'warn');
}

function msq_metric(string $value, string $label): string
{
    if (function_exists('opsui_metric')) {
        return opsui_metric($value, $label);
    }
    return '<div class="metric"><strong>' . msq_h($value) . '</strong><span>' . msq_h($label) . '</span></div>';
}

function msq_shell_begin(): void
{
    if (function_exists('opsui_shell_begin')) {
        opsui_shell_begin([
            'title' => 'Mobile Submit QA Dashboard',
            'page_title' => 'Mobile Submit QA Dashboard',
            'active_section' => 'Mobile Submit',
            'breadcrumbs' => 'Αρχική / Mobile Submit / QA Dashboard',
            'safe_notice' => 'Read-only QA dashboard. It does not submit to EDXEIX, call AADE, call Bolt, write workflow data, or modify the production pre-ride tool.',
            'force_safe_notice' => true,
        ]);
        return;
    }

    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Mobile Submit QA Dashboard | gov.cabnet.app</title><style>body{font-family:Arial,Helvetica,sans-serif;background:#f3f6fb;color:#07152f;margin:0;padding:20px}.card{background:#fff;border:1px solid #d8dde7;border-radius:8px;padding:16px;margin:0 0 16px}.badge{display:inline-block;padding:5px 9px;border-radius:999px;font-size:12px;font-weight:700;background:#e5e7eb}.badge-good{background:#dcfce7;color:#166534}.badge-warn{background:#fff7ed;color:#b45309}.badge-bad{background:#fee2e2;color:#991b1b}.btn{display:inline-block;background:#4f5ea7;color:#fff;padding:10px 13px;border-radius:6px;text-decoration:none;font-weight:700}.small{font-size:13px;color:#667085}code{background:#eef2ff;padding:2px 5px;border-radius:5px}</style></head><body>';
}

function msq_shell_end(): void
{
    if (function_exists('opsui_shell_end')) {
        opsui_shell_end();
        return;
    }
    echo '</body></html>';
}

function msq_home_root(): string
{
    return dirname(__DIR__, 3);
}

function msq_file_status(string $path): array
{
    $exists = is_file($path);
    $readable = $exists && is_readable($path);
    return [
        'exists' => $exists,
        'readable' => $readable,
        'mtime' => $readable ? date('Y-m-d H:i:s', (int)filemtime($path)) : '',
        'size' => $readable ? (int)filesize($path) : 0,
        'sha' => $readable ? substr((string)hash_file('sha256', $path), 0, 12) : '',
    ];
}

function msq_db(?string &$error = null): ?mysqli
{
    $bootstrap = msq_home_root() . '/gov.cabnet.app_app/src/bootstrap.php';
    if (!is_file($bootstrap)) {
        $error = 'Private app bootstrap not found.';
        return null;
    }

    try {
        $ctx = require $bootstrap;
        if (!is_array($ctx) || !isset($ctx['db']) || !method_exists($ctx['db'], 'connection')) {
            throw new RuntimeException('Private app bootstrap did not return usable DB context.');
        }
        $db = $ctx['db']->connection();
        if (!$db instanceof mysqli) {
            throw new RuntimeException('DB context did not return mysqli.');
        }
        $db->query('SELECT 1');
        $error = null;
        return $db;
    } catch (Throwable $e) {
        $error = $e->getMessage();
        return null;
    }
}

function msq_table_exists(mysqli $db, string $table): bool
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

function msq_columns(mysqli $db, string $table): array
{
    $cols = [];
    try {
        $stmt = $db->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION');
        $stmt->bind_param('s', $table);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $cols[(string)$row['COLUMN_NAME']] = true;
        }
    } catch (Throwable) {
        return [];
    }
    return $cols;
}

function msq_first_existing_col(array $cols, array $choices): string
{
    foreach ($choices as $choice) {
        if (isset($cols[$choice])) {
            return $choice;
        }
    }
    return '';
}

function msq_count(mysqli $db, string $table): ?int
{
    try {
        if (!msq_table_exists($db, $table)) {
            return null;
        }
        $res = $db->query('SELECT COUNT(*) AS c FROM `' . str_replace('`', '``', $table) . '`');
        $row = $res ? $res->fetch_assoc() : null;
        return is_array($row) ? (int)($row['c'] ?? 0) : null;
    } catch (Throwable) {
        return null;
    }
}

function msq_latest_capture(mysqli $db): array
{
    try {
        if (!msq_table_exists($db, 'ops_edxeix_submit_captures')) {
            return ['exists' => false, 'row' => null, 'complete' => false, 'missing' => ['table_missing'], 'error' => 'ops_edxeix_submit_captures table is not installed.'];
        }
        $res = $db->query('SELECT id, form_method, action_host, action_path, csrf_field_name, coordinate_field_names, required_field_names, created_at, updated_at FROM ops_edxeix_submit_captures ORDER BY id DESC LIMIT 1');
        $row = $res ? $res->fetch_assoc() : null;
        if (!is_array($row)) {
            return ['exists' => true, 'row' => null, 'complete' => false, 'missing' => ['no_capture_rows'], 'error' => 'No sanitized capture rows found.'];
        }
        $missing = [];
        foreach (['form_method', 'action_host', 'action_path', 'required_field_names'] as $key) {
            if (trim((string)($row[$key] ?? '')) === '') {
                $missing[] = $key;
            }
        }
        return ['exists' => true, 'row' => $row, 'complete' => $missing === [], 'missing' => $missing, 'error' => ''];
    } catch (Throwable $e) {
        return ['exists' => false, 'row' => null, 'complete' => false, 'missing' => ['query_error'], 'error' => $e->getMessage()];
    }
}

function msq_find_whiteblue_override(mysqli $db): array
{
    $table = 'mapping_lessor_starting_points';
    if (!msq_table_exists($db, $table)) {
        return ['checked' => false, 'found' => false, 'reason' => 'mapping_lessor_starting_points table missing', 'row' => null];
    }

    $cols = msq_columns($db, $table);
    $lessorCol = msq_first_existing_col($cols, ['lessor_id', 'edxeix_lessor_id', 'company_lessor_id', 'lessor_edxeix_id']);
    $pointCol = msq_first_existing_col($cols, ['starting_point_id', 'edxeix_starting_point_id', 'starting_point_edxeix_id']);
    if ($lessorCol === '' || $pointCol === '') {
        return ['checked' => false, 'found' => false, 'reason' => 'Expected lessor/starting-point columns not found', 'row' => null];
    }

    try {
        $sql = 'SELECT * FROM `' . str_replace('`', '``', $table) . '` WHERE `' . str_replace('`', '``', $lessorCol) . '` = ? AND `' . str_replace('`', '``', $pointCol) . '` = ? LIMIT 1';
        $lessor = '1756';
        $point = '612164';
        $stmt = $db->prepare($sql);
        $stmt->bind_param('ss', $lessor, $point);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        return ['checked' => true, 'found' => is_array($row), 'reason' => '', 'row' => is_array($row) ? $row : null, 'lessor_col' => $lessorCol, 'point_col' => $pointCol];
    } catch (Throwable $e) {
        return ['checked' => false, 'found' => false, 'reason' => $e->getMessage(), 'row' => null];
    }
}

function msq_file_contains(string $path, array $needles): bool
{
    if (!is_file($path) || !is_readable($path)) {
        return false;
    }
    $content = file_get_contents($path);
    if (!is_string($content)) {
        return false;
    }
    $haystack = strtolower($content);
    foreach ($needles as $needle) {
        if (str_contains($haystack, strtolower($needle))) {
            return true;
        }
    }
    return false;
}

function msq_gate_row(string $gate, bool $ok, string $status, string $details, string $route = ''): string
{
    $link = $route !== '' ? '<br><a class="small" href="' . msq_h($route) . '">' . msq_h($route) . '</a>' : '';
    return '<tr><td><strong>' . msq_h($gate) . '</strong>' . $link . '</td><td>' . msq_bool_badge($ok) . '</td><td>' . msq_h($status) . '</td><td>' . msq_h($details) . '</td></tr>';
}

$homeRoot = msq_home_root();
$publicRoot = dirname(__DIR__);
$appRoot = $homeRoot . '/gov.cabnet.app_app';
$sqlRoot = $homeRoot . '/gov.cabnet.app_sql';

$routes = [
    '/ops/mobile-submit-center.php' => $publicRoot . '/ops/mobile-submit-center.php',
    '/ops/mobile-submit-gates.php' => $publicRoot . '/ops/mobile-submit-gates.php',
    '/ops/mobile-submit-readiness.php' => $publicRoot . '/ops/mobile-submit-readiness.php',
    '/ops/mobile-submit-trial-run.php' => $publicRoot . '/ops/mobile-submit-trial-run.php',
    '/ops/mobile-submit-evidence.php' => $publicRoot . '/ops/mobile-submit-evidence.php',
    '/ops/mobile-submit-evidence-log.php' => $publicRoot . '/ops/mobile-submit-evidence-log.php',
    '/ops/mobile-submit-evidence-review.php' => $publicRoot . '/ops/mobile-submit-evidence-review.php',
    '/ops/mobile-submit-evidence-center.php' => $publicRoot . '/ops/mobile-submit-evidence-center.php',
    '/ops/edxeix-submit-capture.php' => $publicRoot . '/ops/edxeix-submit-capture.php',
    '/ops/edxeix-submit-dry-run.php' => $publicRoot . '/ops/edxeix-submit-dry-run.php',
    '/ops/edxeix-submit-preflight-gate.php' => $publicRoot . '/ops/edxeix-submit-preflight-gate.php',
    '/ops/edxeix-submit-connector-dev.php' => $publicRoot . '/ops/edxeix-submit-connector-dev.php',
    '/ops/edxeix-submit-payload-validator.php' => $publicRoot . '/ops/edxeix-submit-payload-validator.php',
    '/ops/edxeix-session-readiness.php' => $publicRoot . '/ops/edxeix-session-readiness.php',
    '/ops/mapping-health.php' => $publicRoot . '/ops/mapping-health.php',
    '/ops/mapping-resolver-test.php' => $publicRoot . '/ops/mapping-resolver-test.php',
    '/ops/mapping-exceptions.php' => $publicRoot . '/ops/mapping-exceptions.php',
];

$classes = [
    'BoltPreRideEmailParser' => $appRoot . '/src/BoltMail/BoltPreRideEmailParser.php',
    'EdxeixMappingLookup' => $appRoot . '/src/BoltMail/EdxeixMappingLookup.php',
    'MaildirPreRideEmailLoader' => $appRoot . '/src/BoltMail/MaildirPreRideEmailLoader.php',
    'EdxeixSubmitPreflightGate' => $appRoot . '/src/Edxeix/EdxeixSubmitPreflightGate.php',
    'EdxeixSubmitConnector' => $appRoot . '/src/Edxeix/EdxeixSubmitConnector.php',
    'EdxeixSubmitPayloadValidator' => $appRoot . '/src/Edxeix/EdxeixSubmitPayloadValidator.php',
];

$routeOk = 0;
foreach ($routes as $path) {
    if (is_file($path) && is_readable($path)) {
        $routeOk++;
    }
}
$classOk = 0;
foreach ($classes as $path) {
    if (is_file($path) && is_readable($path)) {
        $classOk++;
    }
}

$dbError = null;
$db = msq_db($dbError);
$tableCounts = [];
$capture = ['exists' => false, 'row' => null, 'complete' => false, 'missing' => ['db_unavailable'], 'error' => 'DB unavailable'];
$whiteblue = ['checked' => false, 'found' => false, 'reason' => 'DB unavailable', 'row' => null];

if ($db instanceof mysqli) {
    foreach ([
        'mapping_lessor_starting_points',
        'mapping_drivers',
        'mapping_vehicles',
        'mapping_verification_status',
        'ops_edxeix_submit_captures',
        'mobile_submit_evidence_log',
    ] as $table) {
        $tableCounts[$table] = msq_count($db, $table);
    }
    $capture = msq_latest_capture($db);
    $whiteblue = msq_find_whiteblue_override($db);
}

$connectorPath = $classes['EdxeixSubmitConnector'];
$connectorDisabled = msq_file_contains($connectorPath, ['disabled', 'live submit remains blocked', 'live submission disabled', 'submit disabled', 'dry-run']);
$mappingReady = ($classOk >= 3)
    && (($tableCounts['mapping_drivers'] ?? 0) > 0)
    && (($tableCounts['mapping_vehicles'] ?? 0) > 0)
    && (($tableCounts['mapping_lessor_starting_points'] ?? 0) > 0);
$startingOverrideReady = !empty($whiteblue['found']);
$captureReady = !empty($capture['complete']);
$dryRunReady = is_file($routes['/ops/edxeix-submit-dry-run.php'])
    && is_file($routes['/ops/edxeix-submit-payload-validator.php'])
    && is_file($classes['EdxeixSubmitPayloadValidator'])
    && is_file($classes['EdxeixSubmitPreflightGate']);
$evidenceReady = (($tableCounts['mobile_submit_evidence_log'] ?? null) !== null)
    && is_file($routes['/ops/mobile-submit-evidence.php'])
    && is_file($routes['/ops/mobile-submit-evidence-log.php'])
    && is_file($routes['/ops/mobile-submit-evidence-review.php']);
$liveSubmitBlocked = $connectorDisabled;

$gates = [
    'mapping_ready' => $mappingReady,
    'starting_point_override_ready' => $startingOverrideReady,
    'capture_ready' => $captureReady,
    'dry_run_ready' => $dryRunReady,
    'evidence_ready' => $evidenceReady,
    'live_submit_blocked' => $liveSubmitBlocked,
];
$passed = count(array_filter($gates));

msq_shell_begin();
?>
<style>
.msq-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}.msq-two{display:grid;grid-template-columns:1fr 1fr;gap:16px}.msq-actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:12px}.msq-code{background:#0f172a;color:#dbeafe;border-radius:6px;padding:14px;font-family:Consolas,Menlo,monospace;font-size:13px;white-space:pre-wrap;overflow:auto}.msq-risk{border-left:6px solid #d97706}.msq-safe{border-left:6px solid #15803d}.msq-bad{border-left:6px solid #b91c1c}.msq-table td code{word-break:break-all}.msq-small{font-size:12px;color:#667085}.msq-status-pills{display:flex;gap:8px;flex-wrap:wrap;margin-top:10px}@media(max-width:1050px){.msq-grid,.msq-two{grid-template-columns:1fr}.msq-actions .btn{width:100%;text-align:center}}
</style>

<section class="card hero neutral">
    <h1>Mobile Submit QA Dashboard</h1>
    <p>Phase 62 read-only dashboard for mobile/server-side EDXEIX submit readiness. It summarizes mapping, starting-point override, capture, dry-run, evidence, and live-submit blocking status.</p>
    <div class="msq-status-pills">
        <?= msq_badge('READ ONLY', 'good') ?>
        <?= msq_badge('NO LIVE SUBMIT', 'good') ?>
        <?= msq_badge('NO EDXEIX CALL', 'good') ?>
        <?= msq_badge('NO AADE CALL', 'good') ?>
        <?= msq_badge('PRODUCTION PRE-RIDE TOOL UNCHANGED', 'good') ?>
    </div>
    <div class="msq-actions">
        <a class="btn" href="/ops/mobile-submit-center.php">Mobile Submit Center</a>
        <a class="btn dark" href="/ops/mobile-submit-gates.php">Gates</a>
        <a class="btn dark" href="/ops/mobile-submit-evidence-center.php">Evidence Center</a>
        <a class="btn dark" href="/ops/mapping-health.php">Mapping Health</a>
    </div>
</section>

<section class="card <?= $liveSubmitBlocked ? 'msq-safe' : 'msq-bad' ?>">
    <h2>QA summary</h2>
    <div class="msq-grid">
        <?= msq_metric((string)$passed . '/6', 'QA gates passing') ?>
        <?= msq_metric((string)$routeOk . '/' . count($routes), 'required routes present') ?>
        <?= msq_metric((string)$classOk . '/' . count($classes), 'private classes present') ?>
    </div>
    <div class="msq-status-pills">
        <?= msq_bool_badge($mappingReady, 'MAPPING READY', 'MAPPING REVIEW') ?>
        <?= msq_bool_badge($startingOverrideReady, 'WHITEBLUE OVERRIDE OK', 'OVERRIDE REVIEW') ?>
        <?= msq_bool_badge($captureReady, 'CAPTURE READY', 'CAPTURE REVIEW') ?>
        <?= msq_bool_badge($dryRunReady, 'DRY-RUN READY', 'DRY-RUN REVIEW') ?>
        <?= msq_bool_badge($evidenceReady, 'EVIDENCE READY', 'EVIDENCE REVIEW') ?>
        <?= msq_badge($liveSubmitBlocked ? 'LIVE SUBMIT BLOCKED' : 'LIVE BLOCK NOT PROVEN', $liveSubmitBlocked ? 'good' : 'bad') ?>
    </div>
</section>

<section class="card">
    <h2>Gate matrix</h2>
    <div class="table-wrap"><table class="msq-table">
        <thead><tr><th>Gate</th><th>Status</th><th>Current state</th><th>Details</th></tr></thead>
        <tbody>
            <?= msq_gate_row('Mapping ready', $mappingReady, $mappingReady ? 'Core mapping tables and classes are present.' : 'Mapping needs review.', 'Requires driver, vehicle, and lessor-specific starting point mapping data.', '/ops/mapping-health.php') ?>
            <?= msq_gate_row('Starting point override ready', $startingOverrideReady, $startingOverrideReady ? 'WHITEBLUE / 1756 override points to 612164.' : 'WHITEBLUE / 1756 override not proven by this dashboard.', $whiteblue['reason'] !== '' ? (string)$whiteblue['reason'] : 'Required: lessor 1756 → starting point 612164.', '/ops/company-mapping-detail.php?lessor=1756') ?>
            <?= msq_gate_row('Capture ready', $captureReady, $captureReady ? 'Latest sanitized EDXEIX submit capture has essentials.' : 'Capture is missing or incomplete.', empty($capture['missing']) ? 'Capture row complete.' : 'Missing: ' . implode(', ', array_map('strval', (array)$capture['missing'])), '/ops/edxeix-submit-capture.php') ?>
            <?= msq_gate_row('Dry-run ready', $dryRunReady, $dryRunReady ? 'Dry-run, validator, preflight and support classes are present.' : 'Dry-run support files need review.', 'Dry-run must remain preview-only and must not submit.', '/ops/edxeix-submit-dry-run.php') ?>
            <?= msq_gate_row('Evidence ready', $evidenceReady, $evidenceReady ? 'Evidence table/routes are available.' : 'Evidence table or routes need review.', (($tableCounts['mobile_submit_evidence_log'] ?? null) === null) ? 'Run Phase 59 SQL before saving evidence.' : 'Saved evidence rows: ' . (string)($tableCounts['mobile_submit_evidence_log'] ?? 0), '/ops/mobile-submit-evidence-center.php') ?>
            <?= msq_gate_row('Live submit blocked', $liveSubmitBlocked, $liveSubmitBlocked ? 'Disabled connector contract detected.' : 'Disabled connector state not proven by file scan.', 'This must remain blocked until Andreas explicitly approves a separate live-submit change.', '/ops/edxeix-submit-connector-dev.php') ?>
        </tbody>
    </table></div>
</section>

<section class="msq-two">
    <div class="card">
        <h2>Database readiness</h2>
        <?php if ($db instanceof mysqli): ?>
            <p><?= msq_badge('DB CONNECTED', 'good') ?></p>
            <div class="table-wrap"><table>
                <thead><tr><th>Table</th><th>Status</th><th>Rows</th></tr></thead>
                <tbody>
                <?php foreach ($tableCounts as $table => $count): ?>
                    <tr>
                        <td><code><?= msq_h($table) ?></code></td>
                        <td><?= msq_bool_badge($count !== null, 'PRESENT', 'MISSING') ?></td>
                        <td><?= msq_h($count === null ? '-' : (string)$count) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
        <?php else: ?>
            <p><?= msq_badge('DB ISSUE', 'warn') ?> <?= msq_h((string)$dbError) ?></p>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2>Latest sanitized capture</h2>
        <?php if (is_array($capture['row'] ?? null)): $row = $capture['row']; ?>
            <p><?= msq_bool_badge($captureReady, 'CAPTURE COMPLETE', 'CAPTURE INCOMPLETE') ?></p>
            <div class="table-wrap"><table>
                <tbody>
                    <tr><td>ID</td><td><strong><?= msq_h((string)($row['id'] ?? '')) ?></strong></td></tr>
                    <tr><td>Method</td><td><?= msq_h((string)($row['form_method'] ?? '')) ?></td></tr>
                    <tr><td>Action host/path</td><td><code><?= msq_h((string)($row['action_host'] ?? '')) ?><?= msq_h((string)($row['action_path'] ?? '')) ?></code></td></tr>
                    <tr><td>CSRF field name</td><td><code><?= msq_h((string)($row['csrf_field_name'] ?? '')) ?></code> <span class="msq-small">name only, no value</span></td></tr>
                    <tr><td>Coordinate fields</td><td><code><?= msq_h((string)($row['coordinate_field_names'] ?? '')) ?></code></td></tr>
                    <tr><td>Required fields</td><td><code><?= msq_h((string)($row['required_field_names'] ?? '')) ?></code></td></tr>
                    <tr><td>Updated</td><td><?= msq_h((string)($row['updated_at'] ?? $row['created_at'] ?? '')) ?></td></tr>
                </tbody>
            </table></div>
        <?php else: ?>
            <p><?= msq_badge('NO CAPTURE ROW', 'warn') ?></p>
            <p class="small"><?= msq_h((string)($capture['error'] ?? 'No sanitized capture row is available.')) ?></p>
        <?php endif; ?>
    </div>
</section>

<section class="card">
    <h2>Route status</h2>
    <div class="table-wrap"><table>
        <thead><tr><th>Route</th><th>Status</th><th>Modified</th><th>SHA</th></tr></thead>
        <tbody>
        <?php foreach ($routes as $route => $path): $st = msq_file_status($path); ?>
            <tr>
                <td><a href="<?= msq_h($route) ?>"><?= msq_h($route) ?></a></td>
                <td><?= msq_bool_badge($st['readable'], 'READY', 'MISSING') ?></td>
                <td><?= msq_h($st['mtime']) ?></td>
                <td><code><?= msq_h($st['sha']) ?></code></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
</section>

<section class="card">
    <h2>Private support files</h2>
    <div class="table-wrap"><table>
        <thead><tr><th>Class/file</th><th>Status</th><th>Modified</th><th>SHA</th></tr></thead>
        <tbody>
        <?php foreach ($classes as $label => $path): $st = msq_file_status($path); ?>
            <tr>
                <td><strong><?= msq_h($label) ?></strong><br><code><?= msq_h($path) ?></code></td>
                <td><?= msq_bool_badge($st['readable'], 'READY', 'MISSING') ?></td>
                <td><?= msq_h($st['mtime']) ?></td>
                <td><code><?= msq_h($st['sha']) ?></code></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
</section>

<section class="card msq-risk">
    <h2>Verification commands</h2>
    <div class="msq-code">php -l /home/cabnet/public_html/gov.cabnet.app/ops/mobile-submit-qa-dashboard.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/mobile-submit-center.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/mobile-submit-gates.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/mobile-submit-trial-run.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/mobile-submit-evidence-center.php

# Confirm Phase 59 evidence table exists, if not already done:
mysql -u cabnet_gov -p cabnet_gov -e "SHOW TABLES LIKE 'mobile_submit_evidence_log';"

# Run only if table is missing:
mysql -u cabnet_gov -p cabnet_gov &lt; /home/cabnet/gov.cabnet.app_sql/2026_05_12_mobile_submit_evidence_log.sql</div>
</section>

<section class="card msq-safe">
    <h2>Safety contract</h2>
    <ul class="list">
        <li>This dashboard is read-only and does not write database rows.</li>
        <li>It does not call Bolt, EDXEIX, AADE, external APIs, or the Firefox helper extensions.</li>
        <li>It does not display raw email text, cookies, sessions, CSRF token values, credentials, or real config values.</li>
        <li>Live server-side EDXEIX submit remains blocked until Andreas explicitly approves a separate live-submit update.</li>
        <li>The production tool <code>/ops/pre-ride-email-tool.php</code> is not modified by this patch.</li>
    </ul>
</section>
<?php
msq_shell_end();
