<?php
/**
 * gov.cabnet.app — Legacy Public Utility Quiet-Period Audit.
 * v3.0.94: read-only quiet-period classification for legacy public-root utilities.
 */

declare(strict_types=1);

require_once '/home/cabnet/gov.cabnet.app_app/cli/legacy_public_utility_quiet_period_audit.php';
require_once __DIR__ . '/_shell.php';

$quietDays = 14;
if (isset($_GET['quiet_days'])) {
    $candidate = filter_var($_GET['quiet_days'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 3650]]);
    if (is_int($candidate)) {
        $quietDays = $candidate;
    }
}

$report = gov_legacy_public_utility_quiet_period_audit_run($quietDays);

function gov_lpuq_ops_h(mixed $value): string
{
    if (function_exists('opsui_h')) {
        return opsui_h($value);
    }
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

if (function_exists('opsui_shell_begin')) {
    opsui_shell_begin([
        'title' => 'Legacy Utility Quiet-Period Audit',
        'page_title' => 'Legacy Utility Quiet-Period Audit',
        'active_section' => 'Developer Archive',
        'breadcrumbs' => 'Αρχική / Διαχειριστικό / Legacy Utility Quiet-Period Audit',
        'force_safe_notice' => true,
        'safe_notice' => 'READ ONLY. No route moves, no route deletes, no redirects, no DB, no Bolt call, no EDXEIX call, no AADE call.',
    ]);
}

$summary = is_array($report['summary'] ?? null) ? $report['summary'] : [];
$routes = is_array($report['routes'] ?? null) ? $report['routes'] : [];
?>
<style>
.legacy-quiet-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:12px;margin:16px 0}.legacy-quiet-card{border:1px solid #d8dde7;border-radius:12px;background:#fff;padding:14px;box-shadow:0 8px 24px rgba(26,33,52,.06)}.legacy-quiet-card strong{display:block;font-size:22px}.legacy-quiet-card span{color:#667085;font-size:12px;text-transform:uppercase;letter-spacing:.04em}.legacy-badge{display:inline-flex;align-items:center;border-radius:999px;padding:4px 9px;background:#eef1f8;border:1px solid #d8dde7;font-size:12px;font-weight:700}.legacy-badge.ok{background:#ecfdf3;border-color:#bbf7d0;color:#166534}.legacy-badge.warn{background:#fffbeb;border-color:#fde68a;color:#92400e}.legacy-badge.stop{background:#fef2f2;border-color:#fecaca;color:#991b1b}.legacy-table{width:100%;border-collapse:collapse;background:#fff;border:1px solid #d8dde7;border-radius:12px;overflow:hidden}.legacy-table th,.legacy-table td{border-bottom:1px solid #eef1f8;padding:10px;text-align:left;vertical-align:top}.legacy-table th{background:#f8fafc;color:#344054;font-size:12px;text-transform:uppercase;letter-spacing:.04em}.legacy-muted{color:#667085}.legacy-code{font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace;font-size:12px}.legacy-notice{border:1px solid #fde68a;background:#fffbeb;border-radius:12px;padding:14px;margin:14px 0;color:#78350f}
</style>
<section>
    <h1>Legacy Public Utility Quiet-Period Audit</h1>
    <p class="legacy-muted">Read-only classification layered on the v3.0.93 usage audit. It only helps decide whether future compatibility-stub review is safe to discuss.</p>
    <div>
        <span class="legacy-badge warn"><?= gov_lpuq_ops_h((string)($report['version'] ?? 'v3.0.94')) ?></span>
        <span class="legacy-badge ok">READ ONLY</span>
        <span class="legacy-badge ok">NO DELETE</span>
        <span class="legacy-badge ok">NO MOVE</span>
        <span class="legacy-badge ok">NO REDIRECT</span>
        <span class="legacy-badge ok">NO DB</span>
        <span class="legacy-badge ok">NO EDXEIX CALL</span>
    </div>

    <div class="legacy-quiet-grid">
        <div class="legacy-quiet-card"><span>Routes reviewed</span><strong><?= gov_lpuq_ops_h($summary['routes_reviewed'] ?? 0) ?></strong></div>
        <div class="legacy-quiet-card"><span>Total mentions</span><strong><?= gov_lpuq_ops_h($summary['usage_mentions_total'] ?? 0) ?></strong></div>
        <div class="legacy-quiet-card"><span>Stub review candidates</span><strong><?= gov_lpuq_ops_h($summary['quiet_period_stub_review_candidates'] ?? 0) ?></strong></div>
        <div class="legacy-quiet-card"><span>Unknown-date evidence</span><strong><?= gov_lpuq_ops_h($summary['usage_evidence_with_unknown_date'] ?? 0) ?></strong></div>
        <div class="legacy-quiet-card"><span>Move now</span><strong><?= gov_lpuq_ops_h($summary['move_recommended_now'] ?? 0) ?></strong></div>
        <div class="legacy-quiet-card"><span>Delete now</span><strong><?= gov_lpuq_ops_h($summary['delete_recommended_now'] ?? 0) ?></strong></div>
    </div>

    <div class="legacy-notice">
        <strong>No route retirement is approved by this page.</strong>
        Candidate means only: safe to discuss a future authenticated compatibility-stub review after explicit approval and another dependency scan.
    </div>

    <table class="legacy-table">
        <thead>
        <tr>
            <th>Route</th>
            <th>Mentions</th>
            <th>Last seen</th>
            <th>Class</th>
            <th>Recommendation</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($routes as $row): if (!is_array($row)) { continue; }
            $class = (string)($row['review_class'] ?? '');
            $badgeClass = !empty($row['quiet_period_ok_for_stub_review']) ? 'warn' : 'stop';
            if ($class === 'no_usage_seen_in_scanned_sources') { $badgeClass = 'ok'; }
        ?>
        <tr>
            <td class="legacy-code"><?= gov_lpuq_ops_h($row['route'] ?? '') ?></td>
            <td><?= gov_lpuq_ops_h($row['mentions'] ?? 0) ?></td>
            <td>
                <div><?= gov_lpuq_ops_h($row['last_seen_raw'] ?? 'none') ?></div>
                <?php if (!empty($row['days_since_last_seen'])): ?><div class="legacy-muted"><?= gov_lpuq_ops_h($row['days_since_last_seen']) ?> days ago</div><?php endif; ?>
            </td>
            <td><span class="legacy-badge <?= gov_lpuq_ops_h($badgeClass) ?>"><?= gov_lpuq_ops_h($class) ?></span></td>
            <td><?= gov_lpuq_ops_h($row['recommended_action'] ?? '') ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>
<?php
if (function_exists('opsui_shell_end')) {
    opsui_shell_end();
}
