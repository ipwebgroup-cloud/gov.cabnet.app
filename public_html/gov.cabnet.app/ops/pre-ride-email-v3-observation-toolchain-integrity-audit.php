<?php
/**
 * gov.cabnet.app — V3 Observation Toolchain Integrity Audit.
 *
 * v3.1.12:
 * - Read-only operator page for V3 observation toolchain integrity.
 * - Does not call Bolt, EDXEIX, AADE, or mutate DB/queue/files.
 */

declare(strict_types=1);

require_once __DIR__ . '/_shell.php';
require_once '/home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_observation_toolchain_integrity_audit.php';

$report = gov_v3_observation_toolchain_integrity_audit_run();
$summary = is_array($report['summary'] ?? null) ? $report['summary'] : [];
$files = is_array($report['files'] ?? null) ? $report['files'] : [];
$shell = is_array($report['shell'] ?? null) ? $report['shell'] : [];
$backups = is_array($report['public_backups'] ?? null) ? $report['public_backups'] : [];
$finalBlocks = is_array($report['final_blocks'] ?? null) ? $report['final_blocks'] : [];

opsui_shell_begin([
    'title' => 'V3 Observation Toolchain Integrity Audit',
    'page_title' => 'V3 Observation Toolchain Integrity Audit',
    'subtitle' => 'Safe Bolt → EDXEIX closed-gate monitoring',
    'breadcrumbs' => 'Αρχική / Διαχειριστικό / V3 Observation Toolchain Integrity Audit',
    'active_section' => 'V3 proof & readiness',
    'force_safe_notice' => true,
    'safe_notice' => 'READ-ONLY V3 TOOLCHAIN AUDIT. No Bolt, EDXEIX, AADE, DB write, queue mutation, route move, route delete, redirect, or live-submit action.',
]);
?>
<style>
.oti-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:14px;margin:16px 0}.oti-card{background:#fff;border:1px solid #d8dde7;border-radius:8px;padding:16px}.oti-card h3{margin:0 0 8px;color:#17386f;font-size:28px}.oti-muted{color:#55637f}.oti-badges{display:flex;flex-wrap:wrap;gap:8px;margin:12px 0}.oti-badge{display:inline-flex;align-items:center;border-radius:999px;padding:5px 9px;font-size:12px;font-weight:800;background:#e8f6ea;color:#176b24;border:1px solid #c8e9cc}.oti-badge.warn{background:#fff4dd;color:#885b00;border-color:#f0d49a}.oti-badge.bad{background:#feeceb;color:#9d241d;border-color:#f3b8b4}.oti-table{width:100%;border-collapse:collapse;background:#fff;border:1px solid #d8dde7;border-radius:8px;overflow:hidden}.oti-table th,.oti-table td{padding:10px;border-bottom:1px solid #e5e9f1;text-align:left;vertical-align:top}.oti-table th{background:#f6f8fb;color:#1d3764}.oti-code{display:inline-block;background:#f4f6fa;border:1px solid #d8dde7;border-radius:5px;padding:2px 5px;font-family:ui-monospace,SFMono-Regular,Consolas,monospace}.oti-block{background:#feeceb;border:1px solid #f3b8b4;color:#7e211b;border-radius:8px;padding:12px;margin:10px 0}.oti-actions{display:flex;flex-wrap:wrap;gap:8px;margin-top:12px}.btn{display:inline-flex;align-items:center;justify-content:center;border:0;border-radius:4px;background:#586482;color:#fff;text-decoration:none;padding:10px 14px;font-weight:800}.btn.primary{background:#4f5fad}.btn:hover{text-decoration:none;filter:brightness(.97)}
</style>

<section class="panel">
    <h1>V3 Observation Toolchain Integrity Audit</h1>
    <p>Checks that the V3 observation pages, CLI tools, shared shell navigation, side-note text, and public backup hygiene are safe and consistent.</p>
    <div class="oti-badges">
        <span class="oti-badge">READ ONLY</span>
        <span class="oti-badge">NO DB WRITES</span>
        <span class="oti-badge">NO QUEUE MUTATION</span>
        <span class="oti-badge">NO EDXEIX CALL</span>
        <span class="oti-badge">NO AADE CALL</span>
        <span class="oti-badge warn"><?= opsui_h((string)($report['version'] ?? 'v3.1.12')) ?></span>
    </div>
    <div class="oti-actions">
        <a class="btn primary" href="/ops/pre-ride-email-v3-observation-overview.php">Observation Overview</a>
        <a class="btn" href="/ops/pre-ride-email-v3-real-mail-queue-health.php">Queue Health</a>
        <a class="btn" href="/ops/pre-ride-email-v3-real-mail-expiry-reason-audit.php">Expiry Reason Audit</a>
        <a class="btn" href="/ops/pre-ride-email-v3-next-real-mail-candidate-watch.php">Next Candidate Watch</a>
    </div>
</section>

<section class="oti-grid">
    <div class="oti-card"><h3><?= !empty($report['ok']) ? 'yes' : 'no' ?></h3><p class="oti-muted">toolchain ok</p></div>
    <div class="oti-card"><h3><?= !empty($summary['component_files_ok']) ? 'yes' : 'no' ?></h3><p class="oti-muted">component files ok</p></div>
    <div class="oti-card"><h3><?= !empty($summary['shell_nav_ok']) ? 'yes' : 'no' ?></h3><p class="oti-muted">shell nav ok</p></div>
    <div class="oti-card"><h3><?= !empty($summary['shell_note_ok']) ? 'yes' : 'no' ?></h3><p class="oti-muted">shell note ok</p></div>
    <div class="oti-card"><h3><?= opsui_h((string)($summary['public_backup_files_found'] ?? 0)) ?></h3><p class="oti-muted">public backup files found</p></div>
    <div class="oti-card"><h3><?= !empty($summary['live_risk_detected']) ? 'yes' : 'no' ?></h3><p class="oti-muted">live risk detected</p></div>
</section>

<?php foreach ($finalBlocks as $block): ?>
    <div class="oti-block"><?= opsui_h((string)$block) ?></div>
<?php endforeach; ?>

<section class="panel">
    <h2>Observation overview status</h2>
    <table class="oti-table">
        <tbody>
            <tr><th>Overview OK</th><td><?= !empty($summary['overview_ok']) ? 'yes' : 'no' ?></td></tr>
            <tr><th>Queue health OK</th><td><?= !empty($summary['queue_health_ok']) ? 'yes' : 'no' ?></td></tr>
            <tr><th>Expiry audit OK</th><td><?= !empty($summary['expiry_audit_ok']) ? 'yes' : 'no' ?></td></tr>
            <tr><th>Candidate watch OK</th><td><?= !empty($summary['candidate_watch_ok']) ? 'yes' : 'no' ?></td></tr>
            <tr><th>Future active rows</th><td><?= opsui_h((string)($summary['future_active_rows'] ?? 0)) ?></td></tr>
            <tr><th>Operator review candidates</th><td><?= opsui_h((string)($summary['operator_review_candidates'] ?? 0)) ?></td></tr>
        </tbody>
    </table>
</section>

<section class="panel">
    <h2>Shared shell posture</h2>
    <table class="oti-table">
        <tbody>
            <tr><th>Navigation OK</th><td><?= !empty($shell['nav_ok']) ? 'yes' : 'no' ?></td></tr>
            <tr><th>Side-note OK</th><td><?= !empty($shell['note_ok']) ? 'yes' : 'no' ?></td></tr>
            <tr><th>Bad tokens found</th><td><?= opsui_h(json_encode($shell['bad_tokens_found'] ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?></td></tr>
            <tr><th>Public backups</th><td><?= opsui_h(json_encode($backups['files'] ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?></td></tr>
        </tbody>
    </table>
</section>

<section class="panel">
    <h2>Required files</h2>
    <table class="oti-table">
        <thead><tr><th>Key</th><th>Status</th><th>Version marker</th><th>Path</th></tr></thead>
        <tbody>
        <?php foreach ($files as $file): ?>
            <?php if (!is_array($file)) { continue; } ?>
            <tr>
                <td><code class="oti-code"><?= opsui_h((string)($file['key'] ?? '')) ?></code></td>
                <td><?= !empty($file['ok']) ? 'ok' : 'blocked' ?></td>
                <td><code class="oti-code"><?= opsui_h((string)($file['version_marker'] ?? '')) ?></code></td>
                <td><small><?= opsui_h((string)($file['path'] ?? '')) ?></small></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>

<section class="panel">
    <h2>Safe conclusion</h2>
    <ol>
        <li>This page verifies observation tooling only; it does not submit or mutate anything.</li>
        <li>If final blocks appear, fix them before relying on the V3 observation overview.</li>
        <li>Live EDXEIX submission remains blocked by the master gate.</li>
        <li>Do not enable live submit until Andreas explicitly requests a live-submit update and all live gates pass.</li>
    </ol>
</section>

<?php opsui_shell_end(); ?>
