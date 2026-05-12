<?php
/**
 * gov.cabnet.app — Mobile Submit Evidence Snapshot
 *
 * Read-only evidence snapshot for the future mobile/server-side EDXEIX submit workflow.
 * Parses a real pre-ride email and produces a sanitized JSON/text evidence report.
 *
 * Safety contract:
 * - No Bolt calls.
 * - No EDXEIX calls.
 * - No AADE calls.
 * - No database writes.
 * - No queue staging.
 * - No live submission.
 * - No raw email output in evidence JSON.
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

$homeRoot = dirname(__DIR__, 3);
$appRoot = $homeRoot . '/gov.cabnet.app_app';
$bootstrapFile = $appRoot . '/src/bootstrap.php';

$files = [
    $appRoot . '/src/BoltMail/BoltPreRideEmailParser.php',
    $appRoot . '/src/BoltMail/EdxeixMappingLookup.php',
    $appRoot . '/src/BoltMail/MaildirPreRideEmailLoader.php',
    $appRoot . '/src/Edxeix/EdxeixSubmitPreflightGate.php',
    $appRoot . '/src/Edxeix/EdxeixSubmitConnector.php',
    $appRoot . '/src/Edxeix/EdxeixSubmitPayloadValidator.php',
];
foreach ($files as $file) {
    if (is_file($file)) {
        require_once $file;
    }
}

use Bridge\BoltMail\BoltPreRideEmailParser;
use Bridge\BoltMail\EdxeixMappingLookup;
use Bridge\BoltMail\MaildirPreRideEmailLoader;
use Bridge\Edxeix\EdxeixSubmitPreflightGate;
use Bridge\Edxeix\EdxeixSubmitConnector;
use Bridge\Edxeix\EdxeixSubmitPayloadValidator;

function mse_h(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function mse_badge(string $text, string $type = 'neutral'): string
{
    if (function_exists('opsui_badge')) {
        return opsui_badge($text, $type);
    }
    return '<span class="badge badge-' . mse_h($type) . '">' . mse_h($text) . '</span>';
}

function mse_shell_begin(): void
{
    if (function_exists('opsui_shell_begin')) {
        opsui_shell_begin([
            'title' => 'Mobile Submit Evidence',
            'page_title' => 'Mobile Submit Evidence Snapshot',
            'active_section' => 'Mobile Submit',
            'breadcrumbs' => 'Αρχική / Mobile Submit / Evidence Snapshot',
            'safe_notice' => 'Read-only evidence snapshot. This page does not call EDXEIX, does not write workflow data, and does not expose raw email text in the generated evidence JSON.',
            'force_safe_notice' => true,
        ]);
        return;
    }
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Mobile Submit Evidence</title><style>body{font-family:Arial,sans-serif;background:#f3f6fb;color:#07152f;margin:0;padding:18px}.card{background:#fff;border:1px solid #d8dde7;border-radius:8px;padding:16px;margin-bottom:16px}.btn{display:inline-block;background:#2563eb;color:#fff;border:0;border-radius:6px;padding:10px 13px;text-decoration:none;font-weight:700}textarea{width:100%;box-sizing:border-box;min-height:260px}.badge{display:inline-block;padding:5px 9px;border-radius:999px;background:#eaf1ff;margin:2px}.badge-good{background:#dcfce7;color:#166534}.badge-warn{background:#fff7ed;color:#b45309}.badge-bad{background:#fee2e2;color:#991b1b}.small{color:#667085;font-size:13px}.grid{display:grid;grid-template-columns:repeat(4,1fr);gap:12px}.metric{background:#f8fbff;border:1px solid #d8dde7;border-radius:8px;padding:12px}.metric strong{font-size:24px;display:block}@media(max-width:900px){.grid{grid-template-columns:1fr}}</style></head><body>';
}

function mse_shell_end(): void
{
    if (function_exists('opsui_shell_end')) {
        opsui_shell_end();
        return;
    }
    echo '</body></html>';
}

function mse_csrf(): string
{
    if (empty($_SESSION['mobile_submit_evidence_csrf']) || !is_string($_SESSION['mobile_submit_evidence_csrf'])) {
        $_SESSION['mobile_submit_evidence_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['mobile_submit_evidence_csrf'];
}

function mse_csrf_ok(string $token): bool
{
    return isset($_SESSION['mobile_submit_evidence_csrf'])
        && is_string($_SESSION['mobile_submit_evidence_csrf'])
        && hash_equals($_SESSION['mobile_submit_evidence_csrf'], $token);
}

function mse_context(?string &$error = null): ?array
{
    static $loaded = false;
    static $ctx = null;
    static $err = null;
    global $bootstrapFile;

    if ($loaded) {
        $error = $err;
        return is_array($ctx) ? $ctx : null;
    }
    $loaded = true;
    if (!is_file($bootstrapFile)) {
        $err = 'Private app bootstrap not found.';
        $error = $err;
        return null;
    }
    try {
        $ctx = require $bootstrapFile;
        if (!is_array($ctx)) {
            throw new RuntimeException('Private app bootstrap did not return context array.');
        }
        $error = null;
        return $ctx;
    } catch (Throwable $e) {
        $err = $e->getMessage();
        $error = $err;
        return null;
    }
}

function mse_db(?string &$error = null): ?mysqli
{
    $ctx = mse_context($error);
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

function mse_table_exists(mysqli $db, string $table): bool
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

function mse_latest_capture(mysqli $db): ?array
{
    if (!mse_table_exists($db, 'ops_edxeix_submit_captures')) {
        return null;
    }
    try {
        $res = $db->query('SELECT * FROM ops_edxeix_submit_captures ORDER BY id DESC LIMIT 1');
        $row = $res ? $res->fetch_assoc() : null;
        return is_array($row) ? $row : null;
    } catch (Throwable) {
        return null;
    }
}

function mse_load_latest_email(): array
{
    if (!class_exists(MaildirPreRideEmailLoader::class)) {
        return ['ok' => false, 'email_text' => '', 'error' => 'MaildirPreRideEmailLoader class not installed.', 'source' => '', 'source_mtime' => ''];
    }
    $extraDirs = [];
    $ctxError = null;
    $ctx = mse_context($ctxError);
    if (is_array($ctx) && isset($ctx['config']) && method_exists($ctx['config'], 'get')) {
        foreach (['mail.pre_ride_maildir', 'mail.bolt_bridge_maildir'] as $key) {
            $value = $ctx['config']->get($key);
            if (is_string($value) && trim($value) !== '') {
                $extraDirs[] = trim($value);
            }
        }
        $many = $ctx['config']->get('mail.pre_ride_maildirs', []);
        if (is_array($many)) {
            foreach ($many as $dir) {
                if (is_string($dir) && trim($dir) !== '') {
                    $extraDirs[] = trim($dir);
                }
            }
        }
    }
    try {
        $loader = new MaildirPreRideEmailLoader();
        return $loader->loadLatest(array_values(array_unique($extraDirs)));
    } catch (Throwable $e) {
        return ['ok' => false, 'email_text' => '', 'error' => $e->getMessage(), 'source' => '', 'source_mtime' => ''];
    }
}

function mse_lookup(array $fields, ?string &$error = null): array
{
    $empty = [
        'ok' => false,
        'lessor_id' => '',
        'driver_id' => '',
        'vehicle_id' => '',
        'starting_point_id' => '',
        'messages' => [],
        'warnings' => ['Mapping lookup unavailable.'],
    ];
    if (!class_exists(EdxeixMappingLookup::class)) {
        $error = 'EdxeixMappingLookup class not installed.';
        return $empty;
    }
    $db = mse_db($error);
    if (!$db) {
        return $empty;
    }
    try {
        $lookup = new EdxeixMappingLookup($db);
        $result = $lookup->lookup($fields);
        return is_array($result) ? $result : $empty;
    } catch (Throwable $e) {
        $error = $e->getMessage();
        $empty['warnings'] = ['Mapping lookup failed: ' . $error];
        return $empty;
    }
}

function mse_lessor_starting_point_status(mysqli $db, string $lessorId, string $startingPointId): array
{
    $out = [
        'has_table' => mse_table_exists($db, 'mapping_lessor_starting_points'),
        'lessor_id' => $lessorId,
        'starting_point_id' => $startingPointId,
        'has_active_override' => false,
        'override_matches_resolver' => false,
        'rows' => [],
    ];
    if (!$out['has_table'] || $lessorId === '') {
        return $out;
    }
    try {
        $stmt = $db->prepare('SELECT id, edxeix_lessor_id, internal_key, label, edxeix_starting_point_id, is_active FROM mapping_lessor_starting_points WHERE edxeix_lessor_id = ? ORDER BY is_active DESC, id ASC');
        $stmt->bind_param('s', $lessorId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $out['rows'][] = $row;
            if ((string)($row['is_active'] ?? '') === '1' && trim((string)($row['edxeix_starting_point_id'] ?? '')) !== '') {
                $out['has_active_override'] = true;
                if ($startingPointId !== '' && trim((string)$row['edxeix_starting_point_id']) === $startingPointId) {
                    $out['override_matches_resolver'] = true;
                }
            }
        }
    } catch (Throwable) {
    }
    return $out;
}

function mse_safe_parsed_fields(array $fields): array
{
    $allow = [
        'operator', 'customer_name', 'customer_phone', 'driver_name', 'vehicle_plate',
        'pickup_address', 'dropoff_address', 'pickup_datetime_local', 'end_datetime_local',
        'pickup_date', 'pickup_time', 'estimated_price_amount', 'estimated_price_text',
        'order_reference',
    ];
    $out = [];
    foreach ($allow as $key) {
        if (array_key_exists($key, $fields)) {
            $out[$key] = (string)$fields[$key];
        }
    }
    return $out;
}

function mse_redact_capture(?array $capture): ?array
{
    if (!$capture) {
        return null;
    }
    $allow = [
        'id', 'form_method', 'action_host', 'action_path', 'csrf_field_name',
        'coordinate_field_names', 'required_field_names', 'select_field_names',
        'submit_success_indicators', 'submit_error_indicators', 'created_at', 'updated_at',
    ];
    $out = [];
    foreach ($allow as $key) {
        if (array_key_exists($key, $capture)) {
            $out[$key] = (string)$capture[$key];
        }
    }
    if (isset($out['csrf_field_name']) && $out['csrf_field_name'] !== '') {
        $out['csrf_token_value'] = '__NOT_STORED_NOT_DISPLAYED__';
    }
    return $out;
}

function mse_redact_request(?array $request): ?array
{
    if (!$request) {
        return null;
    }
    $copy = $request;
    if (isset($copy['headers_preview']) && is_array($copy['headers_preview'])) {
        foreach ($copy['headers_preview'] as $k => $v) {
            if (stripos((string)$k, 'token') !== false || stripos((string)$k, 'cookie') !== false) {
                $copy['headers_preview'][$k] = '__NOT_STORED_NOT_DISPLAYED__';
            }
        }
    }
    if (isset($copy['payload_preview']) && is_array($copy['payload_preview'])) {
        foreach ($copy['payload_preview'] as $k => $v) {
            $lk = strtolower((string)$k);
            if ($lk === '_token' || str_contains($lk, 'csrf') || str_contains($lk, 'cookie') || str_contains($lk, 'session')) {
                $copy['payload_preview'][$k] = '__NOT_STORED_NOT_DISPLAYED__';
            }
        }
    }
    return $copy;
}

function mse_build_evidence(string $rawEmail, array $parseResult, array $mapping, ?array $capture, ?array $preflight, ?array $request, ?array $validation, ?array $spStatus): array
{
    $fields = is_array($parseResult['fields'] ?? null) ? $parseResult['fields'] : [];
    return [
        'generated_at' => date('c'),
        'system' => 'gov.cabnet.app mobile/server-side EDXEIX submit evidence snapshot',
        'safety_contract' => [
            'calls_bolt' => false,
            'calls_edxeix' => false,
            'calls_aade' => false,
            'writes_database' => false,
            'live_submit_enabled' => false,
            'raw_email_included' => false,
        ],
        'raw_email' => [
            'included' => false,
            'sha256' => hash('sha256', $rawEmail),
            'byte_length' => strlen($rawEmail),
        ],
        'parser' => [
            'ok' => (bool)($parseResult['ok'] ?? false),
            'confidence' => (string)($parseResult['confidence'] ?? ''),
            'missing_required' => array_values((array)($parseResult['missing_required'] ?? [])),
            'warnings' => array_values((array)($parseResult['warnings'] ?? [])),
            'fields' => mse_safe_parsed_fields($fields),
        ],
        'mapping' => [
            'ok' => (bool)($mapping['ok'] ?? false),
            'lookup_version' => (string)($mapping['lookup_version'] ?? ''),
            'lessor_id' => (string)($mapping['lessor_id'] ?? ''),
            'lessor_source' => (string)($mapping['lessor_source'] ?? ''),
            'driver_id' => (string)($mapping['driver_id'] ?? ''),
            'driver_label' => (string)($mapping['driver_label'] ?? ''),
            'vehicle_id' => (string)($mapping['vehicle_id'] ?? ''),
            'vehicle_label' => (string)($mapping['vehicle_label'] ?? ''),
            'starting_point_id' => (string)($mapping['starting_point_id'] ?? ''),
            'starting_point_label' => (string)($mapping['starting_point_label'] ?? ''),
            'messages' => array_values((array)($mapping['messages'] ?? [])),
            'warnings' => array_values((array)($mapping['warnings'] ?? [])),
        ],
        'lessor_starting_point_status' => $spStatus,
        'sanitized_submit_capture' => mse_redact_capture($capture),
        'preflight_gate' => $preflight,
        'connector_request_preview' => mse_redact_request($request),
        'payload_validation' => $validation,
        'final_status' => [
            'dry_run_ready' => (bool)(($preflight['dry_run_payload_allowed'] ?? false) && ($validation['dry_run_payload_valid'] ?? false)),
            'live_submit_allowed' => false,
            'live_submit_blocker' => 'live_submit_disabled_by_design',
        ],
    ];
}

$csrf = mse_csrf();
$rawEmail = '';
$error = '';
$mailLoad = null;
$parseResult = null;
$mapping = [];
$capture = null;
$preflight = null;
$request = null;
$validation = null;
$spStatus = null;
$evidence = null;
$dbError = null;
$lookupError = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!mse_csrf_ok((string)($_POST['csrf'] ?? ''))) {
        $error = 'Security token expired. Please reload and try again.';
    } else {
        $action = (string)($_POST['action'] ?? 'parse_pasted');
        if ($action === 'load_latest_server_email') {
            $mailLoad = mse_load_latest_email();
            if (!empty($mailLoad['ok'])) {
                $rawEmail = (string)($mailLoad['email_text'] ?? '');
            } else {
                $error = (string)($mailLoad['error'] ?? 'Unable to load latest server email.');
                $rawEmail = (string)($_POST['email_text'] ?? '');
            }
        } else {
            $rawEmail = (string)($_POST['email_text'] ?? '');
        }

        if ($error === '' && trim($rawEmail) === '') {
            $error = 'No email text was provided.';
        }

        if ($error === '') {
            try {
                if (!class_exists(BoltPreRideEmailParser::class)) {
                    throw new RuntimeException('BoltPreRideEmailParser class not installed.');
                }
                $parser = new BoltPreRideEmailParser();
                $parseResult = $parser->parse($rawEmail);
                $fields = is_array($parseResult['fields'] ?? null) ? $parseResult['fields'] : [];
                $mapping = mse_lookup($fields, $lookupError);
                $db = mse_db($dbError);
                $capture = $db ? mse_latest_capture($db) : null;
                $spStatus = $db ? mse_lessor_starting_point_status($db, (string)($mapping['lessor_id'] ?? ''), (string)($mapping['starting_point_id'] ?? '')) : null;

                if (class_exists(EdxeixSubmitPreflightGate::class)) {
                    $gate = new EdxeixSubmitPreflightGate();
                    $preflight = $gate->evaluate($fields, $mapping, $capture, [
                        'live_connector_enabled' => false,
                        'operator_final_confirmed' => false,
                        'map_point_confirmed' => false,
                    ]);
                }
                if (class_exists(EdxeixSubmitConnector::class)) {
                    $connector = new EdxeixSubmitConnector();
                    $request = $connector->buildRequest($fields, $mapping, $capture);
                }
                if (class_exists(EdxeixSubmitPayloadValidator::class)) {
                    $validator = new EdxeixSubmitPayloadValidator();
                    $validation = $validator->validate(is_array($request) ? $request : [], $capture, $preflight);
                }

                $evidence = mse_build_evidence($rawEmail, $parseResult, $mapping, $capture, $preflight, $request, $validation, $spStatus);
            } catch (Throwable $e) {
                $error = $e->getMessage();
            }
        }
    }
}

if (strtolower((string)($_GET['format'] ?? '')) === 'json' && $evidence !== null) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($evidence, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

mse_shell_begin();
?>
<style>
.mse-grid{display:grid;grid-template-columns:minmax(0,1fr) minmax(330px,.72fr);gap:18px}.mse-card{background:#fff;border:1px solid #d8dde7;border-radius:4px;padding:18px 20px;box-shadow:0 6px 18px rgba(26,33,52,.06);margin-bottom:18px}.mse-card h2{margin-top:0}.mse-actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:12px}.mse-textarea{min-height:300px;font-family:Arial,Helvetica,sans-serif}.mse-json{white-space:pre-wrap;background:#0b1220;color:#dbeafe;border-radius:6px;padding:14px;overflow:auto;max-height:620px;font-family:Consolas,Menlo,monospace;font-size:12px;line-height:1.42}.mse-kv{display:grid;grid-template-columns:190px minmax(0,1fr);gap:8px 12px;border-top:1px solid #eef1f5}.mse-kv>div{padding:9px 0;border-bottom:1px solid #eef1f5}.mse-key{font-weight:700;color:#667085}.mse-risk{border-left:6px solid #b45309}.mse-good{border-left:6px solid #166534}.mse-bad{border-left:6px solid #991b1b}@media(max-width:980px){.mse-grid{grid-template-columns:1fr}.mse-kv{grid-template-columns:1fr}.mse-actions .btn{width:100%;text-align:center}}
</style>

<section class="card hero neutral">
    <h1>Mobile Submit Evidence Snapshot</h1>
    <p>Create a sanitized evidence snapshot for a real pre-ride email trial. The raw email is never printed in the evidence JSON; only its SHA-256 hash and parsed/mapped facts are included.</p>
    <div>
        <?= mse_badge('READ ONLY', 'good') ?>
        <?= mse_badge('NO RAW EMAIL IN JSON', 'good') ?>
        <?= mse_badge('NO EDXEIX CALL', 'good') ?>
        <?= mse_badge('NO LIVE SUBMIT', 'good') ?>
    </div>
</section>

<section class="mse-grid">
    <div>
        <form class="mse-card" method="post" action="/ops/mobile-submit-evidence.php" autocomplete="off">
            <h2>1. Load or paste pre-ride email</h2>
            <p class="small">Use this after a trial run to capture sanitized proof of what the system would do, without submitting anything.</p>
            <input type="hidden" name="csrf" value="<?= mse_h($csrf) ?>">
            <textarea class="mse-textarea" name="email_text" placeholder="Paste Bolt pre-ride email here..."><?= mse_h($rawEmail) ?></textarea>
            <div class="mse-actions">
                <button class="btn green" type="submit" name="action" value="parse_pasted">Build evidence snapshot</button>
                <button class="btn gold" type="submit" name="action" value="load_latest_server_email">Load latest server email</button>
                <a class="btn dark" href="/ops/mobile-submit-evidence.php">Clear</a>
            </div>
            <?php if (is_array($mailLoad) && !empty($mailLoad['ok'])): ?>
                <p class="goodline"><strong>Loaded:</strong> <?= mse_h((string)($mailLoad['source'] ?? '')) ?> <?= !empty($mailLoad['source_mtime']) ? '(' . mse_h((string)$mailLoad['source_mtime']) . ')' : '' ?></p>
            <?php endif; ?>
        </form>

        <?php if ($error !== ''): ?>
            <section class="mse-card mse-bad"><h2>Problem</h2><p class="badline"><strong><?= mse_h($error) ?></strong></p></section>
        <?php endif; ?>

        <?php if ($evidence !== null): ?>
            <section class="mse-card <?= !empty($evidence['final_status']['dry_run_ready']) ? 'mse-good' : 'mse-risk' ?>">
                <h2>2. Evidence summary</h2>
                <div class="grid">
                    <div class="metric"><strong><?= !empty($evidence['final_status']['dry_run_ready']) ? 'READY' : 'REVIEW' ?></strong><span>Dry-run state</span></div>
                    <div class="metric"><strong><?= mse_h((string)($evidence['mapping']['lessor_id'] ?? '-')) ?></strong><span>Lessor ID</span></div>
                    <div class="metric"><strong><?= mse_h((string)($evidence['mapping']['driver_id'] ?? '-')) ?></strong><span>Driver ID</span></div>
                    <div class="metric"><strong><?= mse_h((string)($evidence['mapping']['starting_point_id'] ?? '-')) ?></strong><span>Starting point</span></div>
                </div>
                <div class="mse-kv" style="margin-top:14px;">
                    <div class="mse-key">Raw email hash</div><div><code><?= mse_h((string)$evidence['raw_email']['sha256']) ?></code></div>
                    <div class="mse-key">Parser confidence</div><div><?= mse_h((string)$evidence['parser']['confidence']) ?></div>
                    <div class="mse-key">Vehicle ID</div><div><?= mse_h((string)$evidence['mapping']['vehicle_id']) ?></div>
                    <div class="mse-key">Starting point override</div><div><?= !empty($evidence['lessor_starting_point_status']['override_matches_resolver']) ? mse_badge('LESSOR-SPECIFIC OK', 'good') : mse_badge('CHECK OVERRIDE', 'warn') ?></div>
                    <div class="mse-key">Live submit</div><div><?= mse_badge('BLOCKED BY DESIGN', 'good') ?></div>
                </div>
                <div class="mse-actions">
                    <button class="btn" type="button" onclick="copyEvidenceJson()">Copy evidence JSON</button>
                    <a class="btn dark" href="/ops/mobile-submit-trial-run.php">Trial Run</a>
                    <a class="btn dark" href="/ops/mobile-submit-center.php">Mobile Submit Center</a>
                </div>
            </section>

            <section class="mse-card">
                <h2>3. Sanitized evidence JSON</h2>
                <p class="small">This JSON intentionally excludes the raw email and does not contain live cookies, sessions, credentials, or CSRF token values.</p>
                <pre id="mse-evidence-json" class="mse-json"><?= mse_h(json_encode($evidence, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}') ?></pre>
            </section>
        <?php endif; ?>
    </div>

    <aside>
        <section class="mse-card">
            <h2>Use this page when</h2>
            <ul class="list">
                <li>A mapping issue is suspected.</li>
                <li>A trial run should be saved as sanitized proof.</li>
                <li>A future mobile submit decision needs evidence.</li>
                <li>A new chat/session needs exact current facts without exposing raw email.</li>
            </ul>
        </section>
        <section class="mse-card">
            <h2>Evidence excludes</h2>
            <ul class="list">
                <li>Raw pre-ride email body</li>
                <li>Cookies and session values</li>
                <li>CSRF token values</li>
                <li>Real credentials or config values</li>
                <li>Any live submit action</li>
            </ul>
        </section>
        <section class="mse-card">
            <h2>Related tools</h2>
            <div class="actions">
                <a class="btn dark" href="/ops/mobile-submit-trial-run.php">Trial Run</a>
                <a class="btn dark" href="/ops/mobile-submit-gates.php">Gates</a>
                <a class="btn dark" href="/ops/edxeix-submit-payload-validator.php">Payload Validator</a>
                <a class="btn dark" href="/ops/mapping-resolver-test.php">Mapping Resolver</a>
            </div>
        </section>
    </aside>
</section>

<script>
function copyEvidenceJson() {
    var el = document.getElementById('mse-evidence-json');
    if (!el) { return; }
    var text = el.textContent || '';
    navigator.clipboard.writeText(text).then(function () {
        alert('Evidence JSON copied.');
    }).catch(function () {
        var ta = document.createElement('textarea');
        ta.value = text;
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        ta.remove();
        alert('Evidence JSON copied.');
    });
}
</script>
<?php mse_shell_end(); ?>
