<?php

namespace Model\Issue;

/**
 * Class Comment
 *
 * @property int $id
 * @property int $issue_id
 * @property int $user_id
 * @property string $text
 * @property int $file_id
 * @property string $created_date
 */
class Comment extends \Model
{
    protected $_table_name = "issue_comment";
    protected static $requiredFields = ["issue_id", "user_id", "text"];

    /**
     * Create and save a new comment
     * @param  array $data
     * @param  bool  $notify
     * @return Comment
     * @throws \Exception
     */
    public static function create(array $data, bool $notify = true): Comment
    {
        if (empty($data['text'])) {
            throw new \Exception("Comment text cannot be empty.");
        }
        /** @var Comment $item */
        $item = parent::create($data);
        if ($notify) {
            $notification = \Helper\Notification::instance();
            $notification->issue_comment($item->issue_id, $item->id);
        }
        return $item;
    }

    /**
     * Save the comment
     * @return Comment
     */
    public function save(): Comment
    {
        // Censor credit card numbers if enabled
        if (\Base::instance()->get("security.block_ccs") && preg_match("/[0-9-]{9,15}[0-9]{4}/", $this->get("text"))) {
            $this->set("text", preg_replace("/[0-9-]{9,15}([0-9]{4})/", "************$1", $this->get("text")));
        }

        return parent::save();
    }
}
