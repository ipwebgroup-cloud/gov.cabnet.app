<?php
/**
 * gov.cabnet.app — Novice Operator Help
 * Read-only help/glossary page. Does not call Bolt, EDXEIX, or the database.
 */

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Robots-Tag: noindex, nofollow', true);

function oh_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function oh_badge(string $text, string $type = 'neutral'): string
{
    return '<span class="badge badge-' . oh_h($type) . '">' . oh_h($text) . '</span>';
}

$terms = [
    'Bolt sync' => 'A safe process that reads ride/order information from Bolt and stores it locally. It does not submit anything to EDXEIX.',
    'Mapping' => 'A connection between a Bolt driver or vehicle and the matching EDXEIX driver or vehicle ID.',
    'EDXEIX ID' => 'The numeric ID used by EDXEIX for a driver, vehicle, lessor, or starting point.',
    'Preflight' => 'A preview check that builds the EDXEIX payload locally so it can be reviewed before any live submission exists.',
    'Future guard' => 'The rule that a ride must start at least the configured number of minutes in the future, usually 30 minutes.',
    'Terminal status' => 'A ride status such as finished, cancelled, expired, failed, or rejected. Terminal rides must never be submitted.',
    'LAB row' => 'A local test booking created only for dry-run testing. LAB rows must never be submitted live.',
    'Submission job' => 'A local queue record used by the dry-run worker. In the current project, it does not cause a live EDXEIX submission.',
    'Dry run' => 'A local test that validates what would happen without sending an HTTP request or form to EDXEIX.',
    'Live submission' => 'A real EDXEIX form/API submission. This is currently disabled and unauthorized.',
];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Operator Help | gov.cabnet.app</title>
    <style>
        :root { --bg:#f3f6fb; --panel:#fff; --ink:#07152f; --muted:#41577a; --line:#d7e1ef; --nav:#081225; --blue:#2563eb; --green:#07875a; --orange:#b85c00; --red:#b42318; --slate:#334155; }
        *{box-sizing:border-box} body{margin:0;background:var(--bg);color:var(--ink);font-family:Arial,Helvetica,sans-serif}.nav{background:var(--nav);color:#fff;min-height:56px;display:flex;align-items:center;gap:18px;padding:0 26px;position:sticky;top:0;z-index:5;overflow:auto}.nav strong{white-space:nowrap}.nav a{color:#fff;text-decoration:none;font-size:15px;white-space:nowrap;opacity:.92}.nav a:hover{opacity:1;text-decoration:underline}.wrap{width:min(1320px,calc(100% - 48px));margin:26px auto 60px}.card{background:var(--panel);border:1px solid var(--line);border-radius:14px;padding:18px;margin-bottom:18px;box-shadow:0 10px 26px rgba(8,18,37,.04)}h1{font-size:34px;margin:0 0 12px}h2{font-size:23px;margin:0 0 14px}h3{margin:16px 0 8px}p,li{color:var(--muted);line-height:1.5}.hero{border-left:7px solid var(--green)}.safety{background:#ecfdf3;border-left:7px solid var(--green)}.warn{background:#fff7ed;border-left:7px solid var(--orange)}.danger{background:#fef3f2;border-left:7px solid var(--red)}.grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px}.steps{counter-reset:steps;list-style:none;padding:0;margin:0}.steps li{counter-increment:steps;margin:10px 0;padding:12px 14px 12px 54px;background:#f8fbff;border:1px solid var(--line);border-radius:10px;position:relative}.steps li:before{content:counter(steps);position:absolute;left:14px;top:12px;width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;background:#eaf1ff;color:#1e40af;font-weight:800}.badge{display:inline-block;padding:5px 9px;border-radius:999px;font-size:12px;font-weight:700;margin:1px 3px 1px 0;white-space:nowrap}.badge-good{background:#dcfce7;color:#166534}.badge-warn{background:#fff7ed;color:#b45309}.badge-bad{background:#fee2e2;color:#991b1b}.badge-neutral{background:#eaf1ff;color:#1e40af}.term{display:grid;grid-template-columns:220px 1fr;gap:12px;border-bottom:1px solid var(--line);padding:11px 0}.term strong{color:var(--ink)}code{background:#eef2ff;padding:2px 5px;border-radius:5px}.actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:12px}.btn{display:inline-block;padding:10px 14px;border-radius:8px;color:#fff;text-decoration:none;font-weight:700;background:var(--blue);font-size:14px}.btn.dark{background:var(--slate)}@media(max-width:850px){.grid{grid-template-columns:1fr}.term{grid-template-columns:1fr}.wrap{width:calc(100% - 24px);margin-top:14px}.nav{padding:0 14px}}
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
    <a href="/ops/help.php">Help</a>
</nav>

<main class="wrap">
    <section class="card hero">
        <h1>Operator Help</h1>
        <p>This page explains the Bolt → EDXEIX bridge in plain language for novice operators.</p>
        <div><?= oh_badge('READ ONLY', 'good') ?> <?= oh_badge('LIVE SUBMIT DISABLED', 'good') ?> <?= oh_badge('OPS GUARDED', 'good') ?></div>
        <div class="actions">
            <a class="btn" href="/ops/index.php">Back to Guided Console</a>
            <a class="btn dark" href="/ops/future-test.php">Open Future Test</a>
        </div>
    </section>

    <section class="card safety">
        <h2>What this system currently does</h2>
        <p>It reads Bolt data, stores normalized local bookings, checks driver/vehicle mappings, builds EDXEIX payload previews, and records dry-run attempts for audit.</p>
        <p><strong>It does not currently submit live EDXEIX forms.</strong></p>
    </section>

    <section class="card danger">
        <h2>Important safety rules</h2>
        <ul>
            <li>Never submit historical, cancelled, terminal, expired, invalid, or past Bolt trips to EDXEIX.</li>
            <li>Never map a driver or vehicle unless the EDXEIX ID is independently confirmed.</li>
            <li>Leave Georgios Zachariou unmapped for now.</li>
            <li>Use Filippos Giannakopoulos with EMX6874 or EHA2545 for the first real future test.</li>
            <li>Live submission requires a separate future patch and explicit approval from Andreas.</li>
        </ul>
    </section>

    <section class="card">
        <h2>A-to-Z real future test procedure</h2>
        <ol class="steps">
            <li>Open <code>/ops/readiness.php</code> and confirm the system is clean.</li>
            <li>Open <code>/ops/future-test.php</code> and confirm it says ready to create a real future test ride.</li>
            <li>Open <code>/ops/mappings.php</code> and confirm Filippos plus a mapped vehicle are available.</li>
            <li>When Filippos is present, create a real Bolt ride 40–60 minutes in the future.</li>
            <li>Run <code>/bolt_sync_orders.php</code> to import the latest Bolt order data.</li>
            <li>Return to <code>/ops/future-test.php</code> and confirm a real future candidate appears.</li>
            <li>Open <code>/bolt_edxeix_preflight.php?limit=30</code> and review the payload.</li>
            <li>Stage a local dry-run job only after preflight looks correct.</li>
            <li>Run the dry-run worker and record a local dry-run attempt.</li>
            <li>Confirm readiness still shows live EDXEIX attempts as zero.</li>
            <li>Stop. Do not attempt live submission from the current tools.</li>
        </ol>
    </section>

    <section class="grid">
        <div class="card">
            <h2>Known good first-test mappings</h2>
            <ul>
                <li><strong>Filippos Giannakopoulos</strong> → EDXEIX driver ID <strong>17585</strong></li>
                <li><strong>EMX6874</strong> → EDXEIX vehicle ID <strong>13799</strong></li>
                <li><strong>EHA2545</strong> → EDXEIX vehicle ID <strong>5949</strong></li>
            </ul>
        </div>
        <div class="card warn">
            <h2>Known reference-only driver IDs</h2>
            <ul>
                <li>1658 — ΒΙΔΑΚΗΣ ΝΙΚΟΛΑΟΣ</li>
                <li>17585 — ΓΙΑΝΝΑΚΟΠΟΥΛΟΣ ΦΙΛΙΠΠΟΣ</li>
                <li>6026 — ΜΑΝΟΥΣΕΛΗΣ ΙΩΣΗΦ</li>
            </ul>
            <p>Reference-only means the ID is visible for operator guidance but does not automatically map a Bolt driver.</p>
        </div>
    </section>

    <section class="card">
        <h2>Glossary</h2>
        <?php foreach ($terms as $term => $definition): ?>
            <div class="term"><strong><?= oh_h($term) ?></strong><span><?= oh_h($definition) ?></span></div>
        <?php endforeach; ?>
    </section>

    <section class="card">
        <h2>Common blockers</h2>
        <div class="term"><strong>driver_not_mapped</strong><span>The Bolt driver has no confirmed EDXEIX driver ID.</span></div>
        <div class="term"><strong>vehicle_not_mapped</strong><span>The Bolt vehicle has no confirmed EDXEIX vehicle ID.</span></div>
        <div class="term"><strong>started_at_not_30_min_future</strong><span>The ride starts too soon or is already in the past.</span></div>
        <div class="term"><strong>terminal_order_status</strong><span>The ride is finished, cancelled, expired, rejected, or failed.</span></div>
        <div class="term"><strong>lab_row_blocked</strong><span>A local test row is being correctly blocked from live behavior.</span></div>
        <div class="term"><strong>never_submit_live</strong><span>The row is marked as dry-run/test only and must never be submitted live.</span></div>
    </section>
</main>
</body>
</html>
