<?php

namespace Model;

class User extends \Model {

	protected
		$_table_name = "user",
		$_groupUsers = null;

	/**
	 * Load currently logged in user, if any
	 * @return mixed
	 */
	public function loadCurrent() {
		$f3 = \Base::instance();

		// Load current session
		$session = new \Model\Session;
		$session->loadCurrent();

		// Load user
		if($session->user_id) {
			$this->load(array("id = ? AND deleted_date IS NULL", $session->user_id));
			if($this->id) {
				$f3->set("user", $this->cast());
				$f3->set("user_obj", $this);

				// Change default language if user has selected one
				if($this->exists("language") && $this->language) {
					$f3->set("LANGUAGE", $this->language);
				}

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
		if(!$this->id) {
			return false;
		}
		if($this->get("avatar_filename") && is_file("uploads/avatars/" . $this->get("avatar_filename"))) {
			return "/avatar/$size-" . $this->id . ".png";
		}
		return \Helper\View::instance()->gravatar($this->get("email"), $size);
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
	 * Get all users within a group
	 * @return array|NULL
	 */
	public function getGroupUsers() {
		if($this->role == "group") {
			if($this->_groupUsers !== null) {
				return $this->_groupUsers;
			}
			$ug = new User\Group;
			$users = $ug->find(array("group_id = ?", $this->id));
			$user_ids = array();
			foreach($users as $user) {
				$user_ids[] = $user->user_id;
			}
			return $this->_groupUsers = $user_ids ? $this->find("id IN (" . implode(",", $user_ids) . ") AND deleted_date IS NULL") : array();
		} else {
			return null;
		}
	}

	/**
	 * Send an email alert with issues due on the given date
	 * @param  string $date
	 * @return bool
	 */
	public function sendDueAlert($date = '') {
		if(!$this->id) {
			return false;
		}

		if(!$date) {
			$date = date("Y-m-d", \Helper\View::instance()->utc2local());
		}

		$issue = new \Model\Issue;
		$issues = $issue->find(array("due_date = ? AND owner_id = ? AND closed_date IS NULL AND deleted_date IS NULL", $date, $this->id), array("order" => "priority DESC"));

		if($issues) {
			$notif = new \Helper\Notification;
			return $notif->user_due_issues($this, $issues);
		} else {
			return false;
		}
	}

	/**
	 * Get user statistics
	 * @param  int $time  The lower limit on timestamps for stats collection
	 * @return array
	 */
	public function stats($time = 0) {
		\Helper\View::instance()->utc2local();
		$offset = \Base::instance()->get("site.timeoffset");

		if(!$time) {
			$time = strtotime("-2 weeks", time() + $offset);
		}

		$result = array();
		$result["spent"] = $this->db->exec(
			"SELECT DATE(DATE_ADD(u.created_date, INTERVAL :offset SECOND)) AS `date`, SUM(f.new_value - f.old_value) AS `val`
			FROM issue_update u
			JOIN issue_update_field f ON u.id = f.issue_update_id AND f.field = 'hours_spent'
			WHERE u.user_id = :user AND u.created_date > :date
			GROUP BY DATE(DATE_ADD(u.created_date, INTERVAL :offset2 SECOND))",
			array(":user" => $this->id, ":offset" => $offset, ":offset2" => $offset, ":date" => date("Y-m-d H:i:s", $time))
		);
		$result["closed"] = $this->db->exec(
			"SELECT DATE(DATE_ADD(i.closed_date, INTERVAL :offset SECOND)) AS `date`, COUNT(*) AS `val`
			FROM issue i
			WHERE i.owner_id = :user AND i.closed_date > :date
			GROUP BY DATE(DATE_ADD(i.closed_date, INTERVAL :offset2 SECOND))",
			array(":user" => $this->id, ":offset" => $offset, ":offset2" => $offset, ":date" => date("Y-m-d H:i:s", $time))
		);
		$result["created"] = $this->db->exec(
			"SELECT DATE(DATE_ADD(i.created_date, INTERVAL :offset SECOND)) AS `date`, COUNT(*) AS `val`
			FROM issue i
			WHERE i.author_id = :user AND i.created_date > :date
			GROUP BY DATE(DATE_ADD(i.created_date, INTERVAL :offset2 SECOND))",
			array(":user" => $this->id, ":offset" => $offset, ":offset2" => $offset, ":date" => date("Y-m-d H:i:s", $time))
		);

		$dates = $this->_createDateRangeArray(date("Y-m-d", $time), date("Y-m-d", time() + $offset));
		$return = array(
			"labels" => array(),
			"spent" => array(),
			"closed" => array(),
			"created" => array()
		);

		foreach($result["spent"] as $r) {
			$return["spent"][$r["date"]] = floatval($r["val"]);
		}
		foreach($result["closed"] as $r) {
			$return["closed"][$r["date"]] = intval($r["val"]);
		}
		foreach($result["created"] as $r) {
			$return["created"][$r["date"]] = intval($r["val"]);
		}

		foreach($dates as $date) {
			$return["labels"][$date] = date("D j", strtotime($date));
			if(!isset($return["spent"][$date])) {
				$return["spent"][$date] = 0;
			}
			if(!isset($return["closed"][$date])) {
				$return["closed"][$date] = 0;
			}
			if(!isset($return["created"][$date])) {
				$return["created"][$date] = 0;
			}
		}

		foreach($return as &$r) {
			ksort($r);
		}

		return $return;
	}

	/**
	 * Reassign assigned issues
	 * @param  int $user_id
	 * @return int Number of issues affected
	 */
	public function reassignIssues($user_id) {
		if(!$this->id) {
			throw new \Exception("User is not initialized.");
		}
		$issue_model = new \Model\Issue;
		$issues = $issue_model->find(array("owner_id = ? AND deleted_date IS NULL AND closed_date IS NULL", $this->id));
		foreach($issues as $issue) {
			$issue->owner_id = $user_id;
			$issue->save();
		}
		return count($issues);
	}

	public function date_picker() {
		return (object) array('language'=>$this->language, 'js'=>($this->language != 'en'));
	}
}

