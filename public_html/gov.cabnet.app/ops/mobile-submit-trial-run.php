<?php
/**
 * gov.cabnet.app — Mobile Submit Trial Run
 *
 * Real-email dry-run evaluator for the future mobile/server-side EDXEIX submit workflow.
 * This page parses and validates only. It never submits to EDXEIX and never writes workflow data.
 *
 * Safety contract:
 * - No Bolt API calls.
 * - No EDXEIX calls.
 * - No AADE calls.
 * - No workflow database writes.
 * - No queue staging.
 * - No live submission controls.
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
$preflightFile = $appRoot . '/src/Edxeix/EdxeixSubmitPreflightGate.php';
$connectorFile = $appRoot . '/src/Edxeix/EdxeixSubmitConnector.php';
$payloadValidatorFile = $appRoot . '/src/Edxeix/EdxeixSubmitPayloadValidator.php';
$bootstrapFile = $appRoot . '/src/bootstrap.php';

foreach ([$parserFile, $lookupFile, $mailLoaderFile, $preflightFile, $connectorFile, $payloadValidatorFile] as $file) {
    if (is_file($file)) {
        require_once $file;
    }
}

function mstr_h(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function mstr_badge(string $text, string $type = 'neutral'): string
{
    $type = in_array($type, ['good', 'warn', 'bad', 'neutral'], true) ? $type : 'neutral';
    if (function_exists('opsui_badge')) {
        return opsui_badge($text, $type);
    }
    return '<span class="badge badge-' . mstr_h($type) . '">' . mstr_h($text) . '</span>';
}

function mstr_csrf(): string
{
    if (empty($_SESSION['mobile_submit_trial_run_csrf']) || !is_string($_SESSION['mobile_submit_trial_run_csrf'])) {
        $_SESSION['mobile_submit_trial_run_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['mobile_submit_trial_run_csrf'];
}

function mstr_validate_csrf(string $token): bool
{
    return isset($_SESSION['mobile_submit_trial_run_csrf'])
        && is_string($_SESSION['mobile_submit_trial_run_csrf'])
        && hash_equals($_SESSION['mobile_submit_trial_run_csrf'], $token);
}

function mstr_shell_begin(): void
{
    if (function_exists('opsui_shell_begin')) {
        opsui_shell_begin([
            'title' => 'Mobile Submit Trial Run',
            'page_title' => 'Mobile Submit Trial Run',
            'active_section' => 'Mobile Submit',
            'breadcrumbs' => 'Αρχική / Mobile Submit / Trial Run',
            'safe_notice' => 'Real-email dry-run only. This page evaluates readiness but never calls EDXEIX and never enables live submission.',
            'force_safe_notice' => true,
        ]);
        return;
    }
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Mobile Submit Trial Run</title><style>body{font-family:Arial,Helvetica,sans-serif;background:#eef1f6;color:#20293a;margin:0;padding:18px}.card{background:#fff;border:1px solid #d8dde7;border-radius:8px;padding:16px;margin:0 0 16px}.badge{display:inline-block;padding:6px 10px;border-radius:12px;background:#e9edf7;margin:2px}.badge-good{background:#dbf0dc;color:#2d7b37}.badge-warn{background:#f8ead3;color:#9a5a00}.badge-bad{background:#f8dedd;color:#b13c35}.btn{display:inline-block;background:#4f5ea7;color:#fff;padding:11px 14px;border-radius:5px;text-decoration:none;border:0;font-weight:700}textarea,input{width:100%;box-sizing:border-box;border:1px solid #d8dde7;border-radius:6px;padding:10px}.small{font-size:13px;color:#667085}.kv-row{display:grid;grid-template-columns:190px 1fr;gap:12px;border-bottom:1px solid #eef1f5;padding:10px 0}.k{font-weight:700;color:#667085}@media(max-width:760px){.kv-row{grid-template-columns:1fr}}</style></head><body>';
}

function mstr_shell_end(): void
{
    if (function_exists('opsui_shell_end')) {
        opsui_shell_end();
        return;
    }
    echo '</body></html>';
}

function mstr_app_context(?string &$error = null): ?array
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

function mstr_db(?string &$error = null): ?mysqli
{
    $ctx = mstr_app_context($error);
    if (!$ctx || !isset($ctx['db']) || !method_exists($ctx['db'], 'connection')) {
        $error = $error ?: 'Private app DB context is unavailable.';
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

function mstr_table_exists(mysqli $db, string $table): bool
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

function mstr_columns(mysqli $db, string $table): array
{
    try {
        $stmt = $db->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
        $stmt->bind_param('s', $table);
        $stmt->execute();
        $cols = [];
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $cols[(string)$row['COLUMN_NAME']] = true;
        }
        return $cols;
    } catch (Throwable) {
        return [];
    }
}

function mstr_latest_capture(mysqli $db): ?array
{
    if (!mstr_table_exists($db, 'ops_edxeix_submit_captures')) {
        return null;
    }
    $cols = mstr_columns($db, 'ops_edxeix_submit_captures');
    $order = isset($cols['created_at']) ? 'created_at' : (isset($cols['id']) ? 'id' : '1');
    try {
        $res = $db->query('SELECT * FROM ops_edxeix_submit_captures ORDER BY ' . $order . ' DESC LIMIT 1');
        $row = $res ? $res->fetch_assoc() : null;
        return is_array($row) ? $row : null;
    } catch (Throwable) {
        return null;
    }
}

function mstr_lookup_ids(array $fields, ?string &$error = null): array
{
    $class = '\\Bridge\\BoltMail\\EdxeixMappingLookup';
    $empty = [
        'ok' => false,
        'lessor_id' => '',
        'driver_id' => '',
        'vehicle_id' => '',
        'starting_point_id' => '',
        'messages' => [],
        'warnings' => ['EDXEIX mapping lookup was not available.'],
    ];

    if (!class_exists($class)) {
        $error = 'EdxeixMappingLookup class is not installed.';
        return $empty;
    }

    $db = mstr_db($error);
    if (!$db) {
        return $empty;
    }

    try {
        $lookup = new $class($db);
        $result = $lookup->lookup($fields);
        return is_array($result) ? $result : $empty;
    } catch (Throwable $e) {
        $error = $e->getMessage();
        $empty['warnings'] = ['DB lookup failed: ' . $error];
        return $empty;
    }
}

function mstr_lessor_starting_point_evidence(mysqli $db, string $lessorId, string $startingPointId): array
{
    $out = [
        'checked' => false,
        'has_override' => false,
        'matches_resolver' => false,
        'row_count' => 0,
        'rows' => [],
        'message' => 'No DB evidence checked.',
    ];

    if ($lessorId === '' || !mstr_table_exists($db, 'mapping_lessor_starting_points')) {
        $out['message'] = 'Lessor-specific starting point table unavailable or lessor missing.';
        return $out;
    }

    $out['checked'] = true;
    try {
        $stmt = $db->prepare("SELECT * FROM mapping_lessor_starting_points WHERE edxeix_lessor_id = ? AND is_active = 1 ORDER BY id ASC");
        $stmt->bind_param('s', $lessorId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $out['rows'][] = $row;
            $out['row_count']++;
            if (trim((string)($row['edxeix_starting_point_id'] ?? '')) !== '') {
                $out['has_override'] = true;
            }
            if ($startingPointId !== '' && trim((string)($row['edxeix_starting_point_id'] ?? '')) === $startingPointId) {
                $out['matches_resolver'] = true;
            }
        }
        $out['message'] = $out['matches_resolver']
            ? 'Lessor-specific starting point override matches resolver result.'
            : ($out['has_override'] ? 'Override exists but does not match resolver result.' : 'No active lessor-specific override exists.');
        return $out;
    } catch (Throwable $e) {
        $out['message'] = $e->getMessage();
        return $out;
    }
}

function mstr_load_latest_server_email(): array
{
    $class = '\\Bridge\\BoltMail\\MaildirPreRideEmailLoader';
    if (!class_exists($class)) {
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
    $ctx = mstr_app_context($ctxError);
    if (is_array($ctx) && isset($ctx['config']) && method_exists($ctx['config'], 'get')) {
        foreach (['mail.pre_ride_maildir', 'mail.bolt_bridge_maildir'] as $key) {
            $single = $ctx['config']->get($key);
            if (is_string($single) && trim($single) !== '') {
                $extraDirs[] = trim($single);
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
        $loader = new $class();
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

function mstr_field(array $fields, string $key): string
{
    return trim((string)($fields[$key] ?? ''));
}

function mstr_row(string $label, string $value, string $hint = ''): string
{
    return '<div class="kv-row"><div class="k">' . mstr_h($label) . '</div><div><strong>' . mstr_h($value !== '' ? $value : '-') . '</strong>' . ($hint !== '' ? '<div class="small">' . mstr_h($hint) . '</div>' : '') . '</div></div>';
}

function mstr_json(mixed $value): string
{
    return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
}

function mstr_build_trial(array $fields, array $mapping): array
{
    $dbError = null;
    $db = mstr_db($dbError);
    $capture = $db ? mstr_latest_capture($db) : null;
    $evidence = $db ? mstr_lessor_starting_point_evidence(
        $db,
        trim((string)($mapping['lessor_id'] ?? '')),
        trim((string)($mapping['starting_point_id'] ?? ''))
    ) : ['checked' => false, 'has_override' => false, 'matches_resolver' => false, 'message' => $dbError ?: 'DB unavailable.'];

    $preflight = null;
    $request = null;
    $validation = null;

    $preflightClass = '\\Bridge\\Edxeix\\EdxeixSubmitPreflightGate';
    if (class_exists($preflightClass)) {
        try {
            $gate = new $preflightClass();
            $preflight = $gate->evaluate($fields, $mapping, $capture, [
                'future_guard_minutes' => 30,
                'timezone' => 'Europe/Athens',
                'live_connector_enabled' => false,
                'operator_final_confirmed' => false,
                'map_point_confirmed' => false,
            ]);
        } catch (Throwable $e) {
            $preflight = ['ok' => false, 'technical_ready' => false, 'technical_blockers' => ['preflight_exception'], 'error' => $e->getMessage()];
        }
    }

    $connectorClass = '\\Bridge\\Edxeix\\EdxeixSubmitConnector';
    if (class_exists($connectorClass)) {
        try {
            $connector = new $connectorClass();
            $request = $connector->buildRequest($fields, $mapping, $capture);
        } catch (Throwable $e) {
            $request = ['ok' => false, 'blockers' => ['connector_exception'], 'error' => $e->getMessage()];
        }
    }

    $validatorClass = '\\Bridge\\Edxeix\\EdxeixSubmitPayloadValidator';
    if (class_exists($validatorClass) && is_array($request)) {
        try {
            $validator = new $validatorClass();
            $validation = $validator->validate($request, $capture, $preflight);
        } catch (Throwable $e) {
            $validation = ['ok' => false, 'dry_run_payload_valid' => false, 'blockers' => ['validator_exception'], 'error' => $e->getMessage()];
        }
    }

    $hardBlockers = [];
    if (empty($mapping['ok'])) {
        $hardBlockers[] = 'mapping_not_ready';
    }
    if (empty($evidence['matches_resolver'])) {
        $hardBlockers[] = 'lessor_specific_starting_point_not_verified';
    }
    foreach ((array)($preflight['technical_blockers'] ?? []) as $b) {
        $hardBlockers[] = (string)$b;
    }
    foreach ((array)($validation['blockers'] ?? []) as $b) {
        $b = (string)$b;
        if (!in_array($b, ['live_submit_disabled_by_design', 'active_edxeix_session_bridge_not_implemented', 'active_csrf_token_not_available_by_design', 'operator_confirmed_map_coordinates_missing'], true)) {
            $hardBlockers[] = $b;
        }
    }

    $hardBlockers = array_values(array_unique(array_filter($hardBlockers)));
    $dryRunReady = count($hardBlockers) === 0 && !empty($validation['dry_run_payload_valid']);

    return [
        'dry_run_ready' => $dryRunReady,
        'live_submit_allowed' => false,
        'final_status' => $dryRunReady ? 'DRY-RUN READY / LIVE BLOCKED' : 'NO-GO / REVIEW REQUIRED',
        'hard_blockers' => $hardBlockers,
        'mapping' => $mapping,
        'capture' => $capture,
        'starting_point_evidence' => $evidence,
        'preflight' => $preflight,
        'connector_request' => $request,
        'payload_validation' => $validation,
        'safety_contract' => [
            'calls_edxeix' => false,
            'calls_bolt' => false,
            'calls_aade' => false,
            'writes_database' => false,
            'live_submit_default' => false,
        ],
    ];
}

$csrf = mstr_csrf();
$rawEmail = '';
$parseResult = null;
$mapping = null;
$trial = null;
$error = '';
$mailLoad = null;
$lookupError = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!mstr_validate_csrf((string)($_POST['csrf'] ?? ''))) {
        $error = 'Security token expired. Please reload and try again.';
    } else {
        $action = (string)($_POST['action'] ?? 'parse_pasted');
        if ($action === 'load_latest_server_email') {
            $mailLoad = mstr_load_latest_server_email();
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
            $parserClass = '\\Bridge\\BoltMail\\BoltPreRideEmailParser';
            if (!class_exists($parserClass)) {
                $error = 'BoltPreRideEmailParser class is not installed.';
            } else {
                try {
                    $parser = new $parserClass();
                    $parseResult = $parser->parse($rawEmail);
                    $fields = is_array($parseResult['fields'] ?? null) ? $parseResult['fields'] : [];
                    $mapping = mstr_lookup_ids($fields, $lookupError);
                    $trial = mstr_build_trial($fields, $mapping);
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

mstr_shell_begin();
?>
<style>
.trial-grid{display:grid;grid-template-columns:minmax(0,1fr) minmax(340px,.72fr);gap:18px}.trial-card{background:#fff;border:1px solid #d8dde7;border-radius:4px;padding:18px 20px;box-shadow:0 6px 18px rgba(26,33,52,.06);margin-bottom:16px}.trial-card h2{margin-top:0}.trial-textarea{min-height:260px;font-family:Arial,Helvetica,sans-serif}.trial-actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:12px}.trial-status{border-left:7px solid #d4922d}.trial-status.good{border-left-color:#059669}.trial-status.bad{border-left-color:#b42318}.trial-pill-list{display:flex;gap:8px;flex-wrap:wrap;margin:8px 0}.trial-blocker{background:#fee2e2;border:1px solid #fecaca;border-radius:4px;padding:9px 10px;margin:6px 0;color:#991b1b}.trial-note{background:#eef6ff;border:1px solid #bfdbfe;border-radius:4px;padding:10px;margin:8px 0;color:#1e3a8a}.trial-json{background:#0b1220;color:#dbeafe;border-radius:4px;padding:12px;overflow:auto;font-family:Consolas,Menlo,monospace;font-size:12px;line-height:1.4;max-height:460px}.kv-row{display:grid;grid-template-columns:190px minmax(0,1fr);gap:12px;border-bottom:1px solid #eef1f5;padding:10px 0}.kv-row:last-child{border-bottom:0}.k{font-weight:700;color:#667085}@media(max-width:980px){.trial-grid{grid-template-columns:1fr}.trial-card{padding:15px}.trial-actions .btn{width:100%;text-align:center}.kv-row{grid-template-columns:1fr}.trial-textarea{min-height:220px}}
</style>

<section class="card hero warn">
    <h1>Mobile Submit Trial Run</h1>
    <p>Run a real pre-ride email through the full mobile/server-side dry-run chain before any future live submit connector exists.</p>
    <div>
        <?= mstr_badge('REAL EMAIL DRY-RUN', 'warn') ?>
        <?= mstr_badge('NO LIVE SUBMIT', 'good') ?>
        <?= mstr_badge('NO EDXEIX CALL', 'good') ?>
        <?= mstr_badge('NO DB WRITE', 'good') ?>
    </div>
</section>

<section class="trial-grid">
    <div>
        <form class="trial-card" method="post" action="/ops/mobile-submit-trial-run.php" autocomplete="off">
            <h2>1. Load or paste Bolt pre-ride email</h2>
            <p class="small">This page evaluates readiness only. It does not create an EDXEIX booking and does not stage a queue job.</p>
            <input type="hidden" name="csrf" value="<?= mstr_h($csrf) ?>">
            <label for="email_text"><strong>Bolt pre-ride email body</strong></label>
            <textarea class="trial-textarea" id="email_text" name="email_text" placeholder="Paste Bolt pre-ride email here..."><?= mstr_h($rawEmail) ?></textarea>
            <div class="trial-actions">
                <button class="btn green" type="submit" name="action" value="parse_pasted">Run Trial</button>
                <button class="btn warn" type="submit" name="action" value="load_latest_server_email">Load latest server email + Run</button>
                <a class="btn dark" href="/ops/mobile-submit-trial-run.php">Clear</a>
            </div>
            <?php if (is_array($mailLoad) && !empty($mailLoad['ok'])): ?>
                <div class="trial-note"><strong>Loaded:</strong> <?= mstr_h((string)($mailLoad['source'] ?? '')) ?> <?= !empty($mailLoad['source_mtime']) ? '(' . mstr_h((string)$mailLoad['source_mtime']) . ')' : '' ?></div>
            <?php endif; ?>
        </form>

        <?php if ($error !== ''): ?>
            <section class="trial-card trial-status bad">
                <h2>Problem</h2>
                <p class="badline"><strong><?= mstr_h($error) ?></strong></p>
            </section>
        <?php endif; ?>

        <?php if (is_array($parseResult)): ?>
            <section class="trial-card">
                <h2>2. Parsed ride details</h2>
                <div class="trial-pill-list">
                    <?= mstr_badge('Parser: ' . (string)($parseResult['confidence'] ?? 'unknown'), empty($parseResult['ok']) ? 'warn' : 'good') ?>
                    <?= mstr_badge('AADE not involved', 'good') ?>
                    <?= mstr_badge('No workflow write', 'good') ?>
                </div>
                <div>
                    <?= mstr_row('Passenger', mstr_field($fields, 'customer_name'), mstr_field($fields, 'customer_phone')) ?>
                    <?= mstr_row('Driver', mstr_field($fields, 'driver_name')) ?>
                    <?= mstr_row('Vehicle', mstr_field($fields, 'vehicle_plate')) ?>
                    <?= mstr_row('Pickup', mstr_field($fields, 'pickup_address')) ?>
                    <?= mstr_row('Drop-off', mstr_field($fields, 'dropoff_address')) ?>
                    <?= mstr_row('Pickup time', mstr_field($fields, 'pickup_datetime_local'), mstr_field($fields, 'pickup_timezone')) ?>
                    <?= mstr_row('Estimated end', mstr_field($fields, 'end_datetime_local'), mstr_field($fields, 'end_timezone')) ?>
                    <?= mstr_row('Price', mstr_field($fields, 'estimated_price_amount'), mstr_field($fields, 'estimated_price_text')) ?>
                </div>
            </section>

            <section class="trial-card">
                <h2>3. EDXEIX IDs and starting point</h2>
                <div class="trial-pill-list">
                    <?= mstr_badge(!empty($mapping['ok']) ? 'MAPPING READY' : 'MAPPING REVIEW', !empty($mapping['ok']) ? 'good' : 'warn') ?>
                    <?= mstr_badge(!empty($trial['starting_point_evidence']['matches_resolver']) ? 'LESSOR STARTING POINT OK' : 'STARTING POINT REVIEW', !empty($trial['starting_point_evidence']['matches_resolver']) ? 'good' : 'bad') ?>
                </div>
                <div>
                    <?= mstr_row('Lessor ID', (string)($mapping['lessor_id'] ?? ''), (string)($mapping['lessor_source'] ?? '')) ?>
                    <?= mstr_row('Driver ID', (string)($mapping['driver_id'] ?? ''), (string)($mapping['driver_label'] ?? '')) ?>
                    <?= mstr_row('Vehicle ID', (string)($mapping['vehicle_id'] ?? ''), (string)($mapping['vehicle_label'] ?? '')) ?>
                    <?= mstr_row('Starting point ID', (string)($mapping['starting_point_id'] ?? ''), (string)($mapping['starting_point_label'] ?? '')) ?>
                    <?= mstr_row('Starting point evidence', (string)($trial['starting_point_evidence']['message'] ?? '')) ?>
                </div>
                <?php foreach ((array)($mapping['messages'] ?? []) as $msg): ?>
                    <div class="goodline">✓ <?= mstr_h((string)$msg) ?></div>
                <?php endforeach; ?>
                <?php foreach ((array)($mapping['warnings'] ?? []) as $msg): ?>
                    <div class="warnline">⚠ <?= mstr_h((string)$msg) ?></div>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>
    </div>

    <aside>
        <?php if (is_array($trial)): ?>
            <section class="trial-card trial-status <?= !empty($trial['dry_run_ready']) ? 'good' : 'bad' ?>">
                <h2>Final trial result</h2>
                <div class="trial-pill-list">
                    <?= mstr_badge((string)$trial['final_status'], !empty($trial['dry_run_ready']) ? 'good' : 'bad') ?>
                    <?= mstr_badge('LIVE SUBMIT BLOCKED', 'good') ?>
                </div>
                <?php if (!empty($trial['hard_blockers'])): ?>
                    <h3>Hard blockers</h3>
                    <?php foreach ((array)$trial['hard_blockers'] as $blocker): ?>
                        <div class="trial-blocker"><?= mstr_h((string)$blocker) ?></div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="trial-note"><strong>Dry-run chain is technically ready.</strong> Live submit remains disabled by design until explicitly approved and until map/session/CSRF connector work is complete.</div>
                <?php endif; ?>
            </section>

            <section class="trial-card">
                <h2>Gate summary</h2>
                <div>
                    <?= mstr_row('Preflight technical ready', !empty($trial['preflight']['technical_ready']) ? 'YES' : 'NO') ?>
                    <?= mstr_row('Payload dry-run valid', !empty($trial['payload_validation']['dry_run_payload_valid']) ? 'YES' : 'NO') ?>
                    <?= mstr_row('Capture available', !empty($trial['capture']) ? 'YES' : 'NO') ?>
                    <?= mstr_row('Live submit allowed', 'NO', 'Blocked intentionally in this phase.') ?>
                </div>
            </section>

            <section class="trial-card">
                <h2>Useful links</h2>
                <div class="trial-actions">
                    <a class="btn dark" href="/ops/mobile-submit-center.php">Mobile Submit Center</a>
                    <a class="btn dark" href="/ops/mobile-submit-gates.php">Mobile Gates</a>
                    <a class="btn dark" href="/ops/edxeix-submit-payload-validator.php">Payload Validator</a>
                    <a class="btn dark" href="/ops/mapping-resolver-test.php">Mapping Resolver Test</a>
                </div>
            </section>

            <section class="trial-card">
                <h2>Dry-run JSON</h2>
                <details open>
                    <summary>Connector request preview</summary>
                    <pre class="trial-json"><?= mstr_h(mstr_json($trial['connector_request'])) ?></pre>
                </details>
                <details>
                    <summary>Preflight result</summary>
                    <pre class="trial-json"><?= mstr_h(mstr_json($trial['preflight'])) ?></pre>
                </details>
                <details>
                    <summary>Payload validation result</summary>
                    <pre class="trial-json"><?= mstr_h(mstr_json($trial['payload_validation'])) ?></pre>
                </details>
            </section>
        <?php else: ?>
            <section class="trial-card">
                <h2>What this proves</h2>
                <ul class="list">
                    <li>Parser can read the real pre-ride email.</li>
                    <li>Resolver returns trusted lessor/driver/vehicle IDs.</li>
                    <li>Lessor-specific starting point exists and matches resolver result.</li>
                    <li>Sanitized EDXEIX submit capture is available.</li>
                    <li>Preflight and dry-run payload validation can pass.</li>
                    <li>Live submit remains blocked.</li>
                </ul>
            </section>
        <?php endif; ?>
    </aside>
</section>
<?php
mstr_shell_end();
