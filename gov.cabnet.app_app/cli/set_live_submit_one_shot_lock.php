<?php
/**
 * gov.cabnet.app — v5.0 set one-shot live-submit lock.
 *
 * Stores the exact booking id and/or order reference that may be submitted by
 * the guarded live gate. No secrets are written. No network call is made.
 */

declare(strict_types=1);

$path = '/home/cabnet/gov.cabnet.app_config/live_submit.php';
$options = getopt('', ['booking-id::', 'order-reference::', 'by::', 'clear']);
$clear = array_key_exists('clear', $options);
$bookingId = trim((string)($options['booking-id'] ?? ''));
$orderReference = trim((string)($options['order-reference'] ?? ''));
$by = trim((string)($options['by'] ?? 'ops')) ?: 'ops';

if (!$clear && $bookingId === '' && $orderReference === '') {
    fwrite(STDERR, "Usage: php set_live_submit_one_shot_lock.php --booking-id=123 [--order-reference=REF] --by=Andreas\n");
    fwrite(STDERR, "   or: php set_live_submit_one_shot_lock.php --clear --by=Andreas\n");
    exit(2);
}

$config = [];
if (is_file($path) && is_readable($path)) {
    $loaded = require $path;
    if (is_array($loaded)) {
        $config = $loaded;
    }
}

if ($clear) {
    $config['allowed_booking_id'] = null;
    $config['allowed_order_reference'] = null;
    $config['one_shot_lock_cleared_at'] = date(DATE_ATOM);
    $config['one_shot_lock_cleared_by'] = $by;
} else {
    $config['require_one_shot_lock'] = true;
    $config['allowed_booking_id'] = $bookingId !== '' ? $bookingId : null;
    $config['allowed_order_reference'] = $orderReference !== '' ? $orderReference : null;
    $config['one_shot_lock_set_at'] = date(DATE_ATOM);
    $config['one_shot_lock_set_by'] = $by;
}

$tmp = $path . '.tmp.' . getmypid();
$php = "<?php\n/**\n * gov.cabnet.app — server-only live submit config.\n * Do not commit this file. Does not contain secrets.\n */\n\nreturn " . var_export($config, true) . ";\n";
file_put_contents($tmp, $php, LOCK_EX);
chmod($tmp, 0640);
rename($tmp, $path);
@chown($path, 'cabnet');
@chgrp($path, 'cabnet');

echo '[' . date(DATE_ATOM) . "] One-shot live-submit lock " . ($clear ? 'cleared' : 'set') . ".\n";
echo "Config: {$path}\n";
echo "allowed_booking_id=" . (($config['allowed_booking_id'] ?? null) ?: 'NULL') . "\n";
echo "allowed_order_reference=" . (($config['allowed_order_reference'] ?? null) ?: 'NULL') . "\n";
echo "Safety: no secrets written; no Bolt/EDXEIX calls; no jobs/attempts created.\n";
