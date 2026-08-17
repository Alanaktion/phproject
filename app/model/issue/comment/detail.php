<?php

namespace Model\Issue\Comment;

/**
 * @property-read ?string $user_username
 * @property-read ?string $user_email
 * @property-read string $user_name
 * @property-read ?string $user_role
 * @property-read ?string $user_task_color
 * @property-read ?string $file_filename
 * @property-read ?int $file_filesize
 * @property-read ?string $file_content_type
 * @property-read ?int $file_downloads
 * @property-read ?string $file_created_date
 * @property-read ?string $file_deleted_date
 * @property-read ?string $issue_deleted_date
 */
class Detail extends \Model\Issue\Comment
{
    protected $_table_name = "issue_comment_detail";
}
