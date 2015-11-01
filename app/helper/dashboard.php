<?php

namespace Helper;

class Dashboard extends \Prefab {

	protected
		$_issue,
		$_ownerIds,
		$_projects,
		$_order = "priority DESC, has_due_date ASC, due_date ASC";

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
			"owner_id IN ($ownerString) AND deleted_date IS NULL AND closed_date IS NULL AND status_closed = 0 AND repeat_cycle NOT IN ('none', '')",
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
		$issues = $issue->find(array("owner_id IN ($ownerString) OR author_id = ?", $f3->get("user.id")));
		$ids = array();
		foreach($issues as $item) {
			$ids[] = $item->id;
		}

		$comment = new \Model\Issue\Comment\Detail;
		$issueIds = implode(",", $ids);
		return $comment->find(array("issue_id IN ($issueIds) AND user_id != ?", $f3->get("user.id")), array("order" => "created_date DESC", "limit" => 15));
	}

}
