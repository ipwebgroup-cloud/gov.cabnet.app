<?php
declare(strict_types=1);

/*
 * gov.cabnet.app — AADE disabled no-op wrapper v6.4.6
 *
 * Safety:
 * - Does NOT call AADE.
 * - Does NOT email receipts.
 * - Does NOT call EDXEIX.
 * - Does NOT create EDXEIX jobs or attempts.
 *
 * Production rule:
 * AADE may only issue after Bolt API confirms order_pickup_timestamp.
 */

header('Content-Type: application/json; charset=utf-8');

echo json_encode([
    'ok' => true,
    'disabled' => true,
    'version' => 'v6.4.6',
    'emergency_lock' => 'AADE_RECEIPT_EMERGENCY_DISABLED',
    'script' => 'auto_bolt_mail_dry_run.php',
    'message' => 'AADE receipt auto-issue is disabled for this worker. No AADE call performed.',
    'strict_rule' => 'AADE may only issue after Bolt API confirms order_pickup_timestamp.',
    'safety' => [
        'does_not_call_aade' => true,
        'does_not_email_receipts' => true,
        'does_not_call_edxeix' => true,
        'does_not_create_submission_jobs' => true,
        'does_not_create_submission_attempts' => true,
        'does_not_print_secrets' => true,
    ],
    'generated_at' => gmdate('c'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
