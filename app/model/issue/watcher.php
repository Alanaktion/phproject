<?php

namespace Model\Issue;

class Watcher extends \Model {

	protected $_table_name = "issue_watcher";

	public function findby_watcher ($f3, $user_id = 0, $orderby = 'id') {
		$db = $f3->get("db.instance");
		return $db->exec(
			'SELECT i.* FROM issue_detail i JOIN issue_watcher w on i.id = w.issue_id  '.
			'WHERE w.user_id = :user_id AND  i.deleted_date IS NULL AND i.closed_date IS NULL AND i.status_closed = 0 '.
			'ORDER BY :orderby ',
			array(':user_id' => $user_id, ':orderby' => $orderby)
		);
	}

}

