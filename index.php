<?php

// Initialize core
$f3=require("lib/base.php");
$f3->mset(array(
	"UI" => "app/view/",
	"LOGS" => "log/",
	"LOCALES" => "app/dict/",
	"AUTOLOAD" => "app/"
));

// Load local configuration
$f3->config("config.ini");

// Set up error handling
$f3->set("ONERROR", function($f3) {
	switch($f3->get("ERROR.code")) {
		case 404:
			$f3->set("title", "Not Found");
			echo Template::instance()->render("error/404.html");
			break;
		case 403:
			echo "You do not have access to this page.";
			break;
		case 500:
			include "app/view/error/500.html";
			break;
		default:
			$f3->set("title", "Error");
			echo View::instance()->render("error/general.html");
	}
});

// Connect to database
$f3->set("db.instance", new DB\SQL(
	"mysql:host=" . $f3->get("db.host") . ";port=3306;dbname=" . $f3->get("db.name"),
	$f3->get("db.user"),
	$f3->get("db.pass")
));

// Define routes
require_once "app/routes.php";

// Load user if session exists
$user = new Model\User();
$user->loadCurrent();

// Include Fat Free manual at /ref
$f3->route("GET /ref", function() {
	echo View::instance()->render("userref.html");
});

// Run the application
$f3->run();
