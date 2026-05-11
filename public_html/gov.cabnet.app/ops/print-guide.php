<?php
/**
 * gov.cabnet.app — Printable Operator Guide
 *
 * Read-only printable guide for the manual Bolt pre-ride email → EDXEIX workflow.
 * No Bolt calls, no EDXEIX calls, no AADE calls, no DB writes.
 */

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Robots-Tag: noindex,nofollow', true);

$shell = __DIR__ . '/_shell.php';
if (!is_file($shell)) {
    http_response_code(500);
    echo 'Shared ops shell not found.';
    exit;
}
require_once $shell;

$generatedAt = date('Y-m-d H:i:s T');

opsui_shell_begin([
    'title' => 'Printable Operator Guide',
    'page_title' => 'Printable Operator Guide',
    'breadcrumbs' => 'Αρχική / Διαχειριστικό / Printable Operator Guide',
    'active_section' => 'Operator Guide',
    'safe_notice' => 'This page is read-only and printable. It does not call Bolt, EDXEIX, or AADE, and it does not write data.',
]);
?>
<style>
.print-guide-header{display:flex;justify-content:space-between;gap:18px;align-items:flex-start;margin-bottom:16px}.print-guide-title h2{border:0!important;margin:0 0 6px!important;padding:0!important}.print-actions{display:flex;gap:10px;flex-wrap:wrap}.print-card{border:1px solid #d8dde7;background:#fff;border-radius:6px;padding:16px;margin:0 0 14px;break-inside:avoid}.print-card h3{margin:0 0 10px!important;color:#27385f!important}.print-steps{counter-reset:step;list-style:none;margin:0;padding:0}.print-steps li{counter-increment:step;position:relative;padding:10px 12px 10px 48px;border:1px solid #e3e7ef;border-radius:6px;margin:8px 0;background:#fafbfd}.print-steps li:before{content:counter(step);position:absolute;left:12px;top:9px;width:26px;height:26px;border-radius:50%;display:flex;align-items:center;justify-content:center;background:#4f5ea7;color:#fff;font-weight:800}.print-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}.print-warning{border-left:6px solid #c44b44;background:#fff5f5}.print-ok{border-left:6px solid #5fa865;background:#f4fbf5}.print-note{border-left:6px solid #d4922d;background:#fffaf0}.print-code{font-family:Consolas,Menlo,monospace;background:#f1f4fa;border:1px solid #d8dde7;border-radius:6px;padding:10px;white-space:pre-wrap;overflow:auto}.print-small{font-size:13px;color:#667085}@media(max-width:900px){.print-grid,.print-guide-header{display:block}.print-actions{margin-top:12px}}@media print{body{background:#fff!important}.gov-topbar,.gov-sidebar,.gov-page-header,.gov-optional-safety,.print-actions{display:none!important}.gov-shell{display:block!important}.gov-content{padding:0!important}.wrap{margin:0!important}.card,.print-card{box-shadow:none!important;border-color:#999!important}.print-grid{grid-template-columns:1fr 1fr!important;gap:8px!important}.print-card{padding:10px!important;margin-bottom:8px!important}.print-steps li{padding-top:6px!important;padding-bottom:6px!important;margin:5px 0!important}.print-small{font-size:11px!important}h1{font-size:22px!important}h2,h3{font-size:15px!important}p,li,td,th{font-size:12px!important}.badge{font-size:10px!important;padding:3px 6px!important}.print-only-title{display:block!important}}
.print-only-title{display:none}
</style>

<section class="card">
    <div class="print-guide-header">
        <div class="print-guide-title">
            <div class="print-only-title"><strong>gov.cabnet.app — Bolt → EDXEIX Operator Guide</strong></div>
            <h2>Bolt pre-ride email → EDXEIX workflow</h2>
            <p>Use this guide for staff training and quick reference. Generated: <?= opsui_h($generatedAt) ?>.</p>
            <div>
                <?= opsui_badge('PRODUCTION TOOL UNCHANGED', 'good') ?>
                <?= opsui_badge('OPERATOR CONFIRMED', 'warn') ?>
                <?= opsui_badge('NO AUTOMATIC EDXEIX SUBMIT', 'good') ?>
            </div>
        </div>
        <div class="print-actions">
            <button class="btn" type="button" onclick="window.print()">Print / Save PDF</button>
            <a class="btn dark" href="/ops/workflow-guide.php">Workflow Guide</a>
            <a class="btn warn" href="/ops/safety-checklist.php">Safety Checklist</a>
        </div>
    </div>
</section>

<div class="print-grid">
    <section class="print-card print-ok">
        <h3>Production route</h3>
        <p>The live production page is:</p>
        <div class="print-code">https://gov.cabnet.app/ops/pre-ride-email-tool.php</div>
        <p class="print-small">This page is currently used by staff. Do not modify it directly unless absolutely necessary.</p>
    </section>

    <section class="print-card print-note">
        <h3>Development route</h3>
        <p>Ongoing development should use:</p>
        <div class="print-code">https://gov.cabnet.app/ops/pre-ride-email-toolv2.php</div>
        <p class="print-small">Only promote V2 changes after testing and production confirmation.</p>
    </section>
</div>

<section class="print-card">
    <h3>Live operator steps</h3>
    <ol class="print-steps">
        <li>Open <strong>/ops/pre-ride-email-tool.php</strong>.</li>
        <li>Click <strong>Load latest server email + DB IDs</strong>, or paste the Bolt pre-ride email and parse it.</li>
        <li>Confirm <strong>IDs READY</strong>.</li>
        <li>Click <strong>Save + open EDXEIX</strong>.</li>
        <li>On EDXEIX, keep both Firefox helpers loaded.</li>
        <li>Click <strong>Fill using exact IDs</strong>.</li>
        <li>Verify company/lessor, passenger, driver, vehicle, pickup, drop-off, times, and price.</li>
        <li>Click/select the exact pickup point on the EDXEIX map.</li>
        <li>Submit only if the ride is real, future, mapped, and reviewed.</li>
    </ol>
</section>

<div class="print-grid">
    <section class="print-card print-warning">
        <h3>Never submit</h3>
        <ul class="list">
            <li>Old rides</li>
            <li>Completed rides</li>
            <li>Cancelled or terminal rides</li>
            <li>Past rides</li>
            <li>Unmapped driver or vehicle rides</li>
            <li>Unsafe/default map point rides</li>
            <li>Test rides unless explicitly approved for local dry-run review</li>
        </ul>
    </section>

    <section class="print-card print-ok">
        <h3>Required before saving</h3>
        <ul class="list">
            <li>Ride is future and real.</li>
            <li>IDs are ready.</li>
            <li>Driver and vehicle match the Bolt email.</li>
            <li>Company/lessor is from EDXEIX mapping.</li>
            <li>Price is correct.</li>
            <li>Map pickup point was manually selected.</li>
            <li>Operator visually reviewed the full EDXEIX form.</li>
        </ul>
    </section>
</div>

<section class="print-card">
    <h3>Firefox helper rule</h3>
    <p>For the current production workflow, keep both temporary Firefox helpers loaded:</p>
    <ul class="list">
        <li><strong>Cabnet EDXEIX Session + Payload Fill</strong></li>
        <li><strong>Gov Cabnet EDXEIX Autofill Helper</strong></li>
    </ul>
    <p class="print-small">Do not remove either helper until the future unified helper has been tested with the full live workflow.</p>
</section>

<div class="print-grid">
    <section class="print-card">
        <h3>Mobile rule</h3>
        <p>Mobile may be used for review/checking only:</p>
        <div class="print-code">https://gov.cabnet.app/ops/pre-ride-mobile-review.php</div>
        <p class="print-small">Actual EDXEIX fill/save remains desktop/laptop Firefox only.</p>
    </section>

    <section class="print-card">
        <h3>Emergency support links</h3>
        <ul class="list">
            <li><a href="/ops/system-status.php">System Status</a></li>
            <li><a href="/ops/smoke-test-center.php">Smoke Test Center</a></li>
            <li><a href="/ops/firefox-extension.php">Firefox Helper Center</a></li>
            <li><a href="/ops/handoff-center.php">Handoff Center</a></li>
        </ul>
    </section>
</div>

<section class="print-card print-warning">
    <h3>AADE separation</h3>
    <p>Pre-ride email parsing must not issue AADE receipts. AADE receipt issuing remains a separate production flow.</p>
</section>

<?php opsui_shell_end(); ?>
