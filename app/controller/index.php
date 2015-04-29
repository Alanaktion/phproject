<?php

namespace Controller;

class Index extends \Controller {

	public function index($f3, $params) {
		if($f3->get("user.id")) {
			$user_controller = new \Controller\User();
			return $user_controller->dashboard($f3, $params);
		} else {
			if($f3->get("site.public_access")) {
				$this->_render("index/index.html");
			} else {
				if($f3->get("site.demo") && is_numeric($f3->get("site.demo"))) {
					$user = new \Model\User();
					$user->load($f3->get("site.demo"));
					if($user->id) {
						$session = new \Model\Session($user->id);
						$session->setCurrent();
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

	public function login($f3) {
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
			$this->_render("index/login.html");
		}
	}

	public function loginpost($f3) {
		$user = new \Model\User();

		// Load user by username or email address
		if(strpos($f3->get("POST.username"), "@")) {
			$user->load(array("email=? AND deleted_date IS NULL", $f3->get("POST.username")));
		} else {
			$user->load(array("username=? AND deleted_date IS NULL", $f3->get("POST.username")));
		}

		// Verify password
		$security = \Helper\Security::instance();
		if($security->hash($f3->get("POST.password"), $user->salt ?: "") == $user->password) {

			// Create a session and use it
			$session = new \Model\Session($user->id);
			$session->setCurrent();

			if($user->salt) {
				if(!$f3->get("POST.to")) {
					$f3->reroute("/");
				} else {
					$f3->reroute($f3->get("POST.to"));
				}
			} else {
				$f3->set("user", $user->cast());
				$this->_render("index/reset_forced.html");
			}

		} else {
			if($f3->get("POST.to")) {
				$f3->set("to", $f3->get("POST.to"));
			}
			$f3->set("login.error", "Invalid login information, try again.");
			$this->_render("index/login.html");
		}
	}

	public function registerpost($f3) {

		// Exit immediately if public registrations are disabled
		if(!$f3->get("site.public_registration")) {
			$f3->error(400);
			return;
		}

		$errors = array();
		$user = new \Model\User;

		// Check for existing users
		$user->load(array("email=?", $f3->get("POST.register-email")));
		if($user->id) {
			$errors[] = "A user already exists with this email address.";
		}
		$user->load(array("username=?", $f3->get("POST.register-username")));
		if($user->id) {
			$errors[] = "A user already exists with this username.";
		}

		// Validate user data
		if(!$f3->get("POST.register-name")) {
			$errors[] = "Name is required";
		}
		if(!preg_match("/^[0-9a-z]{4,}$/i", $f3->get("POST.register-username"))) {
			$errors[] = "Usernames must be at least 4 characters and can only contain letters and numbers.";
		}
		if(!filter_var($f3->get("POST.register-email"), FILTER_VALIDATE_EMAIL)) {
			$errors[] = "A valid email address is required.";
		}
		if(strlen($f3->get("POST.register-password")) < 6) {
			$errors[] = "Password must be at least 6 characters.";
		}

		// Show errors or create new user
		if($errors) {
			$f3->set("register.error", implode("<br>", $errors));
			$this->_render("index/login.html");
		} else {
			$user->reset();
			$user->username = trim($f3->get("POST.register-username"));
			$user->email = trim($f3->get("POST.register-email"));
			$user->name = trim($f3->get("POST.register-name"));
			$security = \Helper\Security::instance();
			extract($security->hash($f3->get("POST.register-password")));
			$user->password = $hash;
			$user->salt = $salt;
			$user->task_color = sprintf("#%02X%02X%02X", mt_rand(0, 0xFF), mt_rand(0, 0xFF), mt_rand(0, 0xFF));
			$user->save();

			// Create a session and use it
			$session = new \Model\Session($user->id);
			$session->setCurrent();

			$f3->reroute("/");
		}
	}

	public function reset($f3) {
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
			$this->_render("index/reset.html");
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
				$this->_render("index/reset.html");
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
			$this->_render("index/reset_complete.html");
		}
	}

	public function reset_forced($f3) {
		$user = new \Model\User;
		$user->loadCurrent();

		if($f3->get("POST.password1") != $f3->get("POST.password2")) {
			$f3->set("reset.error", "The given passwords don't match.");
		} elseif(strlen($f3->get("POST.password1")) < 6) {
			$f3->set("reset.error", "The given password is too short. Passwords must be at least 6 characters.");
		} else {
			// Save new password and redirect to dashboard
			$security = \Helper\Security::instance();
			$user->salt = $security->salt();
			$user->password = $security->hash($f3->get("POST.password1"), $user->salt);
			$user->save();
			$f3->reroute("/");
			return;
		}
		$this->_render("index/reset_forced.html");
	}

	public function logout($f3) {
		$session = new \Model\Session;
		$session->loadCurrent();
		$session->delete();
		$f3->reroute("/");
	}

	public function ping($f3) {
		if($f3->get("user.id")) {
			$this->_printJson(array("user_id" => $f3->get("user.id"), "is_logged_in" => true));
		} else {
			$this->_printJson(array("user_id" => null, "is_logged_in" => false));
		}
	}

	public function atom($f3) {
		// Authenticate user
		if($f3->get("GET.key")) {
			$user = new \Model\User;
			$user->load(array("api_key = ?", $f3->get("GET.key")));
			if(!$user->id) {
				$f3->error(403);
				return;
			}
		} else {
			$f3->error(403);
			return;
		}

		// Get requested array substituting defaults
		$get = $f3->get("GET") + array("type" => "assigned", "user" => $user->username);
		unset($user);

		// Load target user
		$user = new \Model\User;
		$user->load(array("username = ?", $get["user"]));
		if(!$user->id) {
			$f3->error(404);
			return;
		}

		// Load issues
		$issue = new \Model\Issue\Detail;
		$options = array("order" => "created_date DESC");
		if($get["type"] == "assigned") {
			$issues = $issue->find(array("author_id = ? AND status_closed = 0 AND deleted_date IS NULL", $user->id), $options);
		} elseif($get["type"] == "created") {
			$issues = $issue->find(array("owner = ? AND status_closed = 0 AND deleted_date IS NULL", $user->id), $options);
		} elseif($get["type"] == "all") {
			$issues = $issue->find("status_closed = 0 AND deleted_date IS NULL", $options + array("limit" => 50));
		} else {
			$f3->error(400, "Invalid feed type");
			return;
		}

		// Render feed
		$f3->set("get", $get);
		$f3->set("feed_user", $user);
		$f3->set("issues", $issues);
		$this->_render("index/atom.xml", "application/atom+xml");
	}

}
