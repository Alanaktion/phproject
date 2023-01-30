<?php

namespace Model;

/**
 * Class Issue
 *
 * @property int $id
 * @property int $status
 * @property int $type_id
 * @property string $name
 * @property string $description
 * @property ?int $parent_id
 * @property int $author_id
 * @property ?int $owner_id
 * @property int $priority
 * @property ?float $hours_total
 * @property ?float $hours_remaining
 * @property ?float $hours_spent
 * @property string $created_date
 * @property ?string $closed_date
 * @property ?string $deleted_date
 * @property ?string $start_date
 * @property ?string $due_date
 * @property ?string $repeat_cycle
 * @property ?int $sprint_id
 */
class Issue extends \Model
{
    protected $_table_name = "issue";
    protected $_heirarchy = null;
    protected $_children = null;
    protected static $requiredFields = ["type_id", "status", "name", "author_id"];

    /**
     * Create and save a new issue
     * @param  array $data
     * @param  bool  $notify
     * @return Issue
     */
    public static function create(array $data, bool $notify = true): Issue
    {
        // Normalize data
        if (isset($data["hours"])) {
            $data["hours_total"] = $data["hours"];
            $data["hours_remaining"] = $data["hours"];
            unset($data["hours"]);
        }
        if (!empty($data["due_date"])) {
            if (!preg_match("/[0-9]{4}(-[0-9]{2}){2}/", $data["due_date"])) {
                $data["due_date"] = date("Y-m-d", strtotime($data["due_date"]));
            }
            if (empty($data["sprint_id"]) && !empty($data['due_date_sprint'])) {
                $sprint = new Sprint();
                $sprint->load(["DATE(?) BETWEEN start_date AND end_date", $data["due_date"]]);
                $data["sprint_id"] = $sprint->id;
            }
        }
        if (empty($data["author_id"]) && $user_id = \Base::instance()->get("user.id")) {
            $data["author_id"] = $user_id;
        }

        // Create issue
        /** @var Issue $item */
        $item = parent::create($data);

        // Send creation notifications
        if ($notify) {
            $notification = \Helper\Notification::instance();
            $notification->issue_create($item->id);
        }

        // Return instance
        return $item;
    }

    /**
     * Get complete parent list for issue
     * @return array
     */
    public function getAncestors(): array
    {
        if ($this->_heirarchy !== null) {
            return $this->_heirarchy;
        }

        $issues = [];
        $issues[] = $this;
        $issueIds = [$this->id];
        $parentId = $this->parent_id;
        while ($parentId) {
            // Catch infinite loops early on, in case server isn't running linux :)
            if (in_array($parentId, $issueIds)) {
                $f3 = \Base::instance();
                $f3->set("error", "Issue parent tree contains an infinite loop. Issue {$parentId} is the first point of recursion.");
                break;
            }
            $issue = new Issue();
            $issue->load($parentId);
            if ($issue->id) {
                $issues[] = $issue;
                $parentId = $issue->parent_id;
                $issueIds[] = $issue->id;
            } else {
                // Handle nonexistent issues
                $f3 = \Base::instance();
                $f3->set("error", "Issue #{$issue->id} has a parent issue #{$issue->parent_id} that doesn't exist.");
                break;
            }
        }

        $this->_heirarchy = array_reverse($issues);
        return $this->_heirarchy;
    }

    /**
     * Remove messy whitespace from a string
     * @param  string $string
     * @return string
     */
    public static function clean(string $string): string
    {
        return preg_replace('/(?:(?:\r\n|\r|\n)\s*){2}/s', "\n\n", str_replace("\r\n", "\n", $string));
    }

    /**
     * Delete without sending notification
     * @param  bool $recursive
     * @return Issue
     */
    public function delete(bool $recursive = true): Issue
    {
        if (!$this->deleted_date) {
            $this->set("deleted_date", date("Y-m-d H:i:s"));
        }
        if ($recursive) {
            $this->_deleteTree();
        }
        return $this->save(false);
    }

    /**
     * Delete a complete issue tree
     * @return Issue
     */
    protected function _deleteTree(): Issue
    {
        $children = $this->find(["parent_id = ?", $this->id]);
        foreach ($children as $child) {
            $child->delete();
        }
        return $this;
    }

    /**
     * Restore a deleted issue without notifying
     * @param  bool $recursive
     * @return Issue
     */
    public function restore(bool $recursive = true): Issue
    {
        $this->set("deleted_date", null);
        if ($recursive) {
            $this->_restoreTree();
        }
        return $this->save(false);
    }

    /**
     * Restore a complete issue tree
     * @return Issue
     */
    protected function _restoreTree(): Issue
    {
        $children = $this->find(["parent_id = ? AND deleted_date IS NOT NULL", $this->id]);
        foreach ($children as $child) {
            $child->restore();
        }
        return $this;
    }

    /**
     * Repeat an issue by generating a minimal copy and setting new due date
     * @param  bool $notify
     * @return Issue
     */
    public function repeat(bool $notify = true): Issue
    {
        $repeatIssue = new Issue();
        $repeatIssue->name = $this->name;
        $repeatIssue->type_id = $this->type_id;
        $repeatIssue->parent_id = $this->parent_id;
        $repeatIssue->author_id = $this->author_id;
        $repeatIssue->owner_id = $this->owner_id;
        $repeatIssue->description = $this->description;
        $repeatIssue->priority = $this->priority;
        $repeatIssue->repeat_cycle = $this->repeat_cycle;
        $repeatIssue->hours_total = $this->hours_total;
        $repeatIssue->hours_remaining = $this->hours_total;
        $repeatIssue->created_date = date("Y-m-d H:i:s");

        // Find a due date in the future
        switch ($repeatIssue->repeat_cycle) {
            case 'daily':
                $repeatIssue->start_date = $this->start_date ? date("Y-m-d", strtotime("tomorrow")) : null;
                $repeatIssue->due_date = date("Y-m-d", strtotime("tomorrow"));
                break;
            case 'weekly':
                $repeatIssue->start_date = $this->start_date ? date("Y-m-d", strtotime($this->start_date . " +1 week")) : null;
                $repeatIssue->due_date = date("Y-m-d", strtotime($this->due_date . " +1 week"));
                break;
            case 'monthly':
                $repeatIssue->start_date = $this->start_date ? date("Y-m-d", strtotime($this->start_date . " +1 month")) : null;
                $repeatIssue->due_date = date("Y-m-d", strtotime($this->due_date . " +1 month"));
                break;
            case 'quarterly':
                $repeatIssue->start_date = $this->start_date ? date("Y-m-d", strtotime($this->start_date . " +3 months")) : null;
                $repeatIssue->due_date = date("Y-m-d", strtotime($this->due_date . " +3 months"));
                break;
            case 'semi_annually':
                $repeatIssue->start_date = $this->start_date ? date("Y-m-d", strtotime($this->start_date . " +6 months")) : null;
                $repeatIssue->due_date = date("Y-m-d", strtotime($this->due_date . " +6 months"));
                break;
            case 'annually':
                $repeatIssue->start_date = $this->start_date ? date("Y-m-d", strtotime($this->start_date . " +1 year")) : null;
                $repeatIssue->due_date = date("Y-m-d", strtotime($this->due_date . " +1 year"));
                break;
            case 'sprint':
                $sprint = new \Model\Sprint();
                $sprint->load(["start_date > NOW()"], ['order' => 'start_date']);
                $repeatIssue->start_date = $this->start_date ? $sprint->start_date : null;
                $repeatIssue->due_date = $sprint->end_date;
                break;
            default:
                $repeatIssue->repeat_cycle = 'none';
        }

        // If the issue was in a sprint before, put it in a sprint again.
        if ($this->sprint_id) {
            $sprint = new \Model\Sprint();
            $sprint->load(["end_date >= ? AND start_date <= ?", $repeatIssue->due_date, $repeatIssue->due_date], ['order' => 'start_date']);
            $repeatIssue->sprint_id = $sprint->id;
        }

        $repeatIssue->save();
        if ($notify) {
            $notification = \Helper\Notification::instance();
            $notification->issue_create($repeatIssue->id);
        }
        return $repeatIssue;
    }

    /**
     * Log and save an issue update
     * @param  bool $notify
     * @return Issue\Update
     */
    protected function _saveUpdate(bool $notify = true): Issue\Update
    {
        $f3 = \Base::instance();

        // Ensure issue is not tied to itself as a parent
        if ($this->id == $this->parent_id) {
            $this->parent_id = $this->_getPrev("parent_id");
        }

        // Log update
        $update = new \Model\Issue\Update();
        $update->issue_id = $this->id;
        $update->user_id = $f3->get("user.id");
        $update->created_date = date("Y-m-d H:i:s");
        if ($f3->exists("update_comment")) {
            $update->comment_id = $f3->get("update_comment")->id;
            $update->notify = (int)$notify;
        } else {
            $update->notify = 0;
        }
        $update->save();

        // Set hours_total to the hours_remaining value under certain conditions
        if (
            $this->hours_remaining && !$this->hours_total &&
            !$this->_getPrev('hours_remaining') &&
            !$this->_getPrev('hours_total')
        ) {
            $this->hours_total = $this->hours_remaining;
        }

        // Set hours remaining to 0 if the issue has been closed
        if ($this->closed_date && $this->hours_remaining) {
            $this->hours_remaining = 0;
        }

        // Create a new issue if repeating
        if ($this->closed_date && $this->repeat_cycle) {
            $this->repeat($notify);
            $this->repeat_cycle = null;
        }

        // Log updated fields
        $updated = 0;
        $importantChanges = 0;
        $importantFields = ['status', 'name', 'description', 'owner_id', 'priority', 'due_date'];
        foreach ($this->fields as $key => $field) {
            if ($field["changed"] && rtrim($field["value"] ?? '') != rtrim($this->_getPrev($key) ?? '')) {
                $updateField = new \Model\Issue\Update\Field();
                $updateField->issue_update_id = $update->id;
                $updateField->field = $key;
                $updateField->old_value = $this->_getPrev($key);
                $updateField->new_value = $field["value"];
                $updateField->save();
                $updated++;
                if ($key == 'sprint_id') {
                    $this->resetTaskSprints();
                }
                if (in_array($key, $importantFields)) {
                    $importantChanges++;
                }
            }
        }

        // Delete update if no fields were changed
        if (!$updated) {
            $update->delete();
        }

        // Set notify flag if important changes occurred
        if ($notify && $importantChanges) {
            $update->notify = 1;
            $update->save();
        }

        // Send back the update
        return $update->id ? $update : false;
    }

    /**
     * Log issue update, send notifications
     * @param  bool $notify
     * @return Issue
     */
    public function save(bool $notify = true): Issue
    {
        $f3 = \Base::instance();

        // Catch empty sprint at the lowest level here
        if ($this->sprint_id === 0) {
            $this->set("sprint_id", null);
        }

        // Censor credit card numbers if enabled
        if ($f3->get("security.block_ccs")) {
            if (preg_match("/([0-9]{3,4}-){3}[0-9]{3,4}/", $this->description)) {
                $this->set("description", preg_replace("/([0-9]{3,4}-){3}([0-9]{3,4})/", "************$2", $this->description));
            }
        }

        // Make dates correct
        if ($this->due_date) {
            $this->due_date = date("Y-m-d", strtotime($this->due_date));
        } else {
            $this->due_date = null;
        }
        if ($this->start_date) {
            $this->start_date = date("Y-m-d", strtotime($this->start_date));
        } else {
            $this->start_date = null;
        }

        // Only save valid repeat_cycle values
        if (!in_array($this->repeat_cycle, ['daily', 'weekly', 'monthly', 'quarterly', 'semi_annually', 'annually', 'sprint'])) {
            $this->repeat_cycle = null;
        }

        // Check if updating or inserting
        if ($this->query) {
            // Save issue updates and send notifications
            $update = $this->_saveUpdate($notify);
            $issue = parent::save();
            if ($notify && $update && $update->id && $update->notify) {
                $notification = \Helper\Notification::instance();
                $notification->issue_update($this->id, $update->id);
            }
        } else {
            // Set closed date if status is closed
            if (!$this->closed_date && $this->status) {
                $status = new Issue\Status();
                $status->load($this->status);
                if ($status->closed) {
                    $this->closed_date = date("Y-m-d H:i:s");
                }
            }
        }

        $return = empty($issue) ? parent::save() : $issue;
        $this->saveTags();
        return $return;
    }

    /**
     * Finds and saves the current issue's tags
     * @return Issue
     */
    public function saveTags(): Issue
    {
        $tag = new \Model\Issue\Tag();
        if ($this->id) {
            $tag->deleteByIssueId($this->id);
        }
        if (!$this->deleted_date) {
            $count = preg_match_all("/(?<=[^a-z\\/&]#|^#)[a-z][a-z0-9_-]*[a-z0-9]+(?=[^a-z\\/]|$)/i", $this->description, $matches);
            if ($count) {
                foreach ($matches[0] as $match) {
                    $tag->reset();
                    $tag->tag = preg_replace("/[_-]+/", "-", ltrim($match, "#"));
                    $tag->issue_id = $this->id;
                    $tag->save();
                }
            }
        }
        return $this;
    }

    /**
     * Duplicate issue and all sub-issues
     * @param  bool  $recursive
     * @return Issue New issue
     * @throws \Exception
     */
    public function duplicate(bool $recursive = true): Issue
    {
        if (!$this->id) {
            throw new \Exception('Cannot duplicate an issue that is not yet saved.');
        }

        $f3 = \Base::instance();

        $this->copyto("duplicating_issue");
        $f3->clear("duplicating_issue.id");
        $f3->clear("duplicating_issue.due_date");
        $f3->clear("duplicating_issue.hours_spent");

        $newIssue = new Issue();
        $newIssue->copyfrom("duplicating_issue");
        $newIssue->author_id = $f3->get("user.id");
        $newIssue->hours_remaining = $newIssue->hours_total;
        $newIssue->created_date = date("Y-m-d H:i:s");
        $newIssue->save();

        if ($recursive) {
            // Run the recursive function to duplicate the complete descendant tree
            $this->_duplicateTree($this->id, $newIssue->id);
        }

        return $newIssue;
    }

    /**
     * Duplicate a complete issue tree, starting from a duplicated issue created by duplicate()
     * @param  int $id
     * @param  int $newId
     * @return Issue $this
     */
    protected function _duplicateTree(int $id, int $newId): Issue
    {
        // Find all child issues
        $children = $this->find(["parent_id = ?", $id]);
        if (count($children)) {
            $f3 = \Base::instance();
            foreach ($children as $child) {
                if (!$child->deleted_date) {
                    // Duplicate issue
                    $child->copyto("duplicating_issue");
                    $f3->clear("duplicating_issue.id");
                    $f3->clear("duplicating_issue.due_date");
                    $f3->clear("duplicating_issue.hours_spent");

                    $newChild = new Issue();
                    $newChild->copyfrom("duplicating_issue");
                    $newChild->author_id = $f3->get("user.id");
                    $newChild->hours_remaining = $newChild->hours_total;
                    $newChild->parent_id = $newId;
                    $newChild->created_date = date("Y-m-d H:i:s");
                    $newChild->save(false);

                    // Duplicate issue's children
                    $this->_duplicateTree($child->id, $newChild->id);
                }
            }
        }
        return $this;
    }

    /**
     * Move all non-project children to same sprint
     * @param bool $replaceExisting
     * @return Issue $this
     */
    public function resetTaskSprints(bool $replaceExisting = true): Issue
    {
        $f3 = \Base::instance();
        if ($this->sprint_id) {
            $query = "UPDATE issue SET sprint_id = :sprint WHERE parent_id = :issue AND type_id != :type";
            if ($replaceExisting) {
                $query .= " AND sprint_id IS NULL";
            }
            $this->db->exec(
                $query,
                [
                    ":sprint" => $this->sprint_id,
                    ":issue" => $this->id,
                    ":type" => $f3->get("issue_type.project"),
                ]
            );
        }
        return $this;
    }

    /**
     * Get children of current issue
     * @return array
     */
    public function getChildren(): array
    {
        if ($this->_children !== null) {
            return $this->_children;
        }

        return $this->_children = $this->find(["parent_id = ? AND deleted_date IS NULL", $this->id]);
    }

    /**
     * Generate MD5 hashes for each column as a key=>value array
     * @return array
     */
    public function hashState(): array
    {
        $result = $this->cast();
        foreach ($result as &$value) {
            if ($value === null) {
                $value = md5('');
            } else {
                $value = md5($value);
            }
        }
        return $result;
    }

    /**
     * Close the issue
     * @return Issue $this
     */
    public function close(): Issue
    {
        if ($this->id && !$this->closed_date) {
            $status = new \Model\Issue\Status();
            $status->load(["closed = ?", 1]);
            $this->status = $status->id;
            $this->closed_date = date("Y-m-d H:i:s");
            $this->save();
        }
        return $this;
    }

    /**
     * Get array of all descendant IDs
     * @return array
     */
    public function descendantIds(): array
    {
        $ids = [$this->id];
        foreach ($this->getChildren() as $child) {
            $ids[] = $child->id;
            $ids = $ids + $child->descendantIds();
        }
        return array_unique($ids);
    }

    /**
     * Get aggregate totals across the project and its descendants
     * @return array
     */
    public function projectStats(): array
    {
        $total = 0;
        $complete = 0;
        $hoursSpent = 0;
        $hoursTotal = 0;
        if ($this->id) {
            $total++;
            if ($this->closed_date) {
                $complete++;
            }
            if ($this->hours_spent > 0) {
                $hoursSpent += $this->hours_spent;
            }
            if ($this->hours_total > 0) {
                $hoursTotal += $this->hours_total;
            }
            foreach ($this->getChildren() as $child) {
                $result = $child->projectStats();
                $total += $result["total"];
                $complete += $result["complete"];
                $hoursSpent += $result["hours_spent"];
                $hoursTotal += $result["hours_total"];
            }
        }
        return [
            "total" => $total,
            "complete" => $complete,
            "hours_spent" => $hoursSpent,
            "hours_total" => $hoursTotal,
        ];
    }

    /**
     * Check if the current/given should be allowed access to the issue.
     * @return bool
     */
    public function allowAccess(\Model\User $user = null): bool
    {
        $f3 = \Base::instance();
        if ($user === null) {
            $user = $f3->get("user_obj");
        }

        if ($user->role == 'admin') {
            return true;
        }

        if ($this->deleted_date) {
            return false;
        }

        if (!$f3->get('security.restrict_access')) {
            return true;
        }

        $helper = \Helper\Dashboard::instance();
        return ($this->owner_id == $user->id)
            || ($this->author_id == $user->id)
            || in_array($this->owner_id, $helper->getGroupIds());
    }
}
