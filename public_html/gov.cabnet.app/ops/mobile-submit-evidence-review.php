<?php
/**
 * gov.cabnet.app — Mobile Submit Evidence Review
 *
 * Read-only dashboard for sanitized mobile submit dry-run evidence records.
 * This page does not call Bolt, EDXEIX, or AADE, and it does not write data.
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

function msevr_h(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function msevr_badge(string $text, string $type = 'neutral'): string
{
    $type = in_array($type, ['good', 'warn', 'bad', 'neutral'], true) ? $type : 'neutral';
    if (function_exists('opsui_badge')) {
        return opsui_badge($text, $type);
    }
    return '<span class="badge badge-' . msevr_h($type) . '">' . msevr_h($text) . '</span>';
}

function msevr_shell_begin(): void
{
    if (function_exists('opsui_shell_begin')) {
        opsui_shell_begin([
            'title' => 'Mobile Evidence Review',
            'page_title' => 'Mobile Evidence Review',
            'active_section' => 'Mobile Submit',
            'breadcrumbs' => 'Αρχική / Mobile Submit / Evidence Review',
            'safe_notice' => 'Read-only sanitized evidence review. This page does not call EDXEIX, Bolt, or AADE and does not write workflow data.',
            'force_safe_notice' => true,
        ]);
        return;
    }
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Mobile Evidence Review</title><style>body{font-family:Arial,Helvetica,sans-serif;background:#f3f6fb;color:#07152f;margin:0;padding:18px}.card{background:#fff;border:1px solid #d8dde7;border-radius:8px;padding:16px;margin:0 0 16px}.badge{display:inline-block;padding:5px 9px;border-radius:999px;background:#eaf1ff;margin:2px}.badge-good{background:#dcfce7;color:#166534}.badge-warn{background:#fff7ed;color:#b45309}.badge-bad{background:#fee2e2;color:#991b1b}.table-wrap{overflow:auto}table{width:100%;border-collapse:collapse}th,td{border-bottom:1px solid #e5e7eb;padding:9px;text-align:left;vertical-align:top}code,pre{background:#0b1220;color:#dbeafe;border-radius:6px;padding:10px;display:block;overflow:auto}.btn{display:inline-block;background:#2563eb;color:#fff;padding:10px 12px;border-radius:6px;text-decoration:none;font-weight:700}.btn.dark{background:#334155}.small{font-size:13px;color:#667085}</style></head><body>';
}

function msevr_shell_end(): void
{
    if (function_exists('opsui_shell_end')) {
        opsui_shell_end();
        return;
    }
    echo '</body></html>';
}

function msevr_app_context(?string &$error = null): ?array
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
        if (!is_array($ctx)) {
            throw new RuntimeException('Private app bootstrap did not return a context array.');
        }
        $error = null;
        return $ctx;
    } catch (Throwable $e) {
        $loadError = $e->getMessage();
        $error = $loadError;
        return null;
    }
}

function msevr_db(?string &$error = null): ?mysqli
{
    $ctx = msevr_app_context($error);
    if (!$ctx || !isset($ctx['db']) || !method_exists($ctx['db'], 'connection')) {
        $error = $error ?: 'DB context unavailable.';
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

function msevr_table_exists(mysqli $db, string $table): bool
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
function msevr_columns(mysqli $db, string $table): array
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
    }
    return $out;
}

function msevr_pick_col(array $cols, array $choices): ?string
{
    foreach ($choices as $col) {
        if (isset($cols[$col])) {
            return $col;
        }
    }
    return null;
}

function msevr_quote(string $identifier): string
{
    return '`' . str_replace('`', '``', $identifier) . '`';
}

/** @return array<int,array<string,mixed>> */
function msevr_rows(mysqli $db, array $cols, int $limit = 100): array
{
    $order = msevr_pick_col($cols, ['created_at', 'updated_at', 'id']) ?: array_key_first($cols);
    if (!$order) {
        return [];
    }
    $sql = 'SELECT * FROM mobile_submit_evidence_log ORDER BY ' . msevr_quote($order) . ' DESC LIMIT ' . max(1, min(250, $limit));
    $rows = [];
    try {
        $res = $db->query($sql);
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $rows[] = $row;
            }
        }
    } catch (Throwable) {
    }
    return $rows;
}

function msevr_row_value(array $row, array $keys): string
{
    foreach ($keys as $key) {
        if (array_key_exists($key, $row) && trim((string)$row[$key]) !== '') {
            return trim((string)$row[$key]);
        }
    }
    return '';
}

function msevr_json_value(array $json, array $paths): string
{
    foreach ($paths as $path) {
        $node = $json;
        foreach (explode('.', $path) as $part) {
            if (is_array($node) && array_key_exists($part, $node)) {
                $node = $node[$part];
            } else {
                $node = null;
                break;
            }
        }
        if (is_scalar($node) && trim((string)$node) !== '') {
            return trim((string)$node);
        }
    }
    return '';
}

function msevr_evidence_json(array $row): array
{
    $raw = msevr_row_value($row, ['evidence_json', 'json_payload', 'payload_json', 'evidence']);
    if ($raw === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function msevr_redact(mixed $value): mixed
{
    if (is_array($value)) {
        $out = [];
        foreach ($value as $key => $child) {
            $keyText = strtolower((string)$key);
            if (preg_match('/cookie|session|token_value|csrf_value|password|passwd|secret|credential|private|api_key|aade/i', $keyText)) {
                $out[$key] = '__REDACTED__';
            } else {
                $out[$key] = msevr_redact($child);
            }
        }
        return $out;
    }
    if (is_string($value) && preg_match('/PHPSESSID|laravel_session|XSRF-TOKEN|BEGIN\s+(RSA|OPENSSH|PRIVATE)\s+KEY/i', $value)) {
        return '__REDACTED_SENSITIVE_PATTERN__';
    }
    return $value;
}

function msevr_summary(array $row): array
{
    $json = msevr_evidence_json($row);
    $status = msevr_row_value($row, ['final_status', 'dry_run_status', 'result_status', 'status', 'decision']);
    if ($status === '') {
        $status = msevr_json_value($json, ['final_status', 'dry_run_status', 'final.result', 'result.status', 'status']);
    }

    $lessor = msevr_row_value($row, ['lessor_id', 'edxeix_lessor_id']);
    if ($lessor === '') { $lessor = msevr_json_value($json, ['edxeix_ids.lessor_id', 'mapping.lessor_id']); }
    $driver = msevr_row_value($row, ['driver_id', 'edxeix_driver_id']);
    if ($driver === '') { $driver = msevr_json_value($json, ['edxeix_ids.driver_id', 'mapping.driver_id']); }
    $vehicle = msevr_row_value($row, ['vehicle_id', 'edxeix_vehicle_id']);
    if ($vehicle === '') { $vehicle = msevr_json_value($json, ['edxeix_ids.vehicle_id', 'mapping.vehicle_id']); }
    $startingPoint = msevr_row_value($row, ['starting_point_id', 'edxeix_starting_point_id']);
    if ($startingPoint === '') { $startingPoint = msevr_json_value($json, ['edxeix_ids.starting_point_id', 'mapping.starting_point_id']); }

    $hash = msevr_row_value($row, ['raw_email_sha256', 'email_sha256', 'source_sha256', 'hash']);
    if ($hash === '') { $hash = msevr_json_value($json, ['raw_email.sha256', 'email.sha256', 'raw_email_sha256']); }

    return [
        'id' => msevr_row_value($row, ['id']),
        'created_at' => msevr_row_value($row, ['created_at', 'updated_at']),
        'created_by' => msevr_row_value($row, ['created_by_username', 'created_by', 'operator_username', 'user_id']),
        'status' => $status !== '' ? $status : 'unknown',
        'lessor_id' => $lessor,
        'driver_id' => $driver,
        'vehicle_id' => $vehicle,
        'starting_point_id' => $startingPoint,
        'email_sha256' => $hash,
        'json' => $json,
    ];
}

function msevr_status_type(string $status): string
{
    $s = strtolower($status);
    if (str_contains($s, 'ready') || str_contains($s, 'ok') || str_contains($s, 'pass')) { return 'good'; }
    if (str_contains($s, 'blocked') || str_contains($s, 'no-go') || str_contains($s, 'fail') || str_contains($s, 'error')) { return 'bad'; }
    if (str_contains($s, 'review') || str_contains($s, 'warn') || str_contains($s, 'unknown')) { return 'warn'; }
    return 'neutral';
}

$dbError = null;
$db = msevr_db($dbError);
$tableExists = $db ? msevr_table_exists($db, 'mobile_submit_evidence_log') : false;
$cols = ($db && $tableExists) ? msevr_columns($db, 'mobile_submit_evidence_log') : [];
$rows = ($db && $tableExists) ? msevr_rows($db, $cols, 120) : [];
$detailId = trim((string)($_GET['id'] ?? ''));
$detail = null;
$summaries = [];
foreach ($rows as $row) {
    $summary = msevr_summary($row);
    $summaries[] = $summary;
    if ($detailId !== '' && $summary['id'] === $detailId) {
        $detail = $summary;
    }
}

$total = count($summaries);
$ready = 0;
$review = 0;
$blocked = 0;
foreach ($summaries as $s) {
    $type = msevr_status_type((string)$s['status']);
    if ($type === 'good') { $ready++; }
    elseif ($type === 'bad') { $blocked++; }
    else { $review++; }
}

msevr_shell_begin();
?>
<section class="card hero neutral">
    <h1>Mobile Submit Evidence Review</h1>
    <p>Read-only review of sanitized mobile submit dry-run evidence records. Use this to compare trial runs, check EDXEIX ID coverage, and identify records that need mapping or capture review.</p>
    <div>
        <?= msevr_badge('READ ONLY', 'good') ?>
        <?= msevr_badge('NO LIVE SUBMIT', 'good') ?>
        <?= msevr_badge('NO EDXEIX CALL', 'good') ?>
        <?= msevr_badge('NO RAW EMAIL OUTPUT', 'good') ?>
    </div>
    <div class="actions" style="margin-top:12px;display:flex;gap:10px;flex-wrap:wrap;">
        <a class="btn" href="/ops/mobile-submit-evidence.php">Generate Evidence</a>
        <a class="btn dark" href="/ops/mobile-submit-evidence-log.php">Evidence Log</a>
        <a class="btn dark" href="/ops/mobile-submit-trial-run.php">Trial Run</a>
        <a class="btn dark" href="/ops/mobile-submit-center.php">Mobile Submit Center</a>
    </div>
</section>

<?php if ($dbError): ?>
<section class="card" style="border-left:6px solid #b42318;"><h2>DB unavailable</h2><p class="badline"><strong><?= msevr_h($dbError) ?></strong></p></section>
<?php endif; ?>

<section class="card">
    <h2>Evidence log status</h2>
    <div class="grid" style="display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;">
        <div class="metric"><strong><?= msevr_h((string)$total) ?></strong><span>Total saved evidence</span></div>
        <div class="metric"><strong><?= msevr_h((string)$ready) ?></strong><span>Ready / OK</span></div>
        <div class="metric"><strong><?= msevr_h((string)$review) ?></strong><span>Review / unknown</span></div>
        <div class="metric"><strong><?= msevr_h((string)$blocked) ?></strong><span>Blocked / failed</span></div>
    </div>
    <p>Table: <?= msevr_badge($tableExists ? 'mobile_submit_evidence_log PRESENT' : 'mobile_submit_evidence_log MISSING', $tableExists ? 'good' : 'warn') ?></p>
    <?php if (!$tableExists): ?>
        <p class="warnline">Run the Phase 59 SQL migration before using the evidence log:</p>
        <pre>mysql -u cabnet_gov -p cabnet_gov &lt; /home/cabnet/gov.cabnet.app_sql/2026_05_12_mobile_submit_evidence_log.sql</pre>
    <?php endif; ?>
</section>

<?php if ($tableExists): ?>
<section class="card">
    <h2>Saved evidence records</h2>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>ID</th><th>Created</th><th>Status</th><th>Lessor</th><th>Driver</th><th>Vehicle</th><th>Starting point</th><th>Email hash</th><th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($summaries === []): ?>
                <tr><td colspan="9">No evidence records saved yet.</td></tr>
            <?php endif; ?>
            <?php foreach ($summaries as $summary): ?>
                <tr>
                    <td><code><?= msevr_h((string)$summary['id']) ?></code></td>
                    <td><?= msevr_h((string)$summary['created_at']) ?><div class="small"><?= msevr_h((string)$summary['created_by']) ?></div></td>
                    <td><?= msevr_badge((string)$summary['status'], msevr_status_type((string)$summary['status'])) ?></td>
                    <td><?= msevr_h((string)$summary['lessor_id']) ?></td>
                    <td><?= msevr_h((string)$summary['driver_id']) ?></td>
                    <td><?= msevr_h((string)$summary['vehicle_id']) ?></td>
                    <td><?= msevr_h((string)$summary['starting_point_id']) ?></td>
                    <td><code><?= msevr_h(substr((string)$summary['email_sha256'], 0, 16)) ?></code></td>
                    <td><?= $summary['id'] !== '' ? '<a class="btn" href="/ops/mobile-submit-evidence-review.php?id=' . rawurlencode((string)$summary['id']) . '">Review</a>' : '-' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php endif; ?>

<?php if ($detail): ?>
<section class="card">
    <h2>Selected evidence detail #<?= msevr_h((string)$detail['id']) ?></h2>
    <p><?= msevr_badge((string)$detail['status'], msevr_status_type((string)$detail['status'])) ?> <?= msevr_badge('SANITIZED VIEW', 'good') ?></p>
    <pre><?= msevr_h(json_encode(msevr_redact($detail['json']), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}') ?></pre>
</section>
<?php endif; ?>

<section class="card">
    <h2>Safety boundary</h2>
    <ul class="list">
        <li>This page only reads sanitized evidence records from the local DB.</li>
        <li>It does not call EDXEIX and has no live submit controls.</li>
        <li>Raw pre-ride email text should not be stored in the evidence log.</li>
        <li>Any displayed JSON is redacted again before rendering.</li>
    </ul>
</section>
<?php
msevr_shell_end();
