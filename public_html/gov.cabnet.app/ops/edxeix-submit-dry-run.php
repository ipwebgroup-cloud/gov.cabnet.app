<?php
/**
 * gov.cabnet.app — EDXEIX Submit Dry-Run Builder v0.1
 *
 * Purpose:
 * - Build a read-only preview of the future server-side EDXEIX submit payload.
 * - Use parsed Bolt pre-ride email + EDXEIX ID mapping + sanitized capture metadata.
 * - Help identify missing field names, map fields, and safety blockers.
 *
 * Safety contract:
 * - No Bolt API calls.
 * - No EDXEIX calls.
 * - No AADE calls.
 * - No workflow database writes.
 * - No queue staging.
 * - No live submission.
 * - Does not read/display cookies, sessions, tokens, passwords, or CSRF token values.
 * - Production pre-ride tool is not modified by this file.
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

$root = dirname(__DIR__, 3);
$parserFile = $root . '/gov.cabnet.app_app/src/BoltMail/BoltPreRideEmailParser.php';
$lookupFile = $root . '/gov.cabnet.app_app/src/BoltMail/EdxeixMappingLookup.php';
$mailLoaderFile = $root . '/gov.cabnet.app_app/src/BoltMail/MaildirPreRideEmailLoader.php';
$bootstrapFile = $root . '/gov.cabnet.app_app/src/bootstrap.php';

if (is_file($parserFile)) { require_once $parserFile; }
if (is_file($lookupFile)) { require_once $lookupFile; }
if (is_file($mailLoaderFile)) { require_once $mailLoaderFile; }

use Bridge\BoltMail\BoltPreRideEmailParser;
use Bridge\BoltMail\EdxeixMappingLookup;
use Bridge\BoltMail\MaildirPreRideEmailLoader;

function esd_h(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function esd_badge(string $text, string $type = 'neutral'): string
{
    $type = in_array($type, ['good', 'warn', 'bad', 'neutral'], true) ? $type : 'neutral';
    if (function_exists('opsui_badge')) {
        return opsui_badge($text, $type);
    }
    return '<span class="badge badge-' . esd_h($type) . '">' . esd_h($text) . '</span>';
}

function esd_csrf(): string
{
    if (empty($_SESSION['edxeix_submit_dry_run_csrf']) || !is_string($_SESSION['edxeix_submit_dry_run_csrf'])) {
        $_SESSION['edxeix_submit_dry_run_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['edxeix_submit_dry_run_csrf'];
}

function esd_validate_csrf(string $token): bool
{
    return isset($_SESSION['edxeix_submit_dry_run_csrf'])
        && is_string($_SESSION['edxeix_submit_dry_run_csrf'])
        && hash_equals($_SESSION['edxeix_submit_dry_run_csrf'], $token);
}

function esd_app_context(?string &$error = null): ?array
{
    static $ctx = null;
    static $loaded = false;
    static $loadError = null;
    global $bootstrapFile;

    if ($loaded) {
        $error = $loadError;
        return is_array($ctx) ? $ctx : null;
    }

    $loaded = true;
    if (!is_file($bootstrapFile)) {
        $loadError = 'Private app bootstrap was not found.';
        $error = $loadError;
        return null;
    }

    try {
        $ctx = require $bootstrapFile;
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

function esd_db(?string &$error = null): ?mysqli
{
    $ctx = esd_app_context($error);
    if (!$ctx || !isset($ctx['db']) || !method_exists($ctx['db'], 'connection')) {
        $error = $error ?: 'Database context is unavailable.';
        return null;
    }
    try {
        return $ctx['db']->connection();
    } catch (Throwable $e) {
        $error = $e->getMessage();
        return null;
    }
}

function esd_table_exists(mysqli $db, string $table): bool
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

function esd_columns(mysqli $db, string $table): array
{
    $cols = [];
    try {
        $stmt = $db->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION');
        $stmt->bind_param('s', $table);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $name = (string)($row['COLUMN_NAME'] ?? '');
            if ($name !== '') { $cols[$name] = true; }
        }
    } catch (Throwable) {
    }
    return $cols;
}

function esd_col(array $row, array $names, string $default = ''): string
{
    foreach ($names as $name) {
        if (array_key_exists($name, $row) && $row[$name] !== null && trim((string)$row[$name]) !== '') {
            return trim((string)$row[$name]);
        }
    }
    return $default;
}

function esd_decode_list(string $raw): array
{
    $raw = trim($raw);
    if ($raw === '') { return []; }
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        $out = [];
        foreach ($decoded as $item) {
            $value = trim((string)$item);
            if ($value !== '') { $out[] = $value; }
        }
        return array_values(array_unique($out));
    }
    $parts = preg_split('/[\r\n,;]+/', $raw) ?: [];
    $out = [];
    foreach ($parts as $part) {
        $value = trim((string)$part);
        if ($value !== '') { $out[] = $value; }
    }
    return array_values(array_unique($out));
}

function esd_latest_capture(?string &$error = null): array
{
    $db = esd_db($error);
    if (!$db) {
        return ['ok' => false, 'row' => null, 'columns' => [], 'error' => $error ?: 'DB unavailable.'];
    }

    $table = 'ops_edxeix_submit_captures';
    if (!esd_table_exists($db, $table)) {
        return ['ok' => false, 'row' => null, 'columns' => [], 'error' => 'Capture table is not installed.'];
    }

    $columns = esd_columns($db, $table);
    $order = isset($columns['id']) ? 'id' : (isset($columns['created_at']) ? 'created_at' : array_key_first($columns));
    if (!is_string($order) || $order === '') {
        return ['ok' => false, 'row' => null, 'columns' => $columns, 'error' => 'Capture table has no readable columns.'];
    }

    try {
        $sql = 'SELECT * FROM `' . str_replace('`', '``', $table) . '` ORDER BY `' . str_replace('`', '``', $order) . '` DESC LIMIT 1';
        $res = $db->query($sql);
        $row = $res ? $res->fetch_assoc() : null;
        if (!is_array($row)) {
            return ['ok' => false, 'row' => null, 'columns' => $columns, 'error' => 'No sanitized EDXEIX submit capture exists yet.'];
        }
        return ['ok' => true, 'row' => $row, 'columns' => $columns, 'error' => ''];
    } catch (Throwable $e) {
        return ['ok' => false, 'row' => null, 'columns' => $columns, 'error' => $e->getMessage()];
    }
}

function esd_capture_meta(array $row): array
{
    $requiredRaw = esd_col($row, ['required_field_names_json', 'required_fields_json', 'required_field_names', 'required_fields']);
    $selectRaw = esd_col($row, ['select_field_names_json', 'select_fields_json', 'select_field_names', 'select_fields']);

    return [
        'id' => esd_col($row, ['id']),
        'method' => strtoupper(esd_col($row, ['form_method', 'method'], 'POST')),
        'action_host' => esd_col($row, ['action_host', 'form_action_host', 'host']),
        'action_path' => esd_col($row, ['action_path', 'form_action_path', 'path']),
        'csrf_field_name' => esd_col($row, ['csrf_field_name', 'csrf_name']),
        'lat_field_name' => esd_col($row, ['map_lat_field_name', 'lat_field_name', 'latitude_field_name']),
        'lng_field_name' => esd_col($row, ['map_lng_field_name', 'lng_field_name', 'longitude_field_name']),
        'address_field_name' => esd_col($row, ['map_address_field_name', 'address_field_name']),
        'required_fields' => esd_decode_list($requiredRaw),
        'select_fields' => esd_decode_list($selectRaw),
        'captured_at' => esd_col($row, ['created_at', 'captured_at', 'updated_at']),
        'notes' => esd_col($row, ['notes', 'sanitized_notes']),
    ];
}

function esd_load_latest_server_email(): array
{
    if (!class_exists(MaildirPreRideEmailLoader::class)) {
        return [
            'ok' => false,
            'email_text' => '',
            'source' => '',
            'source_mtime' => '',
            'error' => 'MaildirPreRideEmailLoader class is not installed.',
            'checked_dirs' => [],
        ];
    }

    $extraDirs = [];
    $ctxError = null;
    $ctx = esd_app_context($ctxError);
    if (is_array($ctx) && isset($ctx['config']) && method_exists($ctx['config'], 'get')) {
        foreach (['mail.pre_ride_maildir', 'mail.bolt_bridge_maildir'] as $key) {
            $single = $ctx['config']->get($key);
            if (is_string($single) && trim($single) !== '') { $extraDirs[] = trim($single); }
        }
        $many = $ctx['config']->get('mail.pre_ride_maildirs', []);
        if (is_array($many)) {
            foreach ($many as $dir) {
                if (is_string($dir) && trim($dir) !== '') { $extraDirs[] = trim($dir); }
            }
        }
    }

    try {
        $loader = new MaildirPreRideEmailLoader();
        return $loader->loadLatest(array_values(array_unique($extraDirs)));
    } catch (Throwable $e) {
        return [
            'ok' => false,
            'email_text' => '',
            'source' => '',
            'source_mtime' => '',
            'error' => $e->getMessage(),
            'checked_dirs' => $extraDirs,
        ];
    }
}

function esd_lookup_ids(array $fields, ?string &$error = null): array
{
    $empty = [
        'ok' => false,
        'lessor_id' => '',
        'driver_id' => '',
        'vehicle_id' => '',
        'starting_point_id' => '',
        'messages' => [],
        'warnings' => ['EDXEIX ID lookup was not available.'],
    ];

    if (!class_exists(EdxeixMappingLookup::class)) {
        $error = 'EdxeixMappingLookup class is not installed.';
        return $empty;
    }

    $ctx = esd_app_context($error);
    if (!$ctx || !isset($ctx['db']) || !method_exists($ctx['db'], 'connection')) {
        return $empty;
    }

    try {
        $lookup = new EdxeixMappingLookup($ctx['db']->connection());
        $result = $lookup->lookup($fields);
        $error = null;
        return is_array($result) ? $result : $empty;
    } catch (Throwable $e) {
        $error = $e->getMessage();
        $empty['warnings'] = ['DB lookup failed: ' . $error];
        return $empty;
    }
}

function esd_field(array $fields, string $key): string
{
    return trim((string)($fields[$key] ?? ''));
}

function esd_future_status(array $fields, int $guardMinutes = 30): array
{
    $raw = esd_field($fields, 'pickup_datetime_local');
    if ($raw === '') {
        return ['ok' => false, 'type' => 'bad', 'label' => 'Missing pickup datetime', 'minutes' => null, 'blocker' => 'missing_pickup_datetime'];
    }
    try {
        $tz = new DateTimeZone('Europe/Athens');
        $pickup = new DateTimeImmutable($raw, $tz);
        $now = new DateTimeImmutable('now', $tz);
        $seconds = $pickup->getTimestamp() - $now->getTimestamp();
        $minutes = (int)floor($seconds / 60);
        if ($seconds <= 0) {
            return ['ok' => false, 'type' => 'bad', 'label' => 'Past or already due', 'minutes' => $minutes, 'blocker' => 'pickup_not_future'];
        }
        if ($seconds < ($guardMinutes * 60)) {
            return ['ok' => false, 'type' => 'warn', 'label' => 'Future but inside guard window', 'minutes' => $minutes, 'blocker' => 'pickup_inside_future_guard'];
        }
        return ['ok' => true, 'type' => 'good', 'label' => 'Future ride window OK', 'minutes' => $minutes, 'blocker' => ''];
    } catch (Throwable) {
        return ['ok' => false, 'type' => 'bad', 'label' => 'Invalid pickup datetime', 'minutes' => null, 'blocker' => 'invalid_pickup_datetime'];
    }
}

function esd_build_canonical_payload(array $fields, array $mapping, array $captureMeta): array
{
    return [
        '_mode' => 'dry_run_preview_only',
        '_would_call_edxeix' => false,
        '_capture_id' => $captureMeta['id'] ?? '',
        '_form' => [
            'method' => $captureMeta['method'] ?? 'POST',
            'action_host' => $captureMeta['action_host'] ?? '',
            'action_path' => $captureMeta['action_path'] ?? '',
            'csrf_field_name_only' => $captureMeta['csrf_field_name'] ?? '',
        ],
        'lessor_id' => trim((string)($mapping['lessor_id'] ?? '')),
        'driver_id' => trim((string)($mapping['driver_id'] ?? '')),
        'vehicle_id' => trim((string)($mapping['vehicle_id'] ?? '')),
        'starting_point_id' => trim((string)($mapping['starting_point_id'] ?? '')),
        'passenger_name' => esd_field($fields, 'customer_name'),
        'passenger_phone' => esd_field($fields, 'customer_phone'),
        'pickup_address' => esd_field($fields, 'pickup_address'),
        'dropoff_address' => esd_field($fields, 'dropoff_address'),
        'pickup_datetime_local' => esd_field($fields, 'pickup_datetime_local'),
        'end_datetime_local' => esd_field($fields, 'end_datetime_local'),
        'price_amount' => esd_field($fields, 'estimated_price_amount'),
        'currency' => esd_field($fields, 'estimated_price_currency') ?: 'EUR',
        'order_reference' => esd_field($fields, 'order_reference'),
        'map' => [
            'latitude_field_name' => $captureMeta['lat_field_name'] ?? '',
            'longitude_field_name' => $captureMeta['lng_field_name'] ?? '',
            'address_field_name' => $captureMeta['address_field_name'] ?? '',
            'latitude_value' => '',
            'longitude_value' => '',
            'operator_must_confirm_map_point' => true,
        ],
    ];
}

function esd_build_field_coverage(array $payload, array $captureMeta): array
{
    $required = is_array($captureMeta['required_fields'] ?? null) ? $captureMeta['required_fields'] : [];
    if ($required === []) {
        $required = [
            'lessor_id', 'driver_id', 'vehicle_id', 'starting_point_id',
            'passenger_name', 'passenger_phone', 'pickup_address', 'dropoff_address',
            'pickup_datetime_local', 'end_datetime_local', 'price_amount'
        ];
    }

    $flat = [];
    $iterator = function (array $arr, string $prefix = '') use (&$flat, &$iterator): void {
        foreach ($arr as $key => $value) {
            $path = $prefix === '' ? (string)$key : $prefix . '.' . (string)$key;
            if (is_array($value)) {
                $iterator($value, $path);
            } else {
                $flat[$path] = (string)$value;
            }
        }
    };
    $iterator($payload);

    $rows = [];
    foreach ($required as $field) {
        $value = $flat[$field] ?? ($payload[$field] ?? null);
        $rows[] = [
            'field' => $field,
            'present' => $value !== null && trim((string)$value) !== '',
            'value_preview' => $value === null ? '' : (string)$value,
        ];
    }
    return $rows;
}

function esd_blockers(array $parseResult, array $mapping, array $capture, array $captureMeta, array $future, array $coverage): array
{
    $blockers = [];
    $missing = is_array($parseResult['missing_required'] ?? null) ? $parseResult['missing_required'] : [];
    foreach ($missing as $label) {
        $blockers[] = 'missing_required_' . strtolower(trim(preg_replace('/[^a-z0-9]+/i', '_', (string)$label), '_'));
    }
    if (!$future['ok'] && $future['blocker'] !== '') { $blockers[] = (string)$future['blocker']; }
    if (empty($mapping['ok'])) { $blockers[] = 'edxeix_mapping_not_ready'; }
    if (trim((string)($mapping['lessor_id'] ?? '')) === '') { $blockers[] = 'missing_edxeix_lessor_id'; }
    if (trim((string)($mapping['driver_id'] ?? '')) === '') { $blockers[] = 'missing_edxeix_driver_id'; }
    if (trim((string)($mapping['vehicle_id'] ?? '')) === '') { $blockers[] = 'missing_edxeix_vehicle_id'; }
    if (empty($capture['ok'])) { $blockers[] = 'missing_sanitized_submit_capture'; }
    if (($captureMeta['action_host'] ?? '') === '' || ($captureMeta['action_path'] ?? '') === '') { $blockers[] = 'missing_edxeix_action_host_or_path'; }
    if (($captureMeta['csrf_field_name'] ?? '') === '') { $blockers[] = 'missing_csrf_field_name_metadata'; }
    if (($captureMeta['lat_field_name'] ?? '') === '' || ($captureMeta['lng_field_name'] ?? '') === '') { $blockers[] = 'missing_map_coordinate_field_names'; }
    foreach ($coverage as $row) {
        if (empty($row['present'])) {
            $blockers[] = 'missing_payload_value_for_' . strtolower(trim(preg_replace('/[^a-z0-9]+/i', '_', (string)$row['field']), '_'));
        }
    }
    $blockers[] = 'live_edxeix_submit_not_implemented_in_dry_run_builder';
    return array_values(array_unique(array_filter($blockers)));
}

function esd_render_kv(string $label, string $value, string $hint = ''): string
{
    return '<div class="kv-row"><div class="k">' . esd_h($label) . '</div><div><strong>' . esd_h($value !== '' ? $value : '-') . '</strong>' . ($hint !== '' ? '<div class="small">' . esd_h($hint) . '</div>' : '') . '</div></div>';
}

function esd_shell_begin(): void
{
    if (function_exists('opsui_shell_begin')) {
        opsui_shell_begin([
            'title' => 'EDXEIX Submit Dry-Run',
            'page_title' => 'EDXEIX Submit Dry-Run Builder',
            'active_section' => 'Mobile Submit',
            'breadcrumbs' => 'Αρχική / Mobile Submit / EDXEIX Dry-Run',
            'safe_notice' => 'Read-only dry-run builder. This page prepares a future server-side submit payload preview but never calls EDXEIX.',
            'force_safe_notice' => true,
        ]);
        return;
    }
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>EDXEIX Submit Dry-Run | gov.cabnet.app</title></head><body>';
}

function esd_shell_end(): void
{
    if (function_exists('opsui_shell_end')) { opsui_shell_end(); return; }
    echo '</body></html>';
}

$csrf = esd_csrf();
$rawEmail = '';
$error = '';
$mailLoad = null;
$parseResult = null;
$mapping = null;
$lookupError = null;
$captureError = null;
$capture = esd_latest_capture($captureError);
$captureMeta = !empty($capture['ok']) && is_array($capture['row'] ?? null) ? esd_capture_meta($capture['row']) : [];
$payload = null;
$coverage = [];
$future = null;
$blockers = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!esd_validate_csrf((string)($_POST['csrf'] ?? ''))) {
        $error = 'Security token expired. Please try again.';
    } else {
        $action = (string)($_POST['action'] ?? 'parse_pasted');
        if ($action === 'load_latest_server_email') {
            $mailLoad = esd_load_latest_server_email();
            if (!empty($mailLoad['ok'])) {
                $rawEmail = (string)$mailLoad['email_text'];
            } else {
                $error = (string)($mailLoad['error'] ?? 'Unable to load latest server email.');
                $rawEmail = (string)($_POST['email_text'] ?? '');
            }
        } else {
            $rawEmail = (string)($_POST['email_text'] ?? '');
        }

        if ($error === '' && trim($rawEmail) !== '') {
            if (!class_exists(BoltPreRideEmailParser::class)) {
                $error = 'BoltPreRideEmailParser class is not installed.';
            } else {
                try {
                    $parser = new BoltPreRideEmailParser();
                    $parseResult = $parser->parse($rawEmail);
                    $fields = is_array($parseResult['fields'] ?? null) ? $parseResult['fields'] : [];
                    $mapping = esd_lookup_ids($fields, $lookupError);
                    $future = esd_future_status($fields, 30);
                    $payload = esd_build_canonical_payload($fields, $mapping, $captureMeta);
                    $coverage = esd_build_field_coverage($payload, $captureMeta);
                    $blockers = esd_blockers($parseResult, $mapping, $capture, $captureMeta, $future, $coverage);
                } catch (Throwable $e) {
                    $error = $e->getMessage();
                }
            }
        } elseif ($error === '') {
            $error = 'No email text was provided.';
        }
    }
}

$fields = is_array($parseResult['fields'] ?? null) ? $parseResult['fields'] : [];

esd_shell_begin();
?>
<style>
.esd-grid{display:grid;grid-template-columns:minmax(0,1fr) minmax(320px,.78fr);gap:18px}.esd-stack{display:grid;gap:14px}.esd-card{background:#fff;border:1px solid #d8dde7;border-radius:4px;padding:18px 20px;box-shadow:0 6px 18px rgba(26,33,52,.06)}.esd-card h2{margin-top:0}.esd-textarea{min-height:250px;font-family:Arial,Helvetica,sans-serif}.esd-actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:12px}.kv-row{display:grid;grid-template-columns:190px minmax(0,1fr);gap:12px;border-bottom:1px solid #eef1f5;padding:10px 0}.kv-row:last-child{border-bottom:0}.k{font-weight:700;color:#667085}.esd-blocker{background:#fee2e2;border:1px solid #fecaca;color:#991b1b;border-radius:4px;padding:9px 10px;margin:6px 0}.esd-warning{background:#fff7ed;border:1px solid #fed7aa;color:#9a3412;border-radius:4px;padding:9px 10px;margin:6px 0}.esd-json{background:#101828;color:#e0e7ff;border-radius:6px;padding:14px;overflow:auto;max-height:520px;font-family:Consolas,Menlo,monospace;font-size:13px;line-height:1.4}.esd-table-wrap{overflow:auto;border:1px solid #d8dde7;border-radius:4px}.esd-table{min-width:720px}.esd-table th,.esd-table td{white-space:nowrap}.esd-muted{opacity:.72}.esd-dev{border-left:5px solid #d4922d}@media(max-width:980px){.esd-grid{grid-template-columns:1fr}.kv-row{grid-template-columns:1fr}.esd-actions .btn{width:100%;text-align:center}.esd-card{padding:15px}.esd-textarea{min-height:220px}}
</style>

<section class="card hero warn">
    <h1>EDXEIX Submit Dry-Run Builder</h1>
    <p>This page previews the future server-side EDXEIX submit payload using parsed Bolt email data, EDXEIX mappings, and sanitized form-capture metadata. It never sends anything to EDXEIX.</p>
    <div>
        <?= esd_badge('DRY-RUN ONLY', 'good') ?>
        <?= esd_badge('NO EDXEIX CALL', 'good') ?>
        <?= esd_badge('NO DB WRITE', 'good') ?>
        <?= esd_badge('LIVE SUBMIT NOT IMPLEMENTED', 'warn') ?>
    </div>
</section>

<section class="esd-grid">
    <div class="esd-stack">
        <form class="esd-card" method="post" action="/ops/edxeix-submit-dry-run.php" autocomplete="off">
            <h2>1. Build dry-run preview</h2>
            <p class="small">Paste a Bolt pre-ride email or load the latest server email. The output is a local payload preview only.</p>
            <input type="hidden" name="csrf" value="<?= esd_h($csrf) ?>">
            <label for="email_text"><strong>Bolt pre-ride email body</strong></label>
            <textarea id="email_text" name="email_text" class="esd-textarea" placeholder="Paste Bolt pre-ride email here..."><?= esd_h($rawEmail) ?></textarea>
            <div class="esd-actions">
                <button class="btn good" type="submit" name="action" value="parse_pasted">Build Dry-Run</button>
                <button class="btn warn" type="submit" name="action" value="load_latest_server_email">Load Latest + Build</button>
                <a class="btn dark" href="/ops/edxeix-submit-dry-run.php">Clear</a>
            </div>
            <?php if (is_array($mailLoad) && !empty($mailLoad['ok'])): ?>
                <p class="goodline"><strong>Loaded:</strong> <?= esd_h((string)($mailLoad['source'] ?? '')) ?> <?= !empty($mailLoad['source_mtime']) ? '(' . esd_h((string)$mailLoad['source_mtime']) . ')' : '' ?></p>
            <?php endif; ?>
        </form>

        <?php if ($error !== ''): ?>
            <section class="esd-card"><h2>Problem</h2><p class="badline"><strong><?= esd_h($error) ?></strong></p></section>
        <?php endif; ?>

        <?php if (is_array($parseResult)): ?>
            <section class="esd-card">
                <h2>2. Parsed ride + mapping summary</h2>
                <div>
                    <?= esd_badge('Parser: ' . (string)($parseResult['confidence'] ?? 'unknown'), empty($parseResult['ok']) ? 'warn' : 'good') ?>
                    <?= esd_badge(!empty($mapping['ok']) ? 'IDs READY' : 'IDS NEED REVIEW', !empty($mapping['ok']) ? 'good' : 'warn') ?>
                    <?= esd_badge(($future['label'] ?? 'Future status unknown'), (string)($future['type'] ?? 'neutral')) ?>
                </div>
                <div class="mobile-kv">
                    <?= esd_render_kv('Passenger', esd_field($fields, 'customer_name'), esd_field($fields, 'customer_phone')) ?>
                    <?= esd_render_kv('Driver', esd_field($fields, 'driver_name'), 'EDXEIX: ' . (string)($mapping['driver_id'] ?? '')) ?>
                    <?= esd_render_kv('Vehicle', esd_field($fields, 'vehicle_plate'), 'EDXEIX: ' . (string)($mapping['vehicle_id'] ?? '')) ?>
                    <?= esd_render_kv('Lessor ID', (string)($mapping['lessor_id'] ?? ''), (string)($mapping['lessor_source'] ?? '')) ?>
                    <?= esd_render_kv('Starting point ID', (string)($mapping['starting_point_id'] ?? ''), (string)($mapping['starting_point_label'] ?? '')) ?>
                    <?= esd_render_kv('Pickup', esd_field($fields, 'pickup_address')) ?>
                    <?= esd_render_kv('Drop-off', esd_field($fields, 'dropoff_address')) ?>
                    <?= esd_render_kv('Pickup time', esd_field($fields, 'pickup_datetime_local')) ?>
                    <?= esd_render_kv('End time', esd_field($fields, 'end_datetime_local')) ?>
                    <?= esd_render_kv('Price', esd_field($fields, 'estimated_price_amount'), esd_field($fields, 'estimated_price_text')) ?>
                </div>
            </section>

            <section class="esd-card">
                <h2>3. Field coverage</h2>
                <div class="esd-table-wrap">
                    <table class="esd-table">
                        <thead><tr><th>Field</th><th>Status</th><th>Value preview</th></tr></thead>
                        <tbody>
                        <?php foreach ($coverage as $row): ?>
                            <tr>
                                <td><code><?= esd_h((string)$row['field']) ?></code></td>
                                <td><?= esd_badge(!empty($row['present']) ? 'present' : 'missing', !empty($row['present']) ? 'good' : 'bad') ?></td>
                                <td><?= esd_h((string)($row['value_preview'] ?? '')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="esd-card">
                <h2>4. Canonical dry-run payload</h2>
                <p class="small">This is not posted anywhere. CSRF token values, cookies, and sessions are never included.</p>
                <pre class="esd-json"><?= esd_h(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></pre>
            </section>
        <?php endif; ?>
    </div>

    <aside class="esd-stack">
        <section class="esd-card esd-dev">
            <h2>Sanitized capture status</h2>
            <?php if (!empty($capture['ok'])): ?>
                <div><?= esd_badge('CAPTURE FOUND', 'good') ?> <?= esd_badge('ID ' . (string)($captureMeta['id'] ?? '-'), 'neutral') ?></div>
                <div class="mobile-kv">
                    <?= esd_render_kv('Method', (string)($captureMeta['method'] ?? '')) ?>
                    <?= esd_render_kv('Action host', (string)($captureMeta['action_host'] ?? '')) ?>
                    <?= esd_render_kv('Action path', (string)($captureMeta['action_path'] ?? '')) ?>
                    <?= esd_render_kv('CSRF field name', (string)($captureMeta['csrf_field_name'] ?? ''), 'Name only, never token value') ?>
                    <?= esd_render_kv('Latitude field', (string)($captureMeta['lat_field_name'] ?? '')) ?>
                    <?= esd_render_kv('Longitude field', (string)($captureMeta['lng_field_name'] ?? '')) ?>
                    <?= esd_render_kv('Captured at', (string)($captureMeta['captured_at'] ?? '')) ?>
                </div>
            <?php else: ?>
                <div><?= esd_badge('NO CAPTURE READY', 'warn') ?></div>
                <p class="warnline"><strong><?= esd_h((string)($capture['error'] ?? $captureError ?? 'Capture unavailable.')) ?></strong></p>
                <p>Use the sanitized capture page first:</p>
                <p><a class="btn warn" href="/ops/edxeix-submit-capture.php">Open EDXEIX Submit Capture</a></p>
            <?php endif; ?>
        </section>

        <section class="esd-card">
            <h2>Submit blockers</h2>
            <?php if ($blockers === []): ?>
                <p class="small">Build a dry-run preview to see blockers.</p>
            <?php else: ?>
                <?php foreach ($blockers as $blocker): ?>
                    <div class="esd-blocker"><?= esd_h($blocker) ?></div>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>

        <section class="esd-card">
            <h2>Next connector requirements</h2>
            <ul class="list">
                <li>Map real EDXEIX field names to canonical payload keys.</li>
                <li>Add server-side authenticated EDXEIX session handling without storing secrets in Git.</li>
                <li>Require map coordinate confirmation before submit.</li>
                <li>Add duplicate prevention and audit rows.</li>
                <li>Keep live submit disabled until Andreas explicitly approves.</li>
            </ul>
        </section>

        <section class="esd-card">
            <h2>Related pages</h2>
            <div class="actions">
                <a class="btn" href="/ops/mobile-submit-dev.php">Mobile Submit Dev</a>
                <a class="btn" href="/ops/edxeix-submit-research.php">Submit Research</a>
                <a class="btn" href="/ops/edxeix-submit-capture.php">Submit Capture</a>
            </div>
        </section>
    </aside>
</section>
<?php
esd_shell_end();
