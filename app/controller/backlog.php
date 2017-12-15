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
        $sprintModel = new \Model\Sprint();
        $backlogModel = new \Model\Issue\Backlog;
        $sprints = $sprintModel->find(array("end_date >= ?", $this->now(false)), array("order" => "start_date ASC"));

        $type = new \Model\Issue\Type;
        $projectTypes = $type->find(["role = ?", "project"]);
        $f3->set("project_types", $projectTypes);
        $typeIds = [];
        foreach ($projectTypes as $type) {
            $typeIds[] = $type->id;
        }
        $typeStr = implode(",", $typeIds);

        $issue = new \Model\Issue\Detail();

        $indexMap = [];
        $sprintDetails = [];
        foreach ($sprints as $sprint) {
            $projects = $issue->find(
                array("deleted_date IS NULL AND sprint_id = ? AND type_id IN ($typeStr)", $sprint->id),
                array('order' => 'priority DESC, due_date')
            );

            // Add sorted projects
            $sprintBacklog = [];
            $sort = $backlogModel->find(array("sprint_id = ?", $sprint->id));
            $sortArray = [];
            foreach ($sort as $row) {
                $sortArray[] = $row->issue_id;
                foreach ($projects as $p) {
                    if ($p->id == $row->issue_id) {
                        $indexMap[$p->id] = $row->index;
                        $sprintBacklog[] = $p;
                    }
                }
            }

            // Add remaining projects
            foreach ($projects as $p) {
                if (!in_array($p->id, $sortArray)) {
                    $sprintBacklog[] = $p;
                }
            }

            $sprintDetails[] = $sprint->cast() + array("projects" => $sprintBacklog);
        }

        $large_projects = $f3->get("db.instance")->exec("SELECT i.parent_id FROM issue i JOIN issue_type t ON t.id = i.type_id WHERE i.parent_id IS NOT NULL AND t.role = 'project'");
        $large_project_ids = [];
        foreach ($large_projects as $p) {
            $large_project_ids[] = $p["parent_id"];
        }

        // Load backlog
        if (!empty($large_project_ids)) {
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
        $sort = $backlogModel->find(array("sprint_id IS NULL"));
        $sortArray = [];
        foreach ($sort as $row) {
            $sortArray[] = $row->issue_id;
            foreach ($unset_projects as $p) {
                if ($p->id == $row->issue_id) {
                    $indexMap[$p->id] = $row->index;
                    $backlog[] = $p;
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

        $f3->set("groupid", $groupId);
        $f3->set("type_ids", $typeIds);
        $f3->set("sprints", $sprintDetails);
        $f3->set("backlog", $backlog);
        $f3->set("unsorted", $unsorted);
        $f3->set("indexMap", $indexMap);

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
    public function redirect(\Base $f3, array $params)
    {
        $f3->reroute("/backlog");
    }

    /**
     * POST /edit
     *
     * Move an item between sprints or within a backlog
     *
     * @param \Base $f3
     * @throws \Exception
     */
    public function edit($f3)
    {
        // Update backlog indexes
        $db = $f3->get('db.instance');
        $db->exec(
            'UPDATE `issue_backlog` SET `index` = `index` + 1 WHERE `sprint_id` = ? AND `index` >= ? ORDER BY `index` DESC',
            [1 => $f3->get('POST.to'), 2 => $f3->get('POST.index')]
        );

        // Create/update backlog item
        $item = new \Model\Issue\Backlog();
        $item->load(['issue_id = ?', $f3->get('POST.id')]);
        $item->sprint_id = $f3->get('POST.to') ? : null;
        $item->issue_id = $f3->get('POST.id');
        $item->index = $f3->get('POST.index');
        $item->save();

        // Update issue when sprint is changed
        if ($f3->get('POST.from') != $f3->get('POST.to')) {
            $issue = new \Model\Issue();
            $issue->load($f3->get('POST.id'));
            $issue->sprint_id = $f3->get('POST.to') ? : null;
            $issue->save();
        }

        $this->_printJson($item->cast());
    }

    /**
     * GET /backlog/old
     * @param \Base $f3
     */
    public function index_old($f3)
    {
        $sprintModel = new \Model\Sprint();
        $sprints = $sprintModel->find(array("end_date < ?", $this->now(false)), array("order" => "start_date DESC"));

        $type = new \Model\Issue\Type;
        $projectTypes = $type->find(["role = ?", "project"]);
        $f3->set("project_types", $projectTypes);
        $typeIds = [];
        foreach ($projectTypes as $type) {
            $typeIds[] = $type->id;
        }
        $typeStr = implode(",", $typeIds);

        $issue = new \Model\Issue\Detail();
        $sprint_details = array();
        foreach ($sprints as $sprint) {
            $projects = $issue->find(array("deleted_date IS NULL AND sprint_id = ? AND type_id IN ($typeStr)", $sprint->id));
            $sprint_details[] = $sprint->cast() + array("projects" => $projects);
        }

        $f3->set("sprints", $sprint_details);

        $f3->set("title", $f3->get("dict.backlog"));
        $f3->set("menuitem", "backlog");
        $this->_render("backlog/old.html");
    }
}
