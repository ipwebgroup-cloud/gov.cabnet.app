<?php
/**
 * gov.cabnet.app — Pre-Ride → EDXEIX Candidate Diagnostic v3.2.24
 *
 * Dry-run/read-only by default. Pasted/latest pre-ride email becomes a sanitized candidate preview.
 * No EDXEIX transport, no AADE, no queue jobs, and no normalized booking writes.
 */

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Robots-Tag: noindex,nofollow', true);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_shell.php';
require_once '/home/cabnet/gov.cabnet.app_app/lib/edxeix_pre_ride_candidate_lib.php';

function prc_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function prc_badge(bool $ok, string $yes = 'YES', string $no = 'NO'): string
{
    return $ok ? opsui_badge($yes, 'good') : opsui_badge($no, 'bad');
}

$emailText = (string)($_POST['email_text'] ?? '');
$loadLatest = !empty($_GET['latest_mail']) || !empty($_POST['latest_mail']);
$capture = !empty($_POST['capture']) && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
$result = null;
$error = null;

try {
    if (trim($emailText) !== '' || $loadLatest) {
        $result = gov_prc_run([
            'email_text' => $emailText,
            'latest_mail' => $loadLatest,
            'write' => $capture,
        ]);
    }
} catch (Throwable $e) {
    $error = $e->getMessage();
}

opsui_shell_begin([
    'title' => 'Pre-Ride EDXEIX Candidate',
    'page_title' => 'Pre-Ride → EDXEIX Candidate v3.2.24',
    'subtitle' => 'Parse a Bolt pre-ride email into a future EDXEIX candidate preview. No live submit.',
    'breadcrumbs' => 'Operations / EDXEIX / Pre-Ride Candidate',
    'active_section' => 'EDXEIX',
    'force_safe_notice' => true,
    'safe_notice' => 'This page does not POST to EDXEIX, does not call AADE, does not create queue jobs, and does not change normalized bookings. Optional capture stores parsed metadata only; raw email body is not stored.',
]);

$class = is_array($result['classification'] ?? null) ? $result['classification'] : [];
$candidate = is_array($result['candidate'] ?? null) ? $result['candidate'] : [];
$source = is_array($result['source'] ?? null) ? $result['source'] : [];
$write = is_array($result['write'] ?? null) ? $result['write'] : [];
$mapping = is_array($candidate['mapping'] ?? null) ? $candidate['mapping'] : [];
$payload = is_array($candidate['payload_summary'] ?? null) ? $candidate['payload_summary'] : [];
$payloadFields = is_array($payload['fields'] ?? null) ? $payload['fields'] : [];
$blockers = is_array($candidate['safety_blockers'] ?? null) ? $candidate['safety_blockers'] : [];
$warnings = is_array($candidate['warnings'] ?? null) ? $candidate['warnings'] : [];
$mappingWarnings = is_array($candidate['mapping_warnings'] ?? null) ? $candidate['mapping_warnings'] : [];
$adminExclusion = is_array($candidate['admin_exclusion'] ?? null) ? $candidate['admin_exclusion'] : [];
?>

<div class="gov-card">
    <h2>Paste or load pre-ride email</h2>
    <form method="post">
        <p><a class="gov-button" href="?latest_mail=1">Load latest server Maildir pre-ride email</a></p>
        <label for="email_text"><strong>Bolt pre-ride email text</strong></label>
        <textarea id="email_text" name="email_text" rows="14" style="width:100%;font-family:monospace;"><?php echo prc_h($emailText); ?></textarea>
        <p>
            <label><input type="checkbox" name="capture" value="1"> Capture sanitized metadata to <code>edxeix_pre_ride_candidates</code> if the SQL table exists</label>
        </p>
        <p><button type="submit" class="gov-button">Analyze dry-run</button></p>
    </form>
</div>

<?php if ($error !== null): ?>
    <div class="gov-alert gov-alert-danger"><strong>Diagnostic exception:</strong> <?php echo prc_h($error); ?></div>
<?php endif; ?>

<?php if ($result === null): ?>
    <div class="gov-card">
        <h2>Status</h2>
        <p>No email loaded yet. Paste a pre-ride email or load the latest server Maildir message.</p>
    </div>
<?php else: ?>
    <div class="gov-grid gov-grid-4">
        <div class="gov-card"><strong><?php echo prc_h($class['code'] ?? ''); ?></strong><span>Classification</span></div>
        <div class="gov-card"><strong><?php echo prc_h($candidate['pickup_datetime'] ?? ''); ?></strong><span>Pickup</span></div>
        <div class="gov-card"><strong><?php echo prc_h($candidate['vehicle_plate'] ?? ''); ?></strong><span>Vehicle</span></div>
        <div class="gov-card"><strong><?php echo prc_h(!empty($candidate['ready_for_edxeix']) ? 'YES' : 'NO'); ?></strong><span>Ready</span></div>
    </div>

    <div class="gov-card">
        <h2>Candidate summary</h2>
        <p><?php echo prc_h($class['message'] ?? ''); ?></p>
        <table class="gov-table">
            <tbody>
            <tr><th>Source system</th><td><?php echo prc_h($candidate['source_system'] ?? ''); ?></td></tr>
            <tr><th>Source label</th><td><?php echo prc_h($source['source_label'] ?? ''); ?></td></tr>
            <tr><th>Source hash</th><td><code><?php echo prc_h($source['source_hash_16'] ?? ''); ?></code></td></tr>
            <tr><th>Order reference</th><td><?php echo prc_h($candidate['order_reference'] ?? ''); ?></td></tr>
            <tr><th>Customer</th><td><?php echo prc_h(($candidate['customer_name'] ?? '') . ' / ' . ($candidate['customer_phone'] ?? '')); ?></td></tr>
            <tr><th>Driver</th><td><?php echo prc_h($candidate['driver_name'] ?? ''); ?></td></tr>
            <tr><th>Vehicle</th><td><?php echo prc_h($candidate['vehicle_plate'] ?? ''); ?></td></tr>
            <tr><th>Pickup</th><td><?php echo prc_h($candidate['pickup_address'] ?? ''); ?></td></tr>
            <tr><th>Drop-off</th><td><?php echo prc_h($candidate['dropoff_address'] ?? ''); ?></td></tr>
            <tr><th>Estimated end</th><td><?php echo prc_h($candidate['estimated_end_datetime'] ?? ''); ?></td></tr>
            <tr><th>Price</th><td><?php echo prc_h(($candidate['price_amount'] ?? '') . ' ' . ($candidate['price_currency'] ?? '')); ?></td></tr>
            <tr><th>Future guard</th><td><?php echo prc_h($candidate['effective_future_guard_minutes'] ?? ''); ?> minutes — <?php echo prc_badge(!empty($candidate['future_guard_passed'])); ?></td></tr>
            <tr><th>Admin excluded</th><td><?php echo prc_badge(empty($adminExclusion['excluded']), 'NO', 'YES'); ?> <?php echo prc_h($adminExclusion['reason'] ?? ''); ?></td></tr>
            <tr><th>Write requested</th><td><?php echo prc_badge(!empty($write['requested']), 'YES', 'NO'); ?></td></tr>
            <tr><th>Written</th><td><?php echo prc_badge(!empty($write['written'])); ?> <?php echo prc_h($write['message'] ?? ''); ?></td></tr>
            </tbody>
        </table>
    </div>

    <div class="gov-grid gov-grid-2">
        <div class="gov-card">
            <h2>Mapping readiness</h2>
            <table class="gov-table"><tbody>
                <tr><th>Mapping ready</th><td><?php echo prc_badge(!empty($candidate['mapping_ready'])); ?></td></tr>
                <tr><th>Lessor</th><td><?php echo prc_h($mapping['lessor_id'] ?? ''); ?></td></tr>
                <tr><th>Lessor source</th><td><?php echo prc_h($mapping['lessor_source'] ?? ''); ?></td></tr>
                <tr><th>Driver ID</th><td><?php echo prc_h($mapping['driver_id'] ?? ''); ?></td></tr>
                <tr><th>Vehicle ID</th><td><?php echo prc_h($mapping['vehicle_id'] ?? ''); ?></td></tr>
                <tr><th>Starting point</th><td><?php echo prc_h($mapping['starting_point_id'] ?? ''); ?></td></tr>
            </tbody></table>
        </div>
        <div class="gov-card">
            <h2>Payload preview summary</h2>
            <table class="gov-table"><tbody>
                <tr><th>Field count</th><td><?php echo prc_h($payload['field_count'] ?? 0); ?></td></tr>
                <tr><th>Payload hash</th><td><code><?php echo prc_h($payload['payload_hash'] ?? ''); ?></code></td></tr>
                <tr><th>Fields</th><td><?php echo prc_h(implode(', ', $payloadFields)); ?></td></tr>
            </tbody></table>
        </div>
    </div>

    <div class="gov-card">
        <h2>Blockers and warnings</h2>
        <div class="gov-grid gov-grid-3">
            <div><h3>Safety blockers</h3><?php if (!$blockers): ?><p><?php echo opsui_badge('none', 'good'); ?></p><?php else: ?><ul><?php foreach ($blockers as $b): ?><li><code><?php echo prc_h($b); ?></code></li><?php endforeach; ?></ul><?php endif; ?></div>
            <div><h3>Parser warnings</h3><?php if (!$warnings): ?><p><?php echo opsui_badge('none', 'good'); ?></p><?php else: ?><ul><?php foreach ($warnings as $w): ?><li><?php echo prc_h($w); ?></li><?php endforeach; ?></ul><?php endif; ?></div>
            <div><h3>Mapping warnings</h3><?php if (!$mappingWarnings): ?><p><?php echo opsui_badge('none', 'good'); ?></p><?php else: ?><ul><?php foreach ($mappingWarnings as $w): ?><li><?php echo prc_h($w); ?></li><?php endforeach; ?></ul><?php endif; ?></div>
        </div>
    </div>

    <div class="gov-card">
        <h2>CLI equivalents</h2>
        <p>Dry-run latest Maildir candidate:</p>
        <pre><code>php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_candidate_diagnostic.php --json --latest-mail=1</code></pre>
        <p>Capture sanitized metadata only, after running the additive SQL migration:</p>
        <pre><code>php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_candidate_diagnostic.php --json --latest-mail=1 --write=1</code></pre>
        <p>Include latest pre-ride candidate in the EDXEIX submit diagnostic:</p>
        <pre><code>php /home/cabnet/gov.cabnet.app_app/cli/edxeix_submit_diagnostic.php --json --list-candidates=1 --limit=75 --pre-ride-latest=1</code></pre>
    </div>
<?php endif; ?>

<?php opsui_shell_end(); ?>
