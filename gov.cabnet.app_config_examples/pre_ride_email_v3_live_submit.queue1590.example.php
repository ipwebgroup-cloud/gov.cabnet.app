<?php
/**
 * Example only. Do not commit real server-only config.
 * Copy manually to:
 *   /home/cabnet/gov.cabnet.app_config/pre_ride_email_v3_live_submit.php
 * only when supervising the queue_id 1590 one-shot live test.
 */
return [
    'enabled' => true,
    'mode' => 'live',
    'adapter' => 'legacy_live_http',
    'hard_enable_live_submit' => true,
    'acknowledgement' => 'I EXPLICITLY APPROVE V3 LIVE EDXEIX SUBMIT',
    'required_acknowledgement' => 'I EXPLICITLY APPROVE V3 LIVE EDXEIX SUBMIT',
    'allowed_queue_id' => 1590,
    'expected_preview_sha256' => '109473d72b6799287e3ef5fadf155238532516f47ef6817362beb48ff56de022',
    'min_future_minutes' => 5,
    'auto_disarm_after_attempt' => true,
    'legacy_live_config_path' => '/home/cabnet/gov.cabnet.app_config/live_submit.php',
    'note' => 'Example only. Live submit must also require /home/cabnet/gov.cabnet.app_config/live_submit.php to be armed with a live submit URL and valid EDXEIX session file.',
];
