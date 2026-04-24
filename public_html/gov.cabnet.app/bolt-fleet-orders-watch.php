<?php
/**
 * Bolt fleet orders watcher.
 * Detects new orders and status changes, storing only watcher state in /tmp.
 * Does not submit anything to EDXEIX.
 */

declare(strict_types=1);

require_once '/home/cabnet/gov.cabnet.app_app/lib/bolt_sync_lib.php';

try {
    $hoursBack = gov_bridge_int_param('hours_back', 24, 1, 2160);
    $stateFile = '/tmp/gov_cabnet_bolt_fleet_orders_state.json';
    $previous = [];
    if (is_file($stateFile)) {
        $decoded = json_decode((string)file_get_contents($stateFile), true);
        if (is_array($decoded)) {
            $previous = $decoded;
        }
    }

    $ordersResult = gov_bolt_get_fleet_orders($hoursBack);
    $current = [];
    $events = [];

    foreach ($ordersResult['items'] as $order) {
        $reference = (string)gov_bolt_pick($order, ['order_reference', 'reference', 'order_id', 'id'], '');
        if ($reference === '') {
            continue;
        }
        $status = (string)gov_bolt_pick($order, ['order_status', 'status'], 'unknown');
        $current[$reference] = [
            'status' => $status,
            'driver_uuid' => (string)gov_bolt_pick($order, ['driver_uuid', 'driver_id'], ''),
            'driver_name' => (string)gov_bolt_pick($order, ['driver_name'], ''),
            'vehicle_uuid' => (string)gov_bolt_pick($order, ['vehicle_uuid', 'vehicle_id'], ''),
            'vehicle_license_plate' => (string)gov_bolt_pick($order, ['vehicle_license_plate', 'reg_number', 'plate'], ''),
            'pickup_address' => (string)gov_bolt_pick($order, ['pickup_address'], ''),
            'destination_address' => (string)gov_bolt_pick($order, ['destination_address'], ''),
            'last_seen_at' => date('c'),
        ];

        if (!isset($previous[$reference])) {
            $events[] = ['type' => 'new_order', 'order_reference' => $reference, 'status' => $status];
        } elseif (($previous[$reference]['status'] ?? null) !== $status) {
            $events[] = [
                'type' => 'status_change',
                'order_reference' => $reference,
                'from' => $previous[$reference]['status'] ?? null,
                'to' => $status,
            ];
        }
    }

    file_put_contents($stateFile, gov_bridge_json_encode_db($current), LOCK_EX);

    gov_bridge_json_response([
        'ok' => true,
        'script' => 'bolt-fleet-orders-watch.php',
        'hours_back' => $hoursBack,
        'state_file' => $stateFile,
        'orders_seen' => count($current),
        'events' => $events,
        'note' => 'Watcher only detects changes. It does not write normalized_bookings and does not submit to EDXEIX.',
    ]);
} catch (Throwable $e) {
    gov_bridge_json_response([
        'ok' => false,
        'script' => 'bolt-fleet-orders-watch.php',
        'error' => $e->getMessage(),
    ], 500);
}
