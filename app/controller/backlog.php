<?php

namespace Controller;

class Backlog extends \Controller {

	protected $_userId;

	public function __construct() {
		$this->_userId = $this->_requireLogin();
	}

	/**
	 * GET /backlog
	 *
	 * @param \Base $f3
	 */
	public function index($f3) {
		$sprint_model = new \Model\Sprint();
		$sprints = $sprint_model->find(array("end_date >= ?", $this->now(false)), array("order" => "start_date ASC"));

		$type = new \Model\Issue\Type;
		$projectTypes = $type->find(["role = ?", "project"]);
		$f3->set("project_types", $projectTypes);
		$typeIds = [];
		foreach($projectTypes as $type) {
			$typeIds[] = $type->id;
		}
		$typeStr = implode(",", $typeIds);

		$issue = new \Model\Issue\Detail();

		$sprint_details = [];
		foreach($sprints as $sprint) {
			$projects = $issue->find(
				array("deleted_date IS NULL AND sprint_id = ? AND type_id IN ($typeStr)", $sprint->id),
				array('order' => 'priority DESC, due_date')
			);

			// Add sorted projects
			$sprintBacklog = [];
			$sortOrder = new \Model\Issue\Backlog;
			$sortOrder->load(array("sprint_id = ?", $sprint->id));
			$sortArray = [];
			if($sortOrder->id) {
				$sortArray = json_decode($sortOrder->issues) ?: [];
				$sortArray = array_unique($sortArray);
				foreach($sortArray as $id) {
					foreach($projects as $p) {
						if($p->id == $id) {
							$sprintBacklog[] = $p;
						}
					}
				}
			}

			// Add remaining projects
			foreach($projects as $p) {
				if(!in_array($p->id, $sortArray)) {
					$sprintBacklog[] = $p;
				}
			}

			$sprint_details[] = $sprint->cast() + array("projects" => $sprintBacklog);
		}

		$large_projects = $f3->get("db.instance")->exec("SELECT i.parent_id FROM issue i JOIN issue_type t ON t.id = i.type_id WHERE i.parent_id IS NOT NULL AND t.role = 'project'");
		$large_project_ids = [];
		foreach($large_projects as $p) {
			$large_project_ids[] = $p["parent_id"];
		}

		// Load backlog
		if(!empty($large_project_ids)) {
			$large_project_ids = implode(",", $large_project_ids);
			$unset_projects = $issue->find(
				array("deleted_date IS NULL AND sprint_id IS NULL AND type_id IN ($typeStr) AND status_closed = '0' AND id NOT IN ({$large_project_ids})"),
				array('order' => 'priority DESC, due_date')
			);
		} else {
			$unset_projects = $issue->find(
				array("deleted_date IS NULL AND sprint_id IS NULL AND type_id IN ($typeStr) AND status_closed = '0'"),
				array('order' => 'priority DESC, due_date')
			);
		}

		// Add sorted projects
		$backlog = [];
		$sortOrder = new \Model\Issue\Backlog;
		$sortOrder->load(array("sprint_id IS NULL"));
		$sortArray = [];
		if($sortOrder->id) {
			$sortArray = json_decode($sortOrder->issues) ?: [];
			$sortArray = array_unique($sortArray);
			foreach($sortArray as $id) {
				foreach($unset_projects as $p) {
					if($p->id == $id) {
						$backlog[] = $p;
					}
				}
			}
		}

		// Add remaining projects
		$unsorted = [];
		foreach($unset_projects as $p) {
			if(!in_array($p->id, $sortArray)) {
				$unsorted[] = $p;
			}
		}

		$user = new \Model\User();
		$f3->set("groups", $user->getAllGroups());

		$f3->set("groupid", $groupId);
		$f3->set("type_ids", $typeIds);
		$f3->set("sprints", $sprint_details);
		$f3->set("backlog", $backlog);
		$f3->set("unsorted", $unsorted);

		$f3->set("title", $f3->get("dict.backlog"));
		$f3->set("menuitem", "backlog");
		$this->_render("backlog/index.html");
	}

	/**
	 * GET /backlog/@filter
	 * GET /backlog/@filter/@groupid
	 * @param \Base $f3
	 * @param array $params
	 */
	public function redirect(\Base $f3, array $params) {
		$f3->reroute("/backlog");
	}

	/**
	 * POST /edit
	 * @param \Base $f3
	 * @throws \Exception
	 */
	public function edit($f3) {
		$post = $f3->get("POST");
		$issue = new \Model\Issue();
		$issue->load($post["id"]);
		$issue->sprint_id = empty($post["sprint_id"]) ? null : $post["sprint_id"];
		$issue->save();
		$this->_printJson($issue);
	}

	/**
	 * POST /sort
	 * @param \Base $f3
	 * @throws \Exception
	 */
	public function sort($f3) {
		$this->_requireLogin(\Model\User::RANK_MANAGER);
		$backlog = new \Model\Issue\Backlog;
		if($f3->get("POST.sprint_id")) {
			$backlog->load(array("sprint_id = ?", $f3->get("POST.sprint_id")));
			$backlog->sprint_id = $f3->get("POST.sprint_id");
		} else {
			$backlog->load(array("sprint_id IS NULL"));
		}
		$backlog->issues = $f3->get("POST.items");
		$backlog->save();
	}

	/**
	 * GET /backlog/old
	 * @param \Base $f3
	 */
	public function index_old($f3) {
		$sprint_model = new \Model\Sprint();
		$sprints = $sprint_model->find(array("end_date < ?", $this->now(false)), array("order" => "start_date ASC"));

		$type = new \Model\Issue\Type;
		$projectTypes = $type->find(["role = ?", "project"]);
		$f3->set("project_types", $projectTypes);
		$typeIds = [];
		foreach($projectTypes as $type) {
			$typeIds[] = $type->id;
		}
		$typeStr = implode(",", $typeIds);

		$issue = new \Model\Issue\Detail();
		$sprint_details = array();
		foreach($sprints as $sprint) {
			$projects = $issue->find(array("deleted_date IS NULL AND sprint_id = ? AND type_id IN ($typeStr)", $sprint->id));
			$sprint_details[] = $sprint->cast() + array("projects" => $projects);
		}

		$f3->set("sprints", $sprint_details);

		$f3->set("title", $f3->get("dict.backlog"));
		$f3->set("menuitem", "backlog");
		$this->_render("backlog/old.html");
	}
}
