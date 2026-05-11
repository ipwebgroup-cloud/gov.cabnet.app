<?php
/**
 * gov.cabnet.app — shared ops UI shell preview v1.0
 *
 * Read-only design preview for new pages before migrating existing production tools.
 */

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Robots-Tag: noindex,nofollow', true);

require_once __DIR__ . '/_shell.php';

opsui_shell_begin([
    'title' => 'UI Shell Preview',
    'page_title' => 'Ενιαίο περιβάλλον λειτουργιών',
    'active_section' => 'UI Shell Preview',
    'subtitle' => 'Shared layout foundation for future /ops pages',
    'breadcrumbs' => 'Αρχική / Διαχειριστικό / UI Shell Preview',
    'safe_notice' => 'This preview page is read-only and exists so future pages can be migrated without touching the production pre-ride email tool.',
]);
?>
<section class="card hero neutral">
    <h1>Shared /ops GUI foundation</h1>
    <p>This is the common shell we can reuse for profile, administration, future v2 tools, and eventually selected operations pages after production testing.</p>
    <div>
        <?= opsui_badge('NO BOLT CALL', 'good') ?>
        <?= opsui_badge('NO EDXEIX CALL', 'good') ?>
        <?= opsui_badge('NO DB WRITE', 'good') ?>
        <?= opsui_badge('PRODUCTION TOOL UNTOUCHED', 'good') ?>
    </div>
    <div class="actions">
        <a class="btn good" href="/ops/profile.php">Open Profile</a>
        <a class="btn" href="/ops/home.php">Ops Home</a>
        <a class="btn dark" href="/ops/pre-ride-email-tool.php">Production Pre-Ride Tool</a>
    </div>
</section>

<section class="gov-admin-grid">
    <a class="gov-admin-link" href="/ops/profile.php"><strong>User profile section</strong><span>Current logged-in operator identity and future account actions.</span></a>
    <a class="gov-admin-link" href="/ops/firefox-extension.php"><strong>Firefox helper</strong><span>Authenticated helper download area for staff tools.</span></a>
    <a class="gov-admin-link" href="/ops/pre-ride-email-tool.php"><strong>Production pre-ride tool</strong><span>Operational page remains unchanged until a v2 is built and approved.</span></a>
</section>

<section class="two">
    <article class="card">
        <h2>Migration rule</h2>
        <ul class="list">
            <li>New pages should use <code>/ops/_shell.php</code>.</li>
            <li>Production pages are migrated only after a tested v2 version is confirmed safe.</li>
            <li><code>/ops/pre-ride-email-tool.php</code> remains the live production tool.</li>
        </ul>
    </article>
    <article class="card">
        <h2>Recommended next conversion</h2>
        <p>Create <code>/ops/pre-ride-email-toolv2.php</code> only when we are ready to test a redesigned workflow screen. The current patch intentionally avoids that production path.</p>
        <div class="gov-alert-note">No existing workflow behavior is changed by this preview page.</div>
    </article>
</section>
<?php opsui_shell_end(); ?>
