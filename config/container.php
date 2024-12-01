<?php

use Laminas\Mvc\Application;
use Laminas\Stdlib\ArrayUtils;

// Retrieve configuration
$appConfig = require __DIR__ . '/application.config.php';
if (file_exists(__DIR__ . '/development.config.php')) {
    /** @var array $devConfig */
    $devConfig = require __DIR__ . '/development.config.php';
    $appConfig = ArrayUtils::merge($appConfig, $devConfig);
}
$services = Application::init($appConfig)->getServiceManager();

$config = $services->get('config');
$phpSettings = $config['php_settings'];
if ($phpSettings) {
    foreach ($phpSettings as $key => $value) {
        ini_set($key, $value);
    }
}

return $services;
