<?php
/**
 * Safe Bolt smoke test replacement.
 * Works from browser and CLI without assuming $argv exists.
 * Does not print OAuth tokens or secrets.
 */

declare(strict_types=1);

require_once '/home/cabnet/gov.cabnet.app_app/lib/bolt_sync_lib.php';

try {
    $summaryOnly = gov_bridge_bool_param('summary_only', true);
    $ordersHours = gov_bridge_int_param('orders_hours', 24, 1, 2160);
    $referenceHours = gov_bridge_int_param('reference_hours', 720, 1, 8760);
    $config = gov_bridge_load_config();

    $companies = gov_bolt_get_companies();
    $drivers = gov_bolt_get_drivers($referenceHours);
    $vehicles = gov_bolt_get_vehicles($referenceHours);
    $orders = gov_bolt_get_fleet_orders($ordersHours);

    $payload = [
        'ok' => true,
        'script' => 'bolt-api-smoke-test.php',
        'token_endpoint' => $config['bolt']['token_url'],
        'api_base' => $config['bolt']['api_base'],
        'scope' => $config['bolt']['scope'],
        'company_id' => $config['bolt']['company_id'],
        'counts' => [
            'companies' => count($companies),
            'drivers' => count($drivers['items']),
            'vehicles' => count($vehicles['items']),
            'fleet_orders' => count($orders['items']),
        ],
        'pagination' => [
            'drivers' => $drivers['pages'],
            'vehicles' => $vehicles['pages'],
            'fleet_orders' => $orders['pages'],
        ],
    ];

    if (!$summaryOnly) {
        $payload['samples'] = [
            'company' => $companies[0] ?? null,
            'driver' => $drivers['items'][0] ?? null,
            'vehicle' => $vehicles['items'][0] ?? null,
            'order' => $orders['items'][0] ?? null,
        ];
    }

    gov_bridge_json_response($payload);
} catch (Throwable $e) {
    gov_bridge_json_response([
        'ok' => false,
        'script' => 'bolt-api-smoke-test.php',
        'error' => $e->getMessage(),
        'hint' => 'Confirm rotated Bolt credentials exist in /home/cabnet/gov.cabnet.app_config/bolt.php or environment variables.',
    ], 500);
}
