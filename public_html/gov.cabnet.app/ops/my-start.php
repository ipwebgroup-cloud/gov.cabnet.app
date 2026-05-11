<?php
/**
 * gov.cabnet.app — My Start redirect v1.0
 *
 * Redirects the logged-in operator to their stored preferred landing page.
 * Read-only. Does not call Bolt, EDXEIX, AADE, or any trip workflow.
 */

declare(strict_types=1);

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Robots-Tag: noindex,nofollow', true);

require_once __DIR__ . '/_shell.php';

$prefs = opsui_preferences();
$target = (string)($prefs['default_landing_path'] ?? '/ops/home.php');
$allowed = [
    '/ops/home.php',
    '/ops/pre-ride-email-tool.php',
    '/ops/pre-ride-email-toolv2.php',
    '/ops/test-session.php',
    '/ops/preflight-review.php',
    '/ops/profile.php',
];

if (!in_array($target, $allowed, true)) {
    $target = '/ops/home.php';
}

header('Location: ' . $target, true, 302);
exit;
