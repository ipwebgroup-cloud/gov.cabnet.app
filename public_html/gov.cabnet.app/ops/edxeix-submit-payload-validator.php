<?php
/**
 * gov.cabnet.app — EDXEIX Submit Payload Validator
 *
 * Development/read-only page for validating the dry-run payload created for the
 * future mobile/server-side EDXEIX submit workflow. No live submit exists here.
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
$files = [
    $homeRoot . '/gov.cabnet.app_app/src/BoltMail/BoltPreRideEmailParser.php',
    $homeRoot . '/gov.cabnet.app_app/src/BoltMail/EdxeixMappingLookup.php',
    $homeRoot . '/gov.cabnet.app_app/src/BoltMail/MaildirPreRideEmailLoader.php',
    $homeRoot . '/gov.cabnet.app_app/src/Edxeix/EdxeixSubmitPreflightGate.php',
    $homeRoot . '/gov.cabnet.app_app/src/Edxeix/EdxeixSubmitConnector.php',
    $homeRoot . '/gov.cabnet.app_app/src/Edxeix/EdxeixSubmitPayloadValidator.php',
];
foreach ($files as $file) {
    if (is_file($file)) {
        require_once $file;
    }
}

function espv_h(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function espv_badge(string $text, string $type = 'neutral'): string
{
    if (function_exists('opsui_badge')) {
        return opsui_badge($text, $type);
    }
    return '<span class="badge badge-' . espv_h($type) . '">' . espv_h($text) . '</span>';
}

function espv_csrf(): string
{
    if (empty($_SESSION['edxeix_payload_validator_csrf']) || !is_string($_SESSION['edxeix_payload_validator_csrf'])) {
        $_SESSION['edxeix_payload_validator_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['edxeix_payload_validator_csrf'];
}

function espv_validate_csrf(string $token): bool
{
    return isset($_SESSION['edxeix_payload_validator_csrf'])
        && is_string($_SESSION['edxeix_payload_validator_csrf'])
        && hash_equals($_SESSION['edxeix_payload_validator_csrf'], $token);
}

function espv_shell_begin(): void
{
    if (function_exists('opsui_shell_begin')) {
        opsui_shell_begin([
            'title' => 'EDXEIX Payload Validator',
            'page_title' => 'EDXEIX Submit Payload Validator',
            'active_section' => 'Mobile Submit',
            'breadcrumbs' => 'Αρχική / Mobile Submit / Payload Validator',
            'safe_notice' => 'Dry-run validation only. This page validates the future server-side EDXEIX request shape and never submits to EDXEIX.',
            'force_safe_notice' => true,
        ]);
        return;
    }
    echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>EDXEIX Payload Validator</title><style>body{font-family:Arial,Helvetica,sans-serif;background:#f3f6fb;color:#07152f;padding:18px}.card{background:#fff;border:1px solid #d8dde7;border-radius:8px;padding:16px;margin:0 0 16px}.btn{background:#2563eb;color:#fff;border:0;border-radius:6px;padding:10px 14px;font-weight:700}.badge{display:inline-block;padding:5px 9px;border-radius:999px;background:#eaf1ff;margin:2px}.badge-good{background:#dcfce7;color:#166534}.badge-warn{background:#fff7ed;color:#b45309}.badge-bad{background:#fee2e2;color:#991b1b}textarea{width:100%;min-height:250px}.small{color:#667085;font-size:13px}pre{background:#0b1220;color:#dbeafe;padding:12px;overflow:auto}</style></head><body>';
}

function espv_shell_end(): void
{
    if (function_exists('opsui_shell_end')) {
        opsui_shell_end();
        return;
    }
    echo '</body></html>';
}

function espv_app_context(?string &$error = null): ?array
{
    static $ctx = null;
    static $loaded = false;
    static $loadError = null;
    global $homeRoot;

    if ($loaded) {
        $error = $loadError;
        return is_array($ctx) ? $ctx : null;
    }

    $loaded = true;
    $bootstrap = $homeRoot . '/gov.cabnet.app_app/src/bootstrap.php';
    if (!is_file($bootstrap)) {
        $loadError = 'Private app bootstrap not found.';
        $error = $loadError;
        return null;
    }

    try {
        $ctx = require $bootstrap;
        if (!is_array($ctx)) {
            throw new RuntimeException('Private bootstrap did not return a context array.');
        }
        $error = null;
        return $ctx;
    } catch (Throwable $e) {
        $loadError = $e->getMessage();
        $error = $loadError;
        return null;
    }
}

function espv_db(?string &$error = null): ?mysqli
{
    $ctx = espv_app_context($error);
    if (!$ctx || !isset($ctx['db']) || !method_exists($ctx['db'], 'connection')) {
        $error = $error ?: 'Private DB context unavailable.';
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

function espv_table_exists(mysqli $db, string $table): bool
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

/** @return array<string,mixed>|null */
function espv_latest_capture(mysqli $db): ?array
{
    if (!espv_table_exists($db, 'ops_edxeix_submit_captures')) {
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

/** @return array<string,mixed> */
function espv_load_latest_server_email(): array
{
    if (!class_exists('Bridge\\BoltMail\\MaildirPreRideEmailLoader')) {
        return ['ok' => false, 'email_text' => '', 'source' => '', 'source_mtime' => '', 'error' => 'Maildir loader class is not installed.', 'checked_dirs' => []];
    }

    $extraDirs = [];
    $ctxError = null;
    $ctx = espv_app_context($ctxError);
    if (is_array($ctx) && isset($ctx['config']) && method_exists($ctx['config'], 'get')) {
        foreach (['mail.pre_ride_maildir', 'mail.bolt_bridge_maildir'] as $key) {
            $dir = $ctx['config']->get($key);
            if (is_string($dir) && trim($dir) !== '') {
                $extraDirs[] = trim($dir);
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
        return ['ok' => false, 'email_text' => '', 'source' => '', 'source_mtime' => '', 'error' => $e->getMessage(), 'checked_dirs' => $extraDirs];
    }
}

/** @param array<string,mixed> $fields */
function espv_lookup_ids(array $fields, ?string &$error = null): array
{
    $empty = ['ok' => false, 'lessor_id' => '', 'driver_id' => '', 'vehicle_id' => '', 'starting_point_id' => '', 'messages' => [], 'warnings' => ['Mapping lookup unavailable.']];
    if (!class_exists('Bridge\\BoltMail\\EdxeixMappingLookup')) {
        $error = 'EdxeixMappingLookup class is not installed.';
        return $empty;
    }
    $db = espv_db($error);
    if (!$db) {
        return $empty;
    }
    try {
        $lookup = new \Bridge\BoltMail\EdxeixMappingLookup($db);
        $result = $lookup->lookup($fields);
        return is_array($result) ? $result : $empty;
    } catch (Throwable $e) {
        $error = $e->getMessage();
        $empty['warnings'] = ['Mapping lookup failed: ' . $error];
        return $empty;
    }
}

/** @param array<string,mixed> $data */
function espv_json(array $data): string
{
    return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
}

function espv_field(array $fields, string $key): string
{
    return trim((string)($fields[$key] ?? ''));
}

function espv_row(string $label, string $value): string
{
    return '<tr><td><strong>' . espv_h($label) . '</strong></td><td>' . espv_h($value !== '' ? $value : '-') . '</td></tr>';
}

$csrf = espv_csrf();
$rawEmail = '';
$error = '';
$mailLoad = null;
$parseResult = null;
$fields = [];
$mapping = null;
$capture = null;
$preflight = null;
$request = null;
$validation = null;
$dbError = null;
$lookupError = null;

$db = espv_db($dbError);
if ($db) {
    $capture = espv_latest_capture($db);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!espv_validate_csrf((string)($_POST['csrf'] ?? ''))) {
        $error = 'Security token expired. Please reload and try again.';
    } else {
        $action = (string)($_POST['action'] ?? 'parse_pasted');
        if ($action === 'load_latest_server_email') {
            $mailLoad = espv_load_latest_server_email();
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
            if (!class_exists('Bridge\\BoltMail\\BoltPreRideEmailParser')) {
                $error = 'BoltPreRideEmailParser class is not installed.';
            } else {
                try {
                    $parser = new \Bridge\BoltMail\BoltPreRideEmailParser();
                    $parseResult = $parser->parse($rawEmail);
                    $fields = is_array($parseResult['fields'] ?? null) ? $parseResult['fields'] : [];
                    $mapping = espv_lookup_ids($fields, $lookupError);

                    if (class_exists('Bridge\\Edxeix\\EdxeixSubmitPreflightGate')) {
                        $preflight = (new \Bridge\Edxeix\EdxeixSubmitPreflightGate())->evaluate($fields, $mapping ?: [], $capture, [
                            'future_guard_minutes' => 30,
                            'live_connector_enabled' => false,
                            'operator_final_confirmed' => false,
                            'map_point_confirmed' => false,
                        ]);
                    }

                    if (class_exists('Bridge\\Edxeix\\EdxeixSubmitConnector')) {
                        $request = (new \Bridge\Edxeix\EdxeixSubmitConnector())->buildRequest($fields, $mapping ?: [], $capture);
                    }

                    if (class_exists('Bridge\\Edxeix\\EdxeixSubmitPayloadValidator') && is_array($request)) {
                        $validation = (new \Bridge\Edxeix\EdxeixSubmitPayloadValidator())->validate($request, $capture, $preflight);
                    }
                } catch (Throwable $e) {
                    $error = $e->getMessage();
                }
            }
        } elseif ($error === '') {
            $error = 'No email text was provided.';
        }
    }
}

espv_shell_begin();
?>
<style>
.espv-grid{display:grid;grid-template-columns:minmax(0,1fr) minmax(340px,.7fr);gap:18px}.espv-card{background:#fff;border:1px solid #d8dde7;border-radius:4px;padding:18px;margin-bottom:18px}.espv-card h2{margin-top:0}.espv-actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:12px}.espv-email{min-height:260px;width:100%;box-sizing:border-box}.espv-table{width:100%;border-collapse:collapse}.espv-table td,.espv-table th{border-bottom:1px solid #eef1f5;padding:9px;text-align:left;vertical-align:top}.espv-pre{background:#0b1220;color:#dbeafe;padding:12px;border-radius:4px;overflow:auto;max-height:520px;font-size:12px}.espv-blocker{border:1px solid #fecaca;background:#fee2e2;color:#991b1b;padding:8px 10px;border-radius:4px;margin:6px 0}.espv-warn{border:1px solid #fed7aa;background:#fff7ed;color:#9a3412;padding:8px 10px;border-radius:4px;margin:6px 0}@media(max-width:980px){.espv-grid{grid-template-columns:1fr}.espv-actions .btn{width:100%;text-align:center}}</style>

<section class="card hero warn">
    <h1>EDXEIX Submit Payload Validator</h1>
    <p>Validates the dry-run request shape for the future mobile/server-side EDXEIX submitter. This page never submits anything and never displays real cookies, sessions, credentials, or CSRF token values.</p>
    <div>
        <?= espv_badge('DRY-RUN ONLY', 'good') ?>
        <?= espv_badge('NO LIVE SUBMIT', 'good') ?>
        <?= espv_badge('NO EDXEIX CALL', 'good') ?>
        <?= espv_badge('NO DB WRITE', 'good') ?>
    </div>
</section>

<section class="espv-grid">
    <div>
        <form class="espv-card" method="post" action="/ops/edxeix-submit-payload-validator.php" autocomplete="off">
            <h2>1. Load or paste Bolt pre-ride email</h2>
            <input type="hidden" name="csrf" value="<?= espv_h($csrf) ?>">
            <textarea class="espv-email" name="email_text" placeholder="Paste Bolt pre-ride email here..."><?= espv_h($rawEmail) ?></textarea>
            <div class="espv-actions">
                <button class="btn green" type="submit" name="action" value="parse_pasted">Parse + Validate Payload</button>
                <button class="btn warn" type="submit" name="action" value="load_latest_server_email">Load latest server email</button>
                <a class="btn dark" href="/ops/edxeix-submit-payload-validator.php">Clear</a>
            </div>
        </form>

        <?php if ($error !== ''): ?>
            <section class="espv-card"><h2>Error</h2><p class="badline"><strong><?= espv_h($error) ?></strong></p></section>
        <?php endif; ?>

        <?php if (is_array($parseResult)): ?>
            <section class="espv-card">
                <h2>2. Parsed ride + EDXEIX IDs</h2>
                <table class="espv-table"><tbody>
                    <?= espv_row('Passenger', espv_field($fields, 'customer_name')) ?>
                    <?= espv_row('Driver', espv_field($fields, 'driver_name') . ' → ' . (string)($mapping['driver_id'] ?? '')) ?>
                    <?= espv_row('Vehicle', espv_field($fields, 'vehicle_plate') . ' → ' . (string)($mapping['vehicle_id'] ?? '')) ?>
                    <?= espv_row('Lessor ID', (string)($mapping['lessor_id'] ?? '')) ?>
                    <?= espv_row('Starting point ID', (string)($mapping['starting_point_id'] ?? '')) ?>
                    <?= espv_row('Pickup', espv_field($fields, 'pickup_address')) ?>
                    <?= espv_row('Drop-off', espv_field($fields, 'dropoff_address')) ?>
                    <?= espv_row('Pickup datetime', espv_field($fields, 'pickup_datetime_local')) ?>
                    <?= espv_row('End datetime', espv_field($fields, 'end_datetime_local')) ?>
                    <?= espv_row('Price', espv_field($fields, 'estimated_price_amount')) ?>
                </tbody></table>
                <?php foreach ((array)($mapping['warnings'] ?? []) as $warning): ?>
                    <div class="espv-warn"><?= espv_h((string)$warning) ?></div>
                <?php endforeach; ?>
                <?php if ($lookupError): ?><div class="espv-warn"><?= espv_h($lookupError) ?></div><?php endif; ?>
            </section>

            <section class="espv-card">
                <h2>3. Payload validation result</h2>
                <?php if (is_array($validation)): ?>
                    <p>
                        <?= espv_badge(!empty($validation['dry_run_payload_valid']) ? 'DRY-RUN PAYLOAD STRUCTURE OK' : 'PAYLOAD NEEDS WORK', !empty($validation['dry_run_payload_valid']) ? 'good' : 'warn') ?>
                        <?= espv_badge('LIVE SUBMIT BLOCKED', 'good') ?>
                    </p>
                    <h3>Blockers</h3>
                    <?php foreach ((array)($validation['blockers'] ?? []) as $blocker): ?>
                        <div class="espv-blocker"><?= espv_h((string)$blocker) ?></div>
                    <?php endforeach; ?>
                    <h3>Warnings</h3>
                    <?php foreach ((array)($validation['warnings'] ?? []) as $warning): ?>
                        <div class="espv-warn"><?= espv_h((string)$warning) ?></div>
                    <?php endforeach; ?>
                    <?php if (empty($validation['warnings'])): ?><p class="small">No additional warnings.</p><?php endif; ?>
                <?php else: ?>
                    <p class="warnline">Validator could not run. Confirm Phase 52 connector and Phase 53 validator files are installed.</p>
                <?php endif; ?>
            </section>

            <?php if (is_array($request)): ?>
            <section class="espv-card">
                <h2>4. Dry-run request preview</h2>
                <pre class="espv-pre"><?= espv_h(espv_json($request)) ?></pre>
            </section>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <aside>
        <section class="espv-card">
            <h2>Readiness facts</h2>
            <table class="espv-table"><tbody>
                <?= espv_row('DB context', $db ? 'available' : 'unavailable: ' . (string)$dbError) ?>
                <?= espv_row('Submit capture', $capture ? 'available: #' . (string)($capture['id'] ?? '') : 'missing') ?>
                <?= espv_row('Preflight gate class', class_exists('Bridge\\Edxeix\\EdxeixSubmitPreflightGate') ? 'installed' : 'missing') ?>
                <?= espv_row('Connector class', class_exists('Bridge\\Edxeix\\EdxeixSubmitConnector') ? 'installed' : 'missing') ?>
                <?= espv_row('Payload validator class', class_exists('Bridge\\Edxeix\\EdxeixSubmitPayloadValidator') ? 'installed' : 'missing') ?>
            </tbody></table>
        </section>

        <section class="espv-card">
            <h2>Related pages</h2>
            <ul class="list">
                <li><a href="/ops/mobile-submit-readiness.php">Mobile Submit Readiness</a></li>
                <li><a href="/ops/edxeix-session-readiness.php">EDXEIX Session Readiness</a></li>
                <li><a href="/ops/edxeix-submit-connector-dev.php">EDXEIX Connector Dev</a></li>
                <li><a href="/ops/edxeix-submit-preflight-gate.php">EDXEIX Preflight Gate</a></li>
                <li><a href="/ops/edxeix-submit-capture.php">EDXEIX Submit Capture</a></li>
                <li><a href="/ops/mapping-center.php">Mapping Center</a></li>
            </ul>
        </section>

        <?php if (is_array($preflight)): ?>
        <section class="espv-card">
            <h2>Preflight gate JSON</h2>
            <pre class="espv-pre"><?= espv_h(espv_json($preflight)) ?></pre>
        </section>
        <?php endif; ?>

        <?php if (is_array($validation)): ?>
        <section class="espv-card">
            <h2>Validator JSON</h2>
            <pre class="espv-pre"><?= espv_h(espv_json($validation)) ?></pre>
        </section>
        <?php endif; ?>
    </aside>
</section>
<?php espv_shell_end(); ?>
