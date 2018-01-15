<?php

use PHPUnit\Framework\TestCase;

class SprintModelTest extends TestCase
{
    public function testGetFirstWeekday()
    {
        $sprint = new \Model\Sprint;
        $sprint->end_date = '2018-01-31';
        $sprint->start_date = '2018-01-01';
        $this->assertEquals('2018-01-01', $sprint->getFirstWeekday());
        $sprint->start_date = '2018-01-03';
        $this->assertEquals('2018-01-03', $sprint->getFirstWeekday());
        $sprint->start_date = '2018-01-06';
        $this->assertEquals('2018-01-08', $sprint->getFirstWeekday());
    }

    public function testGetLastWeekday()
    {
        $sprint = new \Model\Sprint;
        $sprint->start_date = '2018-01-01';
        $sprint->end_date = '2018-01-05';
        $this->assertEquals('2018-01-05', $sprint->getLastWeekday());
        $sprint->end_date = '2018-01-07';
        $this->assertEquals('2018-01-05', $sprint->getLastWeekday());
        $sprint->end_date = '2018-01-09';
        $this->assertEquals('2018-01-09', $sprint->getLastWeekday());
    }
}
