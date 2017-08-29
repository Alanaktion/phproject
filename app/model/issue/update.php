<?php

namespace Model\Issue;

/**
 * Class Update
 *
 * @property int $id
 * @property int $issue_id
 * @property int $user_id
 * @property string $created_date
 * @property int $comment_id
 * @property int $notify
 */
class Update extends \Model
{
    protected $_table_name = "issue_update";
}
