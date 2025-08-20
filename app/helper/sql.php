<?php

namespace Helper;

class SQL extends \DB\SQL
{
    /**
     * Cast value to PHP type, explicitly casting float values
     * @return mixed
     * @param $type string
     * @param $val mixed
     **/
    public function value($type, $val)
    {
        if ($type == self::PARAM_FLOAT) {
            $val = (float)$val;
        }
        return parent::value($type, $val);
    }
}
