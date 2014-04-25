<?php

namespace Helper;

class Update extends \Prefab {

	function humanReadableValues($field, $old_val, $new_val) {
		switch($field) {
			case "owner_id":
			case "author_id":
				// Load old and new users and get names
				$user = new \Model\User;
				$user->load($old_val);
				$old_val = $user->name;
				$user->load($new_val);
				$new_val = $user->name;
				break;
			case "status":
				// Load old and new statuses and get names
				$status = new \Model\Issue\Status;
				$status->load($old_val);
				$old_val = $status->name;
				$status->load($new_val);
				$new_val = $status->name;
				break;
			case "priority":
				// Load old and new priorities and get names
				$priority = new \Model\Issue\Priority;
				$priority->load(array("value = ?", $old_val));
				$old_val = $priority->name;
				$priority->load(array("value = ?", $new_val));
				$new_val = $priority->name;
				break;
			case "parent_id":
				$name = "Parent";
				$issue = new \Model\Issue;
				$issue->load($old_val);
				$old_val = $issue->name;
				$issue->load($new_val);
				$new_val = $issue->name;
			case "sprint_id":
				$name = "Sprint";
				$issue = new \Model\sprint;
				$issue->load($old_val);
				$old_val = $issue->name;
				$issue->load($new_val);
				$new_val = $issue->name." - ". date('n/j', strtotime($issue->start_date))."-".date('n/j', strtotime($issue->end_date));
		}

		// Generate human readable field name if not already specified
		if(empty($name)) {
			$name = ucwords(str_replace("_", " ", $field));
		}

		return array("field" => $name, "old" => $old_val, "new" => $new_val);
	}

}
