<?php
/**
 * gov.cabnet.app — Mobile compatibility notes.
 *
 * Read-only operator guidance page.
 * Does not call Bolt, does not call EDXEIX, and does not write data.
 */

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Robots-Tag: noindex,nofollow', true);

require_once __DIR__ . '/_shell.php';

opsui_shell_begin([
    'title' => 'Mobile Compatibility',
    'page_title' => 'Mobile compatibility',
    'active_section' => 'Mobile compatibility',
    'subtitle' => 'Safe guidance for mobile use of the pre-ride tool and Firefox helpers',
    'breadcrumbs' => 'Αρχική / Administration / Mobile compatibility',
    'safe_notice' => 'Read-only guidance page. It does not call Bolt, EDXEIX, or AADE, and does not write data.',
]);
?>
<section class="card hero neutral">
    <h1>Can the workflow run on mobile?</h1>
    <p>The safe answer for today: use a desktop or laptop for the full EDXEIX submission workflow. Mobile can help with review and communication, but the Firefox helper extensions are not approved as a production mobile workflow yet.</p>
    <div>
        <?= opsui_badge('WEB TOOL: PARTIAL MOBILE USE', 'warn') ?>
        <?= opsui_badge('FIREFOX HELPERS: DESKTOP REQUIRED TODAY', 'bad') ?>
        <?= opsui_badge('EDXEIX SUBMISSION: DESKTOP/LAPTOP', 'bad') ?>
    </div>
</section>

<section class="gov-mobile-decision-grid">
    <article class="card gov-mobile-decision gov-mobile-ok">
        <h2>Pre-ride email tool</h2>
        <p><strong>Status:</strong> mobile browser can be used for basic access/review.</p>
        <ul class="list">
            <li>Login should work from a mobile browser.</li>
            <li>Loading or pasting an email may work, depending on the phone/browser.</li>
            <li>Reviewing parsed passenger, driver, vehicle, route, and price data is suitable for mobile.</li>
            <li>Operator submission into EDXEIX should still be done from desktop/laptop.</li>
        </ul>
    </article>

    <article class="card gov-mobile-decision gov-mobile-no">
        <h2>Current Firefox helpers</h2>
        <p><strong>Status:</strong> not a production mobile workflow.</p>
        <ul class="list">
            <li>The current helpers are loaded as temporary desktop Firefox extensions.</li>
            <li>The helper behavior targets the desktop EDXEIX form and desktop browser tabs.</li>
            <li>Keep using the two loaded desktop helpers until we build and test a unified helper.</li>
            <li>Do not rely on a phone for the EDXEIX fill/save step.</li>
        </ul>
    </article>

    <article class="card gov-mobile-decision gov-mobile-warn">
        <h2>Future mobile path</h2>
        <p><strong>Status:</strong> possible only after a separate tested build.</p>
        <ul class="list">
            <li>Android would require an Android-compatible signed Firefox extension or a separate mobile web workflow.</li>
            <li>iPhone/iPad Firefox add-ons are not available for this type of helper.</li>
            <li>The safest future mobile option may be a server-side operator review page, not a browser extension.</li>
        </ul>
    </article>
</section>

<section class="card">
    <h2>Recommended operating rule</h2>
    <div class="gov-checklist-grid">
        <div class="gov-check-item good"><strong>Allowed</strong><span>Use mobile to view Ops Home, Profile, Activity, and read-only status pages.</span></div>
        <div class="gov-check-item warn"><strong>Use with care</strong><span>Use mobile to open the pre-ride email tool for review only.</span></div>
        <div class="gov-check-item bad"><strong>Do not use mobile</strong><span>Do not complete the EDXEIX helper fill/save workflow on mobile.</span></div>
        <div class="gov-check-item good"><strong>Production path</strong><span>Desktop/laptop Firefox with both helpers loaded remains the approved workflow.</span></div>
    </div>
</section>

<section class="two">
    <div class="card">
        <h2>Desktop production workflow</h2>
        <ol class="list">
            <li>Open the production pre-ride email tool.</li>
            <li>Load latest server email or paste the Bolt pre-ride email.</li>
            <li>Confirm IDs READY.</li>
            <li>Open EDXEIX in desktop Firefox.</li>
            <li>Use both loaded helpers.</li>
            <li>Verify every visible field.</li>
            <li>Select the exact EDXEIX map point.</li>
            <li>Submit only for a valid future ride.</li>
        </ol>
    </div>
    <div class="card">
        <h2>Mobile-safe usage</h2>
        <ol class="list">
            <li>Open Ops Home or My Activity.</li>
            <li>Check a ride or mapping status.</li>
            <li>Use the pre-ride tool only for review.</li>
            <li>Call/message staff with the result if needed.</li>
            <li>Move to desktop/laptop for actual EDXEIX fill/save.</li>
        </ol>
    </div>
</section>

<section class="card">
    <h2>Next development option</h2>
    <p>When the desktop workflow is fully stable, the safest mobile development path is to create a separate mobile review page for staff. That page can show “ready / not ready / missing mapping / expired / past ride” without trying to automate the EDXEIX website on a phone.</p>
    <div class="actions">
        <a class="btn" href="/ops/pre-ride-email-tool.php">Production Pre-Ride Tool</a>
        <a class="btn dark" href="/ops/firefox-extensions-status.php">Extension Pair Status</a>
        <a class="btn warn" href="/ops/pre-ride-email-toolv2.php">Pre-Ride V2 Dev</a>
    </div>
</section>
<?php opsui_shell_end(); ?>
