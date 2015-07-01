<?php

namespace Model;

class Issue extends \Model {

	protected
	$_table_name = "issue",
	$_heirarchy = null,
	$_children = null;
	protected static $requiredFields = array("type_id", "status", "name", "author_id");

	/**
	 * Create and save a new comment
	 * @param  array $data
	 * @param  bool  $notify
	 * @return Comment
	 */
	public static function create(array $data, $notify = true) {
		// Normalize data
		if (isset($data["hours"])) {
			$data["hours_total"] = $data["hours"];
			$data["hours_remaining"] = $data["hours"];
			unset($data["hours"]);
		}
		if (!empty($data["due_date"])) {
			if (!preg_match("/[0-9]{4}(-[0-9]{2}){2}/", $data["due_date"])) {
				$data["due_date"] = date("Y-m-d", strtotime($data["due_date"]));
			}
			if (empty($data["sprint_id"])) {
				$sprint = new Sprint();
				$sprint->load(array("DATE(?) BETWEEN start_date AND end_date", $issue->due_date));
				$data["sprint_id"] = $sprint->id;
			}
		}
		if (empty($data["author_id"]) && $user_id = \Base::instance()->get("user.id")) {
			$data["author_id"] = $user_id;
		}

		// Create issue
		$item = parent::create($data);

		// Send creation notifications
		if ($notify) {
			$notification = \Helper\Notification::instance();
			$notification->issue_create($item->id);
		}

		// Return instance
		return $item;
	}

	/**
	 * Get complete parent list for issue
	 * @return array
	 */
	public function getAncestors() {
		if ($this->_heirarchy !== null) {
			return $this->_heirarchy;
		}

		$issues = array();
		$issues[] = $this;
		$issue_ids = array($this->get("id"));
		$parent_id = $this->parent_id;
		while ($parent_id) {
			// Catch infinite loops early on, in case server isn't running linux :)
			if (in_array($parent_id, $issue_ids)) {
				$f3 = \Base::instance();
				$f3->set("error", "Issue parent tree contains an infinite loop. Issue {$parent_id} is the first point of recursion.");
				break;
			}
			$issue = new Issue();
			$issue->load($parent_id);
			if ($issue->id) {
				$issues[] = $issue;
				$parent_id = $issue->parent_id;
				$issue_ids[] = $issue->id;
			} else {
				// Handle nonexistent issues
				$f3 = \Base::instance();
				$f3->set("error", "Issue #{$issue->id} has a parent issue #{$issue->parent_id} that doesn't exist.");
				break;
			}
		}

		$this->_heirarchy = array_reverse($issues);
		return $this->_heirarchy;
	}

	/**
	 * Remove messy whitespace from a string
	 * @param  string $string
	 * @return string
	 */
	public static function clean($string) {
		return preg_replace('/(?:(?:\r\n|\r|\n)\s*){2}/s', "\n\n", str_replace("\r\n", "\n", $string));
	}

	/**
	 * Delete without sending notification
	 * @param  bool $recursive
	 * @return Issue
	 */
	public function delete($recursive = true) {
		if (!$this->get("deleted_date")) {
			$this->set("deleted_date", date("Y-m-d H:i:s"));
		}
		if ($recursive) {
			$this->_deleteTree();
		}
		return $this->save(false);
	}

	/**
	 * Delete a complete issue tree
	 * @return Issue
	 */
	protected function _deleteTree() {
		$children = $this->find(array("parent_id = ?", $this->get("id")));
		foreach ($children as $child) {
			$child->delete();
		}
		return $this;
	}

	/**
	 * Restore a deleted issue without notifying
	 * @param  bool $recursive
	 * @return Issue
	 */
	public function restore($recursive = true) {
		$this->set("deleted_date", null);
		if ($recursive) {
			$this->_restoreTree();
		}
		return $this->save(false);
	}

	/**
	 * Restore a complete issue tree
	 * @return Issue
	 */
	protected function _restoreTree() {
		$children = $this->find(array("parent_id = ? AND deleted_date IS NOT NULL", $this->get("id")));
		foreach ($children as $child) {
			$child->restore();
		}
		return $this;
	}

	/**
	 * Log and save an issue update
	 * @param  boolean $notify
	 * @return Issue\Update
	 */
	protected function _saveUpdate($notify = true) {
		$f3 = \Base::instance();

		// Ensure issue is not tied to itself as a parent
		if ($this->get("id") == $this->get("parent_id")) {
			$this->set("parent_id", $this->_getPrev("parent_id"));
		}

		// Log update
		$update = new \Model\Issue\Update();
		$update->issue_id = $this->id;
		$update->user_id = $f3->get("user.id");
		$update->created_date = date("Y-m-d H:i:s");
		if ($f3->exists('update_comment')) {
			$update->comment_id = $f3->get('update_comment')->id;
			if ($notify) {
				$update->notify = 1;
			}
		} else {
			$update->notify = 0;
		}
		$update->save();

		// Set hours_total to the hours_remaining value if it's 0 or null
		if ($this->get("hours_remaining") && !$this->get("hours_total")) {
			$this->set("hours_total", $this->get("hours_remaining"));
		}

		// Set hours remaining to 0 if the issue has been closed
		if ($this->get("closed_date") && $this->get("hours_remaining")) {
			$this->set("hours_remaining", 0);
		}

		// Create a new issue if repeating
		if ($this->get("closed_date") && $this->get("repeat_cycle") && $this->get("repeat_cycle") != "none") {
			$repeat_issue = new \Model\Issue();
			$repeat_issue->name = $this->get("name");
			$repeat_issue->type_id = $this->get("type_id");
			$repeat_issue->parent_id = $this->get("parent_id");
			$repeat_issue->author_id = $this->get("author_id");
			$repeat_issue->owner_id = $this->get("owner_id");
			$repeat_issue->description = $this->get("description");
			$repeat_issue->priority = $this->get("priority");
			$repeat_issue->repeat_cycle = $this->get("repeat_cycle");
			$repeat_issue->hours_total = $this->get("hours_total");
			$repeat_issue->hours_remaining = $this->get("hours_total"); // Reset hours remaining to start hours
			$repeat_issue->created_date = date("Y-m-d H:i:s");

			// Find a due date in the future
			switch ($repeat_issue->repeat_cycle) {
				case 'daily':
					$repeat_issue->start_date = $this->get("start_date") ? date("Y-m-d", strtotime("tomorrow")) : NULL;
					$repeat_issue->due_date = date("Y-m-d", strtotime("tomorrow"));
					break;
				case 'weekly':
					$repeat_issue->start_date = $this->get("start_date") ? date("Y-m-d", strtotime($this->get("start_date") . " +1 week")) : NULL;
					$repeat_issue->due_date = date("Y-m-d", strtotime($this->get("due_date") . " +1 week"));
					break;
				case 'monthly':
					$repeat_issue->start_date = $this->get("start_date") ? date("Y-m-d", strtotime($this->get("start_date") . " +1 month")) : NULL;
					$repeat_issue->due_date = date("Y-m-d", strtotime($this->get("due_date") . " +1 month"));
					break;
				case 'sprint':
					$sprint = new \Model\Sprint();
					$sprint->load(array("start_date > NOW()"), array('order' => 'start_date'));
					$repeat_issue->start_date = $this->get("start_date") ? $sprint->start_date : NULL;
					$repeat_issue->due_date = $sprint->end_date;
					break;
				default:
					$repeat_issue->repeat_cycle = 'none';
			}

			// If the issue was in a sprint before, put it in a sprint again.
			if ($this->get("sprint_id")) {
				$sprint = new \Model\Sprint();
				$sprint->load(array("end_date >= ? AND start_date <= ?", $repeat_issue->due_date, $repeat_issue->due_date), array('order' => 'start_date'));
				$repeat_issue->sprint_id = $sprint->id;
			}

			$repeat_issue->save();
			$notification = \Helper\Notification::instance();
			$notification->issue_create($repeat_issue->id);
			$this->set("repeat_cycle", null);
		}

		// Log updated fields
		$updated = 0;
		$important_changes = 0;
		$important_fields = array('status', 'name', 'description', 'owner_id', 'priority', 'due_date');
		foreach ($this->fields as $key => $field) {
			if ($field["changed"] && $field["value"] != $this->_getPrev($key)) {
				$update_field = new \Model\Issue\Update\Field();
				$update_field->issue_update_id = $update->id;
				$update_field->field = $key;
				$update_field->old_value = $this->_getPrev($key);
				$update_field->new_value = $field["value"];
				$update_field->save();
				$updated++;
				if ($key == 'sprint_id') {
					$this->resetTaskSprints();
				}
				if (in_array($key, $important_fields)) {
					$important_changes++;
				}
			}
		}

		// Delete update if no fields were changed
		if (!$updated) {
			$update->delete();
		}

		// Set notify flag if important changes occurred
		if ($notify && $important_changes) {
			$update->notify = 1;
			$update->save();
		}

		// Send back the update
		return $update->id ? $update : false;

	}

	/**
	 * Log issue update, send notifications
	 * @param  boolean $notify
	 * @return Issue
	 */
	public function save($notify = true) {
		$f3 = \Base::instance();

		// Catch empty sprint at the lowest level here
		if ($this->get("sprint_id") === 0) {
			$this->set("sprint_id", null);
		}

		// Censor credit card numbers if enabled
		if ($f3->get("security.block_ccs")) {
			if (preg_match("/([0-9]{3,4}-){3}[0-9]{3,4}/", $this->get("description"))) {
				$this->set("description", preg_replace("/([0-9]{3,4}-){3}([0-9]{3,4})/", "************$2", $this->get("description")));
			}
		}

		// Make dates correct
		if ($this->due_date) {
			$this->due_date = date("Y-m-d", strtotime($this->due_date));
		} else {
			$this->due_date = null;
		}
		if ($this->start_date) {
			$this->start_date = date("Y-m-d", strtotime($this->start_date));
		} else {
			$this->start_date = null;
		}

		// Check if updating or inserting
		if ($this->query) {

			// Save issue updates and send notifications
			$update = $this->_saveUpdate($notify);
			$issue = parent::save();
			if ($notify && $update && $update->id && $update->notify) {
				$notification = \Helper\Notification::instance();
				$notification->issue_update($this->get("id"), $update->id);
			}

		} else {

			// Move task to a sprint if the parent is in a sprint
			if ($this->get("parent_id") && !$this->get("sprint_id")) {
				$parent = new \Model\Issue;
				$parent->load($this->get("parent_id"));
				if ($parent->sprint_id) {
					$this->set("sprint_id", $parent->sprint_id);
				}
			}

			// Save issue and send notifications
			$issue = parent::save();
			if ($notify) {
				$notification = \Helper\Notification::instance();
				$notification->issue_create($issue->id);
			}

			return $issue;
		}

		$this->saveTags();

		return empty($issue) ? parent::save() : $issue;
	}

	/**
	 * Finds and saves the current issue's tags
	 * @return Issue
	 */
	function saveTags() {
		$tag = new \Model\Issue\Tag;
		$issue_id = $this->get("id");
		$str = $this->get("description");
		$count = preg_match_all("/(?<=\W#|^#)[a-z][a-z0-9_-]*[a-z0-9]+(?=\W|$)/i", $str, $matches);
		$tag->deleteByIssueId($issue_id);
		if ($count) {
			foreach ($matches[0] as $match) {
				$tag->reset();
				$tag->tag = str_replace("_", "-", $match);
				$tag->issue_id = $issue_id;
				$tag->save();
			}
		}
		return $this;
	}

	/**
	 * Duplicate issue and all sub-issues
	 * @return Issue
	 */
	function duplicate() {
		if (!$this->get("id")) {
			return false;
		}

		$f3 = \Base::instance();

		$this->copyto("duplicating_issue");
		$f3->clear("duplicating_issue.id");
		$f3->clear("duplicating_issue.due_date");

		$new_issue = new Issue;
		$new_issue->copyfrom("duplicating_issue");
		$new_issue->clear("due_date");
		$new_issue->author_id = $f3->get("user.id");
		$new_issue->save();

		// Run the recursive function to duplicate the complete descendant tree
		$this->_duplicateTree($this->get("id"), $new_issue->id);

		return $new_issue;
	}

	/**
	 * Duplicate a complete issue tree, starting from a duplicated issue created by duplicate()
	 * @param  int $id
	 * @param  int $new_id
	 * @return Issue $this
	 */
	protected function _duplicateTree($id, $new_id) {
		// Find all child issues
		$children = $this->find(array("parent_id = ?", $id));
		if (count($children)) {
			$f3 = \Base::instance();
			foreach ($children as $child) {
				if (!$child->deleted_date) {
					// Duplicate issue
					$child->copyto("duplicating_issue");
					$f3->clear("duplicating_issue.id");
					$f3->clear("duplicating_issue.due_date");

					$new_child = new Issue;
					$new_child->copyfrom("duplicating_issue");
					$new_child->clear("id");
					$new_child->clear("due_date");
					$new_child->author_id = $f3->get("user.id");
					$new_child->set("parent_id", $new_id);
					$new_child->save(false);

					// Duplicate issue's children
					$this->_duplicateTree($child->id, $new_child->id);
				}
			}
		}
		return $this;
	}

	/**
	 * Move all non-project children to same sprint
	 * @return Issue $this
	 */
	public function resetTaskSprints($replace_existing = true) {
		$f3 = \Base::instance();
		if ($this->get("sprint_id")) {
			$query = "UPDATE issue SET sprint_id = :sprint WHERE parent_id = :issue AND type_id != :type";
			if ($replace_existing) {
				$query .= " AND sprint_id IS NULL";
			}
			$this->db->exec(
				$query,
				array(
					":sprint" => $this->get("sprint_id"),
					":issue" => $this->get("id"),
					":type" => $f3->get("issue_type.project"),
				)
			);
		}
		return $this;
	}

	/**
	 * Get children of current issue
	 * @return array
	 */
	public function getChildren() {
		if ($this->_children !== null) {
			return $this->_children;
		}

		return $this->_children ?: $this->_children = $this->find(array("parent_id = ? AND deleted_date IS NULL", $this->get("id")));
	}

	/**
	 * Generate MD5 hashes for each column in a key=>value array
	 * @return array
	 */
	public function hashState() {
		$result = $this->cast();
		foreach ($result as &$value) {
			$value = md5($value);
		}
		return $result;
	}

}
