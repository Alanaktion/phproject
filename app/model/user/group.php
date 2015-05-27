<?php

namespace Model\User;

class Group extends \Model {

	protected $_table_name = "user_group";

	/**
	 * Get complete group list for user
	 * @return array
	 */
	public function getUserGroups($user_id = 0) {
		$f3 = \Base::instance();
		$db = $f3->get("db.instance");

		if(empty($user_id)) {
			$user_id =  $f3->get("user.id");
		}

		$query_groups = "SELECT u.id, u.name, u.username
			FROM user u
			JOIN user_group g ON u.id = g.group_id
			WHERE g.user_id = :user AND u.deleted_date IS NULL ORDER BY u.name";

		$result = $db->exec($query_groups, array(":user" => $user_id));
		return $result;

	}

}

