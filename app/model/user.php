<?php

namespace Model;

/**
 * Class User
 *
 * @property int $id
 * @property string $username
 * @property string $email
 * @property string $name
 * @property string $password
 * @property string $salt
 * @property string $role
 * @property int $rank
 * @property string $task_color
 * @property string $theme
 * @property string $language
 * @property string $avatar_filename
 * @property string $options
 * @property string $api_key
 * @property string $created_date
 * @property string $deleted_date
 */
class User extends \Model
{
    public const
        RANK_GUEST = 0,
    RANK_CLIENT = 1,
    RANK_USER = 2,
    RANK_MANAGER = 3,
    RANK_ADMIN = 4,
    RANK_SUPER = 5;

    protected $_table_name = "user";
    protected $_groupUsers = null;

    /**
     * Load currently logged in user, if any
     * @return mixed
     */
    public function loadCurrent()
    {
        $f3 = \Base::instance();

        // Load current session
        $session = new Session();
        $session->loadCurrent();

        // Load user
        if ($session->user_id) {
            $this->load(["id = ? AND deleted_date IS NULL", $session->user_id]);
            if ($this->id) {
                $f3->set("user", $this->cast());
                $f3->set("user_obj", $this);

                // Change default language if user has selected one
                if ($this->exists("language") && $this->language) {
                    $f3->set("LANGUAGE", $this->language);
                }
            }
        }

        return $this;
    }

    /**
     * Get path to user's avatar or gravatar
     * @param  int $size
     * @return string|bool
     */
    public function avatar(int $size = 80)
    {
        if (!$this->id) {
            return false;
        }
        if ($this->get("avatar_filename") && is_file("uploads/avatars/" . $this->get("avatar_filename"))) {
            return \Base::instance()->get('BASE') . "/avatar/$size-{$this->id}.png";
        }
        return \Helper\View::instance()->gravatar($this->get("email"), $size);
    }

    /**
     * Load all active users
     * @return array
     */
    public function getAll(): array
    {
        return $this->find("deleted_date IS NULL AND role != 'group'", ["order" => "name ASC"]);
    }

    /**
     * Load all deleted users
     * @return array
     */
    public function getAllDeleted(): array
    {
        return $this->find("deleted_date IS NOT NULL AND role != 'group'", ["order" => "name ASC"]);
    }

    /**
     * Load all active groups
     * @return array
     */
    public function getAllGroups(): array
    {
        return $this->find("deleted_date IS NULL AND role = 'group'", ["order" => "name ASC"]);
    }

    /**
     * Get all users within a group
     * @return array|NULL
     */
    public function getGroupUsers()
    {
        if ($this->role == "group") {
            if ($this->_groupUsers !== null) {
                return $this->_groupUsers;
            }
            $ug = new User\Group();
            /** @var User\Group[] $users */
            $users = $ug->find(["group_id = ?", $this->id]);
            $userIds = [];
            foreach ($users as $user) {
                $userIds[] = $user->user_id;
            }
            return $this->_groupUsers = $userIds ? $this->find("id IN (" . implode(",", $userIds) . ") AND deleted_date IS NULL") : [];
        } else {
            return null;
        }
    }

    /**
     * Get array of IDs of users within a group
     * @return array|NULL
     */
    public function getGroupUserIds()
    {
        if ($this->role == "group") {
            if ($this->_groupUsers === null) {
                $this->getGroupUsers();
            }
            $ids = [];
            foreach ($this->_groupUsers as $u) {
                $ids[] = $u->id;
            }
            return $ids;
        } else {
            return null;
        }
    }

    /**
     * Get all user IDs in a group with a user, and all group IDs the user is in
     * @return array
     */
    public function getSharedGroupUserIds(): array
    {
        $groupModel = new \Model\User\Group();
        $groups = $groupModel->find(["user_id = ?", $this->id]);
        $groupIds = [];
        foreach ($groups as $g) {
            $groupIds[] = $g["group_id"];
        }
        $ids = $groupIds;
        if ($groupIds) {
            $groupIdString = implode(",", $groupIds);
            $users = $groupModel->find("group_id IN ({$groupIdString})", ["group" => "id,user_id"]);
            foreach ($users as $u) {
                $ids[] = $u->user_id;
            }
        }
        if (!count($ids)) {
            return [$this->id];
        }
        return $ids;
    }
    /**
     * Get all user options
     * @return array
     */
    public function options(): array
    {
        return $this->options ? json_decode($this->options, true, 512, JSON_THROW_ON_ERROR) : [];
    }

    /**
     * Get or set a user option
     * @param  string $key
     * @param  mixed  $value
     * @return mixed
     */
    public function option(string $key, $value = null)
    {
        $options = $this->options();
        if ($value === null) {
            return $options[$key] ?? null;
        }
        $options[$key] = $value;
        $this->options = json_encode($options, JSON_THROW_ON_ERROR);
        return $this;
    }

    /**
     * Send an email alert with issues due on the given date
     * @param  string $date
     * @return bool
     */
    public function sendDueAlert(string $date = ''): bool
    {
        if (!$this->id) {
            return false;
        }

        if (!$date) {
            $date = date("Y-m-d", \Helper\View::instance()->utc2local());
        }

        // Get group owner IDs
        $ownerIds = [$this->id];
        $groups = new \Model\User\Group();
        foreach ($groups->find(["user_id = ?", $this->id]) as $r) {
            $ownerIds[] = $r->group_id;
        }
        $ownerStr = implode(",", $ownerIds);

        // Find issues assigned to user or user's group
        $issue = new Issue();
        $due = $issue->find(["due_date = ? AND owner_id IN($ownerStr) AND closed_date IS NULL AND deleted_date IS NULL", $date], ["order" => "priority DESC"]);
        $overdue = $issue->find(["due_date < ? AND owner_id IN($ownerStr) AND closed_date IS NULL AND deleted_date IS NULL", $date], ["order" => "priority DESC"]);

        if ($due || $overdue) {
            $notif = new \Helper\Notification();
            return $notif->user_due_issues($this, $due, $overdue);
        } else {
            return false;
        }
    }

    /**
     * Get user statistics
     * @param  int $time  The lower limit on timestamps for stats collection
     * @return array
     */
    public function stats(int $time = 0): array
    {
        $offset = \Helper\View::instance()->timeoffset();

        if (!$time) {
            $time = strtotime("-2 weeks", time() + $offset);
        }

        $result = [];
        $result["spent"] = $this->db->exec(
            "SELECT DATE(DATE_ADD(u.created_date, INTERVAL :offset SECOND)) AS `date`, SUM(f.new_value - f.old_value) AS `val`
            FROM issue_update u
            JOIN issue_update_field f ON u.id = f.issue_update_id AND f.field = 'hours_spent'
            WHERE u.user_id = :user AND u.created_date > :date
            GROUP BY `date`",
            [":user" => $this->id, ":offset" => $offset, ":date" => date("Y-m-d H:i:s", $time)]
        );
        $result["closed"] = $this->db->exec(
            "SELECT DATE(DATE_ADD(i.closed_date, INTERVAL :offset SECOND)) AS `date`, COUNT(*) AS `val`
            FROM issue i
            WHERE i.owner_id = :user AND i.closed_date > :date
            GROUP BY `date`",
            [":user" => $this->id, ":offset" => $offset, ":date" => date("Y-m-d H:i:s", $time)]
        );
        $result["created"] = $this->db->exec(
            "SELECT DATE(DATE_ADD(i.created_date, INTERVAL :offset SECOND)) AS `date`, COUNT(*) AS `val`
            FROM issue i
            WHERE i.author_id = :user AND i.created_date > :date
            GROUP BY `date`",
            [":user" => $this->id, ":offset" => $offset, ":date" => date("Y-m-d H:i:s", $time)]
        );

        $dates = $this->_createDateRangeArray(date("Y-m-d", $time), date("Y-m-d", time() + $offset));
        $return = [
            "labels" => [],
            "spent" => [],
            "closed" => [],
            "created" => [],
        ];

        foreach ($result["spent"] as $r) {
            $return["spent"][$r["date"]] = floatval($r["val"]);
        }
        foreach ($result["closed"] as $r) {
            $return["closed"][$r["date"]] = intval($r["val"]);
        }
        foreach ($result["created"] as $r) {
            $return["created"][$r["date"]] = intval($r["val"]);
        }

        foreach ($dates as $date) {
            $return["labels"][$date] = date("D j", strtotime($date));
            if (!isset($return["spent"][$date])) {
                $return["spent"][$date] = 0;
            }
            if (!isset($return["closed"][$date])) {
                $return["closed"][$date] = 0;
            }
            if (!isset($return["created"][$date])) {
                $return["created"][$date] = 0;
            }
        }

        foreach ($return as &$r) {
            ksort($r);
        }

        return $return;
    }

    /**
     * Reassign open assigned issues
     * @param  int|null $userId
     * @return int Number of issues affected
     * @throws \Exception
     */
    public function reassignIssues(?int $userId): int
    {
        if (!$this->id) {
            throw new \Exception("User is not initialized.");
        }
        $issueModel = new Issue();
        $issues = $issueModel->find(["owner_id = ? AND deleted_date IS NULL AND closed_date IS NULL", $this->id]);
        foreach ($issues as $issue) {
            $issue->owner_id = $userId;
            $issue->save();
        }
        return count($issues);
    }

    public function date_picker()
    {
        $lang = $this->language ?: \Base::instance()->get("LANGUAGE");
        $lang = explode(',', $lang, 2)[0];
        return (object)["language" => $lang, "js" => ($lang != "en")];
    }

    /**
     * Generate a password reset token and store hashed value
     * @return string
     */
    public function generateResetToken(): string
    {
        $random = random_bytes(512);
        $token = hash("sha384", $random) . time();
        $this->reset_token = hash("sha384", $token);
        return $token;
    }

    /**
     * Validate a plaintext password reset token
     * @param  string $token
     * @return bool
     */
    public function validateResetToken(string $token): bool
    {
        $ttl = \Base::instance()->get("security.reset_ttl");
        $timestampValid = substr($token, 96) > (time() - $ttl);
        $tokenValid = hash("sha384", $token) == $this->reset_token;
        return $timestampValid && $tokenValid;
    }
}
