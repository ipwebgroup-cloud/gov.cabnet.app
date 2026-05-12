<?php
/**
 * gov.cabnet.app — EDXEIX Submit Preflight Gate v0.1
 *
 * Read-only safety gate evaluator for the future mobile/server-side EDXEIX submit workflow.
 * It parses, resolves IDs, loads sanitized submit capture metadata, and reports blockers.
 * It does not submit to EDXEIX and does not write workflow data.
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
$bootstrapFile = $root . '/gov.cabnet.app_app/src/bootstrap.php';
$parserFile = $root . '/gov.cabnet.app_app/src/BoltMail/BoltPreRideEmailParser.php';
$lookupFile = $root . '/gov.cabnet.app_app/src/BoltMail/EdxeixMappingLookup.php';
$mailLoaderFile = $root . '/gov.cabnet.app_app/src/BoltMail/MaildirPreRideEmailLoader.php';
$gateFile = $root . '/gov.cabnet.app_app/src/Edxeix/EdxeixSubmitPreflightGate.php';

foreach ([$parserFile, $lookupFile, $mailLoaderFile, $gateFile] as $file) {
    if (is_file($file)) {
        require_once $file;
    }
}

use Bridge\BoltMail\BoltPreRideEmailParser;
use Bridge\BoltMail\EdxeixMappingLookup;
use Bridge\BoltMail\MaildirPreRideEmailLoader;
use Bridge\Edxeix\EdxeixSubmitPreflightGate;

function epg_h(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function epg_badge(string $text, string $type = 'neutral'): string
{
    if (function_exists('opsui_badge')) {
        return opsui_badge($text, $type);
    }
    return '<span class="badge badge-' . epg_h($type) . '">' . epg_h($text) . '</span>';
}

function epg_csrf(): string
{
    if (empty($_SESSION['edxeix_preflight_gate_csrf']) || !is_string($_SESSION['edxeix_preflight_gate_csrf'])) {
        $_SESSION['edxeix_preflight_gate_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['edxeix_preflight_gate_csrf'];
}

function epg_validate_csrf(string $token): bool
{
    return isset($_SESSION['edxeix_preflight_gate_csrf'])
        && is_string($_SESSION['edxeix_preflight_gate_csrf'])
        && hash_equals($_SESSION['edxeix_preflight_gate_csrf'], $token);
}

function epg_context(?string &$error = null): ?array
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
        $loadError = 'Private app bootstrap not found.';
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

function epg_load_latest_email(): array
{
    if (!class_exists(MaildirPreRideEmailLoader::class)) {
        return ['ok' => false, 'email_text' => '', 'error' => 'MaildirPreRideEmailLoader class is not installed.', 'source' => '', 'source_mtime' => '', 'checked_dirs' => []];
    }

    $extraDirs = [];
    $ctxError = null;
    $ctx = epg_context($ctxError);
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
        return (new MaildirPreRideEmailLoader())->loadLatest(array_values(array_unique($extraDirs)));
    } catch (Throwable $e) {
        return ['ok' => false, 'email_text' => '', 'error' => $e->getMessage(), 'source' => '', 'source_mtime' => '', 'checked_dirs' => $extraDirs];
    }
}

function epg_lookup_ids(array $fields, ?string &$error = null): array
{
    $empty = ['ok' => false, 'lessor_id' => '', 'driver_id' => '', 'vehicle_id' => '', 'starting_point_id' => '', 'messages' => [], 'warnings' => ['EDXEIX ID lookup unavailable.']];
    if (!class_exists(EdxeixMappingLookup::class)) {
        $error = 'EdxeixMappingLookup class is not installed.';
        return $empty;
    }
    $ctx = epg_context($error);
    if (!$ctx || !isset($ctx['db']) || !method_exists($ctx['db'], 'connection')) {
        return $empty;
    }
    try {
        return (new EdxeixMappingLookup($ctx['db']->connection()))->lookup($fields);
    } catch (Throwable $e) {
        $error = $e->getMessage();
        $empty['warnings'] = ['DB lookup failed: ' . $error];
        return $empty;
    }
}

function epg_table_exists(mysqli $db, string $table): bool
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

function epg_latest_capture(?string &$error = null): ?array
{
    $ctx = epg_context($error);
    if (!$ctx || !isset($ctx['db']) || !method_exists($ctx['db'], 'connection')) {
        return null;
    }
    try {
        $db = $ctx['db']->connection();
        if (!epg_table_exists($db, 'ops_edxeix_submit_captures')) {
            $error = 'ops_edxeix_submit_captures table is not installed yet.';
            return null;
        }
        $res = $db->query('SELECT * FROM ops_edxeix_submit_captures ORDER BY id DESC LIMIT 1');
        $row = $res ? $res->fetch_assoc() : null;
        if (!is_array($row)) {
            $error = 'No sanitized submit capture has been saved yet.';
            return null;
        }
        $error = null;
        return $row;
    } catch (Throwable $e) {
        $error = $e->getMessage();
        return null;
    }
}

function epg_field(array $fields, string $key): string
{
    return trim((string)($fields[$key] ?? ''));
}

function epg_row(string $label, string $value, string $hint = ''): string
{
    return '<div class="kv-row"><div class="k">' . epg_h($label) . '</div><div><strong>' . epg_h($value !== '' ? $value : '-') . '</strong>' . ($hint !== '' ? '<div class="small">' . epg_h($hint) . '</div>' : '') . '</div></div>';
}

function epg_shell_begin(): void
{
    if (function_exists('opsui_shell_begin')) {
        opsui_shell_begin([
            'title' => 'EDXEIX Submit Preflight Gate',
            'page_title' => 'EDXEIX Submit Preflight Gate',
            'active_section' => 'Mobile Submit',
            'breadcrumbs' => 'Αρχική / Mobile Submit / Preflight Gate',
            'safe_notice' => 'Read-only gate evaluator. This page does not submit to EDXEIX, does not stage jobs, and does not modify the production pre-ride tool.',
            'force_safe_notice' => true,
        ]);
        return;
    }
    echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>EDXEIX Submit Preflight Gate</title><style>body{font-family:Arial;background:#eef1f6;color:#20293a;margin:0;padding:18px}.card{background:#fff;border:1px solid #d8dde7;border-radius:6px;padding:16px;margin:0 0 16px}.badge{display:inline-block;padding:6px 10px;border-radius:12px;background:#e9edf7;margin:2px}.badge-good{background:#dbf0dc;color:#2d7b37}.badge-warn{background:#f8ead3;color:#9a5a00}.badge-bad{background:#f8dedd;color:#b13c35}.btn{display:inline-block;background:#4f5ea7;color:#fff;padding:10px 14px;border:0;border-radius:4px;text-decoration:none;font-weight:bold}textarea{width:100%;min-height:240px}.kv-row{display:grid;grid-template-columns:210px 1fr;gap:12px;border-bottom:1px solid #eef1f5;padding:9px 0}.k{font-weight:bold;color:#667085}.small{font-size:13px;color:#667085}@media(max-width:760px){.kv-row{grid-template-columns:1fr}}</style></head><body>';
}

function epg_shell_end(): void
{
    if (function_exists('opsui_shell_end')) {
        opsui_shell_end();
        return;
    }
    echo '</body></html>';
}

$rawEmail = '';
$parseResult = null;
$mapping = null;
$capture = null;
$evaluation = null;
$error = '';
$mailLoad = null;
$lookupError = null;
$captureError = null;
$csrf = epg_csrf();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!epg_validate_csrf((string)($_POST['csrf'] ?? ''))) {
        $error = 'Security token expired. Please try again.';
    } else {
        $action = (string)($_POST['action'] ?? 'parse_pasted');
        if ($action === 'load_latest_server_email') {
            $mailLoad = epg_load_latest_email();
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
            } elseif (!class_exists(EdxeixSubmitPreflightGate::class)) {
                $error = 'EdxeixSubmitPreflightGate class is not installed.';
            } else {
                try {
                    $parseResult = (new BoltPreRideEmailParser())->parse($rawEmail);
                    $fields = is_array($parseResult['fields'] ?? null) ? $parseResult['fields'] : [];
                    $mapping = epg_lookup_ids($fields, $lookupError);
                    $capture = epg_latest_capture($captureError);
                    $evaluation = (new EdxeixSubmitPreflightGate())->evaluate($fields, is_array($mapping) ? $mapping : [], $capture, [
                        'future_guard_minutes' => 30,
                        'timezone' => 'Europe/Athens',
                        'live_connector_enabled' => false,
                        'operator_final_confirmed' => false,
                        'map_point_confirmed' => false,
                    ]);
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

epg_shell_begin();
?>
<style>
.epg-grid{display:grid;grid-template-columns:minmax(0,1fr) minmax(340px,.8fr);gap:18px}.epg-stack{display:grid;gap:16px}.epg-card{background:#fff;border:1px solid #d8dde7;border-radius:4px;padding:18px 20px;box-shadow:0 6px 18px rgba(26,33,52,.06)}.epg-card h2{margin-top:0}.epg-textarea{width:100%;min-height:280px;box-sizing:border-box;border:1px solid #d8dde7;border-radius:4px;padding:12px;font-family:Arial,Helvetica,sans-serif}.epg-actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:12px}.epg-pill-list{display:flex;gap:8px;flex-wrap:wrap;margin:8px 0 14px}.epg-blocker{border:1px solid #fecaca;background:#fee2e2;color:#991b1b;border-radius:4px;padding:9px 11px;margin:6px 0;font-family:Consolas,Menlo,monospace;font-size:13px}.epg-warning{border:1px solid #fed7aa;background:#fff7ed;color:#9a3412;border-radius:4px;padding:9px 11px;margin:6px 0}.epg-json{background:#101827;color:#dbeafe;border-radius:4px;padding:14px;overflow:auto;font-size:13px;line-height:1.45}.kv-row{display:grid;grid-template-columns:210px minmax(0,1fr);gap:12px;border-bottom:1px solid #eef1f5;padding:9px 0}.kv-row:last-child{border-bottom:0}.k{font-weight:700;color:#667085}@media(max-width:980px){.epg-grid{grid-template-columns:1fr}.kv-row{grid-template-columns:1fr}.epg-actions .btn{width:100%;text-align:center}.epg-textarea{min-height:220px}}</style>

<section class="card hero warn">
    <h1>EDXEIX Submit Preflight Gate</h1>
    <p>This page evaluates whether a parsed Bolt pre-ride email would pass the technical and live-submit safety gates for the future mobile/server-side EDXEIX workflow. Live submit remains disabled.</p>
    <div>
        <?= epg_badge('READ ONLY', 'good') ?>
        <?= epg_badge('NO EDXEIX CALL', 'good') ?>
        <?= epg_badge('NO WORKFLOW WRITE', 'good') ?>
        <?= epg_badge('LIVE SUBMIT OFF', 'warn') ?>
    </div>
</section>

<section class="epg-grid">
    <div class="epg-stack">
        <form class="epg-card" method="post" action="/ops/edxeix-submit-preflight-gate.php" autocomplete="off">
            <h2>1. Load or paste pre-ride email</h2>
            <input type="hidden" name="csrf" value="<?= epg_h($csrf) ?>">
            <textarea class="epg-textarea" name="email_text" placeholder="Paste Bolt pre-ride email here..."><?= epg_h($rawEmail) ?></textarea>
            <div class="epg-actions">
                <button class="btn good" type="submit" name="action" value="parse_pasted">Evaluate Gate</button>
                <button class="btn warn" type="submit" name="action" value="load_latest_server_email">Load latest server email</button>
                <a class="btn dark" href="/ops/edxeix-submit-preflight-gate.php">Clear</a>
            </div>
            <?php if (is_array($mailLoad) && !empty($mailLoad['ok'])): ?>
                <p class="goodline"><strong>Loaded:</strong> <?= epg_h((string)($mailLoad['source'] ?? '')) ?> <?= !empty($mailLoad['source_mtime']) ? '(' . epg_h((string)$mailLoad['source_mtime']) . ')' : '' ?></p>
            <?php endif; ?>
        </form>

        <?php if ($error !== ''): ?>
            <section class="epg-card"><h2>Problem</h2><p class="badline"><strong><?= epg_h($error) ?></strong></p></section>
        <?php endif; ?>

        <?php if (is_array($parseResult)): ?>
            <section class="epg-card">
                <h2>2. Parsed ride</h2>
                <div class="epg-pill-list">
                    <?= epg_badge('Parser: ' . (string)($parseResult['confidence'] ?? 'unknown'), empty($parseResult['ok']) ? 'warn' : 'good') ?>
                    <?= epg_badge('AADE not involved', 'good') ?>
                </div>
                <?= epg_row('Passenger', epg_field($fields, 'customer_name'), epg_field($fields, 'customer_phone')) ?>
                <?= epg_row('Driver', epg_field($fields, 'driver_name')) ?>
                <?= epg_row('Vehicle', epg_field($fields, 'vehicle_plate')) ?>
                <?= epg_row('Pickup', epg_field($fields, 'pickup_address')) ?>
                <?= epg_row('Drop-off', epg_field($fields, 'dropoff_address')) ?>
                <?= epg_row('Pickup datetime', epg_field($fields, 'pickup_datetime_local'), epg_field($fields, 'pickup_timezone')) ?>
                <?= epg_row('End datetime', epg_field($fields, 'end_datetime_local'), epg_field($fields, 'end_timezone')) ?>
                <?= epg_row('Price', epg_field($fields, 'estimated_price_amount'), epg_field($fields, 'estimated_price_text')) ?>
            </section>

            <section class="epg-card">
                <h2>3. EDXEIX IDs and capture</h2>
                <div class="epg-pill-list">
                    <?= epg_badge(!empty($mapping['ok']) ? 'IDS READY' : 'IDS NEED REVIEW', !empty($mapping['ok']) ? 'good' : 'warn') ?>
                    <?= epg_badge(is_array($capture) ? 'CAPTURE AVAILABLE' : 'CAPTURE MISSING', is_array($capture) ? 'good' : 'warn') ?>
                </div>
                <?= epg_row('Lessor ID', (string)($mapping['lessor_id'] ?? ''), (string)($mapping['lessor_source'] ?? '')) ?>
                <?= epg_row('Driver ID', (string)($mapping['driver_id'] ?? ''), (string)($mapping['driver_label'] ?? '')) ?>
                <?= epg_row('Vehicle ID', (string)($mapping['vehicle_id'] ?? ''), (string)($mapping['vehicle_label'] ?? '')) ?>
                <?= epg_row('Starting point ID', (string)($mapping['starting_point_id'] ?? ''), (string)($mapping['starting_point_label'] ?? '')) ?>
                <?= epg_row('Capture ID', is_array($capture) ? (string)($capture['id'] ?? '') : '', $captureError ?: '') ?>
                <?= epg_row('Capture action', is_array($capture) ? ((string)($capture['form_method'] ?? '') . ' ' . (string)($capture['action_host'] ?? '') . (string)($capture['action_path'] ?? '')) : '') ?>
            </section>
        <?php endif; ?>
    </div>

    <aside class="epg-stack">
        <section class="epg-card">
            <h2>Gate result</h2>
            <?php if (is_array($evaluation)): ?>
                <div class="epg-pill-list">
                    <?= epg_badge(!empty($evaluation['technical_ready']) ? 'TECHNICAL READY' : 'TECHNICAL BLOCKED', !empty($evaluation['technical_ready']) ? 'good' : 'bad') ?>
                    <?= epg_badge(!empty($evaluation['dry_run_payload_allowed']) ? 'DRY-RUN ALLOWED' : 'DRY-RUN BLOCKED', !empty($evaluation['dry_run_payload_allowed']) ? 'good' : 'bad') ?>
                    <?= epg_badge(!empty($evaluation['live_submit_allowed']) ? 'LIVE WOULD BE ALLOWED' : 'LIVE BLOCKED', !empty($evaluation['live_submit_allowed']) ? 'good' : 'warn') ?>
                </div>

                <h3>Technical blockers</h3>
                <?php if (empty($evaluation['technical_blockers'])): ?>
                    <p class="goodline">No technical blockers detected.</p>
                <?php else: ?>
                    <?php foreach ((array)$evaluation['technical_blockers'] as $blocker): ?>
                        <div class="epg-blocker"><?= epg_h((string)$blocker) ?></div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <h3>Live blockers</h3>
                <?php foreach ((array)$evaluation['live_blockers'] as $blocker): ?>
                    <div class="epg-blocker"><?= epg_h((string)$blocker) ?></div>
                <?php endforeach; ?>

                <?php if (!empty($evaluation['warnings'])): ?>
                    <h3>Warnings</h3>
                    <?php foreach ((array)$evaluation['warnings'] as $warning): ?>
                        <div class="epg-warning"><?= epg_h((string)$warning) ?></div>
                    <?php endforeach; ?>
                <?php endif; ?>
            <?php else: ?>
                <p>Evaluate a pre-ride email to see technical and live-submit blockers.</p>
            <?php endif; ?>
        </section>

        <?php if (is_array($evaluation)): ?>
            <section class="epg-card">
                <h2>Gate JSON</h2>
                <pre class="epg-json"><?= epg_h(json_encode($evaluation, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></pre>
            </section>
        <?php endif; ?>

        <section class="epg-card">
            <h2>Next safe step</h2>
            <p>After this gate is proven stable, the next phase should store sanitized dry-run gate evaluations locally for audit visibility. Live submit remains a separate explicit decision.</p>
            <div class="epg-actions">
                <a class="btn" href="/ops/edxeix-submit-dry-run.php">Dry-Run Builder</a>
                <a class="btn dark" href="/ops/edxeix-submit-capture.php">Submit Capture</a>
            </div>
        </section>
    </aside>
</section>
<?php
epg_shell_end();
