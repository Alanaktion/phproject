<?php

namespace Model;

class Custom extends \Model
{
    /**
     * Creates a custom model from a specified table name
     * @param string $_table_name
     */
    public function __construct(protected $_table_name)
    {
        parent::__construct();
    }
}
