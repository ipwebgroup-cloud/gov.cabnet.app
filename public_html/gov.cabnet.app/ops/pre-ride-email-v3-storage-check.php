<?php
/**
 * gov.cabnet.app — V3 Storage Check
 *
 * Read-only Ops page for V3 pulse/storage prerequisites.
 * Does not call Bolt, EDXEIX, AADE, Gmail, or production submission tables.
 */

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Robots-Tag: noindex, nofollow', true);

require_once __DIR__ . '/_ops-nav.php';

function gov_v3_storage_perms(string $path): string
{
    $perms = @fileperms($path);
    if ($perms === false) {
        return 'n/a';
    }
    return substr(sprintf('%o', $perms), -4);
}

function gov_v3_storage_owner_group(string $path): string
{
    $owner = @fileowner($path);
    $group = @filegroup($path);
    $ownerName = is_int($owner) ? (string)$owner : 'n/a';
    $groupName = is_int($group) ? (string)$group : 'n/a';

    if (function_exists('posix_getpwuid') && is_int($owner)) {
        $pw = @posix_getpwuid($owner);
        if (is_array($pw) && isset($pw['name'])) {
            $ownerName = (string)$pw['name'];
        }
    }

    if (function_exists('posix_getgrgid') && is_int($group)) {
        $gr = @posix_getgrgid($group);
        if (is_array($gr) && isset($gr['name'])) {
            $groupName = (string)$gr['name'];
        }
    }

    return $ownerName . ':' . $groupName;
}

function gov_v3_storage_status(string $path, string $kind, bool $needsWritable): array
{
    $exists = $kind === 'dir' ? is_dir($path) : is_file($path);
    $readable = $exists && is_readable($path);
    $writable = $exists && is_writable($path);

    return [
        'path' => $path,
        'kind' => $kind,
        'exists' => $exists,
        'readable' => $readable,
        'writable' => $writable,
        'needs_writable' => $needsWritable,
        'perms' => $exists ? gov_v3_storage_perms($path) : 'n/a',
        'owner_group' => $exists ? gov_v3_storage_owner_group($path) : 'n/a',
        'ok' => $exists && $readable && (!$needsWritable || $writable),
    ];
}

$appRoot = dirname(__DIR__, 3) . '/gov.cabnet.app_app';
$rows = [
    'App root' => gov_v3_storage_status($appRoot, 'dir', false),
    'Storage directory' => gov_v3_storage_status($appRoot . '/storage', 'dir', true),
    'V3 lock directory' => gov_v3_storage_status($appRoot . '/storage/locks', 'dir', true),
    'Logs directory' => gov_v3_storage_status($appRoot . '/logs', 'dir', true),
    'Pulse CLI' => gov_v3_storage_status($appRoot . '/cli/pre_ride_email_v3_fast_pipeline_pulse.php', 'file', false),
    'Pulse cron worker' => gov_v3_storage_status($appRoot . '/cli/pre_ride_email_v3_fast_pipeline_pulse_cron_worker.php', 'file', false),
];

$allOk = true;
foreach ($rows as $row) {
    if (empty($row['ok'])) {
        $allOk = false;
        break;
    }
}

$tabs = [
    ['key' => 'dashboard', 'label' => 'V3 Dashboard', 'href' => '/ops/pre-ride-email-v3-dashboard.php'],
    ['key' => 'queue_watch', 'label' => 'Queue Watch', 'href' => '/ops/pre-ride-email-v3-queue-watch.php'],
    ['key' => 'pulse', 'label' => 'Pulse Monitor', 'href' => '/ops/pre-ride-email-v3-fast-pipeline-pulse.php'],
    ['key' => 'readiness', 'label' => 'Automation Readiness', 'href' => '/ops/pre-ride-email-v3-automation-readiness.php'],
    ['key' => 'storage', 'label' => 'Storage Check', 'href' => '/ops/pre-ride-email-v3-storage-check.php'],
];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>V3 Storage Check | gov.cabnet.app</title>
    <style>
        :root{--ops-navy:#2f3659;--ops-navy-2:#25304f;--ops-blue:#5563b7;--ops-blue-2:#4159a8;--ops-bg:#f3f5fa;--ops-panel:#fff;--ops-line:#d9deea;--ops-ink:#1f2d4d;--ops-muted:#5a6785;--ops-good:#5fae63;--ops-warn:#d39a31;--ops-bad:#c94b4b;--ops-info:#4d89d8;--ops-shadow:0 4px 14px rgba(31,45,77,.06)}
        *{box-sizing:border-box}body{margin:0;background:var(--ops-bg);color:var(--ops-ink);font-family:Arial,Helvetica,sans-serif}.ops-shell-topbar{height:76px;background:#fff;border-bottom:1px solid var(--ops-line);display:flex;align-items:center;gap:22px;padding:0 26px;position:sticky;top:0;z-index:30}.ops-shell-brand{display:flex;align-items:center;gap:12px;text-decoration:none;color:var(--ops-ink);min-width:270px}.ops-shell-logo{width:42px;height:42px;border-radius:12px;background:var(--ops-blue);color:#fff;display:inline-flex;align-items:center;justify-content:center;font-weight:800}.ops-shell-brand-text strong{display:block;font-size:16px}.ops-shell-brand-text em{display:block;font-size:12px;color:var(--ops-muted);font-style:normal}.ops-shell-topnav{display:flex;gap:4px;align-items:center;flex:1;overflow:auto}.ops-shell-topnav a{font-size:12px;font-weight:800;letter-spacing:.04em;color:var(--ops-muted);text-decoration:none;padding:12px 13px;border-radius:10px;white-space:nowrap}.ops-shell-topnav a.active,.ops-shell-topnav a:hover{background:#eef1ff;color:var(--ops-blue-2)}.ops-shell-user{display:flex;align-items:center;gap:9px}.ops-shell-user-mark{width:35px;height:35px;border-radius:50%;background:var(--ops-navy);color:#fff;display:inline-flex;align-items:center;justify-content:center;font-weight:800}.ops-shell-user strong{font-size:12px;display:block}.ops-shell-user em{font-style:normal;color:var(--ops-muted);font-size:11px}.ops-shell-layout{display:grid;grid-template-columns:292px minmax(0,1fr);min-height:calc(100vh - 76px)}.ops-shell-sidebar{background:var(--ops-navy);color:#fff;padding:22px 18px}.ops-operator-card{background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.13);border-radius:16px;padding:16px;margin-bottom:20px}.ops-operator-top{display:flex;align-items:center;gap:10px;margin-bottom:12px}.ops-operator-avatar{width:42px;height:42px;border-radius:50%;background:#fff;color:var(--ops-navy);display:inline-flex;align-items:center;justify-content:center;font-weight:800}.ops-operator-card strong{display:block}.ops-operator-card em{display:block;font-style:normal;font-size:12px;opacity:.72}.ops-operator-actions{display:grid;grid-template-columns:1fr 1fr;gap:8px}.ops-operator-actions a{color:#fff;background:rgba(255,255,255,.08);border-radius:9px;padding:8px;text-decoration:none;font-size:12px;text-align:center}.ops-side-section{margin:18px 0}.ops-side-section h3{font-size:12px;text-transform:uppercase;letter-spacing:.08em;color:rgba(255,255,255,.58);margin:0 0 8px}.ops-side-link{display:flex;align-items:center;justify-content:space-between;color:rgba(255,255,255,.88);text-decoration:none;padding:10px 12px;border-radius:10px;margin-bottom:5px}.ops-side-link:hover,.ops-side-link.active{background:rgba(255,255,255,.14);color:#fff}.ops-side-hint{font-size:12px;line-height:1.4;color:rgba(255,255,255,.7);margin:2px 10px 10px}.ops-shell-main{padding:26px 30px 60px;min-width:0}.ops-page-header{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;margin-bottom:18px}.ops-page-kicker{color:var(--ops-blue-2);font-size:12px;text-transform:uppercase;font-weight:900;letter-spacing:.1em;margin:0 0 7px}.ops-page-header h1{font-size:36px;line-height:1.05;margin:0}.ops-page-header p{color:var(--ops-muted);margin:9px 0 0;max-width:850px;line-height:1.48}.ops-page-tabs{display:flex;gap:8px;flex-wrap:wrap;margin:10px 0 20px}.ops-page-tabs a{background:#fff;border:1px solid var(--ops-line);color:var(--ops-muted);border-radius:999px;padding:10px 14px;text-decoration:none;font-weight:800;font-size:13px}.ops-page-tabs a.active,.ops-page-tabs a:hover{background:var(--ops-blue);border-color:var(--ops-blue);color:#fff}.ops-card{background:#fff;border:1px solid var(--ops-line);border-radius:14px;box-shadow:var(--ops-shadow);padding:20px;margin-bottom:18px}.ops-card h2{font-size:22px;margin:0 0 12px}.ops-card p{color:var(--ops-muted);line-height:1.5}.ops-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}.ops-metric{border:1px solid var(--ops-line);border-left:5px solid var(--ops-blue);border-radius:12px;background:#fff;padding:16px}.ops-metric.good{border-left-color:var(--ops-good)}.ops-metric.bad{border-left-color:var(--ops-bad)}.ops-metric.warn{border-left-color:var(--ops-warn)}.ops-metric strong{display:block;font-size:32px;line-height:1}.ops-metric span{display:block;color:var(--ops-muted);font-size:13px;margin-top:7px}.ops-badge{display:inline-flex;align-items:center;border-radius:999px;padding:5px 9px;font-weight:900;font-size:12px}.ops-badge-good{background:#e6f5e8;color:#276b2b}.ops-badge-warn{background:#fff4de;color:#8a5a05}.ops-badge-bad{background:#ffe9e9;color:#943131}.ops-badge-info{background:#e8f1ff;color:#245f9e}.ops-badge-neutral{background:#eef1f7;color:#46546f}.ops-table-wrap{overflow:auto;border:1px solid var(--ops-line);border-radius:12px}.ops-table{width:100%;border-collapse:collapse;min-width:880px}.ops-table th,.ops-table td{padding:12px 14px;border-bottom:1px solid var(--ops-line);text-align:left;vertical-align:top;font-size:14px}.ops-table th{background:#f8f9fc;color:var(--ops-muted);font-size:12px;text-transform:uppercase;letter-spacing:.05em}.ops-code{display:block;white-space:pre-wrap;background:#f8f9fc;border:1px solid var(--ops-line);border-radius:12px;padding:14px;overflow:auto;color:#1f2d4d;font-family:Consolas,Monaco,monospace;font-size:13px;line-height:1.45}.ops-alert{border-radius:14px;padding:15px 16px;border:1px solid var(--ops-line);background:#fff}.ops-alert.good{border-color:#cbe8ce;background:#f3fbf4}.ops-alert.warn{border-color:#f5dca4;background:#fff9ea}.ops-alert.bad{border-color:#f2c2c2;background:#fff3f3}@media(max-width:1100px){.ops-shell-layout{grid-template-columns:1fr}.ops-shell-sidebar{position:relative}.ops-grid{grid-template-columns:1fr}.ops-shell-topbar{height:auto;flex-wrap:wrap;padding:14px}.ops-shell-brand{min-width:0}.ops-shell-user{display:none}}@media(max-width:720px){.ops-shell-main{padding:18px 14px 40px}.ops-page-header{display:block}.ops-page-header h1{font-size:30px}}
    </style>
</head>
<body>
<?php gov_ops_render_topbar('pre_ride'); ?>
<div class="ops-shell-layout">
    <?php gov_ops_render_sidebar('v3_storage_check'); ?>
    <main class="ops-shell-main">
        <header class="ops-page-header">
            <div>
                <p class="ops-page-kicker">V3 diagnostics</p>
                <h1>V3 Storage Check</h1>
                <p>Read-only check for the storage, lock, log, and pulse files needed by the V3 fast pipeline cron. This page does not modify files or database rows.</p>
            </div>
            <div><?= $allOk ? gov_ops_badge('storage ok', 'good') : gov_ops_badge('needs attention', 'warn') ?></div>
        </header>

        <?php gov_ops_render_page_tabs($tabs, 'storage'); ?>

        <section class="ops-card">
            <h2>Current status</h2>
            <div class="ops-grid">
                <div class="ops-metric <?= $allOk ? 'good' : 'warn' ?>"><strong><?= $allOk ? 'OK' : 'CHECK' ?></strong><span>Overall storage prerequisite status</span></div>
                <div class="ops-metric <?= !empty($rows['V3 lock directory']['ok']) ? 'good' : 'bad' ?>"><strong><?= !empty($rows['V3 lock directory']['ok']) ? 'OK' : 'NO' ?></strong><span>Pulse lock directory writable</span></div>
                <div class="ops-metric <?= !empty($rows['Pulse cron worker']['ok']) ? 'good' : 'bad' ?>"><strong><?= !empty($rows['Pulse cron worker']['ok']) ? 'OK' : 'NO' ?></strong><span>Pulse cron worker file present</span></div>
            </div>
        </section>

        <section class="ops-card">
            <h2>Paths</h2>
            <div class="ops-table-wrap">
                <table class="ops-table">
                    <thead><tr><th>Check</th><th>Status</th><th>Path</th><th>Exists</th><th>Readable</th><th>Writable</th><th>Perms</th><th>Owner:Group</th></tr></thead>
                    <tbody>
                    <?php foreach ($rows as $label => $row): ?>
                        <tr>
                            <td><strong><?= gov_ops_h($label) ?></strong></td>
                            <td><?= !empty($row['ok']) ? gov_ops_badge('ok', 'good') : gov_ops_badge('check', 'bad') ?></td>
                            <td><code><?= gov_ops_h($row['path']) ?></code></td>
                            <td><?= !empty($row['exists']) ? gov_ops_badge('yes', 'good') : gov_ops_badge('no', 'bad') ?></td>
                            <td><?= !empty($row['readable']) ? gov_ops_badge('yes', 'good') : gov_ops_badge('no', 'bad') ?></td>
                            <td><?= !empty($row['writable']) ? gov_ops_badge('yes', 'good') : (empty($row['needs_writable']) ? gov_ops_badge('not required', 'neutral') : gov_ops_badge('no', 'bad')) ?></td>
                            <td><?= gov_ops_h($row['perms']) ?></td>
                            <td><?= gov_ops_h($row['owner_group']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <?php if (!$allOk): ?>
            <section class="ops-alert warn">
                <strong>Recommended one-time repair command</strong>
                <p>Run this as root if the lock/log directories are missing or not writable by the cabnet account.</p>
                <code class="ops-code">install -d -o cabnet -g cabnet -m 750 /home/cabnet/gov.cabnet.app_app/storage/locks
install -d -o cabnet -g cabnet -m 750 /home/cabnet/gov.cabnet.app_app/logs
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_storage_check.php --json</code>
            </section>
        <?php endif; ?>

        <section class="ops-card">
            <h2>Safe CLI checks</h2>
            <p>These commands are V3-only and do not touch V0 production, EDXEIX live submission, AADE, or production submission tables.</p>
            <code class="ops-code">/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_storage_check.php
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_storage_check.php --json
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_storage_check.php --fix --owner=cabnet --group=cabnet</code>
        </section>

        <section class="ops-alert good">
            <strong>Boundary reminder</strong>
            <p>V0 remains the active laptop/manual production helper. This V3 page is only for PC-side development/test operations and storage health. Operator judgment remains the fallback decision path.</p>
        </section>
    </main>
</div>
</body>
</html>
