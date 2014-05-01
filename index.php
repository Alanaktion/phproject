<?php
/** CRON TEST */
if ((float)PCRE_VERSION < 7.9)
	trigger_error('PCRE version is out of date');

// Initialize core
$f3=require("lib/base.php");
$f3->mset(array(
	"UI" => "app/view/",
	"LOGS" => "log/",
	"TEMP" => "tmp/",
	"AUTOLOAD" => "app/",
	"PACKAGE" => "Phproject",
	"microtime" => microtime(true)
));

// Get current Git revision
if(is_file(".git/refs/heads/master")) {
	$f3->set("revision", @file_get_contents(".git/refs/heads/master"));
} else {
	$f3->set("revision", "");
}

// Load configuration
$f3->config("config-base.ini");
$f3->config("config.ini");

// Load routes
$f3->config("app/routes.ini");

// Set up error handling
$f3->set("ONERROR", function($f3) {
	switch($f3->get("ERROR.code")) {
		case 404:
			$f3->set("title", "Not Found");
			$f3->set("ESCAPE", false);
			echo Template::instance()->render("error/404.html");
			break;
		case 403:
			echo "You do not have access to this page.";
			break;
		default:
			return false;
	}
});

// Connect to database
$f3->set("db.instance", new DB\SQL(
	"mysql:host=" . $f3->get("db.host") . ";port=3306;dbname=" . $f3->get("db.name"),
	$f3->get("db.user"),
	$f3->get("db.pass")
));

// Define global core functions
require_once "app/functions.php";

// Minify static resources
// Cache for 1 week
$f3->route("GET /minify/@type/@files", function($f3, $args) {
	$f3->set("UI", $args["type"] . "/");
	echo Web::instance()->minify($args["files"]);
}, $f3->get("cache_expire.minify"));

// Set up session handler
session_name("PHPROJSESS");
// new Session();

// Load user if session exists
$user = new Model\User();
$user->loadCurrent();

// Load issue types if user is logged in
if($f3->get("user")) {
	$types = new \Model\Issue\Type();
	$f3->set("issue_types", $types->find(null, null, $f3->get("cache_expire.db")));
}

// Run the application
$f3->set("menuitem", false);
$f3->run();
