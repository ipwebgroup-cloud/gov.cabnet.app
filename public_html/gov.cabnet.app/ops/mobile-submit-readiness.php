<?php
/**
 * gov.cabnet.app — Mobile Submit Readiness v0.1
 *
 * Mobile/server-side submit integration preview.
 * This page connects the parser, mapping resolver, latest sanitized submit capture,
 * dry-run payload builder, and preflight gate in one read-only screen.
 *
 * Safety contract:
 * - No Bolt API calls.
 * - No EDXEIX calls.
 * - No AADE calls.
 * - No workflow database writes.
 * - No queue staging.
 * - No live submission.
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

$homeRoot = dirname(__DIR__, 3);
$appRoot = $homeRoot . '/gov.cabnet.app_app';
$parserFile = $appRoot . '/src/BoltMail/BoltPreRideEmailParser.php';
$lookupFile = $appRoot . '/src/BoltMail/EdxeixMappingLookup.php';
$mailLoaderFile = $appRoot . '/src/BoltMail/MaildirPreRideEmailLoader.php';
$gateFile = $appRoot . '/src/Edxeix/EdxeixSubmitPreflightGate.php';
$bootstrapFile = $appRoot . '/src/bootstrap.php';

foreach ([$parserFile, $lookupFile, $mailLoaderFile, $gateFile] as $file) {
    if (is_file($file)) {
        require_once $file;
    }
}

function msr_h(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function msr_badge(string $text, string $type = 'neutral'): string
{
    $type = in_array($type, ['good', 'warn', 'bad', 'neutral'], true) ? $type : 'neutral';
    if (function_exists('opsui_badge')) {
        return opsui_badge($text, $type);
    }
    return '<span class="badge badge-' . msr_h($type) . '">' . msr_h($text) . '</span>';
}

function msr_csrf(): string
{
    if (empty($_SESSION['mobile_submit_readiness_csrf']) || !is_string($_SESSION['mobile_submit_readiness_csrf'])) {
        $_SESSION['mobile_submit_readiness_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['mobile_submit_readiness_csrf'];
}

function msr_validate_csrf(string $token): bool
{
    return isset($_SESSION['mobile_submit_readiness_csrf'])
        && is_string($_SESSION['mobile_submit_readiness_csrf'])
        && hash_equals($_SESSION['mobile_submit_readiness_csrf'], $token);
}

function msr_app_context(?string &$error = null): ?array
{
    static $loaded = false;
    static $ctx = null;
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

function msr_db(?string &$error = null): ?mysqli
{
    $ctx = msr_app_context($error);
    if (!$ctx || !isset($ctx['db']) || !method_exists($ctx['db'], 'connection')) {
        $error = $error ?: 'Private app DB context is unavailable.';
        return null;
    }
    try {
        $db = $ctx['db']->connection();
        if (!$db instanceof mysqli) {
            throw new RuntimeException('DB connection is not mysqli.');
        }
        return $db;
    } catch (Throwable $e) {
        $error = $e->getMessage();
        return null;
    }
}

function msr_table_exists(mysqli $db, string $table): bool
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

function msr_load_latest_server_email(): array
{
    if (!class_exists('Bridge\\BoltMail\\MaildirPreRideEmailLoader')) {
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
    $ctx = msr_app_context($ctxError);
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
        $loader = new \Bridge\BoltMail\MaildirPreRideEmailLoader();
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

function msr_lookup_ids(array $fields, ?string &$error = null): array
{
    $empty = [
        'ok' => false,
        'lessor_id' => '',
        'driver_id' => '',
        'vehicle_id' => '',
        'starting_point_id' => '',
        'messages' => [],
        'warnings' => ['EDXEIX mapping lookup was not available.'],
    ];

    if (!class_exists('Bridge\\BoltMail\\EdxeixMappingLookup')) {
        $error = 'EdxeixMappingLookup class is not installed.';
        return $empty;
    }

    $db = msr_db($error);
    if (!$db) {
        return $empty;
    }

    try {
        $lookup = new \Bridge\BoltMail\EdxeixMappingLookup($db);
        $result = $lookup->lookup($fields);
        $error = null;
        return is_array($result) ? $result : $empty;
    } catch (Throwable $e) {
        $error = $e->getMessage();
        $empty['warnings'] = ['DB lookup failed: ' . $error];
        return $empty;
    }
}

function msr_latest_capture(?string &$error = null): ?array
{
    $db = msr_db($error);
    if (!$db) {
        return null;
    }
    if (!msr_table_exists($db, 'ops_edxeix_submit_captures')) {
        $error = 'ops_edxeix_submit_captures table is not installed.';
        return null;
    }

    try {
        $res = $db->query('SELECT * FROM ops_edxeix_submit_captures ORDER BY COALESCE(updated_at, created_at) DESC, id DESC LIMIT 1');
        $row = $res ? $res->fetch_assoc() : null;
        if (!is_array($row)) {
            $error = 'No sanitized EDXEIX submit capture rows found.';
            return null;
        }
        $error = null;
        return $row;
    } catch (Throwable $e) {
        $error = $e->getMessage();
        return null;
    }
}

function msr_field(array $fields, string $key): string
{
    return trim((string)($fields[$key] ?? ''));
}

function msr_el_datetime(string $raw): string
{
    $raw = trim($raw);
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})[ T](\d{1,2}):(\d{2})(?::\d{2})?/', $raw, $m)) {
        return $m[3] . '/' . $m[2] . '/' . $m[1] . ' ' . str_pad($m[4], 2, '0', STR_PAD_LEFT) . ':' . $m[5];
    }
    return $raw;
}

function msr_split_names(string $value): array
{
    $parts = preg_split('/[\r\n,;]+/', $value) ?: [];
    $out = [];
    foreach ($parts as $part) {
        $part = trim($part);
        if ($part !== '') {
            $out[] = $part;
        }
    }
    return array_values(array_unique($out));
}

function msr_build_payload(array $fields, array $mapping, ?array $capture): array
{
    $payload = [
        'lessor' => (string)($mapping['lessor_id'] ?? ''),
        'lessee[type]' => 'natural',
        'lessee[name]' => msr_field($fields, 'customer_name'),
        'driver' => (string)($mapping['driver_id'] ?? ''),
        'vehicle' => (string)($mapping['vehicle_id'] ?? ''),
        'starting_point_id' => (string)($mapping['starting_point_id'] ?? ''),
        'boarding_point' => msr_field($fields, 'pickup_address'),
        'coordinates' => '__OPERATOR_MAP_POINT_REQUIRED__',
        'disembark_point' => msr_field($fields, 'dropoff_address'),
        'drafted_at' => msr_el_datetime(msr_field($fields, 'pickup_datetime_local')),
        'started_at' => msr_el_datetime(msr_field($fields, 'pickup_datetime_local')),
        'ended_at' => msr_el_datetime(msr_field($fields, 'end_datetime_local')),
        'price' => msr_field($fields, 'estimated_price_amount'),
        'broker' => '',
    ];

    $required = [];
    if ($capture) {
        $required = msr_split_names((string)($capture['required_field_names'] ?? ''));
    }

    $coverage = [];
    foreach ($required as $name) {
        $coverage[$name] = [
            'present_in_payload' => array_key_exists($name, $payload),
            'has_value' => array_key_exists($name, $payload) && trim((string)$payload[$name]) !== '',
        ];
    }

    return [
        'endpoint' => [
            'method' => strtoupper(trim((string)($capture['form_method'] ?? 'POST'))),
            'host' => trim((string)($capture['action_host'] ?? '')),
            'path' => trim((string)($capture['action_path'] ?? '')),
            'csrf_field_name' => trim((string)($capture['csrf_field_name'] ?? '')),
            'csrf_value' => '__BROWSER_OR_SERVER_SESSION_TOKEN_REQUIRED__',
        ],
        'payload' => $payload,
        'required_field_coverage' => $coverage,
        'safety' => [
            'live_submit_enabled' => false,
            'calls_edxeix' => false,
            'requires_operator_map_point' => true,
            'requires_final_confirmation' => true,
        ],
    ];
}

function msr_starting_point_evidence(string $lessorId, string $startingPointId): array
{
    $out = [
        'table_available' => false,
        'lessor_specific_rows' => [],
        'matched_lessor_specific' => false,
        'warning' => '',
    ];
    if ($lessorId === '') {
        $out['warning'] = 'No lessor ID available.';
        return $out;
    }

    $err = null;
    $db = msr_db($err);
    if (!$db) {
        $out['warning'] = $err ?: 'DB unavailable.';
        return $out;
    }
    if (!msr_table_exists($db, 'mapping_lessor_starting_points')) {
        $out['warning'] = 'mapping_lessor_starting_points table is missing.';
        return $out;
    }

    $out['table_available'] = true;
    try {
        $stmt = $db->prepare('SELECT id, edxeix_lessor_id, internal_key, label, edxeix_starting_point_id, is_active, updated_at FROM mapping_lessor_starting_points WHERE edxeix_lessor_id = ? ORDER BY is_active DESC, id ASC');
        $stmt->bind_param('s', $lessorId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $out['lessor_specific_rows'][] = $row;
            if ((string)($row['is_active'] ?? '1') !== '0' && trim((string)($row['edxeix_starting_point_id'] ?? '')) === $startingPointId) {
                $out['matched_lessor_specific'] = true;
            }
        }
        if (!$out['lessor_specific_rows']) {
            $out['warning'] = 'No lessor-specific starting point override for lessor ' . $lessorId . '.';
        } elseif (!$out['matched_lessor_specific']) {
            $out['warning'] = 'Resolved starting point does not match an active lessor-specific override.';
        }
    } catch (Throwable $e) {
        $out['warning'] = $e->getMessage();
    }
    return $out;
}

function msr_gate(array $fields, array $mapping, ?array $capture): array
{
    if (!class_exists('Bridge\\Edxeix\\EdxeixSubmitPreflightGate')) {
        return [
            'ok' => false,
            'technical_ready' => false,
            'live_submit_allowed' => false,
            'technical_blockers' => ['preflight_gate_class_missing'],
            'live_blockers' => ['preflight_gate_class_missing'],
            'warnings' => ['EdxeixSubmitPreflightGate class is not installed.'],
        ];
    }
    try {
        $gate = new \Bridge\Edxeix\EdxeixSubmitPreflightGate();
        return $gate->evaluate($fields, $mapping, $capture, [
            'future_guard_minutes' => 30,
            'timezone' => 'Europe/Athens',
            'live_connector_enabled' => false,
            'operator_final_confirmed' => false,
            'map_point_confirmed' => false,
        ]);
    } catch (Throwable $e) {
        return [
            'ok' => false,
            'technical_ready' => false,
            'live_submit_allowed' => false,
            'technical_blockers' => ['preflight_gate_exception'],
            'live_blockers' => ['preflight_gate_exception'],
            'warnings' => [$e->getMessage()],
        ];
    }
}

function msr_row(string $label, string $value, string $hint = ''): string
{
    return '<div class="msr-kv"><div class="msr-k">' . msr_h($label) . '</div><div><strong>' . msr_h($value !== '' ? $value : '-') . '</strong>' . ($hint !== '' ? '<div class="small">' . msr_h($hint) . '</div>' : '') . '</div></div>';
}

function msr_shell_begin(): void
{
    if (function_exists('opsui_shell_begin')) {
        opsui_shell_begin([
            'title' => 'Mobile Submit Readiness',
            'page_title' => 'Mobile Submit Readiness',
            'active_section' => 'Mobile Submit',
            'breadcrumbs' => 'Αρχική / Mobile / Submit Readiness',
            'safe_notice' => 'Read-only integration preview. It connects parser, mapping, submit capture, dry-run payload, and preflight gate without calling EDXEIX or enabling live submit.',
            'force_safe_notice' => true,
        ]);
        return;
    }
    echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Mobile Submit Readiness</title></head><body>';
}

function msr_shell_end(): void
{
    if (function_exists('opsui_shell_end')) {
        opsui_shell_end();
        return;
    }
    echo '</body></html>';
}

$rawEmail = '';
$error = '';
$mailLoad = null;
$parseResult = null;
$mapping = null;
$captureError = null;
$capture = null;
$lookupError = null;
$gate = null;
$payloadPreview = null;
$startingEvidence = null;
$csrf = msr_csrf();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!msr_validate_csrf((string)($_POST['csrf'] ?? ''))) {
        $error = 'Security token expired. Please reload and try again.';
    } else {
        $action = (string)($_POST['action'] ?? 'parse_pasted');
        if ($action === 'load_latest_server_email') {
            $mailLoad = msr_load_latest_server_email();
            if (!empty($mailLoad['ok'])) {
                $rawEmail = (string)($mailLoad['email_text'] ?? '');
            } else {
                $error = (string)($mailLoad['error'] ?? 'Unable to load latest server email.');
                $rawEmail = (string)($_POST['email_text'] ?? '');
            }
        } else {
            $rawEmail = (string)($_POST['email_text'] ?? '');
        }

        if ($error === '' && trim($rawEmail) !== '') {
            if (!class_exists('Bridge\\BoltMail\\BoltPreRideEmailParser')) {
                $error = 'BoltPreRideEmailParser class is not installed.';
            } else {
                try {
                    $parser = new \Bridge\BoltMail\BoltPreRideEmailParser();
                    $parseResult = $parser->parse($rawEmail);
                    $fields = is_array($parseResult['fields'] ?? null) ? $parseResult['fields'] : [];
                    $mapping = msr_lookup_ids($fields, $lookupError);
                    $capture = msr_latest_capture($captureError);
                    $gate = msr_gate($fields, $mapping, $capture);
                    $payloadPreview = msr_build_payload($fields, $mapping, $capture);
                    $startingEvidence = msr_starting_point_evidence((string)($mapping['lessor_id'] ?? ''), (string)($mapping['starting_point_id'] ?? ''));
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

msr_shell_begin();
?>
<style>
.msr-grid{display:grid;grid-template-columns:minmax(0,1fr) minmax(340px,.72fr);gap:18px}.msr-stack{display:grid;gap:14px}.msr-card{background:#fff;border:1px solid #d8dde7;border-radius:4px;padding:18px 20px;box-shadow:0 6px 18px rgba(26,33,52,.06)}.msr-card h2{margin-top:0}.msr-textarea{min-height:260px;width:100%;box-sizing:border-box;border:1px solid #d8dde7;border-radius:6px;padding:10px;font-family:Arial,Helvetica,sans-serif}.msr-actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:12px}.msr-kv{display:grid;grid-template-columns:185px minmax(0,1fr);gap:12px;border-bottom:1px solid #eef1f5;padding:10px 0}.msr-kv:last-child{border-bottom:0}.msr-k{font-weight:700;color:#667085}.msr-pill-list{display:flex;gap:8px;flex-wrap:wrap;margin:8px 0}.msr-blocker{background:#fff7ed;border:1px solid #fed7aa;border-radius:4px;padding:10px;margin:6px 0;color:#9a3412}.msr-blocker.bad{background:#fee2e2;border-color:#fecaca;color:#991b1b}.msr-code{white-space:pre-wrap;overflow:auto;max-height:520px;background:#0b1220;color:#dbeafe;border-radius:6px;padding:14px;font-family:Consolas,Menlo,monospace;font-size:12.5px;line-height:1.4}.msr-note{border-left:5px solid #d4922d}.msr-ok{border-left:5px solid #15803d}.msr-bad{border-left:5px solid #b42318}@media(max-width:980px){.msr-grid{grid-template-columns:1fr}.msr-kv{grid-template-columns:1fr}.msr-card{padding:15px}.msr-actions .btn{width:100%;text-align:center}.msr-textarea{min-height:220px}}
</style>

<section class="card hero warn">
    <h1>Mobile Submit Readiness</h1>
    <p>This page connects the mobile submit workstream pieces into one read-only readiness screen. It parses the ride, resolves mappings, checks lessor-specific starting point evidence, reads the latest sanitized EDXEIX submit capture, builds a dry-run payload preview, and runs the preflight gate.</p>
    <div>
        <?= msr_badge('READ ONLY', 'good') ?>
        <?= msr_badge('NO LIVE SUBMIT', 'good') ?>
        <?= msr_badge('NO EDXEIX CALL', 'good') ?>
        <?= msr_badge('MOBILE WORKSTREAM', 'warn') ?>
    </div>
</section>

<section class="msr-grid">
    <div class="msr-stack">
        <form class="msr-card" method="post" action="/ops/mobile-submit-readiness.php" autocomplete="off">
            <h2>1. Load or paste Bolt pre-ride email</h2>
            <p class="small">Use this to prove readiness before any future server-side submit connector is allowed.</p>
            <input type="hidden" name="csrf" value="<?= msr_h($csrf) ?>">
            <textarea class="msr-textarea" name="email_text" placeholder="Paste Bolt pre-ride email here..."><?= msr_h($rawEmail) ?></textarea>
            <div class="msr-actions">
                <button class="btn green" type="submit" name="action" value="parse_pasted">Parse + Readiness</button>
                <button class="btn warn" type="submit" name="action" value="load_latest_server_email">Load latest server email</button>
                <a class="btn dark" href="/ops/mobile-submit-readiness.php">Clear</a>
            </div>
            <?php if (is_array($mailLoad) && !empty($mailLoad['ok'])): ?>
                <p class="goodline"><strong>Loaded:</strong> <?= msr_h((string)($mailLoad['source'] ?? '')) ?> <?= !empty($mailLoad['source_mtime']) ? '(' . msr_h((string)$mailLoad['source_mtime']) . ')' : '' ?></p>
            <?php endif; ?>
        </form>

        <?php if ($error !== ''): ?>
        <section class="msr-card msr-bad">
            <h2>Problem</h2>
            <p class="badline"><strong><?= msr_h($error) ?></strong></p>
        </section>
        <?php endif; ?>

        <?php if (is_array($parseResult)): ?>
        <section class="msr-card">
            <h2>2. Parsed ride</h2>
            <div class="msr-pill-list">
                <?= msr_badge('Parser: ' . (string)($parseResult['confidence'] ?? 'unknown'), empty($parseResult['ok']) ? 'warn' : 'good') ?>
                <?= msr_badge('AADE not involved', 'good') ?>
            </div>
            <?= msr_row('Passenger', msr_field($fields, 'customer_name'), msr_field($fields, 'customer_phone')) ?>
            <?= msr_row('Driver', msr_field($fields, 'driver_name')) ?>
            <?= msr_row('Vehicle', msr_field($fields, 'vehicle_plate')) ?>
            <?= msr_row('Pickup', msr_field($fields, 'pickup_address')) ?>
            <?= msr_row('Drop-off', msr_field($fields, 'dropoff_address')) ?>
            <?= msr_row('Pickup time', msr_field($fields, 'pickup_datetime_local')) ?>
            <?= msr_row('Estimated end', msr_field($fields, 'end_datetime_local')) ?>
            <?= msr_row('Price', msr_field($fields, 'estimated_price_amount'), msr_field($fields, 'estimated_price_text')) ?>
        </section>

        <section class="msr-card">
            <h2>3. Mapping and starting point readiness</h2>
            <div class="msr-pill-list">
                <?= msr_badge(!empty($mapping['ok']) ? 'IDS READY' : 'IDS NEED REVIEW', !empty($mapping['ok']) ? 'good' : 'warn') ?>
                <?= msr_badge(!empty($startingEvidence['matched_lessor_specific']) ? 'LESSOR STARTING POINT OK' : 'STARTING POINT REVIEW', !empty($startingEvidence['matched_lessor_specific']) ? 'good' : 'warn') ?>
            </div>
            <?= msr_row('Lessor ID', (string)($mapping['lessor_id'] ?? ''), (string)($mapping['lessor_source'] ?? '')) ?>
            <?= msr_row('Driver ID', (string)($mapping['driver_id'] ?? ''), (string)($mapping['driver_label'] ?? '')) ?>
            <?= msr_row('Vehicle ID', (string)($mapping['vehicle_id'] ?? ''), (string)($mapping['vehicle_label'] ?? '')) ?>
            <?= msr_row('Starting point ID', (string)($mapping['starting_point_id'] ?? ''), (string)($mapping['starting_point_label'] ?? '')) ?>
            <?php if (!empty($startingEvidence['warning'])): ?><div class="msr-blocker bad"><?= msr_h((string)$startingEvidence['warning']) ?></div><?php endif; ?>
            <?php foreach ((array)($startingEvidence['lessor_specific_rows'] ?? []) as $row): ?>
                <div class="msr-blocker <?= ((string)($row['edxeix_starting_point_id'] ?? '') === (string)($mapping['starting_point_id'] ?? '') && (string)($row['is_active'] ?? '1') !== '0') ? '' : 'bad' ?>">
                    Override row #<?= msr_h((string)($row['id'] ?? '')) ?>: lessor <?= msr_h((string)($row['edxeix_lessor_id'] ?? '')) ?> → starting point <?= msr_h((string)($row['edxeix_starting_point_id'] ?? '')) ?> / <?= msr_h((string)($row['label'] ?? '')) ?> / active <?= msr_h((string)($row['is_active'] ?? '')) ?>
                </div>
            <?php endforeach; ?>
            <?php foreach ((array)($mapping['messages'] ?? []) as $msg): ?><div class="goodline">✓ <?= msr_h((string)$msg) ?></div><?php endforeach; ?>
            <?php foreach ((array)($mapping['warnings'] ?? []) as $msg): ?><div class="warnline">⚠ <?= msr_h((string)$msg) ?></div><?php endforeach; ?>
            <?php if ($lookupError): ?><div class="warnline">Lookup note: <?= msr_h($lookupError) ?></div><?php endif; ?>
        </section>
        <?php endif; ?>
    </div>

    <aside class="msr-stack">
        <section class="msr-card msr-note">
            <h2>Target mobile submit path</h2>
            <ol class="list">
                <li>Mobile operator loads a pre-ride email.</li>
                <li>System resolves exact EDXEIX IDs and lessor-specific starting point.</li>
                <li>Dry-run payload is built and checked.</li>
                <li>Preflight gate blocks unsafe cases.</li>
                <li>Only later, after explicit approval, a server-side connector may submit.</li>
            </ol>
        </section>

        <?php if (is_array($gate)): ?>
        <section class="msr-card <?= !empty($gate['technical_ready']) ? 'msr-ok' : 'msr-bad' ?>">
            <h2>4. Preflight gate</h2>
            <div class="msr-pill-list">
                <?= msr_badge(!empty($gate['technical_ready']) ? 'TECHNICAL READY' : 'TECHNICAL BLOCKED', !empty($gate['technical_ready']) ? 'good' : 'bad') ?>
                <?= msr_badge(!empty($gate['live_submit_allowed']) ? 'LIVE READY' : 'LIVE BLOCKED', !empty($gate['live_submit_allowed']) ? 'good' : 'warn') ?>
                <?= msr_badge('LIVE CONNECTOR DISABLED', 'good') ?>
            </div>
            <h3>Technical blockers</h3>
            <?php if (empty($gate['technical_blockers'])): ?><p class="goodline">No technical blockers found.</p><?php endif; ?>
            <?php foreach ((array)($gate['technical_blockers'] ?? []) as $blocker): ?><div class="msr-blocker bad"><?= msr_h((string)$blocker) ?></div><?php endforeach; ?>
            <h3>Live blockers</h3>
            <?php foreach ((array)($gate['live_blockers'] ?? []) as $blocker): ?><div class="msr-blocker"><?= msr_h((string)$blocker) ?></div><?php endforeach; ?>
            <?php foreach ((array)($gate['warnings'] ?? []) as $warning): ?><div class="warnline">⚠ <?= msr_h((string)$warning) ?></div><?php endforeach; ?>
        </section>
        <?php endif; ?>

        <?php if ($capture || $captureError): ?>
        <section class="msr-card">
            <h2>5. Sanitized submit capture</h2>
            <div class="msr-pill-list">
                <?= msr_badge($capture ? 'CAPTURE FOUND' : 'CAPTURE MISSING', $capture ? 'good' : 'warn') ?>
            </div>
            <?php if ($capture): ?>
                <?= msr_row('Capture ID', (string)($capture['id'] ?? '')) ?>
                <?= msr_row('Method', (string)($capture['form_method'] ?? '')) ?>
                <?= msr_row('Action host', (string)($capture['action_host'] ?? '')) ?>
                <?= msr_row('Action path', (string)($capture['action_path'] ?? '')) ?>
                <?= msr_row('CSRF field name', (string)($capture['csrf_field_name'] ?? ''), 'Token value is never stored here.') ?>
            <?php else: ?>
                <div class="msr-blocker"><?= msr_h((string)$captureError) ?></div>
            <?php endif; ?>
        </section>
        <?php endif; ?>

        <?php if (is_array($payloadPreview)): ?>
        <section class="msr-card">
            <h2>6. Dry-run payload JSON</h2>
            <p class="small">Preview only. No EDXEIX request is made.</p>
            <pre class="msr-code"><?= msr_h(json_encode($payloadPreview, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></pre>
        </section>
        <?php endif; ?>

        <section class="msr-card">
            <h2>Related tools</h2>
            <div class="actions">
                <a class="btn dark" href="/ops/mobile-submit-dev.php">Mobile Submit Dev</a>
                <a class="btn dark" href="/ops/edxeix-submit-dry-run.php">Dry-Run Builder</a>
                <a class="btn dark" href="/ops/edxeix-submit-preflight-gate.php">Preflight Gate</a>
                <a class="btn dark" href="/ops/mapping-resolver-test.php">Mapping Resolver Test</a>
            </div>
        </section>
    </aside>
</section>
<?php msr_shell_end(); ?>
