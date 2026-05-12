<?php
/**
 * gov.cabnet.app — Mobile Submit Scenario Tester
 *
 * Read-only synthetic scenario runner for the future mobile/server-side EDXEIX submit workflow.
 * It generates safe TEST-ONLY Bolt pre-ride email bodies, parses them, resolves mappings,
 * runs the preflight gate, builds the disabled connector preview, and validates the dry-run payload.
 *
 * Safety contract:
 * - Does not call Bolt.
 * - Does not call EDXEIX.
 * - Does not call AADE.
 * - Does not write database rows.
 * - Does not stage jobs.
 * - Does not enable live submission.
 * - Synthetic emails are TEST ONLY and must never be posted to EDXEIX.
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
$files = [
    'parser' => $root . '/gov.cabnet.app_app/src/BoltMail/BoltPreRideEmailParser.php',
    'lookup' => $root . '/gov.cabnet.app_app/src/BoltMail/EdxeixMappingLookup.php',
    'preflight' => $root . '/gov.cabnet.app_app/src/Edxeix/EdxeixSubmitPreflightGate.php',
    'connector' => $root . '/gov.cabnet.app_app/src/Edxeix/EdxeixSubmitConnector.php',
    'validator' => $root . '/gov.cabnet.app_app/src/Edxeix/EdxeixSubmitPayloadValidator.php',
];
foreach ($files as $file) {
    if (is_file($file)) {
        require_once $file;
    }
}

use Bridge\BoltMail\BoltPreRideEmailParser;
use Bridge\BoltMail\EdxeixMappingLookup;
use Bridge\Edxeix\EdxeixSubmitPreflightGate;
use Bridge\Edxeix\EdxeixSubmitConnector;
use Bridge\Edxeix\EdxeixSubmitPayloadValidator;

function mss_h(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function mss_badge(string $text, string $type = 'neutral'): string
{
    $type = in_array($type, ['good', 'warn', 'bad', 'neutral'], true) ? $type : 'neutral';
    if (function_exists('opsui_badge')) {
        return opsui_badge($text, $type);
    }
    return '<span class="badge badge-' . mss_h($type) . '">' . mss_h($text) . '</span>';
}

function mss_shell_begin(): void
{
    if (function_exists('opsui_shell_begin')) {
        opsui_shell_begin([
            'title' => 'Mobile Submit Scenarios',
            'page_title' => 'Mobile Submit Scenarios',
            'active_section' => 'Mobile Submit',
            'breadcrumbs' => 'Αρχική / Mobile / Submit Scenarios',
            'safe_notice' => 'Read-only synthetic scenario tester. It does not call EDXEIX and synthetic rides must never be posted.',
            'force_safe_notice' => true,
        ]);
        return;
    }
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Mobile Submit Scenarios</title><style>body{font-family:Arial,Helvetica,sans-serif;background:#eef1f6;color:#101828;margin:0;padding:18px}.card{background:#fff;border:1px solid #d8dde7;border-radius:8px;padding:16px;margin:0 0 16px}.btn{display:inline-block;background:#4f5ea7;color:#fff;padding:10px 14px;border-radius:6px;border:0;text-decoration:none;font-weight:700}.badge{display:inline-block;padding:5px 9px;border-radius:999px;background:#e5e7eb;margin:2px}.badge-good{background:#dcfce7;color:#166534}.badge-warn{background:#fff7ed;color:#b45309}.badge-bad{background:#fee2e2;color:#991b1b}.grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}.table-wrap{overflow:auto}table{width:100%;border-collapse:collapse}td,th{border-bottom:1px solid #e5e7eb;padding:8px;text-align:left}textarea{width:100%;min-height:220px}</style></head><body>';
}

function mss_shell_end(): void
{
    if (function_exists('opsui_shell_end')) {
        opsui_shell_end();
        return;
    }
    echo '</body></html>';
}

function mss_csrf(): string
{
    if (empty($_SESSION['mobile_submit_scenarios_csrf']) || !is_string($_SESSION['mobile_submit_scenarios_csrf'])) {
        $_SESSION['mobile_submit_scenarios_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['mobile_submit_scenarios_csrf'];
}

function mss_valid_csrf(string $token): bool
{
    return isset($_SESSION['mobile_submit_scenarios_csrf'])
        && is_string($_SESSION['mobile_submit_scenarios_csrf'])
        && hash_equals($_SESSION['mobile_submit_scenarios_csrf'], $token);
}

/** @return array<string,array<string,string>> */
function mss_scenarios(): array
{
    return [
        'whiteblue_tsatsas_xzo1837' => [
            'label' => 'WHITEBLUE — Georgios Tsatsas / XZO1837',
            'operator' => 'WHITEBLUE PREMIUM E E',
            'customer' => 'TEST ONLY Mobile Scenario Whiteblue',
            'phone' => '+306900000000',
            'driver' => 'Georgios Tsatsas',
            'vehicle' => 'XZO1837',
            'pickup' => 'Ομβροδέκτης, Κοινότητα Μυκόνου, Mykonos 84600, Greece',
            'dropoff' => 'Mykonos Airport, Mikonos 846 00, Greece',
            'price' => '40.00 - 44.00 eur',
            'expected' => 'lessor 1756 / driver 4382 / vehicle 4327 / starting point 612164',
        ],
        'luxlimo_filippos_eha2545' => [
            'label' => 'LUXLIMO — Filippos Giannakopoulos / EHA2545',
            'operator' => 'Fleet Mykonos LUXLIMO Ι Κ Ε||MYKONOS CAB',
            'customer' => 'TEST ONLY Mobile Scenario Luxlimo',
            'phone' => '+306900000001',
            'driver' => 'Filippos Giannakopoulos',
            'vehicle' => 'EHA2545',
            'pickup' => 'Mikonos 846 00, Greece',
            'dropoff' => 'Mykonos Port, Mikonos 846 00, Greece',
            'price' => '60.00 eur',
            'expected' => 'lessor 3814 / driver 17585 / vehicle 5949 / starting point requires active lessor override or safe fallback warning',
        ],
        'qualitative_kaci_xht8172' => [
            'label' => 'QUALITATIVE — Stefanos Kaci / XHT8172',
            'operator' => 'QUALITATIVE TRANSFER MYKONOS ΙΚ Ε',
            'customer' => 'TEST ONLY Mobile Scenario Qualitative',
            'phone' => '+306900000002',
            'driver' => 'Stefanos Kaci',
            'vehicle' => 'XHT8172',
            'pickup' => 'ΧΩΡΑ ΜΥΚΟΝΟΥ, Mykonos 846 00, Greece',
            'dropoff' => 'Mykonos Airport, Mikonos 846 00, Greece',
            'price' => '40.00 eur',
            'expected' => 'lessor 2307 / driver 20999 / vehicle 13868 / starting point requires active lessor override or safe fallback warning',
        ],
        'mta_lampros_xhi9499' => [
            'label' => 'MTA — Lampros Kanellos / XHI9499',
            'operator' => 'MYKONOS TOURIST AGENCY',
            'customer' => 'TEST ONLY Mobile Scenario MTA',
            'phone' => '+306900000003',
            'driver' => 'Lampros Kanellos',
            'vehicle' => 'XHI9499',
            'pickup' => 'Mikonos 846 00, Greece',
            'dropoff' => 'Ornos, Mykonos 846 00, Greece',
            'price' => '55.00 eur',
            'expected' => 'lessor 3894 / driver 21657 / vehicle 9048 / starting point requires active lessor override or safe fallback warning',
        ],
    ];
}

/** @param array<string,string> $scenario */
function mss_generate_email(array $scenario): string
{
    $tz = new DateTimeZone('Europe/Athens');
    $start = new DateTimeImmutable('+90 minutes', $tz);
    $pickup = $start->modify('+2 minutes');
    $end = $pickup->modify('+28 minutes');

    return "TEST ONLY — DO NOT POST TO EDXEIX\n"
        . "Operator: " . ($scenario['operator'] ?? '') . "\n\n"
        . "Customer: " . ($scenario['customer'] ?? '') . "\n"
        . "Customer mobile: " . ($scenario['phone'] ?? '') . "\n\n"
        . "Driver: " . ($scenario['driver'] ?? '') . "\n"
        . "Vehicle: " . ($scenario['vehicle'] ?? '') . "\n\n"
        . "Pickup: " . ($scenario['pickup'] ?? '') . "\n"
        . "Drop-off: " . ($scenario['dropoff'] ?? '') . "\n\n"
        . "Start time: " . $start->format('Y-m-d H:i:s') . " EEST\n"
        . "Estimated pick-up time: " . $pickup->format('Y-m-d H:i:s') . " EEST\n"
        . "Estimated end time: " . $end->format('Y-m-d H:i:s') . " EEST\n\n"
        . "Estimated price: " . ($scenario['price'] ?? '40.00 eur') . "\n"
        . "Order reference: TEST-MOBILE-SCENARIO-" . $start->format('YmdHis') . "\n";
}

function mss_app_context(?string &$error = null): ?array
{
    static $loaded = false;
    static $ctx = null;
    static $ctxError = null;
    global $root;
    if ($loaded) {
        $error = $ctxError;
        return is_array($ctx) ? $ctx : null;
    }
    $loaded = true;
    $bootstrap = $root . '/gov.cabnet.app_app/src/bootstrap.php';
    if (!is_file($bootstrap)) {
        $ctxError = 'Private app bootstrap not found.';
        $error = $ctxError;
        return null;
    }
    try {
        $ctx = require $bootstrap;
        if (!is_array($ctx)) {
            throw new RuntimeException('Private app bootstrap did not return an array.');
        }
        $error = null;
        return $ctx;
    } catch (Throwable $e) {
        $ctxError = $e->getMessage();
        $error = $ctxError;
        return null;
    }
}

function mss_db(?string &$error = null): ?mysqli
{
    $ctx = mss_app_context($error);
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

function mss_table_exists(mysqli $db, string $table): bool
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
function mss_latest_capture(mysqli $db): ?array
{
    if (!mss_table_exists($db, 'ops_edxeix_submit_captures')) {
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
function mss_lookup_ids(array $fields, ?string &$error = null): array
{
    $empty = [
        'ok' => false,
        'lessor_id' => '',
        'driver_id' => '',
        'vehicle_id' => '',
        'starting_point_id' => '',
        'messages' => [],
        'warnings' => ['Lookup unavailable.'],
    ];
    if (!class_exists(EdxeixMappingLookup::class)) {
        $error = 'EdxeixMappingLookup class is unavailable.';
        return $empty;
    }
    $db = mss_db($error);
    if (!$db) {
        return $empty;
    }
    try {
        $lookup = new EdxeixMappingLookup($db);
        $result = $lookup->lookup($fields);
        $error = null;
        return is_array($result) ? $result : $empty;
    } catch (Throwable $e) {
        $error = $e->getMessage();
        $empty['warnings'] = ['Lookup failed: ' . $error];
        return $empty;
    }
}

/** @return array<string,mixed>|null */
function mss_lessor_starting_point(mysqli $db, string $lessorId): ?array
{
    if ($lessorId === '' || !mss_table_exists($db, 'mapping_lessor_starting_points')) {
        return null;
    }
    try {
        $stmt = $db->prepare('SELECT * FROM mapping_lessor_starting_points WHERE edxeix_lessor_id = ? AND is_active = 1 ORDER BY id ASC LIMIT 1');
        $stmt->bind_param('s', $lessorId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        return is_array($row) ? $row : null;
    } catch (Throwable) {
        return null;
    }
}

function mss_json(mixed $data): string
{
    return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
}

function mss_row(string $label, mixed $value, string $type = 'neutral'): string
{
    $text = trim((string)$value);
    return '<tr><td><strong>' . mss_h($label) . '</strong></td><td>' . ($text !== '' ? mss_h($text) : '-') . '</td><td>' . mss_badge($text !== '' ? 'PRESENT' : 'MISSING', $text !== '' ? $type : 'warn') . '</td></tr>';
}

$scenarios = mss_scenarios();
$csrf = mss_csrf();
$selectedKey = (string)($_POST['scenario'] ?? $_GET['scenario'] ?? 'whiteblue_tsatsas_xzo1837');
if (!isset($scenarios[$selectedKey])) {
    $selectedKey = 'whiteblue_tsatsas_xzo1837';
}
$action = (string)($_POST['action'] ?? '');
$emailText = '';
$error = '';
$parse = null;
$fields = [];
$mapping = null;
$capture = null;
$preflight = null;
$request = null;
$validation = null;
$lookupError = null;
$dbError = null;
$startingOverride = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!mss_valid_csrf((string)($_POST['csrf'] ?? ''))) {
        $error = 'Security token expired. Please reload and try again.';
    } elseif ($action === 'run_scenario') {
        $emailText = mss_generate_email($scenarios[$selectedKey]);
    } else {
        $emailText = (string)($_POST['email_text'] ?? '');
    }

    if ($error === '' && trim($emailText) !== '') {
        if (!class_exists(BoltPreRideEmailParser::class)) {
            $error = 'BoltPreRideEmailParser class is unavailable.';
        } else {
            try {
                $parser = new BoltPreRideEmailParser();
                $parse = $parser->parse($emailText);
                $fields = is_array($parse['fields'] ?? null) ? $parse['fields'] : [];
                $mapping = mss_lookup_ids($fields, $lookupError);
                $db = mss_db($dbError);
                $capture = $db ? mss_latest_capture($db) : null;
                $startingOverride = ($db && is_array($mapping)) ? mss_lessor_starting_point($db, trim((string)($mapping['lessor_id'] ?? ''))) : null;

                if (class_exists(EdxeixSubmitPreflightGate::class)) {
                    $gate = new EdxeixSubmitPreflightGate();
                    $preflight = $gate->evaluate($fields, is_array($mapping) ? $mapping : [], $capture, [
                        'future_guard_minutes' => 30,
                        'timezone' => 'Europe/Athens',
                        'live_connector_enabled' => false,
                        'operator_final_confirmed' => false,
                        'map_point_confirmed' => false,
                    ]);
                }

                if (class_exists(EdxeixSubmitConnector::class)) {
                    $connector = new EdxeixSubmitConnector();
                    $request = $connector->buildRequest($fields, is_array($mapping) ? $mapping : [], $capture);
                }

                if (class_exists(EdxeixSubmitPayloadValidator::class) && is_array($request)) {
                    $validator = new EdxeixSubmitPayloadValidator();
                    $validation = $validator->validate($request, $capture, $preflight);
                }
            } catch (Throwable $e) {
                $error = $e->getMessage();
            }
        }
    } elseif ($error === '') {
        $emailText = mss_generate_email($scenarios[$selectedKey]);
    }
} else {
    $emailText = mss_generate_email($scenarios[$selectedKey]);
}

mss_shell_begin();
?>
<style>
.mss-grid{display:grid;grid-template-columns:minmax(0,1fr) minmax(320px,.72fr);gap:18px}.mss-card{background:#fff;border:1px solid #d8dde7;border-radius:4px;padding:18px;margin-bottom:18px;box-shadow:0 6px 18px rgba(26,33,52,.05)}.mss-card h2{margin-top:0}.mss-textarea{width:100%;min-height:280px;border:1px solid #d8dde7;border-radius:6px;padding:12px;font-family:Consolas,Menlo,monospace;font-size:13px;line-height:1.45}.mss-actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:12px}.mss-select{width:100%;max-width:620px;border:1px solid #d8dde7;border-radius:6px;padding:10px}.mss-code{white-space:pre-wrap;background:#0b1220;color:#dbeafe;border-radius:6px;padding:12px;font-family:Consolas,Menlo,monospace;font-size:12px;line-height:1.4;max-height:420px;overflow:auto}.mss-warn{border-left:6px solid #b45309;background:#fff7ed}.mss-good{border-left:6px solid #166534;background:#f0fdf4}.mss-bad{border-left:6px solid #991b1b;background:#fef2f2}.mss-mini-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px}.mss-metric{border:1px solid #d8dde7;background:#f8fafc;border-radius:6px;padding:12px}.mss-metric strong{display:block;font-size:20px;word-break:break-word}.mss-table{width:100%;border-collapse:collapse}.mss-table th,.mss-table td{border-bottom:1px solid #e5e7eb;padding:9px;text-align:left;vertical-align:top}.mss-pill-list{display:flex;gap:8px;flex-wrap:wrap}@media(max-width:1000px){.mss-grid,.mss-mini-grid{grid-template-columns:1fr}.mss-actions .btn{width:100%;text-align:center}}
</style>

<section class="card hero warn">
    <h1>Mobile Submit Scenario Tester</h1>
    <p>Generate TEST-ONLY pre-ride scenarios, run the parser, resolver, preflight gate, dry-run connector, and payload validator in one place. This page never submits to EDXEIX.</p>
    <div class="mss-pill-list">
        <?= mss_badge('READ ONLY', 'good') ?>
        <?= mss_badge('SYNTHETIC TEST DATA', 'warn') ?>
        <?= mss_badge('NO LIVE SUBMIT', 'good') ?>
        <?= mss_badge('NO EDXEIX CALL', 'good') ?>
    </div>
</section>

<section class="mss-card mss-warn">
    <strong>TEST ONLY.</strong>
    Scenario emails generated by this page are synthetic. They are for parser/resolver/preflight testing only and must never be posted or saved as real EDXEIX rides.
</section>

<div class="mss-grid">
    <div>
        <section class="mss-card">
            <h2>1. Choose scenario or paste test email</h2>
            <form method="post" action="/ops/mobile-submit-scenarios.php" autocomplete="off">
                <input type="hidden" name="csrf" value="<?= mss_h($csrf) ?>">
                <label for="scenario"><strong>Scenario library</strong></label><br>
                <select class="mss-select" id="scenario" name="scenario">
                    <?php foreach ($scenarios as $key => $scenario): ?>
                        <option value="<?= mss_h($key) ?>" <?= $key === $selectedKey ? 'selected' : '' ?>><?= mss_h($scenario['label']) ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="small">Expected: <?= mss_h($scenarios[$selectedKey]['expected']) ?></p>
                <textarea class="mss-textarea" name="email_text" id="email_text"><?= mss_h($emailText) ?></textarea>
                <div class="mss-actions">
                    <button class="btn green" type="submit" name="action" value="run_scenario">Generate + Run Scenario</button>
                    <button class="btn" type="submit" name="action" value="parse_pasted">Parse Pasted Test Email</button>
                    <a class="btn dark" href="/ops/mobile-submit-scenarios.php">Reset</a>
                </div>
            </form>
        </section>

        <?php if ($error !== ''): ?>
            <section class="mss-card mss-bad"><h2>Runtime problem</h2><p class="badline"><strong><?= mss_h($error) ?></strong></p></section>
        <?php endif; ?>

        <?php if (is_array($parse)): ?>
            <section class="mss-card">
                <h2>2. Parsed scenario</h2>
                <div class="mss-mini-grid">
                    <div class="mss-metric"><strong><?= mss_h((string)($parse['confidence'] ?? 'unknown')) ?></strong><span>Parser confidence</span></div>
                    <div class="mss-metric"><strong><?= mss_h((string)count((array)($parse['missing_required'] ?? []))) ?></strong><span>Missing required</span></div>
                    <div class="mss-metric"><strong><?= mss_h((string)($fields['driver_name'] ?? '')) ?></strong><span>Driver</span></div>
                    <div class="mss-metric"><strong><?= mss_h((string)($fields['vehicle_plate'] ?? '')) ?></strong><span>Vehicle</span></div>
                </div>
                <div class="table-wrap" style="margin-top:14px;"><table class="mss-table">
                    <tbody>
                        <?= mss_row('Operator', $fields['operator'] ?? '') ?>
                        <?= mss_row('Passenger', $fields['customer_name'] ?? '') ?>
                        <?= mss_row('Phone', $fields['customer_phone'] ?? '') ?>
                        <?= mss_row('Pickup', $fields['pickup_address'] ?? '') ?>
                        <?= mss_row('Drop-off', $fields['dropoff_address'] ?? '') ?>
                        <?= mss_row('Pickup datetime', $fields['pickup_datetime_local'] ?? '') ?>
                        <?= mss_row('End datetime', $fields['end_datetime_local'] ?? '') ?>
                        <?= mss_row('Price amount', $fields['estimated_price_amount'] ?? '') ?>
                    </tbody>
                </table></div>
            </section>

            <section class="mss-card">
                <h2>3. Mapping resolver result</h2>
                <div class="mss-pill-list">
                    <?= mss_badge(!empty($mapping['ok']) ? 'IDS READY' : 'IDS NEED REVIEW', !empty($mapping['ok']) ? 'good' : 'warn') ?>
                    <?= mss_badge(!empty($startingOverride) ? 'LESSOR STARTING POINT OVERRIDE FOUND' : 'NO LESSOR OVERRIDE', !empty($startingOverride) ? 'good' : 'warn') ?>
                </div>
                <?php if ($lookupError): ?><p class="warnline">Lookup note: <?= mss_h($lookupError) ?></p><?php endif; ?>
                <div class="table-wrap" style="margin-top:12px;"><table class="mss-table"><tbody>
                    <?= mss_row('Lessor ID', $mapping['lessor_id'] ?? '', 'good') ?>
                    <?= mss_row('Driver ID', $mapping['driver_id'] ?? '', 'good') ?>
                    <?= mss_row('Vehicle ID', $mapping['vehicle_id'] ?? '', 'good') ?>
                    <?= mss_row('Starting point ID', $mapping['starting_point_id'] ?? '', 'good') ?>
                    <?= mss_row('Override source', is_array($startingOverride) ? (($startingOverride['label'] ?? '') . ' / ' . ($startingOverride['edxeix_starting_point_id'] ?? '')) : '') ?>
                </tbody></table></div>
                <?php foreach ((array)($mapping['messages'] ?? []) as $msg): ?><div class="goodline">✓ <?= mss_h((string)$msg) ?></div><?php endforeach; ?>
                <?php foreach ((array)($mapping['warnings'] ?? []) as $msg): ?><div class="warnline">⚠ <?= mss_h((string)$msg) ?></div><?php endforeach; ?>
            </section>
        <?php endif; ?>
    </div>

    <aside>
        <section class="mss-card">
            <h2>Scenario workflow status</h2>
            <div class="table-wrap"><table class="mss-table"><tbody>
                <?= mss_row('Parser class', class_exists(BoltPreRideEmailParser::class) ? 'Installed' : '') ?>
                <?= mss_row('Mapping lookup class', class_exists(EdxeixMappingLookup::class) ? 'Installed' : '') ?>
                <?= mss_row('Preflight gate class', class_exists(EdxeixSubmitPreflightGate::class) ? 'Installed' : '') ?>
                <?= mss_row('Connector class', class_exists(EdxeixSubmitConnector::class) ? 'Installed' : '') ?>
                <?= mss_row('Payload validator class', class_exists(EdxeixSubmitPayloadValidator::class) ? 'Installed' : '') ?>
                <?= mss_row('Latest sanitized capture', is_array($capture) ? ('ID ' . (string)($capture['id'] ?? '')) : '') ?>
            </tbody></table></div>
        </section>

        <?php if (is_array($preflight)): ?>
            <section class="mss-card">
                <h2>4. Preflight gate</h2>
                <div class="mss-pill-list">
                    <?= mss_badge(!empty($preflight['technical_ready']) ? 'TECHNICAL READY' : 'TECHNICAL BLOCKERS', !empty($preflight['technical_ready']) ? 'good' : 'bad') ?>
                    <?= mss_badge('LIVE SUBMIT BLOCKED', 'warn') ?>
                </div>
                <h3>Technical blockers</h3>
                <?php foreach ((array)($preflight['technical_blockers'] ?? []) as $blocker): ?><div class="badline">- <?= mss_h((string)$blocker) ?></div><?php endforeach; ?>
                <?php if (empty($preflight['technical_blockers'])): ?><p class="goodline">No technical blockers except live-submit gates.</p><?php endif; ?>
                <h3>Live blockers</h3>
                <?php foreach ((array)($preflight['live_blockers'] ?? []) as $blocker): ?><div class="warnline">- <?= mss_h((string)$blocker) ?></div><?php endforeach; ?>
            </section>
        <?php endif; ?>

        <?php if (is_array($validation)): ?>
            <section class="mss-card">
                <h2>5. Payload validation</h2>
                <div class="mss-pill-list">
                    <?= mss_badge(!empty($validation['dry_run_payload_valid']) ? 'DRY-RUN PAYLOAD VALID' : 'PAYLOAD NEEDS REVIEW', !empty($validation['dry_run_payload_valid']) ? 'good' : 'warn') ?>
                    <?= mss_badge('NO HTTP REQUEST', 'good') ?>
                </div>
                <h3>Required missing</h3>
                <?php foreach ((array)($validation['required_missing'] ?? []) as $item): ?><div class="badline">- <?= mss_h((string)$item) ?></div><?php endforeach; ?>
                <?php if (empty($validation['required_missing'])): ?><p class="goodline">No required payload fields missing from sanitized capture list.</p><?php endif; ?>
                <h3>Blockers</h3>
                <?php foreach (array_slice((array)($validation['blockers'] ?? []), 0, 14) as $blocker): ?><div class="warnline">- <?= mss_h((string)$blocker) ?></div><?php endforeach; ?>
            </section>
        <?php endif; ?>

        <?php if (is_array($request)): ?>
            <section class="mss-card">
                <h2>Dry-run request preview</h2>
                <div class="mss-code"><?= mss_h(mss_json($request)) ?></div>
            </section>
        <?php endif; ?>
    </aside>
</div>

<section class="mss-card">
    <h2>Related mobile submit tools</h2>
    <div class="mss-actions">
        <a class="btn" href="/ops/mobile-submit-center.php">Mobile Submit Center</a>
        <a class="btn" href="/ops/mobile-submit-gates.php">Mobile Submit Gates</a>
        <a class="btn" href="/ops/mobile-submit-readiness.php">Mobile Submit Readiness</a>
        <a class="btn" href="/ops/edxeix-submit-payload-validator.php">Payload Validator</a>
        <a class="btn dark" href="/ops/mapping-resolver-test.php">Mapping Resolver Test</a>
    </div>
</section>
<?php mss_shell_end(); ?>
