<?php

namespace Helper;

class Dashboard extends \Prefab
{
    protected $_issue;
    protected $_ownerIds;
    protected $_groupIds;
    protected $_groupUserIds;
    protected $_projects;
    protected $_order = "priority DESC, has_due_date ASC, due_date ASC, created_date DESC";

    public $defaultConfig = [
        "left" => ["projects", "subprojects", "bugs", "repeat_work", "watchlist"],
        "right" => ["tasks"]
    ];

    public $allWidgets = ["projects", "subprojects", "tasks", "bugs", "repeat_work", "watchlist", "my_comments", "recent_comments", "open_comments", "issue_tree"];

    public function getIssue()
    {
        return $this->_issue ?? ($this->_issue = new \Model\Issue\Detail());
    }

    public function getOwnerIds()
    {
        if ($this->_ownerIds) {
            return $this->_ownerIds;
        }
        $f3 = \Base::instance();
        $this->_ownerIds = [$f3->get("user.id")];
        $groups = new \Model\User\Group();
        foreach ($groups->find(["user_id = ?", $f3->get("user.id")]) as $r) {
            $this->_ownerIds[] = $r->group_id;
        }
        return $this->_ownerIds;
    }

    public function getGroupIds()
    {
        if ($this->_groupIds !== null) {
            return $this->_groupIds;
        }
        $f3 = \Base::instance();
        $groups = new \Model\User\Group();
        foreach ($groups->find(["user_id = ?", $f3->get("user.id")]) as $r) {
            $this->_groupIds[] = $r->group_id;
        }
        return $this->_groupIds;
    }

    public function getGroupUserIds()
    {
        if ($this->_groupUserIds !== null) {
            return $this->_groupUserIds;
        }
        $groups = new \Model\User\Group();
        $groupString = implode(",", $this->getGroupIds());
        foreach ($groups->find(["group_id IN (" . $groupString . ")"]) as $r) {
            $this->_groupUserIds[] = $r->user_id;
        }
        return $this->_groupUserIds;
    }

    public function projects()
    {
        $f3 = \Base::instance();
        $typeIds = [];
        foreach ($f3->get('issue_types') as $t) {
            if ($t->role == 'project') {
                $typeIds[] = $t->id;
            }
        }
        if (!$typeIds) {
            return [];
        }
        $ownerString = implode(",", $this->getOwnerIds());
        $typeIdStr = implode(",", $typeIds);
        $this->_projects = $this->getIssue()->find(
            ["owner_id IN ($ownerString) AND type_id IN ($typeIdStr) AND deleted_date IS NULL AND closed_date IS NULL AND status_closed = 0"],
            ["order" => $this->_order]
        );
        return $this->_projects;
    }

    public function subprojects()
    {
        if ($this->_projects === null) {
            $this->projects();
        }

        $projects = $this->_projects;
        $subprojects = [];
        foreach ($projects as $i => $project) {
            if ($project->parent_id) {
                $subprojects[] = $project;
                unset($projects[$i]);
            }
        }

        return $subprojects;
    }

    public function bugs()
    {
        $f3 = \Base::instance();
        $typeIds = [];
        foreach ($f3->get('issue_types') as $t) {
            if ($t->role == 'bug') {
                $typeIds[] = $t->id;
            }
        }
        if (!$typeIds) {
            return [];
        }
        $ownerString = implode(",", $this->getOwnerIds());
        $typeIdStr = implode(",", $typeIds);
        return $this->getIssue()->find(
            ["owner_id IN ($ownerString) AND type_id IN ($typeIdStr) AND deleted_date IS NULL AND closed_date IS NULL AND status_closed = 0"],
            ["order" => $this->_order]
        );
    }

    public function repeat_work()
    {
        $ownerString = implode(",", $this->getOwnerIds());
        return $this->getIssue()->find(
            "owner_id IN ($ownerString) AND deleted_date IS NULL AND closed_date IS NULL AND status_closed = 0 AND repeat_cycle IS NOT NULL",
            ["order" => $this->_order]
        );
    }

    public function watchlist()
    {
        $f3 = \Base::instance();
        $watchlist = new \Model\Issue\Watcher();
        return $watchlist->findby_watcher($f3->get("user.id"), $this->_order);
    }

    public function tasks()
    {
        $f3 = \Base::instance();
        $typeIds = [];
        foreach ($f3->get('issue_types') as $t) {
            if ($t->role == 'task') {
                $typeIds[] = $t->id;
            }
        }
        if (!$typeIds) {
            return [];
        }
        $ownerString = implode(",", $this->getOwnerIds());
        $typeIdStr = implode(",", $typeIds);
        return $this->getIssue()->find(
            ["owner_id IN ($ownerString) AND type_id IN ($typeIdStr) AND deleted_date IS NULL AND closed_date IS NULL AND status_closed = 0"],
            ["order" => $this->_order]
        );
    }

    public function my_comments()
    {
        $f3 = \Base::instance();
        $comment = new \Model\Issue\Comment\Detail();
        return $comment->find(["user_id = ? AND issue_deleted_date IS NULL", $f3->get("user.id")], ["order" => "created_date DESC", "limit" => 10]);
    }

    public function recent_comments()
    {
        $f3 = \Base::instance();

        $issue = new \Model\Issue();
        $ownerString = implode(",", $this->getOwnerIds());
        $issues = $issue->find(["owner_id IN ($ownerString) OR author_id = ? AND deleted_date IS NULL", $f3->get("user.id")]);
        if (!$issues) {
            return [];
        }

        $ids = [];
        foreach ($issues as $item) {
            $ids[] = $item->id;
        }

        if (!$ids) {
            return [];
        }
        $issueIds = implode(",", $ids);
        $comment = new \Model\Issue\Comment\Detail();
        return $comment->find(["issue_id IN ($issueIds) AND user_id != ?", $f3->get("user.id")], ["order" => "created_date DESC", "limit" => 15]);
    }

    public function open_comments()
    {
        $f3 = \Base::instance();

        $issue = new \Model\Issue();
        $ownerString = implode(",", $this->getOwnerIds());
        $issues = $issue->find(["(owner_id IN ($ownerString) OR author_id = ?) AND closed_date IS NULL AND deleted_date IS NULL", $f3->get("user.id")]);
        if (!$issues) {
            return [];
        }

        $ids = [];
        foreach ($issues as $item) {
            $ids[] = $item->id;
        }

        if (!$ids) {
            return [];
        }
        $issueIds = implode(",", $ids);
        $comment = new \Model\Issue\Comment\Detail();
        return $comment->find(["issue_id IN ($issueIds) AND user_id != ?", $f3->get("user.id")], ["order" => "created_date DESC", "limit" => 15]);
    }

    /**
     * Get data for Issue Tree widget
     * @return array
     */
    public function issue_tree()
    {
        $f3 = \Base::instance();
        $userId = $f3->get("this_user") ? $f3->get("this_user")->id : $f3->get("user.id");

        // Load assigned issues
        $issue = new \Model\Issue\Detail();
        $assigned = $issue->find(["closed_date IS NULL AND deleted_date IS NULL AND owner_id = ?", $userId]);

        // Build issue list
        $issues = [];
        $assigned_ids = [];
        $missing_ids = [];
        foreach ($assigned as $iss) {
            $issues[] = $iss->cast();
            $assigned_ids[] = $iss->id;
        }
        foreach ($issues as $iss) {
            if ($iss["parent_id"] && !in_array($iss["parent_id"], $assigned_ids)) {
                $missing_ids[] = $iss["parent_id"];
            }
        }
        while (!empty($missing_ids)) {
            $parents = $issue->find("id IN (" . implode(",", $missing_ids) . ")");
            foreach ($parents as $iss) {
                if (($key = array_search($iss->id, $missing_ids)) !== false) {
                    unset($missing_ids[$key]);
                }
                $issues[] = $iss->cast();
                $assigned_ids[] = $iss->id;
                if ($iss->parent_id && !in_array($iss->parent_id, $assigned_ids)) {
                    $missing_ids[] = $iss->parent_id;
                }
            }
        }

        // Convert list to tree
        $tree = $this->_buildTree($issues);

        /**
         * Helper function for recursive tree rendering
         * @param   array $issue
         * @var     callable $renderTree This function, required for recursive calls
         */
        $renderTree = function (&$issue, $level = 0) use (&$renderTree) {
            if (!empty($issue['id'])) {
                $f3 = \Base::instance();
                $hive = ["issue" => $issue, "dict" => $f3->get("dict"), "BASE" => $f3->get("BASE"), "level" => $level, "issue_type" => $f3->get("issue_type")];
                echo \Helper\View::instance()->render("issues/project/tree-item.html", "text/html", $hive);
                if (!empty($issue['children'])) {
                    foreach ($issue['children'] as $item) {
                        $renderTree($item, $level + 1);
                    }
                }
            }
        };
        $f3->set("renderTree", $renderTree);

        return $tree;
    }

    /**
     * Convert a flat issue array to a tree array. Child issues are added to
     * the 'children' key in each issue.
     * @param  array $array Flat array of issues, including all parents needed
     * @return array Tree array where each issue contains its child issues
     */
    protected function _buildTree($array)
    {
        $tree = [];

        // Create an associative array with each key being the ID of the item
        foreach ($array as $k => &$v) {
            $tree[$v['id']] = &$v;
        }

        // Loop over the array and add each child to their parent
        foreach ($tree as $k => &$v) {
            if (empty($v['parent_id'])) {
                continue;
            }
            $tree[$v['parent_id']]['children'][] = &$v;
        }

        // Loop over the array again and remove any items that don't have a parent of 0;
        foreach ($tree as $k => &$v) {
            if (empty($v['parent_id'])) {
                continue;
            }
            unset($tree[$k]);
        }

        return $tree;
    }
}
