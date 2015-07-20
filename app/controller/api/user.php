<?php

namespace Controller\Api;

class User extends \Controller\Api {

	protected function user_array(\Model\User $user) {

		$group_id = $user->id;

		if($user->role == 'group') {
			$group = new \Model\Custom("user_group");
			$man = $group->find(array("group_id = ? AND manager = 1", $user->id));
			$man = array_filter($man);

			if(!empty($man) && $man[0]->user_id > 0) {
				$group_id = $man[0]->user_id;
			}
		}

		$result = array(
				"id" =>$group_id,
				"name" => $user->name,
				"username" => $user->username,
				"email" => $user->email
			);

		return ($result);
	}

	public function single_get($f3, $params) {
		if($params["username"] == "me") {
			$user = $f3->get("user_obj");
		} else {
			$user = new \Model\User();
			$user->load(array("username = ?", $params["username"]));
		}
		if($user->id) {
			$this->_printJson($this->user_array($user));
		} else {
			$f3->error(404);
		}
	}

	public function single_email($f3, $params) {
		$user = new \Model\User();
		$user->load(array("email = ? AND deleted_date IS NULL", $params["email"]));
		if($user->id) {
			$this->_printJson($this->user_array($user));
		} else {
			$f3->error(404);
		}
	}


	// Gets a List of uers
	public function get($f3) {
		$pagLimit = $f3->get("GET.limit") ?: 30;
		if($pagLimit == -1) {
			$pagLimit = 100000;
		} elseif ($pagLimit < 0) {
			$pagLimit = 30;
		}

		$user = new \Model\User;
		$result = $user->paginate(
				$f3->get("GET.offset") / $pagLimit,
				$pagLimit,
				"deleted_date IS NULL AND role != 'group'"
			);

		$users = array();
		foreach ($result["subset"] as $user) {
		 	$users[] = $this->user_array($user);
		}

		$this->_printJson(array(
				"total_count" => $result["total"],
				"limit" => $result["limit"],
				"users" => $users,
				"offset" => $result["pos"] * $result["limit"]
			));
	}


	// Gets a list of Uers
	public function get_group($f3) {

		$pagLimit = $f3->get("GET.limit") ?: 30;

		if($pagLimit == -1) {
			$pagLimit = 100000;
		} elseif ($pagLimit < 0) {
			$pagLimit = 30;
		}

		$user = new \Model\User;
		$result = $user->paginate(
				$f3->get("GET.offset") / $pagLimit,
				$pagLimit,
				"deleted_date IS NULL AND role = 'group'"
			);

		$groups = array();
		foreach ($result["subset"] as $user) {
		 	$groups[] = $this->user_array($user);
		}

		$this->_printJson(array(
				"total_count" => $result["total"],
				"limit" => $result["limit"],
				"groups" => $groups,
				"offset" => $result["pos"] * $result["limit"]
			));

	}

}
