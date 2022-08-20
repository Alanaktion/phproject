<?php

namespace Controller;

class Taskboard extends \Controller
{
    public function __construct()
    {
        $this->_userId = $this->_requireLogin();
    }

    /**
     * Get a list of users from a filter
     * @param  string $params URL Parameters
     * @return array
     */
    protected function _filterUsers($params)
    {
        if ($params["filter"] == "groups") {
            $group_model = new \Model\User\Group();
            $groups_result = $group_model->find(["user_id = ?", $this->_userId]);
            $filter_users = [$this->_userId];
            foreach ($groups_result as $g) {
                $filter_users[] = $g["group_id"];
            }
            $groups = implode(",", $filter_users);
            $users_result = $group_model->find("group_id IN ({$groups})");
            foreach ($users_result as $u) {
                $filter_users[] = $u["user_id"];
            }
        } elseif ($params["filter"] == "me") {
            $filter_users = [$this->_userId];
        } elseif (is_numeric($params["filter"])) {
            $user = new \Model\User();
            $user->load($params["filter"]);
            if ($user->role == 'group') {
                \Base::instance()->set("filterGroup", $user);
                $group_model = new \Model\User\Group();
                $users_result = $group_model->find(["group_id = ?", $user->id]);
                $filter_users = [intval($params["filter"])];
                foreach ($users_result as $u) {
                    $filter_users[] = $u["user_id"];
                }
            } else {
                $filter_users = [$params["filter"]];
            }
        } elseif ($params["filter"] == "all") {
            return [];
        } else {
            return [$this->_userId];
        }
        return $filter_users;
    }

    /**
     * GET /taskboard
     * GET /taskboard/@id
     * GET /taskboard/@id/@filter
     *
     * View a taskboard
     *
     * @param \Base $f3
     * @param array $params
     */
    public function index($f3, $params)
    {
        $sprint = new \Model\Sprint();

        // Load current sprint if no sprint ID is given
        if (empty($params["id"]) || !intval($params["id"])) {
            $localDate = date('Y-m-d', \Helper\View::instance()->utc2local());
            $sprint->load(["? BETWEEN start_date AND end_date", $localDate]);
            if (!$sprint->id) {
                $f3->error(404);
                return;
            }
        }

        // Default to showing group tasks
        if (empty($params["filter"])) {
            $params["filter"] = "groups";
        }

        // Load the requested sprint
        if (!$sprint->id) {
            $sprint->load($params["id"]);
            if (!$sprint->id) {
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
        $statuses = $status->find(['taskboard > 0'], ['order' => 'taskboard_sort ASC']);
        $mapped_statuses = [];
        $visible_status_ids = [];
        $column_count = 0;
        foreach ($statuses as $s) {
            $visible_status_ids[] = $s->id;
            $mapped_statuses[$s->id] = $s;
            $column_count += $s->taskboard;
        }

        $visible_status_ids = implode(",", $visible_status_ids);
        $f3->set("statuses", $mapped_statuses);
        $f3->set("column_count", $column_count);

        // Load issue priorities
        $priority = new \Model\Issue\Priority();
        $f3->set("priorities", $priority->find(null, ["order" => "value DESC"], $f3->get("cache_expire.db")));

        // Load project list
        $issue = new \Model\Issue\Detail();

        // Determine type filtering
        $type = new \Model\Issue\Type();
        $projectTypes = $type->find(["role = ?", "project"]);
        $f3->set("project_types", $projectTypes);
        if ($f3->get("GET.type_id")) {
            $typeIds = array_filter($f3->split($f3->get("GET.type_id")), "is_numeric");
        } else {
            $typeIds = [];
            foreach ($projectTypes as $type) {
                $typeIds[] = $type->id;
            }
        }
        $typeStr = implode(",", $typeIds);
        sort($typeIds, SORT_NUMERIC);

        // Find all visible tasks
        $tasks = $issue->find([
            "sprint_id = ? AND type_id NOT IN ($typeStr) AND deleted_date IS NULL AND status IN ($visible_status_ids)"
                . (empty($filter_users) ? "" : " AND owner_id IN (" . implode(",", $filter_users) . ")"),
            $sprint->id
        ], ["order" => "priority DESC, id ASC"]);
        $task_ids = [];
        $parent_ids = [0];
        foreach ($tasks as $task) {
            $task_ids[] = $task->id;
            if ($task->parent_id) {
                $parent_ids[] = $task->parent_id;
            }
        }
        $task_ids_str = implode(",", $task_ids);
        $parent_ids_str = implode(",", $parent_ids);
        $f3->set("tasks", $task_ids_str);

        // Find all visible projects or parent tasks if no type filter is given
        $queryArray = [
            "(id IN ($parent_ids_str) AND type_id IN ($typeStr)) OR (sprint_id = ? AND type_id IN ($typeStr) AND deleted_date IS NULL"
                . (empty($filter_users) ? ")" : " AND owner_id IN (" . implode(",", $filter_users) . "))"),
            $sprint->id
        ];
        $projects = $issue->find($queryArray, ["order" => "owner_id ASC, priority DESC"]);

        // Sort projects if a filter is given
        $sortModel = new \Model\Issue\Backlog();
        $sortOrder = $sortModel->load(["sprint_id = ?", $sprint->id]);
        if ($sortOrder) {
            $sortArray = json_decode($sortOrder->issues, null, 512, JSON_THROW_ON_ERROR) ?: [];
            $sortArray = array_unique($sortArray);
            usort($projects, function (\Model\Issue $a, \Model\Issue $b) use ($sortArray) {
                $ka = array_search($a->id, $sortArray);
                $kb = array_search($b->id, $sortArray);
                if ($ka === false && $kb !== false) {
                    return -1;
                }
                if ($ka !== false && $kb === false) {
                    return 1;
                }
                return $ka <=> $kb;
            });
        }

        // Build multidimensional array of all tasks and projects
        $taskboard = [];
        foreach ($projects as $project) {
            // Build array of statuses to put tasks under
            $columns = [];
            foreach ($statuses as $status) {
                $columns[$status["id"]] = [];
            }

            // Add current project's tasks
            foreach ($tasks as $task) {
                if ($task->parent_id == $project->id || $project->id == 0 && (!$task->parent_id || !in_array($task->parent_id, $parent_ids))) {
                    $columns[$task->status][] = $task;
                }
            }

            // Add hierarchical structure to taskboard array
            $taskboard[] = [
                "project" => $project,
                "columns" => $columns
            ];
        }

        $f3->set("type_ids", $typeIds);
        $f3->set("taskboard", array_values($taskboard));
        $f3->set("filter", $params["filter"]);

        // Get user list for select
        $users = new \Model\User();
        $f3->set("users", $users->getAll());
        $f3->set("groups", $users->getAllGroups());

        // Get next/previous sprints
        $f3->set("nextSprint", $sprint->findone(['start_date >= ?', $sprint->end_date], ['order' => 'start_date asc']));
        $f3->set("prevSprint", $sprint->findone(['end_date <= ?', $sprint->start_date], ['order' => 'end_date desc']));

        $this->_render("taskboard/index.html");
    }

    /**
     * GET /taskboard/@id/burndown
     * GET /taskboard/@id/burndown/@filter
     *
     * Load the hourly burndown chart data
     *
     * @param  \Base $f3
     * @param  array $params
     */
    public function burndown($f3, $params)
    {
        $sprint = new \Model\Sprint();
        $sprint->load($params["id"]);

        if (!$sprint->id) {
            $f3->error(404);
            return;
        }

        $db = $f3->get("db.instance");

        $user = new \Model\User();
        $user->load(["id = ?", $params["filter"]]);
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
        while ($cur < $end) {
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
     * POST /taskboard/saveManHours
     *
     * Save man hours for a group/user
     *
     * @param  \Base $f3
     */
    public function saveManHours($f3)
    {
        $this->validateCsrf();
        $user = new \Model\User();
        $user->load(["id = ?", $f3->get("POST.user_id")]);
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
     * POST /taskboard/add
     *
     * Add a new task
     *
     * @param \Base $f3
     */
    public function add($f3)
    {
        $this->validateCsrf();
        $post = $f3->get("POST");
        $post['sprint_id'] = $post['sprintId'];
        $post['name'] = $post['title'];
        $post['owner_id'] = $post['assigned'] ?: null;
        $post['due_date'] = $post['dueDate'];
        $post['parent_id'] = $post['storyId'];
        $issue = \Model\Issue::create($post);
        $this->_printJson($issue->cast() + ["taskId" => $issue->id]);
    }

    /**
     * POST /taskboard/edit/@id
     *
     * Update an existing task
     *
     * @param \Base $f3
     */
    public function edit($f3)
    {
        $this->validateCsrf();
        $post = $f3->get("POST");
        $issue = new \Model\Issue();
        $issue->load($post["taskId"]);
        if (!empty($post["receiver"])) {
            if ($post["receiver"]["story"]) {
                $issue->parent_id = $post["receiver"]["story"];
            }
            $issue->status = $post["receiver"]["status"];
            $status = new \Model\Issue\Status();
            $status->load($issue->status);
            if ($status->closed) {
                if (!$issue->closed_date) {
                    $issue->closed_date = $this->now();
                }
            } else {
                $issue->closed_date = null;
            }
        } else {
            $issue->name = $post["title"];
            $issue->description = $post["description"];
            $issue->owner_id = $post["assigned"] ?: null;
            $issue->hours_remaining = floatval($post["hours"] ?? null) ?: 0;
            $issue->hours_spent += floatval($post["hours_spent"] ?? null) ?: 0;
            if (!empty($post["hours_spent"]) && !empty($post["burndown"])) {
                $issue->hours_remaining -= $post["hours_spent"];
            }
            if (!$issue->hours_remaining || $issue->hours_remaining < 0) {
                $issue->hours_remaining = 0;
            }
            if (!empty($post["dueDate"])) {
                $issue->due_date = date("Y-m-d", strtotime($post["dueDate"]));
            } else {
                $issue->due_date = null;
            }
            if (isset($post["repeat_cycle"])) {
                $issue->repeat_cycle = $post["repeat_cycle"] ?: null;
            }
            $issue->priority = $post["priority"];
            if (!empty($post["storyId"])) {
                $issue->parent_id = $post["storyId"];
            }
            $issue->title = $post["title"];
        }

        if (!empty($post["comment"])) {
            $comment = new \Model\Issue\Comment();
            $comment->user_id = $this->_userId;
            $comment->issue_id = $issue->id;
            if (!empty($post["hours_spent"])) {
                $comment->text = trim($post["comment"]) . sprintf(" (%s %s spent)", $post["hours_spent"], $post["hours_spent"] == 1 ? "hour" : "hours");
            } else {
                $comment->text = $post["comment"];
            }
            $comment->created_date = $this->now();
            $comment->save();
            $issue->update_comment = $comment->id;
        }

        $issue->save();

        $this->_printJson($issue->cast() + ["taskId" => $issue->id]);
    }
}
