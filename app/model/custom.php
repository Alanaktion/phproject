<?php

namespace Model;

class Custom extends \Model
{
    /**
     * Creates a custom model from a specified table name
     */
    public function __construct(protected string $_table_name)
    {
        parent::__construct();
    }
}
