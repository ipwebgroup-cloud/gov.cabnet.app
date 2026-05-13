<?php
/**
 * gov.cabnet.app — V3 starting-point options alias fix patcher.
 *
 * Purpose:
 * - Fix V3 submit dry-run/preflight lookup queries to use the real verified
 *   starting-point options table columns:
 *     edxeix_lessor_id
 *     edxeix_starting_point_id
 * - Preserve display aliases as lessor_id / starting_point_id for existing code.
 *
 * Safety:
 * - No database writes.
 * - No EDXEIX calls.
 * - No AADE calls.
 * - No production submission_jobs/submission_attempts access.
 * - Does not touch public_html/gov.cabnet.app/ops/pre-ride-email-tool.php.
 */

declare(strict_types=1);

const FIX_VERSION = 'v2026-05-13-v3-start-options-alias-fix';

function usage(): string
{
    return "V3 starting-point options alias fix " . FIX_VERSION . "\n\n"
        . "Usage:\n"
        . "  php fix_v3_start_options_aliases.php --dry-run\n"
        . "  php fix_v3_start_options_aliases.php --apply\n\n"
        . "Safety: modifies only V3 CLI submit preflight/dry-run worker PHP files.\n";
}

function has_arg(array $argv, string $name): bool
{
    foreach ($argv as $arg) {
        if ($arg === $name) {
            return true;
        }
    }
    return false;
}

function app_root(): string
{
    return dirname(__DIR__);
}

function backup_dir(): string
{
    return app_root() . '/storage/patch_backups/v3_start_options_aliases_' . date('Ymd_His');
}

/** @return array<string,string> */
function target_files(): array
{
    $root = app_root();
    return [
        $root . '/cli/pre_ride_email_v3_submit_dry_run_worker.php' => 'submit dry-run worker',
        $root . '/cli/pre_ride_email_v3_submit_preflight.php' => 'submit preflight',
    ];
}

/** @return array{content:string,changed:bool,notes:array<int,string>} */
function patch_content(string $content, string $label): array
{
    $changed = false;
    $notes = [];

    $replacements = [
        "'SELECT lessor_id, starting_point_id, label, is_active, source '" => "'SELECT edxeix_lessor_id AS lessor_id, edxeix_starting_point_id AS starting_point_id, label, is_active, source '",
        "'WHERE lessor_id = ? AND is_active = 1 '" => "'WHERE edxeix_lessor_id = ? AND is_active = 1 '",
        "'ORDER BY label ASC, starting_point_id ASC'" => "'ORDER BY label ASC, edxeix_starting_point_id ASC'",
        "const PRV3_SUBMIT_DRY_RUN_VERSION = 'v3.0.19-submit-dry-run-starting-point-guard';" => "const PRV3_SUBMIT_DRY_RUN_VERSION = 'v3.0.33-submit-dry-run-start-options-alias-fix';",
        "const V3_SUBMIT_PREFLIGHT_VERSION = 'v3.0.19-submit-preflight-starting-point-guard';" => "const V3_SUBMIT_PREFLIGHT_VERSION = 'v3.0.33-submit-preflight-start-options-alias-fix';",
    ];

    foreach ($replacements as $old => $new) {
        if (str_contains($content, $old)) {
            $content = str_replace($old, $new, $content);
            $changed = true;
            $notes[] = 'replaced: ' . trim($old, "'");
        } elseif (str_contains($content, $new)) {
            $notes[] = 'already fixed: ' . trim($new, "'");
        } else {
            if (str_contains($old, 'const ') && !str_contains($content, explode(' = ', $old)[0] ?? $old)) {
                $notes[] = 'version constant not found for ' . $label;
            } elseif (!str_contains($old, 'const ')) {
                $notes[] = 'query fragment not found: ' . trim($old, "'");
            }
        }
    }

    return ['content' => $content, 'changed' => $changed, 'notes' => $notes];
}

$help = has_arg($argv, '--help') || has_arg($argv, '-h');
$dryRun = has_arg($argv, '--dry-run');
$apply = has_arg($argv, '--apply');

if ($help || (!$dryRun && !$apply)) {
    echo usage();
    exit($help ? 0 : 1);
}
if ($dryRun && $apply) {
    fwrite(STDERR, "Use either --dry-run or --apply, not both.\n");
    exit(1);
}

$mode = $apply ? 'APPLY' : 'DRY_RUN';
echo "V3 starting-point options alias fix " . FIX_VERSION . "\n";
echo "Mode: {$mode}\n";
echo "App path: " . app_root() . "\n";
echo "Safety: no DB writes, no EDXEIX call, no AADE call, production pre-ride tool untouched.\n";

$backupDir = $apply ? backup_dir() : '';
if ($apply && !is_dir($backupDir) && !mkdir($backupDir, 0750, true) && !is_dir($backupDir)) {
    fwrite(STDERR, "Unable to create backup directory: {$backupDir}\n");
    exit(1);
}
if ($apply) {
    echo "Backup path: {$backupDir}\n";
}

$overallChanged = false;
$errors = 0;
foreach (target_files() as $file => $label) {
    if (!is_file($file) || !is_readable($file)) {
        echo "ERROR | missing/unreadable | {$file}\n";
        $errors++;
        continue;
    }
    $original = file_get_contents($file);
    if (!is_string($original)) {
        echo "ERROR | could not read | {$file}\n";
        $errors++;
        continue;
    }
    $patched = patch_content($original, $label);
    $changed = (bool)$patched['changed'];
    $overallChanged = $overallChanged || $changed;
    $state = $changed ? 'changed' : 'unchanged';
    echo "OK | {$state} | {$file} | " . implode('; ', $patched['notes']) . "\n";

    if ($apply && $changed) {
        $backupFile = $backupDir . '/' . basename($file) . '.bak';
        if (file_put_contents($backupFile, $original, LOCK_EX) === false) {
            echo "ERROR | could not backup | {$file}\n";
            $errors++;
            continue;
        }
        if (file_put_contents($file, $patched['content'], LOCK_EX) === false) {
            echo "ERROR | could not write | {$file}\n";
            $errors++;
            continue;
        }
    }
}

if ($errors > 0) {
    echo "Completed with {$errors} error(s).\n";
    exit(1);
}

if ($dryRun) {
    echo $overallChanged ? "Dry-run completed. Re-run with --apply to write changes.\n" : "Dry-run completed. No changes needed.\n";
} else {
    echo $overallChanged ? "Patch applied. Run php -l and submit dry-run worker checks now.\n" : "No changes needed.\n";
}

exit(0);
