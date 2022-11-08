<?php

namespace Controller;

class Backlog extends \Controller
{
    protected $_userId;

    public function __construct()
    {
        $this->_userId = $this->_requireLogin();
    }

    /**
     * GET /backlog
     *
     * @param \Base $f3
     */
    public function index($f3)
    {
        $sprint_model = new \Model\Sprint();
        $sprints = $sprint_model->find(["end_date >= ?", $this->now(false)], ["order" => "start_date ASC"]);

        $type = new \Model\Issue\Type();
        $projectTypes = $type->find(["role = ?", "project"]);
        $f3->set("project_types", $projectTypes);
        $typeIds = [];
        foreach ($projectTypes as $type) {
            $typeIds[] = $type->id;
        }
        $typeStr = implode(",", $typeIds);

        $issue = new \Model\Issue\Detail();

        $sprint_details = [];
        foreach ($sprints as $sprint) {
            $projects = $issue->find(
                ["deleted_date IS NULL AND sprint_id = ? AND type_id IN ($typeStr)", $sprint->id],
                ['order' => 'priority DESC, due_date']
            );

            // Add sorted projects
            $sprintBacklog = [];
            $sortOrder = new \Model\Issue\Backlog();
            $sortOrder->load(["sprint_id = ?", $sprint->id]);
            $sortArray = [];
            if ($sortOrder->id) {
                $sortArray = json_decode($sortOrder->issues, null, 512, JSON_THROW_ON_ERROR) ?? [];
                $sortArray = array_unique($sortArray);
                foreach ($sortArray as $id) {
                    foreach ($projects as $p) {
                        if ($p->id == $id) {
                            $sprintBacklog[] = $p;
                        }
                    }
                }
            }

            // Add remaining projects
            foreach ($projects as $p) {
                if (!in_array($p->id, $sortArray)) {
                    $sprintBacklog[] = $p;
                }
            }

            $sprint_details[] = $sprint->cast() + ["projects" => $sprintBacklog];
        }

        $large_projects = $f3->get("db.instance")->exec("SELECT i.parent_id FROM issue i JOIN issue_type t ON t.id = i.type_id WHERE i.parent_id IS NOT NULL AND t.role = 'project' AND i.deleted_date IS NULL");
        $large_project_ids = [];
        foreach ($large_projects as $p) {
            $large_project_ids[] = $p["parent_id"];
        }

        // Load backlog
        if (!empty($large_project_ids)) {
            $large_project_ids = implode(",", array_unique($large_project_ids));
            $unset_projects = $issue->find(
                ["deleted_date IS NULL AND sprint_id IS NULL AND type_id IN ($typeStr) AND status_closed = '0' AND id NOT IN ({$large_project_ids})"],
                ['order' => 'priority DESC, due_date']
            );
        } else {
            $unset_projects = $issue->find(
                ["deleted_date IS NULL AND sprint_id IS NULL AND type_id IN ($typeStr) AND status_closed = '0'"],
                ['order' => 'priority DESC, due_date']
            );
        }

        // Add sorted projects
        $backlog = [];
        $sortOrder = new \Model\Issue\Backlog();
        $sortOrder->load(["sprint_id IS NULL"]);
        $sortArray = [];
        if ($sortOrder->id) {
            $sortArray = json_decode($sortOrder->issues, null, 512, JSON_THROW_ON_ERROR) ?? [];
            $sortArray = array_unique($sortArray);
            foreach ($sortArray as $id) {
                foreach ($unset_projects as $p) {
                    if ($p->id == $id) {
                        $backlog[] = $p;
                    }
                }
            }
        }

        // Add remaining projects
        $unsorted = [];
        foreach ($unset_projects as $p) {
            if (!in_array($p->id, $sortArray)) {
                $unsorted[] = $p;
            }
        }

        $user = new \Model\User();
        $f3->set("groups", $user->getAllGroups());

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
     */
    public function redirect(\Base $f3)
    {
        $f3->reroute("/backlog");
    }

    /**
     * POST /edit
     * @param \Base $f3
     * @throws \Exception
     */
    public function edit($f3)
    {
        $this->validateCsrf();

        // Move project
        $post = $f3->get("POST");
        $issue = new \Model\Issue();
        $issue->load($post["id"]);
        $issue->sprint_id = empty($post["sprint_id"]) ? null : $post["sprint_id"];
        $issue->save();

        // Move tasks within project
        $tasks = $issue->find([
            'parent_id = ? AND type_id IN (SELECT id FROM issue_type WHERE role = "task")',
            $issue->id,
        ]);
        foreach ($tasks as $task) {
            $task->sprint_id = $issue->sprint_id;
            $task->save();
        }
    }

    /**
     * POST /sort
     * @param \Base $f3
     * @throws \Exception
     */
    public function sort($f3)
    {
        $this->validateCsrf();
        $this->_requireLogin(\Model\User::RANK_MANAGER);
        $backlog = new \Model\Issue\Backlog();
        if ($f3->get("POST.sprint_id")) {
            $backlog->load(["sprint_id = ?", $f3->get("POST.sprint_id")]);
            $backlog->sprint_id = $f3->get("POST.sprint_id");
        } else {
            $backlog->load(["sprint_id IS NULL"]);
        }
        $backlog->issues = $f3->get("POST.items");
        $backlog->save();
    }

    /**
     * GET /backlog/old
     * @param \Base $f3
     */
    public function index_old($f3)
    {
        $sprint_model = new \Model\Sprint();
        $sprints = $sprint_model->find(["end_date < ?", $this->now(false)], ["order" => "start_date DESC"]);

        $type = new \Model\Issue\Type();
        $projectTypes = $type->find(["role = ?", "project"]);
        $f3->set("project_types", $projectTypes);
        $typeIds = [];
        foreach ($projectTypes as $type) {
            $typeIds[] = $type->id;
        }
        $typeStr = implode(",", $typeIds);

        $issue = new \Model\Issue\Detail();
        $sprint_details = [];
        foreach ($sprints as $sprint) {
            $projects = $issue->find(["deleted_date IS NULL AND sprint_id = ? AND type_id IN ($typeStr)", $sprint->id]);
            $sprint_details[] = $sprint->cast() + ["projects" => $projects];
        }

        $f3->set("sprints", $sprint_details);

        $f3->set("title", $f3->get("dict.backlog"));
        $f3->set("menuitem", "backlog");
        $this->_render("backlog/old.html");
    }
}
