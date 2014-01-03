<?php

namespace Model;

class User extends Base {

	protected $_table_name = "user";

	// Load currently logged in user, if any
	public function loadCurrent() {
		$f3 = \Base::instance();
		if($user_id = $f3->get("SESSION.user_id")) {
			$this->load(array("id=?", $user_id));
			if($this->id) {
				$f3->set("user", $this->cast());
			}
		}
		return $this;
	}

	public function verify_password($password) {
		if($this->dry() || empty($this->password)) {
			return false;
		}
		$security = \Helper\Security::instance();
		return $security->bcrypt_verify($this->password, $password);
	}

}

