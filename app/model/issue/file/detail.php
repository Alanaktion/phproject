<?php

namespace Model\Issue\File;

/**
 * @property-read ?string $user_username
 * @property-read ?string $user_email
 * @property-read string $user_name
 */
class Detail extends \Model\Issue\File
{
    protected $_table_name = "issue_file_detail";
}
