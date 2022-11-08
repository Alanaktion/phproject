<?php

namespace Model;

/**
 * Class Config
 *
 * @property int $id
 * @property string $attribute
 * @property string $value
 */
class Config extends \Model
{
    protected $_table_name = "config";
    protected static $requiredFields = ['attribute', 'value'];

    /**
     * Loads the configuration for the site
     * @return void
     */
    public static function loadAll()
    {
        $f3 = \Base::instance();
        $db = $f3->get("db.instance");
        $result = $db->exec("SELECT attribute,value FROM config");
        $foundAttributes = [];
        foreach ($result as $item) {
            $foundAttributes[] = $item["attribute"];
            if ($item["attribute"] == 'session_lifetime') {
                $f3->set('JAR.expire', $item['value'] + time());
            }
            $f3->set($item["attribute"], $item["value"]);
        }

        // Set some basic config values if they're not already there
        if (!in_array("site.theme", $foundAttributes)) {
            self::setVal('site.theme', 'css/bootstrap-phproject.css');
        }
        if (!in_array("site.name", $foundAttributes)) {
            self::importAll();
        }
    }

    /**
     * Imports the settings from config.ini to the database
     *
     * This will overwrite config.ini with only database connection settings!
     * @return void
     */
    public static function importAll()
    {
        $f3 = \Base::instance();
        $root = $f3->get("ROOT") . $f3->get("BASE");

        // Import existing config
        $ini = parse_ini_file($root . "/config.ini");
        $ini = $ini + parse_ini_file($root . "/config-base.ini");
        foreach ($ini as $key => $val) {
            if (substr($key, 0, 3) == "db.") {
                continue;
            }
            $conf = new Config();
            $conf->attribute = $key;
            $conf->value = $val;
            $conf->save();
        }

        // Write new config.ini
        $data = "[globals]\n";
        $data .= "db.host=\"{$ini['db.host']}\"\n";
        $data .= "db.user=\"{$ini['db.user']}\"\n";
        $data .= "db.pass=\"{$ini['db.pass']}\"\n";
        $data .= "db.name=\"{$ini['db.name']}\"\n";
        file_put_contents($root . "/config.ini", $data);
    }

    /**
     * Convert INI configuration to PHP format
     *
     * @return void
     */
    public static function iniToPhp()
    {
        $f3 = \Base::instance();

        // Move the config from INI to PHP
        $root = $f3->get("ROOT") . $f3->get("BASE");
        $ini = parse_ini_file($root . "/config.ini");
        $data = "<?php\nreturn " . var_export($ini, true) . ";\n";
        file_put_contents("$root/config.php", $data);
        unlink("$root/config.ini");
    }

    /**
     * Set a configuration value
     * @param  string $key
     * @param  mixed  $value
     * @return Config
     */
    public static function setVal(string $key, $value): Config
    {
        $f3 = \Base::instance();
        $f3->set($key, $value);
        $item = new static();
        $item->load(['attribute = ?', $key]);
        $item->attribute = $key;
        $item->value = $value;
        $item->save();
        return $item;
    }
}
