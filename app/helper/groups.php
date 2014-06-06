<?php

namespace Helper;

class Groups extends \Prefab {

	public function getAll() {
		$group_model = new \Model\User();
		$groups_result =  $group_model->find(array("role = 'group' AND (deleted_date IS NULL OR deleted_date = '0000-00-00 00:00:00')"));
		return $groups_result;
	}

}
