<?php

declare(strict_types=1);

set_include_path(get_include_path() . PATH_SEPARATOR . dirname(__DIR__));
require_once("vendor/autoload.php");

$f3 = \Base::instance();
$f3->mset(array(
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
));
