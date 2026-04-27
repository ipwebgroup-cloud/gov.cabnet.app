<?php
/**
 * gov.cabnet.app — EDXEIX-style Ops Home
 *
 * Read-only operator landing page.
 * Does not call Bolt, does not call EDXEIX, does not stage jobs,
 * does not update mappings, and does not write database rows.
 */

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Robots-Tag: noindex,nofollow', true);

function oph_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function oph_badge(string $text, string $type = 'neutral'): string
{
    return '<span class="badge badge-' . oph_h($type) . '">' . oph_h($text) . '</span>';
}

function oph_metric($value, string $label): string
{
    return '<div class="metric"><strong>' . oph_h((string)$value) . '</strong><span>' . oph_h($label) . '</span></div>';
}

function oph_bool_badge(bool $value, string $yes = 'YES', string $no = 'NO'): string
{
    return oph_badge($value ? $yes : $no, $value ? 'good' : 'bad');
}

function oph_json_response(array $payload, int $statusCode = 200): void
{
    if (!headers_sent()) {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('X-Robots-Tag: noindex,nofollow', true);
    }
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit;
}

function oph_load_readiness(): array
{
    $path = dirname(__DIR__) . '/bolt_readiness_audit.php';
    $out = ['ok' => false, 'audit' => null, 'error' => null, 'path' => $path];

    try {
        if (!is_file($path) || !is_readable($path)) {
            throw new RuntimeException('bolt_readiness_audit.php was not found or is not readable.');
        }
        require_once $path;
        if (!function_exists('gov_readiness_build_audit')) {
            throw new RuntimeException('gov_readiness_build_audit() is unavailable after loading readiness audit.');
        }
        $out['audit'] = gov_readiness_build_audit(['limit' => 40, 'analysis_limit' => 250]);
        $out['ok'] = true;
    } catch (Throwable $e) {
        $out['error'] = $e->getMessage();
    }

    return $out;
}

$format = strtolower(trim((string)($_GET['format'] ?? 'html')));
$readiness = oph_load_readiness();
$audit = is_array($readiness['audit'] ?? null) ? $readiness['audit'] : [];

$config = is_array($audit['config_state'] ?? null) ? $audit['config_state'] : [];
$drivers = is_array($audit['reference_counts']['drivers'] ?? null) ? $audit['reference_counts']['drivers'] : ['mapped' => 0, 'total' => 0, 'unmapped' => 0];
$vehicles = is_array($audit['reference_counts']['vehicles'] ?? null) ? $audit['reference_counts']['vehicles'] : ['mapped' => 0, 'total' => 0, 'unmapped' => 0];
$recent = is_array($audit['recent_bookings'] ?? null) ? $audit['recent_bookings'] : ['submission_safe_rows' => 0];
$queue = is_array($audit['queue_safety'] ?? null) ? $audit['queue_safety'] : ['submission_jobs_total' => 0];
$lab = is_array($audit['lab_safety'] ?? null) ? $audit['lab_safety'] : [];
$attempts = is_array($audit['submission_attempt_safety'] ?? null) ? $audit['submission_attempt_safety'] : [];

$verdict = (string)($audit['verdict'] ?? 'NOT_READY');
$dryRun = !empty($config['dry_run_enabled']);
$boltConfig = !empty($config['bolt_credentials_present']);
$edxeixConfig = !empty($config['edxeix_lessor_present']) && !empty($config['edxeix_default_starting_point_present']);
$hasMappedDriver = (int)($drivers['mapped'] ?? 0) > 0;
$hasMappedVehicle = (int)($vehicles['mapped'] ?? 0) > 0;
$cleanLab = (int)($lab['normalized_lab_rows'] ?? 0) === 0 && (int)($lab['staged_lab_jobs'] ?? 0) === 0;
$cleanQueue = (int)($queue['submission_jobs_total'] ?? 0) === 0;
$noLiveAttempts = (int)($attempts['confirmed_live_indicated'] ?? 0) === 0;
$candidateCount = (int)($recent['submission_safe_rows'] ?? 0);

$readyForFutureTest = $readiness['ok'] && $dryRun && $boltConfig && $edxeixConfig && $hasMappedDriver && $hasMappedVehicle && $cleanLab && $cleanQueue && $noLiveAttempts;
$realCandidateReady = $readyForFutureTest && $candidateCount > 0;

$heroType = $realCandidateReady ? 'good' : ($readyForFutureTest ? 'warn' : 'bad');
$heroText = 'System needs attention before the next real future Bolt test.';
if ($realCandidateReady) {
    $heroText = 'A real future candidate appears ready for preflight-only review. Live submit remains blocked.';
} elseif ($readyForFutureTest) {
    $heroText = 'System is clean and waiting for a real future Bolt ride.';
}

$payload = [
    'ok' => $readiness['ok'],
    'script' => 'ops/home.php',
    'generated_at' => date('c'),
    'safety_contract' => [
        'calls_bolt' => false,
        'calls_edxeix' => false,
        'stages_jobs' => false,
        'updates_mappings' => false,
        'writes_database' => false,
        'live_edxeix_submission' => 'disabled_not_used',
        'purpose' => 'read-only operator landing page',
    ],
    'readiness' => [
        'loaded' => $readiness['ok'],
        'error' => $readiness['error'],
        'verdict' => $verdict,
        'ready_for_future_test' => $readyForFutureTest,
        'real_candidate_ready' => $realCandidateReady,
        'candidate_count' => $candidateCount,
        'mapped_drivers' => $drivers,
        'mapped_vehicles' => $vehicles,
        'dry_run_enabled' => $dryRun,
        'bolt_config_present' => $boltConfig,
        'edxeix_config_present' => $edxeixConfig,
        'clean_lab' => $cleanLab,
        'clean_queue' => $cleanQueue,
        'no_live_attempts' => $noLiveAttempts,
    ],
    'links' => [
        'html' => '/ops/home.php',
        'json' => '/ops/home.php?format=json',
        'test_session' => '/ops/test-session.php',
        'admin_control' => '/ops/admin-control.php',
        'readiness_control' => '/ops/readiness-control.php',
        'mapping_control' => '/ops/mapping-control.php',
        'jobs_control' => '/ops/jobs-control.php',
        'preflight_review' => '/ops/preflight-review.php',
        'dev_accelerator' => '/ops/dev-accelerator.php',
        'evidence_bundle' => '/ops/evidence-bundle.php',
        'evidence_report' => '/ops/evidence-report.php',
        'original_console' => '/ops/index.php',
    ],
];

if ($format === 'json') {
    oph_json_response($payload, $payload['ok'] ? 200 : 500);
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Ops Home | gov.cabnet.app</title>
    <link rel="stylesheet" href="/assets/css/gov-ops-edxeix.css?v=2.1">
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
        <a href="/ops/home.php">Αρχική</a>
        <a href="/ops/admin-control.php">Administration</a>
        <a href="/ops/test-session.php">Test Session</a>
        <a href="/ops/preflight-review.php">Preflight Review</a>
        <a class="gov-logout" href="/ops/index.php">Original Console</a>
    </div>
</div>

<div class="gov-shell">
    <aside class="gov-sidebar">
        <h3>Ops Home</h3>
        <p>Safe Bolt → EDXEIX operator landing page</p>

        <div class="gov-side-group">
            <div class="gov-side-group-title">Primary workflow</div>
            <a class="gov-side-link active" href="/ops/home.php">Ops Home</a>
            <a class="gov-side-link" href="/ops/test-session.php">Test Session Control</a>
            <a class="gov-side-link" href="/ops/preflight-review.php">Preflight Review</a>
            <a class="gov-side-link" href="/ops/dev-accelerator.php">Dev Accelerator</a>

            <div class="gov-side-group-title">Evidence</div>
            <a class="gov-side-link" href="/ops/evidence-bundle.php">Evidence Bundle</a>
            <a class="gov-side-link" href="/ops/evidence-report.php">Evidence Report</a>

            <div class="gov-side-group-title">Administration</div>
            <a class="gov-side-link" href="/ops/admin-control.php">Admin Control</a>
            <a class="gov-side-link" href="/ops/readiness-control.php">Readiness Control</a>
            <a class="gov-side-link" href="/ops/mapping-control.php">Mapping Review</a>
            <a class="gov-side-link" href="/ops/jobs-control.php">Jobs Review</a>
        </div>

        <div class="gov-side-note">Live EDXEIX submission remains blocked. This page is read-only.</div>
    </aside>

    <div class="gov-content">
        <div class="gov-page-header">
            <div>
                <h1 class="gov-page-title">Κεντρική σελίδα λειτουργιών</h1>
                <div class="gov-breadcrumbs">Αρχική / Διαχειριστικό / Κεντρική σελίδα λειτουργιών</div>
            </div>
            <div class="gov-tabs">
                <a class="gov-tab active" href="/ops/home.php">Καρτέλα</a>
                <a class="gov-tab" href="/ops/test-session.php">Test Session</a>
                <a class="gov-tab" href="/ops/admin-control.php">Administration</a>
                <a class="gov-tab" href="/ops/index.php">Original</a>
            </div>
        </div>

        <main class="wrap wrap-shell">
            <section class="safety">
                <strong>READ-ONLY OPS HOME.</strong>
                This landing page reads readiness state only. It does not call Bolt, does not call EDXEIX, does not stage jobs, and does not write data.
            </section>

            <section class="card hero <?= oph_h($heroType) ?>">
                <h1>Bolt → EDXEIX Operations Home</h1>
                <p><?= oph_h($heroText) ?></p>
                <div>
                    <?= oph_badge('LIVE SUBMIT OFF', 'good') ?>
                    <?= oph_badge('NO BOLT CALL HERE', 'good') ?>
                    <?= oph_badge('NO EDXEIX CALL HERE', 'good') ?>
                    <?= oph_badge('READ ONLY', 'good') ?>
                </div>
                <div class="grid" style="margin-top:14px">
                    <?= oph_metric($verdict, 'Readiness verdict') ?>
                    <?= oph_metric(($drivers['mapped'] ?? 0) . '/' . ($drivers['total'] ?? 0), 'Drivers mapped') ?>
                    <?= oph_metric(($vehicles['mapped'] ?? 0) . '/' . ($vehicles['total'] ?? 0), 'Vehicles mapped') ?>
                    <?= oph_metric((string)$candidateCount, 'Real future candidates') ?>
                </div>
                <div class="actions">
                    <a class="btn good" href="/ops/test-session.php">Open Test Session</a>
                    <a class="btn" href="/ops/admin-control.php">Open Admin Control</a>
                    <a class="btn dark" href="/ops/home.php?format=json">Open JSON</a>
                    <a class="btn warn" href="/ops/preflight-review.php">Preflight Review</a>
                </div>
            </section>

            <?php if (!$readiness['ok']): ?>
                <section class="card">
                    <h2>Readiness load problem</h2>
                    <p class="badline"><strong><?= oph_h((string)$readiness['error']) ?></strong></p>
                </section>
            <?php endif; ?>

            <section class="gov-admin-grid">
                <a class="gov-admin-link" href="/ops/test-session.php"><strong>Test Session Control</strong><span>Main page to use during the next real future Bolt ride test.</span></a>
                <a class="gov-admin-link" href="/ops/dev-accelerator.php"><strong>Dev Accelerator</strong><span>Dry-run capture buttons for accepted, pickup, started, and completed snapshots.</span></a>
                <a class="gov-admin-link" href="/ops/preflight-review.php"><strong>Preflight Review</strong><span>Plain-language explanation of current preflight state and blockers.</span></a>
                <a class="gov-admin-link" href="/ops/evidence-bundle.php"><strong>Evidence Bundle</strong><span>Review sanitized visibility snapshots after a real test ride.</span></a>
                <a class="gov-admin-link" href="/ops/evidence-report.php?format=md"><strong>Evidence Markdown</strong><span>Copy/paste report for implementation review.</span></a>
                <a class="gov-admin-link" href="/ops/admin-control.php"><strong>Admin Control</strong><span>Read-only administration hub for readiness, mappings, and jobs.</span></a>
            </section>

            <section class="two">
                <div class="card">
                    <h2>Safety passport</h2>
                    <div class="kv">
                        <div class="k">Dry-run enabled</div><div><?= oph_bool_badge($dryRun) ?></div>
                        <div class="k">Bolt config present</div><div><?= oph_bool_badge($boltConfig) ?></div>
                        <div class="k">EDXEIX config present</div><div><?= oph_bool_badge($edxeixConfig) ?></div>
                        <div class="k">Mapped driver exists</div><div><?= oph_bool_badge($hasMappedDriver) ?></div>
                        <div class="k">Mapped vehicle exists</div><div><?= oph_bool_badge($hasMappedVehicle) ?></div>
                        <div class="k">Clean LAB state</div><div><?= oph_bool_badge($cleanLab) ?></div>
                        <div class="k">Clean queue</div><div><?= oph_bool_badge($cleanQueue) ?></div>
                        <div class="k">No live attempts</div><div><?= oph_bool_badge($noLiveAttempts) ?></div>
                    </div>
                </div>

                <div class="card">
                    <h2>Recommended next action</h2>
                    <?php if ($realCandidateReady): ?>
                        <p class="goodline"><strong>Real future candidate detected.</strong></p>
                        <p>Open Preflight Review and continue with preflight-only verification. Do not submit live.</p>
                        <div class="actions">
                            <a class="btn good" href="/ops/preflight-review.php">Open Preflight Review</a>
                            <a class="btn warn" href="/bolt_edxeix_preflight.php?limit=30">Raw Preflight JSON</a>
                        </div>
                    <?php elseif ($readyForFutureTest): ?>
                        <p class="warnline"><strong>System is ready, but no real future Bolt candidate exists yet.</strong></p>
                        <p>When a real ride is available, start from Test Session Control.</p>
                        <div class="actions">
                            <a class="btn good" href="/ops/test-session.php">Open Test Session</a>
                            <a class="btn dark" href="/ops/readiness-control.php">Readiness Control</a>
                        </div>
                    <?php else: ?>
                        <p class="badline"><strong>Resolve readiness blockers before testing.</strong></p>
                        <p>Open Readiness Control to review configuration, mapping, LAB, queue, and live-attempt safety.</p>
                        <div class="actions">
                            <a class="btn warn" href="/ops/readiness-control.php">Open Readiness Control</a>
                            <a class="btn dark" href="/ops/mapping-control.php">Mapping Review</a>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
        </main>
    </div>
</div>
</body>
</html>
