<?php
/**
 * Bolt API Visibility Diagnostic UI.
 *
 * Operator page for observing whether the current Bolt Fleet orders endpoint
 * exposes an active/assigned/in-progress trip before completion.
 */

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Robots-Tag: noindex, nofollow', true);

require_once '/home/cabnet/gov.cabnet.app_app/lib/bolt_visibility_diagnostic.php';

function bv_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function bv_bool_param(string $key, bool $default = false): bool
{
    return gov_bridge_bool_param($key, $default);
}

function bv_query(array $replace = []): string
{
    $base = array_merge($_GET, $replace);
    foreach ($base as $key => $value) {
        if ($value === null) {
            unset($base[$key]);
        }
    }
    return http_build_query($base);
}

function bv_badge(string $text, string $type = 'neutral'): string
{
    return '<span class="badge badge-' . bv_h($type) . '">' . bv_h($text) . '</span>';
}

function bv_count_badge($value): string
{
    $n = is_numeric($value) ? (int)$value : 0;
    return bv_badge((string)$n, $n > 0 ? 'good' : 'warn');
}

function bv_status_class(?string $status): string
{
    $status = strtoupper((string)$status);
    if (in_array($status, ['COMPLETED', 'FINISHED', 'DONE', 'ACCEPTED', 'ACTIVE', 'STARTED', 'IN_PROGRESS'], true)) {
        return 'good';
    }
    if (in_array($status, ['CANCELLED', 'FAILED', 'REJECTED', 'EXPIRED'], true)) {
        return 'bad';
    }
    return 'neutral';
}

$run = bv_bool_param('run', false);
$record = bv_bool_param('record', false);
$hoursBack = gov_bridge_int_param('hours_back', 24, 1, 2160);
$sampleLimit = gov_bridge_int_param('sample_limit', 20, 1, 50);
$refresh = gov_bridge_int_param('refresh', 0, 0, 300);
$label = trim((string)gov_bridge_request_param('label', ''));
$watchDriverUuid = trim((string)gov_bridge_request_param('watch_driver_uuid', ''));
$watchVehiclePlate = trim((string)gov_bridge_request_param('watch_vehicle_plate', ''));
$watchOrderId = trim((string)gov_bridge_request_param('watch_order_id', ''));

$snapshot = null;
$error = null;

if ($run) {
    try {
        $snapshot = gov_bolt_visibility_build_snapshot([
            'hours_back' => $hoursBack,
            'sample_limit' => $sampleLimit,
            'record' => $record,
            'label' => $label,
            'watch_driver_uuid' => $watchDriverUuid,
            'watch_vehicle_plate' => $watchVehiclePlate,
            'watch_order_id' => $watchOrderId,
        ]);
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$recent = gov_bolt_visibility_recent_snapshots(80);
$recentDisplay = array_reverse($recent);
$autoRefreshUrl = '/ops/bolt-api-visibility.php?' . bv_query(['run' => '1', 'record' => $record ? '1' : '0']);
$jsonUrl = '/ops/bolt-api-visibility-run.php?' . bv_query(['run' => null]);
$filipposUrl = '/ops/bolt-api-visibility.php?' . http_build_query([
    'run' => '1',
    'record' => '1',
    'hours_back' => '24',
    'sample_limit' => '20',
    'watch_driver_uuid' => '57256761-d21b-4940-a3ca-bdcec5ef6af1',
    'watch_vehicle_plate' => 'EMX6874',
    'label' => 'filippos-emx6874-probe',
]);
$filipposRefreshUrl = $filipposUrl . '&refresh=20';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <?php if ($run && $refresh > 0): ?>
        <meta http-equiv="refresh" content="<?= bv_h((string)$refresh) ?>;url=<?= bv_h($autoRefreshUrl) ?>">
    <?php endif; ?>
    <title>Bolt API Visibility Diagnostic | gov.cabnet.app</title>
    <style>
        :root{--bg:#f4f7fb;--panel:#fff;--ink:#07152f;--muted:#475569;--line:#d7e1ef;--nav:#081225;--blue:#2563eb;--green:#07875a;--orange:#b85c00;--red:#b42318;--slate:#334155;--soft:#f8fbff}*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--ink);font-family:Arial,Helvetica,sans-serif}.nav{background:var(--nav);color:#fff;min-height:56px;display:flex;align-items:center;gap:18px;padding:0 26px;position:sticky;top:0;z-index:5;overflow:auto}.nav strong{white-space:nowrap}.nav a{color:#fff;text-decoration:none;font-size:15px;white-space:nowrap;opacity:.92}.nav a:hover{opacity:1;text-decoration:underline}.wrap{width:min(1480px,calc(100% - 48px));margin:26px auto 60px}.card{background:var(--panel);border:1px solid var(--line);border-radius:14px;padding:18px;margin-bottom:18px;box-shadow:0 10px 26px rgba(8,18,37,.04)}.hero{border-left:7px solid var(--blue)}.safety{background:#ecfdf3;border:1px solid #bbf7d0;border-left:7px solid var(--green);border-radius:14px;padding:16px;margin-bottom:18px}.safety strong{color:#166534}h1{font-size:34px;margin:0 0 12px}h2{font-size:23px;margin:0 0 14px}h3{font-size:18px;margin:0 0 8px}p{color:var(--muted);line-height:1.45}.grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}.two{display:grid;grid-template-columns:1fr 1fr;gap:18px}.metric{border:1px solid var(--line);border-radius:10px;padding:14px;background:var(--soft);min-height:82px}.metric strong{display:block;font-size:30px;line-height:1.05;word-break:break-word}.metric span{color:var(--muted);font-size:14px}.badge{display:inline-block;padding:5px 9px;border-radius:999px;font-size:12px;font-weight:700;margin:1px 3px 1px 0;white-space:nowrap}.badge-good{background:#dcfce7;color:#166534}.badge-warn{background:#fff7ed;color:#b45309}.badge-bad{background:#fee2e2;color:#991b1b}.badge-neutral{background:#eaf1ff;color:#1e40af}.btn{display:inline-block;padding:10px 14px;border-radius:8px;color:#fff;text-decoration:none;font-weight:700;background:var(--blue);border:0;cursor:pointer;font-size:14px}.btn.light{background:var(--slate)}.btn.good{background:var(--green)}.btn.warn{background:var(--orange)}label{display:block;font-size:13px;font-weight:700;color:var(--slate);margin:10px 0 5px}input,select{width:100%;padding:10px;border:1px solid var(--line);border-radius:8px;background:#fff;color:var(--ink)}input[type=checkbox]{width:auto;margin-right:8px}.form-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}.actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:14px}.table-wrap{overflow:auto;border:1px solid var(--line);border-radius:10px}table{width:100%;border-collapse:collapse;background:#fff}th,td{border-bottom:1px solid var(--line);padding:10px;text-align:left;vertical-align:top;font-size:14px}th{background:#f1f5f9;color:#334155}.small{font-size:13px;color:var(--muted)}code{background:#eef2ff;padding:2px 5px;border-radius:5px}.badline{color:#991b1b}.goodline{color:#166534}.warnline{color:#b45309}.mono{font-family:Consolas,Menlo,monospace;font-size:12px;word-break:break-all}.list{margin:0;padding-left:18px;color:var(--muted)}.list li{margin:7px 0}@media(max-width:1050px){.grid,.two,.form-grid{grid-template-columns:1fr 1fr}}@media(max-width:720px){.grid,.two,.form-grid{grid-template-columns:1fr}.wrap{width:calc(100% - 24px);margin-top:14px}.nav{padding:0 14px}}
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
    <a href="/ops/bolt-api-visibility.php">Bolt Visibility</a>
</nav>

<main class="wrap">
    <section class="safety">
        <strong>SAFE DIAGNOSTIC ONLY.</strong>
        This tool probes Bolt order visibility through the existing dry-run sync path. It does not submit to EDXEIX, does not stage jobs, and does not print raw Bolt payloads.
    </section>

    <section class="card hero">
        <h1>Bolt API Visibility Diagnostic</h1>
        <p>Use this during a real Bolt test ride to prove exactly when the current Bolt Fleet orders endpoint exposes the trip: accepted/assigned, passenger picked up, trip started, or only after completion.</p>
        <div><?= bv_badge('EDXEIX LIVE SUBMIT OFF', 'good') ?> <?= bv_badge('BOLT DRY-RUN PROBE', 'good') ?> <?= bv_badge('SANITIZED SUMMARY ONLY', 'good') ?></div>
    </section>

    <?php if ($run && $refresh > 0): ?>
        <section class="card">
            <strong class="warnline">Auto-refresh active:</strong>
            this page will run another sanitized dry-run probe every <?= bv_h((string)$refresh) ?> seconds.
            <div class="actions"><a class="btn light" href="/ops/bolt-api-visibility.php">Stop auto-refresh</a></div>
        </section>
    <?php endif; ?>

    <section class="two">
        <div class="card">
            <h2>Run a snapshot</h2>
            <form method="get" action="/ops/bolt-api-visibility.php">
                <input type="hidden" name="run" value="1">
                <div class="form-grid">
                    <div>
                        <label for="hours_back">Hours back</label>
                        <input id="hours_back" name="hours_back" type="number" min="1" max="2160" value="<?= bv_h((string)$hoursBack) ?>">
                    </div>
                    <div>
                        <label for="sample_limit">Sample limit</label>
                        <input id="sample_limit" name="sample_limit" type="number" min="1" max="50" value="<?= bv_h((string)$sampleLimit) ?>">
                    </div>
                    <div>
                        <label for="refresh">Auto-refresh seconds</label>
                        <input id="refresh" name="refresh" type="number" min="0" max="300" value="<?= bv_h((string)$refresh) ?>">
                    </div>
                    <div>
                        <label for="label">Snapshot label</label>
                        <input id="label" name="label" value="<?= bv_h($label) ?>" placeholder="accepted / picked-up / started / completed">
                    </div>
                    <div>
                        <label for="watch_driver_uuid">Watch driver UUID</label>
                        <input id="watch_driver_uuid" name="watch_driver_uuid" value="<?= bv_h($watchDriverUuid) ?>" placeholder="Bolt driver UUID">
                    </div>
                    <div>
                        <label for="watch_vehicle_plate">Watch vehicle plate</label>
                        <input id="watch_vehicle_plate" name="watch_vehicle_plate" value="<?= bv_h($watchVehiclePlate) ?>" placeholder="EMX6874">
                    </div>
                    <div>
                        <label for="watch_order_id">Watch order ID fragment</label>
                        <input id="watch_order_id" name="watch_order_id" value="<?= bv_h($watchOrderId) ?>" placeholder="optional">
                    </div>
                    <div>
                        <label>&nbsp;</label>
                        <label><input type="checkbox" name="record" value="1" <?= $record ? 'checked' : '' ?>>Record sanitized private timeline</label>
                    </div>
                </div>
                <div class="actions">
                    <button class="btn good" type="submit">Run Snapshot</button>
                    <a class="btn warn" href="<?= bv_h($filipposUrl) ?>">Run Filippos + EMX6874</a>
                    <a class="btn light" href="<?= bv_h($filipposRefreshUrl) ?>">Watch Filippos every 20s</a>
                    <a class="btn light" href="<?= bv_h($jsonUrl) ?>">JSON endpoint</a>
                </div>
            </form>
        </div>
        <div class="card">
            <h2>Recommended live test sequence</h2>
            <ul class="list">
                <li>Create/schedule the Bolt ride 40–60 minutes in the future where possible.</li>
                <li>Run and record one snapshot after Filippos accepts the ride.</li>
                <li>Run another snapshot after pickup/waiting.</li>
                <li>Run another snapshot after the trip starts.</li>
                <li>Run the final snapshot after completion.</li>
            </ul>
            <p class="small">Known watch values: Filippos Bolt UUID <code>57256761-d21b-4940-a3ca-bdcec5ef6af1</code>, EMX6874 vehicle.</p>
        </div>
    </section>

    <?php if ($error !== null): ?>
        <section class="card">
            <h2>Snapshot error</h2>
            <p class="badline"><strong><?= bv_h($error) ?></strong></p>
        </section>
    <?php endif; ?>

    <?php if (is_array($snapshot)): ?>
        <?php $visibility = $snapshot['visibility'] ?? []; $watch = $visibility['watch']['matches'] ?? []; ?>
        <section class="card">
            <h2>Current snapshot</h2>
            <div class="grid">
                <div class="metric"><strong><?= bv_h((string)($visibility['orders_seen'] ?? 0)) ?></strong><span>Orders seen</span></div>
                <div class="metric"><strong><?= bv_h((string)($visibility['sample_count'] ?? 0)) ?></strong><span>Sanitized samples</span></div>
                <div class="metric"><strong><?= !empty($snapshot['recorded']) ? 'YES' : 'NO' ?></strong><span>Recorded privately</span></div>
                <div class="metric"><strong><?= bv_h((string)($snapshot['duration_ms'] ?? 0)) ?>ms</strong><span>Probe duration</span></div>
            </div>
            <p class="small">Captured at <code><?= bv_h((string)($snapshot['captured_at'] ?? '')) ?></code>. Probe ID <code><?= bv_h((string)($snapshot['probe_id'] ?? '')) ?></code>.</p>
            <div>
                <?= bv_badge('order match: ' . (!empty($watch['order_id']) ? 'YES' : 'NO'), !empty($watch['order_id']) ? 'good' : 'neutral') ?>
                <?= bv_badge('driver match: ' . (!empty($watch['driver_uuid']) ? 'YES' : 'NO'), !empty($watch['driver_uuid']) ? 'good' : 'neutral') ?>
                <?= bv_badge('vehicle match: ' . (!empty($watch['vehicle_plate']) ? 'YES' : 'NO'), !empty($watch['vehicle_plate']) ? 'good' : 'neutral') ?>
            </div>
            <?php if (!empty($snapshot['recorded_file'])): ?>
                <p class="small">Private artifact file: <code><?= bv_h((string)$snapshot['recorded_file']) ?></code></p>
            <?php endif; ?>
        </section>

        <section class="card">
            <h2>Sanitized order samples</h2>
            <?php $samples = $snapshot['order_samples'] ?? []; ?>
            <?php if (!$samples): ?>
                <p class="warnline"><strong>No order samples were exposed by the dry-run probe.</strong> This is the expected result if the active trip is still invisible to the current Bolt endpoint.</p>
            <?php else: ?>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>Order</th><th>Status</th><th>Driver</th><th>Vehicle</th><th>Times</th><th>Watch match</th></tr></thead>
                        <tbody>
                        <?php foreach ($samples as $sample): ?>
                            <?php $sampleWatch = $sample['watch_match'] ?? []; ?>
                            <tr>
                                <td class="mono"><?= bv_h((string)($sample['external_order_id'] ?? '')) ?></td>
                                <td><?= bv_badge((string)($sample['status'] ?? 'UNKNOWN'), bv_status_class($sample['status'] ?? null)) ?></td>
                                <td class="mono"><?= bv_h((string)($sample['driver_uuid_or_id'] ?? '')) ?></td>
                                <td><span class="mono"><?= bv_h((string)($sample['vehicle_id'] ?? '')) ?></span><br><?= bv_h((string)($sample['vehicle_plate'] ?? '')) ?></td>
                                <td class="small">
                                    Created: <?= bv_h((string)($sample['created_at'] ?? '')) ?><br>
                                    Scheduled: <?= bv_h((string)($sample['scheduled_for'] ?? '')) ?><br>
                                    Started: <?= bv_h((string)($sample['started_at'] ?? '')) ?><br>
                                    Ended: <?= bv_h((string)($sample['ended_at'] ?? '')) ?>
                                </td>
                                <td>
                                    <?= bv_badge('order ' . (!empty($sampleWatch['order_id']) ? 'YES' : 'NO'), !empty($sampleWatch['order_id']) ? 'good' : 'neutral') ?>
                                    <?= bv_badge('driver ' . (!empty($sampleWatch['driver_uuid']) ? 'YES' : 'NO'), !empty($sampleWatch['driver_uuid']) ? 'good' : 'neutral') ?>
                                    <?= bv_badge('vehicle ' . (!empty($sampleWatch['vehicle_plate']) ? 'YES' : 'NO'), !empty($sampleWatch['vehicle_plate']) ? 'good' : 'neutral') ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <section class="card">
        <h2>Today’s recorded sanitized timeline</h2>
        <?php if (!$recentDisplay): ?>
            <p>No recorded snapshots yet today. Run with <code>record=1</code> to create a private sanitized timeline.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Captured</th><th>Label</th><th>Orders</th><th>Samples</th><th>Status counts</th><th>Watch matches</th></tr></thead>
                    <tbody>
                    <?php foreach ($recentDisplay as $row): ?>
                        <?php $vis = $row['visibility'] ?? []; $matches = $vis['watch']['matches'] ?? []; ?>
                        <tr>
                            <td class="small"><code><?= bv_h((string)($row['captured_at'] ?? '')) ?></code><br><span class="mono"><?= bv_h((string)($row['probe_id'] ?? '')) ?></span></td>
                            <td><?= bv_h((string)($row['label'] ?? '')) ?></td>
                            <td><?= bv_count_badge($vis['orders_seen'] ?? 0) ?></td>
                            <td><?= bv_h((string)($vis['sample_count'] ?? 0)) ?></td>
                            <td class="small">
                                <?php foreach (($vis['status_counts_from_samples'] ?? []) as $status => $count): ?>
                                    <?= bv_badge((string)$status . ': ' . (string)$count, bv_status_class((string)$status)) ?>
                                <?php endforeach; ?>
                            </td>
                            <td>
                                <?= bv_badge('order ' . (!empty($matches['order_id']) ? 'YES' : 'NO'), !empty($matches['order_id']) ? 'good' : 'neutral') ?>
                                <?= bv_badge('driver ' . (!empty($matches['driver_uuid']) ? 'YES' : 'NO'), !empty($matches['driver_uuid']) ? 'good' : 'neutral') ?>
                                <?= bv_badge('vehicle ' . (!empty($matches['vehicle_plate']) ? 'YES' : 'NO'), !empty($matches['vehicle_plate']) ? 'good' : 'neutral') ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
