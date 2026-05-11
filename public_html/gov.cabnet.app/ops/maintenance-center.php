<?php
/**
 * gov.cabnet.app — Ops Maintenance Center
 *
 * Read-only operator/admin maintenance checklist page.
 * No Bolt calls, no EDXEIX calls, no AADE calls, no DB writes.
 */

declare(strict_types=1);

require_once __DIR__ . '/_shell.php';

function omc_file_info(string $path): array
{
    if (!is_file($path)) {
        return [
            'exists' => false,
            'mtime' => '',
            'size' => '',
            'sha' => '',
        ];
    }

    return [
        'exists' => true,
        'mtime' => date('Y-m-d H:i:s', (int)filemtime($path)),
        'size' => number_format((float)filesize($path)) . ' bytes',
        'sha' => substr(hash_file('sha256', $path) ?: '', 0, 16),
    ];
}

function omc_status_badge(bool $ok): string
{
    return opsui_badge($ok ? 'FOUND' : 'MISSING', $ok ? 'good' : 'bad');
}

function omc_code(string $text): string
{
    return '<pre class="gov-code-block"><code>' . opsui_h($text) . '</code></pre>';
}

$siteRoot = dirname(__DIR__);
$appRoot = dirname(__DIR__, 3) . '/gov.cabnet.app_app';
$sqlRoot = dirname(__DIR__, 3) . '/gov.cabnet.app_sql';
$configRoot = dirname(__DIR__, 3) . '/gov.cabnet.app_config';

$files = [
    'Production Pre-Ride Tool' => $siteRoot . '/ops/pre-ride-email-tool.php',
    'Pre-Ride V2 Dev Tool' => $siteRoot . '/ops/pre-ride-email-toolv2.php',
    'Shared Ops Shell' => $siteRoot . '/ops/_shell.php',
    'Auth Prepend' => $siteRoot . '/_auth_prepend.php',
    'OpsAuth Class' => $appRoot . '/src/Auth/OpsAuth.php',
    'Pre-Ride Parser' => $appRoot . '/src/BoltMail/BoltPreRideEmailParser.php',
    'EDXEIX Mapping Lookup' => $appRoot . '/src/BoltMail/EdxeixMappingLookup.php',
    'Maildir Loader' => $appRoot . '/src/BoltMail/MaildirPreRideEmailLoader.php',
    'System Status Page' => $siteRoot . '/ops/system-status.php',
    'Deployment Center' => $siteRoot . '/ops/deployment-center.php',
    'Handoff Center' => $siteRoot . '/ops/handoff-center.php',
    'Release Notes' => $siteRoot . '/ops/release-notes.php',
];

$syntaxCommand = <<<'CMD'
php -l /home/cabnet/public_html/gov.cabnet.app/ops/_shell.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-tool.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-toolv2.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/system-status.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/deployment-center.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/handoff-center.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/release-notes.php
CMD;

$authCheckCommand = <<<'CMD'
curl -sS -D - -o /dev/null https://gov.cabnet.app/ops/pre-ride-email-tool.php | egrep -i 'HTTP/|Location:|Set-Cookie:'
curl -sS -D - -o /dev/null https://gov.cabnet.app/ops/login.php | egrep -i 'HTTP/|Location:|Set-Cookie:'
CMD;

$backupCommand = <<<'CMD'
mkdir -p /root/gov_cabnet_backups
TS="$(date +%Y%m%d_%H%M%S)"
tar -czf "/root/gov_cabnet_backups/gov_public_ops_${TS}.tar.gz" \
  /home/cabnet/public_html/gov.cabnet.app/ops \
  /home/cabnet/public_html/gov.cabnet.app/assets/css/gov-ops-shell.css
mysqldump -u cabnet_gov -p cabnet_gov ops_users ops_login_attempts ops_audit_log ops_user_preferences \
  > "/root/gov_cabnet_backups/gov_ops_auth_${TS}.sql"
CMD;

$afterPatchCommand = <<<'CMD'
apachectl -t
php -l /home/cabnet/public_html/gov.cabnet.app/ops/_shell.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-tool.php
curl -sS -D - -o /dev/null https://gov.cabnet.app/ops/pre-ride-email-tool.php | egrep -i 'HTTP/|Location:|Set-Cookie:'
CMD;

opsui_shell_begin([
    'title' => 'Maintenance Center',
    'page_title' => 'Maintenance Center',
    'breadcrumbs' => 'Αρχική / Διαχειριστικό / Maintenance Center',
    'active_section' => 'Maintenance',
    'safe_notice' => 'This page is a read-only maintenance checklist. It does not call Bolt, EDXEIX, or AADE, and it does not write data.',
]);
?>
<section class="card hero neutral">
    <h1>Ops Maintenance Center</h1>
    <p>Use this page before and after manual cPanel uploads to confirm that the protected operator system remains safe and the production pre-ride tool has not been disrupted.</p>
    <div>
        <?= opsui_badge('READ ONLY', 'good') ?>
        <?= opsui_badge('NO SECRET OUTPUT', 'good') ?>
        <?= opsui_badge('NO WORKFLOW WRITES', 'good') ?>
        <?= opsui_badge('PRODUCTION TOOL PROTECTED', 'good') ?>
    </div>
</section>

<section class="gov-admin-grid">
    <a class="gov-admin-link" href="/ops/system-status.php"><strong>System Status</strong><span>Check DB connectivity, auth tables, and core runtime files.</span></a>
    <a class="gov-admin-link" href="/ops/tool-inventory.php"><strong>Tool Inventory</strong><span>Review operator routes and safe file fingerprints.</span></a>
    <a class="gov-admin-link" href="/ops/deployment-center.php"><strong>Deployment Center</strong><span>Manual upload and verification checklist.</span></a>
    <a class="gov-admin-link" href="/ops/release-notes.php"><strong>Release Notes</strong><span>Change register for the shared GUI rollout.</span></a>
    <a class="gov-admin-link" href="/ops/handoff-center.php"><strong>Handoff Center</strong><span>Copy/paste continuity prompt for the next Sophion session.</span></a>
    <a class="gov-admin-link" href="/ops/pre-ride-email-tool.php"><strong>Production Pre-Ride Tool</strong><span>Live staff workflow. Do not disrupt.</span></a>
</section>

<section class="card">
    <h2>Critical file snapshot</h2>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>File</th>
                    <th>Status</th>
                    <th>Modified</th>
                    <th>Size</th>
                    <th>SHA-256</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($files as $label => $path): $info = omc_file_info($path); ?>
                    <tr>
                        <td><strong><?= opsui_h($label) ?></strong><br><span class="small"><?= opsui_h($path) ?></span></td>
                        <td><?= omc_status_badge((bool)$info['exists']) ?></td>
                        <td><?= opsui_h($info['mtime']) ?></td>
                        <td><?= opsui_h($info['size']) ?></td>
                        <td><code><?= opsui_h($info['sha']) ?></code></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="two">
    <div class="card">
        <h2>Before patch upload</h2>
        <ol class="list">
            <li>Confirm the patch does not include <code>ops/pre-ride-email-tool.php</code> unless intentionally approved.</li>
            <li>Back up any file that will be replaced.</li>
            <li>Upload only the listed changed/added files.</li>
            <li>Run syntax checks before browser testing.</li>
        </ol>
    </div>
    <div class="card">
        <h2>After patch upload</h2>
        <ol class="list">
            <li>Run <code>apachectl -t</code>.</li>
            <li>Run <code>php -l</code> on uploaded PHP files.</li>
            <li>Confirm the production pre-ride tool still redirects to login when logged out.</li>
            <li>Open the new page and confirm no workflow action occurs.</li>
        </ol>
    </div>
</section>

<section class="card">
    <h2>Copy/paste syntax check block</h2>
    <?= omc_code($syntaxCommand) ?>
</section>

<section class="card">
    <h2>Copy/paste auth check block</h2>
    <?= omc_code($authCheckCommand) ?>
</section>

<section class="card">
    <h2>Optional backup block before larger changes</h2>
    <p>This creates a root-only backup of the ops folder and auth/profile tables. It prompts for the DB password and does not place secrets in the command line.</p>
    <?= omc_code($backupCommand) ?>
</section>

<section class="card">
    <h2>Minimal after-patch verification block</h2>
    <?= omc_code($afterPatchCommand) ?>
</section>

<section class="card">
    <h2>Server-only files reminder</h2>
    <p>These files must remain server-only and should not be committed with real values:</p>
    <ul class="list">
        <li><code>/home/cabnet/gov.cabnet.app_config/config.php</code></li>
        <li><code>/home/cabnet/gov.cabnet.app_config/ops.php</code></li>
        <li><code>/home/cabnet/gov.cabnet.app_config/*.local.php</code></li>
        <li>session files, logs, runtime artifacts, SQL dumps, and raw diagnostic output</li>
    </ul>
</section>
<?php opsui_shell_end(); ?>
