<?php
/**
 * gov.cabnet.app — Mobile Pre-Ride Review
 *
 * Mobile-friendly, read-only review page for Bolt pre-ride email parsing.
 * Does not call Bolt, does not call EDXEIX, does not call AADE, and does not write data.
 */

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Robots-Tag: noindex,nofollow', true);

require_once __DIR__ . '/_shell.php';

function pmr_private_app_path(string $relative): string
{
    return dirname(__DIR__, 3) . '/gov.cabnet.app_app/' . ltrim($relative, '/');
}

function pmr_include_if_exists(string $file): bool
{
    if (is_file($file)) {
        require_once $file;
        return true;
    }
    return false;
}

function pmr_bootstrap_path(): string
{
    return pmr_private_app_path('src/bootstrap.php');
}

function pmr_context(?string &$error = null): ?array
{
    static $ctx = null;
    static $loaded = false;
    static $loadError = null;

    if ($loaded) {
        $error = $loadError;
        return is_array($ctx) ? $ctx : null;
    }

    $loaded = true;
    $bootstrap = pmr_bootstrap_path();
    if (!is_file($bootstrap)) {
        $loadError = 'Private app bootstrap not found.';
        $error = $loadError;
        return null;
    }

    try {
        $ctx = require $bootstrap;
        if (!is_array($ctx)) {
            $loadError = 'Private app bootstrap did not return context.';
            $error = $loadError;
            return null;
        }
        $error = null;
        return $ctx;
    } catch (Throwable $e) {
        $loadError = $e->getMessage();
        $error = $loadError;
        return null;
    }
}

function pmr_csrf_token(): string
{
    if (empty($_SESSION['pmr_csrf']) || !is_string($_SESSION['pmr_csrf'])) {
        $_SESSION['pmr_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['pmr_csrf'];
}

function pmr_csrf_valid(string $token): bool
{
    return isset($_SESSION['pmr_csrf']) && is_string($_SESSION['pmr_csrf']) && hash_equals($_SESSION['pmr_csrf'], $token);
}

function pmr_load_latest_server_email(): array
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
    $ctx = pmr_context($ctxError);
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

    $loader = new Bridge\BoltMail\MaildirPreRideEmailLoader();
    return $loader->loadLatest(array_values(array_unique($extraDirs)));
}

function pmr_lookup_edxeix_ids(array $fields, ?string &$error = null): array
{
    $empty = [
        'ok' => false,
        'lessor_id' => '',
        'driver_id' => '',
        'vehicle_id' => '',
        'starting_point_id' => '',
        'messages' => [],
        'warnings' => ['DB mapping lookup was not available.'],
    ];

    if (!class_exists('Bridge\\BoltMail\\EdxeixMappingLookup')) {
        $error = 'EdxeixMappingLookup class is not installed.';
        return $empty;
    }

    $ctx = pmr_context($error);
    if (!$ctx || !isset($ctx['db']) || !method_exists($ctx['db'], 'connection')) {
        return $empty;
    }

    try {
        $lookup = new Bridge\BoltMail\EdxeixMappingLookup($ctx['db']->connection());
        $result = $lookup->lookup($fields);
        $error = null;
        return is_array($result) ? $result : $empty;
    } catch (Throwable $e) {
        $error = $e->getMessage();
        $empty['warnings'] = ['DB mapping lookup failed: ' . $error];
        return $empty;
    }
}

function pmr_future_status(string $localDatetime): array
{
    $localDatetime = trim($localDatetime);
    if ($localDatetime === '') {
        return ['status' => 'unknown', 'label' => 'Unknown', 'message' => 'Pickup datetime was not parsed.', 'badge' => 'warn'];
    }

    try {
        $tz = new DateTimeZone('Europe/Athens');
        $ride = new DateTimeImmutable($localDatetime, $tz);
        $now = new DateTimeImmutable('now', $tz);
        $diff = $ride->getTimestamp() - $now->getTimestamp();

        if ($diff <= 0) {
            return ['status' => 'past', 'label' => 'Past/old', 'message' => 'This parsed ride time is not in the future. Do not submit to EDXEIX.', 'badge' => 'bad'];
        }
        if ($diff < 30 * 60) {
            return ['status' => 'soon', 'label' => 'Too soon', 'message' => 'Ride is in the future but inside the 30-minute safety window. Review only.', 'badge' => 'warn'];
        }
        return ['status' => 'future', 'label' => 'Future', 'message' => 'Ride appears future-dated. Use desktop/laptop workflow for actual EDXEIX entry.', 'badge' => 'good'];
    } catch (Throwable) {
        return ['status' => 'unknown', 'label' => 'Unknown', 'message' => 'Could not evaluate the parsed pickup datetime.', 'badge' => 'warn'];
    }
}

function pmr_field(array $fields, string $key): string
{
    return trim((string)($fields[$key] ?? ''));
}

function pmr_mobile_field(string $label, string $value, bool $full = false): string
{
    return '<div class="gov-mobile-field' . ($full ? ' full' : '') . '"><label>' . opsui_h($label) . '</label><div>' . opsui_h($value !== '' ? $value : '—') . '</div></div>';
}

$parserFile = pmr_private_app_path('src/BoltMail/BoltPreRideEmailParser.php');
$lookupFile = pmr_private_app_path('src/BoltMail/EdxeixMappingLookup.php');
$mailLoaderFile = pmr_private_app_path('src/BoltMail/MaildirPreRideEmailLoader.php');
pmr_include_if_exists($parserFile);
pmr_include_if_exists($lookupFile);
pmr_include_if_exists($mailLoaderFile);

$rawEmail = '';
$result = null;
$error = '';
$mailLoad = null;
$mailLoadError = '';
$dbLookupError = '';
$mapping = [
    'ok' => false,
    'lessor_id' => '',
    'driver_id' => '',
    'vehicle_id' => '',
    'starting_point_id' => '',
    'messages' => [],
    'warnings' => [],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? 'parse_pasted');
    if (!pmr_csrf_valid((string)($_POST['csrf'] ?? ''))) {
        $error = 'Security token expired. Please reload and try again.';
    } elseif ($action === 'load_latest_server_email') {
        $mailLoad = pmr_load_latest_server_email();
        if (!empty($mailLoad['ok'])) {
            $rawEmail = (string)$mailLoad['email_text'];
        } else {
            $mailLoadError = (string)($mailLoad['error'] ?? 'Unable to load latest server email.');
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
                $parser = new Bridge\BoltMail\BoltPreRideEmailParser();
                $result = $parser->parse($rawEmail);
                $fieldsForLookup = is_array($result) ? (array)($result['fields'] ?? []) : [];
                if ($fieldsForLookup) {
                    $mapping = pmr_lookup_edxeix_ids($fieldsForLookup, $dbLookupError);
                }
            } catch (Throwable $e) {
                $error = $e->getMessage();
            }
        }
    } elseif ($error === '' && $mailLoadError === '') {
        $error = 'No email text was provided.';
    }
}

$csrf = pmr_csrf_token();
$fields = is_array($result) ? (array)($result['fields'] ?? []) : [];
$missing = is_array($result) ? (array)($result['missing_required'] ?? []) : [];
$warnings = is_array($result) ? (array)($result['warnings'] ?? []) : [];
$generated = is_array($result) ? (array)($result['generated'] ?? []) : [];
$confidence = is_array($result) ? (string)($result['confidence'] ?? 'not parsed') : 'not parsed';
$future = pmr_future_status(pmr_field($fields, 'pickup_datetime_local'));
$idsReady = !empty($mapping['ok']);

opsui_shell_begin([
    'title' => 'Mobile Pre-Ride Review',
    'page_title' => 'Mobile pre-ride review',
    'active_section' => 'Mobile review',
    'subtitle' => 'Mobile-friendly review/check page for Bolt pre-ride emails',
    'breadcrumbs' => 'Αρχική / Pre-Ride / Mobile review',
    'safe_notice' => 'Read-only mobile review page. It parses email text and performs read-only DB ID lookup only. It does not call Bolt, EDXEIX, or AADE, and does not write data.',
    'force_safe_notice' => true,
]);
?>
<section class="card hero neutral">
    <h1>Mobile pre-ride review</h1>
    <p>This page is for checking a Bolt pre-ride email from a phone or tablet. It is not the approved EDXEIX fill/save workflow. Actual EDXEIX form fill and save must remain on desktop/laptop Firefox with both helpers loaded.</p>
    <div>
        <?= opsui_badge('READ ONLY', 'good') ?>
        <?= opsui_badge('NO EDXEIX SUBMIT', 'good') ?>
        <?= opsui_badge('MOBILE REVIEW ONLY', 'warn') ?>
        <?= opsui_badge('DESKTOP REQUIRED FOR SAVE', 'bad') ?>
    </div>
</section>

<section class="card gov-mobile-review-form">
    <h2>Load or paste pre-ride email</h2>
    <p>Use this from mobile only to verify data and readiness. Do not use mobile for the EDXEIX POST/save step.</p>
    <?php if ($error !== ''): ?><?= opsui_flash($error, 'bad') ?><?php endif; ?>
    <?php if ($mailLoadError !== ''): ?><?= opsui_flash($mailLoadError, 'warn') ?><?php endif; ?>
    <?php if (is_array($mailLoad) && !empty($mailLoad['ok'])): ?>
        <?= opsui_flash('Loaded latest server email: ' . (string)($mailLoad['source'] ?? ''), 'good') ?>
    <?php endif; ?>
    <form method="post" action="/ops/pre-ride-mobile-review.php" autocomplete="off">
        <input type="hidden" name="csrf" value="<?= opsui_h($csrf) ?>">
        <textarea name="email_text" placeholder="Paste Bolt pre-ride email here...\n\nOr use Load latest server email if available."><?= opsui_h($rawEmail) ?></textarea>
        <div class="actions">
            <button class="btn good" type="submit" name="action" value="parse_pasted">Parse for mobile review</button>
            <button class="btn warn" type="submit" name="action" value="load_latest_server_email">Load latest server email</button>
            <a class="btn dark" href="/ops/pre-ride-mobile-review.php">Clear</a>
        </div>
    </form>
</section>

<?php if (is_array($result)): ?>
<section class="card">
    <h2>Review status</h2>
    <div class="gov-mobile-review-status">
        <div class="gov-mobile-review-card <?= empty($missing) ? 'good' : 'warn' ?>"><strong><?= opsui_h(strtoupper($confidence)) ?></strong><span>Parser confidence</span></div>
        <div class="gov-mobile-review-card <?= $idsReady ? 'good' : 'warn' ?>"><strong><?= $idsReady ? 'IDS READY' : 'CHECK IDS' ?></strong><span>Read-only EDXEIX ID lookup</span></div>
        <div class="gov-mobile-review-card <?= opsui_h((string)$future['badge']) ?>"><strong><?= opsui_h((string)$future['label']) ?></strong><span><?= opsui_h((string)$future['message']) ?></span></div>
        <div class="gov-mobile-review-card warn"><strong>REVIEW ONLY</strong><span>Move to desktop/laptop for the actual EDXEIX form save.</span></div>
    </div>

    <?php if (!empty($missing)): ?>
        <h3>Missing required fields</h3>
        <ul class="list"><?php foreach ($missing as $item): ?><li class="warnline"><?= opsui_h((string)$item) ?></li><?php endforeach; ?></ul>
    <?php endif; ?>
    <?php if (!empty($warnings)): ?>
        <h3>Parser warnings</h3>
        <ul class="list"><?php foreach ($warnings as $item): ?><li class="warnline"><?= opsui_h((string)$item) ?></li><?php endforeach; ?></ul>
    <?php endif; ?>
    <?php foreach ((array)($mapping['warnings'] ?? []) as $item): ?>
        <?= opsui_flash((string)$item, 'warn') ?>
    <?php endforeach; ?>
    <?php if ($dbLookupError !== ''): ?><?= opsui_flash($dbLookupError, 'warn') ?><?php endif; ?>
</section>

<section class="card">
    <h2>Parsed ride details</h2>
    <div class="gov-mobile-field-grid">
        <?= pmr_mobile_field('Passenger', pmr_field($fields, 'customer_name')) ?>
        <?= pmr_mobile_field('Mobile', pmr_field($fields, 'customer_phone')) ?>
        <?= pmr_mobile_field('Driver', pmr_field($fields, 'driver_name')) ?>
        <?= pmr_mobile_field('Vehicle', pmr_field($fields, 'vehicle_plate')) ?>
        <?= pmr_mobile_field('Pickup date/time', pmr_field($fields, 'pickup_datetime_local')) ?>
        <?= pmr_mobile_field('End date/time', pmr_field($fields, 'end_datetime_local')) ?>
        <?= pmr_mobile_field('Price', pmr_field($fields, 'estimated_price_amount') . (pmr_field($fields, 'estimated_price_currency') !== '' ? ' ' . pmr_field($fields, 'estimated_price_currency') : '')) ?>
        <?= pmr_mobile_field('Order reference', pmr_field($fields, 'order_reference')) ?>
        <?= pmr_mobile_field('Pickup', pmr_field($fields, 'pickup_address'), true) ?>
        <?= pmr_mobile_field('Drop-off', pmr_field($fields, 'dropoff_address'), true) ?>
    </div>
</section>

<section class="two">
    <div class="card">
        <h2>EDXEIX IDs from mapping</h2>
        <div class="kv">
            <div class="k">Company / lessor</div><div><?= opsui_h((string)($mapping['lessor_id'] ?? '')) ?: '—' ?></div>
            <div class="k">Driver ID</div><div><?= opsui_h((string)($mapping['driver_id'] ?? '')) ?: '—' ?></div>
            <div class="k">Vehicle ID</div><div><?= opsui_h((string)($mapping['vehicle_id'] ?? '')) ?: '—' ?></div>
            <div class="k">Starting point ID</div><div><?= opsui_h((string)($mapping['starting_point_id'] ?? '')) ?: '—' ?></div>
        </div>
        <?php foreach ((array)($mapping['messages'] ?? []) as $msg): ?>
            <p class="goodline">✓ <?= opsui_h((string)$msg) ?></p>
        <?php endforeach; ?>
    </div>
    <div class="card">
        <h2>Dispatch summary</h2>
        <div class="gov-mobile-summary-box"><?= opsui_h((string)($generated['dispatch_summary'] ?? '')) ?></div>
    </div>
</section>

<section class="card">
    <h2>What to do next</h2>
    <?php if (empty($missing) && $idsReady && ($future['status'] ?? '') === 'future'): ?>
        <p class="goodline"><strong>Mobile review looks ready.</strong> Continue on desktop/laptop Firefox for the actual EDXEIX form fill, visible field verification, exact map selection, and save.</p>
    <?php else: ?>
        <p class="warnline"><strong>Do not continue to EDXEIX save yet.</strong> Resolve missing fields, mapping warnings, or time safety warnings first.</p>
    <?php endif; ?>
    <div class="actions">
        <a class="btn" href="/ops/pre-ride-email-tool.php">Open Production Pre-Ride Tool</a>
        <a class="btn warn" href="/ops/pre-ride-email-toolv2.php">Open V2 Dev Tool</a>
        <a class="btn dark" href="/ops/mobile-compatibility.php">Mobile Compatibility Rule</a>
    </div>
</section>
<?php endif; ?>
<?php opsui_shell_end(); ?>
