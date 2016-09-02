<?php

namespace Helper;

class Dashboard extends \Prefab {

	protected
		$_issue,
		$_ownerIds,
		$_projects,
		$_order = "priority DESC, has_due_date ASC, due_date ASC";

	public $allWidgets = array("projects", "subprojects", "tasks", "bugs", "repeat_work", "watchlist", "my_comments", "recent_comments", "open_comments", "issue_tree");

	public function getIssue() {
		return $this->_issue === null ? $this->_issue = new \Model\Issue\Detail : $this->_issue;
	}

	public function getOwnerIds() {
		if($this->_ownerIds) {
			return $this->_ownerIds;
		}
		$f3 = \Base::instance();
		$this->_ownerIds = array($f3->get("user.id"));
		$groups = new \Model\User\Group();
		foreach($groups->find(array("user_id = ?", $f3->get("user.id"))) as $r) {
			$this->_ownerIds[] = $r->group_id;
		}
		return $this->_ownerIds;
	}

	public function projects() {
		$f3 = \Base::instance();
		$ownerString = implode(",", $this->getOwnerIds());
		$this->_projects = $this->getIssue()->find(
			array(
				"owner_id IN ($ownerString) AND type_id=:type AND deleted_date IS NULL AND closed_date IS NULL AND status_closed = 0",
				":type" => $f3->get("issue_type.project"),
			),
			array("order" => $this->_order)
		);
		return $this->_projects;
	}

	public function subprojects() {
		if($this->_projects === null) {
			$this->projects();
		}

		$projects = $this->_projects;
		$subprojects = array();
		foreach($projects as $i=>$project) {
			if($project->parent_id) {
				$subprojects[] = $project;
				unset($projects[$i]);
			}
		}

		return $subprojects;
	}

	public function bugs() {
		$f3 = \Base::instance();
		$ownerString = implode(",", $this->getOwnerIds());
		return $this->getIssue()->find(
			array(
				"owner_id IN ($ownerString) AND type_id=:type AND deleted_date IS NULL AND closed_date IS NULL AND status_closed = 0",
				":type" => $f3->get("issue_type.bug"),
			),
			array("order" => $this->_order)
		);
	}

	public function repeat_work() {
		$ownerString = implode(",", $this->getOwnerIds());
		return $this->getIssue()->find(
			"owner_id IN ($ownerString) AND deleted_date IS NULL AND closed_date IS NULL AND status_closed = 0 AND repeat_cycle IS NOT NULL",
			array("order" => $this->_order)
		);
	}

	public function watchlist() {
		$f3 = \Base::instance();
		$watchlist = new \Model\Issue\Watcher();
		return $watchlist->findby_watcher($f3->get("user.id"), $this->_order);
	}

	public function tasks() {
		$f3 = \Base::instance();
		$ownerString = implode(",", $this->getOwnerIds());
		return $this->getIssue()->find(
			array(
				"owner_id IN ($ownerString) AND type_id=:type AND deleted_date IS NULL AND closed_date IS NULL AND status_closed = 0",
				":type" => $f3->get("issue_type.task"),
			),
			array("order" => $this->_order)
		);
	}

	public function my_comments() {
		$f3 = \Base::instance();
		$comment = new \Model\Issue\Comment\Detail;
		return $comment->find(array("user_id = ?", $f3->get("user.id")), array("order" => "created_date DESC", "limit" => 10));
	}

	public function recent_comments() {
		$f3 = \Base::instance();

		$issue = new \Model\Issue;
		$ownerString = implode(",", $this->getOwnerIds());
		$issues = $issue->find(array("owner_id IN ($ownerString) OR author_id = ? AND deleted_date IS NULL", $f3->get("user.id")));
		if(!$issues) {
			return array();
		}

		$ids = array();
		foreach($issues as $item) {
			$ids[] = $item->id;
		}

		$comment = new \Model\Issue\Comment\Detail;
		$issueIds = implode(",", $ids);
		return $comment->find(array("issue_id IN ($issueIds) AND user_id != ?", $f3->get("user.id")), array("order" => "created_date DESC", "limit" => 15));
	}

	public function open_comments() {
		$f3 = \Base::instance();

		$issue = new \Model\Issue;
		$ownerString = implode(",", $this->getOwnerIds());
		$issues = $issue->find(array("(owner_id IN ($ownerString) OR author_id = ?) AND closed_date IS NULL AND deleted_date IS NULL", $f3->get("user.id")));
		if(!$issues) {
			return array();
		}

		$ids = array();
		foreach($issues as $item) {
			$ids[] = $item->id;
		}

		$comment = new \Model\Issue\Comment\Detail;
		$issueIds = implode(",", $ids);
		return $comment->find(array("issue_id IN ($issueIds) AND user_id != ?", $f3->get("user.id")), array("order" => "created_date DESC", "limit" => 15));
	}

	/**
	 * Get data for Issue Tree widget
	 * @return array
	 */
	public function issue_tree() {
		$f3 = \Base::instance();

		// Load assigned issues
		$issue = new \Model\Issue\Detail;
		$assigned = $issue->find(array("closed_date IS NULL AND deleted_date IS NULL AND owner_id = ?", $f3->get("user.id")));

		// Build issue list
		$issues = array();
		$assigned_ids = array();
		$missing_ids = array();
		foreach($assigned as $iss) {
			$issues[] = $iss->cast();
			$assigned_ids[] = $iss->id;
		}
		foreach($issues as $iss) {
			if($iss["parent_id"] && !in_array($iss["parent_id"], $assigned_ids)) {
				$missing_ids[] = $iss["parent_id"];
			}
		}
		while(!empty($missing_ids)) {
			$parents = $issue->find("id IN (" . implode(",", $missing_ids) . ")");
			foreach($parents as $iss) {
				if (($key = array_search($iss->id, $missing_ids)) !== false) {
					unset($missing_ids[$key]);
				}
				$issues[] = $iss->cast();
				$assigned_ids[] = $iss->id;
				if($iss->parent_id && !in_array($iss->parent_id, $assigned_ids)) {
					$missing_ids[] = $iss->parent_id;
				}
			}
		}

		// Convert list to tree
		$tree = $this->_buildTree($issues);

		/**
		 * Helper function for recursive tree rendering
		 * @param   array $issue
		 * @var     callable $renderTree This function, required for recursive calls
		 */
		$renderTree = function(&$issue, $level = 0) use(&$renderTree) {
			if(!empty($issue['id'])) {
				$f3 = \Base::instance();
				$hive = array("issue" => $issue, "dict" => $f3->get("dict"), "BASE" => $f3->get("BASE"), "level" => $level, "issue_type" => $f3->get("issue_type"));
				echo \Helper\View::instance()->render("issues/project/tree-item.html", "text/html", $hive);
				if(!empty($issue['children'])) {
					foreach($issue['children'] as $item) {
						$renderTree($item, $level + 1);
					}
				}
			}
		};
		$f3->set("renderTree", $renderTree);

		return $tree;
	}

	/**
	 * Convert a flat issue array to a tree array. Child issues are added to
	 * the 'children' key in each issue.
	 * @param  array $array Flat array of issues, including all parents needed
	 * @return array Tree array where each issue contains its child issues
	 */
	protected function _buildTree($array) {
		$tree = array();

		// Create an associative array with each key being the ID of the item
		foreach($array as $k => &$v) {
			$tree[$v['id']] = &$v;
		}

		// Loop over the array and add each child to their parent
		foreach($tree as $k => &$v) {
			if(empty($v['parent_id'])) {
				continue;
			}
			$tree[$v['parent_id']]['children'][] = &$v;
		}

		// Loop over the array again and remove any items that don't have a parent of 0;
		foreach($tree as $k => &$v) {
			if(empty($v['parent_id'])) {
				continue;
			}
			unset($tree[$k]);
		}

		return $tree;
	}

}
