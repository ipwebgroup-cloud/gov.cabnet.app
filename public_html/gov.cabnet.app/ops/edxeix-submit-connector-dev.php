<?php
/**
 * gov.cabnet.app — EDXEIX Submit Connector Dev
 *
 * Development-only page for the future server-side EDXEIX connector contract.
 * It builds a disabled dry-run request preview and never submits anything.
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
$preflightFile = $root . '/gov.cabnet.app_app/src/Edxeix/EdxeixSubmitPreflightGate.php';
$connectorFile = $root . '/gov.cabnet.app_app/src/Edxeix/EdxeixSubmitConnector.php';
$bootstrapFile = $root . '/gov.cabnet.app_app/src/bootstrap.php';

foreach ([$parserFile, $lookupFile, $mailLoaderFile, $preflightFile, $connectorFile] as $file) {
    if (is_file($file)) {
        require_once $file;
    }
}

use Bridge\BoltMail\BoltPreRideEmailParser;
use Bridge\BoltMail\EdxeixMappingLookup;
use Bridge\BoltMail\MaildirPreRideEmailLoader;
use Bridge\Edxeix\EdxeixSubmitConnector;
use Bridge\Edxeix\EdxeixSubmitPreflightGate;

function escdev_h(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function escdev_badge(string $text, string $type = 'neutral'): string
{
    $type = in_array($type, ['good', 'warn', 'bad', 'neutral'], true) ? $type : 'neutral';
    if (function_exists('opsui_badge')) {
        return opsui_badge($text, $type);
    }
    return '<span class="badge badge-' . escdev_h($type) . '">' . escdev_h($text) . '</span>';
}

function escdev_csrf(): string
{
    if (empty($_SESSION['edxeix_submit_connector_dev_csrf']) || !is_string($_SESSION['edxeix_submit_connector_dev_csrf'])) {
        $_SESSION['edxeix_submit_connector_dev_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['edxeix_submit_connector_dev_csrf'];
}

function escdev_validate_csrf(string $token): bool
{
    return isset($_SESSION['edxeix_submit_connector_dev_csrf'])
        && is_string($_SESSION['edxeix_submit_connector_dev_csrf'])
        && hash_equals($_SESSION['edxeix_submit_connector_dev_csrf'], $token);
}

function escdev_app_context(?string &$error = null): ?array
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
            throw new RuntimeException('Private app bootstrap did not return an array.');
        }
        $error = null;
        return $ctx;
    } catch (Throwable $e) {
        $loadError = $e->getMessage();
        $error = $loadError;
        return null;
    }
}

function escdev_table_exists(mysqli $db, string $table): bool
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
function escdev_latest_capture(?string &$error = null): ?array
{
    $ctx = escdev_app_context($error);
    if (!$ctx || !isset($ctx['db']) || !method_exists($ctx['db'], 'connection')) {
        return null;
    }

    try {
        $db = $ctx['db']->connection();
        if (!$db instanceof mysqli || !escdev_table_exists($db, 'ops_edxeix_submit_captures')) {
            $error = 'ops_edxeix_submit_captures table is not available.';
            return null;
        }
        $res = $db->query('SELECT * FROM ops_edxeix_submit_captures ORDER BY id DESC LIMIT 1');
        $row = $res ? $res->fetch_assoc() : null;
        if (!is_array($row)) {
            $error = 'No sanitized submit capture row exists yet.';
            return null;
        }
        $error = null;
        return $row;
    } catch (Throwable $e) {
        $error = $e->getMessage();
        return null;
    }
}

/** @return array<string,mixed> */
function escdev_load_latest_email(): array
{
    if (!class_exists(MaildirPreRideEmailLoader::class)) {
        return ['ok' => false, 'email_text' => '', 'source' => '', 'source_mtime' => '', 'error' => 'MaildirPreRideEmailLoader class is missing.', 'checked_dirs' => []];
    }

    $extraDirs = [];
    $ctxError = null;
    $ctx = escdev_app_context($ctxError);
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
        return ['ok' => false, 'email_text' => '', 'source' => '', 'source_mtime' => '', 'error' => $e->getMessage(), 'checked_dirs' => $extraDirs];
    }
}

/** @param array<string,mixed> $fields @return array<string,mixed> */
function escdev_lookup(array $fields, ?string &$error = null): array
{
    $empty = [
        'ok' => false,
        'lessor_id' => '',
        'driver_id' => '',
        'vehicle_id' => '',
        'starting_point_id' => '',
        'messages' => [],
        'warnings' => ['EDXEIX mapping lookup unavailable.'],
    ];

    if (!class_exists(EdxeixMappingLookup::class)) {
        $error = 'EdxeixMappingLookup class is missing.';
        return $empty;
    }

    $ctx = escdev_app_context($error);
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
        $empty['warnings'] = ['Lookup error: ' . $error];
        return $empty;
    }
}

function escdev_shell_begin(): void
{
    if (function_exists('opsui_shell_begin')) {
        opsui_shell_begin([
            'title' => 'EDXEIX Submit Connector Dev',
            'page_title' => 'EDXEIX Submit Connector Dev',
            'active_section' => 'Mobile Submit',
            'breadcrumbs' => 'Αρχική / EDXEIX / Connector Dev',
            'safe_notice' => 'Development-only connector contract. This page builds a disabled dry-run request preview and never submits to EDXEIX.',
            'force_safe_notice' => true,
        ]);
        return;
    }
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>EDXEIX Submit Connector Dev</title><style>body{font-family:Arial,sans-serif;background:#eef1f6;padding:20px}.card{background:#fff;border:1px solid #d8dde7;border-radius:8px;padding:16px;margin:0 0 16px}.badge{display:inline-block;padding:6px 9px;border-radius:12px;background:#e9edf7}.badge-good{background:#dcfce7}.badge-warn{background:#fff7ed}.badge-bad{background:#fee2e2}.grid{display:grid;grid-template-columns:repeat(3,1fr);gap:12px}textarea{width:100%;min-height:260px}.code{background:#0b1220;color:#dbeafe;padding:12px;border-radius:6px;white-space:pre-wrap;overflow:auto}@media(max-width:900px){.grid{grid-template-columns:1fr}}</style></head><body>';
}

function escdev_shell_end(): void
{
    if (function_exists('opsui_shell_end')) {
        opsui_shell_end();
        return;
    }
    echo '</body></html>';
}

function escdev_field(array $fields, string $key): string
{
    return trim((string)($fields[$key] ?? ''));
}

function escdev_kv(string $label, string $value): string
{
    return '<div class="kv-row"><div class="k">' . escdev_h($label) . '</div><div><strong>' . escdev_h($value !== '' ? $value : '-') . '</strong></div></div>';
}

$csrf = escdev_csrf();
$rawEmail = '';
$mailLoad = null;
$error = '';
$parseResult = null;
$mapping = null;
$capture = null;
$captureError = null;
$preflight = null;
$request = null;
$disabledSubmitResult = null;
$lookupError = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!escdev_validate_csrf((string)($_POST['csrf'] ?? ''))) {
        $error = 'Security token expired. Please reload and try again.';
    } else {
        $action = (string)($_POST['action'] ?? 'parse_pasted');
        if ($action === 'load_latest_server_email') {
            $mailLoad = escdev_load_latest_email();
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
                $error = 'BoltPreRideEmailParser class is missing.';
            } elseif (!class_exists(EdxeixSubmitConnector::class)) {
                $error = 'EdxeixSubmitConnector class is missing.';
            } else {
                try {
                    $parser = new BoltPreRideEmailParser();
                    $parseResult = $parser->parse($rawEmail);
                    $fields = is_array($parseResult['fields'] ?? null) ? $parseResult['fields'] : [];
                    $mapping = escdev_lookup($fields, $lookupError);
                    $capture = escdev_latest_capture($captureError);

                    if (class_exists(EdxeixSubmitPreflightGate::class)) {
                        $gate = new EdxeixSubmitPreflightGate();
                        $preflight = $gate->evaluate($fields, $mapping, $capture, [
                            'live_connector_enabled' => false,
                            'operator_final_confirmed' => false,
                            'map_point_confirmed' => false,
                            'future_guard_minutes' => 30,
                            'timezone' => 'Europe/Athens',
                        ]);
                    }

                    $connector = new EdxeixSubmitConnector();
                    $request = $connector->buildRequest($fields, $mapping, $capture);
                    $disabledSubmitResult = $connector->submitDisabled($request);
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

escdev_shell_begin();
?>
<style>
.escdev-grid{display:grid;grid-template-columns:minmax(0,1fr) minmax(320px,.72fr);gap:18px}.escdev-card{background:#fff;border:1px solid #d8dde7;border-radius:4px;padding:18px 20px;box-shadow:0 6px 18px rgba(26,33,52,.06);margin-bottom:18px}.escdev-card h2{margin-top:0}.escdev-textarea{min-height:260px;font-family:Arial,Helvetica,sans-serif}.escdev-actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:12px}.kv-row{display:grid;grid-template-columns:185px minmax(0,1fr);gap:12px;border-bottom:1px solid #eef1f5;padding:9px 0}.kv-row:last-child{border-bottom:0}.k{font-weight:700;color:#667085}.escdev-code{background:#0b1220;color:#dbeafe;border-radius:6px;padding:13px;font-family:Consolas,Menlo,monospace;font-size:12.5px;white-space:pre-wrap;overflow:auto;max-height:560px}.escdev-blocker{background:#fff7ed;border:1px solid #fed7aa;border-radius:4px;padding:9px;margin:6px 0;color:#9a3412}.escdev-blocker.bad{background:#fee2e2;border-color:#fecaca;color:#991b1b}.escdev-pill-list{display:flex;gap:8px;flex-wrap:wrap;margin:8px 0}.escdev-disabled{opacity:.65}.escdev-note{border-left:5px solid #d4922d;background:#fff7ed;padding:12px;border-radius:4px;color:#8a4d00;margin:10px 0}@media(max-width:980px){.escdev-grid{grid-template-columns:1fr}.kv-row{grid-template-columns:1fr}.escdev-actions .btn{width:100%;text-align:center}}
</style>

<section class="card hero warn">
    <h1>EDXEIX Submit Connector Dev</h1>
    <p>This page defines and tests the future server-side connector contract. It builds a request preview only. It does not call EDXEIX and does not contain a live submit button.</p>
    <div>
        <?= escdev_badge('DRY-RUN CONTRACT', 'warn') ?>
        <?= escdev_badge('NO LIVE SUBMIT', 'good') ?>
        <?= escdev_badge('NO EDXEIX CALL', 'good') ?>
        <?= escdev_badge('NO DB WRITE', 'good') ?>
    </div>
</section>

<section class="escdev-grid">
    <div>
        <form class="escdev-card" method="post" action="/ops/edxeix-submit-connector-dev.php" autocomplete="off">
            <h2>1. Load or paste Bolt pre-ride email</h2>
            <p class="small">The connector contract uses parsed ride fields, resolver IDs, and the latest sanitized submit capture to build a disabled dry-run request.</p>
            <input type="hidden" name="csrf" value="<?= escdev_h($csrf) ?>">
            <textarea class="escdev-textarea" name="email_text" placeholder="Paste Bolt pre-ride email here..."><?= escdev_h($rawEmail) ?></textarea>
            <div class="escdev-actions">
                <button class="btn good" type="submit" name="action" value="parse_pasted">Build connector dry-run</button>
                <button class="btn warn" type="submit" name="action" value="load_latest_server_email">Load latest server email</button>
                <a class="btn dark" href="/ops/edxeix-submit-connector-dev.php">Clear</a>
            </div>
            <?php if (is_array($mailLoad) && !empty($mailLoad['ok'])): ?>
                <p class="goodline"><strong>Loaded:</strong> <?= escdev_h((string)($mailLoad['source'] ?? '')) ?> <?= !empty($mailLoad['source_mtime']) ? '(' . escdev_h((string)$mailLoad['source_mtime']) . ')' : '' ?></p>
            <?php endif; ?>
        </form>

        <?php if ($error !== ''): ?>
        <section class="escdev-card">
            <h2>Problem</h2>
            <p class="badline"><strong><?= escdev_h($error) ?></strong></p>
        </section>
        <?php endif; ?>

        <?php if (is_array($parseResult)): ?>
        <section class="escdev-card">
            <h2>2. Parsed ride + EDXEIX IDs</h2>
            <div class="escdev-pill-list">
                <?= escdev_badge('Parser: ' . (string)($parseResult['confidence'] ?? 'unknown'), empty($parseResult['ok']) ? 'warn' : 'good') ?>
                <?= escdev_badge(!empty($mapping['ok']) ? 'IDS READY' : 'IDS NEED REVIEW', !empty($mapping['ok']) ? 'good' : 'warn') ?>
                <?= escdev_badge('AADE separate', 'good') ?>
            </div>
            <?= escdev_kv('Passenger', escdev_field($fields, 'customer_name')) ?>
            <?= escdev_kv('Driver', escdev_field($fields, 'driver_name') . ' → ' . (string)($mapping['driver_id'] ?? '')) ?>
            <?= escdev_kv('Vehicle', escdev_field($fields, 'vehicle_plate') . ' → ' . (string)($mapping['vehicle_id'] ?? '')) ?>
            <?= escdev_kv('Lessor ID', (string)($mapping['lessor_id'] ?? '')) ?>
            <?= escdev_kv('Starting point ID', (string)($mapping['starting_point_id'] ?? '')) ?>
            <?= escdev_kv('Pickup', escdev_field($fields, 'pickup_address')) ?>
            <?= escdev_kv('Drop-off', escdev_field($fields, 'dropoff_address')) ?>
            <?= escdev_kv('Pickup time', escdev_field($fields, 'pickup_datetime_local')) ?>
            <?= escdev_kv('End time', escdev_field($fields, 'end_datetime_local')) ?>
            <?= escdev_kv('Price', escdev_field($fields, 'estimated_price_amount')) ?>
            <?php foreach ((array)($mapping['warnings'] ?? []) as $warning): ?>
                <div class="escdev-blocker"><?= escdev_h((string)$warning) ?></div>
            <?php endforeach; ?>
            <?php if ($lookupError): ?><div class="escdev-blocker"><?= escdev_h($lookupError) ?></div><?php endif; ?>
        </section>
        <?php endif; ?>

        <?php if (is_array($request)): ?>
        <section class="escdev-card">
            <h2>3. Connector request preview</h2>
            <div class="escdev-pill-list">
                <?= escdev_badge('Connector: ' . (string)($request['version'] ?? 'unknown'), 'neutral') ?>
                <?= escdev_badge('DRY RUN ONLY', 'warn') ?>
                <?= escdev_badge('LIVE DISABLED', 'good') ?>
            </div>
            <?= escdev_kv('Method', (string)($request['method'] ?? '')) ?>
            <?= escdev_kv('Action host', (string)($request['action_host'] ?? '')) ?>
            <?= escdev_kv('Action path', (string)($request['action_path'] ?? '')) ?>
            <?= escdev_kv('Target preview', (string)($request['target_url_preview'] ?? '')) ?>
            <h3>Payload preview</h3>
            <pre class="escdev-code"><?= escdev_h(json_encode($request['payload_preview'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></pre>
        </section>
        <?php endif; ?>
    </div>

    <aside>
        <section class="escdev-card">
            <h2>Connector status</h2>
            <div class="escdev-note"><strong>Live submit remains disabled.</strong> This page is meant to expose the remaining connector requirements, not to submit forms.</div>
            <ul class="list">
                <li>Active EDXEIX browser/session bridge is not connected.</li>
                <li>CSRF token value is never stored here.</li>
                <li>Map coordinates require explicit operator confirmation.</li>
                <li>Andreas must explicitly approve any later live-submit switch.</li>
            </ul>
        </section>

        <section class="escdev-card">
            <h2>Sanitized capture</h2>
            <?php $capStatus = is_array($capture) ? 'AVAILABLE' : 'MISSING'; ?>
            <div class="escdev-pill-list">
                <?= escdev_badge($capStatus, is_array($capture) ? 'good' : 'warn') ?>
            </div>
            <?php if (is_array($capture)): ?>
                <?= escdev_kv('Capture ID', (string)($capture['id'] ?? '')) ?>
                <?= escdev_kv('Method', (string)($capture['form_method'] ?? '')) ?>
                <?= escdev_kv('Host', (string)($capture['action_host'] ?? '')) ?>
                <?= escdev_kv('Path', (string)($capture['action_path'] ?? '')) ?>
                <?= escdev_kv('CSRF field name', (string)($capture['csrf_field_name'] ?? '')) ?>
            <?php else: ?>
                <div class="escdev-blocker"><?= escdev_h($captureError ?: 'No capture available.') ?></div>
                <p><a class="btn" href="/ops/edxeix-submit-capture.php">Open Submit Capture</a></p>
            <?php endif; ?>
        </section>

        <?php if (is_array($preflight)): ?>
        <section class="escdev-card">
            <h2>Preflight gate</h2>
            <div class="escdev-pill-list">
                <?= escdev_badge(!empty($preflight['technical_ready']) ? 'TECHNICAL READY' : 'TECHNICAL BLOCKERS', !empty($preflight['technical_ready']) ? 'good' : 'bad') ?>
                <?= escdev_badge(!empty($preflight['live_submit_allowed']) ? 'LIVE READY' : 'LIVE BLOCKED', !empty($preflight['live_submit_allowed']) ? 'good' : 'warn') ?>
            </div>
            <h3>Live blockers</h3>
            <?php foreach ((array)($preflight['live_blockers'] ?? []) as $blocker): ?>
                <div class="escdev-blocker bad"><?= escdev_h((string)$blocker) ?></div>
            <?php endforeach; ?>
            <h3>Preflight JSON</h3>
            <pre class="escdev-code"><?= escdev_h(json_encode($preflight, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></pre>
        </section>
        <?php endif; ?>

        <?php if (is_array($request)): ?>
        <section class="escdev-card">
            <h2>Connector blockers</h2>
            <?php foreach ((array)($request['blockers'] ?? []) as $blocker): ?>
                <div class="escdev-blocker bad"><?= escdev_h((string)$blocker) ?></div>
            <?php endforeach; ?>
            <h3>Disabled submit result</h3>
            <pre class="escdev-code"><?= escdev_h(json_encode($disabledSubmitResult, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></pre>
        </section>
        <?php endif; ?>
    </aside>
</section>
<?php escdev_shell_end(); ?>
