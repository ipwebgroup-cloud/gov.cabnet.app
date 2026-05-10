<?php
/**
 * gov.cabnet.app — Bolt Pre-Ride Email Manual Form Utility
 *
 * Paste a Bolt pre-ride email and produce an editable, copy-friendly operations form.
 * v6.6.8 adds read-only DB ID lookup and optional latest Maildir email loading.
 * It still does not submit to EDXEIX/AADE and does not create queue jobs.
 */

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Robots-Tag: noindex, nofollow', true);

$parserFile = dirname(__DIR__, 3) . '/gov.cabnet.app_app/src/BoltMail/BoltPreRideEmailParser.php';
if (!is_file($parserFile)) {
    $parserFile = dirname(__DIR__, 2) . '/gov.cabnet.app_app/src/BoltMail/BoltPreRideEmailParser.php';
}
if (!is_file($parserFile)) {
    http_response_code(500);
    echo 'Parser file not found. Expected private app parser under gov.cabnet.app_app/src/BoltMail/.';
    exit;
}
require_once $parserFile;

$lookupFile = dirname(__DIR__, 3) . '/gov.cabnet.app_app/src/BoltMail/EdxeixMappingLookup.php';
if (is_file($lookupFile)) { require_once $lookupFile; }
$mailLoaderFile = dirname(__DIR__, 3) . '/gov.cabnet.app_app/src/BoltMail/MaildirPreRideEmailLoader.php';
if (is_file($mailLoaderFile)) { require_once $mailLoaderFile; }

use Bridge\BoltMail\BoltPreRideEmailParser;
use Bridge\BoltMail\EdxeixMappingLookup;
use Bridge\BoltMail\MaildirPreRideEmailLoader;

function pe_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function pe_badge(string $text, string $type = 'neutral'): string
{
    return '<span class="badge badge-' . pe_h($type) . '">' . pe_h($text) . '</span>';
}

function pe_field(array $fields, string $name): string
{
    return pe_h($fields[$name] ?? '');
}



function pe_app_bootstrap_path(): string
{
    return dirname(__DIR__, 3) . '/gov.cabnet.app_app/src/bootstrap.php';
}

function pe_app_context(?string &$error = null): ?array
{
    static $ctx = null;
    static $loaded = false;
    static $loadError = null;

    if ($loaded) {
        $error = $loadError;
        return is_array($ctx) ? $ctx : null;
    }

    $loaded = true;
    $bootstrap = pe_app_bootstrap_path();
    if (!is_file($bootstrap)) {
        $loadError = 'Private app bootstrap not found; DB lookup is unavailable.';
        $error = $loadError;
        return null;
    }

    try {
        $ctx = require $bootstrap;
        if (!is_array($ctx)) {
            $loadError = 'Private app bootstrap did not return a context array.';
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

/**
 * @param array<string,string> $fields
 * @return array<string,mixed>
 */
function pe_lookup_edxeix_ids(array $fields, ?string &$error = null): array
{
    $empty = [
        'ok' => false,
        'lessor_id' => '',
        'driver_id' => '',
        'vehicle_id' => '',
        'starting_point_id' => '5875309',
        'messages' => [],
        'warnings' => ['DB lookup was not available.'],
    ];

    if (!class_exists(EdxeixMappingLookup::class)) {
        $error = 'EdxeixMappingLookup class is not installed.';
        return $empty;
    }

    $ctx = pe_app_context($error);
    if (!$ctx || !isset($ctx['db']) || !method_exists($ctx['db'], 'connection')) {
        return $empty;
    }

    try {
        $lookup = new EdxeixMappingLookup($ctx['db']->connection());
        $result = $lookup->lookup($fields);
        $error = null;
        return $result;
    } catch (Throwable $e) {
        $error = $e->getMessage();
        $empty['warnings'] = ['DB lookup failed: ' . $error];
        return $empty;
    }
}

/**
 * @return array{ok:bool,email_text:string,source:string,source_mtime:string,error:string,checked_dirs:array<int,string>}
 */
function pe_load_latest_server_email(): array
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
    $ctx = pe_app_context($ctxError);
    if (is_array($ctx) && isset($ctx['config']) && method_exists($ctx['config'], 'get')) {
        $single = $ctx['config']->get('mail.pre_ride_maildir');
        if (is_string($single) && trim($single) !== '') {
            $extraDirs[] = trim($single);
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

    $loader = new MaildirPreRideEmailLoader();
    return $loader->loadLatest($extraDirs);
}

function pe_el_date_from_iso(string $iso): string
{
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $iso, $m)) {
        return $iso;
    }
    return $m[3] . '/' . $m[2] . '/' . $m[1];
}

function pe_edxeix_autofill_script(array $fields): string
{
    $pickupDate = trim((string)($fields['pickup_date'] ?? ''));
    $payload = [
        'lessor' => trim((string)($fields['operator'] ?? '')),
        'passengerName' => trim((string)($fields['customer_name'] ?? '')),
        'passengerPhone' => trim((string)($fields['customer_phone'] ?? '')),
        'driver' => trim((string)($fields['driver_name'] ?? '')),
        'vehicle' => trim((string)($fields['vehicle_plate'] ?? '')),
        'pickupAddress' => trim((string)($fields['pickup_address'] ?? '')),
        'dropoffAddress' => trim((string)($fields['dropoff_address'] ?? '')),
        'pickupDateIso' => $pickupDate,
        'pickupDateEl' => pe_el_date_from_iso($pickupDate),
        'pickupTime' => trim((string)($fields['pickup_time'] ?? '')),
        'pickupDateTime' => trim((string)($fields['pickup_datetime_local'] ?? '')),
        'endDateTime' => trim((string)($fields['end_datetime_local'] ?? '')),
        'priceText' => trim((string)($fields['estimated_price_text'] ?? '')),
        'priceAmount' => trim((string)($fields['estimated_price_amount'] ?? '')),
        'orderReference' => trim((string)($fields['order_reference'] ?? '')),
    ];

    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

    $script = <<<'JS'
'use strict';

const DATA = __PAYLOAD__;
const results = [];

function norm(value) {
    return String(value || '')
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/\s+/g, ' ')
        .trim();
}

function visibleText(el) {
    return norm(el ? (el.innerText || el.textContent || el.value || '') : '');
}

function mark(el) {
    if (!el) { return; }
    el.style.outline = '3px solid #059669';
    el.style.outlineOffset = '2px';
}

function fire(el) {
    if (!el) { return; }
    for (const type of ['input', 'change', 'blur']) {
        el.dispatchEvent(new Event(type, { bubbles: true }));
    }
}

function labelMatches(el, re) {
    return re.test(visibleText(el));
}

function controlFromLabel(label, selector) {
    const htmlFor = label.getAttribute && label.getAttribute('for');
    if (htmlFor) {
        const byFor = document.getElementById(htmlFor);
        if (byFor && byFor.matches(selector)) { return byFor; }
    }
    const inside = label.querySelector && label.querySelector(selector);
    if (inside) { return inside; }
    let node = label;
    for (let depth = 0; depth < 5 && node; depth += 1, node = node.parentElement) {
        const found = node.querySelector && node.querySelector(selector);
        if (found) { return found; }
        const next = node.nextElementSibling;
        if (next) {
            if (next.matches && next.matches(selector)) { return next; }
            const inNext = next.querySelector && next.querySelector(selector);
            if (inNext) { return inNext; }
        }
    }
    return null;
}

function findControlNearText(labelRegex, selector) {
    const labelTags = Array.from(document.querySelectorAll('label, .form-label, .control-label, strong, b, span, div, p, td, th'));
    for (const label of labelTags) {
        if (!labelMatches(label, labelRegex)) { continue; }
        const control = controlFromLabel(label, selector);
        if (control) { return control; }
    }
    return null;
}

function setValueNear(labelRegex, value, labelName, selector = 'input:not([type=hidden]), textarea') {
    if (!value) {
        results.push(labelName + ': skipped, empty source value');
        return false;
    }
    const el = findControlNearText(labelRegex, selector);
    if (!el) {
        results.push(labelName + ': not found');
        return false;
    }
    const type = String(el.type || '').toLowerCase();
    if (type === 'date') {
        el.value = DATA.pickupDateIso || value;
    } else if (type === 'time') {
        el.value = String(DATA.pickupTime || value).slice(0, 5);
    } else {
        el.value = value;
    }
    fire(el);
    mark(el);
    results.push(labelName + ': filled');
    return true;
}

function setDateNear(labelRegex, isoValue, elValue, labelName) {
    if (!isoValue && !elValue) {
        results.push(labelName + ': skipped, empty source value');
        return false;
    }
    const el = findControlNearText(labelRegex, 'input:not([type=hidden])');
    if (!el) {
        results.push(labelName + ': not found');
        return false;
    }
    const type = String(el.type || '').toLowerCase();
    el.value = type === 'date' ? isoValue : (elValue || isoValue);
    fire(el);
    mark(el);
    results.push(labelName + ': filled');
    return true;
}

function setTimeNear(labelRegex, value, labelName) {
    if (!value) {
        results.push(labelName + ': skipped, empty source value');
        return false;
    }
    const el = findControlNearText(labelRegex, 'input:not([type=hidden])');
    if (!el) {
        results.push(labelName + ': not found');
        return false;
    }
    const type = String(el.type || '').toLowerCase();
    el.value = type === 'time' ? String(value).slice(0, 5) : value;
    fire(el);
    mark(el);
    results.push(labelName + ': filled');
    return true;
}

function bestOption(select, target) {
    const t = norm(target);
    if (!select || !t) { return null; }
    const options = Array.from(select.options || []);
    let best = null;
    let score = 0;
    for (const opt of options) {
        const o = norm(opt.textContent || opt.label || opt.value || '');
        if (!o || /^παρακαλουμε|please select/.test(o)) { continue; }
        let s = 0;
        if (o === t) { s = 100; }
        else if (o.includes(t) || t.includes(o)) { s = 80; }
        else {
            const parts = t.split(/[\s,;|/-]+/).filter(Boolean);
            for (const part of parts) {
                if (part.length >= 3 && o.includes(part)) { s += 10; }
            }
        }
        if (s > score) { score = s; best = opt; }
    }
    return score >= 10 ? best : null;
}

function selectNear(labelRegex, target, labelName) {
    const select = findControlNearText(labelRegex, 'select');
    if (!select) {
        results.push(labelName + ': select not found');
        return false;
    }
    const opt = bestOption(select, target);
    if (!opt) {
        results.push(labelName + ': option not found for "' + target + '"');
        mark(select);
        return false;
    }
    select.value = opt.value;
    fire(select);
    mark(select);
    results.push(labelName + ': selected "' + (opt.textContent || opt.value).trim() + '"');
    return true;
}

function selectNaturalPerson() {
    const labels = Array.from(document.querySelectorAll('label, span, div'));
    for (const label of labels) {
        if (!/φυσικο προσωπο|natural person/.test(visibleText(label))) { continue; }
        const radio = controlFromLabel(label, 'input[type=radio]') || label.querySelector('input[type=radio]');
        if (radio) {
            radio.checked = true;
            fire(radio);
            mark(radio);
            results.push('Tenant type: natural person selected');
            return true;
        }
    }
    results.push('Tenant type: natural person radio not found');
    return false;
}

function fillTextFields() {
    setValueNear(/ονοματεπωνυμο|μισθωτη|επιβατη|επικεφαλης/, DATA.passengerName, 'Passenger / tenant name');
    setValueNear(/τηλεφωνο|κινητο|mobile|phone/, DATA.passengerPhone, 'Passenger phone');
    setValueNear(/σημειο εναρξης|τοπος εναρξης|αφετηρια|pickup|παραλαβ/, DATA.pickupAddress, 'Pickup / starting point');
    setValueNear(/σημειο ληξης|τοπος ληξης|προορισμ|drop.?off|αποβιβασ/, DATA.dropoffAddress, 'Drop-off / destination');
    setDateNear(/ημερομηνια.*(εναρξης|παραλαβ)|ημερομηνια/, DATA.pickupDateIso, DATA.pickupDateEl, 'Pickup date');
    setTimeNear(/ωρα.*(εναρξης|παραλαβ)|ωρα/, DATA.pickupTime, 'Pickup time');
    setValueNear(/μισθωμα|τιμημα|αξια|ποσο|τιμη|price|amount/, (DATA.priceAmount || '').replace('.', ','), 'Price / amount');
    setValueNear(/παρατηρησεις|σχολια|comments|notes/, 'Bolt pre-ride email. Order reference: ' + (DATA.orderReference || ''), 'Notes');
}

function main() {
    console.clear();
    console.log('gov.cabnet.app EDXEIX autofill helper starting…');
    selectNear(/εκμισθωτης|lessor/, DATA.lessor, 'Lessor');
    selectNaturalPerson();
    selectNear(/οδηγος|driver/, DATA.driver, 'Driver');
    selectNear(/οχημα|vehicle/, DATA.vehicle, 'Vehicle');
    fillTextFields();
    setTimeout(function () {
        fillTextFields();
        console.log(results.join('\n'));
        alert('EDXEIX autofill helper finished. Verify every field before saving/submitting.\n\n' + results.join('\n'));
    }, 650);
}

main();
JS;

    return "(function () {\n" . str_replace('__PAYLOAD__', $json, $script) . "\n})();\n";
}

$rawEmail = '';
$result = null;
$error = null;
$mailLoad = null;
$mailLoadError = null;
$dbLookupError = null;
$mapping = [
    'ok' => false,
    'lessor_id' => '',
    'driver_id' => '',
    'vehicle_id' => '',
    'starting_point_id' => '5875309',
    'messages' => [],
    'warnings' => [],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? 'parse_pasted');

    if ($action === 'load_latest_server_email') {
        $mailLoad = pe_load_latest_server_email();
        if (!empty($mailLoad['ok'])) {
            $rawEmail = (string)$mailLoad['email_text'];
        } else {
            $mailLoadError = (string)($mailLoad['error'] ?? 'Unable to load latest server email.');
            $rawEmail = (string)($_POST['email_text'] ?? '');
        }
    } else {
        $rawEmail = (string)($_POST['email_text'] ?? '');
    }

    if (trim($rawEmail) !== '') {
        try {
            $parser = new BoltPreRideEmailParser();
            $result = $parser->parse($rawEmail);
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    } elseif ($mailLoadError === null) {
        $error = 'No email text was provided.';
    }
}

$fields = is_array($result) ? ($result['fields'] ?? []) : [];
$generated = is_array($result) ? ($result['generated'] ?? []) : [];
if (is_array($result)) {
    $mapping = pe_lookup_edxeix_ids($fields, $dbLookupError);
}
$edxeixAutofillScript = is_array($result) ? pe_edxeix_autofill_script($fields) : '';
$missing = is_array($result) ? ($result['missing_required'] ?? []) : [];
$warnings = is_array($result) ? ($result['warnings'] ?? []) : [];
$confidence = is_array($result) ? (string)($result['confidence'] ?? 'not parsed') : 'not parsed';
$statusType = $result === null ? 'neutral' : (empty($missing) ? 'good' : 'warn');

$sample = "Operator: Fleet Mykonos LUXLIMO IKE\nCustomer: Example Customer\nCustomer mobile: +306900000000\nDriver: Example Driver\nVehicle: ABC1234\nPickup: Mikonos 846 00, Greece\nDrop-off: Mykonos Airport, Greece\nStart time: 2026-05-10 18:10:00 EEST\nEstimated pick-up time: 2026-05-10 18:15:00 EEST\nEstimated end time: 2026-05-10 18:40:00 EEST\nEstimated price: €60.00";
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Bolt Pre-Ride Email Tool | gov.cabnet.app</title>
    <style>
        :root{--bg:#f3f6fb;--panel:#fff;--ink:#07152f;--muted:#41577a;--line:#d7e1ef;--nav:#081225;--blue:#2563eb;--green:#07875a;--orange:#b85c00;--red:#b42318;--slate:#334155;--soft:#f8fbff;--gold:#d4922d}*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--ink);font-family:Arial,Helvetica,sans-serif}.nav{background:var(--nav);color:#fff;min-height:56px;display:flex;align-items:center;gap:18px;padding:0 26px;position:sticky;top:0;z-index:5;overflow:auto}.nav strong{white-space:nowrap}.nav a{color:#fff;text-decoration:none;font-size:15px;white-space:nowrap;opacity:.92}.nav a:hover{opacity:1;text-decoration:underline}.wrap{width:min(1480px,calc(100% - 48px));margin:26px auto 60px}.card{background:var(--panel);border:1px solid var(--line);border-radius:14px;padding:18px;margin-bottom:18px;box-shadow:0 10px 26px rgba(8,18,37,.04)}h1{font-size:34px;margin:0 0 12px}h2{font-size:23px;margin:0 0 14px}h3{font-size:18px;margin:18px 0 10px}p{color:var(--muted);line-height:1.45}.safety{border-left:7px solid var(--green);background:#ecfdf3}.hero{border-left:7px solid var(--gold)}.two{display:grid;grid-template-columns:1fr 1fr;gap:18px}.three{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}.field-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.field{display:flex;flex-direction:column;gap:5px}.field.full{grid-column:1 / -1}label{font-weight:700;font-size:13px;color:#27385f}input,textarea{width:100%;border:1px solid var(--line);border-radius:9px;padding:11px 12px;font-size:15px;font-family:Arial,Helvetica,sans-serif;background:#fff;color:var(--ink)}textarea{min-height:240px;resize:vertical}.raw textarea{min-height:420px}.output textarea{min-height:150px;background:#fbfdff}.btn{display:inline-block;border:0;padding:11px 14px;border-radius:8px;color:#fff;text-decoration:none;font-weight:700;background:var(--blue);font-size:14px;cursor:pointer}.btn.green{background:var(--green)}.btn.orange{background:var(--orange)}.btn.dark{background:var(--slate)}.btn.gold{background:var(--gold)}.btn.light{background:#eaf1ff;color:#1e40af}.actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:14px}.badge{display:inline-block;padding:5px 9px;border-radius:999px;font-size:12px;font-weight:700;margin:1px 3px 1px 0;white-space:nowrap}.badge-good{background:#dcfce7;color:#166534}.badge-warn{background:#fff7ed;color:#b45309}.badge-bad{background:#fee2e2;color:#991b1b}.badge-neutral{background:#eaf1ff;color:#1e40af}.list{margin:0;padding-left:18px;color:var(--muted)}.list li{margin:7px 0}.metric{border:1px solid var(--line);border-radius:10px;padding:14px;background:var(--soft);min-height:86px}.metric strong{display:block;font-size:27px;line-height:1.08;word-break:break-word}.metric span{color:var(--muted);font-size:14px}.warnline{color:#b45309}.badline{color:#991b1b}.goodline{color:#166534}.small{font-size:13px;color:var(--muted)}code{background:#eef2ff;padding:2px 5px;border-radius:5px}.copy-row{display:flex;gap:8px;align-items:center}.copy-row input{flex:1}.copy-row button{flex:0 0 auto}.form-note{background:#fff7ed;border:1px solid #fed7aa;border-radius:10px;padding:12px;color:#9a3412}.code-box{font-family:Consolas,Menlo,Monaco,monospace;font-size:13px;line-height:1.35;min-height:280px;background:#0b1220;color:#dbeafe}.stepbox{background:#eef6ff;border:1px solid #bfdbfe;border-radius:10px;padding:12px;color:#1e3a8a}.stepbox ol{margin:8px 0 0 22px;padding:0}.stepbox li{margin:5px 0}.helper-status{display:inline-block;margin-left:8px;font-weight:700}.helper-status.ok{color:#166534}.helper-status.warn{color:#b45309}.helper-status.bad{color:#991b1b}@media(max-width:980px){.two,.three,.field-grid{grid-template-columns:1fr}.field.full{grid-column:auto}.wrap{width:calc(100% - 24px);margin-top:14px}.nav{padding:0 14px}.raw textarea{min-height:280px}}
    </style>
</head>
<body>
<nav class="nav">
    <strong>GC gov.cabnet.app</strong>
    <a href="/ops/index.php">Operations Console</a>
    <a href="/ops/home.php">Ops Home</a>
    <a href="/ops/pre-ride-email-tool.php">Pre-Ride Email Tool</a>
    <a href="/ops/readiness.php">Readiness</a>
    <a href="/ops/jobs.php">Jobs</a>
</nav>

<main class="wrap">
    <section class="card safety">
        <strong>Manual utility only.</strong>
        This page parses pasted Bolt pre-ride email text and fills an editable operator form. It does not save the email, does not write to the database, does not call EDXEIX, and does not issue AADE receipts.
    </section>

    <section class="card hero">
        <h1>Bolt Pre-Ride Email → Operator Form</h1>
        <p>Paste the full Bolt pre-ride email below, press <strong>Parse email</strong>, then verify the extracted fields before using them for dispatch or manual EDXEIX entry.</p>
        <div>
            <?= pe_badge('NO DB WRITE', 'good') ?>
            <?= pe_badge('NO EDXEIX CALL', 'good') ?>
            <?= pe_badge('NO AADE CALL', 'good') ?>
            <?= pe_badge('POST ONLY', 'neutral') ?>
        </div>
    </section>

    <section class="two">
        <form class="card raw" method="post" action="/ops/pre-ride-email-tool.php" autocomplete="off">
            <h2>1. Paste pre-ride email</h2>
            <p class="small">Use the email body text. The tool accepts normal pasted Gmail text, basic HTML email text, and quoted-printable fragments.</p>
            <textarea name="email_text" id="email_text" placeholder="Paste Bolt pre-ride email here..."><?= pe_h($rawEmail) ?></textarea>
            <div class="actions">
                <button class="btn green" type="submit" name="action" value="parse_pasted">Parse email + DB IDs</button>
                <button class="btn gold" type="submit" name="action" value="load_latest_server_email">Load latest server email + DB IDs</button>
                <button class="btn light" type="button" onclick="loadSample()">Load safe sample</button>
                <button class="btn dark" type="button" onclick="clearInput()">Clear</button>
            </div>
        </form>

        <section class="card">
            <h2>2. Parse status</h2>
            <?php if ($error): ?>
                <p class="badline"><strong>Error:</strong> <?= pe_h($error) ?></p>
            <?php elseif ($result === null): ?>
                <p>No email parsed yet.</p>
                <ul class="list">
                    <li>Required fields: Customer, phone, driver, vehicle, pickup, drop-off, pickup datetime, estimated end time.</li>
                    <li>If the estimated pickup time is missing, Start time is used as a fallback.</li>
                    <li>Always visually verify before using the values operationally.</li>
                </ul>
            <?php else: ?>
                <div class="three">
                    <div class="metric"><strong><?= pe_h(strtoupper($confidence)) ?></strong><span>Parser confidence</span></div>
                    <div class="metric"><strong><?= pe_h((string)count($missing)) ?></strong><span>Missing required fields</span></div>
                    <div class="metric"><strong><?= pe_h((string)($result['raw_length'] ?? 0)) ?></strong><span>Input bytes checked</span></div>
                </div>
                <p>Status: <?= pe_badge(empty($missing) ? 'READY TO REVIEW' : 'NEEDS REVIEW', $statusType) ?></p>
                <?php if (!empty($missing)): ?>
                    <h3>Missing required fields</h3>
                    <ul class="list"><?php foreach ($missing as $item): ?><li class="warnline"><?= pe_h($item) ?></li><?php endforeach; ?></ul>
                <?php endif; ?>
                <?php if (!empty($warnings)): ?>
                    <h3>Warnings</h3>
                    <ul class="list"><?php foreach ($warnings as $item): ?><li class="warnline"><?= pe_h($item) ?></li><?php endforeach; ?></ul>
                <?php endif; ?>
                <?php if (empty($missing)): ?>
                    <p class="goodline"><strong>Ready:</strong> the main fields were found. Verify them carefully before use.</p>
                <?php endif; ?>
                <?php if (is_array($mailLoad) && !empty($mailLoad['ok'])): ?>
                    <p class="goodline"><strong>Server email loaded:</strong> <?= pe_h($mailLoad['source'] ?? '') ?> <?= !empty($mailLoad['source_mtime']) ? '(' . pe_h($mailLoad['source_mtime']) . ')' : '' ?></p>
                <?php elseif ($mailLoadError): ?>
                    <p class="warnline"><strong>Server email loader:</strong> <?= pe_h($mailLoadError) ?></p>
                <?php endif; ?>
                <?php if ($dbLookupError): ?>
                    <p class="warnline"><strong>DB ID lookup:</strong> <?= pe_h($dbLookupError) ?></p>
                <?php endif; ?>
                <div class="stepbox" style="margin-top:12px;">
                    <strong>DB ID lookup result:</strong> <?= !empty($mapping['ok']) ? pe_badge('IDs READY', 'good') : pe_badge('CHECK IDS', 'warn') ?>
                    <?php foreach (($mapping['messages'] ?? []) as $msg): ?><div class="goodline">✓ <?= pe_h($msg) ?></div><?php endforeach; ?>
                    <?php foreach (($mapping['warnings'] ?? []) as $msg): ?><div class="warnline">⚠ <?= pe_h($msg) ?></div><?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </section>

    <?php if ($result !== null && !$error): ?>
    <section class="card">
        <h2>3. Editable operator form</h2>
        <p class="form-note"><strong>Important:</strong> This is a manual assistance screen. Edit anything that looks wrong before copying it to EDXEIX or dispatch notes.</p>
        <div class="field-grid" id="operatorForm">
            <div class="field"><label>Operator</label><input id="operator" value="<?= pe_field($fields, 'operator') ?>"></div>
            <div class="field"><label>Order / ride reference</label><input id="order_reference" value="<?= pe_field($fields, 'order_reference') ?>"></div>
            <div class="field"><label>Customer</label><div class="copy-row"><input id="customer_name" value="<?= pe_field($fields, 'customer_name') ?>"><button class="btn light" type="button" data-copy="customer_name">Copy</button></div></div>
            <div class="field"><label>Customer mobile</label><div class="copy-row"><input id="customer_phone" value="<?= pe_field($fields, 'customer_phone') ?>"><button class="btn light" type="button" data-copy="customer_phone">Copy</button></div></div>
            <div class="field full"><label>Pickup</label><div class="copy-row"><input id="pickup_address" value="<?= pe_field($fields, 'pickup_address') ?>"><button class="btn light" type="button" data-copy="pickup_address">Copy</button></div></div>
            <div class="field full"><label>Drop-off</label><div class="copy-row"><input id="dropoff_address" value="<?= pe_field($fields, 'dropoff_address') ?>"><button class="btn light" type="button" data-copy="dropoff_address">Copy</button></div></div>
            <div class="field"><label>Pickup date</label><input id="pickup_date" value="<?= pe_field($fields, 'pickup_date') ?>"></div>
            <div class="field"><label>Pickup time</label><input id="pickup_time" value="<?= pe_field($fields, 'pickup_time') ?>"></div>
            <div class="field"><label>Pickup datetime local</label><input id="pickup_datetime_local" value="<?= pe_field($fields, 'pickup_datetime_local') ?>"></div>
            <div class="field"><label>Pickup timezone</label><input id="pickup_timezone" value="<?= pe_field($fields, 'pickup_timezone') ?>"></div>
            <div class="field"><label>Estimated end datetime</label><input id="end_datetime_local" value="<?= pe_field($fields, 'end_datetime_local') ?>"></div>
            <div class="field"><label>Estimated end timezone</label><input id="end_timezone" value="<?= pe_field($fields, 'end_timezone') ?>"></div>
            <div class="field"><label>Driver</label><div class="copy-row"><input id="driver_name" value="<?= pe_field($fields, 'driver_name') ?>"><button class="btn light" type="button" data-copy="driver_name">Copy</button></div></div>
            <div class="field"><label>Vehicle / plate</label><div class="copy-row"><input id="vehicle_plate" value="<?= pe_field($fields, 'vehicle_plate') ?>"><button class="btn light" type="button" data-copy="vehicle_plate">Copy</button></div></div>
            <div class="field"><label>Estimated price text</label><input id="estimated_price_text" value="<?= pe_field($fields, 'estimated_price_text') ?>"></div>
            <div class="field"><label>Estimated price amount</label><input id="estimated_price_amount" value="<?= pe_field($fields, 'estimated_price_amount') ?>"></div>
        </div>

        <h3>Exact EDXEIX IDs for reliable fill / POST</h3>
        <p class="form-note"><strong>Strong mode:</strong> use the real EDXEIX IDs. The Firefox helper will select/post these exact IDs, even when text matching fails.</p>
        <div class="field-grid">
            <div class="field"><label>Company / lessor ID</label><input id="edxeix_lessor_id" inputmode="numeric" value="<?= pe_h($mapping['lessor_id'] ?? '') ?>" placeholder="e.g. 3814"></div>
            <div class="field"><label>Registered starting point ID</label><input id="edxeix_starting_point_id" inputmode="numeric" value="<?= pe_h($mapping['starting_point_id'] ?? '5875309') ?>" placeholder="e.g. 5875309"></div>
            <div class="field"><label>Driver ID</label><input id="edxeix_driver_id" inputmode="numeric" value="<?= pe_h($mapping['driver_id'] ?? '') ?>" placeholder="DB should fill this; paste only if missing"></div>
            <div class="field"><label>Vehicle ID</label><input id="edxeix_vehicle_id" inputmode="numeric" value="<?= pe_h($mapping['vehicle_id'] ?? '') ?>" placeholder="DB should fill this; paste only if missing"></div>
        </div>
        <div class="actions">
            <button class="btn light" type="button" onclick="autoFillKnownEdxeixIds()">Auto-fill known IDs</button>
            <button class="btn dark" type="button" onclick="rememberEdxeixIds()">Remember these IDs on this PC</button>
            <span id="id_status" class="helper-status"></span>
        </div>

        <div class="actions">
            <button class="btn green" type="button" onclick="copyManualForm()">Copy manual form</button>
            <button class="btn orange" type="button" onclick="print()">Print / save PDF</button>
            <a class="btn dark" href="/ops/pre-ride-email-tool.php">Start new email</a>
        </div>
    </section>

    <section class="card">
        <h2>4. EDXEIX exact-ID fill / POST helper</h2>
        <p class="form-note"><strong>Recommended office workflow:</strong> install the Firefox helper once. Staff paste the Bolt email, verify the form, confirm the EDXEIX IDs, click <strong>Save for EDXEIX helper</strong>, then use the green helper on EDXEIX. No F12, no Console, no tokens copied.</p>
        <div class="stepbox">
            <strong>No-programmer workflow:</strong>
            <ol>
                <li>Review/edit the operator form above.</li>
                <li>Confirm the Company ID, Driver ID, and Vehicle ID in section 3.</li>
                <li>Click <strong>Save for EDXEIX helper</strong>.</li>
                <li>Open the EDXEIX rental contract form.</li>
                <li>Click <strong>Open correct company form</strong> if needed.</li>
                <li>Click <strong>Fill using exact IDs</strong>.</li>
                <li>After verifying every field, click <strong>POST / Save reviewed form</strong> in the EDXEIX helper.</li>
            </ol>
        </div>
        <div class="actions">
            <button class="btn green" type="button" onclick="saveForEdxeixHelper()">Save for EDXEIX helper</button>
            <button class="btn gold" type="button" onclick="saveForEdxeixHelper(true)">Save + open EDXEIX</button>
            <a class="btn gold" id="open_edxeix_link" href="https://edxeix.yme.gov.gr/dashboard/lease-agreement/create" target="_blank" rel="noopener">Open EDXEIX form</a>
            <button class="btn light" type="button" onclick="copyManualForm()">Copy manual fallback form</button>
            <span id="helper_status" class="helper-status"></span>
        </div>
        <h3>Fallback for Andreas only</h3>
        <p class="small">Firefox blocks console pasting until you type <code>allow pasting</code>. Staff should not use this fallback. It remains here only for emergency testing.</p>
        <textarea id="edxeix_autofill_script" class="code-box" spellcheck="false"><?= pe_h($edxeixAutofillScript) ?></textarea>
        <div class="actions">
            <button class="btn dark" type="button" data-copy="edxeix_autofill_script">Copy emergency console script</button>
        </div>
        <p class="small">Because <code>gov.cabnet.app</code> and <code>edxeix.yme.gov.gr</code> are different domains, the gov page cannot directly control the EDXEIX page by itself. The Firefox helper runs locally inside the logged-in browser and uses the existing EDXEIX page session. It does not expose cookies, CSRF tokens, passwords, or API credentials.</p>
    </section>

    <section class="two output">
        <section class="card">
            <h2>5. Dispatch summary</h2>
            <textarea id="dispatch_summary"><?= pe_h($generated['dispatch_summary'] ?? '') ?></textarea>
            <div class="actions"><button class="btn light" type="button" data-copy="dispatch_summary">Copy dispatch summary</button></div>
        </section>
        <section class="card">
            <h2>6. Spreadsheet row</h2>
            <p class="small">Header and row are separated so you can paste the header once, then only paste rows afterwards.</p>
            <label>CSV header</label>
            <textarea id="csv_header" style="min-height:80px"><?= pe_h($generated['csv_header'] ?? '') ?></textarea>
            <label>CSV row</label>
            <textarea id="csv_row" style="min-height:80px"><?= pe_h($generated['csv_row'] ?? '') ?></textarea>
            <div class="actions">
                <button class="btn light" type="button" data-copy="csv_row">Copy row</button>
                <button class="btn light" type="button" onclick="copyCsvWithHeader()">Copy header + row</button>
            </div>
        </section>
    </section>
    <?php endif; ?>
</main>

<script>
const SAMPLE_EMAIL = <?= json_encode($sample, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

function loadSample() {
    document.getElementById('email_text').value = SAMPLE_EMAIL;
}

function clearInput() {
    document.getElementById('email_text').value = '';
    document.getElementById('email_text').focus();
}

async function copyText(text) {
    if (!text) { return; }
    try {
        await navigator.clipboard.writeText(text);
    } catch (e) {
        const ta = document.createElement('textarea');
        ta.value = text;
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        document.body.removeChild(ta);
    }
}

document.addEventListener('click', function (event) {
    const btn = event.target.closest('[data-copy]');
    if (!btn) { return; }
    const id = btn.getAttribute('data-copy');
    const el = document.getElementById(id);
    if (!el) { return; }
    copyText(el.value || el.textContent || '');
    const old = btn.textContent;
    btn.textContent = 'Copied';
    setTimeout(() => { btn.textContent = old; }, 900);
});

function valueOf(id) {
    const el = document.getElementById(id);
    return el ? el.value.trim() : '';
}

function copyManualForm() {
    const lines = [
        'Bolt pre-ride transfer',
        'Operator: ' + valueOf('operator'),
        'Order reference: ' + valueOf('order_reference'),
        'Customer: ' + valueOf('customer_name'),
        'Customer mobile: ' + valueOf('customer_phone'),
        'Pickup: ' + valueOf('pickup_address'),
        'Drop-off: ' + valueOf('dropoff_address'),
        'Pickup date: ' + valueOf('pickup_date'),
        'Pickup time: ' + valueOf('pickup_time'),
        'Pickup datetime: ' + valueOf('pickup_datetime_local') + ' ' + valueOf('pickup_timezone'),
        'Estimated end: ' + valueOf('end_datetime_local') + ' ' + valueOf('end_timezone'),
        'Driver: ' + valueOf('driver_name'),
        'Vehicle: ' + valueOf('vehicle_plate'),
        'Estimated price: ' + valueOf('estimated_price_text')
    ];
    copyText(lines.join('\n'));
}

function copyCsvWithHeader() {
    const header = valueOf('csv_header');
    const row = valueOf('csv_row');
    copyText(header + '\n' + row);
}

function isoToElDate(iso) {
    const m = String(iso || '').match(/^(\d{4})-(\d{2})-(\d{2})$/);
    return m ? (m[3] + '/' + m[2] + '/' + m[1]) : String(iso || '');
}


const KNOWN_EDXEIX_LESSORS = {
    'LUXLIMO': '3814',
    'LUX LIMO': '3814',
    'LUXLIMO IKE': '3814',
    'LUXLIMO Ι Κ Ε': '3814',
    'FLEET MYKONOS LUXLIMO IKE': '3814',
    'NGK': '2124',
    'N G K': '2124',
    'QUALITATIVE TRANSFER MYKONOS': '2307',
    'MYKONOS TOURIST AGENCY': '3894',
    'ΜΥΚΟΝΟΣ TOURIST AGENCY': '3894',
    'VIP ROAD MYKONOS': '1487',
    'WHITEBLUE PREMIUM': '1756',
    'LUX MYKONOS': '4635'
};

const KNOWN_EDXEIX_DRIVERS = {
    'FILIPPOS GIANNAKOPOULOS': '17585',
    'GIANNIAGOPOULOS FILIPPOS': '17585',
    'ΓΙΑΝΝΑΚΟΠΟΥΛΟΣ ΦΙΛΙΠΠΟΣ': '17585',
    'VIDAKIS NIKOLAOS': '1658',
    'ΒΙΔΑΚΗΣ ΝΙΚΟΛΑΟΣ': '1658',
    'KALLINTERIS GEORGIOS': '20234',
    'ΚΑΛΛΙΝΤΕΡΗΣ ΓΕΩΡΓΙΟΣ': '20234',
    'MANOUSELIS IOSIF': '6026',
    'ΜΑΝΟΥΣΕΛΗΣ ΙΩΣΗΦ': '6026'
};

const KNOWN_EDXEIX_VEHICLES = {
    'EHA2545': '5949',
    'ΕΗΑ2545': '5949',
    'EMX6874': '13799',
    'ΕΜΧ6874': '13799'
};

function idNorm(value) {
    return String(value || '')
        .toUpperCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/[^A-Z0-9Α-Ω]+/g, ' ')
        .replace(/\s+/g, ' ')
        .trim();
}

function plateNorm(value) {
    return String(value || '')
        .toUpperCase()
        .replace(/Α/g, 'A').replace(/Β/g, 'B').replace(/Ε/g, 'E').replace(/Ζ/g, 'Z').replace(/Η/g, 'H')
        .replace(/Ι/g, 'I').replace(/Κ/g, 'K').replace(/Μ/g, 'M').replace(/Ν/g, 'N').replace(/Ο/g, 'O')
        .replace(/Ρ/g, 'P').replace(/Τ/g, 'T').replace(/Υ/g, 'Y').replace(/Χ/g, 'X')
        .replace(/[^A-Z0-9]/g, '');
}

function findKnownId(map, text, plateMode) {
    const raw = String(text || '');
    const key = plateMode ? plateNorm(raw) : idNorm(raw);
    if (map[key]) { return map[key]; }
    for (const [name, id] of Object.entries(map)) {
        const candidate = plateMode ? plateNorm(name) : idNorm(name);
        if (key && (key.includes(candidate) || candidate.includes(key))) { return id; }
    }
    return '';
}

function setValueIfEmpty(id, value) {
    const el = document.getElementById(id);
    if (el && !el.value && value) { el.value = value; }
}

function autoFillKnownEdxeixIds() {
    const lessorId = findKnownId(KNOWN_EDXEIX_LESSORS, valueOf('operator'), false);
    const driverId = findKnownId(KNOWN_EDXEIX_DRIVERS, valueOf('driver_name'), false);
    const vehicleId = findKnownId(KNOWN_EDXEIX_VEHICLES, valueOf('vehicle_plate'), true);

    setValueIfEmpty('edxeix_lessor_id', lessorId);
    setValueIfEmpty('edxeix_driver_id', driverId);
    setValueIfEmpty('edxeix_vehicle_id', vehicleId);
    setValueIfEmpty('edxeix_starting_point_id', '5875309');

    const status = document.getElementById('id_status');
    if (status) {
        status.textContent = 'Known IDs checked. Fill any missing IDs manually.';
        status.className = 'helper-status warn';
    }
    updateEdxeixOpenLink();
}

function rememberEdxeixIds() {
    const key = 'govCabnetEdxeixIdMemory';
    let memory = {};
    try { memory = JSON.parse(localStorage.getItem(key) || '{}') || {}; } catch (e) {}

    const driver = idNorm(valueOf('driver_name'));
    const vehicle = plateNorm(valueOf('vehicle_plate'));
    const operator = idNorm(valueOf('operator'));
    if (operator && valueOf('edxeix_lessor_id')) { memory['lessor:' + operator] = valueOf('edxeix_lessor_id'); }
    if (driver && valueOf('edxeix_driver_id')) { memory['driver:' + driver] = valueOf('edxeix_driver_id'); }
    if (vehicle && valueOf('edxeix_vehicle_id')) { memory['vehicle:' + vehicle] = valueOf('edxeix_vehicle_id'); }
    if (valueOf('edxeix_starting_point_id')) { memory['starting_point'] = valueOf('edxeix_starting_point_id'); }
    localStorage.setItem(key, JSON.stringify(memory));

    const status = document.getElementById('id_status');
    if (status) {
        status.textContent = 'IDs remembered on this PC.';
        status.className = 'helper-status ok';
    }
}

function applyRememberedEdxeixIds() {
    const key = 'govCabnetEdxeixIdMemory';
    let memory = {};
    try { memory = JSON.parse(localStorage.getItem(key) || '{}') || {}; } catch (e) {}
    setValueIfEmpty('edxeix_lessor_id', memory['lessor:' + idNorm(valueOf('operator'))] || '');
    setValueIfEmpty('edxeix_driver_id', memory['driver:' + idNorm(valueOf('driver_name'))] || '');
    setValueIfEmpty('edxeix_vehicle_id', memory['vehicle:' + plateNorm(valueOf('vehicle_plate'))] || '');
    setValueIfEmpty('edxeix_starting_point_id', memory['starting_point'] || '5875309');
}

function updateEdxeixOpenLink() {
    const link = document.getElementById('open_edxeix_link');
    if (!link) { return; }
    const lessorId = valueOf('edxeix_lessor_id');
    link.href = lessorId
        ? 'https://edxeix.yme.gov.gr/dashboard/lease-agreement/create?lessor=' + encodeURIComponent(lessorId)
        : 'https://edxeix.yme.gov.gr/dashboard/lease-agreement/create';
}

function buildEdxeixPayloadFromForm() {
    return {
        lessor: valueOf('operator'),
        lessorId: valueOf('edxeix_lessor_id'),
        driverId: valueOf('edxeix_driver_id'),
        vehicleId: valueOf('edxeix_vehicle_id'),
        startingPointId: valueOf('edxeix_starting_point_id'),
        passengerName: valueOf('customer_name'),
        passengerPhone: valueOf('customer_phone'),
        driver: valueOf('driver_name'),
        vehicle: valueOf('vehicle_plate'),
        pickupAddress: valueOf('pickup_address'),
        dropoffAddress: valueOf('dropoff_address'),
        pickupDateIso: valueOf('pickup_date'),
        pickupDateEl: isoToElDate(valueOf('pickup_date')),
        pickupTime: valueOf('pickup_time'),
        pickupDateTime: valueOf('pickup_datetime_local'),
        endDateTime: valueOf('end_datetime_local'),
        priceText: valueOf('estimated_price_text'),
        priceAmount: valueOf('estimated_price_amount'),
        orderReference: valueOf('order_reference'),
        savedAt: new Date().toISOString(),
        source: 'gov.cabnet.app pre-ride email tool'
    };
}

function setHelperStatus(text, type) {
    const el = document.getElementById('helper_status');
    if (!el) { return; }
    el.textContent = text || '';
    el.className = 'helper-status ' + (type || '');
}

window.addEventListener('message', function (event) {
    if (event.source !== window) { return; }
    const msg = event.data || {};
    if (msg.type === 'GOV_CABNET_EDXEIX_PAYLOAD_SAVED') {
        setHelperStatus('Saved. Open EDXEIX and click Fill using exact IDs.', 'ok');
    }
});

function saveForEdxeixHelper(openAfterSave) {
    const payload = buildEdxeixPayloadFromForm();
    if (!payload.passengerName || !payload.pickupAddress || !payload.pickupTime) {
        setHelperStatus('Missing key fields. Review the form first.', 'bad');
        alert('Missing key fields. Review the parsed form first.');
        return;
    }

    if (!payload.lessorId || !payload.driverId || !payload.vehicleId) {
        setHelperStatus('Missing EDXEIX IDs. Fill Company ID, Driver ID, and Vehicle ID first.', 'bad');
        alert('Missing EDXEIX IDs. Fill Company ID, Driver ID, and Vehicle ID first.');
        return;
    }

    try {
        localStorage.setItem('govCabnetLatestEdxeixPayload', JSON.stringify(payload));
    } catch (e) {}

    setHelperStatus('Sending to browser helper...', 'warn');
    window.postMessage({ type: 'GOV_CABNET_EDXEIX_PAYLOAD', payload: payload }, '*');

    if (openAfterSave) {
        setTimeout(function () {
            updateEdxeixOpenLink();
            const link = document.getElementById('open_edxeix_link');
            if (link && link.href) { window.open(link.href, '_blank', 'noopener'); }
        }, 350);
    }

    setTimeout(function () {
        const current = document.getElementById('helper_status');
        if (current && /Sending/.test(current.textContent || '')) {
            setHelperStatus('Firefox helper not detected. Install/load the helper folder first.', 'bad');
            alert('Firefox helper was not detected. Install/load the included tools/firefox-edxeix-autofill-helper folder first, then click Save for EDXEIX helper again.');
        }
    }, 1200);
}



document.addEventListener('DOMContentLoaded', function () {
    applyRememberedEdxeixIds();
    autoFillKnownEdxeixIds();
    updateEdxeixOpenLink();
    ['edxeix_lessor_id', 'edxeix_driver_id', 'edxeix_vehicle_id', 'edxeix_starting_point_id'].forEach(function (id) {
        const el = document.getElementById(id);
        if (el) { el.addEventListener('input', updateEdxeixOpenLink); }
    });
});

</script>
</body>
</html>
