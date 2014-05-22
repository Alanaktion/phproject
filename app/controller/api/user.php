<?php

namespace Controller\Api;

class User extends \Controller\Api\Base {


protected $_userID;

public function __construct(){

	$this->_userID = $this->_requireAuth();
}

protected function user_array(\Model\User $user){

	$group_id = $user->id;

	 if($user->role == 'group')
	 {
	 	
	 	$group = new \Model\Custom("user_group");
	 	$man = $group->find(array("group_id = ? AND manager = 1", $user->id));

	 	if($man[0]->user_id > 0)
	 	{//echo $man[0]->manager;
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

public function single_get($f3, $params){

$user = new \Model\User();
//echo $params["username"];
$user->load($params["username"]);
//;echo $user->username;
if($user->id) {
 			print_json(array("User" => $this->user_array($user)));
 		} else {
 			$f3->error(404);
 		}
}


//Gets a List of uers
public function get($f3, $params){

	$user = new \Model\User;
	$result = $user->paginate(
			$f3->get("GET.offset") / ($f3->get("GET.limit") ?: 30),
			$f3->get("GET.limit") ?: 30,
			"deleted_date IS NULL AND role != 'group'"
		);


	$users = array();
	foreach ($result["subset"] as $user) {
	 	//echo "hello";
	 	$users[] = $this->user_array($user);
	// 	# code...
	}

	print_json(array(
			"total_count" => $result["total"],
			"limit" => $result["limit"],
			"users" => $users,
			"offset" => $result["pos"] * $result["limit"]
		));
}


//Gets a list of Uers
public function get_group($f3, $params){

	$user = new \Model\User;
	$result = $user->paginate(
			$f3->get("GET.offset") / ($f3->get("GET.limit") ?: 30),
			$f3->get("GET.limit") ?: 30,
			"deleted_date IS NULL AND role = 'group'"
		);

	$groups = array();
	foreach ($result["subset"] as $user) {
	 	//echo "hello";
	 	$groups[] = $this->user_array($user);
	// 	# code...
	}


	print_json(array(
			"total_count" => $result["total"],
			"limit" => $result["limit"],
			"groups" => $groups,
			"offset" => $result["pos"] * $result["limit"]
		));
	


}
}


