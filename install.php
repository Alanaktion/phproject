<?php

// Initialize core
$f3=require("lib/base.php");
$f3->mset(array(
	"UI" => "app/view/",
	"LOGS" => "log/",
	"TEMP" => "tmp/",
	"PREFIX" => "dict.",
	"LOCALES" => "app/dict/",
	"FALLBACK" => "en",
	"CACHE" => false,
	"AUTOLOAD" => "app/",
	"PACKAGE" => "Phproject",
	"site.url" => $f3->get("SCHEME") . "://" . $f3->get("HOST") . $f3->get("BASE") . "/"
));

// Check if already installed
if(is_file("config.ini")) {
	$f3->set("success", "Phproject is already installed.");
}

// Check PCRE version
if((float)PCRE_VERSION < 7.9) {
	$f3->set("error", "PCRE version is out of date");
}

// Check for MySQL PDO
if(!in_array("mysql", PDO::getAvailableDrivers())) {
	$f3->set("error", "MySQL PDO driver is not avaialble.");
}

// Check for GD library
if(!function_exists("imagecreatetruecolor")) {
	$f3->set("warning", "GD library is not available. Profile pictures and file thumbnails will not work until it is installed.");
}

// Run installation process if post data received
if($_POST) {
	$post = $_POST;

	try {
		// Connect to database
		$db = new \DB\SQL(
			"mysql:host=" . $post["db-host"] . ";port=" . $post["db-port"] . ";dbname=" . $post["db-name"],
			$post["db-user"],
			$post["db-pass"]
		);

		// Run installation scripts
		$install_db = file_get_contents("db/database.sql");
		$db->exec(explode(";", $install_db));

		// Create admin user
		$f3->set("db.instance", $db);
		$security = \Helper\Security::instance();
		$user = new \Model\User;
		$user->role = "admin";
		$user->rank = \Model\User::RANK_SUPER;
		$user->name = "Admin";
		$user->username = $post["user-username"] ?: "admin";
		$user->email = $post["user-email"];
		$user->salt = $security->salt();
		$user->password = $security->hash($post["user-password"] ?: "admin", $user->salt);
		$user->api_key = $security->salt_sha1();
		$user->save();

		// Ensure required directories exist
		if(!is_dir("tmp/cache")) {
			mkdir("tmp/cache", 0777, true);
		}
		if(!is_dir("log")) {
			mkdir("log", 0777, true);
		}

		// Save configruation options
		if(!empty($post["language"])) {
			\Model\Config::setVal("LANGUAGE", $post["language"]);
		}
		if($post["parser"] == "both") {
			\Model\Config::setVal("parse.markdown", 1);
			\Model\Config::setVal("parse.textile", 1);
		} elseif($post["parser"] == "markdown") {
			\Model\Config::setVal("parse.markdown", 0);
			\Model\Config::setVal("parse.textile", 1);
		} elseif($post["parser"] == "textile") {
			\Model\Config::setVal("parse.markdown", 1);
			\Model\Config::setVal("parse.textile", 0);
		}
		\Model\Config::setVal("site.name", $post['site-name']);
		\Model\Config::setVal("site.timezone", $post['site-timezone']);
		\Model\Config::setVal("site.public_registration", $post['site-public_registration']);
		\Model\Config::setVal("mail.from", $post['mail-from']);

		// Write database connection file
		$data = "[globals]\n";
		$data .= "db.host=\"{$post['db-host']}\"\n";
		$data .= "db.user=\"{$post['db-user']}\"\n";
		$data .= "db.pass=\"{$post['db-pass']}\"\n";
		$data .= "db.name=\"{$post['db-name']}\"\n";
		file_put_contents("config.ini", $data);

		$f3->set("success", "Installation complete.");
	} catch(PDOException $e) {
		$f3->set("warning", $e->getMessage());
	}
}

// Render installer template
echo Template::instance()->render("install.html");
