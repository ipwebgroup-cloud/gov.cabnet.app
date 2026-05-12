<?php
/**
 * gov.cabnet.app — Mobile Submit Dev v0.1
 *
 * Mobile-first development route for the future server-side EDXEIX submit workflow.
 * This page is intentionally preview-only: it parses/looks up/reviews but never submits.
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

function msd_h(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function msd_badge(string $text, string $type = 'neutral'): string
{
    $type = in_array($type, ['good', 'warn', 'bad', 'neutral'], true) ? $type : 'neutral';
    if (function_exists('opsui_badge')) {
        return opsui_badge($text, $type);
    }
    return '<span class="badge badge-' . msd_h($type) . '">' . msd_h($text) . '</span>';
}

function msd_csrf(): string
{
    if (empty($_SESSION['mobile_submit_dev_csrf']) || !is_string($_SESSION['mobile_submit_dev_csrf'])) {
        $_SESSION['mobile_submit_dev_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['mobile_submit_dev_csrf'];
}

function msd_validate_csrf(string $token): bool
{
    return isset($_SESSION['mobile_submit_dev_csrf'])
        && is_string($_SESSION['mobile_submit_dev_csrf'])
        && hash_equals($_SESSION['mobile_submit_dev_csrf'], $token);
}

function msd_app_context(?string &$error = null): ?array
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

function msd_load_latest_server_email(): array
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
    $ctx = msd_app_context($ctxError);
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

function msd_lookup_ids(array $fields, ?string &$error = null): array
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

    $ctx = msd_app_context($error);
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

function msd_safe_field(array $fields, string $key): string
{
    return trim((string)($fields[$key] ?? ''));
}

function msd_future_status(array $fields, int $guardMinutes = 30): array
{
    $raw = msd_safe_field($fields, 'pickup_datetime_local');
    if ($raw === '') {
        return [
            'ok' => false,
            'type' => 'bad',
            'label' => 'Missing pickup datetime',
            'minutes_until_pickup' => null,
            'blocker' => 'missing_pickup_datetime',
        ];
    }

    try {
        $tz = new DateTimeZone('Europe/Athens');
        $pickup = new DateTimeImmutable($raw, $tz);
        $now = new DateTimeImmutable('now', $tz);
        $seconds = $pickup->getTimestamp() - $now->getTimestamp();
        $minutes = (int)floor($seconds / 60);
        if ($seconds <= 0) {
            return [
                'ok' => false,
                'type' => 'bad',
                'label' => 'Past or already due',
                'minutes_until_pickup' => $minutes,
                'blocker' => 'pickup_not_future',
            ];
        }
        if ($seconds < ($guardMinutes * 60)) {
            return [
                'ok' => false,
                'type' => 'warn',
                'label' => 'Future but too soon',
                'minutes_until_pickup' => $minutes,
                'blocker' => 'pickup_inside_future_guard',
            ];
        }
        return [
            'ok' => true,
            'type' => 'good',
            'label' => 'Future ride window OK',
            'minutes_until_pickup' => $minutes,
            'blocker' => '',
        ];
    } catch (Throwable) {
        return [
            'ok' => false,
            'type' => 'bad',
            'label' => 'Invalid pickup datetime',
            'minutes_until_pickup' => null,
            'blocker' => 'invalid_pickup_datetime',
        ];
    }
}

function msd_build_review(array $parseResult, array $mapping): array
{
    $fields = is_array($parseResult['fields'] ?? null) ? $parseResult['fields'] : [];
    $missing = is_array($parseResult['missing_required'] ?? null) ? $parseResult['missing_required'] : [];
    $future = msd_future_status($fields, 30);
    $blockers = [];
    $warnings = [];

    foreach ($missing as $item) {
        $blockers[] = 'missing_required_' . strtolower(preg_replace('/[^a-z0-9]+/i', '_', (string)$item));
    }

    if (!$future['ok'] && $future['blocker'] !== '') {
        $blockers[] = $future['blocker'];
    }

    if (empty($mapping['ok'])) {
        $blockers[] = 'edxeix_mapping_not_ready';
    }
    if (trim((string)($mapping['lessor_id'] ?? '')) === '') {
        $blockers[] = 'missing_edxeix_lessor_id';
    }
    if (trim((string)($mapping['driver_id'] ?? '')) === '') {
        $blockers[] = 'missing_edxeix_driver_id';
    }
    if (trim((string)($mapping['vehicle_id'] ?? '')) === '') {
        $blockers[] = 'missing_edxeix_vehicle_id';
    }

    $warnings = array_merge(
        is_array($parseResult['warnings'] ?? null) ? $parseResult['warnings'] : [],
        is_array($mapping['warnings'] ?? null) ? $mapping['warnings'] : []
    );

    $technicalReady = count($blockers) === 0;
    $submitBlockers = $blockers;
    $submitBlockers[] = 'server_side_edxeix_connector_disabled_in_dev_route';

    return [
        'technical_ready' => $technicalReady,
        'submit_enabled' => false,
        'future' => $future,
        'blockers' => array_values(array_unique($blockers)),
        'submit_blockers' => array_values(array_unique($submitBlockers)),
        'warnings' => array_values(array_unique(array_filter(array_map('strval', $warnings)))),
    ];
}

function msd_render_row(string $label, string $value, string $hint = ''): string
{
    return '<div class="kv-row"><div class="k">' . msd_h($label) . '</div><div><strong>' . msd_h($value !== '' ? $value : '-') . '</strong>' . ($hint !== '' ? '<div class="small">' . msd_h($hint) . '</div>' : '') . '</div></div>';
}

function msd_shell_begin(): void
{
    if (function_exists('opsui_shell_begin')) {
        opsui_shell_begin([
            'title' => 'Mobile Submit Dev',
            'page_title' => 'Mobile Submit Dev',
            'active_section' => 'Mobile Submit',
            'breadcrumbs' => 'Αρχική / Mobile / Submit Dev',
            'safe_notice' => 'Development route only. This page previews the future mobile submit workflow but does not submit to EDXEIX or write workflow data.',
            'force_safe_notice' => true,
        ]);
        return;
    }
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Mobile Submit Dev | gov.cabnet.app</title><style>body{font-family:Arial,Helvetica,sans-serif;background:#eef1f6;color:#20293a;margin:0;padding:18px}.card{background:#fff;border:1px solid #d8dde7;border-radius:8px;padding:16px;margin:0 0 16px}.badge{display:inline-block;padding:6px 10px;border-radius:12px;background:#e9edf7;margin:2px}.badge-good{background:#dbf0dc;color:#2d7b37}.badge-warn{background:#f8ead3;color:#9a5a00}.badge-bad{background:#f8dedd;color:#b13c35}.btn{display:inline-block;background:#4f5ea7;color:#fff;padding:11px 14px;border-radius:5px;text-decoration:none;border:0;font-weight:700}.btn.dark{background:#6b7280}textarea,input{width:100%;box-sizing:border-box;border:1px solid #d8dde7;border-radius:6px;padding:10px}.small{font-size:13px;color:#667085}.kv-row{display:grid;grid-template-columns:170px 1fr;gap:12px;border-bottom:1px solid #eef1f5;padding:10px 0}.k{font-weight:700;color:#667085}@media(max-width:760px){.kv-row{grid-template-columns:1fr}}</style></head><body>';
}

function msd_shell_end(): void
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
$review = null;
$error = '';
$mailLoad = null;
$lookupError = null;
$csrf = msd_csrf();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!msd_validate_csrf((string)($_POST['csrf'] ?? ''))) {
        $error = 'Security token expired. Please try again.';
    } else {
        $action = (string)($_POST['action'] ?? 'parse_pasted');
        if ($action === 'load_latest_server_email') {
            $mailLoad = msd_load_latest_server_email();
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
                    $mapping = msd_lookup_ids($fields, $lookupError);
                    $review = msd_build_review($parseResult, $mapping);
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

msd_shell_begin();
?>
<style>
.mobile-submit-grid{display:grid;grid-template-columns:minmax(0,1fr) minmax(320px,.7fr);gap:18px}.mobile-submit-stack{display:grid;gap:14px}.mobile-submit-card{background:#fff;border:1px solid #d8dde7;border-radius:4px;padding:18px 20px;box-shadow:0 6px 18px rgba(26,33,52,.06)}.mobile-submit-card h2{margin-top:0}.mobile-submit-textarea{min-height:260px;font-family:Arial,Helvetica,sans-serif}.mobile-actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:12px}.mobile-kv{display:grid;gap:0}.kv-row{display:grid;grid-template-columns:180px minmax(0,1fr);gap:12px;border-bottom:1px solid #eef1f5;padding:10px 0}.kv-row:last-child{border-bottom:0}.k{font-weight:700;color:#667085}.mobile-submit-disabled{opacity:.65;cursor:not-allowed}.mobile-pill-list{display:flex;gap:8px;flex-wrap:wrap;margin:8px 0}.mobile-blocker{background:#fff7ed;border:1px solid #fed7aa;border-radius:4px;padding:10px;margin:6px 0;color:#9a3412}.mobile-blocker.bad{background:#fee2e2;border-color:#fecaca;color:#991b1b}.mobile-checks{list-style:none;padding:0;margin:0}.mobile-checks li{border:1px solid #d8dde7;border-radius:4px;background:#fff;padding:10px 12px;margin:8px 0}.mobile-form-row{margin:12px 0}.mobile-form-row label{display:block;font-weight:700;margin-bottom:6px}.mobile-next{border-left:5px solid #d4922d}.mobile-submit-dev-banner{background:#eef8f0;border:1px solid #cfe4d2;border-left:6px solid #5fa865;border-radius:4px;padding:14px 18px;margin-bottom:18px}@media(max-width:980px){.mobile-submit-grid{grid-template-columns:1fr}.kv-row{grid-template-columns:1fr}.mobile-submit-card{padding:15px}.mobile-submit-textarea{min-height:220px}.mobile-actions .btn{width:100%;text-align:center}}</style>

<section class="card hero warn">
    <h1>Mobile Submit Dev</h1>
    <p>This is the first development screen for the future mobile EDXEIX submission workflow. It parses and reviews the ride, but the final submit is intentionally disabled until the server-side EDXEIX connector is built and approved.</p>
    <div>
        <?= msd_badge('MOBILE-FIRST DEV', 'warn') ?>
        <?= msd_badge('NO LIVE SUBMIT', 'good') ?>
        <?= msd_badge('NO EDXEIX CALL', 'good') ?>
        <?= msd_badge('PRODUCTION TOOL UNCHANGED', 'good') ?>
    </div>
</section>

<section class="mobile-submit-grid">
    <div class="mobile-submit-stack">
        <form class="mobile-submit-card" method="post" action="/ops/mobile-submit-dev.php" autocomplete="off">
            <h2>1. Load or paste Bolt pre-ride email</h2>
            <p class="small">Use this on mobile to prepare the future submit workflow. Today it is review-only.</p>
            <input type="hidden" name="csrf" value="<?= msd_h($csrf) ?>">
            <div class="mobile-form-row">
                <label for="email_text">Bolt pre-ride email body</label>
                <textarea class="mobile-submit-textarea" id="email_text" name="email_text" placeholder="Paste Bolt pre-ride email here..."><?= msd_h($rawEmail) ?></textarea>
            </div>
            <div class="mobile-actions">
                <button class="btn good" type="submit" name="action" value="parse_pasted">Parse + Review</button>
                <button class="btn warn" type="submit" name="action" value="load_latest_server_email">Load latest server email</button>
                <a class="btn dark" href="/ops/mobile-submit-dev.php">Clear</a>
            </div>
            <?php if (is_array($mailLoad) && !empty($mailLoad['ok'])): ?>
                <p class="goodline"><strong>Loaded:</strong> <?= msd_h((string)($mailLoad['source'] ?? '')) ?> <?= !empty($mailLoad['source_mtime']) ? '(' . msd_h((string)$mailLoad['source_mtime']) . ')' : '' ?></p>
            <?php endif; ?>
        </form>

        <?php if ($error !== ''): ?>
            <section class="mobile-submit-card">
                <h2>Problem</h2>
                <p class="badline"><strong><?= msd_h($error) ?></strong></p>
            </section>
        <?php endif; ?>

        <?php if (is_array($parseResult)): ?>
            <section class="mobile-submit-card">
                <h2>2. Parsed ride details</h2>
                <div class="mobile-pill-list">
                    <?= msd_badge('Parser: ' . (string)($parseResult['confidence'] ?? 'unknown'), empty($parseResult['ok']) ? 'warn' : 'good') ?>
                    <?= msd_badge('Source: pre-ride email', 'neutral') ?>
                    <?= msd_badge('AADE not involved', 'good') ?>
                </div>
                <div class="mobile-kv">
                    <?= msd_render_row('Passenger', msd_safe_field($fields, 'customer_name'), msd_safe_field($fields, 'customer_phone')) ?>
                    <?= msd_render_row('Driver', msd_safe_field($fields, 'driver_name')) ?>
                    <?= msd_render_row('Vehicle', msd_safe_field($fields, 'vehicle_plate')) ?>
                    <?= msd_render_row('Pickup', msd_safe_field($fields, 'pickup_address')) ?>
                    <?= msd_render_row('Drop-off', msd_safe_field($fields, 'dropoff_address')) ?>
                    <?= msd_render_row('Pickup time', msd_safe_field($fields, 'pickup_datetime_local'), msd_safe_field($fields, 'pickup_timezone')) ?>
                    <?= msd_render_row('Estimated end', msd_safe_field($fields, 'end_datetime_local'), msd_safe_field($fields, 'end_timezone')) ?>
                    <?= msd_render_row('Price', msd_safe_field($fields, 'estimated_price_amount'), msd_safe_field($fields, 'estimated_price_text')) ?>
                    <?= msd_render_row('Order reference', msd_safe_field($fields, 'order_reference')) ?>
                </div>
            </section>

            <section class="mobile-submit-card">
                <h2>3. EDXEIX ID readiness</h2>
                <div class="mobile-pill-list">
                    <?= msd_badge(!empty($mapping['ok']) ? 'IDs READY' : 'IDS NEED REVIEW', !empty($mapping['ok']) ? 'good' : 'warn') ?>
                    <?= msd_badge('Read-only lookup', 'good') ?>
                </div>
                <div class="mobile-kv">
                    <?= msd_render_row('Lessor ID', (string)($mapping['lessor_id'] ?? ''), (string)($mapping['lessor_source'] ?? '')) ?>
                    <?= msd_render_row('Driver ID', (string)($mapping['driver_id'] ?? ''), (string)($mapping['driver_label'] ?? '')) ?>
                    <?= msd_render_row('Vehicle ID', (string)($mapping['vehicle_id'] ?? ''), (string)($mapping['vehicle_label'] ?? '')) ?>
                    <?= msd_render_row('Starting point ID', (string)($mapping['starting_point_id'] ?? ''), (string)($mapping['starting_point_label'] ?? '')) ?>
                </div>
                <?php foreach ((array)($mapping['messages'] ?? []) as $msg): ?>
                    <div class="goodline">✓ <?= msd_h((string)$msg) ?></div>
                <?php endforeach; ?>
                <?php foreach ((array)($mapping['warnings'] ?? []) as $msg): ?>
                    <div class="warnline">⚠ <?= msd_h((string)$msg) ?></div>
                <?php endforeach; ?>
                <?php if ($lookupError): ?>
                    <div class="warnline">Lookup note: <?= msd_h($lookupError) ?></div>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </div>

    <aside class="mobile-submit-stack">
        <section class="mobile-submit-card mobile-next">
            <h2>Target workflow</h2>
            <ol class="list">
                <li>Mobile operator loads pre-ride email.</li>
                <li>System parses and resolves EDXEIX IDs.</li>
                <li>Operator confirms map point and future ride safety.</li>
                <li>Server-side connector submits to EDXEIX only after all gates pass.</li>
                <li>Audit log records who submitted, when, and why it was allowed.</li>
            </ol>
        </section>

        <?php if (is_array($review)): ?>
            <section class="mobile-submit-card">
                <h2>4. Submit gate preview</h2>
                <div class="mobile-pill-list">
                    <?= msd_badge($review['future']['label'] ?? 'Unknown future status', (string)($review['future']['type'] ?? 'neutral')) ?>
                    <?= msd_badge(!empty($review['technical_ready']) ? 'TECHNICAL REVIEW OK' : 'BLOCKERS PRESENT', !empty($review['technical_ready']) ? 'good' : 'bad') ?>
                    <?= msd_badge('SUBMIT DISABLED', 'warn') ?>
                </div>
                <?php if (($review['future']['minutes_until_pickup'] ?? null) !== null): ?>
                    <p class="small">Minutes until pickup: <strong><?= msd_h((string)$review['future']['minutes_until_pickup']) ?></strong></p>
                <?php endif; ?>

                <h3>Current blockers</h3>
                <?php foreach ((array)$review['submit_blockers'] as $blocker): ?>
                    <div class="mobile-blocker <?= $blocker === 'server_side_edxeix_connector_disabled_in_dev_route' ? '' : 'bad' ?>"><?= msd_h((string)$blocker) ?></div>
                <?php endforeach; ?>

                <?php if (!empty($review['warnings'])): ?>
                    <h3>Warnings</h3>
                    <?php foreach ((array)$review['warnings'] as $warning): ?>
                        <div class="mobile-blocker"><?= msd_h((string)$warning) ?></div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <div class="mobile-form-row">
                    <label><input type="checkbox" disabled> I confirm this is a real eligible future ride.</label>
                </div>
                <div class="mobile-form-row">
                    <label><input type="checkbox" disabled> I confirmed the exact pickup map point.</label>
                </div>
                <button class="btn warn mobile-submit-disabled" type="button" disabled>Submit to EDXEIX disabled in dev route</button>
            </section>
        <?php else: ?>
            <section class="mobile-submit-card">
                <h2>Submit gate preview</h2>
                <p>Parse a pre-ride email to see future/past safety, mapping readiness, and the disabled submit gate.</p>
            </section>
        <?php endif; ?>

        <section class="mobile-submit-card">
            <h2>Non-negotiable safety rules</h2>
            <ul class="mobile-checks">
                <li><?= msd_badge('BLOCK', 'bad') ?> Old, past, completed, cancelled, expired, terminal, duplicate, unmapped, or invalid rides.</li>
                <li><?= msd_badge('REQUIRE', 'warn') ?> Lessor/driver/vehicle IDs from EDXEIX mapping, not only Bolt operator text.</li>
                <li><?= msd_badge('REQUIRE', 'warn') ?> Explicit operator confirmation and map point confirmation.</li>
                <li><?= msd_badge('SEPARATE', 'good') ?> AADE receipt issuing remains separate from pre-ride workflow.</li>
            </ul>
        </section>
    </aside>
</section>
<?php
msd_shell_end();
