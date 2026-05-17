#!/usr/bin/env php
<?php
/**
 * gov.cabnet.app — CLI EDXEIX create-form token diagnostic v3.2.33.
 * Read-only GET diagnostic. No EDXEIX POST, no AADE, no queue, no config write.
 */

declare(strict_types=1);

require_once '/home/cabnet/gov.cabnet.app_app/lib/edxeix_pre_ride_one_shot_transport_trace_lib.php';

$json = in_array('--json', array_slice($argv, 1), true);

try {
    $result = [
        'ok' => true,
        'version' => 'v3.2.33-edxeix-create-form-token-diagnostic',
        'started_at' => date('Y-m-d H:i:s'),
        'safety' => [
            'edxeix_post' => false,
            'aade_call' => false,
            'queue_job' => false,
            'normalized_booking_write' => false,
            'live_config_write' => false,
            'raw_cookie_printed' => false,
            'raw_csrf_printed' => false,
            'raw_body_printed' => false,
        ],
        'diagnostic' => gov_prtx_form_token_diagnostic(),
    ];
    $diag = is_array($result['diagnostic'] ?? null) ? $result['diagnostic'] : [];
    $result['classification'] = [
        'code' => !empty($diag['ok']) ? 'EDXEIX_CREATE_FORM_TOKEN_READY' : 'EDXEIX_CREATE_FORM_TOKEN_NOT_READY',
        'message' => !empty($diag['ok'])
            ? 'EDXEIX create form was fetched and a hidden form token was detected. No POST was performed.'
            : 'EDXEIX create form token diagnostic is not ready. Review warnings/status/redirects. No POST was performed.',
    ];

    if ($json) {
        echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        exit(0);
    }

    echo 'Classification: ' . $result['classification']['code'] . PHP_EOL;
    echo $result['classification']['message'] . PHP_EOL;
    echo 'Create URL: ' . (string)($diag['create_url'] ?? '') . PHP_EOL;
    echo 'Final URL: ' . (string)($diag['final_url'] ?? '') . PHP_EOL;
    echo 'HTTP status: ' . (string)($diag['status'] ?? '') . PHP_EOL;
    echo 'Token present: ' . (!empty($diag['token_present']) ? 'YES' : 'NO') . PHP_EOL;
    echo 'Form present: ' . (!empty($diag['form_summary']['form_present']) ? 'YES' : 'NO') . PHP_EOL;
    if (!empty($diag['warnings'])) {
        echo 'Warnings:' . PHP_EOL;
        foreach ($diag['warnings'] as $warning) { echo '- ' . $warning . PHP_EOL; }
    }
    exit(0);
} catch (Throwable $e) {
    $error = [
        'ok' => false,
        'version' => 'v3.2.33-edxeix-create-form-token-diagnostic',
        'classification' => [
            'code' => 'EDXEIX_CREATE_FORM_TOKEN_DIAGNOSTIC_ERROR',
            'message' => $e->getMessage(),
        ],
        'safety' => [
            'edxeix_post' => false,
            'raw_cookie_printed' => false,
            'raw_csrf_printed' => false,
            'raw_body_printed' => false,
        ],
    ];
    if ($json) {
        echo json_encode($error, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    } else {
        fwrite(STDERR, 'ERROR: ' . $e->getMessage() . PHP_EOL);
    }
    exit(1);
}
