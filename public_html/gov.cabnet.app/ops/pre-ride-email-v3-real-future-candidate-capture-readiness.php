<?php
/**
 * gov.cabnet.app — V3 Real Future Candidate Capture Readiness.
 *
 * v3.2.0:
 * - Read-only board for real future possible-real pre-ride candidate capture readiness.
 * - Shows minutes until pickup, completeness, missing fields, closed-gate review qualification,
 *   urgency/expiry posture, and operator-alert suitability.
 * - Does not call Bolt, EDXEIX, AADE, or mutate DB/queue/files.
 *
 * v3.2.1:
 * - Adds compact operator watch snapshot and fixes Latest rows table column alignment.
 *
 * v3.2.2:
 * - Adds sanitized candidate evidence snapshot section for closed-gate review.
 *
 * v3.2.3:
 * - Adds EDXEIX payload preview / dry-run preflight section.
 *
 * v3.2.4:
 * - Adds expired candidate safety regression audit section.
 *
 * v3.2.5:
 * - Adds controlled live-submit readiness checklist / go-no-go section.
 *
 * v3.2.6:
 * - Adds single-row controlled live-submit design draft section.
 *
 * v3.2.7:
 * - Adds controlled live-submit runbook / authorization packet section.
 *
 * v3.2.8:
 * - Adds real-format demo mail fixture preview section.
 *
 * v3.2.9:
 * - Adds controlled Maildir fixture writer design section.
 *
 * v3.2.10:
 * - Adds Maildir fixture writer preflight audit section.
 *
 * v3.2.11:
 * - Adds Maildir fixture writer authorization packet section.
 */

declare(strict_types=1);

require_once __DIR__ . '/_shell.php';
require_once '/home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_real_future_candidate_capture_readiness.php';

$report = gov_v3_real_future_candidate_capture_readiness_run();
$watchSnapshot = gov_v3rfccr_watch_snapshot($report);
$evidenceSnapshot = gov_v3rfccr_candidate_evidence_snapshot($report);
$edxeixPreview = gov_v3rfccr_edxeix_payload_preview($report);
$expiredSafetyAudit = gov_v3rfccr_expired_candidate_safety_audit($report);
$liveReadiness = gov_v3rfccr_controlled_live_submit_readiness($report);
$singleRowDesign = gov_v3rfccr_single_row_live_submit_design_draft($report);
$authorizationPacket = gov_v3rfccr_controlled_live_submit_authorization_packet($report);
$demoMailFixture = gov_v3rfccr_real_format_demo_mail_fixture_preview($report);
$maildirWriterDesign = gov_v3rfccr_controlled_maildir_fixture_writer_design($report);
$maildirWriterPreflight = gov_v3rfccr_controlled_maildir_fixture_writer_preflight($report);
$maildirWriterAuthorization = gov_v3rfccr_maildir_fixture_writer_authorization_packet($report);
$edxeixCandidate = is_array($edxeixPreview['candidate'] ?? null) ? $edxeixPreview['candidate'] : null;
$edxeixPayload = is_array($edxeixPreview['normalized_payload_preview'] ?? null) ? $edxeixPreview['normalized_payload_preview'] : null;
$edxeixChecks = is_array($edxeixPreview['dry_run_preflight_checks'] ?? null) ? $edxeixPreview['dry_run_preflight_checks'] : [];
$edxeixBlocks = is_array($edxeixPreview['preflight_blocks'] ?? null) ? $edxeixPreview['preflight_blocks'] : [];
$expiredStaleRows = is_array($expiredSafetyAudit['stale_live_submit_ready_rows'] ?? null) ? $expiredSafetyAudit['stale_live_submit_ready_rows'] : [];
$expiredAuditRules = is_array($expiredSafetyAudit['audit_rules'] ?? null) ? $expiredSafetyAudit['audit_rules'] : [];
$liveReadinessComponents = is_array($liveReadiness['component_results'] ?? null) ? $liveReadiness['component_results'] : [];
$liveReadinessManualGates = is_array($liveReadiness['manual_gates_before_any_future_live_submit_patch'] ?? null) ? $liveReadiness['manual_gates_before_any_future_live_submit_patch'] : [];
$liveReadinessNoGo = is_array($liveReadiness['hard_no_go_reasons'] ?? null) ? $liveReadiness['hard_no_go_reasons'] : [];
$singleRowPolicy = is_array($singleRowDesign['single_row_policy'] ?? null) ? $singleRowDesign['single_row_policy'] : [];
$singleRowSequence = is_array($singleRowDesign['pre_execution_gate_sequence'] ?? null) ? $singleRowDesign['pre_execution_gate_sequence'] : [];
$singleRowComponents = is_array($singleRowDesign['component_results'] ?? null) ? $singleRowDesign['component_results'] : [];
$authorizationGates = is_array($authorizationPacket['authorization_gates'] ?? null) ? $authorizationPacket['authorization_gates'] : [];
$authorizationRunbook = is_array($authorizationPacket['runbook_steps_for_future_explicit_patch'] ?? null) ? $authorizationPacket['runbook_steps_for_future_explicit_patch'] : [];
$authorizationComponents = is_array($authorizationPacket['component_results'] ?? null) ? $authorizationPacket['component_results'] : [];
$demoFixtureHeaders = is_array($demoMailFixture['fixture_headers_preview'] ?? null) ? $demoMailFixture['fixture_headers_preview'] : [];
$demoFixtureTimes = is_array($demoMailFixture['fixture_times'] ?? null) ? $demoMailFixture['fixture_times'] : [];
$demoFixtureIdentity = is_array($demoMailFixture['fixture_identity_preview'] ?? null) ? $demoMailFixture['fixture_identity_preview'] : [];
$demoFixtureRoute = is_array($demoMailFixture['fixture_route_and_price_preview'] ?? null) ? $demoMailFixture['fixture_route_and_price_preview'] : [];
$maildirWriterPolicy = is_array($maildirWriterDesign['single_write_policy'] ?? null) ? $maildirWriterDesign['single_write_policy'] : [];
$maildirWriterSequence = is_array($maildirWriterDesign['pre_write_gate_sequence'] ?? null) ? $maildirWriterDesign['pre_write_gate_sequence'] : [];
$maildirWriterComponents = is_array($maildirWriterDesign['component_results'] ?? null) ? $maildirWriterDesign['component_results'] : [];
$maildirPreflightPaths = is_array($maildirWriterPreflight['target_maildir_paths'] ?? null) ? $maildirWriterPreflight['target_maildir_paths'] : [];
$maildirPreflightFixture = is_array($maildirWriterPreflight['fixture_preflight'] ?? null) ? $maildirWriterPreflight['fixture_preflight'] : [];
$maildirPreflightComponents = is_array($maildirWriterPreflight['component_results'] ?? null) ? $maildirWriterPreflight['component_results'] : [];
$maildirPreflightBlocks = is_array($maildirWriterPreflight['preflight_blocks'] ?? null) ? $maildirWriterPreflight['preflight_blocks'] : [];
$maildirAuthorizationGates = is_array($maildirWriterAuthorization['authorization_gates'] ?? null) ? $maildirWriterAuthorization['authorization_gates'] : [];
$maildirAuthorizationRunbook = is_array($maildirWriterAuthorization['runbook_steps_for_future_explicit_writer_patch'] ?? null) ? $maildirWriterAuthorization['runbook_steps_for_future_explicit_writer_patch'] : [];
$maildirAuthorizationComponents = is_array($maildirWriterAuthorization['component_results'] ?? null) ? $maildirWriterAuthorization['component_results'] : [];
$evidenceCandidate = is_array($evidenceSnapshot['candidate'] ?? null) ? $evidenceSnapshot['candidate'] : null;
$evidenceTiming = is_array($evidenceCandidate['timing'] ?? null) ? $evidenceCandidate['timing'] : [];
$evidenceReadiness = is_array($evidenceCandidate['readiness'] ?? null) ? $evidenceCandidate['readiness'] : [];
$evidenceIdentity = is_array($evidenceCandidate['identity_presence'] ?? null) ? $evidenceCandidate['identity_presence'] : [];
$evidenceMapping = is_array($evidenceCandidate['operator_and_mapping'] ?? null) ? $evidenceCandidate['operator_and_mapping'] : [];
$evidenceRoute = is_array($evidenceCandidate['route_and_price'] ?? null) ? $evidenceCandidate['route_and_price'] : [];
$evidenceNegative = is_array($evidenceCandidate['negative_checks'] ?? null) ? $evidenceCandidate['negative_checks'] : [];
$watchCounts = is_array($watchSnapshot['counts'] ?? null) ? $watchSnapshot['counts'] : [];
$watchNext = is_array($watchSnapshot['next_candidate'] ?? null) ? $watchSnapshot['next_candidate'] : null;
$summary = is_array($report['summary'] ?? null) ? $report['summary'] : [];
$queue = is_array($report['queue'] ?? null) ? $report['queue'] : [];
$nextCandidate = is_array($queue['next_future_possible_real_row'] ?? null) ? $queue['next_future_possible_real_row'] : null;
$reviewRows = is_array($queue['closed_gate_operator_review_rows'] ?? null) ? $queue['closed_gate_operator_review_rows'] : [];
$alertRows = is_array($queue['operator_alert_rows'] ?? null) ? $queue['operator_alert_rows'] : [];
$futureRows = is_array($queue['future_possible_real_rows'] ?? null) ? $queue['future_possible_real_rows'] : [];
$latestRows = is_array($queue['latest_rows'] ?? null) ? $queue['latest_rows'] : [];
$warnings = is_array($report['warnings'] ?? null) ? $report['warnings'] : [];
$finalBlocks = is_array($report['final_blocks'] ?? null) ? $report['final_blocks'] : [];

function v3rfccr_bool_label(bool $value): string
{
    return $value ? 'yes' : 'no';
}

function v3rfccr_missing_label(array $row): string
{
    $missing = is_array($row['missing_required_fields'] ?? null) ? $row['missing_required_fields'] : [];
    return $missing ? implode(', ', array_map('strval', $missing)) : 'none';
}

opsui_shell_begin([
    'title' => 'V3 Real Future Candidate Capture Readiness',
    'page_title' => 'V3 Real Future Candidate Capture Readiness',
    'subtitle' => 'Safe real-mail capture readiness before pickup expiry',
    'breadcrumbs' => 'Αρχική / Διαχειριστικό / V3 Real Future Candidate Capture Readiness',
    'active_section' => 'V3 proof & readiness',
    'force_safe_notice' => true,
    'safe_notice' => 'READ-ONLY V3 CAPTURE READINESS. No Bolt, EDXEIX, AADE, DB write, queue mutation, route move, route delete, redirect, or live-submit action.',
]);
?>
<style>
.v3cap-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:14px;margin:16px 0}.v3cap-card{background:#fff;border:1px solid #d8dde7;border-radius:8px;padding:16px}.v3cap-card h3{margin:0 0 8px;color:#17386f;font-size:28px}.v3cap-muted{color:#55637f}.v3cap-badges{display:flex;flex-wrap:wrap;gap:8px;margin:12px 0}.v3cap-badge{display:inline-flex;align-items:center;border-radius:999px;padding:5px 9px;font-size:12px;font-weight:800;background:#e8f6ea;color:#176b24;border:1px solid #c8e9cc}.v3cap-badge.warn{background:#fff4dd;color:#885b00;border-color:#f0d49a}.v3cap-badge.bad{background:#feeceb;color:#9d241d;border-color:#f3b8b4}.v3cap-table{width:100%;border-collapse:collapse;background:#fff;border:1px solid #d8dde7;border-radius:8px;overflow:hidden}.v3cap-table th,.v3cap-table td{padding:10px;border-bottom:1px solid #e5e9f1;text-align:left;vertical-align:top}.v3cap-table th{background:#f6f8fb;color:#1d3764}.v3cap-code{display:inline-block;background:#f4f6fa;border:1px solid #d8dde7;border-radius:5px;padding:2px 5px;font-family:ui-monospace,SFMono-Regular,Consolas,monospace;max-width:100%;overflow-wrap:anywhere}.v3cap-warning{background:#fff4dd;border:1px solid #f0d49a;color:#704a00;border-radius:8px;padding:12px;margin:10px 0}.v3cap-block{background:#feeceb;border:1px solid #f3b8b4;color:#7e211b;border-radius:8px;padding:12px;margin:10px 0}.v3cap-actions{display:flex;flex-wrap:wrap;gap:8px;margin-top:12px}.btn{display:inline-flex;align-items:center;justify-content:center;border:0;border-radius:4px;background:#586482;color:#fff;text-decoration:none;padding:10px 14px;font-weight:800}.btn.primary{background:#4f5fad}.btn:hover{text-decoration:none;filter:brightness(.97)}.v3cap-next{background:#f7fbff;border:1px solid #cfe1ff;border-radius:10px;padding:16px;margin:16px 0}.v3cap-next strong{color:#17386f}.v3cap-small{font-size:12px;color:#55637f}.v3cap-scroll{overflow:auto}.v3cap-watch{background:#fbfcff;border:1px solid #cfd8ea;border-radius:10px;padding:16px;margin:16px 0}.v3cap-watch h2{margin-top:0}.v3cap-watch .status{font-size:18px;font-weight:900;color:#17386f}.v3cap-watch .status.urgent{color:#9d241d}.v3cap-watch .status.warning{color:#885b00}.v3cap-watch pre{white-space:pre-wrap;overflow-wrap:anywhere;background:#f4f6fa;border:1px solid #d8dde7;border-radius:8px;padding:10px}.v3cap-scroll{overflow:auto}
</style>

<section class="panel">
    <h1>V3 Real Future Candidate Capture Readiness</h1>
    <p>Detects whether a new possible-real future row exists and whether it is complete enough for closed-gate operator review before pickup expiry. This page is read-only and does not submit anything.</p>
    <div class="v3cap-badges">
        <span class="v3cap-badge">READ ONLY</span>
        <span class="v3cap-badge">NO DB WRITES</span>
        <span class="v3cap-badge">NO QUEUE MUTATION</span>
        <span class="v3cap-badge">NO BOLT CALL</span>
        <span class="v3cap-badge">NO EDXEIX CALL</span>
        <span class="v3cap-badge">NO AADE CALL</span>
        <span class="v3cap-badge warn"><?= opsui_h((string)($report['version'] ?? 'v3.2.10')) ?></span>
    </div>
    <div class="v3cap-actions">
        <a class="btn primary" href="/ops/pre-ride-email-v3-next-real-mail-candidate-watch.php">Next Candidate Watch</a>
        <a class="btn" href="/ops/pre-ride-email-v3-observation-overview.php">Observation Overview</a>
        <a class="btn" href="/ops/pre-ride-email-v3-live-operator-console.php">Live Operator Console</a>
        <a class="btn" href="/ops/pre-ride-email-v3-live-gate-drift-guard.php">Live Gate Drift Guard</a>
    </div>
</section>

<section class="v3cap-grid">
    <div class="v3cap-card"><h3><?= opsui_h((string)($summary['future_possible_real_rows'] ?? 0)) ?></h3><p class="v3cap-muted">future possible-real rows</p></div>
    <div class="v3cap-card"><h3><?= opsui_h((string)($summary['complete_future_possible_real_rows'] ?? 0)) ?></h3><p class="v3cap-muted">complete future rows</p></div>
    <div class="v3cap-card"><h3><?= opsui_h((string)($summary['closed_gate_operator_review_candidates'] ?? 0)) ?></h3><p class="v3cap-muted">closed-gate review candidates</p></div>
    <div class="v3cap-card"><h3><?= opsui_h((string)($summary['operator_alerts_appropriate'] ?? 0)) ?></h3><p class="v3cap-muted">operator alerts appropriate</p></div>
    <div class="v3cap-card"><h3><?= opsui_h((string)($summary['urgent_or_about_to_expire_rows'] ?? 0)) ?></h3><p class="v3cap-muted">urgent/about-to-expire rows</p></div>
    <div class="v3cap-card"><h3><?= opsui_h((string)($summary['stale_live_submit_ready_rows'] ?? 0)) ?></h3><p class="v3cap-muted">stale live-ready rows</p></div>
    <div class="v3cap-card"><h3><?= !empty($summary['live_risk_detected']) ? 'yes' : 'no' ?></h3><p class="v3cap-muted">live risk detected</p></div>
</section>

<section class="v3cap-watch">
    <h2>Operator Watch Snapshot</h2>
    <p class="status <?= opsui_h((string)($watchSnapshot['severity'] ?? 'clear')) ?>"><?= opsui_h((string)($watchSnapshot['action_code'] ?? 'UNKNOWN')) ?></p>
    <p><?= opsui_h((string)($watchSnapshot['action_label'] ?? 'No watch status available.')) ?></p>
    <div class="v3cap-grid">
        <div class="v3cap-card"><h3><?= opsui_h((string)($watchCounts['future_possible_real_rows'] ?? 0)) ?></h3><p class="v3cap-muted">watch future rows</p></div>
        <div class="v3cap-card"><h3><?= opsui_h((string)($watchCounts['closed_gate_operator_review_candidates'] ?? 0)) ?></h3><p class="v3cap-muted">watch review candidates</p></div>
        <div class="v3cap-card"><h3><?= opsui_h((string)($watchCounts['operator_alerts_appropriate'] ?? 0)) ?></h3><p class="v3cap-muted">watch alerts</p></div>
        <div class="v3cap-card"><h3><?= opsui_h((string)($watchCounts['urgent_or_about_to_expire_rows'] ?? 0)) ?></h3><p class="v3cap-muted">watch urgent rows</p></div>
    </div>
    <?php if ($watchNext): ?>
        <p><strong>Next candidate:</strong> queue <code class="v3cap-code"><?= opsui_h((string)($watchNext['id'] ?? '')) ?></code>, pickup <?= opsui_h((string)($watchNext['pickup_datetime'] ?? '')) ?>, <?= opsui_h((string)($watchNext['minutes_until_pickup_now'] ?? '')) ?> minutes, complete <?= !empty($watchNext['complete']) ? 'yes' : 'no' ?>.</p>
    <?php else: ?>
        <p>No next candidate is currently visible in the watch snapshot.</p>
    <?php endif; ?>
    <p><strong>Manual terminal watch:</strong></p>
    <pre>watch -n 30 '/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_real_future_candidate_capture_readiness.php --status-line'</pre>
    <p class="v3cap-small">This is manual terminal polling only. It does not create cron jobs, notifications, logs, DB writes, queue changes, or live submissions.</p>
</section>

<section class="panel">
    <h2>Candidate Evidence Snapshot</h2>
    <p>This is a sanitized, read-only evidence view for operator review. It intentionally hides raw payloads, parsed JSON, hashes, full mailbox paths, and unmasked customer phone numbers.</p>
    <?php if (empty($evidenceSnapshot['candidate_found']) || !$evidenceCandidate): ?>
        <p>No candidate evidence is available because no real future candidate is currently visible.</p>
    <?php else: ?>
        <div class="v3cap-next">
            <p><strong>Outcome:</strong> <code class="v3cap-code"><?= opsui_h((string)($evidenceSnapshot['operator_review_outcome'] ?? '')) ?></code></p>
            <p><strong>Queue ID:</strong> <code class="v3cap-code"><?= opsui_h((string)($evidenceCandidate['queue_id'] ?? '')) ?></code> · <strong>Status:</strong> <code class="v3cap-code"><?= opsui_h((string)($evidenceCandidate['queue_status'] ?? '')) ?></code></p>
            <p><strong>Source file:</strong> <code class="v3cap-code"><?= opsui_h((string)($evidenceCandidate['source_mailbox_file'] ?? '')) ?></code></p>
            <p><strong>Timing:</strong> pickup <?= opsui_h((string)($evidenceTiming['pickup_datetime'] ?? '')) ?> · end <?= opsui_h((string)($evidenceTiming['estimated_end_datetime'] ?? '')) ?> · <?= opsui_h((string)($evidenceTiming['minutes_until_pickup_now'] ?? '')) ?> minutes remaining.</p>
            <p><strong>Readiness:</strong> complete <?= !empty($evidenceReadiness['complete']) ? 'yes' : 'no' ?> · review <?= !empty($evidenceReadiness['closed_gate_operator_review']) ? 'yes' : 'no' ?> · reason <code class="v3cap-code"><?= opsui_h((string)($evidenceReadiness['reason'] ?? '')) ?></code></p>
            <p><strong>Customer fields:</strong> name present <?= !empty($evidenceIdentity['customer_name_present']) ? 'yes' : 'no' ?> · phone <?= opsui_h((string)($evidenceIdentity['customer_phone_masked'] ?? '')) ?></p>
            <p><strong>Driver / vehicle:</strong> <?= opsui_h((string)($evidenceMapping['driver_name'] ?? '')) ?> · <code class="v3cap-code"><?= opsui_h((string)($evidenceMapping['vehicle_plate'] ?? '')) ?></code></p>
            <p><strong>Mapping IDs:</strong> lessor <?= opsui_h((string)($evidenceMapping['lessor_id'] ?? '')) ?> · driver <?= opsui_h((string)($evidenceMapping['driver_id'] ?? '')) ?> · vehicle <?= opsui_h((string)($evidenceMapping['vehicle_id'] ?? '')) ?> · start <?= opsui_h((string)($evidenceMapping['starting_point_id'] ?? '')) ?></p>
            <p><strong>Route:</strong> <?= opsui_h((string)($evidenceRoute['pickup_address'] ?? '')) ?> → <?= opsui_h((string)($evidenceRoute['dropoff_address'] ?? '')) ?></p>
            <p><strong>Price:</strong> <?= opsui_h((string)($evidenceRoute['price_text'] ?? '')) ?> · amount <?= opsui_h((string)($evidenceRoute['price_amount'] ?? '')) ?></p>
            <p><strong>Negative checks:</strong> canary/test <?= !empty($evidenceNegative['is_canary_or_test']) ? 'yes' : 'no' ?> · submitted <?= !empty($evidenceNegative['submitted']) ? 'yes' : 'no' ?> · terminal/blocked <?= !empty($evidenceNegative['terminal_or_failed_or_blocked']) ? 'yes' : 'no' ?> · last error <?= !empty($evidenceNegative['last_error_present']) ? 'yes' : 'no' ?></p>
            <p><strong>Safety:</strong> live risk <?= !empty($evidenceSnapshot['safety_confirmed']['live_risk_detected']) ? 'yes' : 'no' ?> · live submit recommended <?= opsui_h((string)($evidenceSnapshot['safety_confirmed']['live_submit_recommended_now'] ?? 0)) ?> · EDXEIX call <?= !empty($evidenceSnapshot['safety_confirmed']['edxeix_call_made']) ? 'yes' : 'no' ?></p>
            <p class="v3cap-small">CLI evidence command: <code class="v3cap-code">/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_real_future_candidate_capture_readiness.php --evidence-json</code></p>
        </div>
    <?php endif; ?>
</section>


<section class="panel">
    <h2>EDXEIX Payload Preview / Dry-Run Preflight</h2>
    <p>This is a read-only preview of the normalized fields that would feed an EDXEIX pre-ride contract submission. It does not submit, does not open the live gate, does not mutate the queue, and masks passenger contact details in this preview.</p>
    <?php if (empty($edxeixPreview['candidate_found']) || !$edxeixPayload): ?>
        <p>No EDXEIX payload preview is available because no real future candidate is currently visible.</p>
    <?php else: ?>
        <div class="v3cap-next">
            <p><strong>Outcome:</strong> <code class="v3cap-code"><?= opsui_h((string)($edxeixPreview['preflight_outcome'] ?? '')) ?></code></p>
            <p><strong>Dry-run only:</strong> <?= !empty($edxeixPreview['dry_run_only']) ? 'yes' : 'no' ?> · <strong>Live submit allowed now:</strong> <?= !empty($edxeixPreview['live_submit_allowed_now']) ? 'yes' : 'no' ?> · <strong>EDXEIX call:</strong> <?= !empty($edxeixPreview['edxeix_call_made']) ? 'yes' : 'no' ?></p>
            <p><strong>Candidate:</strong> queue <code class="v3cap-code"><?= opsui_h((string)($edxeixCandidate['queue_id'] ?? '')) ?></code> · status <code class="v3cap-code"><?= opsui_h((string)($edxeixCandidate['queue_status'] ?? '')) ?></code> · readiness <code class="v3cap-code"><?= opsui_h((string)($edxeixCandidate['capture_readiness'] ?? '')) ?></code></p>
            <?php $map = is_array($edxeixPayload['edxeix_mapping_ids'] ?? null) ? $edxeixPayload['edxeix_mapping_ids'] : []; ?>
            <?php $times = is_array($edxeixPayload['ride_times'] ?? null) ? $edxeixPayload['ride_times'] : []; ?>
            <?php $route = is_array($edxeixPayload['route'] ?? null) ? $edxeixPayload['route'] : []; ?>
            <?php $price = is_array($edxeixPayload['price'] ?? null) ? $edxeixPayload['price'] : []; ?>
            <?php $passenger = is_array($edxeixPayload['passenger_fields'] ?? null) ? $edxeixPayload['passenger_fields'] : []; ?>
            <?php $context = is_array($edxeixPayload['context'] ?? null) ? $edxeixPayload['context'] : []; ?>
            <p><strong>EDXEIX IDs:</strong> lessor <?= opsui_h((string)($map['lessor_id'] ?? '')) ?> · driver <?= opsui_h((string)($map['driver_id'] ?? '')) ?> · vehicle <?= opsui_h((string)($map['vehicle_id'] ?? '')) ?> · start <?= opsui_h((string)($map['starting_point_id'] ?? '')) ?></p>
            <p><strong>Times:</strong> pickup <?= opsui_h((string)($times['pickup_datetime'] ?? '')) ?> · end <?= opsui_h((string)($times['estimated_end_datetime'] ?? '')) ?> · <?= opsui_h((string)($times['minutes_until_pickup_now'] ?? '')) ?> minutes remaining.</p>
            <p><strong>Passenger:</strong> name <?= opsui_h((string)($passenger['customer_name_preview'] ?? '')) ?> · phone <?= opsui_h((string)($passenger['customer_phone_preview'] ?? '')) ?> · unmasked values hidden <?= !empty($passenger['unmasked_values_hidden_in_preview']) ? 'yes' : 'no' ?></p>
            <p><strong>Route:</strong> <?= opsui_h((string)($route['pickup_address'] ?? '')) ?> → <?= opsui_h((string)($route['dropoff_address'] ?? '')) ?></p>
            <p><strong>Price:</strong> <?= opsui_h((string)($price['amount'] ?? '')) ?> <?= opsui_h((string)($price['currency'] ?? 'EUR')) ?> · source <code class="v3cap-code"><?= opsui_h((string)($price['source_text'] ?? '')) ?></code></p>
            <p><strong>Context:</strong> driver <?= opsui_h((string)($context['driver_name'] ?? '')) ?> · vehicle <code class="v3cap-code"><?= opsui_h((string)($context['vehicle_plate'] ?? '')) ?></code> · lessor source <?= opsui_h((string)($context['lessor_source'] ?? '')) ?></p>
            <p><strong>Preview hash:</strong> <code class="v3cap-code"><?= opsui_h((string)($edxeixPreview['preview_hash_sha256'] ?? '')) ?></code></p>
            <?php if ($edxeixBlocks): ?>
                <p><strong>Preflight blocks:</strong> <?= opsui_h(implode('; ', array_map('strval', $edxeixBlocks))) ?></p>
            <?php else: ?>
                <p><strong>Preflight blocks:</strong> none. Preview passed, but live submission remains blocked by design.</p>
            <?php endif; ?>
            <p class="v3cap-small">CLI dry-run preview command: <code class="v3cap-code">/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_real_future_candidate_capture_readiness.php --edxeix-preview-json</code></p>
        </div>
    <?php endif; ?>
</section>


<section class="panel">
    <h2>Expired Candidate Safety Regression Audit</h2>
    <p>This read-only audit proves that a row which was once <code class="v3cap-code">live_submit_ready</code> cannot remain eligible after pickup time passes. It does not mutate queue state and does not call EDXEIX.</p>
    <div class="v3cap-next">
        <p><strong>Outcome:</strong> <code class="v3cap-code"><?= opsui_h((string)($expiredSafetyAudit['audit_outcome'] ?? '')) ?></code></p>
        <p><strong>Stale live-ready rows:</strong> <?= opsui_h((string)($expiredSafetyAudit['stale_live_submit_ready_rows_found'] ?? 0)) ?> · <strong>Safety blocks:</strong> <?= opsui_h((string)($expiredSafetyAudit['expired_live_ready_safety_blocks'] ?? 0)) ?> · <strong>Regression passed:</strong> <?= !empty($expiredSafetyAudit['eligibility_regression_passed']) ? 'yes' : 'no' ?></p>
        <p><strong>Safety:</strong> live risk <?= !empty($expiredSafetyAudit['safety_confirmed']['live_risk_detected']) ? 'yes' : 'no' ?> · DB write <?= !empty($expiredSafetyAudit['safety_confirmed']['db_write_made']) ? 'yes' : 'no' ?> · queue mutation <?= !empty($expiredSafetyAudit['safety_confirmed']['queue_mutation_made']) ? 'yes' : 'no' ?> · EDXEIX call <?= !empty($expiredSafetyAudit['safety_confirmed']['edxeix_call_made']) ? 'yes' : 'no' ?></p>
        <p class="v3cap-small">CLI expired safety audit command: <code class="v3cap-code">/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_real_future_candidate_capture_readiness.php --expired-safety-json</code></p>
    </div>
    <div class="v3cap-scroll">
    <table class="v3cap-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Status / pickup</th>
                <th>Future?</th>
                <th>Review eligible?</th>
                <th>Would block live submit?</th>
                <th>Reason</th>
            </tr>
        </thead>
        <tbody>
        <?php if (!$expiredStaleRows): ?>
            <tr><td colspan="6">No stale live_submit_ready rows are currently visible.</td></tr>
        <?php endif; ?>
        <?php foreach ($expiredStaleRows as $row): ?>
            <?php if (!is_array($row)) { continue; } ?>
            <tr>
                <td><code class="v3cap-code"><?= opsui_h((string)($row['queue_id'] ?? '')) ?></code></td>
                <td><code class="v3cap-code"><?= opsui_h((string)($row['queue_status'] ?? '')) ?></code><br><small><?= opsui_h((string)($row['pickup_datetime'] ?? '')) ?> / <?= opsui_h((string)($row['minutes_until_pickup_now'] ?? '')) ?> min</small></td>
                <td><?= !empty($row['is_future_now']) ? 'yes' : 'no' ?></td>
                <td><?= !empty($row['qualifies_for_closed_gate_operator_review']) ? 'yes' : 'no' ?></td>
                <td><?= !empty($row['would_block_live_submit_now']) ? 'yes' : 'no' ?></td>
                <td><code class="v3cap-code"><?= opsui_h((string)($row['reason'] ?? '')) ?></code><br><small><?= opsui_h((string)($row['safe_interpretation'] ?? '')) ?></small></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</section>


<section class="panel">
    <h2>Controlled Live-Submit Readiness Checklist</h2>
    <p>This read-only go/no-go snapshot summarizes whether the system is even ready for a future controlled live-submit discussion. It does not enable live submit and does not call EDXEIX.</p>
    <div class="v3cap-next">
        <p><strong>Outcome:</strong> <code class="v3cap-code"><?= opsui_h((string)($liveReadiness['readiness_outcome'] ?? '')) ?></code></p>
        <p><strong>Decision:</strong> <?= opsui_h((string)($liveReadiness['readiness_label'] ?? '')) ?></p>
        <p><strong>Live submit allowed now:</strong> <?= !empty($liveReadiness['live_submit_allowed_now']) ? 'yes' : 'no' ?> · <strong>Blocked by design:</strong> <?= !empty($liveReadiness['live_submit_blocked_by_design']) ? 'yes' : 'no' ?> · <strong>Explicit Andreas request required:</strong> <?= !empty($liveReadiness['requires_explicit_andreas_live_submit_request']) ? 'yes' : 'no' ?></p>
        <p><strong>Safety:</strong> live risk <?= !empty($liveReadiness['safety_confirmed']['live_risk_detected']) ? 'yes' : 'no' ?> · DB write <?= !empty($liveReadiness['safety_confirmed']['db_write_made']) ? 'yes' : 'no' ?> · queue mutation <?= !empty($liveReadiness['safety_confirmed']['queue_mutation_made']) ? 'yes' : 'no' ?> · EDXEIX call <?= !empty($liveReadiness['safety_confirmed']['edxeix_call_made']) ? 'yes' : 'no' ?></p>
        <p class="v3cap-small">CLI live-readiness command: <code class="v3cap-code">/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_real_future_candidate_capture_readiness.php --live-readiness-json</code></p>
    </div>
    <div class="v3cap-scroll">
    <table class="v3cap-table">
        <thead><tr><th>Component</th><th>Result</th></tr></thead>
        <tbody>
        <?php foreach ($liveReadinessComponents as $key => $value): ?>
            <tr><td><code class="v3cap-code"><?= opsui_h((string)$key) ?></code></td><td><?= is_bool($value) ? ($value ? 'yes' : 'no') : opsui_h((string)$value) ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$liveReadinessComponents): ?>
            <tr><td colspan="2">No component results are available.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
    </div>
    <h3>Hard no-go reasons</h3>
    <?php if (!$liveReadinessNoGo): ?>
        <p>No hard no-go reason is currently reported by this checklist. Live submit is still blocked by design.</p>
    <?php else: ?>
        <ul>
        <?php foreach ($liveReadinessNoGo as $reason): ?>
            <li><code class="v3cap-code"><?= opsui_h((string)$reason) ?></code></li>
        <?php endforeach; ?>
        </ul>
    <?php endif; ?>
    <h3>Manual gates before any future live-submit patch</h3>
    <ul>
    <?php foreach ($liveReadinessManualGates as $gate => $required): ?>
        <li><code class="v3cap-code"><?= opsui_h((string)$gate) ?></code>: <?= !empty($required) ? 'required' : 'not required' ?></li>
    <?php endforeach; ?>
    </ul>
</section>

<section class="panel">
    <h2>Single-Row Controlled Live-Submit Design Draft</h2>
    <p>This is a design-only snapshot for the first possible controlled live test. It does not add a submitter, does not enable the live gate, and does not call EDXEIX.</p>
    <div class="v3cap-next">
        <p><strong>Outcome:</strong> <code class="v3cap-code"><?= opsui_h((string)($singleRowDesign['design_outcome'] ?? '')) ?></code></p>
        <p><strong>Decision:</strong> <?= opsui_h((string)($singleRowDesign['design_label'] ?? '')) ?></p>
        <p><strong>Design only:</strong> <?= !empty($singleRowDesign['design_only']) ? 'yes' : 'no' ?> · <strong>Executable submitter added:</strong> <?= !empty($singleRowDesign['executable_submitter_added']) ? 'yes' : 'no' ?> · <strong>Live submit allowed now:</strong> <?= !empty($singleRowDesign['live_submit_allowed_now']) ? 'yes' : 'no' ?></p>
        <p><strong>Safety:</strong> live risk <?= !empty($singleRowDesign['safety_confirmed']['live_risk_detected']) ? 'yes' : 'no' ?> · DB write <?= !empty($singleRowDesign['safety_confirmed']['db_write_made']) ? 'yes' : 'no' ?> · queue mutation <?= !empty($singleRowDesign['safety_confirmed']['queue_mutation_made']) ? 'yes' : 'no' ?> · EDXEIX call <?= !empty($singleRowDesign['safety_confirmed']['edxeix_call_made']) ? 'yes' : 'no' ?></p>
        <p class="v3cap-small">CLI design command: <code class="v3cap-code">/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_real_future_candidate_capture_readiness.php --single-row-live-design-json</code></p>
    </div>
    <div class="v3cap-scroll">
    <table class="v3cap-table">
        <thead><tr><th>Single-row policy</th><th>Required</th></tr></thead>
        <tbody>
        <?php foreach ($singleRowPolicy as $key => $value): ?>
            <tr><td><code class="v3cap-code"><?= opsui_h((string)$key) ?></code></td><td><?= is_bool($value) ? ($value ? 'yes' : 'no') : opsui_h((string)$value) ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$singleRowPolicy): ?><tr><td colspan="2">No single-row policy is available.</td></tr><?php endif; ?>
        </tbody>
    </table>
    </div>
    <h3>Component posture</h3>
    <div class="v3cap-scroll">
    <table class="v3cap-table">
        <thead><tr><th>Component</th><th>Result</th></tr></thead>
        <tbody>
        <?php foreach ($singleRowComponents as $key => $value): ?>
            <tr><td><code class="v3cap-code"><?= opsui_h((string)$key) ?></code></td><td><?= is_bool($value) ? ($value ? 'yes' : 'no') : opsui_h((string)$value) ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$singleRowComponents): ?><tr><td colspan="2">No component results are available.</td></tr><?php endif; ?>
        </tbody>
    </table>
    </div>
    <h3>Pre-execution gate sequence for any future patch</h3>
    <ol>
    <?php foreach ($singleRowSequence as $step): ?>
        <li><code class="v3cap-code"><?= opsui_h((string)$step) ?></code></li>
    <?php endforeach; ?>
    </ol>
</section>

<section class="panel">
    <h2>Controlled Live-Submit Runbook / Authorization Packet</h2>
    <p>This is a non-executable authorization packet for a future first live test. It does not enable the live gate, does not submit to EDXEIX, and does not mutate queue state.</p>
    <div class="v3cap-next">
        <p><strong>Packet status:</strong> <code class="v3cap-code"><?= opsui_h((string)($authorizationPacket['packet_status'] ?? '')) ?></code></p>
        <p><strong>Decision:</strong> <?= opsui_h((string)($authorizationPacket['packet_label'] ?? '')) ?></p>
        <p><strong>Packet only:</strong> <?= !empty($authorizationPacket['authorization_packet_only']) ? 'yes' : 'no' ?> · <strong>Executable submitter added:</strong> <?= !empty($authorizationPacket['executable_submitter_added']) ? 'yes' : 'no' ?> · <strong>Live submit allowed now:</strong> <?= !empty($authorizationPacket['live_submit_allowed_now']) ? 'yes' : 'no' ?></p>
        <p><strong>Safety:</strong> live risk <?= !empty($authorizationPacket['safety_confirmed']['live_risk_detected']) ? 'yes' : 'no' ?> · DB write <?= !empty($authorizationPacket['safety_confirmed']['db_write_made']) ? 'yes' : 'no' ?> · queue mutation <?= !empty($authorizationPacket['safety_confirmed']['queue_mutation_made']) ? 'yes' : 'no' ?> · EDXEIX call <?= !empty($authorizationPacket['safety_confirmed']['edxeix_call_made']) ? 'yes' : 'no' ?></p>
        <p class="v3cap-small">CLI authorization packet command: <code class="v3cap-code">/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_real_future_candidate_capture_readiness.php --authorization-packet-json</code></p>
    </div>
    <div class="v3cap-scroll">
    <table class="v3cap-table">
        <thead><tr><th>Authorization gate</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($authorizationGates as $key => $value): ?>
            <tr><td><code class="v3cap-code"><?= opsui_h((string)$key) ?></code></td><td><?= is_bool($value) ? ($value ? 'yes' : 'no') : opsui_h((string)$value) ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$authorizationGates): ?><tr><td colspan="2">No authorization gates are available.</td></tr><?php endif; ?>
        </tbody>
    </table>
    </div>
    <h3>Future explicit-patch runbook</h3>
    <ol>
    <?php foreach ($authorizationRunbook as $step): ?>
        <li><code class="v3cap-code"><?= opsui_h((string)$step) ?></code></li>
    <?php endforeach; ?>
    </ol>
    <h3>Component posture</h3>
    <div class="v3cap-scroll">
    <table class="v3cap-table">
        <thead><tr><th>Component</th><th>Result</th></tr></thead>
        <tbody>
        <?php foreach ($authorizationComponents as $key => $value): ?>
            <tr><td><code class="v3cap-code"><?= opsui_h((string)$key) ?></code></td><td><?= is_bool($value) ? ($value ? 'yes' : 'no') : opsui_h((string)$value) ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$authorizationComponents): ?><tr><td colspan="2">No component results are available.</td></tr><?php endif; ?>
        </tbody>
    </table>
    </div>
</section>

<section class="panel">
    <h2>Real-Format Demo Mail Fixture Preview</h2>
    <p>This preview shows a redacted, future-timestamped, real-format pre-ride email fixture. It does not write to Maildir, does not create a queue row, and does not submit anything.</p>
    <div class="v3cap-next">
        <p><strong>Fixture preview only:</strong> <?= !empty($demoMailFixture['fixture_preview_only']) ? 'yes' : 'no' ?> · <strong>Maildir write:</strong> <?= !empty($demoMailFixture['maildir_write_made']) ? 'yes' : 'no' ?> · <strong>Executable writer added:</strong> <?= !empty($demoMailFixture['executable_mail_writer_added']) ? 'yes' : 'no' ?></p>
        <p><strong>Subject:</strong> <code class="v3cap-code"><?= opsui_h((string)($demoFixtureHeaders['subject'] ?? '')) ?></code> · <strong>Pickup time:</strong> <code class="v3cap-code"><?= opsui_h((string)($demoFixtureTimes['estimated_pickup_time'] ?? '')) ?></code></p>
        <p><strong>Safety:</strong> DB write <?= !empty($demoMailFixture['db_write_made']) ? 'yes' : 'no' ?> · queue mutation <?= !empty($demoMailFixture['queue_mutation_made']) ? 'yes' : 'no' ?> · EDXEIX call <?= !empty($demoMailFixture['edxeix_call_made']) ? 'yes' : 'no' ?> · AADE call <?= !empty($demoMailFixture['aade_call_made']) ? 'yes' : 'no' ?></p>
        <p class="v3cap-small">CLI fixture preview command: <code class="v3cap-code">/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_real_future_candidate_capture_readiness.php --demo-mail-fixture-json</code></p>
    </div>
    <div class="v3cap-grid">
        <div class="v3cap-card"><h3><?= opsui_h((string)($demoFixtureIdentity['driver'] ?? '')) ?></h3><p class="v3cap-muted">driver</p></div>
        <div class="v3cap-card"><h3><?= opsui_h((string)($demoFixtureIdentity['vehicle'] ?? '')) ?></h3><p class="v3cap-muted">vehicle</p></div>
        <div class="v3cap-card"><h3><?= opsui_h((string)($demoFixtureIdentity['customer_mobile_preview'] ?? '')) ?></h3><p class="v3cap-muted">masked customer mobile</p></div>
    </div>
    <div class="v3cap-scroll">
    <table class="v3cap-table">
        <thead><tr><th>Fixture field</th><th>Preview value</th></tr></thead>
        <tbody>
            <tr><td>Operator</td><td><?= opsui_h((string)($demoFixtureIdentity['operator'] ?? '')) ?></td></tr>
            <tr><td>Customer preview</td><td><?= opsui_h((string)($demoFixtureIdentity['customer_name_preview'] ?? '')) ?></td></tr>
            <tr><td>Pickup</td><td><?= opsui_h((string)($demoFixtureRoute['pickup'] ?? '')) ?></td></tr>
            <tr><td>Drop-off</td><td><?= opsui_h((string)($demoFixtureRoute['dropoff'] ?? '')) ?></td></tr>
            <tr><td>Estimated price</td><td><?= opsui_h((string)($demoFixtureRoute['estimated_price'] ?? '')) ?></td></tr>
            <tr><td>Danger tokens in body</td><td><?= !empty($demoMailFixture['body_contains_demo_test_canary_tokens']) ? 'yes' : 'no' ?></td></tr>
            <tr><td>Redacted body hash</td><td><code class="v3cap-code"><?= opsui_h((string)($demoMailFixture['redacted_body_sha256'] ?? '')) ?></code></td></tr>
        </tbody>
    </table>
    </div>
    <h3>Redacted body preview</h3>
    <pre><?= opsui_h((string)($demoMailFixture['redacted_body_preview'] ?? '')) ?></pre>
</section>

<section class="panel">
    <h2>Controlled Maildir Fixture Writer Design Draft</h2>
    <p>This is a design-only snapshot for a future explicit Maildir fixture writer. It does not add a writer, does not create a mail file, and does not trigger intake.</p>
    <div class="v3cap-next">
        <p><strong>Design outcome:</strong> <code class="v3cap-code"><?= opsui_h((string)($maildirWriterDesign['design_outcome'] ?? '')) ?></code></p>
        <p><strong>Design only:</strong> <?= !empty($maildirWriterDesign['design_only']) ? 'yes' : 'no' ?> · <strong>Executable writer added:</strong> <?= !empty($maildirWriterDesign['executable_mail_writer_added']) ? 'yes' : 'no' ?> · <strong>Maildir write allowed now:</strong> <?= !empty($maildirWriterDesign['maildir_write_allowed_now']) ? 'yes' : 'no' ?></p>
        <p><strong>Safety:</strong> Maildir write <?= !empty($maildirWriterDesign['maildir_write_made']) ? 'yes' : 'no' ?> · DB write <?= !empty($maildirWriterDesign['db_write_made']) ? 'yes' : 'no' ?> · queue mutation <?= !empty($maildirWriterDesign['queue_mutation_made']) ? 'yes' : 'no' ?> · EDXEIX call <?= !empty($maildirWriterDesign['edxeix_call_made']) ? 'yes' : 'no' ?></p>
        <p class="v3cap-small">CLI writer design command: <code class="v3cap-code">/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_real_future_candidate_capture_readiness.php --maildir-writer-design-json</code></p>
    </div>
    <div class="v3cap-scroll">
    <table class="v3cap-table">
        <thead><tr><th>Single-write policy</th><th>Required</th></tr></thead>
        <tbody>
        <?php foreach ($maildirWriterPolicy as $key => $value): ?>
            <tr><td><code class="v3cap-code"><?= opsui_h((string)$key) ?></code></td><td><?= is_bool($value) ? ($value ? 'yes' : 'no') : opsui_h((string)$value) ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$maildirWriterPolicy): ?><tr><td colspan="2">No Maildir writer policy is available.</td></tr><?php endif; ?>
        </tbody>
    </table>
    </div>
    <h3>Future explicit writer gate sequence</h3>
    <ol>
    <?php foreach ($maildirWriterSequence as $step): ?>
        <li><code class="v3cap-code"><?= opsui_h((string)$step) ?></code></li>
    <?php endforeach; ?>
    </ol>
    <h3>Writer design component posture</h3>
    <div class="v3cap-scroll">
    <table class="v3cap-table">
        <thead><tr><th>Component</th><th>Result</th></tr></thead>
        <tbody>
        <?php foreach ($maildirWriterComponents as $key => $value): ?>
            <tr><td><code class="v3cap-code"><?= opsui_h((string)$key) ?></code></td><td><?= is_bool($value) ? ($value ? 'yes' : 'no') : opsui_h((string)$value) ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$maildirWriterComponents): ?><tr><td colspan="2">No Maildir writer component results are available.</td></tr><?php endif; ?>
        </tbody>
    </table>
    </div>
</section>


<section class="panel">
    <h2>Maildir Fixture Writer Preflight Audit</h2>
    <p>This audit checks the Maildir path posture for a future explicit one-shot writer without creating a file, probe, queue row, or DB write.</p>
    <div class="v3cap-next">
        <p><strong>Preflight outcome:</strong> <code class="v3cap-code"><?= opsui_h((string)($maildirWriterPreflight['preflight_outcome'] ?? '')) ?></code></p>
        <p><strong>Preflight only:</strong> <?= !empty($maildirWriterPreflight['preflight_only']) ? 'yes' : 'no' ?> · <strong>Executable writer added:</strong> <?= !empty($maildirWriterPreflight['executable_mail_writer_added']) ? 'yes' : 'no' ?> · <strong>Maildir write allowed now:</strong> <?= !empty($maildirWriterPreflight['maildir_write_allowed_now']) ? 'yes' : 'no' ?></p>
        <p><strong>Safety:</strong> Maildir write <?= !empty($maildirWriterPreflight['maildir_write_made']) ? 'yes' : 'no' ?> · write probe <?= !empty($maildirWriterPreflight['write_probe_performed']) ? 'yes' : 'no' ?> · DB write <?= !empty($maildirWriterPreflight['db_write_made']) ? 'yes' : 'no' ?> · EDXEIX call <?= !empty($maildirWriterPreflight['edxeix_call_made']) ? 'yes' : 'no' ?></p>
        <p class="v3cap-small">CLI preflight command: <code class="v3cap-code">/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_real_future_candidate_capture_readiness.php --maildir-writer-preflight-json</code></p>
    </div>
    <?php if ($maildirPreflightBlocks): ?>
        <div class="v3cap-block"><strong>Preflight blocks:</strong> <?= opsui_h(implode('; ', array_map('strval', $maildirPreflightBlocks))) ?></div>
    <?php endif; ?>
    <div class="v3cap-scroll">
    <table class="v3cap-table">
        <thead><tr><th>Maildir path</th><th>Exists</th><th>Directory</th><th>Readable</th><th>Writable by current process</th><th>Perms</th><th>Entries</th></tr></thead>
        <tbody>
        <?php foreach ($maildirPreflightPaths as $label => $meta): ?>
            <?php if (!is_array($meta)) { continue; } ?>
            <tr>
                <td><code class="v3cap-code"><?= opsui_h((string)$label) ?></code><br><small><?= opsui_h((string)($meta['path'] ?? '')) ?></small></td>
                <td><?= !empty($meta['exists']) ? 'yes' : 'no' ?></td>
                <td><?= !empty($meta['is_dir']) ? 'yes' : 'no' ?></td>
                <td><?= !empty($meta['is_readable']) ? 'yes' : 'no' ?></td>
                <td><?= !empty($meta['is_writable_by_current_process']) ? 'yes' : 'no' ?></td>
                <td><code class="v3cap-code"><?= opsui_h((string)($meta['permissions_octal'] ?? '')) ?></code></td>
                <td><?= opsui_h((string)($meta['entry_count_if_readable'] ?? '')) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$maildirPreflightPaths): ?><tr><td colspan="7">No Maildir path metadata is available.</td></tr><?php endif; ?>
        </tbody>
    </table>
    </div>
    <h3>Fixture preflight</h3>
    <div class="v3cap-scroll">
    <table class="v3cap-table">
        <thead><tr><th>Check</th><th>Result</th></tr></thead>
        <tbody>
        <?php foreach ($maildirPreflightFixture as $key => $value): ?>
            <tr><td><code class="v3cap-code"><?= opsui_h((string)$key) ?></code></td><td><?= is_bool($value) ? ($value ? 'yes' : 'no') : (is_array($value) ? opsui_h(implode(', ', array_map('strval', $value))) : opsui_h((string)$value)) ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$maildirPreflightFixture): ?><tr><td colspan="2">No fixture preflight data is available.</td></tr><?php endif; ?>
        </tbody>
    </table>
    </div>
</section>

<?php foreach ($warnings as $warning): ?>
    <div class="v3cap-warning"><?= opsui_h((string)$warning) ?></div>
<?php endforeach; ?>

<?php foreach ($finalBlocks as $block): ?>
    <div class="v3cap-block"><?= opsui_h((string)$block) ?></div>
<?php endforeach; ?>

<section class="panel">
    <h2>Next future possible-real row</h2>
    <?php if (!$nextCandidate): ?>
        <p>No real future possible-real row is visible right now. Continue observing until a real pre-ride email arrives before pickup expiry.</p>
    <?php else: ?>
        <div class="v3cap-next">
            <p><strong>Queue ID:</strong> <code class="v3cap-code"><?= opsui_h((string)($nextCandidate['id'] ?? '')) ?></code></p>
            <p><strong>Pickup:</strong> <?= opsui_h((string)($nextCandidate['pickup_datetime'] ?? '')) ?> — <strong><?= opsui_h((string)($nextCandidate['minutes_until_pickup_now'] ?? '')) ?> minutes</strong> until pickup now.</p>
            <p><strong>Completeness:</strong> <?= v3rfccr_bool_label(!empty($nextCandidate['complete'])) ?> · <strong>Missing fields:</strong> <?= opsui_h(v3rfccr_missing_label($nextCandidate)) ?></p>
            <p><strong>Closed-gate operator review:</strong> <?= v3rfccr_bool_label(!empty($nextCandidate['qualifies_for_closed_gate_operator_review'])) ?> · <strong>Operator alert:</strong> <?= v3rfccr_bool_label(!empty($nextCandidate['operator_alert_appropriate'])) ?> / <?= opsui_h((string)($nextCandidate['operator_alert_priority'] ?? 'none')) ?></p>
            <p><strong>Readiness:</strong> <code class="v3cap-code"><?= opsui_h((string)($nextCandidate['capture_readiness'] ?? '')) ?></code> · <strong>Reason:</strong> <code class="v3cap-code"><?= opsui_h((string)($nextCandidate['reason'] ?? '')) ?></code></p>
            <p class="v3cap-small"><?= opsui_h((string)($nextCandidate['safe_interpretation'] ?? 'Review only.')) ?></p>
        </div>
    <?php endif; ?>
</section>

<section class="panel">
    <h2>Closed-gate operator review candidates</h2>
    <div class="v3cap-scroll">
    <table class="v3cap-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Pickup / minutes</th>
                <th>Customer</th>
                <th>Driver / vehicle</th>
                <th>Mapping IDs</th>
                <th>Missing</th>
                <th>Alert</th>
            </tr>
        </thead>
        <tbody>
        <?php if (!$reviewRows): ?>
            <tr><td colspan="7">No complete future row currently qualifies for closed-gate operator review.</td></tr>
        <?php endif; ?>
        <?php foreach ($reviewRows as $row): ?>
            <?php if (!is_array($row)) { continue; } ?>
            <tr>
                <td><code class="v3cap-code"><?= opsui_h((string)($row['id'] ?? '')) ?></code></td>
                <td><?= opsui_h((string)($row['pickup_datetime'] ?? '')) ?><br><small><?= opsui_h((string)($row['minutes_until_pickup_now'] ?? '')) ?> min</small></td>
                <td><?= opsui_h((string)($row['customer_name'] ?? '')) ?><br><small><?= opsui_h((string)($row['customer_phone'] ?? '')) ?></small></td>
                <td><?= opsui_h((string)($row['driver_name'] ?? '')) ?><br><code class="v3cap-code"><?= opsui_h((string)($row['vehicle_plate'] ?? '')) ?></code></td>
                <td><small>lessor <?= opsui_h((string)($row['lessor_id'] ?? '')) ?><br>driver <?= opsui_h((string)($row['driver_id'] ?? '')) ?><br>vehicle <?= opsui_h((string)($row['vehicle_id'] ?? '')) ?><br>start <?= opsui_h((string)($row['starting_point_id'] ?? '')) ?></small></td>
                <td><small><?= opsui_h(v3rfccr_missing_label($row)) ?></small></td>
                <td><?= v3rfccr_bool_label(!empty($row['operator_alert_appropriate'])) ?><br><code class="v3cap-code"><?= opsui_h((string)($row['operator_alert_priority'] ?? 'none')) ?></code></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</section>

<section class="panel">
    <h2>Operator alert rows</h2>
    <div class="v3cap-scroll">
    <table class="v3cap-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Readiness</th>
                <th>Pickup</th>
                <th>Complete</th>
                <th>Missing fields</th>
                <th>Reason</th>
                <th>Priority</th>
            </tr>
        </thead>
        <tbody>
        <?php if (!$alertRows): ?>
            <tr><td colspan="7">No operator alert is appropriate right now.</td></tr>
        <?php endif; ?>
        <?php foreach ($alertRows as $row): ?>
            <?php if (!is_array($row)) { continue; } ?>
            <tr>
                <td><code class="v3cap-code"><?= opsui_h((string)($row['id'] ?? '')) ?></code></td>
                <td><code class="v3cap-code"><?= opsui_h((string)($row['capture_readiness'] ?? '')) ?></code></td>
                <td><?= opsui_h((string)($row['pickup_datetime'] ?? '')) ?><br><small><?= opsui_h((string)($row['minutes_until_pickup_now'] ?? '')) ?> min</small></td>
                <td><?= v3rfccr_bool_label(!empty($row['complete'])) ?></td>
                <td><small><?= opsui_h(v3rfccr_missing_label($row)) ?></small></td>
                <td><code class="v3cap-code"><?= opsui_h((string)($row['reason'] ?? '')) ?></code></td>
                <td><code class="v3cap-code"><?= opsui_h((string)($row['operator_alert_priority'] ?? 'none')) ?></code></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</section>

<section class="panel">
    <h2>Future possible-real rows</h2>
    <div class="v3cap-scroll">
    <table class="v3cap-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Status</th>
                <th>Pickup / minutes</th>
                <th>Customer</th>
                <th>Complete</th>
                <th>Missing fields</th>
                <th>Closed-gate review</th>
                <th>Reason</th>
            </tr>
        </thead>
        <tbody>
        <?php if (!$futureRows): ?>
            <tr><td colspan="8">No future possible-real rows found.</td></tr>
        <?php endif; ?>
        <?php foreach ($futureRows as $row): ?>
            <?php if (!is_array($row)) { continue; } ?>
            <tr>
                <td><code class="v3cap-code"><?= opsui_h((string)($row['id'] ?? '')) ?></code></td>
                <td><?= opsui_h((string)($row['queue_status'] ?? '')) ?></td>
                <td><?= opsui_h((string)($row['pickup_datetime'] ?? '')) ?><br><small><?= opsui_h((string)($row['minutes_until_pickup_now'] ?? '')) ?> min</small></td>
                <td><?= opsui_h((string)($row['customer_name'] ?? '')) ?></td>
                <td><?= v3rfccr_bool_label(!empty($row['complete'])) ?></td>
                <td><small><?= opsui_h(v3rfccr_missing_label($row)) ?></small></td>
                <td><?= v3rfccr_bool_label(!empty($row['qualifies_for_closed_gate_operator_review'])) ?></td>
                <td><code class="v3cap-code"><?= opsui_h((string)($row['reason'] ?? '')) ?></code></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</section>

<section class="panel">
    <h2>Latest rows scanned</h2>
    <div class="v3cap-scroll">
    <table class="v3cap-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Possible real</th>
                <th>Pickup</th>
                <th>Readiness</th>
                <th>Missing</th>
                <th>Reason</th>
                <th>Last error</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach (array_slice($latestRows, 0, 15) as $row): ?>
            <?php if (!is_array($row)) { continue; } ?>
            <tr>
                <td><code class="v3cap-code"><?= opsui_h((string)($row['id'] ?? '')) ?></code></td>
                <td><?= v3rfccr_bool_label(!empty($row['possible_real_mail'])) ?></td>
                <td><?= opsui_h((string)($row['pickup_datetime'] ?? '')) ?><br><small><?= opsui_h((string)($row['minutes_until_pickup_now'] ?? '')) ?> min</small></td>
                <td><code class="v3cap-code"><?= opsui_h((string)($row['capture_readiness'] ?? '')) ?></code></td>
                <td><small><?= opsui_h(v3rfccr_missing_label($row)) ?></small></td>
                <td><code class="v3cap-code"><?= opsui_h((string)($row['reason'] ?? '')) ?></code></td>
                <td><small><?= opsui_h((string)($row['last_error'] ?? '')) ?></small></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</section>

<section class="panel">
    <h2>Safe conclusion</h2>
    <ol>
        <li>This tool is only a capture-readiness layer; it does not submit, approve, mutate, or write.</li>
        <li>If a row qualifies for closed-gate operator review, inspect it before pickup expiry while the live gate remains closed.</li>
        <li>If missing fields appear, correct the source/mapping path first; do not submit incomplete data.</li>
        <li>Live EDXEIX submission remains blocked until Andreas explicitly requests a live-submit update and all live gates pass.</li>
    </ol>
    <p><strong>Recommended next step:</strong> <?= opsui_h((string)($report['recommended_next_step'] ?? 'Continue observing.')) ?></p>
</section>


<section class="panel">
    <h2>Maildir Fixture Writer Authorization Packet</h2>
    <p>This packet consolidates the real-format fixture preview, Maildir writer design, path preflight, and explicit-request gates for a future one-shot writer patch. It does not add a writer, does not create mail, and does not trigger intake.</p>
    <div class="v3cap-next">
        <p><strong>Packet status:</strong> <code class="v3cap-code"><?= opsui_h((string)($maildirWriterAuthorization['packet_status'] ?? '')) ?></code></p>
        <p><strong>Decision:</strong> <?= opsui_h((string)($maildirWriterAuthorization['packet_label'] ?? '')) ?></p>
        <p><strong>Packet only:</strong> <?= !empty($maildirWriterAuthorization['authorization_packet_only']) ? 'yes' : 'no' ?> · <strong>Executable writer added:</strong> <?= !empty($maildirWriterAuthorization['executable_mail_writer_added']) ? 'yes' : 'no' ?> · <strong>Maildir write allowed now:</strong> <?= !empty($maildirWriterAuthorization['maildir_write_allowed_now']) ? 'yes' : 'no' ?></p>
        <p><strong>Safety:</strong> Maildir write <?= !empty($maildirWriterAuthorization['maildir_write_made']) ? 'yes' : 'no' ?> · write probe <?= !empty($maildirWriterAuthorization['write_probe_performed']) ? 'yes' : 'no' ?> · DB write <?= !empty($maildirWriterAuthorization['db_write_made']) ? 'yes' : 'no' ?> · EDXEIX call <?= !empty($maildirWriterAuthorization['edxeix_call_made']) ? 'yes' : 'no' ?></p>
        <p class="v3cap-small">CLI authorization command: <code class="v3cap-code">/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_real_future_candidate_capture_readiness.php --maildir-writer-authorization-json</code></p>
    </div>
    <div class="v3cap-scroll">
    <table class="v3cap-table">
        <thead><tr><th>Authorization gate</th><th>Result</th></tr></thead>
        <tbody>
        <?php foreach ($maildirAuthorizationGates as $key => $value): ?>
            <tr><td><code class="v3cap-code"><?= opsui_h((string)$key) ?></code></td><td><?= is_bool($value) ? ($value ? 'yes' : 'no') : opsui_h((string)$value) ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$maildirAuthorizationGates): ?><tr><td colspan="2">No Maildir authorization gates are available.</td></tr><?php endif; ?>
        </tbody>
    </table>
    </div>
    <h3>Future explicit writer patch runbook</h3>
    <ol>
    <?php foreach ($maildirAuthorizationRunbook as $step): ?>
        <li><code class="v3cap-code"><?= opsui_h((string)$step) ?></code></li>
    <?php endforeach; ?>
    </ol>
    <h3>Authorization component posture</h3>
    <div class="v3cap-scroll">
    <table class="v3cap-table">
        <thead><tr><th>Component</th><th>Result</th></tr></thead>
        <tbody>
        <?php foreach ($maildirAuthorizationComponents as $key => $value): ?>
            <tr><td><code class="v3cap-code"><?= opsui_h((string)$key) ?></code></td><td><?= is_bool($value) ? ($value ? 'yes' : 'no') : opsui_h((string)$value) ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$maildirAuthorizationComponents): ?><tr><td colspan="2">No Maildir authorization component results are available.</td></tr><?php endif; ?>
        </tbody>
    </table>
    </div>
</section>

<?php opsui_shell_end(); ?>
