<?php
/**
 * gov.cabnet.app — Ops Handoff Center
 *
 * Copy/paste continuity page and admin-only handoff ZIP workflows.
 * The ZIP builder excludes real config/secrets and writes sanitized placeholders.
 *
 * V3 alignment: v3.0.75 closed-gate live adapter contract test verified.
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

const GOV_HANDOFF_CENTER_VERSION = 'v3.0.76-v3-handoff-center-alignment';
const GOV_HANDOFF_CURRENT_MILESTONE = 'v3.0.75 live adapter contract test production-verified';
const GOV_HANDOFF_QUEUE_ID = 716;
const GOV_HANDOFF_PAYLOAD_HASH = 'e784e788532fc57824a46dad90debec9d0ad5a24f94679538c37d1d164e9f472';

function handoff_safe_file_status(string $path): string
{
    if (is_dir($path)) {
        return is_readable($path) ? 'present' : 'not readable';
    }
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
    $version = GOV_HANDOFF_CENTER_VERSION;
    $milestone = GOV_HANDOFF_CURRENT_MILESTONE;
    $queueId = (string)GOV_HANDOFF_QUEUE_ID;
    $payloadHash = GOV_HANDOFF_PAYLOAD_HASH;

    return <<<TEXT
You are Sophion assisting Andreas with the gov.cabnet.app Bolt → EDXEIX bridge project.

Generated from /ops/handoff-center.php at: {$generatedAt}
Handoff Center version: {$version}

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
- Live server is not a cloned Git repo.
- Workflow: ChatGPT/Sophion patch ZIP → local GitHub Desktop repo → manual server upload → live test → GitHub Desktop commit.

Source-of-truth order:
1. Latest uploaded files, pasted code, screenshots, SQL output, or live audit output in the current chat.
2. HANDOFF.md and CONTINUE_PROMPT.md.
3. README.md, SCOPE.md, DEPLOYMENT.md, SECURITY.md, docs/, and PROJECT_FILE_MANIFEST.md.
4. GitHub repo.
5. Prior memory/context only as background, never as proof of current code state.

Current V3 milestone:
- {$milestone}
- Latest validated canary queue row: #{$queueId}
- Queue status at validation: live_submit_ready
- Payload hash: {$payloadHash}
- Live operator console verified behind ops login.
- Live adapter contract test verified with ok=true and contract_safe=true.
- Adapter remains edxeix_live_skeleton and is_live_capable=false.

Current V3 safety posture:
- Live EDXEIX gate remains closed.
- Config path: /home/cabnet/gov.cabnet.app_config/pre_ride_email_v3_live_submit.php
- Expected config posture:
  enabled=false
  mode=disabled
  adapter=disabled
  hard_enable_live_submit=false
- No Bolt call was made by the V3 proof/contract tools.
- No EDXEIX call was made.
- No AADE call was made.
- No DB writes were made by read-only proof/contract tools.
- No V0 workflow was touched.
- Queue row #716 remains unsubmitted and not failed.

Latest V3 verification commands:
php -l /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_live_adapter_contract_test.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-live-adapter-contract-test.php
curl -I https://gov.cabnet.app/ops/pre-ride-email-v3-live-adapter-contract-test.php
su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_live_adapter_contract_test.php --queue-id=716 --json"
su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_live_gate_drift_guard.php --json"
su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_pre_live_switchboard.php --queue-id=716 --json"
su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_adapter_payload_consistency.php --queue-id=716 --json"
su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_pre_live_proof_bundle_export.php --queue-id=716 --write"

Important V3 routes:
- /ops/pre-ride-email-v3-live-operator-console.php
- /ops/pre-ride-email-v3-live-adapter-contract-test.php
- /ops/pre-ride-email-v3-live-gate-drift-guard.php
- /ops/pre-ride-email-v3-pre-live-switchboard.php
- /ops/pre-ride-email-v3-adapter-payload-consistency.php
- /ops/pre-ride-email-v3-adapter-row-simulation.php
- /ops/pre-ride-email-v3-pre-live-proof-bundle-export.php

Important V3 private files:
- /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_live_adapter_contract_test.php
- /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_live_gate_drift_guard.php
- /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_pre_live_switchboard.php
- /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_adapter_payload_consistency.php
- /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_adapter_row_simulation.php
- /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_pre_live_proof_bundle_export.php
- /home/cabnet/gov.cabnet.app_app/src/BoltMailV3/LiveSubmitAdapterV3.php
- /home/cabnet/gov.cabnet.app_app/src/BoltMailV3/DisabledLiveSubmitAdapterV3.php
- /home/cabnet/gov.cabnet.app_app/src/BoltMailV3/DryRunLiveSubmitAdapterV3.php
- /home/cabnet/gov.cabnet.app_app/src/BoltMailV3/EdxeixLiveSubmitAdapterV3.php

Critical safety rules:
- Default to read-only, dry-run, preview, audit, queue visibility, and preflight behavior.
- Do not enable live EDXEIX submission unless Andreas explicitly asks for a live-submit update.
- Live submission must remain blocked unless there is a real eligible future Bolt trip, preflight passes, and the trip is sufficiently in the future.
- Historical, cancelled, terminal, expired, invalid, duplicate, unmapped, or past Bolt orders must never be submitted to EDXEIX.
- V0 / existing production workflows must remain untouched unless Andreas explicitly requests otherwise.
- Never request or expose real API keys, DB passwords, tokens, cookies, session files, private credentials, AADE credentials, or real EDXEIX credentials.
- Config examples may be committed; real config files must remain server-only and ignored by Git.
- Sanitized patch zips must exclude secrets, logs, sessions, raw data dumps, mailboxes, cache files, storage artifacts, generated proof bundles, generated handoff packages, and temporary public diagnostic scripts unless explicitly needed and safe.

Handoff package rules:
- /ops/handoff-center.php has two package modes:
  1. Private Operational ZIP: may include DATABASE_EXPORT.sql; admin-only; never commit to GitHub.
  2. Git-Safe Continuity ZIP: DB-free; admin-only; intended for local repo continuity review; validate before commit.
- Treat storage artifacts under /home/cabnet/gov.cabnet.app_app/storage/artifacts as private operational evidence. Do not include them in Git commits.
- The grep output from storage/artifacts can include customer/trip/email data. Do not paste or commit raw artifacts unless intentionally sanitized.

Current production V0 / staff workflow:
- Production tool: https://gov.cabnet.app/ops/pre-ride-email-tool.php
- Do not modify production V0 directly unless Andreas explicitly asks for a production hotfix.
- Use separate V3 routes for staged work.
- Current Firefox helper workflow may remain in place while V3 closed-gate readiness continues.

Mapping governance reminder:
- Lessors, drivers, vehicles, and starting points are safety-critical.
- Any lessor without lessor-specific starting point verification is a mapping risk.
- Before any live submit work, verify lessor, driver, vehicle, starting point, route, time, price, and map point.

Development workflow:
1. Inspect first, patch second.
2. Prefer small, production-safe patches over rewrites.
3. Preserve existing routes, filenames, includes, database compatibility, and cPanel paths.
4. Keep public endpoints thin; reusable logic belongs in /home/cabnet/gov.cabnet.app_app.
5. Use mysqli prepared statements, defensive input validation, output escaping, and clear error handling.
6. For every patch/update provide: changed files, exact upload paths, SQL, verification commands, expected result, commit title, and commit description.

Recommended next safest step:
Improve V3 closed-gate operator visibility and Git-safe package hygiene. Do not enable live submission.
TEXT;
}

function handoff_append_git_safe_zip_notice(string $zipPath): void
{
    if (!class_exists(ZipArchive::class) || !is_file($zipPath) || !is_writable($zipPath)) {
        return;
    }

    $zip = new ZipArchive();
    if ($zip->open($zipPath) !== true) {
        return;
    }

    if ($zip->locateName('DATABASE_EXPORT.sql') !== false) {
        $zip->deleteName('DATABASE_EXPORT.sql');
    }

    $notice = <<<MD
# Git-Safe Continuity Package Notice

This package was built from `/ops/handoff-center.php` using DB-free mode.

Expected safety posture:

- `DATABASE_EXPORT.sql` is not included.
- Real files from `gov.cabnet.app_config/` are not included.
- Sanitized placeholders are generated under `gov.cabnet.app_config_examples/`.
- Runtime storage artifacts, proof bundles, logs, sessions, cache, mailboxes, archives, and temporary data are excluded by the builder.
- No Bolt, EDXEIX, or AADE calls are made by the package builder.

Before committing to GitHub, still inspect the ZIP tree and validate it with the package validator.
MD;

    $zip->addFromString('GIT_SAFE_CONTINUITY_NOTICE.md', $notice);
    $zip->close();
}

function handoff_stream_zip(string $zipPath, string $downloadName): void
{
    header_remove('Content-Type');
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $downloadName . '"');
    header('Content-Length: ' . (string)filesize($zipPath));
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    readfile($zipPath);
    @unlink($zipPath);
}

$downloadError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array((string)($_POST['action'] ?? ''), ['build_private_operational_zip', 'build_git_safe_continuity_zip'], true)) {
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

        $action = (string)$_POST['action'];
        if ($action === 'build_git_safe_continuity_zip') {
            $zipPath = $builder->build(['include_database' => false]);
            handoff_append_git_safe_zip_notice($zipPath);
            handoff_stream_zip($zipPath, 'gov_cabnet_git_safe_continuity_' . date('Ymd_His') . '.zip');
            exit;
        }

        $zipPath = $builder->build(['include_database' => true]);
        handoff_stream_zip($zipPath, 'gov_cabnet_private_operational_handoff_' . date('Ymd_His') . '.zip');
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
    'Production pre-ride tool / V0' => $publicRoot . '/ops/pre-ride-email-tool.php',
    'Handoff Center' => $publicRoot . '/ops/handoff-center.php',
    'Shared ops shell' => $publicRoot . '/ops/_shell.php',
    'Safe handoff package builder' => $appRoot . '/src/Support/SafeHandoffPackageBuilder.php',
    'Safe handoff package validator' => $appRoot . '/src/Support/SafeHandoffPackageValidator.php',
    'CLI handoff package builder' => $appRoot . '/cli/build_safe_handoff_package.php',
    'CLI handoff package validator' => $appRoot . '/cli/validate_safe_handoff_package.php',
    'V3 live operator console' => $publicRoot . '/ops/pre-ride-email-v3-live-operator-console.php',
    'V3 live adapter contract test page' => $publicRoot . '/ops/pre-ride-email-v3-live-adapter-contract-test.php',
    'V3 live adapter contract test CLI' => $appRoot . '/cli/pre_ride_email_v3_live_adapter_contract_test.php',
    'V3 live gate drift guard CLI' => $appRoot . '/cli/pre_ride_email_v3_live_gate_drift_guard.php',
    'V3 pre-live switchboard CLI' => $appRoot . '/cli/pre_ride_email_v3_pre_live_switchboard.php',
    'V3 payload consistency CLI' => $appRoot . '/cli/pre_ride_email_v3_adapter_payload_consistency.php',
    'V3 proof bundle exporter CLI' => $appRoot . '/cli/pre_ride_email_v3_pre_live_proof_bundle_export.php',
    'V3 LiveSubmitAdapter interface' => $appRoot . '/src/BoltMailV3/LiveSubmitAdapterV3.php',
    'V3 EDXEIX live skeleton adapter' => $appRoot . '/src/BoltMailV3/EdxeixLiveSubmitAdapterV3.php',
    'Server-only config directory' => $configRoot,
    'SQL directory' => $sqlRoot,
    'Tools directory' => $toolsRoot,
];

$v3Links = [
    'Live Operator Console' => '/ops/pre-ride-email-v3-live-operator-console.php?queue_id=716',
    'Live Adapter Contract Test' => '/ops/pre-ride-email-v3-live-adapter-contract-test.php?queue_id=716',
    'Live Gate Drift Guard' => '/ops/pre-ride-email-v3-live-gate-drift-guard.php',
    'Pre-Live Switchboard' => '/ops/pre-ride-email-v3-pre-live-switchboard.php?queue_id=716',
    'Payload Consistency' => '/ops/pre-ride-email-v3-adapter-payload-consistency.php?queue_id=716',
    'Adapter Row Simulation' => '/ops/pre-ride-email-v3-adapter-row-simulation.php?queue_id=716',
    'Proof Bundle Export' => '/ops/pre-ride-email-v3-pre-live-proof-bundle-export.php?queue_id=716',
];

$csrf = handoff_csrf_token();
$builderInstalled = class_exists(\Bridge\Support\SafeHandoffPackageBuilder::class);

opsui_shell_begin([
    'title' => 'Handoff Center',
    'page_title' => 'Handoff Center',
    'active_section' => 'Deployment',
    'breadcrumbs' => 'Αρχική / Διαχειριστικό / Handoff Center',
    'safe_notice' => 'SAFE OPS SHELL. Updated for V3 closed-gate milestone v3.0.75. Live EDXEIX submit remains disabled. Private packages and Git-safe continuity packages are separated.',
]);
?>
<section class="card hero neutral">
    <h1>New session handoff</h1>
    <p>This page gives Andreas a current copy/paste prompt and admin-only handoff ZIP workflows without exposing real server-only config values.</p>
    <div>
        <?= opsui_badge('PROMPT READY', 'good') ?>
        <?= opsui_badge('V3.0.75 VERIFIED', 'good') ?>
        <?= opsui_badge('LIVE GATE CLOSED', 'good') ?>
        <?= opsui_badge('NO EDXEIX CALL', 'good') ?>
        <?= opsui_badge('NO AADE CALL', 'good') ?>
        <?= opsui_badge('V0 UNTOUCHED', 'good') ?>
    </div>
    <div class="actions">
        <a class="btn" href="/ops/handoff-center.php?format=text" target="_blank" rel="noopener">Open plain text</a>
        <a class="btn dark" href="/ops/handoff-package-tools.php">Package Tools</a>
        <a class="btn dark" href="/ops/handoff-package-archive.php">Package Archive</a>
        <a class="btn dark" href="/ops/handoff-package-validator.php">Package Validator</a>
        <a class="btn dark" href="/ops/deployment-center.php">Deployment Center</a>
        <a class="btn dark" href="/ops/system-status.php">System Status</a>
    </div>
</section>

<section class="card" style="border-left:6px solid #2f6fdd;">
    <h2>Current V3 milestone</h2>
    <p><strong><?= opsui_h(GOV_HANDOFF_CURRENT_MILESTONE) ?></strong></p>
    <div class="grid two-col" style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;">
        <div class="safety"><strong>Queue row:</strong> #<?= (int)GOV_HANDOFF_QUEUE_ID ?> — closed-gate canary, live_submit_ready at validation</div>
        <div class="safety"><strong>Payload hash:</strong> <code><?= opsui_h(GOV_HANDOFF_PAYLOAD_HASH) ?></code></div>
        <div class="safety"><strong>Adapter:</strong> edxeix_live_skeleton, is_live_capable=false</div>
        <div class="safety"><strong>Gate:</strong> enabled=false, mode=disabled, adapter=disabled, hard_enable_live_submit=false</div>
    </div>
    <ul class="list" style="margin-top:14px;">
        <li>Contract test passed with <code>ok=true</code>, <code>contract_safe=true</code>, and <code>final_blocks=[]</code>.</li>
        <li>The contract test does not call adapter <code>submit()</code>, does not open network access, and does not write to the database.</li>
        <li>Storage proof bundles may contain operational/customer data and must remain private.</li>
    </ul>
</section>

<?php if ($downloadError !== ''): ?>
<section class="card" style="border-left:6px solid #b42318;">
    <h2>ZIP builder error</h2>
    <p class="badline"><strong><?= opsui_h($downloadError) ?></strong></p>
    <p class="small">No package was downloaded.</p>
</section>
<?php endif; ?>

<section class="two">
    <div class="card">
        <h2>Private Operational ZIP</h2>
        <p>This package may include <code>DATABASE_EXPORT.sql</code>. Use it only for private server continuity/recovery. Do not commit it to GitHub.</p>
        <div class="safety" style="margin:12px 0;">
            <strong>PRIVATE OPERATIONAL PACKAGE.</strong> Database export may contain customer, trip, email, queue, and operational data.
        </div>
        <form method="post" action="/ops/handoff-center.php" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
            <input type="hidden" name="csrf" value="<?= opsui_h($csrf) ?>">
            <input type="hidden" name="action" value="build_private_operational_zip">
            <button class="btn green" type="submit" <?= (!$builderInstalled || !opsui_is_admin()) ? 'disabled' : '' ?>>Build Private Operational ZIP</button>
            <?= opsui_is_admin() ? opsui_badge('ADMIN', 'good') : opsui_badge('ADMIN REQUIRED', 'warn') ?>
            <?= $builderInstalled ? opsui_badge('BUILDER INSTALLED', 'good') : opsui_badge('BUILDER MISSING', 'warn') ?>
        </form>
        <ul class="list" style="margin-top:14px;">
            <li>Includes database export when builder succeeds.</li>
            <li>Real config files are replaced with sanitized placeholders.</li>
            <li>No Bolt, EDXEIX, or AADE calls are made.</li>
        </ul>
    </div>

    <div class="card">
        <h2>Git-Safe Continuity ZIP</h2>
        <p>This package is DB-free and intended for local repo continuity review before committing via GitHub Desktop.</p>
        <div class="safety" style="margin:12px 0;">
            <strong>DB-FREE PACKAGE.</strong> Still validate the ZIP before commit and inspect for temporary diagnostics or operational files.
        </div>
        <form method="post" action="/ops/handoff-center.php" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
            <input type="hidden" name="csrf" value="<?= opsui_h($csrf) ?>">
            <input type="hidden" name="action" value="build_git_safe_continuity_zip">
            <button class="btn" type="submit" <?= (!$builderInstalled || !opsui_is_admin()) ? 'disabled' : '' ?>>Build Git-Safe Continuity ZIP</button>
            <?= opsui_is_admin() ? opsui_badge('ADMIN', 'good') : opsui_badge('ADMIN REQUIRED', 'warn') ?>
            <?= $builderInstalled ? opsui_badge('DB EXPORT OFF', 'good') : opsui_badge('BUILDER MISSING', 'warn') ?>
        </form>
        <ul class="list" style="margin-top:14px;">
            <li>Builds with <code>include_database=false</code>.</li>
            <li>Deletes <code>DATABASE_EXPORT.sql</code> defensively if found.</li>
            <li>Adds <code>GIT_SAFE_CONTINUITY_NOTICE.md</code> to the ZIP.</li>
        </ul>
    </div>
</section>

<section class="card">
    <h2>V3 verification links</h2>
    <div class="actions">
        <?php foreach ($v3Links as $label => $href): ?>
            <a class="btn dark" href="<?= opsui_h($href) ?>"><?= opsui_h($label) ?></a>
        <?php endforeach; ?>
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
                <?php $status = handoff_safe_file_status($path); ?>
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
            <li><code>gov.cabnet.app_config_examples/...</code> sanitized placeholders only</li>
            <li><code>DATABASE_EXPORT.sql</code> only in Private Operational ZIP mode</li>
        </ul>
    </div>
    <div class="card">
        <h2>Package excludes</h2>
        <ul class="list">
            <li>Real server-only config values</li>
            <li>Logs, caches, sessions, mailboxes, temp files</li>
            <li>Runtime storage artifacts and proof bundles</li>
            <li>Backup/archive files such as zip/tar/gz/bak</li>
            <li>Private keys, cookie/session dumps, raw secret files</li>
            <li>Any live EDXEIX/Bolt/AADE interaction</li>
        </ul>
    </div>
</section>
<?php opsui_shell_end(); ?>
