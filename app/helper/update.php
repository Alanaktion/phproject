<?php

namespace Helper;

class Update extends \Prefab
{
    protected $cache = [];

    /**
     * Generate human-readable data for issue updates
     * @param  string $field
     * @param  string|int $old_val
     * @param  string|int $new_val
     * @return array
     */
    public function humanReadableValues($field, $old_val, $new_val)
    {
        $f3 = \Base::instance();

        // Generate human readable values
        $func = $f3->camelcase("convert_$field");
        if (is_callable([$this, $func])) {
            if ($old_val !== null && $old_val !== '') {
                $old_val = call_user_func_array(
                    [$this, $func],
                    [$old_val]
                );
            }
            if ($new_val !== null && $new_val !== '') {
                $new_val = call_user_func_array(
                    [$this, $func],
                    [$new_val]
                );
            }
        }

        // Generate human readable field name
        $name = $f3->get("dict.cols." . $field);
        if ($name === null) {
            $name = ucwords(str_replace(
                ["_", " id"],
                [" ", ""],
                $field
            ));
        }

        return ["field" => $name, "old" => $old_val, "new" => $new_val];
    }

    /**
     * Convert a user ID to a user name
     * @param int $id
     * @return string
     */
    public function convertUserId($id)
    {
        if (isset($this->cache['user.' . $id])) {
            $user = $this->cache['user.' . $id];
        } else {
            $user = new \Model\User();
            $user->load($id);
            $this->cache['user.' . $id] = $user;
        }
        return $user->name;
    }

    /**
     * Convert an owner user ID to a name
     * @param int $id
     * @return string
     */
    public function convertOwnerId($id)
    {
        return $this->convertUserId($id);
    }

    /**
     * Convert an author user ID to a name
     * @param int $id
     * @return string
     */
    public function convertAuthorId($id)
    {
        return $this->convertUserId($id);
    }

    /**
     * Convert a status ID to a name
     * @param int $id
     * @return string
     */
    public function convertStatus($id)
    {
        if (isset($this->cache['status.' . $id])) {
            $status = $this->cache['status.' . $id];
        } else {
            $status = new \Model\Issue\Status();
            $status->load($id);
            $this->cache['status.' . $id] = $status;
        }
        return $status->name;
    }

    /**
     * Convert a priority ID to a name
     * @param int $value
     * @return string
     */
    public function convertPriority($value)
    {
        if (isset($this->cache['priority.' . $value])) {
            $priority = $this->cache['priority.' . $value];
        } else {
            $priority = new \Model\Issue\Priority();
            $priority->load(["value = ?", $value]);
            $this->cache['priority.' . $value] = $priority;
        }
        return $priority->name;
    }

    /**
     * Convert an issue ID to a name
     * @param int $id
     * @return string
     */
    public function convertIssueId($id)
    {
        if (isset($this->cache['issue.' . $id])) {
            $issue = $this->cache['issue.' . $id];
        } else {
            $issue = new \Model\Issue();
            $issue->load($id);
            $this->cache['issue.' . $id] = $issue;
        }
        return $issue->name;
    }

    /**
     * Convert a parent issue ID to a name
     * @param int $id
     * @param string
     */
    public function convertParentId($id)
    {
        return $this->convertIssueId($id);
    }

    /**
     * Convert a sprint ID to a name/date
     * @param int $id
     * @return string
     */
    public function convertSprintId($id)
    {
        if (isset($this->cache['sprint.' . $id])) {
            $sprint = $this->cache['sprint.' . $id];
        } else {
            $sprint = new \Model\Sprint();
            $sprint->load($id);
            $this->cache['sprint.' . $id] = $sprint;
        }
        return $sprint->name . " - " .
                date('n/j', strtotime($sprint->start_date)) . "-" .
                date('n/j', strtotime($sprint->end_date));
    }

    /**
     * Convert a sprint ID to a name/date
     * @param int $id
     * @return string
     */
    public function convertTypeId($id)
    {
        if (isset($this->cache['type.' . $id])) {
            $type = $this->cache['type.' . $id];
        } else {
            $type = new \Model\Issue\Type();
            $type->load($id);
            $this->cache['type.' . $id] = $type;
        }
        return $type->name;
    }

    /**
     * Convert MySQL datetime to formatted local time
     * @param  string $date
     * @return string
     */
    public function convertClosedDate($date)
    {
        $time = View::instance()->utc2local(strtotime($date));
        return date("D, M j, Y g:ia", $time);
    }
}
