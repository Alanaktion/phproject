<?php

// Initialize core
$f3=require("lib/base.php");
$f3->mset(array(
	"UI" => "app/view/",
	"LOGS" => "log/",
	"TEMP" => "tmp/",
	"LOCALES" => "app/dict/",
	"FALLBACK" => "en",
	"CACHE" => false,
	"AUTOLOAD" => "app/",
	"PACKAGE" => "Phproject",
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
if($f3->get("POST")) {
	$f3 = \Base::instance();
	$post = $f3->get("POST");

	try {
		// Connect to database
		$db = new \DB\SQL(
			"mysql:host=" . $post["db-host"] . ";port=" . $post["db-port"] . ";dbname=" . $post["db-name"],
			$post["db-user"],
			$post["db-pass"]
		);

		$install_db = file_get_contents("db/database.sql");

		// Rewrite database import script with table prefixes
		if(!empty($post["db-prefix"])) {
			$install_db = preg_replace_callback("/((CREATE|DROP) (VIEW|TABLE)( IF EXISTS)?|INSERT INTO|JOIN|REFERENCES|FROM) \(*`?([a-z_]+)`?/im", function ($matches) use($post) {
				$table = $matches[count($matches) - 1];
				return str_replace($table, $post["db-prefix"] . $table, $matches[0]);
			}, $install_db);
		}

		file_put_contents("db/database-prefixed.sql", $install_db);

		// Run database installation script
		$db->exec(explode(";", $install_db));

		// Create admin user
		$f3->set("db.instance", $db);
		$security = \Helper\Security::instance();
		$user = new \Model\User;
		$user->role = "admin";
		$user->name = "Admin";
		$user->username = $post["user-username"] ?: "admin";
		$user->email = $post["user-email"];
		$user->salt = $security->salt();
		$user->password = $security->hash($post["user-password"] ?: "admin", $user->salt);
		$user->save();

	} catch(PDOException $e) {
		$f3->set("warning", $e->getMessage());
		return false;
	}

	// Ensure required directories exist
	if(!is_dir("tmp/cache")) {
		mkdir("tmp/cache", 0777, true);
	}
	if(!is_dir("log")) {
		mkdir("log", 0777, true);
	}

	$config = "[globals]";
	if(!empty($post["language"])) {
		$config .= "\nLANGUAGE={$post['language']}";
	}

	// Write configuration file
	file_put_contents("config.ini",
"$config

; Database
db.host={$post['db-host']}
db.port={$post['db-port']}
db.user={$post['db-user']}
db.pass={$post['db-pass']}
db.name={$post['db-name']}
db.prefix={$post['db-prefix']}

; Global site configuration
site.name={$post['site-name']}
site.timezone={$post['site-timezone']}
site.public_registration={$post['site-public_registration']}
site.db_sessions=1

; Email
mail.from={$post['mail-from']}
");

	$f3->set("success", "Installation complete.");
}

<<<<<<< HEAD
// Attempt installation if POST data received
if($f3->get("POST") && !is_file("config.ini")) {
	run_install();
}

=======
>>>>>>> master
// Render installer template
echo Template::instance()->render("install.html");
