<?php

namespace Controller;

class Taskboard extends Base {

	public function index($f3, $params) {
		$user_id = $this->_requireLogin();

		if(empty($params["filter"])) {
			$params["filter"] = "groups";
		}

		// Get list of all users in the user's groups
		if($params["filter"] == "groups") {
			$group_model = new \Model\User\Group();
			$groups_result = $group_model->find(array("user_id = ?", $user_id));
			$filter_users = array($user_id);
			foreach($groups_result as $g) {
				$filter_users[] = $g["group_id"];
			}
			$groups = implode(",", $filter_users);
			$users_result = $group_model->find("group_id IN ({$groups})");
			foreach($users_result as $u) {
				$filter_users[] = $u["user_id"];
			}
		} elseif($params["filter"] == "me") {
			$filter_users = array($user_id);
		}

		// Load the requested sprint
		$sprint = new \Model\Sprint();
		$sprint->load($params["id"]);
		if(!$sprint->id) {
			$f3->error(404);
			return;
		}
		
		$f3->set("sprint", $sprint);
		$f3->set("title", $sprint->name);
		$f3->set("menuitem", "backlog");

		// Load issue statuses
		$status = new \Model\Issue\Status();
		$statuses = $status->find(array('taskboard = 1'), null, $f3->get("cache_expire.db"));
		$f3->set("statuses", $statuses);

		// Load issue priorities
		$priority = new \Model\Issue\Priority();
		$f3->set("priorities", $priority->find(null, null, $f3->get("cache_expire.db")));

		// Load project list
		$issue = new \Model\Issue\Detail();

		//Add the default project for non assigned bugs or tasks (id=0)
		//Add any projects where the project may not be due, but a task within the project is due this sprint (3rd OR)
		$projects = $issue->find(array(
			"id = 0 OR (sprint_id = ? AND deleted_date IS NULL AND type_id = ?) OR (id IN (SELECT parent_id FROM issue WHERE type_id != ? AND sprint_id = ?) AND IFNULL(sprint_id, 0) != ?)",
			$sprint->id,
			$f3->get("issue_type.project"),
			$f3->get("issue_type.project"),
			$sprint->id,
			$sprint->id
		), array("order" => "owner_id ASC"));

		// Build multidimensional array of all tasks and projects
		$taskboard = array();
		foreach($projects as $project) {

			// Build array of statuses to put tasks under
			$columns = array();
			foreach($statuses as $status) {
				$columns[$status["id"]] = array();
			}

			if ($project["id"] == 0) {
				// Orphaned sprint tasks - grab and attach here
				$tasks = $issue->find(array("type_id!=? and IFNULL(parent_id,'')='' and deleted_date is null and sprint_id=?",$f3->get("issue_type.project"),$sprint->id), array("order" => "priority DESC, has_due_date ASC, due_date ASC"));
				foreach ($tasks as $task) {
					$task["parent_id"] = 0;
				}
			} elseif ($project["sprint_id"] != $sprint->id) {
				// Get tasks that are due during the sprint with a parent project not in the sprint
				$tasks = $issue->find(array("parent_id = ? AND type_id != ? AND deleted_Date IS NULL and sprint_id=?", $project["id"], $f3->get("issue_type.project"), $sprint->id), array("order" => "priority DESC, has_due_date ASC, due_date ASC"));
			} else {
				// Get all non-projects (generally tasks) under the project, put them under their status
				$tasks = $issue->find(array("parent_id = ? AND type_id != ? AND deleted_Date IS NULL", $project["id"], $f3->get("issue_type.project")), array("order" => "priority DESC, has_due_date ASC, due_date ASC"));
			}


			foreach($tasks as $task) {
				$columns[$task["status"]][] = $task;
			}

			// Add hierarchial structure to taskboard array
			$taskboard[] = array(
				"project" => $project,
				"columns" => $columns
			);

		}

		// Filter tasks and projects
		if(!empty($filter_users)) {

			// Determine which projects to keep and which to remove
			$remove_project_indexes = array();
			foreach ($taskboard as $pi=>&$p) {

				$kept_task_count = 0;

				// Only remove tasks if project isn't in the list of shown users
				if(!in_array($p["project"]["owner_id"], $filter_users)) {
					foreach($p["columns"] as &$c) {

						// Determine which tasks to keep and which to remove
						$remove_task_indexes = array();
						foreach($c as $ci=>&$t) {
							if(!in_array($t["owner_id"], $filter_users)) {
								// Task is not in list of shown users, mark for removal
								$remove_task_indexes[] = $ci;
							} else {
								// Task is in list of shown users, increment kept task counter
								$kept_task_count ++;
							}
						}

						// Remove marked tasks
						foreach($remove_task_indexes as $r) {
							unset($c[$r]);
						}
					}
				}

				// Project is empty and not in the list of shown users, mark for removal
				if(!$kept_task_count && !in_array($p["project"]["owner_id"], $filter_users)) {
					$remove_project_indexes[] = $pi;
				}

			}

			// Remove marked projects
			foreach($remove_project_indexes as $r) {
				unset($taskboard[$r]);
			}
		}

		$f3->set("taskboard", array_values($taskboard));
		$f3->set("filter", $params["filter"]);

		// Get user list for select
		$users = new \Model\User();
		$f3->set("users", $users->find("deleted_date IS NULL AND role != 'group'", array("order" => "name ASC")));
		$f3->set("groups", $users->find("deleted_date IS NULL AND role = 'group'", array("order" => "name ASC")));

		echo \Template::instance()->render("taskboard/index.html");

	}

	public function add($f3, $params) {
		$user_id = $this->_requireLogin();
		$post = $f3->get("POST");

		$issue = new \Model\Issue();
		$issue->name = $post["title"];
		$issue->description = $post["description"];
		$issue->author_id = $user_id;
		$issue->owner_id = $post["assigned"];
		$issue->created_date = now();
		$issue->hours_total = $post["hours"];
		$issue->hours_remaining = $post["hours"];
		if(!empty($post["dueDate"])) {
			$issue->due_date = date("Y-m-d", strtotime($post["dueDate"]));
		}
		$issue->priority = $post["priority"];
		$issue->parent_id = $post["storyId"];
		$issue->save();

		print_json($issue->cast() + array("taskId" => $issue->id));
	}

	public function edit($f3, $params) {
		$post = $f3->get("POST");
		$issue = new \Model\Issue();
		$issue->load($post["taskId"]);
		if(!empty($post["receiver"])) {
			$issue->parent_id = $post["receiver"]["story"];
			$issue->status = $post["receiver"]["status"];
			$status = new \Model\Issue\Status();
			$status->load($issue->status);
			if($status->closed) {
				if(!$issue->closed_date) {
					$issue->closed_date = now();
				}
			} else {
				$issue->closed_date = null;
			}
		} else {
			$issue->name = $post["title"];
			$issue->description = $post["description"];
			$issue->owner_id = $post["assigned"];
			$issue->hours_remaining = $post["hours"];
			if(!empty($post["dueDate"])) {
				$issue->due_date = date("Y-m-d", strtotime($post["dueDate"]));
			} else {
				$issue->due_date = null;
			}
			$issue->priority = $post["priority"];
			if(!empty($post["storyId"])) {
				$issue->parent_id = $post["storyId"];
			}
			$issue->title = $post["title"];
		}
		$issue->save();

		print_json($issue->cast() + array("taskId" => $issue->id));
	}

}
