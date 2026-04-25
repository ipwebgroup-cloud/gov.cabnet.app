<?php
/**
 * gov.cabnet.app — Guided Operations Console
 *
 * Novice-friendly read-only workflow landing page.
 * This page does not call Bolt, does not call EDXEIX, does not write to the
 * database, and does not create queue jobs.
 */

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Robots-Tag: noindex, nofollow', true);

require_once dirname(__DIR__) . '/bolt_readiness_audit.php';

function od_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function od_badge(string $text, string $type = 'neutral'): string
{
    return '<span class="badge badge-' . od_h($type) . '">' . od_h($text) . '</span>';
}

function od_tip(string $text): string
{
    return '<span class="tip" tabindex="0" aria-label="' . od_h($text) . '">?</span>';
}

function od_status_badge(string $status): string
{
    $type = 'neutral';
    if (in_array($status, ['ready', 'complete', 'safe'], true)) { $type = 'good'; }
    if (in_array($status, ['waiting', 'attention', 'dry-run'], true)) { $type = 'warn'; }
    if (in_array($status, ['blocked', 'error'], true)) { $type = 'bad'; }
    return od_badge(strtoupper(str_replace('-', ' ', $status)), $type);
}

function od_check(bool $value): string
{
    return $value ? od_badge('OK', 'good') : od_badge('CHECK', 'warn');
}

function od_step_card(int $number, string $title, string $status, string $text, string $button, string $href, string $tip = '', string $secondaryText = '', string $secondaryHref = ''): string
{
    $html = '<article class="step-card step-' . od_h($status) . '">';
    $html .= '<div class="step-head"><span class="step-number">' . $number . '</span><div><h3>' . od_h($title);
    if ($tip !== '') { $html .= ' ' . od_tip($tip); }
    $html .= '</h3><div>' . od_status_badge($status) . '</div></div></div>';
    $html .= '<p>' . od_h($text) . '</p>';
    $html .= '<div class="actions"><a class="btn" href="' . od_h($href) . '">' . od_h($button) . '</a>';
    if ($secondaryText !== '' && $secondaryHref !== '') {
        $html .= '<a class="btn light" href="' . od_h($secondaryHref) . '">' . od_h($secondaryText) . '</a>';
    }
    $html .= '</div></article>';
    return $html;
}

$audit = null;
$error = null;
try {
    $audit = gov_readiness_build_audit(['limit' => 30, 'analysis_limit' => 200]);
} catch (Throwable $e) {
    $error = $e->getMessage();
}

$verdict = $audit['verdict'] ?? 'NOT_READY';
$config = $audit['config_state'] ?? [];
$drivers = $audit['reference_counts']['drivers'] ?? ['mapped' => 0, 'total' => 0, 'unmapped' => 0];
$vehicles = $audit['reference_counts']['vehicles'] ?? ['mapped' => 0, 'total' => 0, 'unmapped' => 0];
$recent = $audit['recent_bookings'] ?? [];
$queue = $audit['queue_safety'] ?? [];
$lab = $audit['lab_safety'] ?? [];
$attempts = $audit['submission_attempt_safety'] ?? [];

$dryRun = !empty($config['dry_run_enabled']);
$boltReady = !empty($config['bolt_credentials_present']);
$edxeixReady = !empty($config['edxeix_lessor_present']) && !empty($config['edxeix_default_starting_point_present']);
$hasMappedDriver = (int)($drivers['mapped'] ?? 0) > 0;
$hasMappedVehicle = (int)($vehicles['mapped'] ?? 0) > 0;
$cleanLab = (int)($lab['normalized_lab_rows'] ?? 0) === 0 && (int)($lab['staged_lab_jobs'] ?? 0) === 0;
$cleanQueue = (int)($queue['submission_jobs_total'] ?? 0) === 0;
$noLiveAttempts = (int)($attempts['confirmed_live_indicated'] ?? 0) === 0;
$submissionSafeRows = (int)($recent['submission_safe_rows'] ?? 0);

$systemClean = $dryRun && $boltReady && $edxeixReady && $cleanLab && $cleanQueue && $noLiveAttempts;
$mappingReady = $hasMappedDriver && $hasMappedVehicle;
$readyForFutureRide = $systemClean && $mappingReady;
$hasRealCandidate = $submissionSafeRows > 0;

$overallStatus = 'waiting';
$overallText = 'System is clean and waiting for a real future Bolt ride.';
if ($error !== null) {
    $overallStatus = 'error';
    $overallText = 'The console could not load the readiness audit.';
} elseif (!$systemClean) {
    $overallStatus = 'attention';
    $overallText = 'One or more safety checks need attention before a real test ride.';
} elseif (!$mappingReady) {
    $overallStatus = 'attention';
    $overallText = 'The system is clean, but mapping coverage still needs attention.';
} elseif ($hasRealCandidate) {
    $overallStatus = 'ready';
    $overallText = 'A real future candidate appears ready for preflight-only validation.';
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Guided Operations Console | gov.cabnet.app</title>
    <style>
        :root { --bg:#f3f6fb; --panel:#fff; --ink:#07152f; --muted:#41577a; --line:#d7e1ef; --nav:#081225; --blue:#2563eb; --green:#07875a; --orange:#b85c00; --red:#b42318; --slate:#334155; --soft:#f8fbff; }
        *{box-sizing:border-box} body{margin:0;background:var(--bg);color:var(--ink);font-family:Arial,Helvetica,sans-serif}.nav{background:var(--nav);color:#fff;min-height:56px;display:flex;align-items:center;gap:18px;padding:0 26px;position:sticky;top:0;z-index:5;overflow:auto}.nav strong{white-space:nowrap}.nav a{color:#fff;text-decoration:none;font-size:15px;white-space:nowrap;opacity:.92}.nav a:hover{opacity:1;text-decoration:underline}.wrap{width:min(1480px,calc(100% - 48px));margin:26px auto 60px}.card,.step-card{background:var(--panel);border:1px solid var(--line);border-radius:14px;padding:18px;margin-bottom:18px;box-shadow:0 10px 26px rgba(8,18,37,.04)}h1{font-size:34px;margin:0 0 12px}h2{font-size:23px;margin:0 0 14px}h3{margin:0 0 8px}p{color:var(--muted);line-height:1.45}.hero{border-left:7px solid var(--orange)}.hero.ready{border-left-color:var(--green)}.hero.error,.hero.attention{border-left-color:var(--red)}.safety-banner{background:#ecfdf3;border:1px solid #bbf7d0;border-left:7px solid var(--green);border-radius:14px;padding:16px;margin-bottom:18px}.safety-banner strong{color:#166534}.grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-top:14px}.metric{border:1px solid var(--line);border-radius:10px;padding:14px;background:var(--soft);min-height:82px}.metric strong{display:block;font-size:30px;line-height:1.05;word-break:break-word}.metric span{color:var(--muted);font-size:14px}.workflow{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}.step-card{margin:0;display:flex;flex-direction:column;justify-content:space-between;min-height:235px;border-top:5px solid var(--slate)}.step-card.step-ready,.step-card.step-safe,.step-card.step-complete{border-top-color:var(--green)}.step-card.step-waiting,.step-card.step-attention,.step-card.step-dry-run{border-top-color:var(--orange)}.step-card.step-blocked,.step-card.step-error{border-top-color:var(--red)}.step-head{display:flex;gap:12px;align-items:flex-start}.step-number{width:38px;height:38px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;background:#eaf1ff;color:#1e40af;font-weight:800;flex:0 0 38px}.actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:12px}.btn{display:inline-block;padding:10px 14px;border-radius:8px;color:#fff;text-decoration:none;font-weight:700;background:var(--blue);font-size:14px}.btn.light{background:var(--slate)}.badge{display:inline-block;padding:5px 9px;border-radius:999px;font-size:12px;font-weight:700;margin:1px 3px 1px 0;white-space:nowrap}.badge-good{background:#dcfce7;color:#166534}.badge-warn{background:#fff7ed;color:#b45309}.badge-bad{background:#fee2e2;color:#991b1b}.badge-neutral{background:#eaf1ff;color:#1e40af}.two{display:grid;grid-template-columns:1fr 1fr;gap:18px}.list{margin:0;padding-left:18px;color:var(--muted)}.list li{margin:7px 0}.tip{display:inline-flex;align-items:center;justify-content:center;width:18px;height:18px;border-radius:50%;background:#eaf1ff;color:#1e40af;font-size:12px;font-weight:800;cursor:help;position:relative}.tip:hover:after,.tip:focus:after{content:attr(aria-label);position:absolute;left:20px;top:-8px;width:260px;background:#081225;color:#fff;padding:10px;border-radius:8px;font-weight:400;line-height:1.35;z-index:10}.small{font-size:13px;color:var(--muted)}code{background:#eef2ff;padding:2px 5px;border-radius:5px}.badline{color:#991b1b}.goodline{color:#166534}.warnline{color:#b45309}@media(max-width:1150px){.workflow{grid-template-columns:repeat(2,minmax(0,1fr))}.grid{grid-template-columns:repeat(2,minmax(0,1fr))}.two{grid-template-columns:1fr}}@media(max-width:720px){.workflow,.grid{grid-template-columns:1fr}.wrap{width:calc(100% - 24px);margin-top:14px}.nav{padding:0 14px}.tip:hover:after,.tip:focus:after{left:auto;right:0;top:22px}}
    </style>
</head>
<body>
<nav class="nav">
    <strong>GC gov.cabnet.app</strong>
    <a href="/ops/index.php">Operations Console</a>
    <a href="/ops/readiness.php">Readiness</a>
    <a href="/ops/future-test.php">Future Test</a>
    <a href="/ops/mappings.php">Mappings</a>
    <a href="/ops/jobs.php">Jobs</a>
    <a href="/ops/help.php">Help</a>
</nav>

<main class="wrap">
    <section class="safety-banner">
        <strong>LIVE EDXEIX SUBMISSION IS DISABLED.</strong>
        This operations area is currently for read-only checks, preflight review, dry-run validation, and guarded mapping edits only.
    </section>

    <section class="card hero <?= od_h($overallStatus) ?>">
        <h1>Guided Operations Console</h1>
        <p><?= od_h($overallText) ?></p>
        <?php if ($error): ?><p class="badline"><strong>Error:</strong> <?= od_h($error) ?></p><?php endif; ?>
        <div><?= od_status_badge($overallStatus) ?> <?= od_badge('LIVE SUBMIT OFF', 'good') ?> <?= od_badge('OPS GUARDED', 'good') ?></div>
        <div class="grid">
            <div class="metric"><strong><?= od_h($verdict) ?></strong><span>Readiness verdict</span></div>
            <div class="metric"><strong><?= od_h(($drivers['mapped'] ?? 0) . '/' . ($drivers['total'] ?? 0)) ?></strong><span>Drivers mapped</span></div>
            <div class="metric"><strong><?= od_h(($vehicles['mapped'] ?? 0) . '/' . ($vehicles['total'] ?? 0)) ?></strong><span>Vehicles mapped</span></div>
            <div class="metric"><strong><?= od_h($submissionSafeRows) ?></strong><span>Real/future candidates</span></div>
        </div>
    </section>

    <section class="card">
        <h2>1–6 Guided Workflow</h2>
        <div class="workflow">
            <?= od_step_card(1, 'Check System', $systemClean ? 'ready' : 'attention', $systemClean ? 'System safety checks are clean. No LAB rows, queued jobs, or live attempts are currently detected.' : 'One or more safety checks need attention before you proceed.', 'Open Readiness', '/ops/readiness.php', 'This page checks configuration, schema, LAB cleanup state, local queue state, and live-attempt safety.', 'Open JSON', '/bolt_readiness_audit.php') ?>
            <?= od_step_card(2, 'Check Mappings', $mappingReady ? 'ready' : 'attention', $mappingReady ? 'At least one mapped driver and one mapped vehicle exist. Use only confirmed mappings for the real test.' : 'Some drivers or vehicles are still missing EDXEIX IDs. Do not guess IDs.', 'Open Mappings', '/ops/mappings.php', 'A mapping connects a Bolt driver or vehicle to the matching EDXEIX ID.') ?>
            <?= od_step_card(3, 'Wait for Bolt Ride', $hasRealCandidate ? 'ready' : 'waiting', $hasRealCandidate ? 'A real future candidate appears to exist. Continue to preflight review.' : 'No real future Bolt ride is available yet. When Filippos is present, create a ride 40–60 minutes in the future.', 'Open Future Test', '/ops/future-test.php', 'The ride must be real, non-terminal, mapped, and at least the configured future guard window in the future.') ?>
            <?= od_step_card(4, 'Review Preflight', $hasRealCandidate ? 'ready' : 'waiting', $hasRealCandidate ? 'Open the preflight preview and verify the EDXEIX payload carefully.' : 'Preflight will be useful after a real future Bolt candidate appears.', 'Open Preflight', '/bolt_edxeix_preflight.php?limit=30', 'Preflight builds a preview payload only. It does not submit to EDXEIX.') ?>
            <?= od_step_card(5, 'Dry-Run Only', $hasRealCandidate ? 'dry-run' : 'waiting', $hasRealCandidate ? 'After reviewing preflight, stage and record a local dry-run only if instructed.' : 'Dry-run queue actions should wait until a real future candidate has passed preflight.', 'Open Jobs', '/ops/jobs.php', 'A dry run records a local audit attempt without sending anything live.') ?>
            <?= od_step_card(6, 'Stop Before Live', 'safe', 'Live EDXEIX submission remains disabled. A separate explicit patch and approval would be required later.', 'Read Help', '/ops/help.php', 'This is the safety boundary. These tools are not a live-submit system.') ?>
        </div>
    </section>

    <section class="two">
        <div class="card">
            <h2>What should I do next?</h2>
            <?php if (!$systemClean): ?>
                <p class="badline"><strong>Next:</strong> Open Readiness and resolve the red or warning items.</p>
            <?php elseif (!$mappingReady): ?>
                <p class="warnline"><strong>Next:</strong> Open Mappings and fill only confirmed EDXEIX IDs. Do not map Georgios Zachariou yet.</p>
            <?php elseif (!$hasRealCandidate): ?>
                <p class="warnline"><strong>Next:</strong> Wait until Filippos is present, then create a real Bolt ride 40–60 minutes in the future using a mapped vehicle.</p>
            <?php else: ?>
                <p class="goodline"><strong>Next:</strong> Open Preflight and review the payload. Do not submit live.</p>
            <?php endif; ?>
            <ul class="list">
                <li>Recommended driver for first real test: <strong>Filippos Giannakopoulos → 17585</strong>.</li>
                <li>Recommended vehicles: <strong>EMX6874 → 13799</strong> or <strong>EHA2545 → 5949</strong>.</li>
                <li>Leave <strong>Georgios Zachariou</strong> unmapped until his exact EDXEIX driver ID is confirmed.</li>
            </ul>
        </div>
        <div class="card">
            <h2>Quick checks</h2>
            <ul class="list">
                <li>Dry-run mode: <?= od_check($dryRun) ?></li>
                <li>Bolt config present: <?= od_check($boltReady) ?></li>
                <li>EDXEIX config present: <?= od_check($edxeixReady) ?></li>
                <li>No LAB/test rows/jobs: <?= od_check($cleanLab) ?></li>
                <li>No local queue jobs: <?= od_check($cleanQueue) ?></li>
                <li>No live attempts: <?= od_check($noLiveAttempts) ?></li>
            </ul>
        </div>
    </section>
</main>
</body>
</html>
