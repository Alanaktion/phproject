<?php

namespace Helper;

class Update
{
    use \F3\Prefab;

    protected $cache = [];

    /**
     * Generate human-readable data for issue updates
     */
    public function humanReadableValues(string $field, string|int $old_val, string|int $new_val): array
    {
        $f3 = \F3\Base::instance();

        // Generate human readable values
        $func = $f3->camelcase("convert_{$field}");
        if (is_callable([$this, $func])) {
            if ($old_val !== '') {
                $old_val = call_user_func_array(
                    [$this, $func],
                    [$old_val]
                );
            }

            if ($new_val !== '') {
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
     */
    public function convertUserId(int $id): string
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
     */
    public function convertOwnerId(int $id): string
    {
        return $this->convertUserId($id);
    }

    /**
     * Convert an author user ID to a name
     */
    public function convertAuthorId(int $id): string
    {
        return $this->convertUserId($id);
    }

    /**
     * Convert a status ID to a name
     */
    public function convertStatus(int $id): string
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
     */
    public function convertPriority(int $value): string
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
     */
    public function convertIssueId(int $id): string
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
     */
    public function convertParentId(int $id): string
    {
        return $this->convertIssueId($id);
    }

    /**
     * Convert a sprint ID to a name/date
     */
    public function convertSprintId(int $id): string
    {
        if (isset($this->cache['sprint.' . $id])) {
            $sprint = $this->cache['sprint.' . $id];
        } else {
            $sprint = new \Model\Sprint();
            $sprint->load($id);
            $this->cache['sprint.' . $id] = $sprint;
        }

        return $sprint->name . " - " .
                date('n/j', strtotime((string) $sprint->start_date)) . "-" .
                date('n/j', strtotime((string) $sprint->end_date));
    }

    /**
     * Convert a sprint ID to a name/date
     */
    public function convertTypeId(int $id): string
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
     */
    public function convertClosedDate(string $date): string
    {
        $time = View::instance()->utc2local(strtotime($date));
        return date("D, M j, Y g:ia", $time);
    }
}
