<?php

namespace Model;

abstract class Base extends \DB\SQL\Mapper {

	protected $fields = array();

	public function __construct() {
		$f3 = \Base::instance();

		if(empty($this->_table_name)) {
			$f3->error(500, "Model instance does not have a table name specified.");
		}

		parent::__construct($f3->get("db.instance"), $this->_table_name, null, 3600);
		return $this;
	}

	// Savely delete object if possible, if not, erase the record.
	public function delete() {
		if(array_key_exists("deleted_date", $this->fields)) {
			$this->deleted_date = now();
			return $this->save();
		} else {
			return $this->erase();
		}
	}

}
