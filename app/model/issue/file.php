<?php

namespace Model\Issue;

class File extends \Model {

	protected $_table_name = "issue_file";
	protected static $requiredFields = array("issue_id", "user_id", "filename", "disk_filename");

	/**
	 * Create and save a new file, optionally sending notifications
	 * @param  array $data
	 * @param  bool  $notify
	 * @return Comment
	 */
	public static function create(array $data, $notify = true) {
		$item = parent::create($data);
		if($notify) {
			$notification = \Helper\Notification::instance();
			$notification->issue_file($item->issue_id, $item->id);
		}
		return $item;
	}

}
