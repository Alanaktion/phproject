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
		$f3->set("title", "My Account");
		echo \Template::instance()->render("user/account.html");
	}

	public function save($f3, $params) {
		$id = $this->_requireLogin();

		$f3 = \Base::instance();
		$post = array_map("trim", $f3->get("POST"));

		$user = new \Model\User();
		$user->load($id);

		if(!empty($post["old_pass"])) {

			$security = \Helper\Security::instance();

			// Update password
			if($security->hash($post["old_pass"], $user->salt) == $user->password) {
				if(strlen($post["new_pass"]) >= 6) {
					$user->salt = $security->salt();
					$user->password = $security->hash($post["new_pass"], $user->salt);
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
			if(empty($error) && ctype_xdigit(ltrim($post["task_color"], "#"))) {
				$user->task_color = ltrim($post["task_color"], "#");
			} else {
				$error = "Please enter a valid 6-hexit color code.";
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

	public function avatar($f3, $params) {
		$id = $this->_requireLogin();
		$f3 = \Base::instance();
		$post = array_map("trim", $f3->get("POST"));

		$user = new \Model\User();
		$user->load($id);
		if(!$user->id) {
			$f3->error(404);
			return;
		}

		$f3->set("user", $user->cast());

		$web = \Web::instance();


		$f3->set("UPLOADS",'uploads/avatars/'); // don't forget to set an Upload directory, and make it writable!
		if(!is_dir($f3->get("UPLOADS"))) {
			mkdir($f3->get("UPLOADS"), 0777, true);
		}
		$overwrite = false; // set to true, to overwrite an existing file; Default: false
		$slug = true; // rename file to filesystem-friendly version

		//Make a good name
		$parts = pathinfo($_FILES['avatar']['name']);
		$_FILES['avatar']['name'] = $user->id . "-" . substr(sha1($user->id), 0, 4)  . "." . $parts["extension"];
		$f3->set("avatar_filename", $_FILES['avatar']['name']);

		$files = $web->receive(function($file) {
			$f3 = \Base::instance();
			$user = $f3->get("user");
			//var_dump($file);
			/* looks like:
				array(5) {
					["name"] =>     string(19) "somefile.png"
					["type"] =>     string(9) "image/png"
					["tmp_name"] => string(14) "/tmp/php2YS85Q"
					["error"] =>    int(0)
					["size"] =>     int(172245)
				}
			*/
			// $file['name'] already contains the slugged name now

			// maybe you want to check the file size
			if($file['size'] > $f3->get("files.maxsize"))
				return false; // this file is not valid, return false will skip moving it


			$newfile = new \Model\User();
						$newfile->load($user["id"]);

						$newfile->avatar_filename = $f3->get("avatar_filename");
			$newfile->save();

			//NEED TO CONVERT TO JPG AND RESIZE?
			return true; // allows the file to be moved from php tmp dir to your defined upload dir
		},
			$overwrite,
			$slug
		);
				$f3->reroute("/user/account");

	}


	public function single($f3, $params) {
		$this->_requireLogin();

		$user = new \Model\User;
		$user->load(array("username = ? AND deleted_date IS NULL", $params["username"]));

		if($user->id) {
			$f3->set("title", $user->name);
			$f3->set("this_user", $user->cast());
			echo \Template::instance()->render("user/single.html");
		} else {
			$f3->error(404);
		}
	}

}
