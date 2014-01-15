<?php

namespace Model;

class Custom extends Base {

	protected $_table_name;

	public function __construct($table_name) {
		$this->_table_name = $table_name;
		parent::__construct();
		return $this;
	}

}

