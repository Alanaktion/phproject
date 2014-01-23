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
					":type" => $f3->get("issue_type.project"),
				),array(
					"order" => "(due_date IS NULL), due_date ASC"
				)
			));

			$bugs = new \DB\SQL\Mapper($f3->get("db.instance"), "issue_user");
			$f3->set("bugs", $bugs->paginate(
				0, 50,
				array(
					"owner_id=:owner and type_id=:type",
					":owner" => $f3->get("user.id"),
					":type" => $f3->get("issue_type.bug"),
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
					":type" => $f3->get("issue_type.task"),
				),array(
					"order" => "(due_date IS NULL), due_date ASC"
				)
			));

			echo \Template::instance()->render("user/dashboard.html");
		} else {
			if($f3->get("site.public")) {
				echo \Template::instance()->render("index/index.html");
			} else {
				$f3->reroute("/login");
			}
		}
	}

	public function login($f3, $params) {
		if($f3->get("user.id")) {
			if(!$f3->get("GET.to")) {
				$f3->reroute("/");
			} else {
				$f3->reroute($f3->get("GET.to"));
			}
		} else {
			if($f3->get("GET.to")) {
				$f3->set("to", $f3->get("GET.to"));
			}
			echo \Template::instance()->render("index/login.html");
		}
	}

	public function loginpost($f3, $params) {
		$user = new \Model\User();

		// Load user by username or email address
		if(strpos($f3->get("POST.username"), "@")) {
			$user->load(array("email=? AND deleted_date IS NULL", $f3->get("POST.username")));
		} else {
			$user->load(array("username=? AND deleted_date IS NULL", $f3->get("POST.username")));
		}

		// Verify password
		$security = \Helper\Security::instance();
		if($security->hash($f3->get("POST.password"), $user->salt) == $user->password) {
			$f3->set("SESSION.user_id", $user->id);
			if(!$f3->get("POST.to")) {
				$f3->reroute("/");
			} else {
				$f3->reroute($f3->get("POST.to"));
			}
		} else {
			if($f3->get("POST.to")) {
				$f3->set("to", $f3->get("POST.to"));
			}
			$f3->set("login.error", "Invalid login information, try again.");
			echo \Template::instance()->render("index/login.html");
		}
	}

	public function logout($f3, $params) {
		$f3->clear("SESSION.user_id");
		$f3->reroute("/");
	}



	public function mailtest($f3, $params) {
		// Send a test message
		$f3 = \Base::instance();
		$smtp = new \SMTP($f3->get("smtp.host"), $f3->get("smtp.port"), $f3->get("smtp.scheme"), $f3->get("smtp.user"), $f3->get("smtp.pass"));
		$smtp->set("Subject", "This is a test message. You can probably ignore it.");
		$smtp->set("From", $f3->get("mail.from"));
		$smtp->set("Reply-to", $f3->get("mail.from"));
		//$smtp->set("Content-type", "text/html");
		$smtp->set("To", "ahardman@shelfreliance.com");
		$smtp->send("This is a test message. Why isn't it working? No idea.");
		echo '<pre>'.$smtp->log().'</pre>';
	}

}
