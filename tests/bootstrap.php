<?php

declare(strict_types=1);

set_include_path(get_include_path() . PATH_SEPARATOR . dirname(__DIR__));
require_once(dirname(__DIR__) . "/vendor/autoload.php");

// Set up server variables for testing web requests
$_SERVER += [
    'SERVER_PROTOCOL' => 'HTTP/1.1',
    'REQUEST_METHOD' => 'GET',
    'REQUEST_URI' => '/',
    'SCRIPT_NAME' => '/index.php',
    'SCRIPT_FILENAME' => dirname(__DIR__) . '/index.php',
    'HTTP_HOST' => 'localhost',
    'SERVER_NAME' => 'localhost',
    'SERVER_PORT' => '80',
    'HTTPS' => 'off',
    'REMOTE_ADDR' => '127.0.0.1',
];

$f3 = \Base::instance();
$f3->mset([
    "HALT" => false,
    "DEBUG" => 0,
    "UI" => "app/view/;app/plugin/",
    "ESCAPE" => false,
    "LOGS" => "log/",
    "TEMP" => "tmp/",
    "PREFIX" => "dict.",
    "LOCALES" => "app/dict/",
    "FALLBACK" => "en",
    "CACHE" => false,
    "AUTOLOAD" => "app/;lib/vendor/",
    "PACKAGE" => "Phproject",
    "TZ" => "UTC",
    "site.timezone" => "America/Phoenix"
]);
