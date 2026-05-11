<?php
/**
 * gov.cabnet.app — Operator workflow guide.
 * Read-only SOP page. No Bolt/EDXEIX/AADE calls and no writes.
 */

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Robots-Tag: noindex, nofollow', true);

require_once __DIR__ . '/_shell.php';

opsui_shell_begin([
    'title' => 'Workflow Guide',
    'page_title' => 'Operator workflow guide',
    'subtitle' => 'Safe Bolt pre-ride email → EDXEIX operator SOP',
    'breadcrumbs' => 'Αρχική / Help / Workflow guide',
    'active_section' => 'Help',
    'safe_notice' => 'Read-only SOP page. It does not call Bolt, EDXEIX, or AADE, does not write data, and does not change the production pre-ride tool.',
]);
?>
<section class="card hero neutral">
    <h1>Production workflow rule</h1>
    <p>The current production staff route remains <code>/ops/pre-ride-email-tool.php</code>. Do not modify or replace it during live operations unless a verified emergency requires it.</p>
    <div>
        <?= opsui_badge('PRODUCTION TOOL UNCHANGED', 'good') ?>
        <?= opsui_badge('OPERATOR CONFIRMED', 'good') ?>
        <?= opsui_badge('DESKTOP SAVE ONLY', 'warn') ?>
    </div>
    <div class="actions">
        <a class="btn good" href="/ops/pre-ride-email-tool.php">Open Production Pre-Ride Tool</a>
        <a class="btn" href="/ops/pre-ride-email-toolv2.php">Open V2 Dev Wrapper</a>
        <a class="btn dark" href="/ops/safety-checklist.php">Open Safety Checklist</a>
    </div>
</section>

<section class="gov-admin-grid">
    <a class="gov-admin-link" href="/ops/pre-ride-email-tool.php"><strong>1. Production Pre-Ride Tool</strong><span>Use this for the live staff workflow when a future Bolt pre-ride email is received.</span></a>
    <a class="gov-admin-link" href="/ops/firefox-extensions-status.php"><strong>2. Confirm Helpers</strong><span>Both Firefox helper extensions should remain loaded until the future merged helper is tested.</span></a>
    <a class="gov-admin-link" href="/ops/mobile-compatibility.php"><strong>3. Mobile Rule</strong><span>Mobile/tablet use is allowed for review only. EDXEIX fill/save stays desktop/laptop Firefox.</span></a>
    <a class="gov-admin-link" href="/ops/pre-ride-mobile-review.php"><strong>4. Mobile Review</strong><span>Paste or load the latest email on mobile to check parsed fields and mapping readiness.</span></a>
    <a class="gov-admin-link" href="/ops/firefox-extension.php"><strong>5. Helper Center</strong><span>Download/reload the current helper package and inspect helper source file status.</span></a>
    <a class="gov-admin-link" href="/ops/profile.php"><strong>6. Profile Area</strong><span>Manage password, preferences, and personal account activity.</span></a>
</section>

<section class="card">
    <h2>Live ride procedure</h2>
    <ol class="timeline">
        <li>Open <strong>Production Pre-Ride Tool</strong>: <code>https://gov.cabnet.app/ops/pre-ride-email-tool.php</code>.</li>
        <li>Click <strong>Load latest server email + DB IDs</strong>, or paste the full Bolt pre-ride email and click <strong>Parse email + DB IDs</strong>.</li>
        <li>Confirm the page shows <strong>IDs READY</strong>. If it does not, stop and review mapping warnings.</li>
        <li>Click <strong>Save + open EDXEIX</strong> only for a real future ride.</li>
        <li>In Firefox on desktop/laptop, keep both helper extensions loaded and click the exact-ID fill button.</li>
        <li>Verify company/lessor, passenger, driver, vehicle, starting point, pickup, drop-off, start/end time, and price.</li>
        <li>Select the exact pickup point on the EDXEIX map before saving.</li>
        <li>Save/post only after operator review and only if the ride is still future and valid.</li>
    </ol>
</section>

<section class="two">
    <div class="card">
        <h2>Never submit</h2>
        <ul class="list">
            <li>Old, completed, cancelled, terminal, expired, or past rides.</li>
            <li>Unmapped or partially mapped rides.</li>
            <li>Rides with conflicting driver/vehicle lessor mapping.</li>
            <li>Rides where the map/default coordinates are unsafe or not manually selected.</li>
            <li>Any ride where the visible EDXEIX form does not match the Bolt pre-ride email.</li>
        </ul>
    </div>
    <div class="card">
        <h2>Development rule</h2>
        <ul class="list">
            <li><code>/ops/pre-ride-email-tool.php</code> is the production page.</li>
            <li><code>/ops/pre-ride-email-toolv2.php</code> is the safe development wrapper.</li>
            <li>Parser/helper changes should be tested in V2 or preview routes first.</li>
            <li>Only confirmed-safe changes should later be copied to the production route.</li>
        </ul>
    </div>
</section>
<?php opsui_shell_end(); ?>
