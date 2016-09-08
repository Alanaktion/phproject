<?php

namespace Model;

/**
 * Class Issue
 *
 * @property int $id
 * @property int $status
 * @property int $type_id
 * @property string $name
 * @property string $description
 * @property int $parent_id
 * @property int $author_id
 * @property int $owner_id
 * @property int $priority
 * @property float $hours_total
 * @property float $hours_remaining
 * @property float $hours_spent
 * @property string $created_date
 * @property string $closed_date
 * @property string $deleted_date
 * @property string $start_date
 * @property string $due_date
 * @property string $repeat_cycle
 * @property int $sprint_id
 */
class Issue extends \Model {

	protected
		$_table_name = "issue",
		$_heirarchy = null,
		$_children = null;
	protected static $requiredFields = array("type_id", "status", "name", "author_id");

	/**
	 * Create and save a new issue
	 * @param  array $data
	 * @param  bool  $notify
	 * @return Issue
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
				$sprint->load(array("DATE(?) BETWEEN start_date AND end_date", $data["due_date"]));
				$data["sprint_id"] = $sprint->id;
			}
		}
		if (empty($data["author_id"]) && $user_id = \Base::instance()->get("user.id")) {
			$data["author_id"] = $user_id;
		}

		// Create issue
		/** @var Issue $item */
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
		$issue_ids = array($this->id);
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
		if (!$this->deleted_date) {
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
		$children = $this->find(array("parent_id = ?", $this->id));
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
		$children = $this->find(array("parent_id = ? AND deleted_date IS NOT NULL", $this->id));
		foreach ($children as $child) {
			$child->restore();
		}
		return $this;
	}

	/**
	 * Repeat an issue by generating a minimal copy and setting new due date
	 * @param  boolean $notify
	 * @return Issue
	 */
	public function repeat($notify = true) {
		$repeat_issue = new \Model\Issue();
		$repeat_issue->name = $this->name;
		$repeat_issue->type_id = $this->type_id;
		$repeat_issue->parent_id = $this->parent_id;
		$repeat_issue->author_id = $this->author_id;
		$repeat_issue->owner_id = $this->owner_id;
		$repeat_issue->description = $this->description;
		$repeat_issue->priority = $this->priority;
		$repeat_issue->repeat_cycle = $this->repeat_cycle;
		$repeat_issue->hours_total = $this->hours_total;
		$repeat_issue->hours_remaining = $this->hours_total;
		$repeat_issue->created_date = date("Y-m-d H:i:s");

		// Find a due date in the future
		switch ($repeat_issue->repeat_cycle) {
			case 'daily':
				$repeat_issue->start_date = $this->start_date ? date("Y-m-d", strtotime("tomorrow")) : NULL;
				$repeat_issue->due_date = date("Y-m-d", strtotime("tomorrow"));
				break;
			case 'weekly':
				$repeat_issue->start_date = $this->start_date ? date("Y-m-d", strtotime($this->start_date . " +1 week")) : NULL;
				$repeat_issue->due_date = date("Y-m-d", strtotime($this->due_date . " +1 week"));
				break;
			case 'monthly':
				$repeat_issue->start_date = $this->start_date ? date("Y-m-d", strtotime($this->start_date . " +1 month")) : NULL;
				$repeat_issue->due_date = date("Y-m-d", strtotime($this->due_date . " +1 month"));
				break;
			case 'sprint':
				$sprint = new \Model\Sprint();
				$sprint->load(array("start_date > NOW()"), array('order' => 'start_date'));
				$repeat_issue->start_date = $this->start_date ? $sprint->start_date : NULL;
				$repeat_issue->due_date = $sprint->end_date;
				break;
			default:
				$repeat_issue->repeat_cycle = 'none';
		}

		// If the issue was in a sprint before, put it in a sprint again.
		if ($this->sprint_id) {
			$sprint = new \Model\Sprint();
			$sprint->load(array("end_date >= ? AND start_date <= ?", $repeat_issue->due_date, $repeat_issue->due_date), array('order' => 'start_date'));
			$repeat_issue->sprint_id = $sprint->id;
		}

		$repeat_issue->save();
		if($notify) {
			$notification = \Helper\Notification::instance();
			$notification->issue_create($repeat_issue->id);
		}
		return $repeat_issue;
	}

	/**
	 * Log and save an issue update
	 * @param  boolean $notify
	 * @return Issue\Update
	 */
	protected function _saveUpdate($notify = true) {
		$f3 = \Base::instance();

		// Ensure issue is not tied to itself as a parent
		if ($this->id == $this->parent_id) {
			$this->parent_id = $this->_getPrev("parent_id");
		}

		// Log update
		$update = new \Model\Issue\Update();
		$update->issue_id = $this->id;
		$update->user_id = $f3->get("user.id");
		$update->created_date = date("Y-m-d H:i:s");
		if ($f3->exists("update_comment")) {
			$update->comment_id = $f3->get("update_comment")->id;
			$update->notify = (int)$notify;
		} else {
			$update->notify = 0;
		}
		$update->save();

		// Set hours_total to the hours_remaining value under certain conditions
		if ($this->hours_remaining && !$this->hours_total &&
			!$this->_getPrev('hours_remaining') &&
			!$this->_getPrev('hours_total')
		) {
			$this->hours_total = $this->hours_remaining;
		}

		// Set hours remaining to 0 if the issue has been closed
		if ($this->closed_date && $this->hours_remaining) {
			$this->hours_remaining = 0;
		}

		// Create a new issue if repeating
		if ($this->closed_date && $this->repeat_cycle) {
			$this->repeat($notify);
			$this->repeat_cycle = null;
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
		if ($this->sprint_id === 0) {
			$this->set("sprint_id", null);
		}

		// Censor credit card numbers if enabled
		if ($f3->get("security.block_ccs")) {
			if (preg_match("/([0-9]{3,4}-){3}[0-9]{3,4}/", $this->description)) {
				$this->set("description", preg_replace("/([0-9]{3,4}-){3}([0-9]{3,4})/", "************$2", $this->description));
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
				$notification->issue_update($this->id, $update->id);
			}

		} else {

			// Set closed date if status is closed
			if(!$this->closed_date && $this->status) {
				$status = new Issue\Status;
				$status->load($this->status);
				if($status->closed) {
					$this->closed_date = date("Y-m-d H:i:s");
				}
			}

		}

		$return = empty($issue) ? parent::save() : $issue;
		$this->saveTags();
		return $return;
	}

	/**
	 * Finds and saves the current issue's tags
	 * @return Issue
	 */
	function saveTags() {
		$tag = new \Model\Issue\Tag;
		if ($this->id) {
			$tag->deleteByIssueId($this->id);
		}
		if (!$this->deleted_date) {
			$count = preg_match_all("/(?<=[^a-z\\/&]#|^#)[a-z][a-z0-9_-]*[a-z0-9]+(?=[^a-z\\/]|$)/i", $this->description, $matches);
			if ($count) {
				foreach ($matches[0] as $match) {
					$tag->reset();
					$tag->tag = preg_replace("/[_-]+/", "-", ltrim($match, "#"));
					$tag->issue_id = $this->id;
					$tag->save();
				}
			}
		}
		return $this;
	}

	/**
	 * Duplicate issue and all sub-issues
	 * @param  bool  $recursive
	 * @return Issue New issue
	 */
	function duplicate($recursive = true) {
		if (!$this->id) {
			return false;
		}

		$f3 = \Base::instance();

		$this->copyto("duplicating_issue");
		$f3->clear("duplicating_issue.id");
		$f3->clear("duplicating_issue.due_date");
		$f3->clear("duplicating_issue.hours_spent");

		$new_issue = new Issue;
		$new_issue->copyfrom("duplicating_issue");
		$new_issue->author_id = $f3->get("user.id");
		$new_issue->hours_remaining = $new_issue->hours_total;
		$new_issue->created_date = date("Y-m-d H:i:s");
		$new_issue->save();

		if($recursive) {
			// Run the recursive function to duplicate the complete descendant tree
			$this->_duplicateTree($this->id, $new_issue->id);
		}

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
					$f3->clear("duplicating_issue.hours_spent");

					$new_child = new Issue;
					$new_child->copyfrom("duplicating_issue");
					$new_child->author_id = $f3->get("user.id");
					$new_child->hours_remaining = $new_child->hours_total;
					$new_child->parent_id = $new_id;
					$new_child->created_date = date("Y-m-d H:i:s");
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
		if ($this->sprint_id) {
			$query = "UPDATE issue SET sprint_id = :sprint WHERE parent_id = :issue AND type_id != :type";
			if ($replace_existing) {
				$query .= " AND sprint_id IS NULL";
			}
			$this->db->exec(
				$query,
				array(
					":sprint" => $this->sprint_id,
					":issue" => $this->id,
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

		return $this->_children ?: $this->_children = $this->find(array("parent_id = ? AND deleted_date IS NULL", $this->id));
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

	/**
	 * Close the issue
	 * @return Issue $this
	 */
	public function close() {
		if($this->id && !$this->closed_date) {
			$status = new \Model\Issue\Status;
			$status->load(array("closed = ?", 1));
			$this->status = $status->id;
			$this->closed_date = date("Y-m-d H:i:s");
			$this->save();
		}
		return $this;
	}

}
