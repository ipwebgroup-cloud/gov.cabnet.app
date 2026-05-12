<?php
/**
 * gov.cabnet.app — Ops Handoff Center
 *
 * Copy/paste continuity page and admin-only safe handoff ZIP builder.
 * The ZIP builder excludes real config/secrets and writes sanitized placeholders.
 */

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Robots-Tag: noindex,nofollow', true);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/_shell.php';

$homeRoot = dirname(__DIR__, 3);
$builderFile = $homeRoot . '/gov.cabnet.app_app/src/Support/SafeHandoffPackageBuilder.php';
if (is_file($builderFile)) {
    require_once $builderFile;
}

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

function handoff_csrf_token(): string
{
    if (empty($_SESSION['handoff_center_csrf']) || !is_string($_SESSION['handoff_center_csrf'])) {
        $_SESSION['handoff_center_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['handoff_center_csrf'];
}

function handoff_validate_csrf(string $token): bool
{
    return isset($_SESSION['handoff_center_csrf'])
        && is_string($_SESSION['handoff_center_csrf'])
        && hash_equals($_SESSION['handoff_center_csrf'], $token);
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

Current mapping governance status:
- Mappings are a known failure point and now have their own governance pages.
- Important mapping routes:
  /ops/mapping-center.php
  /ops/company-mapping-control.php
  /ops/company-mapping-detail.php?lessor=1756
  /ops/starting-point-control.php
  /ops/mapping-health.php
  /ops/mapping-audit.php
  /ops/mapping-resolver-test.php
  /ops/mapping-exceptions.php
  /ops/mapping-verification.php
  /ops/mapping-control.php
  /ops/mappings.php
- WHITEBLUE / lessor 1756 was verified in live EDXEIX:
  driver Georgios Tsatsas → 4382
  vehicle XZO1837 → 4327
  starting point Ομβροδέκτης / Mykonos → 612164
- EdxeixMappingLookup now resolves lessor first, then prefers mapping_lessor_starting_points before global starting point fallback.
- Any lessor without a lessor-specific starting point override must be treated as a mapping risk.

Mobile development direction:
- Mobile must eventually be able to submit, otherwise it is not operationally complete.
- Do not rely on mobile Firefox extensions as the final architecture.
- Target architecture: authenticated mobile web app + controlled server-side EDXEIX submitter.
- Current mobile submit dev route: /ops/mobile-submit-dev.php
- Current server-side submit research routes:
  /ops/edxeix-submit-research.php
  /ops/edxeix-submit-capture.php
  /ops/edxeix-submit-dry-run.php
  /ops/edxeix-submit-preflight-gate.php
- No live server-side EDXEIX submit is enabled yet.

Current handoff package utility:
- /ops/handoff-center.php has an admin-only Safe Handoff ZIP builder.
- The ZIP should include live project files, a database SQL export, sanitized config placeholders, docs, and handoff files.
- The ZIP must be treated as private operational material because the database export can contain operational/customer data.
- Real config values are not included; sanitized examples are generated under gov.cabnet.app_config_examples/.

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
- Mobile/tablet can be used for review only until the server-side mobile submitter is complete.

Current shared GUI direction:
- A shared /ops shell has been added for the uniform EDXEIX-style operator GUI.
- User/profile pages, preferences, activity logs, system status, deployment center, guides, tool inventory, mapping governance, mobile dev, and submit research pages have been added.
- The production pre-ride tool remains intentionally stable unless a production hotfix is explicitly approved.

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

$downloadError = '';
$downloadNotice = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['action'] ?? '') === 'build_safe_handoff_zip') {
    try {
        if (!opsui_is_admin()) {
            http_response_code(403);
            echo 'Forbidden. Admin role required.';
            exit;
        }
        if (!handoff_validate_csrf((string)($_POST['csrf'] ?? ''))) {
            throw new RuntimeException('Security token expired. Please reload and try again.');
        }
        if (!class_exists(\Bridge\Support\SafeHandoffPackageBuilder::class)) {
            throw new RuntimeException('SafeHandoffPackageBuilder is not installed yet.');
        }

        $builder = new \Bridge\Support\SafeHandoffPackageBuilder([
            'homeRoot' => $homeRoot,
            'publicRoot' => $homeRoot . '/public_html/gov.cabnet.app',
            'appRoot' => $homeRoot . '/gov.cabnet.app_app',
            'configRoot' => $homeRoot . '/gov.cabnet.app_config',
            'sqlRoot' => $homeRoot . '/gov.cabnet.app_sql',
            'docsRoot' => $homeRoot . '/docs',
            'toolsRoot' => $homeRoot . '/tools',
            'bootstrap' => $homeRoot . '/gov.cabnet.app_app/src/bootstrap.php',
        ]);

        $zipPath = $builder->build(['include_database' => true]);
        $downloadName = 'gov_cabnet_safe_handoff_' . date('Ymd_His') . '.zip';

        header_remove('Content-Type');
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $downloadName . '"');
        header('Content-Length: ' . (string)filesize($zipPath));
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        readfile($zipPath);
        @unlink($zipPath);
        exit;
    } catch (Throwable $e) {
        $downloadError = $e->getMessage();
    }
}

$prompt = handoff_build_prompt();
if (strtolower((string)($_GET['format'] ?? '')) === 'text') {
    header('Content-Type: text/plain; charset=utf-8');
    echo $prompt;
    exit;
}

$publicRoot = dirname(__DIR__);
$appRoot = $homeRoot . '/gov.cabnet.app_app';
$configRoot = $homeRoot . '/gov.cabnet.app_config';
$sqlRoot = $homeRoot . '/gov.cabnet.app_sql';
$toolsRoot = $homeRoot . '/tools';

$checks = [
    'Production pre-ride tool' => $publicRoot . '/ops/pre-ride-email-tool.php',
    'Pre-ride V2 development route' => $publicRoot . '/ops/pre-ride-email-toolv2.php',
    'Shared ops shell' => $publicRoot . '/ops/_shell.php',
    'Ops auth prepend' => $publicRoot . '/_auth_prepend.php',
    'Safe handoff package builder' => $appRoot . '/src/Support/SafeHandoffPackageBuilder.php',
    'Private app bootstrap' => $appRoot . '/src/bootstrap.php',
    'OpsAuth class' => $appRoot . '/src/Auth/OpsAuth.php',
    'Pre-ride parser' => $appRoot . '/src/BoltMail/BoltPreRideEmailParser.php',
    'Mapping lookup' => $appRoot . '/src/BoltMail/EdxeixMappingLookup.php',
    'Maildir loader' => $appRoot . '/src/BoltMail/MaildirPreRideEmailLoader.php',
    'Server-only config directory' => $configRoot,
    'SQL directory' => $sqlRoot,
    'Tools directory' => $toolsRoot,
];

$csrf = handoff_csrf_token();
$builderInstalled = class_exists(\Bridge\Support\SafeHandoffPackageBuilder::class);

opsui_shell_begin([
    'title' => 'Handoff Center',
    'page_title' => 'Handoff Center',
    'active_section' => 'Deployment',
    'breadcrumbs' => 'Αρχική / Διαχειριστικό / Handoff Center',
    'safe_notice' => 'Continuity page and admin-only safe handoff ZIP builder. Real config values are never copied into the ZIP.',
]);
?>
<section class="card hero neutral">
    <h1>New session handoff</h1>
    <p>This page gives Andreas a clean, current copy/paste prompt and an admin-only safe handoff ZIP for continuity without exposing real server-only config values.</p>
    <div>
        <?= opsui_badge('PROMPT READY', 'good') ?>
        <?= opsui_badge('SAFE ZIP BUILDER', $builderInstalled ? 'good' : 'warn') ?>
        <?= opsui_badge('CONFIG SANITIZED', 'good') ?>
        <?= opsui_badge('NO EDXEIX CALL', 'good') ?>
    </div>
    <div class="actions">
        <a class="btn" href="/ops/handoff-center.php?format=text" target="_blank" rel="noopener">Open plain text</a>
        <a class="btn dark" href="/ops/deployment-center.php">Deployment Center</a>
        <a class="btn dark" href="/ops/system-status.php">System Status</a>
    </div>
</section>

<?php if ($downloadError !== ''): ?>
<section class="card" style="border-left:6px solid #b42318;">
    <h2>Safe ZIP builder error</h2>
    <p class="badline"><strong><?= opsui_h($downloadError) ?></strong></p>
    <p class="small">If this persists, run the PHP syntax checks and inspect the Apache/PHP error log. No package was downloaded.</p>
</section>
<?php endif; ?>

<section class="card">
    <h2>Safe handoff ZIP download</h2>
    <p>This creates a private handoff package from the live server layout. It includes project files, a database SQL export, documentation, and sanitized config placeholders. It excludes obvious logs, sessions, cache, mailboxes, temporary files, backups, archives, and real config values.</p>
    <div class="safety" style="margin:12px 0;">
        <strong>PRIVATE OPERATIONAL PACKAGE.</strong>
        The database export may contain operational/customer data. Download only as admin, keep it private, and do not commit the database export to GitHub unless intentionally sanitized.
    </div>
    <form method="post" action="/ops/handoff-center.php" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
        <input type="hidden" name="csrf" value="<?= opsui_h($csrf) ?>">
        <input type="hidden" name="action" value="build_safe_handoff_zip">
        <button class="btn green" type="submit" <?= (!$builderInstalled || !opsui_is_admin()) ? 'disabled' : '' ?>>Build / Download Safe Handoff ZIP</button>
        <?= opsui_is_admin() ? opsui_badge('ADMIN', 'good') : opsui_badge('ADMIN REQUIRED', 'warn') ?>
        <?= $builderInstalled ? opsui_badge('BUILDER INSTALLED', 'good') : opsui_badge('BUILDER MISSING', 'warn') ?>
    </form>
    <ul class="list" style="margin-top:14px;">
        <li>ZIP is generated in a temporary private server location and streamed to the browser.</li>
        <li>Real files from <code>gov.cabnet.app_config</code> are not copied; sanitized placeholders are generated under <code>gov.cabnet.app_config_examples/</code>.</li>
        <li>Database export is generated through the private app mysqli connection; no DB password is placed in the ZIP.</li>
        <li>No Bolt, EDXEIX, or AADE calls are made.</li>
    </ul>
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
        <h2>Package includes</h2>
        <ul class="list">
            <li><code>public_html/gov.cabnet.app/...</code></li>
            <li><code>gov.cabnet.app_app/...</code></li>
            <li><code>gov.cabnet.app_sql/...</code></li>
            <li><code>docs/...</code> when present</li>
            <li><code>tools/firefox*/...</code> and EDXEIX helper folders when present</li>
            <li><code>DATABASE_EXPORT.sql</code></li>
            <li><code>gov.cabnet.app_config_examples/...</code> sanitized placeholders only</li>
        </ul>
    </div>
    <div class="card">
        <h2>Package excludes</h2>
        <ul class="list">
            <li>Real server-only config values</li>
            <li>Logs, caches, sessions, mailboxes, temp files</li>
            <li>Backup/archive files such as zip/tar/gz/bak</li>
            <li>Private keys, cookie/session dumps, raw secret files</li>
            <li>Any live EDXEIX/Bolt/AADE interaction</li>
        </ul>
    </div>
</section>
<?php opsui_shell_end(); ?>
