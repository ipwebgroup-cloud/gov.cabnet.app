<?php
/**
 * gov.cabnet.app — Ops Home using shared UI shell v2.5
 *
 * Read-only operator landing page.
 * Does not call Bolt, does not call EDXEIX, does not stage jobs,
 * does not update mappings, and does not write database rows.
 */

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Robots-Tag: noindex,nofollow', true);

require_once __DIR__ . '/_shell.php';

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
];

if ($format === 'json') {
    oph_json_response($payload, $payload['ok'] ? 200 : 500);
}

opsui_shell_begin([
    'title' => 'Ops Home',
    'page_title' => 'Κεντρική σελίδα λειτουργιών',
    'active_section' => 'Ops Home',
    'subtitle' => 'Safe Bolt → EDXEIX operator landing page',
    'breadcrumbs' => 'Αρχική / Διαχειριστικό / Κεντρική σελίδα λειτουργιών',
    'safe_notice' => 'READ-ONLY OPS HOME. This landing page reads readiness state only. It does not call Bolt, does not call EDXEIX, does not stage jobs, and does not write data.',
]);
?>
<section class="card hero <?= opsui_h($heroType) ?>">
    <h1>Bolt → EDXEIX Operations Home</h1>
    <p><?= opsui_h($heroText) ?></p>
    <div>
        <?= opsui_badge('LIVE SUBMIT OFF', 'good') ?>
        <?= opsui_badge('NO BOLT CALL HERE', 'good') ?>
        <?= opsui_badge('NO EDXEIX CALL HERE', 'good') ?>
        <?= opsui_badge('READ ONLY', 'good') ?>
    </div>
    <div class="grid" style="margin-top:14px">
        <?= opsui_metric($verdict, 'Readiness verdict') ?>
        <?= opsui_metric(($drivers['mapped'] ?? 0) . '/' . ($drivers['total'] ?? 0), 'Drivers mapped') ?>
        <?= opsui_metric(($vehicles['mapped'] ?? 0) . '/' . ($vehicles['total'] ?? 0), 'Vehicles mapped') ?>
        <?= opsui_metric((string)$candidateCount, 'Real future candidates') ?>
    </div>
    <div class="actions">
        <a class="btn good" href="/ops/pre-ride-email-tool.php">Production Pre-Ride Tool</a>
        <a class="btn" href="/ops/pre-ride-email-toolv2.php">Open V2 Dev Wrapper</a>
        <a class="btn dark" href="/ops/route-index.php">Route Index</a>
        <a class="btn warn" href="/ops/preflight-review.php">Preflight Review</a>
    </div>
</section>

<?php if (!$readiness['ok']): ?>
    <section class="card">
        <h2>Readiness load problem</h2>
        <p class="badline"><strong><?= opsui_h((string)$readiness['error']) ?></strong></p>
    </section>
<?php endif; ?>

<section class="gov-admin-grid">
    <a class="gov-admin-link" href="/ops/pre-ride-email-tool.php"><strong>Production Pre-Ride Tool</strong><span>Current live operator tool. Do not modify during GUI development.</span><small>Stable production route</small></a>
    <a class="gov-admin-link" href="/ops/pre-ride-email-toolv2.php"><strong>Pre-Ride Tool V2 Dev</strong><span>Safe development wrapper using the shared shell.</span><small>Production tool remains untouched</small></a>
    <a class="gov-admin-link" href="/ops/profile.php"><strong>Operator Profile</strong><span>View current login/session details.</span><small>User area foundation</small></a>
    <a class="gov-admin-link" href="/ops/test-session.php"><strong>Test Session Control</strong><span>Main page to use during the next real future Bolt ride test.</span></a>
    <a class="gov-admin-link" href="/ops/preflight-review.php"><strong>Preflight Review</strong><span>Plain-language explanation of current preflight state and blockers.</span></a>
    <a class="gov-admin-link" href="/ops/admin-control.php"><strong>Admin Control</strong><span>Read-only administration hub for readiness, mappings, and jobs.</span></a>
</section>

<section class="two">
    <div class="card">
        <h2>Safety passport</h2>
        <div class="kv">
            <div class="k">Dry-run enabled</div><div><?= opsui_bool_badge($dryRun) ?></div>
            <div class="k">Bolt config present</div><div><?= opsui_bool_badge($boltConfig) ?></div>
            <div class="k">EDXEIX config present</div><div><?= opsui_bool_badge($edxeixConfig) ?></div>
            <div class="k">Mapped driver exists</div><div><?= opsui_bool_badge($hasMappedDriver) ?></div>
            <div class="k">Mapped vehicle exists</div><div><?= opsui_bool_badge($hasMappedVehicle) ?></div>
            <div class="k">Clean LAB state</div><div><?= opsui_bool_badge($cleanLab) ?></div>
            <div class="k">Clean queue</div><div><?= opsui_bool_badge($cleanQueue) ?></div>
            <div class="k">No live attempts</div><div><?= opsui_bool_badge($noLiveAttempts) ?></div>
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
            <p>For daily operation, use the production pre-ride tool. For GUI development, use the V2 wrapper.</p>
            <div class="actions">
                <a class="btn good" href="/ops/pre-ride-email-tool.php">Production Tool</a>
                <a class="btn" href="/ops/pre-ride-email-toolv2.php">V2 Dev Wrapper</a>
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
<?php
opsui_shell_end();
