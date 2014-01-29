<?php

namespace Model;

class Issue extends Base {

	protected $_table_name = "issue";

	public function hierarchy() {
		$issues = array();
		$issues[] = $this->cast();
		$parent_id = $this->parent_id;
		while($parent_id) {
			$issue = new Issue();
			$issue->load($parent_id);
			$issues[] = $issue->cast();
			$parent_id = $issue->parent_id;
		}

		return array_reverse($issues);
	}

	public static function clean($string) {
		return preg_replace('/(?:(?:\r\n|\r|\n)\s*){2}/s', "\n\n", str_replace("\r\n", "\n", $string));
	}

	// Log issue update, send notifications
	public function save($notify = true) {
		$f3 = \Base::instance();
		if($this->query) {

			// Log update
			$update = new \Model\Issue\Update();
			$update->issue_id = $this->id;
			$update->user_id = $f3->get("user.id");
			$update->created_date = now();
			$update->save();

			// Log updated fields
			foreach ($this->fields as $key=>$field) {
				if ($field["changed"] && $field["value"] != $this->get_prev($key)) {
					$update_field = new \Model\Issue\Update\Field();
					$update_field->issue_update_id = $update->id;
					$update_field->field = $key;
					$update_field->old_value = $this->get_prev($key);
					$update_field->new_value = $field["value"];
					$update_field->save();
				}
			}

			// Send notifications
			if($notify) {
				$notification = \Helper\Notification::instance();
				$notification->issue_update($this->id, $update->id);
			}

		} else {
			// TODO: New issue notififcation
		}

		return parent::save();
	}

}

