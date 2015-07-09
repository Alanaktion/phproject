<?php

namespace Controller;

class Taskboard extends \Controller {

	public function __construct() {
		$this->_userId = $this->_requireLogin();
	}

	/**
	 * Takes two dates formatted as YYYY-MM-DD and creates an
	 * inclusive array of the dates between the from and to dates.
	 * @param  string $strDateFrom
	 * @param  string $strDateTo
	 * @return array
	 */
	protected function _createDateRangeArray($strDateFrom, $strDateTo) {
		$aryRange = array();

		$iDateFrom = mktime(1,0,0,substr($strDateFrom,5,2),substr($strDateFrom,8,2),substr($strDateFrom,0,4));
		$iDateTo = mktime(1,0,0,substr($strDateTo,5,2),substr($strDateTo,8,2),substr($strDateTo,0,4));

		if ($iDateTo >= $iDateFrom) {
			$aryRange[] = date('Y-m-d', $iDateFrom); // first entry
			while ($iDateFrom < $iDateTo) {
				$iDateFrom += 86400; // add 24 hours
				$aryRange[] = date('Y-m-d', $iDateFrom);
			}
		}

		return $aryRange;
	}

	/**
	 * Get a list of users from a filter
	 * @param  string $params URL Parameters
	 * @return array
	 */
	protected function _filterUsers($params) {
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
			$user = new \Model\User();
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
		} elseif($params["filter"] == "all") {
			return array();
		} else {
			return array($this->_userId);
		}
		return $filter_users;
	}

	/**
	 * View a taskboard
	 */
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
		$filter_users = $this->_filterUsers($params);

		// Load issue statuses
		$status = new \Model\Issue\Status();
		$statuses = $status->find(array('taskboard > 0'), null, $f3->get("cache_expire.db"));
		$mapped_statuses = array();
		$visible_status_ids = array();
		$column_count = 0;
		foreach($statuses as $s) {
			$visible_status_ids[] = $s->id;
			$mapped_statuses[$s->id] = $s;
			$column_count += $s->taskboard;
		}

		$visible_status_ids = implode(",", $visible_status_ids);
		$f3->set("statuses", $mapped_statuses);
		$f3->set("column_count", $column_count);

		// Load issue priorities
		$priority = new \Model\Issue\Priority();
		$f3->set("priorities", $priority->find(null, array("order" => "value DESC"), $f3->get("cache_expire.db")));

		// Load project list
		$issue = new \Model\Issue\Detail();

		// Find all visible tasks
		$tasks = $issue->find(array(
			"sprint_id = ? AND type_id != ? AND deleted_date IS NULL AND status IN ($visible_status_ids)"
				. (empty($filter_users) ? "" : " AND owner_id IN (" . implode(",", $filter_users) . ")"),
			$sprint->id, $f3->get("issue_type.project")
		), array("order" => "priority DESC"));
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

		// Find all visible projects or parent tasks
		$projects = $issue->find(array(
			"id IN ($parent_ids_str) OR (sprint_id = ? AND type_id = ? AND deleted_date IS NULL"
				. (empty($filter_users) ? ")" : " AND owner_id IN (" . implode(",", $filter_users) . "))"),
			$sprint->id, $f3->get("issue_type.project")
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
				if($task->parent_id == $project->id || $project->id == 0 && (!$task->parent_id || !in_array($task->parent_id, $parent_ids))) {
					$columns[$task->status][] = $task;
				}
			}

			// Add hierarchical structure to taskboard array
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

		$this->_render("taskboard/index.html");
	}

	/**
	 * Load the burndown chart data
	 */
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

		// Check to see if the sprint is completed
		if ($today < strtotime($sprint->end_date . ' + 1 day')) {
			$burnComplete = 0;
			$burnDates = $this->_createDateRangeArray($sprint->start_date, $today);
			$remainingDays = $this->_createDateRangeArray($today, $sprint->end_date);
		} else {
			$burnComplete = 1;
			$burnDates = $this->_createDateRangeArray($sprint->start_date, $sprint->end_date);
			$remainingDays = array();
		}

		$burnDays = array();
		$burnDatesCount = count($burnDates);

		$db = $f3->get("db.instance");
		$visible_tasks_str = implode(",", $visible_tasks);
		$query_initial =
				"SELECT SUM(IFNULL(i.hours_total, i.hours_remaining)) AS remaining
				FROM issue i
				WHERE i.created_date < :date
				AND i.id IN (" . implode(",", $visible_tasks) . ")";
		$query_daily =
				"SELECT SUM(IF(f.id IS NULL, IFNULL(i.hours_total, i.hours_remaining), f.new_value)) AS remaining
				FROM issue_update_field f
				JOIN issue_update u ON u.id = f.issue_update_id
				JOIN (
					SELECT MAX(u.id) AS max_id
					FROM issue_update u
					JOIN issue_update_field f ON f.issue_update_id = u.id
					WHERE f.field = 'hours_remaining'
					AND u.created_date < :date
					AND u.issue_id IN ($visible_tasks_str)
					GROUP BY u.issue_id
				) a ON a.max_id = u.id
				RIGHT JOIN issue i ON i.id = u.issue_id
				WHERE (f.field = 'hours_remaining' OR f.field IS NULL)
				AND i.created_date < :date
				AND i.id IN ($visible_tasks_str)";

		$i = 1;
		foreach($burnDates as $date) {

			// Get total_hours, which is the initial amount entered on each task, and cache this query
			if($i == 1) {
				$result = $db->exec($query_initial, array(":date" => $sprint->start_date), 2592000);
				$burnDays[$date] = $result[0];
			}

			// Get between day values and cache them... this also will get the last day of completed sprints so they will be cached
			elseif ($i < ($burnDatesCount - 1) || $burnComplete) {
				$result = $db->exec($query_daily, array(":date" => $date . " 23:59:59"), 2592000);
				$burnDays[$date] = $result[0];
			}

			// Get the today's info and don't cache it
			else {
				$result = $db->exec($query_daily, array(":date" => $date . " 23:59:59"));
				$burnDays[$date] = $result[0];
			}

			$i++;
		}

		// Add in empty days
		if(!$burnComplete) {
			$i = 0;
			foreach($remainingDays as $day) {
				if($i != 0){
					$burnDays[$day] = NULL;
				}
				$i++;
			}
		}

		// Reformat the date and remove weekends
		$i = 0;
		foreach($burnDays as $burnKey => $burnDay) {

			$weekday = date("D", strtotime($burnKey));
			$weekendDays = array("Sat","Sun");

			if(!in_array($weekday, $weekendDays)) {
				$newDate = date("M j", strtotime($burnKey));
				$burnDays[$newDate] = $burnDays[$burnKey];
				unset($burnDays[$burnKey]);
			} else { // Remove weekend days
				unset($burnDays[$burnKey]);
			}

			$i++;
		}

		$this->_printJson($burnDays);
	}

	/**
	 * Add a new task
	 */
	public function add($f3, $params) {
		$post = $f3->get("POST");
		$post['sprint_id'] = $post['sprintId'];
		$post['name'] = $post['title'];
		$post['owner_id'] = $post['assigned'];
		$post['due_date'] = $post['dueDate'];
		$post['parent_id'] = $post['storyId'];
		$issue = \Model\Issue::create($post);
		$this->_printJson($issue->cast() + array("taskId" => $issue->id));
	}

	/**
	 * Update an existing task
	 */
	public function edit($f3, $params) {
		$post = $f3->get("POST");
		$issue = new \Model\Issue();
		$issue->load($post["taskId"]);
		if(!empty($post["receiver"])) {
			if($post["receiver"]["story"]) {
				$issue->parent_id = $post["receiver"]["story"];
			}
			$issue->status = $post["receiver"]["status"];
			$status = new \Model\Issue\Status();
			$status->load($issue->status);
			if($status->closed) {
				if(!$issue->closed_date) {
					$issue->closed_date = $this->now();
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
			if(!empty($post["hours_spent"]) && !empty($post["burndown"])) {
				$issue->hours_remaining -=  $post["hours_spent"];
			}
			if($issue->hours_remaining < 0) {
				$issue->hours_remaining = 0;
			}
			if(!empty($post["dueDate"])) {
				$issue->due_date = date("Y-m-d", strtotime($post["dueDate"]));
			} else {
				$issue->due_date = null;
			}
			if(!empty($post["repeat_cycle"])) {
				$issue->repeat_cycle = $post["repeat_cycle"];
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
			if(!empty($post["hours_spent"])) {
				$comment->text = trim($post["comment"]) . sprintf(" (%s %s spent)", $post["hours_spent"], $post["hours_spent"] == 1 ? "hour" : "hours");
			} else {
				$comment->text = $post["comment"];
			}
			$comment->created_date = $this->now();
			$comment->save();
			$issue->update_comment = $comment->id;
		}

		$issue->save();

		$this->_printJson($issue->cast() + array("taskId" => $issue->id));
	}

}
