<?php
/**
 * gov.cabnet.app — v5.0 Guarded Live Submit Readiness
 *
 * Read-only view for the live-armed / session-disconnected phase.
 * Does not import mail, send email, create bookings/evidence/jobs/attempts,
 * call Bolt, call EDXEIX, write files, or submit anything live.
 */

declare(strict_types=1);

require_once '/home/cabnet/gov.cabnet.app_app/lib/edxeix_live_submit_gate.php';

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Robots-Tag: noindex,nofollow', true);

function lsr_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function lsr_bool($value): bool
{
    if (is_bool($value)) {
        return $value;
    }
    return in_array(strtolower(trim((string)$value)), ['1', 'true', 'yes', 'on'], true);
}

function lsr_authorized(array $config): bool
{
    $expected = (string)($config['app']['internal_api_key'] ?? '');
    if ($expected === '' || str_starts_with($expected, 'REPLACE_WITH_')) {
        return false;
    }
    $provided = (string)($_GET['key'] ?? $_POST['key'] ?? ($_SERVER['HTTP_X_INTERNAL_API_KEY'] ?? ''));
    return $provided !== '' && hash_equals($expected, $provided);
}

function lsr_json(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit;
}

function lsr_badge(string $text, string $type = 'neutral'): string
{
    return '<span class="badge badge-' . lsr_h($type) . '">' . lsr_h($text) . '</span>';
}

function lsr_metric($value, string $label): string
{
    return '<div class="metric"><strong>' . lsr_h($value) . '</strong><span>' . lsr_h($label) . '</span></div>';
}

try {
    $config = gov_bridge_load_config();
    if (!empty($config['app']['timezone'])) {
        date_default_timezone_set((string)$config['app']['timezone']);
    }
    if (!lsr_authorized($config)) {
        lsr_json(['ok' => false, 'error' => 'Unauthorized'], 403);
    }

    $db = gov_bridge_db();
    $liveConfig = gov_live_load_config();
    $session = gov_live_session_state($liveConfig);
    $candidates = gov_live_analyzed_candidates($db, max(1, min(100, (int)($_GET['limit'] ?? 30))));

    $counts = [
        'submission_jobs' => gov_bridge_table_exists($db, 'submission_jobs') ? (int)(gov_bridge_fetch_one($db, 'SELECT COUNT(*) AS c FROM submission_jobs')['c'] ?? 0) : 0,
        'submission_attempts' => gov_bridge_table_exists($db, 'submission_attempts') ? (int)(gov_bridge_fetch_one($db, 'SELECT COUNT(*) AS c FROM submission_attempts')['c'] ?? 0) : 0,
        'live_audit_rows' => gov_bridge_table_exists($db, 'edxeix_live_submission_audit') ? (int)(gov_bridge_fetch_one($db, 'SELECT COUNT(*) AS c FROM edxeix_live_submission_audit')['c'] ?? 0) : 0,
        'live_audit_success' => gov_bridge_table_exists($db, 'edxeix_live_submission_audit') ? (int)(gov_bridge_fetch_one($db, 'SELECT COUNT(*) AS c FROM edxeix_live_submission_audit WHERE success = 1')['c'] ?? 0) : 0,
    ];

    $readyCandidates = 0;
    foreach ($candidates as $candidate) {
        if (!empty($candidate['live_submission_allowed'])) {
            $readyCandidates++;
        }
    }

    $sessionDisconnected = empty($liveConfig['edxeix_session_connected']);
    $armed = !empty($liveConfig['live_submit_enabled']) && !empty($liveConfig['http_submit_enabled']);
    $oneShotSet = !empty($liveConfig['allowed_booking_id']) || !empty($liveConfig['allowed_order_reference']);

    $verdict = 'LIVE_SUBMIT_DISABLED';
    if ($armed && $sessionDisconnected) {
        $verdict = 'LIVE_ARMED_SESSION_DISCONNECTED';
    } elseif ($armed && !$sessionDisconnected && !$oneShotSet) {
        $verdict = 'LIVE_ARMED_BLOCKED_ONE_SHOT_LOCK_MISSING';
    } elseif ($armed && !$sessionDisconnected && $oneShotSet && $readyCandidates > 0) {
        $verdict = 'LIVE_ARMED_HAS_ALLOWED_CANDIDATE';
    } elseif ($armed) {
        $verdict = 'LIVE_ARMED_NO_ALLOWED_CANDIDATE';
    }

    $payload = [
        'ok' => true,
        'script' => 'ops/live-submit-readiness.php',
        'generated_at' => date(DATE_ATOM),
        'verdict' => $verdict,
        'safety_contract' => [
            'read_only' => true,
            'displays_secrets' => false,
            'writes_files' => false,
            'imports_mail' => false,
            'sends_driver_email' => false,
            'creates_normalized_bookings' => false,
            'creates_dry_run_evidence' => false,
            'creates_submission_jobs' => false,
            'creates_submission_attempts' => false,
            'calls_bolt' => false,
            'calls_edxeix' => false,
            'live_edxeix_submission' => false,
        ],
        'config' => [
            'app_dry_run' => !empty($config['app']['dry_run']),
            'edxeix_config_live_submit_enabled' => !empty($config['edxeix']['live_submit_enabled']),
            'future_start_guard_minutes' => (int)($config['edxeix']['future_start_guard_minutes'] ?? 0),
            'live_submit_enabled' => !empty($liveConfig['live_submit_enabled']),
            'http_submit_enabled' => !empty($liveConfig['http_submit_enabled']),
            'edxeix_session_connected' => !empty($liveConfig['edxeix_session_connected']),
            'require_one_shot_lock' => !empty($liveConfig['require_one_shot_lock']),
            'allowed_booking_id' => $liveConfig['allowed_booking_id'] ?? null,
            'allowed_order_reference' => $liveConfig['allowed_order_reference'] ?? null,
            'submit_url_configured' => trim((string)($liveConfig['edxeix_submit_url'] ?? '')) !== '',
        ],
        'session_state' => $session,
        'counts' => $counts,
        'summary' => [
            'candidates_checked' => count($candidates),
            'live_submission_allowed' => $readyCandidates,
        ],
        'candidates' => $candidates,
        'commands' => [
            'arm_session_disconnected' => '/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/arm_live_submit_session_disconnected.php --by=Andreas',
            'set_one_shot_lock' => '/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/set_live_submit_one_shot_lock.php --booking-id=BOOKING_ID --by=Andreas',
            'analyze_one_booking' => "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/live_submit_one_booking.php --booking-id=BOOKING_ID --analyze-only",
            'manual_submit_one_booking' => "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/live_submit_one_booking.php --booking-id=BOOKING_ID --confirm='I UNDERSTAND SUBMIT LIVE TO EDXEIX'",
        ],
    ];

    if (($_GET['format'] ?? '') === 'json') {
        lsr_json($payload);
    }
} catch (Throwable $e) {
    lsr_json(['ok' => false, 'error' => $e->getMessage()], 500);
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Live Submit Readiness | gov.cabnet.app</title>
<style>
body{font-family:Arial,Helvetica,sans-serif;margin:0;background:#f6f8fb;color:#172033}header{background:#101828;color:#fff;padding:18px 24px}main{max-width:1400px;margin:0 auto;padding:24px}.card{background:#fff;border:1px solid #dce4f0;border-radius:14px;padding:18px;margin-bottom:16px;box-shadow:0 8px 22px rgba(16,24,40,.06)}.metrics{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px}.metric{background:#f8fafc;border:1px solid #dce4f0;border-radius:12px;padding:14px}.metric strong{display:block;font-size:24px}.metric span{font-size:13px;color:#60708a}.badge{display:inline-block;border-radius:999px;padding:5px 10px;font-size:12px;font-weight:700;background:#eef2f6;color:#344054}.badge-good{background:#ecfdf3;color:#067647}.badge-bad{background:#fef3f2;color:#b42318}.badge-warn{background:#fffaeb;color:#b54708}.table-wrap{overflow:auto;border:1px solid #dce4f0;border-radius:12px}table{width:100%;border-collapse:collapse;background:#fff;min-width:1100px}th,td{text-align:left;border-bottom:1px solid #dce4f0;padding:9px 10px;font-size:13px;vertical-align:top}th{background:#f8fafc;color:#475467;text-transform:uppercase;font-size:11px}pre{white-space:pre-wrap;background:#0b1020;color:#d7e3ff;padding:14px;border-radius:12px;overflow:auto}.danger{border-left:6px solid #b42318}.warn{border-left:6px solid #b54708}.ok{border-left:6px solid #067647}.muted{color:#60708a}
</style>
</head>
<body>
<header><strong>gov.cabnet.app — Guarded Live Submit Readiness</strong></header>
<main>
<section class="card <?= $payload['verdict'] === 'LIVE_ARMED_SESSION_DISCONNECTED' ? 'warn' : ($readyCandidates > 0 ? 'danger' : 'ok') ?>">
<h1><?= lsr_h($payload['verdict']) ?></h1>
<p class="muted">Read-only panel. It does not call Bolt or EDXEIX and does not submit anything.</p>
<div class="metrics">
<?= lsr_metric($payload['config']['live_submit_enabled'] ? 'true' : 'false', 'live_submit_enabled') ?>
<?= lsr_metric($payload['config']['http_submit_enabled'] ? 'true' : 'false', 'http_submit_enabled') ?>
<?= lsr_metric($payload['config']['edxeix_session_connected'] ? 'true' : 'false', 'edxeix_session_connected') ?>
<?= lsr_metric($payload['config']['allowed_booking_id'] ?: 'NULL', 'allowed_booking_id') ?>
<?= lsr_metric($payload['counts']['submission_jobs'], 'submission_jobs') ?>
<?= lsr_metric($payload['counts']['submission_attempts'], 'submission_attempts') ?>
<?= lsr_metric($payload['summary']['live_submission_allowed'], 'live allowed candidates') ?>
</div>
</section>
<section class="card">
<h2>Session state, redacted</h2>
<pre><?= lsr_h(json_encode($payload['session_state'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></pre>
</section>
<section class="card">
<h2>Commands</h2>
<pre><?= lsr_h(implode("\n", $payload['commands'])) ?></pre>
</section>
<section class="card">
<h2>Analyzed candidates</h2>
<div class="table-wrap"><table><thead><tr><th>Booking</th><th>Order Ref</th><th>Source</th><th>Status</th><th>Started</th><th>Driver</th><th>Plate</th><th>Mapping</th><th>Future Guard</th><th>Live Allowed</th><th>Blockers</th></tr></thead><tbody>
<?php foreach ($payload['candidates'] as $row): ?>
<tr>
<td><?= lsr_h($row['booking_id']) ?></td>
<td><?= lsr_h($row['order_reference']) ?></td>
<td><?= lsr_h($row['source_system']) ?></td>
<td><?= lsr_h($row['status']) ?></td>
<td><?= lsr_h($row['started_at']) ?></td>
<td><?= lsr_h($row['driver_name']) ?></td>
<td><?= lsr_h($row['plate']) ?></td>
<td><?= !empty($row['mapping_ready']) ? lsr_badge('yes','good') : lsr_badge('no','bad') ?></td>
<td><?= !empty($row['future_guard_passed']) ? lsr_badge('pass','good') : lsr_badge('fail','warn') ?></td>
<td><?= !empty($row['live_submission_allowed']) ? lsr_badge('YES','bad') : lsr_badge('blocked','good') ?></td>
<td><?= lsr_h(implode(', ', $row['live_blockers'] ?? [])) ?></td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
</section>
</main>
</body>
</html>
