<?php

namespace Controller;

class Index extends Base {

	public function index($f3, $params) {
		if($f3->get("user.id")) {
			$projects = new \DB\SQL\Mapper($f3->get("db.instance"), "issues_user_data");
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

			$tasks = new \DB\SQL\Mapper($f3->get("db.instance"), "issues_user_data");
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

			echo \Template::instance()->render("dashboard.html");
		} else {
			echo \Template::instance()->render("index.html");
		}
	}

	public function login($f3, $params) {
		if($f3->get("user.id")) {
			$f3->reroute("/");
		} else {
			echo \Template::instance()->render("login.html");
		}
	}

	public function loginpost($f3, $params) {
		$user = new \Model\User();
		$user->load(array("username=?",$f3->get("POST.username")));

		if($user->verify_password($f3->get("POST.password"))) {
			$f3->set("SESSION.user_id", $user->id);
			$f3->reroute("/");
		} else {
			$f3->set("login.error", "Invalid login information, try again.");
			echo \Template::instance()->render("login.html");
		}
	}

	public function logout($f3, $params) {
		$f3->clear("SESSION.user_id");
		$f3->reroute("/");
	}

}
