<?php
/**
 * gov.cabnet.app — V3 Starting-Point Guard Status
 *
 * Read-only visibility for verified EDXEIX starting-point options and active V3 queue validation.
 */

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Robots-Tag: noindex, nofollow', true);

const SPGP_VERSION = 'v3.0.18-starting-point-guard-page';

function spgp_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function spgp_badge(string $text, string $type = 'neutral'): string
{
    return '<span class="badge badge-' . spgp_h($type) . '">' . spgp_h($text) . '</span>';
}

function spgp_private_file(string $relative): string
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

function spgp_app_context(?string &$error = null): ?array
{
    $bootstrap = spgp_private_file('src/bootstrap.php');
    if (!is_file($bootstrap)) {
        $error = 'Private app bootstrap not found.';
        return null;
    }
    try {
        $ctx = require $bootstrap;
        if (!is_array($ctx) || !isset($ctx['db']) || !method_exists($ctx['db'], 'connection')) {
            $error = 'Private app bootstrap did not return DB context.';
            return null;
        }
        return $ctx;
    } catch (Throwable $e) {
        $error = $e->getMessage();
        return null;
    }
}

function spgp_table_exists(mysqli $db, string $table): bool
{
    $stmt = $db->prepare('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1');
    if (!$stmt) { return false; }
    $stmt->bind_param('s', $table);
    $stmt->execute();
    return (bool)$stmt->get_result()->fetch_assoc();
}

/** @return array<int,array<string,mixed>> */
function spgp_fetch_all(mysqli $db, string $sql): array
{
    $rows = [];
    $res = $db->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) { $rows[] = $row; }
    }
    return $rows;
}

$ctxError = null;
$ctx = spgp_app_context($ctxError);
$db = null;
$dbName = '';
$error = $ctxError;
$schema = [
    'queue' => false,
    'events' => false,
    'options' => false,
];
$options = [];
$activeRows = [];
$recentEvents = [];
$validCount = 0;
$invalidKnownCount = 0;
$unknownLessorCount = 0;

if ($ctx) {
    try {
        /** @var mysqli $db */
        $db = $ctx['db']->connection();
        $res = $db->query('SELECT DATABASE() AS db_name');
        $dbNameRow = $res ? $res->fetch_assoc() : null;
        $dbName = (string)($dbNameRow['db_name'] ?? '');
        $schema['queue'] = spgp_table_exists($db, 'pre_ride_email_v3_queue');
        $schema['events'] = spgp_table_exists($db, 'pre_ride_email_v3_queue_events');
        $schema['options'] = spgp_table_exists($db, 'pre_ride_email_v3_starting_point_options');

        if ($schema['options']) {
            $options = spgp_fetch_all($db, "
                SELECT edxeix_lessor_id, edxeix_starting_point_id, label, is_active, source, updated_at
                FROM pre_ride_email_v3_starting_point_options
                ORDER BY edxeix_lessor_id ASC, is_active DESC, label ASC
            ");
        }

        if ($schema['queue']) {
            $activeRows = spgp_fetch_all($db, "
                SELECT q.id, q.dedupe_key, q.queue_status, q.lessor_id, q.starting_point_id,
                       q.customer_name, q.driver_name, q.vehicle_plate, q.pickup_datetime,
                       EXISTS(
                         SELECT 1 FROM pre_ride_email_v3_starting_point_options o
                         WHERE o.edxeix_lessor_id = q.lessor_id
                           AND o.edxeix_starting_point_id = q.starting_point_id
                           AND o.is_active = 1
                       ) AS start_verified,
                       (SELECT COUNT(*) FROM pre_ride_email_v3_starting_point_options o2 WHERE o2.edxeix_lessor_id = q.lessor_id AND o2.is_active = 1) AS known_options
                FROM pre_ride_email_v3_queue q
                WHERE q.queue_status IN ('queued', 'ready', 'submit_dry_run_ready', 'needs_review')
                ORDER BY COALESCE(q.pickup_datetime, q.created_at) ASC, q.id ASC
                LIMIT 100
            ");
            foreach ($activeRows as $row) {
                if ((int)($row['known_options'] ?? 0) <= 0) { $unknownLessorCount++; }
                elseif ((int)($row['start_verified'] ?? 0) === 1) { $validCount++; }
                else { $invalidKnownCount++; }
            }
        }

        if ($schema['events']) {
            $recentEvents = spgp_fetch_all($db, "
                SELECT id, queue_id, dedupe_key, event_type, event_status, event_message, created_by, created_at
                FROM pre_ride_email_v3_queue_events
                WHERE event_type LIKE 'starting_point_guard%'
                   OR event_type = 'operator_mapping_correction_blocked'
                ORDER BY id DESC
                LIMIT 25
            ");
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$schemaOk = $schema['queue'] && $schema['events'] && $schema['options'];
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>V3 Starting-Point Guard | gov.cabnet.app</title>
<style>
:root{--bg:#f3f6fb;--panel:#fff;--ink:#061735;--muted:#41577a;--line:#d7e1ef;--nav:#081225;--green:#07875a;--red:#b42318;--orange:#b85c00;--blue:#2563eb;--purple:#6d28d9}*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--ink);font-family:Arial,Helvetica,sans-serif}.nav{background:var(--nav);color:#fff;min-height:56px;display:flex;align-items:center;gap:18px;padding:0 26px;overflow:auto}.nav strong{white-space:nowrap}.nav a{color:#fff;text-decoration:none;font-size:14px;white-space:nowrap}.wrap{width:min(1480px,calc(100% - 48px));margin:22px auto 60px}.card{background:var(--panel);border:1px solid var(--line);border-radius:14px;padding:18px;margin-bottom:18px;box-shadow:0 10px 26px rgba(8,18,37,.04)}.hero{border-left:7px solid var(--purple)}h1{font-size:32px;margin:0 0 10px}h2{font-size:22px;margin:0 0 14px}p{color:var(--muted);line-height:1.45}.badge{display:inline-block;padding:5px 9px;border-radius:999px;font-size:12px;font-weight:700;margin:1px 3px 1px 0;white-space:nowrap}.badge-good{background:#dcfce7;color:#166534}.badge-warn{background:#fff7ed;color:#b45309}.badge-bad{background:#fee2e2;color:#991b1b}.badge-neutral{background:#eaf1ff;color:#1e40af}.grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}.metric{border:1px solid var(--line);border-radius:10px;padding:14px;background:#f8fbff}.metric strong{display:block;font-size:26px}.small{font-size:13px;color:var(--muted)}table{width:100%;border-collapse:collapse;font-size:13px}th,td{border-bottom:1px solid var(--line);padding:9px;text-align:left;vertical-align:top}th{background:#f8fbff;color:#27385f}code{background:#eef2ff;padding:2px 5px;border-radius:5px}.bad{color:#991b1b}.good{color:#166534}.warn{color:#92400e}@media(max-width:980px){.grid{grid-template-columns:1fr}.wrap{width:calc(100% - 24px)}}
</style>
</head>
<body>
<nav class="nav">
<strong>GC gov.cabnet.app</strong>
<a href="/ops/pre-ride-email-v3-queue-watch.php">V3 Watch</a>
<a href="/ops/pre-ride-email-v3-queue.php">V3 Queue</a>
<a href="/ops/pre-ride-email-v3-cron-health.php">V3 Cron Health</a>
<a href="/ops/pre-ride-email-v3-starting-point-guard.php">V3 Starting-Point Guard</a>
</nav>
<main class="wrap">
<section class="card hero">
<h1>V3 Starting-Point Guard</h1>
<p>Read-only validation page for operator-verified EDXEIX starting-point IDs. Version <code><?= spgp_h(SPGP_VERSION) ?></code>.</p>
<div>
<?= spgp_badge($schemaOk ? 'SCHEMA READY' : 'SCHEMA MISSING', $schemaOk ? 'good' : 'bad') ?>
<?= spgp_badge('NO DB WRITES', 'good') ?>
<?= spgp_badge('NO EDXEIX CALLS', 'good') ?>
<?= spgp_badge('NO AADE CALLS', 'good') ?>
</div>
</section>

<?php if ($error): ?><section class="card"><p class="bad"><strong>Error:</strong> <?= spgp_h($error) ?></p></section><?php endif; ?>

<section class="card">
<h2>Status</h2>
<div class="grid">
<div class="metric"><strong><?= spgp_h($dbName ?: 'n/a') ?></strong><span class="small">Database</span></div>
<div class="metric"><strong><?= spgp_h((string)count($options)) ?></strong><span class="small">Verified options</span></div>
<div class="metric"><strong><?= spgp_h((string)$validCount) ?></strong><span class="small">Active rows valid</span></div>
<div class="metric"><strong><?= spgp_h((string)$invalidKnownCount) ?></strong><span class="small">Active rows known-invalid</span></div>
</div>
<p class="small">Schema: queue <?= $schema['queue'] ? spgp_badge('yes','good') : spgp_badge('no','bad') ?> events <?= $schema['events'] ? spgp_badge('yes','good') : spgp_badge('no','bad') ?> options <?= $schema['options'] ? spgp_badge('yes','good') : spgp_badge('no','bad') ?></p>
</section>

<section class="card">
<h2>Verified EDXEIX starting-point options</h2>
<?php if (!$options): ?><p>No verified options found. Run the SQL migration first.</p><?php else: ?>
<table><thead><tr><th>Lessor</th><th>Starting point ID</th><th>Label</th><th>Active</th><th>Source</th><th>Updated</th></tr></thead><tbody>
<?php foreach ($options as $row): ?><tr>
<td><?= spgp_h($row['edxeix_lessor_id'] ?? '') ?></td>
<td><code><?= spgp_h($row['edxeix_starting_point_id'] ?? '') ?></code></td>
<td><?= spgp_h($row['label'] ?? '') ?></td>
<td><?= !empty($row['is_active']) ? spgp_badge('active','good') : spgp_badge('inactive','warn') ?></td>
<td><?= spgp_h($row['source'] ?? '') ?></td>
<td><?= spgp_h($row['updated_at'] ?? '') ?></td>
</tr><?php endforeach; ?>
</tbody></table>
<?php endif; ?>
</section>

<section class="card">
<h2>Active V3 queue rows</h2>
<?php if (!$activeRows): ?><p>No active V3 queue rows to validate.</p><?php else: ?>
<table><thead><tr><th>ID</th><th>Status</th><th>Pickup</th><th>Transfer</th><th>Lessor</th><th>Queued start</th><th>Guard</th></tr></thead><tbody>
<?php foreach ($activeRows as $row):
    $known = (int)($row['known_options'] ?? 0) > 0;
    $verified = (int)($row['start_verified'] ?? 0) === 1;
    $guard = !$known ? spgp_badge('unknown lessor options','warn') : ($verified ? spgp_badge('verified','good') : spgp_badge('known-invalid','bad'));
?>
<tr>
<td><a href="/ops/pre-ride-email-v3-queue.php?id=<?= (int)$row['id'] ?>"><?= (int)$row['id'] ?></a></td>
<td><?= spgp_h($row['queue_status'] ?? '') ?></td>
<td><?= spgp_h($row['pickup_datetime'] ?? '') ?></td>
<td><?= spgp_h(($row['customer_name'] ?? '') . ' / ' . ($row['driver_name'] ?? '') . ' / ' . ($row['vehicle_plate'] ?? '')) ?></td>
<td><?= spgp_h($row['lessor_id'] ?? '') ?></td>
<td><code><?= spgp_h($row['starting_point_id'] ?? '') ?></code></td>
<td><?= $guard ?></td>
</tr>
<?php endforeach; ?>
</tbody></table>
<?php endif; ?>
</section>

<section class="card">
<h2>Recent starting-point guard events</h2>
<?php if (!$recentEvents): ?><p>No guard events yet.</p><?php else: ?>
<table><thead><tr><th>ID</th><th>Queue</th><th>Type</th><th>Status</th><th>Message</th><th>By</th><th>Created</th></tr></thead><tbody>
<?php foreach ($recentEvents as $row): ?><tr>
<td><?= (int)$row['id'] ?></td>
<td><?= spgp_h($row['queue_id'] ?? '') ?></td>
<td><?= spgp_h($row['event_type'] ?? '') ?></td>
<td><?= spgp_h($row['event_status'] ?? '') ?></td>
<td><?= spgp_h($row['event_message'] ?? '') ?></td>
<td><?= spgp_h($row['created_by'] ?? '') ?></td>
<td><?= spgp_h($row['created_at'] ?? '') ?></td>
</tr><?php endforeach; ?>
</tbody></table>
<?php endif; ?>
</section>
</main>
</body>
</html>
