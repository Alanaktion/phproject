<?php

namespace Model;

abstract class Base extends \DB\SQL\Mapper {

	protected $fields = array();

	function __construct($table_name = null) {
		$f3 = \Base::instance();

		if(empty($this->_table_name)) {
			if(empty($table_name)) {
				$f3->error(500, "Model instance does not have a table name specified.");
			} else {
				$this->_table_name = $table_name;
			}
		}

		$table = $f3->get("db.prefix") ? $f3->get("db.prefix") . $this->_table_name : $this->_table_name;

		parent::__construct($f3->get("db.instance"), $table, null, $f3->get("cache_expire.db"));
		return $this;
	}

	/**
	 * Set object created date if possible
	 * @return mixed
	 */
	function save() {
		if(array_key_exists("created_date", $this->fields) && !$this->query && !$this->get("created_date")) {
			$this->set("created_date", now());
		}
		return parent::save();
	}

	/**
	 * Safely delete object if possible, if not, erase the record.
	 * @return mixed
	 */
	function delete() {
		if(array_key_exists("deleted_date", $this->fields)) {
			$this->deleted_date = now();
			return $this->save();
		} else {
			return $this->erase();
		}
	}

	/**
	 * Load by ID directly if a string is passed
	 * @param  string|array  $filter
	 * @param  array         $options
	 * @param  integer       $ttl
	 * @return mixed
	 */
	function load($filter=NULL, array $options=NULL, $ttl=0) {
		if(is_numeric($filter)) {
			return parent::load(array("id = ?", $filter), $options, $ttl);
		} else {
			return parent::load($filter, $options, $ttl);
		}
	}

	/**
	 * Get most recent value of field
	 * @param  string $key
	 * @return mixed
	 */
	protected function get_prev($key) {
		if(!$this->query) {
			return null;
		}
		$prev_fields = $this->query[count($this->query) - 1]->fields;
		return array_key_exists($key, $prev_fields) ? $prev_fields[$key]["value"] : null;
	}

}
