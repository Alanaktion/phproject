<?php

namespace Controller;

class Issues extends \Controller
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
     * Clean a string for encoding in JSON
     * Collapses whitespace, then trims
     *
     * @param  string $string
     * @return string
     */
    protected function _cleanJson($string)
    {
        return trim(preg_replace('/\s+/', ' ', $string));
    }

    /**
     * Build a WHERE clause for issue listings based on the current filters and sort options
     *
     * @return array
     */
    protected function _buildFilter()
    {
        $f3 = \Base::instance();
        $db = $f3->get("db.instance");
        $issues = new \Model\Issue\Detail();

        // Filter issue listing by URL parameters
        $filter = [];
        $args = $f3->get("GET");
        foreach ($args as $key => $val) {
            if ($issues->exists($key)) {
                if ($val == '-1') {
                    $filter[$key] = null;
                } elseif (!empty($val) && !is_array($val)) {
                    $filter[$key] = $val;
                }
            }
        }
        unset($val);

        // Build SQL string to use for filtering
        $filter_str = "";
        foreach ($filter as $field => $val) {
            if ($field == "name") {
                $filter_str .= "`$field` LIKE " . $db->quote("%$val%") . " AND ";
            } elseif ($field == "status" && $val == "open") {
                $filter_str .= "status_closed = 0 AND ";
            } elseif ($field == "status" && $val == "closed") {
                $filter_str .= "status_closed = 1 AND ";
            } elseif ($field == "repeat_cycle" && $val == "repeat") {
                $filter_str .= "repeat_cycle IS NOT NULL AND ";
            } elseif ($field == "repeat_cycle" && $val == "none") {
                $filter_str .= "repeat_cycle IS NULL AND ";
            } elseif (($field == "author_id" || $field == "owner_id") && !empty($val) && is_numeric($val)) {
                // Find all users in a group if necessary
                $user = new \Model\User();
                $user->load($val);
                if ($user->role == 'group') {
                    $groupUsers = new \Model\User\Group();
                    $list = $groupUsers->find(['group_id = ?', $val]);
                    $groupUserArray = [$val]; // Include the group in the search
                    foreach ($list as $obj) {
                        $groupUserArray[] = $obj->user_id;
                    }
                    $filter_str .= "$field in (" . implode(",", $groupUserArray) . ") AND ";
                } else {
                    // Just select by user
                    $filter_str .= "$field = " . $db->quote($val) . " AND ";
                }
            } elseif ($val === null) {
                $filter_str .= "`$field` IS NULL AND ";
            } else {
                $filter_str .= "`$field` = " . $db->quote($val) . " AND ";
            }
        }
        unset($val);
        $filter_str .= " deleted_date IS NULL ";

        // Build SQL ORDER BY string
        $orderby = !empty($args['orderby']) ? $args['orderby'] : "priority";
        $filter["orderby"] = $orderby;
        $ascdesc = !empty($args['ascdesc']) && strtolower($args['ascdesc']) == 'asc' ? "ASC" : "DESC";
        $filter["ascdesc"] = $ascdesc;
        switch ($orderby) {
            case "id":
                $filter_str .= " ORDER BY id {$ascdesc} ";
                break;
            case "title":
                $filter_str .= " ORDER BY name {$ascdesc}";
                break;
            case "type":
                $filter_str .= " ORDER BY type_id {$ascdesc}, priority DESC, due_date DESC ";
                break;
            case "status":
                $filter_str .= " ORDER BY status {$ascdesc}, priority DESC, due_date DESC ";
                break;
            case "parent_id":
                $filter_str .= " ORDER BY parent_id {$ascdesc}, priority DESC, due_date DESC ";
                break;
            case "author":
                $filter_str .= " ORDER BY author_name {$ascdesc}, priority DESC, due_date DESC ";
                break;
            case "assignee":
                $filter_str .= " ORDER BY owner_name {$ascdesc}, priority DESC, due_date DESC ";
                break;
            case "created":
                $filter_str .= " ORDER BY created_date {$ascdesc}, priority DESC, due_date DESC ";
                break;
            case "due":
                $filter_str .= " ORDER BY due_date {$ascdesc}, priority DESC";
                break;
            case "sprint":
                $filter_str .= " ORDER BY sprint_start_date {$ascdesc}, priority DESC, due_date DESC ";
                break;
            case "closed":
                $filter_str .= " ORDER BY closed_date {$ascdesc}, priority DESC, due_date DESC ";
                break;
            case "priority":
            default:
                $filter_str .= " ORDER BY priority {$ascdesc}, due_date DESC ";
                break;
        }

        return [$filter, $filter_str];
    }

    /**
     * GET /issues
     * Display a sortable, filterable issue list
     *
     * @param  \Base  $f3
     */
    public function index($f3)
    {
        $issues = new \Model\Issue\Detail();

        // Get filter
        $args = $f3->get("GET");

        // load all issues if user is admin, otherwise load by group access
        $user = $f3->get("user_obj");
        if ($user->role == 'admin' || !$f3->get('security.restrict_access')) {
            [$filter, $filter_str] = $this->_buildFilter();
        } else {
            $helper = \Helper\Dashboard::instance();
            $groupString = implode(",", array_merge($helper->getGroupIds(), [$user->id]));

            // Get filter
            [$filter, $filter_str] = $this->_buildFilter();
            $filter_str = "(owner_id IN (" . $groupString . ")) AND " . $filter_str;
        }

        // Load type if a type_id was passed
        $type = new \Model\Issue\Type();
        if (!empty($args["type_id"])) {
            $type->load($args["type_id"]);
            if ($type->id) {
                $f3->set("title", $f3->get("dict.issues") . " - " . $f3->get("dict.by_type") . ": " . $type->name);
                $f3->set("type", $type);
            }
        } else {
            $f3->set("title", $f3->get("dict.issues"));
        }

        $status = new \Model\Issue\Status();
        $f3->set("statuses", $status->find(null, null, $f3->get("cache_expire.db")));

        $priority = new \Model\Issue\Priority();
        $f3->set("priorities", $priority->find(null, ["order" => "value DESC"], $f3->get("cache_expire.db")));

        $f3->set("types", $type->find(null, null, $f3->get("cache_expire.db")));

        $sprint = new \Model\Sprint();
        $f3->set("sprints", $sprint->find(["end_date >= ?", date("Y-m-d")], ["order" => "start_date ASC, id ASC"]));
        $f3->set("old_sprints", $sprint->find(["end_date < ?", date("Y-m-d")], ["order" => "start_date ASC, id ASC"]));

        $users = new \Model\User();
        $f3->set("users", $users->getAll());
        $f3->set("deleted_users", $users->getAllDeleted());
        $f3->set("groups", $users->getAllGroups());

        if (empty($args["page"])) {
            $args["page"] = 0;
        }
        $issue_page = $issues->paginate($args["page"], 50, $filter_str);
        $f3->set("issues", $issue_page);

        // Pass filter string for pagination
        $filter_get = http_build_query($filter);

        if (!empty($orderby)) {
            $filter_get  .= "&orderby=" . $orderby;
        }
        if ($issue_page["count"] > 7) {
            if ($issue_page["pos"] <= 3) {
                $min = 0;
            } else {
                $min = $issue_page["pos"] - 3;
            }
            if ($issue_page["pos"] < $issue_page["count"] - 3) {
                $max = $issue_page["pos"] + 3;
            } else {
                $max = $issue_page["count"] - 1;
            }
        } else {
            $min = 0;
            $max = $issue_page["count"] - 1;
        }
        $f3->set("pages", range($min, $max));
        $f3->set("filter_get", $filter_get);

        $f3->set("menuitem", "browse");
        $f3->set("heading_links_enabled", true);

        $f3->set("show_filters", true);
        $f3->set("show_export", true);

        $this->_render("issues/index.html");
    }

    /**
     * POST /issues/bulk_update
     * Update a list of issues
     *
     * @param \Base $f3
     */
    public function bulk_update($f3)
    {
        $this->validateCsrf();
        $post = $f3->get("POST");

        $issue = new \Model\Issue();
        if (!empty($post["id"]) && is_array($post["id"])) {
            foreach ($post["id"] as $id) {
                // Updating existing issue.
                $issue->load($id);
                if ($issue->id) {
                    if (!empty($post["update_copy"])) {
                        $issue = $issue->duplicate(false);
                    }

                    // Diff contents and save what's changed.
                    foreach ($post as $i => $val) {
                        if (
                            $issue->exists($i)
                            && $i != "id"
                            && $issue->$i != $val
                            && (!empty($val) || $val === "0")
                        ) {
                            // Allow setting to Not Assigned
                            if (($i == "owner_id" || $i == "sprint_id") && $val == -1) {
                                $val = null;
                            }
                            $issue->$i = $val;
                            if ($i == "status") {
                                $status = new \Model\Issue\Status();
                                $status->load($val);

                                // Toggle closed_date if issue has been closed/restored
                                if ($status->closed) {
                                    if (!$issue->closed_date) {
                                        $issue->closed_date = $this->now();
                                    }
                                } else {
                                    $issue->closed_date = null;
                                }
                            }
                        }
                    }

                    // Save to the sprint of the due date if no sprint selected
                    if (!empty($post['due_date']) && empty($post['sprint_id']) && !empty($post['due_date_sprint'])) {
                        $sprint = new \Model\Sprint();
                        $sprint->load(["DATE(?) BETWEEN start_date AND end_date", $issue->due_date]);
                        $issue->sprint_id = $sprint->id;
                    }

                    // If it's a child issue and the parent is in a sprint, assign to that sprint
                    if (!empty($post['bulk']['parent_id']) && !$issue->sprint_id) {
                        $parent = new \Model\Issue();
                        $parent->load($issue->parent_id);
                        if ($parent->sprint_id) {
                            $issue->sprint_id = $parent->sprint_id;
                        }
                    }

                    $notify = !empty($post["notify"]);
                    $issue->save($notify);
                } else {
                    $f3->error(500, "Failed to update all the issues, starting with: $id.");
                    return;
                }
            }
        }

        $f3->reroute("/issues?" . $post["url_query"]);
    }

    /**
     * GET /issues/export
     * Export a list of issues
     *
     * @param  \Base  $f3
     */
    public function export($f3)
    {
        $issue = new \Model\Issue\Detail();

        // Get filter data and load issues
        $filter = $this->_buildFilter();
        $issues = $issue->find($filter[1]);

        // Configure visible fields
        $fields = [
            "id" => $f3->get("dict.cols.id"),
            "name" => $f3->get("dict.cols.title"),
            "type_name" => $f3->get("dict.cols.type"),
            "priority_name" => $f3->get("dict.cols.priority"),
            "status_name" => $f3->get("dict.cols.status"),
            "author_name" => $f3->get("dict.cols.author"),
            "owner_name" => $f3->get("dict.cols.assignee"),
            "sprint_name" => $f3->get("dict.cols.sprint"),
            "created_date" => $f3->get("dict.cols.created"),
            "due_date" => $f3->get("dict.cols.due_date"),
            "closed_date" => $f3->get("dict.cols.closed_date"),
        ];

        // Notify browser that file is a CSV, send as attachment (force download)
        header("Content-type: text/csv");
        header("Content-Disposition: attachment; filename=issues-" . time() . ".csv");
        header("Pragma: no-cache");
        header("Expires: 0");

        // Output data directly
        $fh = fopen("php://output", "w");

        // Add column headings
        fwrite($fh, '"' . implode('","', array_values($fields)) . "\"\n");

        // Add rows
        foreach ($issues as $row) {
            $cols = [];
            foreach (array_keys($fields) as $field) {
                $cols[] = $row->get($field);
            }
            fputcsv($fh, $cols);
        }

        fclose($fh);
    }

    /**
     * GET /issues/new/@type
     * GET /issues/new/@type/@parent
     * Create a new issue
     *
     * @param \Base $f3
     */
    public function add($f3)
    {
        if ($f3->get("PARAMS.type")) {
            $type_id = $f3->get("PARAMS.type");
        } else {
            $type_id = 1;
        }

        $type = new \Model\Issue\Type();
        $type->load($type_id);

        if (!$type->id) {
            $f3->error(404, "Issue type does not exist");
            return;
        }

        if ($f3->get("PARAMS.parent")) {
            $parent_id = $f3->get("PARAMS.parent");
            $parent = new \Model\Issue();
            $parent->load(["id = ?", $parent_id]);
            if ($parent->id) {
                $f3->set("parent", $parent);
            }
        }

        $f3->set('owner_id', null);
        if ($f3->get("GET.owner_id")) {
            $f3->set("owner_id", $f3->get("GET.owner_id"));
        }

        $status = new \Model\Issue\Status();
        $f3->set("statuses", $status->find(null, null, $f3->get("cache_expire.db")));

        $priority = new \Model\Issue\Priority();
        $f3->set("priorities", $priority->find(null, ["order" => "value DESC"], $f3->get("cache_expire.db")));

        $sprint = new \Model\Sprint();
        $f3->set("sprints", $sprint->find(["end_date >= ?", $this->now(false)], ["order" => "start_date ASC, id ASC"]));

        $users = new \Model\User();

        $helper = \Helper\Dashboard::instance();

        // Load all issues if user is admin, otherwise load by group access
        $groupUserFilter = "";
        $groupFilter = "";

        $user = $f3->get("user_obj");
        if ($user->role != 'admin' && $f3->get('security.restrict_access')) {
            // TODO: restrict user/group list when user is not in any groups
            if ($helper->getGroupUserIds()) {
                $groupUserFilter = " AND id IN (" . implode(",", array_merge($helper->getGroupUserIds(), [$user->id])) . ")";
            }
            if ($helper->getGroupIds()) {
                $groupFilter = " AND id IN (" . implode(",", $helper->getGroupIds()) . ")";
            }
        }

        $f3->set("users", $users->find("deleted_date IS NULL AND role != 'group'" . $groupUserFilter, ["order" => "name ASC"]));
        $f3->set("groups", $users->find("deleted_date IS NULL AND role = 'group'" . $groupFilter, ["order" => "name ASC"]));
        $f3->set("title", $f3->get("dict.new_n", $type->name));
        $f3->set("menuitem", "new");
        $f3->set("type", $type);

        $this->_render("issues/edit.html");
    }

    /**
     * @param \Base $f3
     */
    public function add_selecttype($f3)
    {
        $type = new \Model\Issue\Type();
        $f3->set("types", $type->find(null, null, $f3->get("cache_expire.db")));

        $f3->set("title", $f3->get("dict.new_n", $f3->get("dict.issues")));
        $f3->set("menuitem", "new");
        $this->_render("issues/new.html");
    }

    /**
     * @param \Base $f3
     * @param array $params
     * @throws \Exception
     */
    public function edit($f3, $params)
    {
        $issue = new \Model\Issue\Detail();
        $issue->load($params["id"]);

        if (!$issue->id) {
            $f3->error(404, "Issue does not exist");
            return;
        }

        if (!$issue->allowAccess()) {
            $f3->error(403);
            return;
        }

        $type = new \Model\Issue\Type();
        $type->load($issue->type_id);

        $this->loadIssueMeta($issue);

        $f3->set("title", $f3->get("edit_n", $issue->id));
        $f3->set("issue", $issue);
        $f3->set("type", $type);

        if ($f3->get("AJAX")) {
            $this->_render("issues/edit-form.html");
        } else {
            $this->_render("issues/edit.html");
        }
    }

    /**
     * Load metadata lists for displaying issue edit forms
     * @param  \Model\Issue $issue
     * @return void
     */
    protected function loadIssueMeta(\Model\Issue $issue)
    {
        $f3 = \Base::instance();
        $status = new \Model\Issue\Status();
        $f3->set("statuses", $status->find(null, null, $f3->get("cache_expire.db")));

        $priority = new \Model\Issue\Priority();
        $f3->set("priorities", $priority->find(null, ["order" => "value DESC"], $f3->get("cache_expire.db")));

        $sprint = new \Model\Sprint();
        $sprintOrder = ["order" => "start_date ASC, id ASC"];
        $f3->set("sprints", $sprint->find(["end_date >= ?", $this->now(false)], $sprintOrder));
        $f3->set("old_sprints", $sprint->find(["end_date < ?", $this->now(false)], $sprintOrder));

        $users = new \Model\User();
        $helper = \Helper\Dashboard::instance();

        // load all issues if user is admin, otherwise load by group access
        $groupUserFilter = "";
        $groupFilter = "";

        $user = $f3->get("user_obj");
        if ($user->role != 'admin' && $f3->get('security.restrict_access')) {
            // TODO: restrict user/group list when user is not in any groups
            if ($helper->getGroupUserIds()) {
                $groupUserFilter = " AND id IN (" . implode(",", array_merge($helper->getGroupUserIds(), [$user->id])) . ")";
            }
            if ($helper->getGroupIds()) {
                $groupFilter = " AND id IN (" . implode(",", $helper->getGroupIds()) . ")";
            }
        }

        $f3->set("users", $users->find("deleted_date IS NULL AND role != 'group'" . $groupUserFilter, ["order" => "name ASC"]));
        $f3->set("groups", $users->find("deleted_date IS NULL AND role = 'group'" . $groupFilter, ["order" => "name ASC"]));
    }

    /**
     * POST /issues/close/@id
     * Close an issue
     *
     * @param \Base $f3
     * @param array $params
     * @throws \Exception
     */
    public function close($f3, $params)
    {
        $this->validateCsrf();
        $issue = new \Model\Issue();
        $issue->load($params["id"]);

        if (!$issue->id) {
            $f3->error(404, "Issue does not exist");
            return;
        }

        if (!$issue->allowAccess()) {
            $f3->error(403);
            return;
        }

        $issue->close();

        $f3->reroute("/issues/" . $issue->id);
    }

    /**
     * POST /issues/reopen/@id
     * Reopen an issue
     *
     * @param \Base $f3
     * @param array $params
     * @throws \Exception
     */
    public function reopen($f3, $params)
    {
        $this->validateCsrf();
        $issue = new \Model\Issue();
        $issue->load($params["id"]);

        if (!$issue->id) {
            $f3->error(404, "Issue does not exist");
            return;
        }

        if (!$issue->allowAccess()) {
            $f3->error(403);
            return;
        }

        if ($issue->closed_date) {
            $status = new \Model\Issue\Status();
            $status->load(["closed = ?", 0]);
            $issue->status = $status->id;
            $issue->closed_date = null;
            $issue->save();
        }

        $f3->reroute("/issues/" . $issue->id);
    }

    /**
     * POST /issues/copy/@id
     * Copy an issue
     *
     * @param \Base $f3
     * @param array $params
     * @throws \Exception
     */
    public function copy($f3, $params)
    {
        $this->validateCsrf();
        $issue = new \Model\Issue();
        $issue->load($params["id"]);

        if (!$issue->id) {
            $f3->error(404, "Issue does not exist");
            return;
        }

        if (!$issue->allowAccess()) {
            $f3->error(403);
            return;
        }

        $new_issue = $issue->duplicate();

        if ($new_issue->id) {
            $f3->reroute("/issues/" . $new_issue->id);
        } else {
            $f3->error(500, "Failed to duplicate issue.");
        }
    }

    /**
     * Save an updated issue
     *
     * @return \Model\Issue
     */
    protected function _saveUpdate()
    {
        $f3 = \Base::instance();

        // Remove parent if user has no rights to it
        if ($f3->get("POST.parent_id")) {
            $parentIssue = (new \Model\Issue())->load(intval($f3->get("POST.parent_id")));
            if (!$parentIssue->allowAccess()) {
                $f3->set("POST.parent_id", null);
            }
        }

        $post = array_map("trim", $f3->get("POST"));
        $issue = new \Model\Issue();

        // Load issue and return if not set
        $issue->load($post["id"]);
        if (!$issue->id) {
            return $issue;
        }

        $newSprint = false;

        // Diff contents and save what's changed.
        $hashState = json_decode($post["hash_state"], null, 512, JSON_THROW_ON_ERROR);
        foreach ($post as $i => $val) {
            if (
                $issue->exists($i)
                && $i != "id"
                && $issue->$i != $val
                && md5($val) != $hashState->$i
            ) {
                if ($i == 'sprint_id') {
                    $newSprint = empty($val) ? null : $val;
                }
                if (empty($val)) {
                    $issue->$i = null;
                } else {
                    $issue->$i = $val;

                    if ($i == "status") {
                        $status = new \Model\Issue\Status();
                        $status->load($val);

                        // Toggle closed_date if issue has been closed/restored
                        if ($status->closed) {
                            if (!$issue->closed_date) {
                                $issue->closed_date = $this->now();
                            }
                        } else {
                            $issue->closed_date = null;
                        }
                    }

                    // Save to the sprint of the due date unless one already set
                    if ($i == "due_date" && !empty($val)) {
                        if (empty($post['sprint_id']) && !empty($post['due_date_sprint'])) {
                            $sprint = new \Model\Sprint();
                            $sprint->load(["DATE(?) BETWEEN start_date AND end_date", $val]);
                            $issue->sprint_id = $sprint->id;
                            $newSprint = $sprint->id;
                        }
                    }
                }
            }
        }

        // Update child issues' sprint if sprint was changed
        if ($newSprint !== false) {
            $children = $issue->find([
                'parent_id = ? AND type_id IN (SELECT id FROM issue_type WHERE role = "task")',
                $issue->id,
            ]);
            foreach ($children as $child) {
                $child->sprint_id = $newSprint;
                $child->save(false);
            }
        }

        // Save comment if given
        if (!empty($post["comment"])) {
            $comment = new \Model\Issue\Comment();
            $comment->user_id = $this->_userId;
            $comment->issue_id = $issue->id;
            $comment->text = $post["comment"];
            $comment->created_date = $this->now();
            $comment->save();
            $f3->set("update_comment", $comment);
        }

        // Save issue, optionally send notifications
        $notify = !empty($post["notify"]);
        $issue->save($notify);

        return $issue;
    }

    /**
     * Create a new issue from $_POST
     *
     * @return \Model\Issue
     */
    protected function _saveNew()
    {
        $f3 = \Base::instance();
        $data = $f3->get("POST");
        $originalAuthor = null;
        if (!empty($data['author_id']) && $data['author_id'] != $this->_userId) {
            $originalAuthor = $data['author_id'];
            $data['author_id'] = $this->_userId;
        }

        // Remove parent if user has no rights to it
        if (!empty($data['parent_id'])) {
            $parentIssue = (new \Model\Issue())->load(intval($data['parent_id']));
            if (!$parentIssue->allowAccess()) {
                $data['parent_id'] = null;
            }
        }

        $issue = \Model\Issue::create($data, !!$f3->get("POST.notify"));
        if ($originalAuthor) {
            $issue->author_id = $originalAuthor;
            $issue->save(false);
        }
        return $issue;
    }

    /**
     * POST /issues
     * Save an issue
     *
     * @param \Base $f3
     */
    public function save($f3)
    {
        $this->validateCsrf();
        if ($f3->get("POST.id")) {
            // Updating existing issue.
            $issue = $this->_saveUpdate();
            if ($issue->id) {
                $f3->reroute("/issues/" . $issue->id);
            } else {
                $f3->error(404, "This issue does not exist.");
            }
        } elseif ($f3->get("POST.name")) {
            // Creating new issue.
            $issue = $this->_saveNew();
            if ($issue->id) {
                $f3->reroute("/issues/" . $issue->id);
            } else {
                $f3->error(500, "An error occurred saving the issue.");
            }
        } else {
            $f3->reroute("/issues/new/" . $f3->get("POST.type_id"));
        }
    }

    /**
     * GET /issues/@id
     * View an issue
     *
     * @param \Base $f3
     * @param array $params
     * @throws \Exception
     */
    public function single($f3, $params)
    {
        $issue = new \Model\Issue\Detail();
        $issue->load(["id=?", $params["id"]]);

        // load issue if user is admin, otherwise load by group access
        if (!$issue->id || !$issue->allowAccess()) {
            $f3->error(404);
            return;
        }

        $type = new \Model\Issue\Type();
        $type->load($issue->type_id);

        $f3->set("title", $type->name . " #" . $issue->id  . ": " . $issue->name);
        $f3->set("menuitem", "browse");

        $author = new \Model\User();
        $author->load($issue->author_id);
        $owner = new \Model\User();
        if ($issue->owner_id) {
            $owner->load($issue->owner_id);
        }

        $files = new \Model\Issue\File\Detail();
        $f3->set("files", $files->find(["issue_id = ? AND deleted_date IS NULL", $issue->id]));

        if ($issue->sprint_id) {
            $sprint = new \Model\Sprint();
            $sprint->load($issue->sprint_id);
            $f3->set("sprint", $sprint);
        }

        $watching = new \Model\Issue\Watcher();
        $watching->load(["issue_id = ? AND user_id = ?", $issue->id, $this->_userId]);
        $f3->set("watching", !!$watching->id);

        $f3->set("issue", $issue);
        $f3->set("ancestors", $issue->getAncestors());
        $f3->set("type", $type);
        $f3->set("author", $author);
        $f3->set("owner", $owner);

        $comments = new \Model\Issue\Comment\Detail();
        $f3->set("comments", $comments->find(["issue_id = ?", $issue->id], ["order" => "created_date DESC, id DESC"]));

        $this->loadIssueMeta($issue);

        $this->_render("issues/single.html");
    }

    /**
     * POST /issues/@id/watchers
     * Add a watcher
     *
     * @param \Base $f3
     * @param array $params
     */
    public function add_watcher($f3, $params)
    {
        $this->validateCsrf();

        $issue = new \Model\Issue();
        $issue->load(["id=?", $params["id"]]);
        if (!$issue->id) {
            $f3->error(404);
        }

        $watcher = new \Model\Issue\Watcher();

        // Loads just in case the user is already a watcher
        $watcher->load(["issue_id = ? AND user_id = ?", $issue->id, $f3->get("POST.user_id")]);
        if (!$watcher->id) {
            $watcher->issue_id = $issue->id;
            $watcher->user_id = $f3->get("POST.user_id");
            $watcher->save();
        }
    }

    /**
     * POST /issues/@id/watchers/delete
     * Delete a watcher
     *
     * @param \Base $f3
     * @param array $params
     */
    public function delete_watcher($f3, $params)
    {
        $this->validateCsrf();

        $issue = new \Model\Issue();
        $issue->load(["id=?", $params["id"]]);
        if (!$issue->id) {
            $f3->error(404);
        }

        $watcher = new \Model\Issue\Watcher();

        $watcher->load(["issue_id = ? AND user_id = ?", $issue->id, $f3->get("POST.user_id")]);
        $watcher->delete();
    }

    /**
     * POST /issues/@id/dependencies
     * Add a dependency
     *
     * @param \Base $f3
     * @param array $params
     */
    public function add_dependency($f3, $params)
    {
        $this->validateCsrf();

        $issue = new \Model\Issue();
        $issue->load(["id=?", $params["id"]]);
        if (!$issue->id) {
            $f3->error(404);
        }

        $dependency = new \Model\Issue\Dependency();

        // Loads just in case the task is already a dependency
        $dependency->load(["issue_id = ? AND dependency_id = ?", $issue->id, $f3->get("POST.id")]);
        $dependency->issue_id = $f3->get("POST.issue_id");
        $dependency->dependency_id = $f3->get("POST.dependency_id");
        $dependency->dependency_type = $f3->get("POST.type");
        $dependency->save();
    }

    /**
     * POST /issues/@id/dependencies/delete
     * Delete a dependency
     *
     * @param \Base $f3
     * @param array $params
     */
    public function delete_dependency($f3, $params)
    {
        $this->validateCsrf();

        $issue = new \Model\Issue();
        $issue->load(["id=?", $params["id"]]);
        if (!$issue->id) {
            $f3->error(404);
        }

        $dependency = new \Model\Issue\Dependency();
        $dependency->load($f3->get("POST.id"));
        $dependency->delete();
    }

    /**
     * GET /issues/@id/history
     * AJAX call for issue history
     *
     * @param \Base $f3
     * @param array $params
     */
    public function single_history($f3, $params)
    {
        // Build updates array
        $updates_array = [];
        $update_model = new \Model\Custom("issue_update_detail");
        $updates = $update_model->find(["issue_id = ?", $params["id"]], ["order" => "created_date DESC, id DESC"]);
        foreach ($updates as $update) {
            $update_array = $update->cast();
            $update_field_model = new \Model\Issue\Update\Field();
            $update_array["changes"] = $update_field_model->find(["issue_update_id = ?", $update["id"]]);
            $updates_array[] = $update_array;
        }

        $f3->set("updates", $updates_array);

        $this->_printJson([
            "total" => count($updates),
            "html" => $this->_cleanJson(\Helper\View::instance()->render("issues/single/history.html")),
        ]);
    }

    /**
     * GET /issues/@id/related
     * AJAX call for related issues
     *
     * @param \Base $f3
     * @param array $params
     * @throws \Exception
     */
    public function single_related($f3, $params)
    {
        $issue = new \Model\Issue();
        $issue->load($params["id"]);

        if (!$issue->id) {
            return $f3->error(404);
        }

        if (!$issue->allowAccess()) {
            return $f3->error(403);
        }

        $f3->set("parent", $issue);

        $issues = new \Model\Issue\Detail();
        if ($exclude = $f3->get("GET.exclude")) {
            $searchParams = ["parent_id = ? AND id != ? AND deleted_date IS NULL", $issue->id, $exclude];
        } else {
            $searchParams = ["parent_id = ? AND deleted_date IS NULL", $issue->id];
        }
        $orderParams = ["order" => "status_closed, priority DESC, due_date"];
        $f3->set("issues", $issues->find($searchParams, $orderParams));

        $searchParams[0] = $searchParams[0] . " AND status_closed = 0";
        $openIssues = $issues->count($searchParams);

        $this->_printJson([
            "total" => count($f3->get("issues")),
            "open" => $openIssues,
            "html" => $this->_cleanJson(\Helper\View::instance()->render("issues/single/related.html")),
        ]);
    }

    /**
     * GET /issues/@id/watchers
     * AJAX call for issue watchers
     *
     * @param \Base $f3
     * @param array $params
     */
    public function single_watchers($f3, $params)
    {
        $watchers = new \Model\Custom("issue_watcher_user");
        $f3->set("watchers", $watchers->find(["issue_id = ?", $params["id"]]));
        $users = new \Model\User();
        $f3->set("users", $users->find("deleted_date IS NULL AND role != 'group'", ["order" => "name ASC"]));

        $this->_printJson([
            "total" => count($f3->get("watchers")),
            "html" => $this->_cleanJson(\Helper\View::instance()->render("issues/single/watchers.html")),
        ]);
    }

    /**
     * GET /issues/@id/dependencies
     * AJAX call for issue dependencies
     *
     * @param \Base $f3
     * @param array $params
     * @throws \Exception
     */
    public function single_dependencies($f3, $params)
    {
        $issue = new \Model\Issue();
        $issue->load($params["id"]);

        if ($issue->id) {
            $f3->set("issue", $issue);
            $dependencies = new \Model\Issue\Dependency();
            $f3->set("dependencies", $dependencies->findby_issue($issue->id));
            $f3->set("dependents", $dependencies->findby_dependent($issue->id));

            $this->_printJson([
                "total" => count($f3->get("dependencies")) + count($f3->get("dependents")),
                "html" => $this->_cleanJson(\Helper\View::instance()->render("issues/single/dependencies.html")),
            ]);
        } else {
            $f3->error(404);
        }
    }

    /**
     * POST /issues/delete/@id
     * Delete an issue
     *
     * @param \Base $f3
     * @param array $params
     * @throws \Exception
     */
    public function single_delete($f3, $params)
    {
        $this->validateCsrf();
        $issue = new \Model\Issue();
        $issue->load($params["id"]);
        $user = $f3->get("user_obj");
        if ($user->role == "admin" || $user->rank >= \Model\User::RANK_MANAGER || $issue->author_id == $user->id) {
            $issue->delete();
            $f3->reroute("/issues/{$issue->id}");
        } else {
            $f3->error(403);
        }
    }

    /**
     * POST /issues/undelete/@id
     * Un-delete an issue
     *
     * @param \Base $f3
     * @param array $params
     * @throws \Exception
     */
    public function single_undelete($f3, $params)
    {
        $this->validateCsrf();
        $issue = new \Model\Issue();
        $issue->load($params["id"]);
        $user = $f3->get("user_obj");
        if ($user->role == "admin" || $user->rank >= \Model\User::RANK_MANAGER || $issue->author_id == $user->id) {
            $issue->restore();
            $f3->reroute("/issues/{$issue->id}");
        } else {
            $f3->error(403);
        }
    }

    /**
     * POST /issues/comment/save
     * Save a comment
     *
     * @param \Base $f3
     * @throws \Exception
     */
    public function comment_save($f3)
    {
        $this->validateCsrf();
        $post = $f3->get("POST");

        $issue = new \Model\Issue();
        $issue->load($post["issue_id"]);

        if (!$issue->id || empty($post["text"])) {
            if ($f3->get("AJAX")) {
                $this->_printJson(["error" => 1]);
            } else {
                $f3->reroute("/issues/" . $post["issue_id"]);
            }
            return;
        }

        if ($f3->get("POST.action") == "close") {
            $issue->close();
        }

        $comment = \Model\Issue\Comment::create(["issue_id" => $post["issue_id"], "user_id" => $this->_userId, "text" => trim($post["text"])], !!$f3->get("POST.notify"));

        if ($f3->get("AJAX")) {
            $this->_printJson([
                "id" => $comment->id,
                "text" => \Helper\View::instance()->parseText($comment->text, ["hashtags" => false]),
                "date_formatted" => date("D, M j, Y \\a\\t g:ia", \Helper\View::instance()->utc2local(time())),
                "user_name" => $f3->get('user.name'),
                "user_username" => $f3->get('user.username'),
                "user_email" => $f3->get('user.email'),
                "user_email_md5" => md5(strtolower($f3->get('user.email'))),
            ]);
            return;
        } else {
            $f3->reroute("/issues/" . $comment->issue_id);
        }
    }

    /**
     * POST /issues/comment/delete
     * Delete a comment
     *
     * @param \Base $f3
     * @throws \Exception
     */
    public function comment_delete($f3)
    {
        $this->validateCsrf();
        $this->_requireAdmin();
        $comment = new \Model\Issue\Comment();
        $comment->load($f3->get("POST.id"));
        $comment->delete();
        $this->_printJson(["id" => $f3->get("POST.id")] + $comment->cast());
    }

    /**
     * POST /issues/file/delete
     * Delete a file
     *
     * @param \Base $f3
     * @throws \Exception
     */
    public function file_delete($f3)
    {
        $this->validateCsrf();
        $file = new \Model\Issue\File();
        $file->load($f3->get("POST.id"));
        $file->delete();
        $this->_printJson($file->cast());
    }

    /**
     * POST /issues/file/undelete
     * Un-delete a file
     *
     * @param \Base $f3
     * @throws \Exception
     */
    public function file_undelete($f3)
    {
        $this->validateCsrf();
        $file = new \Model\Issue\File();
        $file->load($f3->get("POST.id"));
        $file->deleted_date = null;
        $file->save();
        $this->_printJson($file->cast());
    }

    /**
     * Build an issue search query WHERE clause
     *
     * @param  string $q User query string
     * @return array  [string, keyword, ...]
     */
    protected function _buildSearchWhere($q)
    {
        if (!$q) {
            return ["deleted_date IS NULL"];
        }
        $return = [];

        // Build WHERE string
        $keywordParts = [];
        foreach (explode(" ", $q) as $w) {
            $keywordParts[] = "CONCAT(name, description, author_name, owner_name,
                author_username, owner_username) LIKE ?";
            $return[] = "%$w%";
        }
        if (is_numeric($q)) {
            $where = "id = ? OR ";
            array_unshift($return, $q);
        } else {
            $where = "";
        }
        $where .= "(" . implode(" AND ", $keywordParts) . ") AND deleted_date IS NULL";

        // Add WHERE string to return array
        array_unshift($return, $where);
        return $return;
    }

    /**
     * GET /search
     * Search for issues
     *
     * @param \Base $f3
     */
    public function search($f3)
    {
        $q = $f3->get("GET.q");
        if (preg_match("/^#([0-9]+)$/", $q, $matches)) {
            $f3->reroute("/issues/{$matches[1]}");
        }

        $issues = new \Model\Issue\Detail();

        $args = $f3->get("GET");
        if (empty($args["page"])) {
            $args["page"] = 0;
        }

        $where = $this->_buildSearchWhere($q);
        if (empty($args["closed"])) {
            $where[0] .= " AND status_closed = '0'";
        }

        // load search for all issues if user is admin, otherwise load by group access
        $user = $f3->get("user_obj");
        if ($user->role != 'admin') {
            $helper = \Helper\Dashboard::instance();
            $groupString = implode(",", array_merge($helper->getGroupIds(), [$user->id]));
            $where[0] .= " AND (owner_id IN (" . $groupString . "))";
        }

        $issue_page = $issues->paginate($args["page"], 50, $where, ["order" => "created_date DESC, id DESC"]);
        $f3->set("issues", $issue_page);

        if ($issue_page["count"] > 7) {
            if ($issue_page["pos"] <= 3) {
                $min = 0;
            } else {
                $min = $issue_page["pos"] - 3;
            }
            if ($issue_page["pos"] < $issue_page["count"] - 3) {
                $max = $issue_page["pos"] + 3;
            } else {
                $max = $issue_page["count"] - 1;
            }
        } else {
            $min = 0;
            $max = $issue_page["count"] - 1;
        }
        $f3->set("pages", range($min, $max));

        $f3->set("show_filters", false);
        $this->_render("issues/search.html");
    }

    /**
     * POST /issues/upload
     * Upload a file
     *
     * @param \Base $f3
     * @throws \Exception
     */
    public function upload($f3)
    {
        $this->validateCsrf();
        $user_id = $this->_userId;

        $issue = new \Model\Issue();
        $issue->load(["id=? AND deleted_date IS NULL", $f3->get("POST.issue_id")]);
        if (!$issue->id) {
            $f3->error(404);
            return;
        }

        $web = \Web::instance();

        $f3->set("UPLOADS", "uploads/" . date("Y") . "/" . date("m") . "/");
        if (!is_dir($f3->get("UPLOADS"))) {
            mkdir($f3->get("UPLOADS"), 0777, true);
        }
        $overwrite = false; // set to true to overwrite an existing file; Default: false
        $slug = true; // rename file to filesystem-friendly version

        // Make a good name
        $orig_name = preg_replace("/[^A-Z0-9._-]/i", "_", $_FILES['attachment']['name']);
        $_FILES['attachment']['name'] = time() . "_" . $orig_name;

        // Blacklist certain file types
        if ($f3->get('security.file_blacklist')) {
            if (preg_match($f3->get('security.file_blacklist'), $orig_name)) {
                $f3->error(415);
                return;
            }
        }

        $i = 0;
        $parts = pathinfo($_FILES['attachment']['name']);
        while (file_exists($f3->get("UPLOADS") . $_FILES['attachment']['name'])) {
            $i++;
            $_FILES['attachment']['name'] = $parts["filename"] . "-" . $i . "." . $parts["extension"];
        }

        $web->receive(
            function ($file) use ($f3, $orig_name, $user_id, $issue) {
                if ($file['size'] > $f3->get("files.maxsize")) {
                    return false;
                }

                $newfile = new \Model\Issue\File();
                $newfile->issue_id = $issue->id;
                $newfile->user_id = $user_id;
                $newfile->filename = $orig_name;
                $newfile->disk_filename = $file['name'];
                $newfile->disk_directory = $f3->get("UPLOADS");
                $newfile->filesize = $file['size'];
                $newfile->content_type = $file['type'];
                $newfile->digest = md5_file($file['tmp_name']);
                $newfile->created_date = date("Y-m-d H:i:s");
                $newfile->save();
                $f3->set('file_id', $newfile->id);

                return true; // moves file from php tmp dir to upload dir
            },
            $overwrite,
            $slug
        );

        if ($f3->get("POST.text")) {
            $comment = new \Model\Issue\Comment();
            $comment->user_id = $this->_userId;
            $comment->issue_id = $issue->id;
            $comment->text = $f3->get("POST.text");
            $comment->created_date = $this->now();
            $comment->file_id = $f3->get('file_id');
            $comment->save();
            if (!!$f3->get("POST.notify")) {
                $notification = \Helper\Notification::instance();
                $notification->issue_comment($issue->id, $comment->id);
            }
        } elseif ($f3->get('file_id') && !!$f3->get("POST.notify")) {
            $notification = \Helper\Notification::instance();
            $notification->issue_file($issue->id, $f3->get("file_id"));
        }

        $f3->reroute("/issues/" . $issue->id);
    }

    /**
     * GET /issues/parent_ajax
     * Load all matching issues
     *
     * @param  \Base $f3
     */
    public function parent_ajax($f3)
    {
        if (!$f3->get("AJAX")) {
            $f3->error(400);
        }

        $user = $f3->get("user_obj");
        $searchFilter = "";
        if ($user->role != 'admin' && $f3->get('security.restrict_access')) {
            // Determine the search string if user is not admin
            $helper = \Helper\Dashboard::instance();
            $groupString = implode(",", array_merge($helper->getGroupIds(), [$user->id]));
            $searchFilter = "(owner_id IN (" . $groupString . ")) AND ";
        }

        $term = trim($f3->get('GET.q'));
        $results = [];

        $issue = new \Model\Issue();
        if ((substr($term, 0, 1) == '#') && is_numeric(substr($term, 1))) {
            $id = (int) substr($term, 1);
            $issues = $issue->find([$searchFilter . 'id LIKE ?', $id . '%'], ['limit' => 20]);

            foreach ($issues as $row) {
                $results[] = ['id' => $row->get('id'), 'text' => $row->get('name')];
            }
        } elseif (is_numeric($term)) {
            $id = (int) $term;
            $issues = $issue->find([$searchFilter . '((id LIKE ?) OR (name LIKE ?))', $id . '%', '%' . $id . '%'], ['limit' => 20]);

            foreach ($issues as $row) {
                $results[] = ['id' => $row->get('id'), 'text' => $row->get('name')];
            }
        } else {
            $issues = $issue->find([$searchFilter . 'name LIKE ?', '%' . addslashes($term) . '%'], ['limit' => 20]);

            foreach ($issues as $row) {
                $results[] = ['id' => $row->get('id'), 'text' => $row->get('name')];
            }
        }

        $this->_printJson(['results' => $results]);
    }
}
