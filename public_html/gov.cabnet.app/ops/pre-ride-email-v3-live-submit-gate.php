<?php
/**
 * gov.cabnet.app — V3 Live-Submit Master Gate Dashboard
 *
 * Read-only page showing whether any future V3 live-submit worker is permitted.
 */

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Robots-Tag: noindex, nofollow', true);

const V3_GATE_PAGE_VERSION = 'v3.0.25-live-submit-master-gate-page';

function v3gate_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function v3gate_badge(string $text, string $type = 'neutral'): string
{
    return '<span class="badge badge-' . v3gate_h($type) . '">' . v3gate_h($text) . '</span>';
}

function v3gate_private_file(string $relative): string
{
    $relative = ltrim($relative, '/');
    $candidates = [
        dirname(__DIR__, 3) . '/gov.cabnet.app_app/' . $relative,
        dirname(__DIR__, 2) . '/gov.cabnet.app_app/' . $relative,
    ];
    foreach ($candidates as $file) {
        if (is_file($file)) {
            return $file;
        }
    }
    return $candidates[0];
}

$error = '';
$result = null;
$classFile = v3gate_private_file('src/BoltMailV3/LiveSubmitGateV3.php');
if (!is_file($classFile)) {
    $error = 'LiveSubmitGateV3 class not found.';
} else {
    try {
        require_once $classFile;
        $result = \Bridge\BoltMailV3\LiveSubmitGateV3::evaluate();
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$ok = is_array($result) && !empty($result['ok_for_future_live_submit']);
$blocks = is_array($result) ? (array)($result['blocks'] ?? []) : [];
$warnings = is_array($result) ? (array)($result['warnings'] ?? []) : [];
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>V3 Live-Submit Gate | gov.cabnet.app</title>
<style>
:root{--bg:#f3f6fb;--panel:#fff;--ink:#061735;--muted:#41577a;--line:#d7e1ef;--nav:#081225;--blue:#2563eb;--green:#07875a;--orange:#b85c00;--red:#b42318;--purple:#6d28d9;--soft:#f8fbff}*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--ink);font-family:Arial,Helvetica,sans-serif}.nav{background:var(--nav);color:#fff;min-height:56px;display:flex;align-items:center;gap:18px;padding:0 26px;position:sticky;top:0;z-index:5}.nav a{color:#fff;text-decoration:none;font-size:14px;white-space:nowrap}.wrap{width:min(1480px,calc(100% - 48px));margin:22px auto 60px}.card{background:var(--panel);border:1px solid var(--line);border-radius:14px;padding:18px;margin-bottom:18px;box-shadow:0 10px 26px rgba(8,18,37,.04)}.hero{border-left:7px solid var(--purple)}h1{font-size:32px;margin:0 0 10px}h2{font-size:22px;margin:0 0 14px}p{color:var(--muted);line-height:1.45}.badge{display:inline-block;padding:5px 9px;border-radius:999px;font-size:12px;font-weight:700;margin:1px 3px 1px 0;white-space:nowrap}.badge-good{background:#dcfce7;color:#166534}.badge-warn{background:#fff7ed;color:#b45309}.badge-bad{background:#fee2e2;color:#991b1b}.badge-neutral{background:#eaf1ff;color:#1e40af}.badge-purple{background:#ede9fe;color:#5b21b6}.grid{display:grid;grid-template-columns:repeat(4,1fr);gap:10px}.metric{border:1px solid var(--line);border-radius:10px;padding:14px;background:var(--soft)}.metric strong{display:block;font-size:26px}.pre{white-space:pre-wrap;background:#07101f;color:#e6f0ff;padding:12px;border-radius:8px;overflow:auto;font-family:Consolas,Monaco,monospace;font-size:12px}.alert{border-radius:10px;padding:12px;margin:12px 0}.alert-info{background:#eff6ff;border:1px solid #bfdbfe}.alert-warn{background:#fff7ed;border:1px solid #fed7aa}.alert-bad{background:#fef2f2;border:1px solid #fecaca}.btn{display:inline-block;border:0;border-radius:8px;padding:9px 13px;background:var(--blue);color:#fff;text-decoration:none;font-weight:700;font-size:13px}.btn-dark{background:#263449}table{width:100%;border-collapse:collapse;border:1px solid var(--line);border-radius:10px;overflow:hidden;background:#fff}th,td{padding:10px;border-bottom:1px solid var(--line);text-align:left;vertical-align:top;font-size:13px}th{background:#f8fbff;font-size:12px}@media(max-width:900px){.grid{grid-template-columns:1fr}.wrap{width:calc(100% - 24px)}}
</style>
</head>
<body>
<nav class="nav">
    <strong>GC gov.cabnet.app</strong>
    <a href="/ops/pre-ride-email-v3-queue-watch.php">V3 Watch</a>
    <a href="/ops/pre-ride-email-v3-queue.php">V3 Queue</a>
    <a href="/ops/pre-ride-email-v3-live-readiness.php">V3 Live Readiness</a>
    <a href="/ops/pre-ride-email-v3-live-submit.php">V3 Live Submit</a>
    <a href="/ops/pre-ride-email-v3-live-payload-audit.php">V3 Payload Audit</a>
    <a href="/ops/pre-ride-email-v3-live-submit-gate.php">V3 Submit Gate</a>
</nav>
<div class="wrap">
    <section class="card hero">
        <h1>V3 Live-Submit Master Gate</h1>
        <p>Read-only gate status for any future V3 live EDXEIX submit worker. This page never submits to EDXEIX.</p>
        <?= v3gate_badge('V3 ISOLATED', 'purple') ?>
        <?= v3gate_badge('READ ONLY', 'good') ?>
        <?= v3gate_badge('NO EDXEIX CALL', 'good') ?>
        <?= v3gate_badge('NO AADE CALL', 'good') ?>
        <?= v3gate_badge(V3_GATE_PAGE_VERSION, 'neutral') ?>
        <div style="margin-top:14px">
            <a class="btn" href="/ops/pre-ride-email-v3-live-submit-gate.php">Refresh</a>
            <a class="btn btn-dark" href="/ops/pre-ride-email-v3-live-submit.php">Back to V3 live submit</a>
        </div>
    </section>

    <?php if ($error !== ''): ?>
        <section class="card"><div class="alert alert-bad"><strong>Error:</strong> <?= v3gate_h($error) ?></div></section>
    <?php endif; ?>

    <section class="card">
        <h2>Gate status</h2>
        <div class="grid">
            <div class="metric"><strong><?= $ok ? 'yes' : 'no' ?></strong><span>OK for future live submit</span></div>
            <div class="metric"><strong><?= !empty($result['enabled']) ? 'yes' : 'no' ?></strong><span>Config enabled</span></div>
            <div class="metric"><strong><?= v3gate_h($result['mode'] ?? '-') ?></strong><span>Mode</span></div>
            <div class="metric"><strong><?= v3gate_h($result['adapter'] ?? '-') ?></strong><span>Adapter</span></div>
        </div>
        <div class="alert <?= $ok ? 'alert-info' : 'alert-warn' ?>">
            <strong>Current gate:</strong> <?= $ok ? 'open for a future explicitly approved live worker' : 'closed / hard-disabled' ?>.
            This still does not mean anything has submitted to EDXEIX.
        </div>
    </section>

    <section class="card">
        <h2>Config details</h2>
        <table>
            <tbody>
                <tr><th>Gate version</th><td><?= v3gate_h($result['version'] ?? '-') ?></td></tr>
                <tr><th>Config loaded</th><td><?= !empty($result['config_loaded']) ? v3gate_badge('yes','good') : v3gate_badge('no','bad') ?></td></tr>
                <tr><th>Config path</th><td><?= v3gate_h($result['config_path'] ?? '-') ?></td></tr>
                <tr><th>Required queue status</th><td><?= v3gate_h($result['required_queue_status'] ?? '-') ?></td></tr>
                <tr><th>Min future minutes</th><td><?= (int)($result['min_future_minutes'] ?? 0) ?></td></tr>
                <tr><th>Acknowledgement present</th><td><?= !empty($result['required_acknowledgement_present']) ? v3gate_badge('yes','good') : v3gate_badge('no','bad') ?></td></tr>
                <tr><th>Allowed lessors</th><td><?= v3gate_h(implode(', ', (array)($result['allowed_lessors'] ?? [])) ?: 'no config-level restriction') ?></td></tr>
                <tr><th>Operator approval required</th><td><?= !empty($result['operator_approval_required']) ? v3gate_badge('yes','warn') : v3gate_badge('no','neutral') ?></td></tr>
            </tbody>
        </table>
    </section>

    <section class="card">
        <h2>Blocks</h2>
        <?php if (!$blocks): ?>
            <p>No gate blocks reported.</p>
        <?php else: ?>
            <div class="pre"><?php foreach ($blocks as $b) { echo v3gate_h('Block: ' . $b) . "\n"; } ?></div>
        <?php endif; ?>
    </section>

    <section class="card">
        <h2>Warnings</h2>
        <?php if (!$warnings): ?>
            <p>No gate warnings reported.</p>
        <?php else: ?>
            <div class="pre"><?php foreach ($warnings as $w) { echo v3gate_h('Warning: ' . $w) . "\n"; } ?></div>
        <?php endif; ?>
    </section>

    <section class="card">
        <h2>Safety</h2>
        <div class="pre">No EDXEIX call.
No AADE call.
No database write.
No production submission_jobs access.
No production submission_attempts access.
Production pre-ride-email-tool.php is untouched.</div>
    </section>
</div>
</body>
</html>
