<?php
/**
 * gov.cabnet.app — Pre-Ride Email Tool V2 development wrapper
 *
 * Safe GUI development route.
 * Does not modify the production pre-ride tool and does not add submission behavior.
 */

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Robots-Tag: noindex, nofollow', true);

require_once __DIR__ . '/_shell.php';

opsui_shell_begin([
    'title' => 'Pre-Ride Tool V2 Dev',
    'page_title' => 'Bolt pre-ride email tool V2',
    'active_section' => 'Pre-Ride V2',
    'subtitle' => 'Safe GUI development wrapper for the live production tool',
    'breadcrumbs' => 'Αρχική / Operations / Pre-Ride Tool V2',
    'safe_notice' => 'Development wrapper only. The production /ops/pre-ride-email-tool.php file is not modified by this page.',
]);
?>
<section class="gov-dev-banner">
    <strong>V2 DEVELOPMENT ROUTE.</strong>
    This page gives us a safe place to build the uniform GUI around the current production workflow. The embedded tool below is still the production page, so operator behavior remains unchanged.
</section>

<section class="card hero warn">
    <h1>Pre-Ride Email Tool V2 wrapper</h1>
    <p>The live production tool remains at <code>/ops/pre-ride-email-tool.php</code>. This V2 page is for shell/layout development and user-profile integration before any production replacement.</p>
    <div>
        <?= opsui_badge('PRODUCTION TOOL UNTOUCHED', 'good') ?>
        <?= opsui_badge('NO DB WRITE ADDED', 'good') ?>
        <?= opsui_badge('NO EDXEIX AUTO-SUBMIT', 'good') ?>
        <?= opsui_badge('DEV WRAPPER', 'warn') ?>
    </div>
    <div class="actions">
        <a class="btn good" href="/ops/pre-ride-email-tool.php">Open Production Tool</a>
        <a class="btn" href="/ops/pre-ride-email-tool.php" target="_blank" rel="noopener">Open Production in New Tab</a>
        <a class="btn dark" href="/ops/profile.php">Operator Profile</a>
    </div>
</section>

<section class="card gov-embedded-tool-card">
    <div class="gov-embedded-tool-head">
        <h2>Embedded current production tool</h2>
        <div><?= opsui_badge('same live route', 'neutral') ?> <?= opsui_badge('operator must verify', 'warn') ?></div>
    </div>
    <iframe class="gov-embedded-tool-frame" src="/ops/pre-ride-email-tool.php" title="Production pre-ride email tool"></iframe>
</section>
<?php
opsui_shell_end();
