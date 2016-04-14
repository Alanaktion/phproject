<?php

namespace Model\Issue;

/**
 * Class Watcher
 *
 * @property int $id
 * @property int $issue_id
 * @property int $user_id
 */
class Watcher extends \Model {

	protected $_table_name = "issue_watcher";

	/**
	 * Find watched issues by user ID
	 * @param  int    $user_id
	 * @param  string $orderby
	 * @return array
	 */
	public function findby_watcher ($user_id, $orderby = 'id') {
		return $this->db->exec(
			'SELECT i.* FROM issue_detail i JOIN issue_watcher w on i.id = w.issue_id  '.
			'WHERE w.user_id = :user_id AND  i.deleted_date IS NULL AND i.closed_date IS NULL AND i.status_closed = 0 AND i.owner_id != :user_id2 '.
			'ORDER BY :orderby ',
			array(':user_id' => $user_id, ':user_id2' => $user_id, ':orderby' => $orderby)
		);
	}

}
