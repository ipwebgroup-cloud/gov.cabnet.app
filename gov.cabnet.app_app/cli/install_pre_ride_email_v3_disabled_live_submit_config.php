<?php
declare(strict_types=1);

/*
 * gov.cabnet.app — V3 disabled live-submit config installer
 * Version: v3.0.30-disabled-live-config-installer
 *
 * Purpose:
 * - Create a server-only V3 live-submit config file in a CLOSED state.
 * - Make the master gate load a real config while still blocking live submit.
 *
 * Safety:
 * - Does NOT enable live submit.
 * - Does NOT call EDXEIX.
 * - Does NOT call AADE.
 * - Does NOT write to the database.
 * - Does NOT touch production pre-ride-email-tool.php.
 */

const V3_DISABLED_CONFIG_INSTALLER_VERSION = 'v3.0.30-disabled-live-config-installer';
const DEFAULT_CONFIG_PATH = '/home/cabnet/gov.cabnet.app_config/pre_ride_email_v3_live_submit.php';
const DEFAULT_BACKUP_DIR = '/home/cabnet/gov.cabnet.app_app/storage/patch_backups/v3_live_submit_config';

function usage(): string
{
    return <<<TXT
V3 disabled live-submit config installer {V3_DISABLED_CONFIG_INSTALLER_VERSION}

Usage:
  php install_pre_ride_email_v3_disabled_live_submit_config.php [options]

Options:
  --dry-run              Preview only. This is the default.
  --write                Write the disabled server-only config if safe.
  --force-disabled       Overwrite an existing config with the disabled config after backup.
  --path=/custom/file    Override destination config path.
  --help                 Show help.

This script only writes a CLOSED/DISABLED config. It cannot enable live submit.
TXT;
}

function options(array $argv): array
{
    $opts = [
        'write' => false,
        'force_disabled' => false,
        'path' => DEFAULT_CONFIG_PATH,
        'help' => false,
    ];

    foreach (array_slice($argv, 1) as $arg) {
        if ($arg === '--dry-run') {
            $opts['write'] = false;
        } elseif ($arg === '--write') {
            $opts['write'] = true;
        } elseif ($arg === '--force-disabled') {
            $opts['force_disabled'] = true;
        } elseif ($arg === '--help' || $arg === '-h') {
            $opts['help'] = true;
        } elseif (preg_match('/^--path=(.+)$/', $arg, $m)) {
            $opts['path'] = trim($m[1]);
        } else {
            fwrite(STDERR, "Unknown option: {$arg}\n\n");
            $opts['help'] = true;
        }
    }

    return $opts;
}

function disabledConfigContents(): string
{
    $generated = gmdate('c');
    return <<<PHP
<?php
/*
 * gov.cabnet.app — SERVER-ONLY V3 live-submit config
 * Generated: {$generated}
 * State: CLOSED / DISABLED
 *
 * Safety:
 * - This config intentionally does NOT enable live EDXEIX submission.
 * - Keep this file out of Git.
 * - Do not add credentials here.
 */

return [
    'enabled' => false,
    'mode' => 'disabled',
    'adapter' => 'disabled',
    'hard_enable_live_submit' => false,

    'required_queue_status' => 'live_submit_ready',
    'min_future_minutes' => 1,
    'operator_approval_required' => true,

    // Must stay empty until Andreas explicitly approves a live-submit activation step.
    'acknowledgement_phrase' => '',
    'required_acknowledgement_phrase' => 'I UNDERSTAND V3 WILL LIVE SUBMIT TO EDXEIX',

    // Empty array means no extra config-level lessor restriction while disabled.
    // A future live activation may restrict this to verified lessor IDs only.
    'allowed_lessors' => [],

    'notes' => 'Disabled server-only config installed so the V3 master gate can load configuration while remaining closed.',
];
PHP;
}

function ensureSafePath(string $path): void
{
    $realParent = realpath(dirname($path));
    if ($realParent === false) {
        return;
    }
    if (!str_starts_with($realParent, '/home/cabnet/')) {
        throw new RuntimeException('Refusing to write outside /home/cabnet: ' . $path);
    }
}

function fileLooksLiveEnabled(string $path): bool
{
    $text = @file_get_contents($path);
    if (!is_string($text)) {
        return false;
    }

    $signals = [
        "/'enabled'\s*=>\s*true/i",
        '/"enabled"\s*=>\s*true/i',
        "/'mode'\s*=>\s*'live'/i",
        '/"mode"\s*=>\s*"live"/i',
        "/'adapter'\s*=>\s*'(?!disabled)[^']+'/i",
        '/"adapter"\s*=>\s*"(?!disabled)[^"]+"/i',
        "/'hard_enable_live_submit'\s*=>\s*true/i",
        '/"hard_enable_live_submit"\s*=>\s*true/i',
    ];

    foreach ($signals as $pattern) {
        if (preg_match($pattern, $text)) {
            return true;
        }
    }
    return false;
}

function backupExisting(string $path): ?string
{
    if (!is_file($path)) {
        return null;
    }

    if (!is_dir(DEFAULT_BACKUP_DIR) && !mkdir(DEFAULT_BACKUP_DIR, 0750, true) && !is_dir(DEFAULT_BACKUP_DIR)) {
        throw new RuntimeException('Unable to create backup directory: ' . DEFAULT_BACKUP_DIR);
    }

    $backup = rtrim(DEFAULT_BACKUP_DIR, '/') . '/' . basename($path) . '.' . gmdate('Ymd_His') . '.bak';
    if (!copy($path, $backup)) {
        throw new RuntimeException('Unable to create backup: ' . $backup);
    }
    @chmod($backup, 0640);
    return $backup;
}

function run(array $argv): int
{
    $opts = options($argv);
    if ($opts['help']) {
        echo usage() . PHP_EOL;
        return 0;
    }

    $path = (string)$opts['path'];
    $exists = is_file($path);
    $looksLive = $exists && fileLooksLiveEnabled($path);

    echo 'V3 disabled live-submit config installer ' . V3_DISABLED_CONFIG_INSTALLER_VERSION . PHP_EOL;
    echo 'Mode: ' . ($opts['write'] ? 'WRITE_DISABLED_CONFIG' : 'DRY_RUN') . PHP_EOL;
    echo 'Config path: ' . $path . PHP_EOL;
    echo 'Exists: ' . ($exists ? 'yes' : 'no') . PHP_EOL;
    echo 'Existing file appears live-enabled: ' . ($looksLive ? 'yes' : 'no') . PHP_EOL;
    echo 'Safety: no EDXEIX call, no AADE call, no DB writes, no production tool change.' . PHP_EOL;

    if (!$opts['write']) {
        if ($exists && !$opts['force_disabled']) {
            echo 'Dry-run result: config exists; would leave unchanged unless --force-disabled is used with --write.' . PHP_EOL;
        } else {
            echo 'Dry-run result: would write disabled config.' . PHP_EOL;
        }
        return 0;
    }

    ensureSafePath($path);

    if ($exists && !$opts['force_disabled']) {
        echo 'Refused: config already exists. Use --force-disabled to overwrite with a disabled config after backup.' . PHP_EOL;
        return 3;
    }

    if ($looksLive && !$opts['force_disabled']) {
        echo 'Refused: existing config appears live-enabled. Use --force-disabled only if intentionally rolling it back to disabled.' . PHP_EOL;
        return 4;
    }

    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
        throw new RuntimeException('Unable to create config directory: ' . $dir);
    }

    $backup = backupExisting($path);
    if ($backup !== null) {
        echo 'Backup: ' . $backup . PHP_EOL;
    }

    $tmp = $path . '.tmp.' . getmypid();
    if (file_put_contents($tmp, disabledConfigContents(), LOCK_EX) === false) {
        throw new RuntimeException('Unable to write temp config: ' . $tmp);
    }
    @chmod($tmp, 0640);
    if (!rename($tmp, $path)) {
        @unlink($tmp);
        throw new RuntimeException('Unable to move temp config into place: ' . $path);
    }
    @chmod($path, 0640);

    echo 'Installed disabled config: yes' . PHP_EOL;
    echo 'Next: run php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_live_submit_gate_check.php' . PHP_EOL;
    return 0;
}

try {
    exit(run($argv));
} catch (Throwable $e) {
    fwrite(STDERR, 'ERROR: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
