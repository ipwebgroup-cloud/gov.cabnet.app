<?php
/**
 * gov.cabnet.app — V3 live-readiness starting-point option alias fix
 *
 * V3-only one-time maintenance patch.
 * Fixes pre_ride_email_v3_live_submit_readiness.php so it queries
 * pre_ride_email_v3_starting_point_options with the real column names:
 *   edxeix_lessor_id
 *   edxeix_starting_point_id
 * instead of the old aliases:
 *   lessor_id
 *   starting_point_id
 *
 * Safety:
 * - Default mode is dry-run/check only.
 * - Use --apply to write the patch.
 * - Backs up the target file before writing.
 * - Does not call Bolt, EDXEIX, AADE, or the database.
 * - Does not modify V0 files or dependencies.
 */

declare(strict_types=1);

$version = 'v3.0.47-live-readiness-start-options-alias-fix';
$args = array_slice($argv, 1);
$apply = in_array('--apply', $args, true);
$checkOnly = in_array('--check', $args, true) || !$apply;

$defaultTarget = __DIR__ . '/pre_ride_email_v3_live_submit_readiness.php';
$target = $defaultTarget;
foreach ($args as $arg) {
    if (str_starts_with($arg, '--target=')) {
        $target = substr($arg, strlen('--target='));
    }
}

function out(string $line = ''): void
{
    echo $line . PHP_EOL;
}

function fail(string $message, int $code = 1): void
{
    fwrite(STDERR, 'ERROR: ' . $message . PHP_EOL);
    exit($code);
}

out('V3 live-readiness starting-point option alias fix ' . $version);
out('Mode: ' . ($apply ? 'apply' : 'dry_run_check_only'));
out('Target: ' . $target);
out('Safety: V3-only file patch. No Bolt, no EDXEIX, no AADE, no DB writes, no V0 changes.');
out('');

if (!is_file($target)) {
    fail('Target file not found: ' . $target);
}
if (!is_readable($target)) {
    fail('Target file is not readable: ' . $target);
}
if ($apply && !is_writable($target)) {
    fail('Target file is not writable by current user. Run as owner or fix permissions first: ' . $target);
}

$original = file_get_contents($target);
if ($original === false) {
    fail('Could not read target file.');
}

$lines = preg_split('/(\r\n|\n|\r)/', $original);
$patchedLines = [];
$changes = [];

foreach ($lines as $lineNo => $line) {
    $new = $line;

    // Only patch SQL lines that explicitly query the V3 starting-point options table constant.
    if (strpos($line, 'LSR_START_OPTIONS_TABLE') !== false) {
        $new = str_replace('WHERE lessor_id = ?', 'WHERE edxeix_lessor_id = ?', $new);
        $new = str_replace('WHERE lessor_id =?', 'WHERE edxeix_lessor_id =?', $new);
        $new = str_replace('AND starting_point_id = ?', 'AND edxeix_starting_point_id = ?', $new);
        $new = str_replace('AND starting_point_id =?', 'AND edxeix_starting_point_id =?', $new);
    }

    if ($new !== $line) {
        $changes[] = [
            'line' => $lineNo + 1,
            'before' => $line,
            'after' => $new,
        ];
    }

    $patchedLines[] = $new;
}

$patched = implode(PHP_EOL, $patchedLines);

$outstanding = [];
foreach (preg_split('/(\r\n|\n|\r)/', $patched) as $lineNo => $line) {
    if (strpos($line, 'LSR_START_OPTIONS_TABLE') !== false) {
        if (strpos($line, 'WHERE lessor_id') !== false || strpos($line, 'starting_point_id') !== false) {
            // The queue row column name may still appear elsewhere in the file, but not in option-table SQL lines.
            // For these option-table lines it should now be edxeix_starting_point_id.
            if (strpos($line, 'edxeix_lessor_id') === false || strpos($line, 'edxeix_starting_point_id') === false) {
                $outstanding[] = 'line ' . ($lineNo + 1) . ': ' . trim($line);
            }
        }
    }
}

out('Detected changes: ' . count($changes));
foreach ($changes as $change) {
    out('--- line ' . $change['line']);
    out('- ' . trim($change['before']));
    out('+ ' . trim($change['after']));
}

if (!empty($outstanding)) {
    out('');
    out('Warnings: possible unpatched option-table alias lines remain:');
    foreach ($outstanding as $warning) {
        out('  ' . $warning);
    }
}

if (count($changes) === 0) {
    out('');
    out('No changes needed. Target may already be patched.');
    exit(0);
}

if ($checkOnly) {
    out('');
    out('Dry run only. Re-run with --apply to write changes.');
    exit(0);
}

$backup = $target . '.bak.' . date('Ymd_His');
if (!copy($target, $backup)) {
    fail('Could not create backup file: ' . $backup);
}

if (file_put_contents($target, $patched) === false) {
    fail('Could not write patched target file. Backup remains at: ' . $backup);
}

out('');
out('Patched successfully.');
out('Backup: ' . $backup);
out('Next: php -l ' . $target);
