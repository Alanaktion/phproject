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
 * @property ?string $deleted_date
 */
class File extends \Model
{
    protected $_table_name = "issue_file";

    protected static $requiredFields = ["issue_id", "user_id", "filename", "disk_filename"];

    /**
     * Get the issue this file is attached to
     */
    public function issue(): \Model\Issue
    {
        $issue = new \Model\Issue();
        if ($this->issue_id) {
            $issue->load($this->issue_id);
        }

        return $issue;
    }

    /**
     * Check whether a user is allowed to access this file, based on access to
     * the issue it is attached to
     */
    public function allowAccess(?\Model\User $user = null): bool
    {
        if (!$this->id) {
            return false;
        }

        $issue = $this->issue();
        return $issue->id && $issue->allowAccess($user);
    }

    /**
     * Create and save a new file, optionally sending notifications
     */
    public static function create(array $data, bool $notify = true): static
    {
        /** @var static $item */
        $item = parent::create($data);
        if ($notify) {
            $notification = \Helper\Notification::instance();
            $notification->issue_file($item->issue_id, $item->id);
        }

        return $item;
    }
}
