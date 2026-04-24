<?php
/**
 * Public/CLI wrapper: sync live Bolt drivers and vehicles into mapping tables.
 *
 * Browser:
 *   /bolt_sync_reference.php?dry_run=1
 *   /bolt_sync_reference.php?hours_back=720
 *
 * CLI:
 *   php bolt_sync_reference.php --dry_run=1 --hours_back=720
 */

declare(strict_types=1);

require_once '/home/cabnet/gov.cabnet.app_app/lib/bolt_sync_lib.php';

try {
    $dryRun = gov_bridge_bool_param('dry_run', false);
    $hoursBack = gov_bridge_int_param('hours_back', 720, 1, 8760);
    $result = gov_bolt_sync_reference($hoursBack, $dryRun);
    $result['script'] = 'bolt_sync_reference.php';
    $result['host'] = gov_bridge_load_config()['bolt']['api_base'] ?? null;
    $result['company_id'] = gov_bridge_load_config()['bolt']['company_id'] ?? null;
    gov_bridge_json_response($result);
} catch (Throwable $e) {
    gov_bridge_json_response([
        'ok' => false,
        'script' => 'bolt_sync_reference.php',
        'error' => $e->getMessage(),
    ], 500);
}
