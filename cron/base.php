<?php
if (!defined('STDIN'))
	die("Cron jobs must be run from the command line.");

$f3=require("../lib/base.php");
$f3->mset(array(
	"UI" => "app/view/",
	"LOGS" => "log/",
	"AUTOLOAD" => "app/"
));

// Load local configuration
$f3->config("../config.ini");

require_once "../app/functions.php";
