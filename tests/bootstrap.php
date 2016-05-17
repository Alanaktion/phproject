<?php
set_include_path(get_include_path() . PATH_SEPARATOR . dirname(__DIR__));
$f3=require("lib/base.php");
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
