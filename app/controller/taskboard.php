<?php

namespace Controller;

class Taskboard extends Base {

	public function __construct() {
		$this->_userId = $this->_requireLogin();
	}

	public function index($f3, $params) {

		// Require a valid numeric sprint ID
		if(!intval($params["id"])) {
			$f3->error(404);
			return;
		}

		// Default to showing group tasks
		if(empty($params["filter"])) {
			$params["filter"] = "groups";
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
			// Get a taskboard for a user or group
			$user= new \Model\User();
			$user->load($params["filter"]);
			if ($user->role == 'group') {
				$group_model = new \Model\User\Group();
				$users_result = $group_model->find(array("group_id = ?", $user->id));
				$filter_users = array(intval($params["filter"]));
				foreach($users_result as $u) {
					$filter_users[] = $u["user_id"];
				}
			} else {
				$filter_users = array($params["filter"]);
			}
		}

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

		// Find all visible tasks
		$tasks = $issue->find(array(
			"sprint_id = ? AND type_id != ? AND deleted_date IS NULL AND status IN ($visible_status_ids)"
				. (empty($filter_users) ? "" : " AND owner_id IN (" . implode(",", $filter_users) . ")"),
			$sprint->id, $f3->get("issue_type.project")
		));
		$task_ids = array();
		$parent_ids = array(0);
		foreach($tasks as $task) {
			$task_ids[] = $task->id;
			if($task->parent_id) {
				$parent_ids[] = $task->parent_id;
			}
		}
		$task_ids_str = implode(",", $task_ids);
		$parent_ids_str = implode(",", $parent_ids);
		$f3->set("tasks", $task_ids_str);

		// Find all visible projects
		$projects = $issue->find(array(
			"(id IN ($parent_ids_str) AND type_id = ?) OR (sprint_id = ? AND type_id = ? AND deleted_date IS NULL"
				. (empty($filter_users) ? ")" : " AND owner_id IN (" . implode(",", $filter_users) . "))"),
			$f3->get("issue_type.project"), $sprint->id, $f3->get("issue_type.project")
		), array("order" => "owner_id ASC"));

		// Build multidimensional array of all tasks and projects
		$taskboard = array();
		foreach($projects as $project) {

			// Build array of statuses to put tasks under
			$columns = array();
			foreach($statuses as $status) {
				$columns[$status["id"]] = array();
			}

			// Add current project's tasks
			foreach ($tasks as $task) {
				if($task->parent_id == $project->id || $project->id == 0 && !$task->parent_id) {
					$columns[$task->status][] = $task;
				}
			}

			// Add hierarchial structure to taskboard array
			$taskboard[] = array(
				"project" => $project,
				"columns" => $columns
			);

		}

		$f3->set("taskboard", array_values($taskboard));
		$f3->set("filter", $params["filter"]);

		// Get user list for select
		$users = new \Model\User();
		$f3->set("users", $users->getAll());
		$f3->set("groups", $users->getAllGroups());

		echo \Template::instance()->render("taskboard/index.html");
	}

	public function burndown($f3, $params) {
		$sprint = new \Model\Sprint;
		$sprint->load($params["id"]);

		if(!$sprint->id) {
			$f3->error(404);
			return;
		}

		$visible_tasks = explode(",", $params["tasks"]);

		// Visible tasks must have at least one key
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
		print_json($burndown);
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
			$issue->hours_spent += $post["hours_spent"];
			if(!empty($post["hours_spent"]) && $issue->hours_remaining > 0 &&  !empty($post["hours_spent"])) {
				$issue->hours_remaining -=  $post["hours_spent"];
			}
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

		if(!empty($post["comment"])) {
			$comment = new \Model\Issue\Comment;
			$comment->user_id = $this->_userId;
			$comment->issue_id = $issue->id;
			$comment->text = $post["comment"];
			$comment->created_date = now();
			$comment->save();
			$issue->update_comment = $comment->id;
		}

		$issue->save();

		print_json($issue->cast() + array("taskId" => $issue->id));
	}

}
