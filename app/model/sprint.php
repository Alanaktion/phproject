<?php

namespace Model;

/**
 * Class Sprint
 *
 * @property int $id
 * @property string $name
 * @property string $start_date
 * @property string $end_date
 */
class Sprint extends \Model
{
    protected $_table_name = "sprint";

    public function getFirstWeekday()
    {
        $weekDay = date("w", strtotime($this->start_date));
        if ($weekDay == 0) {
            return date("Y-m-d", strtotime($this->start_date . " +1 day"));
        } elseif ($weekDay == 6) {
            return date("Y-m-d", strtotime($this->start_date . " +2 days"));
        }
        return $this->start_date;
    }

    public function getLastWeekday()
    {
        $weekDay = date("w", strtotime($this->end_date));
        if ($weekDay == 0) {
            return date("Y-m-d", strtotime($this->end_date . " -2 days"));
        } elseif ($weekDay == 6) {
            return date("Y-m-d", strtotime($this->end_date . " -1 day"));
        }
        return $this->end_date;
    }
}
