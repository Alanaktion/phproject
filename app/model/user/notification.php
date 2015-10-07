<?php

namespace Model\User;

class Notification extends \Model {

	protected $_table_name = "user_notification";
	protected static $requiredFields = array("user_id", "issue_id");

	/**
	 * Find all unread notifications by user ID
	 * @param  int $user_id
	 * @param  int $limit
	 * @return array
	 */
	public function findUnread($user_id = null, $limit = 15) {
		if($user_id === null) {
			$user_id = \Base::instance()->get('user.id');
		}
		return $this->find(array("user_id = ? AND read_date IS NULL", $user_id), array("order" => "created_date DESC", "limit" => $limit));
	}

}
