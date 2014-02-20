<?php
if (!defined('STDIN'))
	die("Cron jobs must be run from the command line.");

$homedir = dirname(dirname(__FILE__)) . "/";
set_include_path($homedir);

$f3=require($homedir."lib/base.php");
$f3->mset(array(
	"UI" => $homedir."app/view/",
	"LOGS" => $homedir."log/",
	"AUTOLOAD" => $homedir."app/"
));

// Load local configuration
$f3->config($homedir."config.ini");

// Connect to database
$f3->set("db.instance", new DB\SQL(
	"mysql:host=" . $f3->get("db.host") . ";port=3306;dbname=" . $f3->get("db.name"),
	$f3->get("db.user"),
	$f3->get("db.pass")
));

require_once $homedir . "app/functions.php";
