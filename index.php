<?php

// Initialize core
$f3=require("lib/base.php");
$f3->mset(array(
	"UI" => "app/view/",
	"LOGS" => "log/",
	"TEMP" => "tmp/",
	"LOCALES" => "app/dict/",
	"FALLBACK" => "en",
	"CACHE" => true,
	"AUTOLOAD" => "app/",
	"PACKAGE" => "Phproject",
	"microtime" => microtime(true),
	"site.url" => $f3->get("SCHEME") . "://" . $f3->get("HOST") . $f3->get("BASE") . "/"
));

// Redirect to installer if no config file is found
if(!is_file("config.ini")) {
	header("Location: install.php");
	return;
}

// Get current Git revision
if(is_file(".git/refs/heads/master")) {
	$f3->set("revision", file_get_contents(".git/refs/heads/master"));
} else {
	$f3->set("revision", "");
}

// Load configuration
$f3->config("config-base.ini");
$f3->config("config.ini");

// Load routes
$f3->config("app/routes.ini");

// Set up error handling
$f3->set("ONERROR", function(Base $f3) {
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
			if(ob_get_level()) {
				include "app/view/error/inline.html";
			} else {
				include "app/view/error/500.html";
			}
	}
});

// Connect to database
$f3->set("db.instance", new DB\SQL(
	"mysql:host=" . $f3->get("db.host") . ";port=" . $f3->get("db.port") . ";dbname=" . $f3->get("db.name"),
	$f3->get("db.user"),
	$f3->get("db.pass")
));

// Minify static resources
// Cache for 1 week
$f3->route("GET /minify/@type/@files", function(Base $f3, $args) {
	$f3->set("UI", $args["type"] . "/");
	echo Web::instance()->minify($args["files"]);
}, $f3->get("cache_expire.minify"));

// Initialize plugins
$plugins = scandir("app/plugin");
foreach($plugins as &$plugin) {
	if($plugin != "." && $plugin != ".." && is_file("app/plugin/$plugin/base.php")) {
		$plugin = "Plugin\\" . str_replace(" ", "_", ucwords(str_replace("_", " ", $plugin))) . "\\Base";
		$plugin = $plugin::instance();
		if(!$plugin->_installed()) {
			$plugin->_install();
		}
		$plugin->_load();
	} else {
		unset($plugin);
	}
}

// Set up session handler
if($f3->get("site.db_sessions")) {
	new \DB\SQL\Session($f3->get("db.instance"), "session", false);
}

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
