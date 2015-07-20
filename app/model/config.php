<?php

namespace Model;

class Config extends \Model {

	protected $_table_name = "config";
	protected static $requiredFields = array('attribute', 'value');

	/**
	 * Loads the configuration for the site
	 */
	public static function loadAll() {
		$f3 = \Base::instance();
		$db = $f3->get("db.instance");
		$result = $db->exec("SELECT attribute,value FROM config");
		$foundAttributes = array();
		foreach($result as $item) {
			$foundAttributes[] = $item["attribute"];
			$f3->set($item["attribute"], $item["value"]);
		}
		if(!in_array("site.name", $foundAttributes)) {
			self::importAll();
		}
	}

	/**
	 * Imports the settings from config.ini to the database
	 *
	 * This will overwrite config.ini with only database connection settings!
	 */
	public static function importAll() {
		$f3 = \Base::instance();
		$root = $f3->get('ROOT').$f3->get('BASE');

		// Import existing config
		$ini = parse_ini_file($root.'/config.ini');
		$ini = $ini + parse_ini_file($root.'/config-base.ini');
		foreach($ini as $key => $val) {
			if(substr($key, 0, 3) == 'db.') {
				continue;
			}
			$conf = new Config;
			$conf->attribute = $key;
			$conf->value = $val;
			$conf->save();
		}

		// Write new config.ini
		$data = "[globals]\n";
		$data .= "db.host={$ini['db.host']}\n";
		$data .= "db.user={$ini['db.user']}\n";
		$data .= "db.pass={$ini['db.pass']}\n";
		$data .= "db.name={$ini['db.name']}\n";
		file_put_contents($root.'/config.ini', $data);
	}

	/**
	 * Set a configuration value
	 * @param  string $key
	 * @param  mixed  $value
	 * @return Config
	 */
	public static function setVal($key, $value) {
		$f3 = \Base::instance();
		$f3->set($key, $value);
		$item = new static();
		$item->load(array('attribute = ?', $key));
		$item->attribute = $attribute;
		$item->value = $value;
		$item->save();
		return $item;
	}

}

