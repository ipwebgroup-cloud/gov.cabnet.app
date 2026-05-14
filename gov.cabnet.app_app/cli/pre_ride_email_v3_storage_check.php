<?php
/**
 * gov.cabnet.app — V3 storage/pulse prerequisite check
 *
 * V3-only utility. Does not call Bolt, EDXEIX, AADE, Gmail, or production
 * submission tables. Default mode is read-only. Use --fix to create the V3
 * storage directories required by the pulse cron lock/log mechanism.
 */

declare(strict_types=1);

const V3_STORAGE_CHECK_VERSION = 'v3.0.39-v3-storage-check';

function v3_arg_exists(string $name): bool
{
    global $argv;
    return in_array($name, $argv ?? [], true);
}

function v3_arg_value(string $prefix, ?string $default = null): ?string
{
    global $argv;
    foreach (($argv ?? []) as $arg) {
        if (str_starts_with($arg, $prefix . '=')) {
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

function v3_owner_group(string $path): string
{
    $owner = @fileowner($path);
    $group = @filegroup($path);
    $ownerName = is_int($owner) ? (string)$owner : 'n/a';
    $groupName = is_int($group) ? (string)$group : 'n/a';

    if (function_exists('posix_getpwuid') && is_int($owner)) {
        $pw = @posix_getpwuid($owner);
        if (is_array($pw) && isset($pw['name'])) {
            $ownerName = (string)$pw['name'];
        }
    }

    if (function_exists('posix_getgrgid') && is_int($group)) {
        $gr = @posix_getgrgid($group);
        if (is_array($gr) && isset($gr['name'])) {
            $groupName = (string)$gr['name'];
        }
    }

    return $ownerName . ':' . $groupName;
}

function v3_status_for_path(string $path, string $kind): array
{
    $exists = $kind === 'dir' ? is_dir($path) : is_file($path);
    return [
        'path' => $path,
        'kind' => $kind,
        'exists' => $exists,
        'readable' => $exists && is_readable($path),
        'writable' => $exists && is_writable($path),
        'perms' => $exists ? v3_oct_perms($path) : 'n/a',
        'owner_group' => $exists ? v3_owner_group($path) : 'n/a',
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
$owner = v3_arg_value('--owner');
$group = v3_arg_value('--group');

$paths = [
    'app_root' => ['path' => $appRoot, 'kind' => 'dir', 'required_writable' => false],
    'storage' => ['path' => $appRoot . '/storage', 'kind' => 'dir', 'required_writable' => true],
    'storage_locks' => ['path' => $appRoot . '/storage/locks', 'kind' => 'dir', 'required_writable' => true],
    'logs' => ['path' => $appRoot . '/logs', 'kind' => 'dir', 'required_writable' => true],
    'pulse_cli' => ['path' => $appRoot . '/cli/pre_ride_email_v3_fast_pipeline_pulse.php', 'kind' => 'file', 'required_writable' => false],
    'pulse_cron_worker' => ['path' => $appRoot . '/cli/pre_ride_email_v3_fast_pipeline_pulse_cron_worker.php', 'kind' => 'file', 'required_writable' => false],
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
}

$status = [];
$ok = true;
foreach ($paths as $key => $def) {
    $row = v3_status_for_path($def['path'], $def['kind']);
    $row['required_writable'] = (bool)$def['required_writable'];
    $row['ok'] = $row['exists'] && $row['readable'] && (!$row['required_writable'] || $row['writable']);
    if (!$row['ok']) {
        $ok = false;
    }
    $status[$key] = $row;
}

$result = [
    'ok' => $ok,
    'version' => V3_STORAGE_CHECK_VERSION,
    'mode' => $fix ? 'fix' : 'read_only',
    'app_root' => $appRoot,
    'events' => $events,
    'paths' => $status,
    'safety' => 'V3-only storage prerequisite check. No Bolt, no EDXEIX, no AADE, no DB writes, no production submission tables.',
];

if ($json) {
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    exit($ok ? 0 : 2);
}

echo 'V3 storage check ' . V3_STORAGE_CHECK_VERSION . PHP_EOL;
echo 'Mode: ' . $result['mode'] . PHP_EOL;
echo 'App root: ' . $appRoot . PHP_EOL;
echo 'OK: ' . v3_bool_word($ok) . PHP_EOL;
echo 'Safety: ' . $result['safety'] . PHP_EOL . PHP_EOL;

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
}

exit($ok ? 0 : 2);
