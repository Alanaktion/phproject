<?php

namespace Controller\Api;

class Issues extends \Controller\Api
{
    /**
     * Converts an issue into a Redmine API-style multidimensional array
     * This isn't pretty.
     * @param  Detail $issue
     * @return array
     */
    protected function _issueMultiArray(\Model\Issue\Detail $issue)
    {
        $casted = $issue->cast();

        // Convert ALL the fields!
        $result = [];
        $result["tracker"] = [
            "id" => $issue->type_id,
            "name" => $issue->type_name
        ];
        $result["status"] = [
            "id" => $issue->status,
            "name" => $issue->status_name
        ];
        $result["priority"] = [
            "id" => $issue->priority_id,
            "name" => $issue->priority_name
        ];
        $result["author"] = [
            "id" => $issue->author_id,
            "name" => $issue->author_name,
            "username" => $issue->author_username,
            "email" => $issue->author_email,
            "task_color" => $issue->author_task_color
        ];
        $result["owner"] = [
            "id" => $issue->owner_id,
            "name" => $issue->owner_name,
            "username" => $issue->owner_username,
            "email" => $issue->owner_email,
            "task_color" => $issue->owner_task_color
        ];
        if (!empty($issue->sprint_id)) {
            $result["sprint"] = [
                "id" => $issue->sprint_id,
                "name" => $issue->sprint_name,
                "start_date" => $issue->sprint_start_date,
                "end_date" => $issue->sprint_end_date
            ];
        }

        // Remove redundant fields
        foreach (array_keys($issue->schema()) as $key) {
            if (preg_match("/(type|status|priority|author|owner|sprint)_.+|has_due_date/", $key)) {
                unset($casted[$key]);
            }
        }

        return array_replace($casted, $result);
    }

    // Get a list of issues
    public function get($f3)
    {
        $issue = new \Model\Issue\Detail();

        // Build filter string
        $filter = [];
        $get = $f3->get("GET");
        $db = $f3->get("db.instance");
        foreach ($issue->fields(false) as $i) {
            if (isset($get[$i])) {
                $filter[] = "`$i` = " . $db->quote($get[$i]);
            }
        }
        $filter_str = $filter ? implode(' AND ', $filter) : null;

        // Build options
        $options = [];
        if ($f3->get("GET.order")) {
            $options["order"] = $f3->get("GET.order") . " " . $f3->get("GET.ascdesc");
        }

        // Load issues
        $result = $issue->paginate(
            $f3->get("GET.offset") / ($f3->get("GET.limit") ?: 30),
            $f3->get("GET.limit") ?: 30,
            $filter_str,
            $options
        );

        // Build result objects
        $issues = [];
        foreach ($result["subset"] as $iss) {
            $issues[] = $this->_issueMultiArray($iss);
        }

        // Output response
        $this->_printJson([
            "total_count" => $result["total"],
            "limit" => $result["limit"],
            "issues" => $issues,
            "offset" => $result["pos"] * $result["limit"],
        ]);
    }

    // Create a new issue
    public function post($f3)
    {
        if ($_REQUEST) {
            // By default, use standard HTTP POST fields
            $post = $_REQUEST;
        } else {
            // For Redmine compatibility, also accept a JSON object
            try {
                $post = json_decode(file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
            } catch (\Exception $e) {
                throw new \Exception("Unable to parse input");
            }

            if (!empty($post["issue"])) {
                $post = $post["issue"];
            }

            // Convert Redmine names to Phproject names
            if (!empty($post["subject"])) {
                $post["name"] = $post["subject"];
            }
            if (!empty($post["parent_issue_id"])) {
                $post["parent_id"] = $post["parent_issue_id"];
            }
            if (!empty($post["tracker_id"])) {
                $post["type_id"] = $post["tracker_id"];
            }
            if (!empty($post["assigned_to_id"])) {
                $post["owner_id"] = $post["assigned_to_id"];
            }
            if (!empty($post["fixed_version_id"])) {
                $post["sprint_id"] = $post["fixed_version_id"];
            }
        }

        // Ensure a status ID is added
        if (!empty($post["status_id"])) {
            $post["status"] = $post["status_id"];
        }
        if (empty($post["status"])) {
            $post["status"] = 1;
        }


        // Verify the required "name" field is passed
        if (empty($post["name"])) {
            $f3->error("The 'name' value is required.");
            return;
        }

        // Verify given values are valid (types, statuses, priorities)
        if (!empty($post["type_id"])) {
            $type = new \Model\Issue\Type();
            $type->load($post["type_id"]);
            if (!$type->id) {
                $f3->error("The 'type_id' field is not valid.");
                return;
            }
        }
        if (!empty($post["parent_id"])) {
            $parent = new \Model\Issue();
            $parent->load($post["parent_id"]);
            if (!$parent->id) {
                $f3->error("The 'type_id' field is not valid.");
                return;
            }
        }
        if (!empty($post["status"])) {
            $status = new \Model\Issue\Status();
            $status->load($post["status"]);
            if (!$status->id) {
                $f3->error("The 'status' field is not valid.");
                return;
            }
        }
        if (!empty($post["priority_id"])) {
            $priority = new \Model\Issue\Priority();
            $priority->load(["value" => $post["priority_id"]]);
            if (!$priority->id) {
                $f3->error("The 'priority_id' field is not valid.");
                return;
            }
        }

        // Create a new issue based on the data
        $issue = new \Model\Issue();

        $issue->author_id = !empty($post["author_id"]) ? $post["author_id"] : $this->_userId;
        $issue->name = trim($post["name"]);
        $issue->type_id = empty($post["type_id"]) ? 1 : $post["type_id"];
        $issue->priority_id = empty($post["priority_id"]) ? $f3->get("issue_priority.default") : $post["priority_id"];
        $issue->status = empty($status) ? 1 : $status->id;

        // Set due date if valid
        if (!empty($post["due_date"]) && preg_match("/^[0-9]{4}-[0-9]{1,2}-[0-9]{1,2}( [0-9:]{8})?$/", $post["due_date"])) {
            $issue->due_date = $post["due_date"];
        } elseif (!empty($post["due_date"]) && $due_date = strtotime($post["due_date"])) {
            $issue->due_date = date("Y-m-d", $due_date);
        }

        if (!empty($post["description"])) {
            $issue->description = $post["description"];
        }
        if (!empty($post["parent_id"])) {
            $issue->parent_id = $post["parent_id"];
        }
        if (!empty($post["owner_id"])) {
            $issue->owner_id = $post["owner_id"];
        }

        $issue->save();

        $notification = \Helper\Notification::instance();
        $notification->issue_create($issue->id);

        $this->_printJson(["issue" => $issue->cast()]);
    }

    // Update an existing issue
    public function single_put($f3, $params)
    {
        $issue = new \Model\Issue();
        $issue->load($params["id"]);

        if (!$issue->id) {
            $f3->error(404);
            return;
        }

        $updated = [];
        foreach ($f3->get("REQUEST") as $key => $val) {
            if (is_scalar($val) && $issue->exists($key)) {
                $updated[] = $key;
                $issue->set($key, $val);
            }
        }

        if ($updated) {
            $issue->save();
        }

        $this->_printJson(["updated_fields" => $updated, "issue" => $this->_issueMultiArray($issue)]);
    }

    // Get a single issue's details
    public function single_get($f3, $params)
    {
        $issue = new \Model\Issue\Detail();
        $issue->load($params["id"]);
        if ($issue->id) {
            $this->_printJson(["issue" => $this->_issueMultiArray($issue)]);
        } else {
            $f3->error(404);
        }
    }

    // Delete a single issue
    public function single_delete($f3, $params)
    {
        $issue = new \Model\Issue();
        $issue->load($params["id"]);
        $issue->delete();

        if (!$issue->id) {
            $f3->error(404);
            return;
        }

        $this->_printJson(["deleted" => $params["id"]]);
    }

    // List issue comments
    public function single_comments($f3, $params)
    {
        $issue = new \Model\Issue();
        $issue->load($params["id"]);
        if (!$issue->id) {
            $f3->error(404);
            return;
        }

        $comment = new \Model\Issue\Comment\Detail();
        $comments = $comment->find(["issue_id = ?", $issue->id], ["order" => "created_date DESC"]);

        $return = [];
        foreach ($comments as $item) {
            $return[] = $item->cast();
        }

        $this->_printJson($return);
    }

    // Add a comment on an issue
    public function single_comments_post($f3, $params)
    {
        $issue = new \Model\Issue();
        $issue->load($params["id"]);
        if (!$issue->id) {
            $f3->error(404);
            return;
        }

        $data = ["issue_id" => $issue->id, "user_id" => $this->_userId, "text" => $f3->get("POST.text")];
        $comment = \Model\Issue\Comment::create($data);
        $this->_printJson($comment->cast());
    }

    // List issue types
    public function types($f3)
    {
        $types = $f3->get("issue_types");
        $return = [];
        foreach ($types as $type) {
            $return[] = $type->cast();
        }
        $this->_printJson($return);
    }

    // List issue tags
    public function tag()
    {
        $tag = new \Model\Issue\Tag();
        $tags = $tag->cloud();
        $this->_printJson($tags);
    }

    // List issues by tag
    public function tag_single($f3, $params)
    {
        $tag = new \Model\Issue\Tag();
        $issueIds = $tag->issues($params['tag']);
        $return = [];
        if ($issueIds) {
            $issue = new \Model\Issue\Detail();
            $issues = $issue->find(["id IN (" . implode(",", $issueIds) . ") AND deleted_date IS NULL"]);
            foreach ($issues as $item) {
                $return[] = $this->_issueMultiArray($item);
            }
        }

        $this->_printJson($return);
    }

    // List sprints
    public function sprints()
    {
        $sprint_model = new \Model\Sprint();
        $sprints = $sprint_model->find(["end_date >= ?", $this->now(false)], ["order" => "start_date ASC"]);
        $return = [];
        foreach ($sprints as $sprint) {
            $return[] = $sprint->cast();
        }
        $this->_printJson($return);
    }

    // List past sprints
    public function sprints_old()
    {
        $sprint_model = new \Model\Sprint();
        $sprints = $sprint_model->find(["end_date < ?", $this->now(false)], ["order" => "start_date ASC"]);
        $return = [];
        foreach ($sprints as $sprint) {
            $return[] = $sprint->cast();
        }
        $this->_printJson($return);
    }
}
