<?php
/**
 * gov.cabnet.app — Bolt Pre-Ride Email Manual Form Utility
 *
 * Paste a Bolt pre-ride email and produce an editable, copy-friendly operations form.
 * This file intentionally does not use the database and does not call EDXEIX/AADE.
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

use Bridge\BoltMail\BoltPreRideEmailParser;

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

$rawEmail = '';
$result = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rawEmail = (string)($_POST['email_text'] ?? '');
    try {
        $parser = new BoltPreRideEmailParser();
        $result = $parser->parse($rawEmail);
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$fields = is_array($result) ? ($result['fields'] ?? []) : [];
$generated = is_array($result) ? ($result['generated'] ?? []) : [];
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
        :root{--bg:#f3f6fb;--panel:#fff;--ink:#07152f;--muted:#41577a;--line:#d7e1ef;--nav:#081225;--blue:#2563eb;--green:#07875a;--orange:#b85c00;--red:#b42318;--slate:#334155;--soft:#f8fbff;--gold:#d4922d}*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--ink);font-family:Arial,Helvetica,sans-serif}.nav{background:var(--nav);color:#fff;min-height:56px;display:flex;align-items:center;gap:18px;padding:0 26px;position:sticky;top:0;z-index:5;overflow:auto}.nav strong{white-space:nowrap}.nav a{color:#fff;text-decoration:none;font-size:15px;white-space:nowrap;opacity:.92}.nav a:hover{opacity:1;text-decoration:underline}.wrap{width:min(1480px,calc(100% - 48px));margin:26px auto 60px}.card{background:var(--panel);border:1px solid var(--line);border-radius:14px;padding:18px;margin-bottom:18px;box-shadow:0 10px 26px rgba(8,18,37,.04)}h1{font-size:34px;margin:0 0 12px}h2{font-size:23px;margin:0 0 14px}h3{font-size:18px;margin:18px 0 10px}p{color:var(--muted);line-height:1.45}.safety{border-left:7px solid var(--green);background:#ecfdf3}.hero{border-left:7px solid var(--gold)}.two{display:grid;grid-template-columns:1fr 1fr;gap:18px}.three{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}.field-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.field{display:flex;flex-direction:column;gap:5px}.field.full{grid-column:1 / -1}label{font-weight:700;font-size:13px;color:#27385f}input,textarea{width:100%;border:1px solid var(--line);border-radius:9px;padding:11px 12px;font-size:15px;font-family:Arial,Helvetica,sans-serif;background:#fff;color:var(--ink)}textarea{min-height:240px;resize:vertical}.raw textarea{min-height:420px}.output textarea{min-height:150px;background:#fbfdff}.btn{display:inline-block;border:0;padding:11px 14px;border-radius:8px;color:#fff;text-decoration:none;font-weight:700;background:var(--blue);font-size:14px;cursor:pointer}.btn.green{background:var(--green)}.btn.orange{background:var(--orange)}.btn.dark{background:var(--slate)}.btn.gold{background:var(--gold)}.btn.light{background:#eaf1ff;color:#1e40af}.actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:14px}.badge{display:inline-block;padding:5px 9px;border-radius:999px;font-size:12px;font-weight:700;margin:1px 3px 1px 0;white-space:nowrap}.badge-good{background:#dcfce7;color:#166534}.badge-warn{background:#fff7ed;color:#b45309}.badge-bad{background:#fee2e2;color:#991b1b}.badge-neutral{background:#eaf1ff;color:#1e40af}.list{margin:0;padding-left:18px;color:var(--muted)}.list li{margin:7px 0}.metric{border:1px solid var(--line);border-radius:10px;padding:14px;background:var(--soft);min-height:86px}.metric strong{display:block;font-size:27px;line-height:1.08;word-break:break-word}.metric span{color:var(--muted);font-size:14px}.warnline{color:#b45309}.badline{color:#991b1b}.goodline{color:#166534}.small{font-size:13px;color:var(--muted)}code{background:#eef2ff;padding:2px 5px;border-radius:5px}.copy-row{display:flex;gap:8px;align-items:center}.copy-row input{flex:1}.copy-row button{flex:0 0 auto}.form-note{background:#fff7ed;border:1px solid #fed7aa;border-radius:10px;padding:12px;color:#9a3412}@media(max-width:980px){.two,.three,.field-grid{grid-template-columns:1fr}.field.full{grid-column:auto}.wrap{width:calc(100% - 24px);margin-top:14px}.nav{padding:0 14px}.raw textarea{min-height:280px}}
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
            <textarea name="email_text" id="email_text" placeholder="Paste Bolt pre-ride email here..." required><?= pe_h($rawEmail) ?></textarea>
            <div class="actions">
                <button class="btn green" type="submit">Parse email</button>
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
        <div class="actions">
            <button class="btn green" type="button" onclick="copyManualForm()">Copy manual form</button>
            <button class="btn orange" type="button" onclick="print()">Print / save PDF</button>
            <a class="btn dark" href="/ops/pre-ride-email-tool.php">Start new email</a>
        </div>
    </section>

    <section class="two output">
        <section class="card">
            <h2>4. Dispatch summary</h2>
            <textarea id="dispatch_summary"><?= pe_h($generated['dispatch_summary'] ?? '') ?></textarea>
            <div class="actions"><button class="btn light" type="button" data-copy="dispatch_summary">Copy dispatch summary</button></div>
        </section>
        <section class="card">
            <h2>5. Spreadsheet row</h2>
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
</script>
</body>
</html>
