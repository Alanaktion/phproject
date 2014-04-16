<?php

namespace Helper;

class Update extends \Prefab {

	function humanReadableValues($field, $old_val, $new_val) {
		switch($field) {
			case "owner_id":
			case "author_id":
				// Load old and new user and get names
				$user = new \Model\User;
				$user->load($old_val);
				$old_val = $user->name;
				$user->load($new_val);
				$new_val = $user->name;
				break;
			case "status":
				// Load old and new status and get names
				$status = new \Model\Issue\Status;
				$status->load($old_val);
				$old_val = $status->name;
				$status->load($new_val);
				$new_val = $status->name;
				break;
			case "priority":
				// Load old and new priority and get names
				$priority = new \Model\Issue\Priority;
				$priority->load(array("value = ?", $old_val));
				$old_val = $priority->name;
				$priority->load(array("value = ?", $new_val));
				$new_val = $priority->name;
				break;
		}
		return array("old" => $old_val, "new" => $new_val);
	}

}
