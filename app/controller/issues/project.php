<?php

namespace Controller\Issues;

class Project extends \Controller
{
    protected $_userId;

    /**
     * Require login on new
     */
    public function __construct()
    {
        $this->_userId = $this->_requireLogin();
    }

    /**
     * GET /issues/project/@id
     * Project Overview action
     *
     * @param  \Base $f3
     * @param  array $params
     */
    public function overview($f3, $params)
    {
        // Load issue
        $project = new \Model\Issue\Detail();
        $project->load($params["id"]);
        if (!$project->id) {
            $f3->error(404);
            return;
        }

        $f3->set("stats", $project->projectStats());


        // Find all nested issues
        $model = new \Model\Issue\Detail();
        $parentMap = [];
        $parents = [$project->id];
        do {
            $pStr = implode(',', array_map('intval', $parents));
            $level = $model->find(["parent_id IN ($pStr) AND deleted_date IS NULL"]);
            if (!$level) {
                break;
            }
            $parents = [];
            foreach ($level as $row) {
                $parentMap[$row->parent_id][] = $row;
                $parents[] = $row->id;
            }
        } while (true);

        /**
         * Helper function for recursive tree rendering
         * @param   \Model\Issue $issue
         * @param   int          $level
         * @var     callable $renderTree This function, required for recursive calls
         */
        $renderTree = function (\Model\Issue &$issue, $level = 0) use ($parentMap, &$renderTree) {
            if ($issue->id) {
                $f3 = \Base::instance();
                $children = $parentMap[$issue->id] ?? [];
                $hive = [
                    "issue" => $issue,
                    "children" => $children,
                    "dict" => $f3->get("dict"),
                    "BASE" => $f3->get("BASE"),
                    "level" => $level,
                    "issue_type" => $f3->get("issue_type")
                ];
                echo \Helper\View::instance()->render("issues/project/tree-item.html", "text/html", $hive);
                if ($children) {
                    foreach ($children as $item) {
                        $renderTree($item, $level + 1);
                    }
                }
            }
        };
        $f3->set("renderTree", $renderTree);


        // Render view
        $f3->set("project", $project);
        $f3->set("title", $project->type_name . " #" . $project->id  . ": " . $project->name . " - " . $f3->get("dict.project_overview"));
        $this->_render("issues/project.html");
    }

    /**
     * GET /issues/project/@id/files
     * Get the file list for a project
     *
     * @param  \Base $f3
     * @param  array  $params
     */
    public function files($f3, array $params)
    {
        // Load issue
        $project = new \Model\Issue();
        $project->load($params["id"]);
        if (!$project->id) {
            $f3->error(404);
            return;
        }

        $files = new \Model\Issue\File\Detail();
        $issueIds = $project->descendantIds();
        $idStr = implode(',', $issueIds);

        $f3->set("files", $files->find("issue_id IN ($idStr) AND deleted_date IS NULL"));
        $this->_render('issues/project/files.html');
    }
}
