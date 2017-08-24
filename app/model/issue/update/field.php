<?php

namespace Model\Issue\Update;

/**
 * Class Field
 *
 * @property int $id
 * @property int $issue_update_id
 * @property string $field
 * @property string $old_value
 * @property string $new_value
 */
class Field extends \Model
{
    protected $_table_name = "issue_update_field";
}
