<?php
/**
 * gov.cabnet.app — Mobile Submit Evidence Center
 *
 * Read-only hub for the future mobile/server-side EDXEIX submit evidence workflow.
 * It centralizes trial run, evidence snapshot, evidence log, and evidence review pages.
 *
 * Safety contract:
 * - Does not call Bolt.
 * - Does not call EDXEIX.
 * - Does not call AADE.
 * - Does not write database rows.
 * - Does not display raw email text, cookies, sessions, CSRF token values, or credentials.
 */

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Robots-Tag: noindex,nofollow', true);

require_once __DIR__ . '/_shell.php';

function msec_path_status(string $path): array
{
    return [
        'exists' => is_file($path),
        'readable' => is_file($path) && is_readable($path),
        'mtime' => is_file($path) ? date('Y-m-d H:i:s', (int)filemtime($path)) : '',
        'size' => is_file($path) ? (int)filesize($path) : 0,
    ];
}

function msec_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function msec_badge(bool $ok, string $yes = 'OK', string $no = 'MISSING'): string
{
    return function_exists('opsui_badge')
        ? opsui_badge($ok ? $yes : $no, $ok ? 'good' : 'warn')
        : '<span>' . msec_h($ok ? $yes : $no) . '</span>';
}

function msec_app_context(?string &$error = null): ?array
{
    $bootstrap = dirname(__DIR__, 3) . '/gov.cabnet.app_app/src/bootstrap.php';
    if (!is_file($bootstrap)) {
        $error = 'Private app bootstrap not found.';
        return null;
    }

    try {
        $ctx = require $bootstrap;
        if (!is_array($ctx)) {
            $error = 'Private app bootstrap did not return context array.';
            return null;
        }
        return $ctx;
    } catch (Throwable $e) {
        $error = $e->getMessage();
        return null;
    }
}

function msec_db(?string &$error = null): ?mysqli
{
    $ctx = msec_app_context($error);
    if (!$ctx || !isset($ctx['db']) || !method_exists($ctx['db'], 'connection')) {
        $error = $error ?: 'DB context unavailable.';
        return null;
    }

    try {
        $db = $ctx['db']->connection();
        if (!$db instanceof mysqli) {
            $error = 'DB context did not return mysqli.';
            return null;
        }
        return $db;
    } catch (Throwable $e) {
        $error = $e->getMessage();
        return null;
    }
}

function msec_table_exists(mysqli $db, string $table): bool
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

function msec_columns(mysqli $db, string $table): array
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
    } catch (Throwable) {}
    return $cols;
}

function msec_first_col(array $cols, array $choices): string
{
    foreach ($choices as $choice) {
        if (isset($cols[$choice])) {
            return $choice;
        }
    }
    return '';
}

function msec_count(mysqli $db, string $table): int
{
    try {
        $res = $db->query('SELECT COUNT(*) AS c FROM `' . str_replace('`', '``', $table) . '`');
        $row = $res ? $res->fetch_assoc() : null;
        return (int)($row['c'] ?? 0);
    } catch (Throwable) {
        return 0;
    }
}

function msec_fetch_recent(mysqli $db, string $table, int $limit = 8): array
{
    $cols = msec_columns($db, $table);
    $orderCol = msec_first_col($cols, ['created_at', 'id']);
    if ($orderCol === '') {
        return [];
    }

    try {
        $sql = 'SELECT * FROM `' . str_replace('`', '``', $table) . '` ORDER BY `' . str_replace('`', '``', $orderCol) . '` DESC LIMIT ' . max(1, min(20, $limit));
        $res = $db->query($sql);
        $rows = [];
        while ($res && ($row = $res->fetch_assoc())) {
            $rows[] = $row;
        }
        return $rows;
    } catch (Throwable) {
        return [];
    }
}

function msec_row_value(array $row, array $keys, string $default = ''): string
{
    foreach ($keys as $key) {
        if (array_key_exists($key, $row) && $row[$key] !== null && $row[$key] !== '') {
            return trim((string)$row[$key]);
        }
    }
    return $default;
}

function msec_status_counts(array $rows): array
{
    $counts = ['ready' => 0, 'review' => 0, 'blocked' => 0, 'unknown' => 0];
    foreach ($rows as $row) {
        $status = strtolower(msec_row_value($row, ['final_status', 'status', 'readiness_status', 'dry_run_status'], 'unknown'));
        if (str_contains($status, 'ready') || str_contains($status, 'ok')) {
            $counts['ready']++;
        } elseif (str_contains($status, 'block') || str_contains($status, 'no-go') || str_contains($status, 'no_go')) {
            $counts['blocked']++;
        } elseif ($status === 'unknown' || $status === '') {
            $counts['unknown']++;
        } else {
            $counts['review']++;
        }
    }
    return $counts;
}

$publicRoot = dirname(__DIR__);
$appRoot = dirname(__DIR__, 3) . '/gov.cabnet.app_app';

$routes = [
    ['/ops/mobile-submit-center.php', 'Mobile Submit Center', 'Hub for mobile/server-side submit development.'],
    ['/ops/mobile-submit-gates.php', 'Mobile Submit Gates', 'Readiness gate matrix.'],
    ['/ops/mobile-submit-trial-run.php', 'Mobile Submit Trial Run', 'Real-email dry-run GO / NO-GO evaluator.'],
    ['/ops/mobile-submit-evidence.php', 'Evidence Snapshot', 'Sanitized dry-run evidence JSON generator.'],
    ['/ops/mobile-submit-evidence-log.php', 'Evidence Log', 'Admin-only sanitized evidence storage.'],
    ['/ops/mobile-submit-evidence-review.php', 'Evidence Review', 'Read-only saved evidence review.'],
    ['/ops/mobile-submit-scenarios.php', 'Synthetic Scenarios', 'TEST-ONLY resolver/preflight scenarios.'],
    ['/ops/edxeix-submit-payload-validator.php', 'Payload Validator', 'Dry-run EDXEIX payload validator.'],
    ['/ops/edxeix-submit-connector-dev.php', 'Connector Dev', 'Disabled EDXEIX connector preview.'],
];

$classes = [
    'Bolt pre-ride parser' => $appRoot . '/src/BoltMail/BoltPreRideEmailParser.php',
    'EDXEIX mapping lookup' => $appRoot . '/src/BoltMail/EdxeixMappingLookup.php',
    'Maildir pre-ride loader' => $appRoot . '/src/BoltMail/MaildirPreRideEmailLoader.php',
    'EDXEIX preflight gate' => $appRoot . '/src/Edxeix/EdxeixSubmitPreflightGate.php',
    'EDXEIX connector dev contract' => $appRoot . '/src/Edxeix/EdxeixSubmitConnector.php',
    'EDXEIX payload validator' => $appRoot . '/src/Edxeix/EdxeixSubmitPayloadValidator.php',
];

$dbError = '';
$db = msec_db($dbError);
$evidenceTableExists = false;
$evidenceCols = [];
$evidenceTotal = 0;
$recentRows = [];
$statusCounts = ['ready' => 0, 'review' => 0, 'blocked' => 0, 'unknown' => 0];

if ($db instanceof mysqli) {
    $evidenceTableExists = msec_table_exists($db, 'mobile_submit_evidence_log');
    if ($evidenceTableExists) {
        $evidenceCols = msec_columns($db, 'mobile_submit_evidence_log');
        $evidenceTotal = msec_count($db, 'mobile_submit_evidence_log');
        $recentRows = msec_fetch_recent($db, 'mobile_submit_evidence_log', 8);
        $statusCounts = msec_status_counts($recentRows);
    }
}

opsui_shell_begin([
    'title' => 'Mobile Submit Evidence Center',
    'page_title' => 'Mobile Submit Evidence Center',
    'active_section' => 'Mobile Submit',
    'breadcrumbs' => 'Αρχική / Mobile Submit / Evidence Center',
    'safe_notice' => 'Read-only evidence hub. No live EDXEIX submit is enabled and no raw email text or secrets are displayed.',
]);
?>
<section class="card hero neutral">
    <h1>Mobile Submit Evidence Center</h1>
    <p>Central hub for sanitized dry-run evidence around the future mobile/server-side EDXEIX submit workflow.</p>
    <div>
        <?= opsui_badge('READ ONLY', 'good') ?>
        <?= opsui_badge('NO LIVE SUBMIT', 'good') ?>
        <?= opsui_badge('NO RAW EMAIL DISPLAY', 'good') ?>
        <?= opsui_badge('NO SECRET OUTPUT', 'good') ?>
    </div>
    <div class="actions">
        <a class="btn" href="/ops/mobile-submit-evidence.php">Generate Evidence</a>
        <a class="btn dark" href="/ops/mobile-submit-evidence-log.php">Evidence Log</a>
        <a class="btn dark" href="/ops/mobile-submit-evidence-review.php">Evidence Review</a>
        <a class="btn dark" href="/ops/mobile-submit-trial-run.php">Trial Run</a>
    </div>
</section>

<section class="three">
    <?= opsui_metric($evidenceTableExists ? 'YES' : 'NO', 'Evidence table installed') ?>
    <?= opsui_metric((string)$evidenceTotal, 'Saved evidence records') ?>
    <?= opsui_metric($db instanceof mysqli ? 'OK' : 'ERROR', 'DB connectivity') ?>
</section>

<?php if ($dbError !== ''): ?>
<section class="card" style="border-left:6px solid #b42318;">
    <h2>DB notice</h2>
    <p class="badline"><strong><?= msec_h($dbError) ?></strong></p>
</section>
<?php endif; ?>

<section class="card">
    <h2>Evidence workflow</h2>
    <div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;">
        <div class="metric"><strong>1</strong><span>Run trial on latest/pasted email</span></div>
        <div class="metric"><strong>2</strong><span>Generate sanitized evidence JSON</span></div>
        <div class="metric"><strong>3</strong><span>Save sanitized evidence to log</span></div>
        <div class="metric"><strong>4</strong><span>Review records and resolve blockers</span></div>
    </div>
    <p class="small">Evidence records are for dry-run/mobile submit readiness only. They do not authorize or perform EDXEIX submission.</p>
</section>

<section class="card">
    <h2>Evidence tools</h2>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Tool</th><th>Status</th><th>Purpose</th><th>Open</th></tr></thead>
            <tbody>
            <?php foreach ($routes as $route): ?>
                <?php $status = msec_path_status($publicRoot . str_replace('/ops', '/ops', $route[0])); ?>
                <tr>
                    <td><strong><?= msec_h($route[1]) ?></strong><br><code><?= msec_h($route[0]) ?></code></td>
                    <td><?= msec_badge($status['exists'] && $status['readable'], 'AVAILABLE', 'MISSING') ?></td>
                    <td><?= msec_h($route[2]) ?></td>
                    <td><a class="btn light" href="<?= msec_h($route[0]) ?>">Open</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="two">
    <div class="card">
        <h2>Private class readiness</h2>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Component</th><th>Status</th><th>Path</th></tr></thead>
                <tbody>
                <?php foreach ($classes as $label => $path): ?>
                    <?php $st = msec_path_status($path); ?>
                    <tr>
                        <td><strong><?= msec_h($label) ?></strong></td>
                        <td><?= msec_badge($st['exists'] && $st['readable'], 'READY', 'MISSING') ?></td>
                        <td><code><?= msec_h($path) ?></code></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <h2>Recent evidence summary</h2>
        <?php if (!$evidenceTableExists): ?>
            <p class="warnline"><strong>Evidence table is not installed yet.</strong></p>
            <p>Install Phase 59 SQL if you want to save evidence records:</p>
            <pre><code>mysql -u cabnet_gov -p cabnet_gov &lt; /home/cabnet/gov.cabnet.app_sql/2026_05_12_mobile_submit_evidence_log.sql</code></pre>
        <?php else: ?>
            <div class="grid" style="grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;">
                <?= opsui_metric((string)$statusCounts['ready'], 'recent ready') ?>
                <?= opsui_metric((string)$statusCounts['review'], 'recent review') ?>
                <?= opsui_metric((string)$statusCounts['blocked'], 'recent blocked') ?>
                <?= opsui_metric((string)$statusCounts['unknown'], 'recent unknown') ?>
            </div>
            <p class="small">Counts are based on the latest saved records visible to this page.</p>
        <?php endif; ?>
    </div>
</section>

<?php if ($recentRows !== []): ?>
<section class="card">
    <h2>Latest saved evidence records</h2>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>ID</th><th>Status</th><th>Lessor</th><th>Driver</th><th>Vehicle</th><th>Starting point</th><th>Email hash</th><th>Created</th><th>Review</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($recentRows as $row): ?>
                <?php
                $id = msec_row_value($row, ['id']);
                $status = msec_row_value($row, ['final_status', 'status', 'readiness_status', 'dry_run_status'], 'unknown');
                $lessor = msec_row_value($row, ['lessor_id', 'edxeix_lessor_id']);
                $driver = msec_row_value($row, ['driver_id', 'edxeix_driver_id']);
                $vehicle = msec_row_value($row, ['vehicle_id', 'edxeix_vehicle_id']);
                $sp = msec_row_value($row, ['starting_point_id', 'edxeix_starting_point_id']);
                $hash = msec_row_value($row, ['raw_email_sha256', 'email_sha256', 'source_hash']);
                $created = msec_row_value($row, ['created_at']);
                ?>
                <tr>
                    <td><?= msec_h($id) ?></td>
                    <td><?= opsui_badge($status !== '' ? $status : 'unknown', str_contains(strtolower($status), 'ready') ? 'good' : (str_contains(strtolower($status), 'block') ? 'bad' : 'warn')) ?></td>
                    <td><?= msec_h($lessor) ?></td>
                    <td><?= msec_h($driver) ?></td>
                    <td><?= msec_h($vehicle) ?></td>
                    <td><?= msec_h($sp) ?></td>
                    <td><code><?= msec_h($hash !== '' ? substr($hash, 0, 16) . '…' : '') ?></code></td>
                    <td><?= msec_h($created) ?></td>
                    <td><?= $id !== '' ? '<a class="btn light" href="/ops/mobile-submit-evidence-review.php?id=' . msec_h($id) . '">Review</a>' : '' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php endif; ?>

<section class="card">
    <h2>Safety boundary</h2>
    <ul class="list">
        <li>This page is a hub only. It does not create, save, or submit anything.</li>
        <li>Saved evidence must remain sanitized: no raw email text, cookies, session values, CSRF token values, credentials, or real config values.</li>
        <li>Live server-side EDXEIX submit remains disabled until Andreas explicitly approves a separate live-submit change.</li>
        <li>The production pre-ride tool remains untouched.</li>
    </ul>
</section>
<?php opsui_shell_end(); ?>
