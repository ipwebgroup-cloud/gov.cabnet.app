<?php
/**
 * gov.cabnet.app — EDXEIX Submit Diagnostic v3.2.20
 *
 * Web UI is dry-run/read-only only. It never performs EDXEIX transport.
 */

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Robots-Tag: noindex,nofollow', true);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_shell.php';
require_once '/home/cabnet/gov.cabnet.app_app/lib/edxeix_submit_diagnostic_lib.php';

function edxdiag_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function edxdiag_badge(bool $ok, string $yes = 'YES', string $no = 'NO'): string
{
    return $ok ? opsui_badge($yes, 'good') : opsui_badge($no, 'bad');
}

$bookingId = trim((string)($_GET['booking_id'] ?? ''));
$orderRef = trim((string)($_GET['order_reference'] ?? ''));
$result = null;
$error = null;

try {
    $result = gov_edxdiag_run([
        'booking_id' => $bookingId,
        'order_reference' => $orderRef,
        'transport' => false,
        'follow_redirects' => true,
    ]);
} catch (Throwable $e) {
    $error = $e->getMessage();
}

opsui_shell_begin([
    'title' => 'EDXEIX Submit Diagnostic',
    'page_title' => 'EDXEIX Submit Diagnostic v3.2.20',
    'subtitle' => 'Dry-run submit-readiness and redirect-trace command center. Web mode performs no EDXEIX HTTP transport.',
    'breadcrumbs' => 'Operations / EDXEIX / Submit Diagnostic',
    'active_section' => 'EDXEIX',
    'force_safe_notice' => true,
    'safe_notice' => 'This page is dry-run/read-only. It does not POST to EDXEIX, does not stage jobs, does not mutate queues, and does not print session secrets.',
]);

$analysis = is_array($result['analysis'] ?? null) ? $result['analysis'] : [];
$class = is_array($result['classification'] ?? null) ? $result['classification'] : [];
$session = is_array($result['session_summary'] ?? null) ? $result['session_summary'] : [];
$payloadSummary = is_array($analysis['payload_summary'] ?? null) ? $analysis['payload_summary'] : [];
$liveBlockers = is_array($analysis['live_blockers'] ?? null) ? $analysis['live_blockers'] : [];
$technicalBlockers = is_array($analysis['technical_blockers'] ?? null) ? $analysis['technical_blockers'] : [];
$fields = is_array($payloadSummary['fields'] ?? null) ? $payloadSummary['fields'] : [];
$cliBase = 'php /home/cabnet/gov.cabnet.app_app/cli/edxeix_submit_diagnostic.php --json';
if (($analysis['booking_id'] ?? '') !== '') {
    $cliBase .= ' --booking-id=' . escapeshellarg((string)$analysis['booking_id']);
}
$cliTransport = $cliBase . ' --transport=1 --confirm=' . escapeshellarg('I UNDERSTAND SUBMIT LIVE TO EDXEIX');
?>

<div class="gov-grid gov-grid-4">
    <div class="gov-card"><strong><?php echo edxdiag_h($analysis['booking_id'] ?? ''); ?></strong><span>Booking ID</span></div>
    <div class="gov-card"><strong><?php echo edxdiag_h($analysis['order_reference'] ?? ''); ?></strong><span>Order reference</span></div>
    <div class="gov-card"><strong><?php echo edxdiag_h($class['code'] ?? ''); ?></strong><span>Classification</span></div>
    <div class="gov-card"><strong><?php echo edxdiag_h(!empty($result['transport_performed']) ? 'YES' : 'NO'); ?></strong><span>Transport performed</span></div>
</div>

<?php if ($error !== null): ?>
    <div class="gov-alert gov-alert-danger"><strong>Diagnostic exception:</strong> <?php echo edxdiag_h($error); ?></div>
<?php endif; ?>

<div class="gov-card">
    <h2>Lookup</h2>
    <form method="get" class="gov-form-grid">
        <label>Booking ID<br><input type="text" name="booking_id" value="<?php echo edxdiag_h($bookingId); ?>" placeholder="normalized_bookings.id"></label>
        <label>Order reference<br><input type="text" name="order_reference" value="<?php echo edxdiag_h($orderRef); ?>" placeholder="Bolt/order reference"></label>
        <div><button type="submit" class="gov-button">Analyze dry-run</button></div>
    </form>
</div>

<div class="gov-card">
    <h2>Safety gate summary</h2>
    <table class="gov-table">
        <tbody>
        <tr><th>Real Bolt source</th><td><?php echo edxdiag_badge(!empty($analysis['is_real_bolt'])); ?></td></tr>
        <tr><th>Receipt-only booking</th><td><?php echo edxdiag_badge(empty($analysis['is_receipt_only_booking']), 'NO', 'YES'); ?></td></tr>
        <tr><th>Lab/test booking</th><td><?php echo edxdiag_badge(empty($analysis['is_lab_or_test']), 'NO', 'YES'); ?></td></tr>
        <tr><th>Mapping ready</th><td><?php echo edxdiag_badge(!empty($analysis['mapping_ready'])); ?></td></tr>
        <tr><th>Future guard passed</th><td><?php echo edxdiag_badge(!empty($analysis['future_guard_passed'])); ?></td></tr>
        <tr><th>Terminal status</th><td><?php echo edxdiag_badge(empty($analysis['terminal_status']), 'NO', 'YES'); ?></td></tr>
        <tr><th>Technical payload valid</th><td><?php echo edxdiag_badge(!empty($analysis['technical_payload_valid'])); ?></td></tr>
        <tr><th>Live submission allowed by all gates</th><td><?php echo edxdiag_badge(!empty($analysis['live_submission_allowed'])); ?></td></tr>
        </tbody>
    </table>
</div>

<div class="gov-grid gov-grid-2">
    <div class="gov-card">
        <h2>Session summary</h2>
        <table class="gov-table">
            <tbody>
            <tr><th>Session file exists</th><td><?php echo edxdiag_badge(!empty($session['session_file_exists'])); ?></td></tr>
            <tr><th>Cookie present</th><td><?php echo edxdiag_badge(!empty($session['cookie_present'])); ?></td></tr>
            <tr><th>CSRF present</th><td><?php echo edxdiag_badge(!empty($session['csrf_present'])); ?></td></tr>
            <tr><th>Placeholder detected</th><td><?php echo edxdiag_badge(empty($session['placeholder_detected']), 'NO', 'YES'); ?></td></tr>
            <tr><th>Session ready</th><td><?php echo edxdiag_badge(!empty($session['ready'])); ?></td></tr>
            <tr><th>Updated at</th><td><?php echo edxdiag_h($session['updated_at'] ?? ''); ?></td></tr>
            </tbody>
        </table>
    </div>

    <div class="gov-card">
        <h2>Payload summary</h2>
        <table class="gov-table">
            <tbody>
            <tr><th>Field count</th><td><?php echo edxdiag_h($payloadSummary['field_count'] ?? 0); ?></td></tr>
            <tr><th>Payload hash</th><td><code><?php echo edxdiag_h($payloadSummary['payload_hash'] ?? ''); ?></code></td></tr>
            <tr><th>Fields</th><td><?php echo edxdiag_h(implode(', ', $fields)); ?></td></tr>
            </tbody>
        </table>
    </div>
</div>

<div class="gov-card">
    <h2>Blockers</h2>
    <div class="gov-grid gov-grid-2">
        <div>
            <h3>Live blockers</h3>
            <?php if (!$liveBlockers): ?>
                <p><?php echo opsui_badge('none', 'good'); ?></p>
            <?php else: ?>
                <ul><?php foreach ($liveBlockers as $blocker): ?><li><code><?php echo edxdiag_h($blocker); ?></code></li><?php endforeach; ?></ul>
            <?php endif; ?>
        </div>
        <div>
            <h3>Technical blockers</h3>
            <?php if (!$technicalBlockers): ?>
                <p><?php echo opsui_badge('none', 'good'); ?></p>
            <?php else: ?>
                <ul><?php foreach ($technicalBlockers as $blocker): ?><li><code><?php echo edxdiag_h($blocker); ?></code></li><?php endforeach; ?></ul>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="gov-card">
    <h2>CLI commands</h2>
    <p>Dry-run diagnostic, no EDXEIX transport:</p>
    <pre><code><?php echo edxdiag_h($cliBase); ?></code></pre>
    <p>Supervised one-shot transport trace command. This remains blocked unless server-only live gates are enabled for a real eligible future trip:</p>
    <pre><code><?php echo edxdiag_h($cliTransport); ?></code></pre>
</div>

<div class="gov-card">
    <h2>Next action</h2>
    <p><?php echo edxdiag_h($result['next_action'] ?? 'Continue dry-run/preflight only.'); ?></p>
</div>

<?php opsui_shell_end(); ?>
