<?php

namespace Controller;

class User extends Base {

	public function index($f3, $params) {
		$this->_requireLogin();
		$f3->reroute("/user/account");
	}

	public function dashboard($f3, $params) {
		$this->_requireLogin();
		$f3->reroute("/");
	}

	public function account($f3, $params) {
		$this->_requireLogin();
		echo \Template::instance()->render("user/account.html");
	}

	public function single($f3, $params) {
		$this->_requireLogin();

		$user = new \Model\User;
		$user->load(array("username = ?", $params["username"]));

		if($user->id) {
			$f3->set("this_user", $user->cast());
			echo \Template::instance()->render("user/single.html");
		} else {
			$f3->error(404);
		}

	}

}
