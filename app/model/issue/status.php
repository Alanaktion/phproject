<?php

namespace Model\Issue;

/**
 * Class Status
 *
 * @property int $id
 * @property string $name
 * @property int $closed
 * @property int $taskboard
 */
class Status extends \Model
{
    protected $_table_name = "issue_status";
}
