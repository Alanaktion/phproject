<?php

namespace Model\Issue;

/**
 * Class Type
 *
 * @property int $id
 * @property int $sprint_id
 * @property string $issues
 */
class Backlog extends \Model
{
    protected $_table_name = "issue_backlog";
}
