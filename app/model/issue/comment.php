<?php

namespace Model\Issue;

class Comment extends \Model {

	protected $_table_name = "issue_comment";
	protected static $requiredFields = array("issue_id", "user_id", "text");

	/**
	 * Create and save a new comment
	 * @param  array $data
	 * @param  bool  $notify
	 * @return Comment
	 */
	public static function create(array $data, $notify = true) {
		if(empty($data['text'])) {
			throw new \Exception("Comment text cannot be empty.");
		}
		$item = parent::create($data);
		if($notify) {
			$notification = \Helper\Notification::instance();
			$notification->issue_comment($item->issue_id, $item->id);
		}
		return $item;
	}

	/**
	 * Save the comment
	 * @return Comment
	 */
	public function save() {

		// Censor credit card numbers if enabled
		if(\Base::instance()->get("security.block_ccs") && preg_match("/[0-9-]{9,15}[0-9]{4}/", $this->get("text"))) {
			$this->set("text", preg_replace("/[0-9-]{9,15}([0-9]{4})/", "************$1", $this->get("text")));
		}

		return parent::save();
	}

}

