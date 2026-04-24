<?php

$container = require __DIR__ . '/../src/bootstrap.php';

use Bridge\Edxeix\SessionStore;

$config = $container['config'];

$options = getopt('', ['cookie:', 'csrf::']);
$cookie = (string) ($options['cookie'] ?? '');
$csrf = (string) ($options['csrf'] ?? '');

if ($cookie === '') {
    fwrite(STDERR, "Usage: php cli/import_session.php --cookie=\"COOKIE_HEADER\" [--csrf=\"TOKEN\"]\n");
    exit(1);
}

$store = new SessionStore($config->get('edxeix.session_file'));
$store->write([
    'cookie_header' => $cookie,
    'csrf_token' => $csrf,
]);

fwrite(STDOUT, "Session imported successfully.\n");
