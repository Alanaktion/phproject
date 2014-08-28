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
						$f3->set("SESSION.phproject_user_id", $user->id);
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
			$f3->set("SESSION.phproject_user_id", $user->id);
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

	public function reset($f3, $params) {
		if($f3->get("user.id")) {
			$f3->reroute("/");
		} else {
			if($f3->get("POST.email")) {
				$user = new \Model\User;
				$user->load(array("email = ?", $f3->get("POST.email")));
				if($user->id && !$user->deleted_date) {
					$notification = \Helper\Notification::instance();
					$notification->user_reset($user->id);
					$f3->set("reset.success", "We've sent an email to " . $f3->get("POST.email") . " with a link to reset your password.");
				} else {
					$f3->set("reset.error", "No user exists with the email address " . $f3->get("POST.email") . ".");
				}
			}
			unset($user);
			echo \Template::instance()->render("index/reset.html");
		}
	}

	public function reset_complete($f3, $params) {
		if($f3->get("user.id")) {
			$f3->reroute("/");
		} else {
			$user = new \Model\User;
			$user->load(array("CONCAT(password, salt) = ?", $params["hash"]));
			if(!$user->id || !$params["hash"]) {
				$f3->set("reset.error", "Invalid reset URL.");
				echo \Template::instance()->render("index/reset.html");
				return;
			}
			if($f3->get("POST.password1")) {
				// Validate new password
				if($f3->get("POST.password1") != $f3->get("POST.password2")) {
					$f3->set("reset.error", "The given passwords don't match.");
				} elseif(strlen($f3->get("POST.password1")) < 6) {
					$f3->set("reset.error", "The given password is too short. Passwords must be at least 6 characters.");
				} else {
					// Save new password and redirect to login
					$security = \Helper\Security::instance();
					$user->salt = $security->salt();
					$user->password = $security->hash($f3->get("POST.password1"), $user->salt);
					$user->save();
					$f3->reroute("/login");
					return;
				}
			}
			$f3->set("resetuser", $user);
			echo \Template::instance()->render("index/reset_complete.html");
		}
	}

	public function logout($f3, $params) {
		$f3->clear("SESSION.phproject_user_id");
		session_destroy();
		$f3->reroute("/");
	}

	public function ping($f3, $params) {
		if($f3->get("user.id")) {
			print_json(array("user_id" => $f3->get("user.id"), "is_logged_in" => true));
		} else {
			print_json(array("user_id" => null, "is_logged_in" => false));
		}
	}

}
