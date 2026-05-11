<?php
/**
 * gov.cabnet.app — Ops deployment center.
 * Read-only deployment/checklist guide for manual cPanel/GitHub Desktop workflow.
 * No Bolt calls, no EDXEIX calls, no AADE calls, no writes.
 */

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Robots-Tag: noindex, nofollow', true);

require_once __DIR__ . '/_shell.php';

function odc_status_file(string $path): array
{
    $exists = is_file($path);
    return [
        'exists' => $exists,
        'readable' => $exists && is_readable($path),
        'mtime' => ($exists && is_readable($path)) ? (int)filemtime($path) : 0,
        'size' => ($exists && is_readable($path)) ? (int)filesize($path) : 0,
    ];
}

function odc_size(int $bytes): string
{
    if ($bytes >= 1048576) { return number_format($bytes / 1048576, 2) . ' MB'; }
    if ($bytes >= 1024) { return number_format($bytes / 1024, 1) . ' KB'; }
    return $bytes . ' B';
}

function odc_time(int $mtime): string
{
    return $mtime > 0 ? date('Y-m-d H:i:s', $mtime) : '-';
}

function odc_file_row(string $label, string $path, bool $critical = false): string
{
    $st = odc_status_file($path);
    $badge = opsui_badge($st['exists'] ? 'FOUND' : ($critical ? 'MISSING' : 'OPTIONAL'), $st['exists'] ? 'good' : ($critical ? 'bad' : 'warn'));
    return '<tr>'
        . '<td><strong>' . opsui_h($label) . '</strong>' . ($critical ? ' ' . opsui_badge('critical', 'neutral') : '') . '</td>'
        . '<td><code>' . opsui_h($path) . '</code></td>'
        . '<td>' . $badge . '</td>'
        . '<td>' . opsui_h(odc_size((int)$st['size'])) . '</td>'
        . '<td>' . opsui_h(odc_time((int)$st['mtime'])) . '</td>'
        . '</tr>';
}

$webroot = dirname(__DIR__);
$appRoot = dirname(__DIR__, 3) . '/gov.cabnet.app_app';
$configRoot = dirname(__DIR__, 3) . '/gov.cabnet.app_config';
$sqlRoot = dirname(__DIR__, 3) . '/gov.cabnet.app_sql';

$criticalFiles = [
    ['Production Pre-Ride Tool', $webroot . '/ops/pre-ride-email-tool.php', true],
    ['Shared Ops Shell', $webroot . '/ops/_shell.php', true],
    ['Auth Prepend', $webroot . '/_auth_prepend.php', true],
    ['Login Page', $webroot . '/ops/login.php', true],
    ['OpsAuth Class', $appRoot . '/src/Auth/OpsAuth.php', true],
    ['Pre-Ride Parser', $appRoot . '/src/BoltMail/BoltPreRideEmailParser.php', true],
    ['EDXEIX Mapping Lookup', $appRoot . '/src/BoltMail/EdxeixMappingLookup.php', true],
    ['Server Config Example Location', $configRoot, false],
    ['SQL Directory', $sqlRoot, false],
];

opsui_shell_begin([
    'title' => 'Deployment Center',
    'page_title' => 'Deployment center',
    'subtitle' => 'Manual upload, verification, and commit checklist for safe cPanel deployment',
    'breadcrumbs' => 'Αρχική / Help / Deployment center',
    'active_section' => 'Help',
    'force_safe_notice' => true,
    'safe_notice' => 'Read-only deployment guide. This page does not upload files, does not modify the server, does not call Bolt/EDXEIX/AADE, and does not write database rows.',
]);
?>
<section class="card hero good">
    <h1>Manual deployment center</h1>
    <p>This page documents the safe deployment workflow for gov.cabnet.app patches. It is designed for the current process: download zip, extract locally, upload manually to cPanel/server, verify, then commit after production confirmation.</p>
    <div>
        <?= opsui_badge('READ ONLY', 'good') ?>
        <?= opsui_badge('NO UPLOAD ACTIONS', 'good') ?>
        <?= opsui_badge('NO EXTERNAL CALLS', 'good') ?>
        <?= opsui_badge('PRE-RIDE PROD PROTECTED', 'good') ?>
    </div>
    <div class="grid" style="margin-top:14px">
        <?= opsui_metric('1', 'Download patch zip') ?>
        <?= opsui_metric('2', 'Upload changed files') ?>
        <?= opsui_metric('3', 'Run syntax checks') ?>
        <?= opsui_metric('4', 'Commit after confirmed') ?>
    </div>
</section>

<section class="two">
    <div class="card">
        <h2>Production route rule</h2>
        <p class="goodline"><strong>Do not disrupt the active production pre-ride tool.</strong></p>
        <div class="kv">
            <div class="k">Production page</div><div><code>/ops/pre-ride-email-tool.php</code></div>
            <div class="k">Development page</div><div><code>/ops/pre-ride-email-toolv2.php</code></div>
            <div class="k">Mobile review</div><div><code>/ops/pre-ride-mobile-review.php</code></div>
            <div class="k">EDXEIX fill/save</div><div>Desktop/laptop Firefox + both helpers loaded</div>
        </div>
    </div>

    <div class="card">
        <h2>Commit rule</h2>
        <ol class="timeline">
            <li>Upload only changed/added files from the patch.</li>
            <li>Run the exact verification commands from the patch README.</li>
            <li>Open the relevant verification URLs in browser.</li>
            <li>Confirm production route still works.</li>
            <li>Commit via GitHub Desktop after server confirmation.</li>
        </ol>
    </div>
</section>

<section class="card">
    <h2>Critical file presence</h2>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Item</th><th>Server path</th><th>Status</th><th>Size</th><th>Modified</th></tr></thead>
            <tbody>
            <?php foreach ($criticalFiles as $row): ?>
                <?= odc_file_row((string)$row[0], (string)$row[1], (bool)$row[2]) ?>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="card">
    <h2>Copy/paste verification commands</h2>
    <p>Use these after GUI shell patches. Add any extra file-specific checks from the patch README.</p>
    <textarea class="code-box" readonly rows="14">php -l /home/cabnet/public_html/gov.cabnet.app/ops/_shell.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/home.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/profile.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-tool.php

curl -sS -D - -o /dev/null https://gov.cabnet.app/ops/pre-ride-email-tool.php | egrep -i 'HTTP/|Location:|Set-Cookie:'
curl -sS -D - -o /dev/null https://gov.cabnet.app/ops/login.php | egrep -i 'HTTP/|Location:|Set-Cookie:'

# Expected for logged-out curl:
# /ops/pre-ride-email-tool.php => 302 to /ops/login.php
# /ops/login.php => 200 OK</textarea>
</section>

<section class="card">
    <h2>Files that should not be committed</h2>
    <p>Keep these server-only or local-only. Use <code>.gitignore</code> and GitHub Desktop review before committing.</p>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Pattern</th><th>Reason</th></tr></thead>
            <tbody>
                <tr><td><code>gov.cabnet.app_config/config.php</code></td><td>real database/API configuration</td></tr>
                <tr><td><code>gov.cabnet.app_config/ops.php</code></td><td>server-only legacy guard config</td></tr>
                <tr><td><code>gov.cabnet.app_app/storage/runtime/*</code></td><td>sessions/cache/runtime state</td></tr>
                <tr><td><code>gov.cabnet.app_app/storage/logs/*</code></td><td>logs may contain operational data</td></tr>
                <tr><td><code>PATCH_README.md</code></td><td>temporary deployment package notes</td></tr>
                <tr><td><code>*.bak</code>, <code>*.zip</code>, <code>*.sql.gz</code></td><td>backups/archives/dumps</td></tr>
            </tbody>
        </table>
    </div>
</section>

<section class="card">
    <h2>Safe rollback reminders</h2>
    <ul class="list">
        <li>Before changing `.htaccess`, keep a timestamped backup in place.</li>
        <li>Before SQL migrations, prefer additive migrations and keep a DB backup.</li>
        <li>If a GUI page fails, restore only that page from the previous package or server backup.</li>
        <li>Never roll back the production pre-ride page during active operations unless there is a verified failure and a backup is ready.</li>
    </ul>
</section>
<?php opsui_shell_end(); ?>
