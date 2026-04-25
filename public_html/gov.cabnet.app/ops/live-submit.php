<?php
/**
 * gov.cabnet.app — Disabled Live EDXEIX Submit Control Panel
 *
 * Guarded operations page for reviewing the final live-submit gate.
 * This page does not submit to EDXEIX in this preparatory patch.
 */

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Robots-Tag: noindex, nofollow', true);

require_once '/home/cabnet/gov.cabnet.app_app/lib/edxeix_live_submit_gate.php';

function ls_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function ls_badge(string $text, string $type = 'neutral'): string
{
    return '<span class="badge badge-' . ls_h($type) . '">' . ls_h($text) . '</span>';
}

function ls_bool_badge(bool $value, string $yes = 'pass', string $no = 'blocked'): string
{
    return $value ? ls_badge($yes, 'good') : ls_badge($no, 'bad');
}

function ls_request_param(string $key, string $default = ''): string
{
    $value = $_GET[$key] ?? $_POST[$key] ?? $default;
    $value = is_scalar($value) ? trim((string)$value) : $default;
    return mb_substr($value, 0, 255, 'UTF-8');
}

function ls_json_response(array $payload): void
{
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('X-Robots-Tag: noindex, nofollow', true);
    }
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit;
}

function ls_public_config_state(array $config): array
{
    return [
        'config_file' => gov_live_config_path(),
        'config_file_exists' => is_file(gov_live_config_path()),
        'live_submit_enabled' => !empty($config['live_submit_enabled']),
        'http_submit_enabled' => !empty($config['http_submit_enabled']),
        'edxeix_submit_url_configured' => trim((string)($config['edxeix_submit_url'] ?? '')) !== '',
        'confirmation_phrase_required' => !empty($config['require_confirmation_phrase']),
        'allowed_booking_id' => $config['allowed_booking_id'] ?? null,
        'allowed_order_reference' => $config['allowed_order_reference'] ?? null,
        'transport_note' => 'This preparatory patch still blocks live HTTP transport even if config is toggled.',
    ];
}

$error = null;
$config = gov_live_load_config();
$db = null;
$candidates = [];
$selected = null;
$postResult = null;

try {
    $bridgeConfig = gov_bridge_load_config();
    if (!empty($bridgeConfig['app']['timezone'])) {
        date_default_timezone_set((string)$bridgeConfig['app']['timezone']);
    }
    $db = gov_bridge_db();
    $limit = gov_bridge_int_param('limit', 50, 1, 200);
    $candidates = gov_live_analyzed_candidates($db, $limit);

    $bookingId = ls_request_param('booking_id', '');
    if ($bookingId !== '') {
        $booking = gov_live_booking_by_id($db, $bookingId);
        if ($booking) {
            $selected = gov_live_analyze_booking($db, $booking, $config);
        }
    } elseif ($candidates) {
        $selected = $candidates[0];
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $postBookingId = ls_request_param('booking_id', '');
        $confirm = ls_request_param('confirm', '');
        $booking = gov_live_booking_by_id($db, $postBookingId);
        if (!$booking) {
            throw new RuntimeException('Selected booking was not found.');
        }
        $postResult = gov_live_submit_if_allowed($db, $booking, $confirm);
        $selected = $postResult['analysis'] ?? gov_live_analyze_booking($db, $booking, $config);
    }
} catch (Throwable $e) {
    $error = $e->getMessage();
}

if (ls_request_param('format', '') === 'json') {
    ls_json_response([
        'ok' => $error === null,
        'script' => 'ops/live-submit.php',
        'generated_at' => date('Y-m-d H:i:s'),
        'read_only_when_get' => $_SERVER['REQUEST_METHOD'] !== 'POST',
        'calls_bolt' => false,
        'calls_edxeix' => false,
        'writes_database_on_get' => false,
        'live_http_transport_enabled_in_this_patch' => false,
        'config_state' => ls_public_config_state($config),
        'candidate_count' => count($candidates),
        'selected' => $selected ? [
            'booking_id' => $selected['booking_id'],
            'order_reference' => $selected['order_reference'],
            'source_system' => $selected['source_system'],
            'status' => $selected['status'],
            'started_at' => $selected['started_at'],
            'driver_name' => $selected['driver_name'],
            'plate' => $selected['plate'],
            'technical_payload_valid' => $selected['technical_payload_valid'],
            'live_submission_allowed' => $selected['live_submission_allowed'],
            'technical_blockers' => $selected['technical_blockers'],
            'live_blockers' => $selected['live_blockers'],
            'payload_hash' => $selected['payload_hash'],
        ] : null,
        'post_result' => $postResult,
        'error' => $error,
        'note' => 'Preparatory live-submit gate only. No EDXEIX HTTP request is performed by this patch.',
    ]);
}

$phrase = (string)($config['confirmation_phrase'] ?? 'I UNDERSTAND SUBMIT LIVE TO EDXEIX');
$liveEnabled = !empty($config['live_submit_enabled']);
$httpEnabled = !empty($config['http_submit_enabled']);
$transportStillBlocked = true;
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Live EDXEIX Submit Gate | gov.cabnet.app</title>
    <style>
        :root { --bg:#f3f6fb; --panel:#fff; --ink:#07152f; --muted:#41577a; --line:#d7e1ef; --nav:#081225; --blue:#2563eb; --green:#07875a; --orange:#b85c00; --red:#b42318; --slate:#334155; }
        *{box-sizing:border-box} body{margin:0;background:var(--bg);color:var(--ink);font-family:Arial,Helvetica,sans-serif}.nav{background:var(--nav);color:#fff;min-height:56px;display:flex;align-items:center;gap:18px;padding:0 26px;position:sticky;top:0;z-index:5;overflow:auto}.nav strong{white-space:nowrap}.nav a{color:#fff;text-decoration:none;font-size:15px;white-space:nowrap;opacity:.92}.nav a:hover{opacity:1;text-decoration:underline}.wrap{width:min(1480px,calc(100% - 48px));margin:26px auto 60px}.card{background:var(--panel);border:1px solid var(--line);border-radius:14px;padding:18px;margin-bottom:18px;box-shadow:0 10px 26px rgba(8,18,37,.04)}h1{font-size:34px;margin:0 0 12px}h2{font-size:23px;margin:0 0 14px}h3{margin:0 0 8px}p{color:var(--muted);line-height:1.45}.hero{border-left:7px solid var(--red)}.safe{border-left:7px solid var(--green)}.warn{border-left:7px solid var(--orange)}.grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-top:14px}.metric{border:1px solid var(--line);border-radius:10px;padding:14px;background:#f8fbff;min-height:82px}.metric strong{display:block;font-size:26px;line-height:1.05;word-break:break-word}.metric span{color:var(--muted);font-size:14px}.badge{display:inline-block;padding:5px 9px;border-radius:999px;font-size:12px;font-weight:700;margin:1px 3px 1px 0;white-space:nowrap}.badge-good{background:#dcfce7;color:#166534}.badge-warn{background:#fff7ed;color:#b45309}.badge-bad{background:#fee2e2;color:#991b1b}.badge-neutral{background:#eaf1ff;color:#1e40af}.actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:12px}.btn,button{display:inline-block;padding:10px 14px;border-radius:8px;color:#fff;text-decoration:none;font-weight:700;background:var(--blue);font-size:14px;border:0;cursor:pointer}.btn.dark{background:var(--slate)}.btn.orange{background:var(--orange)}button.danger{background:var(--red)}button[disabled]{opacity:.45;cursor:not-allowed}.table-wrap{overflow:auto;border:1px solid var(--line);border-radius:10px}table{width:100%;border-collapse:collapse;min-width:950px}th,td{text-align:left;padding:10px 12px;border-bottom:1px solid var(--line);vertical-align:top;font-size:14px}th{background:#f8fafc;font-size:12px;text-transform:uppercase;letter-spacing:.02em}.list{margin:0;padding-left:18px;color:var(--muted)}.list li{margin:7px 0}.badline{color:#991b1b}.goodline{color:#166534}.warnline{color:#b45309}.small{font-size:13px;color:var(--muted)}code{background:#eef2ff;padding:2px 5px;border-radius:5px}pre{background:#0b1020;color:#d7e3ff;padding:14px;border-radius:12px;max-height:420px;overflow:auto}.two{display:grid;grid-template-columns:1fr 1fr;gap:18px}input{width:100%;padding:10px 12px;border:1px solid var(--line);border-radius:8px;font-size:14px}label{display:block;font-weight:700;font-size:13px;margin-bottom:5px}@media(max-width:1100px){.grid{grid-template-columns:repeat(2,minmax(0,1fr))}.two{grid-template-columns:1fr}}@media(max-width:720px){.grid{grid-template-columns:1fr}.wrap{width:calc(100% - 24px);margin-top:14px}.nav{padding:0 14px}}
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
    <a href="/ops/live-submit.php">Live Submit Gate</a>
    <a href="/ops/help.php">Help</a>
</nav>

<main class="wrap">
    <section class="card hero">
        <h1>Live EDXEIX Submit Gate</h1>
        <p>This is the final production control panel scaffold. It is intentionally blocked in this preparatory patch. No EDXEIX HTTP request is performed.</p>
        <div>
            <?= ls_badge('LIVE HTTP TRANSPORT BLOCKED', 'bad') ?>
            <?= $liveEnabled ? ls_badge('CONFIG LIVE ENABLED', 'warn') : ls_badge('CONFIG LIVE DISABLED', 'good') ?>
            <?= $httpEnabled ? ls_badge('HTTP CONFIG ENABLED', 'warn') : ls_badge('HTTP CONFIG DISABLED', 'good') ?>
            <?= ls_badge('OPS GUARDED', 'good') ?>
        </div>
        <?php if ($error): ?><p class="badline"><strong>Error:</strong> <?= ls_h($error) ?></p><?php endif; ?>
        <?php if ($postResult): ?>
            <p class="warnline"><strong>POST result:</strong> <?= ls_h($postResult['response']['body'] ?? 'Blocked. No EDXEIX request performed.') ?></p>
        <?php endif; ?>
        <div class="actions">
            <a class="btn" href="/ops/live-submit.php?format=json">Open Gate JSON</a>
            <a class="btn dark" href="/ops/future-test.php">Open Future Test</a>
            <a class="btn orange" href="/bolt_edxeix_preflight.php?limit=30">Open Preflight</a>
        </div>
        <div class="grid">
            <div class="metric"><strong><?= count($candidates) ?></strong><span>Analyzed candidates</span></div>
            <div class="metric"><strong><?= $selected ? ls_h($selected['booking_id']) : 'none' ?></strong><span>Selected booking</span></div>
            <div class="metric"><strong><?= $selected && $selected['technical_payload_valid'] ? 'yes' : 'no' ?></strong><span>Technical payload valid</span></div>
            <div class="metric"><strong>no</strong><span>Live HTTP execution</span></div>
        </div>
    </section>

    <section class="card safe">
        <h2>Current Safety Gates</h2>
        <div class="table-wrap"><table>
            <thead><tr><th>Gate</th><th>Status</th><th>Meaning</th></tr></thead>
            <tbody>
                <tr><td><strong>Server config live_submit_enabled</strong></td><td><?= ls_bool_badge($liveEnabled, 'enabled', 'disabled') ?></td><td>Must be true in server-only config for a future live patch.</td></tr>
                <tr><td><strong>Server config http_submit_enabled</strong></td><td><?= ls_bool_badge($httpEnabled, 'enabled', 'disabled') ?></td><td>Must be true in server-only config for a future live patch.</td></tr>
                <tr><td><strong>EDXEIX URL configured</strong></td><td><?= ls_bool_badge(trim((string)($config['edxeix_submit_url'] ?? '')) !== '', 'configured', 'missing') ?></td><td>The exact EDXEIX submit URL is required later.</td></tr>
                <tr><td><strong>EDXEIX session ready</strong></td><td><?= $selected ? ls_bool_badge(!empty($selected['session_state']['ready']), 'ready', 'not ready') : ls_badge('unknown', 'warn') ?></td><td>Saved server-side cookie/CSRF must be available. Secrets are never displayed.</td></tr>
                <tr><td><strong>HTTP transport in this patch</strong></td><td><?= ls_badge('blocked', 'bad') ?></td><td>This patch intentionally refuses live HTTP even if other gates are toggled.</td></tr>
            </tbody>
        </table></div>
    </section>

    <section class="two">
        <div class="card">
            <h2>Selected Candidate</h2>
            <?php if (!$selected): ?>
                <p class="warnline"><strong>No candidate selected.</strong> A real future Bolt candidate is needed before live submission can ever be considered.</p>
            <?php else: ?>
                <ul class="list">
                    <li>Booking ID: <strong><?= ls_h($selected['booking_id']) ?></strong></li>
                    <li>Order reference: <code><?= ls_h($selected['order_reference']) ?></code></li>
                    <li>Source: <strong><?= ls_h($selected['source_system']) ?></strong></li>
                    <li>Status: <strong><?= ls_h($selected['status']) ?></strong></li>
                    <li>Started at: <strong><?= ls_h($selected['started_at']) ?></strong></li>
                    <li>Driver: <strong><?= ls_h($selected['driver_name']) ?></strong></li>
                    <li>Plate: <strong><?= ls_h($selected['plate']) ?></strong></li>
                    <li>Technical payload: <?= ls_bool_badge($selected['technical_payload_valid'], 'valid', 'blocked') ?></li>
                    <li>Live allowed: <?= ls_bool_badge($selected['live_submission_allowed'], 'allowed', 'blocked') ?></li>
                </ul>
                <?php if ($selected['technical_blockers']): ?><p class="badline"><strong>Technical blockers:</strong> <?= ls_h(implode(', ', $selected['technical_blockers'])) ?></p><?php endif; ?>
                <?php if ($selected['live_blockers']): ?><p class="badline"><strong>Live blockers:</strong> <?= ls_h(implode(', ', $selected['live_blockers'])) ?></p><?php endif; ?>
            <?php endif; ?>
        </div>
        <div class="card warn">
            <h2>Disabled Submit Form</h2>
            <p>This form exists to validate the final operator flow. In this patch it is still blocked and performs no EDXEIX HTTP request.</p>
            <form method="post">
                <label for="booking_id">Booking ID</label>
                <input id="booking_id" name="booking_id" value="<?= ls_h($selected['booking_id'] ?? '') ?>" placeholder="real normalized booking id" required>
                <label for="confirm" style="margin-top:10px;">Confirmation phrase</label>
                <input id="confirm" name="confirm" value="" placeholder="<?= ls_h($phrase) ?>" required>
                <p class="small">Required phrase later: <code><?= ls_h($phrase) ?></code></p>
                <button class="danger" type="submit" disabled>Submit to EDXEIX — Disabled in this patch</button>
            </form>
        </div>
    </section>

    <section class="card">
        <h2>Candidate List</h2>
        <?php if (!$candidates): ?>
            <p>No real/future/technically-valid candidates are currently available. This is expected until the real Bolt test ride exists.</p>
        <?php else: ?>
            <div class="table-wrap"><table>
                <thead><tr><th>Booking</th><th>Order Ref</th><th>Source</th><th>Status</th><th>Started</th><th>Driver</th><th>Plate</th><th>Technical</th><th>Live</th><th>Open</th></tr></thead>
                <tbody>
                <?php foreach ($candidates as $row): ?>
                    <tr>
                        <td><?= ls_h($row['booking_id']) ?></td>
                        <td><code><?= ls_h($row['order_reference']) ?></code></td>
                        <td><?= ls_h($row['source_system']) ?></td>
                        <td><?= ls_h($row['status']) ?></td>
                        <td><?= ls_h($row['started_at']) ?></td>
                        <td><?= ls_h($row['driver_name']) ?></td>
                        <td><?= ls_h($row['plate']) ?></td>
                        <td><?= ls_bool_badge($row['technical_payload_valid'], 'valid', 'blocked') ?></td>
                        <td><?= ls_bool_badge($row['live_submission_allowed'], 'allowed', 'blocked') ?></td>
                        <td><a class="btn dark" href="/ops/live-submit.php?booking_id=<?= urlencode((string)$row['booking_id']) ?>">Review</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
        <?php endif; ?>
    </section>

    <?php if ($selected && !empty($selected['edxeix_payload_preview'])): ?>
    <section class="card">
        <h2>EDXEIX Payload Preview</h2>
        <p class="small">Secrets such as cookies and CSRF tokens are not shown here. The payload is for review only.</p>
        <pre><?= ls_h(json_encode($selected['edxeix_payload_preview'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></pre>
    </section>
    <?php endif; ?>
</main>
</body>
</html>
