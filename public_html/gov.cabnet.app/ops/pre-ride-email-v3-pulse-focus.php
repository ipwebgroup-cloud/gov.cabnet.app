<?php
/**
 * gov.cabnet.app — V3 Pulse Focus
 *
 * Read-only pulse cron/log visibility for V3 pre-ride automation.
 * Does not call Bolt, EDXEIX, AADE, Gmail, or production submission tables.
 * Does not modify database rows or files.
 */

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Robots-Tag: noindex, nofollow', true);

require_once __DIR__ . '/_ops-nav.php';

function gov_v3_pf_owner_group(string $path): string
{
    if (!file_exists($path)) {
        return 'missing';
    }

    $owner = @fileowner($path);
    $group = @filegroup($path);
    $ownerName = is_int($owner) ? (string)$owner : 'unknown';
    $groupName = is_int($group) ? (string)$group : 'unknown';

    if (function_exists('posix_getpwuid') && is_int($owner)) {
        $info = @posix_getpwuid($owner);
        if (is_array($info) && isset($info['name'])) {
            $ownerName = (string)$info['name'];
        }
    }

    if (function_exists('posix_getgrgid') && is_int($group)) {
        $info = @posix_getgrgid($group);
        if (is_array($info) && isset($info['name'])) {
            $groupName = (string)$info['name'];
        }
    }

    return $ownerName . ':' . $groupName;
}

function gov_v3_pf_perms(string $path): string
{
    if (!file_exists($path)) {
        return 'missing';
    }
    $perms = @fileperms($path);
    if (!is_int($perms)) {
        return 'unknown';
    }
    return substr(sprintf('%o', $perms), -4);
}

function gov_v3_pf_tail_lines(string $path, int $maxLines = 220): array
{
    if (!is_file($path) || !is_readable($path)) {
        return [];
    }

    try {
        $file = new SplFileObject($path, 'r');
        $file->seek(PHP_INT_MAX);
        $lastLine = $file->key();
        $start = max(0, $lastLine - $maxLines + 1);
        $lines = [];
        for ($i = $start; $i <= $lastLine; $i++) {
            $file->seek($i);
            $line = rtrim((string)$file->current(), "\r\n");
            if ($line !== '') {
                $lines[] = $line;
            }
        }
        return $lines;
    } catch (Throwable $e) {
        $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!is_array($lines)) {
            return [];
        }
        return array_slice($lines, -$maxLines);
    }
}

function gov_v3_pf_extract_timestamp(string $line): ?DateTimeImmutable
{
    if (!preg_match('/^\[([^\]]+)\]/', $line, $m)) {
        return null;
    }

    try {
        return new DateTimeImmutable($m[1]);
    } catch (Throwable $e) {
        return null;
    }
}

function gov_v3_pf_seconds_since(?DateTimeImmutable $dt): ?int
{
    if (!$dt) {
        return null;
    }
    return time() - $dt->getTimestamp();
}

function gov_v3_pf_seconds_badge_type(?int $seconds): string
{
    if ($seconds === null) {
        return 'warn';
    }
    if ($seconds <= 120) {
        return 'good';
    }
    if ($seconds <= 300) {
        return 'warn';
    }
    return 'bad';
}

function gov_v3_pf_exit_badge_type(?int $exitCode): string
{
    if ($exitCode === 0) {
        return 'good';
    }
    if ($exitCode === null) {
        return 'warn';
    }
    return 'bad';
}

function gov_v3_pf_analyze_log(array $lines): array
{
    $latestStart = null;
    $latestFinish = null;
    $latestSummary = null;
    $latestError = null;
    $latestExit = null;
    $tailErrors = [];
    $events = [];

    foreach ($lines as $line) {
        $timestamp = gov_v3_pf_extract_timestamp($line);

        if (strpos($line, 'V3 fast pipeline pulse cron start') !== false) {
            $latestStart = ['line' => $line, 'time' => $timestamp];
            $events[] = ['type' => 'start', 'line' => $line, 'badge' => 'info'];
        }

        if (strpos($line, 'Pulse summary:') !== false) {
            $latestSummary = $line;
            $events[] = ['type' => 'summary', 'line' => $line, 'badge' => (strpos($line, 'failed=0') !== false ? 'good' : 'bad')];
        }

        if (preg_match('/finish exit_code=(\d+)/', $line, $m)) {
            $latestExit = (int)$m[1];
            $latestFinish = ['line' => $line, 'time' => $timestamp];
            $events[] = ['type' => 'finish', 'line' => $line, 'badge' => ((int)$m[1] === 0 ? 'good' : 'bad')];
        }

        if (strpos($line, 'ERROR:') !== false) {
            $latestError = $line;
            $tailErrors[] = $line;
            $events[] = ['type' => 'error', 'line' => $line, 'badge' => 'bad'];
        }
    }

    $events = array_slice($events, -24);
    $secondsSinceFinish = gov_v3_pf_seconds_since(is_array($latestFinish) ? ($latestFinish['time'] ?? null) : null);
    $secondsSinceStart = gov_v3_pf_seconds_since(is_array($latestStart) ? ($latestStart['time'] ?? null) : null);

    return [
        'latest_start' => $latestStart,
        'latest_finish' => $latestFinish,
        'latest_summary' => $latestSummary,
        'latest_error' => $latestError,
        'latest_exit_code' => $latestExit,
        'tail_errors' => $tailErrors,
        'events' => $events,
        'seconds_since_finish' => $secondsSinceFinish,
        'seconds_since_start' => $secondsSinceStart,
        'is_running_now' => is_array($latestStart) && (!is_array($latestFinish) || (($latestStart['time'] instanceof DateTimeImmutable) && ($latestFinish['time'] instanceof DateTimeImmutable) && $latestStart['time']->getTimestamp() > $latestFinish['time']->getTimestamp())),
    ];
}

$appRoot = dirname(__DIR__, 3) . '/gov.cabnet.app_app';
$logPath = $appRoot . '/logs/pre_ride_email_v3_fast_pipeline_pulse.log';
$lockPath = $appRoot . '/storage/locks/pre_ride_email_v3_fast_pipeline_pulse.lock';
$cronWorkerPath = $appRoot . '/cli/pre_ride_email_v3_fast_pipeline_pulse_cron_worker.php';
$pulseCliPath = $appRoot . '/cli/pre_ride_email_v3_fast_pipeline_pulse.php';

$lines = gov_v3_pf_tail_lines($logPath, 260);
$analysis = gov_v3_pf_analyze_log($lines);

$lockOwner = gov_v3_pf_owner_group($lockPath);
$lockPerms = gov_v3_pf_perms($lockPath);
$lockOk = is_file($lockPath) && is_readable($lockPath) && is_writable($lockPath) && $lockOwner === 'cabnet:cabnet';
$logOk = is_file($logPath) && is_readable($logPath);
$cronOk = is_file($cronWorkerPath) && is_readable($cronWorkerPath);
$pulseCliOk = is_file($pulseCliPath) && is_readable($pulseCliPath);
$exitCode = $analysis['latest_exit_code'];
$seconds = $analysis['seconds_since_finish'];
$overallOk = $lockOk && $logOk && $cronOk && $pulseCliOk && $exitCode === 0 && ($seconds === null || $seconds <= 180);

$lastSummary = (string)($analysis['latest_summary'] ?? 'no pulse summary found in recent log tail');
$lastFinish = is_array($analysis['latest_finish']) ? (string)($analysis['latest_finish']['line'] ?? '') : 'no finish line found in recent log tail';
$lastStart = is_array($analysis['latest_start']) ? (string)($analysis['latest_start']['line'] ?? '') : 'no start line found in recent log tail';
$lastError = (string)($analysis['latest_error'] ?? 'none in recent tail');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>V3 Pulse Focus | gov.cabnet.app</title>
    <style>
        :root { --bg:#eef2f7; --panel:#fff; --ink:#14234a; --ink-strong:#092159; --muted:#31466c; --line:#d5ddec; --sidebar:#30385f; --brand:#5662b1; --brand-dark:#405096; --green:#58b267; --green-soft:#e1f6e6; --amber:#d99529; --amber-soft:#fff0d6; --red:#cc392f; --red-soft:#ffe5e3; --blue:#386fd4; --blue-soft:#e8efff; --shadow:0 7px 18px rgba(31,45,77,.07); }
        * { box-sizing:border-box; } body { margin:0; background:var(--bg); color:var(--ink); font-family:Arial, Helvetica, sans-serif; font-size:14px; }
        .ops-shell-topbar { min-height:68px; background:#fff; border-bottom:1px solid var(--line); display:flex; align-items:center; gap:22px; padding:0 26px; position:sticky; top:0; z-index:30; }
        .ops-shell-brand { display:flex; align-items:center; gap:10px; min-width:290px; color:var(--ink); text-decoration:none; }
        .ops-shell-logo { width:46px; height:46px; border:2px solid #7d8ccc; border-radius:50%; display:grid; place-items:center; font-weight:800; color:#5361a9; letter-spacing:.03em; font-size:19px; }
        .ops-shell-brand-text strong { display:block; font-size:20px; line-height:1; color:#1e3a78; }
        .ops-shell-brand-text em { display:block; font-style:normal; color:#31466c; font-size:12px; margin-top:3px; }
        .ops-shell-topnav { display:flex; align-items:center; gap:5px; flex:1; min-width:0; overflow:auto; padding:8px 0; }
        .ops-shell-topnav a { color:#34436b; text-decoration:none; padding:9px 13px; border-radius:14px; white-space:nowrap; font-size:13px; font-weight:700; }
        .ops-shell-topnav a:hover, .ops-shell-topnav a.active { background:#e9eefb; color:#2f4193; box-shadow:inset 0 0 0 1px #d4dcf4; }
        .ops-shell-user { display:flex; align-items:center; gap:9px; color:#2d3a62; min-width:150px; justify-content:flex-end; }
        .ops-shell-user-mark { width:36px; height:36px; border-radius:50%; display:grid; place-items:center; background:#5864b0; color:#fff; font-weight:800; }
        .ops-shell-user strong { display:block; font-size:12px; line-height:1; letter-spacing:.03em; } .ops-shell-user em { display:block; font-style:normal; font-size:10px; color:#64748b; margin-top:2px; }
        .ops-shell-layout { display:grid; grid-template-columns:300px 1fr; min-height:calc(100vh - 68px); }
        .ops-shell-sidebar { background:var(--sidebar); color:#fff; padding:22px 16px 44px; box-shadow:inset -1px 0 0 rgba(0,0,0,.08); }
        .ops-operator-card { border:1px solid rgba(255,255,255,.18); background:rgba(255,255,255,.08); border-radius:8px; padding:13px; margin-bottom:22px; }
        .ops-operator-top { display:flex; align-items:center; gap:10px; margin-bottom:12px; } .ops-operator-avatar { width:42px; height:42px; background:#fff; color:#5964ad; border-radius:50%; display:grid; place-items:center; font-weight:900; }
        .ops-operator-card strong { display:block; } .ops-operator-card em { display:block; font-style:normal; color:#d9e1f2; font-size:12px; }
        .ops-operator-actions { display:flex; gap:7px; flex-wrap:wrap; } .ops-operator-actions a { color:#fff; text-decoration:none; font-size:11px; font-weight:700; padding:6px 8px; border:1px solid rgba(255,255,255,.22); border-radius:4px; background:rgba(0,0,0,.08); }
        .ops-side-section { margin-bottom:22px; } .ops-side-section h3 { color:#cdd7ef; text-transform:uppercase; letter-spacing:.06em; font-size:11px; font-weight:500; margin:0 0 8px 7px; }
        .ops-side-link { display:block; color:#fff; text-decoration:none; padding:11px 12px; border-radius:4px; margin:2px 0; font-weight:700; font-size:14px; } .ops-side-link:hover, .ops-side-link.active { background:#5662b1; }
        .ops-side-hint { margin:6px 7px 13px; color:#edf3ff; line-height:1.45; }
        .ops-main { padding:22px 24px 60px; max-width:1600px; width:100%; } .ops-page-title { display:flex; align-items:flex-start; justify-content:space-between; gap:18px; margin-bottom:8px; }
        h1 { margin:0; color:var(--ink-strong); font-size:30px; line-height:1.15; letter-spacing:-.02em; } .ops-kicker { color:#5662b1; text-transform:uppercase; letter-spacing:.08em; font-size:12px; font-weight:800; margin-bottom:5px; }
        .ops-page-subtitle { margin:6px 0 0; color:var(--muted); line-height:1.45; max-width:980px; }
        .ops-page-tabs { display:flex; flex-wrap:wrap; gap:12px; margin:18px 0; } .ops-page-tabs a { text-decoration:none; color:#4d5e7f; padding:12px 16px; border-radius:22px; background:#fff; border:1px solid var(--line); font-weight:800; font-size:13px; } .ops-page-tabs a:hover, .ops-page-tabs a.active { background:#5662b1; color:#fff; }
        .ops-badge { display:inline-block; padding:5px 9px; border-radius:999px; font-size:11px; font-weight:900; margin:2px; white-space:nowrap; } .ops-badge-good { background:var(--green-soft); color:#087a33; } .ops-badge-warn { background:var(--amber-soft); color:#a55700; } .ops-badge-bad { background:var(--red-soft); color:#a11c15; } .ops-badge-neutral { background:#eef2f7; color:#40516d; } .ops-badge-info { background:var(--blue-soft); color:#214aab; } .ops-badge-dark { background:#29345a; color:#fff; }
        .ops-alert { border:1px solid var(--line); background:#fff; border-radius:8px; padding:13px 16px; margin:16px 0; box-shadow:var(--shadow); } .ops-alert.safe { border-left:4px solid var(--green); background:#f3fff5; } .ops-alert.warn { border-left:4px solid var(--amber); background:#fffaf1; } .ops-alert.danger { border-left:4px solid var(--red); background:#fff6f5; }
        .ops-grid { display:grid; gap:14px; } .ops-grid.metrics { grid-template-columns:repeat(5,minmax(0,1fr)); margin:16px 0; } .ops-grid.two { grid-template-columns:1fr 1fr; margin:16px 0; }
        .ops-card { background:#fff; border:1px solid var(--line); border-radius:8px; padding:16px; box-shadow:var(--shadow); } .ops-card h2, .ops-card h3 { color:var(--ink-strong); margin:0 0 10px; } .ops-card h2 { font-size:20px; } .ops-card h3 { font-size:17px; } .ops-card p { color:#31486d; line-height:1.45; margin:7px 0; }
        .metric-card { min-height:108px; border-left:4px solid var(--brand); } .metric-card.good { border-left-color:var(--green); } .metric-card.bad { border-left-color:var(--red); } .metric-card.warn { border-left-color:var(--amber); }
        .metric-card strong { display:block; font-size:30px; color:var(--ink-strong); line-height:1; } .metric-card span { display:block; color:#344a70; margin-top:8px; font-weight:700; } .metric-card small { display:block; color:#5c6b84; margin-top:7px; line-height:1.35; }
        .status-list { list-style:none; margin:0; padding:0; } .status-list li { display:flex; justify-content:space-between; gap:16px; align-items:flex-start; padding:11px 0; border-bottom:1px solid var(--line); } .status-list li:last-child { border-bottom:0; } .status-list span { color:#40577b; } .status-list strong { color:#071f4f; text-align:right; }
        .table-wrap { overflow:auto; border:1px solid var(--line); border-radius:8px; } table { width:100%; border-collapse:collapse; min-width:900px; } th,td { padding:10px 12px; border-bottom:1px solid var(--line); text-align:left; vertical-align:top; } th { color:#445078; text-transform:uppercase; letter-spacing:.05em; font-size:11px; background:#f6f8fc; }
        .mono { font-family:Consolas, Monaco, monospace; font-size:12px; overflow-wrap:anywhere; } pre { white-space:pre-wrap; word-break:break-word; margin:8px 0 0; background:#f6f8fc; border:1px solid var(--line); padding:10px; border-radius:6px; color:#15264f; max-height:360px; overflow:auto; }
        .btn { display:inline-flex; align-items:center; justify-content:center; text-decoration:none; border:0; border-radius:4px; padding:10px 13px; color:#fff; font-weight:800; font-size:13px; background:var(--brand); box-shadow:var(--shadow); } .btn.green { background:var(--green); } .btn.amber { background:var(--amber); } .btn.blue { background:var(--blue); } .btn.slate { background:#687386; }
        .ops-actions { display:flex; flex-wrap:wrap; gap:9px; margin-top:14px; }
        tr.event-error { background:#fff6f5; } tr.event-summary, tr.event-finish { background:#f8fff9; }
        @media (max-width:1300px) { .ops-grid.metrics { grid-template-columns:repeat(3,1fr); } .ops-grid.two { grid-template-columns:1fr; } }
        @media (max-width:1200px) { .ops-shell-layout { grid-template-columns:1fr; } .ops-shell-sidebar { position:static; } }
        @media (max-width:760px) { .ops-shell-topbar { padding:10px 14px; flex-wrap:wrap; } .ops-shell-brand { min-width:0; } .ops-shell-user { display:none; } .ops-main { padding:16px 14px 44px; } .ops-page-title { display:block; } .ops-grid.metrics { grid-template-columns:1fr; } }
    </style>
</head>
<body>
<?php gov_ops_render_topbar('pre_ride'); ?>
<div class="ops-shell-layout">
    <?php gov_ops_render_sidebar('v3_pulse_focus'); ?>
    <main class="ops-main">
        <div class="ops-page-title">
            <div>
                <div class="ops-kicker">V3 Pulse Visibility</div>
                <h1>V3 Pulse Focus</h1>
                <p class="ops-page-subtitle">Fast read-only view for the V3 pulse cron log, latest cycle summary, lock file ownership, and recent pulse events. This page does not run the pipeline and does not make operational decisions.</p>
            </div>
            <div>
                <?= gov_ops_badge('Production', 'dark') ?>
                <?= gov_ops_badge('V3 only', 'info') ?>
                <?= gov_ops_badge('V0 untouched', 'good') ?>
                <?= gov_ops_badge('Live submit disabled', 'bad') ?>
            </div>
        </div>

        <?php gov_ops_render_page_tabs([
            ['key' => 'monitor', 'label' => 'Compact Monitor', 'href' => '/ops/pre-ride-email-v3-monitor.php'],
            ['key' => 'queue_focus', 'label' => 'Queue Focus', 'href' => '/ops/pre-ride-email-v3-queue-focus.php'],
            ['key' => 'pulse_focus', 'label' => 'Pulse Focus', 'href' => '/ops/pre-ride-email-v3-pulse-focus.php'],
            ['key' => 'dashboard', 'label' => 'V3 Dashboard', 'href' => '/ops/pre-ride-email-v3-dashboard.php'],
            ['key' => 'queue_watch', 'label' => 'Queue Watch', 'href' => '/ops/pre-ride-email-v3-queue-watch.php'],
            ['key' => 'pulse', 'label' => 'Pulse Monitor', 'href' => '/ops/pre-ride-email-v3-fast-pipeline-pulse.php'],
            ['key' => 'readiness', 'label' => 'Automation Readiness', 'href' => '/ops/pre-ride-email-v3-automation-readiness.php'],
            ['key' => 'storage', 'label' => 'Storage Check', 'href' => '/ops/pre-ride-email-v3-storage-check.php'],
        ], 'pulse_focus'); ?>

        <section class="ops-alert safe">
            <strong>SAFE READ-ONLY V3 VIEW.</strong>
            No Bolt call, no EDXEIX call, no AADE call, no DB writes, no V0 production helper changes.
        </section>

        <?php if (!$overallOk): ?>
            <section class="ops-alert warn">
                <strong>Pulse attention:</strong>
                Check the cards below. The page only reads files; it does not repair permissions or run cron workers.
            </section>
        <?php endif; ?>

        <section class="ops-grid metrics">
            <article class="ops-card metric-card <?= $overallOk ? 'good' : 'warn' ?>">
                <strong><?= $overallOk ? 'OK' : 'CHECK' ?></strong>
                <span>Pulse health</span>
                <small><?= gov_ops_h($lastSummary) ?></small>
            </article>
            <article class="ops-card metric-card <?= gov_v3_pf_exit_badge_type($exitCode) ?>">
                <strong><?= $exitCode === null ? 'n/a' : gov_ops_h((string)$exitCode) ?></strong>
                <span>Last exit code</span>
                <small>Expected 0.</small>
            </article>
            <article class="ops-card metric-card <?= gov_v3_pf_seconds_badge_type(is_int($seconds) ? $seconds : null) ?>">
                <strong><?= $seconds === null ? 'n/a' : gov_ops_h((string)$seconds) ?></strong>
                <span>Seconds since finish</span>
                <small>Fresh pulse should stay recent.</small>
            </article>
            <article class="ops-card metric-card <?= $lockOk ? 'good' : 'bad' ?>">
                <strong><?= $lockOk ? 'OK' : 'BAD' ?></strong>
                <span>Pulse lock</span>
                <small><?= gov_ops_h($lockOwner) ?> / <?= gov_ops_h($lockPerms) ?></small>
            </article>
            <article class="ops-card metric-card <?= empty($analysis['tail_errors']) ? 'good' : 'bad' ?>">
                <strong><?= gov_ops_h((string)count($analysis['tail_errors'])) ?></strong>
                <span>Recent errors</span>
                <small>ERROR lines in recent tail.</small>
            </article>
        </section>

        <section class="ops-grid two">
            <article class="ops-card">
                <h2>Latest Pulse State</h2>
                <ul class="status-list">
                    <li><span>Log readable</span><strong><?= gov_ops_badge($logOk ? 'yes' : 'no', $logOk ? 'good' : 'bad') ?></strong></li>
                    <li><span>Cron worker file</span><strong><?= gov_ops_badge($cronOk ? 'present' : 'missing', $cronOk ? 'good' : 'bad') ?></strong></li>
                    <li><span>Pulse CLI file</span><strong><?= gov_ops_badge($pulseCliOk ? 'present' : 'missing', $pulseCliOk ? 'good' : 'bad') ?></strong></li>
                    <li><span>Last start</span><strong class="mono"><?= gov_ops_h($lastStart) ?></strong></li>
                    <li><span>Last summary</span><strong class="mono"><?= gov_ops_h($lastSummary) ?></strong></li>
                    <li><span>Last finish</span><strong class="mono"><?= gov_ops_h($lastFinish) ?></strong></li>
                    <li><span>Last error</span><strong class="mono"><?= gov_ops_h($lastError) ?></strong></li>
                </ul>
                <div class="ops-actions">
                    <a class="btn green" href="/ops/pre-ride-email-v3-monitor.php">Compact Monitor</a>
                    <a class="btn blue" href="/ops/pre-ride-email-v3-queue-focus.php">Queue Focus</a>
                    <a class="btn slate" href="/ops/pre-ride-email-v3-storage-check.php">Storage Check</a>
                </div>
            </article>

            <article class="ops-card">
                <h2>Lock / Path Details</h2>
                <ul class="status-list">
                    <li><span>Log path</span><strong class="mono"><?= gov_ops_h($logPath) ?></strong></li>
                    <li><span>Lock path</span><strong class="mono"><?= gov_ops_h($lockPath) ?></strong></li>
                    <li><span>Lock exists</span><strong><?= gov_ops_badge(is_file($lockPath) ? 'yes' : 'no', is_file($lockPath) ? 'good' : 'bad') ?></strong></li>
                    <li><span>Lock readable</span><strong><?= gov_ops_badge(is_readable($lockPath) ? 'yes' : 'no', is_readable($lockPath) ? 'good' : 'bad') ?></strong></li>
                    <li><span>Lock writable</span><strong><?= gov_ops_badge(is_writable($lockPath) ? 'yes' : 'no', is_writable($lockPath) ? 'good' : 'bad') ?></strong></li>
                    <li><span>Owner/group</span><strong><?= gov_ops_badge($lockOwner, $lockOwner === 'cabnet:cabnet' ? 'good' : 'bad') ?></strong></li>
                    <li><span>Permissions</span><strong><?= gov_ops_badge($lockPerms, $lockPerms === '0660' ? 'good' : 'warn') ?></strong></li>
                </ul>
                <p><strong>Operator note:</strong> manually test the pulse cron worker as <code>cabnet</code>, not root, to avoid root-owned lock files.</p>
            </article>
        </section>

        <section class="ops-card">
            <h2>Recent Pulse Events</h2>
            <?php if (empty($analysis['events'])): ?>
                <p>No recent pulse events found in the log tail.</p>
            <?php else: ?>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>Type</th><th>Event line</th></tr></thead>
                        <tbody>
                        <?php foreach ($analysis['events'] as $event): ?>
                            <?php $type = (string)($event['type'] ?? 'event'); ?>
                            <tr class="event-<?= gov_ops_h($type) ?>">
                                <td><?= gov_ops_badge($type, (string)($event['badge'] ?? 'neutral')) ?></td>
                                <td class="mono"><?= gov_ops_h((string)($event['line'] ?? '')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <section class="ops-card">
            <h2>Recent Raw Log Tail</h2>
            <pre><?= gov_ops_h(implode("\n", array_slice($lines, -80))) ?></pre>
        </section>
    </main>
</div>
</body>
</html>
