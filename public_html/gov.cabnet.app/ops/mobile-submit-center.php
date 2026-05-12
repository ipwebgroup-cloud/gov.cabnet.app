<?php
/**
 * gov.cabnet.app — Mobile Submit Center v0.1
 *
 * Read-only hub for the future mobile/server-side EDXEIX submit workflow.
 * It does not submit, stage jobs, write workflow data, or call external services.
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

function msc_h(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function msc_badge(string $text, string $type = 'neutral'): string
{
    $type = in_array($type, ['good', 'warn', 'bad', 'neutral'], true) ? $type : 'neutral';
    if (function_exists('opsui_badge')) {
        return opsui_badge($text, $type);
    }
    return '<span class="badge badge-' . msc_h($type) . '">' . msc_h($text) . '</span>';
}

function msc_shell_begin(): void
{
    if (function_exists('opsui_shell_begin')) {
        opsui_shell_begin([
            'title' => 'Mobile Submit Center',
            'page_title' => 'Mobile Submit Center',
            'active_section' => 'Mobile Submit',
            'breadcrumbs' => 'Αρχική / Mobile / Submit Center',
            'safe_notice' => 'Read-only mobile submit development hub. It does not submit to EDXEIX and does not modify the production pre-ride tool.',
            'force_safe_notice' => true,
        ]);
        return;
    }

    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Mobile Submit Center | gov.cabnet.app</title><style>body{font-family:Arial,Helvetica,sans-serif;background:#f3f6fb;color:#07152f;margin:0;padding:20px}.card{background:#fff;border:1px solid #d8dde7;border-radius:8px;padding:16px;margin:0 0 16px}.badge{display:inline-block;padding:5px 9px;border-radius:999px;font-size:12px;font-weight:700;background:#e5e7eb}.badge-good{background:#dcfce7;color:#166534}.badge-warn{background:#fff7ed;color:#b45309}.badge-bad{background:#fee2e2;color:#991b1b}.btn{display:inline-block;background:#4f5ea7;color:#fff;padding:10px 13px;border-radius:6px;text-decoration:none;font-weight:700}.small{font-size:13px;color:#667085}code{background:#eef2ff;padding:2px 5px;border-radius:5px}</style></head><body>';
}

function msc_shell_end(): void
{
    if (function_exists('opsui_shell_end')) {
        opsui_shell_end();
        return;
    }
    echo '</body></html>';
}

function msc_file_status(string $path): array
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

function msc_table_exists(mysqli $db, string $table): bool
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

function msc_table_count(mysqli $db, string $table): ?int
{
    try {
        if (!msc_table_exists($db, $table)) {
            return null;
        }
        $res = $db->query('SELECT COUNT(*) AS c FROM `' . str_replace('`', '``', $table) . '`');
        $row = $res ? $res->fetch_assoc() : null;
        return is_array($row) ? (int)($row['c'] ?? 0) : null;
    } catch (Throwable) {
        return null;
    }
}

function msc_latest_capture(mysqli $db): array
{
    try {
        if (!msc_table_exists($db, 'ops_edxeix_submit_captures')) {
            return ['exists' => false, 'row' => null, 'error' => 'ops_edxeix_submit_captures table is not installed.'];
        }
        $res = $db->query('SELECT id, form_method, action_host, action_path, csrf_field_name, coordinate_field_names, required_field_names, created_at, updated_at FROM ops_edxeix_submit_captures ORDER BY id DESC LIMIT 1');
        $row = $res ? $res->fetch_assoc() : null;
        return ['exists' => true, 'row' => is_array($row) ? $row : null, 'error' => ''];
    } catch (Throwable $e) {
        return ['exists' => false, 'row' => null, 'error' => $e->getMessage()];
    }
}

function msc_metric(string $value, string $label): string
{
    if (function_exists('opsui_metric')) {
        return opsui_metric($value, $label);
    }
    return '<div class="metric"><strong>' . msc_h($value) . '</strong><span>' . msc_h($label) . '</span></div>';
}

function msc_bool(bool $ok, string $yes = 'OK', string $no = 'MISSING'): string
{
    return msc_badge($ok ? $yes : $no, $ok ? 'good' : 'warn');
}

function msc_link_card(string $href, string $title, string $description, string $status = 'neutral'): string
{
    return '<div class="msc-link-card">'
        . '<div><h3>' . msc_h($title) . '</h3><p>' . msc_h($description) . '</p></div>'
        . '<div class="msc-link-card-foot">' . msc_badge(strtoupper($status), $status === 'ready' || $status === 'good' ? 'good' : ($status === 'blocked' ? 'warn' : 'neutral'))
        . '<a class="btn" href="' . msc_h($href) . '">Open</a></div>'
        . '</div>';
}

$homeRoot = dirname(__DIR__, 3);
$publicRoot = dirname(__DIR__);
$appRoot = $homeRoot . '/gov.cabnet.app_app';
$bootstrap = $appRoot . '/src/bootstrap.php';

$routes = [
    'Mobile Submit Dev' => ['/ops/mobile-submit-dev.php', $publicRoot . '/ops/mobile-submit-dev.php'],
    'Mobile Submit Readiness' => ['/ops/mobile-submit-readiness.php', $publicRoot . '/ops/mobile-submit-readiness.php'],
    'EDXEIX Submit Research' => ['/ops/edxeix-submit-research.php', $publicRoot . '/ops/edxeix-submit-research.php'],
    'EDXEIX Submit Capture' => ['/ops/edxeix-submit-capture.php', $publicRoot . '/ops/edxeix-submit-capture.php'],
    'EDXEIX Submit Dry Run' => ['/ops/edxeix-submit-dry-run.php', $publicRoot . '/ops/edxeix-submit-dry-run.php'],
    'EDXEIX Preflight Gate' => ['/ops/edxeix-submit-preflight-gate.php', $publicRoot . '/ops/edxeix-submit-preflight-gate.php'],
    'EDXEIX Session Readiness' => ['/ops/edxeix-session-readiness.php', $publicRoot . '/ops/edxeix-session-readiness.php'],
    'EDXEIX Connector Dev' => ['/ops/edxeix-submit-connector-dev.php', $publicRoot . '/ops/edxeix-submit-connector-dev.php'],
    'EDXEIX Payload Validator' => ['/ops/edxeix-submit-payload-validator.php', $publicRoot . '/ops/edxeix-submit-payload-validator.php'],
    'Mapping Resolver Test' => ['/ops/mapping-resolver-test.php', $publicRoot . '/ops/mapping-resolver-test.php'],
    'Mapping Exception Queue' => ['/ops/mapping-exceptions.php', $publicRoot . '/ops/mapping-exceptions.php'],
];

$classes = [
    'Pre-Ride Parser' => $appRoot . '/src/BoltMail/BoltPreRideEmailParser.php',
    'EDXEIX Mapping Lookup' => $appRoot . '/src/BoltMail/EdxeixMappingLookup.php',
    'Maildir Email Loader' => $appRoot . '/src/BoltMail/MaildirPreRideEmailLoader.php',
    'EDXEIX Preflight Gate' => $appRoot . '/src/Edxeix/EdxeixSubmitPreflightGate.php',
    'EDXEIX Disabled Connector' => $appRoot . '/src/Edxeix/EdxeixSubmitConnector.php',
    'EDXEIX Payload Validator' => $appRoot . '/src/Edxeix/EdxeixSubmitPayloadValidator.php',
];

$routeOk = 0;
foreach ($routes as $route) {
    if (is_file($route[1])) {
        $routeOk++;
    }
}
$classOk = 0;
foreach ($classes as $path) {
    if (is_file($path)) {
        $classOk++;
    }
}

$dbStatus = ['ok' => false, 'error' => '', 'tables' => [], 'latest_capture' => ['exists' => false, 'row' => null, 'error' => 'not checked']];
if (is_file($bootstrap)) {
    try {
        $ctx = require $bootstrap;
        if (!is_array($ctx) || !isset($ctx['db']) || !method_exists($ctx['db'], 'connection')) {
            throw new RuntimeException('Private bootstrap did not return usable DB context.');
        }
        $db = $ctx['db']->connection();
        if (!$db instanceof mysqli) {
            throw new RuntimeException('DB context did not return mysqli.');
        }
        $db->query('SELECT 1');
        $dbStatus['ok'] = true;
        foreach (['ops_edxeix_submit_captures', 'mapping_lessor_starting_points', 'mapping_verification_status', 'mapping_drivers', 'mapping_vehicles'] as $table) {
            $count = msc_table_count($db, $table);
            $dbStatus['tables'][$table] = [
                'exists' => $count !== null,
                'count' => $count,
            ];
        }
        $dbStatus['latest_capture'] = msc_latest_capture($db);
    } catch (Throwable $e) {
        $dbStatus['error'] = $e->getMessage();
    }
} else {
    $dbStatus['error'] = 'Private bootstrap not found.';
}

$latestCapture = $dbStatus['latest_capture'];
$captureRow = is_array($latestCapture['row'] ?? null) ? $latestCapture['row'] : null;
$hasCapture = is_array($captureRow);
$hasCaptureEssentials = $hasCapture
    && trim((string)($captureRow['form_method'] ?? '')) !== ''
    && trim((string)($captureRow['action_host'] ?? '')) !== ''
    && trim((string)($captureRow['action_path'] ?? '')) !== ''
    && trim((string)($captureRow['required_field_names'] ?? '')) !== '';

$readyFacts = [
    'routes' => $routeOk === count($routes),
    'classes' => $classOk === count($classes),
    'db' => !empty($dbStatus['ok']),
    'capture' => $hasCaptureEssentials,
    'mapping_overrides' => (($dbStatus['tables']['mapping_lessor_starting_points']['count'] ?? 0) > 0),
    'connector_disabled' => is_file($classes['EDXEIX Disabled Connector']),
];
$readinessScore = 0;
foreach ($readyFacts as $ok) {
    if ($ok) { $readinessScore++; }
}

msc_shell_begin();
?>
<style>
.msc-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}.msc-two{display:grid;grid-template-columns:1fr 1fr;gap:16px}.msc-link-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.msc-link-card{display:flex;flex-direction:column;justify-content:space-between;border:1px solid #d8dde7;background:#fff;border-radius:6px;padding:16px;min-height:150px}.msc-link-card h3{margin:0 0 8px}.msc-link-card p{margin:0;color:#667085;line-height:1.45}.msc-link-card-foot{display:flex;justify-content:space-between;gap:10px;align-items:center;margin-top:14px}.msc-stage{border-left:6px solid #d8dde7;background:#fff;border:1px solid #d8dde7;border-radius:6px;padding:14px;margin-bottom:10px}.msc-stage.good{border-left-color:#15803d}.msc-stage.warn{border-left-color:#d97706}.msc-stage.bad{border-left-color:#b91c1c}.msc-code{background:#0f172a;color:#dbeafe;border-radius:6px;padding:14px;font-family:Consolas,Menlo,monospace;font-size:13px;white-space:pre-wrap;overflow:auto}.msc-table td code{word-break:break-all}.msc-mini{font-size:12px;color:#667085}@media(max-width:1050px){.msc-grid,.msc-two,.msc-link-grid{grid-template-columns:1fr}.msc-link-card-foot{align-items:flex-start;flex-direction:column}.msc-link-card-foot .btn{width:100%;text-align:center}}</style>

<section class="card hero neutral">
    <h1>Mobile Submit Center</h1>
    <p>Read-only control hub for the future mobile/server-side EDXEIX submit workflow. This page centralizes status, routes, required classes, capture readiness, mapping readiness, and next steps.</p>
    <div>
        <?= msc_badge('READ ONLY', 'good') ?>
        <?= msc_badge('NO LIVE SUBMIT', 'good') ?>
        <?= msc_badge('NO EDXEIX CALL', 'good') ?>
        <?= msc_badge('PRODUCTION TOOL UNCHANGED', 'good') ?>
    </div>
</section>

<section class="card">
    <h2>Readiness overview</h2>
    <div class="msc-grid">
        <?= msc_metric((string)$readinessScore . '/6', 'readiness checks passed') ?>
        <?= msc_metric((string)$routeOk . '/' . count($routes), 'mobile/submit routes present') ?>
        <?= msc_metric((string)$classOk . '/' . count($classes), 'private classes present') ?>
    </div>
    <div style="margin-top:14px;display:flex;gap:8px;flex-wrap:wrap;">
        <?= msc_bool($readyFacts['routes'], 'ROUTES OK', 'ROUTES MISSING') ?>
        <?= msc_bool($readyFacts['classes'], 'CLASSES OK', 'CLASSES MISSING') ?>
        <?= msc_bool($readyFacts['db'], 'DB OK', 'DB ISSUE') ?>
        <?= msc_bool($readyFacts['capture'], 'CAPTURE READY', 'CAPTURE INCOMPLETE') ?>
        <?= msc_bool($readyFacts['mapping_overrides'], 'OVERRIDES EXIST', 'NO OVERRIDES') ?>
        <?= msc_bool($readyFacts['connector_disabled'], 'CONNECTOR DISABLED', 'CONNECTOR MISSING') ?>
    </div>
</section>

<section class="msc-two">
    <div class="card">
        <h2>Mobile submit development stages</h2>
        <div class="msc-stage <?= $readyFacts['routes'] ? 'good' : 'warn' ?>"><strong>1. Mobile routes</strong><br><span class="small">Mobile Submit Dev and Mobile Submit Readiness routes exist and are kept separate from production.</span></div>
        <div class="msc-stage <?= $readyFacts['mapping_overrides'] ? 'good' : 'warn' ?>"><strong>2. Mapping governance</strong><br><span class="small">Lessor-specific starting point overrides are required to avoid unsafe global fallback.</span></div>
        <div class="msc-stage <?= $readyFacts['capture'] ? 'good' : 'warn' ?>"><strong>3. Sanitized submit capture</strong><br><span class="small">Need form method/action/required fields/CSRF field name/map fields. Never store token values.</span></div>
        <div class="msc-stage <?= $readyFacts['classes'] ? 'good' : 'warn' ?>"><strong>4. Dry-run connector contract</strong><br><span class="small">Request preview can be built, but submit remains disabled.</span></div>
        <div class="msc-stage good"><strong>5. Safety gate</strong><br><span class="small">Live submit remains blocked by preflight, missing map confirmation, final confirmation, and disabled connector.</span></div>
    </div>

    <div class="card">
        <h2>Latest sanitized submit capture</h2>
        <?php if ($hasCapture): ?>
            <p><?= msc_badge($hasCaptureEssentials ? 'CAPTURE ESSENTIALS READY' : 'CAPTURE INCOMPLETE', $hasCaptureEssentials ? 'good' : 'warn') ?></p>
            <div class="table-wrap"><table class="msc-table"><tbody>
                <tr><td>ID</td><td><strong><?= msc_h((string)($captureRow['id'] ?? '')) ?></strong></td></tr>
                <tr><td>Method</td><td><?= msc_h((string)($captureRow['form_method'] ?? '')) ?></td></tr>
                <tr><td>Action</td><td><code><?= msc_h((string)($captureRow['action_host'] ?? '')) ?><?= msc_h((string)($captureRow['action_path'] ?? '')) ?></code></td></tr>
                <tr><td>CSRF field name</td><td><code><?= msc_h((string)($captureRow['csrf_field_name'] ?? '')) ?></code> <span class="msc-mini">name only</span></td></tr>
                <tr><td>Coordinate fields</td><td><code><?= msc_h((string)($captureRow['coordinate_field_names'] ?? '')) ?></code></td></tr>
                <tr><td>Required fields</td><td><code><?= msc_h((string)($captureRow['required_field_names'] ?? '')) ?></code></td></tr>
                <tr><td>Updated</td><td><?= msc_h((string)($captureRow['updated_at'] ?? $captureRow['created_at'] ?? '')) ?></td></tr>
            </tbody></table></div>
        <?php else: ?>
            <p><?= msc_badge('NO CAPTURE FOUND', 'warn') ?></p>
            <p class="small"><?= msc_h((string)($latestCapture['error'] ?? 'No sanitized capture row is available.')) ?></p>
            <div class="actions"><a class="btn" href="/ops/edxeix-submit-capture.php">Open Submit Capture</a></div>
        <?php endif; ?>
    </div>
</section>

<section class="card">
    <h2>Mobile / submit workflow links</h2>
    <div class="msc-link-grid">
        <?= msc_link_card('/ops/mobile-submit-dev.php', 'Mobile Submit Dev', 'Mobile-first parser and review route. Submit remains disabled.', 'ready') ?>
        <?= msc_link_card('/ops/mobile-submit-readiness.php', 'Mobile Submit Readiness', 'Integrated parser, mapping, capture, preflight, and dry-run status.', 'ready') ?>
        <?= msc_link_card('/ops/edxeix-submit-capture.php', 'Submit Capture', 'Record sanitized EDXEIX form metadata only; no tokens or secrets.', $readyFacts['capture'] ? 'ready' : 'blocked') ?>
        <?= msc_link_card('/ops/edxeix-submit-dry-run.php', 'Dry-Run Builder', 'Build canonical payload preview without sending anything.', 'ready') ?>
        <?= msc_link_card('/ops/edxeix-submit-preflight-gate.php', 'Preflight Gate', 'Evaluate technical and live blockers before any future submit.', 'ready') ?>
        <?= msc_link_card('/ops/edxeix-submit-connector-dev.php', 'Connector Dev', 'Disabled connector contract and request preview.', 'ready') ?>
        <?= msc_link_card('/ops/edxeix-submit-payload-validator.php', 'Payload Validator', 'Validate dry-run payload shape and secret-safety.', 'ready') ?>
        <?= msc_link_card('/ops/edxeix-session-readiness.php', 'Session Readiness', 'Show remaining gaps for future server-side session/CSRF strategy.', 'ready') ?>
    </div>
</section>

<section class="msc-two">
    <div class="card">
        <h2>Route status</h2>
        <div class="table-wrap"><table>
            <thead><tr><th>Route</th><th>Status</th><th>Modified</th><th>SHA</th></tr></thead>
            <tbody>
            <?php foreach ($routes as $label => $route): $st = msc_file_status($route[1]); ?>
                <tr>
                    <td><a href="<?= msc_h($route[0]) ?>"><?= msc_h($label) ?></a></td>
                    <td><?= msc_bool($st['readable'], 'READY', 'MISSING') ?></td>
                    <td><?= msc_h($st['mtime']) ?></td>
                    <td><code><?= msc_h($st['sha']) ?></code></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    </div>

    <div class="card">
        <h2>Private class status</h2>
        <div class="table-wrap"><table>
            <thead><tr><th>Class</th><th>Status</th><th>Modified</th><th>SHA</th></tr></thead>
            <tbody>
            <?php foreach ($classes as $label => $path): $st = msc_file_status($path); ?>
                <tr>
                    <td><?= msc_h($label) ?></td>
                    <td><?= msc_bool($st['readable'], 'READY', 'MISSING') ?></td>
                    <td><?= msc_h($st['mtime']) ?></td>
                    <td><code><?= msc_h($st['sha']) ?></code></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    </div>
</section>

<section class="card">
    <h2>Database readiness</h2>
    <?php if (!empty($dbStatus['ok'])): ?>
        <p><?= msc_badge('DB CONNECTED', 'good') ?></p>
        <div class="table-wrap"><table>
            <thead><tr><th>Table</th><th>Status</th><th>Rows</th></tr></thead>
            <tbody>
            <?php foreach ($dbStatus['tables'] as $table => $info): ?>
                <tr>
                    <td><code><?= msc_h($table) ?></code></td>
                    <td><?= msc_bool(!empty($info['exists']), 'PRESENT', 'MISSING') ?></td>
                    <td><?= msc_h($info['count'] === null ? '-' : (string)$info['count']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    <?php else: ?>
        <p><?= msc_badge('DB ISSUE', 'warn') ?> <?= msc_h($dbStatus['error']) ?></p>
    <?php endif; ?>
</section>

<section class="card">
    <h2>Recommended verification commands</h2>
    <div class="msc-code">php -l /home/cabnet/public_html/gov.cabnet.app/ops/mobile-submit-center.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/mobile-submit-readiness.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/edxeix-submit-connector-dev.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/edxeix-submit-payload-validator.php
php -l /home/cabnet/gov.cabnet.app_app/src/Edxeix/EdxeixSubmitConnector.php
php -l /home/cabnet/gov.cabnet.app_app/src/Edxeix/EdxeixSubmitPayloadValidator.php</div>
</section>
<?php
msc_shell_end();
