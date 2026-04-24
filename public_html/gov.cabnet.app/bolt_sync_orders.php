<?php
/**
 * Public/CLI wrapper: import recent Bolt fleet orders into raw payloads and normalized bookings.
 * This file does NOT submit to EDXEIX.
 *
 * Browser:
 *   /bolt_sync_orders.php?dry_run=1&hours_back=24
 *
 * CLI:
 *   php bolt_sync_orders.php --dry_run=1 --hours_back=24
 */

declare(strict_types=1);

require_once '/home/cabnet/gov.cabnet.app_app/lib/bolt_sync_lib.php';

try {
    $dryRun = gov_bridge_bool_param('dry_run', false);
    $hoursBack = gov_bridge_int_param('hours_back', 24, 1, 2160);
    $result = gov_bolt_sync_orders($hoursBack, $dryRun);
    $result['script'] = 'bolt_sync_orders.php';
    $result['host'] = gov_bridge_load_config()['bolt']['api_base'] ?? null;
    $result['company_id'] = gov_bridge_load_config()['bolt']['company_id'] ?? null;
    $result['note'] = 'Orders are imported and normalized only. No EDXEIX submission is performed by this script.';
    gov_bridge_json_response($result);
} catch (Throwable $e) {
    gov_bridge_json_response([
        'ok' => false,
        'script' => 'bolt_sync_orders.php',
        'error' => $e->getMessage(),
    ], 500);
}
