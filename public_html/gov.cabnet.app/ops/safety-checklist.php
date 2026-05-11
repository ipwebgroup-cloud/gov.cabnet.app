<?php
/**
 * gov.cabnet.app — Operator safety checklist.
 * Read-only checklist page. No Bolt/EDXEIX/AADE calls and no writes.
 */

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Robots-Tag: noindex, nofollow', true);

require_once __DIR__ . '/_shell.php';

opsui_shell_begin([
    'title' => 'Safety Checklist',
    'page_title' => 'Operator safety checklist',
    'subtitle' => 'Pre-submit checklist for Bolt → EDXEIX staff workflow',
    'breadcrumbs' => 'Αρχική / Help / Safety checklist',
    'active_section' => 'Help',
    'safe_notice' => 'Read-only checklist. It does not call Bolt, EDXEIX, or AADE, does not store answers, and does not change workflow state.',
]);
?>
<section class="card hero warn">
    <h1>Before any EDXEIX save</h1>
    <p>Use this page as a manual operator checklist. It intentionally does not store checklist answers.</p>
    <div>
        <?= opsui_badge('READ ONLY', 'good') ?>
        <?= opsui_badge('MANUAL CHECKLIST', 'warn') ?>
        <?= opsui_badge('NO WORKFLOW ACTIONS', 'good') ?>
    </div>
    <div class="actions">
        <a class="btn good" href="/ops/pre-ride-email-tool.php">Production Pre-Ride Tool</a>
        <a class="btn" href="/ops/workflow-guide.php">Workflow Guide</a>
        <a class="btn dark" href="/ops/firefox-extensions-status.php">Helper Status</a>
    </div>
</section>

<section class="card">
    <h2>Required checks</h2>
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>Check</th><th>Required operator confirmation</th><th>Stop if...</th></tr>
            </thead>
            <tbody>
                <tr><td>Ride timing</td><td>The ride is a real future ride and not too close to the current time.</td><td>It is old, completed, cancelled, terminal, expired, or past.</td></tr>
                <tr><td>IDs ready</td><td>The pre-ride tool shows EDXEIX lessor, driver, vehicle, and starting point IDs.</td><td>Any required ID is missing or warnings show a conflict.</td></tr>
                <tr><td>Lessor/company</td><td>The EDXEIX legal company is based on mapped driver/vehicle data, not only Bolt operator text.</td><td>The company was resolved only from alias fallback.</td></tr>
                <tr><td>Driver</td><td>The visible driver in EDXEIX matches the Bolt pre-ride email and ID lookup.</td><td>Driver name or ID does not match.</td></tr>
                <tr><td>Vehicle</td><td>The visible vehicle plate in EDXEIX matches the Bolt pre-ride email and ID lookup.</td><td>Vehicle plate or ID does not match.</td></tr>
                <tr><td>Passenger</td><td>Passenger name and phone are filled and visible.</td><td>Passenger details are blank or obviously wrong.</td></tr>
                <tr><td>Route</td><td>Pickup and drop-off are populated and match the Bolt email.</td><td>Route is blank, reversed, or mismatched.</td></tr>
                <tr><td>Time</td><td>Start and end date/time are filled and match the pre-ride email.</td><td>Times are blank, old, or mismatched.</td></tr>
                <tr><td>Price</td><td>Price is filled using the parsed first Bolt range value when a range is present.</td><td>Price is blank or not the operator-confirmed amount.</td></tr>
                <tr><td>Map</td><td>The exact pickup point is manually selected on the EDXEIX map.</td><td>The map remains default/unsafe or no exact point was selected.</td></tr>
            </tbody>
        </table>
    </div>
</section>

<section class="two">
    <div class="card">
        <h2>Green light</h2>
        <ul class="list">
            <li>All required fields are visible and match the Bolt email.</li>
            <li>IDs are ready and mapping warnings are clean.</li>
            <li>The ride is future and still valid.</li>
            <li>The exact map point was selected manually.</li>
            <li>The operator has reviewed the final form before saving.</li>
        </ul>
    </div>
    <div class="card">
        <h2>Red light</h2>
        <ul class="list">
            <li>Any required value is missing.</li>
            <li>Any driver/vehicle/company ID is uncertain.</li>
            <li>The ride is no longer future.</li>
            <li>The customer or route differs from the email.</li>
            <li>Anything feels uncertain; stop and ask Andreas/admin before saving.</li>
        </ul>
    </div>
</section>
<?php opsui_shell_end(); ?>
