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
				// Do stuff
				break;
		}
		return array("old" => $old_val, "new" => $new_val);
	}

}
