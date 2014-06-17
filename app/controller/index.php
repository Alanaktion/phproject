<?php

namespace Controller;

class Index extends Base {

	public function index($f3, $params) {
		if($f3->get("user.id")) {
			$user_controller = new \Controller\User();
			return $user_controller->dashboard($f3, $params);
		} else {
			if($f3->get("site.public")) {
				echo \Template::instance()->render("index/index.html");
			} else {
				if($f3->get("site.demo") && is_numeric($f3->get("site.demo"))) {
					$user = new \Model\User();
					$user->load($f3->get("site.demo"));
					if($user->id) {
						$f3->set("SESSION.user_id", $user->id);
						$f3->reroute("/");
						return;
					} else {
						$f3->set("error", "Auto-login failed, demo user was not found.");
					}
				}
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
			if($f3->exists("GET.demo")) {
				$f3->set("login.error", 'Log in with username and password "demo".');
			}
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

}
