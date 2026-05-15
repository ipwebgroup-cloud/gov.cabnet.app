<?php
/**
 * Bolt Live Data panel for gov.cabnet.app ops.
 * Shows Bolt reference mappings, normalized bookings, EDXEIX preview payloads,
 * and a read-only submission preflight checklist.
 */

declare(strict_types=1);

require_once '/home/cabnet/gov.cabnet.app_app/lib/bolt_sync_lib.php';

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function ops_value(array $row, array $keys, $default = '')
{
    foreach ($keys as $key) {
        if (array_key_exists($key, $row) && $row[$key] !== null && $row[$key] !== '') {
            return $row[$key];
        }
    }
    return $default;
}

function ops_terminal_status(string $status): bool
{
    $status = strtolower(trim($status));
    if ($status === '') {
        return false;
    }
    $terminalExact = [
        'finished', 'completed', 'client_cancelled', 'driver_cancelled',
        'driver_cancelled_after_accept', 'cancelled', 'canceled', 'expired',
        'rejected', 'failed',
    ];
    if (in_array($status, $terminalExact, true)) {
        return true;
    }
    return strpos($status, 'cancel') !== false || strpos($status, 'finished') !== false || strpos($status, 'complete') !== false;
}

function ops_badge(string $label, string $class = ''): string
{
    return '<span class="badge ' . h($class) . '">' . h($label) . '</span>';
}

function render_table(array $rows, array $columns): void
{
    if (!$rows) {
        echo '<p class="muted">Δεν υπάρχουν εγγραφές ακόμα.</p>';
        return;
    }
    echo '<div class="table-wrap"><table><thead><tr>';
    foreach ($columns as $label => $key) {
        echo '<th>' . h($label) . '</th>';
    }
    echo '</tr></thead><tbody>';
    foreach ($rows as $row) {
        echo '<tr>';
        foreach ($columns as $label => $key) {
            $value = is_callable($key) ? $key($row) : ($row[$key] ?? '');
            echo '<td>' . (is_string($value) && strpos($value, '<span') === 0 ? $value : h($value)) . '</td>';
        }
        echo '</tr>';
    }
    echo '</tbody></table></div>';
}

function build_preflight_rows(mysqli $db, array $bookings): array
{
    $rows = [];
    foreach ($bookings as $booking) {
        $preview = gov_build_edxeix_preview_payload($db, $booking);
        $mapping = $preview['_mapping_status'] ?? [];
        $status = (string)ops_value($booking, ['order_status', 'status'], '');
        $startedAt = (string)ops_value($booking, ['started_at'], '');
        $driverMapped = !empty($mapping['driver_mapped']);
        $vehicleMapped = !empty($mapping['vehicle_mapped']);
        $futureGuard = !empty($mapping['passes_future_guard']);
        $terminal = ops_terminal_status($status);

        $blockers = [];
        if (!$driverMapped) { $blockers[] = 'driver not mapped'; }
        if (!$vehicleMapped) { $blockers[] = 'vehicle not mapped'; }
        if (!$startedAt) { $blockers[] = 'missing started_at'; }
        elseif (!$futureGuard) { $blockers[] = '+30m guard failed'; }
        if ($terminal) { $blockers[] = 'terminal status'; }

        $mappingReady = $driverMapped && $vehicleMapped;
        $submissionSafe = $mappingReady && $futureGuard && !$terminal;

        $rows[] = [
            'order_reference' => ops_value($booking, ['order_reference', 'external_order_id', 'external_reference'], ''),
            'status' => $status,
            'started_at' => $startedAt,
            'driver' => trim((string)($preview['driver'] ?? '')),
            'vehicle' => trim((string)($preview['vehicle'] ?? '')),
            'plate' => ops_value($booking, ['vehicle_plate', 'plate'], ''),
            'mapping_ready' => $mappingReady ? 'yes' : 'no',
            'future_guard' => $futureGuard ? 'pass' : 'fail',
            'submission_safe' => $submissionSafe ? 'yes' : 'no',
            'blockers' => $blockers ? implode(', ', $blockers) : 'none',
            '_preview' => $preview,
        ];
    }
    return $rows;
}

$syncResult = null;
$error = null;
$drivers = $vehicles = $bookings = $preflightRows = [];
$latestPreview = null;
$summary = [
    'drivers' => 0,
    'vehicles' => 0,
    'orders' => 0,
    'mapping_ready' => 0,
    'submission_safe' => 0,
];

try {
    $config = gov_bridge_load_config();
    if (!empty($config['app']['timezone'])) {
        date_default_timezone_set((string)$config['app']['timezone']);
    }

    $action = $_GET['action'] ?? '';
    if ($action === 'sync_reference') {
        $syncResult = gov_bolt_sync_reference(720, false);
    } elseif ($action === 'sync_orders') {
        $syncResult = gov_bolt_sync_orders(gov_bridge_int_param('hours_back', 24, 1, 2160), false);
    }

    $db = gov_bridge_db();
    $drivers = gov_recent_rows($db, 'mapping_drivers', 20);
    $vehicles = gov_recent_rows($db, 'mapping_vehicles', 30);
    $bookings = gov_recent_rows($db, 'normalized_bookings', 30);
    $preflightRows = build_preflight_rows($db, $bookings);

    $summary['drivers'] = count($drivers);
    $summary['vehicles'] = count($vehicles);
    $summary['orders'] = count($bookings);
    foreach ($preflightRows as $row) {
        if ($row['mapping_ready'] === 'yes') { $summary['mapping_ready']++; }
        if ($row['submission_safe'] === 'yes') { $summary['submission_safe']++; }
    }

    if (!empty($preflightRows[0]['_preview'])) {
        $latestPreview = $preflightRows[0]['_preview'];
    } elseif (!empty($bookings[0])) {
        $latestPreview = gov_build_edxeix_preview_payload($db, $bookings[0]);
    }
} catch (Throwable $e) {
    $error = $e->getMessage();
}
?>
<!doctype html>
<html lang="el">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Bolt Live Data | gov.cabnet.app</title>
    <style>
        :root { color-scheme: light; --bg:#f5f7fb; --panel:#fff; --ink:#172033; --muted:#60708a; --line:#dce4f0; --accent:#1f66d1; --ok:#0b7f4f; --warn:#b36b00; --danger:#b42318; --soft:#eef4ff; }
        * { box-sizing:border-box; }
        body { margin:0; font-family:Arial, Helvetica, sans-serif; background:var(--bg); color:var(--ink); }
        header { background:#101828; color:#fff; padding:18px 24px; position:sticky; top:0; z-index:10; }
        header a { color:#fff; text-decoration:none; opacity:.9; margin-right:16px; }
        main { padding:24px; max-width:1480px; margin:0 auto; }
        .grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(280px,1fr)); gap:16px; }
        .stat-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(170px,1fr)); gap:12px; margin-top:14px; }
        .stat { background:#f8fafc; border:1px solid var(--line); border-radius:12px; padding:14px; }
        .stat strong { display:block; font-size:26px; color:#0f172a; }
        .stat span { color:var(--muted); font-size:13px; }
        .card { background:var(--panel); border:1px solid var(--line); border-radius:14px; padding:18px; box-shadow:0 8px 22px rgba(16,24,40,.06); margin-bottom:16px; }
        .toolbar { display:flex; gap:10px; flex-wrap:wrap; align-items:center; }
        .btn { display:inline-block; background:var(--accent); color:#fff; text-decoration:none; padding:10px 14px; border-radius:10px; font-weight:700; }
        .btn.secondary { background:#344054; }
        .btn.safe { background:var(--ok); }
        .btn.warn { background:var(--warn); }
        .muted { color:var(--muted); }
        .pill { display:inline-flex; align-items:center; border-radius:999px; padding:5px 10px; background:#eef4ff; color:#1849a9; font-weight:700; font-size:12px; }
        .badge { display:inline-flex; align-items:center; border-radius:999px; padding:4px 8px; font-size:12px; font-weight:700; background:#eef2f6; color:#344054; white-space:nowrap; }
        .badge.ok { background:#ecfdf3; color:#067647; }
        .badge.warn { background:#fffaeb; color:#b54708; }
        .badge.danger { background:#fef3f2; color:#b42318; }
        .table-wrap { overflow:auto; border:1px solid var(--line); border-radius:12px; }
        table { width:100%; border-collapse:collapse; background:#fff; min-width:920px; }
        th,td { text-align:left; padding:10px 12px; border-bottom:1px solid var(--line); vertical-align:top; font-size:14px; }
        th { background:#f8fafc; color:#475467; font-size:12px; text-transform:uppercase; letter-spacing:.03em; }
        pre { overflow:auto; background:#0b1020; color:#d7e3ff; padding:14px; border-radius:12px; max-height:460px; }
        .alert { border-left:5px solid var(--warn); background:#fff7ed; padding:12px 14px; border-radius:10px; margin-bottom:16px; }
        .alert.ok { border-left-color:var(--ok); background:#ecfdf3; }
        .alert.danger { border-left-color:var(--danger); background:#fef3f2; }
    </style>
</head>
<body>
<header>
    <strong>GC gov.cabnet.app</strong>
    <a href="/ops/index.php">Operations Console</a>
    <a href="/health">Health</a>
    <a href="/ops/public-utility-relocation-plan.php">Legacy Utility Plan</a>
    <a href="/ops/public-route-exposure-audit.php">Public Route Audit</a>
    <a href="/ops/readiness.php">Readiness</a>
    <a href="/bolt_edxeix_preflight.php">EDXEIX Preflight JSON</a>
</header>
<main>
    <section class="card">
        <span class="pill">Bolt Fleet API → mappings → normalized bookings → EDXEIX preflight</span>
        <h1>Bolt Live Data</h1>
        <p class="muted">This panel reads the local database tables after sync. It does not submit anything to EDXEIX. Mapping-ready and submission-safe are intentionally separate: live EDXEIX tests must pass the +30 minute future-start guard.</p>
        <div class="toolbar">
            <a class="btn safe" href="?action=sync_reference">Sync Bolt Drivers/Vehicles</a>
            <a class="btn" href="?action=sync_orders&hours_back=24">Sync Recent Orders 24h</a>
            <a class="btn secondary" href="?action=sync_orders&hours_back=168">Sync Recent Orders 7d</a>
            <a class="btn secondary" href="/ops/readiness.php">Open Readiness</a>
            <a class="btn warn" href="/bolt_edxeix_preflight.php?limit=30">Open Preflight JSON</a>
        </div>
        <div class="stat-grid">
            <div class="stat"><strong><?= h($summary['drivers']) ?></strong><span>Bolt driver mappings</span></div>
            <div class="stat"><strong><?= h($summary['vehicles']) ?></strong><span>Bolt vehicle mappings</span></div>
            <div class="stat"><strong><?= h($summary['orders']) ?></strong><span>Recent normalized orders</span></div>
            <div class="stat"><strong><?= h($summary['mapping_ready']) ?></strong><span>Mapping-ready rows</span></div>
            <div class="stat"><strong><?= h($summary['submission_safe']) ?></strong><span>Submission-safe rows</span></div>
        </div>
    </section>

    <?php if ($error): ?>
        <div class="alert danger"><strong>Error:</strong> <?= h($error) ?></div>
    <?php endif; ?>

    <?php if ($syncResult): ?>
        <div class="alert ok"><strong>Sync completed.</strong><pre><?= h(json_encode($syncResult, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></pre></div>
    <?php endif; ?>

    <section class="grid">
        <div class="card">
            <h2>Drivers</h2>
            <p class="muted">Bolt UUID-based driver mappings. EDXEIX driver IDs remain manually controlled.</p>
            <?php render_table($drivers, [
                'Source' => 'source_system',
                'Bolt Driver UUID' => 'external_driver_id',
                'Driver Name' => 'external_driver_name',
                'EDXEIX Driver ID' => 'edxeix_driver_id',
                'Last Seen' => 'last_seen_at',
            ]); ?>
        </div>
        <div class="card">
            <h2>Vehicles</h2>
            <p class="muted">Bolt vehicle UUID / plate mappings. EDXEIX vehicle IDs remain manually controlled.</p>
            <?php render_table($vehicles, [
                'Source' => 'source_system',
                'Bolt Vehicle UUID' => 'external_vehicle_id',
                'Plate' => 'plate',
                'Model' => 'vehicle_model',
                'EDXEIX Vehicle ID' => 'edxeix_vehicle_id',
                'Last Seen' => 'last_seen_at',
            ]); ?>
        </div>
    </section>

    <section class="card">
        <h2>EDXEIX Submission Preflight</h2>
        <p class="muted">Read-only safety review. A row is submission-safe only when driver and vehicle are mapped, the ride is not terminal/cancelled/finished, and started_at is at least +30 minutes in the future.</p>
        <?php render_table($preflightRows, [
            'Order Ref' => 'order_reference',
            'Status' => 'status',
            'Started At' => 'started_at',
            'Driver ID' => 'driver',
            'Vehicle ID' => 'vehicle',
            'Plate' => 'plate',
            'Mapping Ready' => function (array $row) { return $row['mapping_ready'] === 'yes' ? ops_badge('yes', 'ok') : ops_badge('no', 'danger'); },
            '+30m Guard' => function (array $row) { return $row['future_guard'] === 'pass' ? ops_badge('pass', 'ok') : ops_badge('fail', 'warn'); },
            'Submission Safe' => function (array $row) { return $row['submission_safe'] === 'yes' ? ops_badge('yes', 'ok') : ops_badge('no', 'danger'); },
            'Blockers' => 'blockers',
        ]); ?>
    </section>

    <section class="card">
        <h2>Recent Normalized Bolt Orders</h2>
        <p class="muted">Orders imported from getFleetOrders and normalized for the EDXEIX bridge. No submission happens here.</p>
        <?php render_table($bookings, [
            'Source' => 'source_system',
            'Order Ref' => 'order_reference',
            'Status' => function (array $row) {
                $status = (string)ops_value($row, ['order_status', 'status'], '');
                return ops_terminal_status($status) ? ops_badge($status, 'warn') : ops_badge($status, 'ok');
            },
            'Driver UUID' => 'driver_external_id',
            'Driver Name' => 'driver_name',
            'Vehicle UUID' => 'vehicle_external_id',
            'Plate' => 'vehicle_plate',
            'Pickup' => 'boarding_point',
            'Destination' => 'disembark_point',
            'Started At' => 'started_at',
            'Ended At' => 'ended_at',
            'Price' => 'price',
            'Mapping Ready' => function (array $row) { return !empty($row['edxeix_ready']) ? ops_badge('yes', 'ok') : ops_badge('no', 'danger'); },
        ]); ?>
    </section>

    <section class="card">
        <h2>Latest EDXEIX Payload Preview</h2>
        <p class="muted">Preview only. The CSRF token and cookies must be loaded from the saved EDXEIX session at real submit time.</p>
        <pre><?= h(json_encode($latestPreview ?: ['message' => 'No normalized booking available yet.'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></pre>
    </section>
</main>
</body>
</html>
