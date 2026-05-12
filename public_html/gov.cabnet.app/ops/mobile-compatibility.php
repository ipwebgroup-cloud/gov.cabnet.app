<?php
/**
 * gov.cabnet.app — Mobile Compatibility Working Solution v1.1
 *
 * Read-only planning/operations page.
 * No Bolt calls, no EDXEIX calls, no AADE calls, no DB writes.
 */

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Robots-Tag: noindex,nofollow', true);

$shell = __DIR__ . '/_shell.php';
if (!is_file($shell)) {
    http_response_code(500);
    echo 'Shared ops shell not found.';
    exit;
}
require_once $shell;

function mc_file_status(string $path): array
{
    $abs = __DIR__ . '/' . ltrim($path, '/');
    return [
        'path' => $path,
        'exists' => is_file($abs),
        'mtime' => is_file($abs) ? date('Y-m-d H:i:s', (int)filemtime($abs)) : '',
        'size' => is_file($abs) ? number_format((int)filesize($abs)) . ' bytes' : '',
    ];
}

$routes = [
    mc_file_status('/pre-ride-email-tool.php'),
    mc_file_status('/pre-ride-mobile-review.php'),
    mc_file_status('/firefox-extension.php'),
    mc_file_status('/firefox-extensions-status.php'),
    mc_file_status('/workflow-guide.php'),
    mc_file_status('/safety-checklist.php'),
];

opsui_shell_begin([
    'title' => 'Mobile Compatibility',
    'page_title' => 'Mobile Compatibility',
    'active_section' => 'Mobile',
    'breadcrumbs' => 'Αρχική / Mobile / Compatibility',
    'safe_notice' => 'Read-only mobile compatibility design. This page does not call Bolt, EDXEIX, or AADE, and does not write data.',
]);
?>

<section class="card hero neutral">
    <h1>Mobile Compatibility — Working Solution</h1>
    <p>The safest production-ready mobile strategy is <strong>mobile review + desktop handoff</strong>. Phones/tablets can review and prepare the ride, but the final EDXEIX form fill/save remains on desktop/laptop Firefox with the helper extensions.</p>
    <div>
        <?= opsui_badge('MOBILE REVIEW YES', 'good') ?>
        <?= opsui_badge('MOBILE EDXEIX SAVE NO', 'warn') ?>
        <?= opsui_badge('DESKTOP FINAL SAVE', 'good') ?>
        <?= opsui_badge('NO WORKFLOW WRITES HERE', 'good') ?>
    </div>
</section>

<section class="gov-admin-grid">
    <article class="card">
        <h2>Current mobile-safe use</h2>
        <p>Use a phone or tablet to sign in, open the mobile review page, load/paste the Bolt pre-ride email, and verify passenger, driver, vehicle, route, time, price, and mapping readiness.</p>
        <div class="actions">
            <a class="btn good" href="/ops/pre-ride-mobile-review.php">Open Mobile Review</a>
            <a class="btn dark" href="/ops/workflow-guide.php">Workflow Guide</a>
        </div>
    </article>
    <article class="card">
        <h2>Desktop-required step</h2>
        <p>Final EDXEIX fill/save must remain on desktop/laptop Firefox because today’s workflow depends on our temporary helper extensions and operator map-point review.</p>
        <div class="actions">
            <a class="btn" href="/ops/firefox-extension.php">Helper Center</a>
            <a class="btn dark" href="/ops/firefox-extensions-status.php">Helper Status</a>
        </div>
    </article>
    <article class="card">
        <h2>Recommended solution</h2>
        <p>Build a short-lived <strong>Mobile → Desktop Handoff</strong>: mobile creates a reviewed handoff token; desktop opens that token and launches the existing EDXEIX helper workflow.</p>
        <div class="actions">
            <a class="btn warn" href="#handoff-design">View Design</a>
            <a class="btn dark" href="#phase-plan">Phase Plan</a>
        </div>
    </article>
</section>

<section id="handoff-design" class="card">
    <h2>Proposed working solution: Mobile → Desktop Handoff</h2>
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>Stage</th><th>Device</th><th>What happens</th><th>Safety boundary</th></tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong>1. Review</strong></td>
                    <td>Mobile</td>
                    <td>Operator opens <code>/ops/pre-ride-mobile-review.php</code>, loads latest email or pastes email, confirms parsed fields and IDs.</td>
                    <td>No EDXEIX call. No AADE call. No submit button.</td>
                </tr>
                <tr>
                    <td><strong>2. Create handoff</strong></td>
                    <td>Mobile</td>
                    <td>Operator clicks “Create Desktop Handoff”. Server stores only parsed fields and EDXEIX IDs, not raw email, with a short expiry.</td>
                    <td>Requires login. Random token. Expires automatically. Past/unsafe rides marked blocked.</td>
                </tr>
                <tr>
                    <td><strong>3. Open handoff</strong></td>
                    <td>Desktop/laptop</td>
                    <td>Operator opens <code>/ops/desktop-handoff.php?t=TOKEN</code>, reviews payload, then opens EDXEIX.</td>
                    <td>Still operator-reviewed. Still no automatic live submit.</td>
                </tr>
                <tr>
                    <td><strong>4. Fill and save</strong></td>
                    <td>Desktop/laptop Firefox</td>
                    <td>Both helper extensions fill the EDXEIX form using exact IDs. Operator selects exact map point and saves only if future/safe.</td>
                    <td>Existing helper POST/map/future guards remain in force.</td>
                </tr>
            </tbody>
        </table>
    </div>
</section>

<section class="two">
    <div class="card">
        <h2>Why not full mobile EDXEIX save today?</h2>
        <ul class="list">
            <li>Current Firefox helpers are desktop temporary extensions.</li>
            <li>iPhone/iPad cannot run Firefox desktop/Android add-ons.</li>
            <li>Android support requires an Android-compatible packaged/signed add-on and separate testing.</li>
            <li>The EDXEIX map point must still be deliberately selected/reviewed by the operator.</li>
        </ul>
    </div>
    <div class="card">
        <h2>What mobile can safely do today</h2>
        <ul class="list">
            <li>Login to ops.</li>
            <li>Review parsed ride data.</li>
            <li>Confirm IDs READY or identify mapping issues.</li>
            <li>Confirm whether the ride is future/too soon/past.</li>
            <li>Prepare handoff for desktop final submission after the next phase is built.</li>
        </ul>
    </div>
</section>

<section id="phase-plan" class="card">
    <h2>Implementation phase plan</h2>
    <div class="timeline">
        <li><strong>Phase A — Today:</strong> keep mobile review read-only and document the operating model. Production pre-ride tool remains unchanged.</li>
        <li><strong>Phase B — Add SQL:</strong> create <code>ops_mobile_handoffs</code> with parsed payload JSON, token hash, creator, expiry, status, and audit timestamps.</li>
        <li><strong>Phase C — Mobile create handoff:</strong> add a guarded button to mobile review to store a reviewed handoff. Store parsed fields only; do not store raw email.</li>
        <li><strong>Phase D — Desktop handoff page:</strong> add <code>/ops/desktop-handoff.php</code> to load token, display payload, and launch EDXEIX helper flow.</li>
        <li><strong>Phase E — Cleanup:</strong> add expiry/revoke controls and a read-only handoff log.</li>
        <li><strong>Phase F — Future optional:</strong> build one signed helper extension, then investigate Android-compatible release separately.</li>
    </div>
</section>

<section class="card">
    <h2>Safety rules for mobile compatibility</h2>
    <div class="gov-admin-grid">
        <div class="gov-admin-link"><strong>No raw email storage</strong><span>Only parsed operational fields should be stored for handoff, with short retention.</span></div>
        <div class="gov-admin-link"><strong>No mobile submit</strong><span>Mobile can review/prepare only. EDXEIX save remains desktop until separately tested.</span></div>
        <div class="gov-admin-link"><strong>Token expiry</strong><span>Handoff tokens should expire quickly, recommended 2 hours.</span></div>
        <div class="gov-admin-link"><strong>Operator ownership</strong><span>Only the creator and admins should view/revoke a handoff.</span></div>
        <div class="gov-admin-link"><strong>Past rides blocked</strong><span>Past/terminal/unsafe rides must never become submit-ready.</span></div>
        <div class="gov-admin-link"><strong>Audit visible</strong><span>Handoff create/open/revoke events should be visible in the Activity pages.</span></div>
    </div>
</section>

<section class="card">
    <h2>Current supporting routes</h2>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Route file</th><th>Status</th><th>Modified</th><th>Size</th></tr></thead>
            <tbody>
                <?php foreach ($routes as $r): ?>
                    <tr>
                        <td><code><?= opsui_h($r['path']) ?></code></td>
                        <td><?= opsui_bool_badge((bool)$r['exists'], 'EXISTS', 'MISSING') ?></td>
                        <td><?= opsui_h($r['mtime'] ?: '-') ?></td>
                        <td><?= opsui_h($r['size'] ?: '-') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="card">
    <h2>Next build target</h2>
    <p>The next practical patch should be <strong>Mobile Handoff Phase B</strong>: additive SQL table only, plus a read-only preview page for the future handoff queue. After that we can add the mobile “Create Handoff” button.</p>
    <div class="actions">
        <a class="btn good" href="/ops/pre-ride-mobile-review.php">Mobile Review</a>
        <a class="btn" href="/ops/safety-checklist.php">Safety Checklist</a>
        <a class="btn dark" href="/ops/documentation-center.php">Documentation Center</a>
    </div>
</section>

<?php opsui_shell_end(); ?>
