<?php

namespace Helper;

class Update extends \Prefab {

	/**
	 * Generate human-readable data for issue updates
	 * @param  string $field
	 * @param  string|int $old_val
	 * @param  string|int $new_val
	 * @return array
	 */
	function humanReadableValues($field, $old_val, $new_val) {
		switch($field) {
			case "owner_id":
			case "author_id":
				// Load old and new users and get names
				$user = new \Model\User;
				if(is_numeric($old_val)) {
					$user->load($old_val);
					$old_val = $user->name;
				}
				if(is_numeric($new_val)) {
					$user->load($new_val);
					$new_val = $user->name;
				}
				break;
			case "status":
				// Load old and new statuses and get names
				$status = new \Model\Issue\Status;
				if(is_numeric($old_val)) {
					$status->load($old_val);
					$old_val = $status->name;
				}
				if(is_numeric($new_val)) {
					$status->load($new_val);
					$new_val = $status->name;
				}
				break;
			case "priority":
				// Load old and new priorities and get names
				$priority = new \Model\Issue\Priority;
				if(is_numeric($old_val)) {
					$priority->load(array("value = ?", $old_val));
					$old_val = $priority->name;
				}
				if(is_numeric($new_val)) {
					$priority->load(array("value = ?", $new_val));
					$new_val = $priority->name;
				}
				break;
			case "parent_id":
				// Load old and new parent issues and get names
				$name = "Parent";
				$issue = new \Model\Issue;
				if(is_numeric($old_val)) {
					$issue->load($old_val);
					$old_val = $issue->name;
				}
				if(is_numeric($new_val)) {
					$issue->load($new_val);
					$new_val = $issue->name;
				}
				break;
			case "sprint_id":
				// Load old and new sprints and get names and dates
				$name = "Sprint";
				$sprint = new \Model\Sprint;
				if(is_numeric($old_val)) {
					$sprint->load($old_val);
					$old_val = $sprint->name . " - " . date('n/j', strtotime($sprint->start_date)) . "-" . date('n/j', strtotime($sprint->end_date));
				}
				if(is_numeric($new_val)) {
					$sprint->load($new_val);
					$new_val = $sprint->name . " - " . date('n/j', strtotime($sprint->start_date)) . "-" . date('n/j', strtotime($sprint->end_date));
				}
				break;
			case "type_id":
				// Load old and new issue types and get names
				$name = "Type";
				$type = new \Model\Issue\Type;
				if(is_numeric($old_val)) {
					$type->load($old_val);
					$old_val = $type->name;
				}
				if(is_numeric($new_val)) {
					$type->load($new_val);
					$new_val = $type->name;
				}
				break;
			case "hours_total":
				$name = "Planned Hours";
				break;
			case "hours_remaining":
				$name = "Remaining Hours";
				break;
			case "hours_spent":
				$name = "Spent Hours";
				break;
		}

		// Generate human readable field name if not already specified
		if(empty($name)) {
			$name = ucwords(str_replace(array("_", " id"), array(" ", ""), $field));
		}

		return array("field" => $name, "old" => $old_val, "new" => $new_val);
	}

}
