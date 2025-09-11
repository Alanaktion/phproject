<?php

namespace Helper;

class SQL extends \F3\DB\SQL
{
    /**
     * Cast value to PHP type, explicitly casting float values
     * @return mixed
     * @param $type string
     * @param $val mixed
     **/
    public function value(string|int $type, mixed $val): mixed
    {
        if ($type == self::PARAM_FLOAT) {
            $val = (float)$val;
        }
        return parent::value($type, $val);
    }
}
