<?php

namespace Model\Issue;

/**
 * @property-read ?string $sprint_name
 * @property-read ?string $sprint_start_date
 * @property-read ?string $sprint_end_date
 * @property-read string $type_name
 * @property-read string $status_name
 * @property-read int $status_closed
 * @property-read int $priority_id
 * @property-read string $priority_name
 * @property-read ?string $author_username
 * @property-read string $author_name
 * @property-read ?string $author_email
 * @property-read ?string $author_task_color
 * @property-read ?string $owner_username
 * @property-read ?string $owner_name
 * @property-read ?string $owner_email
 * @property-read ?string $owner_task_color
 * @property-read ?string $parent_name
 */
class Detail extends \Model\Issue
{
    protected $_table_name = "issue_detail";

    public $children = [];
}
