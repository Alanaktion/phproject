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
		$f3->set("title", "Administration");
		echo \Template::instance()->render("admin/users.html");
	}

	public function user_edit($f3, $params) {
		$this->_requireAdmin();
		$f3->set("title", "Administration");

		$user = new \Model\User;
		$user->load(array("id = ?", $params["id"]));

		if($user->id) {
			$f3->set("this_user", $user->cast());
			echo \Template::instance()->render("admin/users/edit.html");
		} else {
			$f3->error(404, "User does not exist.");
		}

	}

}
