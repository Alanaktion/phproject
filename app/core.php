<?php

abstract class Controller {

	/**
	 * Require a user to be logged in. Redirects to /login if a session is not found.
	 * @return int|bool
	 */
	protected function _requireLogin() {
		$f3 = \Base::instance();
		if($id = $f3->get("user.id")) {
			return $id;
		} else {
			if(empty($_GET)) {
				$f3->reroute("/login?to=" . urlencode($f3->get("PATH")));
			} else {
				$f3->reroute("/login?to=" . urlencode($f3->get("PATH")) . urlencode("?" . http_build_query($_GET)));
			}
			$f3->unload();
			return false;
		}
	}

	/**
	 * Require a user to be an administrator. Throws HTTP 403 if logged in, but not an admin.
	 * @return int|bool
	 */
	protected function _requireAdmin() {
		$id = $this->_requireLogin();

		$f3 = \Base::instance();
		if($f3->get("user.role") == "admin") {
			return $id;
		} else {
			$f3->error(403);
			$f3->unload();
			return false;
		}
	}

}

abstract class Model extends \DB\SQL\Mapper {

	protected $fields = array();

	function __construct($table_name = null) {
		$f3 = \Base::instance();

		if(empty($this->_table_name)) {
			if(empty($table_name)) {
				$f3->error(500, "Model instance does not have a table name specified.");
			} else {
				$this->table_name = $table_name;
			}
		}

		parent::__construct($f3->get("db.instance"), $this->_table_name, null, $f3->get("cache_expire.db"));
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
