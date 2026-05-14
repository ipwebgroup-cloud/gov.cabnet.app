<?php
/**
 * gov.cabnet.app — V3 storage/pulse prerequisite check
 *
 * V3-only utility. Does not call Bolt, EDXEIX, AADE, Gmail, or production
 * submission tables. Default mode is read-only. Use --fix to create/repair the
 * V3 storage directories and pulse lock file required by the pulse cron worker.
 */

declare(strict_types=1);

const V3_STORAGE_CHECK_VERSION = 'v3.0.40-pulse-lock-owner-hardening';

function v3_arg_exists(string $name): bool
{
    global $argv;
    return in_array($name, $argv ?? [], true);
}

function v3_arg_value(string $prefix, ?string $default = null): ?string
{
    global $argv;
    foreach (($argv ?? []) as $arg) {
        if (strpos($arg, $prefix . '=') === 0) {
            return substr($arg, strlen($prefix) + 1);
        }
    }
    return $default;
}

function v3_bool_word(bool $value): string
{
    return $value ? 'yes' : 'no';
}

function v3_oct_perms(string $path): string
{
    $perms = @fileperms($path);
    if ($perms === false) {
        return 'n/a';
    }
    return substr(sprintf('%o', $perms), -4);
}

function v3_owner_name(int $uid): string
{
    if (function_exists('posix_getpwuid')) {
        $pw = @posix_getpwuid($uid);
        if (is_array($pw) && isset($pw['name'])) {
            return (string)$pw['name'];
        }
    }
    return (string)$uid;
}

function v3_group_name(int $gid): string
{
    if (function_exists('posix_getgrgid')) {
        $gr = @posix_getgrgid($gid);
        if (is_array($gr) && isset($gr['name'])) {
            return (string)$gr['name'];
        }
    }
    return (string)$gid;
}

function v3_current_user_label(): string
{
    if (function_exists('posix_geteuid')) {
        return v3_owner_name((int)posix_geteuid());
    }
    return get_current_user() ?: 'unknown';
}

function v3_owner_group(string $path): string
{
    $owner = @fileowner($path);
    $group = @filegroup($path);
    $ownerName = is_int($owner) ? v3_owner_name($owner) : 'n/a';
    $groupName = is_int($group) ? v3_group_name($group) : 'n/a';
    return $ownerName . ':' . $groupName;
}

function v3_status_for_path(string $path, string $kind, bool $requiredWritable, ?string $expectedOwner, ?string $expectedGroup): array
{
    $exists = $kind === 'dir' ? is_dir($path) : is_file($path);
    $ownerGroup = $exists ? v3_owner_group($path) : 'n/a';
    $ownerOk = true;
    $groupOk = true;

    if ($exists && $expectedOwner !== null && $expectedOwner !== '') {
        $ownerOk = strpos($ownerGroup, $expectedOwner . ':') === 0;
    }
    if ($exists && $expectedGroup !== null && $expectedGroup !== '') {
        $groupOk = substr($ownerGroup, -strlen(':' . $expectedGroup)) === ':' . $expectedGroup;
    }

    $readable = $exists && is_readable($path);
    $writable = $exists && is_writable($path);
    $ok = $exists && $readable && (!$requiredWritable || $writable) && $ownerOk && $groupOk;

    $notes = [];
    if (!$exists) {
        $notes[] = 'missing';
    }
    if ($exists && !$readable) {
        $notes[] = 'not readable by current user';
    }
    if ($exists && $requiredWritable && !$writable) {
        $notes[] = 'not writable by current user';
    }
    if ($exists && !$ownerOk) {
        $notes[] = 'owner is not expected owner ' . $expectedOwner;
    }
    if ($exists && !$groupOk) {
        $notes[] = 'group is not expected group ' . $expectedGroup;
    }

    return [
        'path' => $path,
        'kind' => $kind,
        'exists' => $exists,
        'readable' => $readable,
        'writable' => $writable,
        'required_writable' => $requiredWritable,
        'perms' => $exists ? v3_oct_perms($path) : 'n/a',
        'owner_group' => $ownerGroup,
        'expected_owner' => $expectedOwner,
        'expected_group' => $expectedGroup,
        'owner_group_ok' => $ownerOk && $groupOk,
        'ok' => $ok,
        'notes' => $notes,
    ];
}

function v3_apply_owner_group(string $path, ?string $owner, ?string $group, array &$events): void
{
    if ($owner !== null && $owner !== '') {
        if (@chown($path, $owner)) {
            $events[] = 'chown ok: ' . $path . ' -> ' . $owner;
        } else {
            $events[] = 'chown skipped/failed: ' . $path . ' -> ' . $owner;
        }
    }

    if ($group !== null && $group !== '') {
        if (@chgrp($path, $group)) {
            $events[] = 'chgrp ok: ' . $path . ' -> ' . $group;
        } else {
            $events[] = 'chgrp skipped/failed: ' . $path . ' -> ' . $group;
        }
    }
}

$appRoot = dirname(__DIR__);
$fix = v3_arg_exists('--fix');
$json = v3_arg_exists('--json');
$owner = v3_arg_value('--owner', 'cabnet');
$group = v3_arg_value('--group', 'cabnet');
$expectedOwner = v3_arg_value('--expected-owner', $owner);
$expectedGroup = v3_arg_value('--expected-group', $group);
$currentUser = v3_current_user_label();

$pulseLock = $appRoot . '/storage/locks/pre_ride_email_v3_fast_pipeline_pulse.lock';

$paths = [
    'app_root' => ['path' => $appRoot, 'kind' => 'dir', 'required_writable' => false, 'expected' => false],
    'storage' => ['path' => $appRoot . '/storage', 'kind' => 'dir', 'required_writable' => true, 'expected' => true],
    'storage_locks' => ['path' => $appRoot . '/storage/locks', 'kind' => 'dir', 'required_writable' => true, 'expected' => true],
    'logs' => ['path' => $appRoot . '/logs', 'kind' => 'dir', 'required_writable' => true, 'expected' => true],
    'pulse_cli' => ['path' => $appRoot . '/cli/pre_ride_email_v3_fast_pipeline_pulse.php', 'kind' => 'file', 'required_writable' => false, 'expected' => false],
    'pulse_cron_worker' => ['path' => $appRoot . '/cli/pre_ride_email_v3_fast_pipeline_pulse_cron_worker.php', 'kind' => 'file', 'required_writable' => false, 'expected' => false],
    'pulse_lock_file' => ['path' => $pulseLock, 'kind' => 'file', 'required_writable' => true, 'expected' => true],
];

$events = [];
if ($fix) {
    foreach (['storage', 'storage_locks', 'logs'] as $key) {
        $path = $paths[$key]['path'];
        if (!is_dir($path)) {
            if (@mkdir($path, 0750, true)) {
                $events[] = 'created: ' . $path;
            } else {
                $events[] = 'create failed: ' . $path;
            }
        }
        if (is_dir($path)) {
            @chmod($path, 0750);
            v3_apply_owner_group($path, $owner, $group, $events);
        }
    }

    if (is_dir(dirname($pulseLock)) && !is_file($pulseLock)) {
        if (@file_put_contents($pulseLock, '{}\n') !== false) {
            $events[] = 'created pulse lock file: ' . $pulseLock;
        } else {
            $events[] = 'create pulse lock file failed: ' . $pulseLock;
        }
    }
    if (is_file($pulseLock)) {
        @chmod($pulseLock, 0660);
        v3_apply_owner_group($pulseLock, $owner, $group, $events);
    }
}

$status = [];
$ok = true;
foreach ($paths as $key => $def) {
    $row = v3_status_for_path(
        $def['path'],
        $def['kind'],
        (bool)$def['required_writable'],
        !empty($def['expected']) ? $expectedOwner : null,
        !empty($def['expected']) ? $expectedGroup : null
    );
    if (!$row['ok']) {
        $ok = false;
    }
    $status[$key] = $row;
}

$result = [
    'ok' => $ok,
    'version' => V3_STORAGE_CHECK_VERSION,
    'mode' => $fix ? 'fix' : 'read_only',
    'current_user' => $currentUser,
    'expected_owner' => $expectedOwner,
    'expected_group' => $expectedGroup,
    'app_root' => $appRoot,
    'events' => $events,
    'paths' => $status,
    'safety' => 'V3-only storage prerequisite check. No Bolt, no EDXEIX, no AADE, no DB writes, no production submission tables. V0 is untouched.',
    'operator_note' => 'Do not run the V3 pulse cron worker as root. Test it as cabnet to avoid root-owned lock files.',
];

if ($json) {
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    exit($ok ? 0 : 2);
}

echo 'V3 storage check ' . V3_STORAGE_CHECK_VERSION . PHP_EOL;
echo 'Mode: ' . $result['mode'] . PHP_EOL;
echo 'Current user: ' . $currentUser . PHP_EOL;
echo 'Expected owner:group for writable V3 runtime paths: ' . $expectedOwner . ':' . $expectedGroup . PHP_EOL;
echo 'App root: ' . $appRoot . PHP_EOL;
echo 'OK: ' . v3_bool_word($ok) . PHP_EOL;
echo 'Safety: ' . $result['safety'] . PHP_EOL;
echo 'Operator note: ' . $result['operator_note'] . PHP_EOL . PHP_EOL;

if (!empty($events)) {
    echo 'Events:' . PHP_EOL;
    foreach ($events as $event) {
        echo '  - ' . $event . PHP_EOL;
    }
    echo PHP_EOL;
}

foreach ($status as $key => $row) {
    echo '[' . $key . '] ' . ($row['ok'] ? 'OK' : 'CHECK') . PHP_EOL;
    echo '  path: ' . $row['path'] . PHP_EOL;
    echo '  exists: ' . v3_bool_word((bool)$row['exists']) . ' readable: ' . v3_bool_word((bool)$row['readable']) . ' writable: ' . v3_bool_word((bool)$row['writable']) . PHP_EOL;
    echo '  perms: ' . $row['perms'] . ' owner:group: ' . $row['owner_group'] . PHP_EOL;
    if (!empty($row['notes'])) {
        echo '  notes: ' . implode('; ', $row['notes']) . PHP_EOL;
    }
}

exit($ok ? 0 : 2);
