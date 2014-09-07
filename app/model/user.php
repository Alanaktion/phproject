<?php

namespace Model;

class User extends Base {

	protected $_table_name = "user";

	/**
	 * Load currently logged in user, if any
	 * @return mixed
	 */
	public function loadCurrent() {
		$f3 = \Base::instance();
		if($user_id = $f3->get("SESSION.phproject_user_id")) {
			$this->load(array("id = ? AND deleted_date IS NULL", $user_id));
			if($this->id) {
				$f3->set("user", $this->cast());
				$f3->set("user_obj", $this);
			}
		}
		return $this;
	}

	/**
	 * Get path to user's avatar or gravatar
	 * @param  integer $size
	 * @return string|bool
	 */
	public function avatar($size = 80) {
		$f3 = \Base::instance();
		if(!$this->get("id")) {
			return false;
		}
		if($this->get("avatar_filename") && is_file($f3->get("ROOT") . "/uploads/avatars/" . $this->get("avatar_filename"))) {
			return "/avatar/$size-" . $this->get("id") . ".png";
		}
		return gravatar($this->get("email"), $size);
	}

	/**
	 * Load all active users
	 * @return array
	 */
	public function getAll() {
		return $this->find("deleted_date IS NULL AND role != 'group'", array("order" => "name ASC"));
	}

	/**
	 * Load all active groups
	 * @return array
	 */
	public function getAllGroups() {
		return $this->find("deleted_date IS NULL AND role = 'group'", array("order" => "name ASC"));
	}

	/**
	 * Send an email alert with issues due on the given date
	 * @param  string $date
	 * @return bool
	 */
	public function sendDueAlert($date = '') {
		if(!$this->get("id")) {
			return false;
		}

		if(!$date) {
			$date = now(false, false);
		}

		$issue = new \Model\Issue;
		$issues = $issue->find(array("due_date = ? AND owner_id = ? AND closed_date IS NULL AND deleted_date IS NULL", $date, $this->get("id")), array("order" => "priority DESC"));

		if($issues) {
			$notif = new \Helper\Notification;
			return $notif->user_due_issues($this, $issues);
		} else {
			return false;
		}
	}

}

