<?php

namespace Controller;

class Taskboard extends Base {

	public function __construct() {
		$this->_userId = $this->_requireLogin();
	}

	public function index($f3, $params) {

		if(empty($params["filter"])) {
			$params["filter"] = "groups";
		}

		// Get list of all users in the user's groups
		if($params["filter"] == "groups") {
			$group_model = new \Model\User\Group();
			$groups_result = $group_model->find(array("user_id = ?", $this->_userId));
			$filter_users = array($this->_userId);
			foreach($groups_result as $g) {
				$filter_users[] = $g["group_id"];
			}
			$groups = implode(",", $filter_users);
			$users_result = $group_model->find("group_id IN ({$groups})");
			foreach($users_result as $u) {
				$filter_users[] = $u["user_id"];
			}
		} elseif($params["filter"] == "me") {
			$filter_users = array($this->_userId);
		} elseif(is_numeric($params["filter"])) {
			//get a taskboard for a user or group
			$user= new \Model\User();
			$user->load($params["filter"]);
			if ($user->role == 'group') {
				$group_model = new \Model\User\Group();
				$users_result = $group_model->find(array("group_id = ?", $user->id));
				$filter_users = array();
				foreach($users_result as $u) {
					$filter_users[] = $u["user_id"];
				}
				if(empty($filter_users)) {
					$filter_users = array($this->_userId);
				}
			} else {
				$filter_users = array($this->_userId);
			}

		}

		// Load the requested sprint
		$sprint = new \Model\Sprint();
		$sprint->load($params["id"]);
		if(!$sprint->id) {
			$f3->error(404);
			return;
		}

		$f3->set("sprint", $sprint);
		$f3->set("title", $sprint->name . " " . date('n/j', strtotime($sprint->start_date)) . "-" . date('n/j', strtotime($sprint->end_date)));
		$f3->set("menuitem", "backlog");

		// Load issue statuses
		$status = new \Model\Issue\Status();
		$statuses = $status->find(array('taskboard = 1'), null, $f3->get("cache_expire.db"));
		$mapped_statuses = array();
		$visible_status_ids = array();
		foreach($statuses as $s) {
			$visible_status_ids[] = $s->id;
			$mapped_statuses[$s->id] = $s;
		}

		$visible_status_ids = implode(",", $visible_status_ids);

		$f3->set("statuses", $mapped_statuses);

		// Load issue priorities
		$priority = new \Model\Issue\Priority();
		$f3->set("priorities", $priority->find(null, null, $f3->get("cache_expire.db")));

		// Load project list
		$issue = new \Model\Issue\Detail();

		//Add the default project for non assigned bugs or tasks (id=0)
		//Add any projects where the project may not be due, but a task within the project is due this sprint (3rd OR)
		$projects = $issue->find(array(
			"id = 0 OR ((sprint_id = ? AND deleted_date IS NULL AND type_id = ?) OR (id IN (SELECT parent_id FROM issue WHERE type_id != ? AND sprint_id = ?) AND IFNULL(sprint_id, 0) != ?)) AND status IN ($visible_status_ids)",
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
				$tasks = $issue->find(array(
					"type_id != ? AND IFNULL(parent_id, '') = '' AND deleted_date IS NULL AND sprint_id = ? AND status IN ($visible_status_ids)",
					$f3->get("issue_type.project"),
					$sprint->id
				), array("order" => "priority DESC, has_due_date ASC, due_date ASC"));
				foreach ($tasks as $task) {
					$task["parent_id"] = 0;
				}
			} elseif ($project["sprint_id"] != $sprint->id) {
				// Get tasks that are due during the sprint with a parent project not in the sprint
				$tasks = $issue->find(array("parent_id = ? AND type_id != ? AND deleted_date IS NULL AND sprint_id = ? AND status IN ($visible_status_ids)", $project["id"], $f3->get("issue_type.project"), $sprint->id), array("order" => "priority DESC, has_due_date ASC, due_date ASC"));
			} else {
				// Get all non-projects (generally tasks) under the project, put them under their status
				$tasks = $issue->find(array("parent_id = ? AND type_id != ? AND deleted_date IS NULL AND status IN ($visible_status_ids)", $project["id"], $f3->get("issue_type.project")), array("order" => "priority DESC, has_due_date ASC, due_date ASC"));
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



		// Build an array of all visible task IDs (used by the burndown)
		$visible_tasks = array();
		foreach($taskboard as $p) {
			foreach($p["columns"] as $c) {
				foreach($c as $t) {
					$visible_tasks[] = $t["id"];
				}
			}
		}
		 //Visible tasks must have at least one key
		if (empty($visible_tasks)) {
			$visible_tasks = array(0);
		}

		// Get today's date
		$today = date('Y-m-d');
		$today = $today . " 23:59:59";

		//Check to see  if the sprint is completed
		if ($today < strtotime($sprint->end_date . ' + 1 day')){
			$burnComplete = 0;
			$burnDates = createDateRangeArray($sprint->start_date, $today);
			$remainingDays = createDateRangeArray($today, $sprint->end_date);
		}
		else{
			$burnComplete = 1;
			$burnDates = createDateRangeArray($sprint->start_date, $sprint->end_date);
			$remainingDays = array();
		}

		$burnDays = array();
		$burnDatesCount = count($burnDates);
		$i = 1;

		$db = $f3->get("db.instance");

		foreach($burnDates as $date){

			//Get total_hours, which is the initial amount entered on each task, and cache this query
			if($i == 1){
				$burnDays[$date] =
					$db->exec("
						SELECT i.hours_total AS remaining
						FROM issue i
						WHERE i.id IN (". implode(",", $visible_tasks) .")
						AND i.created_date < '" . $sprint->start_date  . " 00:00:00'", // Only count tasks added before sprint
						NULL,
						2678400 // 31 days
					);
			}

			//Get between day values and cache them... this also will get the last day of completed sprints so they will be cached
			else if($i < ($burnDatesCount - 1) || $burnComplete ){
				$burnDays[$date] = $db->exec("
					SELECT IF(f.new_value = '' OR f.new_value IS NULL, i.hours_total, f.new_value) AS remaining
					FROM issue_update_field f
					JOIN issue_update u ON u.id = f.issue_update_id
					JOIN (
						SELECT MAX(u.id) AS max_id
						FROM issue_update u
						JOIN issue_update_field f ON f.issue_update_id = u.id
						WHERE f.field = 'hours_remaining'
						AND u.created_date < '". $date . " 23:59:59'
						GROUP BY u.issue_id
					) a ON a.max_id = u.id
					RIGHT JOIN issue i ON i.id = u.issue_id
					WHERE (f.field = 'hours_remaining' OR f.field IS NULL)
					AND i.id IN (". implode(",", $visible_tasks) . ")
					AND i.created_date < '". $date . " 23:59:59'",
					NULL,
					2678400 // 31 days
				);
			}

			//Get the today's info and don't cache it
			else{
				$burnDays[$date] =
					$db->exec("
						SELECT IF(f.new_value = '' OR f.new_value IS NULL, i.hours_total, f.new_value) AS remaining
						FROM issue_update_field f
						JOIN issue_update u ON u.id = f.issue_update_id
						JOIN (
							SELECT MAX(u.id) AS max_id
							FROM issue_update u
							JOIN issue_update_field f ON f.issue_update_id = u.id
							WHERE f.field = 'hours_remaining'
							AND u.created_date < '" . $date . " 23:59:59'
							GROUP BY u.issue_id
						) a ON a.max_id = u.id
						RIGHT JOIN issue i ON i.id = u.issue_id
						WHERE (f.field = 'hours_remaining' OR f.field IS NULL)
						AND i.created_date < '". $date . " 23:59:59'
						AND i.id IN (". implode(",", $visible_tasks) . ")"
				);
			}

			$i++;
		}

		if(!$burnComplete){//add in empty days
			$i = 0;
			foreach($remainingDays as $day) {
				if($i != 0){
					$burnDays[$day] = NULL;
				}
				$i++;
			}
		}

		//reformat the date and remove weekends
		$i = 0;
		foreach($burnDays as $burnKey => $burnDay){

			$weekday = date("D", strtotime($burnKey));
			$weekendDays = array("Sat","Sun");

			if( !in_array($weekday, $weekendDays) ){
				$newDate = date("M j", strtotime($burnKey));
				$burnDays[$newDate] = $burnDays[$burnKey];
				unset($burnDays[$burnKey]);
			}
			else{//remove weekend days
				unset($burnDays[$burnKey]);
			}

			$i++;
		}

		$burndown = array($burnDays);

		$f3->set("burndown", json_encode($burndown));

		$f3->set("taskboard", array_values($taskboard));
		$f3->set("filter", $params["filter"]);

		$grouplist = \Helper\Groups::instance();
		$f3->set("groups", $grouplist->getAll());

		// Get user list for select
		$users = new \Model\User();
		$f3->set("users", $users->find("deleted_date IS NULL AND role != 'group'", array("order" => "name ASC")));
		$f3->set("groups", $users->find("deleted_date IS NULL AND role = 'group'", array("order" => "name ASC")));

		echo \Template::instance()->render("taskboard/index.html");

	}

	public function add($f3, $params) {
		$post = $f3->get("POST");

		$issue = new \Model\Issue();
		$issue->name = $post["title"];
		$issue->description = $post["description"];
		$issue->author_id = $this->_userId;
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
