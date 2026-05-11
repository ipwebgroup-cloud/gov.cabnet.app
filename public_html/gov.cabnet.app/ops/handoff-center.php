<?php
/**
 * gov.cabnet.app — Ops Handoff Center
 *
 * Read-only copy/paste continuity page for starting a new ChatGPT/Sophion session.
 * Does not read secrets, does not write data, and does not call external services.
 */

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Robots-Tag: noindex,nofollow', true);

require_once __DIR__ . '/_shell.php';

function handoff_safe_file_status(string $path): string
{
    if (!is_file($path)) {
        return 'missing';
    }
    if (!is_readable($path)) {
        return 'not readable';
    }
    return 'present';
}

function handoff_build_prompt(): string
{
    $generatedAt = date('Y-m-d H:i:s T');

    return <<<TEXT
You are Sophion assisting Andreas with the gov.cabnet.app Bolt → EDXEIX bridge project.

Generated from /ops/handoff-center.php at: {$generatedAt}

Project identity:
- Domain: https://gov.cabnet.app
- GitHub repo: https://github.com/ipwebgroup-cloud/gov.cabnet.app
- Stack: plain PHP, mysqli/MariaDB, cPanel/manual upload workflow.
- Do not introduce frameworks, Composer, Node build tools, or heavy dependencies unless Andreas explicitly approves.
- Expected server layout:
  /home/cabnet/public_html/gov.cabnet.app
  /home/cabnet/gov.cabnet.app_app
  /home/cabnet/gov.cabnet.app_config
  /home/cabnet/gov.cabnet.app_sql
  /home/cabnet/tools/firefox-edxeix-autofill-helper

Current operational priority:
- The live production tool is:
  https://gov.cabnet.app/ops/pre-ride-email-tool.php
- This page is production-critical and actively used by staff.
- Do not modify it directly unless Andreas explicitly asks for a production hotfix.
- Use /ops/pre-ride-email-toolv2.php for ongoing development and staged changes.

Current access/security state:
- The old IP-only restriction has been replaced by the ops login system.
- /ops/pre-ride-email-tool.php redirects unauthenticated users to /ops/login.php.
- Server-only config files under /home/cabnet/gov.cabnet.app_config must not be committed.
- Real config, API keys, DB passwords, tokens, cookies, sessions, AADE credentials, and raw private data must never be exposed or committed.

Critical safety rules:
- Do not enable automatic live EDXEIX submission unless Andreas explicitly asks.
- EDXEIX submission must remain operator-confirmed.
- Historical, cancelled, terminal, expired, invalid, duplicate, unmapped, or past Bolt orders must never be submitted to EDXEIX.
- Do not submit old test rides.
- Pre-ride email parsing must not issue AADE receipts.
- AADE receipt issuing remains separate from the pre-ride workflow.
- Prefer read-only, dry-run, preview, audit, queue visibility, and preflight behavior.

Current live workflow for staff:
1. Open https://gov.cabnet.app/ops/pre-ride-email-tool.php
2. Load latest server email or paste the Bolt pre-ride email.
3. Confirm IDs READY.
4. Click Save + open EDXEIX.
5. On EDXEIX page, keep both Firefox helper extensions loaded.
6. Click Fill using exact IDs.
7. Verify company/lessor, passenger, driver, vehicle, starting point, pickup, drop-off, start/end time, and price.
8. Click/select the exact pickup point on the EDXEIX map.
9. Submit only if the ride is real, future, mapped, and safe.
10. Never submit old/completed rides.

Current Firefox helper rule:
- Keep both temporary Firefox helper extensions loaded for now:
  1. Cabnet EDXEIX Session + Payload Fill
  2. Gov Cabnet EDXEIX Autofill Helper
- Future improvement: merge both into one signed/self-distributed XPI or enterprise-managed extension.
- Mobile/tablet can be used for review only; actual EDXEIX fill/save remains desktop/laptop Firefox.

Current shared GUI direction:
- A shared /ops shell has been added for the uniform EDXEIX-style operator GUI.
- User/profile pages, preferences, activity logs, system status, deployment center, guides, tool inventory, and mobile review pages have been added.
- The production pre-ride tool remains intentionally unchanged.

Important routes:
- /ops/home.php
- /ops/profile.php
- /ops/profile-edit.php
- /ops/profile-password.php
- /ops/profile-preferences.php
- /ops/profile-activity.php
- /ops/users-control.php
- /ops/activity-center.php
- /ops/audit-log.php
- /ops/login-attempts.php
- /ops/firefox-extension.php
- /ops/firefox-extensions-status.php
- /ops/mobile-compatibility.php
- /ops/pre-ride-mobile-review.php
- /ops/workflow-guide.php
- /ops/safety-checklist.php
- /ops/tool-inventory.php
- /ops/system-status.php
- /ops/deployment-center.php
- /ops/handoff-center.php

Development workflow:
1. Code with ChatGPT/Sophion.
2. Download zip patch.
3. Extract into local GitHub Desktop repo.
4. Upload manually to server.
5. Test on server.
6. Commit via GitHub Desktop after production confirmation.

Patch packaging rule:
- Zip root must mirror live/repo structure directly.
- Do not wrap files in an extra package folder.
- Include only changed/added files unless Andreas asks for a full archive.

Expected response style:
- Be direct, practical, implementation-focused.
- Show exact upload paths, SQL, verification commands, expected results, and commit title/description.
- Preserve plain PHP/mysqli/cPanel/manual upload workflow.
- Keep production pre-ride tool stable unless a production hotfix is explicitly requested.
TEXT;
}

$prompt = handoff_build_prompt();
if (strtolower((string)($_GET['format'] ?? '')) === 'text') {
    header('Content-Type: text/plain; charset=utf-8');
    echo $prompt;
    exit;
}

$publicRoot = dirname(__DIR__);
$appRoot = dirname(__DIR__, 3) . '/gov.cabnet.app_app';
$configRoot = dirname(__DIR__, 3) . '/gov.cabnet.app_config';
$sqlRoot = dirname(__DIR__, 3) . '/gov.cabnet.app_sql';

$checks = [
    'Production pre-ride tool' => $publicRoot . '/ops/pre-ride-email-tool.php',
    'Pre-ride V2 development route' => $publicRoot . '/ops/pre-ride-email-toolv2.php',
    'Shared ops shell' => $publicRoot . '/ops/_shell.php',
    'Ops auth prepend' => $publicRoot . '/_auth_prepend.php',
    'Private app bootstrap' => $appRoot . '/src/bootstrap.php',
    'OpsAuth class' => $appRoot . '/src/Auth/OpsAuth.php',
    'Pre-ride parser' => $appRoot . '/src/BoltMail/BoltPreRideEmailParser.php',
    'Mapping lookup' => $appRoot . '/src/BoltMail/EdxeixMappingLookup.php',
    'Maildir loader' => $appRoot . '/src/BoltMail/MaildirPreRideEmailLoader.php',
    'Server-only config directory' => $configRoot,
    'SQL directory' => $sqlRoot,
];

opsui_shell_begin([
    'title' => 'Handoff Center',
    'page_title' => 'Handoff Center',
    'active_section' => 'Deployment',
    'breadcrumbs' => 'Αρχική / Διαχειριστικό / Handoff Center',
    'safe_notice' => 'Read-only continuity page. It generates copy/paste handoff text and does not read or display secrets.',
]);
?>
<section class="card hero neutral">
    <h1>New session handoff</h1>
    <p>This page gives Andreas a clean, current copy/paste prompt for opening a new Sophion session without exposing credentials or private config.</p>
    <div>
        <?= opsui_badge('READ ONLY', 'good') ?>
        <?= opsui_badge('NO SECRET OUTPUT', 'good') ?>
        <?= opsui_badge('NO DB WRITE', 'good') ?>
        <?= opsui_badge('NO EDXEIX CALL', 'good') ?>
    </div>
    <div class="actions">
        <a class="btn" href="/ops/handoff-center.php?format=text" target="_blank" rel="noopener">Open plain text</a>
        <a class="btn dark" href="/ops/deployment-center.php">Deployment Center</a>
        <a class="btn dark" href="/ops/system-status.php">System Status</a>
    </div>
</section>

<section class="card">
    <h2>Copy/paste prompt</h2>
    <p class="small">Copy this into a new ChatGPT/Sophion session when continuing the gov.cabnet.app project.</p>
    <textarea readonly style="width:100%;min-height:560px;border:1px solid #d8dde7;border-radius:4px;padding:14px;font-family:Consolas,Menlo,monospace;font-size:13px;line-height:1.45;white-space:pre-wrap;"><?= opsui_h($prompt) ?></textarea>
</section>

<section class="card">
    <h2>Safe file presence check</h2>
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>Item</th><th>Status</th><th>Path</th></tr>
            </thead>
            <tbody>
            <?php foreach ($checks as $label => $path): ?>
                <?php $status = is_dir($path) ? 'present' : handoff_safe_file_status($path); ?>
                <tr>
                    <td><strong><?= opsui_h($label) ?></strong></td>
                    <td><?= opsui_badge(strtoupper($status), $status === 'present' ? 'good' : 'warn') ?></td>
                    <td><code><?= opsui_h($path) ?></code></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="two">
    <div class="card">
        <h2>Production boundary</h2>
        <ul class="list">
            <li><strong>/ops/pre-ride-email-tool.php</strong> is live production and should not be changed casually.</li>
            <li>Use <strong>/ops/pre-ride-email-toolv2.php</strong> for future GUI or workflow experiments.</li>
            <li>Any EDXEIX save action remains operator-confirmed and desktop/laptop Firefox only.</li>
        </ul>
    </div>
    <div class="card">
        <h2>Recommended next safe work</h2>
        <ul class="list">
            <li>Keep improving shared-shell pages around the production tool.</li>
            <li>Build V2 workflow in parallel without touching the live production route.</li>
            <li>Later merge the two Firefox helpers into one signed/update-capable helper.</li>
        </ul>
    </div>
</section>
<?php opsui_shell_end(); ?>
