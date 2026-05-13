<?php
declare(strict_types=1);

/**
 * Idempotent patcher for EMT8640 operational vehicle exemption.
 *
 * Usage:
 *   php apply_emt8640_vehicle_exemption_patch.php --dry-run
 *   php apply_emt8640_vehicle_exemption_patch.php --apply
 *   php apply_emt8640_vehicle_exemption_patch.php --apply --root=/path/to/repo-or-/home/cabnet
 */

const PATCH_VERSION = 'v2026-05-13-emt8640-vehicle-exemption';

function opt_value(array $argv, string $name, ?string $default = null): ?string
{
    foreach ($argv as $arg) {
        if (str_starts_with($arg, $name . '=')) {
            return substr($arg, strlen($name) + 1);
        }
    }
    return $default;
}

function has_opt(array $argv, string $name): bool
{
    foreach ($argv as $arg) {
        if ($arg === $name || str_starts_with($arg, $name . '=')) {
            return true;
        }
    }
    return false;
}

function line(string $message): void
{
    echo $message . PHP_EOL;
}

function fail(string $message): never
{
    fwrite(STDERR, 'ERROR: ' . $message . PHP_EOL);
    exit(1);
}

function normalize_path(string $path): string
{
    return rtrim(str_replace('\\', '/', $path), '/');
}

/** @return array{root:string,app:string,public:string,backup:string} */
function resolve_paths(array $argv): array
{
    $root = opt_value($argv, '--root');
    if (!is_string($root) || trim($root) === '') {
        $root = dirname(__DIR__, 2);
    }
    $root = normalize_path($root);

    $app = is_dir($root . '/gov.cabnet.app_app') ? $root . '/gov.cabnet.app_app' : $root;
    if (basename($app) !== 'gov.cabnet.app_app' && is_dir(dirname($app) . '/gov.cabnet.app_app')) {
        $app = dirname($app) . '/gov.cabnet.app_app';
    }
    if (basename($app) !== 'gov.cabnet.app_app') {
        fail('Could not resolve gov.cabnet.app_app from root: ' . $root);
    }

    $public = dirname($app) . '/public_html/gov.cabnet.app';
    $backup = $app . '/storage/patch_backups/emt8640_' . date('Ymd_His');

    return ['root' => dirname($app), 'app' => $app, 'public' => $public, 'backup' => $backup];
}

function backup_file(string $file, string $backupDir): void
{
    if (!is_dir($backupDir) && !mkdir($backupDir, 0750, true) && !is_dir($backupDir)) {
        fail('Could not create backup directory: ' . $backupDir);
    }
    $target = $backupDir . '/' . basename($file) . '.bak';
    if (!copy($file, $target)) {
        fail('Could not back up ' . $file . ' to ' . $target);
    }
}

/**
 * @param array<int,array{marker:string,search:string,replace:string}> $ops
 * @return array<string,mixed>
 */
function patch_file(string $file, array $ops, bool $apply, string $backupDir): array
{
    if (!is_file($file)) {
        return ['file' => $file, 'ok' => false, 'changed' => false, 'message' => 'file not found'];
    }
    $original = file_get_contents($file);
    if (!is_string($original)) {
        return ['file' => $file, 'ok' => false, 'changed' => false, 'message' => 'could not read file'];
    }

    $content = $original;
    $messages = [];
    foreach ($ops as $op) {
        if (str_contains($content, $op['marker'])) {
            $messages[] = 'already has ' . $op['marker'];
            continue;
        }
        if (!str_contains($content, $op['search'])) {
            return ['file' => $file, 'ok' => false, 'changed' => false, 'message' => 'search pattern not found for ' . $op['marker']];
        }
        $content = str_replace($op['search'], $op['replace'], $content, $count);
        if ($count < 1) {
            return ['file' => $file, 'ok' => false, 'changed' => false, 'message' => 'replace failed for ' . $op['marker']];
        }
        $messages[] = 'will add ' . $op['marker'];
    }

    $changed = $content !== $original;
    if ($changed && $apply) {
        backup_file($file, $backupDir);
        if (file_put_contents($file, $content, LOCK_EX) === false) {
            return ['file' => $file, 'ok' => false, 'changed' => false, 'message' => 'could not write patched file'];
        }
    }

    return ['file' => $file, 'ok' => true, 'changed' => $changed, 'message' => implode('; ', $messages) ?: 'no changes needed'];
}

$apply = has_opt($argv, '--apply');
$dryRun = has_opt($argv, '--dry-run') || !$apply;
if (has_opt($argv, '--help') || has_opt($argv, '-h')) {
    line('Usage: php apply_emt8640_vehicle_exemption_patch.php --dry-run|--apply [--root=/home/cabnet-or-repo-root]');
    exit(0);
}

$paths = resolve_paths($argv);
$app = $paths['app'];
$backupDir = $paths['backup'];

line('EMT8640 vehicle exemption patch ' . PATCH_VERSION);
line('Mode: ' . ($apply ? 'APPLY' : 'DRY RUN'));
line('App path: ' . $app);
if ($apply) {
    line('Backup path: ' . $backupDir);
}

$classFile = $app . '/src/Domain/VehicleExemptionService.php';
if (!is_file($classFile)) {
    fail('VehicleExemptionService.php is missing. Upload it before running this patcher: ' . $classFile);
}

$targets = [];

$aadeFile = $app . '/src/Receipts/AadeReceiptAutoIssuer.php';
$targets[] = patch_file($aadeFile, [
    [
        'marker' => 'VEHICLE_EXEMPTION_EMT8640_USE_GUARD',
        'search' => "use Bridge\\Database;\n",
        'replace' => "use Bridge\\Database;\nuse Bridge\\Domain\\VehicleExemptionService; // VEHICLE_EXEMPTION_EMT8640_USE_GUARD\n",
    ],
    [
        'marker' => 'VEHICLE_EXEMPTION_EMT8640_AADE_GATE',
        'search' => "        if (!is_array(\$booking)) {\n            \$blockers[] = 'booking_not_found';\n            return ['enabled' => \$this->isAutoEnabled(), 'blockers' => \$blockers];\n        }\n\n",
        'replace' => "        if (!is_array(\$booking)) {\n            \$blockers[] = 'booking_not_found';\n            return ['enabled' => \$this->isAutoEnabled(), 'blockers' => \$blockers];\n        }\n\n        // VEHICLE_EXEMPTION_EMT8640_AADE_GATE\n        // EMT8640 must never issue AADE/myDATA invoices/receipts or trigger driver receipt email.\n        if (VehicleExemptionService::isExemptRow(\$booking)) {\n            \$blockers[] = VehicleExemptionService::reasonCode();\n        }\n\n",
    ],
], $apply, $backupDir);

$mailFile = $app . '/src/Mail/BoltMailDriverNotificationService.php';
$targets[] = patch_file($mailFile, [
    [
        'marker' => 'VEHICLE_EXEMPTION_EMT8640_MAIL_USE_GUARD',
        'search' => "use Bridge\\Database;\n",
        'replace' => "use Bridge\\Database;\nuse Bridge\\Domain\\VehicleExemptionService; // VEHICLE_EXEMPTION_EMT8640_MAIL_USE_GUARD\n",
    ],
    [
        'marker' => 'VEHICLE_EXEMPTION_EMT8640_DRIVER_NOTIFICATION_GATE',
        'search' => "        \$subject = \$this->buildSubject(\$row);\n\n        if (\$this->looksLikeSyntheticOrTest(\$row)) {\n",
        'replace' => "        \$subject = \$this->buildSubject(\$row);\n\n        // VEHICLE_EXEMPTION_EMT8640_DRIVER_NOTIFICATION_GATE\n        // EMT8640 must not receive the driver pre-ride email, voucher, or receipt-copy email.\n        if (VehicleExemptionService::isExemptRow(\$row) || VehicleExemptionService::isExemptPlate(\$vehiclePlate)) {\n            return \$this->recordSkipped(\$intakeId, \$messageHash, \$driverName, \$vehiclePlate, null, \$subject, VehicleExemptionService::reasonCode());\n        }\n\n        if (\$this->looksLikeSyntheticOrTest(\$row)) {\n",
    ],
    [
        'marker' => 'VEHICLE_EXEMPTION_EMT8640_AADE_RECEIPT_EMAIL_GATE',
        'search' => "        if (\$this->looksLikeSyntheticOrTest(\$row)) {\n            return ['status' => 'skipped', 'recipient' => null, 'reason' => 'test_or_synthetic_email_suppressed', 'error' => null];\n        }\n\n        \$existing = \$this->db->fetchOne(\n",
        'replace' => "        if (\$this->looksLikeSyntheticOrTest(\$row)) {\n            return ['status' => 'skipped', 'recipient' => null, 'reason' => 'test_or_synthetic_email_suppressed', 'error' => null];\n        }\n\n        // VEHICLE_EXEMPTION_EMT8640_AADE_RECEIPT_EMAIL_GATE\n        // Defense-in-depth: even if AADE issued elsewhere, do not email an official receipt for EMT8640.\n        if (VehicleExemptionService::isExemptRow(\$row)) {\n            return ['status' => 'skipped', 'recipient' => null, 'reason' => VehicleExemptionService::reasonCode(), 'error' => null];\n        }\n\n        \$existing = \$this->db->fetchOne(\n",
    ],
], $apply, $backupDir);

$submissionFile = $app . '/src/Domain/SubmissionService.php';
$targets[] = patch_file($submissionFile, [
    [
        'marker' => 'VEHICLE_EXEMPTION_EMT8640_EDXEIX_SUBMISSION_GATE',
        'search' => "        if (!\$booking) {\n            \$this->jobs->markFailed((int) \$job['id'], 'failed_validation', 'Booking not found.');\n            return ['job_id' => (int) \$job['id'], 'success' => false, 'error' => 'Booking not found'];\n        }\n\n",
        'replace' => "        if (!\$booking) {\n            \$this->jobs->markFailed((int) \$job['id'], 'failed_validation', 'Booking not found.');\n            return ['job_id' => (int) \$job['id'], 'success' => false, 'error' => 'Booking not found'];\n        }\n\n        // VEHICLE_EXEMPTION_EMT8640_EDXEIX_SUBMISSION_GATE\n        // EMT8640 must never be submitted to EDXEIX by production workers.\n        if (VehicleExemptionService::isExemptRow(\$booking)) {\n            \$reason = VehicleExemptionService::reasonCode();\n            \$this->jobs->markFailed((int) \$job['id'], 'vehicle_exempt', \$reason);\n            return ['job_id' => (int) \$job['id'], 'success' => false, 'error' => \$reason];\n        }\n\n",
    ],
], $apply, $backupDir);

$v3IntakeFile = $app . '/cli/pre_ride_email_v3_intake.php';
$targets[] = patch_file($v3IntakeFile, [
    [
        'marker' => 'VEHICLE_EXEMPTION_EMT8640_V3_REQUIRE_FILE',
        'search' => "\$mailLoaderFile = \$appRoot . '/src/BoltMailV3/MaildirPreRideEmailLoaderV3.php';\n\nforeach ([\$bootstrapFile, \$parserFile, \$lookupFile, \$mailLoaderFile] as \$file) {\n",
        'replace' => "\$mailLoaderFile = \$appRoot . '/src/BoltMailV3/MaildirPreRideEmailLoaderV3.php';\n\$vehicleExemptionFile = \$appRoot . '/src/Domain/VehicleExemptionService.php'; // VEHICLE_EXEMPTION_EMT8640_V3_REQUIRE_FILE\n\nforeach ([\$bootstrapFile, \$parserFile, \$lookupFile, \$mailLoaderFile, \$vehicleExemptionFile] as \$file) {\n",
    ],
    [
        'marker' => 'VEHICLE_EXEMPTION_EMT8640_V3_USE_GUARD',
        'search' => "use Bridge\\BoltMailV3\\MaildirPreRideEmailLoaderV3;\n\n",
        'replace' => "use Bridge\\BoltMailV3\\MaildirPreRideEmailLoaderV3;\nuse Bridge\\Domain\\VehicleExemptionService; // VEHICLE_EXEMPTION_EMT8640_V3_USE_GUARD\n\n",
    ],
    [
        'marker' => 'VEHICLE_EXEMPTION_EMT8640_V3_INTAKE_GATE',
        'search' => "    \$fields = is_array(\$parsed) ? (array)(\$parsed['fields'] ?? []) : [];\n    \$missing = is_array(\$parsed) ? (array)(\$parsed['missing_required'] ?? []) : ['parse_failed'];\n    \$parserOk = is_array(\$parsed) && empty(\$missing);\n\n    \$mapping = pe3_cli_lookup(\$db, \$fields);\n",
        'replace' => "    \$fields = is_array(\$parsed) ? (array)(\$parsed['fields'] ?? []) : [];\n    \$missing = is_array(\$parsed) ? (array)(\$parsed['missing_required'] ?? []) : ['parse_failed'];\n    \$parserOk = is_array(\$parsed) && empty(\$missing);\n\n    // VEHICLE_EXEMPTION_EMT8640_V3_INTAKE_GATE\n    // EMT8640 is not allowed into the isolated V3 automation queue.\n    if (VehicleExemptionService::isExemptRow(\$fields)) {\n        \$dedupeKey = pe3_cli_dedupe_key(\$fields, [], \$candidate);\n        return [\n            'index' => \$index,\n            'candidate_number' => \$index + 1,\n            'source_mailbox' => (string)(\$candidate['source'] ?? ''),\n            'source_mtime' => (string)(\$candidate['source_mtime'] ?? ''),\n            'source_hash' => hash('sha256', (string)(\$candidate['source'] ?? '') . '|' . (string)(\$candidate['source_mtime'] ?? '')),\n            'email_hash' => hash('sha256', \$emailText),\n            'dedupe_key' => \$dedupeKey,\n            'ready' => false,\n            'queue_status' => 'blocked',\n            'parser_ok' => \$parserOk,\n            'mapping_ok' => false,\n            'future_ok' => false,\n            'future' => ['ok' => false, 'message' => VehicleExemptionService::reasonText(), 'minutes_until' => null, 'start_iso' => ''],\n            'block_reasons' => [VehicleExemptionService::reasonCode()],\n            'parsed' => \$parsed,\n            'fields' => \$fields,\n            'mapping' => [],\n            'payload' => [],\n            'raw_email_preview' => pe3_cli_email_preview(\$emailText),\n            'summary' => [\n                'customer' => (string)(\$fields['customer_name'] ?? ''),\n                'driver' => (string)(\$fields['driver_name'] ?? ''),\n                'vehicle' => (string)(\$fields['vehicle_plate'] ?? ''),\n                'pickup_datetime' => (string)(\$fields['pickup_datetime_local'] ?? ''),\n                'minutes_until' => null,\n                'lessor_id' => '',\n                'driver_id' => '',\n                'vehicle_id' => '',\n                'starting_point_id' => '',\n            ],\n        ];\n    }\n\n    \$mapping = pe3_cli_lookup(\$db, \$fields);\n",
    ],
], $apply, $backupDir);

$ok = true;
foreach ($targets as $target) {
    $status = $target['ok'] ? 'OK' : 'FAIL';
    $changed = !empty($target['changed']) ? 'changed' : 'unchanged';
    line($status . ' | ' . $changed . ' | ' . $target['file'] . ' | ' . $target['message']);
    if (!$target['ok']) {
        $ok = false;
    }
}

if (!$ok) {
    fail('One or more patch operations failed. No partial writes occur in dry-run; in apply mode, inspect backups if any file had already changed.');
}

line($apply ? 'Patch applied.' : 'Dry-run completed. Re-run with --apply to write changes.');
line('Next: run php -l on patched files and verify EMT8640 is blocked from notification, invoice, V3 queue, and EDXEIX worker paths.');
