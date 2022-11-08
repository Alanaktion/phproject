<?php

namespace Model\Issue;

/**
 * Class File
 *
 * @property int $id
 * @property int $issue_id
 * @property string $filename
 * @property string $disk_filename
 * @property string $disk_directory
 * @property int $filesize
 * @property string $content_type
 * @property string $digest
 * @property int $downloads
 * @property int $user_id
 * @property string $created_date
 * @property string $deleted_date
 */
class File extends \Model
{
    protected $_table_name = "issue_file";
    protected static $requiredFields = ["issue_id", "user_id", "filename", "disk_filename"];

    /**
     * Create and save a new file, optionally sending notifications
     * @param  array $data
     * @param  bool  $notify
     * @return File
     */
    public static function create(array $data, bool $notify = true): File
    {
        /** @var File $item */
        $item = parent::create($data);
        if ($notify) {
            $notification = \Helper\Notification::instance();
            $notification->issue_file($item->issue_id, $item->id);
        }
        return $item;
    }
}
