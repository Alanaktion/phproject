<?php

namespace Model\User;

class Notification extends \Model {

	protected $_table_name = "user_notification";
	protected static $requiredFields = array("user_id", "issue_id", "text");

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

	/**
	 * Mark notifications as read by issue ID
	 * @param  int $issue_id
	 * @param  int $user_id
	 * @return Notification
	 */
	public function markRead($issue_id, $user_id = null) {
		if($user_id === null) {
			$user_id = \Base::instance()->get('user.id');
		}
		$this->db->exec(
			"UPDATE {$this->_table_name} SET read_date = :date WHERE issue_id = :issue AND user_id = :user",
			array(":date" => date("Y-m-d H:i:s"), ":issue" => $issue_id, ":user" => $user_id)
		);
		return $this;
	}

}
