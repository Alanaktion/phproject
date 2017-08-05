<?php

require_once 'app/app.php';
if (App::init()) {
    App::run();
}

// Ensure database is up to date
/*$version = \Helper\Security::instance()->checkDatabaseVersion();
if ($version !== true) {
    \Helper\Security::instance()->updateDatabase($version);
}*/

// Initialize plugins and any included locales
/*$pluginDir = scandir("app/plugin");
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
$f3->set("plugins", $plugins);*/
