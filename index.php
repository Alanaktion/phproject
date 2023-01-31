<?php

define('PHPROJECT_VERSION', '1.7.14');

// Initialize core
require_once "vendor/autoload.php";
$f3 = Base::instance();
$f3->mset(array(
    "UI" => "app/view/;app/plugin/",
    "ESCAPE" => false,
    "LOGS" => "log/",
    "TEMP" => "tmp/",
    "PREFIX" => "dict.",
    "LOCALES" => "app/dict/",
    "FALLBACK" => "en",
    "CACHE" => true,
    "AUTOLOAD" => "app/;lib/vendor/",
    "JAR.samesite" => "Lax",
    "PACKAGE" => "Phproject",
    "TZ" => "UTC",
    "microtime" => microtime(true),
    "site.url" => $f3->get("SCHEME") . "://" . $f3->get("HOST") . $f3->get("BASE") . "/"
));

// Load configuration
if (is_file('config.php')) {
    $config = require_once('config.php');
    $f3->mset($config);
} elseif (is_file('config.ini')) {
    $f3->config('config.ini');
    \Model\Config::iniToPhp();
} else {
    header('Location: install.php');
    return;
}

// Get current Git revision
if (is_file(".git/refs/heads/master")) {
    $f3->set("revision", trim(file_get_contents(".git/refs/heads/master")));
} else {
    $f3->set("revision", "");
}

// Load routes
$f3->config("app/routes.ini");

// Set up error handling
$f3->set("ONERROR", function (Base $f3) {
    if ($f3->get("AJAX")) {
        if (!headers_sent()) {
            header("Content-type: application/json");
        }
        echo json_encode(array(
            "error" => $f3->get("ERROR.title"),
            "text" => $f3->get("ERROR.text")
        ));
    } else {
        switch ($f3->get("ERROR.code")) {
            case 404:
                $f3->set("title", "Not Found");
                $f3->set("ESCAPE", false);
                echo \Helper\View::instance()->render("error/404.html");
                break;
            case 403:
                echo "You do not have access to this page.";
                break;
            default:
                if (ob_get_level()) {
                    include "app/view/error/inline.html";
                } else {
                    include "app/view/error/500.html";
                }
        }
    }
});

// Connect to database
$f3->set("db.instance", new DB\SQL(
    "mysql:host=" . $f3->get("db.host") . ";port=" . $f3->get("db.port") . ";dbname=" . $f3->get("db.name"),
    $f3->get("db.user"),
    $f3->get("db.pass"),
    [\PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4;']
));

// Load final configuration
\Model\Config::loadAll();

// Ensure database is up to date
$version = \Helper\Security::instance()->checkDatabaseVersion();
if ($version !== true) {
    \Helper\Security::instance()->updateDatabase($version);
}

// Set up CSRF protection
\Helper\Security::instance()->initCsrfToken();

// Initialize template extensions
\Helper\View::instance()->extend("csrf-token", "Helper\Template::csrfToken");

// Minify static resources
// Cache for 1 week
// This route is deprecated, and will be removed in a future release.
$f3->route("GET /minify/@type/@files", function (Base $f3, $args) {
    if (!in_array($args['type'], ['js', 'css'])) {
        $f3->abort(404);
        return;
    }
    $f3->set("UI", $args["type"] . "/");
    echo Web::instance()->minify($args["files"]);
}, $f3->get("cache_expire.minify"));

// Initialize plugins and any included locales
$pluginDir = scandir("app/plugin");
$plugins = array();
$locales = "";
foreach ($pluginDir as $pluginName) {
    if ($pluginName != "." && $pluginName != ".." && is_dir("app/plugin/$pluginName") && is_file("app/plugin/$pluginName/base.php") && is_dir("app/plugin/$pluginName/dict")) {
        $locales .= ";app/plugin/$pluginName/dict/";
    }
}
if ($locales) {
    $f3->set("LOCALES", $f3->get("LOCALES") . $locales);
}
foreach ($pluginDir as $pluginName) {
    if ($pluginName != "." && $pluginName != ".." && is_dir("app/plugin/$pluginName") && is_file("app/plugin/$pluginName/base.php")) {
        $pluginName = "Plugin\\" . str_replace(" ", "_", ucwords(str_replace("_", " ", $pluginName))) . "\\Base";
        $plugin = $pluginName::instance();
        $slug = \Web::instance()->slug($plugin->_package());
        $plugins[$slug] = $plugin;
        if (!$plugin->_installed()) {
            try {
                $plugin->_install();
            } catch (Exception $e) {
                $f3->set("error", "Failed to install plugin " . $plugin->_package() . ": " . $e->getMessage());
            }
        }
        try {
            $plugin->_load();
        } catch (Exception $e) {
            $f3->set("error", "Failed to initialize plugin " . $plugin->_package() . ": " . $e->getMessage());
        }
    }
}
$f3->set("plugins", $plugins);

// Set up user session
$user = new Model\User();
$user->loadCurrent();

// Load issue types
$types = new \Model\Issue\Type();
$f3->set("issue_types", $types->find(null, null, $f3->get("cache_expire.db")));

// Run the application
$f3->set("menuitem", false);
$f3->run();
