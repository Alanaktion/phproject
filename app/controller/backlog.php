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
		$groupId = $f3->get("GET.group_id");

		// Get list of all users in the user's groups
		if($groupId != "all" && $groupId != "me") {
			$group_model = new \Model\User\Group();
			if($groupId && is_numeric($groupId)) {
				// Get users list from a specific group
				$users_result = $group_model->find(array("group_id = ?", $groupId));
			} else {
				// Get users list from all groups that you are in
				$groups_result = $group_model->find(array("user_id = ?", $this->_userId));
				$filter_users = array($this->_userId);
				foreach($groups_result as $g) {
					$filter_users[] = $g["group_id"];
				}
				$groups = implode(",", $filter_users);
				$users_result = $group_model->find("group_id IN ({$groups})");
			}

			foreach($users_result as $u) {
				$filter_users[] = $u["user_id"];
			}
		} elseif($groupId == "me") {
			// Just get your own id
			$filter_users = array($this->_userId);
		}

		$filter_string = empty($filter_users) ? "" : "AND owner_id IN (" . implode(",", $filter_users) . ")";

		$sprint_model = new \Model\Sprint();
		$sprints = $sprint_model->find(array("end_date >= ?", $this->now(false)), array("order" => "start_date ASC"));

		$typeIds = $f3->get("GET.type_id")
			? array_filter($f3->split($f3->get("GET.type_id")), "is_numeric")
			: array($f3->get("issue_type.project"));
		sort($typeIds, SORT_NUMERIC);
		$typeStr = implode(",", $typeIds);
		$issue = new \Model\Issue\Detail();

		$sprint_details = array();
		foreach($sprints as $sprint) {
			$projects = $issue->find(
				array("deleted_date IS NULL AND sprint_id = ? AND type_id IN ($typeStr) $filter_string", $sprint->id),
				array('order' => 'priority DESC, due_date')
			);

			if(!empty($groupId)) {
				// Add sorted projects
				$sprintBacklog = array();
				$sortModel = new \Model\Issue\Backlog;
				$sortOrders = $sortModel->find(array("user_id = ? AND sprint_id = ? AND type_id IN ($typeStr)", $groupId, $sprint->id), array("order" => "type_id ASC"));
				$sortArray = array();
				if($sortOrders) {
					$orders = array();
					foreach($sortOrders as $order) {
						$orders[] = json_decode($order->issues) ?: array();
					}
					$sortArray = \Helper\Matrix::instance()->merge($orders);
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
			} else {
				$sprintBacklog = $projects;
			}

			$sprint_details[] = $sprint->cast() + array("projects" => $sprintBacklog);
		}

		$large_projects = $f3->get("db.instance")->exec("SELECT parent_id FROM issue WHERE parent_id IS NOT NULL AND type_id IN ($typeStr)");
		$large_project_ids = array();
		foreach($large_projects as $p) {
			$large_project_ids[] = $p["parent_id"];
		}

		// Load backlog
		if(!empty($large_project_ids)) {
			$large_project_ids = implode(",", $large_project_ids);
			$unset_projects = $issue->find(
				array("deleted_date IS NULL AND sprint_id IS NULL AND type_id IN ($typeStr) AND status_closed = '0' AND id NOT IN ({$large_project_ids}) $filter_string"),
				array('order' => 'priority DESC, due_date')
			);
		} else {
			$unset_projects = $issue->find(
				array("deleted_date IS NULL AND sprint_id IS NULL AND type_id IN ($typeStr) AND status_closed = '0' $filter_string"),
				array('order' => 'priority DESC, due_date')
			);
		}

		// Filter projects into sorted and unsorted arrays if filtering by group
		if($groupId && $groupId != "all") {
			// Add sorted projects
			$backlog = array();
			$sortModel = new \Model\Issue\Backlog;
			$sortOrders = $sortModel->find(array("user_id = ? AND sprint_id IS NULL AND type_id IN ($typeStr)", $groupId), array("order" => "type_id ASC"));
			$sortArray = array();
			if($sortOrders) {
				$orders = array();
				foreach($sortOrders as $order) {
					$orders[] = json_decode($order->issues) ?: array();
				}
				$sortArray = \Helper\Matrix::instance()->merge($orders);
				foreach($sortArray as $id) {
					foreach($unset_projects as $p) {
						if($p->id == $id) {
							$backlog[] = $p;
						}
					}
				}
			}

			// Add remaining projects
			$unsorted = array();
			foreach($unset_projects as $p) {
				if(!in_array($p->id, $sortArray)) {
					$unsorted[] = $p;
				}
			}
		} else {
			$backlog = $unset_projects;
		}

		$groups = new \Model\User();
		$f3->set("groups", $groups->getAllGroups());
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
		if (isset($params["groupid"]) && intval($params["groupid"]) == $params["groupid"]) {
			$f3->reroute("/backlog?group_id=" . $params["groupid"]);
		} elseif(in_array($params["filter"], array("me", "all"))) {
			$f3->reroute("/backlog?group_id=" . $params["filter"]);
		} else {
			$f3->reroute("/backlog");
		}
	}

	/**
	 * POST /edit
	 * @param \Base $f3
	 * @throws \Exception
	 */
	public function edit($f3) {
		$post = $f3->get("POST");
		$issue = new \Model\Issue();
		$issue->load($post["itemId"]);
		$issue->sprint_id = empty($post["reciever"]["receiverId"]) ? null : $post["reciever"]["receiverId"];
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
			$backlog->load(array("user_id = ? AND type_id = ? AND sprint_id = ?", $f3->get("POST.user_id"), $f3->get("POST.type_id"), $f3->get("POST.sprint_id")));
			$backlog->sprint_id = $f3->get("POST.sprint_id");
		} else {
			$backlog->load(array("user_id = ? AND type_id = ? AND sprint_id IS NULL", $f3->get("POST.user_id"), $f3->get("POST.type_id")));
		}
		$backlog->user_id = $f3->get("POST.user_id");
		$backlog->type_id = $f3->get("POST.type_id");
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

		$issue = new \Model\Issue\Detail();

		$sprint_details = array();
		foreach($sprints as $sprint) {
			$projects = $issue->find(array("deleted_date IS NULL AND sprint_id = ? AND type_id = ?", $sprint->id, $f3->get("issue_type.project")));
			$sprint_details[] = $sprint->cast() + array("projects" => $projects);
		}

		$f3->set("sprints", $sprint_details);

		$f3->set("title", $f3->get("dict.backlog"));
		$f3->set("menuitem", "backlog");
		$this->_render("backlog/old.html");
	}
}
