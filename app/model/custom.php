<?php

namespace Model;

class Custom extends \Model
{
    protected $_table_name;

    /**
     * Creates a custom model from a specified table name
     * @param string $tableName
     */
    public function __construct(string $tableName)
    {
        $this->_table_name = $tableName;
        parent::__construct();
    }
}
