<?php

namespace Controller;

class Index extends Base {

	public function index($f3, $params) {
		if($f3->get("user.id")) {
			$projects = new \DB\SQL\Mapper($f3->get("db.instance"), "issue_user");
			$f3->set("projects", $projects->paginate(
				0, 50,
				array(
					"owner_id=:owner and type_id=:type",
					":owner" => $f3->get("user.id"),
					":type" => "2",
				),array(
					"order" => "(due_date IS NULL), due_date ASC"
				)
			));

			$tasks = new \DB\SQL\Mapper($f3->get("db.instance"), "issue_user");
			$f3->set("tasks", $tasks->paginate(
				0, 50,
				array(
					"owner_id=:owner and type_id=:type",
					":owner" => $f3->get("user.id"),
					":type" => "1",
				),array(
					"order" => "(due_date IS NULL), due_date ASC"
				)
			));

			echo \Template::instance()->render("user/dashboard.html");
		} else {
			echo \Template::instance()->render("index/index.html");
		}
	}

	public function login($f3, $params) {
		if($f3->get("user.id")) {
			$f3->reroute("/");
		} else {
			echo \Template::instance()->render("index/login.html");
		}
	}

	public function loginpost($f3, $params) {
		$user = new \Model\User();
		$user->load(array("username=? AND deleted_date IS NULL", $f3->get("POST.username")));

		if($user->verify_password($f3->get("POST.password"))) {
			$f3->set("SESSION.user_id", $user->id);
			$f3->reroute("/");
		} else {
			$f3->set("login.error", "Invalid login information, try again.");
			echo \Template::instance()->render("index/login.html");
		}
	}

	public function logout($f3, $params) {
		$f3->clear("SESSION.user_id");
		$f3->reroute("/");
	}

}
