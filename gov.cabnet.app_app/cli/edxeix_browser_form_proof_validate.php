#!/usr/bin/env php
<?php
/**
 * gov.cabnet.app — CLI validate pasted browser create-form proof v3.2.34.
 * No EDXEIX request, no POST, no DB write.
 */

declare(strict_types=1);

require_once '/home/cabnet/gov.cabnet.app_app/lib/edxeix_browser_form_proof_lib.php';

$args = array_slice($argv, 1);
$jsonOut = in_array('--json', $args, true);
$file = '';
foreach ($args as $arg) {
    if (str_starts_with($arg, '--file=')) { $file = substr($arg, 7); }
}

try {
    if ($file !== '') {
        if (!is_file($file) || !is_readable($file)) {
            throw new RuntimeException('Cannot read proof file: ' . $file);
        }
        $input = (string)file_get_contents($file);
    } else {
        $input = '';
        while (!feof(STDIN)) { $input .= (string)fgets(STDIN); }
    }
    $result = gov_bfp_validate_json($input);
    if ($jsonOut) {
        echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    } else {
        echo 'Classification: ' . ($result['classification']['code'] ?? 'UNKNOWN') . PHP_EOL;
        echo ($result['classification']['message'] ?? '') . PHP_EOL;
        if (!empty($result['blockers'])) {
            echo 'Blockers:' . PHP_EOL;
            foreach ($result['blockers'] as $b) { echo '- ' . $b . PHP_EOL; }
        }
        if (!empty($result['warnings'])) {
            echo 'Warnings:' . PHP_EOL;
            foreach ($result['warnings'] as $w) { echo '- ' . $w . PHP_EOL; }
        }
    }
    exit(0);
} catch (Throwable $e) {
    $error = [
        'ok' => false,
        'version' => 'v3.2.34-browser-create-form-proof-validator',
        'classification' => [
            'code' => 'BROWSER_CREATE_FORM_PROOF_VALIDATE_ERROR',
            'message' => $e->getMessage(),
        ],
        'safety' => [
            'edxeix_http_request_from_server' => false,
            'edxeix_post' => false,
            'raw_cookie_printed' => false,
            'raw_csrf_printed' => false,
            'raw_token_printed_or_stored' => false,
        ],
    ];
    if ($jsonOut) { echo json_encode($error, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL; }
    else { fwrite(STDERR, 'ERROR: ' . $e->getMessage() . PHP_EOL); }
    exit(1);
}
