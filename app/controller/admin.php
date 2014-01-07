<?php

namespace Controller;

class Admin extends Base {

	public function index($f3, $params) {
		$this->_requireAdmin();
		$f3->set("title", "Administration");
		echo \Template::instance()->render("admin/index.html");
	}

	public function users($f3, $params) {
		$this->_requireAdmin();
		$f3->set("title", "Manage Users");
		$users = new \Model\User();
		$f3->set("users", $users->paginate(0, 1000));
		echo \Template::instance()->render("admin/users.html");
	}

	public function user_edit($f3, $params) {
		$this->_requireAdmin();
		$f3->set("title", "Administration");

		$user = new \Model\User;
		$user->load(array("id = ?", $params["id"]));

		if($user->id) {
			$f3->set("title", "Edit User");
			if($f3->get("POST")) {
				foreach($f3->get("POST") as $i=>$val) {
					if($user->$i != $val) {
						$user->$i = $val;
					}
					$user->save();
					$f3->set("success", "User chnages saved.");
				}
			}
			$f3->set("this_user", $user->cast());
			echo \Template::instance()->render("admin/users/edit.html");
		} else {
			$f3->error(404, "User does not exist.");
		}

	}

	public function user_new($f3, $params) {
		if($f3->get("POST")) {
			$user = new \Model\User();
			$user->username = $f3->get("POST.username");
			$user->email = $f3->get("POST.email");
			$user->name = $f3->get("POST.name");
			$user->password = \Helper\Security::instance()->bcrypt($f3->get("POST.password"));
			$user->role = $f3->get("POST.role");
			$user->task_color = ltrim($f3->get("POST.task_color"), "#");
			$user->created_date = now();
			$user->save();
			if($user->id) {
				$f3->reroute("/admin/users#" . $user->id);
			} else {
				$f3->error(500, "Failed to save user.");
			}
		} else {
			$f3->set("title", "Add User");
			$f3->set("rand_color", sprintf("#%06X", mt_rand(0, 0xFFFFFF)));
			echo \Template::instance()->render("admin/users/new.html");
		}
	}

}
