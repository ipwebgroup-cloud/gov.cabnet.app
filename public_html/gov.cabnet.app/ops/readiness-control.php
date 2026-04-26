<?php
declare(strict_types=1);
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Robots-Tag: noindex,nofollow', true);

require_once dirname(__DIR__) . '/bolt_readiness_audit.php';

function rdc_h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function rdc_badge(string $text, string $type='neutral'): string { return '<span class="badge badge-' . rdc_h($type) . '">' . rdc_h($text) . '</span>'; }
function rdc_yes($v): string { return $v ? rdc_badge('YES','good') : rdc_badge('NO','bad'); }
function rdc_metric($value, string $label): string { return '<div class="metric"><strong>' . rdc_h((string)$value) . '</strong><span>' . rdc_h($label) . '</span></div>'; }

$audit = null; $error = null;
try { $audit = gov_readiness_build_audit(['limit' => 40, 'analysis_limit' => 250]); }
catch (Throwable $e) { $error = $e->getMessage(); }

$verdict = $audit['verdict'] ?? 'NOT_READY';
$config = $audit['config_state'] ?? [];
$drivers = $audit['reference_counts']['drivers'] ?? ['mapped'=>0,'total'=>0];
$vehicles = $audit['reference_counts']['vehicles'] ?? ['mapped'=>0,'total'=>0];
$recent = $audit['recent_bookings'] ?? [];
$queue = $audit['queue_safety'] ?? [];
$lab = $audit['lab_safety'] ?? [];
$attempts = $audit['submission_attempt_safety'] ?? [];

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Readiness Control | gov.cabnet.app</title>
    <link rel="stylesheet" href="/assets/css/gov-ops-edxeix.css?v=2.0">
</head>
<body>
<div class="gov-topbar">
    <div class="gov-brand">
        <div class="gov-brand-crest">ΕΔ</div>
        <div class="gov-brand-text">
            <strong>gov.cabnet.app</strong>
            <span>Bolt → EDXEIX operational console</span>
        </div>
    </div>
    <div class="gov-top-links">
        <a href="/ops/index.php">Αρχική</a>
        <a href="/ops/admin-control.php">Administration</a>
        <a href="/ops/test-session.php">Test Session</a>
        <a href="/ops/preflight-review.php">Preflight Review</a>
        <a class="gov-logout" href="/ops/index.php">Safe Ops</a>
    </div>
</div>
<div class="gov-shell">
    <aside class="gov-sidebar">
        <h3>Readiness Control</h3>
        <p>Read-only safety and configuration posture.</p>
        <div class="gov-side-group"><div class="gov-side-group-title">Administration</div><a class="gov-side-link" href="/ops/admin-control.php">Admin Control</a><a class="gov-side-link active" href="/ops/readiness-control.php">Readiness Control</a><a class="gov-side-link" href="/ops/mapping-control.php">Mapping Review</a><a class="gov-side-link" href="/ops/jobs-control.php">Jobs Review</a><div class="gov-side-group-title">Workflow</div><a class="gov-side-link" href="/ops/test-session.php">Test Session</a><a class="gov-side-link" href="/ops/dev-accelerator.php">Dev Accelerator</a><a class="gov-side-link" href="/ops/preflight-review.php">Preflight Review</a><a class="gov-side-link" href="/ops/evidence-bundle.php">Evidence Bundle</a><a class="gov-side-link" href="/ops/evidence-report.php">Evidence Report</a></div>
        <div class="gov-side-note">Read-only admin companion pages. Original operational pages remain available and unchanged.</div>
    </aside>
    <div class="gov-content">
        <div class="gov-page-header">
            <div>
                <h1 class="gov-page-title">Έλεγχος ετοιμότητας</h1>
                <div class="gov-breadcrumbs">Αρχική / Διαχειριστικό / Έλεγχος ετοιμότητας</div>
            </div>
            <div class="gov-tabs"><a class="gov-tab active" href="/ops/readiness-control.php">Readiness</a><a class="gov-tab" href="/ops/readiness.php">Original Page</a><a class="gov-tab" href="/bolt_readiness_audit.php">JSON</a></div>
        </div>
        <main class="wrap wrap-shell">


<section class="safety">
    <strong>READ-ONLY READINESS CONTROL.</strong>
    This companion page reads the readiness audit only. It does not call Bolt, does not call EDXEIX, and does not write data.
</section>

<section class="card hero <?= $verdict === 'READY_FOR_REAL_BOLT_FUTURE_TEST' ? 'good' : 'warn' ?>">
    <h1>Bolt / EDXEIX Readiness Control</h1>
    <?php if ($error): ?>
        <p class="badline"><strong>Error:</strong> <?= rdc_h($error) ?></p>
    <?php else: ?>
        <p><strong>Verdict:</strong> <?= rdc_badge($verdict, $verdict === 'READY_FOR_REAL_BOLT_FUTURE_TEST' ? 'good' : 'warn') ?> <?= rdc_h($audit['verdict_reason'] ?? '') ?></p>
        <div class="grid">
            <?= rdc_metric(($drivers['mapped'] ?? 0) . '/' . ($drivers['total'] ?? 0), 'Driver mappings') ?>
            <?= rdc_metric(($vehicles['mapped'] ?? 0) . '/' . ($vehicles['total'] ?? 0), 'Vehicle mappings') ?>
            <?= rdc_metric($recent['submission_safe_rows'] ?? 0, 'Real future candidates') ?>
            <?= rdc_metric($queue['submission_jobs_total'] ?? 0, 'Local submission jobs') ?>
        </div>
        <div class="actions">
            <a class="btn" href="/bolt_readiness_audit.php">Open JSON Audit</a>
            <a class="btn dark" href="/ops/readiness.php">Original Readiness Page</a>
            <a class="btn warn" href="/ops/preflight-review.php">Preflight Review</a>
        </div>
    <?php endif; ?>
</section>

<?php if (!$error && $audit): ?>
<section class="two">
    <div class="card">
        <h2>Configuration / Bootstrap</h2>
        <div class="kv">
            <div class="k">Bridge library loaded</div><div><?= rdc_yes($audit['bootstrap']['loaded'] ?? false) ?></div>
            <div class="k">Environment</div><div><strong><?= rdc_h($config['app_env'] ?? '') ?></strong></div>
            <div class="k">Debug enabled</div><div><?= !empty($config['debug_enabled']) ? rdc_badge('YES','bad') : rdc_badge('NO','good') ?></div>
            <div class="k">Dry run enabled</div><div><?= !empty($config['dry_run_enabled']) ? rdc_badge('YES','good') : rdc_badge('NO','bad') ?></div>
            <div class="k">Bolt credentials present</div><div><?= rdc_yes($config['bolt_credentials_present'] ?? false) ?></div>
            <div class="k">EDXEIX lessor configured</div><div><?= rdc_yes($config['edxeix_lessor_present'] ?? false) ?></div>
            <div class="k">Starting point configured</div><div><?= rdc_yes($config['edxeix_default_starting_point_present'] ?? false) ?></div>
            <div class="k">Future guard</div><div><strong><?= rdc_h($config['future_start_guard_minutes'] ?? 30) ?> minutes</strong></div>
        </div>
    </div>
    <div class="card">
        <h2>LAB / Attempt Safety</h2>
        <div class="kv">
            <div class="k">LAB normalized rows</div><div><?= ((int)($lab['normalized_lab_rows'] ?? 0) === 0) ? rdc_badge('0','good') : rdc_badge((string)$lab['normalized_lab_rows'],'bad') ?></div>
            <div class="k">Staged LAB jobs</div><div><?= ((int)($lab['staged_lab_jobs'] ?? 0) === 0) ? rdc_badge('0','good') : rdc_badge((string)$lab['staged_lab_jobs'],'bad') ?></div>
            <div class="k">Submission attempts</div><div><strong><?= rdc_h($attempts['total'] ?? 0) ?></strong></div>
            <div class="k">Dry-run attempts</div><div><strong><?= rdc_h($attempts['dry_run_indicated'] ?? 0) ?></strong></div>
            <div class="k">Live attempts indicated</div><div><?= ((int)($attempts['confirmed_live_indicated'] ?? 0) === 0) ? rdc_badge('0','good') : rdc_badge((string)$attempts['confirmed_live_indicated'],'bad') ?></div>
            <div class="k">Confidence</div><div><code><?= rdc_h($attempts['confidence'] ?? '') ?></code></div>
        </div>
    </div>
</section>

<section class="card">
    <h2>Recent Normalized Bookings</h2>
    <?php if (empty($recent['rows'])): ?>
        <p>No recent normalized bookings found.</p>
    <?php else: ?>
        <div class="table-wrap"><table>
            <thead><tr><th>ID</th><th>Source</th><th>Order Ref</th><th>Status</th><th>Started</th><th>Driver</th><th>Plate</th><th>Mapping</th><th>Future Guard</th><th>Safe</th><th>Blockers</th></tr></thead>
            <tbody>
            <?php foreach ($recent['rows'] as $row): ?>
                <tr>
                    <td><?= rdc_h($row['id'] ?? '') ?></td>
                    <td><?= rdc_h($row['source_system'] ?? '') ?></td>
                    <td><code><?= rdc_h($row['order_reference'] ?? '') ?></code></td>
                    <td><?= rdc_h($row['status'] ?? '') ?></td>
                    <td><?= rdc_h($row['started_at'] ?? '') ?></td>
                    <td><?= rdc_h($row['driver_name'] ?? '') ?></td>
                    <td><?= rdc_h($row['plate'] ?? '') ?></td>
                    <td><?= !empty($row['mapping_ready']) ? rdc_badge('YES','good') : rdc_badge('NO','bad') ?></td>
                    <td><?= !empty($row['future_guard_passed']) ? rdc_badge('PASS','good') : rdc_badge('BLOCKED','warn') ?></td>
                    <td><?= !empty($row['submission_safe']) ? rdc_badge('YES','good') : rdc_badge('NO','bad') ?></td>
                    <td><?= rdc_h(implode(', ', $row['blockers'] ?? [])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    <?php endif; ?>
</section>
<?php endif; ?>
        </main>
    </div>
</div>
</body>
</html>
