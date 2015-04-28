<?php

namespace Model;

class Config extends \Model {

	protected $_table_name = "config";

	/**
	 * Loads the configuration for the site
	 */
	public static function loadAll() {
		$f3 = \Base::instance();
		$db = $f3->get("db.instance");
		$result = $db->exec("SELECT attribute,value FROM config");
		foreach($result as $item) {
			$f3->set($item["attribute"], $item["value"]);
		}
	}

}

