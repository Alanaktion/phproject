<?php

namespace Model\User;

/**
 * Class Group
 *
 * @property int $id
 * @property int $user_id
 * @property int $group_id
 * @property int $manager
 */
class Group extends \Model
{
    protected $_table_name = "user_group";

    /**
     * Get complete group list for user
     */
    public static function getUserGroups(?int $user_id = null): array
    {
        $f3 = \Base::instance();
        $db = $f3->get("db.instance");

        if ($user_id === null) {
            $user_id = $f3->get("user.id");
        }

        $query_groups = "SELECT u.id, u.name, u.username
            FROM user u
            JOIN user_group g ON u.id = g.group_id
            WHERE g.user_id = :user AND u.deleted_date IS NULL ORDER BY u.name";
        return $db->exec($query_groups, [":user" => $user_id]);
    }

    /**
     * Check if a user is in a group
     */
    public static function userIsInGroup(int $group_id, ?int $user_id = null): bool
    {
        $f3 = \Base::instance();

        if ($user_id === null) {
            $user_id = $f3->get("user.id");
        }

        $group = new static();
        $group->load(['user_id = ? AND group_id = ?', $user_id, $group_id]);

        return (bool) $group->id;
    }
}
