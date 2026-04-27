<?php
declare(strict_types=1);
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Robots-Tag: noindex,nofollow', true);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Admin Control | gov.cabnet.app</title>
    <link rel="stylesheet" href="/assets/css/gov-ops-edxeix.css?v=2.4">
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
        <a href="/ops/route-index.php">Route Index</a>
        <a class="gov-logout" href="/ops/index.php">Safe Ops</a>
    </div>
</div>
<div class="gov-shell">
    <aside class="gov-sidebar">
        <h3>Admin Control</h3>
        <p>EDXEIX-style read-only administration hub.</p>
        <div class="gov-side-group">
            <div class="gov-side-group-title">Administration</div>
            <a class="gov-side-link active" href="/ops/admin-control.php">Admin Control</a>
            <a class="gov-side-link" href="/ops/readiness-control.php">Readiness Control</a>
            <a class="gov-side-link" href="/ops/mapping-control.php">Mapping Review</a>
            <a class="gov-side-link" href="/ops/jobs-control.php">Jobs Review</a>
            <a class="gov-side-link" href="/ops/route-index.php">Route Index</a>
            <div class="gov-side-group-title">Workflow</div>
            <a class="gov-side-link" href="/ops/home.php">Ops Home</a>
            <a class="gov-side-link" href="/ops/test-session.php">Test Session</a>
            <a class="gov-side-link" href="/ops/dev-accelerator.php">Dev Accelerator</a>
            <a class="gov-side-link" href="/ops/preflight-review.php">Preflight Review</a>
            <a class="gov-side-link" href="/ops/evidence-bundle.php">Evidence Bundle</a>
            <a class="gov-side-link" href="/ops/evidence-report.php">Evidence Report</a>
        </div>
        <div class="gov-side-note">Read-only admin companion pages. Original operational pages remain available and unchanged.</div>
    </aside>
    <div class="gov-content">
        <div class="gov-page-header">
            <div>
                <h1 class="gov-page-title">Κέντρο διαχείρισης λειτουργιών</h1>
                <div class="gov-breadcrumbs">Αρχική / Διαχειριστικό / Κέντρο διαχείρισης λειτουργιών</div>
            </div>
            <div class="gov-tabs">
                <a class="gov-tab active" href="/ops/admin-control.php">Καρτέλα</a>
                <a class="gov-tab" href="/ops/route-index.php">Route Index</a>
                <a class="gov-tab" href="/ops/test-session.php">Test Session</a>
                <a class="gov-tab" href="/ops/preflight-review.php">Preflight</a>
            </div>
        </div>
        <main class="wrap wrap-shell">

<section class="safety">
    <strong>READ-ONLY ADMINISTRATION HUB.</strong>
    This page only links to safe operator/admin review pages. It does not call Bolt, does not call EDXEIX, and does not write data.
</section>

<section class="card hero">
    <h1>Operations Administration Hub</h1>
    <p>EDXEIX-style control entry point for readiness, mapping review, local jobs visibility, and route safety documentation.</p>
    <div>
        <span class="badge badge-good">LIVE SUBMIT OFF</span>
        <span class="badge badge-good">READ ONLY</span>
        <span class="badge badge-neutral">COMPANION PAGES</span>
    </div>
    <div class="actions">
        <a class="btn good" href="/ops/home.php">Ops Home</a>
        <a class="btn" href="/ops/route-index.php">Route Index</a>
        <a class="btn dark" href="/ops/index.php">Original Console</a>
    </div>
</section>

<section class="gov-admin-grid">
    <a class="gov-admin-link" href="/ops/readiness-control.php"><strong>Readiness Control</strong><span>Configuration, dry-run, queue, LAB, and safety posture.</span></a>
    <a class="gov-admin-link" href="/ops/mapping-control.php"><strong>Mapping Review</strong><span>Read-only driver and vehicle mapping coverage.</span></a>
    <a class="gov-admin-link" href="/ops/jobs-control.php"><strong>Jobs Review</strong><span>Read-only local jobs and attempts visibility.</span></a>
    <a class="gov-admin-link" href="/ops/route-index.php"><strong>Route Index</strong><span>Safety matrix for operator, evidence, admin, JSON, and guarded action routes.</span></a>
    <a class="gov-admin-link" href="/ops/test-session.php"><strong>Test Session</strong><span>Main real future ride workflow control.</span></a>
    <a class="gov-admin-link" href="/ops/preflight-review.php"><strong>Preflight Review</strong><span>Human-readable preflight decision assistant.</span></a>
</section>
        </main>
    </div>
</div>
</body>
</html>
