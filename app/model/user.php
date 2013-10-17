<?php

namespace Model;

class User extends Base {

	protected $_table_name = "users";

	public function save() {
		echo "User record saved.";
		parent::save();
	}

	public function verify_password($password) {
		if($this->dry() || empty($this->password)) {
			return false;
		}
		return \Helper\Security::bcrypt_verify($this->password, $password);
	}

}

