<?php
/**
 * gov.cabnet.app — Local dry-run future booking harness.
 *
 * SAFETY:
 * - Does not call Bolt.
 * - Does not call EDXEIX.
 * - Default page load is read-only preview.
 * - POST create only inserts a LAB/local normalized_bookings row.
 */

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Robots-Tag: noindex, nofollow', true);

require_once '/home/cabnet/gov.cabnet.app_app/lib/bolt_sync_lib.php';
require_once '/home/cabnet/gov.cabnet.app_app/src/TestBookingFactory.php';

function gov_tb_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function gov_tb_badge(string $text, string $type = 'neutral'): string
{
    return '<span class="badge badge-' . gov_tb_h($type) . '">' . gov_tb_h($text) . '</span>';
}

function gov_tb_json($value): string
{
    return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
}

$config = [];
$preview = null;
$result = null;
$error = null;
$minutesAhead = 75;

try {
    $config = gov_bridge_load_config();
    if (!empty($config['app']['timezone'])) {
        date_default_timezone_set((string)$config['app']['timezone']);
    }

    $minutesAhead = gov_bridge_int_param('minutes', 75, 35, 10080);
    $db = gov_bridge_db();
    $preview = GovTestBookingFactory::preview($db, $minutesAhead);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $confirm = trim((string)($_POST['confirm_text'] ?? ''));
        if ($confirm !== 'CREATE LOCAL DRY RUN BOOKING') {
            $result = [
                'ok' => false,
                'action' => 'blocked',
                'errors' => ['Confirmation text did not match. No row was created.'],
            ];
        } else {
            $result = GovTestBookingFactory::create($db, $minutesAhead);
            $preview = GovTestBookingFactory::preview($db, $minutesAhead);
        }
    }
} catch (Throwable $e) {
    $error = $e->getMessage();
}

$createdOk = is_array($result) && !empty($result['ok']);
$previewOk = is_array($preview) && !empty($preview['ok']);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Local Test Booking | gov.cabnet.app</title>
    <style>
        :root { --bg:#f3f6fb; --panel:#fff; --ink:#07152f; --muted:#41577a; --line:#d7e1ef; --nav:#081225; --blue:#2563eb; --green:#07875a; --orange:#b85c00; --red:#b42318; --slate:#334155; }
        * { box-sizing:border-box; }
        body { margin:0; background:var(--bg); color:var(--ink); font-family:Arial, Helvetica, sans-serif; }
        .nav { background:var(--nav); color:#fff; min-height:56px; display:flex; align-items:center; gap:18px; padding:0 26px; position:sticky; top:0; z-index:5; overflow:auto; }
        .nav strong { white-space:nowrap; }
        .nav a { color:#fff; text-decoration:none; font-size:15px; white-space:nowrap; opacity:.92; }
        .nav a:hover { opacity:1; text-decoration:underline; }
        .wrap { width:min(1280px, calc(100% - 48px)); margin:26px auto 60px; }
        .card { background:var(--panel); border:1px solid var(--line); border-radius:14px; padding:18px; margin-bottom:18px; box-shadow:0 10px 26px rgba(8,18,37,.04); }
        .hero { border-left:7px solid var(--orange); }
        .hero.good { border-left-color:var(--green); }
        .hero.bad { border-left-color:var(--red); }
        h1 { font-size:32px; margin:0 0 12px; }
        h2 { font-size:22px; margin:0 0 12px; }
        p { color:var(--muted); line-height:1.45; }
        .grid { display:grid; grid-template-columns:repeat(3, minmax(0,1fr)); gap:12px; margin-top:14px; }
        .metric { border:1px solid var(--line); border-radius:10px; padding:14px; background:#f8fbff; min-height:80px; }
        .metric strong { display:block; font-size:20px; line-height:1.15; word-break:break-word; }
        .metric span { color:var(--muted); font-size:14px; }
        .two { display:grid; grid-template-columns:1fr 1fr; gap:18px; }
        .badge { display:inline-block; padding:5px 9px; border-radius:999px; font-size:12px; font-weight:700; margin:1px 3px 1px 0; }
        .badge-good { background:#dcfce7; color:#166534; } .badge-warn { background:#fff7ed; color:#b45309; } .badge-bad { background:#fee2e2; color:#991b1b; } .badge-neutral { background:#eaf1ff; color:#1e40af; }
        .actions { display:flex; flex-wrap:wrap; gap:10px; margin-top:14px; }
        .btn { display:inline-block; border:0; cursor:pointer; padding:11px 15px; border-radius:8px; color:#fff; text-decoration:none; font-weight:700; background:var(--blue); font-size:14px; }
        .btn.green { background:var(--green); } .btn.orange { background:var(--orange); } .btn.red { background:var(--red); } .btn.dark { background:var(--slate); }
        input[type="number"], input[type="text"] { width:100%; padding:11px 12px; border:1px solid var(--line); border-radius:8px; font-size:15px; }
        label { display:block; font-weight:700; margin:10px 0 6px; }
        .small { font-size:13px; color:var(--muted); }
        .goodline { color:#166534; } .warnline { color:#b45309; } .badline { color:#991b1b; }
        pre { overflow:auto; background:#081225; color:#e5edff; padding:14px; border-radius:10px; font-size:13px; line-height:1.45; }
        code { background:#eef2ff; padding:2px 5px; border-radius:5px; }
        ul { color:var(--muted); }
        li { margin:6px 0; }
        @media (max-width:980px) { .grid, .two { grid-template-columns:1fr; } .wrap { width:calc(100% - 24px); } .nav { padding:0 14px; } }
    </style>
</head>
<body>
<nav class="nav">
    <strong>GC gov.cabnet.app</strong>
    <a href="/ops/index.php">Operations Console</a>
    <a href="/ops/bolt-live.php">Bolt Live</a>
    <a href="/ops/jobs.php">Jobs Queue</a>
    <a href="/ops/readiness.php">Readiness</a>
    <a href="/ops/test-booking.php">Local Test Booking</a>
    <a href="/bolt_readiness_audit.php">Readiness JSON</a>
</nav>

<main class="wrap">
    <section class="card hero <?= $error ? 'bad' : ($createdOk ? 'good' : '') ?>">
        <h1>Local Dry-Run Future Booking</h1>
        <p>This tool creates a synthetic LAB/local future booking so the Bolt → normalized booking → EDXEIX preflight/queue/worker workflow can be tested without a real future Bolt ride.</p>
        <p><strong>Safety:</strong> <?= gov_tb_badge('No Bolt call', 'good') ?> <?= gov_tb_badge('No EDXEIX call', 'good') ?> <?= gov_tb_badge('LAB/local row only', 'warn') ?> <?= gov_tb_badge('Never live-submit', 'bad') ?></p>
        <?php if ($error): ?>
            <p class="badline"><strong>Error:</strong> <?= gov_tb_h($error) ?></p>
        <?php elseif ($createdOk): ?>
            <p class="goodline"><strong>Created:</strong> normalized booking #<?= gov_tb_h($result['normalized_booking_id'] ?? '') ?> / <?= gov_tb_h($result['order_reference'] ?? '') ?></p>
        <?php elseif (!$previewOk): ?>
            <p class="badline"><strong>Blocked:</strong> the system cannot build a local test row yet.</p>
        <?php else: ?>
            <p class="warnline"><strong>Preview only:</strong> no database row has been created yet.</p>
        <?php endif; ?>
    </section>

    <?php if (!$error): ?>
    <section class="two">
        <div class="card">
            <h2>Preview / Create</h2>
            <form method="get" action="/ops/test-booking.php">
                <label for="minutes">Future start offset, minutes</label>
                <input id="minutes" name="minutes" type="number" min="35" max="10080" value="<?= gov_tb_h($minutesAhead) ?>">
                <p class="small">Recommended: 75 minutes. Minimum: 35 minutes so it passes the configured future guard.</p>
                <button class="btn dark" type="submit">Refresh Preview</button>
            </form>

            <hr style="border:0;border-top:1px solid var(--line);margin:18px 0;">

            <form method="post" action="/ops/test-booking.php?minutes=<?= gov_tb_h($minutesAhead) ?>">
                <label for="confirm_text">Type this exact phrase to create the LAB/local row</label>
                <input id="confirm_text" name="confirm_text" type="text" placeholder="CREATE LOCAL DRY RUN BOOKING" autocomplete="off">
                <p class="small">This inserts one row into <code>normalized_bookings</code>. It does not call Bolt or EDXEIX.</p>
                <button class="btn orange" type="submit" <?= $previewOk ? '' : 'disabled' ?>>Create Local Dry-Run Booking</button>
            </form>
        </div>

        <div class="card">
            <h2>Selected Mapping Inputs</h2>
            <?php if (!empty($preview['errors'])): ?>
                <ul>
                    <?php foreach ($preview['errors'] as $item): ?>
                        <li class="badline"><?= gov_tb_h($item) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <div class="grid" style="grid-template-columns:1fr;">
                <div class="metric">
                    <strong><?= gov_tb_h($preview['driver']['external_driver_name'] ?? 'No mapped driver') ?></strong>
                    <span>Driver UUID: <?= gov_tb_h($preview['driver']['external_driver_id'] ?? '-') ?> / EDXEIX: <?= gov_tb_h($preview['driver']['edxeix_driver_id'] ?? '-') ?></span>
                </div>
                <div class="metric">
                    <strong><?= gov_tb_h($preview['vehicle']['plate'] ?? 'No mapped vehicle') ?></strong>
                    <span>Vehicle UUID: <?= gov_tb_h($preview['vehicle']['external_vehicle_id'] ?? '-') ?> / EDXEIX: <?= gov_tb_h($preview['vehicle']['edxeix_vehicle_id'] ?? '-') ?></span>
                </div>
            </div>
        </div>
    </section>

    <?php if ($createdOk): ?>
    <section class="card">
        <h2>Next Verification URLs</h2>
        <p>Use these in this order. The first stage URL should show the LAB row blocked; the allow_lab URL is only for local dry-run verification.</p>
        <div class="actions">
            <a class="btn" href="/bolt_edxeix_preflight.php?limit=30">Preflight JSON</a>
            <a class="btn dark" href="/bolt_stage_edxeix_jobs.php?limit=30">Stage Dry Run — LAB Blocked</a>
            <a class="btn orange" href="/bolt_stage_edxeix_jobs.php?limit=30&allow_lab=1">Stage Dry Run — allow_lab Preview</a>
            <a class="btn red" href="/bolt_stage_edxeix_jobs.php?limit=30&create=1&allow_lab=1">Create Local Staged Job — LAB Only</a>
            <a class="btn dark" href="/bolt_submission_worker.php?limit=30&allow_lab=1">Worker Preview</a>
            <a class="btn orange" href="/bolt_submission_worker.php?limit=30&record=1&allow_lab=1">Record Local Dry-Run Attempt</a>
            <a class="btn green" href="/ops/readiness.php">Readiness</a>
            <a class="btn" href="/ops/jobs.php">Jobs Queue</a>
        </div>
    </section>
    <?php endif; ?>

    <?php if ($result): ?>
    <section class="card">
        <h2>Create Result</h2>
        <pre><?= gov_tb_h(gov_tb_json($result)) ?></pre>
    </section>
    <?php endif; ?>

    <section class="card">
        <h2>Row Preview</h2>
        <pre><?= gov_tb_h(gov_tb_json($preview['row_preview'] ?? [])) ?></pre>
    </section>

    <section class="card">
        <h2>Safety Notes</h2>
        <ul>
            <li>The row uses <code>source_system=lab_local_test</code>.</li>
            <li>The order reference starts with <code>LAB-LOCAL-FUTURE</code>.</li>
            <li>Existing staging blocks LAB rows unless <code>allow_lab=1</code> is explicitly used.</li>
            <li><code>allow_lab=1</code> is only for local dry-run queue and worker validation.</li>
            <li>This patch does not implement live EDXEIX submission.</li>
        </ul>
    </section>
    <?php endif; ?>
</main>
</body>
</html>
