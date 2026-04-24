<?php

spl_autoload_register(function (string $class): void {
    if (!str_starts_with($class, 'Bridge\\')) {
        return;
    }

    $relative = str_replace('Bridge\\', '', $class);
    $relative = str_replace('\\', DIRECTORY_SEPARATOR, $relative);
    $file = __DIR__ . DIRECTORY_SEPARATOR . $relative . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});

$configFile = getenv('GOV_CABNET_CONFIG');
if (!is_string($configFile) || $configFile === '') {
    $configFile = dirname(__DIR__, 2) . '/gov.cabnet.app_config/config.php';
}

if (!file_exists($configFile)) {
    throw new RuntimeException('Missing external config file. Expected: ' . $configFile);
}

$config = new Bridge\Config(require $configFile);
$db = new Bridge\Database($config);
$http = new Bridge\HttpClient();
$logger = new Bridge\Logger($config->get('paths.logs'));

return [
    'config' => $config,
    'db' => $db,
    'http' => $http,
    'logger' => $logger,
];
