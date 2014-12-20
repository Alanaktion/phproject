<?php

namespace Model;

class Custom extends \Model {

	protected $_table_name;

	/**
	 * Creates a custom model from a specified table name
	 * @param string $table_name
	 */
	public function __construct($table_name) {
		$this->_table_name = $table_name;
		parent::__construct();
		return $this;
	}

}

