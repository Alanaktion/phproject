<?php

namespace Controller;

class Taskboard extends \Controller {

	public function __construct() {
		$this->_userId = $this->_requireLogin();
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
				\Base::instance()->set("filterGroup", $user);
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
	 *
	 * @param \Base $f3
	 * @param array $params
	 */
	public function index($f3, $params) {
		$sprint = new \Model\Sprint();

		// Load current sprint if no sprint ID is given
		if(empty($params["id"]) || !intval($params["id"])) {
			$localDate = date('Y-m-d', \Helper\View::instance()->utc2local());
			$sprint->load(array("? BETWEEN start_date AND end_date", $localDate));
			if(!$sprint->id) {
				$f3->error(404);
				return;
			}
		}

		// Default to showing group tasks
		if(empty($params["filter"])) {
			$params["filter"] = "groups";
		}

		// Load the requested sprint
		if(!$sprint->id) {
			$sprint->load($params["id"]);
			if(!$sprint->id) {
				$f3->error(404);
				return;
			}
		}

		$f3->set("sprint", $sprint);
		$f3->set("title", $sprint->name . " " . date('n/j', strtotime($sprint->start_date)) . "-" . date('n/j', strtotime($sprint->end_date)));
		$f3->set("menuitem", "backlog");

		// Get list of all users in the user's groups
		$filter_users = $this->_filterUsers($params);

		// Load issue statuses
		$status = new \Model\Issue\Status();
		$statuses = $status->find(array('taskboard > 0'), array('order' => 'taskboard_sort ASC'));
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

		// Determine type filtering
		$type = new \Model\Issue\Type;
		$projectTypes = $type->find(["role = ?", "project"]);
		$f3->set("project_types", $projectTypes);
		if ($f3->get("GET.type_id")) {
			$typeIds =	array_filter($f3->split($f3->get("GET.type_id")), "is_numeric");
		} else {
			$typeIds = [];
			foreach($projectTypes as $type) {
				$typeIds[] = $type->id;
			}
		}
		$typeStr = implode(",", $typeIds);
		sort($typeIds, SORT_NUMERIC);

		// Find all visible tasks
		$tasks = $issue->find(array(
			"sprint_id = ? AND type_id NOT IN ($typeStr) AND deleted_date IS NULL AND status IN ($visible_status_ids)"
				. (empty($filter_users) ? "" : " AND owner_id IN (" . implode(",", $filter_users) . ")"),
			$sprint->id
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

		// Find all visible projects or parent tasks if no type filter is given
		$queryArray = array(
			"(id IN ($parent_ids_str) AND type_id IN ($typeStr)) OR (sprint_id = ? AND type_id IN ($typeStr) AND deleted_date IS NULL"
				. (empty($filter_users) ? ")" : " AND owner_id IN (" . implode(",", $filter_users) . "))"),
			$sprint->id
		);
		$projects = $issue->find($queryArray, array("order" => "owner_id ASC, priority DESC"));

		// Sort projects if a filter is given
		$sortModel = new \Model\Issue\Backlog;
		$sortOrder = $sortModel->load(array("sprint_id = ?", $sprint->id));
		if($sortOrder) {
			$sortArray = json_decode($sortOrder->issues) ?: array();
			$sortArray = array_unique($sortArray);
			usort($projects, function(\Model\Issue $a, \Model\Issue $b) use($sortArray) {
				$ka = array_search($a->id, $sortArray);
				$kb = array_search($b->id, $sortArray);
				if($ka === false && $kb !== false) {
					return -1;
				}
				if($ka !== false && $kb === false) {
					return 1;
				}
				if($ka === $kb) {
					return 0;
				}
				if($ka > $kb) {
					return 1;
				}
				if($ka < $kb) {
					return -1;
				}
			});
		}

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

		$f3->set("type_ids", $typeIds);
		$f3->set("taskboard", array_values($taskboard));
		$f3->set("filter", $params["filter"]);

		// Get user list for select
		$users = new \Model\User();
		$f3->set("users", $users->getAll());
		$f3->set("groups", $users->getAllGroups());

		$this->_render("taskboard/index.html");
	}

	/**
	 * Load the hourly burndown chart data
	 *
	 * @param  \Base $f3
	 * @param  array $params
	 */
	public function burndown($f3, $params) {
		$sprint = new \Model\Sprint;
		$sprint->load($params["id"]);

		if (!$sprint->id) {
			$f3->error(404);
			return;
		}

		$db = $f3->get("db.instance");

		$user = new \Model\User;
		$user->load(array("id = ?", $params["filter"]));
		if (!$user->id) {
			$f3->error(404);
			return;
		}

		$query = "
			SELECT SUM(IFNULL(f.new_value, IFNULL(i.hours_total, i.hours_remaining))) AS remaining
			FROM issue_update_field f
			JOIN issue_update u ON u.id = f.issue_update_id
			JOIN (
				SELECT MAX(u.id) AS max_id
				FROM issue_update u
				JOIN issue_update_field f ON f.issue_update_id = u.id
				JOIN issue i ON i.id = u.issue_id
				JOIN user_group g ON g.user_id = i.owner_id OR g.group_id = i.owner_id
				WHERE f.field = 'hours_remaining'
					AND i.sprint_id = :sprint1
					AND u.created_date < :date1
					AND g.group_id = :user1
				GROUP BY u.issue_id
			) a ON a.max_id = u.id
			RIGHT JOIN (
				SELECT i.*
				FROM issue i
				JOIN user_group g ON g.user_id = i.owner_id OR g.group_id = i.owner_id
				WHERE i.sprint_id = :sprint2
				AND g.group_id = :user2
			) i ON i.id = u.issue_id
			WHERE (f.field = 'hours_remaining' OR f.field IS NULL)
				AND i.created_date < :date2";

		$start = strtotime($sprint->getFirstWeekday());
		$end = min(strtotime($sprint->getLastWeekday() . " 23:59:59"), time());

		$return = [];
		$cur = $start;
		$helper = \Helper\View::instance();
		$offset = $helper->timeoffset();
		while($cur < $end) {
			/*// Weekdays only
			if (in_array(date("w", $cur), [0, 6])) {
				continue;
			}*/
			$date = date("Y-m-d H:i:00", $cur);
			$utc = date("Y-m-d H:i:s", $cur - $offset);
			$return[$date] = round($db->exec($query, [
				":date1" => $utc,
				":date2" => $utc,
				":sprint1" => $sprint->id,
				":sprint2" => $sprint->id,
				":user1" => $user->id,
				":user2" => $user->id,
			])[0]["remaining"], 2);
			$cur += 3600;
		}

		$this->_printJson($return);
	}

	/**
	 * Load the precise burndown chart data
	 *
	 * This function is not currently used due to calculation issues. It's been
	 * replaced with the burndown() function above.
	 *
	 * @todo  Find and fix calculation issues
	 *
	 * @param \Base $f3
	 * @param array $params
	 */
	public function burndownPrecise($f3, $params) {
		$sprint = new \Model\Sprint;
		$sprint->load($params["id"]);

		if (!$sprint->id) {
			$f3->error(404);
			return;
		}

		$user = new \Model\User;
		$user->load(array("id = ?", $params["filter"]));
		if (!$user->id) {
			$f3->error(404);
			return;
		}

		$helper = \Helper\View::instance();
		$offset = $helper->timeoffset();
		$start = date("Y-m-d H:i:s", strtotime($sprint->start_date) - $offset);
		$end = date("Y-m-d H:i:s", strtotime($sprint->end_date . " 23:59:59") - $offset);

		$db = $f3->get("db.instance");
		$plannedHours = $db->exec(
			"SELECT GREATEST(i.created_date, :start) AS ts,
				SUM(i.hours_total) AS hours
			FROM issue i
			JOIN user_group g ON g.`user_id` = i.`owner_id` OR g.`group_id` = i.`owner_id`
			WHERE i.sprint_id = :sprint
				AND g.`group_id` = :user
				AND i.`hours_total` > 0
			GROUP BY ts
			ORDER BY ts ASC",
			array(":sprint" => $sprint->id, ":user" => $user->id, ":start" => $start)
		);
		$updatedHours = $db->exec(
			"SELECT GREATEST(u.created_date, :start) AS ts,
				SUM(IFNULL(f.`old_value`, 0)) `old`,
				SUM(f.`new_value`) `new`,
				(SUM(f.new_value) - SUM(IFNULL(f.old_value, 0))) diff
			FROM issue_update_field f
			JOIN issue_update u ON f.`issue_update_id` = u.`id`
			JOIN issue i ON i.id = u.`issue_id`
			JOIN user_group g ON g.`user_id` = i.`owner_id`
				OR g.`group_id` = i.`owner_id`
			WHERE i.sprint_id = :sprint
				AND g.`group_id` = :user
				AND u.`created_date` < :end
				AND f.`field` = 'hours_remaining'
				AND IFNULL(f.`old_value`, 0) != IFNULL(f.`new_value`, 0)
			GROUP BY ts
			ORDER BY ts ASC",
			array(":sprint" => $sprint->id, ":user" => $user->id, ":start" => $start, ":end" => $end)
		);

		$diffs = array();
		foreach($plannedHours as $h) {
			$diffs[date("Y-m-d H:i:s", $helper->utc2local($h["ts"]))] = $h["hours"];
		}
		foreach($updatedHours as $h) {
			if (array_key_exists(date("Y-m-d H:i:s", $helper->utc2local($h["ts"])), $diffs)) {
				$diffs[date("Y-m-d H:i:s", $helper->utc2local($h["ts"]))] += $h["diff"];
			} else {
				$diffs[date("Y-m-d H:i:s", $helper->utc2local($h["ts"]))] = $h["diff"];
			}
		}
		ksort($diffs);

		$totals = array();
		$current = 0;
		foreach($diffs as $ts=>$diff) {
			$totals[$ts] = $current = $current + $diff;
		}

		$totalsRounded = array_map(function($val) {
			return round($val, 2);
		}, $totals);

		$this->_printJson($totalsRounded);
	}

	/**
	 * Save man hours for a group/user
	 *
	 * @param  \Base $f3
	 */
	public function saveManHours($f3) {
		$user = new \Model\User;
		$user->load(array("id = ?", $f3->get("POST.user_id")));
		if (!$user->id) {
			$f3->error(404);
		}
		if ($user->id != $this->_userId && $user->role != "group") {
			$f3->error(403);
		}
		$user->option("man_hours", floatval($f3->get("POST.man_hours")));
		$user->save();
	}

	/**
	 * Add a new task
	 *
	 * @param \Base $f3
	 */
	public function add($f3) {
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
	 *
	 * @param \Base $f3
	 */
	public function edit($f3) {
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
				$issue->hours_remaining -= $post["hours_spent"];
			}
			if(!$issue->hours_remaining || $issue->hours_remaining < 0) {
				$issue->hours_remaining = 0;
			}
			if(!empty($post["dueDate"])) {
				$issue->due_date = date("Y-m-d", strtotime($post["dueDate"]));
			} else {
				$issue->due_date = null;
			}
			if(isset($post["repeat_cycle"])) {
				$issue->repeat_cycle = $post["repeat_cycle"] ?: null;
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
