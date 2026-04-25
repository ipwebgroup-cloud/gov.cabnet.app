<?php
/**
 * JSON endpoint: Bolt API Visibility Diagnostic snapshot.
 *
 * This endpoint runs the existing Bolt order sync path in dry-run mode only.
 * It does not submit to EDXEIX and does not stage jobs.
 */

declare(strict_types=1);

require_once '/home/cabnet/gov.cabnet.app_app/lib/bolt_visibility_diagnostic.php';

try {
    $snapshot = gov_bolt_visibility_build_snapshot([
        'hours_back' => gov_bridge_int_param('hours_back', 24, 1, 2160),
        'sample_limit' => gov_bridge_int_param('sample_limit', 20, 1, 50),
        'record' => gov_bridge_bool_param('record', false),
        'label' => (string)gov_bridge_request_param('label', ''),
        'watch_driver_uuid' => (string)gov_bridge_request_param('watch_driver_uuid', ''),
        'watch_vehicle_plate' => (string)gov_bridge_request_param('watch_vehicle_plate', ''),
        'watch_order_id' => (string)gov_bridge_request_param('watch_order_id', ''),
    ]);

    $snapshot['endpoint'] = '/ops/bolt-api-visibility-run.php';
    $snapshot['operator_note'] = 'Use record=1 during an active Bolt ride to create a private sanitized timeline. Live EDXEIX submission remains disabled.';
    gov_bridge_json_response($snapshot);
} catch (Throwable $e) {
    gov_bridge_json_response([
        'ok' => false,
        'endpoint' => '/ops/bolt-api-visibility-run.php',
        'safety' => [
            'edxeix_live_submission' => 'not_used',
            'bolt_sync_mode' => 'dry_run_only',
        ],
        'error' => $e->getMessage(),
    ], 500);
}
