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

	public function save($f3, $params) {
		$id = $this->_requireLogin();

		$f3 = \Base::instance();
		$post = array_map("trim", $f3->get("POST"));

		$user = new \Model\User();
		$user->load(array("id = ?", $id));

		if(!empty($post["old_pass"])) {

			$security = \Helper\Security::instance();

			// Update password
			if($security->bcrypt_verify($user->password, $post["old_pass"])) {
				if(strlen($post["new_pass"]) >= 6) {
					$user->password = $security->bcrypt($post["new_pass"]);
					$f3->set("success", "Password updated successfully.");
				} else {
					$f3->set("error", "New password must be at least 6 characters.");
				}
			} else {
				$f3->set("error", "Current password entered is not valid.");
			}

		} else {

			// Update profile
			if(!empty($post["name"])) {
				$user->name = filter_var($post["name"], FILTER_SANITIZE_STRING);
			} else {
				$error = "Please enter a name.";
			}
			if(filter_var($post["email"], FILTER_VALIDATE_EMAIL)) {
				$user->email = $post["email"];
			} else {
				$error = "Please enter a valid email address.";
			}

			if(empty($error)) {
				$f3->set("success", "Profile updated successfully.");
			} else {
				$f3->set("error", $error);
			}

		}

		$user->save();
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
